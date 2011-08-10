<?php if (!defined('ABSPATH')) exit; /* silence is golden... */ ?>

<div id="sharepress" <?php if (($posted || $scheduled || $last_posted) && @$_GET['sharepress'] != 'schedule') echo 'style="display:none;"' ?>>
  
  <br />
  <fieldset>
    <legend>
      <label for="sharepress_meta_message">
        <b>Message</b> &nbsp;&nbsp; 
        <label style="display:inline-block;">
          <input type="checkbox" id="sharepress_meta_title_is_message" name="sharepress_meta[title_is_message]" value="1" onclick="click_use_post_title(this);" <?php if (@$meta['title_is_message']) echo 'checked="checked"' ?> /> 
          same as Post Title
        </label>
      </label>
    </legend>
    <textarea style="width:100%; height:75px;" name="sharepress_meta[message]" id="sharepress_meta_message"><?php echo @$meta['message'] ?></textarea>
  </fieldset>

  <?php if ( ($posted || $scheduled) || (!$posted && !$scheduled && $post->post_status == 'publish') ) { ?>
    <br />
    <fieldset>
      <legend>
        <label for="sharepress_meta_schedule">
          <b>Schedule</b>
        </label>
      </legend>
      <?php Sharepress::$pro->touch_time($scheduled); ?>
      <div style="padding:10px 0 2px 0;">
        <input type="submit" class="button-primary" value="Schedule" style="margin-right:4px;" />
        <input type="submit" class="button" onclick="if(confirm('Are you sure you want to cancel posting to Facebook?')) { sharepress_cancel_publish_again(); } else { return false; }" value="Cancel" />
      </div>
    </fieldset>
  <?php } ?>

  <p class="sharepress_show_advanced">
    <a href="javascript:;" onclick="jQuery(this).parent().hide(); jQuery('.sharepress_advanced').slideDown();">Show Advanced Options</a>
  </p>

  <div class="sharepress_advanced" style="display:none;">
  
    <br />
    <fieldset>
      <legend>
        <label for="sharepress_meta_picture">
          <b>Picture</b>
        </label>
      </legend>
  
      <label style="display:block; margin-bottom: 5px;">
        <input type="radio" name="sharepress_meta[let_facebook_pick_pic]" value="0" <?php if (!@$meta['let_facebook_pick_pic']) echo 'checked="checked"' ?> /> 
        Use this post's <a href="javascript:;" onclick="jQuery('#set-post-thumbnail').click();">Featured Image</a>
      </label>

      <label style="display:block; margin-bottom: 5px;">
        <input type="radio" name="sharepress_meta[let_facebook_pick_pic]" value="1" <?php if (@$meta['let_facebook_pick_pic']) echo 'checked="checked"' ?> /> 
        Let Facebook choose
      </label>
    </fieldset>
  
    <br />
    <fieldset>
      <legend>
        <label for="sharepress_meta_targets">
          <b>Publishing Targets</b> &nbsp;&nbsp; 
        </label>
      </legend>
    
      <div style="max-height:150px; overflow:auto;">
        <p style="color:red; display:none; padding-top: 0; margin-top: 0;" id="publish_target_error">
          Choose at least one.
        </p>
        <p>
          <?php $wall_name = ((preg_match('/s$/i', trim($name = Sharepress::me('name')))) ? $name.'&apos;' : $name.'&apos;s') . ' Wall'; ?>
          <label for="sharepress_target_wall" title="<?php echo $wall_name ?>"> 
            <input type="checkbox" class="sharepress_target" id="sharepress_target_wall" name="sharepress_meta[targets][]" value="wall" <?php if (@in_array('wall', $meta['targets'])) echo 'checked="checked"' ?> />
            <?php echo $wall_name ?>
          </label>
        </p>
        <?php 
          $pages = self::pages(); usort($pages, array('Sharepress', 'sort_by_selected')); 
          foreach($pages as $page) { 
            if (self::is_excluded_page($page)) {
              continue; 
            }
            ?>
               <p>
                <label for="sharepress_target_<?php echo $page['id'] ?>" title="<?php echo $page['name'] ?>">
                  <input class="sharepress_target" type="checkbox" id="sharepress_target_<?php echo $page['id'] ?>" name="sharepress_meta[targets][]" value="<?php echo $page['id'] ?>" <?php if (@in_array($page['id'], $meta['targets'])) echo 'checked="checked"' ?> />
                  <?php $name = trim(substr($page['name'], 0, 30)); $name .= ($name != $page['name']) ? '...' : ''; echo $name ?>
                </label>
              </p>
            <?php
          }
        ?>
      </div>
    </fieldset>
    
    <p>
      <a class="sharepress_hide_advanced" href="javascript:;" onclick="jQuery('.sharepress_advanced').slideUp(function() { jQuery('.sharepress_show_advanced').fadeIn(); });">Hide Advanced Options</a>
    </p>
  </div>
  
