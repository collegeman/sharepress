<div id="sp-updates-screen" class="wrap">

  <div id="message" class="updated fade" style="display:none;"></div> 

  <!--   <p>You are reviewing the social publication history of <a href="#">Some post title...</a> &nbsp; &nbsp; <a class="button" href="#">remove filter</a></p>
   -->

  <?php screen_icon(); ?>
  <h2>SharePress Updates</h2>

  <ul class="subsubsub"></ul>
    
  <table class="wp-list-table widefat fixed posts" cellspacing="0">
    <thead>
      <tr>
        <th scope="col" id="cb" class="manage-column column-cb check-column" style="">
          <label class="screen-reader-text" for="cb-select-all-1">Select All</label>
          <input id="cb-select-all-1" type="checkbox">
        </th>
        <th scope="col" class="manage-column column-update">
          <span>Update</span>
        </th>
        <th scope="col" class="manage-column column-post">
          <span>Post</span>
        </th>
        <th scope="col" class="manage-column column-date">
          <span>Date</span>
        </th>
        <th scope="col" class="manage-column column-error">
          <span>Error</span>
        </th>
      </tr>
    </thead>

    <tfoot>
      <th scope="col" id="cb" class="manage-column column-cb check-column" style="">
        <label class="screen-reader-text" for="cb-select-all-1">Select All</label>
        <input id="cb-select-all-1" type="checkbox">
      </th>
      <th scope="col" class="manage-column column-update">
        <span>Update</span>
      </th>
      <th scope="col" class="manage-column column-post">
        <span>Post</span>
      </th>
      <th scope="col" class="manage-column column-date">
        <span>Date</span>
      </th>
      <th scope="col" class="manage-column column-error">
        <span>Error</span>
      </th>
    </tfoot>

    <tbody id="the-list">
      <tr class="no-items" style="display:none;">
        <td class="colspanchange" colspan="4">No Updates found.</td>
      </tr>
    </tbody>
  </table>

  <div class="tablenav bottom">

    <div class="alignleft actions bulkactions">
      <select name="action2">
        <option value="-1" selected="selected">Bulk Actions</option>
        <option value="trash">Move to Trash</option>
      </select>
      <input type="submit" name="" id="doaction2" class="button action" value="Apply">
    </div>
    <div class="alignleft actions"></div>

    <div class="tablenav-pages one-page"><span class="displaying-num">4 items</span>
      <span class="pagination-links"><a class="first-page disabled" title="Go to the first page" href="http://www.dev.wp.fatpandadev.com/wp-admin/edit.php">«</a>
      <a class="prev-page disabled" title="Go to the previous page" href="http://www.dev.wp.fatpandadev.com/wp-admin/edit.php?paged=1">‹</a>
      <span class="paging-input">1 of <span class="total-pages">1</span></span>
      <a class="next-page disabled" title="Go to the next page" href="http://www.dev.wp.fatpandadev.com/wp-admin/edit.php?paged=1">›</a>
      <a class="last-page disabled" title="Go to the last page" href="http://www.dev.wp.fatpandadev.com/wp-admin/edit.php?paged=1">»</a></span>
    </div>
    
    <br class="clear">
    
  </div>
</div>

<script id="sp-subsubsub" type="text/x-template">
  <li class="all"><a href="<?php echo admin_url('admin.php?page=sp-updates') ?>" <% if (selected == 'all') { %> class="current" <% } %> >All <span class="count">(<%= all %>)</span></a> |</li>
  <li class="sent"><a href="<?php echo admin_url('admin.php?page=sp-updates&post_status=sent') ?>"<% if (selected == 'sent') { %> class="current" <% } %> >Sent <span class="count">(<%= sent %>)</span></a> |</li>
  <li class="pending"><a href="<?php echo admin_url('admin.php?page=sp-updates&post_status=buffer') ?>"<% if (selected == 'buffer') { %> class="current" <% } %> >Pending <span class="count">(<%= buffer %>)</span></a> |</li>
  <li class="errors"><a href="<?php echo admin_url('admin.php?page=sp-updates&post_status=error') ?>"<% if (selected == 'error') { %> class="current" <% } %> >Errors <span class="count">(<%= error %>)</span></a> |</li>
  <li class="trash"><a href="<?php echo admin_url('admin.php?page=sp-updates&post_status=trash') ?>"<% if (selected == 'trash') { %> class="current" <% } %> >Trash <span class="count">(<%= trash %>)</span></a></li>
</script>

<script id="sp-tablerow-template" type="text/x-template">
  <th scope="row" class="check-column">
    <label class="screen-reader-text" for="cb-select-#">Select Update <%= id %></label>
    <input id="cb-select-<%= id %>" type="checkbox" name="post[]" value="<%= id %>">
    <div class="locked-indicator"></div>
  </th>
  <td class="column-update">
    <span class="text"><%= text %></span>
    <div class="row-actions">
      <% if (status === 'buffer') { %>
        <span class="edit hide-if-no-js">
          <a href="#" data-action="edit" title="Edit this Update">Edit</a> |
        </span>
      <% } %>
      <% if (status === 'error') { %>
        <span class="edit hide-if-no-js">
          <a href="#" data-action="retry" title="Retry publishing this Update">Retry</a> |
        </span>
      <% } else if (status === 'sent' && post.status !== 'trash') { %>
        <span class="edit hide-if-no-js">
          <a href="<?php echo admin_url('post.php?action=edit') ?>&post=<%= post.id %>" title="Repost this Update">Repost</a> |
        </span>
      <% } %>
      <span class="trash">
        <a class="submitdelete" data-action="delete" title="Move this Update to the Trash" href="#">Trash</a>
      </span>
    </div>
  </td>
  <td class="column-post">
    <a class="post status-<%= post.status %>" href="<?php echo admin_url('post.php?action=edit') ?>&amp;post=<%= post.id %>"><%= post.title %></a>
  </td>
  <td class="column-date">
    <abbr title="<%= moment(sent_at ? sent_at : created_at, 'X').local().format('YYYY/MM/DD h:mm A') %>">
      <%= moment(sent_at ? sent_at : created_at, 'X').local().fromNow() %>
    </abbr><br><%= status %>
  </td> 
  <td class="column-error"><%= error ? '' : '&mdash;' %></td> 
</script>

<script>
jQuery(function($) {
  new sp.views.UpdatesScreen({ el: $('#sp-updates-screen') });
});
</script>