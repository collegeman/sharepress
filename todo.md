# dev
* Bigger buffers plugin
* LinkedIn plugin
* Custom templates plugin
* Custom post types plugin
* Custom URL shortener plugin
* Calendar view plugin
* SharePress meta box can't be used until Post saved as draft
* Open Graph image configuration
* After buffering with SharePress, update future time to reflect publish date
* Then reload post editor, which will reflect updated future publishing time
* Add WP_Error results to buf_ data functions
* In bf_update_update, make sure user owns profile
* Validate days and times in schedules/update

# doc
* How to set license keys with constants, e.g., SP_(FACEBOOK)

# how to buffer something
foreach profile selected
  insert as sp_update with meta.buffer = profile.id and menu_order = 0
get a list of all post_status = future 
  for post_type = sp_update
  sorted by menu_order desc
while (lists have items) {
  get_next_update_time
  for next not updates
    if next.post_date_gmt <= next_update_time
      queue next; remove from list
    else
      break;
  for next update
    if next is for a scheduled post
      get post_date_gmt for schedule post
      if post_date_gmt <= next_update_time
        queue next; remove from list
      else
        break;
    else
      queue next; remove from list
      break;
}

# how to resort
