<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// --- Version de la structure BDD --------------------------------------------
define( 'OLTHEM_DB_VERSION', '1.6.0' );


// --- Cr�ation des tables -----------------------------------------------------

function olthem_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // -- olthem_users ----------------------------------------------------------
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
        reset_token          VARCHAR(64)  DEFAULT NULL,
        reset_token_expires  DATETIME     DEFAULT NULL,
        UNIQUE KEY username (username)
    ) $charset_collate;";
    dbDelta( $sql_users );

    // -- olthem_ateliers -------------------------------------------------------
    $table_ateliers = $wpdb->prefix . 'olthem_ateliers';
    $table_posts    = $wpdb->posts;

    $sql_ateliers = "CREATE TABLE $table_ateliers (
        id                   BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id              BIGINT(20) UNSIGNED DEFAULT NULL,
        thematique_id        BIGINT(20) UNSIGNED DEFAULT NULL,
        mundaneum            TINYINT(1)          NOT NULL DEFAULT 0,
        displayEvent         TINYINT(1)          NOT NULL DEFAULT 0,
        displayContact       TINYINT(1)          NOT NULL DEFAULT 0,
        etablissement        VARCHAR(255)        DEFAULT NULL,
        adresse              VARCHAR(255)        DEFAULT NULL,
        localite             VARCHAR(100)        DEFAULT NULL,
        code_postal          VARCHAR(10)         DEFAULT NULL,
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

    // Contrainte vers les thematiques (stock�es dans wp_posts avec post_type olthem_thematique).
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

    // -- olthem_tracking -------------------------------------------------------
    $table_tracking = $wpdb->prefix . 'olthem_tracking';
    $sql_tracking = "CREATE TABLE $table_tracking (
        id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id     BIGINT(20) UNSIGNED DEFAULT NULL,
        action      VARCHAR(100)        NOT NULL,
        metadata    JSON                DEFAULT NULL,
        created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY action (action),
        KEY created_at (created_at)
    ) $charset_collate;";
    dbDelta( $sql_tracking );

    update_option( 'olthem_db_version', OLTHEM_DB_VERSION );
}

function olthem_maybe_run_migration() {
    if ( get_option( 'olthem_db_version' ) !== OLTHEM_DB_VERSION ) {
        olthem_create_tables();
    }
}
add_action( 'init', 'olthem_maybe_run_migration', 1 );

function olthem_create_mailing_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // -- olthem_email_templates --
    $table_tpl = $wpdb->prefix . 'olthem_email_templates';
    $sql_tpl = "CREATE TABLE $table_tpl (
        id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        nom          VARCHAR(100) NOT NULL,
        declencheur  VARCHAR(50)  NOT NULL DEFAULT '',
        sujet        VARCHAR(255) NOT NULL DEFAULT '',
        corps        LONGTEXT     NOT NULL,
        created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    dbDelta( $sql_tpl );

    // -- olthem_newsletters --
    $table_nl = $wpdb->prefix . 'olthem_newsletters';
    $sql_nl = "CREATE TABLE $table_nl (
        id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        sujet           VARCHAR(255) NOT NULL DEFAULT '',
        corps           LONGTEXT     NOT NULL,
        nb_destinataires INT         NOT NULL DEFAULT 0,
        envoye_le       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    dbDelta( $sql_nl );
}
add_action( 'init', 'olthem_create_mailing_tables', 3 );

function olthem_ensure_tracking_table() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table_tracking = $wpdb->prefix . 'olthem_tracking';
    $sql_tracking = "CREATE TABLE $table_tracking (
        id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id     BIGINT(20) UNSIGNED DEFAULT NULL,
        action      VARCHAR(100)        NOT NULL,
        metadata    LONGTEXT            DEFAULT NULL,
        created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY action (action),
        KEY created_at (created_at)
    ) $charset_collate;";

    dbDelta( $sql_tracking );
}
add_action( 'init', 'olthem_ensure_tracking_table', 4 );

function olthem_migrate_ateliers_display_flags() {
    global $wpdb;

    $table_ateliers = $wpdb->prefix . 'olthem_ateliers';
    $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_ateliers ) );
    if ( $table_exists !== $table_ateliers ) {
        return;
    }

    $has_display_event = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table_ateliers} LIKE %s", 'displayEvent' ) );
    if ( ! $has_display_event ) {
        $wpdb->query( "ALTER TABLE {$table_ateliers} ADD COLUMN displayEvent TINYINT(1) NOT NULL DEFAULT 0" );
    }

    $has_display_contact = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table_ateliers} LIKE %s", 'displayContact' ) );
    if ( ! $has_display_contact ) {
        $wpdb->query( "ALTER TABLE {$table_ateliers} ADD COLUMN displayContact TINYINT(1) NOT NULL DEFAULT 0" );
    }

    $has_utilisateur_connecte = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM {$table_ateliers} LIKE %s", 'utilisateur_connecte' ) );
    if ( $has_utilisateur_connecte ) {
        $wpdb->query( "ALTER TABLE {$table_ateliers} DROP COLUMN utilisateur_connecte" );
    }
}
add_action( 'init', 'olthem_migrate_ateliers_display_flags', 2 );


// --- Seeding : comptes de test ------------------------------------------------

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


// --- Seeding : comptes WordPress (visibles back-office) ---------------------

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

function olthem_find_page_by_candidates( $slug_candidates, $title_candidates = array() ) {
    foreach ( (array) $slug_candidates as $slug ) {
        $slug = sanitize_title( (string) $slug );
        if ( '' === $slug ) {
            continue;
        }

        $page = get_page_by_path( $slug, OBJECT, 'page' );
        if ( $page instanceof WP_Post ) {
            return $page;
        }
    }

    foreach ( (array) $title_candidates as $title ) {
        $title = trim( (string) $title );
        if ( '' === $title ) {
            continue;
        }

        $query = new WP_Query( array(
            'post_type'      => 'page',
            'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
            'posts_per_page' => 1,
            'title'          => $title,
        ) );

        if ( ! empty( $query->posts ) ) {
            return $query->posts[0];
        }
    }

    return null;
}

function olthem_clone_page_meta( $source_post_id, $target_post_id ) {
    $skip_meta_keys = array(
        '_edit_lock',
        '_edit_last',
        '_wp_old_slug',
        '_wp_trash_meta_status',
        '_wp_trash_meta_time',
    );

    $all_meta = get_post_meta( (int) $source_post_id );
    if ( ! is_array( $all_meta ) ) {
        return;
    }

    foreach ( $all_meta as $meta_key => $values ) {
        if ( in_array( $meta_key, $skip_meta_keys, true ) ) {
            continue;
        }

        delete_post_meta( (int) $target_post_id, (string) $meta_key );

        if ( ! is_array( $values ) ) {
            continue;
        }

        foreach ( $values as $meta_value ) {
            add_post_meta( (int) $target_post_id, (string) $meta_key, maybe_unserialize( $meta_value ) );
        }
    }
}

function olthem_ensure_overlay_duplicate_page( $source_page, $target_slug, $target_title ) {
    $target_slug = sanitize_title( (string) $target_slug );
    if ( ! ( $source_page instanceof WP_Post ) || '' === $target_slug ) {
        return null;
    }

    $existing_target = get_page_by_path( $target_slug, OBJECT, 'page' );
    if ( $existing_target instanceof WP_Post ) {
        return (int) $existing_target->ID;
    }

    $new_post_id = wp_insert_post( array(
        'post_type'    => 'page',
        'post_status'  => 'publish',
        'post_title'   => (string) $target_title,
        'post_name'    => $target_slug,
        'post_content' => (string) $source_page->post_content,
        'post_excerpt' => (string) $source_page->post_excerpt,
        'post_parent'  => (int) $source_page->post_parent,
        'menu_order'   => (int) $source_page->menu_order,
    ) );

    if ( is_wp_error( $new_post_id ) || ! $new_post_id ) {
        return null;
    }

    if ( has_post_thumbnail( (int) $source_page->ID ) ) {
        $thumb_id = get_post_thumbnail_id( (int) $source_page->ID );
        if ( $thumb_id ) {
            set_post_thumbnail( (int) $new_post_id, (int) $thumb_id );
        }
    }

    olthem_clone_page_meta( (int) $source_page->ID, (int) $new_post_id );
    return (int) $new_post_id;
}

function olthem_ensure_atelier_edit_pages() {
    $source_page = olthem_find_page_by_candidates(
        array( 'creation-datelier', 'creation-d-atelier' ),
        array( 'Creation d\'atelier', 'Creation d’atelier', 'Creation atelier' )
    );

    if ( ! ( $source_page instanceof WP_Post ) ) {
        return;
    }

    olthem_ensure_overlay_duplicate_page( $source_page, 'modification-atelier', 'Modification atelier' );
    olthem_ensure_overlay_duplicate_page( $source_page, 'modification-atelier-admin', 'Modification atelier Admin' );
}
add_action( 'init', 'olthem_ensure_atelier_edit_pages', 20 );


// --- Seeding : atelier factice ----------------------------------------------

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
        'nom'                  => $linked_user ? (string) $linked_user->nom : 'Catteau',
        'prenom'               => $linked_user ? (string) $linked_user->prenom : 'Guillaume',
        'email'                => $linked_user ? (string) $linked_user->email : 'guillaume.catteau@gmail.com',
        'telephone'            => '0470000000',
        'start_date'           => '2026-05-15',
        'end_date'             => '2026-05-15',
        'valid_date'           => current_time( 'Y-m-d' ),
        'nb_participants'      => 24,
    );

    $format = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' );

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

function olthem_seed_random_data() {
    global $wpdb;

    if ( get_option( 'olthem_random_data_seeded' ) ) {
        return;
    }

    $table_ateliers = $wpdb->prefix . 'olthem_ateliers';
    $table_tracking = $wpdb->prefix . 'olthem_tracking';
    $theme_ids      = get_posts( array(
        'post_type'      => 'olthem_thematique',
        'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
        'posts_per_page' => 50,
        'fields'         => 'ids',
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
    ) );

    $first_names = array( 'Alice', 'Basile', 'Camille', 'Diane', 'Elias', 'Fatou', 'Gaspard', 'Hana', 'Isaac', 'Jade', 'Karim', 'Lina', 'Milo', 'Nora', 'Oscar', 'Paula', 'Quentin', 'Rania', 'Sami', 'Thea', 'Ugo', 'Valentine', 'Wassim', 'Ysaline', 'Zoe' );
    $last_names  = array( 'Dubois', 'Lambert', 'Moreau', 'Lefevre', 'Simon', 'Bernard', 'Henry', 'Garcia', 'Petit', 'Francis', 'Remy', 'Marchal', 'Boulanger', 'Rousseau', 'Dupont', 'Janssens', 'Martin', 'Dumont', 'Leclercq', 'Noel', 'Denis', 'Thomas', 'Robert', 'Maes', 'Colin' );
    $cities      = array( 'Bruxelles', 'Mons', 'Liege', 'Namur', 'Charleroi', 'Tournai', 'Louvain', 'Bruges', 'Gand', 'Anvers' );
    $schools     = array( 'Athenee Royal', 'College Saint-Pierre', 'Institut des Arts', 'Lycee communal', 'Ecole des Medias', 'Centre culturel', 'Haute Ecole', 'Academie locale', 'Maison de jeunes', 'Campus creatif' );
    $created_wp_users = array();

    for ( $i = 0; $i < 25; $i++ ) {
        $prenom   = $first_names[ $i % count( $first_names ) ];
        $nom      = $last_names[ $i % count( $last_names ) ];
        $email    = strtolower( sanitize_user( $prenom . '.' . $nom . '.' . $i, true ) ) . '@olthem.test';
        $login    = sanitize_user( strtolower( $prenom . '-' . $nom . '-' . $i ), true );
        $nickname = $prenom . ' ' . $nom;
        $user     = get_user_by( 'email', $email );

        if ( ! $user ) {
            $user_id = wp_insert_user( array(
                'user_login'   => $login,
                'user_pass'    => 'Password123!',
                'user_email'   => $email,
                'display_name' => $nickname,
                'first_name'   => $prenom,
                'last_name'    => $nom,
                'role'         => 'subscriber',
            ) );

            if ( is_wp_error( $user_id ) ) {
                continue;
            }

            update_user_meta( $user_id, 'nickname', $nickname );
            update_user_meta( $user_id, 'remember', rand( 0, 1 ) );
            update_user_meta( $user_id, 'newsletter', rand( 0, 1 ) );
            update_user_meta( $user_id, 'is_admin', 0 );
            olthem_upsert_custom_user_row( $user_id, array(
                'username'   => $nickname,
                'prenom'     => $prenom,
                'nom'        => $nom,
                'remember'   => (int) get_user_meta( $user_id, 'remember', true ),
                'newsletter' => (int) get_user_meta( $user_id, 'newsletter', true ),
                'is_admin'   => 0,
            ) );
            $created_wp_users[] = (int) $user_id;
        } else {
            $created_wp_users[] = (int) $user->ID;
        }
    }

    if ( ! empty( $created_wp_users ) ) {
        for ( $i = 0; $i < 10; $i++ ) {
            $user_id      = (int) $created_wp_users[ array_rand( $created_wp_users ) ];
            $wp_user      = get_user_by( 'id', $user_id );
            $prenom       = (string) get_user_meta( $user_id, 'first_name', true );
            $nom          = (string) get_user_meta( $user_id, 'last_name', true );
            $thematique_id = ! empty( $theme_ids ) ? (int) $theme_ids[ array_rand( $theme_ids ) ] : null;
            $city         = $cities[ array_rand( $cities ) ];
            $school       = $schools[ array_rand( $schools ) ] . ' ' . chr( 65 + ( $i % 26 ) );
            $start_ts     = strtotime( '+' . rand( 1, 60 ) . ' days' );
            $end_ts       = strtotime( '+' . rand( 1, 3 ) . ' days', $start_ts );
            $is_validated = (bool) rand( 0, 1 );

            $wpdb->insert(
                $table_ateliers,
                array(
                    'user_id'         => $user_id,
                    'thematique_id'   => $thematique_id,
                    'mundaneum'       => rand( 0, 1 ),
                    'displayEvent'    => rand( 0, 1 ),
                    'displayContact'  => rand( 0, 1 ),
                    'etablissement'   => $school,
                    'adresse'         => rand( 1, 120 ) . ' rue Exemple',
                    'localite'        => $city,
                    'code_postal'     => str_pad( (string) rand( 1000, 7999 ), 4, '0', STR_PAD_LEFT ),
                    'nom'             => $nom,
                    'prenom'          => $prenom,
                    'email'           => $wp_user ? (string) $wp_user->user_email : '',
                    'telephone'       => '04' . str_pad( (string) rand( 10000000, 99999999 ), 8, '0', STR_PAD_LEFT ),
                    'start_date'      => gmdate( 'Y-m-d', $start_ts ),
                    'end_date'        => gmdate( 'Y-m-d', $end_ts ),
                    'valid_date'      => $is_validated ? gmdate( 'Y-m-d', strtotime( '-'. rand( 0, 20 ) .' days' ) ) : null,
                    'nb_participants' => rand( 8, 36 ),
                ),
                array( '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
            );
        }
    }

    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_tracking ) ) === $table_tracking ) {
        $actions = array( 'page_view', 'overlay_open', 'atelier_submit', 'atelier_update', 'login', 'search' );
        for ( $i = 0; $i < 80; $i++ ) {
            $user_id = ! empty( $created_wp_users ) && rand( 0, 100 ) > 20 ? (int) $created_wp_users[ array_rand( $created_wp_users ) ] : null;
            $created_at = gmdate( 'Y-m-d H:i:s', strtotime( '-' . rand( 0, 14 ) . ' days -' . rand( 0, 23 ) . ' hours' ) );
            $wpdb->insert(
                $table_tracking,
                array(
                    'user_id'    => $user_id,
                    'action'     => $actions[ array_rand( $actions ) ],
                    'metadata'   => wp_json_encode( array( 'source' => 'seed', 'index' => $i ), JSON_UNESCAPED_UNICODE ),
                    'created_at' => $created_at,
                ),
                array( '%d', '%s', '%s', '%s' )
            );
        }
    }

    update_option( 'olthem_random_data_seeded', 1 );
}
add_action( 'init', 'olthem_seed_random_data', 8 );

