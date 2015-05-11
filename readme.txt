=== Auto Upload Images ===
Contributors: airani
Donate link: http://p30design.net/
Tags: upload, auto, automaticlly, image, images, admin, administrator, post, save, media, library
Requires at least: 2.7
Tested up to: 4.1.0
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Automatically detect external images in the post content and uploading images to your site then adding to the media library and replace image urls.

== Description ==

When you want to save a post, this plugin search for image urls which exists in post and automatically upload external images to the Wordpress upload directory and add images to the media library and then replace new image urls with old urls.

= Features =

* Automatically find images in posts and save them to the your site
* Save posts with new image urls
* Add images saved by plugin to the Wordpress media library
* Choose exclude domain to save images from this domain address
* Choose custom your base url for images
* Choose custom images file name with patterns
* Choose max width and height for images uploaded


= Translators =

* English
* Persian (fa_IR) - [Ali Irani](http://p30design.net)
* Español (es) - [Diego Herrera](https://github.com/diegoh)
* Russion (ru_RU) - [Артём Рябков](https://github.com/rad96)
* German (de_DE) - [Till Zimmermann](https://github.com/tillz)


= Links =

* [Official Plugin Page](http://p30design.net/1391/08/wp-auto-upload-images.html)
* [Github Repository](https://github.com/airani/wp-auto-upload)

== Installation ==

Upload the "Auto Upload Images" to plugin directory and Activate it.
To change settings go to "Settings > Auto Upload Images" and change it.

== Frequently Asked Questions ==

= What is "Base URL" in settings page? =
This URL is used as the new URL image.

= What is "Image Name" in settings page? =
You can change the final filename of the image uploaded.

= What is "Exclude Domains" in settings page? =
You can exclude many domains from the upload.

== Screenshots ==

1. Settings page in English language
2. Settings page in Persian language

== Changelog ==

= 2.2 =
* Added %random% pattern for file names

= 2.1 =
* Fixed bug in problem with some urls

= 2.0 =
* Added option for choosing max width and height of saved images
* Added new shortcodes for custom filenames. `%year%`, `%month%` and `%day%`
* Added error message for "PHP CURL" disabled sites
* Fixed bug in saving Persian and Arabic filename
* Fixed bug in saving image process
* Fixed bug in getting images url
* Many optimizations in code and enhancements performance

= 1.6 =
* [Fixed] Fixed a bug in replace exclude urls
* [Updated] Some optimize in code
* [Added] Added Español translation. Thanks to [Diegoh](https://github.com/diegoh)
* [Added] Added Russion translation. Thanks to [Артём](https://github.com/rad96)
* [Added] Added German translation. Thanks to [Till](https://github.com/tillz)

= 1.5 =
* [Updated] Optimize save post
* [Added] Add language files (English, Persian)
* [Added] Add option to choose exclude urls
* [Added] Add option for choosing a custom filename
* [Added] Add option for choosing a custom base url
* [Added] Add settings page
* [Fixed] Fixed for adding image correctly to the media library

= 1.4.1 =

* [Fixed] Fixed tiny bug ;) Thanks to Ali for reporting bug

= 1.4 =

* [New Feature] Work With Multi Address Sites
* [Fixed] Work with Persian & Arabic URLs
* [Fixed] Replace URL for images already been uploaded
* Implementation with object-oriented

= 1.3 =

* Fixed some bugs

= 1.2 =

* Fixed Bug: Save one revision post
* Fixed Bug: Fix pattern of urls
* Fixed Bug: Save file with same name
* Fixed Bug: More images with same urls in post
* Fixed Bug: Work with ssl urls

= 1.1 =

* Add image to Media Library and attach to post
* Fix a bug

= 1.0 =

* It's first version.
