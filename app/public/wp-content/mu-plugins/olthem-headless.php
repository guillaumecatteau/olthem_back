<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ─── ACF : Groupe de champs Thématique ──────────────────────────────────
// Priorité 20 : ACF est entièrement initialisé avant nos hooks.
// La page d'options est gérée dans functions.php du thème.

add_action( 'acf/init', function () {

    error_log( '[OLTHEM-DEBUG] acf/init p20 — acf_add_local_field_group: ' . ( function_exists( 'acf_add_local_field_group' ) ? 'YES' : 'NO' ) );

    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    acf_add_local_field_group( array(
        'key'                   => 'group_olthem_thematique',
        'title'                 => 'Informations de la thématique',
        'description'           => 'Champs obligatoires pour la publication d\'une thématique.',
        'position'              => 'normal',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
        'active'                => true,
        'menu_order'            => 0,
        'location'              => array(
            array(
                array(
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'olthem_thematique',
                ),
            ),
        ),
        'fields' => array(

            array(
                'key'          => 'field_olthem_thm_titre',
                'label'        => 'Titre',
                'name'         => 'titre',
                'type'         => 'text',
                'required'     => 1,
                'instructions' => 'Titre de la thématique affiché sur le site.',
                'placeholder'  => 'Ex. : Fake news',
            ),

            array(
                'key'          => 'field_olthem_thm_descriptif_desktop',
                'label'        => 'Descriptif desktop',
                'name'         => 'descriptif_desktop',
                'type'         => 'textarea',
                'required'     => 1,
                'instructions' => 'Description affichée sur desktop.',
                'rows'         => 4,
                'new_lines'    => 'br',
            ),

            array(
                'key'          => 'field_olthem_thm_descriptif_mobile',
                'label'        => 'Descriptif mobile',
                'name'         => 'descriptif_mobile',
                'type'         => 'textarea',
                'required'     => 1,
                'instructions' => 'Description courte affichée sur mobile (recommandé : 2 lignes max).',
                'rows'         => 2,
                'new_lines'    => 'br',
            ),

            array(
                'key'           => 'field_olthem_thm_episode',
                'label'         => 'Episode',
                'name'          => 'episode',
                'type'          => 'true_false',
                'required'      => 0,
                'instructions'  => 'Activer si la thématique est liée à un épisode avec un personnage.',
                'default_value' => 0,
                'ui'            => 1,
                'ui_on_text'    => 'Oui',
                'ui_off_text'   => 'Non',
            ),

            array(
                'key'               => 'field_olthem_thm_personnage',
                'label'             => 'Nom du personnage',
                'name'              => 'personnage',
                'type'              => 'text',
                'required'          => 0,
                'instructions'      => 'Nom du personnage associé à l\'épisode.',
                'placeholder'       => 'Ex. : Ada Lovelace',
                'conditional_logic' => array(
                    array(
                        array(
                            'field'    => 'field_olthem_thm_episode',
                            'operator' => '==',
                            'value'    => '1',
                        ),
                    ),
                ),
            ),

            array(
                'key'               => 'field_olthem_thm_episode_numero',
                'label'             => 'Numéro de l\'épisode',
                'name'              => 'episode_numero',
                'type'              => 'number',
                'required'          => 0,
                'instructions'      => 'Numéro d\'ordre de l\'épisode.',
                'placeholder'       => 'Ex. : 3',
                'min'               => 1,
                'step'              => 1,
                'conditional_logic' => array(
                    array(
                        array(
                            'field'    => 'field_olthem_thm_episode',
                            'operator' => '==',
                            'value'    => '1',
                        ),
                    ),
                ),
            ),

            array(
                'key'           => 'field_olthem_thm_header',
                'label'         => 'Header',
                'name'          => 'header',
                'type'          => 'true_false',
                'required'      => 0,
                'instructions'  => 'Activer si la thématique doit apparaître dans le header du site.',
                'default_value' => 0,
                'ui'            => 1,
                'ui_on_text'    => 'Oui',
                'ui_off_text'   => 'Non',
            ),

            array(
                'key'               => 'field_olthem_thm_header_position',
                'label'             => 'Position dans le header',
                'name'              => 'header_position',
                'type'              => 'select',
                'required'          => 0,
                'instructions'      => 'Ordre d\'apparition dans le header.',
                'choices'           => array(
                    'premier'   => 'Premier',
                    'deuxieme'  => 'Deuxième',
                    'troisieme' => 'Troisième',
                ),
                'default_value'     => array( 'premier' ),
                'allow_null'        => 0,
                'multiple'          => 0,
                'ui'                => 0,
                'return_format'     => 'value',
                'conditional_logic' => array(
                    array(
                        array(
                            'field'    => 'field_olthem_thm_header',
                            'operator' => '==',
                            'value'    => '1',
                        ),
                    ),
                ),
            ),

            array(
                'key'           => 'field_olthem_thm_visuel',
                'label'         => 'Visuel du thème',
                'name'          => 'visuel',
                'type'          => 'image',
                'required'      => 1,
                'instructions'  => 'Visuel principal du thème — recommandé : 1920 × 1080 px.',
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'library'       => 'all',
            ),

            array(
                'key'            => 'field_olthem_thm_couleur',
                'label'          => 'Couleur du thème',
                'name'           => 'couleur',
                'type'           => 'color_picker',
                'required'       => 1,
                'instructions'   => 'Couleur d\'identification utilisée pour les cartes et accents visuels.',
                'default_value'  => '#3F3F48',
                'enable_opacity' => 0,
                'return_format'  => 'string',
            ),

        ),
    ) );

    error_log( '[OLTHEM-DEBUG] acf_add_local_field_group appelé pour group_olthem_thematique' );

}, 20 );

