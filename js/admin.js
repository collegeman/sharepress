(function($) {
  window.SharePress = {
    
    autoscale: function(iframe) {
      $(iframe).height( $(iframe).contents().find("body").height() );
    }
      
  }
})(jQuery);