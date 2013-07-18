/*
sharepress/js/sharepress.js
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

  sp.getPointerById = function(id) {
    if (!window.spTour || !window.spTour.pointers || spTour.pointers.length === 0) {
      return false;
    }
    for (var i = 0; i < spTour.pointers.length; i++) {
      if (spTour.pointers[i].pointer_id === id) {
        return spTour.pointers[i];
      }
    }
    return false;
  };

  sp.showPointer = function(id, onDismiss) {
    var pointer = sp.getPointerById(id);
    if (pointer) {
      $(pointer.target).pointer($.extend({}, pointer.options, {
        close: function() {
          if ($.isFunction(onDismiss)) {
            onDismiss();
          }
          $.post(ajaxurl, {
            pointer: pointer.pointer_id,
            action: 'dismiss-wp-pointer'
          });
        }
      })).pointer('open');
      return true;
    } else {
      return false;
    }
  };

  sp.dismissPointer = function(id) {
    var pointer = sp.getPointerById('sp_connect_btn');
    if (pointer) {
      $(pointer.target).pointer(pointer.options).pointer('close');
      return true;
    } else {
      return false;
    }  
  };

  sp.Update = Backbone.Model.extend({

  });

  sp.Profile = Backbone.Model.extend({

  });

  sp.Profiles = Backbone.Collection.extend({
    url: function() {
      return sp.api + '/profiles';
    },
    model: sp.Profile
  });

}(jQuery, Backbone);