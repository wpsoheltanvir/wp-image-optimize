=== WP Image Optimize ===
Contributors: wp-image-optimize
Tags: image optimization, local optimization, webp, jpeg, png
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later

Optimize WordPress media uploads locally. No API key or external service required.

== Description ==

WP Image Optimize uses the image editor already available on your WordPress server to recompress JPEG and PNG files. Choose a JPEG quality setting, enable automatic optimization for new uploads, and optionally create WebP copies when supported by your server.

No image is uploaded to a third-party service and no subscription or API key is required.

== Installation ==

1. Download the repository ZIP.
2. In WordPress, go to Plugins > Add New > Upload Plugin and upload the ZIP.
3. Activate WP Image Optimize.
4. Go to Settings > WP Image Optimize and choose your preferences.

== Notes ==

* Supported formats: JPEG and PNG.
* Existing media can be optimized one image at a time from the Media Library list.
* Compression and WebP support depend on the server’s GD or Imagick image library.
* The plugin overwrites the source image. Back up your media library before enabling it on a production site.
* Generated WebP files require your WordPress theme or web server to be configured to serve them.
