<?php
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
      return new WP_Error(($code = $e->getCode()) ? $code : 'error', $e->getMessage());
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

}