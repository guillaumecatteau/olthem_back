<?php

/**
 * olthem-admin.php
 *
 * Responsabilité unique : interface back-office WordPress.
 *   - Colonnes personnalisées dans la liste des utilisateurs.
 *   - Champs Olthem sur les profils utilisateurs (remember, newsletter, isAdmin).
 *   - Page de gestion des ateliers dans le menu WP (création + liste).
 *
 * Voir aussi :
 *   olthem-db.php    — structure BDD et seeding.
 *   olthem-auth.php  — tokens API + endpoints REST d’authentification.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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


// ─── Geocoding via Nominatim (OpenStreetMap) ──────────────────────────────────────────────────

/**
 * Géocode une adresse via l'API Nominatim (OpenStreetMap).
 * Retourne array ['lat' => float, 'lng' => float] ou null si introuvable.
 * Respecte la politique d'usage Nominatim : User-Agent identifié, pas de spam.
 */
function olthem_geocode_address( string $adresse, string $localite, string $code_postal ): ?array {
    $query = trim( implode( ', ', array_filter( array( $adresse, $code_postal . ' ' . $localite, 'Belgique' ) ) ) );
    if ( '' === $query ) return null;

    $url = add_query_arg( array(
        'q'              => $query,
        'format'         => 'jsonv2',
        'limit'          => '1',
        'addressdetails' => '0',
        'countrycodes'   => 'be',
    ), 'https://nominatim.openstreetmap.org/search' );

    $response = wp_remote_get( $url, array(
        'timeout'    => 5,
        'user-agent' => 'Olthem-Headless/1.0 (contact@mundaneum.be)',
    ) );

    if ( is_wp_error( $response ) ) return null;

    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( ! is_array( $data ) || empty( $data[0] ) ) return null;

    $lat = isset( $data[0]['lat'] ) ? (float) $data[0]['lat'] : null;
    $lng = isset( $data[0]['lon'] ) ? (float) $data[0]['lon'] : null;

    if ( null === $lat || null === $lng ) return null;

    return array( 'lat' => $lat, 'lng' => $lng );
}

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
    $share_contact        = isset( $_POST['share_contact'] ) ? 1 : 0;

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
        'share_contact'        => $share_contact,
    );

    // Géocodage automatique via Nominatim
    $coords = olthem_geocode_address( $adresse, $localite, $code_postal );
    if ( $coords ) {
        $data['latitude']  = $coords['lat'];
        $data['longitude'] = $coords['lng'];
    }

    $format = array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' );

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

    echo '<tr><th><label for="share_contact">Partager le contact</label></th><td><input type="checkbox" id="share_contact" name="share_contact" value="1" /><p class="description">Si coché, l\'email de contact sera visible publiquement sur le site.</p></td></tr>';

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
    echo '<th>Share contact</th>';
    echo '<th>Lat/Lng</th>';
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
        echo '<td>' . esc_html( (int) $atelier->share_contact ? 'Oui' : 'Non' ) . '</td>';
        $lat_lng = ( $atelier->latitude && $atelier->longitude )
            ? esc_html( $atelier->latitude ) . ', ' . esc_html( $atelier->longitude )
            : '—';
        echo '<td>' . $lat_lng . '</td>';
        echo '<td>' . esc_html( (string) $atelier->created_at ) . '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}


