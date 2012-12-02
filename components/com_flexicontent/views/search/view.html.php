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
class FLEXIcontentViewSearch extends JViewLegacy
{
	function display($tpl = null)
	{
		$mainframe = &JFactory::getApplication();
		jimport( 'joomla.html.parameter' );
		require_once(JPATH_COMPONENT.DS.'helpers'.DS.'search.php' );

		// Initialize some variables
		$pathway  =& $mainframe->getPathway();
		$uri      =& JFactory::getURI();
		$dispatcher = & JDispatcher::getInstance();
		$document 	= & JFactory::getDocument();
		
		FLEXI_J30GE ? JHtml::_('behavior.framework') : JHTML::_('behavior.mootools');
		flexicontent_html::loadJQuery();
		$document->addScript( JURI::base().'components/com_flexicontent/assets/js/rounded-corners.js' );
		
		$error	= '';
		$rows	= null;
		$total	= 0;

		// Get some data from the model
		$areas	= & $this->get('areas');
		$state	= & $this->get('state');
		$searchword = $state->get('keyword');

		$params = &$mainframe->getParams();
		//$params->bind($params->_raw);
		//$typeid_for_advsearch = $params->get('typeid_for_advsearch');

		if (!$params->get('disablecss', '')) {
			$document->addStyleSheet($this->baseurl.'/components/com_flexicontent/assets/css/flexicontent.css');
			$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext {zoom:1;}</style><![endif]-->');
		}

		$searchkeywordlabel = $params->get('searchkeywordlabel', 'Search Keyword');
		//require_once(JPATH_COMPONENT.DS.'classes'.DS.'flexicontent.fields.php');
		//JRequest::setVar('typeid', $typeid_for_advsearch, '', 'int');
		
		if(!($itemmodel = @$this->getModel(FLEXI_ITEMVIEW))) {
			require_once(JPATH_COMPONENT.DS.'models'.DS.FLEXI_ITEMVIEW.'.php');
			$itemmodel = !FLEXI_J16GE ? new FlexicontentModelItems() : new FlexicontentModelItem();
		}
		
		// Dummy object for passing to onDisplayField of some fields
		$item = new stdClass;
		$item->version = 0;
		
		$search_fields = $params->get('search_fields', '');
		$search_fields = explode(",", $search_fields);
		$search_fields = "'".implode("','", array_unique($search_fields))."'";
		$fields			= & $itemmodel->getAdvSearchFields($search_fields);
		
		//Import fields
		JPluginHelper::importPlugin('flexicontent_fields');
		
		// Add html to field object trought plugins
		$custom = FLEXI_J16GE ? JRequest::getVar('custom', array()) : false;
		foreach ($fields as $field) {
			$field->parameters->set( 'use_html', 0 );
			$field->parameters->set( 'allow_multiple', 0 );
			/*if( ($field->field_type == 'title') || ($field->field_type == 'maintext') || ($field->field_type == 'textarea')) {
				$field->field_type = 'text';
			}*/
			$label = $field->label;
			$fieldsearch = JRequest::getVar('filter_'.$field->id, array(), 'array');
			//$fieldsearch = $mainframe->getUserStateFromRequest( 'flexicontent.search.'.'filter_'.$field->id, 'filter_'.$field->id, array(), 'array' );
			$field->value = isset($fieldsearch[0]) ? $fieldsearch : array();
			//echo "FIELD value: "; print_r($field->value);
			
			//$results = $dispatcher->trigger('onAdvSearchDisplayFilter', array( &$field, &$item ));
			$fieldname = $field->iscore ? 'core' : $field->field_type;
			FLEXIUtilities::call_FC_Field_Func($fieldname, 'onAdvSearchDisplayFilter', array( &$field, $field->value, 'searchForm'));
			
			$field->label = $label;
		}
		//FlexicontentFields::getItemFields();
		$menus	= &JSite::getMenu();
		$menu	= $menus->getActive();
		$document	= &JFactory::getDocument();
		
		
		// **********************
		// Calculate a page title
		// **********************
		
		// Verify menu item points to current FLEXIcontent object, IF NOT then overwrite page title and clear page class sufix
		if ( $menu && $menu->query['view'] != 'search' ) {
			$params->set('page_title',	'');
			$params->set('pageclass_sfx',	'');
		}
		
		// Set a page title if one was not already set
		$params->def('page_title',	JText::_( 'FLEXI_SEARCH' ));
		
		
		// *******************
		// Create page heading
		// *******************
		
		if ( !FLEXI_J16GE )
			$params->def('show_page_heading', $params->get('show_page_title'));  // J1.5: parameter name was show_page_title instead of show_page_heading
		else
			$params->def('show_page_title', $params->get('show_page_heading'));  // J2.5: to offer compatibility with old custom templates or template overrides
		
		// if above did not set the parameter, then default to NOT showing page heading (title)
		$params->def('show_page_heading', 0);
		$params->def('show_page_title', 0);
		
		// ... the page heading text
		$params->def('page_heading', $params->get('page_title'));    // J1.5: parameter name was show_page_title instead of show_page_heading
		$params->def('page_title', $params->get('page_heading'));    // J2.5: to offer compatibility with old custom templates or template overrides
		
		
		// ************************************************************
		// Create the document title, by from page title and other data
		// ************************************************************
		
		$doc_title = $params->get( 'page_title' );
		
		// Check and prepend or append site name
		if (FLEXI_J16GE) {  // Not available in J1.5
			// Add Site Name to page title
			if ($mainframe->getCfg('sitename_pagetitles', 0) == 1) {
				$doc_title = $mainframe->getCfg('sitename') ." - ". $doc_title ;
			}
			elseif ($mainframe->getCfg('sitename_pagetitles', 0) == 2) {
				$doc_title = $doc_title ." - ". $mainframe->getCfg('sitename') ;
			}
		}
		
		// Finally, set document title
		$document->setTitle($doc_title);
		

		// ************************
		// Set document's META tags
		// ************************
		
		// ** writting both old and new way as an example
		if (!FLEXI_J16GE) {
			if ($mainframe->getCfg('MetaTitle') == '1') 	$mainframe->addMetaTag('title', $params->get('page_title'));
		} else {
			if (JApplication::getCfg('MetaTitle') == '1') $document->setMetaData('title', $params->get('page_title'));
		}		
		
		
		// Get the parameters of the active menu item
		$params	= &$mainframe->getParams();
		$lists = array();
		
		// Get Content Types allowed for user selection in the Search Form
		$search_contenttypes = $params->get('contenttypes', array());
		if ( $search_contenttypes && !is_array($search_contenttypes) ) {
			$search_contenttypes = array($search_contenttypes);
		}
		
		if( $params->get('cantypes', 1) && count($search_contenttypes) )
		{
			$search_contenttypes_list = !count($search_contenttypes) ? "" : "'". implode("','", $search_contenttypes)."'";
			
			// Get Content Types currently selected in the Search Form
			$form_contenttypes = JRequest::getVar('contenttypes', array());
			if ( !$form_contenttypes || !count($form_contenttypes) ) {
				$form_contenttypes = $search_contenttypes;
			}
			
			$db =& JFactory::getDBO();
			$query = "SELECT id AS value, name AS text"
			. " FROM #__flexicontent_types"
			. " WHERE published = 1"
			. ($search_contenttypes_list ? " AND id IN (". $search_contenttypes_list .")"  :  "")
			. " ORDER BY name ASC, id ASC"
			;
			$db->setQuery($query);
			$types = $db->loadObjectList();
			
			//$lists['contenttypes'] = JHTML::_('select.genericlist', $types, 'contenttypes[]', 'multiple="true" size="5" style="min-width:186px;" ', 'value', 'text', $form_contenttypes, 'contenttypes');
			$fieldname = 'contenttypes[]';
			$lists['contenttypes'] = '';
			foreach($types as $type) {
				$checked = in_array($type->value, $form_contenttypes);
				$checked_attr = $checked ? 'checked=checked' : '';
				$checked_class = $checked ? 'highlight' : '';
				$lists['contenttypes'] .= '<label class="flexi_radiotab rc5 '.$checked_class.'" style="display:inline-block; white-space:nowrap;" for="fieldtype_'.$type->value.'">';
				$lists['contenttypes'] .= ' <input href="javascript:;" onclick="fc_toggleClass(this.parentNode, \'highlight\');" id="fieldtype_'.$type->value.'" type="checkbox" name="'.$fieldname.'" value="'.$type->value.'" '.$checked_attr.' />';
				$lists['contenttypes'] .= '<span style="float:left; display:inline-block;" >'.JText::_($type->text).'</span>';
				$lists['contenttypes'] .= '</label>';
			}
		}
		
		
		// FLEXIcontent Results Ordering
		if($orderby_override = $params->get('orderby_override', 1)) {
			$lists['orderby'] = flexicontent_html::ordery_selector( $params, 'searchForm', $autosubmit=0 );
		}
		
		// Non-FLEXIcontent Results Ordering
		if($show_searchordering = $params->get('show_searchordering', 1)) {
			$default_searchordering = $params->get('default_searchordering', 'newest');
			// built select lists
			$orders = array();
			$orders[] = JHTML::_('select.option',  'newest', JText::_( 'FLEXI_ADV_NEWEST_FIRST' ) );
			$orders[] = JHTML::_('select.option',  'oldest', JText::_( 'FLEXI_ADV_OLDEST_FIRST' ) );
			$orders[] = JHTML::_('select.option',  'popular', JText::_( 'FLEXI_ADV_MOST_POP' ) );
			$orders[] = JHTML::_('select.option',  'alpha', JText::_( 'FLEXI_ADV_ALPHA' ) );
			$orders[] = JHTML::_('select.option',  'category', JText::_( 'FLEXI_ADV_SEARCH_SEC_CAT' ) );
			$lists['ordering'] = JHTML::_('select.genericlist',   $orders, 'ordering', 'class="inputbox"', 'value', 'text', $state->get('ordering', $default_searchordering) );
		}		
		
		
		if($show_searchphrase = $params->get('show_searchphrase', 1)) {
			$default_searchphrase = $params->get('default_searchphrase', 'all');
			$searchphrase = JRequest::getVar('searchphrase', $default_searchphrase);
			$searchphrase_names = array('all'=>'FLEXI_ALL_WORDS', 'any'=>'FLEXI_ANY_WORDS', 'exact'=>'FLEXI_EXACT_PHRASE');
			$lists['searchphrase'] = '';
			foreach ($searchphrase_names as $searchphrase_value => $searchphrase_name) {
				$checked = $searchphrase_value == $searchphrase;
				$checked_attr = $checked ? 'checked=checked' : '';
				$checked_class = $checked ? 'highlight' : '';
				$lists['searchphrase'] .= '<label class="flexi_radiotab rc5 '.$checked_class.'" style="display:inline-block; white-space:nowrap;" for="searchphrase_'.$searchphrase_value.'">';
				$lists['searchphrase'] .= ' <input href="javascript:;" onclick="fc_toggleClassGrp(this.parentNode.parentNode, \'highlight\');" id="searchphrase_'.$searchphrase_value.'" type="radio" name="searchphrase" value="'.$searchphrase_value.'" '.$checked_attr.' />';
				$lists['searchphrase'] .=  '<span style="float:left; display:inline-block;" >'.JText::_($searchphrase_name).'</span>';
				$lists['searchphrase'] .= '</label>';
			}
		}
		if($show_filtersop = $params->get('show_filtersop', 1)) {
			$default_filtersop = $params->get('default_filtersop', 'all');
			$filtersop = JRequest::getVar('operator', $default_filtersop);
			$filtersop_arr		= array();
			$filtersop_arr[] = JHTML::_('select.option',  'all', JText::_( 'FLEXI_SEARCH_ALL' ) );
			$filtersop_arr[] = JHTML::_('select.option',  'any', JText::_( 'FLEXI_SEARCH_ANY' ) );
			$lists['filtersop']= JHTML::_('select.radiolist',  $filtersop_arr, 'filtersop', '', 'value', 'text', $filtersop );
		}
		
		
		
		if( $params->get('show_searchareas', 0) )
		{
			// Get Content Types currently selected in the Search Form
			$form_areas = JRequest::getVar('areas', array());
			if ( !$form_areas || !count($form_areas) ) {
				$form_areas = array('flexicontent');
			}
			
			//$lists['areas'] = JHTML::_('select.genericlist', $types, 'areas[]', 'multiple="true" size="5" style="min-width:186px;" ', 'value', 'text', $form_areas, 'areas');
			$fieldname = 'areas[]';
			$lists['areas'] = '';
			
			// DISABLE search areas 'content' and old 'flexisearch', TODO more for flexisearch
			unset($this->searchareas['search']['content']);
			unset($this->searchareas['search']['flexisearch']);
			
			foreach($areas['search'] as $area_name => $area_label) {
				$checked = in_array($area_name, $form_areas);
				$checked_attr = $checked ? 'checked=checked' : '';
				$checked_class = $checked ? 'highlight' : '';
				$lists['areas'] .= '<label class="flexi_radiotab rc5 '.$checked_class.'" style="display:inline-block; white-space:nowrap;" for="area_'.$area_name.'">';
				$lists['areas'] .= ' <input href="javascript:;" onclick="fc_toggleClass(this.parentNode, \'highlight\');" id="area_'.$area_name.'" type="checkbox" name="'.$fieldname.'" value="'.$area_name.'" '.$checked_attr.' />';
				$lists['areas'] .= '<span style="float:left; display:inline-block;" >'.JText::_($area_label).'</span>';
				$lists['areas'] .= '</label>';
			}
		}
		
		// log the search
		FLEXIadvsearchHelper::logSearch( $searchword);

		//limit searchword
		$min = $params->get('minchars', 3);
		$max = $params->get('maxchars', 20);
		if(FLEXIadvsearchHelper::limitSearchWord($searchword, $min, $max)) {
			$error = JText::sprintf( 'FLEXI_SEARCH_MESSAGE', $min, $max );
		}

		//sanatise searchword
		if(FLEXIadvsearchHelper::santiseSearchWord($searchword, $state->get('match'), $min)) {
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

			if ($state->get('match') == 'exact') {
				$searchwords = array($searchword);
				//$needle = $searchword;
			} else {
				$searchwords = preg_split("/\s+/u", $searchword);
				//print_r($searchwords);
			}
			
			for ($i=0; $i < count($results); $i++) {
				$row = &$results[$i]->text;
				if( strlen($searchwords[0]) )
				{
					$parts = FLEXIadvsearchHelper::prepareSearchContent( $row, 200, $searchwords );
					//if( count($parts)>1 ) { echo "<pre>"; print_r($parts); exit;}
					
					foreach ($parts as $word_found => $part) {
						if (!$word_found) continue;
						$searchRegex = '#('. preg_quote($word_found, '#') .')#iu';
						$parts[$word_found] = preg_replace($searchRegex, '<span class="highlight">\0</span>', $part );
					}
					$row = implode($parts, " <br/> ");
					
					// Add some message about matches
					if ( $state->get('match')=='any' ) {
						$text_search_header = "<u><b>".JText::sprintf('Text Search matched %d %% (%d out of %d words)', count($parts)/count($searchwords) * 100, count($parts), count($searchwords)).": </b></u><br/>";
					} else if ( $state->get('match')=='all' ) {
						$text_search_header = "<u><b>".JText::sprintf('Text Search (all %d words required)', count($searchwords)).": </b></u><br/>";
					} else {
						$text_search_header = "<u><b>".JText::_('Text Search (exact phrase)').": </b></u><br/>";
					}
					$results[$i]->text = $text_search_header . $results[$i]->text;
				}
				
				if ( !empty($results[$i]->fields_text) ) {
					$results[$i]->text .= "<br/><u><b>".JText::_('Attribute filters matched')." : </b></u>";
					$results[$i]->fields_text = str_replace('[span=highlight]', '<span class="highlight">', $results[$i]->fields_text);
					$results[$i]->fields_text = str_replace('[/span]', '</span>', $results[$i]->fields_text);
					$results[$i]->fields_text = str_replace('[br /]', '<br />', $results[$i]->fields_text);
					$results[$i]->text .= $results[$i]->fields_text;
				}
				
				$result =& $results[$i];
				if ($result->created) {
					$created = JHTML::Date( $result->created );
				} else {
					$created = '';
				}

			    $result->created	= $created;
			    $result->count		= $i + 1;
			}
		}
		$this->result	= JText::sprintf( 'FLEXI_TOTALRESULTSFOUND', $total );

		$print_link = JRoute::_('&pop=1&tmpl=component&print=1');
		$pageclass_sfx = htmlspecialchars($params->get('pageclass_sfx'));
		
		$this->assignRef('print_link',  $print_link);
		$this->assignRef('pageclass_sfx',  $pageclass_sfx);
		$this->assignRef('pageNav',  $pagination);
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
		
		$this->assign('searchkeywordlabel', 	    $searchkeywordlabel);
		$this->assignRef('document', $document);
		
		parent::display($tpl);
	}
}
