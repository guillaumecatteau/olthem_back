<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ─── Version de la structure BDD ────────────────────────────────────────────
define( 'OLTHEM_DB_VERSION', '1.3.0' );


// ─── Création des tables ─────────────────────────────────────────────────────

function olthem_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // ── olthem_users ──────────────────────────────────────────────────────────
    $table_users = $wpdb->prefix . 'olthem_users';
    $sql_users = "CREATE TABLE $table_users (
        id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        username    VARCHAR(60)  NOT NULL,
        nom         VARCHAR(100) NOT NULL,
        prenom      VARCHAR(100) NOT NULL,
        email       VARCHAR(254) NOT NULL,
        password    VARCHAR(255) NOT NULL,
        remember    TINYINT(1)   NOT NULL DEFAULT 0,
        newsletter  TINYINT(1)   NOT NULL DEFAULT 0,
        is_admin    TINYINT(1)   NOT NULL DEFAULT 0,
        created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY email (email),
        UNIQUE KEY username (username)
    ) $charset_collate;";
    dbDelta( $sql_users );

    // ── olthem_ateliers ───────────────────────────────────────────────────────
    $table_ateliers = $wpdb->prefix . 'olthem_ateliers';
    $table_posts    = $wpdb->posts;

        // Supprime l'ancienne table si la structure a changé (dev only – géré par versioning).
        $wpdb->query( "DROP TABLE IF EXISTS {$table_ateliers}" );

        $sql_ateliers = "CREATE TABLE $table_ateliers (
            id                   BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id              BIGINT(20) UNSIGNED DEFAULT NULL,
            thematique_id        BIGINT(20) UNSIGNED DEFAULT NULL,
            mundaneum            TINYINT(1)          NOT NULL DEFAULT 0,
            etablissement        VARCHAR(255)        DEFAULT NULL,
            adresse              VARCHAR(255)        DEFAULT NULL,
            localite             VARCHAR(100)        DEFAULT NULL,
            code_postal          VARCHAR(10)         DEFAULT NULL,
            utilisateur_connecte TINYINT(1)          NOT NULL DEFAULT 0,
            nom                  VARCHAR(100)        DEFAULT NULL,
            prenom               VARCHAR(100)        DEFAULT NULL,
            email                VARCHAR(254)        DEFAULT NULL,
            telephone            VARCHAR(30)         DEFAULT NULL,
            start_date           DATE                DEFAULT NULL,
            end_date             DATE                DEFAULT NULL,
            valid_date           DATE                DEFAULT NULL,
            nb_participants      INT                 DEFAULT NULL,
            created_at           DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY thematique_id (thematique_id)
        ) $charset_collate;";
        dbDelta( $sql_ateliers );

        // Contrainte vers les thematiques (stockées dans wp_posts avec post_type olthem_thematique).
        $fk_exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT CONSTRAINT_NAME
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = %s
               AND CONSTRAINT_NAME = %s
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'
             LIMIT 1",
            $table_ateliers,
            'fk_olthem_ateliers_thematique'
        ) );

        if ( ! $fk_exists ) {
            $wpdb->query( "ALTER TABLE {$table_ateliers}
                ADD CONSTRAINT fk_olthem_ateliers_thematique
                FOREIGN KEY (thematique_id) REFERENCES {$table_posts}(ID)
                ON DELETE SET NULL
                ON UPDATE CASCADE" );
        }

    update_option( 'olthem_db_version', OLTHEM_DB_VERSION );
}

function olthem_maybe_run_migration() {
    if ( get_option( 'olthem_db_version' ) !== OLTHEM_DB_VERSION ) {
        olthem_create_tables();
    }
}
add_action( 'init', 'olthem_maybe_run_migration', 1 );


// ─── Seeding : comptes de test ────────────────────────────────────────────────

function olthem_seed_test_users() {
    global $wpdb;
    $table = $wpdb->prefix . 'olthem_users';

    $seed = array(
        array(
            'username'   => 'GC digital arts',
            'nom'        => 'Catteau',
            'prenom'     => 'Guillaume',
            'email'      => 'guillaume.catteau@gmail.com',
            'password'   => 'Thibaut01',
            'remember'   => 1,
            'newsletter' => 1,
            'is_admin'   => 1,
        ),
        array(
            'username'   => 'Jean-Michel Admin',
            'nom'        => 'Admin',
            'prenom'     => 'Jean-Michel',
            'email'      => 'jeanmicheladmin@gmail.com',
            'password'   => 'Admin1234',
            'remember'   => 1,
            'newsletter' => 1,
            'is_admin'   => 1,
        ),
        array(
            'username'   => 'Jean-Michel User',
            'nom'        => 'User',
            'prenom'     => 'Jean-Michel',
            'email'      => 'jeanmicheluser@gmail.com',
            'password'   => 'User1234',
            'remember'   => 0,
            'newsletter' => 0,
            'is_admin'   => 0,
        ),
    );

    foreach ( $seed as $user ) {
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE email = %s LIMIT 1",
            $user['email']
        ) );

        if ( $exists ) {
            continue;
        }

        $wpdb->replace(
            $table,
            array(
                'username'   => $user['username'],
                'nom'        => $user['nom'],
                'prenom'     => $user['prenom'],
                'email'      => $user['email'],
                'password'   => wp_hash_password( $user['password'] ),
                'remember'   => $user['remember'],
                'newsletter' => $user['newsletter'],
                'is_admin'   => $user['is_admin'],
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d' )
        );
    }
}
add_action( 'init', 'olthem_seed_test_users', 5 );


