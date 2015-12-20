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
		// Initialise variables
		$app = JFactory::getApplication();
		$option   = JRequest::getVar('option');
		$db       = JFactory::getDBO();
		$document = JFactory::getDocument();
		$user     = JFactory::getUser();
		
		$use_jquery_sortable = true;
		$type    = JRequest::getVar('type',  'items', '', 'word');
		$folder  = JRequest::getVar('folder',  'default', '', 'cmd');
		$ismodal = JRequest::getVar('ismodal',  'default', '', 'int');
		
		FLEXIUtilities::loadTemplateLanguageFile( $folder );

		//Get data from the model
		$layout  = $this->get( 'Data');
		if (!$layout)
		{
			$app->redirect('index.php?option=com_flexicontent', JText::_( 'Template not found: <b>' ).JRequest::getVar('folder',  'default', '', 'cmd').'</b>');
		}
		$conf    = $this->get( 'LayoutConf');
		
		$fields  = $this->get( 'Fields');
		$fbypos  = $this->get( 'FieldsByPositions');
		$used    = $this->get( 'UsedFields');
		
		$contentTypes = $this->get( 'TypesList' );
		//$fieldTypes = $this->get( 'FieldTypesList' );
		$fieldTypes = flexicontent_db::getFieldTypes($_grouped = true, $_usage=false, $_published=false);  // Field types with content type ASSIGNMENT COUNTING
		
		
		// Create CONTENT TYPE SELECTOR
		foreach ($fields as $field) {
			$field->type_ids = !empty($field->reltypes)  ?  explode("," , $field->reltypes)  :  array();
		}
		$options = array();
		$options[] = JHTML::_('select.option',  '',  JText::_( 'FLEXI_ALL' ) );
		foreach ($contentTypes as $contentType) {
			$options[] = JHTML::_('select.option', $contentType->id, JText::_( $contentType->name ) );
		}
		$fieldname = $elementid = 'content_type__au__';
		$attribs = ' onchange="filterFieldList(\'%s\', \'%s\', \'%s\');" class="use_select2_lib" ';
		$content_type_select = JHTML::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', '', $elementid );
		
		
		// Create FIELD TYPE SELECTOR
		$ALL = mb_strtoupper(JText::_( 'FLEXI_ALL' ), 'UTF-8') . ' : ';
		$fftypes = array();
		$fftypes[] = array('value'=>'', 'text'=>JText::_( 'FLEXI_ALL' ) );
		//$fftypes[] = array('value'=>'BV', 'text'=>$ALL . JText::_( 'FLEXI_BACKEND_FIELDS' ) );
		//$fftypes[] = array('value'=>'C',  'text'=>$ALL . JText::_( 'FLEXI_CORE_FIELDS' ) );
		//$fftypes[] = array('value'=>'NC', 'text'=>$ALL . JText::_( 'FLEXI_NON_CORE_FIELDS' ));
		foreach ($fieldTypes as $field_group => $ft_types) {
			$fftypes[] = $field_group;
			foreach ($ft_types as $field_type => $ftdata) {
				$fftypes[] = array('value'=>$ftdata->field_type, 'text'=>$ftdata->friendly);
			}
			$fftypes[] = '';
		}
		$fieldname = $elementid = 'field_type__au__';
		$attribs = ' class="use_select2_lib" onchange="filterFieldList(\'%s\', \'%s\', \'%s\');"';
		$field_type_select = flexicontent_html::buildfieldtypeslist($fftypes, $fieldname, '', ($_grouped ? 1 : 0), $attribs, $elementid);
		
		
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
		
		
		
		// **************************
		// Add css and js to document
		// **************************
		
		flexicontent_html::loadFramework('select2');
		JHTML::_('behavior.tooltip');
		
		$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VERSION);
		$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VERSION);
		
		
		
		// *****************************
		// Get user's global permissions
		// *****************************
		
		$perms = FlexicontentHelperPerm::getPerm();

		if (!$perms->CanTemplates) {
			$app->redirect('index.php?option=com_flexicontent', JText::_( 'FLEXI_NO_ACCESS' ));
		}
		
		
		
		// ************************
		// Create Submenu & Toolbar
		// ************************
		
		// Create Submenu (and also check access to current view)
		FLEXISubmenu('CanTemplates');

		//create the toolbar
		$bar = JToolBar::getInstance('toolbar');
		JToolBarHelper::title( JText::_( 'FLEXI_EDIT_TEMPLATE' ), 'templates' );
		if (!$ismodal) {
			JToolBarHelper::apply('templates.apply');
			JToolBarHelper::save('templates.save');
			JToolBarHelper::cancel('templates.cancel');
		} else {
			JToolBarHelper::apply('templates.apply_modal');
			echo $bar->render();
		}
		
		
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