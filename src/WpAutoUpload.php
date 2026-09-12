<?php

require 'ImageUploader.php';

/**
 * Wordpress Auto Upload Images
 * @link http://wordpress.org/plugins/auto-upload-images/
 * @link https://github.com/airani/wp-auto-upload
 * @author Ali Irani <ali@irani.im>
 */
class WpAutoUpload
{
    const WP_OPTIONS_KEY = 'aui-setting';

    private static $_options;

    /**
     * WP_Auto_Upload Run.
     * Set default variables and options
     * Add wordpress actions
     */
    public function run()
    {
        add_action('plugins_loaded', array($this, 'initTextdomain'));
        add_action('admin_menu', array($this, 'addAdminMenu'));

        add_filter('wp_insert_post_data', array($this, 'savePost'), 10, 2);
        add_action('save_post', array($this, 'savePostMeta'), 10, 2);
    }

    /**
     * Initial plugin textdomain for translation files
     */
    public function initTextdomain()
    {
        load_plugin_textdomain('auto-upload-images', false, basename(WPAUI_DIR) . '/src/lang');
    }

    /**
     * Automatically upload external images of a post to Wordpress upload directory
     * call by wp_insert_post_data filter
     * @param array data An array of slashed post data
     * @param array $postarr An array of sanitized, but otherwise unmodified post data
     * @return array $data
     */
    public function savePost($data, $postarr)
    {
        if (wp_is_post_revision($postarr['ID']) ||
            wp_is_post_autosave($postarr['ID']) ||
            (defined('DOING_AJAX') && DOING_AJAX) ||
            (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return $data;
        }

        if ($content = $this->save($postarr)) {
            $data['post_content'] = $content;
        }
        return $data;
    }

    /**
     * Find and replace external images in selected custom fields after post save
     * @param int $postId
     * @param WP_Post $post
     */
    public function savePostMeta($postId, $post)
    {
        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId) ||
            (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }

        $fields = self::getOption('custom_fields');
        if (!is_array($fields) || count($fields) == 0) {
            return;
        }

        $postarr = get_object_vars($post);
        foreach ($fields as $field) {
            $meta = get_post_meta($postId, $field, true);
            if (!is_string($meta) || $meta === '') {
                continue; // only simple string values
            }
            if ($newMeta = $this->save($postarr, $meta)) {
                update_post_meta($postId, $field, $newMeta);
            }
        }
    }

    /**
     * Upload images and save new urls
     * @param array $postarr
     * @param string|null $content custom content to process (defaults to post_content)
     * @return string|false filtered content
     */
    public function save($postarr, $content = null)
    {
        $excludePostTypes = self::getOption('exclude_post_types');
        if (is_array($excludePostTypes) && in_array($postarr['post_type'], $excludePostTypes, true)) {
            return false;
        }

        if ($content === null) {
            $content = $postarr['post_content'];
        }
        $content = stripslashes($content);
        $images = $this->findAllImageUrls($content);

        if (count($images) == 0) {
            return false;
        }

        // Guard: a post with many external images can exhaust resources (each triggers a download)
        $images = array_slice($images, 0, 50);

        foreach ($images as $image) {
            // Content stores urls html-encoded (e.g. &amp;); decode for download and replacement
            $image['url'] = htmlspecialchars_decode($image['url'], ENT_QUOTES);
            $uploader = new ImageUploader($image['url'], $image['alt'], $postarr);
            if ($uploadedImage = $uploader->save()) {
                $urlParts = parse_url($uploadedImage['url']);
                $base_url = $uploader::getHostUrl(null, true, true);
                $image_url = $base_url . $urlParts['path'];
                $image_url = esc_url($image_url);
                $content = str_replace(array($image['url'], htmlspecialchars($image['url'], ENT_QUOTES)), $image_url, $content);
                if (!empty($image['alt'])) {
                    $content = preg_replace('/alt=["\']' . preg_quote($image['alt'], '/') . '["\']/', "alt='" . $uploader->getAlt() . "'", $content);
                }
            }
        }
        return $content;
    }

    /**
     * Find image urls in content and retrieve urls by array
     * @param $content
     * @return array
     */
    public function findAllImageUrls($content)
    {
        $urls1 = array();
        preg_match_all('/<img[^>]*srcset=["\']([^"\']*)[^"\']*["\'][^>]*>/i', $content, $srcsets, PREG_SET_ORDER);
        if (count($srcsets) > 0) {
            $count = 0;
            foreach ($srcsets as $key => $srcset) {
                preg_match_all('/(https?:)?\/\/[^\s,]+/i', $srcset[1], $srcsetUrls, PREG_SET_ORDER);
                if (count($srcsetUrls) == 0) {
                    continue;
                }
                foreach ($srcsetUrls as $srcsetUrl) {
                    if (self::isValidImageUrl($srcsetUrl[0])) {
                        $urls1[$count][] = $srcset[0];
                        $urls1[$count][] = $srcsetUrl[0];
                        $count++;
                    }
                }
            }
        }

        preg_match_all('/<img[^>]*src=["\']([^"\']*)[^"\']*["\'][^>]*>/i', $content, $urls, PREG_SET_ORDER);
        $urls = array_merge($urls, $urls1);

        if (count($urls) == 0) {
            return array();
        }
        foreach ($urls as $index => &$url) {
            if (!self::isValidImageUrl($url[1])) {
                unset($urls[$index]);
                continue;
            }
            $images[$index]['alt'] = preg_match('/<img[^>]*alt=["\']([^"\']*)[^"\']*["\'][^>]*>/i', $url[0], $alt) ? $alt[1] : null;
            $images[$index]['url'] = $url = $url[1];
        }
        foreach (array_unique($urls) as $index => $url) {
            $unique_array[] = $images[$index];
        }
        return isset($unique_array) ? $unique_array : array();
    }