function olthem_seed_random_tracking() {
    global $wpdb;

    if ( get_option( 'olthem_random_tracking_seeded' ) ) {
        return;
    }

    $table_tracking = $wpdb->prefix . 'olthem_tracking';
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_tracking ) ) !== $table_tracking ) {
        return;
    }

    $user_ids = get_users( array(
        'fields'      => 'ID',
        'number'      => 200,
        'count_total' => false,
    ) );

    $actions = array( 'page_view', 'overlay_open', 'atelier_submit', 'atelier_update', 'login', 'search' );
    for ( $i = 0; $i < 80; $i++ ) {
        $user_id = ! empty( $user_ids ) && rand( 0, 100 ) > 20 ? (int) $user_ids[ array_rand( $user_ids ) ] : null;
        $created_at = gmdate( 'Y-m-d H:i:s', strtotime( '-' . rand( 0, 14 ) . ' days -' . rand( 0, 23 ) . ' hours' ) );
        $wpdb->insert(
            $table_tracking,
            array(
                'user_id'    => $user_id,
                'action'     => $actions[ array_rand( $actions ) ],
                'metadata'   => wp_json_encode( array( 'source' => 'seed', 'index' => $i ), JSON_UNESCAPED_UNICODE ),
                'created_at' => $created_at,
            ),
            array( '%d', '%s', '%s', '%s' )
        );
    }

    update_option( 'olthem_random_tracking_seeded', 1 );
}
add_action( 'init', 'olthem_seed_random_tracking', 9 );


// --- Back-office : colonnes utilisateurs personnalis�es ---------------------

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


// --- Back-office : champs bool�ens sur le profil utilisateur ---------------

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


// --- Back-office : page Ateliers --------------------------------------------

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
    $displayEvent         = isset( $_POST['displayEvent'] ) ? 1 : 0;
    $displayContact       = isset( $_POST['displayContact'] ) ? 1 : 0;
    $adresse              = isset( $_POST['adresse'] ) ? sanitize_text_field( wp_unslash( $_POST['adresse'] ) ) : '';
    $localite             = isset( $_POST['localite'] ) ? sanitize_text_field( wp_unslash( $_POST['localite'] ) ) : '';
    $code_postal          = isset( $_POST['code_postal'] ) ? sanitize_text_field( wp_unslash( $_POST['code_postal'] ) ) : '';
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

    $data = array(
        'mundaneum'      => $mundaneum,
        'displayEvent'   => $displayEvent,
        'displayContact' => $displayContact,
        'etablissement'  => $etablissement,
        'adresse'        => $adresse,
        'localite'       => $localite,
        'code_postal'    => $code_postal,
        'nom'            => $nom,
        'prenom'         => $prenom,
        'email'          => $email,
        'telephone'      => $telephone,
        'start_date'     => ( '' !== $start_date ) ? $start_date : null,
        'end_date'       => ( '' !== $end_date ) ? $end_date : null,
        'valid_date'     => ( '' !== $valid_date ) ? $valid_date : null,
        'nb_participants' => $nb_participants,
    );

    $format = array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' );

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

    if ( $inserted ) {
        olthem_notify_admins_atelier_created( (int) $wpdb->insert_id );
    }

    wp_safe_redirect( $redirect );
    exit;
}
add_action( 'admin_post_olthem_create_atelier', 'olthem_handle_create_atelier' );

// --- Back-office : suppression d ateliers ---

function olthem_handle_delete_atelier_single() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Acces refuse.' );
    }

    $atelier_id = isset( $_GET['atelier_id'] ) ? (int) $_GET['atelier_id'] : 0;
    check_admin_referer( 'olthem_delete_atelier_' . $atelier_id );

    if ( $atelier_id > 0 ) {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'olthem_ateliers', array( 'id' => $atelier_id ), array( '%d' ) );
    }

    wp_safe_redirect( add_query_arg( 'olthem_notice', 'deleted', admin_url( 'admin.php?page=olthem-ateliers' ) ) );
    exit;
}
add_action( 'admin_action_olthem_delete_atelier_single', 'olthem_handle_delete_atelier_single' );

// --- Back-office : suppression en masse d ateliers ---

function olthem_handle_bulk_delete_ateliers() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Acces refuse.' );
    }

    check_admin_referer( 'olthem_bulk_delete_ateliers' );

    global $wpdb;
    $table_ateliers = $wpdb->prefix . 'olthem_ateliers';

    $ids = isset( $_POST['atelier_ids'] ) ? (array) $_POST['atelier_ids'] : array();
    $ids = array_map( 'intval', $ids );
    $ids = array_filter( $ids, function( $id ) { return $id > 0; } );

    if ( empty( $ids ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=olthem-ateliers' ) );
        exit;
    }

    $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$table_ateliers} WHERE id IN ({$placeholders})", ...$ids ) );

    $redirect = add_query_arg(
        array( 'olthem_notice' => 'bulk_deleted', 'count' => count( $ids ) ),
        admin_url( 'admin.php?page=olthem-ateliers' )
    );

    wp_safe_redirect( $redirect );
    exit;
}
add_action( 'admin_post_olthem_bulk_delete_ateliers', 'olthem_handle_bulk_delete_ateliers' );

// --- Back-office : edition d ateliers ---

