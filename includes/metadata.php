<?php
add_action('wp_head', 'sp_wp_head');

/**
 * @return String URL for the default picture to be used in og:image
 * when no other image is provided for
 */
function sp_get_default_picture() {
  return get_option('sp_default_picture', '');
}

/**
 * @return array 
 *   - key = and open graph meta tag, e.g., og:title
 *   - value = boolean, true for should be rendered by sharepress, otherwise false
 */
function sp_get_allowed_og_tags() {
  // backwards compat with SharePress 2.x
  $old_settings = get_option('sharepress_settings');
  if (!empty($old_settings['page_og_tag'])) {
    $defaults = $old_settings['page_og_tag'];
  } else {
    // by default, all but og:url
    $defaults = array(
      'og:title'          => true,
      'og:type'           => true,
      'og:image'          => true,
      'og:url'            => false,
      'fb:app_id'         => true,
      'og:site_name'      => true,
      'og:description'    => true,
      'og:locale'         => true,
      'article:publisher' => true,
      'article:author'    => true
    );
  }

  // load saved from the database
  $allowed = sp_get_opt('og_tag', $defaults);

  return $allowed;
}

/**
 * @return String The value to be used for the og:locale tag
 */
function sp_get_og_locale() {
  // backwards compat with SharePress 2.x
  $old_settings = get_option('sharepress_settings');
  if (!empty($old_settings['og_locale'])) {
    $default = $old_settings['og_locale'];
  } else {
    $default = get_locale();
  }
  return sp_get_opt('og_locale', $default);
}

/**
 * @return String The value that should be used for the og:type meta tag on
 * the home page.
 */
function sp_get_og_site_type() {
  // backwards compat with SharePress 2.x
  $old_settings = get_option('sharepress_settings');
  if (!empty($old_settings['page_og_type'])) {
    $default = $old_settings['page_og_type'];
  } else {
    $default = 'website';
  }
  return sp_get_opt('og_site_type', $default);
}

/**
 * @return String The value that should be used for the article:publisher OG meta tag
 */
function sp_get_og_article_publisher() {
  // backwards compat with SharePress 2.x
  $old_settings = get_option('sharepress_settings');
  if (!empty($old_settings['fb_publisher_url'])) {
    $default = $old_settings['fb_publisher_url'];
  } else {
    $default = '';
  }
  return sp_get_opt('og_article_publisher', $default);
}

/**
 * @param Object WordPress Post object
 * @return String For a given post, return the value to be used for the article:author OG meta tag
 */
function sp_get_og_article_author($post) {
  // backwards compatibility:
  $old_setting = get_the_author_meta( 'fb_author_url', $post->post_author );

  $new_setting = get_the_author_meta( 'article:author', $post->post_author );
  return $new_setting ? $new_setting : $old_setting;
}

function replace_utf8_entities($text) {
  $chr_map = array(
     // Windows codepage 1252
     "\xC2\x82" => "'", // U+0082⇒U+201A single low-9 quotation mark
     "\xC2\x84" => '"', // U+0084⇒U+201E double low-9 quotation mark
     "\xC2\x8B" => "'", // U+008B⇒U+2039 single left-pointing angle quotation mark
     "\xC2\x91" => "'", // U+0091⇒U+2018 left single quotation mark
     "\xC2\x92" => "'", // U+0092⇒U+2019 right single quotation mark
     "\xC2\x93" => '"', // U+0093⇒U+201C left double quotation mark
     "\xC2\x94" => '"', // U+0094⇒U+201D right double quotation mark
     "\xC2\x9B" => "'", // U+009B⇒U+203A single right-pointing angle quotation mark

     // Regular Unicode     // U+0022 quotation mark (")
                            // U+0027 apostrophe     (')
     "\xC2\xAB"     => '"', // U+00AB left-pointing double angle quotation mark
     "\xC2\xBB"     => '"', // U+00BB right-pointing double angle quotation mark
     "\xE2\x80\x98" => "'", // U+2018 left single quotation mark
     "\xE2\x80\x99" => "'", // U+2019 right single quotation mark
     "\xE2\x80\x9A" => "'", // U+201A single low-9 quotation mark
     "\xE2\x80\x9B" => "'", // U+201B single high-reversed-9 quotation mark
     "\xE2\x80\x9C" => '"', // U+201C left double quotation mark
     "\xE2\x80\x9D" => '"', // U+201D right double quotation mark
     "\xE2\x80\x9E" => '"', // U+201E double low-9 quotation mark
     "\xE2\x80\x9F" => '"', // U+201F double high-reversed-9 quotation mark
     "\xE2\x80\xB9" => "'", // U+2039 single left-pointing angle quotation mark
     "\xE2\x80\xBA" => "'", // U+203A single right-pointing angle quotation mark
  );
  $chr = array_keys  ($chr_map); // but: for efficiency you should
  $rpl = array_values($chr_map); // pre-calculate these two arrays
  return urlencode(str_replace($chr, $rpl, html_entity_decode($text, ENT_QUOTES, "UTF-8")));
}

/**
 * Add Open Graph meta data to the <head> tag
 */
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
      'og:locale' => sp_get_og_locale()
    );
    
  } else {
    $defaults = array(
      'og:type' => sp_get_og_site_type(),
      'og:url' => is_front_page() ? get_bloginfo('siteurl') : get_permalink(),
      'og:title' => strip_tags(get_bloginfo('name')),
      'og:site_name' => get_bloginfo('name'),
      'og:image' => sp_get_default_picture(),
      'fb:app_id' => sp_get_opt('facebook_key'),
      'og:description' => strip_shortcodes(get_bloginfo('description')),
      'og:locale' => sp_get_og_locale()
    );
  }
   
  $og = array_merge($defaults, $overrides);

  if ( $article_publisher = sp_get_og_article_publisher() ) {
    $og['article:publisher'] = $article_publisher;
  }

  if ( $article_author = sp_get_og_article_author($post) ) {
    $og['article:author'] = $article_author;
  }
  
  $allowed = array_merge(array(
    'og:title' => false,
    'og:type' => false,
    'og:image' => false,
    'og:url' => false,
    'fb:app_id' => false,
    'og:site_name' => false,
    'og:description' => false,
    'og:locale' => false,
    'article:publisher' => false,
    'article:author' => false
  ), sp_get_allowed_og_tags());
  foreach($allowed as $tag => $allowed) {
    if (!$allowed) {
      unset($og[$tag]);
    }
  }

  $og = apply_filters('sharepress_og_tags', $og, $post, $meta);

  if ($og) {
    echo '<!-- sharepress social metatags -->';

    foreach($og as $property => $content) {
      list($prefix, $tagName) = explode(':', $property);

      // TODO: make these three overrides part of the Advanced Metadata plugin:
      // (backwards compatibility) filter overrides
      $og[$property] = apply_filters("sharepress_og_tag_{$tagName}", $content, $post, $meta);
      
      // generic filter overrides
      $og[$property] = apply_filters("sp_social_{$tagName}", $content, $post, $meta);
      
      // (backwards compatibility) allow for overrides from custom field data 
      if ($content = get_post_meta($post->ID, $property, true)) {
        $og[$property] = $content;
      }
      
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
      if ($stripped = strip_shortcodes($content)) {
        echo sprintf("<meta property=\"{$property}\" content=\"%s\" />\n", replace_utf8_entities(str_replace(
          array('"', '<', '>'), 
          array('&quot;', '&lt;', '&gt;'), 
          $stripped
        )));
      }
    }
    
    echo '<!-- /sharepress social metatags -->';
  } 
}