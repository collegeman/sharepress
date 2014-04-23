<?php
add_action('add_meta_boxes', 'sp_add_meta_boxes');
add_action('admin_enqueue_scripts', 'sp_meta_admin_enqueue_scripts');
add_action('save_post', 'sp_save_social_metadata');
add_action('admin_head', 'sp_add_help_widget');

function sp_add_help_widget() {
  if (sp_get_opt('help_widget_enabled', true)) {
    if (preg_match('/(post.php|post-new.php)$/i', $_SERVER['PHP_SELF'])) {
      sp_require_view('help-widget');
    }
  }
}

function sp_save_social_metadata($post_id) {

  if ( ! isset( $_POST['sp_metabox_og_nonce'] ) )
    return $post_id;

  $nonce = $_POST['sp_metabox_og_nonce'];

  // Verify that the nonce is valid.
  if ( ! wp_verify_nonce( $nonce, 'sp_metabox_og' ) )
      return $post_id;

  // If this is an autosave, our form has not been submitted, so we don't want to do anything.
  if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
      return $post_id;

  // Check the user's permissions.
  if ( 'page' == $_POST['post_type'] ) {

    if ( ! current_user_can( 'edit_page', $post_id ) )
        return $post_id;
  
  } else {

    if ( ! current_user_can( 'edit_post', $post_id ) )
        return $post_id;
  }

  update_post_meta($post_id, 'social:title', sanitize_text_field($_POST['social:title']));
  update_post_meta($post_id, 'social:image', sanitize_text_field($_POST['social:image']));
  update_post_meta($post_id, 'social:description', sanitize_text_field($_POST['social:description']));
}

function sp_add_meta_boxes() {
  //foreach(sp_supported_post_types() as $type) {
    add_meta_box('sp_metabox', 'SharePress', 'sp_metabox', 'post', 'side', 'high');
    add_meta_box('sp_metabox_og', 'Simple Social Metadata', 'sp_metabox_og', 'post', 'side', 'high');
  //}
}


function sp_meta_admin_enqueue_scripts($hook) {
  if ($hook === 'post.php' || $hook === 'post-new.php') {
    wp_enqueue_media();
    wp_enqueue_style('sp_metabox_style', SP_URL.'/css/metabox.css');
    wp_enqueue_script('sp_metabox_script', SP_URL.'/js/metabox.js', array('sp_sharepress_script'));
  }
}

function sp_metabox($post) {
  add_thickbox();
  sp_require_view('metabox', array('post' => $post));
}

function sp_metabox_og($post) {
  add_thickbox();
  sp_require_view('social-metabox', array(
    'socialmeta' => array(
      'title' => get_post_meta($post->ID, 'social:title', true),
      'image' => get_post_meta($post->ID, 'social:image', true),
      'description' => get_post_meta($post->ID, 'social:description', true)
    )
  ));
}