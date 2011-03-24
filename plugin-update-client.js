/*
getwpapps.com Plugin Update Client
Copyright (C)2010-2011  Fat Panda, LLC

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

(function($) {
  var self = window.PluginUpdate = {
    
    editKey: function(name, plugin, server) {
      $.post(ajaxurl, { action: 'plugin_update_get_key', plugin: plugin }, function(key) {
        $('.plugin-update').show();
        $('#plugin-update-key').val(key);
        $('#plugin-update-key').select();
        $('.plugin-update-key-editor form input[type="hidden"]').val(plugin);
        $('.plugin-update-key-editor').css('top', $(window).scrollTop() + 100);
        $('.plugin-update-key-editor .name').text(name);
        $('.plugin-update-key-editor .server').attr('href', server);
      });
    },
    
    setKey: function(form) {
      var data = $(form).serialize();
      var button = $('.plugin-update-key-editor .buttons .button');
      
      data += '&action=plugin_update_set_key';
      $.post(ajaxurl, data, function(result) {
        button.addClass('disabled');
        if (result) { // error state
          alert(result);
          button.removeClass('disabled');
        } else {
          $('.plugin-update').hide();
          document.location.reload();
        }
      });
    },
    
    cancel: function() {
      $('.plugin-update').hide();
    }
  }

})(jQuery);