// ─── Seeding : comptes WordPress (visibles back-office) ─────────────────────

function olthem_seed_wp_users() {
    $seed = array(
        array(
            'user_login' => 'gc-digital-arts',
            'display'    => 'GC digital arts',
            'nickname'   => 'GC digital arts',
            'nom'        => 'Catteau',
            'prenom'     => 'Guillaume',
            'email'      => 'guillaume.catteau@gmail.com',
            'password'   => 'Thibaut01',
            'remember'   => 1,
            'newsletter' => 1,
            'is_admin'   => 1,
        ),
        array(
            'user_login' => 'jean-michel-admin',
            'display'    => 'Jean-Michel Admin',
            'nickname'   => 'Jean-Michel Admin',
            'nom'        => 'Admin',
            'prenom'     => 'Jean-Michel',
            'email'      => 'jeanmicheladmin@gmail.com',
            'password'   => 'Admin1234',
            'remember'   => 1,
            'newsletter' => 1,
            'is_admin'   => 1,
        ),
        array(
            'user_login' => 'jean-michel-user',
            'display'    => 'Jean-Michel User',
            'nickname'   => 'Jean-Michel User',
            'nom'        => 'User',
            'prenom'     => 'Jean-Michel',
            'email'      => 'jeanmicheluser@gmail.com',
            'password'   => 'User1234',
            'remember'   => 0,
            'newsletter' => 0,
            'is_admin'   => 0,
        ),
    );

    foreach ( $seed as $user ) {
        $existing = get_user_by( 'email', $user['email'] );

        if ( ! $existing ) {
            $existing = get_user_by( 'login', $user['user_login'] );
        }

        if ( ! $existing ) {
            $user_id = wp_insert_user( array(
                'user_login'   => $user['user_login'],
                'user_pass'    => $user['password'],
                'user_email'   => $user['email'],
                'display_name' => $user['display'],
                'first_name'   => $user['prenom'],
                'last_name'    => $user['nom'],
                'role'         => $user['is_admin'] ? 'administrator' : 'subscriber',
            ) );

            if ( is_wp_error( $user_id ) ) {
                continue;
            }
        } else {
            $user_id = $existing->ID;

            wp_update_user( array(
                'ID'           => $user_id,
                'display_name' => $user['display'],
                'first_name'   => $user['prenom'],
                'last_name'    => $user['nom'],
                'role'         => $user['is_admin'] ? 'administrator' : 'subscriber',
            ) );
        }

        update_user_meta( $user_id, 'remember', (int) $user['remember'] );
        update_user_meta( $user_id, 'newsletter', (int) $user['newsletter'] );
        update_user_meta( $user_id, 'is_admin', (int) $user['is_admin'] );
        update_user_meta( $user_id, 'nickname', $user['nickname'] );
    }
}
add_action( 'init', 'olthem_seed_wp_users', 6 );


// ─── Seeding : atelier factice ──────────────────────────────────────────────

function olthem_seed_test_atelier() {
    global $wpdb;

    $table_ateliers = $wpdb->prefix . 'olthem_ateliers';
    $table_users    = $wpdb->prefix . 'olthem_users';

    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$table_ateliers} WHERE email = %s LIMIT 1",
        'guillaume.catteau@gmail.com'
    ) );

    if ( $existing ) {
        return;
    }

    $user_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$table_users} WHERE email = %s LIMIT 1",
        'guillaume.catteau@gmail.com'
    ) );

    $linked_user = null;

    if ( $user_id ) {
        $linked_user = $wpdb->get_row( $wpdb->prepare(
            "SELECT nom, prenom, email FROM {$table_users} WHERE id = %d LIMIT 1",
            (int) $user_id
        ) );
    }

    $thematique_ids = get_posts( array(
        'post_type'      => 'olthem_thematique',
        'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'orderby'        => 'date',
        'order'          => 'ASC',
    ) );

    $thematique_id = ! empty( $thematique_ids ) ? (int) $thematique_ids[0] : null;

    $data = array(
        'mundaneum'            => 1,
        'etablissement'        => 'Mundaneum',
        'adresse'              => 'Rue de Nimy 76',
        'localite'             => 'Mons',
        'code_postal'          => '7000',
        'utilisateur_connecte' => 1,
        'nom'                  => $linked_user ? (string) $linked_user->nom : 'Catteau',
        'prenom'               => $linked_user ? (string) $linked_user->prenom : 'Guillaume',
        'email'                => $linked_user ? (string) $linked_user->email : 'guillaume.catteau@gmail.com',
        'telephone'            => '0470000000',
        'start_date'           => '2026-05-15',
        'end_date'             => '2026-05-15',
        'valid_date'           => current_time( 'Y-m-d' ),
        'nb_participants'      => 24,
    );

    $format = array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' );

    if ( $user_id ) {
        $data['user_id'] = (int) $user_id;
        array_unshift( $format, '%d' );
    }

    if ( $thematique_id ) {
        $insert_position = isset( $data['user_id'] ) ? 1 : 0;
        $data = array_slice( $data, 0, $insert_position, true ) + array( 'thematique_id' => $thematique_id ) + array_slice( $data, $insert_position, null, true );
        array_splice( $format, $insert_position, 0, '%d' );
    }

    $wpdb->insert( $table_ateliers, $data, $format );
}
add_action( 'init', 'olthem_seed_test_atelier', 7 );


