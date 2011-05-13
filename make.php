<?php
/*
getwpapps.com Plugin project make script.
Copyright (C)2010-2011  Fat Panda, LLC

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

/*
###############################################################################
# SETUP 
###############################################################################

Use this section to define your plugins' meta data. When you're finished, run

    php make.php configure
    
This command will open core.php, lite.php, pro.php, and readme.txt, and
configure them all with your plugin's meta data. You can run this at any time,
and it runs automatically when you run the publish command.

To learn more about this make script, visit 

    http://github.com/collegeman/wpapp/wiki/Make
    
*/

// What is the current revision number of your plugins?
// Please use a pattern supported by version_compare(): 
//   see http://php.net/manual/en/function.version-compare.php
// This script will automatically append the current time to 
// ensure release uniqueness.
@define('PLUGIN_VERSION',              '1.0');

// What is your name?
@define('PLUGIN_AUTHOR',               'Aaron Collegeman');

// What is the URL of your website?
@define('PLUGIN_AUTHOR_URI',           'http://aaroncollegeman.com');

// What is the URL for your plugin?
@define('PLUGIN_URI',                  'http://getwpapps.com/plugins/sharepress');

// Describe your plugin.
@define('PLUGIN_DESCRIPTION',          'This is my plugin. There are others like it, but this one is mine.');

// What is the name of the lite version of your plugin?
@define('PLUGIN_LITE_NAME',            'Sharepress');

// What is the PHP class name of the lite version of your plugin?
@define('PLUGIN_LITE_CLASS',           'Sharepress');

// What is the name you registered with Wordpress.org?
//   see http://wordpress.org/extend/plugins/add/
@define('PLUGIN_LITE_SLUG',            'sharepress');

// What is the name of the pro version of your plugin?
//   If you don't specify one, "%PLUGIN_LITE_NAME% Pro" will be used
@define('PLUGIN_PRO_NAME',             'Sharepress Pro');

// What is the PHP class name of the pro version of your plugin?
//   If you don't specify one, "%PLUGIN_LITE_NAME%Pro" will be used
@define('PLUGIN_PRO_CLASS',            'SharepressPro');

// What is the name you registered with wpgetapps.com?
//   see http://wpgetapps.com/developers/plugin/add
@define('PLUGIN_PRO_SLUG',             'sharepress');

###############################################################################
# THAT'S IT! The script does the rest. 
###############################################################################

error_reporting(-1);

/**
 * Updates the placeholders in lite.php, core.php, pro.php, and update-client.php.
 */
function configure() {
  $slug = PLUGIN_LITE_SLUG;
  
  // generate new version number
  $version = sprintf('%s.%s', PLUGIN_VERSION, date('Ymdhis'));
  
  // generate pro defaults, when necessary
  $pro_class = PLUGIN_PRO_CLASS ? PLUGIN_PRO_CLASS : sprintf('%sPro', PLUGIN_LITE_CLASS);
  $pro_name = PLUGIN_PRO_NAME ? PLUGIN_PRO_NAME : sprintf('%s Pro', PLUGIN_LITE_NAME);
  
  // setup the variables for lite.php and core.php
  $vars = array(
    'Plugin Name:'        => array( 'value' => PLUGIN_LITE_NAME,    'header' => true  ),
    'Plugin URI:'         => array( 'value' => PLUGIN_URI,          'header' => true  ),
    'Description:'        => array( 'value' => PLUGIN_DESCRIPTION,  'header' => true  ),
    'Version:'            => array( 'value' => $version,            'header' => true  ),
    'Author:'             => array( 'value' => PLUGIN_AUTHOR,       'header' => true  ),
    'Author URI:'         => array( 'value' => PLUGIN_AUTHOR_URI,   'header' => true  ),
    'Stable tag:'         => array( 'value' => $version,            'header' => true  ),
    
    '@PLUGIN_LITE_CLASS@' => array( 'value' => PLUGIN_LITE_CLASS,   'header' => false ),
    '@PLUGIN_PRO_CLASS@'  => array( 'value' => $pro_class,          'header' => false ),
    '@PLUGIN_LITE_SLUG@'  => array( 'value' => PLUGIN_LITE_SLUG,    'header' => false )
  );
  
  file_put_contents("readme.txt", __replace("readme.txt", $vars, true));
  file_put_contents("lite.php", __replace("lite.php", $vars, true));
  file_put_contents("core.php", __replace("core.php", $vars, true));
  
  // update the variables for pro.php
  $vars['Plugin Name:'] =       array( 'value' => $pro_name,        'header' => true  );
  $vars['@PLUGIN_PRO_SLUG@'] =  array( 'value' => PLUGIN_PRO_SLUG,  'header' => false );
  
  file_put_contents("pro.php", __replace("pro.php", $vars, true));
}

function __replace($file, $vars, $return = false) {
  if (!($content = file_get_contents($file))) {
    die("Failed to open file: $file");
  }

  // update the headers in a PHP file
  if (preg_match('#/\*(.*?)\*/#s', $content, $matches, PREG_OFFSET_CAPTURE)) {
    list( $header, $pos ) = $matches[0];
    $len = strlen($header);
    foreach($vars as $token => $var) {
      if ($var['header']) {
        $header = preg_replace('#'.preg_quote($token, '#').'\s.*#', sprintf('%s %s', $token, $var['value']), $header);
      }
    }
    $content = substr($content, 0, $pos).$header.substr($content, $pos + $len);
  }
  
  // update the headers in the readme.txt file
  if ($file == 'readme.txt' && strpos($content, '=== Plugin Name ===') !== false) {
    $start = strpos($content, '=== Plugin Name ===') + 20;
    $end = strpos($content, '== Description ==') + 1;
    $len = $end - $start;
    $header = substr( $content, strpos($content, '=== Plugin Name ===')+20, $len );
    
    foreach($vars as $token => $var) {
      if ($var['header']) {
        $header = preg_replace('#'.preg_quote($token, '#').'\s.*#', sprintf('%s %s', $token, $var['value']), $header);
      }
    }
    
    $content = substr($content, 0, $start).$header.substr($content, $end);
  }
  
  // update other tokens
  foreach($vars as $token => $var) {
    if (!$var['header']) {
      $content = preg_replace(sprintf('#/\*%s\*/ ([\'"])([\w\-]+)([\'"])#', preg_quote($token)), "/*{$token}*/ \${1}{$var['value']}\${3}", $content);
      $content = preg_replace(sprintf('#/\*%s\*/ ([\w\-]+)#', preg_quote($token)), "/*{$token}*/ {$var['value']}", $content);
    }
  }
  
  
  if ($return) {
    return $content;
  } else {
    echo $content;
  }
}

function publish() {
  // run configure first
  configure();
}

###############################################################################
# Go, dogs, go!
###############################################################################

$cmd = @$argv[1];
if (!$cmd) {
  $cmd = 'configure';
}

$cmd();