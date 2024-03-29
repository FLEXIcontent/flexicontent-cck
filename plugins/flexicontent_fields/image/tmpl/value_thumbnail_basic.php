<?php  // *** DO NOT EDIT THIS FILE, CREATE A COPY !!

/**
 * Thumbnail (basic) layout
 *
 * This layout supports inline_info, pretext, posttext
 */


// ***
// *** Values loop
// ***

$i = -1;
foreach ($values as $n => $value)
{
	// Include common layout code for preparing values, but you may copy here to customize
	$result = include( JPATH_ROOT . '/plugins/flexicontent_fields/image/tmpl_common/prepare_value_display.php' );
	if ($result === _FC_CONTINUE_) continue;
	if ($result === _FC_BREAK_) break;

	$field->{$prop}[] = $pretext . $img_legend . $inline_info . $posttext;
}



// ***
// *** Add per field custom JS
// ***

if ( !isset(static::$js_added[$field->id][__FILE__]) )
{
	$js = '';

	if ($js) \Joomla\CMS\Factory::getDocument()->addScriptDeclaration($js);

	static::$js_added[$field->id][__FILE__] = true;
}



/**
 * Include common layout code before finalize values
 */

$result = include( JPATH_ROOT . '/plugins/flexicontent_fields/image/tmpl_common/before_values_finalize.php' );
if ($result !== _FC_RETURN_)
{
	// ***
	// *** Add container HTML (if required by current layout) and add value separator (if supported by current layout), then finally apply open/close tags
	// ***

	// Add value separator
	$field->{$prop} = implode($separatorf, $field->{$prop});

	// Apply open/close tags
	$field->{$prop}  = $opentag . $field->{$prop} . $closetag;
}