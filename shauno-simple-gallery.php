<?php
/*
Plugin Name: Shauno Simple Gallery
Plugin URI: http://shauno.co.za/wordpress-shauno-simple-gallery/
Description: A simple, straight forward image gallery. Front end display is easily templated, to display as you please.
Version: 1.0
Author: Shaun Alberts
Author URI: http://shauno.co.za
*/
/*
Copyright 2011  Shaun Alberts  (email : shaun@shauno.com)

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

// stop direct call
if(preg_match("#".basename(__FILE__)."#", $_SERVER["PHP_SELF"])) {die("You are not allowed to call this page directly.");}

class shaunoSimpleGallery { //might as well contain it in a class
	private $adminUrl, $pluginUrl; //should be constant, but cant define it here with calling the get_admin_url() method
	
	//construct
	function shaunoSimpleGallery() {
		register_activation_hook(__FILE__, array(&$this, 'dbUpgrade'));
		
		add_action('admin_init', array(&$this, 'adminInits'));
		
		add_action('admin_menu', array(&$this, 'adminMenus'));
		
		add_shortcode('ssgallery' , array(&$this, 'code_ssgallery'));
		
		$this->adminUrl = get_admin_url().'admin.php?';
		
		$d = explode('/', str_replace('\\', '/', dirname(__FILE__)));
		$dir = array_pop($d);
		$this->pluginUrl = WP_PLUGIN_URL.'/'.$dir.'/';
		$this->pluginPath = WP_PLUGIN_DIR.'/'.$dir.'/';
	}
	
	//activation
	function dbUpgrade() {
		global $wpdb;
		
		$table_name = $wpdb->prefix.'ssg_gallery';
		$sql = 'CREATE TABLE '.$table_name.' (
			id BIGINT NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL,
			slug VARCHAR(255) NOT NULL,
			description TEXT NULL,
			directory VARCHAR(255) NOT NULL,
			url VARCHAR(255) NOT NULL,
			added_by BIGINT NOT NULL DEFAULT 0,
			date_added DATETIME NOT NULL DEFAULT "0000-00-00 00:00:00",
			edited_by BIGINT NOT NULL DEFAULT 0,
			date_edited DATETIME NOT NULL DEFAULT "0000-00-00 00:00:00",
			deleted BIGINT NOT NULL DEFAULT 0,
			deleted_by BIGINT NOT NULL DEFAULT 0,
			date_deleted DATETIME NOT NULL DEFAULT "0000-00-00 00:00:00",
			UNIQUE KEY id (id)
		);';
		require_once(ABSPATH.'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		
		$table_name = $wpdb->prefix.'ssg_image';
		$sql = 'CREATE TABLE '.$table_name.' (
			id BIGINT NOT NULL AUTO_INCREMENT,
			gallery_id BIGINT NOT NULL,
			filename VARCHAR(255) NOT NULL,
			alt VARCHAR(255) NULL,
			caption TEXT NULL,
			added_by BIGINT NOT NULL DEFAULT 0,
			date_added DATETIME NOT NULL DEFAULT "0000-00-00 00:00:00",
			edited_by BIGINT NOT NULL DEFAULT 0,
			date_edited DATETIME NOT NULL DEFAULT "0000-00-00 00:00:00",
			deleted BIGINT NOT NULL DEFAULT 0,
			deleted_by BIGINT NOT NULL DEFAULT 0,
			date_deleted DATETIME NOT NULL DEFAULT "0000-00-00 00:00:00",
			UNIQUE KEY id (id)
		);';
		require_once(ABSPATH.'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		
  }
  
  // API Functions {
  	private function validSlug($slug) {
  		return preg_match('/^[a-z0-9\_\-]+$/i', $slug);
  	}
  	
  	private function thumb($config) {
  		if(!$config['src']) {
  			return false;
  		}
  		if(!(int)$config['width'] && !(int)$config['height']) {
  			return false;
  		}
  		
  		if(file_exists($config['src']) && function_exists('gd_info')) {
  			$info = getimagesize($config['src']);
  			switch($info[2]) {
  			case IMAGETYPE_GIF :
  				$createfrom_function = 'imagecreatefromgif';
  				$saveas_function = 'imagegif';
  				break;
  			case IMAGETYPE_JPEG :
  				$createfrom_function = 'imagecreatefromjpeg';
  				$saveas_function = 'imagejpeg';
  				$quality = $config['quality'] ? $config['quality'] : 100;
  				break;
  			case IMAGETYPE_PNG :
  				$createfrom_function = 'imagecreatefrompng';
  				$saveas_function = 'imagepng';
  				$quality = $config['quality'] ? $config['quality'] : 100;
  				$quality = ceil(($quality - 10) / 10); //quality is 0-9 in pngs
  				break;
  				default	:
  				return false;
  			}
  			
  			$pathInfo = pathinfo($config['src']);
  			$extLen = strlen($pathInfo['extension']) + 1;
  			$newFile = $pathInfo['dirname'].'/'.substr($pathInfo['basename'], 0, ($extLen *= -1)).'_'.$config['width'].'x'.$config['height'].($config['crop'] ? '_crop' : '').($quality ? '_'.$quality : '').'.'.$pathInfo['extension'];
  			
  			if(file_exists($newFile)) { //create it if not created already
  				$part = explode('/', $newFile);
  				return array_pop($part);
  			}else{
  				$image = $createfrom_function($config['src']);
  				// Get original width and height
  				$width = imagesx($image);
  				$height = imagesy($image);
  				
  				$destW = $config['width'];
  				$destH = $config['height'];
  				
  				// don't allow new width or height to be greater than the original
  				if($destW > $width) {
  					$destW = $width;
  				}
  				if($destH > $height) {
  					$destH = $height;
  				}
  				
  				// generate new w/h if not provided
  				if($destW && !$destH) {
  					$destH = $height * ($destW / $width);
  				} elseif($destH && !$destW) {
  					$destW = $width * ($destH / $height);
  				} elseif(!$destW && !$destH) {
  					$destW = $width;
  					$destH = $height;
  				}
  				
  				if($config['crop']) {
  					$srcX = $srcY = 0;
  					$srcW = $width;
  					$srcH = $height;
  					
  					$cmp_x = $width  / $destW;
  					$cmp_y = $height / $destH;
  					
  					// calculate x or y coordinate and width or height of source
  					if ( $cmp_x > $cmp_y ) {
  						$srcW = round(($width / $cmp_x * $cmp_y));
  						$srcX = round(($width - ($width / $cmp_x * $cmp_y)) / 2);
  					} elseif ($cmp_y > $cmp_x) {
  						$srcH = round(($height / $cmp_y * $cmp_x));
  						$srcY = round(($height - ($height / $cmp_y * $cmp_x)) / 2);
  					}
  					
  					//just make sure all the args are set nice and standard for the copy
  					$destX = 0;
  					$destY = 0;
  				} else {
  					if($destW && $destH) {
  						//not allowed to grow, so fit sizes into original image sizes
  						$destW = $destW < $info[0] ? $destW : $info[0];
  						$destH = $destH < $info[1] ? $destH : $info[1];
  						
  						$xPerc = $destW / $info[0] * 100;
  						$yPerc = $destH / $info[1] * 100;
  						
  						//calc smallest ratio, and use that to get other side proportional size
  						if($xPerc >= $yPerc) {
  							$destW = round($info[0] * $yPerc / 100);
  						}else{
  							$destH = round($info[1] * $xPerc / 100);
  						}
  					}else if($destW && !$destH){
  						$destW = $destW < $info[0] ? $destW : $info[0];
  						$perc = $destW / $info[0] * 100;
  						$destH = round($info[1] * $perc / 100);
  					}else if(!$destW && $destH){
  						$destH = $destH < $info[1] ? $destH : $info[1];
  						$perc = $destH / $info[1] * 100;
  						$destW = round($info[0] * $perc / 100);
  					}
  					
  					$destX = 0;
  					$destY = 0;
  					$srcX = 0;
  					$srcY = 0;
  					$srcW = $width;
  					$srcH = $height;
  				}
  				
  				$canvas = imagecreatetruecolor($destW, $destH);
  				
  				//check transparency and allow for it
  				if($info[2] == IMAGETYPE_GIF || $info[2] == IMAGETYPE_PNG) { //copied and adapted off 3rd party script. Thanks random dude :)
  					$trnprt_indx = imagecolortransparent($image);
  					
  					// If we have a specific transparent color
  					if ($trnprt_indx >= 0) {
  						// Get the original image's transparent color's RGB values
  						$trnprt_color = imagecolorsforindex($image, $trnprt_indx);
  						
  						// Allocate the same color in the new image resource
  						$trnprt_indx = imagecolorallocate($canvas, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);
  						
  						// Completely fill the background of the new image with allocated color.
  						imagefill($canvas, 0, 0, $trnprt_indx);
  						
  						// Set the background color for new image to transparent
  						imagecolortransparent($canvas, $trnprt_indx);
  					}elseif ($info[2] == IMAGETYPE_PNG) {	// Always make a transparent background color for PNGs that don't have one allocated already
  						// Turn off transparency blending (temporarily)
  						imagealphablending($canvas, false);
  						
  						// Create a new transparent color for image
  						$color = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
  						
  						// Completely fill the background of the new image with allocated color.
  						imagefill($canvas, 0, 0, $color);
  						
  						// Restore transparency blending
  						imagesavealpha($canvas, true);
  					}
  				}
  				
  				imagecopyresampled($canvas, $image, $destX, $destY, $srcX, $srcY, $destW, $destH, $srcW, $srcH);
  				
  				$args = array($canvas, $newFile);
  				if($quality) {
  					$args[] = $quality;
  				}
  				
  				call_user_func_array($saveas_function, $args);
  				imagedestroy($image);
  				imagedestroy($canvas);
  				
  				$part = explode('/', $newFile);
  				return array_pop($part);
  			}
  		}else{
  			return false;
  		}
  	}
  	
  	private function saveGallery($config) {
  		global $wpdb;
  		
  		if(isset($config['id'])) {
  			
  		}else{
  			//try create the directory to store images in
  			$wpDir = wp_upload_dir();
  			$dirName = '';
  			if($wpDir['basedir']) {
  				$dirName = $wpDir['basedir'].'/shauno-simple-gallery/'.$config['slug'];
  				mkdir($dirName, 0744, true);
  				chmod($dirName, 0744); //all for owner, read only for group and world (TODO, check if 'execute' is needed (making it 0755)
  				
  				if(!is_dir($dirName)) {
  					return false;
  				}
  			}
  			
  			global $current_user;
  			get_currentuserinfo();
  			
  			$qry = 'INSERT INTO '.$wpdb->prefix.'ssg_gallery';
  			$qry .= ' (
  			id,
  			name,
  			slug,
  			description,
  			directory,
  			url,
  			added_by, date_added,
  			edited_by, date_edited,
  			deleted, deleted_by, date_deleted
  			)';
  			
  			$qry .= ' VALUES (
  			null,
  			"'.$wpdb->escape($config['name']).'",
  			"'.$wpdb->escape($config['slug']).'",
  			"'.$wpdb->escape($config['description']).'",
  			"'.$wpdb->escape($dirName).'",
  			"'.$wpDir['baseurl'].'/shauno-simple-gallery/'.$config['slug'].'",
  			"'.$current_user->ID.'", "'.date('Y-m-d H:i:s', time()).'",
  			0, "0000-00-00 00:00:00",
  			0, 0, "0000-00-00 00:00:00"
  			)';
  		}
  		
  		if($wpdb->query($qry)) {
  			return $wpdb->insert_id;
  		}else{
  			return false;
  		}
  	}
  	
  	private function getGalleryList($config=array()) {
  		global $wpdb;
  		
  		$config['fields'] = $config['fields'] ? $config['fields'] : '*';
  		$config['order'] = $config['order'] ? $config['order'] : 'date_added DESC';
  		
  		if(!$config['allow_deleted']) {
  			if($config['where']) {$config['where'] = '('.$config['where'].') AND ';}
  			$config['where'] .= 'deleted = 0';
  		}
  		
  		$qry = 'SELECT '.$config['fields'].' FROM '.$wpdb->prefix.'ssg_gallery';
  		if($config['where']) {$qry .= ' WHERE '.$config['where'];}
  		if($config['order']) {$qry .= ' ORDER BY '.$config['order'];}
  		if($config['group']) {$qry .= ' GROUP BY '.$config['group'];}
  		if($config['limit']) {$qry .= ' LIMIT '.$config['limit'];}
  		
  		if($return = $wpdb->get_results($qry, OBJECT)) {
  			return $return;
  		}else{
  			return array();
  		}
  	}
  	
  	private function getGallery($config) {
  		global $wpdb;
  		
  		if($config['id'] && is_numeric($config['id'])) {
  			$config['where'] = 'id = '.$wpdb->escape($config['id']);
  		}else if($config['slug']) {
  			$config['where'] = 'slug = "'.$wpdb->escape($config['slug']).'"';
  		}else{ //no params that match, bye bye
  			return false;
  		}
  		
  		$config['fields'] = $config['fields'] ? $config['fields'] : '*';
  		
  		if(!$config['allow_deleted']) {
  			if($config['where']) {$config['where'] = '('.$config['where'].') AND ';}
  			$config['where'] .= 'deleted = 0';
  		}
  		
  		$qry = 'SELECT '.$config['fields'].' FROM '.$wpdb->prefix.'ssg_gallery WHERE '.$config['where'];
  		
  		if($return = $wpdb->get_row($qry, OBJECT)) {
  			return $return;
  		}else{
  			return array();
  		}
  	}
  	
  	private function getImageList($config) {
  		if(!is_numeric($config['gallery_id'])) {
  			return false;
  		}
  		
  		global $wpdb;
  		
  		$config['fields'] = $config['fields'] ? $config['fields'] : '*';
  		$config['order'] = $config['order'] ? $config['order'] : 'date_added DESC';
  		
  		if($config['where']) {$config['where'] = '('.$config['where'].') AND ';}
  		$config['where'] .= 'gallery_id = '.$config['gallery_id'];

  		if(!$config['allow_deleted']) {
  			if($config['where']) {$config['where'] = '('.$config['where'].') AND ';}
  			$config['where'] .= 'deleted = 0';
  		}
  		  		
  		$qry = 'SELECT '.$config['fields'].' FROM '.$wpdb->prefix.'ssg_image';
  		if($config['where']) {$qry .= ' WHERE '.$config['where'];}
  		if($config['order']) {$qry .= ' ORDER BY '.$config['order'];}
  		if($config['group']) {$qry .= ' GROUP BY '.$config['group'];}
  		if($config['limit']) {$qry .= ' LIMIT '.$config['limit'];}
  		
  		if($return = $wpdb->get_results($qry, OBJECT)) {
  			return $return;
  		}else{
  			return array();
  		}
  	}
  	
  	public function uploadFile($tmpName, $name, $dir) {
  		if(!is_dir($dir)) {
  			return false;
  		}
  		
  		if(file_exists($dir.'/'.$name)) { //if file exists, add an interger to its name to make it unique again
  			$split = explode('.', $name);
  			$nameFragment = $split[count($split)-2]; //get the actual last bit before the extension
  			$cnt = 1;
  			do {
  				$cnt++;
  				$split[count($split)-2] = $nameFragment.$cnt;
  				
  				$name = implode('.', $split);
  			}while(file_exists($dir.'/'.$name.''));
  		}
  		
  		if(move_uploaded_file($tmpName, $dir.'/'.$name)) {
  			return array('path'=>$dir, 'filename'=>$name);
  		}else{
  			return false;
  		}
  	}
  	
  	public function saveImage($config) {
  		global $wpdb;
  		
  		if(isset($config['id'])) {
  			if(!is_numeric($config['id'])) {
  				return false;
  			}
  			
  			global $current_user;
  			get_currentuserinfo();
  			
  			$qry = 'UPDATE '.$wpdb->prefix.'ssg_image SET ';
  			
  			$fields = '';
  			$config['edited_by'] = $current_user->ID;
  			$config['date_added'] = date('Y-m-d H:i:s', time());
  			foreach ((array)$config as $key=>$val) {
					$fields .= $fields ? ', ' : '';
					$fields .= '`'.$wpdb->escape($key).'` = "'.$wpdb->escape($val).'"';
				}
				
				$qry .= $fields;
				$qry .= ' WHERE id = '.$wpdb->escape($config['id']);
				  			  			
  			if($wpdb->query($qry)) {
  				return true;
  			}else{
  				return false;
  			}
  		}else{
  			if(!is_numeric($config['gallery_id'])) { //need a gallery_id to create an image
  				return false;
  			}
  			
  			if(!$config['filename']) { //also need an actual image :)
  				return false;
  			}
  			
  			global $current_user;
  			get_currentuserinfo();
  			
  			$qry = 'INSERT INTO '.$wpdb->prefix.'ssg_image';
  			$qry .= ' (
  			id,
  			gallery_id,
  			filename,
  			added_by, date_added,
  			edited_by, date_edited,
  			deleted, deleted_by, date_deleted
  			)';
  			
  			$qry .= ' VALUES (
  			null,
  			"'.$wpdb->escape($config['gallery_id']).'",
  			"'.$wpdb->escape($config['filename']).'",
  			"'.$current_user->ID.'", "'.date('Y-m-d H:i:s', time()).'",
  			0, "0000-00-00 00:00:00",
  			0, 0, "0000-00-00 00:00:00"
  			)';
  			
  			if($wpdb->query($qry)) {
  				return $wpdb->insert_id;
  			}else{
  				return false;
  			}
  		}
  		
  	}
  	
  	private function showTemplate($template, $args=array()) {
  		//create the variables that will be available to the template file
  		foreach ((array)$args as $key=>$val) {
				$$key = $val;
			}
			
			ob_start();
			include($this->pluginPath.'gallery-templates/'.$template.'.php');
			$out = ob_get_contents ();
			ob_end_clean ();
			
			return $out;
  	}
  // }
  
  // admin side functions{
  	function adminInits() { //on admin init, include some js before output is given
  		if($_GET['action'] == 'add-images') {
  			//wp_enqueue_script('swfuploader', $this->pluginUrl.'sj/swfupload/swfupload.js', array(), '2.2.0.1', false);
  			//wp_enqueue_script('ssg-add-images', $this->pluginUrl.'js/ssg-addimages.js');
  		}
  	}
  	
  	function adminMenus() {
  		add_menu_page('Simple Gallery', 'Simple Gallery', 'manage_options', 'ss-gallery', array(&$this, 'manageGalleries'));
  		add_submenu_page('ss-gallery', 'Manage Galleries | Simple Gallery', 'Manage Galleries', 'manage_options', 'ss-gallery', array(&$this, 'manageGalleries'));
  		add_submenu_page('ss-gallery', 'Add Gallery | Simple Gallery', 'Add Gallery', 'manage_options', 'ss-gallery-add', array(&$this, 'addGallery'));
  	}
  	
  	function manageGalleries() {
  		if(is_numeric($_GET['ssg_id'])) {
  			switch($_GET['action']) {
  			default: $this->viewGallery($_GET['ssg_id']); break;
  			}
  			return;
  		}
  		
  		$list = $this->getGalleryList();
  		?>
  		<div class="wrap">
  			<h2>
  				Manage Galleries
  				<a class="button add-new-h2" href="<?php echo $this->adminUrl; ?>page=ss-gallery-add">Add New</a>
  			</h2>
  			
  			<?php if($_GET['update']) { ?>
  				<div class="updated" id="message">
  					<?php if($_GET['update'] == 'new') { ?>
  						<p>New gallery created.</p>
  					<?php } ?>
  				</div>
  			<?php } ?>

  			
  			<table cellspacing="0" class="wp-list-table widefat fixed ss-galleries">
  				<thead>
  					<tr>
  						<th style="width:30px;">id</th>
  						<th>Name</th>
  						<th>Description</th>
  						<!--<th>Image Count</th>-->
  					</tr>
  				</thead>
  				
  				<tbody>
  					<?php if($list) { ?>
  						<?php $cnt = 0; ?>
  						<?php foreach ($list as $key=>$val) { ?>
								<tr id="gallery-<?php echo $val->id; ?>" <?php echo $cnt % 2 == 0 ? 'class="alternate"' : '' ?>>
									<td><?php echo $val->id ?></td>
									<td><a href="<?php echo $this->adminUrl; ?>page=ss-gallery&ssg_id=<?php echo $val->id; ?>"><?php echo $val->name ?></a></td>
									<td><?php echo nl2br($val->description); ?></td>
									<!--<td>n</td>-->
								</tr>
								<?php $cnt++; ?>
							<?php } ?>
  					<?php }else{ ?>
  						<tr>
  							<td colspan="4">No galleries found. <a href="<?php echo $this->adminUrl; ?>page=ss-gallery-add">Click here</a> to add your first gallery.</td>
  						</tr>
  					<?php } ?>
  				</tbody>
  			</table>

  		</div> <!-- /.wrap -->
  		<?php
  	}
  	
  	function addGallery() {
  		if($_POST['ssg']) {
  			$err = array();
  			
  			if(!trim($_POST['ssg']['name'])) {
  				$err['name'] = 'Please enter a name for the gallery';
  			}
  			
  			if(!self::validSlug($_POST['ssg']['slug'])) {
  				$err['slug'] = 'Please enter a slug using only a-z, 0-9, underscores and hyphens';
  			}else if($sExists = $this->getGallery(array('slug'=>$_POST['ssg']['slug']))){
  				$err['slug-exist'] = 'The slug <strong>'.$_POST['ssg']['slug'].'</strong> exists already';
  			}
  			
  			if(!$err) { //if error, better get the header in place
  				if($id = $this->saveGallery($_POST['ssg'])) {
  					wp_redirect(bloginfo('url').'/wp-admin/admin.php?page=ss-gallery&update=new#gallery-'.$id);
  				}else{
  					$err['save-failed'] = 'There was an error saving to the datebase!';
  				}
  			}
  			
  			if (isset($_GET['noheader'])) {require_once(ABSPATH.'wp-admin/admin-header.php');} //replace the header if we need output...

  		}
  		?>
  		<div class="wrap">
  			<h2>Add New Gallery</h2>
  			
  			<?php if($err) { ?>
  				<div class="error">
  					<?php foreach ((array)$err as $key=>$val) { ?>
							<p><strong>ERROR</strong>: <?php echo $val; ?></p>
						<?php } ?>
  				</div>
  			<?php } ?>
  			
  			<p>Add a new gallery by filling in the form below</p>
  			
  			<form class="add:ssgallery" id="create_ssgallery" name="create_ssgallery" method="post" action="<?php echo $this->adminUrl; ?>page=ss-gallery-add&noheader=true">
  				<table class="form-table">
  				<tbody>
  					<tr class="form-field">
  						<th scope="row"><label for="user_login">Gallery Name <span class="description">(required)</span></label></th>
  						<td><input type="text" id="ssg_name" name="ssg[name]" value="<?php echo htmlspecialchars($_POST['ssg']['name']); ?>" /></td>
  					</tr>
  					
  					<tr class="form-field">
  						<th scope="row"><label for="user_login">Slug <span class="description">(required)</span></label></th>
  						<td><input type="text" id="ssg_slug" name="ssg[slug]" value="<?php echo htmlspecialchars($_POST['ssg']['slug']); ?>" /><br />
  						<span class="description">Use a-z, 0-9, underscores and hyphens</span></td>
  					</tr>
  					
  					<tr class="form-field">
  						<th scope="row"><label for="user_login">Description</label></th>
  						<td><textarea id="ssg_description" name="ssg[description]"><?php echo htmlspecialchars($_POST['ssg']['description']); ?></textarea>
  					</tr>
  					
  				</tbody>
  				</table>
  				
  				<p class="submit"><input type="submit" value="Add New Gallery " class="button-primary" id="addnewgallery" name="addnewgallery"></p>
  			</form>
  		</div> <!-- /.wrap -->
  		<?php
  	}
  	
  	function viewGallery($gid) {
  		$gallery = $this->getGallery(array('id'=>$gid));
  		
  		if($_POST['ssg']['new-image']) {
  			$uploadErr = false;
  			if($_FILES['ssg']['tmp_name']['image']) {
  				$file = $this->uploadFile($_FILES['ssg']['tmp_name']['image'], $_FILES['ssg']['name']['image'], $gallery->directory);
  				if($file['filename']) {
  					$id = $this->saveImage(array('gallery_id'=>$gallery->id, 'filename'=>$file['filename']));
  					wp_redirect(bloginfo('url').'/wp-admin/admin.php?page=ss-gallery&ssg_id='.$gallery->id.'&update=new#image-'.$id);
  				}else{
  					$uploadErr = 'File upload failed. Please try again in a moment.';
  				}
  			}else{
  				switch($_FILES['ssg']['error']['image']) {
  				case UPLOAD_ERR_INI_SIZE:		$uploadErr = 'The file exceeded the <strong>upload_max_filesize</strong> in your PHP setup.';							break;
  				case UPLOAD_ERR_NO_FILE:		$uploadErr = 'Please click the "Browse" button to select a file before clicking the "Upload" button.';		break;
  				default:										$uploadErr = 'There was an error uploading the file, please try again in a moment.';											break;
  				}
  			}
  			
  			if (isset($_GET['noheader'])) {require_once(ABSPATH.'wp-admin/admin-header.php');} //replace the header if we need output...
  		}else if($_POST['ssg_images']) {
  			foreach ((array)$_POST['ssg_images'] as $id=>$val) {
					$this->saveImage(array('id'=>$id, 'alt'=>$val['alt'], 'caption'=>$val['caption']));
				}
  		}
  		
  		$images = $this->getImageList(array('gallery_id'=>$gallery->id));
			?>
			<div class="wrap">
				<h2>Manage Gallery: <?php echo $gallery->name; ?></h2>
  	  	
				<div id="poststuff">
					<div class="postbox">
						<h3>Gallery settings</h3>
							<table class="form-table">
								<tbody>
									<tr>
										<th style="width:20%">Name:</th>
										<td style="width:30%"><?php echo $gallery->name ?></td>
										<th style="width:20%">Slug:</th>
										<td style="width:30%"><?php echo $gallery->slug ?></td>
									</tr>
									<tr>
										<th>Description:</th>
										<td><?php echo nl2br($gallery->description); ?></td>
										<th>Created By:</th>
										<?php $u = get_userdata($gallery->added_by); ?>
										<td valign="top"><?php echo $u->user_login; ?></td>
									</tr>
								</tbody>
							</table>
					</div>
				</div> <!-- /.#poststuff -->
				
				<?php if($uploadErr) { ?>
					<div class="error">
						<p><strong>ERROR</strong>: <?php echo $uploadErr; ?></p>
  				</div>
				<?php } ?>
				<form method="post" action="<?php echo $this->adminUrl; ?>page=ss-gallery&ssg_id=<?php echo $gallery->id; ?>&noheader=true" enctype="multipart/form-data">
					<input type="hidden" name="ssg[new-image]" value="1" />
					<label for="user_login">Add Image: </th>
					<input type="file" name="ssg[image]" />
					&nbsp;&nbsp;
					<input type="submit" value="Upload" />
				</form>
				<br /><br />

				<form method="post" action="<?php echo $this->adminUrl; ?>page=ss-gallery&ssg_id=<?php echo $gallery->id; ?>">
					<table cellspacing="0" class="wp-list-table widefat fixed ss-gallery-images">
  					<thead>
  						<tr>
  							<th style="width:30px;">id</th>
  							<th style="width:180px;">Thumbnail (cropped 4x3)</th>
  							<th>Filename</th>
  							<th>Alt Text and Caption</td>
  						</tr>
  					</thead>
  					
  					<tbody>
  						<?php if($images) { ?>
  							<?php $cnt = 0; ?>
  							<?php foreach ($images as $key=>$val) { ?>
									<tr id="image-<?php echo $val->id; ?>" <?php echo $cnt % 2 == 0 ? 'class="alternate"' : '' ?>>
										<td><?php echo $val->id ?></td>
										<td><img src="<?php echo $gallery->url.'/'.$this->thumb(array('src'=>$gallery->directory.'/'.$val->filename, 'width'=>160, 'height'=>120, 'crop'=>true)) ?>" /></td>
										<td><?php echo $val->filename; ?></td>
										<td valign="top">
											<input type="text" name="ssg_images[<?php echo $val->id ?>][alt]" style="width:250px;" value="<?php echo htmlspecialchars($val->alt); ?>"/><br />
											<textarea name="ssg_images[<?php echo $val->id ?>][caption]" style="width:250px;" rows="4"><?php echo htmlspecialchars($val->caption); ?></textarea>
										</td>
									</tr>
									<?php $cnt++; ?>
								<?php } ?>
  						<?php }else{ ?>
  							<tr>
  							<td colspan="2">No images found. Click the "Browse" button above to find the image you want on your computer, and click "Upload" to add to this gallery.</td>
  							</tr>
  						<?php } ?>
  					</tbody>
  				</table>
  				
  				<input type="submit" class="button-primary" value="Save Images" />
  			</form>

			</div> <!-- /.wrap -->
			<?php
  	}

	// }
	
	// front end functions {
		function code_ssgallery($config=array()) {
			if(!$config['template']) {
				$config['template'] = 'default';
			}
			
			$out = '';
			
			if(!file_exists($this->pluginPath.'gallery-templates/'.$config['template'].'.php')) { //make sure the template exists
				$out .= 'Gallery template <strong>'.$config['template'].'</strong> not found.';
				return $out;
			}
			
			$gallery = $this->getGallery(array('id'=>$config['id']));
			$images = $this->getImageList(array('gallery_id'=>$gallery->id));
			
			$out .= $this->showTemplate($config['template'], array('gallery'=>$gallery, 'images'=>$images));
			
			return $out;
		}
	// }
}

$ss_gallery = new shaunoSimpleGallery();
?>