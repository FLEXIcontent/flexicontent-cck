<?php
/**
 * @version 1.5 stable $Id: view.html.php 1577 2012-12-02 15:10:44Z ggppdk $
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

jimport('joomla.application.component.view');

/**
 * View class for the FLEXIcontent templates screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewTemplate extends JViewLegacy {

	function display($tpl = null)
	{
		
		//initialise variables
		$app = JFactory::getApplication();
		$option   = JRequest::getVar('option');
		$db       = JFactory::getDBO();
		$document = JFactory::getDocument();
		$user     = JFactory::getUser();
		
		$use_jquery_sortable = true;
		$type 	= JRequest::getVar('type',  'items', '', 'word');
		$folder = JRequest::getVar('folder',  'default', '', 'cmd');
		
		FLEXIUtilities::loadTemplateLanguageFile( $folder );

		//Get data from the model
		$layout  = $this->get( 'Data');
		$conf    = $this->get( 'LayoutConf');
		
		$fields  = $this->get( 'Fields');
		$fbypos  = $this->get( 'FieldsByPositions');
		$used    = $this->get( 'UsedFields');
		
		$contentTypes = $this->get( 'ContentTypesList' );
		$fieldTypes   = $this->get( 'FieldTypesList' );
		
		
		// Create CONTENT TYPE SELECTOR
		foreach ($fields as $field) {
			$field->type_ids = !empty($field->reltypes)  ?  explode("," , $field->reltypes)  :  array();
		}
		$options = array();
		$options[] = JHTML::_('select.option',  '',  JText::_( 'FLEXI_ALL' ) );
		foreach ($contentTypes as $contentType) {
			$options[] = JHTML::_('select.option', $contentType->id, JText::_( $contentType->name ) );
		}
		$fieldname =  $elementid = 'content_type__au__';
		$attribs = ' onchange="filterFieldList(\'%s\', \'%s\', \'%s\');" class="fcfield_selectval" ';
		$content_type_select = JHTML::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', '', $elementid );
		
		
		// Create FIELD TYPE SELECTOR
		$options = array();
		$options[] = JHTML::_('select.option',  '',  JText::_( 'FLEXI_ALL' ) );
		foreach ($fieldTypes as $fieldType) {
			$options[] = JHTML::_('select.option', $fieldType->type_name, $fieldType->field_name );
		}
		$fieldname =  $elementid = 'field_type__au__';
		$attribs = ' onchange="filterFieldList(\'%s\', \'%s\', \'%s\');" class="fcfield_selectval" ';
		$field_type_select = JHTML::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', '', $elementid );
		
		
		if (isset($layout->positions)) {
			$sort = array();
			$jssort = array();
			$idsort = array();
			$sort[0] = 'sortablecorefields';
			$sort[1] = 'sortableuserfields';
			$i = 2;
			$count=-1;
			foreach ($layout->positions as $pos) {
				$count++;
				if ( isset($layout->attributes[$count]) && isset($layout->attributes[$count]['readonly']) ) {
					continue;
				}
				$sort[$i] 	= 'sortable-'.$pos;
				$idsort[$i] = $pos;
				$i++;
			}
			foreach ($idsort as $k => $v) {
				if ($k > 1) {
					$jssort[] = 'storeordering(jQuery("#sortable-'.$v.'"))';
				}
			}
			$positions = implode(',', $idsort);
			
			$jssort = implode("; ", $jssort);
			$sortable_ids = "#".implode(",#", $sort);
			
			$js = "
			jQuery(function() {
				my = jQuery( \"$sortable_ids\" ).sortable({
					connectWith: \"".$sortable_ids."\",
					update: function(event, ui) {
						if(ui.sender) {
							storeordering(jQuery(ui.sender));
						}else{
							storeordering(jQuery(ui.item).parent());
						}
					}
				});
				initordering();
			});
			function storeordering(parent_element) {
				hidden_id = '#'+jQuery.trim(parent_element.attr('id').replace('sortable-',''));
				fields = new Array();
				i = 0;
				parent_element.children('li').each(function(){
					fields[i++] = jQuery(this).attr('id').replace('field_', '');
				});
				jQuery(hidden_id).val(fields.join(','))
			}
			";
			
			$js .= '
			var fieldListFilters = new Array( "content_type", "field_type" );
			function filterFieldList (containerID, method, group)
			{
				var needed_classes = "";
				for (i=0; i<fieldListFilters.length; i++)
				{
					filter_name = fieldListFilters[i];
					
					var filter_val = jQuery("#" + filter_name + "_" + group).val();
					if (filter_val) {
						needed_classes += "."+filter_name+"_"+filter_val;
					}
				}
				
				if (needed_classes) {
					(method=="hide") ?
						jQuery("#"+containerID).find("li").show().filter(":not("+needed_classes+")").hide() :
						jQuery("#"+containerID).find("li").css({"color":"red"}).filter(":not("+needed_classes+")").css({"color":"black"});
				} else {
					(method=="hide") ?
						jQuery("#"+containerID).find("li").show() :
						jQuery("#"+containerID).find("li").css({"color":"black"});
				}
			}
			
			';
			
			$document->addScriptDeclaration( $js );
		}
		
		
		JHTML::_('behavior.tooltip');
		JHTML::_('behavior.modal');

		//add css and submenu to document
		$document->addStyleSheet(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css');
		if      (FLEXI_J30GE) $document->addStyleSheet(JURI::base(true).'/components/com_flexicontent/assets/css/j3x.css');
		else if (FLEXI_J16GE) $document->addStyleSheet(JURI::base(true).'/components/com_flexicontent/assets/css/j25.css');
		
		$permission = FlexicontentHelperPerm::getPerm();
		if (!$permission->CanTemplates) {
			$app->redirect('index.php?option=com_flexicontent', JText::_( 'FLEXI_NO_ACCESS' ));
		}
		
		//Create Submenu
		FLEXISubmenu('CanTemplates');

		//create the toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_EDIT_TEMPLATE' ), 'templates' );
		JToolBarHelper::apply('templates.apply');
		JToolBarHelper::save('templates.save');
		JToolBarHelper::cancel('templates.cancel');
		
		
		// **********************************************************************************
		// Get Templates and apply Template Parameters values into the form fields structures 
		// **********************************************************************************
		
		if (FLEXI_J16GE) {
			$jform = new JForm('com_flexicontent.template.category', array('control' => 'jform', 'load_data' => true));
			$jform->load($layout->params);
			$layout->params = $jform;
			// ... values applied at the template form file
		} else {
			$layout->params->loadINI($row->params);
		}
		//print_r($layout);
		
		//assign data to template
		//print_r($conf);
		$this->assignRef('conf'   	  , $conf);
		$this->assignRef('layout'   	, $layout);
		$this->assignRef('fields'   	, $fields);
		$this->assignRef('user'     	, $user);
		$this->assignRef('type'     	, $type);
		$this->assignRef('folder'			, $folder);
		$this->assignRef('jssort'			, $jssort);
		$this->assignRef('positions'	, $positions);
		$this->assignRef('used'				, $used);
		$this->assignRef('fbypos'			, $fbypos);
		$this->assignRef('use_jquery_sortable' , $use_jquery_sortable);
		$this->assignRef('content_type_select' , $content_type_select );
		$this->assignRef('field_type_select' , $field_type_select );
		
		parent::display($tpl);
	}
}
?>