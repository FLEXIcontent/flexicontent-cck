<?php
defined("_JEXEC") or die("Restricted Access");

class plgFlexicontent_fieldsCoreprops extends JPlugin
{
	static $field_types = array('coreprops');
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsCoreprops( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_coreprops', JPATH_ADMINISTRATOR);
	}
	
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$props_type     = $field->parameters->get( 'props_type' ) ;
		
		$field->html = '';
	}
	
}
?>