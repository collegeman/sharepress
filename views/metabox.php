<div class="updates" data-ui="updates">
  <div class="misc-pub-section" data-ui="none" style="display:none;">
    This post has no pending updates.
  </div>
</div>
<div class="buttons">
  <ul data-ui="profiles" class="profiles"></ul>
  <div class="combo-button">
    <a href="#" class="button button-sp-connect" data-target="dropdown" onclick="sp.dismissPointer('sp_connect_btn');">Connect...</a>
    <ul class="dropdown">
      <li class="item"><a href="<?php echo site_url('/sp/1/auth/facebook/profiles') ?>" data-open-in="modal">Facebook</a></li>
      <li class="item"><a href="<?php echo site_url('/sp/1/auth/twitter/profiles') ?>" data-open-in="modal">Twitter</a></li>
      <li class="item"><a href="<?php 
        if ($has_googleplus = sp_has_client('googleplus')) {
          echo site_url('/sp/1/auth/googleplus/profiles');
        } else {
          echo admin_url('admin.php?page=sp-addons');
        }
      ?>" <?php
        if ($has_googleplus) {
          echo 'data-open-in="modal"';
        } else {
          echo 'target="_blank"';
        }
      ?>>Google+</a></li>
      <li class="item"><a href="<?php 
        if ($has_linkedin = sp_has_client('linkedin')) {
          echo site_url('/sp/1/auth/linkedin/profiles');
        } else {
          echo admin_url('admin.php?page=sp-addons');
        } 
      ?>" <?php
        if ($has_linkedin) {
          echo 'data-open-in="modal"';
        } else {
          echo 'target="_blank"';
        }
      ?>>LinkedIn</a></li>
    </ul>
  </div>
  <a href="<?php echo admin_url('admin.php?page=sp-updates&post_id=' . $post->ID); ?>" class="button pull-right">History</a>
</div>
<div class="calendar" id="sp_calendar" data-ui="calendar">
  <div class="controls">
    Post  
    <select data-value="when">
      <?php if ($post->post_status === 'publish') { ?>
        <option value="immediately">immediately</option>
      <?php } else { ?>
        <option value="publish">on publish</option>
      <?php } ?>
      <option value="future">on a future date:</option>
    </select>
    <div data-ui="date" style="display:none;">
      <select data-value="month">
        <?php for($i = 0; $i < 12; $i++) { ?>
          <option value="<?php echo $i ?>"><?php echo date('F', mktime(0, 0, 0, $i + 1, 10)) ?></option>
        <?php } ?>
      </select>
      <input type="text" data-value="date" value="" maxlength="2" style="width:30px;">,
      <select data-value="year">
        <?php for($i = 0; $i < 10; $i++) { $year = date('Y') + $i; ?>
          <option value="<?php echo $year ?>"><?php echo $year ?></option>
        <?php } ?>
      </select>
      <input type="text" data-value="time" value="" maxlength="8" style="width:50px;">,
    </div>
    <?php sp_require_view('calendar-repeat-options') ?>
  </div>
  <div class="submitbox">
    <div id="wp-link-update">
      <button type="submit" class="button-primary" data-action="save">Change</button>
    </div>
    <div id="wp-link-cancel">
      <a class="submitdelete deletion" data-action="cancel" href="#">Cancel</a>
    </div>
  </div>
</div>
<div data-template="update" style="display:none;">
  <div class="misc-pub-section media update">
    <div class="img">
      <a href="#" title="/* service: name */"><img data-ui="avatar" class="sp-profile thumb /* service */" src=""></a>
    </div>
    <div class="bd">
     <span data-value="text"></span><br>
     <b><span data-value="schedule"></span></b>&nbsp;&nbsp;<a href="#" data-action="edit">Edit</a>
    </div>
  </div>
</div>
<div data-template="editor" style="display:none;">
  <div data-ui="update" class="misc-pub-section media editor">
    <span class="count">0</span>
    <div class="img">
      <a href="#" title="/* service: name */"><img data-ui="avatar" class="sp-profile thumb /* service */" src=""></a>
    </div>
    <div class="bd">
      <textarea data-value="text" readonly></textarea>
      <p class="promo howto" style="display:none;">You should be writing custom messages for each social media network. <a href="<?php echo admin_url('admin.php?page=sp-addons') ?>" target="_blank">Learn&nbsp;more&nbsp;&rarr;</a></p>
      <div class="date">
        <b data-value="schedule"></b>
        &nbsp;<a href="#" data-action="change-schedule">Change</a>
      </div>
      <button class="button" data-action="save">Save</button>
      &nbsp;<a class="delete" data-action="delete" href="#">Delete</a>
    </div>
  </div>
</div>
<script>
  jQuery(function($) {
    new sp.views.Metabox({ el: $('#sp_metabox'), post: <?php echo json_encode($post) ?> });
  });
</script>