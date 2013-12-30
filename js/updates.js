!function($, B) {

  var updates = new sp.models.Updates();

  var counts = new (B.Model.extend({
    url: function() {
      return sp.api + '/updates/counts'
    }
  }))({
    all: 0,
    buffer: 0,
    sent: 0,
    error: 0,
    trash: 0
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

  var Router = Backbone.Router.extend({
    routes: {
      '': 'updates',
      '?*qs': 'updates'
    },
    updates: function() {
      alert('here');
    }
  });

  SubSubSub.template = _.template( $('#sp-subsubsub').html() );

  var TableRow = B.View.extend({
    tagName: 'tr',
    className: 'type-sp_update',
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
    initialize: function(options) {
      this.$table = this.$('table');
      this.$nav = this.$('.tablenav');
      this.filters = new SubSubSub({ el: this.$('.subsubsub') }).render().el;
      this.listenTo(updates, 'add', this.addUpdate);
      this.listenTo(updates, 'remove', this.removeUpdate);
      this.listenTo(updates, 'sync', this.reset);
      this.router = new Router();

      counts.fetch();
      updates.params.set({ fields: 'profile,error' });

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
      this.$table.find('update-' + update.get('id')).remove();
    }
  });

}(jQuery, Backbone);