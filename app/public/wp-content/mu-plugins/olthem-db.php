<?php
/**
 * olthem-db.php — Structure de base de données.
 *
 * Responsabilité : création et migration des tables olthem_users et
 * olthem_ateliers. Aucun seeding. Ne jamais dropper de tables en prod.
 *
 * Tables gérées :
 *   - wp_olthem_users    : comptes utilisateurs propriétaires
 *   - wp_olthem_ateliers : demandes d'ateliers
 *
 * Voir aussi :
 *   olthem-auth.php     — tokens Bearer + endpoints REST auth
 *   olthem-api-rest.php — API REST publique et admin
 *   olthem-admin.php    — back-office WP (colonnes, profil)
 *   olthem-headless.php — CPT, ACF, CORS, contenu REST
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ─── Version de la structure BDD ────────────────────────────────────────────
define( 'OLTHEM_DB_VERSION', '1.6.0' );


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
        last_name   VARCHAR(100) NOT NULL,
        first_name  VARCHAR(100) NOT NULL,
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

        $sql_ateliers = "CREATE TABLE $table_ateliers (
            id                   BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id              BIGINT(20) UNSIGNED DEFAULT NULL,
            thematic_id          BIGINT(20) UNSIGNED DEFAULT NULL,
            mundaneum            TINYINT(1)          NOT NULL DEFAULT 0,
            institution          VARCHAR(255)        DEFAULT NULL,
            address              VARCHAR(255)        DEFAULT NULL,
            city                 VARCHAR(100)        DEFAULT NULL,
            postal_code          VARCHAR(10)         DEFAULT NULL,
            is_registered_user   TINYINT(1)          NOT NULL DEFAULT 0,
            last_name            VARCHAR(100)        DEFAULT NULL,
            first_name           VARCHAR(100)        DEFAULT NULL,
            email                VARCHAR(254)        DEFAULT NULL,
            phone                VARCHAR(30)         DEFAULT NULL,
            start_date           DATE                DEFAULT NULL,
            end_date             DATE                DEFAULT NULL,
            valid_date           DATE                DEFAULT NULL,
            participants_count   INT                 DEFAULT NULL,
            share_contact        TINYINT(1)          NOT NULL DEFAULT 0,
            latitude             DECIMAL(10,7)       DEFAULT NULL,
            longitude            DECIMAL(10,7)       DEFAULT NULL,
            created_at           DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY thematic_id (thematic_id)
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
                FOREIGN KEY (thematic_id) REFERENCES {$table_posts}(ID)
                ON DELETE SET NULL
                ON UPDATE CASCADE" );
        }

    // ── Migration 1.4.0 : colonnes map (ALTER TABLE pour prod, ignoré si déjà présentes)
    $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$table_ateliers}" );
    if ( ! in_array( 'share_contact', $cols, true ) ) {
        $wpdb->query( "ALTER TABLE {$table_ateliers} ADD COLUMN share_contact TINYINT(1) NOT NULL DEFAULT 0" );
    }
    if ( ! in_array( 'latitude', $cols, true ) ) {
        $wpdb->query( "ALTER TABLE {$table_ateliers} ADD COLUMN latitude DECIMAL(10,7) DEFAULT NULL" );
    }
    if ( ! in_array( 'longitude', $cols, true ) ) {
        $wpdb->query( "ALTER TABLE {$table_ateliers} ADD COLUMN longitude DECIMAL(10,7) DEFAULT NULL" );
    }

    // ── olthem_email_templates ────────────────────────────────────────────────
    $table_email_templates = $wpdb->prefix . 'olthem_email_templates';
    $sql_email_templates = "CREATE TABLE $table_email_templates (
        id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        name       VARCHAR(100)        NOT NULL,
        event_key  VARCHAR(50)         NOT NULL,
        subject    VARCHAR(255)        NOT NULL,
        body       LONGTEXT            NOT NULL,
        created_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY event_key (event_key)
    ) $charset_collate;";
    dbDelta( $sql_email_templates );

    // Données initiales : templates email par défaut (uniquement si la table est vide).
    $tpl_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_email_templates}" );
    if ( 0 === $tpl_count ) {
        $wpdb->insert( $table_email_templates, array(
            'name'      => 'création_atelier',
            'event_key' => 'atelier_admin',
            'subject'   => "Demande d'atelier reçue",
            'body'      => "L'utilisateur [USERNAME] vient de créer une demande de programmation d'atelier.\n\nLieu de l'événement : [LIEU]\n\nThématique de l'atelier : [THEMATIQUE]\nPériode de programmation souhaitée : entre le [start_date] et le [end_date]\nNombre de participants estimé : +[participants_count]\n\nPersonne de contact : [first_name] [last_name]\nAdresse e-mail : [email]\nTéléphone : [phone]",
        ), array( '%s', '%s', '%s', '%s' ) );
        $wpdb->insert( $table_email_templates, array(
            'name'      => 'reset_password',
            'event_key' => 'reset_password',
            'subject'   => 'Othem - Réinitialisation de mot de passe',
            'body'      => "Bonjour [first_name] [last_name],\n\nVous avez demandé la réinitialisation de votre mot de passe.\nCliquez sur le lien suivant pour choisir un nouveau mot de passe (valable 1 heure) :\n\n[RESET_LINK]\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez cet email.",
        ), array( '%s', '%s', '%s', '%s' ) );
    }

    // ── olthem_newsletters ────────────────────────────────────────────────────
    $table_newsletters = $wpdb->prefix . 'olthem_newsletters';
    $sql_newsletters = "CREATE TABLE $table_newsletters (
        id               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        subject          VARCHAR(255)        NOT NULL,
        body             LONGTEXT            NOT NULL,
        recipients_count INT                 NOT NULL DEFAULT 0,
        sent_at          DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    dbDelta( $sql_newsletters );

    update_option( 'olthem_db_version', OLTHEM_DB_VERSION );
}

function olthem_maybe_run_migration() {
    if ( get_option( 'olthem_db_version' ) !== OLTHEM_DB_VERSION ) {
        olthem_create_tables();
    }
}
add_action( 'init', 'olthem_maybe_run_migration', 1 );


// ─── Cohérence ateliers : identité utilisateur connecté ───────────────────

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
         SET a.last_name = u.last_name,
             a.first_name = u.first_name,
             a.email = u.email
         WHERE a.is_registered_user = 1"
    );
}
add_action( 'init', 'olthem_sync_connected_ateliers_user_identity', 8 );


