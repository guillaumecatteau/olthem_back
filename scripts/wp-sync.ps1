param(
    [ValidateSet('push', 'pull')]
    [string]$Mode,
    [string]$Branch = 'main',
    [switch]$NoCommit
)

Set-StrictMode -Version 3
$ErrorActionPreference = 'Stop'
if (Get-Variable -Name PSNativeCommandUseErrorActionPreference -ErrorAction SilentlyContinue) {
    $PSNativeCommandUseErrorActionPreference = $false
}

function Write-Step {
    param([string]$Message)
    Write-Host "[wp-sync] $Message" -ForegroundColor Cyan
}

function Assert-CommandSuccess {
    param([int]$ExitCode, [string]$Action)
    if ($ExitCode -ne 0) {
        throw "$Action failed with exit code $ExitCode"
    }
}

function ConvertTo-CliArgument {
    param([string]$Arg)
    if ($Arg -match '[\s"]') {
        return '"' + ($Arg -replace '"', '\\"') + '"'
    }
    return $Arg
}

function Invoke-Native {
    param(
        [string]$Exe,
        [string[]]$Args,
        [string]$StdInText
    )

    $psi = New-Object System.Diagnostics.ProcessStartInfo
    $psi.FileName = $Exe
    $psi.Arguments = (($Args | ForEach-Object { ConvertTo-CliArgument -Arg $_ }) -join ' ')
    $psi.RedirectStandardOutput = $true
    $psi.RedirectStandardError = $true
    $psi.RedirectStandardInput = $true
    $psi.UseShellExecute = $false
    $psi.CreateNoWindow = $true

    $proc = New-Object System.Diagnostics.Process
    $proc.StartInfo = $psi
    [void]$proc.Start()

    if ($null -ne $StdInText) {
        $proc.StandardInput.Write($StdInText)
    }
    $proc.StandardInput.Close()

    $stdout = $proc.StandardOutput.ReadToEnd()
    $stderr = $proc.StandardError.ReadToEnd()
    $proc.WaitForExit()

    return [pscustomobject]@{
        ExitCode = $proc.ExitCode
        StdOut = $stdout
        StdErr = $stderr
    }
}

function Resolve-ToolPath {
    param([string]$ExeName)

    $cmd = Get-Command $ExeName -ErrorAction SilentlyContinue
    if ($cmd) {
        return $cmd.Source
    }

    $roots = @(
        "$env:LOCALAPPDATA\Programs\Local",
        "$env:APPDATA\Local",
        "$env:LOCALAPPDATA\Local"
    )

    $matches = @()
    foreach ($root in $roots) {
        if (Test-Path $root) {
            $matches += Get-ChildItem -Path $root -Recurse -File -Filter $ExeName -ErrorAction SilentlyContinue
        }
    }

    if (-not $matches -or $matches.Count -eq 0) {
        throw "Unable to find $ExeName. Install it or add it to PATH."
    }

    $preferred = $matches | Where-Object { $_.FullName -like '*win64*' } | Select-Object -First 1
    if ($preferred) {
        return $preferred.FullName
    }

    return ($matches | Select-Object -First 1).FullName
}

function Get-WpDbConfig {
    param([string]$WpConfigPath)

    if (-not (Test-Path $WpConfigPath)) {
        throw "wp-config.php not found at $WpConfigPath"
    }

    $raw = Get-Content -Path $WpConfigPath -Raw

    function Get-DefineValue {
        param([string]$Key)
        $pattern = "define\(\s*'$Key'\s*,\s*'([^']*)'\s*\)"
        $match = [regex]::Match($raw, $pattern)
        if (-not $match.Success) {
            throw "Unable to parse $Key from wp-config.php"
        }
        return $match.Groups[1].Value
    }

    $hostRaw = Get-DefineValue -Key 'DB_HOST'
    $dbHost = $hostRaw
    $port = '3306'

    if ($hostRaw -match '^([^:]+):(\d+)$') {
        $dbHost = $matches[1]
        $port = $matches[2]
    }

    return [pscustomobject]@{
        Name = Get-DefineValue -Key 'DB_NAME'
        User = Get-DefineValue -Key 'DB_USER'
        Password = Get-DefineValue -Key 'DB_PASSWORD'
        Host = $dbHost
        Port = $port
    }
}

