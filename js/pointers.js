window.sp = window.sp || {};

!function($) {
  $(window).on('sp-profiles-loaded', function(e, profiles) {
    if (profiles.size() === 0) {
      sp.showPointer('sp_connect_btn');
    } else {
      sp.showPointer('sp_profiles');
    }
  });
}(jQuery);