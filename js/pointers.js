window.sp = window.sp || {};

!function($) {
  var t = null;
  $(window).on('sp.profiles.sync', function(e, profiles) {
    clearTimeout(t);
    t = setTimeout(function() {
      if (profiles.size() === 0) {
        sp.showPointer('sp_connect_btn');
      } else {
        sp.showPointer('sp_profiles');
      }
    }, 500);
  });
}(jQuery);