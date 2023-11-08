<?php 

//compression: 50kb -> 16kb
//http://www.fiftyfoureleven.com/weblog/web-development/css/the-definitive-css-gzip-method

ob_start('ob_gzhandler');
header('Content-type: text/javascript; charset: UTF-8');
header('Cache-Control: must-revalidate');
header('Expires: '.gmdate('D, d M Y H:i:s',time()+60*60).' GMT');

require './mootools-1.2-core.js';

?>