function olthem_register_content_types() {
    register_post_type(
        'olthem_section',
        array(
            'labels'              => array(
                'name'               => 'Sections',
                'singular_name'      => 'Section',
                'menu_name'          => 'Sections',
                'add_new'            => 'Ajouter',
                'add_new_item'       => 'Ajouter une section',
                'edit_item'          => 'Modifier la section',
                'new_item'           => 'Nouvelle section',
                'view_item'          => 'Voir la section',
                'search_items'       => 'Rechercher des sections',
                'not_found'          => 'Aucune section trouvee',
                'not_found_in_trash' => 'Aucune section dans la corbeille',
                'all_items'          => 'Toutes les sections',
            ),
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_admin_bar'   => true,
            'has_archive'         => false,
            'show_in_rest'        => true,
            'rest_base'           => 'sections',
            'menu_icon'           => 'dashicons-screenoptions',
            'menu_position'       => 20,
            'capability_type'     => 'page',
            'map_meta_cap'        => true,
            'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes' ),
            'publicly_queryable'  => true,
            'exclude_from_search' => true,
            'show_in_nav_menus'   => false,
            'rewrite'             => array( 'slug' => 'sections', 'with_front' => false ),
        )
    );

    register_post_type(
        'olthem_thematique',
        array(
            'labels'              => array(
                'name'               => 'Thematiques',
                'singular_name'      => 'Thematique',
                'menu_name'          => 'Thematiques',
                'add_new'            => 'Ajouter',
                'add_new_item'       => 'Ajouter une thematique',
                'edit_item'          => 'Modifier la thematique',
                'new_item'           => 'Nouvelle thematique',
                'view_item'          => 'Voir la thematique',
                'search_items'       => 'Rechercher des thematiques',
                'not_found'          => 'Aucune thematique trouvee',
                'not_found_in_trash' => 'Aucune thematique dans la corbeille',
                'all_items'          => 'Toutes les thematiques',
            ),
            'public'              => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_admin_bar'   => true,
            'has_archive'         => false,
            'show_in_rest'        => true,
            'rest_base'           => 'thematiques',
            'menu_icon'           => 'dashicons-layout',
            'menu_position'       => 21,
            'capability_type'     => 'page',
            'map_meta_cap'        => true,
            'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes' ),
            'publicly_queryable'  => true,
            'exclude_from_search' => true,
            'show_in_nav_menus'   => false,
            'rewrite'             => array( 'slug' => 'thematiques', 'with_front' => false ),
        )
    );
}
add_action( 'init', 'olthem_register_content_types', 1 );

function olthem_add_featured_image_field() {
    register_rest_field(
        array( 'post', 'page', 'olthem_section', 'olthem_thematique' ),
        'featured_image_url',
        array(
            'get_callback' => function ( $item ) {
                $image_id = get_post_thumbnail_id( $item['id'] );

                if ( ! $image_id ) {
                    return null;
                }

                return wp_get_attachment_image_url( $image_id, 'full' );
            },
            'schema'       => array(
                'description' => 'URL de l image mise en avant.',
                'type'        => 'string',
                'context'     => array( 'view', 'edit' ),
            ),
        )
    );
}

