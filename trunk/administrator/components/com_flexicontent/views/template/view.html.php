<?php
/**
 * @version 1.5 stable $Id$
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
		$mainframe = JFactory::getApplication();
		$option    = JRequest::getVar('option');
		$db       = JFactory::getDBO();
		$document = JFactory::getDocument();
		$user     = JFactory::getUser();
		
		FLEXI_J30GE ? JHtml::_('behavior.framework') : JHTML::_('behavior.mootools');
		$use_jquery_sortable = true; //FLEXI_J16GE ? true : false;
		
		if ($use_jquery_sortable) {
			flexicontent_html::loadJQuery();
		} else {
			// mootools sortable
			$document->addScript( JURI::base().'components/com_flexicontent/assets/js/sortables.js' );
		}
		
		$type 	= JRequest::getVar('type',  'items', '', 'word');
		$folder = JRequest::getVar('folder',  'default', '', 'cmd');
		
		if (FLEXI_FISH || FLEXI_J16GE)
			FLEXIUtilities::loadTemplateLanguageFile( $folder );

		//Get data from the model
		$layout  = $this->get( 'Data');
		$fields  = $this->get( 'Fields');
		$fbypos  = $this->get( 'FieldsByPositions');
		$used    = $this->get( 'UsedFields');

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
					$jssort[] = $use_jquery_sortable  ?  'storeordering(jQuery("#sortable-'.$v.'"))'  :  'results('.$k.',\''.$v.'\')';
				}
			}
			$positions = implode(',', $idsort);
			
			$jssort = implode("; ", $jssort);
			$sortable_ids = "#".implode(",#", $sort);
			
			if ($use_jquery_sortable) {
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
			} else {
				$js = "
				var my = '';
				window.addEvent('domready', function(){
					var mySortables = new Sortables('.positions', {
						constrain: false,
						clone: false,
						revert: true,
						onComplete: storeordering
					});
					my = mySortables;
					storeordering();

					var slideaccess = new Fx.Slide('propvisible');
					var slidenoaccess = new Fx.Slide('propnovisible');
					var legend = $$('fieldset.tmplprop legend');
					slidenoaccess.hide();
					legend.addEvent('click', function(ev) {
						legend.toggleClass('open');
						slideaccess.toggle();
						slidenoaccess.toggle();
					});


				});

				function results(i, field) {
					var res = my.serialize(i, function(element, index){
					return element.getProperty('id').replace('field_','');
				}).join(',');
					$(field).value = res;
				}
				";
			}
			$document->addScriptDeclaration( $js );
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
		if (FLEXI_J16GE) {
			JToolBarHelper::apply('templates.apply');
			JToolBarHelper::save('templates.save');
			JToolBarHelper::cancel('templates.cancel');
		} else {
			JToolBarHelper::apply();
			JToolBarHelper::save();
			JToolBarHelper::cancel();
		}
		
		//assign data to template
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

		parent::display($tpl);
	}
}
?>