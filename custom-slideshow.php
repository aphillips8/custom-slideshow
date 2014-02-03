<?php
/*
Plugin Name: Custom Slideshow
Description: Organises and displays slideshows.
Author: Anna Phillips
Author URI: http://annaphillips.co.nz/

Image uploading code based on WP-Cycle (http://wordpress.org/plugins/wp-cycle/) from Nathan Rice
*/

// Prevent people from loading the plugin directly
if(preg_match("#" . basename(__FILE__) . "#", $_SERVER["PHP_SELF"])) {
	die("You are not allowed to call this page directly.");
}

// Set plugin directory
define("CUSTOM_SLIDESHOW_IMAGES_TABLE", $wpdb->prefix . "custom_slideshow_images");

// Creates tables etc when plugin is activated
register_activation_hook(__FILE__, "custom_slideshow_table");

function custom_slideshow_table() {
	global $wpdb;
	
	$table_name = $wpdb->prefix . "custom_slideshow_images";

	if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
		
		$sql = "CREATE TABLE " . $table_name . " (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			file text NOT NULL,
			file_url text NOT NULL,
			thumb text NOT NULL,
			thumb_url text NOT NULL,
			file_description text NOT NULL,
			page_id mediumint(9) DEFAULT '1' NOT NULL,
			sort_order mediumint(9) DEFAULT '1' NOT NULL,
			UNIQUE KEY id (id)
		);";	
		
		require_once(ABSPATH . "wp-admin/includes/upgrade.php");
		dbDelta($sql);
	}

}

add_action("admin_init", "custom_slideshow_admin_init");
add_action("admin_menu", "custom_slideshow_admin_menu");

function custom_slideshow_admin_init() {
	// Custom Images admin stylesheet
	wp_register_style("custom-slideshow-admin-styles", plugins_url("css/custom-slideshow-admin.css", __FILE__));
	
	// Include validation for admin input
	wp_register_script("custom-slideshow-admin-form-validation", plugins_url("js/custom-slideshow-admin-form-validation.js", __FILE__));
	
	// Include sortable scripts
	wp_register_script("custom-slideshow-sortable", plugins_url("js/custom-slideshow-sortable.js", __FILE__), array("jquery", "jquery-ui-core", "jquery-ui-sortable"));
}

function custom_slideshow_admin_menu() {	
	// Page title, Menu title, Capability, Menu Slug, Function, Icon URL
	$page = add_menu_page("Slideshows", "Slideshows", "upload_files", "custom-slideshow-images", "custom_slideshow_images_admin_page", plugins_url("/images/icon.png", __FILE__));

	// Use registered $page handle to hook stylesheets
	add_action("admin_print_styles-" . $page, "custom_slideshow_admin_styles");
	
	// Use registered $page handle to hook scripts
	add_action("admin_print_scripts-" . $page, "custom_slideshow_admin_scripts");
}

// Enqueue plugin stylesheets
function custom_slideshow_admin_styles() {
	wp_enqueue_style("custom-slideshow-admin-styles");
}

// Enqueue plugin scripts
function custom_slideshow_admin_scripts() {
	wp_enqueue_script("custom-slideshow-admin-form-validation");
	wp_enqueue_script("custom-slideshow-sortable");
}

// AJAX Sortable scripts
add_action("wp_ajax_custom_slideshow_sortable_images_save", "custom_slideshow_sortable_images_save");

function custom_slideshow_sortable_images_save() {
	global $wpdb;
	
	$sortable_order_array = $_POST['custom_slideshow_sortable_images_order_data'];
	$sort_order_value = 1;
	
	foreach($sortable_order_array as $sortable_order) {
		echo $sortable_order;
		$wpdb->get_results("UPDATE `" . CUSTOM_SLIDESHOW_IMAGES_TABLE . "` SET `sort_order` = '". mysql_real_escape_string($sort_order_value) . "' WHERE `id` = " . mysql_real_escape_string($sortable_order));
		$sort_order_value++;
	}
	die();
}

