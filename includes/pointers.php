<?php
add_action('admin_enqueue_scripts', 'sp_admin_pointers_load');
add_filter('sp_admin_pointers_post', 'sp_admin_pointers_post');
add_filter('sp_admin_pointers_page', 'sp_admin_pointers_post');

function sp_admin_pointers_post($p) {
  
  $p['sp_connect_btn'] = array(
    'target' => '#sp_metabox .button.button-sp-connect',
    'options' => array(
      'content' => '<h3>Welcome to SharePress</h3> <p>You haven\'t connected any social media profiles yet. Click <b>Connect...</b> to get started.</p>',
      'position' => array(
        'edge' => 'right', 
        'align' => 'center'
      )
    )
  );

  $p['sp_profiles'] = array(
    'target' => '#sp_metabox .profiles',
    'options' => array(
      'content' => '<h3>Your Profiles</h3> <p>These are the social media profiles you\'ve connected. Just click one to create a new update to publicize this Post, or click <b>Connect...</b> below to add more profiles.</p>',
      'position' => array(
        'edge' => 'right', 
        'align' => 'center'
      )
    )
  );

  return $p;
}

function sp_admin_pointers_load()  {
  // Don't run on WP < 3.3
  if ( get_bloginfo( 'version' ) < '3.3' ) {
    return false;
  }

  $screen = get_current_screen();
  $screen_id = $screen->id;

  // Get pointers for this screen
  $pointers = apply_filters( 'sp_admin_pointers_' . $screen_id, array() );

  if ( ! $pointers || ! is_array( $pointers ) ) {
    return;
  }

  // Get dismissed pointers
  $dismissed = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );
  $valid_pointers = array();

  // Check pointers and remove dismissed ones.
  foreach ( $pointers as $pointer_id => $pointer ) {

    // Sanity check
    if ( in_array( $pointer_id, $dismissed ) || empty( $pointer )  || empty( $pointer_id ) || empty( $pointer['target'] ) || empty( $pointer['options'] ) )
      continue;

    $pointer['pointer_id'] = $pointer_id;

    // Add the pointer to $valid_pointers array
    $valid_pointers['pointers'][] = $pointer;
  }

  // No valid pointers? Stop here.
  if ( empty( $valid_pointers ) ) {
    return;
  }

  // Add pointers style to queue.
  wp_enqueue_style( 'wp-pointer' );

  // Add pointers script to queue. Add custom script.
  wp_enqueue_script( 'sp-pointers', SP_URL.'/js/pointers.js', array( 'wp-pointer' ) );

  // Add pointer options to script.
  wp_localize_script( 'sp-pointers', 'spTour', $valid_pointers );
}