<?php 
/*
sharepress
Copyright (C)2010-2011  Fat Panda LLC
You must own a valid licenses to use the Pro version of sharepress.
*/

if (!defined('ABSPATH')) exit; /* silence is golden... */ ?>

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

<br />
<fieldset>
  <legend>
    <label for="sharepress_meta_description">
      <b>Description</b> &nbsp;&nbsp; 
      <label style="display:inline-block;">
        <input type="checkbox" id="sharepress_meta_excerpt_is_description" name="sharepress_meta[excerpt_is_description]" value="1" onclick="click_use_excerpt(this);" <?php if (@$meta['excerpt_is_description']) echo 'checked="checked"' ?> /> 
        same as Excerpt
      </label> &nbsp;
      <span id="sharepress_description_wait" style="position:relative; top:4px; margin-right:5px;">
        <img src="<?php echo plugins_url('sharepress/img/wait.gif') ?>" />
      </span>
    </label>
  </legend>
  <textarea style="width:100%; height:75px;" name="sharepress_meta[description]" id="sharepress_meta_description"><?php echo htmlentities(@$meta['description']) ?></textarea>
</fieldset>

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
      Let Facebook choose a picture
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
      <p>
        <?php $wall_name = ((preg_match('/s$/i', trim($name = Sharepress::me('name')))) ? $name.'&apos;' : $name.'&apos;s') . ' Wall'; ?>
        <label for="sharepress_target_wall" title="<?php echo $wall_name ?>"> 
          <input type="checkbox" id="sharepress_target_wall" name="sharepress_meta[targets][]" value="wall" <?php if (@in_array('wall', $meta['targets'])) echo 'checked="checked"' ?> />
          <?php echo $wall_name ?>
        </label>
      </p>
      <?php foreach(Sharepress::pages() as $page) { ?>
        <p>
          <label for="sharepress_target_<?php echo $page['id'] ?>" title="<?php echo $page['name'] ?>">
            <input type="checkbox" id="sharepress_target_<?php echo $page['id'] ?>" name="sharepress_meta[targets][]" value="<?php echo $page['id'] ?>" <?php if (@in_array($page['id'], $meta['targets'])) echo 'checked="checked"' ?> />
            <?php $name = trim(substr($page['name'], 0, 30)); $name .= ($name != $page['name']) ? '...' : ''; echo $name ?>
          </label>
        </p>
      <?php } ?>
    </div>
  </fieldset>
  
  <p>
    <a class="sharepress_hide_advanced" href="javascript:;" onclick="jQuery('.sharepress_advanced').slideUp(function() { jQuery('.sharepress_show_advanced').fadeIn(); });">Hide Advanced Options</a>
  </p>
</div>

<script>
(function($) {
  $(function() {
    window.click_use_post_title = function(cb) {
      if (cb.checked) {
        copy_title_to_message(true);
      } else {
        clearInterval(copy_title_to_message_intv);
        $('#sharepress_meta_message').focus();
      }
    };
    
    window.click_use_excerpt = function(cb) {
      if (cb.checked) {
        copy_excerpt_to_description(true);
      } else {
        clearInterval(copy_excerpt_to_description_intv);
        $('#sharepress_meta_description').focus();
      }
    };
    
    window.copy_title_to_message = function(synchronize) {
      var msg = $('#sharepress_meta_message');
      var title = $('#title');
      if (synchronize || !$.trim(msg.val())) {
        msg.val(title.val());
      }
    
      var msg_was = msg.val();
      window.copy_title_to_message_intv = setInterval(function() {
        if (msg_was != msg.val()) {
          $('#sharepress_meta_title_is_message').attr('checked', '');
          clearInterval(copy_title_to_message_intv);
        } else {
          msg.val(title.val());
          msg_was = msg.val();
        }
      }, 100);
    };
    
    if ($('#sharepress_meta_title_is_message:checked').size()) {
      copy_title_to_message();
    }
    
    window.copy_excerpt_to_description = function(synchronize) {
      var description = $('#sharepress_meta_description');
      var excerpt = $('#excerpt');
      
      if (synchronize || !$.trim(description.val())) {
        description.val(excerpt.val());
      }
    
      var description_was = description.val();
      
      window.copy_excerpt_to_description_intv = setInterval(function() {
        if ($('#sharepress_meta_excerpt_is_description:checked').size()) {
          clearInterval(copy_excerpt_to_description_intv);
          $.post(ajax_url, { action: ''})
        }
          
        } else {
          if ($.trim(excerpt.val())) {
            description.val(excerpt.val());
            description_was = description.val();
          }
        }
      }, 10000);
    };
    
    if ($('#sharepress_meta_excerpt_is_description:checked').size()) {
      copy_excerpt_to_description();
    }
    
  });
})(jQuery);
</script>