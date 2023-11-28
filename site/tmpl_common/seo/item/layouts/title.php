<?php
/**
 * Create an appropriate document title, using things like
 *
 * - item title
 * - category title
 * - site name
 * - ITEM FIELDS VALUES
 *
 * This improves SEO of your content
 *
 * And then set them using the following commands
 * - HTML DOCUMENT TITLE <title>     --    $document->setTitle(...);
 * - PAGE TITLE <h1>                 --    $item->title =  ' ... ' . $item->title . ' ... ';
 *
 * Example of useful field data (following code is testing code to prints them)
 *

	echo '<pre>';
	$field_A = $item->fields['some_fieldname'];   // One of the fields
	$field_A->raw_values;                         // Array of raw uncompressed field VALUEs, to test use:    // echo '<pre>'; print_r($field_A->raw_values); echo '</pre>';
	$field_A->basic_texts;                        // Array of basic textual display of VALUEs, to test use:  // echo '<pre>'; print_r($field_A->basic_texts); echo '</pre>';
	echo JText::_('LANG_STRING_NAME');            // A Joomla language string
	echo '<pre>';

 *
 */



/**
 * Use the page heading as document title, (already calculated above via 'appropriate' logic ...)
 * or the overriden custom <title> ... set via parameter
 */
$doc_title = !$params->get('override_title', 0)
	? $params->get('page_title')
	: $params->get('custom_ititle', $item->title);


// Check and prepend category title
if ( $params->get('addcat_title', 1) && count($parents) )
{
	if ( isset($item->category_title) )
	{
		if ( $params->get('addcat_title', 1) == 1) { // On Left
			$doc_title = JText::sprintf('FLEXI_PAGETITLE_SEPARATOR', $item->category_title, $doc_title);
		}
		else { // On Right
			$doc_title = JText::sprintf('FLEXI_PAGETITLE_SEPARATOR', $doc_title, $item->category_title);
		}
	}
}


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
 * Optional modify the item title too (This is typically used inside <H1> tags as page's title)
 */
//$item->title = ' ... ' . $item->title . ' ... ';