function olthem_handle_update_atelier() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Acces refuse.' );
    }

    check_admin_referer( 'olthem_update_atelier' );

    global $wpdb;
    $table_ateliers = $wpdb->prefix . 'olthem_ateliers';
    $atelier_id = isset( $_POST['atelier_id'] ) ? (int) $_POST['atelier_id'] : 0;

    if ( $atelier_id <= 0 ) {
        wp_safe_redirect( admin_url( 'admin.php?page=olthem-ateliers' ) );
        exit;
    }

    $etablissement = isset( $_POST['etablissement'] ) ? sanitize_text_field( wp_unslash( $_POST['etablissement'] ) ) : '';

    if ( '' === $etablissement ) {
        $redirect = add_query_arg( 'olthem_notice', 'missing_etablissement', admin_url( 'admin.php?page=olthem-ateliers' ) );
        wp_safe_redirect( $redirect );
        exit;
    }

    $user_id         = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
    $thematique_id   = isset( $_POST['thematique_id'] ) ? (int) $_POST['thematique_id'] : 0;
    $mundaneum       = isset( $_POST['mundaneum'] ) ? 1 : 0;
    $displayEvent    = isset( $_POST['displayEvent'] ) ? 1 : 0;
    $displayContact  = isset( $_POST['displayContact'] ) ? 1 : 0;
    $adresse         = isset( $_POST['adresse'] ) ? sanitize_text_field( wp_unslash( $_POST['adresse'] ) ) : '';
    $localite        = isset( $_POST['localite'] ) ? sanitize_text_field( wp_unslash( $_POST['localite'] ) ) : '';
    $code_postal     = isset( $_POST['code_postal'] ) ? sanitize_text_field( wp_unslash( $_POST['code_postal'] ) ) : '';
    $nom             = isset( $_POST['nom'] ) ? sanitize_text_field( wp_unslash( $_POST['nom'] ) ) : '';
    $prenom          = isset( $_POST['prenom'] ) ? sanitize_text_field( wp_unslash( $_POST['prenom'] ) ) : '';
    $email           = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
    $telephone       = isset( $_POST['telephone'] ) ? sanitize_text_field( wp_unslash( $_POST['telephone'] ) ) : '';
    $start_date      = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
    $end_date        = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';
    $valid_date      = isset( $_POST['valid_date'] ) ? sanitize_text_field( wp_unslash( $_POST['valid_date'] ) ) : '';
    $nb_participants = isset( $_POST['nb_participants'] ) ? (int) $_POST['nb_participants'] : 0;

    foreach ( array( $start_date, $end_date, $valid_date ) as $date_value ) {
        if ( '' !== $date_value && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_value ) ) {
            $redirect = add_query_arg( 'olthem_notice', 'invalid_date', admin_url( 'admin.php?page=olthem-ateliers' ) );
            wp_safe_redirect( $redirect );
            exit;
        }
    }

    $data = array(
        'mundaneum'       => $mundaneum,
        'displayEvent'    => $displayEvent,
        'displayContact'  => $displayContact,
        'etablissement'   => $etablissement,
        'adresse'         => $adresse,
        'localite'        => $localite,
        'code_postal'     => $code_postal,
        'nom'             => $nom,
        'prenom'          => $prenom,
        'email'           => $email,
        'telephone'       => $telephone,
        'start_date'      => ( '' !== $start_date ) ? $start_date : null,
        'end_date'        => ( '' !== $end_date ) ? $end_date : null,
        'valid_date'      => ( '' !== $valid_date ) ? $valid_date : null,
        'nb_participants' => $nb_participants,
        'user_id'         => $user_id > 0 ? $user_id : null,
        'thematique_id'   => $thematique_id > 0 ? $thematique_id : null,
    );

    $format = array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d' );

    $updated = $wpdb->update( $table_ateliers, $data, array( 'id' => $atelier_id ), $format, array( '%d' ) );

    $redirect = add_query_arg(
        'olthem_notice',
        ( false !== $updated ) ? 'updated' : 'db_error',
        admin_url( 'admin.php?page=olthem-ateliers' )
    );

    wp_safe_redirect( $redirect );
    exit;
}
add_action( 'admin_post_olthem_update_atelier', 'olthem_handle_update_atelier' );

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

        if ( 'deleted' === $notice ) {
            echo '<div class="notice notice-success is-dismissible"><p>Atelier supprime avec succes.</p></div>';
        }

        if ( 'bulk_deleted' === $notice ) {
            $count = isset( $_GET['count'] ) ? (int) $_GET['count'] : 0;
            echo '<div class="notice notice-success is-dismissible"><p>' . $count . ' atelier(s) supprime(s) avec succes.</p></div>';
        }

        if ( 'updated' === $notice ) {
            echo '<div class="notice notice-success is-dismissible"><p>Atelier modifie avec succes.</p></div>';
        }
    }

    if ( $table_exists !== $table_ateliers ) {
        echo '<div class="notice notice-warning"><p>La table des ateliers n\'existe pas encore.</p></div>';
        echo '</div>';
        return;
    }

    $is_edit_mode = isset( $_GET['action'] ) && 'edit' === $_GET['action'] && isset( $_GET['atelier_id'] );
    $atelier_to_edit = null;

    if ( $is_edit_mode ) {
        $edit_id = (int) $_GET['atelier_id'];
        $atelier_to_edit = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_ateliers} WHERE id = %d LIMIT 1", $edit_id ) );
        if ( ! $atelier_to_edit ) {
            echo '<div class="notice notice-error is-dismissible"><p>Atelier introuvable.</p></div>';
            $is_edit_mode = false;
        }
    }

    if ( $is_edit_mode && $atelier_to_edit ) {
        echo '<h2>Modifier l\'' . esc_html( (string) $atelier_to_edit->etablissement ) . ' <small>(#' . esc_html( (string) $atelier_to_edit->id ) . ')</small></h2>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'olthem_update_atelier' );
        echo '<input type="hidden" name="action" value="olthem_update_atelier" />';
        echo '<input type="hidden" name="atelier_id" value="' . esc_attr( (string) $atelier_to_edit->id ) . '" />';
        echo '<table class="form-table" role="presentation">';

        echo '<tr><th><label for="edit_etablissement">Etablissement</label></th><td><input required type="text" id="edit_etablissement" name="etablissement" value="' . esc_attr( (string) $atelier_to_edit->etablissement ) . '" class="regular-text" /></td></tr>';

        echo '<tr><th><label for="edit_user_id">Utilisateur lie</label></th><td><select id="edit_user_id" name="user_id"><option value="">-- Aucun --</option>';
        foreach ( $users_rows as $user_row ) {
            $label = sprintf( '#%d - %s (%s)', (int) $user_row->id, (string) $user_row->username, (string) $user_row->email );
            $sel = ( (string) $user_row->id === (string) $atelier_to_edit->user_id ) ? ' selected' : '';
            echo '<option value="' . esc_attr( (string) $user_row->id ) . '"' . $sel . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th><label for="edit_thematique_id">Thematique</label></th><td><select id="edit_thematique_id" name="thematique_id"><option value="">-- Aucune --</option>';
        foreach ( $thematiques as $thematique ) {
            $sel = ( (string) $thematique->ID === (string) $atelier_to_edit->thematique_id ) ? ' selected' : '';
            echo '<option value="' . esc_attr( (string) $thematique->ID ) . '"' . $sel . '>' . esc_html( $thematique->post_title ) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th><label for="edit_mundaneum">Mundaneum</label></th><td><input type="checkbox" id="edit_mundaneum" name="mundaneum" value="1"' . ( $atelier_to_edit->mundaneum ? ' checked' : '' ) . ' /></td></tr>';
        echo '<tr><th><label for="edit_displayEvent">Afficher dans les evenements</label></th><td><input type="checkbox" id="edit_displayEvent" name="displayEvent" value="1"' . ( $atelier_to_edit->displayEvent ? ' checked' : '' ) . ' /></td></tr>';
        echo '<tr><th><label for="edit_displayContact">Afficher dans les contacts</label></th><td><input type="checkbox" id="edit_displayContact" name="displayContact" value="1"' . ( $atelier_to_edit->displayContact ? ' checked' : '' ) . ' /></td></tr>';

        echo '<tr><th><label for="edit_adresse">Adresse</label></th><td><input type="text" id="edit_adresse" name="adresse" value="' . esc_attr( (string) $atelier_to_edit->adresse ) . '" class="regular-text" /></td></tr>';
        echo '<tr><th><label for="edit_localite">Localite</label></th><td><input type="text" id="edit_localite" name="localite" value="' . esc_attr( (string) $atelier_to_edit->localite ) . '" class="regular-text" /></td></tr>';
        echo '<tr><th><label for="edit_code_postal">Code postal</label></th><td><input type="text" id="edit_code_postal" name="code_postal" value="' . esc_attr( (string) $atelier_to_edit->code_postal ) . '" class="regular-text" /></td></tr>';
        echo '<tr><th><label for="edit_nom">Nom</label></th><td><input type="text" id="edit_nom" name="nom" value="' . esc_attr( (string) $atelier_to_edit->nom ) . '" class="regular-text" /></td></tr>';
        echo '<tr><th><label for="edit_prenom">Prenom</label></th><td><input type="text" id="edit_prenom" name="prenom" value="' . esc_attr( (string) $atelier_to_edit->prenom ) . '" class="regular-text" /></td></tr>';
        echo '<tr><th><label for="edit_email">Adresse mail</label></th><td><input type="email" id="edit_email" name="email" value="' . esc_attr( (string) $atelier_to_edit->email ) . '" class="regular-text" /></td></tr>';
        echo '<tr><th><label for="edit_telephone">Telephone</label></th><td><input type="text" id="edit_telephone" name="telephone" value="' . esc_attr( (string) $atelier_to_edit->telephone ) . '" class="regular-text" /></td></tr>';
        echo '<tr><th><label for="edit_start_date">Start date</label></th><td><input type="date" id="edit_start_date" name="start_date" value="' . esc_attr( (string) $atelier_to_edit->start_date ) . '" /></td></tr>';
        echo '<tr><th><label for="edit_end_date">End date</label></th><td><input type="date" id="edit_end_date" name="end_date" value="' . esc_attr( (string) $atelier_to_edit->end_date ) . '" /></td></tr>';
        echo '<tr><th><label for="edit_valid_date">Valid date</label></th><td><input type="date" id="edit_valid_date" name="valid_date" value="' . esc_attr( (string) $atelier_to_edit->valid_date ) . '" /></td></tr>';
        echo '<tr><th><label for="edit_nb_participants">Participants</label></th><td><input type="number" min="0" id="edit_nb_participants" name="nb_participants" value="' . esc_attr( (string) $atelier_to_edit->nb_participants ) . '" /></td></tr>';

        echo '</table>';
        submit_button( 'Enregistrer les modifications' );
        echo '</form>';
        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=olthem-ateliers' ) ) . '">&larr; Retour a la liste</a></p>';
        echo '<hr />';
    }

    if ( ! $is_edit_mode ) {
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
    echo '<tr><th><label for="displayEvent">Afficher dans les �v�nements</label></th><td><input type="checkbox" id="displayEvent" name="displayEvent" value="1" /></td></tr>';
    echo '<tr><th><label for="displayContact">Afficher dans les contacts</label></th><td><input type="checkbox" id="displayContact" name="displayContact" value="1" /></td></tr>';
    echo '<tr><th><label for="adresse">Adresse</label></th><td><input type="text" id="adresse" name="adresse" class="regular-text" /></td></tr>';
    echo '<tr><th><label for="localite">Localite</label></th><td><input type="text" id="localite" name="localite" class="regular-text" /></td></tr>';
    echo '<tr><th><label for="code_postal">Code postal</label></th><td><input type="text" id="code_postal" name="code_postal" class="regular-text" /></td></tr>';

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
    } // end if ( ! $is_edit_mode )
    echo '<h2>Liste des ateliers</h2>';

    $results = $wpdb->get_results(
        "SELECT a.*,
                p.post_title AS thematique_title
         FROM {$table_ateliers} a
         LEFT JOIN {$table_posts} p ON p.ID = a.thematique_id
         ORDER BY a.created_at DESC, a.id DESC"
    );

    if ( empty( $results ) ) {
        echo '<p>Aucun atelier enregistre pour le moment.</p>';
        echo '</div>';
        return;
    }

    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" id="olthem-ateliers-bulk-form">';
    wp_nonce_field( 'olthem_bulk_delete_ateliers' );
    echo '<input type="hidden" name="action" value="olthem_bulk_delete_ateliers" />';
echo '<table class="widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th class="check-column"><input type="checkbox" id="cb-select-all" title="Tout selectionner" /></th>';
    echo '<th>ID</th>';
    echo '<th>User ID</th>';
    echo '<th>Thematique</th>';
    echo '<th>Mundaneum</th>';
    echo '<th>Afficher �v�nement</th>';
    echo '<th>Afficher contact</th>';
    echo '<th>Etablissement</th>';
    echo '<th>Adresse</th>';
    echo '<th>Localite</th>';
    echo '<th>Code postal</th>';
    echo '<th>Nom</th>';
    echo '<th>Prenom</th>';
    echo '<th>Email</th>';
    echo '<th>Telephone</th>';
    echo '<th>Start date</th>';
    echo '<th>End date</th>';
    echo '<th>Valid date</th>';
    echo '<th>Participants</th>';
    echo '<th>Created at</th>';
    echo '<th>Actions</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    foreach ( $results as $atelier ) {
        echo '<tr>';
        echo '<td class="check-column"><input type="checkbox" name="atelier_ids[]" value="' . esc_attr( (string) $atelier->id ) . '" /></td>';
        echo '<td>' . esc_html( (string) $atelier->id ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->user_id ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->thematique_title ) . '</td>';
        echo '<td>' . esc_html( (int) $atelier->mundaneum ? 'Oui' : 'Non' ) . '</td>';
        echo '<td>' . esc_html( (int) $atelier->displayEvent ? 'Oui' : 'Non' ) . '</td>';
        echo '<td>' . esc_html( (int) $atelier->displayContact ? 'Oui' : 'Non' ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->etablissement ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->adresse ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->localite ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->code_postal ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->nom ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->prenom ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->email ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->telephone ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->start_date ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->end_date ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->valid_date ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->nb_participants ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->created_at ) . '</td>';
        echo '<td>';
        $edit_url        = add_query_arg( array( 'page' => 'olthem-ateliers', 'action' => 'edit', 'atelier_id' => (int) $atelier->id ), admin_url( 'admin.php' ) );
        echo '<a href="' . esc_url( $edit_url ) . '">Modifier</a>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
    echo '<div class="tablenav bottom" style="margin-top:6px">';
    echo '<div class="alignleft actions bulkactions">';
    echo '<button type="submit" form="olthem-ateliers-bulk-form" class="button action" style="color:#a00;border-color:#a00">Supprimer la selection</button>';
    echo '</div></div>';
    echo '</form>';
    echo '<script>document.getElementById("cb-select-all").addEventListener("change",function(){document.querySelectorAll("input[name=\"atelier_ids[]\"]").forEach(function(cb){cb.checked=document.getElementById("cb-select-all").checked})});</script>';
    echo '</div>';
}


// --- Authentification : email uniquement -----------------------------------

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


// --- API auth (front) : register / login / me / logout --------------------

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

    // Persist remember preference if explicitly provided by the client.
    $upsert_payload = array();
    if ( array_key_exists( 'remember', $params ) ) {
        $remember = olthem_bool_to_int( $params['remember'] );
        update_user_meta( $user->ID, 'remember', $remember );
        $upsert_payload['remember'] = $remember;
    }

    olthem_upsert_custom_user_row( $user->ID, $upsert_payload );
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

function olthem_form_allowed_tables() {
    return array(
        'olthem_ateliers',
        'olthem_users',
    );
}

function olthem_form_resolve_table_name( $raw_table ) {
    global $wpdb;

    $table = sanitize_key( (string) $raw_table );
    if ( '' === $table ) {
        return null;
    }

    $prefix = $wpdb->prefix;
    if ( 0 === strpos( $table, $prefix ) ) {
        $table = substr( $table, strlen( $prefix ) );
    }

    $allowed = olthem_form_allowed_tables();
    if ( ! in_array( $table, $allowed, true ) ) {
        return null;
    }

    return $prefix . $table;
}

function olthem_form_table_columns( $table_name ) {
    global $wpdb;

    $columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table_name}", 0 );
    if ( ! is_array( $columns ) ) {
        return array();
    }

    return array_map( 'strval', $columns );
}

function olthem_form_normalize_value( $column, $value ) {
    $column = (string) $column;

    if ( is_array( $value ) ) {
        return wp_json_encode( $value, JSON_UNESCAPED_UNICODE );
    }

    if ( is_bool( $value ) ) {
        return $value ? 1 : 0;
    }

    if ( null === $value ) {
        return null;
    }

    if ( 'password' === $column ) {
        return wp_hash_password( (string) $value );
    }

    return (string) $value;
}

