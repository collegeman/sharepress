<?php
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
        'status' => $message,
        'trim_user' => 1
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
      $response = json_decode($result['body']);
      if ($result['response']['code'] !== 200) {
        $code = $result['response']['code'];
        if ($code === 401) {
          $code = SharePressClient::ERROR_AUTHENTICATION;
        }
        return new WP_Error($code, strtolower($result['response']['message']).': '.$response->error);
      } else {
        $id = $response->id;
        unset($response->id);
        return (object) array(
          'service_update_id' => $id,
          'data' => $response
        );
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

add_action('sp_add_new_account_menu', 'add_new_account_menu_twitter');
function add_new_account_menu_twitter() {
  echo '<li><a tabindex="-1" href="#">Add Twitter Profile</a></li>';
}