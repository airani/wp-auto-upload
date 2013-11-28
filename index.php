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

class wp_auto_upload {

	public function __construct() {
		add_action('save_post', array($this, 'auto_upload'));
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
		
		if($images_url) {
			foreach ($images_url as $image_url) {
				if(!$this->wp_is_myurl($image_url) && $new_image_url = $this->wp_save_image($image_url, $post_id)) {
					$new_images_url[] = $new_image_url;
					unset($new_image_url);
				} else {
					$new_images_url[] = $image_url;
				}
			}
			
			$total = count($new_images_url);
			
			for ($i = 0; $i <= $total-1; $i++) {
				$new_images_url[$i] = parse_url($new_images_url[$i]);
				$content = preg_replace('/'. preg_quote($images_url[$i], '/') .'/', $new_images_url[$i]['path'], $content);
			}
			
			remove_action( 'save_post', array($this, 'auto_upload') );
			wp_update_post( array('ID' => $post_id, 'post_content' => $content) );
			add_action( 'save_post', array($this, 'auto_upload') );
		}
	}

	/**
	 * Detect url of images which exists in content
	 *
	 * @param $content
	 * @return array of urls or false
	 */
	public function wp_get_images_url( $content ) {
		preg_match_all('/<img[^>]*src=("|\')([^(\?|#|"|\')]*)(\?|#)?[^("|\')]*("|\')[^>]*\/>/', $content, $urls, PREG_SET_ORDER);
		
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
	 * Check url is internal or external
	 *
	 * @param $url
	 * @return true or false
	 */
	public function wp_is_myurl( $url ) {
		$url = $this->wp_get_base_url($url);
		$myurl = $this->wp_get_base_url(get_bloginfo('url'));
		
		switch ($url) {	
			case NULL:
			case $myurl:
				return true;
				break;

			default:
				return false;
				break;
		}
	}

	/**
	 * Give a $url and return Base of a $url
	 *
	 * @param $url
	 * @return base of $url without wwww
	 */
	public function wp_get_base_url( $url ) {
		$url = parse_url($url, PHP_URL_HOST); // Give base URL
		$temp = preg_split('/^(www(2|3)?\.)/i', $url, -1, PREG_SPLIT_NO_EMPTY); // Delete www from URL
		
		return $temp[0];
	}

	/**
	 * Save image on wp_upload_dir
	 * Add image to Media Library and attach to post
	 *
	 * @param string $url
	 * @param int $post_id
	 * @return string $out address or false
	 */
	public function wp_save_image($url, $post_id = 0) {
		require_once(ABSPATH . 'wp-admin/includes/image.php');

		$image_name = basename($url);
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
		
		if(function_exists('curl_init')) {
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

}

$wp_auto_upload = new wp_auto_upload();