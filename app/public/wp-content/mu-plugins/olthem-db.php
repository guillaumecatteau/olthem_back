<?php
/**
 * olthem-db.php — Structure de base de données.
 *
 * Responsabilité : création / migration des tables, seeding dev.
 *
 * Voir aussi :
 *   olthem-auth.php   — tokens API + REST auth.
 *   olthem-admin.php  — back-office WP (colonnes, profil, ateliers).
 *   olthem-headless.php — CPT, ACF, CORS, REST content.
 */
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

        // En développement (WP_DEBUG=true) : DROP + recréation complète de la table.
        // Utile quand la structure change (dbDelta ne supprime pas les colonnes).
        // En production : mettre WP_DEBUG=false ou commenter ces deux lignes
        // et gérer les changements de schéma avec des ALTER TABLE explicites.
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $wpdb->query( "DROP TABLE IF EXISTS {$table_ateliers}" );
        }

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


