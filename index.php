<?php
/*
Plugin Name: Auto Upload Images
Plugin URI: http://p30design.net/1391/08/wp-auto-upload-images.html
Description: Automatically upload and import external images of a post to Wordpress upload directory and media management
Version: 3.1.1
Author: Ali Irani
Author URI: http://p30design.net
Text Domain: auto-upload-images
Domain Path: /src/lang
License: GPLv2 or later
*/

if (!defined('ABSPATH')) exit();

define('WPAUI_DIR', dirname(__FILE__));

require 'src/WpAutoUpload.php';

new WPAutoUpload();
