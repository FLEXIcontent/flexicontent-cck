phpThumb
========

phpThumb() - The PHP thumbnail generator

phpThumb() uses the GD library and/or ImageMagick to create thumbnails from images (GIF, PNG or JPEG) on the fly.
The output size is configurable (can be larger or smaller than the source), and the source may be the entire
image or only a portion of the original image. True color and resampling is used if GD v2.0+ is available,
otherwise low-color and simple resizing is used. Source image can be a physical file on the server or can be
retrieved from a database. GIFs are supported on all versions of GD even if GD does not have native GIF support
thanks to the GIFutil class by Fabien Ezber. AntiHotlinking feature prevents other people from using your server
to resize their thumbnails, or link to your images from another server. The cache feature reduces server load.

Bundled version
---------------

phpThumb v1.7.24-202602061556, https://github.com/JamesHeinrich/phpThumb commit 89559c5 (2026-02-06),
which includes the fix for CVE-2025-52994 (v1.7.24) and the PHP 8.5 deprecation fixes committed after the tag.

Files taken as they are from upstream: phpthumb.functions.php, phpthumb.filters.php, phpthumb.gif.php,
phpthumb.bmp.php, phpthumb.ico.php, phpthumb.unsharp.php, license.txt, fonts/, images/.

FLEXIcontent changes to upstream files (re-apply them when updating the library):

- phpThumb.php: the two lines forcing `error_reporting(E_ALL)` and `display_errors` are commented out.
- phpthumb.class.php: a default timezone (UTC) is set when php.ini has none, and `config_cache_directory`
  is coerced to a string before `substr()` (PHP 8.1+ deprecation).
- phpThumb.config.php: FLEXIcontent's own configuration (not upstream's phpThumb.config.php.default); it can
  be included more than once.