// ─── Cohérence ateliers : utilisateur connecté ─────────────────────────────

function olthem_sync_connected_ateliers_user_identity() {
    global $wpdb;

    $table_ateliers = $wpdb->prefix . 'olthem_ateliers';
    $table_users    = $wpdb->prefix . 'olthem_users';

    $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_ateliers ) );

    if ( $table_exists !== $table_ateliers ) {
        return;
    }

    $wpdb->query(
        "UPDATE {$table_ateliers} a
         INNER JOIN {$table_users} u ON u.id = a.user_id
         SET a.nom = u.nom,
             a.prenom = u.prenom,
             a.email = u.email
         WHERE a.utilisateur_connecte = 1"
    );
}
add_action( 'init', 'olthem_sync_connected_ateliers_user_identity', 8 );


// ─── Back-office : colonnes utilisateurs personnalisées ─────────────────────

function olthem_users_columns( $columns ) {
    $new_columns = array();

    foreach ( $columns as $key => $label ) {
        if ( 'cb' === $key ) {
            $new_columns[ $key ] = $label;
            $new_columns['olthem_user_id'] = 'ID';
            continue;
        }

        if ( 'name' === $key ) {
            $new_columns['olthem_nickname'] = 'Nom d\'utilisateur';
            $new_columns['olthem_prenom'] = 'Prenom';
            $new_columns['olthem_nom'] = 'Nom';
            continue;
        }

        $new_columns[ $key ] = $label;

        if ( 'role' === $key ) {
            $new_columns['olthem_remember'] = 'Remember';
            $new_columns['olthem_newsletter'] = 'Newsletter';
            $new_columns['olthem_is_admin'] = 'isAdmin';
            $new_columns['olthem_password_hash'] = 'Password hash';
        }
    }

    return $new_columns;
}
add_filter( 'manage_users_columns', 'olthem_users_columns' );

function olthem_users_custom_column( $value, $column_name, $user_id ) {
    if ( 'olthem_user_id' === $column_name ) {
        return (string) $user_id;
    }

    if ( 'olthem_prenom' === $column_name ) {
        return esc_html( (string) get_user_meta( $user_id, 'first_name', true ) );
    }

    if ( 'olthem_nickname' === $column_name ) {
        return esc_html( (string) get_user_meta( $user_id, 'nickname', true ) );
    }

    if ( 'olthem_nom' === $column_name ) {
        return esc_html( (string) get_user_meta( $user_id, 'last_name', true ) );
    }

    if ( 'olthem_remember' === $column_name ) {
        return (int) get_user_meta( $user_id, 'remember', true ) ? 'Oui' : 'Non';
    }

    if ( 'olthem_newsletter' === $column_name ) {
        return (int) get_user_meta( $user_id, 'newsletter', true ) ? 'Oui' : 'Non';
    }

    if ( 'olthem_is_admin' === $column_name ) {
        return (int) get_user_meta( $user_id, 'is_admin', true ) ? 'Oui' : 'Non';
    }

    if ( 'olthem_password_hash' === $column_name ) {
        $user = get_userdata( $user_id );

        if ( ! $user ) {
            return '';
        }

        return '<code>' . esc_html( (string) $user->user_pass ) . '</code>';
    }

    return $value;
}
add_filter( 'manage_users_custom_column', 'olthem_users_custom_column', 10, 3 );


// ─── Back-office : champs booléens sur le profil utilisateur ───────────────

function olthem_user_profile_fields( $user ) {
    ?>
    <h2>Parametres Olthem</h2>
    <table class="form-table" role="presentation">
        <tr>
            <th>ID utilisateur</th>
            <td>
                <code><?php echo esc_html( (string) $user->ID ); ?></code>
            </td>
        </tr>
        <tr>
            <th>Password hash</th>
            <td>
                <code style="word-break: break-all;"><?php echo esc_html( (string) $user->user_pass ); ?></code>
                <p class="description">Le mot de passe original n'est pas stocke par WordPress. Seul le hash est visible.</p>
            </td>
        </tr>
        <tr>
            <th><label for="nickname">Nom d'utilisateur</label></th>
            <td>
                <input type="text" name="nickname" id="nickname" value="<?php echo esc_attr( (string) get_user_meta( $user->ID, 'nickname', true ) ); ?>" class="regular-text" />
                <p class="description">Equivalent metier du display name / nickname, avec espaces autorises.</p>
            </td>
        </tr>
        <tr>
            <th><label for="olthem_remember">Remember</label></th>
            <td>
                <input type="checkbox" name="olthem_remember" id="olthem_remember" value="1" <?php checked( (int) get_user_meta( $user->ID, 'remember', true ), 1 ); ?> />
            </td>
        </tr>
        <tr>
            <th><label for="olthem_newsletter">Newsletter</label></th>
            <td>
                <input type="checkbox" name="olthem_newsletter" id="olthem_newsletter" value="1" <?php checked( (int) get_user_meta( $user->ID, 'newsletter', true ), 1 ); ?> />
            </td>
        </tr>
        <tr>
            <th><label for="olthem_is_admin">isAdmin</label></th>
            <td>
                <input type="checkbox" name="olthem_is_admin" id="olthem_is_admin" value="1" <?php checked( (int) get_user_meta( $user->ID, 'is_admin', true ), 1 ); ?> />
            </td>
        </tr>
    </table>
    <?php
}
add_action( 'show_user_profile', 'olthem_user_profile_fields' );
add_action( 'edit_user_profile', 'olthem_user_profile_fields' );

