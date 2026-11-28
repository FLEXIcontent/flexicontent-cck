<?php

$field->{$prop} = array();
$n = 0;

foreach ($values as $value)
{
	// Basic sanity check for a valid email address
	$value['addr'] = !empty($value['addr']) && strpos($value['addr'], '@') !== false ? $value['addr'] : '';

	// Skip empty value, adding an empty placeholder if field inside in field group
	if ( empty($value['addr']) )
	{
		if ( $is_ingroup )
		{
			$field->{$prop}[$n++]	= '';
		}
		continue;
	}

	// If not using property or property is empty, then use default property value
	// NOTE: default property values have been cleared, if (propertyname_usage != 2)
	$addr = $value['addr'];
	$text = @$value['text'];
	$text = ($usetitle && strlen($text))  ?  $text  :  $default_title;

	if ( !strlen($text) || !$usetitle )
	{
		$text = \Joomla\CMS\String\PunycodeHelper::emailToUTF8($addr);  // email in Punycode to UTF8, for the purpose of displaying it
		$text_is_email = 1;
	}
	else
	{
		$text_is_email = strpos($text,'@') !== false;
	}

	// Create field's display
	// Use paremeters to decide if email should be cloaked and if we need a mailto: link
	if ($format != 'feed' && $email_cloaking)
	{
		$html = \Joomla\CMS\HTML\HTMLHelper::_('email.cloak', $addr, $mailto_link, $text, $text_is_email);
	}
	else
	{
		// Address and link text are stored text: encoded for the attribute and for the HTML text
		$text_html = htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
		$html = $mailto_link ?
			'<a href="mailto:' . htmlspecialchars((string) $addr, ENT_QUOTES, 'UTF-8') . '" target="_blank" itemprop="email">' . $text_html . '</a>' :
			$text_html;
	}

	// Add prefix / suffix
	$field->{$prop}[$n]	= $pretext . $html . $posttext;

	$n++;
	if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
}