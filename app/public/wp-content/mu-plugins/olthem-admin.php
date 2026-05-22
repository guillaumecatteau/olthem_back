<?php

/**
 * olthem-admin.php
 *
 * Responsabilité unique : interface back-office WordPress.
 *   - Colonnes personnalisées dans la liste des utilisateurs.
 *   - Champs Olthem sur les profils utilisateurs (remember, newsletter, isAdmin).
 *   - Menu principal Olthem avec sous-menus : Ateliers, Email Templates, Newsletters.
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
            $new_columns['olthem_nickname'] = 'Username';
            $new_columns['olthem_prenom'] = 'First Name';
            $new_columns['olthem_nom'] = 'Last Name';
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


// ─── Géocodage via Nominatim (OpenStreetMap) ───────────────────────────────────────────────────

/**
 * Géocode une adresse via l'API Nominatim (OpenStreetMap).
 * Retourne array ['lat' => float, 'lng' => float] ou null si introuvable.
 * Respecte la politique d'usage Nominatim : User-Agent identifié, pas de spam.
 */
function olthem_geocode_address( string $address, string $city, string $postal_code ): ?array {
    $query = trim( implode( ', ', array_filter( array( $address, $postal_code . ' ' . $city, 'Belgique' ) ) ) );
    if ( '' === $query ) return null;

    $url = add_query_arg( array(
        'q'              => $query,
        'format'         => 'jsonv2',
        'limit'          => '1',
        'addressdetails' => '0',
        'countrycodes'   => 'be',
    ), 'https://nominatim.openstreetmap.org/search' );

    $response = wp_remote_get( $url, array(
        'timeout'    => 3,
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

// ─── Back-office : menus Ateliers et Mailing ─────────────────────────────────

function olthem_register_admin_menu() {
    // ── Ateliers — menu indépendant ───────────────────────────────────────────
    add_menu_page(
        'Ateliers',
        'Ateliers',
        'manage_options',
        'olthem-ateliers',
        'olthem_render_ateliers_admin_page',
        'dashicons-welcome-learn-more',
        26
    );

    // ── Mailing — menu parent avec sous-menus Email Templates et Newsletters ──
    add_menu_page(
        'Mailing',
        'Mailing',
        'manage_options',
        'olthem-email-templates',
        'olthem_render_email_templates_page',
        'dashicons-email-alt',
        27
    );

    add_submenu_page(
        'olthem-email-templates',
        'Email Templates',
        'Email Templates',
        'manage_options',
        'olthem-email-templates',
        'olthem_render_email_templates_page'
    );

    add_submenu_page(
        'olthem-email-templates',
        'Newsletters',
        'Newsletters',
        'manage_options',
        'olthem-newsletters',
        'olthem_render_newsletters_page'
    );
}
add_action( 'admin_menu', 'olthem_register_admin_menu' );

function olthem_handle_create_atelier() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Acces refuse.' );
    }

    check_admin_referer( 'olthem_create_atelier' );

    global $wpdb;
    $table_ateliers = $wpdb->prefix . 'olthem_ateliers';
    $table_users    = $wpdb->prefix . 'olthem_users';

    $institution = isset( $_POST['institution'] ) ? sanitize_text_field( wp_unslash( $_POST['institution'] ) ) : '';

    if ( '' === $institution ) {
        $redirect = add_query_arg( 'olthem_notice', 'missing_etablissement', admin_url( 'admin.php?page=olthem-ateliers' ) );
        wp_safe_redirect( $redirect );
        exit;
    }

    $user_id              = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
    $thematic_id          = isset( $_POST['thematic_id'] ) ? (int) $_POST['thematic_id'] : 0;
    $mundaneum            = isset( $_POST['mundaneum'] ) ? 1 : 0;
    $address              = isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '';
    $city                 = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
    $postal_code          = isset( $_POST['postal_code'] ) ? sanitize_text_field( wp_unslash( $_POST['postal_code'] ) ) : '';
    $is_registered_user   = isset( $_POST['is_registered_user'] ) ? 1 : 0;
    $last_name            = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
    $first_name           = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
    $email                = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
    $phone                = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
    $start_date           = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
    $end_date             = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';
    $valid_date           = isset( $_POST['valid_date'] ) ? sanitize_text_field( wp_unslash( $_POST['valid_date'] ) ) : '';
    $participants_count   = isset( $_POST['participants_count'] ) ? (int) $_POST['participants_count'] : 0;
    $share_contact        = isset( $_POST['share_contact'] ) ? 1 : 0;

    foreach ( array( $start_date, $end_date, $valid_date ) as $date_value ) {
        if ( '' !== $date_value && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_value ) ) {
            $redirect = add_query_arg( 'olthem_notice', 'invalid_date', admin_url( 'admin.php?page=olthem-ateliers' ) );
            wp_safe_redirect( $redirect );
            exit;
        }
    }

    if ( $is_registered_user && $user_id > 0 ) {
        $linked_user = $wpdb->get_row( $wpdb->prepare(
            "SELECT last_name, first_name, email FROM {$table_users} WHERE id = %d LIMIT 1",
            $user_id
        ) );

        if ( $linked_user ) {
            $last_name  = (string) $linked_user->last_name;
            $first_name = (string) $linked_user->first_name;
            $email      = (string) $linked_user->email;
        }
    }

    $data = array(
        'mundaneum'          => $mundaneum,
        'institution'        => $institution,
        'address'            => $address,
        'city'               => $city,
        'postal_code'        => $postal_code,
        'is_registered_user' => $is_registered_user,
        'last_name'          => $last_name,
        'first_name'         => $first_name,
        'email'              => $email,
        'phone'              => $phone,
        'start_date'         => ( '' !== $start_date ) ? $start_date : null,
        'end_date'           => ( '' !== $end_date ) ? $end_date : null,
        'valid_date'         => ( '' !== $valid_date ) ? $valid_date : null,
        'participants_count' => $participants_count,
        'share_contact'      => $share_contact,
    );

    // Géocodage automatique via Nominatim
    $coords = olthem_geocode_address( $address, $city, $postal_code );
    if ( $coords ) {
        $data['latitude']  = $coords['lat'];
        $data['longitude'] = $coords['lng'];
    }

    $format = array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' );

    if ( $user_id > 0 ) {
        $data['user_id'] = $user_id;
        array_unshift( $format, '%d' );
    }

    if ( $thematic_id > 0 ) {
        $insert_position = isset( $data['user_id'] ) ? 1 : 0;
        $data = array_slice( $data, 0, $insert_position, true ) + array( 'thematic_id' => $thematic_id ) + array_slice( $data, $insert_position, null, true );
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
            echo '<div class="notice notice-success is-dismissible"><p>Atelier created successfully.</p></div>';
        }

        if ( 'missing_etablissement' === $notice ) {
            echo '<div class="notice notice-error is-dismissible"><p>The Institution field is required.</p></div>';
        }

        if ( 'invalid_date' === $notice ) {
            echo '<div class="notice notice-error is-dismissible"><p>Dates must be in YYYY-MM-DD format.</p></div>';
        }

        if ( 'db_error' === $notice ) {
            echo '<div class="notice notice-error is-dismissible"><p>Error while creating the atelier.</p></div>';
        }
    }

    if ( $table_exists !== $table_ateliers ) {
        echo '<div class="notice notice-warning"><p>The ateliers table does not exist yet.</p></div>';
        echo '</div>';
        return;
    }

    echo '<h2>Create an Atelier</h2>';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    wp_nonce_field( 'olthem_create_atelier' );
    echo '<input type="hidden" name="action" value="olthem_create_atelier" />';
    echo '<table class="form-table" role="presentation">';

    echo '<tr><th><label for="institution">Institution</label></th><td><input required type="text" id="institution" name="institution" class="regular-text" /></td></tr>';

    echo '<tr><th><label for="user_id">Linked User</label></th><td><select id="user_id" name="user_id"><option value="">-- Aucun --</option>';
    foreach ( $users_rows as $user_row ) {
        $label = sprintf( '#%d - %s (%s)', (int) $user_row->id, (string) $user_row->username, (string) $user_row->email );
        echo '<option value="' . esc_attr( (string) $user_row->id ) . '">' . esc_html( $label ) . '</option>';
    }
    echo '</select></td></tr>';

    echo '<tr><th><label for="thematic_id">Thematic</label></th><td><select id="thematic_id" name="thematic_id"><option value="">-- Aucune --</option>';
    foreach ( $thematiques as $thematique ) {
        echo '<option value="' . esc_attr( (string) $thematique->ID ) . '">' . esc_html( $thematique->post_title ) . '</option>';
    }
    echo '</select></td></tr>';

    echo '<tr><th><label for="mundaneum">Mundaneum</label></th><td><input type="checkbox" id="mundaneum" name="mundaneum" value="1" /></td></tr>';
    echo '<tr><th><label for="address">Address</label></th><td><input type="text" id="address" name="address" class="regular-text" /></td></tr>';
    echo '<tr><th><label for="city">City</label></th><td><input type="text" id="city" name="city" class="regular-text" /></td></tr>';
    echo '<tr><th><label for="postal_code">Postal Code</label></th><td><input type="text" id="postal_code" name="postal_code" class="regular-text" /></td></tr>';

    echo '<tr><th><label for="is_registered_user">Registered User</label></th><td><input type="checkbox" id="is_registered_user" name="is_registered_user" value="1" /><p class="description">If checked and a linked user is set, last name/first name/email are filled automatically.</p></td></tr>';

    echo '<tr><th><label for="last_name">Last Name</label></th><td><input type="text" id="last_name" name="last_name" class="regular-text" /></td></tr>';
    echo '<tr><th><label for="first_name">First Name</label></th><td><input type="text" id="first_name" name="first_name" class="regular-text" /></td></tr>';
    echo '<tr><th><label for="email">Email</label></th><td><input type="email" id="email" name="email" class="regular-text" /></td></tr>';
    echo '<tr><th><label for="phone">Phone</label></th><td><input type="text" id="phone" name="phone" class="regular-text" /></td></tr>';

    echo '<tr><th><label for="start_date">Start Date</label></th><td><input type="date" id="start_date" name="start_date" /></td></tr>';
    echo '<tr><th><label for="end_date">End Date</label></th><td><input type="date" id="end_date" name="end_date" /></td></tr>';
    echo '<tr><th><label for="valid_date">Valid Date</label></th><td><input type="date" id="valid_date" name="valid_date" /></td></tr>';

    echo '<tr><th><label for="participants_count">Participants Count</label></th><td><input type="number" min="0" id="participants_count" name="participants_count" value="0" /></td></tr>';

    echo '<tr><th><label for="share_contact">Share Contact</label></th><td><input type="checkbox" id="share_contact" name="share_contact" value="1" /><p class="description">If checked, the contact email will be publicly visible on the site.</p></td></tr>';

    echo '</table>';
    submit_button( 'Create Atelier' );
    echo '</form>';
    echo '<hr />';
    echo '<h2>Atelier List</h2>';

    $results = $wpdb->get_results(
        "SELECT a.*, 
                u.username AS linked_username,
                u.email AS linked_user_email,
                p.post_title AS thematique_title
         FROM {$table_ateliers} a
         LEFT JOIN {$table_users} u ON u.id = a.user_id
         LEFT JOIN {$table_posts} p ON p.ID = a.thematic_id
         ORDER BY a.created_at DESC, a.id DESC"
    );

    if ( empty( $results ) ) {
        echo '<p>No ateliers registered yet.</p>';
        echo '</div>';
        return;
    }

    echo '<table class="widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th>ID</th>';
    echo '<th>User ID</th>';
    echo '<th>Linked User</th>';
    echo '<th>Thematic</th>';
    echo '<th>Mundaneum</th>';
    echo '<th>Institution</th>';
    echo '<th>Address</th>';
    echo '<th>City</th>';
    echo '<th>Postal Code</th>';
    echo '<th>Registered User</th>';
    echo '<th>Last Name</th>';
    echo '<th>First Name</th>';
    echo '<th>Email</th>';
    echo '<th>Phone</th>';
    echo '<th>Start Date</th>';
    echo '<th>End Date</th>';
    echo '<th>Valid Date</th>';
    echo '<th>Participants</th>';
    echo '<th>Share Contact</th>';
    echo '<th>Lat/Lng</th>';
    echo '<th>Created At</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    foreach ( $results as $atelier ) {
        echo '<tr>';
        echo '<td>' . esc_html( (string) $atelier->id ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->user_id ) . '</td>';
        echo '<td>' . esc_html( trim( (string) $atelier->linked_username . ' ' . (string) $atelier->linked_user_email ) ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->thematique_title ) . '</td>';
        echo '<td>' . esc_html( (int) $atelier->mundaneum ? 'Yes' : 'No' ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->institution ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->address ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->city ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->postal_code ) . '</td>';
        echo '<td>' . esc_html( (int) $atelier->is_registered_user ? 'Yes' : 'No' ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->last_name ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->first_name ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->email ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->phone ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->start_date ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->end_date ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->valid_date ) . '</td>';
        echo '<td>' . esc_html( (string) $atelier->participants_count ) . '</td>';
        echo '<td>' . esc_html( (int) $atelier->share_contact ? 'Yes' : 'No' ) . '</td>';
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


