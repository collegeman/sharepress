<?php
add_action('init', 'sp_init', 1, 12);

/**
 * Load a view file, search for paths defined by the
 * filter "sp_view_paths".
 * @param String A view file name
 * @param array Hashmap of variables that should be available to the view
 */
function sp_require_view($view, $params = array()) {
  extract($params);
  $paths = apply_filters('sp_view_paths', array(SP_DIR.'/views'));
  foreach($paths as $path) {
    if (file_exists($file = $path.'/'.$view.'.php')) {
      require($file);
      break;
    }
  }
}

function sp_activate() {
  do_action('sp_activated');
}

function sp_deactivate() {
  do_action('sp_deactivated');
}

/**
 * Is the given plugin activated?
 * @param String plugin-directory/plugin-file.php or simply plugin-directory
 * @return bool
 */
function sp_is_plugin_active($plugin) {
  include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
  return is_plugin_active( strpos($plugin, '/') ? $plugin : "$plugin/plugin.php" );
}

/**
 * Log given message to a SharePress-specific log file. If the requested
 * log level is not ERROR and not WARN and WP_DEBUG is not true, then log
 * request will be ignored.
 * @param mixed The message to print in the log; if this argument is a WP_Error
 * object, the level will automatically be set to ERROR
 * @param String the logging level: ERROR, WARN, or DEBUG
 */
function sp_log($message, $level = 'DEBUG') {
  global $sp_thread_id, $blog_id;
  
  // normalize $level argument
  if (is_wp_error($message)) {
    $level = 'ERROR';
  } else {
    $level = strtoupper($level);
  }
  // if not in debugging mode, ignore non-critical messages
  if (( !defined('WP_DEBUG') || !WP_DEBUG ) && !apply_filters('sp_is_debug_enabled', false)) {
    if ($level !== 'ERROR' && $level !== 'WARN') {
      return;
    }
  }

  // establish uniq ID for this request
  if (is_null($sp_thread_id)) {
    $sp_thread_id = substr(md5(uniqid()), 0, 6);
  }

  // create an obscured log name
  $filename = SP_DIR.'/sp-'.substr(md5('sharepress'.SECURE_AUTH_SALT), 0, 6).'-'.get_date_from_gmt(gmdate('Y-m-d H:i:s'), 'Ymd').'.log';
  if (is_wp_error($message)) {
    $message = $message->get_error_message();
  }
  $blog_id = (string) $blog_id;
  
  $message = sprintf("%-5s %s %s %-5s %s\n", 
    $blog_id ? $blog_id : 0, 
    $sp_thread_id, 
    get_date_from_gmt(gmdate('Y-m-d H:i:s'), 'H:i:s'), 
    strtoupper($level), 
    $message);

  if (!@file_put_contents($filename, $message, FILE_APPEND) || $level === 'ERROR') {
    error_log($message);
  }
}

function sp_init() {
  // load remaining dependencies...
  // TODO: try to only load the dependencies we need
  require(SP_DIR.'/includes/settings.php');
  require(SP_DIR.'/includes/profile.php');
  require(SP_DIR.'/includes/update.php');
  require(SP_DIR.'/includes/buffer.php');
  require(SP_DIR.'/includes/cron.php');
  require(SP_DIR.'/includes/api.php');
  require(SP_DIR.'/includes/ajax.php');
  require(SP_DIR.'/includes/metaboxes.php');
  require(SP_DIR.'/includes/metadata.php');
  require(SP_DIR.'/includes/addons.php');
  require(SP_DIR.'/includes/pointers.php');

  wp_register_script('sp_sharepress_script', SP_URL.'/js/sharepress.js', array('backbone'));
  wp_localize_script('sp_sharepress_script', 'sp', array(
    'url' => get_site_url(null), 
    'plugin' => SP_URL,
    'api' => site_url('/sp/1')
  ));

  do_action('sp_init');
}

/**
 * Seek out the public and private keys for the given service. Default sources
 * of keys are WP options (via sp_get_opt) and constants, with WP optiosn taking
 * precedence.
 * @param String $service The unique name for the service, e.g., 'facebook'
 * @return mixed If available, an array of the keys with entries "key" and "secret",
 * otherwise false.
 * @see sp_get_opt($name, $default)
 * @filter sp_has_installed($service, $keys) Allows for other plugins to override
 * the keys used for a given service.
 */
function sp_service_has_keys($service) {
  $lower = strtolower($service);
  $upper = strtoupper($service);
  
  $key = @sp_get_opt("{$lower}_key", constant("SP_{$upper}_KEY"));
  $secret = @sp_get_opt("{$lower}_secret", constant("SP_{$upper}_SECRET"));

  $keys = false;

  if ($key && $secret) {
    $keys = (object) array(
      'key' => $key,
      'secret' => $secret
    );  
  }

  if (apply_filters('sp_has_installed', $service, $keys)) {
    return $keys;
  }  

  return $keys;
}

