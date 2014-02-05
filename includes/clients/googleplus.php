<?php
if (!class_exists('Google_Client')) {
  require(SP_DIR.'/lib/google-sdk/src/Google_Client.php');
  require(SP_DIR.'/lib/google-sdk/src/contrib/Google_PlusService.php');
}

class GooglePlusSharePressClient implements SharePressClient {
  
  function __construct($key, $secret, $profile = false) {
    $this->client = new Google_Client();
    $this->client->setApplicationName('GooglePlusSharePressClient');
    $this->client->setClientId($key);
    $this->client->setClientSecret($secret);
    $requestVisibleActions = array(
      'http://schemas.google.com/AddActivity',
      'http://schemas.google.com/ReviewActivity');
    $this->client->setRequestVisibleActions($requestVisibleActions);
    $this->plus = new Google_PlusService($this->client);
    if ($profile) {
      $this->profile = $profile;
      if ( $profile->user_token ) {
        $this->client->setAccessToken($profile->user_token);
      }
    }
  }

  function getName(){
    return "GooglePlus";
  }

  function profile() {
    if (!isset($_REQUEST['code'])) {
      return false;
    } else {
      if (!wp_verify_nonce($_REQUEST['state'], 'googleplus-state')) {
        return new WP_Error('nonce', 'Invalid nonce.');
      }
      
      $this->client->setRedirectUri( site_url($_SERVER['REDIRECT_URL']) );      
      $this->client->authenticate($_GET['code']);
      $access_token = $this->client->getAccessToken();
      $id_token = json_decode($access_token);
      $id_token = $id_token->id_token;
      $me = $this->plus->people->get('me');
      
      return (object) array(
        'service' => 'googleplus',
        'service_id' => md5($id_token),
        'user_token' => $access_token,
        'expires_in' => $access_token->expires_in,
        'expires_at' => time() + $access_token->expires_in,
        'formatted_username' => $me['displayName'],
        'avatar' => $me['image']['url'],
        'link' => $me['url'],
        'readonly' => true
      );      
    } 
  }

  function test( $message = false, $url = false ) {
    return $this->post(
      $message ? $message : constant('SP_TEST_MESSAGE'),
      array(
        'url' => $url ? $url : constant('SP_TEST_URL')
      ) 
    );
  }

  function post($message, $config = '') {
    //$this->client->getAccessToken();
    $moments = $this->plus->moments;
    $moment_body = new Google_Moment();
    $moment_body->setType("http://schemas.google.com/AddActivity");
    $item_scope = new Google_ItemScope();
    $item_scope->setId("target-id-1");
    $item_scope->setType("http://schemas.google.com/AddActivity");
    $item_scope->setName($message);
    $item_scope->setDescription($message);
    $item_scope->setImage("https://developers.google.com/+/plugins/snippet/examples/thing.png");
    $moment_body->setTarget($item_scope);
    $momentResult = $moments->insert('me', 'vault', $moment_body);
    return (object) array(
      'service_update_id' => $momentResult['id'],
      'data' => (object) $momentResult
    );
  }

  function loginUrl( $redirect_uri = false ) {
    $this->client->setRedirectUri( ( $redirect_uri ? $redirect_uri : site_url($_SERVER['REQUEST_URI']) ) );
    $this->client->setState(wp_create_nonce('googleplus-state'));
    return $this->client->createAuthUrl();
  }

   function settings_keys_section() {
    ?>
      <p>Drop in your Google App public key and secret below. Don't know what this means? <a href="https://getsharepress.com/docs/sharepress/googleplus" target="_new">Read this tutorial</a></p>
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

  function filter_update_text($text) {
    return '[title] [link]';
  }

  function profiles() {
    return array();
  }

}