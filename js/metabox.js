/*
sharepress/js/metabox.js
Copyright (C)2011-2012 Fat Panda, LLC

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

window.sp = window.sp || {};

!function($, B) {
  
  'use strict';  

  $(document).on('click', function() {
    $('.combo-button .dropdown:visible').each(function() {
      var $menu = $(this), $combo = $menu.closest('.combo-button');
      $menu.hide();
      $combo.find('[data-target="dropdown"]').removeClass('active');
    });
  });

  sp.Metabox = Backbone.View.extend({
    events: {
      'click [data-target="dropdown"]': function(e) {
        var $btn = $(e.target), $menu = $btn.parent('.combo-button').find('.dropdown');
        if (!$menu.is(':visible')) {
          $btn.addClass('active');
          $menu.show();        
        } else {
          $btn.removeClass('active');
          $menu.hide();
        }
        return false;
      },
      'click .combo-button .dropdown .item a': function(e) {
        var $a = $(e.target), $combo = $a.closest('.combo-button');
        $combo.find('[data-target="dropdown"]').removeClass('active');
        $combo.find('.dropdown').hide();
      },
      'click [data-open-in="modal"]': function(e) {
        var that = this,
            $a = $(e.target),
            left = ( $(window).width() - 850 ) / 2,
            top = ( $(window).height() - 500 ) / 2,
            windowName = Math.floor( Math.random()*10000000 ) + 'sp-modal',
            popup = window.open($a.attr('href'), windowName, 'width=850,height=650,status=1,toolbar=0,left='+left+',top='+top);

        // start an interval that checks up on the logged in status
        var check = setInterval(function() {
          if (popup.closed) {
            clearInterval(check);
            that.ui.profiles.html('<li>Loading...</li>');
            that.profiles.fetch({
              success: function(profiles) {
                $(window).trigger('sp-profiles-loaded', [ profiles ]);
              }
            });
          }
        }, 100);

        return false;
      }
    },
    initialize: function() {
      this.profiles = new sp.Profiles();
      this.profiles.on('add', $.proxy(this.add, this));
      this.profiles.on('remove', $.proxy(this.remove, this));
      this.profiles.on('reset', $.proxy(this.reset, this));
      this.profiles.fetch({
        success: function(profiles) {
          $(window).trigger('sp-profiles-loaded', [ profiles ]);
        }
      });

      this.ui = {};
      this.ui.profiles = this.$('ul.profiles');
    },
    add: function(profile) {
      var $ui = $('<li class="profile"><a href="#" title="' + profile.get('service') + ': ' + profile.get('formatted_username') + '"><img class="thumb" src="' + profile.get('avatar') + '"></a></li>');
      this.ui.profiles.append($ui);
      profile.$ui = $ui;
    },
    remove: function(profile) {
      profile.$ui && profile.$ui.remove();
    },
    reset: function(profiles, result) {
      this.ui.profiles.html('');
      var that = this;
      profiles.each(function(profile) {
        that.add(profile);
      });
    }
  });

}(jQuery, Backbone);