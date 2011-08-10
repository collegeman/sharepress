<?php if (!defined('ABSPATH')) exit; ?>

<fieldset>
  <legend>
    <?php if ($posted) { ?>
      <b>Last Published on Facebook</b>
    <?php } else if ($scheduled) { ?>
      <b>Scheduled to Post to Facebook</b>
    <?php } else if ($last_posted) { ?>
      <b>Last Published on Facebook</b>
    <?php } ?>
  </legend>
  <label style="display:inline-block; width:100%;">
    <p style="margin-bottom:12px; color:#555; width:235px; overflow:hidden;">
      <?php echo htmlentities($scheduled ? $meta['message'] : $last_result['message']) ?>
    </p>
    <div style="width:100%;">
      <?php if (Sharepress::$pro) { ?>
        <a class="button" id="btn_publish_again" style="float:right; position:relative; top:-6px; margin-bottom:-6px; <?php if (@$_GET['sharepress'] == 'schedule') echo 'display:none;' ?>" href="#" onclick="sharepress_publish_again(); return false;">
          <?php if ($posted) { ?>
            Publish Again
          <?php } else if ($scheduled) { ?>
            Edit
          <?php } else if ($last_posted) { ?>
            Publish Again
          <?php } ?>
        </a>
      <?php } ?>
      <?php if ($posted) { ?>
        <span><?php echo date_i18n('M d, Y @ H:i', ( is_numeric($posted) ? $posted : strtotime($posted) ) + ( get_option( 'gmt_offset' ) * 3600 ), true) ?></span>
      <?php } else if ($scheduled) { ?>
        <span><?php echo date_i18n('M d, Y @ H:i', ( is_numeric($scheduled) ? $scheduled : strtotime($scheduled) ), true) ?></span>
      <?php } else if ($last_posted) { ?>  
        <span><?php echo date_i18n('M d, Y @ H:i', $last_posted + ( get_option( 'gmt_offset' ) * 3600 ), true) ?></span>
      <?php } ?>
    </div>
    <?php if (!Sharepress::$pro) { ?>
      <div style="padding:12px 0 2px;">
        <input type="checkbox" id="sharepress_meta_publish_again" name="sharepress_meta[publish_again]" value="1" /> 
        Publish Again
      </div>
    <?php } else { ?>
      <input type="hidden" id="sharepress_meta_publish_again" name="sharepress_meta[publish_again]" value="<?php echo @$_GET['sharepress'] == 'schedule' ? 1 : 0 ?>" />
      <input type="hidden" id="sharepress_meta_cancelled" name="sharepress_meta[cancelled]" value="0" />
    <?php } ?>
  </label>
</fieldset>
