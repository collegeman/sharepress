<?php
/**
 * Working with WordPress MU or Multi-Network? Place this file in 
 * wp-content/mu-plugins to enable the sharing of Facebook App ID
 * and App Secret keys across all sites. Optionally, allow all sites
 * to share the same access token.
 */

//
// If you want for all sites in your MU or Multi-Network installation to
// share a single Facebook access token, set this constant to true.
//
@define('SHAREPRESS_MU_SHARED_ACCESS_TOKEN', false);

///////////////////////////////////////////////////////////////////////////////
// You should not need to edit below this line
///////////////////////////////////////////////////////////////////////////////

@define('SHAREPRESS_MU', true);

class SharePressMu {
  
  private static $plugin;
  static function load() {
    $class = __CLASS__; 
    return ( self::$plugin ? self::$plugin : ( self::$plugin = new $class() ) );
  }

  private function __construct() {
    add_action('sharepress_init', array($this, 'init'));
  }

  function sharepress_init() {
    add_filter('option_sharepress_api_key', array($this, 'api_key'));
    add_filter('option_sharepress_app_secret', array($this, 'app_secret')); 
    add_filter('pre_update_option_sharepress_api_key', array($this, 'update_api_key'), 10, 3);
    add_filter('pre_update_option_sharepress_app_secret', array($this, 'update_app_secret'), 10, 3);

    if (SHAREPRESS_MU_SHARED_ACCESS_TOKEN) {
      add_filter('option_sharepress_session', array($this, 'session'));
      add_filter('pre_update_option_sharepress_session', array($this, 'update_session'), 10, 3);
    }
  }

  function api_key($value) {
    return get_site_option('sharepress_api_key');
  }

  function update_api_key($option, $newvalue, $oldvalue) {
    update_site_option('sharepress_api_key', $newvalue);
  }

  function app_secret($value) {
    return get_site_option('sharepress_app_secret');
  }

  function update_app_secret($option, $newvalue, $oldvalue) {
    update_site_option('sharepress_app_secret', $newvalue);
  }

  function session($value) {
    return get_site_option('sharepress_session');
  }

  function update_session($option, $newvalue, $oldvalue) {
    update_site_option('sharepress_session', $newvalue);
  }

}

SharePressMu::load();