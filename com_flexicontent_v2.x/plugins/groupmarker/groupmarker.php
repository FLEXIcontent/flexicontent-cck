<?php
defined("_JEXEC") or die("Restricted Access");

class plgFlexicontent_fieldsGroupmarker extends JPlugin{

	function plgFlexicontent_fieldsGroupmarker( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_groupmarker', JPATH_ADMINISTRATOR);
	}


	function onDisplayField(&$field, $item)
	{
		if ($field->field_type != 'groupmarker') return;
		
		$marker_type   = $field->parameters->get( 'marker_type' ) ;
		$cont_label    = $field->parameters->get( 'cont_label' ) ;
		$cont_cssclass = $field->parameters->get( 'cont_cssclass' ) ;
		
		$field->html = '';
		switch ($marker_type) {
			case 'tabset_start':
				$field->html .= "<div style='margin-top:24px; width:100%; float:left; clear:both;'></div>\n";
				$field->html .= "<!-- tabber start --><div class='fctabber' class='".$cont_cssclass."' >\n";
				break;
			case 'tab_open':
				$field->html .= "<div class='tabbertab' style='float:left;'>\n";
				$field->html .= " <h3>".JText::_( $cont_label )."</h3>\n";   // Current TAB LABEL
				$field->html .= $cont_cssclass? " <div class='".$cont_cssclass."'></div>\n" : " <div style='border:0px!important; margin:0px!important; padding:0px!important;'></div>\n";
				break;
			case 'tab_close':
				$field->html .= " </div>\n";      // Close content's container (used to apply a css class)
				break;
			case 'tabset_end':
				$field->html .= "</div>\n";       // Close Tabset
				break;
			case 'fieldset_open':
				$field->html .= "<div style='margin-top:24px; width:100%; float:left; clear:both;'></div>\n";
				$field->html .= "<fieldset class='".$cont_cssclass."' style='margin:0px 1% 0px 1%; min-width:96%; float:left; clear:both;'>\n";
				$field->html .= " <legend>".JText::_( $cont_label )."</legend>\n";
				$field->html .= $cont_cssclass? " <div class='".$cont_cssclass."'></div>\n" : " <div style='border:0px!important; margin:0px!important; padding:0px!important;'></div>\n";
				break;
			case 'fieldset_close':
				$field->html .= " </div>\n";      // Close content's container (used to apply a css class)
				$field->html .= "</fieldset>\n";  // Close Fieldset
				break;
		}
	}
	
}
?>