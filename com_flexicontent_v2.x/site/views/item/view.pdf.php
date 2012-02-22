<?php
/**
 * @version 1.5 stable $Id: view.pdf.php 1138 2012-02-07 03:01:38Z ggppdk $
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

defined( '_JEXEC' ) or die( 'Restricted access' );

jimport( 'joomla.application.component.view');

/**
 * HTML Item View class for the FLEXIcontent component
 *
 * @package		Joomla
 * @subpackage	FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewItems extends JView
{
	function display($tpl = null)
	{
		$mainframe =& JFactory::getApplication();

		$dispatcher	=& JDispatcher::getInstance();

		// Initialize some variables
		$item 		= & $this->get('Item');
		$params 	= & $mainframe->getParams('com_flexicontent');
		$fields		= & $this->get( 'Extrafields' );

		$tags = null;
		$categories = null;
		$favourites = null;
		$favoured = null;

		// process the new plugins
		JPluginHelper::importPlugin('content', 'image');
		if (!FLEXI_J16GE) {
			$dispatcher->trigger('onPrepareContent', array (& $item, & $params, 0));
		} else {
			$dispatcher->trigger('onContentPrepare', array ('com_content.article', &$item, &$params, 0));
		}

		$document = &JFactory::getDocument();

		// set document information
		$document->setTitle($item->title);
		$document->setName($item->alias);
		$document->setDescription($item->metadesc);
		$document->setMetaData('keywords', $item->metakey);

		// prepare header lines
		$document->setHeader($this->_getHeaderText($item, $params));
		
		foreach ($fields as $field) {
			if ($field->iscore == 1 || $field->field_type == ('image' || 'file')) {
/*
				if ($field->iscore) {
					//$results = $dispatcher->trigger('onDisplayCoreFieldValue', array( &$field, $item, &$params, $tags, $categories, $favourites, $favoured ));
					FLEXIUtilities::call_FC_Field_Func('core', 'onDisplayCoreFieldValue', array( &$field, $item, &$params, $tags, $categories, $favourites, $favoured ));
				} else {
					//$results = $dispatcher->trigger('onDisplayFieldValue', array( &$field, $item ));
					FLEXIUtilities::call_FC_Field_Func($field->field_type, 'onDisplayFieldValue', array( &$field, $item ));
				}
*/
			} else {
				//$results = $dispatcher->trigger('onDisplayFieldValue', array( &$field, $item ));
				FLEXIUtilities::call_FC_Field_Func($field->field_type, 'onDisplayFieldValue', array( &$field, $item ));
				echo $field->label . ': ';
				echo $field->display . '<br />';
			}
		}

		echo $item->text;
	}

	function _getHeaderText(& $item, & $params)
	{
		// Initialize some variables
		$text = '';

		if ($params->get('show_author')) {
			// Display Author name
			$text .= "\n";
			$text .= JText::_( 'FLEXI_WRITTEN_BY' ).' '. ($item->created_by_alias ? $item->created_by_alias : $item->author);
		}

		if ($params->get('show_create_date') && $params->get('show_author')) {
			// Display Separator
			$text .= "\n";
		}

		if ($params->get('show_create_date')) {
			// Display Created Date
			if (intval($item->created)) {
				$create_date = JHTML::_('date', $item->created, JText::_( 'DATE_FORMAT_LC2' ));
				$text .= $create_date;
			}
		}

		if ($params->get('show_modify_date') && ($params->get('show_author') || $params->get('show_create_date'))) {
			// Display Separator
			$text .= " - ";
		}

		if ($params->get('show_modify_date')) {
			// Display Modified Date
			if (intval($item->modified)) {
				$mod_date = JHTML::_('date', $item->modified);
				$text .= JText::_( 'FLEXI_LAST_REVISED' ).' '.$mod_date;
			}
		}
		return $text;
	}
}
?>