function olthem_save_user_profile_fields( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return;
    }

    if ( isset( $_POST['nickname'] ) ) {
        $nickname = sanitize_text_field( wp_unslash( $_POST['nickname'] ) );
        update_user_meta( $user_id, 'nickname', $nickname );
        wp_update_user( array(
            'ID'           => $user_id,
            'display_name' => $nickname,
        ) );
    }

    update_user_meta( $user_id, 'remember', isset( $_POST['olthem_remember'] ) ? 1 : 0 );
    update_user_meta( $user_id, 'newsletter', isset( $_POST['olthem_newsletter'] ) ? 1 : 0 );
    update_user_meta( $user_id, 'is_admin', isset( $_POST['olthem_is_admin'] ) ? 1 : 0 );
}
add_action( 'personal_options_update', 'olthem_save_user_profile_fields' );
add_action( 'edit_user_profile_update', 'olthem_save_user_profile_fields' );


// ─── Back-office : page Ateliers ────────────────────────────────────────────

function olthem_register_ateliers_admin_page() {
    add_menu_page(
        'Ateliers',
        'Ateliers',
        'manage_options',
        'olthem-ateliers',
        'olthem_render_ateliers_admin_page',
        'dashicons-welcome-learn-more',
        26
    );
}
add_action( 'admin_menu', 'olthem_register_ateliers_admin_page' );

function olthem_handle_create_atelier() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Acces refuse.' );
    }

    check_admin_referer( 'olthem_create_atelier' );

    global $wpdb;
    $table_ateliers = $wpdb->prefix . 'olthem_ateliers';
    $table_users    = $wpdb->prefix . 'olthem_users';

    $etablissement = isset( $_POST['etablissement'] ) ? sanitize_text_field( wp_unslash( $_POST['etablissement'] ) ) : '';

    if ( '' === $etablissement ) {
        $redirect = add_query_arg( 'olthem_notice', 'missing_etablissement', admin_url( 'admin.php?page=olthem-ateliers' ) );
        wp_safe_redirect( $redirect );
        exit;
    }

    $user_id              = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
    $thematique_id        = isset( $_POST['thematique_id'] ) ? (int) $_POST['thematique_id'] : 0;
    $mundaneum            = isset( $_POST['mundaneum'] ) ? 1 : 0;
    $adresse              = isset( $_POST['adresse'] ) ? sanitize_text_field( wp_unslash( $_POST['adresse'] ) ) : '';
    $localite             = isset( $_POST['localite'] ) ? sanitize_text_field( wp_unslash( $_POST['localite'] ) ) : '';
    $code_postal          = isset( $_POST['code_postal'] ) ? sanitize_text_field( wp_unslash( $_POST['code_postal'] ) ) : '';
    $utilisateur_connecte = isset( $_POST['utilisateur_connecte'] ) ? 1 : 0;
    $nom                  = isset( $_POST['nom'] ) ? sanitize_text_field( wp_unslash( $_POST['nom'] ) ) : '';
    $prenom               = isset( $_POST['prenom'] ) ? sanitize_text_field( wp_unslash( $_POST['prenom'] ) ) : '';
    $email                = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
    $telephone            = isset( $_POST['telephone'] ) ? sanitize_text_field( wp_unslash( $_POST['telephone'] ) ) : '';
    $start_date           = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
    $end_date             = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';
    $valid_date           = isset( $_POST['valid_date'] ) ? sanitize_text_field( wp_unslash( $_POST['valid_date'] ) ) : '';
    $nb_participants      = isset( $_POST['nb_participants'] ) ? (int) $_POST['nb_participants'] : 0;

    foreach ( array( $start_date, $end_date, $valid_date ) as $date_value ) {
        if ( '' !== $date_value && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_value ) ) {
            $redirect = add_query_arg( 'olthem_notice', 'invalid_date', admin_url( 'admin.php?page=olthem-ateliers' ) );
            wp_safe_redirect( $redirect );
            exit;
        }
    }

    if ( $utilisateur_connecte && $user_id > 0 ) {
        $linked_user = $wpdb->get_row( $wpdb->prepare(
            "SELECT nom, prenom, email FROM {$table_users} WHERE id = %d LIMIT 1",
            $user_id
        ) );

        if ( $linked_user ) {
            $nom    = (string) $linked_user->nom;
            $prenom = (string) $linked_user->prenom;
            $email  = (string) $linked_user->email;
        }
    }

    $data = array(
        'mundaneum'            => $mundaneum,
        'etablissement'        => $etablissement,
        'adresse'              => $adresse,
        'localite'             => $localite,
        'code_postal'          => $code_postal,
        'utilisateur_connecte' => $utilisateur_connecte,
        'nom'                  => $nom,
        'prenom'               => $prenom,
        'email'                => $email,
        'telephone'            => $telephone,
        'start_date'           => ( '' !== $start_date ) ? $start_date : null,
        'end_date'             => ( '' !== $end_date ) ? $end_date : null,
        'valid_date'           => ( '' !== $valid_date ) ? $valid_date : null,
        'nb_participants'      => $nb_participants,
    );

    $format = array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' );

    if ( $user_id > 0 ) {
        $data['user_id'] = $user_id;
        array_unshift( $format, '%d' );
    }

    if ( $thematique_id > 0 ) {
        $insert_position = isset( $data['user_id'] ) ? 1 : 0;
        $data = array_slice( $data, 0, $insert_position, true ) + array( 'thematique_id' => $thematique_id ) + array_slice( $data, $insert_position, null, true );
        array_splice( $format, $insert_position, 0, '%d' );
    }

    $inserted = $wpdb->insert( $table_ateliers, $data, $format );

    $redirect = add_query_arg(
        'olthem_notice',
        $inserted ? 'created' : 'db_error',
        admin_url( 'admin.php?page=olthem-ateliers' )
    );

    wp_safe_redirect( $redirect );
    exit;
}
add_action( 'admin_post_olthem_create_atelier', 'olthem_handle_create_atelier' );