function Get-LocalMysqlCandidates {
    $candidates = @()

    $procs = Get-CimInstance Win32_Process -Filter "name = 'mysqld.exe'" -ErrorAction SilentlyContinue
    foreach ($proc in $procs) {
        if (-not $proc.CommandLine) {
            continue
        }

        $m = [regex]::Match($proc.CommandLine, '--defaults-file=([^\s]+)')
        if (-not $m.Success) {
            continue
        }

        $cnfPath = $m.Groups[1].Value -replace '/', '\\'
        if (-not (Test-Path $cnfPath)) {
            continue
        }

        $cnfRaw = Get-Content -Path $cnfPath -Raw
        $portMatch = [regex]::Match($cnfRaw, '(?m)^\s*port\s*=\s*(\d+)\s*$')
        $hostMatch = [regex]::Match($cnfRaw, '(?m)^\s*host\s*=\s*([^\r\n]+)\s*$')

        if ($portMatch.Success) {
            $portValue = $portMatch.Groups[1].Value
            if ($hostMatch.Success) {
                $hostValue = $hostMatch.Groups[1].Value.Trim()
                $candidates += [pscustomobject]@{ Host = $hostValue; Port = $portValue }
            }

            # IPv4 localhost fallback for machines where ::1 is not reachable.
            $candidates += [pscustomobject]@{ Host = '127.0.0.1'; Port = $portValue }
        }
    }

    return $candidates
}

function Test-DbEndpoint {
    param(
        [pscustomobject]$Db,
        [string]$DbHost,
        [string]$Port,
        [string]$MysqlExe
    )

    $query = "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$($Db.Name)';"
    $args = @(
        "--host=$DbHost",
        "--port=$Port",
        "--user=$($Db.User)",
        '--batch',
        '--skip-column-names',
        "--execute=$query"
    )

    $oldPwd = $env:MYSQL_PWD
    $env:MYSQL_PWD = $Db.Password
    $result = Invoke-Native -Exe $MysqlExe -Args $args
    if ($null -ne $oldPwd) {
        $env:MYSQL_PWD = $oldPwd
    } else {
        Remove-Item Env:\MYSQL_PWD -ErrorAction SilentlyContinue
    }

    if ($result.ExitCode -ne 0) {
        return $false
    }

    $clean = @($result.StdOut -split "`r?`n") | Where-Object { $_ -and ($_ -ne '') }

    return ($clean -contains $Db.Name)
}

function Resolve-DbEndpoint {
    param([pscustomobject]$Db)

    $mysql = Resolve-ToolPath -ExeName 'mysql.exe'

    $candidates = @()
    $candidates += [pscustomobject]@{ Host = $Db.Host; Port = $Db.Port }
    $candidates += Get-LocalMysqlCandidates
    $candidates += [pscustomobject]@{ Host = '127.0.0.1'; Port = '3306' }

    $seen = @{}
    foreach ($candidate in $candidates) {
        if (-not $candidate) {
            continue
        }

        $key = "$($candidate.Host):$($candidate.Port)"
        if ($seen.ContainsKey($key)) {
            continue
        }
        $seen[$key] = $true

        if (Test-DbEndpoint -Db $Db -DbHost $candidate.Host -Port $candidate.Port -MysqlExe $mysql) {
            $Db.Host = $candidate.Host
            $Db.Port = $candidate.Port
            Write-Step "Using DB endpoint $key"
            return
        }
    }

    throw "Unable to connect to DB '$($Db.Name)' with known Local/MySQL endpoints."
}

