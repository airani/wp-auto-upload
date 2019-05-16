<?php
/*
Plugin Name: Auto Upload Images
Plugin URI: http://p30design.irani.im/1391/08/wp-auto-upload-images.html
Description: Automatically upload and import external images of a post to Wordpress upload directory and media management
Version: 3.2.2
Author: Ali Irani
Author URI: https://irani.im
Text Domain: auto-upload-images
Domain Path: /src/lang
License: GPLv2 or later
*/

if (!defined('ABSPATH')) exit();

define('WPAUI_DIR', dirname(__FILE__));

require 'src/functions.php';
require 'src/WpAutoUpload.php';

$wp_aui = new WPAutoUpload();

$wp_aui->run();
