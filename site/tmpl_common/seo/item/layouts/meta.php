<?php
/**
 * Set document's META tags, using things like
 *
 * - item META
 * - menu META
 * - item type configuration
 * - ITEM FIELDS VALUES
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
	$field_A = $item->fields['some_fieldname'];   // One of the fields
	$field_A->raw_values;                         // Array of raw uncompressed field VALUEs, to test use:    // echo '<pre>'; print_r($field_A->raw_values); echo '</pre>';
	$field_A->basic_texts;                        // Array of basic textual display of VALUEs, to test use:  // echo '<pre>'; print_r($field_A->basic_texts); echo '</pre>';
	echo JText::_('LANG_STRING_NAME');            // A Joomla language string
	echo '<pre>';

 *
 */


/**
 * Joomla does not setting the default value for 'robots', component must do it
 */
if (($_mp=$app->getParams()->get('robots')))    $document->setMetadata('robots', $_mp);


/**
 * Set item's META data: desc, keyword, title, author
 */
if ($item->metadesc)		$document->setDescription( $item->metadesc );
if ($item->metakey)			$document->setMetadata('keywords', $item->metakey);
//if ($app->getCfg('MetaTitle') == '1') $document->setMetaData('title', $item->title);   // This has been deprecated, instead search engines will use the <title> tag
if ($app->getCfg('MetaAuthor') == '1')  $document->setMetaData('author', $item->author);


/**
 * Set remaining META keys
 */
$mdata = $item->metadata->toArray();

foreach ($mdata as $k => $v)
{
	if ($v) $document->setMetadata($k, $v);
}


/**
 * Overwrite with menu META data, if menu matched current view
 */
if ($model->menu_matches)
{
	if (($_mp=$menu->getParams()->get('menu-meta_description')))  $document->setDescription( $_mp );
	if (($_mp=$menu->getParams()->get('menu-meta_keywords')))     $document->setMetadata('keywords', $_mp);
	if (($_mp=$menu->getParams()->get('robots')))                 $document->setMetadata('robots', $_mp);
	if (($_mp=$menu->getParams()->get('secure')))                 $document->setMetadata('secure', $_mp);
}
