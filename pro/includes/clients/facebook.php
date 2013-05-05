<?php
class FacebookSharePressClientPro extends FacebookSharePressClient {

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
        $result = $e->getResult();
        return new WP_Error($result['error']['code'], $e->getMessage());
      }
    }
  }

}