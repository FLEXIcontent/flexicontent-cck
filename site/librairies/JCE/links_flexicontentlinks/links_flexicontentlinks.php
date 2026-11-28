<?php
/**
 * @package     FLEXIcontent
 * @subpackage  JCE link browser
 * @copyright   Copyright (C) 2014 FLEXIcontent project. All rights reserved.
 * @license     GNU/GPL 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 * FLEXIcontent links for the link dialog of the JCE editor.
 *
 * JCE 2.9 and JCE 3 find link extensions in the same place: an enabled plugin of the "jce" plugin group that is
 * named links_<name>, with this file as plugins/jce/links_<name>/links_<name>.php. JCE 2.9 then creates
 * WFLinkBrowser_<Name>, JCE 3 looks for Wfe\Plugins\Links\<Name> first and accepts WFLinkBrowser_<Name> too.
 *
 * The link browser itself is part of the FLEXIcontent component, so that it is updated with the component.
 */
defined('_JEXEC') or die;

if (!class_exists('WFLinkBrowser_Flexicontentlinks', false)
	&& is_file(JPATH_SITE . '/components/com_flexicontent/librairies/JCE/links/flexicontentlinks.php'))
{
	require_once JPATH_SITE . '/components/com_flexicontent/librairies/JCE/links/flexicontentlinks.php';
}

if (!class_exists('WFLinkBrowser_Flexicontentlinks', false))
{
	// The component is no longer installed: JCE still creates the class, it lists nothing
	class WFLinkBrowser_Flexicontentlinks
	{
		public function __construct($options = array(), $container = null) {}
		public function display() {}
		public function isEnabled() { return false; }
		public function getOption() { return array(); }
		public function getList() { return ''; }
		public function getLinks($args) { return array(); }
	}
}

if (!class_exists('Wfe\\Plugins\\Links\\Flexicontentlinks', false))
{
	class_alias('WFLinkBrowser_Flexicontentlinks', 'Wfe\\Plugins\\Links\\Flexicontentlinks');
}
