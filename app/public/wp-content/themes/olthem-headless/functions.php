<?php

add_action('admin_head', function() {
  echo '<style>
.acf-input .acf-input-prepend,
.acf-input .acf-input-append {
  height: fit-content !important;
}
</style>';
});

add_action('acf/init', function() {
  if( function_exists('acf_add_options_page') ) {

    acf_add_options_page(array(
        'page_title'    => 'Informations generales',
        'menu_title'    => 'Informations generales',
        'menu_slug'     => 'olthem-informations-generales',
        'post_id'       => 'options',
        'capability'    => 'edit_posts',
        'redirect'      => false
    ));
  }
});

add_filter('acf/load_field_group/key=group_69ada387d4db2', function($group) {
  $group['active'] = true;
  $group['location'] = array(
    array(
      array(
        'param' => 'options_page',
        'operator' => '==',
        'value' => 'olthem-informations-generales',
      ),
    ),
  );

  return $group;
});