// ─── Back-office : page Email Templates ──────────────────────────────────────

function olthem_handle_save_email_template() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Access denied.' );
    }
    check_admin_referer( 'olthem_save_email_template' );

    global $wpdb;
    $table     = $wpdb->prefix . 'olthem_email_templates';
    $id        = (int) ( $_POST['template_id'] ?? 0 );
    $name      = sanitize_text_field( wp_unslash( $_POST['name']      ?? '' ) );
    $event_key = sanitize_key( wp_unslash( $_POST['event_key']        ?? '' ) );
    $subject   = sanitize_text_field( wp_unslash( $_POST['subject']   ?? '' ) );
    $body      = wp_unslash( $_POST['body'] ?? '' );

    if ( $id > 0 ) {
        $wpdb->update(
            $table,
            array( 'name' => $name, 'event_key' => $event_key, 'subject' => $subject, 'body' => $body ),
            array( 'id' => $id ),
            array( '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );
    } else {
        if ( '' === $name || '' === $event_key || '' === $subject ) {
            wp_safe_redirect( add_query_arg( array( 'page' => 'olthem-email-templates', 'olthem_tpl_notice' => 'invalid' ), admin_url( 'admin.php' ) ) );
            exit;
        }
        $wpdb->insert(
            $table,
            array( 'name' => $name, 'event_key' => $event_key, 'subject' => $subject, 'body' => $body ),
            array( '%s', '%s', '%s', '%s' )
        );
    }

    wp_safe_redirect( add_query_arg( array( 'page' => 'olthem-email-templates', 'olthem_tpl_notice' => 'saved' ), admin_url( 'admin.php' ) ) );
    exit;
}
add_action( 'admin_post_olthem_save_email_template', 'olthem_handle_save_email_template' );

function olthem_handle_delete_email_template() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Access denied.' );
    }
    check_admin_referer( 'olthem_delete_email_template' );

    global $wpdb;
    $id = (int) ( $_POST['template_id'] ?? 0 );
    if ( $id > 0 ) {
        $wpdb->delete( $wpdb->prefix . 'olthem_email_templates', array( 'id' => $id ), array( '%d' ) );
    }

    wp_safe_redirect( add_query_arg( array( 'page' => 'olthem-email-templates', 'olthem_tpl_notice' => 'deleted' ), admin_url( 'admin.php' ) ) );
    exit;
}
add_action( 'admin_post_olthem_delete_email_template', 'olthem_handle_delete_email_template' );