function olthem_rest_form_submit( WP_REST_Request $request ) {
    global $wpdb;

    $table_raw = $request->get_param( 'table' );
    $values    = $request->get_param( 'values' );

    if ( ! is_string( $table_raw ) || '' === trim( $table_raw ) ) {
        return new WP_REST_Response( array( 'message' => 'Table manquante.' ), 400 );
    }

    if ( ! is_array( $values ) || empty( $values ) ) {
        return new WP_REST_Response( array( 'message' => 'Aucune valeur a enregistrer.' ), 400 );
    }

    $table_name = olthem_form_resolve_table_name( $table_raw );
    if ( ! $table_name ) {
        return new WP_REST_Response( array( 'message' => 'Table non autorisee.' ), 403 );
    }

    $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
    if ( $table_exists !== $table_name ) {
        return new WP_REST_Response( array( 'message' => 'Table introuvable.' ), 404 );
    }

    $columns = olthem_form_table_columns( $table_name );
    if ( empty( $columns ) ) {
        return new WP_REST_Response( array( 'message' => 'Colonnes introuvables.' ), 500 );
    }

    $columns_by_key = array();
    foreach ( $columns as $db_column ) {
        $columns_by_key[ sanitize_key( (string) $db_column ) ] = (string) $db_column;
    }

    $blocked = array( 'id', 'created_at' );
    $data    = array();
    $format  = array();

    foreach ( $values as $key => $value ) {
        $column_key = sanitize_key( (string) $key );
        if ( '' === $column_key ) {
            continue;
        }

        if ( in_array( $column_key, $blocked, true ) ) {
            continue;
        }

        if ( ! isset( $columns_by_key[ $column_key ] ) ) {
            continue;
        }

        $column = $columns_by_key[ $column_key ];

        $normalized = olthem_form_normalize_value( $column, $value );
        $data[ $column ] = $normalized;

        if ( is_int( $normalized ) ) {
            $format[] = '%d';
        } elseif ( is_float( $normalized ) ) {
            $format[] = '%f';
        } elseif ( null === $normalized ) {
            $format[] = '%s';
        } else {
            $format[] = '%s';
        }
    }

    if ( empty( $data ) ) {
        return new WP_REST_Response( array( 'message' => 'Aucune colonne valide a enregistrer.' ), 400 );
    }

    $inserted = $wpdb->insert( $table_name, $data, $format );
    if ( false === $inserted ) {
        return new WP_REST_Response( array( 'message' => 'Erreur d\'insertion en base.' ), 500 );
    }

    if ( $table_name === $wpdb->prefix . 'olthem_ateliers' ) {
        olthem_notify_admins_atelier_created( (int) $wpdb->insert_id );
    }

    return new WP_REST_Response(
        array(
            'message'   => 'Enregistre.',
            'table'     => $table_name,
            'insert_id' => (int) $wpdb->insert_id,
        ),
        201
    );
}

function olthem_rest_forgot_password( WP_REST_Request $request ) {
    global $wpdb;
    $table = $wpdb->prefix . 'olthem_users';

    $email = sanitize_email( (string) $request->get_param( 'email' ) );
    if ( ! is_email( $email ) ) {
        return new WP_REST_Response( array( 'message' => 'Adresse email invalide.' ), 400 );
    }

    $user = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s LIMIT 1", $email ) );
    if ( ! $user ) {
        return new WP_REST_Response( array( 'message' => 'Si un compte existe, un email a ete envoye.' ), 200 );
    }

    $token   = bin2hex( random_bytes( 32 ) );
    $expires = gmdate( 'Y-m-d H:i:s', time() + 3600 );

    $wpdb->update(
        $table,
        array( 'reset_token' => $token, 'reset_token_expires' => $expires ),
        array( 'id' => (int) $user->id ),
        array( '%s', '%s' ),
        array( '%d' )
    );

    $frontend_base = get_option( 'olthem_frontend_url', 'http://localhost:5500' );
    $reset_url     = rtrim( $frontend_base, '/' ) . '/?reset_token=' . rawurlencode( $token );

    $mail = olthem_send_mailing_template( 'reset_password', array(
        'USERNAME'   => (string) $user->username,
        'prenom'     => (string) $user->prenom,
        'nom'        => (string) $user->nom,
        'email'      => $email,
        'RESET_LINK' => $reset_url,
    ) );

    if ( $mail ) {
        wp_mail(
            $email,
            $mail['sujet'],
            $mail['corps'],
            array( 'Content-Type: text/html; charset=UTF-8' )
        );
    }

    return new WP_REST_Response( array( 'message' => 'Si un compte existe, un email a ete envoye.' ), 200 );
}

function olthem_rest_reset_password( WP_REST_Request $request ) {
    global $wpdb;
    $table = $wpdb->prefix . 'olthem_users';

    $token    = sanitize_text_field( (string) $request->get_param( 'token' ) );
    $password = (string) $request->get_param( 'password' );

    if ( ! $token || ! $password ) {
        return new WP_REST_Response( array( 'message' => 'Token ou mot de passe manquant.' ), 400 );
    }

    if ( strlen( $password ) < 8 ) {
        return new WP_REST_Response( array( 'message' => 'Le mot de passe doit contenir au moins 8 caracteres.' ), 400 );
    }

    $user = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$table} WHERE reset_token = %s AND reset_token_expires > %s LIMIT 1",
        $token,
        gmdate( 'Y-m-d H:i:s' )
    ) );

    if ( ! $user ) {
        return new WP_REST_Response( array( 'message' => 'Lien invalide ou expire.' ), 400 );
    }

    $hashed = wp_hash_password( $password );
    $wpdb->update(
        $table,
        array( 'password' => $hashed, 'reset_token' => null, 'reset_token_expires' => null ),
        array( 'id' => (int) $user->id ),
        array( '%s', '%s', '%s' ),
        array( '%d' )
    );

    $wp_user = get_user_by( 'email', (string) $user->email );
    if ( $wp_user ) {
        wp_set_password( $password, $wp_user->ID );
    }

    return new WP_REST_Response( array( 'message' => 'Mot de passe mis a jour.' ), 200 );
}

// --- PUT /auth/me --- Mise � jour du profil ---------------------------------

function olthem_rest_update_me( WP_REST_Request $request ) {
    $token = olthem_get_bearer_token();
    $user  = olthem_get_user_from_bearer_token( $token, $_ );

    if ( ! $user ) {
        return new WP_REST_Response( array( 'message' => 'Token invalide ou expire.' ), 401 );
    }

    $params   = $request->get_json_params();
    $username = isset( $params['username'] ) ? sanitize_text_field( (string) $params['username'] ) : null;
    $prenom   = isset( $params['prenom'] )   ? sanitize_text_field( (string) $params['prenom'] )   : null;
    $nom      = isset( $params['nom'] )      ? sanitize_text_field( (string) $params['nom'] )      : null;
    $email    = isset( $params['email'] )    ? sanitize_email( (string) $params['email'] )          : null;
    $remember    = array_key_exists( 'remember',    $params ) ? olthem_bool_to_int( $params['remember'] )    : null;
    $newsletter  = array_key_exists( 'newsletter',  $params ) ? olthem_bool_to_int( $params['newsletter'] )  : null;

    // Validation username
    if ( null !== $username ) {
        if ( mb_strlen( $username ) < 2 ) {
            return new WP_REST_Response( array( 'code' => 'username_too_short', 'message' => 'Le nom d\'utilisateur doit contenir au moins 2 caracteres.' ), 400 );
        }
        // Unicit� : cherche si un autre user a d�j� ce nickname
        global $wpdb;
        $existing_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'nickname' AND meta_value = %s LIMIT 1",
            $username
        ) );
        if ( $existing_id && (int) $existing_id !== (int) $user->ID ) {
            return new WP_REST_Response( array( 'code' => 'username_exists', 'message' => 'Ce nom est deja pris.' ), 409 );
        }
    }

    // Validation email
    if ( null !== $email ) {
        if ( ! is_email( $email ) ) {
            return new WP_REST_Response( array( 'code' => 'email_invalid', 'message' => 'Email invalide.' ), 400 );
        }
        $existing_id = email_exists( $email );
        if ( $existing_id && (int) $existing_id !== (int) $user->ID ) {
            return new WP_REST_Response( array( 'code' => 'email_exists', 'message' => 'Un compte existe deja avec cet email.' ), 409 );
        }
    }

    // Mise � jour WP user
    $wp_update = array( 'ID' => (int) $user->ID );
    if ( null !== $username ) { $wp_update['display_name'] = $username; update_user_meta( $user->ID, 'nickname', $username ); }
    if ( null !== $prenom )   { $wp_update['first_name'] = $prenom; update_user_meta( $user->ID, 'first_name', $prenom ); }
    if ( null !== $nom )      { $wp_update['last_name']  = $nom;    update_user_meta( $user->ID, 'last_name',  $nom ); }
    if ( null !== $email )    { $wp_update['user_email'] = $email; }
    if ( null !== $remember )   { update_user_meta( $user->ID, 'remember',   $remember ); }
    if ( null !== $newsletter ) { update_user_meta( $user->ID, 'newsletter', $newsletter ); }

    $result = wp_update_user( $wp_update );
    if ( is_wp_error( $result ) ) {
        return new WP_REST_Response( array( 'message' => $result->get_error_message() ), 400 );
    }

    // Sync table custom
    $upsert = array();
    if ( null !== $username )   { $upsert['username']   = $username; }
    if ( null !== $prenom )     { $upsert['prenom']     = $prenom; }
    if ( null !== $nom )        { $upsert['nom']        = $nom; }
    if ( null !== $remember )   { $upsert['remember']   = $remember; }
    if ( null !== $newsletter ) { $upsert['newsletter'] = $newsletter; }
    olthem_upsert_custom_user_row( $user->ID, $upsert );

    $fresh_user = get_user_by( 'id', (int) $user->ID );
    $new_token  = olthem_issue_api_token( $user->ID );

    return new WP_REST_Response( array(
        'token' => $new_token,
        'user'  => olthem_format_user_payload( $fresh_user ),
    ), 200 );
}

// --- GET /auth/check-username --- Disponibilit� du username -----------------

function olthem_rest_check_username( WP_REST_Request $request ) {
    $username = sanitize_text_field( (string) ( $request->get_param( 'username' ) ?? '' ) );
    $current_user_id = (int) ( $request->get_param( 'current_user_id' ) ?? 0 );

    if ( mb_strlen( $username ) < 2 ) {
        return new WP_REST_Response( array( 'available' => false, 'reason' => 'too_short' ), 200 );
    }

    global $wpdb;
    $existing_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'nickname' AND meta_value = %s LIMIT 1",
        $username
    ) );

    $taken = $existing_id && ( ! $current_user_id || (int) $existing_id !== $current_user_id );
    return new WP_REST_Response( array( 'available' => ! $taken ), 200 );
}

// --- GET /auth/me/ateliers --- Ateliers de l'utilisateur connect� ------------

function olthem_rest_my_ateliers( WP_REST_Request $request ) {
    $token = olthem_get_bearer_token();
    $user  = olthem_get_user_from_bearer_token( $token, $_ );

    if ( ! $user ) {
        return new WP_REST_Response( array( 'message' => 'Token invalide ou expire.' ), 401 );
    }

    global $wpdb;
    $table_ateliers = $wpdb->prefix . 'olthem_ateliers';
    $table_posts    = $wpdb->posts;

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT a.id, a.thematique_id, a.start_date, a.end_date, a.valid_date,
                a.nom, a.prenom, a.email, a.telephone,
                a.etablissement, a.adresse, a.localite, a.code_postal, a.mundaneum,
                a.nb_participants, a.displayEvent, a.displayContact,
                p.post_title AS thematique
         FROM {$table_ateliers} a
         LEFT JOIN {$table_posts} p ON p.ID = a.thematique_id
         WHERE a.user_id = %d
         ORDER BY a.start_date ASC",
        (int) $user->ID
    ) );

    if ( ! is_array( $rows ) ) {
        $rows = array();
    }

    $result = array_map( function( $row ) {
        $lieu = '';
        if ( (int) $row->mundaneum ) {
            $lieu = 'Mundaneum, Rue de Nimy 76, 7000 Mons';
        } else {
            $parts = array_filter( array( $row->etablissement, $row->adresse ) );
            $lieu  = implode( ', ', $parts );
        }

        return array(
            'id'             => (int) $row->id,
            'thematique'     => (string) ( $row->thematique ?? '' ),
            'thematique_id'  => (int) ( $row->thematique_id ?? 0 ),
            'start_date'     => (string) ( $row->start_date ?? '' ),
            'end_date'       => (string) ( $row->end_date ?? '' ),
            'lieu'           => $lieu,
            'localite'       => (string) ( $row->localite ?? '' ),
            'valid_date'     => $row->valid_date ?: null,
            'nb_participants'=> (int) ( $row->nb_participants ?? 0 ),
            'nom'            => (string) ( $row->nom ?? '' ),
            'prenom'         => (string) ( $row->prenom ?? '' ),
            'email'          => (string) ( $row->email ?? '' ),
            'telephone'      => (string) ( $row->telephone ?? '' ),
            'etablissement'  => (string) ( $row->etablissement ?? '' ),
            'adresse'        => (string) ( $row->adresse ?? '' ),
            'code_postal'    => (string) ( $row->code_postal ?? '' ),
            'mundaneum'      => (int) ( $row->mundaneum ?? 0 ),
            'displayevent'   => (int) ( $row->displayEvent ?? 0 ),
            'displaycontact' => (int) ( $row->displayContact ?? 0 ),
        );
    }, $rows );

    return new WP_REST_Response( $result, 200 );
}
// --- PUT /auth/me/ateliers/{id} --- Modifier un atelier en attente --------

