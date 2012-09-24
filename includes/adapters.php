<?php

interface SharePressClient {

  function __construct($id, $secret);
  function profile();
  function loginUrl();
  function logoutUrl();

}

class FacebookSharePressClient extends Facebook implements SharePressClient {

  function __construct($id, $secret) {
    parent::__construct(array(
      'appId' => $id,
      'secret' => $secret
    ));
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
      return false;
    }

    parse_str($response['body'], $result);

    return (object) array(
      'service' => 'facebook',
      'service_id' => $user['id'],
      'formatted_username' => $user['username'],
      'service_username' => $user['username'],
      'avatar' => 'https://graph.facebook.com/'.$user['id'].'/picture',
      'access_token' => $result['access_token']
    );
  }
    
  function loginUrl() {
    return $this->getLoginUrl(array(
      'scope' => 'read_stream,publish_stream,manage_pages,share_item'
    ));
  }

  function logoutUrl() {

  }

}

