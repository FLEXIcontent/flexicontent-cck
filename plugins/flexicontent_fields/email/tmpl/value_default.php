<?php
// Create field's HTML
$field->{$prop} = array();
$n = 0;
foreach ($values as $value)
{
	if ( empty($value['addr']) && !$is_ingroup ) continue; // Skip empty if not in field group
	if ( empty($value['addr']) ) {
		$field->{$prop}[$n++]	= '';
		continue;
	}
	
	// If not using property or property is empty, then use default property value
	// NOTE: default property values have been cleared, if (propertyname_usage != 2)
	$addr = $value['addr'];
	$text = @$value['text'];
	$text = ($usetitle && strlen($text))  ?  $text  :  $default_title;
	
	if ( !strlen($text) || !$usetitle ) {
		$text = FLEXI_J30GE ? JStringPunycode::emailToUTF8($addr) : $addr;  // email in Punycode to UTF8, for the purpose of displaying it
		$text_is_email = 1;
	} else {
		$text_is_email = strpos($text,'@') !== false;
	}
	
	// Create field's display
	// A cloacked email address with custom linking text
	$html = $format != 'feed' ?
		JHTML::_('email.cloak', $addr, 1, $text, $text_is_email) :
		'<a href="mailto:'.$addr.'" target="_blank" itemprop="email">' .$text. '</a>';
	
	// Add prefix / suffix
	$field->{$prop}[$n]	= $pretext . $html . $posttext;
	
	$n++;
	if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
}