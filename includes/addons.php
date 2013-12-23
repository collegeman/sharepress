<?php
add_action('admin_menu', 'sp_addons_menu');

/**
 * Is the given addon installed?
 * @param String Test string
 * ** Would be great if $addon could be "plugin-folder/plugin-file.php",
 * as that would be very consistent with other functionality. There may
 * even be something like this already in the WP API.
 */
function sp_addon_installed($addon) {
  return false;
}

function sp_addons_menu() {
  if (apply_filters('sp_show_settings_screens', true)) {
    add_submenu_page('sp-settings', 'Add-ons', 'Add-ons', 'manage_options', 'sp-addons', 'sp_addons_page');
  }
}

function sp_addons_page() {
  wp_enqueue_style('sp-addons', SP_URL.'/css/addons.css');
  sp_require_view('addons');
}