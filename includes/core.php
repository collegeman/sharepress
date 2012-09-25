<?php
add_action('activate_sharepress/sharepress.php', 'sp_activate');
add_action('init', 'sp_init', 1, 12);

function sp_init() {
  if (!class_exists('Facebook')) {
    require(SP_DIR.'/lib/facebook-sdk/facebook.php');
  }
  require(SP_DIR.'/includes/clients.php');
}

function sp_get_opt($option, $default = false) {
  return apply_filters("sp_get_opt_{$option}", get_option('sp_'.$option, $default));
}

function sp_set_opt($option, $value) {
  return update_option('sp_'.$option, apply_filters("sp_set_opt_{$option}", $value));
}

function sp_activate() {
  do_action('sp_activated');
}