function custom_slideshow_convert_pages_to_array() {
	global $wpdb;
	// Get page IDs, add into array
	$custom_slideshow_pages = get_pages(array('sort_column' => 'menu_order'));
	$custom_slideshow_services = get_posts(array('numberposts' => -1, 'post_type' => 'services', 'orderby' => 'menu_order', 'order' => 'ASC'));
	$page_ids = array();
	$page_titles = array();
	$page_titles_hierarchical = array();
	
	if(!empty($custom_slideshow_pages)) {	
		foreach($custom_slideshow_pages as $custom_slideshow_page) {
			array_push($page_ids, $custom_slideshow_page->ID);
			array_push($page_titles, stripslashes($custom_slideshow_page->post_title));
			
			// Add additional hierarchical array to show when pages have a parent
			if(!empty($custom_slideshow_page->post_parent)) {
				array_push($page_titles_hierarchical, "&ndash; " . stripslashes($custom_slideshow_page->post_title));
			} else {
				array_push($page_titles_hierarchical, stripslashes($custom_slideshow_page->post_title));
			}
			
			// Loop through the individual Services if the Services page was just output
			if($custom_slideshow_page->ID == 7) {
				if(!empty($custom_slideshow_services)) {	
					foreach($custom_slideshow_services as $custom_slideshow_service) {
						array_push($page_ids, $custom_slideshow_service->ID);
						array_push($page_titles, stripslashes($custom_slideshow_service->post_title));
						
						// All pages are sub-pages, so include the dash
						array_push($page_titles_hierarchical, "&ndash; " . stripslashes($custom_slideshow_service->post_title));
						
						$category_projects = new WP_Query(array('posts_per_page' => -1, 
							'tax_query' => array(
								array(
									'taxonomy' => 'services-provided',
									'field' => 'slug',
									'terms' => $custom_slideshow_service->post_name
								)
							)
						));
						
						if($category_projects->have_posts()) while($category_projects->have_posts()) { $category_projects->the_post();

							array_push($page_ids, get_the_ID());
							array_push($page_titles, get_the_title());
							
							// All pages are sub-pages, so include the dash
							array_push($page_titles_hierarchical, "&nbsp;&nbsp;&nbsp;&ndash; " . get_the_title());
					
						} wp_reset_postdata(); // End if projects
					}
				}
			}
		}
	}

	// Add inactive category
	array_push($page_ids, 0);
	array_push($page_titles, "Inactive");
	array_push($page_titles_hierarchical, "Inactive");
	
	return array('page_ids' => $page_ids, 'page_titles' => $page_titles, 'page_titles_hierarchical' => $page_titles_hierarchical);
}

function custom_slideshow_images_admin_page() {
	global $wpdb;
	
	echo '<div class="wrap">';
	
	// Check user permissions before showing options page
	if(!current_user_can('upload_files')) {
		wp_die('You do not have sufficient permissions to access this page.');
	}
	
	// Handle image upload and category addition, if necessary
	if(isset($_REQUEST['action'])) {
		if($_REQUEST['action'] == 'wp_handle_upload') {
			custom_slideshow_handle_upload();
		} elseif($_REQUEST['action'] == 'update_custom_slideshow_images') {
			update_custom_slideshow_images();
		}
	}
	
	// Copy an image, if necessary
	if(isset($_REQUEST['copy'])) {
		copy_custom_slideshow_image($_REQUEST['copy']);
	}
	
	// Delete an image, if necessary
	if(isset($_REQUEST['delete'])) {
		delete_custom_slideshow_image($_REQUEST['delete']);
	}
	
	// The image management form
	custom_slideshow_images_admin();

	echo '</div>';
}

