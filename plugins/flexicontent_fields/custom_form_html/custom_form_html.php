<?php
/**
 * @package         FLEXIcontent
 * @version         3.4
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2020, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined( '_JEXEC' ) or die( 'Restricted access' );
JLoader::register('FCField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php');

class plgFlexicontent_fieldsCustom_form_html extends FCField
{
	static $field_types = null; // Automatic, do not remove since needed for proper late static binding, define explicitely when a field can render other field types
	var $task_callable = null;  // Field's methods allowed to be called via AJAX

	// ***
	// *** CONSTRUCTOR
	// ***

	public function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
	}


	public function onDisplayFieldValue(&$field, $item, $values = null, $prop = 'display')
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		// Nothing to display
	}


	// ***
	// *** DISPLAY methods, item form & frontend views
	// ***

	// Method to create field's HTML display for item form
	public function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

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
				if ( !isset($tabCnt[$tabSetCur]) ) $field->html .= "WARNING: TAB-set is misconfigured, TAB OPEN field encountered, before it a TAB-SET START field is needed";
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
			case 'custom_html':
				$field->html .= $custom_html_sep;
				break;
		}
	}

}
?>