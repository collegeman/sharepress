<div id="sp-updates-screen" class="wrap">

  <div id="message" class="updated fade" style="display:none;"></div> 

  <!--   <p>You are reviewing the social publication history of <a href="#">Some post title...</a> &nbsp; &nbsp; <a class="button" href="#">remove filter</a></p>
   -->

  <?php screen_icon(); ?>
  <h2>SharePress Update History</h2>

  <ul class="subsubsub"></ul>
    
  <div class="tablenav top">

    <div class="alignleft actions bulkactions">
      <select name="action">
        <option value="-1" selected="selected">Bulk Actions</option>
        <option value="trash">Move to Trash</option>
      </select>
      <input type="submit" name="" id="doaction" class="button action" value="Apply">
    </div>
    <div class="alignleft actions">
      <!-- <select name="m">
        <option selected="selected" value="0">Show all dates</option>
        <option value="201312">December 2013</option>
      </select>
      <select name="cat" id="cat" class="postform">
        <option value="0">View all categories</option>
        <option class="level-0" value="1">Uncategorized</option>
      </select>
      <input type="submit" name="" id="post-query-submit" class="button" value="Filter">  -->   
    </div>

    <div class="tablenav-pages one-page">
      <span class="displaying-num"></span>
      <span class="pagination-links"><a class="first-page" title="Go to the first page" href="http://www.dev.wp.fatpandadev.com/wp-admin/edit.php">&laquo;</a>
      <a class="prev-page" title="Go to the previous page" href="http://www.dev.wp.fatpandadev.com/wp-admin/edit.php?paged=1">&lsaquo;</a>
      <span class="paging-input">1 of <span class="total-pages">1</span></span>
      <a class="next-page" title="Go to the next page" href="http://www.dev.wp.fatpandadev.com/wp-admin/edit.php?paged=1">&rsaquo;</a>
      <a class="last-page" title="Go to the last page" href="http://www.dev.wp.fatpandadev.com/wp-admin/edit.php?paged=1">&raquo;</a></span>
    </div>
    
    
    <!-- <div class="view-switch">
      <a href="/wp-admin/edit.php?mode=list" class="current"><img id="view-switch-list" src="http://www.dev.wp.fatpandadev.com/wp-includes/images/blank.gif" width="20" height="20" title="List View" alt="List View"></a>
      <a href="/wp-admin/edit.php?mode=excerpt"><img id="view-switch-excerpt" src="http://www.dev.wp.fatpandadev.com/wp-includes/images/blank.gif" width="20" height="20" title="Excerpt View" alt="Excerpt View"></a>
    </div> -->

    <br class="clear">
  </div>

  <table class="wp-list-table widefat fixed posts" cellspacing="0">
    <thead>
      <tr>
        <th scope="col" id="cb" class="manage-column column-cb check-column" style="">
          <label class="screen-reader-text" for="cb-select-all-1">Select All</label>
          <input id="cb-select-all-1" type="checkbox">
        </th>
        <th scope="col" class="manage-column column-profile">
          <label class="screen-reader-text">Profile</label>
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
      <th scope="col" class="manage-column column-profile">
        <label class="screen-reader-text">Profile</label>
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

    <div class="tablenav-pages one-page">
      <span class="displaying-num"></span>
      <span class="pagination-links"><a class="first-page" title="Go to the first page" href="http://www.dev.wp.fatpandadev.com/wp-admin/edit.php">&laquo;</a>
      <a class="prev-page" title="Go to the previous page" href="http://www.dev.wp.fatpandadev.com/wp-admin/edit.php?paged=1">&lsaquo;</a>
      <span class="paging-input">1 of <span class="total-pages">1</span></span>
      <a class="next-page" title="Go to the next page" href="http://www.dev.wp.fatpandadev.com/wp-admin/edit.php?paged=1">&rsaquo;</a>
      <a class="last-page" title="Go to the last page" href="http://www.dev.wp.fatpandadev.com/wp-admin/edit.php?paged=1">&raquo;</a></span>
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
  <td class="column-profile">
    <% if (profile) { %>
      <img src="<%= profile.avatar %>" class="sp-profile thumb <%= profile.service %>" title="<%= profile.service %>: <%= profile.formatted_username %>">
    <% } %>
  </td>
  <td class="column-update">
    <span class="text"><%= text_formatted ? text_formatted : text %></span>
    <div class="row-actions">
      <% if (status === 'buffer') { %>
        <span class="edit hide-if-no-js">
          <a href="<?php echo admin_url('post.php?action=edit') ?>&post=<%= post.id %>" title="Edit this Update">Edit</a> |
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
    <a class="post" href="<?php echo admin_url('post.php?action=edit') ?>&amp;post=<%= post.id %>"><%= post.title %></a>
    <br><span class="post status status-<%= post.status %>"><%= post.status == 'publish' ? 'published' : post.status %></span>
  </td>
  <td class="column-date">
    <% if (status === 'sent') { %>
      <abbr title="<%= moment(sent_at, 'X').local().format('YYYY/MM/DD h:mm A') %>">
        <%= moment(sent_at, 'X').local().fromNow() %>
      </abbr>
    <% } else if (status === 'buffer' || status === 'error') { %>
      <abbr title="<%= moment(parseInt(due_at) ? due_at : created_at, 'X').local().format('YYYY/MM/DD h:mm A') %>">
        <%= parseInt(due_at) ? moment(due_at, 'X').local().fromNow() : ( schedule.when == 'publish' ? 'Post on publish' : 'Post now' ) %>
      </abbr>
    <% } else { %>
      <abbr title="<%= moment(created_at, 'X').local().format('YYYY/MM/DD h:mm A') %>">
        <%= moment(created_at, 'X').local().fromNow() %>
      </abbr>
    <% } %>
    <br><span class="update status status-<%= status %>"><%= status == 'buffer' ? 'pending' : status %></span>
  </td> 
  <td class="column-error"><%= error ? '<span class="error">' + error + '</span>' : '&mdash;' %></td> 
</script>

<script>
jQuery(function($) {
  new sp.views.UpdatesScreen({ 
    el: $('#sp-updates-screen'),
    root: '<?php echo admin_url('admin.php') ?>' 
  });
});
</script>