function olthem_render_ateliers_admin_page() {
    global $wpdb;

    $table_ateliers = $wpdb->prefix . 'olthem_ateliers';
    $table_users    = $wpdb->prefix . 'olthem_users';
    $table_posts    = $wpdb->posts;
    $table_exists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_ateliers ) );

    $users_rows = $wpdb->get_results( "SELECT id, username, email FROM {$table_users} ORDER BY id ASC" );
    $thematiques = get_posts( array(
        'post_type'      => 'olthem_thematique',
        'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ) );

    echo '<div class="wrap">';
    echo '<h1>Ateliers</h1>';

    if ( isset( $_GET['olthem_notice'] ) ) {
        $notice = sanitize_text_field( wp_unslash( $_GET['olthem_notice'] ) );

        if ( 'created' === $notice ) {
            echo '<div class="notice notice-success is-dismissible"><p>Atelier cree avec succes.</p></div>';
        }

        if ( 'missing_etablissement' === $notice ) {
            echo '<div class="notice notice-error is-dismissible"><p>Le champ Etablissement est obligatoire.</p></div>';
        }

        if ( 'invalid_date' === $notice ) {
            echo '<div class="notice notice-error is-dismissible"><p>Les dates doivent etre au format YYYY-MM-DD.</p></div>';
        }

        if ( 'db_error' === $notice ) {
            echo '<div class="notice notice-error is-dismissible"><p>Erreur lors de la creation de l\'atelier.</p></div>';
        }
    }

    if ( $table_exists !== $table_ateliers ) {
        echo '<div class="notice notice-warning"><p>La table des ateliers n\'existe pas encore.</p></div>';
        echo '</div>';
        return;
    }

    echo '<h2>Creer un atelier</h2>';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    wp_nonce_field( 'olthem_create_atelier' );
    echo '<input type="hidden" name="action" value="olthem_create_atelier" />';
    echo '<table class="form-table" role="presentation">';

    echo '<tr><th><label for="etablissement">Etablissement</label></th><td><input required type="text" id="etablissement" name="etablissement" class="regular-text" /></td></tr>';

    echo '<tr><th><label for="user_id">Utilisateur lie</label></th><td><select id="user_id" name="user_id"><option value="">-- Aucun --</option>';
    foreach ( $users_rows as $user_row ) {
        $label = sprintf( '#%d - %s (%s)', (int) $user_row->id, (string) $user_row->username, (string) $user_row->email );
        echo '<option value="' . esc_attr( (string) $user_row->id ) . '">' . esc_html( $label ) . '</option>';
    }
    echo '</select></td></tr>';

    echo '<tr><th><label for="thematique_id">Thematique</label></th><td><select id="thematique_id" name="thematique_id"><option value="">-- Aucune --</option>';
    foreach ( $thematiques as $thematique ) {
        echo '<option value="' . esc_attr( (string) $thematique->ID ) . '">' . esc_html( $thematique->post_title ) . '</option>';
    }
    echo '</select></td></tr>';

    echo '<tr><th><label for="mundaneum">Mundaneum</label></th><td><input type="checkbox" id="mundaneum" name="mundaneum" value="1" /></td></tr>';
    echo '<tr><th><label for="adresse">Adresse</label></th><td><input type="text" id="adresse" name="adresse" class="regular-text" /></td></tr>';
    echo '<tr><th><label for="localite">Localite</label></th><td><input type="text" id="localite" name="localite" class="regular-text" /></td></tr>';
    echo '<tr><th><label for="code_postal">Code postal</label></th><td><input type="text" id="code_postal" name="code_postal" class="regular-text" /></td></tr>';

    echo '<tr><th><label for="utilisateur_connecte">Utilisateur connecte</label></th><td><input type="checkbox" id="utilisateur_connecte" name="utilisateur_connecte" value="1" /><p class="description">Si coche et un utilisateur est lie, nom/prenom/email sont repris automatiquement.</p></td></tr>';

    echo '<tr><th><label for="nom">Nom</label></th><td><input type="text" id="nom" name="nom" class="regular-text" /></td></tr>';
    echo '<tr><th><label for="prenom">Prenom</label></th><td><input type="text" id="prenom" name="prenom" class="regular-text" /></td></tr>';
    echo '<tr><th><label for="email">Adresse mail</label></th><td><input type="email" id="email" name="email" class="regular-text" /></td></tr>';
    echo '<tr><th><label for="telephone">Telephone</label></th><td><input type="text" id="telephone" name="telephone" class="regular-text" /></td></tr>';

    echo '<tr><th><label for="start_date">StartDate</label></th><td><input type="date" id="start_date" name="start_date" /></td></tr>';
    echo '<tr><th><label for="end_date">EndDate</label></th><td><input type="date" id="end_date" name="end_date" /></td></tr>';
    echo '<tr><th><label for="valid_date">ValidDate</label></th><td><input type="date" id="valid_date" name="valid_date" /></td></tr>';

    echo '<tr><th><label for="nb_participants">Nombre de participants</label></th><td><input type="number" min="0" id="nb_participants" name="nb_participants" value="0" /></td></tr>';

    echo '</table>';
    submit_button( 'Creer l\'atelier' );
    echo '</form>';
    echo '<hr />';
    echo '<h2>Liste des ateliers</h2>';

    $results = $wpdb->get_results(
        "SELECT a.*, 
                u.username AS linked_username,
                u.email AS linked_user_email,
                p.post_title AS thematique_title
         FROM {$table_ateliers} a
         LEFT JOIN {$table_users} u ON u.id = a.user_id
         LEFT JOIN {$table_posts} p ON p.ID = a.thematique_id
         ORDER BY a.created_at DESC, a.id DESC"
    );

    if ( empty( $results ) ) {
        echo '<p>Aucun atelier enregistre pour le moment.</p>';
        echo '</div>';
        return;
    }

    echo '<table class="widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th>ID</th>';
    echo '<th>User ID</th>';
    echo '<th>Utilisateur lie</th>';
    echo '<th>Thematique</th>';
    echo '<th>Mundaneum</th>';
    echo '<th>Etablissement</th>';
    echo '<th>Adresse</th>';
    echo '<th>Localite</th>';
    echo '<th>Code postal</th>';
    echo '<th>Utilisateur connecte</th>';
    echo '<th>Nom</th>';
    echo '<th>Prenom</th>';
    echo '<th>Email</th>';
    echo '<th>Telephone</th>';
    echo '<th>Start date</th>';
    echo '<th>End date</th>';
    echo '<th>Valid date</th>';
    echo '<th>Participants</th>';
    echo '<th>Created at</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    foreach ( $results as $atelier ) {
        echo '<tr>';
        echo '<td>' . esc_html( (string) $atelier->id ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->user_id ) . '</td>';
        echo '<td>' . esc_html( trim( (string) $atelier->linked_username . ' ' . (string) $atelier->linked_user_email ) ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->thematique_title ) . '</td>';
        echo '<td>' . esc_html( (int) $atelier->mundaneum ? 'Oui' : 'Non' ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->etablissement ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->adresse ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->localite ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->code_postal ) . '</td>';
        echo '<td>' . esc_html( (int) $atelier->utilisateur_connecte ? 'Oui' : 'Non' ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->nom ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->prenom ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->email ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->telephone ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->start_date ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->end_date ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->valid_date ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->nb_participants ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->created_at ) . '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}


// ─── Authentification : email uniquement ───────────────────────────────────

function olthem_restrict_login_to_email( $user, $username, $password ) {
    if ( empty( $username ) || empty( $password ) ) {
        return $user;
    }

    if ( is_wp_error( $user ) ) {
        return $user;
    }

    if ( ! is_email( $username ) ) {
        return new WP_Error(
            'olthem_email_required',
            'Veuillez utiliser votre adresse e-mail pour vous connecter.'
        );
    }

    return $user;
}
add_filter( 'authenticate', 'olthem_restrict_login_to_email', 5, 3 );

function olthem_login_label_email_only( $translated_text, $text, $domain ) {
    if ( 'default' !== $domain || ! isset( $GLOBALS['pagenow'] ) || 'wp-login.php' !== $GLOBALS['pagenow'] ) {
        return $translated_text;
    }

    if ( 'Username or Email Address' === $text ) {
        return 'Adresse e-mail';
    }

    if ( 'Username' === $text ) {
        return 'Adresse e-mail';
    }

    return $translated_text;
}
add_filter( 'gettext', 'olthem_login_label_email_only', 10, 3 );


// ─── API auth (front) : register / login / me / logout ────────────────────

function olthem_bool_to_int( $value ) {
    if ( is_bool( $value ) ) {
        return $value ? 1 : 0;
    }

    if ( is_numeric( $value ) ) {
        return ( (int) $value ) ? 1 : 0;
    }

    $normalized = strtolower( trim( (string) $value ) );
    return in_array( $normalized, array( '1', 'true', 'yes', 'on' ), true ) ? 1 : 0;
}

function olthem_make_unique_login_from_email( $email ) {
    $base = sanitize_user( current( explode( '@', (string) $email ) ), true );
    $base = $base ? $base : 'user';

    $candidate = $base;
    $i = 1;

    while ( username_exists( $candidate ) ) {
        $candidate = $base . '-' . $i;
        $i++;
    }

    return $candidate;
}

function olthem_get_bearer_token() {
    $header = '';

    if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
        $header = (string) $_SERVER['HTTP_AUTHORIZATION'];
    } elseif ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
        $header = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    if ( 0 !== stripos( $header, 'Bearer ' ) ) {
        return '';
    }

    return trim( substr( $header, 7 ) );
}

function olthem_issue_api_token( $user_id ) {
    $token_plain = wp_generate_password( 64, false, false );
    $token_hash  = wp_hash_password( $token_plain );

    $tokens = get_user_meta( $user_id, 'olthem_api_tokens', true );
    if ( ! is_array( $tokens ) ) {
        $tokens = array();
    }

    $tokens[] = array(
        'hash'       => $token_hash,
        'created_at' => current_time( 'mysql', true ),
        'expires_at' => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS * 30 ),
    );

    if ( count( $tokens ) > 10 ) {
        $tokens = array_slice( $tokens, -10 );
    }

    update_user_meta( $user_id, 'olthem_api_tokens', $tokens );

    return $token_plain;
}

