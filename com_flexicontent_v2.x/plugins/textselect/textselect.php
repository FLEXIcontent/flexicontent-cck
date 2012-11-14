<?php
/**
 * @version 1.0 $Id: text.php 623 2011-06-30 14:29:28Z enjoyman@gmail.com $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.text
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
defined( '_JEXEC' ) or die( 'Restricted access' );

//jimport('joomla.plugin.plugin');
jimport('joomla.event.plugin');

class plgFlexicontent_fieldsTextSelect extends JPlugin {

	function plgFlexicontent_fieldsTextSelect( &$subject, $params ) {
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_text', JPATH_ADMINISTRATOR);
		JPlugin::loadLanguage('plg_flexicontent_fields_select', JPATH_ADMINISTRATOR);
		JPlugin::loadLanguage('plg_flexicontent_fields_textselect', JPATH_ADMINISTRATOR);
		JPluginHelper::importPlugin('flexicontent_fields', 'text' );
		JPluginHelper::importPlugin('flexicontent_fields', 'select' );
	}
	
	
	function onAdvSearchDisplayField(&$field, &$item) {
		if($field->field_type != 'textselect') return;
		$arrays = $field->parameters->renderToArray('params', 'group-textselect');
		foreach($arrays as $k=>$a) {
			$select_ = substr($k, 0, 7);
			if($select_=='select_') {
				$keyname = $select_ = substr($k, 7);
				$field->parameters->set($keyname, $field->parameters->get($k));
			}
		}
		$field->parameters->set('sql_mode', 1);
		$query = "select distinct value, value as `text` FROM `#__flexicontent_fields_item_relations` as fir WHERE field_id='{$field->id}' AND `value` != '' GROUP BY `value`;";
		$field->parameters->set('field_elements', $query);
		$field->field_type = 'select';
		plgFlexicontent_fieldsSelect::onDisplayField($field, $item);
		$field->field_type = 'textselect';
	}
	
	
	function onDisplayField(&$field, &$item) {
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'textselect') return;
		$field->field_type = 'text';
		plgFlexicontent_fieldsText::onDisplayField($field, $item);
		$field->field_type = 'textselect';
	}


	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'textselect') return;
		$field->field_type = 'text';
		plgFlexicontent_fieldsText::onBeforeSaveField($field, $post, $file, $item);
		$field->field_type = 'textselect';
	}


	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'textselect') return;
		$field->field_type = 'text';
		plgFlexicontent_fieldsText::onDisplayFieldValue($field, $item, $values, $prop);
		$field->field_type = 'textselect';
	}
	
	
	function onDisplayFilter(&$filter, $value='')
	{
		// execute the code only if the field type match the plugin type
		if($filter->field_type != 'textselect' ) return;
		$filter->field_type = 'text';
		plgFlexicontent_fieldsText::onDisplayFilter($filter, $value);
		$filter->field_type = 'textselect';
	}	
	
	function getFiltered($field_id, $value, $field_type = '')
	{
		// execute the code only if the field type match the plugin type
		if($field_type != 'textselect' ) return;
		$field_type = 'text';
		plgFlexicontent_fieldsText::getFiltered($field_id, $value, $field_type);
		$field_type = 'textselect';
	}	
}
