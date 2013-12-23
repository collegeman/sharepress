<div class="updates" data-ui="updates">
  <div class="misc-pub-section" data-ui="none" style="display:none;">
    This post has no scheduled publications.
  </div>
</div>
<div class="buttons">
  <ul data-ui="profiles" class="profiles"></ul>
  <div class="combo-button">
    <a href="#" class="button button-sp-connect" data-target="dropdown" onclick="sp.dismissPointer('sp_connect_btn');">Connect...</a>
    <ul class="dropdown">
      <li class="item"><a href="<?php echo site_url('/sp/1/auth/facebook/profiles') ?>" data-open-in="modal">Facebook</a></li>
      <li class="item"><a href="<?php echo site_url('/sp/1/auth/twitter/profiles') ?>" data-open-in="modal">Twitter</a></li>
      <li class="item"><a href="<?php echo site_url('/sp/1/auth/googleplus/profiles') ?>" data-open-in="modal">Google+</a></li>
      <li class="item"><a href="<?php echo site_url('/sp/1/auth/linkedin/profiles') ?>" data-open-in="modal">LinkedIn</a></li>
    </ul>
  </div>
  <button href="#" class="button pull-right" disabled>History</button>
</div>
<div class="calendar" id="sp_calendar" data-ui="calendar">
  <div class="controls">
    Post on 
    <select data-value="when">
      <option value="publish">publish</option>
      <option value="future">a future date:</option>
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
      <a href="#" title="/* service: name */"><img data-ui="avatar" class="thumb /* service */" src=""></a>
    </div>
    <div class="bd">
     <span data-value="text"></span><br>
     <b><span data-value="schedule"></span></b>&nbsp;&nbsp;<a href="#" data-action="edit">Edit</a>
    </div>
  </div>
</div>
<div data-template="editor" style="display:none;">
  <div data-ui="update" class="misc-pub-section media editor">
    <div class="img">
      <a href="#" title="/* service: name */"><img data-ui="avatar" class="thumb /* service */" src=""></a>
    </div>
    <div class="bd">
      <textarea data-value="text"></textarea>
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
    new sp.views.Metabox({ el: $('#sp_metabox') });
  });
</script>