function olthem_get_user_from_bearer_token( $token_plain, &$matched_hash = null ) {
    if ( '' === $token_plain ) {
        return null;
    }

    $users = get_users( array(
        'meta_key'   => 'olthem_api_tokens',
        'number'     => 200,
        'fields'     => array( 'ID' ),
        'count_total'=> false,
    ) );

    foreach ( $users as $user_obj ) {
        $tokens = get_user_meta( (int) $user_obj->ID, 'olthem_api_tokens', true );
        if ( ! is_array( $tokens ) ) {
            continue;
        }

        foreach ( $tokens as $token_row ) {
            if ( empty( $token_row['hash'] ) || empty( $token_row['expires_at'] ) ) {
                continue;
            }

            if ( strtotime( (string) $token_row['expires_at'] ) < time() ) {
                continue;
            }

            if ( wp_check_password( $token_plain, (string) $token_row['hash'] ) ) {
                $matched_hash = (string) $token_row['hash'];
                return get_user_by( 'id', (int) $user_obj->ID );
            }
        }
    }

    return null;
}

function olthem_upsert_custom_user_row( $wp_user_id, $payload ) {
    global $wpdb;
    $table = $wpdb->prefix . 'olthem_users';

    $wp_user = get_userdata( $wp_user_id );
    if ( ! $wp_user ) {
        return;
    }

    $wpdb->replace(
        $table,
        array(
            'id'         => (int) $wp_user_id,
            'username'   => (string) ( $payload['username'] ?? get_user_meta( $wp_user_id, 'nickname', true ) ),
            'nom'        => (string) ( $payload['nom'] ?? get_user_meta( $wp_user_id, 'last_name', true ) ),
            'prenom'     => (string) ( $payload['prenom'] ?? get_user_meta( $wp_user_id, 'first_name', true ) ),
            'email'      => (string) $wp_user->user_email,
            'password'   => (string) $wp_user->user_pass,
            'remember'   => olthem_bool_to_int( $payload['remember'] ?? get_user_meta( $wp_user_id, 'remember', true ) ),
            'newsletter' => olthem_bool_to_int( $payload['newsletter'] ?? get_user_meta( $wp_user_id, 'newsletter', true ) ),
            'is_admin'   => olthem_bool_to_int( $payload['is_admin'] ?? get_user_meta( $wp_user_id, 'is_admin', true ) ),
        ),
        array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d' )
    );
}

