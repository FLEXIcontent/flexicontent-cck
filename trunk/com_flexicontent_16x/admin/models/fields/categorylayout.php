<?php
/**
 * @version 1.5 stable $Id: categorylayout.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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
defined('_JEXEC') or die();
jimport('joomla.html.html');
jimport('joomla.form.formfield');
jimport('joomla.form.helper');
JFormHelper::loadFieldClass('list');
/**
 * Renders a categorylayout element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.0
 */
class JFormFieldCategorylayout extends JFormFieldList{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	 protected $type = 'Categorylayout';

	function getOptions() {
		$themes	= flexicontent_tmpl::getTemplates();
		$tmpls	= $themes->category;
		$view	= JRequest::getVar('view');
		if ($tmpls !== false) {
			if ($view != 'category') {
				$layouts[] = JHTMLSelect::option('', JText::_( 'FLEXI_USE_GLOBAL' ));
			}
			foreach ($tmpls as $tmpl) {
				$layouts[] = JHTMLSelect::option($tmpl->name, $tmpl->name); 
			}
		}
		return $layouts;
	}
}
?>