    /**
     * Basic validation for image urls extracted from content
     * @param string $url
     * @return bool
     */
    public static function isValidImageUrl($url)
    {
        if (!is_string($url) || strlen($url) > 2048) {
            return false;
        }

        // Must start with http:// or https:// or be protocol-relative
        return (bool) preg_match('/^(https?:)?\/\//', $url);
    }

    /**
     * Returns all public custom field (post meta) keys existing in the database
     * @return array
     */
    public static function getCustomFields()
    {
        global $wpdb;

        $keys = $wpdb->get_col(
            "SELECT DISTINCT meta_key FROM {$wpdb->postmeta}
             WHERE meta_key NOT LIKE '\_%' AND meta_key NOT LIKE '%_edit_%'
             ORDER BY meta_key ASC"
        );

        return $keys ?: array();
    }

    /**
     * Add settings page under options menu
     */
    public function addAdminMenu()
    {
        add_options_page(
            __('Auto Upload Images Settings', 'auto-upload-images'),
            __('Auto Upload Images', 'auto-upload-images'),
            'manage_options',
            'auto-upload',
            array($this, 'settingPage')
        );
    }

    /**
     * Returns options in an array
     * @return array
     */
    public static function getOptions()
    {
        if (static::$_options) {
            return static::$_options;
        }
        $defaults = array(
            'base_url' => get_bloginfo('url'),
            'image_name' => '%filename%',
            'alt_name' => '%image_alt%',
        );
        return static::$_options = wp_parse_args(get_option(self::WP_OPTIONS_KEY), $defaults);
    }

    /**
     * Reset options to default options
     * @return bool
     */
    public static function resetOptionsToDefaults()
    {
        $defaults = array(
            'base_url' => get_bloginfo('url'),
            'image_name' => '%filename%',
            'alt_name' => '%image_alt%',
        );
        static::$_options = $defaults;
        return update_option(self::WP_OPTIONS_KEY, $defaults);
    }

    /**
     * Return an option with specific key
     * @param $key
     * @return mixed
     */
    public static function getOption($key, $default = null)
    {
        $options = static::getOptions();
        if (isset($options[$key]) === false) {
            return $default;
        }
        return $options[$key];
    }

    /**
     * Returns fixed and replace deprecated patterns
     * @param $pattern
     * @return string
     */
    public function replaceDeprecatedPatterns($pattern)
    {
        preg_match_all('/%(date|day)%/', $pattern, $rules);

        $patterns = array(
            '%date%' => '%today_date%',
            '%day%' => '%today_day%',
        );

        if ($rules[0]) {
            foreach ($rules[0] as $rule) {
                $pattern = str_replace($rule, array_key_exists($rule, $patterns) ? $patterns[$rule] : $rule, $pattern);
            }
        }

        return $pattern;
    }

    /**
     * Settings page contents
     */
    public function settingPage()
    {
        if (isset($_POST['submit']) && check_admin_referer('aui_settings')) {
            $textFields = array('base_url', 'image_name', 'alt_name', 'max_width', 'max_height');
            foreach ($textFields as $field) {
                if (array_key_exists($field, $_POST) && $_POST[$field]) {
                    if ($field === 'image_name' || $field === 'alt_name') {
                        $_POST[$field] = $this->replaceDeprecatedPatterns($_POST[$field]);
                    }

                    static::$_options[$field] = sanitize_text_field($_POST[$field]);
                }
            }
            if (array_key_exists('exclude_urls', $_POST) && $_POST['exclude_urls']) {
                static::$_options['exclude_urls'] = sanitize_textarea_field($_POST['exclude_urls']);
            }
            if (array_key_exists('exclude_post_types', $_POST) && $_POST['exclude_post_types']) {
                foreach ($_POST['exclude_post_types'] as $typ) {
                    static::$_options['exclude_post_types'][] = sanitize_text_field($typ);
                }
            }
            if (array_key_exists('custom_fields', $_POST) && is_array($_POST['custom_fields'])) {
                $validFields = self::getCustomFields();
                static::$_options['custom_fields'] = array_values(array_intersect(
                    array_map('sanitize_text_field', $_POST['custom_fields']),
                    $validFields
                ));
            } else {
                static::$_options['custom_fields'] = array();
            }
            update_option(self::WP_OPTIONS_KEY, static::$_options);
            $message = __('Settings Saved.', 'auto-upload-images');
        }

        if (isset($_POST['reset']) && check_admin_referer('aui_settings') && self::resetOptionsToDefaults()) {
            $message = __('Successfully settings reset to defaults.', 'auto-upload-images');
        }

        include_once('setting-page.php');
    }
}
