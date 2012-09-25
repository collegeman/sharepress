<?php
@define('SP_TEST_MESSAGE', "I'm testing SharePress: a plugin for WordPress that helps you curate and autopost to Facebook, Twitter, and LinkedIn!");
@define('SP_TEST_URL', 'http://getsharepress.com');

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
   * @return Response data from third-party API, or WP_Error
   */
  function post($message, $config = '');

  /**
   * Test the client as configured.
   */
  function test();

}

class FacebookSharePressClient extends Facebook implements SharePressClient {

  function __construct($key, $secret, $profile = false) {
    parent::__construct(array(
      'appId' => $key,
      'secret' => $secret
    ));

    $this->profile = $profile;

    if ($profile) {
      $this->setAccessToken($profile->user_token);
    }
  }

  function profile() {
    if ($this->getUser()) {
      try {
        $user = $this->api('/me');
      } catch (Exception $e) {
        return false;
      } 
    }

    if (!$user) {
      return false;
    }

    // exchange access token
    $response = _wp_http_get_object()->post(sprintf(
      'https://graph.facebook.com/oauth/access_token?client_id=%s&client_secret=%s&grant_type=fb_exchange_token&fb_exchange_token=%s', 
      $this->getAppId(),
      $this->getAppSecret(),
      $this->getPersistentData('access_token')
    ));

    if (is_wp_error($response)) {
      $this->clearAllPersistentData();
      return $response;
    }

    parse_str($response['body'], $result);

    return (object) array(
      'service' => 'facebook',
      'service_id' => $user['id'],
      'formatted_username' => $user['username'],
      'service_username' => $user['username'],
      'avatar' => 'https://graph.facebook.com/'.$user['id'].'/picture',
      'user_token' => $result['access_token']
    );
  }
    
  function loginUrl() {
    return $this->getLoginUrl(array(
      'scope' => 'email,read_stream,publish_stream,manage_pages,share_item'
    ));
  }

  function profiles() {
    if (empty($this->profile->config['is_page'])) {
      try {
        $profiles = array();
        $response = $this->api($this->profile->service_id.'/accounts');
        foreach($response['data'] as $page) {
          if ($page['category'] != 'Application' && in_array('CREATE_CONTENT', $page['perms'])) {
            $profiles[] = (object) array(
              'service' => 'facebook',
              'service_id' => $page['id'],
              'user_token' => $page['access_token'],
              'formatted_username' => $page['name'],
              'service_username' => $page['id'],
              'avatar' => 'https://graph.facebook.com/'.$page['id'].'/picture',
              'config' => array('is_page' => true)
            );
          }
        }
        return $profiles;
      } catch (Exception $e) {
        return new WP_Error('error', $e->getMessage());
      }
    }
  }

  function post($message, $config = '') {
    try {
      return $this->api(
        $this->profile->service_id.'/links',
        'POST',
        array(
          'message' => $message,
          'link' => $config['url']
        )
      );
    } catch (Exception $e) {
      return new WP_Error(($code = $e->getCode()) ? $code : 'error', $e->getMessage());
    }
  }

  function test($message = false, $url = false) {
    return $this->post(
      $message ? $message : constant('SP_TEST_MESSAGE'),
      array(
        'url' => $url ? $url : constant('SP_TEST_URL')
      ) 
    );
  }

}

class TwitterSharePressClient implements SharePressClient {

  function __construct($key, $secret, $profile = false) {
    $this->consumer = new oAuthConsumer($key, $secret);
    if ($profile) {
      $this->profile = $profile;
      $this->user = new oAuthToken($profile->user_token, $profile->user_secret);
    }
  }

  function profile() {

    if (!isset($_REQUEST['oauth_verifier'])) {
      return false;

    } else {
      if (!wp_verify_nonce($_REQUEST['state'], 'twitter-state')) {
        return new WP_Error('nonce', 'Invalid nonce.');
      }

      $oauth = oAuthRequest::from_consumer_and_token(
        $this->consumer,
        null,
        'POST',
        'https://api.twitter.com/oauth/access_token',
        array(
          'oauth_consumer_key' => $this->consumer->key,
          'oauth_token' => $_REQUEST['oauth_token'],
          'oauth_verifier' => $_REQUEST['oauth_verifier']
        )
      );

      // sign using HMAC-SHA1
      $oauth->sign_request(
        new oAuthSignatureMethod_HMAC_SHA1(),
        $this->consumer,
        $_SESSION['twitter-request-token']
      );

      unset($_SESSION['twitter-request-token']);

      // get the oauth_token
      if (is_wp_error($result = wp_remote_post($oauth->to_url()))) {
        return $result;
      }
      if ($result['response']['code'] !== 200) {
        return new WP_Error("oauth-access-fail", "Unsuccessful authentication");
      }

      parse_str($result['body'], $response);

      return (object) array(
        'service' => 'twitter',
        'service_id' => $response['user_id'],
        'user_token' => $response['oauth_token'],
        'user_secret' => $response['oauth_token_secret'],
        'formatted_username' => '@'.$response['screen_name'],
        'service_username' => $response['screen_name'],
        'avatar' => 'https://api.twitter.com/1/users/profile_image?screen_name='.$response['screen_name']
      );      

    } 
  }

  function profiles() {
    return array();
  }

  function loginUrl() {
    $oauth = oAuthRequest::from_consumer_and_token(
      $this->consumer,
      null,
      'POST',
      'https://api.twitter.com/oauth/request_token',
      array(
        'oauth_consumer_key' => $this->consumer->key,
        'oauth_callback' => site_url($_SERVER['REQUEST_URI']).'?state='.wp_create_nonce('twitter-state'),
        'x_auth_access_type' => 'write'
      )
    );

    // sign using HMAC-SHA1
    $oauth->sign_request(
      new oAuthSignatureMethod_HMAC_SHA1(),
      $this->consumer,
      null
    );

    if (is_wp_error($result = wp_remote_post($oauth->to_url()))) {
      return $result;
    }
    if ($result['response']['code'] !== 200) {
      return new WP_Error("oauth-request-fail", "Unsuccessful authentication"); 
    }

    parse_str($result['body'], $request_token);

    $_SESSION['twitter-request-token'] = $request_token;
      
    return 'https://api.twitter.com/oauth/authorize?force_login=1&oauth_token='.$_SESSION['twitter-request-token']['oauth_token'];
  }

  function post($message, $config = '') {
    if (!empty($config['url'])) {
      $message .= ' '.$config['url'];
    }

    $oauth = oAuthRequest::from_consumer_and_token(
      $this->consumer,
      $this->user,
      'POST',
      'https://api.twitter.com/1/statuses/update.json',
      array(
        'status' => $message
      )
    );

    $oauth->sign_request(
      new oAuthSignatureMethod_HMAC_SHA1(),
      $this->consumer,
      $this->user
    );

    $params = array(
      'body' => $oauth->to_postdata(),
      'sslverify' => false,
      'headers' => array(
        'Expect:'
      )
    );

    if (is_wp_error($result = wp_remote_post('https://api.twitter.com/1/statuses/update.json', $params))) {
      return $result;
    } else {
      if ($result['response']['code'] !== 200) {
        $response = json_decode($result['body']);
        return new WP_Error(strtolower($result['response']['message']), $response->error);
      } else {
        return json_decode($result['body']);
      }
    }  
  }

  function test($message = false, $url = false) {
    return $this->post(
      $message ? $message : constant('SP_TEST_MESSAGE'),
      array(
        'url' => $url ? $url : constant('SP_TEST_URL')
      ) 
    );
  }

}