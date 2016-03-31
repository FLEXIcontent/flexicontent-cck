<?php

// THIS FILES IS NOT OVERWRITTEN BY FLEXICONTENT
// PLEASE ADD YOUR CUSTOM CONFIGURATION STRINGS HERE


// * DocumentRoot configuration
// phpThumb() depends on $_SERVER['DOCUMENT_ROOT'] to resolve path/filenames. This value is usually correct,
// but has been known to be broken on some servers. This value allows you to override the default value.
// Do not modify from the auto-detect default value unless you are having problems.

// $PHPTHUMB_CONFIG['document_root'] =  ...;

//$PHPTHUMB_CONFIG['high_security_enabled']       = false;   // DO NOT DISABLE THIS ON ANY PUBLIC-ACCESSIBLE SERVER. If disabled, your server is more vulnerable to hacking attempts, both on your server and via your server to other servers. When enabled, requires 'high_security_password' set to be set and requires the use of phpThumbURL() function (at the bottom of phpThumb.config.php) to generate hashed URLs
//$PHPTHUMB_CONFIG['high_security_password']      = '';      // required if 'high_security_enabled' is true, and must be at complex (uppercase, lowercase, numbers, punctuation, etc -- punctuation is strongest, lowercase is weakest; see PasswordStrength() in phpthumb.functions.php). You can use a password generator like http://silisoftware.com/tools/password-random.php to generate a strong password
