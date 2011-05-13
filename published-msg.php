<?php 
/*
sharepress
Copyright (C)2010-2011  Fat Panda LLC

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

if (!defined('ABSPATH')) exit; ?>

<fieldset>
  <legend>
    <b>Published on Facebook</b>
  </legend>
  <label style="display:inline-block;">
    <p><em><?php echo htmlentities($meta['message']) ?></em></p>
    <div style="margin-bottom:10px;"><?php echo date_i18n('M d, Y @ H:i', is_string($posted) ? strtotime($posted) : $posted, true) ?></div>
    <input type="checkbox" id="sharepress_meta_publish_again" name="sharepress_meta[publish_again]" value="1" /> 
    Publish to Facebook again
  </label>
</fieldset>
<br />