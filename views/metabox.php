<div class="misc-pub-section" data-ui="never-published">
  This post has never been publicized.
</div>
<!--
<div class="misc-pub-section media update">
  <div class="img">
    <a href="#" title="Facebook Profile"><img class="thumb" src="http://placehold.it/50x50"></a>
  </div>
  <div class="bd">
   <b>Post to Facebook on publish:</b> Lorem ipsum dolor sit amet &nbsp;<a href="#">Edit</a>
  </div>
</div>
<div class="misc-pub-section media update">
  <div class="img">
    <a href="#" title="Twitter Profile"><img class="thumb" src="http://placehold.it/50x50"></a>
  </div>
  <div class="bd">
    <b>Post to Twitter next Friday:</b> Lorem ipsum dolor sit amet, {{title}} adipisicing elit, sed do eiusmod
    tempor incididunt ut labore et dolore magna aliqua. &nbsp;<a href="#">Edit</a>
  </div>
</div>
<div class="misc-pub-section media editor">
  <div class="img">
    <a href="#"><img class="thumb" src="http://placehold.it/50x50"></a>
  </div>
  <div class="bd">
    <textarea>This is my message {{title}} {{author}}</textarea>
    <div class="date">
      <b>Post on publish</b>
      &nbsp;<a href="#">Schedule</a>
    </div>
    <button class="button">Save</button>
    &nbsp;<a class="delete" href="#">Delete</a>
  </div>
</div>
-->
<div class="buttons">
  <ul class="profiles"></ul>
  <div class="combo-button">
    <a href="#" class="button" data-target="dropdown">Connect...</a>
    <ul class="dropdown">
      <li class="item"><a href="<?php echo site_url('/sp/1/auth/facebook/profiles') ?>" data-open-in="modal">Facebook</a></li>
      <li class="item"><a href="<?php echo site_url('/sp/1/auth/twitter/profiles') ?>" data-open-in="modal">Twitter</a></li>
      <li class="item"><a href="<?php echo site_url('/sp/1/auth/googleplus/profiles') ?>" data-open-in="modal">Google+</a></li>
      <li class="item"><a href="<?php echo site_url('/sp/1/auth/linkedin/profiles') ?>" data-open-in="modal">LinkedIn</a></li>
    </ul>
  </div>
  <button href="#" class="button pull-right" disabled>History</button>
</div>
<script>
  jQuery(function($) {
    new sp.Metabox({ el: $('#sp_metabox') });
  });
</script>