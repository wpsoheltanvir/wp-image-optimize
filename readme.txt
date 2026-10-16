=== WP Image Optimize ===
Contributors: wp-image-optimize
Tags: image optimization, imagify, images, jpeg, png, gif
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Optimize WordPress media uploads through the Imagify API.

== Description ==

WP Image Optimize provides a compact Imagify integration without requiring the official Imagify plugin. Add your API key under Settings > WP Image Optimize, choose Normal, Aggressive, or Ultra compression, then automatically optimize supported uploads or optimize an individual image from the Media Library.

The plugin replaces the source image after Imagify returns the optimized version. Back up your media library before enabling it on a production site.

== Installation ==

1. Zip the `wp-image-optimize` folder.
2. In WordPress, go to Plugins > Add New > Upload Plugin and upload the ZIP.
3. Activate WP Image Optimize.
4. Go to Settings > WP Image Optimize, enter your Imagify API key, and save.

== Notes ==

* Supported formats: JPEG, PNG, and GIF.
* An Imagify account and API key are required.
* The server needs PHP cURL support.
* Existing media can be optimized one image at a time from the Media Library list.
