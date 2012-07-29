<?php
add_action('activate_sharepress/sharepress.php', 'sp_activate');

function sp_get_opt($option, $default = false) {
  return apply_filters("sp_get_opt_{$option}", get_option('sp_'.$option, $default));
}

function sp_set_opt($option, $value) {
  return update_option($option, apply_filters("sp_set_opt_{$option}", $value));
}

function sp_activate() {
  do_action('sp_activated');
}
