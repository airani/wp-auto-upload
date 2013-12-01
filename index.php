<?php
/*
Plugin Name: Auto Upload Images
Plugin URI: http://p30design.net/1391/08/wp-auto-upload-images.html
Description: Automatically upload external images of a post to wordpress upload directory
Version: 1.5
Author: Ali Irani
Author URI: http://p30design.net
License: GPLv2 or later
*/

class WP_Auto_Upload {

	public $base_url;
	public $options;

	public function __construct() {
		$defaults['base_url'] = get_bloginfo('url');
		$defaults['image_name'] = '%filename%';
		$this->options = get_option('aui-setting', $defaults);
		$this->base_url = $this->wp_get_base_url($this->options['base_url']);

		$this->options = wp_parse_args($this->options, $defaults);

		add_action('save_post', array($this, 'auto_upload'));
		add_action('admin_menu', array($this, 'admin_menu'));
	}

	/**
	 * Automatically upload external images of a post to wordpress upload directory
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

		$content = $wpdb->get_var( "SELECT post_content FROM wp_posts WHERE ID='$post_id' LIMIT 1" );

		$images_url = $this->wp_get_images_url($content);
		
		if ($images_url) {
			foreach ($images_url as $image_url) {
				if (!$this->wp_is_myurl($image_url)) {
					if ($new_image_url = $this->wp_save_image($image_url, $post_id))
						$new_images_url[] = $new_image_url;
					else
						$new_images_url[] = $image_url;
				} else {
					$new_images_url[] = $image_url;
				}
			}
			
			$total = count($new_images_url);
			
			for ($i = 0; $i <= $total-1; $i++) {
				$new_images_url[$i] = parse_url($new_images_url[$i]);
				$base_url = $this->base_url == NULL ? NULL : "http://{$this->base_url}";
				$new_image_url = $base_url . $new_images_url[$i]['path'];
				$content = preg_replace('/'. preg_quote($images_url[$i], '/') .'/', $new_image_url, $content);
			}
			
			remove_action( 'save_post', array($this, 'auto_upload') );
			wp_update_post( array('ID' => $post_id, 'post_content' => $content) );
			add_action( 'save_post', array($this, 'auto_upload') );
		}
	}

	/**
	 * Save image on wp_upload_dir
	 * Add image to Media Library and attach to post
	 *
	 * @param string $url
	 * @param int $post_id
	 * @return string $out address or false
	 */
	public function wp_save_image( $url, $post_id = 0 ) {

		$image_name = $this->get_image_name(basename($url));
		$upload_dir = wp_upload_dir(date('Y/m'));
		$path = $upload_dir['path'] . '/' . $image_name;
		$new_image_url = $upload_dir['url'] . '/' . rawurlencode($image_name);
		$file_exists = true;
		$i = 0;
		
		while ( $file_exists ) {
			if ( file_exists($path) ) {
				if ( $this->wp_get_exfilesize($url) == filesize($path) ) {
					return $new_image_url;
				} else {
					$i++;
					$path = $upload_dir['path'] . '/' . $i . '_' . $image_name;	
					$new_image_url = $upload_dir['url'] . '/' . $i . '_' . $image_name;
				}
			} else {
				$file_exists = false;
			}
		}
		
		if (function_exists('curl_init')) {
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);     
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); 
			$data = curl_exec($ch);
			curl_close($ch);
			file_put_contents($path, $data);
			
			$wp_filetype = wp_check_filetype($new_image_url);
			$attachment = array(
				'guid' => $new_image_url, 
				'post_mime_type' => $wp_filetype['type'],
				'post_title' => preg_replace('/\.[^.]+$/', '', basename($new_image_url)),
				'post_content' => '',
				'post_status' => 'inherit'
			);
			$attach_id = wp_insert_attachment($attachment, $path, $post_id);
			$attach_data = wp_generate_attachment_metadata($attach_id, $path);
			wp_update_attachment_metadata($attach_id, $attach_data);
			
