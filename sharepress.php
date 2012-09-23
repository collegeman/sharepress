<?php
/*
Plugin Name: SharePress
Plugin URI: http://fatpandadev.com/sharepress
Description: SharePress: your content, your schedule. Curate awesome content from around the Web, and autopost to Facebook, Twitter, and LinkedIn.
Author: Fat Panda, LLC
Author URI: http://fatpandadev.com
Version: 3.0
License: GPL2
*/

/*
Copyright (C)2011 Fat Panda, LLC

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
@define('SP_DIR', dirname(__FILE__));
@define('SP_URL', plugins_url('', __FILE__));
@define('SP_DEBUG', false);

# bootstrap
require(SP_DIR.'/lib/facebook-sdk/facebook.php');
require(SP_DIR.'/lib/tmh/tmhOAuth.php');
require(SP_DIR.'/lib/tmh/tmhUtilities.php');
require(SP_DIR.'/includes/core.php');
require(SP_DIR.'/includes/buffer.php');
require(SP_DIR.'/includes/adapters.php');
require(SP_DIR.'/includes/api.php');
require(SP_DIR.'/includes/ajax.php');