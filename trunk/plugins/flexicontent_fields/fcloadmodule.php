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
	function plgFlexicontent_fieldsFcloadmodule( &$subject, $params )
	{
		parent::__construct( $subject, $params );
        	JPlugin::loadLanguage('plg_flexicontent_fields_fcloadmodule', JPATH_ADMINISTRATOR);
	}
	function onAdvSearchDisplayField(&$field, &$item) {
		plgFlexicontent_fieldsFcloadmodule::onDisplayField($field, $item);
	}
	function onDisplayField(&$field, &$item)
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'fcloadmodule') return;
	}
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'fcloadmodule') return;

		global $mainframe, $addthis;
		
		// parameters shortcuts
		$module_method	= $field->parameters->get('module-method', 1);
		$mymodule		= $field->parameters->get('modules', '');
		$position		= $field->parameters->get('position', '');
		$style 			= $field->parameters->get('style', -2);
		
		$document		= &JFactory::getDocument();
		$display 		= '';
		$renderer		= $document->loadRenderer('module');
		$mparams		= array('style'=>$style);
		
		if ($module_method == 1) { // module case
			if (!$mymodule) { $field->{$prop} = 'Pauvre tâche !!!'; return; }
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
/*
echo '<xmp>';
print_r($mod);
echo '</xmp>';
*/
			$display = $renderer->render($mod, $mparams);

		} else { // position case		
			if (!$position) { $field->{$prop} = 'Erreur'; return; }
			foreach (JModuleHelper::getModules($position) as $mod)  {
				$display .= $renderer->render($mod, $mparams);
			}
		}
	
		$field->{$prop} = $display;
	}

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
