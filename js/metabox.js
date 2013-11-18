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
sp.views = sp.views || {};

!function($, B) {
  
  'use strict';

  var sp_media_frame;  

  $(document).on('click', function() {
    $('.combo-button .dropdown:visible').each(function() {
      var $menu = $(this), $combo = $menu.closest('.combo-button');
      $menu.hide();
      $combo.find('[data-target="dropdown"]').removeClass('active');
    });
  });

  sp.views.Calendar = Backbone.View.extend({
    events: {
      'click [data-action="save"]': function(e) {
        this.save();
        return false;
      },
      'click [data-action="cancel"]': function(e) {
        this._hide();
        return false;
      },
      'change [data-value="when"]': function(e) {
        this.render();
      },
      'change [data-value="repeat"]': function(e) {
        this.render();
      },
      'change [data-value="until"]': function(e) {
        this.render();
      },
      'keydown input[type="text"], select': function(e) {
        var key = e.which || e.keyCode;
        if (key === 13) {
          this.save();
          return false;
        }
      },
      'blur [data-value="date"]': function(e) {
        var $this = $(e.currentTarget);
        if (!parseInt($this.val())) {
          $this.val('01');
        } else if (new String($this.val()).length < 2) {
          $this.val('0' + $this.val());
        } else if ($this.val() > 31) {
          $this.val('01');
        }
      },
      'blur [data-value="until_date"]': function(e) {
        var $this = $(e.currentTarget);
        if (!parseInt($this.val())) {
          $this.val('01');
        } else if (new String($this.val()).length < 2) {
          $this.val('0' + $this.val());
        } else if ($this.val() > 31) {
          $this.val('01');
        }
      },
      'blur [data-value="time"]': function(e) {
        var $this = $(e.currentTarget);
        if (!$this.val().match(/\d\d?\:\d\d?/)) {
          $this.val('00:00');
        } 
      },
      'blur [data-value="until_time"]': function(e) {
        var $this = $(e.currentTarget);
        if (!$this.val().match(/\d\d?\:\d\d?/)) {
          $this.val('00:00');
        } 
      }
    },
    render: function() {
      this.$('[data-ui="date"]').toggle( this.$('[data-value="when"]').val() !== 'publish' );
      this.$('[data-ui="until"]').toggle( this.$('[data-value="repeat"]').val() !== 'never' );
      this.$('[data-ui="until_date"]').toggle( this.$('[data-value="until"]').val() === 'future' );
    },
    edit: function(update) {
      this._setUpdate(update);
      this.render();
      this._show();
    },
    save: function() {
      var schedule = {};
      schedule.when = this.$get('when').val();
      schedule.repeat = this.$get('repeat').val();
      schedule.until = this.$get('until').val();
      
      var date = moment();
      date.month(parseInt(this.$get('month').val()));
      date.year(this.$get('year').val());
      date.date(this.$get('date').val());
      var time = this.$get('time').val().split(':');
      date.hours(time[0]);
      date.minutes(time[1]);
      schedule.date = date.utc().format('YYYY-MM-DD HH:mm');
      schedule.time = date.unix();

      date = moment();
      date.month(parseInt(this.$get('until_month').val()));
      date.year(this.$get('until_year').val());
      date.date(this.$get('until_date').val());
      time = this.$get('until_time').val().split(':');
      date.hours(time[0]);
      date.minutes(time[1]);
      schedule.until_date = date.utc().format('YYYY-MM-DD HH:mm');
      schedule.until_time = date.unix();

      this._update.set('schedule', schedule);

      this._hide();
    },
    $get: function(byVal) {
      return this.$('[data-value="' + byVal + '"]');
    },
    _setUpdate: function(update) {
      this._update = update;
      var schedule = this._update.get('schedule') || {};
      var date = schedule.date ? moment(schedule.date + '+0000', 'YYYY-MM-DD HH:mm Z') : moment().utc();
      var untilDate = schedule.until_date ? moment(schedule.until_date + '+0000', 'YYYY-MM-DD HH:mm Z') : moment().utc();
      this.$get('when').val(schedule.when || 'publish');
      this.$get('repeat').val(schedule.repeat || 'never');      
      this.$get('until').val(schedule.until || 'once');
      this.$get('month').val(date.month());
      this.$get('date').val(date.date() < 10 ? '0' + date.date() : date.date());
      this.$get('year').val(date.year());
      this.$get('time').val(date.local().format('HH:mm'));
      this.$get('until_month').val(untilDate.month());
      this.$get('until_date').val(untilDate.date() < 10 ? '0' + untilDate.date() : untilDate.date());
      this.$get('until_year').val(untilDate.year());
      this.$get('until_time').val(untilDate.local().format('HH:mm'));
    },
    _show: function() {
      this.options.metabox.$('.calendar-container').show();
      this.options.metabox.$('.calendar-container .onion').fadeIn();
      this.options.metabox.$el.addClass('show_calendar');
    },
    _hide: function() {
      var metabox = this.options.metabox;
      metabox.$('.onion').hide();
      metabox.$el.removeClass('show_calendar');
      setTimeout(function() {
        metabox.$('.calendar-container').hide();
      }, 500);
    }
  });

  sp.views.Update = Backbone.View.extend({
    events: {
      'click [data-action="change-schedule"]': function(e) {
        this.options.metabox.ui.calendar.edit(this.model);
        return false;
      },
      'keydown textarea': function(e) {
        if (this._editing) {
          var key = e.which || e.keyCode;
          if (key === 13) {
            this.save();
            return false;
          }
        }
      },
      'keyup textarea': function(e) {
        if (this._editing) {
          this.model.set('text', $(e.currentTarget).val());
        }
      },
      'click [data-action="save"]': function(e) {
        this.save();
        return false;
      },
      'click [data-action="delete"]': function(e) {
        // TODO: replace with an undoable workflow:
        if (confirm('Are you sure you want to delete this update?')) {
          this.delete();
        }
        return false;
      },
      'click [data-action="edit"]': function(e) {
        this._editing = true;
        this.render();
        this.$('textarea').focus();
        return false;
      }
    },
    initialize: function() {
      var that = this;

      this._editing = !this.model.get('id');

      this.model.on('change:schedule', function() {
        that.$el.find('[data-value="schedule"]').text(this.getScheduleText());
      });

      this.model.on('change:hidden', function() {
        that.$el.toggle(!that.model.get('hidden'));
      });
    },
    save: function() {
      this._editing = false;
      this.model.save();
      this.render();
    },
    delete: function() {
      this._editing = false;
      this.model.destroy();
      this.remove();
    },
    render: function() {
      if (this.model.get('status') === 'trash') {
        this.$el.html('');
      } else {
        var $template = this._editing ? this.options.metabox.$('[data-template="editor"]') : this.options.metabox.$('[data-template="update"]');
        this.$el.html( $template.html() );
        this.$('[data-value="schedule"]').text(this.model.getScheduleText());
        this.$('[data-ui="avatar"]').attr('src', this.model.profile.get('avatar'));
        this.$('[data-value="text"]').text(this.model.get('text'));
      }
      this.$el.toggle(!this.model.get('hidden'));
      return this;
    }
  });

  sp.views.SocialMetabox = Backbone.View.extend({
    events: {
      'click [data-action="save"]': function(e){
        this.model.set({
          'title': this.$title.val(),
          'image': this.$img.val(),
          'description': this.$desc.val()
        });
        this.model.save();
        return false;
      },
      'click [data-value="social:image"]': function(e) {
        sp.media(this.setImage);
        return false;
      }
    },
    setImage: function(media) {
      console.log('media', media);
    },
    initialize: function() {
      var that = this;
      this.$title = this.$('[data-value="social:title"]');
      this.$img = this.$('[data-value="social:image"]');
      this.$desc = this.$('[data-value="social:description"]');
      this.model = new sp.models.SocialMeta({ post_id: $('#post_ID').val() });
      this.model.fetch({
        success: function(social_metadata) {
          that.render();
        }
      })
      console.log(wp.media.featuredImage, 'fi');
      overrideFeaturedImage();
    },
    render: function() {
      this.$title.val(this.model.get('title'));
      this.$img.val(this.model.get('image'));
      this.$desc.val(this.model.get('description'));
    }
  });

  function overrideFeaturedImage() {
    wp.media.featuredImage = {
      get: function() {
        return wp.media.view.settings.post.featuredImageId;
      },

      set: function( id ) {
        var settings = wp.media.view.settings;

        settings.post.featuredImageId = id;

        wp.media.post( 'set-post-thumbnail', {
          json:         true,
          post_id:      settings.post.id,
          thumbnail_id: settings.post.featuredImageId,
          _wpnonce:     settings.post.nonce
        }).done( function( html ) {
          $( '.inside', '#postimagediv' ).html( html );
        });
      },

      frame: function() {
        if ( this._frame )
          return this._frame;

        this._frame = wp.media({
          state: 'featured-image',
          states: [ new wp.media.controller.FeaturedImage() ]
        });

        this._frame.on( 'toolbar:create:featured-image', function( toolbar ) {
          this.createSelectToolbar( toolbar, {
            text: wp.media.view.l10n.setFeaturedImage
          });
        }, this._frame );

        this._frame.state('featured-image').on( 'select', this.select );
        return this._frame;
      },

      select: function() {
        var settings = wp.media.view.settings,
          selection = this.get('selection').single();

        if ( ! settings.post.featuredImageId )
          return;

        console.log(selection);
        wp.media.featuredImage.set( selection ? selection.id : -1 );
      },

      init: function() {
        // Open the content media manager to the 'featured image' tab when
        // the post thumbnail is clicked.
        $('#postimagediv').on( 'click', '#set-post-thumbnail', function( event ) {
          event.preventDefault();
          // Stop propagation to prevent thickbox from activating.
          event.stopPropagation();

          wp.media.featuredImage.frame().open();

        // Update the featured image id when the 'remove' link is clicked.
        }).on( 'click', '#remove-post-thumbnail', function() {
          wp.media.view.settings.post.featuredImageId = -1;
        });
      }
    };

    $( wp.media.featuredImage.init );
  }

  sp.views.Metabox = Backbone.View.extend({
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
      'click [data-action="create-update"]': function(e) {
        var profile = this.profiles.get( parseInt( $(e.currentTarget).data('profile') ));
        var update = new sp.models.Update({
          'profile_id': profile.get('id'),
          'post_id': $('#post_ID').val()
        });
        update.profile = profile;
        this.updates.add( update );
        return false;
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
                that.updates.each(function(update) {
                  var profile = that.profiles.get(parseInt(update.get('profile_id')));
                  if (!profile) {
                    update.set({ hidden: true });
                  } else {
                    update.set({ hidden: false });
                    update.profile = profile;
                  }
                });
              }
            });
          }
        }, 100);

        return false;
      }
    },
    initialize: function() {
      var metabox = this;

      this.profiles = new sp.models.Profiles();
      this.profiles.on('add', $.proxy(this.addProfile, this));
      this.profiles.on('remove', $.proxy(this.removeProfile, this));
      this.profiles.on('reset', $.proxy(this.resetProfiles, this));

      this.updates = new sp.models.Updates([], { post_id: $('#post_ID').val() });
      this.updates.on('add', $.proxy(this.addUpdate, this));
      this.updates.on('remove', $.proxy(this.removeUpdate, this));
      this.updates.on('reset', $.proxy(this.resetUpdates, this));

      this.ui = {};
      this.ui.profiles = this.$('[data-ui="profiles"]');
      this.ui.updates = this.$('[data-ui="updates"]');
      this.ui.calendar = new sp.views.Calendar({ 'metabox': metabox, el: this.$('[data-ui="calendar"]') });

      this.profiles.fetch({
        success: function(profiles) {
          metabox.updates.fetch({
            success: function(updates) {
              if (updates.length === 0) {
                metabox.$('[data-ui="none"]').show();
              }
              $(window).trigger('sp-updates-loaded', [ updates ]);
            }
          });
          $(window).trigger('sp-profiles-loaded', [ profiles ]);
        }
      });
    },
    addProfile: function(profile) {
      var $ui = $('<li class="profile ' + profile.get('service') + '"><a href="#" data-action="create-update" data-profile="' + profile.get('id') + '" title="' + profile.get('service') + ': ' + profile.get('formatted_username') + '" data-default-text="' + profile.get('default_text') + '"><img class="thumb" src="' + profile.get('avatar') + '"></a></li>');
      this.ui.profiles.append($ui);
      profile.$ui = $ui;
    },
    removeProfile: function(profile) {
      profile.$ui && profile.$ui.remove();
    },
    resetProfiles: function(profiles, result) {
      var that = this;
      this.ui.profiles.html('');
      profiles.each(function(profile) {
        that.addProfile(profile);
      });
    },
    addUpdate: function(update) {
      this.$('[data-ui="none"]').hide();
      if (!update.profile) {
        update.set({ hidden: true });
      }
      update.view = new sp.views.Update({
        model: update,
        metabox: this
      });
      var $update = update.view.render().$el;
      this.ui.updates.append($update);
      $update.find('textarea').focus();
    },
    removeUpdate: function(update) {
      update.view.remove();
      if (this.updates.length === 0) {
        this.$('[data-ui="none"]').show();
      }
    },
    resetUpdates: function(updates, result) {
      var that = this;
      updates.each(function(update) {
        update.view && update.view.remove();
        that.addUpdate(update);
      });
    }
  });

  sp.media = function(callback) {
    // If the frame already exists, re-open it.
    if ( sp_media_frame ) {
        sp_media_frame.open();
        return;
    }
    sp_media_frame = wp.media.frames.sp_media_frame = wp.media({
      className: 'media-frame sp-media-frame',
      frame: 'select',
      multiple: false,
      title: 'Choose a share image',
      library: {
          type: 'image'
      },
      button: {
          text:  'share image'
      }
    });
 
    sp_media_frame.on('select', function(){
      // Grab our attachment selection and construct a JSON representation of the model.
      var media_attachment = sp_media_frame.state().get('selection').first().toJSON();
      callback(media_attachment);
    });

    // Now that everything has been set, let's open up the frame.
    sp_media_frame.open();
  }

}(jQuery, Backbone);