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

jimport('legacy.view.legacy');

/**
 * HTML Item View class for the FLEXIcontent component
 *
 * @package		Joomla
 * @subpackage	FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewItems extends JViewLegacy
{
	function display($tpl = null)
	{
		$app        = JFactory::getApplication();
		$user       = JFactory::getUser();
		$dispatcher = JEventDispatcher::getInstance();

		// Initialize some variables
		$item 		= $this->get('Item');
		$params 	= & $item->parameters;
		$fields		= $this->get( 'Extrafields' );

		$tags = & $item->tags;
		$categories = & $item->categories;
		$favourites = $item->favourites;
		$favoured = $item->favoured;

		// process the new plugins
		JPluginHelper::importPlugin('content', 'image');
		FLEXI_J40GE
			? $app->triggerEvent('onContentPrepare', array ('com_content.article', &$item, &$params, 0))
			: $dispatcher->trigger('onContentPrepare', array ('com_content.article', &$item, &$params, 0));

		$document = JFactory::getDocument();

		// set document information
		$document->setTitle($item->title);
		$document->setName($item->alias);
		$document->setDescription($item->metadesc);
		$document->setMetaData('keywords', $item->metakey);

		// prepare header lines
		$document->setHeader($this->_getHeaderText($item, $params));
		
		$pdf_format_fields = trim($params->get("pdf_format_fields"));
		$pdf_format_fields = !$pdf_format_fields ? array() : preg_split("/[\s]*,[\s]*/", $pdf_format_fields);
		
		$methodnames = array();
		foreach($pdf_format_fields as $pdf_format_field) {
			@list($fieldname,$methodname) = preg_split("/[\s]*:[\s]*/", $pdf_format_field);
			$methodnames[$fieldname] = empty($methodname) ? 'display' : $methodname;
		}
		
		// IF no fields set then just print the item's description text
		if ( !count($pdf_format_fields) ) {
			echo $item->text;
			return;
		}
		
		foreach ($fields as $field)
		{
			if ( !isset($methodnames[$field->name]) ) continue;
			
			if ($field->iscore) {
				FlexicontentFields::loadFieldConfig($field, $item);
				//$results = $dispatcher->trigger('onDisplayCoreFieldValue', array( &$field, $item, &$params, $tags, $categories, $favourites, $favoured ));
				FLEXIUtilities::call_FC_Field_Func('core', 'onDisplayCoreFieldValue', array( &$field, $item, &$params, $tags, $categories, $favourites, $favoured ));
			} else {
				//$results = $dispatcher->trigger('onDisplayFieldValue', array( &$field, $item ));
				FLEXIUtilities::call_FC_Field_Func($field->field_type, 'onDisplayFieldValue', array( &$field, $item ));
			}
			if ( @$field->display ) {
				echo '<b>'.$field->label.'</b>: ';
				echo $field->display . '<br /><br />';
			}
		}
	}

	function _getHeaderText(& $item, & $params)
	{
		// Initialize some variables
		$text = '';

		// Display Author name
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
				$create_date = JHtml::_('date', $item->created, JText::_( 'DATE_FORMAT_LC2' ));
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
				$mod_date = JHtml::_('date', $item->modified);
				$text .= JText::_( 'FLEXI_LAST_REVISED' ).' '.$mod_date;
			}
		}
		return $text;
	}
}
?>