function olthem_rest_update_my_atelier( WP_REST_Request $request ) {
    $token = olthem_get_bearer_token();
    $user  = olthem_get_user_from_bearer_token( $token, $_ );
    if ( ! $user ) {
        return new WP_REST_Response( array( 'message' => 'Token invalide ou expire.' ), 401 );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'olthem_ateliers';
    $id    = (int) $request->get_param( 'id' );

    $atelier = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ) );
    if ( ! $atelier ) {
        return new WP_REST_Response( array( 'message' => 'Atelier introuvable.' ), 404 );
    }
    if ( (int) $atelier->user_id !== (int) $user->ID ) {
        return new WP_REST_Response( array( 'message' => 'Acces refuse.' ), 403 );
    }
    if ( ! empty( $atelier->valid_date ) ) {
        return new WP_REST_Response( array( 'message' => 'Cet atelier est deja confirme.' ), 422 );
    }

    $values  = $request->get_param( 'values' );
    if ( ! is_array( $values ) ) {
        return new WP_REST_Response( array( 'message' => 'Aucune valeur fournie.' ), 400 );
    }

    $allowed = array( 'nom', 'prenom', 'email', 'telephone', 'etablissement', 'adresse', 'localite', 'code_postal', 'mundaneum', 'start_date', 'end_date', 'nb_participants', 'thematique_id', 'displayEvent', 'displayContact' );
    $data    = array();
    $format  = array();

    foreach ( $allowed as $column ) {
        if ( ! array_key_exists( $column, $values ) ) continue;
        $v = $values[ $column ];
        if ( in_array( $column, array( 'mundaneum', 'displayEvent', 'displayContact' ), true ) ) {
            $data[ $column ] = (int) ( $v ? 1 : 0 );
            $format[]         = '%d';
        } elseif ( in_array( $column, array( 'nb_participants', 'thematique_id' ), true ) ) {
            $data[ $column ] = ( $v !== null && $v !== '' ) ? (int) $v : null;
            $format[]         = '%d';
        } else {
            $data[ $column ] = ( $v !== null && $v !== '' ) ? sanitize_text_field( wp_unslash( (string) $v ) ) : null;
            $format[]         = '%s';
        }
    }

    if ( empty( $data ) ) {
        return new WP_REST_Response( array( 'message' => 'Aucune colonne valide.' ), 400 );
    }

    $updated = $wpdb->update( $table, $data, array( 'id' => $id ), $format, array( '%d' ) );
    if ( false === $updated ) {
        return new WP_REST_Response( array( 'message' => 'Erreur lors de la mise a jour.' ), 500 );
    }

    return new WP_REST_Response( array( 'message' => 'Atelier mis a jour.', 'id' => $id ), 200 );
}

function olthem_rest_require_admin_user() {
    $token = olthem_get_bearer_token();
    $user  = olthem_get_user_from_bearer_token( $token, $_ );

    if ( ! $user ) {
        return new WP_Error( 'olthem_auth', 'Token invalide ou expire.', array( 'status' => 401 ) );
    }

    $is_admin_meta = (int) get_user_meta( $user->ID, 'is_admin', true ) === 1;
    $can_manage    = user_can( $user, 'manage_options' );

    if ( ! $is_admin_meta && ! $can_manage ) {
        return new WP_Error( 'olthem_forbidden', 'Acces reserve aux administrateurs.', array( 'status' => 403 ) );
    }

    return $user;
}

function olthem_track_event( $action, $user_id = null, $metadata = null ) {
    global $wpdb;
    $table = $wpdb->prefix . 'olthem_tracking';

    $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    if ( $table_exists !== $table ) {
        return false;
    }

    $data = array(
        'action'     => (string) $action,
        'user_id'    => null === $user_id ? null : (int) $user_id,
        'metadata'   => null === $metadata ? null : wp_json_encode( $metadata, JSON_UNESCAPED_UNICODE ),
    );

    $format = array( '%s', $user_id ? '%d' : '%s', $metadata ? '%s' : '%s' );

    return $wpdb->insert( $table, $data, $format );
}

function olthem_get_tracking_stats() {
    global $wpdb;
    $table = $wpdb->prefix . 'olthem_tracking';

    $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
    if ( $table_exists !== $table ) {
        return array(
            'supported' => false,
            'message'   => 'Table de tracking non presente.',
        );
    }

    $total_events = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    $total_sessions = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$table} WHERE user_id IS NOT NULL" );
    $today_events = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE DATE(created_at) = CURDATE()" );
    $last_7_days = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)" );
    $action_counts = $wpdb->get_results( "SELECT action, COUNT(*) AS total FROM {$table} GROUP BY action ORDER BY total DESC, action ASC LIMIT 8" );
    $recent_events = $wpdb->get_results(
        "SELECT t.action, t.created_at, t.metadata,
                u.display_name AS username
         FROM {$table} t
         LEFT JOIN {$wpdb->users} u ON u.ID = t.user_id
         ORDER BY t.created_at DESC, t.id DESC
         LIMIT 12"
    );

    return array(
        'supported' => true,
        'message'   => null,
        'counts'    => array(
            'total_events'   => $total_events,
            'total_sessions' => $total_sessions,
            'today_events'   => $today_events,
            'last_7_days'    => $last_7_days,
        ),
        'action_counts' => array_map( function( $row ) {
            return array(
                'action' => (string) ( $row->action ?? '' ),
                'total'  => (int) ( $row->total ?? 0 ),
            );
        }, (array) $action_counts ),
        'recent_events' => array_map( function( $row ) {
            $meta = json_decode( (string) ( $row->metadata ?? '' ), true );
            if ( ! is_array( $meta ) ) {
                $meta = array();
            }

            $meta_summary = array();
            foreach ( $meta as $key => $value ) {
                if ( is_scalar( $value ) ) {
                    $meta_summary[] = sanitize_text_field( (string) $key ) . ': ' . sanitize_text_field( (string) $value );
                }
            }

            return array(
                'action'       => (string) ( $row->action ?? '' ),
                'created_at'   => (string) ( $row->created_at ?? '' ),
                'username'     => (string) ( $row->username ?? '' ),
                'user_label'   => (string) ( $row->username ?? '' ),
                'meta_summary' => implode( ' | ', array_slice( $meta_summary, 0, 2 ) ),
            );
        }, (array) $recent_events ),
    );
}

function olthem_rest_admin_permission_callback() {
    $admin = olthem_rest_require_admin_user();
    return is_wp_error( $admin ) ? $admin : true;
}

function olthem_admin_parse_bool_or_null( $value ) {
    if ( null === $value || '' === $value ) {
        return null;
    }

    if ( is_bool( $value ) ) {
        return $value ? 1 : 0;
    }

    $raw = strtolower( trim( (string) $value ) );
    if ( in_array( $raw, array( '1', 'true', 'yes', 'oui' ), true ) ) {
        return 1;
    }
    if ( in_array( $raw, array( '0', 'false', 'no', 'non' ), true ) ) {
        return 0;
    }

    return null;
}

function olthem_admin_format_user_row( $row ) {
    $registered = isset( $row->user_registered ) ? (string) $row->user_registered : '';
    return array(
        'id'         => (int) ( $row->ID ?? 0 ),
        'username'   => (string) ( $row->username ?? '' ),
        'nom'        => (string) ( $row->nom ?? '' ),
        'prenom'     => (string) ( $row->prenom ?? '' ),
        'email'      => (string) ( $row->user_email ?? '' ),
        'created_at' => $registered,
        'newsletter' => (int) ( $row->newsletter ?? 0 ),
        'isAdmin'    => (int) ( $row->is_admin ?? 0 ),
        'role'       => (string) ( $row->role ?? '' ),
    );
}

function olthem_rest_admin_overview( WP_REST_Request $request ) {
    $admin = olthem_rest_require_admin_user();
    if ( is_wp_error( $admin ) ) {
        return new WP_REST_Response( array( 'message' => $admin->get_error_message() ), (int) ( $admin->get_error_data()['status'] ?? 403 ) );
    }

    global $wpdb;
    $table_ateliers = $wpdb->prefix . 'olthem_ateliers';
    $table_users    = $wpdb->users;
    $table_posts    = $wpdb->posts;

    $total_users = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_users}" );
    $total_ateliers = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_ateliers}" );
    $pending_ateliers = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_ateliers} WHERE valid_date IS NULL OR valid_date = '0000-00-00'" );
    $validated_ateliers = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_ateliers} WHERE valid_date IS NOT NULL AND valid_date <> '0000-00-00'" );

    $recent_users = $wpdb->get_results(
        "SELECT u.ID, u.user_email, u.user_registered,
                MAX(CASE WHEN um.meta_key = 'nickname' THEN um.meta_value END) AS username,
                MAX(CASE WHEN um.meta_key = 'first_name' THEN um.meta_value END) AS prenom,
                MAX(CASE WHEN um.meta_key = 'last_name' THEN um.meta_value END) AS nom,
                MAX(CASE WHEN um.meta_key = 'newsletter' THEN um.meta_value END) AS newsletter,
                MAX(CASE WHEN um.meta_key = 'is_admin' THEN um.meta_value END) AS is_admin,
                MAX(CASE WHEN um.meta_key = '{$wpdb->prefix}capabilities' THEN um.meta_value END) AS role
         FROM {$table_users} u
         LEFT JOIN {$wpdb->usermeta} um ON um.user_id = u.ID
         GROUP BY u.ID
         ORDER BY u.user_registered DESC
         LIMIT 10"
    );

    $recent_ateliers = $wpdb->get_results(
        "SELECT a.id, a.created_at, a.valid_date, a.start_date, a.end_date, a.etablissement, a.localite,
                a.nom, a.prenom, a.email,
                p.post_title AS thematique,
                u.display_name AS username
         FROM {$table_ateliers} a
         LEFT JOIN {$table_posts} p ON p.ID = a.thematique_id
         LEFT JOIN {$table_users} u ON u.ID = a.user_id
         ORDER BY a.created_at DESC
         LIMIT 5"
    );

    $user_items = array();
    foreach ( (array) $recent_users as $row ) {
        $item = olthem_admin_format_user_row( $row );
        $item['role'] = (int) $item['isAdmin'] ? 'administrator' : 'subscriber';
        $user_items[] = $item;
    }

    $atelier_items = array_map( function( $row ) {
        return array(
            'id'           => (int) ( $row->id ?? 0 ),
            'thematique'   => (string) ( $row->thematique ?? '' ),
            'username'     => (string) ( $row->username ?? '' ),
            'created_at'   => (string) ( $row->created_at ?? '' ),
            'start_date'   => (string) ( $row->start_date ?? '' ),
            'end_date'     => (string) ( $row->end_date ?? '' ),
            'valid_date'   => (string) ( $row->valid_date ?? '' ),
            'etablissement'=> (string) ( $row->etablissement ?? '' ),
            'localite'     => (string) ( $row->localite ?? '' ),
            'nom'          => (string) ( $row->nom ?? '' ),
            'prenom'       => (string) ( $row->prenom ?? '' ),
            'email'        => (string) ( $row->email ?? '' ),
        );
    }, (array) $recent_ateliers );

    $tracking_stats = olthem_get_tracking_stats();

    return new WP_REST_Response( array(
        'counts' => array(
            'users_total'        => $total_users,
            'ateliers_total'     => $total_ateliers,
            'ateliers_pending'   => $pending_ateliers,
            'ateliers_validated' => $validated_ateliers,
        ),
        'visits' => $tracking_stats,
        'latest_users'    => $user_items,
        'latest_ateliers' => $atelier_items,
    ), 200 );
}

