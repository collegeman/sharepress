<?php
class TwitterSharePressClient implements SharePressClient {

  function __construct($key, $secret, $profile = false) {
    require_once(SP_DIR.'/includes/oauth.php');
    $this->consumer = new SpOAuthConsumer($key, $secret);
    if ($profile) {
      $this->profile = $profile;
      $this->user = new SpOAuthToken($profile->user_token, $profile->user_secret);
    }
  }

  function getName() {
    return "Twitter";
  }

  function profile() {
    if (!isset($_REQUEST['oauth_verifier'])) {
      
      return false;

    } else {
      if (!wp_verify_nonce($_REQUEST['state'], 'twitter-state')) {
        return new WP_Error('nonce', 'Invalid nonce.');
      }

      $oauth = SpOAuthRequest::from_consumer_and_token(
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
        new SpOAuthSignatureMethod_HMAC_SHA1(),
        $this->consumer,
        $_SESSION['twitter-request-token']
      );

      unset($_SESSION['twitter-request-token']);

      // get the oauth_token
      if (is_wp_error($result = wp_remote_post($oauth->to_url()))) {
        return $result;
      }
      if ($result['response']['code'] != 200) {
        return new WP_Error("oauth-access-fail", "Unsuccessful authentication");
      }

      parse_str($result['body'], $response);
      $request_token = new SpOAuthToken($response['oauth_token'], $response['oauth_token_secret']);
      
      $profile_data = $this->getProfileData($request_token, $response['screen_name']);

      return (object) array(
        'service' => 'twitter',
        'service_id' => $response['user_id'],
        'user_token' => $response['oauth_token'],
        'user_secret' => $response['oauth_token_secret'],
        'formatted_username' => '@'.$response['screen_name'],
        'service_username' => $response['screen_name'],
        'link' => 'http://twitter.com/'.$response['screen_name'],
        'avatar' => $profile_data->profile_image_url_https,
        'limit' => 140
      );      

    } 
  }

  function profiles() {
    return array();
  }

  function getProfileData($request_token, $screen_name) {
    $oauth = SpOAuthRequest::from_consumer_and_token(
      $this->consumer,
      $request_token,
      'GET',
      'https://api.twitter.com/1.1/users/show.json',
      array(
        'screen_name' => $screen_name
      )
    );
    
    // sign using HMAC-SHA1
    $oauth->sign_request(
      new SpOAuthSignatureMethod_HMAC_SHA1(),
      $this->consumer,
      $request_token
    );    

    if (is_wp_error( $profile = wp_remote_get( $oauth->to_url() ) )){
      return new WP_Error("profile-lookup-fail", "Authenication happened but profile lookup failed");
    }

    return json_decode( $profile['body'] );
  }

  function loginUrl($redirect_uri = false) {
    $oauth = SpOAuthRequest::from_consumer_and_token(
      $this->consumer,
      null,
      'POST',
      'https://api.twitter.com/oauth/request_token',
      array(
        'oauth_consumer_key' => $this->consumer->key,
        'oauth_callback' => ( $redirect_uri ? $redirect_uri : site_url($_SERVER['REQUEST_URI']) ) . '?state='.wp_create_nonce('twitter-state'),
        'x_auth_access_type' => 'write'
      )
    );

    // sign using HMAC-SHA1
    $oauth->sign_request(
      new SpOAuthSignatureMethod_HMAC_SHA1(),
      $this->consumer,
      null
    );

    if (is_wp_error($result = wp_remote_post($oauth->to_url()))) {
      return $result;
    }

    if ($result['response']['code'] != 200) {
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

    $oauth = SpOAuthRequest::from_consumer_and_token(
      $this->consumer,
      $this->user,
      'POST',
      'https://api.twitter.com/1.1/statuses/update.json',
      array(
        'status' => $message,
        'trim_user' => 1
      )
    );

    $oauth->sign_request(
      new SpOAuthSignatureMethod_HMAC_SHA1(),
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
  
    if (is_wp_error($result = wp_remote_post('https://api.twitter.com/1.1/statuses/update.json', $params))) {
      return $result;
    } else {
      $response = json_decode($result['body']);
      if (property_exists($response, 'errors') && $response->errors[0]->code != 200) {
        $code = $response->errors[0]->code;
        if ($code === 401) {
          $code = SharePressClient::ERROR_AUTHENTICATION;
        }
        return new WP_Error($code, strtolower($response->errors[0]->message).': '.$response->errors);
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

  function settings_keys_section() {
    ?>
      <p>Drop in your Twitter App public key and secret below. Don't know what this means? <a href="#">Read this tutorial</a></p>
    <?php
  }

  function settings($page, $option_group, $service) {
    add_settings_section($option_group.'-keys', 'App Keys', array($this, 'settings_keys_section'), $page);
    register_setting($option_group, "sp_{$service}_key");
    add_settings_field($option_group.'-key', 'Public Key', 'sp_settings_field_text', $page, $option_group.'-keys', 
      array('label_for' => "sp_{$service}_key"));
    register_setting($option_group, "sp_{$service}_secret");
    add_settings_field($option_group.'-secret', 'Secret Key', 'sp_settings_field_text', $page, $option_group.'-keys',
      array('label_for' => "sp_{$service}_secret"));
  }

}

add_action('sp_add_new_account_menu', 'sp_add_new_account_menu_twitter');
function sp_add_new_account_menu_twitter() {
  echo '<li><a tabindex="-1" href="#">Add Twitter Profile</a></li>';
}