</div><!-- /#sharepress -->

<script>
(function($) {
  if (!$.fn.prop) {
    $.fn.prop = $.fn.attr;
  }
  
  $(function() {
    var editor = null;
    var description_timeout = null;
    var message_timeout = null;
    var excerpt_was = null;
    var content_was = null;

    var msg = $('#sharepress_meta_message');
    var title = $('#title');

    var excerpt = $('#excerpt');
    var description = $('#sharepress_meta_description');
    var description_was = description.val();
    var suspend = false;
    
    msg.keypress(function() {
      $('#sharepress_meta_title_is_message').prop('checked', false);
    })
    
    window.sharepress_publish_again = function() {
      $('#sharepress').show();
      $('#btn_publish_again').hide();
      $('#sharepress_meta_cancelled').val(0);
      $('#sharepress_meta_publish_again').val(1);
    };
    
    window.sharepress_cancel_publish_again = function() {
      $('#sharepress_meta_cancelled').val(1);
      $('#sharepress_meta_publish_again').val(0);      
      $('#sharepress_meta_enabled_on').prop('checked', false);
      $('#sharepress_meta_enabled_off').attr('checked', true);
    };
    
    window.click_use_post_title = function(cb) {
      if (cb.checked) {
        copy_title_to_message(true);
      } else {
        $('#sharepress_meta_message').focus();
      }
    };
    
    window.click_use_excerpt = function(cb) {
      if (cb.checked) {
        excerpt_was = null;
        content_was = null;
        copy_excerpt_to_description(true);
      } else {
        $('#sharepress_meta_description').focus();
      }
    };
    
    window.copy_title_to_message = function(synchronize) {
      if (!$('#sharepress_meta_title_is_message:checked').size()) {
        return false;
      }
      
      clearTimeout(message_timeout);
      setTimeout(function() {
        msg.val(title.val());
        msg_was = msg.val();
      }, synchronize ? 0 : 1000);
    };
    
    title.bind('keypress blur', function() {
      copy_title_to_message();
      return true;
    });

    var check_for_targets = true;

    $('#save-post').click(function() {
      check_for_targets = false;
      return true;
    });

    $('#publish').click(function() {
      check_for_targets = true;
      return true;
    });

    $('#post').submit(function() {
      if (!check_for_targets) {
        return true;
      }

      // are we trying to post with sharepress?
      if ($('#sharepress_meta_enabled_on:checked').size() || $('#sharepress_meta_publish_again').val() == '1') {
        // no targets?
        if (!$('input.sharepress_target:checked').size()) {

          // reveal the targets selection, and try to focus the screen on it:
          $('#ajax-loading').hide();
          $('#publish').removeClass('button-primary-disabled');
          $('.sharepress_show_advanced').hide(); 
          $('.sharepress_advanced').slideDown();
          $('label[for="sharepress_meta_targets"]').css('color', 'red');
          $('#publish_target_error').show();
          $(window).scrollTop($('#sharepress_meta').offset().top)

          // don't allow submission:
          return false;

        } else {
          $('.sharepress_show_advanced').show(); 
          $('.sharepress_advanced').hide();
        }
      }
    });
    
  });
})(jQuery);
</script>