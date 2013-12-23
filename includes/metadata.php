<?php
add_action('wp_head', 'sp_wp_head');

function sp_get_default_picture() {
  return get_option('sp_default_picture', '');
}

function sp_wp_head() {
  global $wpdb, $post;

  // get any values stored in meta data
  $defaults = array();
  $overrides = array();

  if (is_single() || ( is_page() && !is_front_page() )) {
    $defaults = array(
      'og:type' => 'article',
      'og:url' => get_permalink(),
      'og:title' => strip_tags(get_the_title()),
      'og:image' => sp_get_default_picture(),
      'og:site_name' => get_bloginfo('name'),
      'fb:app_id' => sp_get_opt('facebook_key'),
      'og:description' => strip_shortcodes($excerpt),
      'og:locale' => sp_get_opt('og_locale', 'en_US')
    );
    
  } else {
    $defaults = array(
      'og:type' => sp_get_opt('page_og_type', 'blog'),
      'og:url' => is_front_page() ? get_bloginfo('siteurl') : get_permalink(),
      'og:title' => strip_tags(get_bloginfo('name')),
      'og:site_name' => get_bloginfo('name'),
      'og:image' => sp_get_default_picture(),
      'fb:app_id' => sp_get_opt('facebook_key'),
      'og:description' => strip_shortcodes(get_bloginfo('description')),
      'og:locale' => sp_get_opt('og_locale', 'en_US')
    );
  }
   
  $og = array_merge($defaults, $overrides);

  if ( $fb_publisher = sp_get_opt('fb_publisher_url') ) {
    $og['article:publisher'] = $fb_publisher;
  }

  if ( $fb_author = get_the_author_meta( 'fb_author_url', $post->post_author ) ) {
    $og['article:author'] = $fb_author;
  }
  
  /* THIS NEED TO BE UNCOMMENTED WHEN THE SETTINGS PAGE IS DONE
  // Unset the meta tags that have be turned off globally.
  // old way:
  if ($page_og_tags = get_option('page_og_tags')) {
    if ($page_og_tags == 'imageonly') {
      $og = array('og:image' => $og['og:image']);
    } else if ($page_og_tags == 'off') {
      $og = array();
    }
  // new way:
  } else {
    $allowable = array_merge(array(
      'og:title' => false,
      'og:type' => false,
      'og:image' => false,
      'og:url' => false,
      'fb:app_id' => false,
      'og:site_name' => false,
      'og:description' => false,
      'og:locale' => false
    ), get_option('page_og_tag', array()));

    foreach($allowable as $tag => $allowed) {
      if (!$allowed) {
        unset($og[$tag]);
      }
    }
  }
*/
  $og = apply_filters('sharepress_og_tags', $og, $post, $meta);

  if ($og) {
    echo '<!-- sharepress social metatags -->';

    foreach($og as $property => $content) {
      list($prefix, $tagName) = explode(':', $property);
      //(backwards compatibility) filter overrides
      $og[$property] = apply_filters("sharepress_og_tag_{$tagName}", $content, $post, $meta);
      
      //generic filter overrides
      $og[$property] = apply_filters("sp_social_{$tagName}", $content, $post, $meta);
      
      //(backwards compatibility) allow for overrides from custom field data 
      if ($content = get_post_meta($post->ID, $property, true)) {
        $og[$property] = $content;
      }
      
      // generic field overrides
      if ( $tagName == 'title' && $content = get_post_meta($post->ID, 'social:title', true) ) {
        $og[$property] = $content;
      }
      if ( $tagName == 'image' && $content = get_post_meta($post->ID, 'social:image', true) ) {
        $og[$property] = $content;
      }
      if ( $tagName == 'description' && $content = get_post_meta($post->ID, 'social:description', true) ) {
        $og[$property] = $content;
      }
    }
    
    foreach($og as $property => $content) {
      echo sprintf("<meta property=\"{$property}\" content=\"%s\" />\n", str_replace(
        array('"', '<', '>'), 
        array('&quot;', '&lt;', '&gt;'), 
        strip_shortcodes($content)
      ));
    }
    echo '<!-- /sharepress social metatags -->';
  } 
}