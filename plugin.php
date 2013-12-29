<?php
/*
Plugin Name: SharePress
Plugin URI: http://getsharepress.com
Description: SharePress: your content, your schedule. Curate awesome content from around the Web and autopost to Facebook, Twitter, LinkedIn, and Google+.
Author: Fat Panda, LLC
Author URI: http://fatpandadev.com
Version: 3.0
License: GPL2
*/

/*
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

@define('SHAREPRESS', __FILE__);
@define('SP_DIR', dirname(__FILE__) );
@define('SP_URL', plugins_url('', SHAREPRESS));
@define('SP_DEBUG', true);
@define('SP_TEST_MESSAGE', "I'm testing SharePress: a plugin for WordPress that helps you curate and autopost to Facebook, Twitter, LinkedIn, and Google+!");
@define('SP_TEST_URL', 'http://getsharepress.com');

require(SP_DIR.'/includes/client.php');
register_activation_hook('sharepress/plugin.php', 'sp_activate');
register_deactivation_hook('sharepress/plugin.php', 'sp_deactivate');