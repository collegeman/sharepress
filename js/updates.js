!function($, B) {

  var updates = new sp.models.Updates();

  var counts = new (B.Model.extend({
    url: function() {
      return sp.api + '/updates/counts'
    },
    sync: sp.sync
  }))({
    all: 0,
    buffer: 0,
    sent: 0,
    error: 0,
    trash: 0
  });

  updates.on('sync', function() {
    counts.fetch();
  });

  var SubSubSub = B.View.extend({
    initialize: function() {
      this.listenTo(counts, 'sync', this.render);
    },
    render: function() {
      var att = counts.attributes;
      att.selected = updates.params.status || 'all';
      this.$el.html( SubSubSub.template(att) );
      return this;
    }
  });

  var router = new (Backbone.Router.extend({
    routes: {
      '?*:qs': 'updates'
    },
    updates: function() {
      var result = {};
      _.each( (document.location.search || '').replace(/^.*?\?/, '').split('&'), function(p) {
        var split = p.split('=');
        if (split.length !== 2) {
          return;
        }
        result[split[0]] = decodeURIComponent(split[1]);
      });
      
      updates.params.set({
        fields: 'profile,error',
        post_status: result.post_status
      });
    }
  }));

  SubSubSub.template = _.template( $('#sp-subsubsub').html() );

  var TableRow = B.View.extend({
    tagName: 'tr',
    className: 'type-sp_update',
    events: {
      'click [data-action="delete"]': function() {
        this.model.destroy({
          success: function() {
            counts.fetch();
          }
        });        
        return false;
      }
    },
    initialize: function() {
      
    },
    render: function() {
      this.$el.removeClass();
      this.$el.addClass('update update-' + this.model.get('id'));
      this.$el.addClass('status-' + this.model.get('status'));
      this.$el.html( TableRow.template(this.model.attributes) );
      return this;
    }
  });

  TableRow.template = _.template( $('#sp-tablerow-template').html() ); 

  sp.views.UpdatesScreen = B.View.extend({
    events: {
      "click [href^='admin.php?page=sp-updates']": function(e) {
        router.navigate($(e.currentTarget).attr('href'), { trigger: true });
        return false;
      }
    },
    initialize: function(options) {
      this.$table = this.$('table');
      this.$nav = this.$('.tablenav');
      this.filters = new SubSubSub({ el: this.$('.subsubsub') }).render().el;
      this.listenTo(updates, 'add', this.addUpdate);
      this.listenTo(updates, 'remove', this.removeUpdate);
      this.listenTo(updates, 'sync', this.reset);
      
      $(function() {  
        B.history.start({ pushState: true, root: options.root });
      });
    },
    reset: function() {
      var cnt = updates.getCount();
      this.$nav.find('.displaying-num').text(cnt + ( cnt != 1 ? ' items' : ' item' ));
      this.$table.find('.no-items').toggle(!updates.length);
      this.$table.find('tr.update').remove();
      updates.each(_.bind(this.addUpdate, this));
      this.render();
    },
    addUpdate: function(update) {
      this.$table.append( new TableRow({ model: update }).render().$el );
    },
    removeUpdate: function(update) {
      this.$table.find('.update-' + update.get('id')).remove();
      this.$table.find('.no-items').toggle(!updates.length);
    }
  });

}(jQuery, Backbone);