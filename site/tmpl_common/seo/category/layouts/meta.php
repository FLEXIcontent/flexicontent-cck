<?php
/**
 * Set document's META tags, using things like
 *
 * - category META
 * - menu META
 * - CATEGORY FILTER VALUES 
 * 
 * Please notice that at the END of this code, we check if active
 * menu is an exact match of the view, then we overwrite meta tags
 *
 * WARNING !! : If you do a more detailed setting of META then remember to comment out
 *              the code that set's META tags using MENU Meta, at the end of this code
 *
 * Example of useful field data (following code is testing code to prints them)
 *

	echo '<pre>';
	$filter_A = $filters['some_fieldname'];   // One of the category view's filters
	$filter_A->raw_values;                    // Array of raw uncompressed field VALUEs, to test use:    // echo '<pre>'; print_r($field_A->raw_values); echo '</pre>';
	$filter_A->basic_texts;                   // Array of basic textual display of VALUEs, to test use:  // echo '<pre>'; print_r($field_A->basic_texts); echo '</pre>';
	echo JText::_('LANG_STRING_NAME');        // A Joomla language string
	echo '<pre>';

 *
 */


/**
 * Joomla does not setting the default value for 'robots', component must do it
 */
if (($_mp=$app->getParams()->get('robots')))
{
	$document->setMetadata('robots', $_mp);
}


/**
 * TAG + CATEGORY meta-description and meta-keywords
 * Possibly not set metadesc, metakey for authored items OR my items
 */
if ($metadesc)
{
	$document->setDescription($metadesc);
}
if ($metakey)
{
	$document->setMetadata('keywords', $metakey);
}


/**
 * Set metadata according to metadata parameters
 */
if ($meta_params && $meta_params->get('robots'))
{
	$document->setMetadata('robots', $meta_params->get('robots'));
}

// This has been deprecated, instead search engines will use the <title> tag
/*if ($app->getCfg('MetaTitle') == '1')
{
	if ($meta_params && $meta_params->get('page_title'))
	{
		$document->setMetaData('title', $meta_params->get('page_title'));
	}
}*/

if ($app->getCfg('MetaAuthor') == '1')
{
	if ($meta_params && $meta_params->get('author'))
	{
		$document->setMetaData('author', $meta_params->get('author'));
	}
}


/**
 * Overwrite with menu META data if menu matched
 */
if ($menu_matches)
{
	if (($_mp=$menu->getParams()->get('menu-meta_description')))  $document->setDescription( $_mp );
	if (($_mp=$menu->getParams()->get('menu-meta_keywords')))     $document->setMetadata('keywords', $_mp);
	if (($_mp=$menu->getParams()->get('robots')))                 $document->setMetadata('robots', $_mp);
	if (($_mp=$menu->getParams()->get('secure')))                 $document->setMetadata('secure', $_mp);
}
