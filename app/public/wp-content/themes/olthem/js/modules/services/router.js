export function createRouterHelpers(homeUrl, sectionIds) {
    function normalize(path) {
        if (!path) {
            return '/';
        }

        if (!path.startsWith('/')) {
            path = '/' + path;
        }

        if (path !== '/' && path.endsWith('/')) {
            path = path.slice(0, -1);
        }

        return path;
    }

    function getHomePath() {
        const parsed = new URL(homeUrl || '/', window.location.origin);
        const path = parsed.pathname.replace(/\/$/, '');
        return path || '';
    }

    const homePath = getHomePath();

    function appPathFromLocation() {
        const fullPath = normalize(window.location.pathname);
        if (homePath && fullPath.startsWith(homePath)) {
            const trimmed = fullPath.slice(homePath.length);
            return normalize(trimmed || '/');
        }

        return fullPath;
    }

    function absolutePath(appPath) {
        const finalPath = normalize(appPath);
        if (!homePath) {
            return finalPath;
        }

        return normalize(homePath + (finalPath === '/' ? '' : finalPath));
    }

    function isSectionPath(path) {
        if (path === '/') {
            return true;
        }

        return sectionIds.some(function(id) {
            return path === '/' + id;
        });
    }

    function isBlogPath(path) {
        return path === '/blog' || /^\/blog\/[^/]+$/.test(path);
    }

    function isAdminPath(path) {
        return path === '/admin-tool' || path.startsWith('/admin-tool/');
    }

    function sectionFromPath(path) {
        if (path === '/') {
            return 'header';
        }

        return path.slice(1);
    }

    return {
        normalize,
        appPathFromLocation,
        absolutePath,
        isSectionPath,
        isBlogPath,
        isAdminPath,
        sectionFromPath,
    };
}
