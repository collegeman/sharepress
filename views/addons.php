<div class="wrap" style="max-width:970px;">
  <?php screen_icon(); ?>
  <h2 style="margin: 4px 0 15px;">SharePress Add-Ons</h2>

  <!-- <div class="acf-alert">
    <p style="">The following Add-ons are available to increase the functionality of the SharePress plugin.<br>Each Add-on can be installed as a separate plugin (receives updates).</p>
  </div>
   -->

  <?php if ($_SERVER['SERVER_NAME'] === 'demo.getsharepress.com') { ?>
    <div id="message" class="updated fade" style="background-color: #d4efbf;">
      <p><b>SharePress has add-ons now!</b> See anything you love? Let us know by clicking the &nbsp;<i class="fa fa-thumbs-up"></i>&nbsp;.</p>
    </div>
  <?php } else { ?>
    <div id="message" class="updated fade">
      <p>Add-ons expand the functionality of SharePress. Each add-on is installed as a separate plugin and receives updates.</p>
    </div>
  <?php } ?>

  <div id="add-ons" class="clearfix">
    <div class="add-on-group clearfix">
      <?php foreach( $premium as $add_on ) { ?>
        <div class="add-on wp-box add-on-active" >
          <a target="_blank" href="<?php echo $add_on['url'] ?>" style="display:block; padding:15px; text-align:center; font-size:10em; background-color:<?php echo $add_on['bg'] ?>; color:#fff"><i class="fa <?php echo $add_on['icon'] ?>"></i></a>
          <div class="inner">
            <h3><a target="_blank" href="<?php echo $add_on['url'] ?>"><?php echo $add_on['title'] ?></a></h3>
            <p><?php echo $add_on['description'] ?></p>
          </div>
          <div class="footer">
            <div style="padding: 10px;">
              <?php if ( $add_on['active'] ) { ?>
                <a class="button button-disabled"><span class="acf-sprite-tick"></span>Installed</a>
              <?php } else if ($_SERVER['SERVER_NAME'] === 'demo.getsharepress.com') { ?>
                <a data-plugin-name="<?php echo $add_on['name'] ?>" target="_blank" href="<?php echo $add_on['url'] ?>" class="button button-primary">&nbsp;<i class="fa fa-thumbs-up"></i>&nbsp;&nbsp;<span>I want this</span></a>
                <!-- Purchase &amp; Install -->
              <?php } else { ?>
                <a data-plugin-name="<?php echo $add_on['name'] ?>" target="_blank" href="<?php echo $add_on['url'] ?>" class="button">&nbsp;Purchase&nbsp;&amp;&nbsp;Install</a>
              <?php } ?>
            </div>
          </div>
        </div>
      <?php } ?>
    </div>
    
    <div class="add-on-group clearfix">
      <?php foreach( $free as $add_on ) { ?>
        <div class="add-on wp-box add-on-active" >
          <a target="_blank" href="<?php echo $add_on['url'] ?>" style="display:block; padding:15px; text-align:center; font-size:10em; background-color:<?php echo $add_on['bg'] ?>; color:#fff"><i class="fa <?php echo $add_on['icon'] ?>"></i></a>
          <div class="inner">
            <h3><a target="_blank" href="<?php echo $add_on['url'] ?>"><?php echo $add_on['title'] ?></a></h3>
            <p><?php echo $add_on['description'] ?></p>
          </div>
          <div class="footer">
            <div style="padding: 10px;">
              <?php if ( $add_on['active'] ) { ?>
                <a class="button button-disabled"><span class="acf-sprite-tick"></span>Installed</a>
              <?php } else if ($_SERVER['SERVER_NAME'] === 'demo.getsharepress.com') { ?>
                <a data-plugin-name="<?php echo $add_on['name'] ?>" target="_blank" href="<?php echo $add_on['url'] ?>" class="button button-primary">&nbsp;<i class="fa fa-thumbs-up"></i>&nbsp;&nbsp;<span>I want this</span></a>
              <?php } else { ?>
                <a data-plugin-name="<?php echo $add_on['name'] ?>" target="_blank" href="<?php echo $add_on['url'] ?>" class="button">&nbsp;Download&nbsp;&amp;&nbsp;Install</a>
              <?php } ?>
            </div>
          </div>
        </div>
      <?php } ?>
    </div>
      
  </div>
</div>

<script type="text/javascript">
(function($) {
  
  $(function() {

    $('#add-ons a').click(function() {
      var $btn = $(this);
      if (!$btn.hasClass('button-disabled')) {
        _gaq.push(['_trackEvent', 'Want Add-On', $btn.data('plugin-name')]);

        $btn.removeClass('button-primary').addClass('button-disabled');
        $btn.find('i').removeClass('fa-thumbs-up').addClass('fa-check');
        $btn.find('span').text('Awesome!');
      }
      return false;
    });

    $('#add-ons .add-on-group').each(function(){
      var $el = $(this),
        h = 0;
      $el.find('.add-on').each(function(){
        h = Math.max( $(this).height(), h );
      });
      $el.find('.add-on').height( h );
    });
    
  });
  
})(jQuery); 
</script>
