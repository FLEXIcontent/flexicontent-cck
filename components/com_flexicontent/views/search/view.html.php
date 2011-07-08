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

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die( 'Restricted access' );

jimport( 'joomla.application.component.view');

/**
 * HTML View class for the FLEXIcontent component
 *
 * @static
 * @package		Joomla
 * @subpackage	Weblinks
 * @since 1.0
 */
class FLEXIcontentViewSearch extends JView
{
	function display($tpl = null)
	{
		$mainframe = &JFactory::getApplication();

		require_once(JPATH_COMPONENT.DS.'helpers'.DS.'search.php' );

		// Initialize some variables
		$pathway  =& $mainframe->getPathway();
		$uri      =& JFactory::getURI();
		$dispatcher = & JDispatcher::getInstance();

		$error	= '';
		$rows	= null;
		$total	= 0;

		// Get some data from the model
		$areas      = &$this->get('areas');
		$state 		= &$this->get('state');
		$searchword = $state->get('keyword');

		//$params = &$mainframe->getParams();
		$params = JComponentHelper::getParams('com_flexicontent');
		//$params->bind($params->_raw);
		$typeid_for_advsearch = $params->get('typeid_for_advsearch');

		//require_once(JPATH_COMPONENT.DS.'classes'.DS.'flexicontent.fields.php');
		JRequest::setVar('typeid', $typeid_for_advsearch, '', 'int');
		if(!($itemmodel = $this->getModel('items'))) {
			require_once(JPATH_COMPONENT.DS.'models'.DS.'items.php');
			$itemmodel = new FlexicontentModelItems();
		}
		$item = &$itemmodel->getItem();

		$fields			= & $itemmodel->getAdvSearchFields($typeid_for_advsearch);
		// Add html to field object trought plugins
		foreach ($fields as $field) {
			$field->parameters->set( 'use_html', 0 );
			$field->parameters->set( 'allow_multiple', 0 );
			if( ($field->field_type == 'title') || ($field->field_type == 'maintext') || ($field->field_type == 'textarea')) {
				$field->field_type = 'text';
			}
			$label = $field->label;
			$fieldsearch = JRequest::getVar($field->name, array());
			//$fieldsearch = $mainframe->getUserStateFromRequest( 'flexicontent.serch.'.$field->name, $field->name, array(), 'array' );
			$field->value = isset($fieldsearch[0])?$fieldsearch:array();
			$results = $dispatcher->trigger('onAdvSearchDisplayField', array( &$field, &$item ));
			$field->label = $label;
		}
		//FlexicontentFields::getItemFields();
		$menus	= &JSite::getMenu();
		$menu	= $menus->getActive();

		// because the application sets a default page title, we need to get it
		// right from the menu item itself
		if (is_object( $menu )) {
			$menu_params = new JParameter( $menu->params );
			if (!$menu_params->get( 'page_title')) {
				$params->set('page_title',	JText::_( 'Search' ));
			}
		} else {
			$params->set('page_title',	JText::_( 'Search' ));
		}

		$document	= &JFactory::getDocument();
		$document->setTitle( $params->get( 'page_title' ) );

		// Get the parameters of the active menu item
		$params	= &$mainframe->getParams();
		$lists = array();
		if($show_searchordering = $params->get('show_searchordering', 1)) {
			$default_searchordering = $params->get('default_searchordering', 'newest');
			// built select lists
			$orders = array();
			$orders[] = JHTML::_('select.option',  'newest', JText::_( 'Newest first' ) );
			$orders[] = JHTML::_('select.option',  'oldest', JText::_( 'Oldest first' ) );
			$orders[] = JHTML::_('select.option',  'popular', JText::_( 'Most popular' ) );
			$orders[] = JHTML::_('select.option',  'alpha', JText::_( 'Alphabetical' ) );
			$orders[] = JHTML::_('select.option',  'category', JText::_( 'Section/Category' ) );
			$lists['ordering'] = JHTML::_('select.genericlist',   $orders, 'ordering', 'class="inputbox"', 'value', 'text', $state->get('ordering', $default_searchordering) );
		}
		if($show_searchphrase = $params->get('show_searchphrase', 1)) {
			$default_searchphrase = $params->get('default_searchphrase', 'all');
			$searchphrases 		= array();
			$searchphrases[] 	= JHTML::_('select.option',  'all', JText::_( 'All words' ) );
			$searchphrases[] 	= JHTML::_('select.option',  'any', JText::_( 'Any words' ) );
			$searchphrases[] 	= JHTML::_('select.option',  'exact', JText::_( 'Exact phrase' ) );
			$lists['searchphrase' ]= JHTML::_('select.radiolist',  $searchphrases, 'searchphrase', '', 'value', 'text', $state->get('match', $default_searchphrase) );
		}
		if($show_operator = $params->get('show_operator', 1)) {
			$default_operator = $params->get('default_operator', 'OR');
			$operators 		= array();
			$operators[] 	= JHTML::_('select.option',  'OR', JText::_( 'OR' ) );
			$operators[] 	= JHTML::_('select.option',  'AND', JText::_( 'AND' ) );
			$lists['operator']= JHTML::_('select.genericlist',  $operators, 'operator', '', 'value', 'text', $state->get('logic', $default_operator) );
		}
		// log the search
		FLEXIadvsearchHelper::logSearch( $searchword);

		//limit searchword

		if(FLEXIadvsearchHelper::limitSearchWord($searchword)) {
			$error = JText::_( 'SEARCH_MESSAGE' );
		}

		//sanatise searchword
		if(FLEXIadvsearchHelper::santiseSearchWord($searchword, $state->get('match'))) {
			$error = JText::_( 'IGNOREKEYWORD' );
		}

		if (!$searchword && count( JRequest::get('post') ) ) {
			//$error = JText::_( 'Enter a search keyword' );
		}

		// put the filtered results back into the model
		// for next release, the checks should be done in the model perhaps...
		$state->set('keyword', $searchword);

		if(!$error)
		{
			$results	= &$this->get('data' );
			$total		= &$this->get('total');
			$pagination	= &$this->get('pagination');

			//require_once (JPATH_SITE.DS.'components'.DS.'com_content'.DS.'helpers'.DS.'route.php');
			require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');

			for ($i=0; $i < count($results); $i++) {
				$row = &$results[$i]->text;
				if($searchword) {
					if ($state->get('match') == 'exact') {
						$searchwords = array($searchword);
						$needle = $searchword;
					}else{
						$searchwords = preg_split("/\s+/u", $searchword);
						$needle = $searchwords[0];
					}

					$row = FLEXIadvsearchHelper::prepareSearchContent( $row, 200, $needle );
					$searchwords = array_unique( $searchwords );
					$searchRegex = '#(';
					$x = 0;
					foreach ($searchwords as $k => $hlword) {
						$searchRegex .= ($x == 0 ? '' : '|');
						$searchRegex .= preg_quote($hlword, '#');
						$x++;
					}
					$searchRegex .= ')#iu';

					$row = preg_replace($searchRegex, '<span class="highlight">\0</span>', $row );
				}
				$results[$i]->text = str_replace('[span=highlight]', '<span class="highlight">', $results[$i]->text);
				$results[$i]->text = str_replace('[/span]', '</span>', $results[$i]->text);
				$results[$i]->text = str_replace('[br /]', '<br />', $results[$i]->text);
				$result =& $results[$i];
				if ($result->created) {
					$created = JHTML::Date( $result->created );
				}else {
					$created = '';
				}

			    $result->created	= $created;
			    $result->count		= $i + 1;
			}
		}
		$this->result	= JText::sprintf( 'FLEXI_TOTALRESULTSFOUND', $total );

		$this->assignRef('pagination',  $pagination);
		$this->assignRef('fields',		$fields);
		$this->assignRef('results',		$results);
		$this->assignRef('lists',		$lists);
		$this->assignRef('params',		$params);

		$this->assign('ordering',		$state->get('ordering'));
		$this->assign('searchword',		$searchword);
		$this->assign('searchphrase',	$state->get('match'));
		$this->assign('searchareas',	$areas);

		$this->assign('total',			$total);
		$this->assign('error',			$error);
		$this->assign('action', 	    $uri->toString());

		parent::display($tpl);
	}
}