function olthem_render_email_templates_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'olthem_email_templates';

    $edit_id  = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;
    $is_new   = isset( $_GET['new'] ) && '1' === $_GET['new'];
    $edit_tpl = $edit_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $edit_id ) ) : null;

    echo '<div class="wrap">';
    echo '<h1 class="wp-heading-inline">Email Templates</h1>';

    if ( ! $edit_tpl && ! $is_new ) {
        echo ' <a href="' . esc_url( admin_url( 'admin.php?page=olthem-email-templates&new=1' ) ) . '" class="page-title-action">Add New</a>';
    }

    if ( isset( $_GET['olthem_tpl_notice'] ) ) {
        $notice = sanitize_text_field( wp_unslash( $_GET['olthem_tpl_notice'] ) );
        if ( 'saved'   === $notice ) echo '<div class="notice notice-success is-dismissible"><p>Template saved successfully.</p></div>';
        if ( 'deleted' === $notice ) echo '<div class="notice notice-success is-dismissible"><p>Template deleted.</p></div>';
        if ( 'invalid' === $notice ) echo '<div class="notice notice-error is-dismissible"><p>Invalid data.</p></div>';
    }

    if ( $edit_tpl || $is_new ) {
        $title = $is_new ? 'New Email Template' : 'Edit: ' . esc_html( (string) $edit_tpl->name );
        echo '<h2>' . $title . '</h2>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'olthem_save_email_template' );
        echo '<input type="hidden" name="action" value="olthem_save_email_template" />';
        if ( $edit_tpl ) {
            echo '<input type="hidden" name="template_id" value="' . esc_attr( (string) $edit_tpl->id ) . '" />';
        }
        echo '<table class="form-table" role="presentation">';
        echo '<tr><th><label for="tpl_name">Name</label></th><td>'
           . '<input type="text" id="tpl_name" name="name" class="large-text" value="' . esc_attr( (string) ( $edit_tpl->name ?? '' ) ) . '" required /></td></tr>';
        echo '<tr><th><label for="tpl_event_key">Event Key</label></th><td>'
           . '<input type="text" id="tpl_event_key" name="event_key" class="regular-text" value="' . esc_attr( (string) ( $edit_tpl->event_key ?? '' ) ) . '" required />'
           . '<p class="description">Identifiant unique utilisé dans le code (ex : <code>atelier_admin</code>, <code>reset_password</code>).</p></td></tr>';
        echo '<tr><th><label for="tpl_subject">Subject</label></th><td>'
           . '<input type="text" id="tpl_subject" name="subject" class="large-text" value="' . esc_attr( (string) ( $edit_tpl->subject ?? '' ) ) . '" required /></td></tr>';
        echo '<tr><th><label for="tpl_body">Body</label></th><td>';
        echo '<textarea id="tpl_body" name="body" class="large-text code" rows="14">' . esc_textarea( (string) ( $edit_tpl->body ?? '' ) ) . '</textarea>';
        echo '<p class="description">Available placeholders — <strong>atelier_admin</strong>: [USERNAME] [LIEU] [THEMATIQUE] [start_date] [end_date] [participants_count] [first_name] [last_name] [email] [phone] | <strong>reset_password</strong>: [first_name] [last_name] [RESET_LINK]</p>';
        echo '</td></tr>';
        echo '</table>';
        submit_button( $is_new ? 'Create Template' : 'Save Template' );
        echo '</form>';
        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=olthem-email-templates' ) ) . '">&larr; Back to list</a></p>';
    } else {
        $templates = $wpdb->get_results( "SELECT id, name, event_key, subject FROM {$table} ORDER BY id ASC" );

        if ( empty( $templates ) ) {
            echo '<p>No email templates found. <a href="' . esc_url( admin_url( 'admin.php?page=olthem-email-templates&new=1' ) ) . '">Create one</a>.</p>';
        } else {
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr><th style="width:40px">ID</th><th>Name</th><th>Event Key</th><th>Subject</th><th style="width:130px">Actions</th></tr></thead>';
            echo '<tbody>';
            foreach ( $templates as $tpl ) {
                $edit_url = esc_url( admin_url( 'admin.php?page=olthem-email-templates&edit=' . (int) $tpl->id ) );
                echo '<tr>';
                echo '<td>' . esc_html( (string) $tpl->id ) . '</td>';
                echo '<td>' . esc_html( (string) $tpl->name ) . '</td>';
                echo '<td><code>' . esc_html( (string) $tpl->event_key ) . '</code></td>';
                echo '<td>' . esc_html( (string) $tpl->subject ) . '</td>';
                echo '<td>';
                echo '<a href="' . $edit_url . '">Edit</a> | ';
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline" onsubmit="return confirm(\'Delete this template?\')">';
                wp_nonce_field( 'olthem_delete_email_template', '_wpnonce', false );
                echo '<input type="hidden" name="action" value="olthem_delete_email_template" />';
                echo '<input type="hidden" name="template_id" value="' . esc_attr( (string) $tpl->id ) . '" />';
                echo '<button type="submit" class="button-link" style="color:#b32d2e;text-decoration:underline">Delete</button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
    }

    echo '</div>';
}


// ─── Back-office : page Newsletters ──────────────────────────────────────────

/**
 * Envoie une newsletter à tous les utilisateurs ayant newsletter = 1.
 * Enregistre le résultat dans wp_olthem_newsletters.
 */
function olthem_handle_send_newsletter() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Access denied.' );
    }
    check_admin_referer( 'olthem_send_newsletter' );

    global $wpdb;

    $subject = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
    $body    = wp_unslash( $_POST['body'] ?? '' );

    if ( '' === $subject || '' === $body ) {
        wp_safe_redirect( add_query_arg( array( 'page' => 'olthem-newsletters', 'olthem_nl_notice' => 'missing_fields' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    $table_users = $wpdb->prefix . 'olthem_users';
    $subscribers = $wpdb->get_results( "SELECT email, first_name, last_name FROM {$table_users} WHERE newsletter = 1" );

    $sent = 0;
    foreach ( $subscribers as $sub ) {
        $personalised_body = str_replace(
            array( '[first_name]', '[last_name]', '[email]' ),
            array(
                esc_html( (string) $sub->first_name ),
                esc_html( (string) $sub->last_name ),
                esc_html( (string) $sub->email ),
            ),
            $body
        );
        $ok = wp_mail( $sub->email, $subject, $personalised_body );
        if ( $ok ) {
            $sent++;
        }
    }

    $table_nl = $wpdb->prefix . 'olthem_newsletters';
    $wpdb->insert(
        $table_nl,
        array(
            'subject'          => $subject,
            'body'             => $body,
            'recipients_count' => $sent,
        ),
        array( '%s', '%s', '%d' )
    );

    wp_safe_redirect( add_query_arg( array( 'page' => 'olthem-newsletters', 'olthem_nl_notice' => 'sent', 'count' => $sent ), admin_url( 'admin.php' ) ) );
    exit;
}
add_action( 'admin_post_olthem_send_newsletter', 'olthem_handle_send_newsletter' );

function olthem_handle_save_newsletter() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Access denied.' );
    }
    check_admin_referer( 'olthem_save_newsletter' );

    global $wpdb;
    $table = $wpdb->prefix . 'olthem_newsletters';
    $id    = (int) ( $_POST['newsletter_id'] ?? 0 );

    if ( $id < 1 ) {
        wp_safe_redirect( add_query_arg( array( 'page' => 'olthem-newsletters', 'olthem_nl_notice' => 'missing_fields' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    $subject = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
    $body    = wp_unslash( $_POST['body'] ?? '' );

    $wpdb->update(
        $table,
        array( 'subject' => $subject, 'body' => $body ),
        array( 'id' => $id ),
        array( '%s', '%s' ),
        array( '%d' )
    );

    wp_safe_redirect( add_query_arg( array( 'page' => 'olthem-newsletters', 'olthem_nl_notice' => 'saved' ), admin_url( 'admin.php' ) ) );
    exit;
}
add_action( 'admin_post_olthem_save_newsletter', 'olthem_handle_save_newsletter' );

function olthem_handle_delete_newsletter() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Access denied.' );
    }
    check_admin_referer( 'olthem_delete_newsletter' );

    global $wpdb;
    $id = (int) ( $_POST['newsletter_id'] ?? 0 );
    if ( $id > 0 ) {
        $wpdb->delete( $wpdb->prefix . 'olthem_newsletters', array( 'id' => $id ), array( '%d' ) );
    }

    wp_safe_redirect( add_query_arg( array( 'page' => 'olthem-newsletters', 'olthem_nl_notice' => 'deleted' ), admin_url( 'admin.php' ) ) );
    exit;
}
add_action( 'admin_post_olthem_delete_newsletter', 'olthem_handle_delete_newsletter' );

function olthem_render_newsletters_page() {
    global $wpdb;

    $table_users = $wpdb->prefix . 'olthem_users';
    $table_nl    = $wpdb->prefix . 'olthem_newsletters';

    $edit_id  = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;
    $edit_nl  = $edit_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_nl} WHERE id = %d LIMIT 1", $edit_id ) ) : null;

    $subscriber_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_users} WHERE newsletter = 1" );
    $history          = $wpdb->get_results( "SELECT * FROM {$table_nl} ORDER BY sent_at DESC" );

    echo '<div class="wrap">';
    echo '<h1>Newsletters</h1>';

    if ( isset( $_GET['olthem_nl_notice'] ) ) {
        $notice = sanitize_text_field( wp_unslash( $_GET['olthem_nl_notice'] ) );
        $count  = (int) ( $_GET['count'] ?? 0 );
        if ( 'sent'          === $notice ) echo '<div class="notice notice-success is-dismissible"><p>Newsletter sent to <strong>' . $count . ' recipient(s)</strong>.</p></div>';
        if ( 'saved'         === $notice ) echo '<div class="notice notice-success is-dismissible"><p>Newsletter saved.</p></div>';
        if ( 'deleted'       === $notice ) echo '<div class="notice notice-success is-dismissible"><p>Newsletter deleted.</p></div>';
        if ( 'missing_fields' === $notice ) echo '<div class="notice notice-error is-dismissible"><p>Subject and body are required.</p></div>';
    }

    if ( $edit_nl ) {
        // ── Mode édition ─────────────────────────────────────────────────────
        echo '<h2>Edit Newsletter #' . (int) $edit_nl->id . '</h2>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'olthem_save_newsletter' );
        echo '<input type="hidden" name="action" value="olthem_save_newsletter" />';
        echo '<input type="hidden" name="newsletter_id" value="' . esc_attr( (string) $edit_nl->id ) . '" />';
        echo '<table class="form-table" role="presentation">';
        echo '<tr><th><label for="nl_subject">Subject</label></th><td>'
           . '<input type="text" id="nl_subject" name="subject" class="large-text" value="' . esc_attr( (string) $edit_nl->subject ) . '" required /></td></tr>';
        echo '<tr><th><label for="nl_body">Body</label></th><td>'
           . '<textarea id="nl_body" name="body" class="large-text code" rows="16" required>' . esc_textarea( (string) $edit_nl->body ) . '</textarea>'
           . '<p class="description">Placeholders : [first_name] [last_name] [email]</p></td></tr>';
        echo '</table>';
        submit_button( 'Save' );
        echo '</form>';
        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=olthem-newsletters' ) ) . '">&larr; Back to list</a></p>';
    } else {
        // ── Compose & Send ───────────────────────────────────────────────────
        echo '<h2>Compose &amp; Send</h2>';
        echo '<p class="description">Will be sent to <strong>' . $subscriber_count . ' subscriber(s)</strong>. '
           . 'Placeholders : <code>[first_name]</code> <code>[last_name]</code> <code>[email]</code>.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" id="nl_form">';
        wp_nonce_field( 'olthem_send_newsletter' );
        echo '<input type="hidden" name="action" value="olthem_send_newsletter" />';
        echo '<table class="form-table" role="presentation">';
        echo '<tr><th><label for="nl_subject">Subject</label></th><td>'
           . '<input type="text" id="nl_subject" name="subject" class="large-text" required /></td></tr>';
        echo '<tr><th><label for="nl_body">Body</label></th><td>'
           . '<textarea id="nl_body" name="body" class="large-text code" rows="16" required></textarea>'
           . '<p class="description">Placeholders : [first_name] [last_name] [email]</p></td></tr>';
        echo '</table>';
        $btn_label = ( $subscriber_count > 0 )
            ? 'Send to ' . $subscriber_count . ' subscriber(s)'
            : 'Send (no subscribers)';
        submit_button( $btn_label, $subscriber_count > 0 ? 'primary' : 'secondary' );
        echo '</form>';

        // ── Historique ───────────────────────────────────────────────────────
        echo '<hr />';
        echo '<h2>History</h2>';

        if ( empty( $history ) ) {
            echo '<p>No newsletters sent yet.</p>';
        } else {
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr>'
               . '<th style="width:40px">ID</th>'
               . '<th>Subject</th>'
               . '<th style="width:90px">Recipients</th>'
               . '<th style="width:160px">Sent At</th>'
               . '<th style="width:150px">Actions</th>'
               . '</tr></thead>';
            echo '<tbody>';
            foreach ( $history as $nl ) {
                $preview_id = 'nl_preview_' . (int) $nl->id;
                $edit_url   = esc_url( admin_url( 'admin.php?page=olthem-newsletters&edit=' . (int) $nl->id ) );
                echo '<tr>';
                echo '<td>' . esc_html( (string) $nl->id ) . '</td>';
                echo '<td>' . esc_html( (string) $nl->subject ) . '</td>';
                echo '<td>' . esc_html( (string) $nl->recipients_count ) . '</td>';
                echo '<td>' . esc_html( (string) $nl->sent_at ) . '</td>';
                echo '<td>';
                echo '<a href="#" onclick="document.getElementById(\'' . esc_js( $preview_id ) . '\').style.display=\'block\';return false;">View</a>';
                echo ' | <a href="' . $edit_url . '">Edit</a> | ';
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline" onsubmit="return confirm(\'Delete this newsletter?\')">';
                wp_nonce_field( 'olthem_delete_newsletter', '_wpnonce', false );
                echo '<input type="hidden" name="action" value="olthem_delete_newsletter" />';
                echo '<input type="hidden" name="newsletter_id" value="' . esc_attr( (string) $nl->id ) . '" />';
                echo '<button type="submit" class="button-link" style="color:#b32d2e;text-decoration:underline">Delete</button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
                echo '<tr id="' . esc_attr( $preview_id ) . '" style="display:none"><td colspan="5">'
                   . '<pre style="white-space:pre-wrap;background:#f6f7f7;padding:12px;border:1px solid #ddd">'
                   . esc_html( (string) $nl->body ) . '</pre>'
                   . '<a href="#" onclick="document.getElementById(\'' . esc_js( $preview_id ) . '\').style.display=\'none\';return false;">Close</a>'
                   . '</td></tr>';
            }
            echo '</tbody></table>';
        }
    }

    echo '</div>';
}
