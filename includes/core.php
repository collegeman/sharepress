<?php
add_action('activate_sharepress/sharepress.php', 'sp_activate');
add_action('init', 'sp_init', 1, 12);

function sp_init() {
  if (!class_exists('Facebook')) {
    require(SP_DIR.'/lib/facebook-sdk/facebook.php');
  }
  require(SP_DIR.'/includes/clients/facebook.php');
  require(SP_DIR.'/includes/clients/twitter.php');
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

interface SharePressClient {

  /**
   * @param string Consumer Key
   * @param string Consumer Secret
   * @param SharePressProfile (optional) Profile to use for configuration
   */ 
  function __construct($key, $secret, $profile = false);

  /**
   * Implement authentication workflow for this client, connection sessions,
   * and retrieve structured profile. If false is returned instead, 
   * SharePressClient::getLoginUrl() should be used to initiate a session.
   * If some other error occurs, this function should return a WP_Error object.
   * @return stdClass An object representing the relationship between the local
   * PHP Session and the remote service, false if none exists, or WP_Error if
   * something else goes wrong.
   * Defined profile responses must have the following structure:
   * 
   * return (object) array(
   *   'service' => '', // service id, e.g., "facebook"
   *   'service_id' => '', // user's primary key on remote system
   *   'formatted_username' => '', // user's username formatted to remote spec, e.g., @collegeman on Twitter
   *   'service_username' => '', // alphanumeric-only username, e.g., collegeman
   *   'avatar' => '', // URL for remote profile picture
   *   'user_token' => '', // current, authenticated user token, if available
   *   'user_secret' => '', // current, authenticated user secret, if available,
   *   'config' => '' // optionally, extra configuration data
   * );
   */
  function profile();

  /**
   * @return Array of additional Profiles that are available by virtue
   * of this client's configuration, e.g., Facebook Pages.
   */
  function profiles();

  /**
   * @return The URL to which a user should be redirected for authentication.
   */
  function loginUrl();

  /**
   * Send a message to the remote system on behalf of the current session.
   * @return Response data from third-party API, or WP_Error. If response
   * from third-party API, must be formatted as follows:
   *
   * return (object) array(
   *   'service_update_id' => '', // primary key of response
   *   'data' => ... // complete response packet
   * );
   */
  function post($message, $config = '');

  /**
   * Test the client as configured.
   */
  function test();

}