function sp_get_opt_name($option) {
  return 'sp_'.$option;
}

function sp_get_opt($option, $default = false) {
  return apply_filters("sp_get_opt_{$option}", get_option(sp_get_opt_name($option), $default));
}

function sp_set_opt($option, $value) {
  return update_option(sp_get_opt_name($option), apply_filters("sp_set_opt_{$option}", $value));
}

/**
 * Store some arbitrary amount of data until the next time it is read
 */
function sp_flash($name, $value = null) {
  $key = 'sp_flash_'.$name;
  if (!is_null($value)) {
    set_transient($key, $value);
  } else {
    $value = get_transient($key);
    delete_transient($key);
    return $value;
  }
}

/**
 * An alias for identifying whether or not the current user is considered to
 * have administrative privileges over the buffering features of this application.
 * Currently this implies that the current user must have the "list_users" privilege,
 * which is currently given to WordPress Super Admins and Administrators.
 * @return bool
 */
function sp_current_user_is_admin() {
  return current_user_can('list_users');
}

/**
 * Is a particular client installed?
 * @param String the service name
 * @return bool
 */
function sp_has_client($service) {
  if (is_wp_error($client = sp_get_client($service))) {
    // if the error code is not equal to "client", then
    // the client is installed
    return $client->get_error_code() !== 'client';
  } else {
    return true;
  }
}

/**
 * Get a SharePressClient instance, optionally configured for accessing
 * the underlying network on behalf of the given SharePressProfile.
 * @param mixed $service Either a string uniquely naming a service, e.g., 'facebook',
 * or a SharePressProfile object from which the service name will be derived
 * (SharePressProfile::$service). 
 * @param SharePressProfile $profile Optionally, configure the client
 * for posting to this profile
 * @return SharePressClient or, in the case of misconfiguration, a WP_Error object.
 */
function sp_get_client($service, $profile = false) {
  global $sp_clients;

  if ($service instanceof SharePressProfile) {
    $profile = $service;
    $service = $profile->service;
  }

  $service = strtolower($service);

  do_action('pre_sp_get_client', $service);

  if (!isset($sp_clients[$service])) {
    if (file_exists($core = SP_DIR.'/includes/clients/'.$service.'.php')) {
      require_once($core);
    }
    do_action("init_sp_get_client_{$service}", $service);
  }

  $class = sprintf('%sSharePressClientPro', ucwords($service));
  if (!class_exists($class)) {
    $class = sprintf('%sSharePressClient', ucwords($service));
    if (!class_exists($class)) {
      return new WP_Error('client', "No client exists for service [$service]");
    }
  }

  if (!$keys = sp_service_has_keys($service)) {
    @session_start();
    $client = new $class(false, false);
    return new WP_Error(
      'keys', 
      "No keys configured for service [$service]",
      array(
        'client' => $client
      )
    );
  }

  @session_start();

  if (!$profile) {
    if (!isset($sp_clients[$service])) {
      $sp_clients[$service] = new $class($keys->key, $keys->secret);
    }
    return $sp_clients[$service];
  } else {
    $client = new $class($keys->key, $keys->secret, $profile);
  }

  return $client;
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

class SharePressClientLoader {

  private $file;
  private $service;

  function __construct($file, $service) {
    $this->file = $file;
    $this->service = $service;
    add_action("init_sp_get_client_{$this->service}", array($this, 'init'));
  }

  function init() {
    // because some people can't follow directions:
    if (file_exists($file = dirname($this->file).'/includes/clients/myspclient.php')) {
      require_once($file);
    }
    // and for everyone else:
    if (file_exists($file = dirname($this->file).'/includes/clients/'.$this->service.'.php')) {
      require_once($file);
    }
  }

}

function sp_register_client($file, $service) {
  new SharePressClientLoader($file, $service);
}

abstract class AbstractSharePressClient implements SharePressClient {

  protected $key;
  protected $secret;
  protected $profile;

  function __construct($key, $secret, $profile = false) {
    $this->key = $key;
    $this->secret = $secret;
    $this->profile = $profile;
  }

  function filter_update_text($text) {
    return '[title] [link]';
  }

  function getName() {
    return ucwords(str_ireplace('SharePressClient', '', get_class($this)));
  }

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
   * @return String A name to use as a label for this service.
   */
  function getName();
  
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
   *   'link' => '', // URL for remote profile page
   *   'user_token' => '', // current, authenticated user token, if available
   *   'user_secret' => '', // current, authenticated user secret, if available,
   *   'limit' => false, // a length limit, if any
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
   * @param String The URL to which the user should be redirected after logging in;
   *   set to false for no redirection
   */
  function loginUrl($redirect_uri = false);

  /**
   * Register the settings sections and fields for this client's config screen.
   * @param String The Id of the settings page that will display the
   *   fields; needed for 
   * @param String The option group name to use for creating sections and
   *   registering settings
   * @param String The service name used to load the client for configuration
   */
  function settings($page, $option_group, $service);

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