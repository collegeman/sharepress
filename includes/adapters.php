<?php

interface SharePressClient {

  function profile();
  function loginUrl();
  function logoutUrl();

}

class FacebookSharePressClient extends Facebook implements SharePressClient {

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
    $accessToken = $this->getPersistentData('access_token');

    return (object) array(
      'service' => 'facebook',
      'service_id' => $user['id'],
      'formatted_username' => $user['username'],
      'service_username' => $user['username'],
      'avatar' => 'https://graph.facebook.com/'.$user['id'].'/picture',
      'access_token' => $access_token
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

