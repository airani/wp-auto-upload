<?php
/*
Plugin Name: Auto Upload Images
Plugin URI: http://p30design.net/1391/08/wp-auto-upload-images.html
Description: Automatically upload external images of a post to Wordpress upload directory
Version: 2.2
Author: Ali Irani
Author URI: http://p30design.net
Text Domain: auto-upload-images
License: GPLv2 or later
*/

class WP_Auto_Upload {

    /**
     * Base of siteurl
     * @var string
     */
    public $site_url;

    /**
     * All options in array
     * @var array
     */
    public $options;

    public function __construct() {
        $defaults['base_url'] = get_bloginfo('url');
        $defaults['image_name'] = '%filename%';
        $this->options = get_option('aui-setting', $defaults);
        $this->options = wp_parse_args($this->options, $defaults);
        $this->site_url = $this->get_host_url($this->options['base_url']);
        add_action('plugins_loaded', array($this, 'init'));
        add_action('save_post', array($this, 'auto_upload'));
        add_action('admin_menu', array($this, 'admin_menu'));
    }
    /**
     * Automatically upload external images of a post to Wordpress upload directory
     *
     * @param int $post_id
     */
    public function auto_upload($post_id) {

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        if (false !== wp_is_post_revision($post_id)) {
            return;
        }

        global $wpdb;

        $content = $wpdb->get_var("SELECT `post_content` FROM {$wpdb->posts} WHERE ID='$post_id'");

        $image_urls = $this->get_image_urls($content);

        if ($image_urls) {
            foreach ($image_urls as $image_url) {
                if ($this->is_allowed($image_url) && $new_image_url = $this->save_image($image_url, $post_id)) {
                    // find image url in content and replace new image url
                    $new_image_url = parse_url($new_image_url);
                    $base_url = $this->site_url == null ? null : "http://{$this->site_url}";
                    $new_image_url = $base_url . $new_image_url['path'];
                    $content = preg_replace('/'. preg_quote($image_url, '/') .'/', $new_image_url, $content);
                }
            }

            return $wpdb->update(
                $wpdb->posts,
                array('post_content' => $content),
                array('ID' => $post_id)
            );
        }
        return false;
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

        setlocale(LC_ALL, "en_US.UTF8");
        $agent= 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERAGENT, $agent);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $image_data = curl_exec($ch);

        if ($image_data === false) {
            return;
        }

        $image_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        if (strpos($image_type,'image') === false) {
            return;
        }
        
        $image_size = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
        $image_file_name = basename($url);
        $image_name = $this->get_image_custom_name($image_file_name);
        $upload_dir = wp_upload_dir(date('Y/m'));
        $image_path = urldecode($upload_dir['path'] . '/' . $image_name);
        $image_url = urldecode($upload_dir['url'] . '/' . $image_name);

        // check if file with same name exists in upload path, rename file
        while (file_exists($image_path)) {
            if ($image_size == filesize($image_path)) {
                return $image_url;
            } else {
                $num = rand(1, 99);
                $image_path = urldecode($upload_dir['path'] . '/' . $num . '_' . $image_name);
                $image_url = urldecode($upload_dir['url'] . '/' . $num . '_' . $image_name);
            }
        }

        curl_close($ch);
        file_put_contents($image_path, $image_data);

        // if set max width and height resize image
        if ((isset($this->options['max_width']) && $this->options['max_width']) ||
            (isset($this->options['max_height']) && $this->options['max_height'])) {
            $width = isset($this->options['max_width']) ? $this->options['max_width'] : null;
            $height = isset($this->options['max_height']) ? $this->options['max_height'] : null;
            $image_resized = image_make_intermediate_size($image_path, $width, $height);
            $image_url = urldecode($upload_dir['url'] . '/' . $image_resized['file']);
        }

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
        $pattern = '/<img[^>]*src=["\']([^"\']*)[^"\']*["\'][^>]*>/i'; // find img tags and retrive src
        preg_match_all($pattern, $content, $urls, PREG_SET_ORDER);

        if (isset($urls)) {
            foreach ($urls as &$url) {
                $url = $url[1];
            }
            return array_unique($urls);
        }
        return;
    }

    /**
     * Return custom image name with user rules
     *
     * @param  string  $filename
     * @return string  custom file name
     */
    public function get_image_custom_name($filename) {
        preg_match('/(.*)?(\.+[^.]*)$/', $filename, $name_parts);

        $name = $name_parts[1];
        $postfix = $name_parts[2];

        if (preg_match('/^(\.[^?]*)\?.*/i', $postfix, $postfix_extra)) {
            $postfix = $postfix_extra[1];
        }

        $pattern_rule = $this->options['image_name'];
        preg_match_all('/%[^%]*%/', $pattern_rule, $rules);

        $patterns = array(
            '%filename%' => $name,
            '%date%' => date('Y-m-j'),
            '%year%' => date('Y'),
            '%month%' => date('m'),
            '%day%' => date('j'),
            '%url%' => $this->get_host_url(get_bloginfo('url')),
            '%random%' => uniqid()
        );

        if ($rules[0]) {
            foreach ($rules[0] as $rule) {
                $pattern_rule = preg_replace("/$rule/", $patterns[$rule] ? $patterns[$rule] : $rule, $pattern_rule);
            }
            return $pattern_rule . $postfix;
        }

        return $filename;
    }

    /**
     * Check url is allowed to upload or not
     *
     * @param string $url this url check is allowable or not
     * @param string $site_url host of site url
     * @return bool true | false
     */
    public function is_allowed($url) {
        $url = $this->get_host_url($url);
        $site_url = ($this->site_url == null) ? $this->get_host_url(site_url('url')) : $this->site_url;

        if ($url === $site_url || empty($url)) {
            return false;
        }

        if ($this->options['exclude_urls']) {
             $exclude_urls = explode("\n", $this->options['exclude_urls']);

            foreach ($exclude_urls as $exclude_url) {
                if ($url === $this->get_host_url(trim($exclude_url))) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Return host of $url without www
     *
     * @param string $url
     * @return string host url
     */
    public function get_host_url($url) {
        $url = parse_url($url); // Give base URL
        
        if (isset($url['port']))
            $url = $url['host'] . ":" . $url['port'];
        else
            $url = $url['host'];
        
        $url = preg_split('/^(www(2|3)?\.)/i', $url, -1, PREG_SPLIT_NO_EMPTY); // Delete www from URL
        return $url[0];
    }

    /**
     * Add settings page under options menu
     */
    public function admin_menu() {
        add_options_page(
            __('Auto Upload Images Settings', 'auto-upload-images'),
            __('Auto Upload Images', 'auto-upload-images'),
            'manage_options',
            'auto-upload',
            array($this, 'settings_page')
        );
    }

    /**
     * Settings page contents
     */
    public function settings_page() {

        if (isset($_POST['submit'])) {
            $this->options['base_url'] = $_POST['base_url'];
            $this->options['image_name'] = $_POST['image_name'];
            $this->options['exclude_urls'] = $_POST['exclude_urls'];
            $this->options['max_width'] = $_POST['max_width'];
            $this->options['max_height'] = $_POST['max_height'];
            update_option('aui-setting', $this->options);
            $message = true;
        }

        if (!function_exists('curl_init')) {
            $curl_error = true;
        }

        include_once('settings_page.php');
    }

    /**
     * Initial plugin textdomain for localization
     */
    public function init() {
        load_plugin_textdomain('auto-upload-images', false, basename(dirname(__FILE__)) . '/lang');
    }
}
new WP_Auto_Upload;
