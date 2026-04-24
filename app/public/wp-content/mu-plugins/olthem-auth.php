<?php

/**
 * olthem-auth.php
 *
 * Responsabilité unique : authentification et tokens API.
 *   - Restriction de connexion WP à l’email uniquement.
 *   - Gestion des tokens porteurs (issue, validate, revoke).
 *   - Endpoints REST front : /olthem/v1/auth/{register|login|me|logout}.
 *
 * Endpoints REST à implémenter (TODO) :
 *   - /auth/check-username  — vérification de disponibilité du username.
 *   - /auth/forgot-password — envoi email de réinitialisation.
 *   - /auth/reset-password  — mise à jour du mot de passe.
 *   - /auth/me (PUT)        — mise à jour du profil utilisateur.
 *   - /auth/me/ateliers     — ateliers de l’utilisateur connecté.
 *
 * Voir aussi :
 *   olthem-db.php    — structure BDD et seeding.
 *   olthem-admin.php — interface back-office WP.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
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

// ─── Helpers auth requis par les autres routes ───────────────────────────────

function olthem_auth_require_token( WP_REST_Request $request ) {
    $token = olthem_get_bearer_token();
    return olthem_get_user_from_bearer_token( $token );
}

// ─── REST : me (PUT) — mise à jour du profil ─────────────────────────────────

function olthem_rest_me_update( WP_REST_Request $request ) {
    $token = olthem_get_bearer_token();
    $user  = olthem_get_user_from_bearer_token( $token );

    if ( ! $user ) {
        return new WP_REST_Response( array( 'message' => 'Token invalide ou expire.' ), 401 );
    }

    $params   = $request->get_json_params() ?: $request->get_params();
    $username = isset( $params['username'] ) ? sanitize_text_field( (string) $params['username'] ) : null;
    $nom      = isset( $params['nom'] )      ? sanitize_text_field( (string) $params['nom'] )      : null;
    $prenom   = isset( $params['prenom'] )   ? sanitize_text_field( (string) $params['prenom'] )   : null;
    $newsletter = isset( $params['newsletter'] ) ? olthem_bool_to_int( $params['newsletter'] ) : null;

    $update = array( 'ID' => $user->ID );
    if ( null !== $username ) {
        if ( strlen( $username ) < 2 ) {
            return new WP_REST_Response( array( 'message' => 'Le username doit contenir au moins 2 caracteres.' ), 400 );
        }
        $update['display_name'] = $username;
        update_user_meta( $user->ID, 'nickname', $username );
    }
    if ( null !== $nom )    { $update['last_name']  = $nom;    update_user_meta( $user->ID, 'last_name', $nom );    }
    if ( null !== $prenom ) { $update['first_name'] = $prenom; update_user_meta( $user->ID, 'first_name', $prenom ); }
    if ( null !== $newsletter ) update_user_meta( $user->ID, 'newsletter', $newsletter );

    wp_update_user( $update );
    olthem_upsert_custom_user_row( $user->ID, array() );

    $updated_user = get_user_by( 'id', $user->ID );
    return new WP_REST_Response( array( 'user' => olthem_format_user_payload( $updated_user ) ), 200 );
}

// ─── REST : me/ateliers — ateliers de l'utilisateur connecté ─────────────────

function olthem_rest_me_ateliers( WP_REST_Request $request ) {
    global $wpdb;
    $token = olthem_get_bearer_token();
    $user  = olthem_get_user_from_bearer_token( $token );

    if ( ! $user ) {
        return new WP_REST_Response( array( 'message' => 'Token invalide ou expire.' ), 401 );
    }

    $table_ateliers = $wpdb->prefix . 'olthem_ateliers';
    $table_posts    = $wpdb->posts;

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT a.*, p.post_title AS thematique_title
         FROM {$table_ateliers} a
         LEFT JOIN {$table_posts} p ON p.ID = a.thematique_id
         WHERE a.user_id = %d
         ORDER BY a.created_at DESC",
        (int) $user->ID
    ) );

    return new WP_REST_Response( is_array( $rows ) ? $rows : array(), 200 );
}

// ─── REST : me/ateliers/{id} — mise à jour d'un atelier de l'utilisateur ─────

function olthem_rest_me_atelier_update( WP_REST_Request $request ) {
    global $wpdb;
    $token = olthem_get_bearer_token();
    $user  = olthem_get_user_from_bearer_token( $token );

    if ( ! $user ) {
        return new WP_REST_Response( array( 'message' => 'Token invalide ou expire.' ), 401 );
    }

    $id             = (int) $request->get_param( 'id' );
    $table_ateliers = $wpdb->prefix . 'olthem_ateliers';

    $atelier = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$table_ateliers} WHERE id = %d AND user_id = %d LIMIT 1",
        $id, (int) $user->ID
    ) );

    if ( ! $atelier ) {
        return new WP_REST_Response( array( 'message' => 'Atelier introuvable.' ), 404 );
    }

    $params = $request->get_json_params() ?: array();
    $values = is_array( $params['values'] ?? null ) ? $params['values'] : $params;

    $allowed = array( 'telephone', 'nb_participants', 'start_date', 'end_date', 'etablissement', 'adresse', 'localite', 'code_postal' );
    $data    = array();
    foreach ( $allowed as $col ) {
        if ( array_key_exists( $col, $values ) ) {
            $data[ $col ] = sanitize_text_field( (string) $values[ $col ] );
        }
    }

    if ( empty( $data ) ) {
        return new WP_REST_Response( array( 'message' => 'Aucune donnee a mettre a jour.' ), 400 );
    }

    $wpdb->update( $table_ateliers, $data, array( 'id' => $id ), null, array( '%d' ) );
    return new WP_REST_Response( array( 'message' => 'Atelier mis a jour.' ), 200 );
}

// ─── REST : check-username ────────────────────────────────────────────────────

function olthem_rest_check_username( WP_REST_Request $request ) {
    $username        = sanitize_text_field( (string) ( $request->get_param( 'username' ) ?? '' ) );
    $current_user_id = (int) ( $request->get_param( 'current_user_id' ) ?? 0 );

    if ( strlen( $username ) < 2 ) {
        return new WP_REST_Response( array( 'available' => false ), 200 );
    }

    $existing = get_users( array(
        'meta_key'   => 'nickname',
        'meta_value' => $username,
        'number'     => 1,
        'fields'     => array( 'ID' ),
    ) );

    $taken = ! empty( $existing ) && (int) $existing[0]->ID !== $current_user_id;
    return new WP_REST_Response( array( 'available' => ! $taken ), 200 );
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

    register_rest_route( 'olthem/v1', '/auth/me', array(
        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => 'olthem_rest_me_update',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'olthem/v1', '/auth/me/ateliers', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'olthem_rest_me_ateliers',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'olthem/v1', '/auth/me/ateliers/(?P<id>\d+)', array(
        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => 'olthem_rest_me_atelier_update',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'olthem/v1', '/auth/logout', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'olthem_rest_logout',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'olthem/v1', '/auth/check-username', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'olthem_rest_check_username',
        'permission_callback' => '__return_true',
    ) );
}
add_action( 'rest_api_init', 'olthem_register_auth_rest_routes' );
