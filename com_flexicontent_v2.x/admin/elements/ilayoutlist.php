<?php
/**
 * @version 1.5 stable $Id: ilayoutlist.php 967 2011-11-21 00:01:36Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * FLEXIcontent is a derivative work of the excellent QuickFAQ component
 * @copyright (C) 2008 Christoph Lukes
 * see www.schlu.net for more information
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');
jimport('joomla.html.html');
jimport('joomla.form.formfield');
jimport('joomla.form.helper');
JFormHelper::loadFieldClass('list');

/**
 * Renders a author element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.0
 */
class JFormFieldIlayoutlist extends JFormFieldList
{
	/**
	 * The form field type.
	 *
	 * @var		string
	 * @since	1.6
	 */
	protected $type = 'Ilayoutlist';

	protected function getOptions()
	{
		$themes	= flexicontent_tmpl::getTemplates();
		$tmpls	= $themes->items;
		$value = $this->value;
		$this->value = explode("|", $this->value);
		
		
		// Field data (the templates)
		$lays = array();
		foreach ($tmpls as $tmpl) {
			$lays[] = $tmpl->name;
		}
		$lays = implode("','", $lays);
		
		if ($tmpls !== false) {
			foreach ($tmpls as $tmpl) {
				$layouts[] = JHTMLSelect::option($tmpl->name, $tmpl->name); 
			}
		}
		
		return $layouts;
	}
}
?>
