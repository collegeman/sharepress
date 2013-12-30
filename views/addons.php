<div class="wrap" style="max-width:970px;">

  <div class="icon32" id="icon-acf"><br></div>
  <h2 style="margin: 4px 0 15px;">SharePress Add-Ons</h2>

  <div class="acf-alert">
    <p style="">The following Add-ons are available to increase the functionality of the SharePress plugin.<br>Each Add-on can be installed as a separate plugin (receives updates).</p>
  </div>
  
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
              <?php } else { ?>
                <a target="_blank" href="<?php echo $add_on['url'] ?>" class="button"><span class="acf-sprite-tick"></span>Purchase &amp; Install</a>
              <?php } ?>
            </div>
          </div>
        </div>
      <?php } ?>
    </div>
    
    <div class="add-on-group clearfix">
        
    </div>
      
  </div>
  
</div>

<script type="text/javascript">
(function($) {
  
  $(window).load(function(){
    
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