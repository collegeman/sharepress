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
  $premium = array();
  $free = array();

  $premium[] = array(
    'title' => 'Support',
    'description' => 'Technical support from SharePress developers, and advanced access to new features',
    'icon' => 'fa-wrench',
    'icon-color' => '',
    'bg' => '#FFA000',
    'active' => sp_is_plugin_active('sharepress-support')
  );

  $premium[] = array(
    'title' => 'Twitter',
    'description' => 'Customize your Tweets',
    'icon' => 'fa-twitter',
    'icon-color' => '',
    'bg' => '#6EB4F9',
    'active' => sp_is_plugin_active('sharepress-twitter')
  );

  $premium[] = array(
    'title' => 'Facebook Pages',
    'description' => 'Post to Facebook Pages',
    'icon' => 'fa-facebook',
    'icon-color' => '',
    'bg' => '#3B5B93',
    'active' => sp_is_plugin_active('sharepress-facebook-pages')
  );

  $premium[] = array(
    'title' => 'Google+',
    'description' => 'Post to Google+ Profiles',
    'icon' => 'fa-google-plus',
    'icon-color' => '',
    'bg' => '#E15B52',
    'active' => sp_is_plugin_active('sharepress-googleplus')
  );

  $premium[] = array(
    'title' => 'LinkedIn',
    'description' => 'Post to LinkedIn Profiles',
    'icon' => 'fa-linkedin',
    'icon-color' => '',
    'bg' => '#57A9CB',
    'active' => sp_is_plugin_active('sharepress-linkedin')
  );

  $premium[] = array(
    'title' => 'Repeater',
    'description' => 'Automatically repeat posts',
    'icon' => 'fa-refresh',
    'icon-color' => '',
    'bg' => '#FFC46A',
    'active' => sp_is_plugin_active('sharepress-repeater')
  );

  $premium[] = array(
    'title' => 'Buffer',
    'description' => 'Stop scheduling and start buffering posts: feed the beast!',
    'icon' => 'fa-th-list',
    'icon-color' => '',
    'bg' => '#18944B',
    'active' => sp_is_plugin_active('sharepress-buffer')
  );

  $premium[] = array(
    'title' => 'Authors',
    'description' => 'Post to your Authors\' social media profiles, too',
    'icon' => 'fa-pencil',
    'icon-color' => '',
    'bg' => '#819197',
    'active' => sp_is_plugin_active('sharepress-authors')
  );

  $premium[] = array(
    'title' => 'Advanced Social Metadata',
    'description' => 'Twitter Cards, and custom meta data on a service-by-service basis',
    'icon' => 'fa-bullhorn',
    'icon-color' => '',
    'bg' => '#2675B2',
    'active' => sp_is_plugin_active('sharepress-advanced-metadata')
  );

  $premium[] = array(
    'title' => 'Calendar',
    'description' => 'See what you have scheduled, and manage your editorial cycle',
    'icon' => 'fa-calendar',
    'icon-color' => '',
    'bg' => '#945A56',
    'active' => sp_is_plugin_active('sharepress-calendar')
  );

  $premium[] = array(
    'title' => 'Meme',
    'description' => 'Add text to any image in your library',
    'icon' => 'fa-fighter-jet',
    'icon-color' => '',
    'bg' => '#50B2FE',
    'active' => sp_is_plugin_active('sharepress-meme')
  );

  $premium[] = array(
    'title' => 'Convo',
    'description' => 'Bring the conversation home: comments and forums, mobile-ready',
    'icon' => 'fa-beer',
    'icon-color' => '',
    'bg' => '#4A3E31',
    'active' => sp_is_plugin_active('sharepress-convo')
  );

  $premium[] = array(
    'title' => 'iPhone App',
    'description' => 'Wrap your responsive WordPress site for the App Store, announce new content with push notifications',
    'icon' => 'fa-apple',
    'icon-color' => '',
    'bg' => '#FE9343',
    'active' => sp_is_plugin_active('sharepress-iphone-app')
  );

  $premium[] = array(
    'title' => 'Android App',
    'description' => 'Wrap your responsive WordPress site for Google Play',
    'icon' => 'fa-android',
    'icon-color' => '',
    'bg' => '#4CB238',
    'active' => sp_is_plugin_active('sharepress-android-app')
  );

  $free[] = array(
    'title' => 'Social Login',
    'description' => 'Allow your users to login for commenting',
    'icon' => 'fa-key',
    'icon-color' => '',
    'bg' => '#B23892',
    'active' => sp_is_plugin_active('sharepress-social-login')
  );

  wp_enqueue_style('sp-addons', SP_URL.'/css/addons.css');
  wp_enqueue_style('sp-fontawesome', '//netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css');
  sp_require_view('addons', array(
    'premium' => $premium,
    'free' => $free
    )
  );
}