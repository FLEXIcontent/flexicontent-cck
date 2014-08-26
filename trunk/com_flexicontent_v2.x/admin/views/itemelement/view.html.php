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
 * View class for the itemelement screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewItemelement extends JViewLegacy {

	function display($tpl = null)
	{
		global $globalcats;
		$app = JFactory::getApplication();
		$option = JRequest::getVar('option');

		//initialise variables
		$db = JFactory::getDBO();
		$document	= JFactory::getDocument();
		$template = $app->isSite() ? (!FLEXI_J16GE ? 'khepri' : (FLEXI_J30GE ? 'hathor' : 'bluestork')) : $app->getTemplate();
		
		JHTML::_('behavior.tooltip');
		JHTML::_('behavior.modal');

		//get var
		$filter_order     = $app->getUserStateFromRequest( $option.'.itemelement.filter_order', 	'filter_order', 	'i.ordering'	, 'cmd' );
		$filter_order_Dir = $app->getUserStateFromRequest( $option.'.itemelement.filter_order_Dir',	'filter_order_Dir',	''				, 'word' );
		$filter_state     = $app->getUserStateFromRequest( $option.'.itemelement.filter_state', 	'filter_state', 	'*'				, 'word' );
		$filter_cats      = $app->getUserStateFromRequest( $option.'.itemelement.filter_cats', 		'filter_cats', 		0, 				'int' );
		$filter_type      = $app->getUserStateFromRequest( $option.'.itemelement.filter_type', 		'filter_type', 		0, 				'int' );
		if (FLEXI_FISH || FLEXI_J16GE) {
			$filter_lang = $app->getUserStateFromRequest( $option.'.itemelement.filter_lang', 	'filter_lang', 		'', 			'cmd' );
		}
		$search = $app->getUserStateFromRequest( $option.'.itemelement.search', 			'search', 			'', 'string' );
		$search = FLEXI_J16GE ? $db->escape( trim(JString::strtolower( $search ) ) ) : $db->getEscaped( trim(JString::strtolower( $search ) ) );

		//prepare the document
		$document->setTitle(JText::_( 'FLEXI_SELECTITEM' ));
		$document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/flexicontentbackend.css');
		if      (FLEXI_J30GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j3x.css');
		else if (FLEXI_J16GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j25.css');
		else                  $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j15.css');
		$document->addStyleSheet(JURI::root().'administrator/templates/'.$template.(FLEXI_J16GE ? '/css/template.css': '/css/general.css'));

		//Get data from the model
		$rows     = $this->get( 'Data');
		$types		= $this->get( 'Typeslist' );
		$pageNav 	= $this->get( 'Pagination' );

		if (FLEXI_FISH || FLEXI_J16GE) {
			$langs = FLEXIUtilities::getLanguages('code');
		}
		
		// build the categories select list for filter
		$categories = $globalcats;
		$lists['filter_cats'] = flexicontent_cats::buildcatselect($categories, 'filter_cats', $filter_cats, 2, 'class="inputbox" size="1" onchange="submitform( );"', $actions_allowed=array('core.create'), true);

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;
		
		$ordering = ($lists['order'] == 'i.ordering');

		//build type select list
		$lists['filter_type'] = flexicontent_html::buildtypesselect($types, 'filter_type', $filter_type, true, 'class="inputbox" size="1" onchange="submitform( );"', 'filter_type');

		// search filter
		$lists['search']= $search;
		
		$state[] = JHTML::_('select.option',  '', JText::_( 'FLEXI_SELECT_STATE' ) );
		$state[] = JHTML::_('select.option',  'P', JText::_( 'FLEXI_PUBLISHED' ) );
		$state[] = JHTML::_('select.option',  'U', JText::_( 'FLEXI_UNPUBLISHED' ) );
		$state[] = JHTML::_('select.option',  'PE', JText::_( 'FLEXI_PENDING' ) );
		$state[] = JHTML::_('select.option',  'OQ', JText::_( 'FLEXI_TO_WRITE' ) );
		$state[] = JHTML::_('select.option',  'IP', JText::_( 'FLEXI_IN_PROGRESS' ) );
		$state[] = JHTML::_('select.option',  'A', JText::_( 'FLEXI_ARCHIVED' ) );

		$lists['state'] = JHTML::_('select.genericlist',   $state, 'filter_state', 'class="inputbox" size="1" onchange="submitform( );"', 'value', 'text', $filter_state );

		if (FLEXI_FISH || FLEXI_J16GE) {
			//build languages filter
			$lists['filter_lang'] = flexicontent_html::buildlanguageslist('filter_lang', 'class="inputbox" onchange="submitform();"', $filter_lang, 2);
		}

		//assign data to template
		if (FLEXI_FISH || FLEXI_J16GE) {
			$this->assignRef('langs'    , $langs);
		}
		$this->assignRef('lists'      	, $lists);
		$this->assignRef('rows'      	, $rows);
		$this->assignRef('pageNav' 		, $pageNav);
		$this->assignRef('ordering'		, $ordering);
		$this->assignRef('filter_cats'	, $filter_cats);

		parent::display($tpl);
	}

}
?>