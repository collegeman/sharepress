<?php
add_action('admin_notices', 'sp_admin_notices', 10, 10);

function sp_admin_notices() {
  $user = wp_get_current_user();
  $all_dismissed = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );
  $is_dismissed = in_array('try_sharepress_nag', $all_dismissed);
  if ($is_dismissed || preg_match('/(admin.php|post.php|post-new.php)$/i', $_SERVER['PHP_SELF'])) {
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