function olthem_add_menu_order_field() {
    register_rest_field(
        array( 'olthem_section', 'olthem_thematique' ),
        'display_order',
        array(
            'get_callback' => function ( $item ) {
                return (int) get_post_field( 'menu_order', $item['id'] );
            },
            'schema'       => array(
                'description' => 'Ordre d affichage du contenu.',
                'type'        => 'integer',
                'context'     => array( 'view', 'edit' ),
            ),
        )
    );
}

function olthem_customize_post_updated_messages( $messages ) {
    $messages['olthem_section'] = array(
        0  => '',
        1  => 'Section mise a jour.',
        2  => 'Champ personnalise mis a jour.',
        3  => 'Champ personnalise supprime.',
        4  => 'Section mise a jour.',
        5  => false,
        6  => 'Section publiee.',
        7  => 'Section enregistree.',
        8  => 'Section soumise.',
        9  => 'Section planifiee.',
        10 => 'Brouillon de section mis a jour.',
    );

    $messages['olthem_thematique'] = array(
        0  => '',
        1  => 'Thematique mise a jour.',
        2  => 'Champ personnalise mis a jour.',
        3  => 'Champ personnalise supprime.',
        4  => 'Thematique mise a jour.',
        5  => false,
        6  => 'Thematique publiee.',
        7  => 'Thematique enregistree.',
        8  => 'Thematique soumise.',
        9  => 'Thematique planifiee.',
        10 => 'Brouillon de thematique mis a jour.',
    );

    return $messages;
}
add_filter( 'post_updated_messages', 'olthem_customize_post_updated_messages' );

function olthem_set_default_order_for_content( $query ) {
    if ( ! $query->is_main_query() ) {
        return;
    }

    if ( is_admin() ) {
        $post_type = $query->get( 'post_type' );
        $is_target = in_array( $post_type, array( 'olthem_section', 'olthem_thematique' ), true );

        if ( $is_target && ! isset( $_GET['orderby'] ) ) {
            $query->set( 'orderby', 'menu_order' );
            $query->set( 'order', 'ASC' );
        }

        return;
    }

    $post_type = $query->get( 'post_type' );
    if ( in_array( $post_type, array( 'olthem_section', 'olthem_thematique' ), true ) && ! $query->get( 'orderby' ) ) {
        $query->set( 'orderby', 'menu_order' );
        $query->set( 'order', 'ASC' );
    }
}
add_action( 'pre_get_posts', 'olthem_set_default_order_for_content' );

function olthem_exclude_cpts_from_sitemap( $types ) {
    unset( $types['olthem_section'] );
    unset( $types['olthem_thematique'] );

    return $types;
}
add_filter( 'wp_sitemaps_post_types', 'olthem_exclude_cpts_from_sitemap' );

function olthem_cleanup_admin_menu() {
    remove_menu_page( 'edit.php' );
    remove_menu_page( 'edit-comments.php' );
}
add_action( 'admin_menu', 'olthem_cleanup_admin_menu', 999 );

function olthem_headless_allowed_origins() {
    return array(
        'http://localhost',
        'http://127.0.0.1',
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost:5500',
        'http://127.0.0.1:5500',
        'http://localhost/olthem/frontend',
    );
}

