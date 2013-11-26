<?php
defined("_JEXEC") or die("Restricted Access");

class plgFlexicontent_fieldsGroupmarker extends JPlugin
{
	static $field_types = array('groupmarker');
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsGroupmarker( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_groupmarker', JPATH_ADMINISTRATOR);
	}
	
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		static $tabsetStack = array();
		
		static $tabSetCur = -1;
		static $tabSetCnt = -1;
		static $tabCnt = array();
		
		$marker_type     = $field->parameters->get( 'marker_type' ) ;
		$cont_label      = $field->parameters->get( 'cont_label' ) ;
		$cont_cssclass   = $field->parameters->get( 'cont_cssclass' ) ;
		$custom_html_sep = $field->parameters->get( 'custom_html_sep' ) ;
		
		$field->html = '';
		switch ($marker_type) {
			case 'tabset_start':
				array_push($tabsetStack, $tabSetCur);
				$tabSetCur = ++$tabSetCnt;
				if (!isset($tabCnt[$tabSetCur])) $tabCnt[$tabSetCur] = 0;
				$field->html .= "<div style='margin-top:24px; width:100%; float:left; clear:both;'></div>\n";
				$field->html .= "<!-- tabber start --><div class='fctabber ".$cont_cssclass."' id='grpmarker_tabset_".($tabSetCur)."'>\n";
				break;
			case 'tab_open':
				if (empty($cont_label)) $cont_label = "TAB LABEL NOT SET";
				$field->html .= " <div class='tabbertab' style='float:left;' id='grpmarker_tabset_".$tabSetCur."_tab_".($tabCnt[$tabSetCur]++)."'>\n";
				$field->html .= "  <h3 class='tabberheading'>".JText::_( $cont_label )."</h3>\n";   // Current TAB LABEL
				$field->html .= $cont_cssclass? "  <div class='".$cont_cssclass."'>\n" : " <div style='border:0px!important; margin:0px!important; padding:0px!important;'>\n";
				break;
			case 'tab_close':
				$field->html .= "  </div>\n";     // Close content's container (used to apply a css class)
				$field->html .= " </div>\n";      // Close Tab
				break;
			case 'tabset_end':
				$tabSetCur = array_pop($tabsetStack);
				$field->html .= "</div>\n";       // Close Tabset
				break;
			case 'fieldset_open':
				$field->html .= "<div style='margin-top:24px; width:100%; float:left; clear:both;'></div>\n";
				$field->html .= "<fieldset class='".$cont_cssclass."' style='margin:0px 1% 0px 1%; min-width:96%; float:left; clear:both;'>\n";
				$field->html .= " <legend>".JText::_( $cont_label )."</legend>\n";
				$field->html .= $cont_cssclass? " <div class='".$cont_cssclass."'>\n" : " <div style='border:0px!important; margin:0px!important; padding:0px!important;'>\n";
				break;
			case 'fieldset_close':
				$field->html .= " </div>\n";      // Close content's container (used to apply a css class)
				$field->html .= "</fieldset>\n";  // Close Fieldset
				break;
			case 'html_separator':
				$field->html .= $custom_html_sep;
				break;
		}
	}
	
}
?>