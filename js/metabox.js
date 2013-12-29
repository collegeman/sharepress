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

  var profiles, updates, sp_media_frame;

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
      this.$('[data-ui="date"]').toggle( this.$('[data-value="when"]').val() !== 'publish' && this.$('[data-value="when"]').val() !== 'immediately' );
      this.$('[data-ui="until"]').toggle( this.$('[data-value="repeat"]').val() !== 'never' );
      this.$('[data-ui="until_date"]').toggle( this.$('[data-value="until"]').val() === 'future' );
      this.$el.dialog({
        modal: true,
        resizable: false,
        draggable: false,
        title: 'Change publish date for this update'
      }).parent('.ui-dialog').addClass('wp-dialog');
    },
    edit: function(update) {
      this._setUpdate(update);
      this.render();
    },
    save: function() {
      var schedule = {};
      schedule.when = this.$get('when').val();
      schedule.repeat = this.$get('repeat').val() || 'never';
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

      if (schedule.repeat != 'never') {

        date = moment();
        date.month(parseInt(this.$get('until_month').val()));
        date.year(this.$get('until_year').val());
        date.date(this.$get('until_date').val());
        time = this.$get('until_time').val().split(':');
        date.hours(time[0]);
        date.minutes(time[1]);
        schedule.until_date = date.utc().format('YYYY-MM-DD HH:mm');
        schedule.until_time = date.unix();

      } else {

        schedule.until_date = null;
        schedule.until_time = null;

      }

      this._update.set('schedule', schedule);

      this._hide();
    },
    $get: function(byVal) {
      return this.$('[data-value="' + byVal + '"]');
    },
    _setUpdate: function(update) {
      this._update = update;
      
      var schedule = this._update.get('schedule') || {},
          date = schedule.date ? moment(schedule.date + '+0000', 'YYYY-MM-DD HH:mm Z') : moment().utc(),
          localDate = date.local(),
          untilDate = schedule.until_date ? moment(schedule.until_date + '+0000', 'YYYY-MM-DD HH:mm Z') : moment().utc(),
          localUntilDate = untilDate.local();

      this.$get('when').val(schedule.when || ( this.options.metabox.options.post.post_status === 'publish' ? 'immediately' : 'publish' ));
      this.$get('repeat').val(schedule.repeat || 'never');      
      this.$get('until').val(schedule.until || 'once');
      this.$get('month').val(localDate.month());
      this.$get('date').val(localDate.date() < 10 ? '0' + localDate.date() : localDate.date());
      this.$get('year').val(localDate.year());
      this.$get('time').val(localDate.format('HH:mm'));
      this.$get('until_month').val(localUntilDate.month());
      this.$get('until_date').val(localUntilDate.date() < 10 ? '0' + localUntilDate.date() : localUntilDate.date());
      this.$get('until_year').val(localUntilDate.year());
      this.$get('until_time').val(localUntilDate.format('HH:mm'));
    },
    _hide: function() {
      this.$el.dialog('destroy')
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
          this.updateCharCount();
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

      $('body').find('#title').on('keyup', function() {
        that.updateCharCount();
      });
    },
    updateCharCount: function() {
      var $textarea = this.$('textarea');
      if ($textarea.length) {
        var content = $textarea.val().toLowerCase();
        if (content.indexOf('[title]') > -1) {
          content = content.replace(/\[title\]/, $('#title').val());
        }
        if (content.indexOf('[link]') > -1) {
          content = content.replace(/\[link\]/, 'http://goo.gl/XXXXXXXXXX');
        }
        this.$('.count').text(content.length);
        this.$('.count').toggleClass('error', content.length > this.model.profile.get('limit'))
      }
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
        var $template = this._editing ? this.options.metabox.$('[data-template="editor"]') : this.options.metabox.$('[data-template="update"]'),
            profile = this.model.profile;

        this.$el.html( $template.html() );
        this.$('[data-value="schedule"]').text(this.model.getScheduleText());
        this.$('[data-ui="avatar"]').attr('src', profile.get('avatar'))
          .addClass(profile.get('service'))
          .parent().attr('title', profile.get('service') + ': ' + profile.get('formatted_username'));
        this.$('[data-value="text"]').text(this.model.get('text'));
      }
      this.$el.toggle(!this.model.get('hidden'));
      this.updateCharCount();
      return this;
    }
  });

  sp.views.SocialMetabox = Backbone.View.extend({
    events: {
      'click [data-action="set-social-image"]': function(e) {
        sp.media(this);
        return false;
      },
      'click .shared_img_thumb': function(e) {
        sp.media(this);
        return false;
      },
      'click [data-action="remove-social-image"]': function(e) {
        $(e.currentTarget).hide();
        this.$('[data-action="set-social-image"]').show();
        this.$img.val('');
        this.$thumb.find('img').remove();
        return false;
      }
    },
    setImage: function(media) {
      this.$('[data-action="set-social-image"]').hide();
      this.$('[data-action="remove-social-image"]').show();
      this.$img.val(media.url);
      this.$thumb.data('media_id', media.id);
      this.setThumb(media.url);
    },
    setThumb: function(img_url) {
      this.$thumb.html($('<img>').attr('src', img_url));
    },
    initialize: function() {
      var that = this;
      this.$title = this.$('[data-value="social:title"]');
      this.$img = this.$('[data-value="social:image"]');
      this.$desc = this.$('[data-value="social:description"]');
      this.$thumb = this.$('.shared_img_thumb');
    }
  });

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
        var profile = profiles.get( parseInt( $(e.currentTarget).data('profile') ));
        var update = new sp.models.Update({
          'profile_id': profile.get('id'),
          'post_id': $('#post_ID').val(),
          'text': '[title] [link]',
          'schedule': {
            'when': this.options.post.post_status === 'publish' ? 'immediately' : 'publish'
          }
        });
        update.profile = profile;
        updates.add( update );
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
            that.ui.profiles.html('<li class="loading">Loading...</li>');
            profiles.fetch({
              success: function(profiles) {
                that.ui.profiles.find('.loading').remove();
              },
              error: function() {
                that.ui.profiles.find('.loading').remove();
                // TODO: helpful error message...?
              }
            });
          }
        }, 100);

        return false;
      }
    },
    initialize: function() {
      // intialize global collections:
      profiles = new sp.models.Profiles(),
      updates = new sp.models.Updates([], { post_id: $('#post_ID').val() });

      this.listenTo(profiles, 'add', this.addProfile);
      this.listenTo(profiles, 'remove', this.removeProfile);
      this.listenTo(profiles, 'sync', this.resetProfiles);
      
      this.listenTo(updates, 'add', this.addUpdate);
      this.listenTo(updates, 'remove', this.removeUpdate);
      this.listenTo(updates, 'sync', this.resetUpdates);
      
      this.ui = {};
      this.ui.profiles = this.$('[data-ui="profiles"]');
      this.ui.updates = this.$('[data-ui="updates"]');
      this.ui.calendar = new sp.views.Calendar({ 
        el: this.$('[data-ui="calendar"]'),
        'metabox': this 
      });

      // anytime profiles is updated, review the updates collection
      // and revise each update to reflect whether or not profiles are present
      profiles.on('sync', function() {
        updates.each(function(update) {
          var profile = profiles.get( parseInt(update.get('profile_id')) );
          update.set({ hidden: !profile });
          update.profile = profile ? profile : update.profile;
        });
      });

      // initialize profiles...
      profiles.fetch({
        // and on success, initialize updates too
        success: function() {
          updates.fetch({
            error: function() {
              // TODO: helper error message...?
            }
          });
        },
        error: function() {
          // TODO: helpful error message...?
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
      this.ui.profiles.html('');
      profiles.each(_.bind(this.addProfile, this));
    },
    addUpdate: function(update) {
      // already drawn? remove and redraw!
      update.view && update.view.remove();
      update.profile = profiles.get(parseInt(update.get('profile_id'))); 
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
      this.$('[data-ui="none"]').toggle(!updates.length);
    },
    removeUpdate: function(update) {
      update.view && update.view.remove();
      this.$('[data-ui="none"]').toggle(!updates.length);
    },
    resetUpdates: function() {
      this.$('[data-ui="none"]').toggle(!updates.length);
      updates.each(_.bind(this.addUpdate, this));
    }
  });

  sp.media = function(metabox) {
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
          text:  'Choose Share Image'
      }
    });
 
    sp_media_frame.on('select', function(){
      // Grab our attachment selection and construct a JSON representation of the model.
      var media_attachment = sp_media_frame.state().get('selection').first().toJSON();
      metabox.setImage(media_attachment);
    });

    sp_media_frame.on('open', function() {
      var id = false;
      if( id = metabox.$thumb.data('media_id') ) {
        // set selection
        var selection   =   sp_media_frame.state().get('selection'),
            attachment  =   wp.media.attachment( id );
        attachment.fetch();
        selection.add( attachment );
      }
    });

    // Now that everything has been set, let's open up the frame.
    sp_media_frame.open();
  }

}(jQuery, Backbone);