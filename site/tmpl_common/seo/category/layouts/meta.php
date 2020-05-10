<?php

/**
 * Set document's META tags
 * 
 * Please notice that at the END of this code, we check if active
 * menu is an exact match of the view, then we overwrite meta tags
 *
 * If you do a more detailed setting of META then comment out the code that set's META tags using MENU Meta
 *
 * These replacements can be used
 *   {{fieldname}}
 *   {{fieldname:displayname}}
 *   {{fieldname:label}}
 *
 * for language string use
 *   JText::_('LANG_STRING_NAME')
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
	if (($_mp=$menu->params->get('menu-meta_description')))  $document->setDescription( $_mp );
	if (($_mp=$menu->params->get('menu-meta_keywords')))     $document->setMetadata('keywords', $_mp);
	if (($_mp=$menu->params->get('robots')))                 $document->setMetadata('robots', $_mp);
	if (($_mp=$menu->params->get('secure')))                 $document->setMetadata('secure', $_mp);
}
