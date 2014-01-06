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
    add_submenu_page('sp-settings', 'SharePress Add-ons', 'Add-ons', 'manage_options', 'sp-addons', 'sp_addons_page');
  }
}

function sp_addons_page() {
  $premium = array();
  $free = array();

  $premium[] = array(
    'title' => 'Support',
    'description' => 'Technical support from SharePress developers, and advanced access to new features.',
    'icon' => 'fa-wrench',
    'icon-color' => '',
    'bg' => '#FFA000',
    'name' => 'sharepress-support'
  );

  $premium[] = array(
    'title' => 'Twitter',
    'description' => 'Custom messages for Twitter.',
    'icon' => 'fa-twitter',
    'icon-color' => '',
    'bg' => '#6EB4F9',
    'name' => 'sharepress-twitter'
  );

  $premium[] = array(
    'title' => 'Facebook Pages',
    'description' => 'Custom messages for Facebook, and access to Facebook Pages.',
    'icon' => 'fa-facebook',
    'icon-color' => '',
    'bg' => '#3B5B93',
    'name' => 'sharepress-facebook'
  );

  $premium[] = array(
    'title' => 'Google+',
    'description' => 'Post to Google+ Profiles.',
    'icon' => 'fa-google-plus',
    'icon-color' => '',
    'bg' => '#E15B52',
    'name' => 'sharepress-googleplus'
  );

  $premium[] = array(
    'title' => 'LinkedIn',
    'description' => 'Post to LinkedIn Profiles.',
    'icon' => 'fa-linkedin',
    'icon-color' => '',
    'bg' => '#57A9CB',
    'name' => 'sharepress-linkedin'
  );

  $premium[] = array(
    'title' => 'Repeater',
    'description' => 'Automatically schedule and repeat social media updates.',
    'icon' => 'fa-refresh',
    'icon-color' => '',
    'bg' => '#FFC46A',
    'name' => 'sharepress-repeater'
  );

  $premium[] = array(
    'title' => 'Buffer',
    'description' => 'Stop scheduling and start buffering posts: feed the beast!',
    'icon' => 'fa-th-list',
    'icon-color' => '',
    'bg' => '#18944B',
    'name' => 'sharepress-buffer'
  );

  $premium[] = array(
    'title' => 'Authors',
    'description' => 'Post to your Authors\' social media profiles, too.',
    'icon' => 'fa-user',
    'icon-color' => '',
    'bg' => '#819197',
    'name' => 'sharepress-authors'
  );

  $premium[] = array(
    'title' => 'More Social Metadata',
    'description' => 'Twitter Cards and other custom metadata options for each social media channel.',
    'icon' => 'fa-cogs',
    'icon-color' => '',
    'bg' => '#2675B2',
    'name' => 'sharepress-advanced-metadata'
  );

  $premium[] = array(
    'title' => 'Calendar',
    'description' => 'See what you have scheduled, and manage your editorial cycle.',
    'icon' => 'fa-calendar',
    'icon-color' => '',
    'bg' => '#945A56',
    'name' => 'sharepress-calendar'
  );

  $premium[] = array(
    'title' => 'Meme',
    'description' => 'Add text to any image in your library.',
    'icon' => 'fa-share',
    'icon-color' => '',
    'bg' => '#50B2FE',
    'name' => 'sharepress-meme'
  );

  $premium[] = array(
    'title' => 'Convo',
    'description' => 'Bring the conversation home: comments and forums, no styling needed, mobile-ready.',
    'icon' => 'fa-comments',
    'icon-color' => '',
    'bg' => '#222222',
    'name' => 'sharepress-convo'
  );

  $premium[] = array(
    'title' => 'iPhone App',
    'description' => 'Wrap your responsive site for the App Store, announce new content with push notifications.',
    'icon' => 'fa-apple',
    'icon-color' => '',
    'bg' => '#FE9343',
    'name' => 'sharepress-apple-app'
  );

  $premium[] = array(
    'title' => 'Android App',
    'description' => 'Wrap your responsive site for Google Play, announce new content with push notifications.',
    'icon' => 'fa-android',
    'icon-color' => '',
    'bg' => '#4CB238',
    'name' => 'sharepress-android-app'
  );

  $free[] = array(
    'title' => 'Social Login',
    'description' => 'Comments can login using Facebook, Twitter, Google+ and LinkedIn.',
    'icon' => 'fa-key',
    'icon-color' => '',
    'bg' => '#B23892',
    'name' => 'sharepress-social-login'
  );

  foreach($premium as &$plugin) {
    $plugin['active'] = sp_is_plugin_active($plugin['name']);
  }

  foreach($free as &$plugin) {
    $plugin['active'] = sp_is_plugin_active($plugin['name']);
  }

  wp_enqueue_style('sp-addons', SP_URL.'/css/addons.css');
  wp_enqueue_style('sp-fontawesome', '//netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css');
  sp_require_view('addons', array(
    'premium' => $premium,
    'free' => $free
    )
  );
}