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
 *
 * - Category filter values
 *   {{fieldname:value}}        // Filter 's value
 *   {{fieldname:value_text}}   // Filter 's option's text (Fields: select, multiselect, radio, radioimage, checkbox, checkboximage)
 *   {{fieldname:value_image}}   // Filter 's option's text (Fields: radioimage, checkboximage)
 *   {{fieldname:label}}
 *
 * - Language string
 *   JText::_('LANG_STRING_NAME')
 */


/**
 * If default code needs to be customized, then please
 * copy the following file here to customize the HTML too
 */
include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'tmpl_common'.DS.'seo'.DS.'category'.DS.'layouts'.DS.'title.php');
