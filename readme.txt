=== Auto Upload Images ===
Contributors: airani
Donate link: https://irani.im/
Tags: upload, auto, automatically, image, images, admin, administrator, post, save, media, automation, editor, filter
Requires at least: 2.7
Tested up to: 5.2
Stable tag: 3.2.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Automatically detect external images in the post content and import images to your site then adding to the media library and replace image urls.

== Description ==

When you want to save a post, this plugin search for image urls which exists in post and automatically upload and import external images to the Wordpress upload directory and add images to the media library and then replace new image urls with old urls.

= Features =

* Automatically find images in posts and save them to the your server and wp media library
* Update posts with new image urls in your server
* Add images saved by plugin to the Wordpress media library
* Select custom post types for excluding auto upload images
* Choose exclude domain to save images from this domain address
* Choose custom your base url for images
* Choose custom images file name with patterns
* Choose custom image alt name with patterns
* Choose max width and height for images uploaded


= Translators =

* English
* Persian (fa_IR) - [Ali Irani](https://irani.im)
* Español (es) - [Diego Herrera](https://github.com/diegoh)
* Russion (ru_RU) - [Артём Рябков](https://github.com/rad96)
* German (de_DE) - [Till Zimmermann](https://github.com/tillz)
* French (fr_FR) - [Malaiac](https://github.com/Malaiac)
* Korean (ko_KR) - [Shodan](https://github.com/luvrei)
* Italian (it_IT) - Patryk Chmura


= Links =

* [Official Plugin Page](https://p30design.irani.im/1391/08/wp-auto-upload-images.html)
* [Github Repository](https://github.com/airani/wp-auto-upload)
* [Report Issues](https://github.com/airani/wp-auto-upload/issues/new)

== Installation ==

Upload the "Auto Upload Images" to plugin directory and Activate it.
To change settings go to "Settings > Auto Upload Images" and change it.

== Frequently Asked Questions ==

= Working with Gutenburg editor in wp 3.1 and later? =
Yes, but after save with ajax not show urls immediately in editor.


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

= 3.2.2 =
* Add support for detecting images from "srcset" attribute in img tag

= 3.2.1 =
* Fixed some important bugs
* Add new feature to reset all options of plugin to defaults settings
* Update with some enhancements in image resizing

= 3.2 =
* Update with change image downloader
* Update with save post enhancements
* Update integrate with Gutenburg editor
* Fixed mime_content_type error in some cases
* Fixed some minor bugs
* Delete deprecated wp functions
* Add Korean translation
* Add French translation

= 3.1.1 =
* Fixed critical bug with update to new file structure

= 3.1 =
* Fixed bug and add support for base url with HTTPS
* Fixed bug in image alt attribute replacement
* Some optimizations and improvements code
* Enhancement in files security access
* Update files structures
* Update setting page styles
* Update screenshot pictures
* Add Information box to setting page

= 3.0.1 =
* Fixed some bugs

= 3.0 =
* Add option for customize images alt attribute with defined patterns
* Add option for exclude post types from auto images uploading
* Add `%timestamp%`, `%post_id%`, `%postname%`, `%image_alt%` patterns for custom file names and image alt names.
* Handling image alt attribute
* Change code structures and many important optimizations
* Saving images on upload directory with same post date
* Fixed bugs with uploading images from create new posts by wp restful api
* Fixed some bugs. Thanks to [Sergey Funn](https://github.com/racypepper)

= 2.2 =
* Added `%random%` pattern for file names. Contributed by [Zdravko Danev](https://github.com/zdanev)
* Added Italian translation. Thanks to Patryk Chmura

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
