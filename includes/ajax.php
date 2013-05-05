<?php
add_action('wp_ajax_sp', 'sp_ajax');

function sp_ajax() {
  if (!empty($_REQUEST['_view']) && preg_match('/^[a-z\-]+$/', $_REQUEST['_view'])) {
    include(SP_DIR."/views/{$_REQUEST['_view']}.php");
  }
  exit;
}