function olthem_format_user_payload( $user ) {
    return array(
        'id'         => (int) $user->ID,
        'username'   => (string) get_user_meta( $user->ID, 'nickname', true ),
        'nom'        => (string) get_user_meta( $user->ID, 'last_name', true ),
        'prenom'     => (string) get_user_meta( $user->ID, 'first_name', true ),
        'email'      => (string) $user->user_email,
        'remember'   => olthem_bool_to_int( get_user_meta( $user->ID, 'remember', true ) ),
        'newsletter' => olthem_bool_to_int( get_user_meta( $user->ID, 'newsletter', true ) ),
        'isAdmin'    => olthem_bool_to_int( get_user_meta( $user->ID, 'is_admin', true ) ),
        'role'       => (string) ( $user->roles[0] ?? '' ),
    );
}

function olthem_rest_register( WP_REST_Request $request ) {
    $params = $request->get_json_params();
    if ( ! is_array( $params ) ) {
        $params = $request->get_params();
    }

    $email    = sanitize_email( (string) ( $params['email'] ?? '' ) );
    $password = (string) ( $params['password'] ?? '' );
    $username = sanitize_text_field( (string) ( $params['username'] ?? '' ) );
    $nom      = sanitize_text_field( (string) ( $params['nom'] ?? '' ) );
    $prenom   = sanitize_text_field( (string) ( $params['prenom'] ?? '' ) );

    if ( ! is_email( $email ) ) {
        return new WP_REST_Response( array( 'message' => 'Email invalide.' ), 400 );
    }

    if ( strlen( $password ) < 8 ) {
        return new WP_REST_Response( array( 'message' => 'Le mot de passe doit contenir au moins 8 caracteres.' ), 400 );
    }

    if ( email_exists( $email ) ) {
        return new WP_REST_Response( array( 'message' => 'Un compte existe deja avec cet email.' ), 409 );
    }

    $is_admin = 0;
    $remember = olthem_bool_to_int( $params['remember'] ?? 0 );
    $newsletter = olthem_bool_to_int( $params['newsletter'] ?? 0 );
    $nickname = '' !== $username ? $username : $prenom . ( $nom ? ' ' . $nom : '' );
    $nickname = trim( $nickname );
    if ( '' === $nickname ) {
        $nickname = current( explode( '@', $email ) );
    }

    $user_id = wp_insert_user( array(
        'user_login'   => olthem_make_unique_login_from_email( $email ),
        'user_pass'    => $password,
        'user_email'   => $email,
        'display_name' => $nickname,
        'first_name'   => $prenom,
        'last_name'    => $nom,
        'role'         => 'subscriber',
    ) );

    if ( is_wp_error( $user_id ) ) {
        return new WP_REST_Response( array( 'message' => $user_id->get_error_message() ), 400 );
    }

    update_user_meta( $user_id, 'nickname', $nickname );
    update_user_meta( $user_id, 'remember', $remember );
    update_user_meta( $user_id, 'newsletter', $newsletter );
    update_user_meta( $user_id, 'is_admin', $is_admin );

    olthem_upsert_custom_user_row( $user_id, array(
        'username'   => $nickname,
        'nom'        => $nom,
        'prenom'     => $prenom,
        'remember'   => $remember,
        'newsletter' => $newsletter,
        'is_admin'   => $is_admin,
    ) );

    $user = get_user_by( 'id', $user_id );
    $token = olthem_issue_api_token( $user_id );

    return new WP_REST_Response( array(
        'token' => $token,
        'user'  => olthem_format_user_payload( $user ),
    ), 201 );
}

