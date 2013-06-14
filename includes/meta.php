<?php
add_action('add_meta_boxes', 'sp_add_meta_boxes');
add_action('admin_enqueue_scripts', 'sp_meta_admin_enqueue_scripts');

function sp_add_meta_boxes() {
  add_meta_box('sp_metabox', 'SharePress', 'sp_metabox', 'post', 'side', 'high');
}

function sp_meta_admin_enqueue_scripts($hook) {
  if ($hook === 'post.php' || $hook === 'post-new.php') {
    wp_enqueue_style('sp_metabox_style', SP_URL.'/css/metabox.css');
    wp_enqueue_script('sp_metabox_script', SP_URL.'/js/metabox.js', array('backbone'));
  }
}

function sp_metabox() {
  require(SP_DIR.'/views/metabox.php');
}