function olthem_rest_admin_users( WP_REST_Request $request ) {
    $admin = olthem_rest_require_admin_user();
    if ( is_wp_error( $admin ) ) {
        return new WP_REST_Response( array( 'message' => $admin->get_error_message() ), (int) ( $admin->get_error_data()['status'] ?? 403 ) );
    }

    global $wpdb;

    $page    = max( 1, (int) $request->get_param( 'page' ) );
    $per_page = max( 1, min( 25, (int) ( $request->get_param( 'per_page' ) ?: 25 ) ) );
    $offset   = ( $page - 1 ) * $per_page;

    $sort_by = strtolower( (string) ( $request->get_param( 'sort_by' ) ?: 'created_at' ) );
    $sort_dir = strtoupper( (string) ( $request->get_param( 'sort_dir' ) ?: 'DESC' ) );
    if ( ! in_array( $sort_dir, array( 'ASC', 'DESC' ), true ) ) {
        $sort_dir = 'DESC';
    }

    $sort_map = array(
        'created_at' => 'u.user_registered',
        'username'   => 'username',
        'nom'        => 'nom',
        'prenom'     => 'prenom',
        'email'      => 'u.user_email',
        'newsletter' => 'newsletter',
        'isadmin'    => 'is_admin',
        'is_admin'   => 'is_admin',
    );
    $order_by = $sort_map[ $sort_by ] ?? 'u.user_registered';

    $having = array();
    $args  = array();

    $id = (int) ( $request->get_param( 'id' ) ?: 0 );
    if ( $id > 0 ) {
        $having[] = 'ID = %d';
        $args[] = $id;
    }

    $prefix_filters = array(
        'username' => 'nickname',
        'nom'      => 'last_name',
        'prenom'   => 'first_name',
    );
    foreach ( $prefix_filters as $param => $column ) {
        $value = trim( (string) ( $request->get_param( $param ) ?: '' ) );
        if ( '' === $value ) {
            continue;
        }
        $having[] = "{$column} LIKE %s";
        $args[] = $wpdb->esc_like( $value ) . '%';
    }

    $email = trim( (string) ( $request->get_param( 'email' ) ?: '' ) );
    if ( '' !== $email ) {
        $having[] = 'user_email LIKE %s';
        $args[] = $wpdb->esc_like( $email ) . '%';
    }

    $created_at = trim( (string) ( $request->get_param( 'created_at' ) ?: '' ) );
    if ( '' !== $created_at ) {
        $having[] = 'user_registered LIKE %s';
        $args[] = $wpdb->esc_like( $created_at ) . '%';
    }

    $newsletter = olthem_admin_parse_bool_or_null( $request->get_param( 'newsletter' ) );
    if ( null !== $newsletter ) {
        $having[] = 'newsletter = %d';
        $args[] = $newsletter;
    }

    $is_admin = olthem_admin_parse_bool_or_null( $request->get_param( 'is_admin' ) );
    if ( null === $is_admin ) {
        $is_admin = olthem_admin_parse_bool_or_null( $request->get_param( 'isAdmin' ) );
    }
    if ( null !== $is_admin ) {
        $having[] = 'is_admin = %d';
        $args[] = $is_admin;
    }

    $having_sql = $having ? ( 'HAVING ' . implode( ' AND ', $having ) ) : '';

    $sql = "SELECT SQL_CALC_FOUND_ROWS
                u.ID, u.user_email, u.user_registered,
                MAX(CASE WHEN um.meta_key = 'nickname' THEN um.meta_value END) AS username,
                MAX(CASE WHEN um.meta_key = 'first_name' THEN um.meta_value END) AS prenom,
                MAX(CASE WHEN um.meta_key = 'last_name' THEN um.meta_value END) AS nom,
                MAX(CASE WHEN um.meta_key = 'newsletter' THEN um.meta_value END) AS newsletter,
                MAX(CASE WHEN um.meta_key = 'is_admin' THEN um.meta_value END) AS is_admin
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um ON um.user_id = u.ID
            GROUP BY u.ID
            {$having_sql}
            ORDER BY {$order_by} {$sort_dir}
            LIMIT %d OFFSET %d";

    $query_args = array_merge( $args, array( $per_page, $offset ) );
    $prepared = $wpdb->prepare( $sql, $query_args );
    $rows = $wpdb->get_results( $prepared );
    $total = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );

    $items = array();
    foreach ( (array) $rows as $row ) {
        $item = olthem_admin_format_user_row( $row );
        $item['role'] = (int) $item['isAdmin'] ? 'administrator' : 'subscriber';
        $items[] = $item;
    }

    $total_pages = max( 1, (int) ceil( $total / $per_page ) );

    return new WP_REST_Response( array(
        'items'       => $items,
        'page'        => $page,
        'per_page'    => $per_page,
        'total'       => $total,
        'total_pages' => $total_pages,
        'sort_by'     => $sort_by,
        'sort_dir'    => $sort_dir,
    ), 200 );
}

function olthem_rest_admin_update_user( WP_REST_Request $request ) {
    $admin = olthem_rest_require_admin_user();
    if ( is_wp_error( $admin ) ) {
        return new WP_REST_Response( array( 'message' => $admin->get_error_message() ), (int) ( $admin->get_error_data()['status'] ?? 403 ) );
    }

    $user_id = (int) $request->get_param( 'id' );
    $target = get_user_by( 'id', $user_id );
    if ( ! $target ) {
        return new WP_REST_Response( array( 'message' => 'Utilisateur introuvable.' ), 404 );
    }

    $params = $request->get_json_params();
    if ( ! is_array( $params ) ) {
        $params = $request->get_params();
    }

    $username = array_key_exists( 'username', $params ) ? sanitize_text_field( (string) $params['username'] ) : null;
    $prenom   = array_key_exists( 'prenom', $params ) ? sanitize_text_field( (string) $params['prenom'] ) : null;
    $nom      = array_key_exists( 'nom', $params ) ? sanitize_text_field( (string) $params['nom'] ) : null;
    $email    = array_key_exists( 'email', $params ) ? sanitize_email( (string) $params['email'] ) : null;
    $newsletter = array_key_exists( 'newsletter', $params ) ? olthem_bool_to_int( $params['newsletter'] ) : null;

    $is_admin = null;
    if ( array_key_exists( 'isAdmin', $params ) ) {
        $is_admin = olthem_bool_to_int( $params['isAdmin'] );
    } elseif ( array_key_exists( 'is_admin', $params ) ) {
        $is_admin = olthem_bool_to_int( $params['is_admin'] );
    }

    if ( null !== $username && mb_strlen( $username ) < 2 ) {
        return new WP_REST_Response( array( 'code' => 'username_too_short', 'message' => 'Le nom d\'utilisateur doit contenir au moins 2 caracteres.' ), 400 );
    }

    if ( null !== $username ) {
        global $wpdb;
        $existing_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'nickname' AND meta_value = %s LIMIT 1",
            $username
        ) );
        if ( $existing_id && (int) $existing_id !== $user_id ) {
            return new WP_REST_Response( array( 'code' => 'username_exists', 'message' => 'Ce nom est deja pris.' ), 409 );
        }
    }

    if ( null !== $email ) {
        if ( ! is_email( $email ) ) {
            return new WP_REST_Response( array( 'code' => 'email_invalid', 'message' => 'Email invalide.' ), 400 );
        }
        $existing_email_id = email_exists( $email );
        if ( $existing_email_id && (int) $existing_email_id !== $user_id ) {
            return new WP_REST_Response( array( 'code' => 'email_exists', 'message' => 'Un compte existe deja avec cet email.' ), 409 );
        }
    }

    $wp_update = array( 'ID' => $user_id );
    if ( null !== $prenom ) { $wp_update['first_name'] = $prenom; }
    if ( null !== $nom ) { $wp_update['last_name'] = $nom; }
    if ( null !== $email ) { $wp_update['user_email'] = $email; }
    if ( null !== $username ) { $wp_update['display_name'] = $username; }

    $result = wp_update_user( $wp_update );
    if ( is_wp_error( $result ) ) {
        return new WP_REST_Response( array( 'message' => $result->get_error_message() ), 400 );
    }

    if ( null !== $username ) { update_user_meta( $user_id, 'nickname', $username ); }
    if ( null !== $prenom ) { update_user_meta( $user_id, 'first_name', $prenom ); }
    if ( null !== $nom ) { update_user_meta( $user_id, 'last_name', $nom ); }
    if ( null !== $newsletter ) { update_user_meta( $user_id, 'newsletter', $newsletter ); }
    if ( null !== $is_admin ) {
        update_user_meta( $user_id, 'is_admin', $is_admin );
        $target_user = get_user_by( 'id', $user_id );
        if ( $target_user ) {
            $target_user->set_role( $is_admin ? 'administrator' : 'subscriber' );
        }
    }

    olthem_upsert_custom_user_row( $user_id, array(
        'username'   => null !== $username ? $username : get_user_meta( $user_id, 'nickname', true ),
        'nom'        => null !== $nom ? $nom : get_user_meta( $user_id, 'last_name', true ),
        'prenom'     => null !== $prenom ? $prenom : get_user_meta( $user_id, 'first_name', true ),
        'newsletter' => null !== $newsletter ? $newsletter : get_user_meta( $user_id, 'newsletter', true ),
        'is_admin'   => null !== $is_admin ? $is_admin : get_user_meta( $user_id, 'is_admin', true ),
    ) );

    $fresh = get_user_by( 'id', $user_id );
    if ( ! $fresh ) {
        return new WP_REST_Response( array( 'message' => 'Utilisateur introuvable apres mise a jour.' ), 404 );
    }

    $payload = olthem_format_user_payload( $fresh );
    $payload['created_at'] = (string) $fresh->user_registered;
    return new WP_REST_Response( array( 'user' => $payload ), 200 );
}

function olthem_rest_admin_delete_user( WP_REST_Request $request ) {
    $admin = olthem_rest_require_admin_user();
    if ( is_wp_error( $admin ) ) {
        return new WP_REST_Response( array( 'message' => $admin->get_error_message() ), (int) ( $admin->get_error_data()['status'] ?? 403 ) );
    }

    $user_id = (int) $request->get_param( 'id' );
    if ( $user_id <= 0 ) {
        return new WP_REST_Response( array( 'message' => 'Identifiant invalide.' ), 400 );
    }

    if ( (int) $admin->ID === $user_id ) {
        return new WP_REST_Response( array( 'message' => 'Vous ne pouvez pas supprimer votre propre compte.' ), 422 );
    }

    require_once ABSPATH . 'wp-admin/includes/user.php';
    $deleted = wp_delete_user( $user_id );

    if ( ! $deleted ) {
        return new WP_REST_Response( array( 'message' => 'Suppression impossible.' ), 500 );
    }

    global $wpdb;
    $wpdb->delete( $wpdb->prefix . 'olthem_users', array( 'id' => $user_id ), array( '%d' ) );
    $wpdb->delete( $wpdb->prefix . 'olthem_ateliers', array( 'user_id' => $user_id ), array( '%d' ) );

    return new WP_REST_Response( array( 'message' => 'Utilisateur supprime.' ), 200 );
}

function olthem_rest_admin_ateliers( WP_REST_Request $request ) {
    $admin = olthem_rest_require_admin_user();
    if ( is_wp_error( $admin ) ) {
        return new WP_REST_Response( array( 'message' => $admin->get_error_message() ), (int) ( $admin->get_error_data()['status'] ?? 403 ) );
    }

    global $wpdb;
    $table_ateliers = $wpdb->prefix . 'olthem_ateliers';
    $table_posts    = $wpdb->posts;
    $table_users    = $wpdb->users;

    $page     = max( 1, (int) $request->get_param( 'page' ) );
    $per_page = max( 1, min( 10, (int) ( $request->get_param( 'per_page' ) ?: 10 ) ) );
    $offset   = ( $page - 1 ) * $per_page;

    $sort_by  = strtolower( (string) ( $request->get_param( 'sort_by' ) ?: 'created_at' ) );
    $sort_dir = strtoupper( (string) ( $request->get_param( 'sort_dir' ) ?: 'DESC' ) );
    if ( ! in_array( $sort_dir, array( 'ASC', 'DESC' ), true ) ) {
        $sort_dir = 'DESC';
    }

    $sort_map = array(
        'created_at' => 'a.created_at',
        'start_date' => 'a.start_date',
        'valid_date' => 'a.valid_date',
    );
    $order_by = $sort_map[ $sort_by ] ?? 'a.created_at';

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT SQL_CALC_FOUND_ROWS
                a.id, a.user_id, a.thematique_id, a.mundaneum, a.displayEvent, a.displayContact,
                a.etablissement, a.adresse, a.localite, a.code_postal,
                a.nom, a.prenom, a.email, a.telephone,
                a.start_date, a.end_date, a.valid_date, a.nb_participants, a.created_at,
                p.post_title AS thematique,
                u.display_name AS username
         FROM {$table_ateliers} a
         LEFT JOIN {$table_posts} p ON p.ID = a.thematique_id
         LEFT JOIN {$table_users} u ON u.ID = a.user_id
         ORDER BY {$order_by} {$sort_dir}
         LIMIT %d OFFSET %d",
        $per_page,
        $offset
    ) );

    $total = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );
    $total_pages = max( 1, (int) ceil( $total / $per_page ) );

    $items = array_map( function( $row ) {
        $lieu = '';
        if ( (int) ( $row->mundaneum ?? 0 ) ) {
            $lieu = 'Mundaneum, Rue de Nimy 76, 7000 Mons';
        } else {
            $parts = array_filter( array( $row->etablissement ?? '', $row->adresse ?? '' ) );
            $lieu  = implode( ', ', $parts );
        }

        return array(
            'id'              => (int) ( $row->id ?? 0 ),
            'user_id'         => (int) ( $row->user_id ?? 0 ),
            'username'        => (string) ( $row->username ?? '' ),
            'thematique_id'   => (int) ( $row->thematique_id ?? 0 ),
            'thematique'      => (string) ( $row->thematique ?? '' ),
            'start_date'      => (string) ( $row->start_date ?? '' ),
            'end_date'        => (string) ( $row->end_date ?? '' ),
            'valid_date'      => (string) ( $row->valid_date ?? '' ),
            'created_at'      => (string) ( $row->created_at ?? '' ),
            'lieu'            => $lieu,
            'localite'        => (string) ( $row->localite ?? '' ),
            'nom'             => (string) ( $row->nom ?? '' ),
            'prenom'          => (string) ( $row->prenom ?? '' ),
            'email'           => (string) ( $row->email ?? '' ),
            'telephone'       => (string) ( $row->telephone ?? '' ),
            'etablissement'   => (string) ( $row->etablissement ?? '' ),
            'adresse'         => (string) ( $row->adresse ?? '' ),
            'code_postal'     => (string) ( $row->code_postal ?? '' ),
            'mundaneum'       => (int) ( $row->mundaneum ?? 0 ),
            'displayEvent'    => (int) ( $row->displayEvent ?? 0 ),
            'displayContact'  => (int) ( $row->displayContact ?? 0 ),
            'nb_participants' => (int) ( $row->nb_participants ?? 0 ),
        );
    }, (array) $rows );

    return new WP_REST_Response( array(
        'items'       => $items,
        'page'        => $page,
        'per_page'    => $per_page,
        'total'       => $total,
        'total_pages' => $total_pages,
        'sort_by'     => $sort_by,
        'sort_dir'    => $sort_dir,
    ), 200 );
}