function custom_slideshow_handle_upload() {
	global $wpdb;
	
	$min_width = 583;
	$min_height = 381;
	$thumb_width = 394;
	$thumb_height = 258;
	
	// Upload the image
	$upload = wp_handle_upload($_FILES['slideshow_image'], 0);
	
	$file_description = htmlentities($_POST["file_description"], ENT_QUOTES, "UTF-8", false);
	$page_id = $_POST["dest_page"];
	
	// Extract the $upload array
	extract($upload);
	
	// The URL of the directory the file was loaded in
	$upload_dir_url = str_replace(basename($file), '', $url);
	
	// Get the image dimensions
	if(file_exists($file)) {
		list($width, $height) = getimagesize($file);
	}
	
	// If the uploaded file is NOT an image
	if(strpos($type, 'image') === FALSE) {
		if(file_exists($file)) {
			unlink($file); // delete the file
		}
		echo '<div class="error" id="message"><p>Sorry, but the file you uploaded does not seem to be a valid image. Please try again.</p></div>';
		return;
	}

	// If the image doesn't meet the minimum width/height requirements
	if($width < $min_width || $height < $min_height) {
		if(file_exists($file)) {
			unlink($file); // delete the image
		}
		echo '<div class="error" id="message"><p>Sorry, but this image does not meet the minimum width/height requirements of ' . $min_width . 'px wide and ' . $min_height . 'px high. Please upload another image.</p></div>';
		return;
	}

	// If the image is larger than the width/height requirements, then scale it down.
	if($width > $min_width || $height > $min_height) {

		// Resize the image
		// (Filepath, Max-Width, Max-Height, Crop, Suffix, Destination File Path, Quality)
		$resized = image_resize($file, $min_width, $min_height, true);
		
		$resized_url = $upload_dir_url . basename($resized);
		
		$file = $resized;
		$url = $resized_url;

	}
	
	if(isset($upload['file'])) {
		$orig_file = $upload['file'];
		
		// Create the thumbnail image
		// (Filepath, Max-Width, Max-Height, Crop, Suffix, Destination File Path, Quality)
		$resized = image_resize($orig_file, $thumb_width, $thumb_height, true);
		
		$resized_url = $upload_dir_url . basename($resized);
		
		$thumb_file = $resized;
		$thumb_url = $resized_url;

	} else {
		$thumb_file = "";
		$thumb_url = "";
	}
	
	// If the file has been resized, delete the original
	if($width > $min_width || $height > $min_height) {
		// Delete the image
		if(file_exists($orig_file)) {
			unlink($orig_file);
		}
	}
	
	$sort_order = $wpdb->get_var("SELECT `sort_order` FROM `" . CUSTOM_SLIDESHOW_IMAGES_TABLE . "` WHERE `page_id` = '" . mysql_real_escape_string($page_id) . "' ORDER BY `sort_order` DESC") + 1;	
	$wpdb->get_results("INSERT INTO `" . CUSTOM_SLIDESHOW_IMAGES_TABLE . "` SET `file` = '" . mysql_real_escape_string($file) . "', `file_url` = '" . mysql_real_escape_string($url) . "', `thumb` = '" . mysql_real_escape_string($thumb_file) . "', `thumb_url` = '" . mysql_real_escape_string($thumb_url) . "', `file_description` = '" . mysql_real_escape_string($file_description) . "', `page_id` = '" . mysql_real_escape_string($page_id) . "', `sort_order` = '" . mysql_real_escape_string($sort_order) . "'");
	
	custom_slideshow_notification_message("image_added");	
}

function update_custom_slideshow_images() {
	global $wpdb;
	$current_page_id = $_POST["current_page_id"];
	$custom_slideshow_image_ids = $wpdb->get_col("SELECT `id` FROM `" . CUSTOM_SLIDESHOW_IMAGES_TABLE . "` WHERE `page_id` = '" . mysql_real_escape_string($current_page_id) . "'");
	
	for($i = 0; $i < count($custom_slideshow_image_ids); $i++) {
		if(isset($_POST['dest_page_' . $custom_slideshow_image_ids[$i]])) {
			$custom_slideshow_file_description = htmlentities($_POST['file_description_' . $custom_slideshow_image_ids[$i]], ENT_QUOTES, "UTF-8", false);
			$custom_slideshow_page_id = htmlentities($_POST['dest_page_' . $custom_slideshow_image_ids[$i]], ENT_QUOTES, "UTF-8", false);
			
			$wpdb->get_results("UPDATE `" . CUSTOM_SLIDESHOW_IMAGES_TABLE . "` SET `file_description` = '" . mysql_real_escape_string($custom_slideshow_file_description) . "', `page_id` = '". mysql_real_escape_string($custom_slideshow_page_id) . "' WHERE `id` = " . $custom_slideshow_image_ids[$i]);
		}
	}
	
	custom_slideshow_notification_message("image_updated");
}

function copy_custom_slideshow_image($id) {
	global $wpdb;
	
	$row_to_copy = $wpdb->get_row("SELECT * FROM `" . CUSTOM_SLIDESHOW_IMAGES_TABLE . "` WHERE `id` = " . mysql_real_escape_string($id));
	
	if($row_to_copy) {
		$sort_order = $wpdb->get_var("SELECT `sort_order` FROM `" . CUSTOM_SLIDESHOW_IMAGES_TABLE . "` WHERE `page_id` = '0' ORDER BY `sort_order` DESC") + 1;	
		$wpdb->get_results("INSERT INTO `" . CUSTOM_SLIDESHOW_IMAGES_TABLE . "` SET `file` = '" . mysql_real_escape_string($row_to_copy->file) . "', `file_url` = '" . mysql_real_escape_string($row_to_copy->file_url) . "', `thumb` = '" . mysql_real_escape_string($row_to_copy->thumb) . "', `thumb_url` = '" . mysql_real_escape_string($row_to_copy->thumb_url) . "', `file_description` = '" . mysql_real_escape_string($row_to_copy->file_description) . "', `page_id` = '0', `sort_order` = '" . mysql_real_escape_string($sort_order) . "'");
		
		custom_slideshow_notification_message("image_copied");
	}
}

