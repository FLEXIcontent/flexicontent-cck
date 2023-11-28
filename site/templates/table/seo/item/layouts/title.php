<?php

/**
 * Create the document title, using page title and other data
 * 
 * Setting
 * - a proper HTML DOCUMENT TITLE <title>  --  $document->setTitle(...);
 * - a PAGE TITLE <h1>                     --  $item->title =  ' ... ' . $item->title . ' ... ';
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
 * If default code needs to be customized, then please
 * copy the following file here to customize the HTML too
 */
include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'tmpl_common'.DS.'seo'.DS.'item'.DS.'layouts'.DS.'title.php');
