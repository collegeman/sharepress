<div>
  <p>Should this Post be posted on Facebook?</p>
  <div style="padding:5px 0 12px 10px;">
    <label style="float: left; margin-right: 1em;">
      <input type="radio" name="sharepress_meta[enabled]" id="sharepress_meta_enabled_on" value="on" <?php if ($enabled) echo 'checked="checked"' ?> /> <strong>Yes</strong>
    </label>
    <label>
      <input type="radio" name="sharepress_meta[enabled]" id="sharepress_meta_enabled_off" value="off" <?php if (!$enabled) echo 'checked="checked"' ?> /> No
    </label>
    <div style="clear:left;"></div>
  </div>
</div>

<div id="sharepress_meta_controls" <?php if (!$enabled) echo 'style="display:none;"' ?>>
  <?php echo $meta_box ?>
</div>

<script>
(function($) {
  var enabled_val;
  setInterval(function() {
    var enabled = $('input[name="sharepress_meta\[enabled\]"]:checked');
    if (enabled.val() != enabled_val) {
      enabled_val = enabled.val();
      if (enabled_val == 'on') {
        $('#sharepress_meta_controls').show();
      } else {
        $('#sharepress_meta_controls').hide();
      }
    }
  }, 250);
})(jQuery);
</script>

