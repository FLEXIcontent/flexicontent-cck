<?php

/**
 * Create the document title, using page title and other data
 * 
 * Setting
 * - a proper HTML DOCUMENT TITLE <title>  --  $document->setTitle(...);
 * - a PAGE TITLE <h1>                     --  $category->title =  ' ... ' . $category->title . ' ... ';
 * improves SEO of your content
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
 * Use the page heading as document title, (already calculated above via 'appropriate' logic ...)
 * or the overriden custom <title> ... set via parameter
 */
$doc_title = empty($meta_params)
	? $params->get('page_title')
	: $meta_params->get('page_title', $params->get('page_title'));


/**
 * Check and prepend or append site name to page title
 */
if ($doc_title != $app->getCfg('sitename'))
{
	if ($app->getCfg('sitename_pagetitles', 0) == 1)
	{
		$doc_title = JText::sprintf('JPAGETITLE', $app->getCfg('sitename'), $doc_title);
	}
	elseif ($app->getCfg('sitename_pagetitles', 0) == 2)
	{
		$doc_title = JText::sprintf('JPAGETITLE', $doc_title, $app->getCfg('sitename'));
	}
}


/**
 * Finally, set document title
 */
$document->setTitle($doc_title);


/**
 * Optional modify the category title too (This is typically used inside <H1> tags as page's title)
 */
//$category->title = ' ... ' . $category->title . ' ... ';