function delete_custom_slideshow_image($id) {
	global $wpdb;
	
	$row_to_delete = $wpdb->get_row("SELECT `file`, `thumb` FROM `" . CUSTOM_SLIDESHOW_IMAGES_TABLE . "` WHERE `id` = " . mysql_real_escape_string($id));
	
	if($row_to_delete) {
		$image_to_delete = $row_to_delete->file;
		$thumb_to_delete = $row_to_delete->thumb;
		
		$affected_rows = $wpdb->get_results("SELECT `file` FROM `" . CUSTOM_SLIDESHOW_IMAGES_TABLE . "` WHERE `file` = '" . mysql_real_escape_string($row_to_delete->file) . "'");
		
		// If the image file is only being used once, then delete it (if the image wasn't copied)
		if(count($affected_rows) == 1) {
			// Delete the image
			if(file_exists($image_to_delete)) {
				unlink($image_to_delete);
			}
			
			// Delete the thumbnail
			if(file_exists($thumb_to_delete)) {
				unlink($thumb_to_delete);
			}
		}
		
		// Remove the image data from the db
		$wpdb->get_results("DELETE FROM `" . CUSTOM_SLIDESHOW_IMAGES_TABLE . "` WHERE `id` = " . mysql_real_escape_string($id));
	
		custom_slideshow_notification_message("image_deleted");
	}
}

// Display notifications
function custom_slideshow_notification_message($notification_to_show) {
	echo '<div class="updated fade" id="message"><p>';
	if($notification_to_show == "image_added") {
		echo "Image added successfully.";
	} elseif($notification_to_show == "image_updated") {
		echo "Image updated successfully.";
	} elseif($notification_to_show == "image_copied") {
		echo "Image duplicated successfully. The new image can be found under the &lsquo;Inactive&rsquo; category.";
	} elseif($notification_to_show == "image_deleted") {
		echo "Image deleted successfully.";
	}
	echo '</p></div>';
}

