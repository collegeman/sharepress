<?php
add_action('add_meta_boxes', 'sp_add_meta_boxes');
add_action('admin_enqueue_scripts', 'sp_meta_admin_enqueue_scripts');
add_action('admin_notices', 'sp_admin_notices', 10, 10);

function sp_add_meta_boxes() {
  add_meta_box('sp_metabox', 'SharePress', 'sp_metabox', 'post', 'side', 'high');
}

function sp_meta_admin_enqueue_scripts($hook) {
  wp_enqueue_script('sp_sharepress_script', SP_URL.'/js/sharepress.js', array('backbone'));
  if ($hook === 'post.php' || $hook === 'post-new.php') {
    wp_enqueue_style('sp_metabox_style', SP_URL.'/css/metabox.css');
    wp_enqueue_script('sp_metabox_script', SP_URL.'/js/metabox.js', array('sp_sharepress_script'));
  }
}

function sp_metabox() {
  add_thickbox();
  require(SP_DIR.'/views/metabox.php');
}

function sp_admin_notices() {
  $user = wp_get_current_user();
  $dimissed = get_user_meta($user->ID, 'sp_dismiss_try_now_prompt', true);
  if ($dismissed || preg_match('/(options-general.php|post.php|post-new.php)$/i', $_SERVER['PHP_SELF'])) {
    return;
  }
    
  ?>
    <div id="message" class="updated fade">
      <p><strong>SharePress is ready!</strong> 
        <a href="<?php echo admin_url('post-new.php') ?>" style="text-decoration:underline;">Try it now</a>, 
        or <a id="spdismisstrynowprompt" href="#" style="text-decoration:underline;">dismiss this message</a>.</p>
    </div>
    <script>
      !function($) {
        $('#spdismisstrynowprompt').click(function() {
          $.post('<?php echo get_site_url(null, '/sp/1/dismiss'); ?>');
          $('#message').hide();
          return false;
        });
      }(jQuery);
    </script>
  <?php
}