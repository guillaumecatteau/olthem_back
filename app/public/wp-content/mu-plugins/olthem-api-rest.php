<?php

/**
 * olthem-api-rest.php — Endpoints REST front (admin + formulaires).
 *
 * Routes enregistrées :
 *   GET  /olthem/v1/admin/overview          — tableau de bord admin
 *   GET  /olthem/v1/admin/users             — liste paginée/filtrée
 *   PUT  /olthem/v1/admin/users/{id}        — modifier un utilisateur
 *   DELETE /olthem/v1/admin/users/{id}      — supprimer un utilisateur
 *   GET  /olthem/v1/admin/ateliers          — liste paginée/filtrée
 *   PUT  /olthem/v1/admin/ateliers/{id}     — modifier un atelier
 *   DELETE /olthem/v1/admin/ateliers/{id}   — supprimer un atelier
 *   POST /olthem/v1/forms/submit            — soumission de formulaire builder
 *
 * Toutes les routes admin exigent un token Bearer d'un utilisateur isAdmin=1.
 *
 * Voir aussi :
 *   olthem-auth.php  — tokens + routes /auth/*.
 *   olthem-db.php    — structure des tables.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ─── Middleware : vérifie le token + le flag is_admin ────────────────────────

function olthem_require_admin_token(): ?WP_User {
    $token = olthem_get_bearer_token();
    if ( '' === $token ) return null;
    $user = olthem_get_user_from_bearer_token( $token );
    if ( ! $user ) return null;
    if ( ! (int) get_user_meta( $user->ID, 'is_admin', true ) ) return null;
    return $user;
}

function olthem_admin_permission_callback(): bool {
    return olthem_require_admin_token() !== null;
}

// ─── Overview ────────────────────────────────────────────────────────────────

function olthem_rest_admin_overview( WP_REST_Request $request ): WP_REST_Response {
    global $wpdb;

    $table_users    = $wpdb->prefix . 'olthem_users';
    $table_ateliers = $wpdb->prefix . 'olthem_ateliers';
    $table_posts    = $wpdb->posts;
    $today          = current_time( 'Y-m-d' );

    $users_total       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_users}" );
    $ateliers_total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_ateliers}" );
    $ateliers_pending  = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_ateliers} WHERE valid_date IS NULL OR valid_date > %s", $today
    ) );
    $ateliers_validated = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_ateliers} WHERE valid_date IS NOT NULL AND valid_date <= %s", $today
    ) );

    $latest_users = $wpdb->get_results(
        "SELECT id, username, email, created_at FROM {$table_users} ORDER BY created_at DESC, id DESC LIMIT 10",
        ARRAY_A
    );

    $latest_ateliers = $wpdb->get_results(
        "SELECT a.id, a.created_at, a.valid_date, a.start_date,
                u.username,
                p.post_title AS thematique
         FROM {$table_ateliers} a
         LEFT JOIN {$table_users} u ON u.id = a.user_id
         LEFT JOIN {$table_posts} p ON p.ID = a.thematique_id
         ORDER BY a.created_at DESC, a.id DESC
         LIMIT 5",
        ARRAY_A
    );

    return new WP_REST_Response( array(
        'counts' => array(
            'users_total'        => $users_total,
            'ateliers_total'     => $ateliers_total,
            'ateliers_pending'   => $ateliers_pending,
            'ateliers_validated' => $ateliers_validated,
        ),
        'latest_users'    => is_array( $latest_users )    ? $latest_users    : array(),
        'latest_ateliers' => is_array( $latest_ateliers ) ? $latest_ateliers : array(),
        'visits'          => array( 'counts' => array(
            'total_events' => 0,
            'last_7_days'  => 0,
            'today_events' => 0,
        ) ),
    ), 200 );
}

// ─── Users list ──────────────────────────────────────────────────────────────

function olthem_rest_admin_users( WP_REST_Request $request ): WP_REST_Response {
    global $wpdb;

    $table = $wpdb->prefix . 'olthem_users';
    $page     = max( 1, (int) ( $request->get_param( 'page' )     ?? 1 ) );
    $per_page = max( 1, (int) ( $request->get_param( 'per_page' ) ?? 25 ) );
    $offset   = ( $page - 1 ) * $per_page;

    $allowed_sort = array( 'id', 'username', 'nom', 'prenom', 'email', 'created_at', 'is_admin' );
    $sort_by      = in_array( $request->get_param( 'sort_by' ), $allowed_sort, true )
        ? $request->get_param( 'sort_by' ) : 'created_at';
    $sort_dir     = strtoupper( $request->get_param( 'sort_dir' ) ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';

    $where  = array( '1=1' );
    $values = array();

    foreach ( array( 'id', 'username', 'nom', 'prenom', 'email' ) as $col ) {
        $val = sanitize_text_field( (string) ( $request->get_param( $col ) ?? '' ) );
        if ( '' !== $val ) {
            $where[]  = "`{$col}` LIKE %s";
            $values[] = '%' . $wpdb->esc_like( $val ) . '%';
        }
    }

    foreach ( array( 'newsletter', 'is_admin' ) as $col ) {
        $val = $request->get_param( $col );
        if ( $val !== null && $val !== '' ) {
            $where[]  = "`{$col}` = %d";
            $values[] = (int) $val;
        }
    }

    $date_val = sanitize_text_field( (string) ( $request->get_param( 'created_at' ) ?? '' ) );
    if ( '' !== $date_val && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_val ) ) {
        $where[]  = 'DATE(created_at) = %s';
        $values[] = $date_val;
    }

    $where_sql = implode( ' AND ', $where );
    $total     = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}",
        ...$values
    ) );

    $query = $wpdb->prepare(
        "SELECT id, username, nom, prenom, email, remember, newsletter, is_admin, created_at
         FROM {$table} WHERE {$where_sql}
         ORDER BY `{$sort_by}` {$sort_dir}
         LIMIT %d OFFSET %d",
        ...array_merge( $values, array( $per_page, $offset ) )
    );
    $rows = $wpdb->get_results( $query, ARRAY_A );

    $items = array_map( function( $row ) {
        return array(
            'id'         => (int)    $row['id'],
            'username'   => (string) $row['username'],
            'nom'        => (string) $row['nom'],
            'prenom'     => (string) $row['prenom'],
            'email'      => (string) $row['email'],
            'remember'   => (int)    $row['remember'],
            'newsletter' => (int)    $row['newsletter'],
            'isAdmin'    => (int)    $row['is_admin'],
            'created_at' => (string) $row['created_at'],
        );
    }, is_array( $rows ) ? $rows : array() );

    return new WP_REST_Response( array(
        'items'       => $items,
        'total'       => $total,
        'page'        => $page,
        'total_pages' => (int) ceil( $total / $per_page ),
    ), 200 );
}

// ─── User update ─────────────────────────────────────────────────────────────

function olthem_rest_admin_user_update( WP_REST_Request $request ): WP_REST_Response {
    global $wpdb;

    $id     = (int) $request->get_param( 'id' );
    $table  = $wpdb->prefix . 'olthem_users';
    $params = $request->get_json_params() ?: array();

    $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d LIMIT 1", $id ) );
    if ( ! $exists ) {
        return new WP_REST_Response( array( 'message' => 'Utilisateur introuvable.' ), 404 );
    }

    $allowed = array( 'username', 'nom', 'prenom', 'email', 'newsletter', 'isAdmin' );
    $data    = array();
    foreach ( $allowed as $key ) {
        if ( ! array_key_exists( $key, $params ) ) continue;
        if ( $key === 'newsletter' || $key === 'isAdmin' ) {
            $data[ $key === 'isAdmin' ? 'is_admin' : $key ] = olthem_bool_to_int( $params[ $key ] );
        } else {
            $data[ $key ] = sanitize_text_field( (string) $params[ $key ] );
        }
    }

    if ( ! empty( $data ) ) {
        $wpdb->update( $table, $data, array( 'id' => $id ) );
    }

    // Mirror to WP user
    $wp_user = get_user_by( 'email', $wpdb->get_var( $wpdb->prepare( "SELECT email FROM {$table} WHERE id = %d", $id ) ) );
    if ( $wp_user ) {
        $wp_update = array( 'ID' => $wp_user->ID );
        if ( isset( $data['username'] ) )   { $wp_update['display_name'] = $data['username']; update_user_meta( $wp_user->ID, 'nickname', $data['username'] ); }
        if ( isset( $data['nom'] ) )        { $wp_update['last_name']    = $data['nom'];       update_user_meta( $wp_user->ID, 'last_name', $data['nom'] ); }
        if ( isset( $data['prenom'] ) )     { $wp_update['first_name']   = $data['prenom'];    update_user_meta( $wp_user->ID, 'first_name', $data['prenom'] ); }
        if ( isset( $data['is_admin'] ) )   { update_user_meta( $wp_user->ID, 'is_admin', $data['is_admin'] ); }
        if ( isset( $data['newsletter'] ) ) { update_user_meta( $wp_user->ID, 'newsletter', $data['newsletter'] ); }
        wp_update_user( $wp_update );
    }

    return new WP_REST_Response( array( 'message' => 'Utilisateur mis a jour.' ), 200 );
}

// ─── User delete ─────────────────────────────────────────────────────────────

function olthem_rest_admin_user_delete( WP_REST_Request $request ): WP_REST_Response {
    global $wpdb;

    $id    = (int) $request->get_param( 'id' );
    $table = $wpdb->prefix . 'olthem_users';

    $email = $wpdb->get_var( $wpdb->prepare( "SELECT email FROM {$table} WHERE id = %d LIMIT 1", $id ) );
    if ( ! $email ) {
        return new WP_REST_Response( array( 'message' => 'Utilisateur introuvable.' ), 404 );
    }

    $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

    $wp_user = get_user_by( 'email', $email );
    if ( $wp_user ) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user( $wp_user->ID );
    }

    return new WP_REST_Response( array( 'message' => 'Utilisateur supprime.' ), 200 );
}

// ─── Ateliers list ───────────────────────────────────────────────────────────

function olthem_rest_admin_ateliers( WP_REST_Request $request ): WP_REST_Response {
    global $wpdb;

    $table_ateliers = $wpdb->prefix . 'olthem_ateliers';
    $table_users    = $wpdb->prefix . 'olthem_users';
    $table_posts    = $wpdb->posts;

    $page     = max( 1, (int) ( $request->get_param( 'page' )     ?? 1 ) );
    $per_page = max( 1, (int) ( $request->get_param( 'per_page' ) ?? 25 ) );
    $offset   = ( $page - 1 ) * $per_page;

    $where  = array( '1=1' );
    $values = array();

    $id_val = sanitize_text_field( (string) ( $request->get_param( 'id' ) ?? '' ) );
    if ( '' !== $id_val ) { $where[] = 'a.id = %d'; $values[] = (int) $id_val; }

    foreach ( array( 'username', 'email', 'telephone' ) as $col ) {
        $val = sanitize_text_field( (string) ( $request->get_param( $col ) ?? '' ) );
        if ( '' !== $val ) {
            $db_col   = $col === 'username' ? 'u.username' : "a.{$col}";
            $where[]  = "{$db_col} LIKE %s";
            $values[] = '%' . $wpdb->esc_like( $val ) . '%';
        }
    }

    $thm_id = $request->get_param( 'thematique_id' );
    if ( $thm_id !== null && $thm_id !== '' ) { $where[] = 'a.thematique_id = %d'; $values[] = (int) $thm_id; }

    $mundaneum = $request->get_param( 'mundaneum' );
    if ( $mundaneum !== null && $mundaneum !== '' ) { $where[] = 'a.mundaneum = %d'; $values[] = (int) $mundaneum; }

    $status = $request->get_param( 'status' );
    $today  = current_time( 'Y-m-d' );
    if ( 'pending' === $status ) {
        $where[]  = '(a.valid_date IS NULL OR a.valid_date > %s)';
        $values[] = $today;
    } elseif ( 'validated' === $status ) {
        $where[]  = '(a.valid_date IS NOT NULL AND a.valid_date <= %s)';
        $values[] = $today;
    }

    $where_sql = implode( ' AND ', $where );
    $total     = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_ateliers} a
         LEFT JOIN {$table_users} u ON u.id = a.user_id
         WHERE {$where_sql}",
        ...$values
    ) );

    $query = $wpdb->prepare(
        "SELECT a.*, u.username AS linked_username, p.post_title AS thematique_title
         FROM {$table_ateliers} a
         LEFT JOIN {$table_users} u ON u.id = a.user_id
         LEFT JOIN {$table_posts} p ON p.ID = a.thematique_id
         WHERE {$where_sql}
         ORDER BY a.created_at DESC, a.id DESC
         LIMIT %d OFFSET %d",
        ...array_merge( $values, array( $per_page, $offset ) )
    );
    $rows = $wpdb->get_results( $query, ARRAY_A );

    return new WP_REST_Response( array(
        'items'       => is_array( $rows ) ? $rows : array(),
        'total'       => $total,
        'page'        => $page,
        'total_pages' => (int) ceil( $total / $per_page ),
    ), 200 );
}

// ─── Atelier update ──────────────────────────────────────────────────────────

function olthem_rest_admin_atelier_update( WP_REST_Request $request ): WP_REST_Response {
    global $wpdb;

    $id             = (int) $request->get_param( 'id' );
    $table_ateliers = $wpdb->prefix . 'olthem_ateliers';
    $params         = $request->get_json_params() ?: array();
    $values         = is_array( $params['values'] ?? null ) ? $params['values'] : $params;

    $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table_ateliers} WHERE id = %d LIMIT 1", $id ) );
    if ( ! $exists ) return new WP_REST_Response( array( 'message' => 'Atelier introuvable.' ), 404 );

    $allowed = array( 'thematique_id', 'mundaneum', 'etablissement', 'adresse', 'localite', 'code_postal',
                      'nom', 'prenom', 'email', 'telephone', 'nb_participants', 'start_date', 'end_date', 'valid_date' );
    $data    = array();
    foreach ( $allowed as $col ) {
        if ( ! array_key_exists( $col, $values ) ) continue;
        $val = $values[ $col ];
        if ( in_array( $col, array( 'thematique_id', 'mundaneum', 'nb_participants' ), true ) ) {
            $data[ $col ] = $val === '' || $val === null ? null : (int) $val;
        } elseif ( in_array( $col, array( 'start_date', 'end_date', 'valid_date' ), true ) ) {
            $data[ $col ] = ( $val !== '' && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $val ) ) ? $val : null;
        } else {
            $data[ $col ] = $val === '' ? null : sanitize_text_field( (string) $val );
        }
    }

    if ( ! empty( $data ) ) {
        $wpdb->update( $table_ateliers, $data, array( 'id' => $id ) );
    }

    return new WP_REST_Response( array( 'message' => 'Atelier mis a jour.' ), 200 );
}

// ─── Atelier delete ──────────────────────────────────────────────────────────

function olthem_rest_admin_atelier_delete( WP_REST_Request $request ): WP_REST_Response {
    global $wpdb;

    $id    = (int) $request->get_param( 'id' );
    $table = $wpdb->prefix . 'olthem_ateliers';

    $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d LIMIT 1", $id ) );
    if ( ! $exists ) return new WP_REST_Response( array( 'message' => 'Atelier introuvable.' ), 404 );

    $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
    return new WP_REST_Response( array( 'message' => 'Atelier supprime.' ), 200 );
}

// ─── Forms submit ────────────────────────────────────────────────────────────

function olthem_rest_forms_submit( WP_REST_Request $request ): WP_REST_Response {
    global $wpdb;

    $params  = $request->get_json_params() ?: array();
    $table   = sanitize_key( (string) ( $params['table']   ?? '' ) );
    $process = sanitize_text_field( (string) ( $params['process'] ?? '' ) );
    $values  = is_array( $params['values'] ?? null ) ? $params['values'] : array();

    if ( '' === $table ) {
        return new WP_REST_Response( array( 'message' => 'Parametre table manquant.' ), 400 );
    }

    $full_table = $wpdb->prefix . ltrim( $table, '_' );
    $exists     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full_table ) );
    if ( $exists !== $full_table ) {
        return new WP_REST_Response( array( 'message' => 'Table inconnue.' ), 400 );
    }

    // ── Processus spécialisés ──────────────────────────────────────────────────

    if ( 'atelier' === $process ) {
        // Résolution du token optionnel pour lier l'utilisateur connecté
        $token   = olthem_get_bearer_token();
        $user    = '' !== $token ? olthem_get_user_from_bearer_token( $token ) : null;
        $user_id = $user ? (int) $user->ID : null;

        $table_ateliers = $wpdb->prefix . 'olthem_ateliers';
        $table_users    = $wpdb->prefix . 'olthem_users';

        $connected = $user_id ? 1 : 0;
        $nom    = sanitize_text_field( (string) ( $values['nom']    ?? '' ) );
        $prenom = sanitize_text_field( (string) ( $values['prenom'] ?? '' ) );
        $email  = sanitize_email( (string) ( $values['email'] ?? '' ) );

        if ( $user_id ) {
            $linked = $wpdb->get_row( $wpdb->prepare(
                "SELECT nom, prenom, email FROM {$table_users} WHERE id = %d LIMIT 1", $user_id
            ) );
            if ( $linked ) { $nom = (string) $linked->nom; $prenom = (string) $linked->prenom; $email = (string) $linked->email; }
        }

        if ( ! is_email( $email ) ) return new WP_REST_Response( array( 'message' => 'Adresse email invalide.' ), 400 );

        $thematique_id = isset( $values['thematique_id'] ) && $values['thematique_id'] !== '' ? (int) $values['thematique_id'] : null;
        $start_date    = ( ! empty( $values['start_date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $values['start_date'] ) ) ? $values['start_date'] : null;
        $end_date      = ( ! empty( $values['end_date'] )   && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $values['end_date'] ) )   ? $values['end_date']   : null;
        $nb            = isset( $values['nb_participants'] ) && $values['nb_participants'] !== '' ? (int) $values['nb_participants'] : null;

        $data = array(
            'mundaneum'            => olthem_bool_to_int( $values['mundaneum'] ?? 0 ),
            'etablissement'        => sanitize_text_field( (string) ( $values['etablissement'] ?? '' ) ),
            'adresse'              => sanitize_text_field( (string) ( $values['adresse'] ?? '' ) ),
            'localite'             => sanitize_text_field( (string) ( $values['localite'] ?? '' ) ),
            'code_postal'          => sanitize_text_field( (string) ( $values['code_postal'] ?? '' ) ),
            'utilisateur_connecte' => $connected,
            'nom'                  => $nom,
            'prenom'               => $prenom,
            'email'                => $email,
            'telephone'            => sanitize_text_field( (string) ( $values['telephone'] ?? '' ) ),
            'nb_participants'      => $nb,
            'start_date'           => $start_date,
            'end_date'             => $end_date,
            'share_contact'        => olthem_bool_to_int( $values['share_contact'] ?? 0 ),
        );
        if ( $user_id )       $data['user_id']       = $user_id;
        if ( $thematique_id ) $data['thematique_id'] = $thematique_id;

        // Géocodage automatique via Nominatim
        if ( function_exists( 'olthem_geocode_address' ) ) {
            $coords = olthem_geocode_address(
                $data['adresse'],
                $data['localite'],
                $data['code_postal']
            );
            if ( $coords ) {
                $data['latitude']  = $coords['lat'];
                $data['longitude'] = $coords['lng'];
            }
        }

        $inserted = $wpdb->insert( $table_ateliers, $data );
        if ( ! $inserted ) return new WP_REST_Response( array( 'message' => 'Erreur base de donnees.' ), 500 );

        return new WP_REST_Response( array( 'message' => 'Atelier enregistre.', 'id' => $wpdb->insert_id ), 201 );
    }

    return new WP_REST_Response( array( 'message' => 'Processus inconnu.' ), 400 );
}

// ─── Ateliers publics : validés à venir ────────────────────────────────────────────────────────

function olthem_rest_upcoming_ateliers( WP_REST_Request $request ): WP_REST_Response {
    global $wpdb;

    $table_ateliers = $wpdb->prefix . 'olthem_ateliers';
    $table_posts    = $wpdb->posts;
    $today          = current_time( 'Y-m-d' );

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT
            a.id,
            a.mundaneum,
            a.etablissement,
            a.localite,
            a.code_postal,
            a.valid_date,
            a.share_contact,
            a.email        AS contact_email,
            a.latitude,
            a.longitude,
            a.thematique_id,
            p.post_title   AS thematique_titre
         FROM {$table_ateliers} a
         LEFT JOIN {$table_posts} p ON p.ID = a.thematique_id
         WHERE a.valid_date IS NOT NULL
           AND a.valid_date >= %s
         ORDER BY a.valid_date ASC, a.id ASC",
        $today
    ), ARRAY_A );

    if ( ! is_array( $rows ) ) $rows = array();

    $items = array_map( function( $row ) {
        return array(
            'id'               => (int) $row['id'],
            'mundaneum'        => (bool) $row['mundaneum'],
            'etablissement'    => (string) $row['etablissement'],
            'localite'         => (string) $row['localite'],
            'code_postal'      => (string) $row['code_postal'],
            'valid_date'       => (string) $row['valid_date'],
            'share_contact'    => (bool) $row['share_contact'],
            // N'exposer l'email que si share_contact est activé
            'contact_email'    => ( (int) $row['share_contact'] === 1 ) ? (string) $row['contact_email'] : null,
            'latitude'         => isset( $row['latitude'] )  && $row['latitude']  !== null ? (float) $row['latitude']  : null,
            'longitude'        => isset( $row['longitude'] ) && $row['longitude'] !== null ? (float) $row['longitude'] : null,
            'thematique_id'    => $row['thematique_id'] ? (int) $row['thematique_id'] : null,
            'thematique_titre' => (string) $row['thematique_titre'],
        );
    }, $rows );

    return new WP_REST_Response( $items, 200 );
}

// ─── Enregistrement des routes ───────────────────────────────────────────────

add_action( 'rest_api_init', function () {

    // Ateliers publics
    register_rest_route( 'olthem/v1', '/ateliers/upcoming', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'olthem_rest_upcoming_ateliers',
        'permission_callback' => '__return_true',
    ) );

    // Overview
    register_rest_route( 'olthem/v1', '/admin/overview', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'olthem_rest_admin_overview',
        'permission_callback' => 'olthem_admin_permission_callback',
    ) );

    // Users
    register_rest_route( 'olthem/v1', '/admin/users', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'olthem_rest_admin_users',
        'permission_callback' => 'olthem_admin_permission_callback',
    ) );
    register_rest_route( 'olthem/v1', '/admin/users/(?P<id>\d+)', array(
        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => 'olthem_rest_admin_user_update',
        'permission_callback' => 'olthem_admin_permission_callback',
    ) );
    register_rest_route( 'olthem/v1', '/admin/users/(?P<id>\d+)', array(
        'methods'             => WP_REST_Server::DELETABLE,
        'callback'            => 'olthem_rest_admin_user_delete',
        'permission_callback' => 'olthem_admin_permission_callback',
    ) );

    // Ateliers
    register_rest_route( 'olthem/v1', '/admin/ateliers', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'olthem_rest_admin_ateliers',
        'permission_callback' => 'olthem_admin_permission_callback',
    ) );
    register_rest_route( 'olthem/v1', '/admin/ateliers/(?P<id>\d+)', array(
        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => 'olthem_rest_admin_atelier_update',
        'permission_callback' => 'olthem_admin_permission_callback',
    ) );
    register_rest_route( 'olthem/v1', '/admin/ateliers/(?P<id>\d+)', array(
        'methods'             => WP_REST_Server::DELETABLE,
        'callback'            => 'olthem_rest_admin_atelier_delete',
        'permission_callback' => 'olthem_admin_permission_callback',
    ) );

    // Forms
    register_rest_route( 'olthem/v1', '/forms/submit', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'olthem_rest_forms_submit',
        'permission_callback' => '__return_true',
    ) );
} );