function olthem_rest_admin_update_atelier( WP_REST_Request $request ) {
    $admin = olthem_rest_require_admin_user();
    if ( is_wp_error( $admin ) ) {
        return new WP_REST_Response( array( 'message' => $admin->get_error_message() ), (int) ( $admin->get_error_data()['status'] ?? 403 ) );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'olthem_ateliers';
    $id    = (int) $request->get_param( 'id' );

    $atelier = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ) );
    if ( ! $atelier ) {
        return new WP_REST_Response( array( 'message' => 'Atelier introuvable.' ), 404 );
    }

    $values = $request->get_param( 'values' );
    if ( ! is_array( $values ) ) {
        return new WP_REST_Response( array( 'message' => 'Aucune valeur fournie.' ), 400 );
    }

    $allowed = array( 'nom', 'prenom', 'email', 'telephone', 'etablissement', 'adresse', 'localite', 'code_postal', 'mundaneum', 'start_date', 'end_date', 'valid_date', 'nb_participants', 'thematique_id', 'displayEvent', 'displayContact' );
    $data    = array();
    $format  = array();

    foreach ( $allowed as $column ) {
        if ( ! array_key_exists( $column, $values ) ) {
            continue;
        }

        $v = $values[ $column ];
        if ( in_array( $column, array( 'mundaneum', 'displayEvent', 'displayContact' ), true ) ) {
            $data[ $column ] = (int) ( $v ? 1 : 0 );
            $format[] = '%d';
        } elseif ( in_array( $column, array( 'nb_participants', 'thematique_id' ), true ) ) {
            $data[ $column ] = ( $v !== null && $v !== '' ) ? (int) $v : null;
            $format[] = '%d';
        } else {
            $data[ $column ] = ( $v !== null && $v !== '' ) ? sanitize_text_field( wp_unslash( (string) $v ) ) : null;
            $format[] = '%s';
        }
    }

    if ( empty( $data ) ) {
        return new WP_REST_Response( array( 'message' => 'Aucune colonne valide.' ), 400 );
    }

    $updated = $wpdb->update( $table, $data, array( 'id' => $id ), $format, array( '%d' ) );
    if ( false === $updated ) {
        return new WP_REST_Response( array( 'message' => 'Erreur lors de la mise a jour.' ), 500 );
    }

    return new WP_REST_Response( array( 'message' => 'Atelier mis a jour.', 'id' => $id ), 200 );
}

function olthem_rest_admin_delete_atelier( WP_REST_Request $request ) {
    $admin = olthem_rest_require_admin_user();
    if ( is_wp_error( $admin ) ) {
        return new WP_REST_Response( array( 'message' => $admin->get_error_message() ), (int) ( $admin->get_error_data()['status'] ?? 403 ) );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'olthem_ateliers';
    $id    = (int) $request->get_param( 'id' );

    if ( $id <= 0 ) {
        return new WP_REST_Response( array( 'message' => 'Identifiant invalide.' ), 400 );
    }

    $deleted = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
    if ( false === $deleted ) {
        return new WP_REST_Response( array( 'message' => 'Suppression impossible.' ), 500 );
    }

    return new WP_REST_Response( array( 'message' => 'Atelier supprime.', 'id' => $id ), 200 );
}


// --- Enregistrement des routes -----------------------------------------------

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

    register_rest_route( 'olthem/v1', '/forms/submit', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'olthem_rest_form_submit',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'olthem/v1', '/auth/forgot-password', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'olthem_rest_forgot_password',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'olthem/v1', '/auth/reset-password', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'olthem_rest_reset_password',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'olthem/v1', '/auth/me', array(
        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => 'olthem_rest_update_me',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'olthem/v1', '/auth/me/ateliers', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'olthem_rest_my_ateliers',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'olthem/v1', '/auth/me/ateliers/(?P<id>\d+)', array(
        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => 'olthem_rest_update_my_atelier',
        'permission_callback' => '__return_true',
        'args'                => array( 'id' => array( 'validate_callback' => function( $value ) { return is_numeric( $value ); } ) ),
    ) );

    register_rest_route( 'olthem/v1', '/auth/check-username', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'olthem_rest_check_username',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'olthem/v1', '/admin/overview', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'olthem_rest_admin_overview',
        'permission_callback' => 'olthem_rest_admin_permission_callback',
    ) );

    register_rest_route( 'olthem/v1', '/admin/users', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'olthem_rest_admin_users',
        'permission_callback' => 'olthem_rest_admin_permission_callback',
    ) );

    register_rest_route( 'olthem/v1', '/admin/users/(?P<id>\d+)', array(
        array(
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => 'olthem_rest_admin_update_user',
            'permission_callback' => 'olthem_rest_admin_permission_callback',
        ),
        array(
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => 'olthem_rest_admin_delete_user',
            'permission_callback' => 'olthem_rest_admin_permission_callback',
        ),
    ) );

    register_rest_route( 'olthem/v1', '/admin/ateliers', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'olthem_rest_admin_ateliers',
        'permission_callback' => 'olthem_rest_admin_permission_callback',
    ) );

    register_rest_route( 'olthem/v1', '/admin/ateliers/(?P<id>\d+)', array(
        array(
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => 'olthem_rest_admin_update_atelier',
            'permission_callback' => 'olthem_rest_admin_permission_callback',
        ),
        array(
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => 'olthem_rest_admin_delete_atelier',
            'permission_callback' => 'olthem_rest_admin_permission_callback',
        ),
    ) );
}
add_action( 'rest_api_init', 'olthem_register_auth_rest_routes' );


// --- Back-office : page Mailing ---

// --- Mailing : helpers ---

function olthem_get_mailing_template( $declencheur ) {
    global $wpdb;
    $table = $wpdb->prefix . 'olthem_email_templates';
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE declencheur = %s LIMIT 1", $declencheur ) );
}

function olthem_send_mailing_template( $declencheur, $tokens = array() ) {
    $tpl = olthem_get_mailing_template( $declencheur );
    if ( ! $tpl ) return false;

    $sujet = (string) $tpl->sujet;
    $corps = (string) $tpl->corps;

    $corps = wpautop( $corps );

    foreach ( $tokens as $key => $value ) {
        $sujet = str_replace( '[' . $key . ']', (string) $value, $sujet );
        $corps = str_replace( '[' . $key . ']', (string) $value, $corps );
    }

    return array( 'sujet' => $sujet, 'corps' => $corps );
}

function olthem_notify_admins_atelier_created( $atelier_id ) {
    global $wpdb;
    $table_users    = $wpdb->prefix . 'olthem_users';
    $table_ateliers = $wpdb->prefix . 'olthem_ateliers';
    $table_posts    = $wpdb->posts;

    error_log( '[OLTHEM] notify_admins called for atelier_id=' . $atelier_id );

    $atelier = $wpdb->get_row( $wpdb->prepare(
        "SELECT a.*, p.post_title AS thematique_title, u.username AS user_username
         FROM {$table_ateliers} a
         LEFT JOIN {$table_posts} p ON p.ID = a.thematique_id
         LEFT JOIN {$table_users} u ON u.id = a.user_id
         WHERE a.id = %d LIMIT 1",
        (int) $atelier_id
    ) );

    if ( ! $atelier ) {
        error_log( '[OLTHEM] notify_admins: atelier not found id=' . $atelier_id );
        return;
    }

    $addr_parts = array_filter( array( (string) $atelier->code_postal, (string) $atelier->localite ) );
    $addr       = implode( ' ', $addr_parts );
    $lieu_parts = array_filter( array( (string) $atelier->etablissement, implode( ' - ', array_filter( array( (string) $atelier->adresse, $addr ) ) ) ) );
    $lieu       = (int) $atelier->mundaneum
        ? 'MUNDANEUM, Rue de Nimy, 76 - 7000 Mons'
        : implode( ', ', $lieu_parts );

    $tokens = array(
        'USERNAME'      => (string) $atelier->user_username,
        'LIEU'          => $lieu,
        'prenom'        => (string) $atelier->prenom,
        'nom'           => (string) $atelier->nom,
        'email'         => (string) $atelier->email,
        'telephone'     => (string) $atelier->telephone,
        'etablissement' => (string) $atelier->etablissement,
        'adresse'       => (string) $atelier->adresse,
        'localite'      => (string) $atelier->localite,
        'code_postal'   => (string) $atelier->code_postal,
        'start_date'    => (string) $atelier->start_date,
        'end_date'      => (string) $atelier->end_date,
        'THEMATIQUE'    => (string) $atelier->thematique_title,
        'nb_participants' => (string) $atelier->nb_participants,
        'ATELIER_ID'    => (string) $atelier->id,
    );

    $mail = olthem_send_mailing_template( 'atelier_admin', $tokens );
    if ( ! $mail ) {
        error_log( '[OLTHEM] notify_admins: no template found for atelier_admin' );
        return;
    }

    $admins = $wpdb->get_results( "SELECT email FROM {$table_users} WHERE is_admin = 1" );
    error_log( '[OLTHEM] notify_admins: ' . count( $admins ) . ' admin(s) found' );
    foreach ( $admins as $admin ) {
        $sent = wp_mail(
            (string) $admin->email,
            $mail['sujet'],
            $mail['corps'],
            array( 'Content-Type: text/html; charset=UTF-8' )
        );
        error_log( '[OLTHEM] notify_admins: wp_mail to ' . $admin->email . ' => ' . ( $sent ? 'OK' : 'FAIL' ) );
    }
}
// --- Mailing : creer un template ---

function olthem_handle_create_email_template() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Acces refuse.' ); }
    check_admin_referer( 'olthem_create_email_template' );
    global $wpdb;
    $table = $wpdb->prefix . 'olthem_email_templates';
    $nom          = sanitize_text_field( wp_unslash( $_POST['nom'] ?? '' ) );
    $declencheur  = sanitize_text_field( wp_unslash( $_POST['declencheur'] ?? '' ) );
    $sujet        = sanitize_text_field( wp_unslash( $_POST['sujet'] ?? '' ) );
    $corps        = wp_kses_post( wp_unslash( $_POST['corps'] ?? '' ) );
    if ( '' === $nom || '' === $sujet ) {
        wp_safe_redirect( add_query_arg( array( 'page' => 'olthem-mailing', 'olthem_notice' => 'missing_fields' ), admin_url( 'admin.php' ) ) );
        exit;
    }
    $ok = $wpdb->insert( $table, compact( 'nom', 'declencheur', 'sujet', 'corps' ), array( '%s', '%s', '%s', '%s' ) );
    wp_safe_redirect( add_query_arg( array( 'page' => 'olthem-mailing', 'olthem_notice' => $ok ? 'tpl_created' : 'db_error' ), admin_url( 'admin.php' ) ) );
    exit;
}
add_action( 'admin_post_olthem_create_email_template', 'olthem_handle_create_email_template' );

// --- Mailing : modifier un template ---

function olthem_handle_update_email_template() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Acces refuse.' ); }
    check_admin_referer( 'olthem_update_email_template' );
    global $wpdb;
    $table       = $wpdb->prefix . 'olthem_email_templates';
    $tpl_id      = (int) ( $_POST['tpl_id'] ?? 0 );
    $nom         = sanitize_text_field( wp_unslash( $_POST['nom'] ?? '' ) );
    $declencheur = sanitize_text_field( wp_unslash( $_POST['declencheur'] ?? '' ) );
    $sujet       = sanitize_text_field( wp_unslash( $_POST['sujet'] ?? '' ) );
    $corps       = wp_kses_post( wp_unslash( $_POST['corps'] ?? '' ) );
    if ( $tpl_id <= 0 || '' === $nom || '' === $sujet ) {
        wp_safe_redirect( add_query_arg( array( 'page' => 'olthem-mailing', 'olthem_notice' => 'missing_fields' ), admin_url( 'admin.php' ) ) );
        exit;
    }
    $ok = $wpdb->update( $table, compact( 'nom', 'declencheur', 'sujet', 'corps' ), array( 'id' => $tpl_id ), array( '%s', '%s', '%s', '%s' ), array( '%d' ) );
    wp_safe_redirect( add_query_arg( array( 'page' => 'olthem-mailing', 'olthem_notice' => ( false !== $ok ) ? 'tpl_updated' : 'db_error' ), admin_url( 'admin.php' ) ) );
    exit;
}
add_action( 'admin_post_olthem_update_email_template', 'olthem_handle_update_email_template' );

// --- Mailing : supprimer un template ---