function Export-Database {
    param(
        [pscustomobject]$Db,
        [string]$SqlFile
    )

    $mysqldump = Resolve-ToolPath -ExeName 'mysqldump.exe'
    Write-Step "Export DB -> $SqlFile"

    New-Item -Path (Split-Path -Path $SqlFile -Parent) -ItemType Directory -Force | Out-Null

    $args = @(
        "--host=$($Db.Host)",
        "--port=$($Db.Port)",
        "--user=$($Db.User)",
        '--single-transaction',
        '--skip-lock-tables',
        '--default-character-set=utf8mb4',
        '--skip-dump-date',
        '--skip-comments',
        "--result-file=$SqlFile",
        $Db.Name
    )

    $oldPwd = $env:MYSQL_PWD
    $env:MYSQL_PWD = $Db.Password
    $result = Invoke-Native -Exe $mysqldump -Args $args
    if ($null -ne $oldPwd) {
        $env:MYSQL_PWD = $oldPwd
    } else {
        Remove-Item Env:\MYSQL_PWD -ErrorAction SilentlyContinue
    }

    if ($result.ExitCode -ne 0) {
        throw "Database export failed: $($result.StdErr.Trim())"
    }
}

function Import-Database {
    param(
        [pscustomobject]$Db,
        [string]$SqlFile
    )

    if (-not (Test-Path $SqlFile)) {
        throw "SQL file not found: $SqlFile"
    }

    $mysql = Resolve-ToolPath -ExeName 'mysql.exe'
    Write-Step "Import DB <- $SqlFile"

    $args = @(
        "--host=$($Db.Host)",
        "--port=$($Db.Port)",
        "--user=$($Db.User)",
        $Db.Name
    )

    $sqlText = Get-Content -Path $SqlFile -Raw

    $oldPwd = $env:MYSQL_PWD
    $env:MYSQL_PWD = $Db.Password
    $result = Invoke-Native -Exe $mysql -Args $args -StdInText $sqlText
    if ($null -ne $oldPwd) {
        $env:MYSQL_PWD = $oldPwd
    } else {
        Remove-Item Env:\MYSQL_PWD -ErrorAction SilentlyContinue
    }

    if ($result.ExitCode -ne 0) {
        throw "Database import failed: $($result.StdErr.Trim())"
    }
}

if (-not $Mode) {
    throw "Missing required parameter: -Mode push|pull"
}

$repoRoot = (git rev-parse --show-toplevel).Trim()
Assert-CommandSuccess -ExitCode $LASTEXITCODE -Action 'Resolve git root'

Set-Location $repoRoot

$wpConfig = Join-Path $repoRoot 'app/public/wp-config.php'
$sqlFile = Join-Path $repoRoot 'app/sql/local.sql'
$db = Get-WpDbConfig -WpConfigPath $wpConfig
Resolve-DbEndpoint -Db $db

switch ($Mode) {
    'push' {
        Export-Database -Db $db -SqlFile $sqlFile

        Write-Step 'Stage changes'
        git add -A
        Assert-CommandSuccess -ExitCode $LASTEXITCODE -Action 'git add'

        git diff --cached --quiet
        $hasChanges = ($LASTEXITCODE -ne 0)

        if ($hasChanges -and -not $NoCommit) {
            $stamp = Get-Date -Format 'yyyy-MM-dd HH:mm'
            $msg = "chore(sync): auto sync from $env:COMPUTERNAME at $stamp"
            Write-Step "Commit: $msg"
            git commit -m $msg
            Assert-CommandSuccess -ExitCode $LASTEXITCODE -Action 'git commit'
        } elseif (-not $hasChanges) {
            Write-Step 'No changes to commit'
        } else {
            Write-Step 'NoCommit enabled: skip commit'
        }

        Write-Step "Rebase on origin/$Branch"
        git pull --rebase origin $Branch
        Assert-CommandSuccess -ExitCode $LASTEXITCODE -Action 'git pull --rebase'

        if (-not $NoCommit) {
            Write-Step "Push to origin/$Branch"
            git push origin $Branch
            Assert-CommandSuccess -ExitCode $LASTEXITCODE -Action 'git push'
        } else {
            Write-Step 'NoCommit enabled: skip push'
        }
    }
    'pull' {
        Write-Step "Pull from origin/$Branch"
        git pull --ff-only origin $Branch
        Assert-CommandSuccess -ExitCode $LASTEXITCODE -Action 'git pull --ff-only'

        Import-Database -Db $db -SqlFile $sqlFile
    }
}

Write-Step 'Done'
