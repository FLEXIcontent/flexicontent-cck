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
			$document->addScript('components/com_flexicontent/assets/js/jquery-1.4.min.js');
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
			foreach ($layout->positions as $pos) {
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
		$permission = FlexicontentHelperPerm::gerPerm();

		$user =& JFactory::getUser();
		$check = JAccess::check($user->id, 'core.admin', 'root.1');
		$CanCats 		= (!$check) ? $permission->CanCats : 1;
		$CanTypes 		= (!$check) ? $permission->Types : 1;
		$CanFields 		= (!$check) ? $permission->CanFields : 1;
		$CanTags 		= (!$check) ? $permission->CanTags : 1;
		$CanArchives 	= (!$check) ? $permission->CanArchives : 1;
		$CanFiles	 	= (!$check) ? $permission->CanFiles : 1;
		$CanStats	 	= (!$check) ? $permission->CanStats : 1;
		$CanRights	 	= (!$check) ? $permission->CanRights : 1;
		$CanTemplates	= (!$check) ? 0 : 1;
		if (!$CanTemplates) {
			$mainframe->redirect('index.php?option=com_flexicontent', JText::_( 'FLEXI_NO_ACCESS' ));
		}
		
		//Create Submenu
		JSubMenuHelper::addEntry( JText::_( 'FLEXI_HOME' ), 'index.php?option=com_flexicontent');
		JSubMenuHelper::addEntry( JText::_( 'FLEXI_ITEMS' ), 'index.php?option=com_flexicontent&view=items');
		if ($CanTypes)		JSubMenuHelper::addEntry( JText::_( 'FLEXI_TYPES' ), 'index.php?option=com_flexicontent&view=types');
		if ($CanCats) 		JSubMenuHelper::addEntry( JText::_( 'FLEXI_CATEGORIES' ), 'index.php?option=com_flexicontent&view=categories');
		if ($CanFields) 	JSubMenuHelper::addEntry( JText::_( 'FLEXI_FIELDS' ), 'index.php?option=com_flexicontent&view=fields');
		if ($CanTags) 		JSubMenuHelper::addEntry( JText::_( 'FLEXI_TAGS' ), 'index.php?option=com_flexicontent&view=tags');
		if ($CanArchives) 	JSubMenuHelper::addEntry( JText::_( 'FLEXI_ARCHIVE' ), 'index.php?option=com_flexicontent&view=archive');
		if ($CanFiles) 		JSubMenuHelper::addEntry( JText::_( 'FLEXI_FILEMANAGER' ), 'index.php?option=com_flexicontent&view=filemanager');
		if ($CanTemplates) 	JSubMenuHelper::addEntry( JText::_( 'FLEXI_TEMPLATES' ), 'index.php?option=com_flexicontent&view=templates', true);
		if ($CanStats)		JSubMenuHelper::addEntry( JText::_( 'FLEXI_STATISTICS' ), 'index.php?option=com_flexicontent&view=stats');

		//create the toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_EDIT_TEMPLATE' ), 'templates' );
		JToolBarHelper::apply();
		JToolBarHelper::save();
		JToolBarHelper::cancel();
		
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
