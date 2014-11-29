<?php
/*
Plugin Name: Auto Upload Images
Plugin URI: http://p30design.net/1391/08/wp-auto-upload-images.html
Description: Automatically upload external images of a post to Wordpress upload directory
Version: 1.6
Author: Ali Irani
Author URI: http://p30design.net
Text Domain: auto-upload-images
License: GPLv2 or later
*/

class WP_Auto_Upload {

	public $base_url;
	public $options;

	public function __construct() {
		$defaults['base_url'] = get_bloginfo('url');
		$defaults['image_name'] = '%filename%';
		$this->options = get_option('aui-setting', $defaults);
		$this->base_url = $this->get_base_url($this->options['base_url']);

		$this->options = wp_parse_args($this->options, $defaults);

		add_action('plugins_loaded', array($this, 'init'));
		add_action('save_post', array($this, 'auto_upload'));
		add_action('admin_menu', array($this, 'admin_menu'));
	}
	/**
	 * Automatically upload external images of a post to Wordpress upload directory
	 *
	 * @param int $post_id
	 */
	public function auto_upload( $post_id ) {

		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) 
			return;
			
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) 
	        return;
		
		if ( false !== wp_is_post_revision($post_id) )
			return;
			
		global $wpdb;

		$content = $wpdb->get_var("SELECT `post_content` FROM {$wpdb->posts} WHERE ID='$post_id'");

		$image_urls = $this->get_image_urls($content);
		
		if ($image_urls) {

			foreach ($image_urls as $image_url) {

				if ($this->is_allowable_url($image_url)) {

					if ($new_image_url = $this->save_image($image_url, $post_id)) { // save image and return new url
						// find image url in content and replace new image url
						$new_image_url = parse_url($new_image_url);
						$base_url = $this->base_url == null ? null : "http://{$this->base_url}";
						$new_image_url = $base_url . $new_image_url['path'];
						$content = preg_replace('/'. preg_quote($image_url, '/') .'/', $new_image_url, $content);
					}
				}
			}
			
			$wpdb->update(
				$wpdb->posts,
				array( 'post_content' => $content ),
				array( 'ID' => $post_id )
			);

		}

	}
	/**
	 * Save image on wp_upload_dir
	 * Add image to the media library and attach in the post
	 *
	 * @param string $url image url
	 * @param int $post_id
	 * @return string new image url
	 */
	public function save_image($url, $post_id = 0) {

        if (!function_exists('curl_init')) {
            return;
        }
		
        $ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);     
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); 
		$image_data = curl_exec($ch);

        if ($image_data === false) {
            return;
        }

        $image_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $image_size = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
        $image_file_name = basename($url);
        $image_name = $this->get_image_custom_name($image_file_name);
        $upload_dir = wp_upload_dir(date('Y/m'));
        $image_path = $upload_dir['path'] . '/' . $image_name;
        $image_url = $upload_dir['url'] . '/' . rawurlencode($image_name);

        $i = 0;
        
        // check if file with same name exists in upload path, rename file
        while (file_exists($image_path)) {
            if ($image_size == filesize($image_path)) {
                return $image_url;
            } else {
                $i++;
                $image_path = $upload_dir['path'] . '/' . $i . '_' . $image_name; 
                $image_url = $upload_dir['url'] . '/' . $i . '_' . $image_name;
            }
        }
        
		curl_close($ch);
		file_put_contents($image_path, $image_data);
		
		$attachment = array(
			'guid' => $image_url, 
			'post_mime_type' => $image_type,
			'post_title' => preg_replace('/\.[^.]+$/', '', $image_name),
			'post_content' => '',
			'post_status' => 'inherit'
		);
		$attach_id = wp_insert_attachment($attachment, $image_path, $post_id);
		$attach_data = wp_generate_attachment_metadata($attach_id, $image_path);
		wp_update_attachment_metadata($attach_id, $attach_data);

		return $image_url;
	}

	/**
	 * find image urls in content and retrive urls by array
	 *
	 * @param $content
	 * @return array
	 */
	public function get_image_urls($content) {
        $pattern = '/<img[^>]*src=("|\')([^(\?|#|"|\')]*)(\?|#)?[^("|\')]*("|\')[^>]*\/?>/';
		preg_match_all($pattern, $content, $urls, PREG_SET_ORDER);
		
		if ($urls) {
			foreach ($urls as $url) {
				$image_urls[] = $url[2];
            }

            if ($image_urls) {
                $image_urls = array_unique($image_urls);
                rsort($image_urls);
    			return $image_urls;
            }
        }

        return false;
	}

	/**
	 * Return custom image name
	 * 
	 * @param string $name orginal name
	 * @return string new name of file
	 */
	public function get_image_custom_name( $name ) {
		preg_match('/(.*)?(\.+[^.]*)$/', $name, $name_parts);

		$name = $name_parts[1];
		$postfix = $name_parts[2];

		$user_rule = $this->options['image_name'];
		preg_match_all('/%[^%]*%/', $user_rule, $rules);

		foreach ($rules[0] as $rule) {
			switch ($rule) {
				case '%filename%':
					$replacement = $name;
					break;

				case '%date%':
					$replacement = date('Y-m-j');
					break;

				case '%url%':
					$replacement = $this->get_base_url(get_bloginfo('url'));
					break;

				default:
                    $replacement = '';
					break;
			}

            $user_rule = preg_replace('/' . $rule . '/', $replacement, $user_rule);
		}

		return $user_rule . $postfix;
	}

	/**
	 * Check url is allowed to upload
	 *
	 * @param string $url
	 * @param string $base_url base of site url
	 * @return bool
	 */
	public function is_allowable_url( $url ) {
		$url = $this->get_base_url($url);
		$base_url = ($url == null) ? $this->get_base_url(site_url('url')) : $this->base_url;
		$exclude_urls = $this->options['exclude_urls'];

		// check exclude urls
		if ($exclude_urls) {
			preg_match_all('/.*\S/', $exclude_urls, $urls);

			for ($i = 0; $i < count($urls[0]); $i++)
				if ($url == $this->get_base_url($urls[0][$i]))
					return false;
		}

		// check base url
		switch ($url) {	
			case null:
			case $base_url:
				return false;
				break;

			default:
				return true;
				break;
		}
	}

	/**
	 * Return base url without www
	 *
	 * @param string $url
	 * @return string $out base url
	 */
	public function get_base_url( $url ) {
		$url = parse_url($url, PHP_URL_HOST); // Give base URL
		$out = preg_split('/^(www(2|3)?\.)/i', $url, -1, PREG_SPLIT_NO_EMPTY); // Delete www from URL
		
		return $out[0];
	}

	/**
	 * Add settings page under options menu
	 */
	public function admin_menu() {
		add_options_page(__('Auto Upload Images Settings', 'auto-upload-images'),__('Auto Upload Images', 'auto-upload-images'),'manage_options','auto-upload', array($this, 'settings_page'));
	}

	/**
	 * Settings page contents
	 */	
	public function settings_page() {

		if (isset($_POST['submit'])) {
			$this->options['base_url'] = $_POST['base_url'];
			$this->options['image_name'] = $_POST['image_name'];
			$this->options['exclude_urls'] = $_POST['exclude_urls'];
			update_option('aui-setting', $this->options);
			$message = true;
		}
 		
		//Start the output buffer
		ob_start(); 
		
		?>

		<div class="wrap">
		    <?php screen_icon('options-general'); ?> <h2><?php _e('Auto Upload Images Settings', 'auto-upload-images'); ?></h2>
		    
		    <?php if ($message == true) : ?>
			<div id="setting-error-settings_updated" class="updated settings-error">
				<p><strong><?php _e('Settings Saved.', 'auto-upload-images'); ?></strong></p>
			</div>
			<?php endif; ?>

		    <form method="POST">
		        <table class="form-table">
		            <tr valign="top">
		                <th scope="row">
		                    <label for="base_url">
		                        <?php _e('Base URL:', 'auto-upload-images'); ?>
		                    </label> 
		                </th>
		                <td>
		                    <input type="text" name="base_url" value="<?php echo $this->options['base_url']; ?>" class="regular-text" dir="ltr" />
		                    <p class="description"><?php _e('If you need to choose a new base URL for the images that will be automatically uploaded. Ex:', 'auto-upload-images'); ?> <code>http://p30design.net</code>, <code>http://cdn.p30design.net</code>, <code>/</code></p>
		                </td>
		            </tr>
		            <tr valign="top">
		                <th scope="row">
		                    <label for="image_name">
		                        <?php _e('Image Name:', 'auto-upload-images'); ?>
		                    </label> 
		                </th>
		                <td>
		                    <input type="text" name="image_name" value="<?php echo $this->options['image_name']; ?>" class="regular-text" dir="ltr" />
		                    <p class="description"><?php _e('Choose a custom filename for the new images will be uploaded. You can also use these shortcodes <code dir="ltr">%filename%</code>, <code dir="ltr">%url%</code>, <code dir="ltr">%date%</code>.', 'auto-upload-images'); ?></p>
		                </td>
		            </tr>
		            <tr valign="top">
		            	<th scope="row">
		            		<label for="exclude_urls">
		            			<?php _e('Exclude Domains:', 'auto-upload-images'); ?>
		            		</label>
		            	</th>
		            	<td>
		            		<p><?php _e('Enter the domains you wish to be excluded from uploading images: (One domain per line)', 'auto-upload-images'); ?></p>
		            		<p><textarea name="exclude_urls" rows="10" cols="50" id="exclude_urls" class="large-text code" placeholder="http://p30design.net"><?php echo $this->options['exclude_urls']; ?></textarea></p>
		            	</td>
		            </tr>
		        </table>
		        <?php submit_button(); ?>
		    </form>
		</div>
		
		<?php

		//Get output buffer contents
		ob_get_flush();

	}
	/**
	 * Initial plugin textdomain for localization
	 */
	public function init() {
		load_plugin_textdomain('auto-upload-images', false, basename(dirname(__FILE__)) . '/lang');
	}
}
new WP_Auto_Upload;