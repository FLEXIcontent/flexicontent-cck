<?php
/**
 * @version 1.0 $Id$
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.file
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

class plgFlexicontent_fieldsFcloadmodule extends JPlugin
{
	static $field_types = array('fcloadmodule');
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsFcloadmodule( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_fcloadmodule', JPATH_ADMINISTRATOR);
	}
		
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		
		// initialise property
		if ( empty($field->value[0]) ) {
			$field->value[0] = '';
		}
		
		$document	= & JFactory::getDocument();
		
		$fieldname = FLEXI_J16GE ? 'custom['.$field->name.']' : $field->name;
		$elementid = FLEXI_J16GE ? 'custom_'.$field->name : $field->name;
		
		$mod_params	= $field->parameters->get( 'mod_params', '') ;
		$mod_params	= preg_split("/[\s]*%%[\s]*/", $mod_params);
		
		if ( empty($mod_params[0]) ) return;
		
		$field->html = array();
		$n = 0;
		$value = unserialize($field->value[0]);
		foreach ($mod_params as $mod_param) {
			list($param_label, $param_name) = preg_split("/[\s]*!![\s]*/", $mod_param);
			
			$param_value = @$value[$param_name];
			
			$field->html[] = $param_label.
				': <input id="'.$elementid.'_'.$n.'" name="'.$fieldname.'[0]['.$param_name.']" class="inputbox" type="text" size="40" value="'.$param_value.'" />'
				;
			$n++;
		}
		
		$field->html = '<div>'. implode('<br/', $field->html) .'</div>';
	}

	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		global $addthis;
		$mainframe =& JFactory::getApplication();
		
		$values = $values ? $values : $field->value;
		if ( empty($values[0]) ) {
			$values[0] = '';
		}
		
		// parameters shortcuts
		$module_method_oldname = $field->parameters->get('module-method', 1);
		$module_method	= $field->parameters->get('module_method', $module_method_oldname );
		$mymodule		= $field->parameters->get('modules', '');
		$position		= $field->parameters->get('position', '');
		$style 			= $field->parameters->get('style', -2);
		
		$document		= &JFactory::getDocument();
		$display 		= '';
		$renderer		= $document->loadRenderer('module');
		$mparams		= array('style'=>$style);
		
		if ($module_method == 1) { // module case
			if (!$mymodule) { $field->{$prop} = 'Please select a module'; return; }
/*
echo '<xmp>';
print_r($mymodule);
echo '</xmp>';
*/
			$object  = $this->_getModuleObject((int)$mymodule);
/*
echo '<xmp>';
print_r($object);
echo '</xmp>';
*/
			$mod	 = JModuleHelper::getModule(substr(@$object->module, 4), @$object->title);
			
			// Set module parameter per item 
			$mod_params	= $field->parameters->get( 'mod_params', '') ;
			$mod_params	= preg_split("/[\s]*%%[\s]*/", $mod_params);
			$mod_params = !empty($mod_params[0]) ? $mod_params : array();
			
			$value = unserialize($values[0]);
			$custom_mod_params = array();
			foreach ($mod_params as $mod_param)
			{
				list($param_label, $param_name) = preg_split("/[\s]*!![\s]*/", $mod_param);
				$custom_mod_params[ $param_name ] = $value[$param_name];
			}
			$_mod_params = FLEXI_J16GE ? new JRegistry($mod->params) : new JParameter($mod->params);
			foreach ($custom_mod_params as $i => $v) $_mod_params->set($i,$v);
			$mod->params = $_mod_params ->toString();

/*
echo '<xmp>';
print_r($mod);
echo '</xmp>';
*/
			$display = $renderer->render($mod, $mparams);

		} else { // position case		
			if (!$position) { $field->{$prop} = 'Error'; return; }
			foreach (JModuleHelper::getModules($position) as $mod)  {
				$display .= $renderer->render($mod, $mparams);
			}
		}
	
		$field->{$prop} = $display;
	}
	
	
	
	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************
	
	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		// execute the code only if the field type match the plugin type
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if(!is_array($post) && !strlen($post)) return;
		
		// Make sure posted data is an array 
		$post = !is_array($post) ? array($post) : $post;
	}
	
	
	// Method to take any actions/cleanups needed after field's values are saved into the DB
	function onAfterSaveField( &$field, &$post, &$file, &$item ) {
	}
	
	
	// Method called just before the item is deleted to remove custom item data related to the field
	function onBeforeDeleteField(&$field, &$item) {
	}
	
	
	
	// **********************
	// VARIOUS HELPER METHODS
	// **********************
	
	function _getModuleObject($id)
	{
		$db =& JFactory::getDBO();
		
		$query 	= 'SELECT * FROM #__modules'
				. ' WHERE id = ' . (int)$id
				;
		$db->setQuery($query);
				
		return $db->loadObject();
	}

}
