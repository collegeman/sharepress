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
    <label for="sharepress_meta_description" style="position:relative;">
      <b>Description</b> &nbsp;&nbsp; 
      <label style="display:inline-block;">
        <input type="checkbox" id="sharepress_meta_excerpt_is_description" name="sharepress_meta[excerpt_is_description]" value="1" onclick="click_use_excerpt(this);" <?php if (@$meta['excerpt_is_description']) echo 'checked="checked"' ?> /> 
        same as Excerpt
      </label> &nbsp;
      <span id="sharepress_description_wait" style="position:absolute; right: -15px; top: -2px; display:none;">
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
      <?php $pages = self::pages(); usort($pages, array('Sharepress', 'sort_by_selected')); foreach($pages as $page) { ?>
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
      $('#sharepress_meta_title_is_message').attr('checked', '');
    })
    
    description.keypress(function() {
      $('#sharepress_meta_excerpt_is_description').attr('checked', '');
    });
    
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
      clearTimeout(message_timeout);
      setTimeout(function() {
        msg.val(title.val());
        msg_was = msg.val();
      }, synchronize ? 0 : 1000);
    };
    
    title.keypress(copy_title_to_message);
    title.blur(copy_title_to_message);
    
    window.copy_excerpt_to_description = function(ed, e) {
      if (suspend) {
        return;
      }
      
      clearTimeout(description_timeout);
      description_timeout = setTimeout(function() {
        if (!$('#sharepress_meta_excerpt_is_description:checked').size()) {
          return false;
        }
        
        if (content_was != editor.getContent() || excerpt_was != excerpt.val()) {
          suspend = true;
          
          content_was = editor.getContent();
          excerpt_was = excerpt.val();
          
          // show the wait icon
          $('#sharepress_description_wait').show();

          $.post(ajaxurl, { action: 'sharepress_get_excerpt', post_id: $('#post_ID').val(), content: excerpt_was ? excerpt_was : content_was }, function(excerpt) {
            // hide the wait icon
            $('#sharepress_description_wait').hide();
            // check the checkbox again
            if (!$('#sharepress_meta_excerpt_is_description:checked').size()) {
              return;
            }
            // update the excerpt
            description.val(excerpt);
            
            suspend = false;
          });
        }
      }, ed === true ? 0 : 1000);
    };
    
    var setupEditor = setInterval(function() {
      editor = tinyMCE.get('content');
      if (editor) {
        clearInterval(setupEditor);
        
        content_was = editor.getContent();
        
        editor.onKeyPress.add(copy_excerpt_to_description);
        editor.onLoadContent.add(copy_excerpt_to_description);
        editor.onChange.add(copy_excerpt_to_description);
        excerpt.keypress(copy_excerpt_to_description);
        excerpt.blur(copy_excerpt_to_description);
      }
    }, 100);
    
  });
})(jQuery);
</script>