// Display the images administration code
function custom_slideshow_images_admin() {
	global $wpdb;
	$pages_array = custom_slideshow_convert_pages_to_array();
	$page_ids = $pages_array['page_ids'];
	$page_titles = $pages_array['page_titles'];
	$page_titles_hierarchical = $pages_array['page_titles_hierarchical'];
?>
	<h2>Add a New Image</h2>
	<form enctype="multipart/form-data" method="post" action="?page=custom-slideshow-images" onsubmit="return add_new_custom_slideshow_image_validate();">	
	<table class="form-table">
		<tr>
			<th scope="row">
				<input type="hidden" name="action" value="wp_handle_upload" />
				<label for="slideshow_image">Image Location</label>
			</th>
			<td>
				<input type="file" name="slideshow_image" id="slideshow_image" />
				<p class="description">Images will be resized to fit automatically. Minimum size is 583px wide by 381px high.</p>
			</td>
		</tr>
		<tr>
			<th scope="row">Description</th>
			<td>
				<input type="text" name="file_description" id="file_description" value="" class="custom-long-text" />
				<p class="description">A brief description of the image &ndash; not usually shown to the public, but it is required for visually impaired users.</p>
			</td>
		</tr>
		<tr>
			<th scope="row">Page</th>
			<td>
				<select name="dest_page" id="dest_page">
					<option value=""></option>
					<?php
					// Loop through all pages, get title and ID while pages exist
					for ($i = 0; $i < count($page_ids); $i++) {
					?>
					<option value="<?php echo $page_ids[$i]; ?>"><?php echo stripslashes($page_titles_hierarchical[$i]); ?></option>
					<?php } ?>
				</select>
				<p class="description">Where the image will be displayed. Choose &lsquo;inactive&rsquo; if you don&rsquo;t want the image to be displayed yet.</p>
			</td>
		</tr>
	</table>
	<p class="submit">
	<input type="submit" class="button-primary" name="html-upload" value="Add Image" />
	</p>
	</form>
	<p><strong>Hint:</strong> Drag and drop the arrow icon in the &lsquo;order&rsquo; column to rearrange the images.</p>
	
	
	<?php
	if(!empty($page_ids)) {
	?>
		<?php
		for ($i = 0; $i < count($page_ids); $i++) {
			$custom_slideshow_images = $wpdb->get_results("SELECT * FROM `" . CUSTOM_SLIDESHOW_IMAGES_TABLE . "` WHERE `page_id` = '". $page_ids[$i] . "' ORDER BY `sort_order` ASC");

			if(!empty($custom_slideshow_images)) {
			?>
	<h2><?php echo stripslashes($page_titles[$i]); ?></h2>
	<form method="post" action="?page=custom-slideshow-images">
	<input type="hidden" name="action" value="update_custom_slideshow_images" />
	<input type="hidden" name="current_page_id" value="<?php echo $page_ids[$i]; ?>" />
	<table class="widefat" cellspacing="0">
		<thead>
			<tr>
				<th scope="col" class="custom-width-order">Order</th>
				<th scope="col" class="custom-slideshow-image-custom-width">Image</th>
				<th scope="col">Description</th>
				<th scope="col">Page</th>
				<th scope="col" class="custom-width-buttons"></th>
			</tr>
		</thead>
		
		<tfoot>
			<tr>
				<th scope="col" class="custom-width-order">Order</th>
				<th scope="col" class="custom-slideshow-image-custom-width">Image</th>
				<th scope="col">Description</th>
				<th scope="col">Page</th>
				<th scope="col" class="custom-width-buttons"></th>
			</tr>
		</tfoot>
		
		<tbody class="images-sortable">
		<?php
		foreach($custom_slideshow_images as $custom_slideshow_image) {
		?>
			<tr>
				<td class="drag custom-width-order"><input type="hidden" name="sort_order" value="<?php echo $custom_slideshow_image->id;  ?>" class="custom-slideshow-image-id" /></td>
				<td class="custom-slideshow-image-custom-width"><img src="<?php echo $custom_slideshow_image->thumb_url ; ?>" width="220" height="144" alt="<?php echo stripslashes($custom_slideshow_image->file_description); ?>" /></td>
				<td><input type="text" name="file_description_<?php echo $custom_slideshow_image->id; ?>" value="<?php echo stripslashes($custom_slideshow_image->file_description); ?>" class="custom-full-width" /></td>
				<td>
				<select name="dest_page_<?php echo $custom_slideshow_image->id; ?>">
					<?php
					// Loop through all pages, get title and ID while pages exist
					for ($loop_i = 0; $loop_i < count($page_ids); $loop_i++) {
					?>
					<option value="<?php echo $page_ids[$loop_i]; ?>"<?php if($page_ids[$loop_i] == $custom_slideshow_image->page_id) { ?> selected="selected"<?php }?>><?php echo stripslashes($page_titles_hierarchical[$loop_i]); ?></option>
					<?php } ?>
				</select>
				</td>
				<td class="custom-width-buttons"><input type="submit" class="button-primary" value="Save Changes" /> <a href="?page=custom-slideshow-images&amp;copy=<?php echo $custom_slideshow_image->id; ?>" class="button custom-copy-button" title="Duplicate image">+</a> <a href="?page=custom-slideshow-images&amp;delete=<?php echo $custom_slideshow_image->id; ?>" class="button" onclick="return confirm('Are you sure you want to delete this image?')">Delete</a></td>
			</tr>
		<?php
		} // End slideshow images loop
		?>
		</tbody>
	</table>
	</form>
	<br />
			<?php
			// Don't show pages with no images except for the inactive category
			} elseif($page_ids[$i] == 0) { ?>
				<h2><?php echo stripslashes($page_titles[$i]); ?></h2>
				<p>There are no inactive slideshow images.</p>
				<br />
			<?php }
		}
	?>
	<?php
	// If there's no pages
	} else { ?>
		<h2>Slideshow Images</h2>
		<p>There are no pages for the slideshow images.</p>
		<br />
<?php }
}

// Front-end code
function display_custom_slideshow($page_id, $large_image = false, $project_url = "") {
	global $wpdb;
	$custom_slideshow_images = $wpdb->get_results("SELECT `file_url`, `thumb_url`, `file_description` FROM `" . CUSTOM_SLIDESHOW_IMAGES_TABLE . "` WHERE `page_id` = '". $page_id . "' ORDER BY `sort_order` ASC");
	
	// Only display the slideshow if there's an image
	if(!empty($custom_slideshow_images)) {
?>
				<figure class="flexslider">
					<ul class="slides">
<?php
		foreach($custom_slideshow_images as $custom_slideshow_image) {
			if($large_image) {
				$img_src = $custom_slideshow_image->file_url;
			} else {
				$img_src = $custom_slideshow_image->thumb_url;
			}
?>
						<li><?php if($project_url != "") { ?><a href="<?php echo $project_url; ?>" title="<?php the_title(); ?> website (opens in new window)" target="_blank"><?php } ?><img src="<?php echo $img_src; ?>" alt="<?php echo stripslashes($custom_slideshow_image->file_description); ?>" /><?php if($project_url != "") { ?></a><?php } ?></li>
<?php } // End foreach ?>
					</ul>
				</figure>
<?php
	}
}
?>