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
 * If default code needs to be customized, then please
 * copy the following file here to customize the HTML too
 */
include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'tmpl_common'.DS.'seo'.DS.'category'.DS.'layouts'.DS.'meta.php');
