<?php
add_action('activate_sharepress/sharepress.php', 'sp_activate');
add_action('init', 'sp_init', 1, 12);

function sp_init() {
  $dir = opendir(SP_DIR.'/includes/clients');
  while($client = readdir($dir)) {
    if ($client !== '.' && $client !== '..') {
      require(SP_DIR.'/includes/clients/'.$client);
    }
  }
  closedir($dir);
  $dir = opendir(SP_DIR.'/pro/includes/clients');
  while($client = readdir($dir)) {
    if ($client !== '.' && $client !== '..') {
      require(SP_DIR.'/pro/includes/clients/'.$client);
    }
  }
  closedir($dir);
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

/**
 * Shorten the given URL.
 * @param string $url
 * @param boolean $flush (optional) When true, flush cached results
 */
function sp_shorten($url, $flush = false) {
  $shortened = false;
  $shortened_cache_key = 'sp_shortened_'.md5($url);
  
  if (!$flush) {
    $shortened = get_transient($shortened_cache_key);
  }

  if (!$shortened) {
    // allow for override by plugins
    if ($url === ($shortened = apply_filters('sp_shorten', $url, $flush))) {
      // use goo.gl
      $result = wp_remote_post('https://www.googleapis.com/urlshortener/v1/url', array(
        'body' => json_encode(array('longUrl' => $url)),
        'headers' => array(
          'Content-Type' => 'application/json'
        )
      ));

      if (is_wp_error($result)) {
        return $result;
      }

      $response = json_decode($result['body']);
      if ($result['response']['code'] !== 200) {
        return new WP_Error('shorten', $response->error->errors[0]->message);
      }  

      $shortened = $response->id;
    }

    if (is_wp_error($shortened)) {
      return $shortened; 
    }
    
    set_transient($shortened_cache_key, $shortened, 60 * 60);
  }

  return $shortened;
}

/**
 * Crawl the given URL and return the title of the document, and
 * a shortened version of the URL.
 * @param string $url
 * @param boolean $flush (optional) When true, flush cached results
 */
function sp_crawl($url, $flush = false) {
  $shortened = sp_shorten($url, $flush);

  $title_cache_key = 'sp_title_'.md5($url);
  $title = get_transient($title_cache_key);

  if (!$title) {
    $title = '';

    $result = wp_remote_get($url, array(
      'timeout' => 5,
      'sslverify' => false
    ));

    if (is_wp_error($result)) {
      return $result;
    }

    if (strpos($result['response']['code'], '2') !== 0) {
      return new WP_Error('crawl', 'Hmm... I got a '.$result['response']['code'].'. Maybe try again later?');
    }
    
    if (strpos($result['headers']['content-type'], 'text/html') !== false) {
      if (preg_match('#<title.*?>(.*?)</title>#mi', $result['body'], $matches)) {
        $title = trim($matches[1]);
      }
    }

    set_transient($title_cache_key, $title, 60 * 5);
  }
  
  return (object) array(
    'url' => $url,
    'short' => $shortened,
    'title' => $title
  );
}

interface SharePressClient {

  const ERROR_AUTHENTICATION = 401;

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
   * Test the client as configured by posting to the service
   * using SharePressClient::post(SP_TEST_MESSAGE, array('url' => SP_TEST_URL)),
   * or $message and/or $url, when supplied.
   * @param string $message (optional) will use SP_TEST_MESSAGE instead
   * @param string $url (optional) will use SP_TEST_URL instead
   */
  function test($message = false, $url = false);

}