function olthem_handle_delete_email_template() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Acces refuse.' ); }
    check_admin_referer( 'olthem_delete_email_template' );
    global $wpdb;
    $table  = $wpdb->prefix . 'olthem_email_templates';
    $tpl_id = (int) ( $_POST['tpl_id'] ?? 0 );
    if ( $tpl_id > 0 ) { $wpdb->delete( $table, array( 'id' => $tpl_id ), array( '%d' ) ); }
    wp_safe_redirect( add_query_arg( array( 'page' => 'olthem-mailing', 'olthem_notice' => 'tpl_deleted' ), admin_url( 'admin.php' ) ) );
    exit;
}
add_action( 'admin_post_olthem_delete_email_template', 'olthem_handle_delete_email_template' );

// --- Mailing : envoyer une newsletter ---

function olthem_handle_send_newsletter() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Acces refuse.' ); }
    check_admin_referer( 'olthem_send_newsletter' );
    global $wpdb;
    $table_users = $wpdb->prefix . 'olthem_users';
    $table_nl    = $wpdb->prefix . 'olthem_newsletters';
    $sujet       = sanitize_text_field( wp_unslash( $_POST['sujet'] ?? '' ) );
    $corps       = wp_kses_post( wp_unslash( $_POST['corps'] ?? '' ) );
    if ( '' === $sujet || '' === $corps ) {
        wp_safe_redirect( add_query_arg( array( 'page' => 'olthem-mailing', 'tab' => 'newsletter', 'olthem_notice' => 'missing_fields' ), admin_url( 'admin.php' ) ) );
        exit;
    }
    $abonnes = $wpdb->get_results( "SELECT email FROM {$table_users} WHERE newsletter = 1" );
    $sent    = 0;
    foreach ( $abonnes as $row ) {
        $ok = wp_mail( (string) $row->email, $sujet, $corps, array( 'Content-Type: text/html; charset=UTF-8' ) );
        if ( $ok ) { $sent++; }
    }
    $wpdb->insert( $table_nl, array( 'sujet' => $sujet, 'corps' => $corps, 'nb_destinataires' => $sent ), array( '%s', '%s', '%d' ) );
    wp_safe_redirect( add_query_arg( array( 'page' => 'olthem-mailing', 'tab' => 'newsletter', 'olthem_notice' => 'nl_sent', 'count' => $sent ), admin_url( 'admin.php' ) ) );
    exit;
}
add_action( 'admin_post_olthem_send_newsletter', 'olthem_handle_send_newsletter' );
function olthem_register_mailing_admin_page() {
    add_menu_page(
        'Mailing',
        'Mailing',
        'manage_options',
        'olthem-mailing',
        'olthem_render_mailing_admin_page',
        'dashicons-email-alt',
        27
    );
}
add_action( 'admin_menu', 'olthem_register_mailing_admin_page' );

function olthem_render_mailing_admin_page() {
    global $wpdb;
    $table_tpl    = $wpdb->prefix . 'olthem_email_templates';
    $table_nl     = $wpdb->prefix . 'olthem_newsletters';
    $table_users  = $wpdb->prefix . 'olthem_users';

    // Onglet actif
    $active_tab = ( isset( $_GET['tab'] ) && 'newsletter' === $_GET['tab'] ) ? 'newsletter' : 'auto';

    // Mode edition template
    $is_edit_tpl = ( 'auto' === $active_tab && isset( $_GET['action'] ) && 'edit' === $_GET['action'] && isset( $_GET['tpl_id'] ) );
    $tpl_to_edit = null;
    if ( $is_edit_tpl ) {
        $tpl_to_edit = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_tpl} WHERE id = %d LIMIT 1", (int) $_GET['tpl_id'] ) );
        if ( ! $tpl_to_edit ) { $is_edit_tpl = false; }
    }

    echo '<div class="wrap">';
    echo '<h1>Mailing</h1>';

    // Notices
    if ( isset( $_GET['olthem_notice'] ) ) {
        $notice = sanitize_text_field( wp_unslash( $_GET['olthem_notice'] ) );
        $map = array(
            'tpl_created'   => array( 'success', 'Template cree avec succes.' ),
            'tpl_updated'   => array( 'success', 'Template modifie avec succes.' ),
            'tpl_deleted'   => array( 'success', 'Template supprime.' ),
            'missing_fields'=> array( 'error',   'Les champs Nom et Sujet sont obligatoires.' ),
            'db_error'      => array( 'error',   'Erreur base de donnees.' ),
            'nl_sent'       => array( 'success', 'Newsletter envoyee a ' . (int) ( $_GET['count'] ?? 0 ) . ' abonne(s).' ),
        );
        if ( isset( $map[ $notice ] ) ) {
            echo '<div class="notice notice-' . esc_attr( $map[$notice][0] ) . ' is-dismissible"><p>' . esc_html( $map[$notice][1] ) . '</p></div>';
        }
    }

    // Onglets
    $url_auto = admin_url( 'admin.php?page=olthem-mailing' );
    $url_nl   = admin_url( 'admin.php?page=olthem-mailing&tab=newsletter' );
    echo '<nav class="nav-tab-wrapper">';
    echo '<a href="' . esc_url( $url_auto ) . '" class="nav-tab' . ( 'auto' === $active_tab ? ' nav-tab-active' : '' ) . '">Emails automatiques</a>';
    echo '<a href="' . esc_url( $url_nl ) . '" class="nav-tab' . ( 'newsletter' === $active_tab ? ' nav-tab-active' : '' ) . '">Newsletter</a>';
    echo '</nav>';

    // =========================================================================
    // ONGLET EMAILS AUTOMATIQUES
    // =========================================================================
    if ( 'auto' === $active_tab ) {
        echo '<div style="margin-top:20px">';

        $declencheurs = array(
            ''         => '-- Aucun --',
            'inscription' => 'Confirmation inscription',
            'atelier'     => 'Confirmation atelier',
            'reset_password' => 'Reinitialisation mot de passe',
            'atelier_admin'  => 'Notification admin - Nouvel atelier',
        );

        // Formulaire edition
        if ( $is_edit_tpl && $tpl_to_edit ) {
            echo '<h2>Modifier le template &laquo;' . esc_html( (string) $tpl_to_edit->nom ) . '&raquo;</h2>';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
            wp_nonce_field( 'olthem_update_email_template' );
            echo '<input type="hidden" name="action" value="olthem_update_email_template" />';
            echo '<input type="hidden" name="tpl_id" value="' . esc_attr( (string) $tpl_to_edit->id ) . '" />';
            echo '<table class="form-table" role="presentation">';
            echo '<tr><th><label for="tpl_nom">Nom interne</label></th><td><input required type="text" id="tpl_nom" name="nom" value="' . esc_attr( (string) $tpl_to_edit->nom ) . '" class="regular-text" /></td></tr>';
            echo '<tr><th><label for="tpl_declencheur">Declencheur</label></th><td><select id="tpl_declencheur" name="declencheur">';
            foreach ( $declencheurs as $val => $label ) {
                $sel = ( (string) $val === (string) $tpl_to_edit->declencheur ) ? ' selected' : '';
                echo '<option value="' . esc_attr( $val ) . '"' . $sel . '>' . esc_html( $label ) . '</option>';
            }
            echo '</select></td></tr>';
            echo '<tr><th><label for="tpl_sujet">Sujet</label></th><td><input required type="text" id="tpl_sujet" name="sujet" value="' . esc_attr( (string) $tpl_to_edit->sujet ) . '" class="large-text" /></td></tr>';
            echo '<tr><th><label for="tpl_corps">Corps (HTML)</label></th><td><textarea id="tpl_corps" name="corps" rows="12" class="large-text">' . esc_textarea( (string) $tpl_to_edit->corps ) . '</textarea><p class="description">Tokens disponibles : [USERNAME], [prenom], [nom], [email]</p></td></tr>';
            echo '</table>';
            submit_button( 'Enregistrer' );
            echo '</form>';
            echo '<p><a href="' . esc_url( $url_auto ) . '">&larr; Retour a la liste</a></p>';
            echo '<hr />';
        }

        // Formulaire creation (masque si mode edition)
        if ( ! $is_edit_tpl ) {
            echo '<h2>Creer un template</h2>';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
            wp_nonce_field( 'olthem_create_email_template' );
            echo '<input type="hidden" name="action" value="olthem_create_email_template" />';
            echo '<table class="form-table" role="presentation">';
            echo '<tr><th><label for="new_nom">Nom interne</label></th><td><input required type="text" id="new_nom" name="nom" class="regular-text" placeholder="ex: confirmation_atelier" /></td></tr>';
            echo '<tr><th><label for="new_declencheur">Declencheur</label></th><td><select id="new_declencheur" name="declencheur">';
            foreach ( $declencheurs as $val => $label ) {
                echo '<option value="' . esc_attr( $val ) . '">' . esc_html( $label ) . '</option>';
            }
            echo '</select><p class="description">A quel moment cet email est-il envoye automatiquement ?</p></td></tr>';
            echo '<tr><th><label for="new_sujet">Sujet</label></th><td><input required type="text" id="new_sujet" name="sujet" class="large-text" /></td></tr>';
            echo '<tr><th><label for="new_corps">Corps (HTML)</label></th><td><textarea id="new_corps" name="corps" rows="12" class="large-text"></textarea><p class="description">Tokens disponibles : [USERNAME], [prenom], [nom], [email]</p></td></tr>';
            echo '</table>';
            submit_button( 'Creer le template' );
            echo '</form>';
            echo '<hr />';
        }

        // Liste des templates
        echo '<h2>Templates existants</h2>';
        $templates = $wpdb->get_results( "SELECT * FROM {$table_tpl} ORDER BY declencheur ASC, nom ASC" );
        if ( empty( $templates ) ) {
            echo '<p>Aucun template pour le moment.</p>';
        } else {
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr><th>ID</th><th>Nom</th><th>Declencheur</th><th>Sujet</th><th>Cree le</th><th>Actions</th></tr></thead>';
            echo '<tbody>';
            foreach ( $templates as $tpl ) {
                $edit_url_tpl   = add_query_arg( array( 'page' => 'olthem-mailing', 'action' => 'edit', 'tpl_id' => (int) $tpl->id ), admin_url( 'admin.php' ) );
                echo '<tr>';
                echo '<td>' . esc_html( (string) $tpl->id ) . '</td>';
                echo '<td>' . esc_html( (string) $tpl->nom ) . '</td>';
                echo '<td>' . esc_html( (string) $tpl->declencheur ) . '</td>';
                echo '<td>' . esc_html( (string) $tpl->sujet ) . '</td>';
                echo '<td>' . esc_html( (string) $tpl->created_at ) . '</td>';
                echo '<td>';
                echo '<a href="' . esc_url( $edit_url_tpl ) . '" style="margin-right:8px">Modifier</a>';
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline" onsubmit="return confirm(\'Supprimer ce template ?\')">';
                wp_nonce_field( 'olthem_delete_email_template' );
                echo '<input type="hidden" name="action" value="olthem_delete_email_template" />';
                echo '<input type="hidden" name="tpl_id" value="' . esc_attr( (string) $tpl->id ) . '" />';
                echo '<button type="submit" class="button-link-delete" style="color:#a00">Supprimer</button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
    }

    // =========================================================================
    // ONGLET NEWSLETTER
    // =========================================================================
    if ( 'newsletter' === $active_tab ) {
        echo '<div style="margin-top:20px">';

        $nb_abonnes = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_users} WHERE newsletter = 1" );
        echo '<p><strong>' . $nb_abonnes . ' abonne(s)</strong> recevront la newsletter.</p>';

        echo '<h2>Composer et envoyer</h2>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'olthem_send_newsletter' );
        echo '<input type="hidden" name="action" value="olthem_send_newsletter" />';
        echo '<table class="form-table" role="presentation">';
        echo '<tr><th><label for="nl_sujet">Sujet</label></th><td><input required type="text" id="nl_sujet" name="sujet" class="large-text" /></td></tr>';
        echo '<tr><th><label for="nl_corps">Corps (HTML)</label></th><td><textarea required id="nl_corps" name="corps" rows="16" class="large-text"></textarea></td></tr>';
        echo '</table>';
        submit_button( 'Envoyer a tous les abonnes', 'primary', 'submit', true, array( 'onclick' => "return confirm('Envoyer la newsletter a " . $nb_abonnes . " abonne(s) ?')" ) );
        echo '</form>';
        echo '<hr />';

        // Historique
        echo '<h2>Historique des envois</h2>';
        $history = $wpdb->get_results( "SELECT * FROM {$table_nl} ORDER BY envoye_le DESC" );
        if ( empty( $history ) ) {
            echo '<p>Aucun envoi pour le moment.</p>';
        } else {
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr><th>ID</th><th>Sujet</th><th>Destinataires</th><th>Envoye le</th></tr></thead>';
            echo '<tbody>';
            foreach ( $history as $nl ) {
                echo '<tr>';
                echo '<td>' . esc_html( (string) $nl->id ) . '</td>';
                echo '<td>' . esc_html( (string) $nl->sujet ) . '</td>';
                echo '<td>' . esc_html( (string) $nl->nb_destinataires ) . '</td>';
                echo '<td>' . esc_html( (string) $nl->envoye_le ) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
    }

    echo '</div>';
}
