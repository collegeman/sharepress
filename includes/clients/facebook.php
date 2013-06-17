<?php
if (!class_exists('Facebook')) {
  require(SP_DIR.'/lib/facebook-sdk/facebook.php');
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

  function getName() {
    return "Facebook";
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
    
  function loginUrl($redirect_uri = false) {
    $config = array(
      'scope' => 'email,read_stream,publish_stream,manage_pages,share_item'
    );
    if ($redirect_uri) {
      $config['redirect_uri'] = $redirect_uri;
    }
    return $this->getLoginUrl($config);
  }

  function profiles() {
    return false;
  }

  function post($message, $config = '') {
    try {
      if (!empty($config['url'])) {
        $response = $this->postLink($message, $config);
      } else {
        $response = $this->postFeed($message, $config);
      }
      
      $id = $response['id'];
      unset($response['id']);
      
      return (object) array(
        'service_update_id' => $id,
        'data' => (object) $response
      );
    } catch (Exception $e) {
      $result = $e->getResult();
      if ($result['error']['code'] === 190) {
        $code = SharePressClient::ERROR_AUTHENTICATION;
      } else {
        $code = $result['error']['code'];
      }
      return new WP_Error($code, $e->getMessage());
    }
  }

  private function postLink($message, $config = '') {
    return $this->api(
      $this->profile->service_id.'/links',
      'POST',
      array(
        'message' => $message,
        'link' => $config['url']
      )
    );
  }

  private function postFeed($message, $config = '') {
    return $this->api(
      $this->profile->service_id.'/feed',
      'POST',
      array(
        'message' => $message
      )
    );
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
      <p>Drop in your Facebook App Id and Secret below. Don't know what this means? <a href="#">Read this tutorial</a></p>
    <?php
  }

  function settings($page, $option_group, $service) {
    add_settings_section($option_group.'-keys', 'App Keys', array($this, 'settings_keys_section'), $page);
    register_setting($option_group, "sp_{$service}_key");
    add_settings_field($option_group.'-key', 'App Id', 'sp_settings_field_text', $page, $option_group.'-keys', 
      array('label_for' => "sp_{$service}_key"));
    register_setting($option_group, "sp_{$service}_secret");
    add_settings_field($option_group.'-secret', 'App Secret', 'sp_settings_field_text', $page, $option_group.'-keys',
      array('label_for' => "sp_{$service}_secret"));
  }

}

add_action('sp_add_new_account_menu', 'sp_add_new_account_menu_facebook');
function sp_add_new_account_menu_facebook() {
  $target = '_self';
  if (!buf_has_keys('facebook')) {
    $href = admin_url('options-general.php?page=sharepress');
    $target = '_blank';
  }
  echo sprintf('<li><a tabindex="-1" href="%s" target="%s">Add Facebook Profile</a></li>', $href, $target);
}