			$out = $new_image_url;
		} else {
			$out = false;
		}

		return $out;
	}

	/**
	 * Detect url of images which exists in content
	 *
	 * @param $content
	 * @return array of urls or false
	 */
	public function wp_get_images_url( $content ) {
		preg_match_all('/<img[^>]*src=("|\')([^(\?|#|"|\')]*)(\?|#)?[^("|\')]*("|\')[^>]*\/?>/', $content, $urls, PREG_SET_ORDER);
		
		if(is_array($urls)) {
			foreach ($urls as $url)
				$images_url[] = $url[2];
		}

		if (is_array($images_url)) {
			$images_url = array_unique($images_url);
			rsort($images_url);
		}
		
		return isset($images_url) ? $images_url : false;
	}

	/**
	 * Return custom image name
	 * 
	 * @param string $name orginal name
	 * @return string new name of file
	 */
	public function get_image_name( $name ) {
		preg_match('/(.*)?(\.+[^.]*)$/', $name, $matches);
		$name = $matches[1];
		$postfix = $matches[2];

		$pattern = $this->options['image_name'];
		preg_match_all('/%[^%]*%/', $pattern, $matches);

		for ($i = 0; $i <= count($matches[0]); $i++) {
			switch ($matches[0][$i]) {
				case '%filename%':
					$replacement = $name;
					$pattern = preg_replace('/' . $matches[0][$i] . '/', $replacement, $pattern);
					break;

				case '%date%':
					$replacement = date('Y-m-j');
					$pattern = preg_replace('/' . $matches[0][$i] . '/', $replacement, $pattern);
					break;

				case '%url%':
					$replacement = $this->wp_get_base_url(get_bloginfo('url'));
					$pattern = preg_replace('/' . $matches[0][$i] . '/', $replacement, $pattern);
					break;
			}
		}

		return $pattern . $postfix;
	}

	/**
	 * Check url is internal or external
	 *
	 * @param string $url
	 * @param string $base_url base of site url
	 * @return true or false
	 */
	public function wp_is_myurl( $url ) {
		$url = $this->wp_get_base_url($url);
		$base_url = $this->base_url;

		if ($base_url == NULL)
			$base_url = $this->wp_get_base_url(get_bloginfo('url'));
		
		switch ($url) {	
			case NULL:
			case $base_url:
				return true;
				break;

			default:
				return false;
				break;
		}
	}

	/**
	 * Return base url without www
	 *
	 * @param string $url
	 * @return string $temp base url
	 */
	public function wp_get_base_url( $url ) {
		$url = parse_url($url, PHP_URL_HOST); // Give base URL
		$temp = preg_split('/^(www(2|3)?\.)/i', $url, -1, PREG_SPLIT_NO_EMPTY); // Delete www from URL
		
		return $temp[0];
	}

	/**
	 * return size of external file
	 *
	 * @param $file
	 * @return $size
	 */
	public function wp_get_exfilesize( $file ) {
		$ch = curl_init($file);
	    curl_setopt($ch, CURLOPT_NOBODY, true);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	    curl_setopt($ch, CURLOPT_HEADER, true);
	    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);     
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); 
	    $data = curl_exec($ch);
	    curl_close($ch);

	    if (preg_match('/Content-Length: (\d+)/', $data, $matches))
	        return $contentLength = (int)$matches[1];
		else
			return false;
	}

	/**
	 * Add settings page under options menu
	 */
	public function admin_menu() {
		add_options_page('Auto Upload Images Settings','Auto Upload Images','manage_options','auto-upload', array($this, 'settings_page'));
	}

	/**
	 * Settings page contents
	 */	
	public function settings_page() {

		if (isset($_POST['submit'])) {
			$this->options['base_url'] = $_POST['base_url'];
			$this->options['image_name'] = $_POST['image_name'];
			update_option('aui-setting', $this->options);
		}

		?>
		<div class="wrap">
		    <?php screen_icon('options-general'); ?> <h2>Auto Upload Images Settings</h2>

		    <form method="POST">
		        <table class="form-table">
		            <tr valign="top">
		                <th scope="row">
		                    <label for="base_url">
		                        Base URL:
		                    </label> 
		                </th>
		                <td>
		                    <input type="text" name="base_url" value="<?php echo $this->options['base_url']; ?>" class="regular-text" dir="ltr" />
		                    <p class="description">Address of your Site or CDN for images url Ex: <code>http://p30design.net</code>, <code>http://cdn.p30design.net</code>, <code>/</code></p>
		                </td>
		            </tr>
		            <tr valign="top">
		                <th scope="row">
		                    <label for="image_name">
		                        Image Name:
		                    </label> 
		                </th>
		                <td>
		                    <input type="text" name="image_name" value="<?php echo $this->options['image_name']; ?>" class="regular-text" dir="ltr" />
		                    <p class="description">Choose custom filename for save new images. You can use <code>%filename%</code>, <code>%url%</code>, <code>%date%</code> and whatever.</p>
		                </td>
		            </tr>
		        </table>
		        <?php submit_button(); ?>
		    </form>
		</div>
		<?php
	}
}

new WP_Auto_Upload;