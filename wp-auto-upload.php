<?php
require 'classes/AutoUploadImages.php';

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
     * WP_Auto_Upload constructor.
     * Set default variables and options
     * Add wordpress actions
     */
    public function __construct()
    {
        add_action('plugins_loaded', array($this, 'initTextdomain'));
        add_action('save_post', array($this, 'afterSavePost'));
        add_action('admin_menu', array($this, 'addAdminMenu'));
    }

    /**
     * Initial plugin textdomain for translation files
     */
    public function initTextdomain()
    {
        load_plugin_textdomain('auto-upload-images', false, basename(dirname(__FILE__)) . '/lang');
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
        );
        return static::$_options = wp_parse_args(get_option(self::WP_OPTIONS_KEY), $defaults);
    }

    /**
     * Return an option with specific key
     * @param $key
     * @return null
     */
    public static function getOption($key)
    {
        if (isset(static::getOptions()[$key]) === false) {
            return null;
        }
        return static::getOptions()[$key];
    }

    /**
     * Automatically upload external images of a post to Wordpress upload directory
     * @param int $post_id
     */
    public function afterSavePost($post_id)
    {
        if (wp_is_post_revision($post_id)) {
            return false;
        }

        $autoUploadImages = new AutoUploadImages($post_id);
        return $autoUploadImages->save();
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
     * Settings page contents
     */
    public function settingPage()
    {
        if (isset($_POST['submit'])) {
            $fields = array('base_url', 'image_name', 'exclude_urls', 'max_width', 'max_height');
            foreach ($fields as $field) {
                if ($_POST[$field]) {
                    static::$_options[$field] = $_POST[$field];
                }
            }
            update_option(self::WP_OPTIONS_KEY, static::$_options);
            $message = true;
        }

        if (function_exists('curl_init') === false) {
            $curl_error = true;
        }

        include_once('setting-page.php');
    }
}