function olthem_rest_login( WP_REST_Request $request ) {
    $params = $request->get_json_params();
    if ( ! is_array( $params ) ) {
        $params = $request->get_params();
    }

    $email    = sanitize_email( (string) ( $params['email'] ?? '' ) );
    $password = (string) ( $params['password'] ?? '' );

    if ( ! is_email( $email ) || '' === $password ) {
        return new WP_REST_Response( array( 'message' => 'Email ou mot de passe invalide.' ), 400 );
    }

    $user = get_user_by( 'email', $email );
    if ( ! $user || ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
        return new WP_REST_Response( array( 'message' => 'Identifiants invalides.' ), 401 );
    }

    olthem_upsert_custom_user_row( $user->ID, array() );
    $token = olthem_issue_api_token( $user->ID );

    return new WP_REST_Response( array(
        'token' => $token,
        'user'  => olthem_format_user_payload( $user ),
    ), 200 );
}

function olthem_rest_me( WP_REST_Request $request ) {
    $matched_hash = null;
    $token = olthem_get_bearer_token();
    $user = olthem_get_user_from_bearer_token( $token, $matched_hash );

    if ( ! $user ) {
        return new WP_REST_Response( array( 'message' => 'Token invalide ou expire.' ), 401 );
    }

    return new WP_REST_Response( array( 'user' => olthem_format_user_payload( $user ) ), 200 );
}

function olthem_rest_logout( WP_REST_Request $request ) {
    $matched_hash = null;
    $token = olthem_get_bearer_token();
    $user = olthem_get_user_from_bearer_token( $token, $matched_hash );

    if ( ! $user || ! $matched_hash ) {
        return new WP_REST_Response( array( 'message' => 'Token invalide ou expire.' ), 401 );
    }

    $tokens = get_user_meta( $user->ID, 'olthem_api_tokens', true );
    if ( ! is_array( $tokens ) ) {
        $tokens = array();
    }

    $tokens = array_values( array_filter( $tokens, function( $row ) use ( $matched_hash ) {
        return ! isset( $row['hash'] ) || (string) $row['hash'] !== $matched_hash;
    } ) );

    update_user_meta( $user->ID, 'olthem_api_tokens', $tokens );

    return new WP_REST_Response( array( 'message' => 'Deconnecte.' ), 200 );
}

function olthem_register_auth_rest_routes() {
    register_rest_route( 'olthem/v1', '/auth/register', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'olthem_rest_register',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'olthem/v1', '/auth/login', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'olthem_rest_login',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'olthem/v1', '/auth/me', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'olthem_rest_me',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'olthem/v1', '/auth/logout', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'olthem_rest_logout',
        'permission_callback' => '__return_true',
    ) );
}
add_action( 'rest_api_init', 'olthem_register_auth_rest_routes' );