add_action(
    'rest_api_init',
    function () {
        olthem_add_featured_image_field();
        olthem_add_menu_order_field();

        // Expose ACF fields of olthem_thematique in the REST API
        if ( ! function_exists( 'get_field' ) ) {
            return;
        }

        $acf_fields = array(
            'titre'              => 'string',
            'descriptif_desktop' => 'string',
            'descriptif_mobile'  => 'string',
            'episode'            => 'boolean',
            'personnage'         => 'string',
            'episode_numero'     => 'integer',
            'header'             => 'boolean',
            'header_position'    => 'string',
            'visuel'             => 'object',
            'couleur'            => 'string',
        );

        foreach ( $acf_fields as $field_name => $field_type ) {
            register_rest_field(
                'olthem_thematique',
                $field_name,
                array(
                    'get_callback' => function ( $item ) use ( $field_name ) {
                        return get_field( $field_name, $item['id'] );
                    },
                    'schema' => array(
                        'type'    => $field_type,
                        'context' => array( 'view', 'edit' ),
                    ),
                )
            );
        }

        // ── Champ builder (flexible content) ───────────────────────────────────────────────
        // Le champ ACF s'appelle 'Builder' (B majuscule) — sensible à la casse.
        // Structure : repeater Builder > flexible content subsection > layouts.

        register_rest_field(
            'olthem_thematique',
            'builder',
            array(
                'get_callback' => function ( $post_item ) {
                    $post_id = $post_item['id'];

                    // get_field() avec le bon nom (B majuscule) résout tout le repeater imbriqué
                    if ( function_exists( 'get_field' ) ) {
                        $value = get_field( 'Builder', $post_id );
                        if ( is_array( $value ) && ! empty( $value ) ) {
                            return $value;
                        }
                    }

                    // Fallback : lecture brute des post meta (B majuscule)
                    $count = (int) get_post_meta( $post_id, 'Builder', true );
                    if ( $count <= 0 ) {
                        return array();
                    }

                    $all_meta = get_post_meta( $post_id );
                    $rows     = array();

                    for ( $i = 0; $i < $count; $i++ ) {
                        $row_prefix  = 'Builder_' . $i . '_';
                        $sub_layouts = isset( $all_meta[ $row_prefix . 'subsection' ][0] )
                            ? maybe_unserialize( $all_meta[ $row_prefix . 'subsection' ][0] )
                            : array();

                        $sub_count = is_array( $sub_layouts ) ? count( $sub_layouts ) : 0;
                        $subsection = array();

                        for ( $j = 0; $j < $sub_count; $j++ ) {
                            $layout_type = is_array( $sub_layouts ) ? $sub_layouts[ $j ] : '';
                            $sub_prefix  = $row_prefix . 'subsection_' . $j . '_';
                            $layout_row  = array( 'acf_fc_layout' => $layout_type );

                            foreach ( $all_meta as $meta_key => $meta_values ) {
                                if ( '_' === $meta_key[0] ) continue;
                                if ( 0 !== strpos( $meta_key, $sub_prefix ) ) continue;
                                $sub_field = substr( $meta_key, strlen( $sub_prefix ) );
                                $layout_row[ $sub_field ] = maybe_unserialize( $meta_values[0] );
                            }

                            $subsection[] = $layout_row;
                        }

                        $rows[] = array( 'subsection' => $subsection );
                    }

                    return $rows;
                },
                'schema' => array(
                    'type'    => 'array',
                    'context' => array( 'view', 'edit' ),
                ),
            )
        );
    }
);

add_filter(
    'rest_pre_serve_request',
    function ( $served, $result, $request, $server ) {
        $origin          = get_http_origin();
        $allowed_origins = apply_filters( 'olthem_headless_allowed_origins', olthem_headless_allowed_origins() );

        if ( $origin && in_array( $origin, $allowed_origins, true ) ) {
            header( 'Access-Control-Allow-Origin: ' . $origin );
            header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, PATCH, DELETE' );
            header( 'Access-Control-Allow-Credentials: true' );
            header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce' );
            header( 'Vary: Origin', false );
        }

        if ( 'OPTIONS' === $request->get_method() ) {
            status_header( 200 );
            return true;
        }

        return $served;
    },
    10,
    4
);

// ─── ACF : Validation des champs requis pour olthem_thematique ────────────────
add_action( 'acf/validate_save_post', function () {

    $post_id   = isset( $_POST['post_ID'] ) ? (int) $_POST['post_ID'] : 0;
    $post_type = get_post_type( $post_id );

    if ( 'olthem_thematique' !== $post_type ) {
        return;
    }

    $required_fields = array(
        'field_olthem_thm_titre'              => 'Titre',
        'field_olthem_thm_descriptif_desktop' => 'Descriptif desktop',
        'field_olthem_thm_descriptif_mobile'  => 'Descriptif mobile',
        'field_olthem_thm_visuel'             => 'Visuel du thème',
        'field_olthem_thm_couleur'            => 'Couleur du thème',
    );

    $acf_values = isset( $_POST['acf'] ) ? $_POST['acf'] : array();
    $errors     = array();

    foreach ( $required_fields as $field_key => $field_label ) {
        $value = isset( $acf_values[ $field_key ] ) ? $acf_values[ $field_key ] : '';

        if ( '' === trim( (string) $value ) || null === $value ) {
            $errors[] = $field_label;
        }
    }

    if ( ! empty( $errors ) ) {
        acf_add_validation_error(
            '',
            sprintf(
                'Impossible de publier cette thématique. Les champs suivants sont obligatoires : %s.',
                implode( ', ', $errors )
            )
        );
    }

}, 10 );