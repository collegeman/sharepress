<?php
add_action('add_meta_boxes', 'sp_add_meta_boxes');
add_action('admin_enqueue_scripts', 'sp_meta_admin_enqueue_scripts');
add_action('admin_notices', 'sp_admin_notices', 10, 10);

function sp_add_meta_boxes() {
  add_meta_box('sp_metabox', 'SharePress', 'sp_metabox', 'post', 'side', 'high');
  add_meta_box('sp_metabox_og', 'Social Metadata', 'sp_metabox_og', 'post', 'side', 'high');
}

function sp_meta_admin_enqueue_scripts($hook) {
  if ($hook === 'post.php' || $hook === 'post-new.php') {
    wp_enqueue_media();
    wp_enqueue_style('sp_metabox_style', SP_URL.'/css/metabox.css');
    wp_enqueue_script('sp_metabox_script', SP_URL.'/js/metabox.js', array('sp_sharepress_script'));
  }
}

function sp_metabox() {
  add_thickbox();
  require(SP_DIR.'/views/metabox.php');
}

function sp_metabox_og() {
  add_thickbox();
  require(SP_DIR.'/views/social_metabox.php');
}

function sp_admin_notices() {
  $user = wp_get_current_user();
  $all_dismissed = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );
  $is_dismissed = in_array('try_sharepress_nag', $all_dismissed);
  if ($is_dismissed || preg_match('/(options-general.php|post.php|post-new.php)$/i', $_SERVER['PHP_SELF'])) {
    return;
  }
    
  ?>
    <div id="message" class="updated fade">
      <p><strong>SharePress is ready!</strong> 
        <a href="<?php echo admin_url('post-new.php') ?>" style="text-decoration:underline;">Try it now</a>, 
        or <a id="spdismisstrynowprompt" href="#" onclick="jQuery.post(ajaxurl, { action: 'dismiss-wp-pointer', pointer: 'try_sharepress_nag' }); jQuery('#message').hide(); return false;" style="text-decoration:underline;">dismiss this message</a>.</p>
    </div>
    <script>
      !function($) {
        $('#spdismisstrynowprompt').click(function() {
          $.post('<?php echo site_url('/sp/1/dismiss'); ?>');
          $('#message').hide();
          return false;
        });
      }(jQuery);
    </script>
  <?php
}