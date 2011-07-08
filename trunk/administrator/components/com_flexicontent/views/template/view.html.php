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

jimport( 'joomla.application.component.view');

/**
 * View class for the FLEXIcontent templates screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewTemplate extends JView {

	function display($tpl = null)
	{
		global $mainframe, $option;

		//initialise variables
		$db  		= & JFactory::getDBO();
		$document	= & JFactory::getDocument();
		$user 		= & JFactory::getUser();
		
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
					$jssort[] = 'results('.$k.',\''.$v.'\')';
				}
			}
			$positions = implode(',', $idsort);
			
			$jssort = implode("; ", $jssort);
			$moosort = implode("','", $sort);
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
			$document->addScript( JURI::base().'components/com_flexicontent/assets/js/sortables.js' );
			$document->addScriptDeclaration( $js );
		}

		JHTML::_('behavior.tooltip');
		JHTML::_('behavior.modal');

		//add css and submenu to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');

		FLEXISubmenu('CanTemplates');

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