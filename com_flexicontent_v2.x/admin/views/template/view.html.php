<?php
/**
 * @version 1.5 stable $Id: view.html.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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

jimport( 'joomla.application.component.view');

/**
 * View class for the FLEXIcontent templates screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewTemplate extends JView {
	function display($tpl = null) {
		$mainframe = &JFactory::getApplication();
		$option = JRequest::getVar('option');

		//initialise variables
		$db  		= & JFactory::getDBO();
		$document	= & JFactory::getDocument();
		$user 		= & JFactory::getUser();
		if(!JPluginHelper::isEnabled('system', 'jquerysupport')) {
			JHTML::_('behavior.mootools');
			$document->addScript('components/com_flexicontent/assets/js/jquery-1.6.2.min.js');
		}
		$document->addCustomTag('<script>jQuery.noConflict();</script>');
		if(!isset($document->jquery_ui_core)){
			$document->addScript(JURI::base()."components/com_flexicontent/assets/js/jquery.ui.core.js");
			$document->jquery_ui_core = true;
		}
		if(!isset($document->jquery_ui_widget)){
			$document->addScript(JURI::base()."components/com_flexicontent/assets/js/jquery.ui.widget.js");
			$document->jquery_ui_widget = true;
		}
		if(!isset($document->jquery_ui_mouse)){
			$document->addScript(JURI::base()."components/com_flexicontent/assets/js/jquery.ui.mouse.js");
			$document->jquery_ui_mouse = true;
		}
		if(!isset($document->jquery_ui_sortable)){
			$document->addScript(JURI::base()."components/com_flexicontent/assets/js/jquery.ui.sortable.js");
			$document->jquery_ui_sortable = true;
		}
		
		$type 	= JRequest::getVar('type',  'items', '', 'word');
		$folder = JRequest::getVar('folder',  'default', '', 'cmd');
		
		if (FLEXI_FISH || FLEXI_J16GE)
			FLEXIUtilities::loadTemplateLanguageFile( $folder );

		//Get data from the model
		$layout    	= & $this->get( 'Data');
		$fields    	= & $this->get( 'Fields');
		$fbypos    	= & $this->get( 'FieldsByPositions');
		$used    	= & $this->get( 'UsedFields');

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
			$jqueyrsort = "#".implode(",#", $sort);

			$js = "
			jQuery(function() {
				my = jQuery( \"$jqueyrsort\" ).sortable({
					connectWith: \"".$jqueyrsort."\",
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
			$document->addScriptDeclaration($js);
		}
		JHTML::_('behavior.tooltip');
		JHTML::_('behavior.modal');

		//add css and submenu to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');
		$permission = FlexicontentHelperPerm::getPerm();

		if (!$permission->CanTemplates) {
			$mainframe->redirect('index.php?option=com_flexicontent', JText::_( 'FLEXI_NO_ACCESS' ));
		}
		
		//Create Submenu
		FLEXISubmenu('CanTemplates');

		//create the toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_EDIT_TEMPLATE' ), 'templates' );
		JToolBarHelper::apply('templates.apply');
		JToolBarHelper::save('templates.save');
		JToolBarHelper::cancel('templates.cancel');
		
		//assign data to template
		$this->assignRef('layout'   	, $layout);
		$this->assignRef('fields'   	, $fields);
		$this->assignRef('user'     	, $user);
		$this->assignRef('type'     	, $type);
		$this->assignRef('folder'		, $folder);
		$this->assignRef('jssort'		, $jssort);
		$this->assignRef('positions'	, $positions);
		$this->assignRef('used'			, $used);
		$this->assignRef('fbypos'		, $fbypos);

		parent::display($tpl);
	}
}
?>
