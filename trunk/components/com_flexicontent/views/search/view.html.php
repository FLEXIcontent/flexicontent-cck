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
		jimport( 'joomla.html.parameter' );
		require_once(JPATH_COMPONENT.DS.'helpers'.DS.'search.php' );

		// Initialize some variables
		$app       = JFactory::getApplication();
		$document  = JFactory::getDocument();
		$uri       = JFactory::getURI();
		$menu      = JSite::getMenu()->getActive();
		$pathway   = $app->getPathway();
		
		$error	= '';
		$rows	= null;
		$total	= 0;
		
		// Get some data from model / and from state
		$areas	= & $this->get('areas');
		$state	= & $this->get('state');
		$searchword = $state->get('keyword');
		
		
		// *******************************************************************************************
		// Get Configuration parameters (Global component configuration + Menu configuration override)
		// *******************************************************************************************
		// Get the PAGE/COMPONENT parameters (WARNING: merges current menu item parameters in J1.5 but not in J1.6+)
		$params = clone($app->isSite()  ?  $app->getParams('com_flexicontent')  : JComponentHelper::getParams('com_flexicontent'));
		
		// Get menu parameters
		if ($menu) {
			$menuParams = new JParameter($menu->params);
			// In J1.6+ the above function does not merge current menu item parameters,
			// it behaves like JComponentHelper::getParams('com_flexicontent') was called
			if (FLEXI_J16GE) $params->merge($menuParams);
		}
		
		// some parameter shortcuts
		$canseltypes  = $params->get('canseltypes', 1);
		$txtmode      = $params->get('txtmode', 0);
		
		
		// ********************************
		// Load needed JS libs & CSS styles
		// ********************************
		FLEXI_J30GE ? JHtml::_('behavior.framework') : JHTML::_('behavior.mootools');
		flexicontent_html::loadJQuery();
		$document->addScript( JURI::base().'components/com_flexicontent/assets/js/rounded-corners-min.js' );
		$document->addScript( JURI::base().'components/com_flexicontent/assets/js/tmpl-common.js' );
		if (!$params->get('disablecss', '')) {
			$document->addStyleSheet($this->baseurl.'/components/com_flexicontent/assets/css/flexicontent.css');
			$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext {zoom:1;}</style><![endif]-->');
		}
		
		
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
			if ($app->getCfg('sitename_pagetitles', 0) == 1) {
				$doc_title = $app->getCfg('sitename') ." - ". $doc_title ;
			}
			elseif ($app->getCfg('sitename_pagetitles', 0) == 2) {
				$doc_title = $doc_title ." - ". $app->getCfg('sitename') ;
			}
		}
		
		// Finally, set document title
		$document->setTitle($doc_title);
		
		
		// ************************
		// Set document's META tags
		// ************************
		// ** writting both old and new way as an example
		if (!FLEXI_J16GE) {
			if ($app->getCfg('MetaTitle') == '1') 	$app->addMetaTag('title', $params->get('page_title'));
		} else {
			if (JApplication::getCfg('MetaTitle') == '1') $document->setMetaData('title', $params->get('page_title'));
		}		
		
		
		// ***************************************************************
		// Get Content Types allowed for user selection in the Search Form
		// ***************************************************************
		// Get them from configuration
		$contenttypes = $params->get('contenttypes', array());
		
		// Sanitize them
		$contenttypes = !is_array($contenttypes)  ?  array($contenttypes)  :  $contenttypes;
		$contenttypes = array_unique(array_map('intval', $contenttypes));  // Make sure these are integers since we will be using them UNQUOTED
		
		// Create a comma list of them
		$contenttypes_list = count($contenttypes) ? "'".implode("','", $contenttypes)."'"  :  "";
		
		
		// *************************************
		// Text Search Fields of the search form
		// *************************************
		// Get them from configuration
		$txtflds = $params->get('txtflds', '');
		
		// Sanitize them
		$txtflds = preg_replace("/[\"'\\\]/u", "", $txtflds);
		$txtflds = array_unique(preg_split("/\s*,\s*/u", $txtflds));
		if ( !strlen($txtflds[0]) ) unset($txtflds[0]);
		
		// Create a comma list of them
		$txtflds_list = "'".implode("','", $txtflds)."'";
		
		// Retrieve field properties/parameters, verifying the support to be used as Text Search Fields
		// This will return all supported fields if field limiting list is empty
		$fields_text = FlexicontentFields::getSearchFields($key='id', $indexer='advanced', $txtflds_list, $contenttypes, $load_params=true, 0, 'search');
		if ( !count($fields_text) )  // all entries of field limiting list were invalid , get ALL
			$fields_text = FlexicontentFields::getSearchFields($key='id', $indexer='advanced', null, $contenttypes, $load_params=true, 0, 'search');
		
		
		// ********************************
		// Filter Fields of the search form
		// ********************************
		// Get them from configuration
		$filtflds = $params->get('filtflds', '');
		
		// Sanitize them
		$filtflds = preg_replace("/[\"'\\\]/u", "", $filtflds);
		$filtflds = array_unique(preg_split("/\s*,\s*/u", $filtflds));
		if ( !strlen($filtflds[0]) ) unset($filtflds[0]);
		
		// Create a comma list of them
		$filtflds_list = "'".implode("','", $filtflds)."'";
		
		// Retrieve field properties/parameters, verifying the support to be used as Filter Fields

		// This will return all supported fields if field limiting list is empty
		if ( count($filtflds) )
			$fields_filter = FlexicontentFields::getSearchFields($key='id', $indexer='advanced', $filtflds_list, $contenttypes, $load_params=true, 0, 'filter');
		else
			$fields_filter = array();
		//if ( !count($fields_filter) )  // all entries of field limiting list were invalid , get ALL
		//	$fields_filter = FlexicontentFields::getSearchFields($key='id', $indexer='advanced', null, $contenttypes, $load_params=true, 0, 'filter');
		
		
		// ****************************************
		// Create Form Elements (the 'lists' array)
		// ****************************************
		$lists = array();
		
		// *** Selector of Content Types
		if( $canseltypes && count($contenttypes) )
		{
			// Get Content Types currently selected in the Search Form
			$form_contenttypes = JRequest::getVar('contenttypes', array());
			if ( !$form_contenttypes || empty($form_contenttypes) ) {
				$form_contenttypes = array(); //array('__FC_ALL__'); //$contenttypes;
			}
			$checked_attr = '';
			$checked_class = !count($form_contenttypes) ? 'highlight' : '';
			
			// Get all configured content Types *(or ALL if these were not set)
			$db =& JFactory::getDBO();
			$query = "SELECT id AS value, name AS text"
			. " FROM #__flexicontent_types"
			. " WHERE published = 1"
			. (count($contenttypes) ? " AND id IN (". $contenttypes_list .")"  :  "")
			. " ORDER BY name ASC, id ASC"
			;
			$db->setQuery($query);
			$types = $db->loadObjectList();
			
			//$lists['contenttypes'] = JHTML::_('select.genericlist', $types, 'contenttypes[]', 'multiple="true" size="5" style="min-width:186px;" ', 'value', 'text', $form_contenttypes, 'contenttypes');
			$lists['contenttypes']  = '<label class="flexi_radiotab rc5 '.$checked_class.'" for="_contenttypes_0">';
			$lists['contenttypes'] .= ' <input href="javascript:;" onclick="fc_toggleClass(this.parentNode, \'highlight\', 1);" style="display:none;"';
			$lists['contenttypes'] .= '  id="_contenttypes_0" type="checkbox" name="contenttypes[0]" value="" '.$checked_attr.' />';
			$lists['contenttypes'] .= ' <span style="float:left; display:inline-block;" >-'.JText::_('FLEXI_ALL').'-</span>';
			$lists['contenttypes'] .= '</label>';
			foreach($types as $type) {
				$checked = in_array($type->value, $form_contenttypes);
				$checked_attr = $checked ? 'checked=checked' : '';
				$checked_class = $checked ? 'highlight' : '';
				$lists['contenttypes'] .= '<label class="flexi_radiotab rc5 '.$checked_class.'" style="display:inline-block; white-space:nowrap;" for="_contenttypes_'.$type->value.'">';
				$lists['contenttypes'] .= ' <input href="javascript:;" onclick="fc_toggleClass(this.parentNode, \'highlight\');" id="_contenttypes_'.$type->value.'" type="checkbox" name="contenttypes[]" value="'.$type->value.'" '.$checked_attr.' />';
				$lists['contenttypes'] .= '<span style="float:left; display:inline-block;" >'.JText::_($type->text).'</span>';
				$lists['contenttypes'] .= '</label>';
			}
		}
		
		
		// *** Selector of Content Types
		if( $txtmode && count($fields_text) )
		{
			// Get Content Types currently selected in the Search Form
			$form_txtflds = JRequest::getVar('txtflds', array());
			if ( !$form_txtflds || empty($form_txtflds) ) {
				$form_txtflds = array(); //array('__FC_ALL__'); //array_keys($fields_text);
			}
			$checked_attr = '';
			$checked_class = !count($form_txtflds) ? 'highlight' : '';
			
			//$lists['contenttypes'] = JHTML::_('select.genericlist', $advsearch, 'contenttypes[]', 'multiple="true" size="5" style="min-width:186px;" ', 'value', 'text', $form_txtflds, 'contenttypes');
			
			$lists['txtflds']  = '<label class="flexi_radiotab rc5 '.$checked_class.'" for="_txtflds_0">';
			$lists['txtflds'] .= ' <input href="javascript:;" onclick="fc_toggleClass(this.parentNode, \'highlight\', 1);" style="display:none;"';
			$lists['txtflds'] .= '  id="_txtflds_0" type="checkbox" name="txtflds[0]" value="" '.$checked_attr.' />';
			$lists['txtflds'] .= ' <span style="float:left; display:inline-block;" >-'.JText::_('FLEXI_ALL').'-</span>';
			$lists['txtflds'] .= '</label>';
			foreach($fields_text as $field) {
				$checked = in_array($field->name, $form_txtflds);
				$checked_attr = $checked ? 'checked=checked' : '';
				$checked_class = $checked ? 'highlight' : '';
				$lists['txtflds'] .= '<label class="flexi_radiotab rc5 '.$checked_class.'" style="display:inline-block; white-space:nowrap;" for="_txtflds_'.$field->id.'">';
				$lists['txtflds'] .= ' <input href="javascript:;" onclick="fc_toggleClass(this.parentNode, \'highlight\');" id="_txtflds_'.$field->id.'" type="checkbox" name="txtflds[]" value="'.$field->name.'" '.$checked_attr.' />';
				$lists['txtflds'] .= '<span style="float:left; display:inline-block;" >'.JText::_($field->label).'</span>';
				$lists['txtflds'] .= '</label>';
			}
		}
		
		
		// *** Selector of FLEXIcontent Results Ordering
		if($orderby_override = $params->get('orderby_override', 1)) {
			$lists['orderby'] = flexicontent_html::ordery_selector( $params, 'searchForm', $autosubmit=0 );
		}
		
		
		// *** Selector of Pagination Limit
		if($limit_override = $params->get('limit_override', 1)) {
			$lists['limit'] = flexicontent_html::limit_selector( $params, 'searchForm', $autosubmit=0 );
		}
		
		
		// *** Selector of non-FLEXIcontent Results Ordering
		if($show_searchordering = $params->get('show_searchordering', 1)) {
			$default_searchordering = $params->get('default_searchordering', 'newest');
			// built select lists
			$orders = array();
			$orders[] = JHTML::_('select.option',  'newest', JText::_( 'FLEXI_ADV_NEWEST_FIRST' ) );
			$orders[] = JHTML::_('select.option',  'oldest', JText::_( 'FLEXI_ADV_OLDEST_FIRST' ) );
			$orders[] = JHTML::_('select.option',  'popular', JText::_( 'FLEXI_ADV_MOST_POP' ) );
			$orders[] = JHTML::_('select.option',  'alpha', JText::_( 'FLEXI_ADV_ALPHA' ) );
			$orders[] = JHTML::_('select.option',  'category', JText::_( 'FLEXI_ADV_SEARCH_SEC_CAT' ) );
			$lists['ordering'] = JHTML::_('select.genericlist', $orders, 'ordering', 'class="inputbox fc_field_filter"', 'value', 'text', $state->get('ordering', $default_searchordering) );
		}		
		
		
		// *** Selector for usage of Search Text
		if($show_searchphrase = $params->get('show_searchphrase', 1)) {
			$default_searchphrase = $params->get('default_searchphrase', 'natural');
			$searchphrase = JRequest::getVar('searchphrase', $default_searchphrase);
			$searchphrase_names = array('natural'=>'FLEXI_NATURAL_PHRASE', 'natural_expanded'=>'FLEXI_NATURAL_PHRASE_GUESS_RELEVANT', 
				'all'=>'FLEXI_ALL_WORDS', 'any'=>'FLEXI_ANY_WORDS', 'exact'=>'FLEXI_EXACT_PHRASE');

			$searchphrases = array();
			foreach ($searchphrase_names as $searchphrase_value => $searchphrase_name) {
				$_obj = new stdClass();
				$_obj->value = $searchphrase_value;
				$_obj->text  = $searchphrase_name;
				$searchphrases[] = $_obj;
			}
			$lists['searchphrase'] = JHTML::_('select.genericlist', $searchphrases, 'searchphrase',
				' class="inputbox fc_field_filter" ', 'value', 'text', $searchphrase, 'searchphrase', $_translate=true);
			
			/*$lists['searchphrase'] = '';
			foreach ($searchphrase_names as $searchphrase_value => $searchphrase_name) {
				$checked = $searchphrase_value == $searchphrase;
				$checked_attr = $checked ? 'checked=checked' : '';
				$checked_class = $checked ? 'highlight' : '';
				$lists['searchphrase'] .= '<label class="flexi_radiotab rc5 '.$checked_class.'" style="display:inline-block; white-space:nowrap;" for="searchphrase_'.$searchphrase_value.'">';
				$lists['searchphrase'] .= ' <input href="javascript:;" onclick="fc_toggleClassGrp(this.parentNode.parentNode, \'highlight\');" id="searchphrase_'.$searchphrase_value.'" type="radio" name="searchphrase" value="'.$searchphrase_value.'" '.$checked_attr.' />';
				$lists['searchphrase'] .=  '<span style="float:left; display:inline-block;" >'.JText::_($searchphrase_name).'</span>';
				$lists['searchphrase'] .= '</label>';
			}*/
		}
		
		
		// *** Selector for filter combination
		/*if($show_filtersop = $params->get('show_filtersop', 1)) {
			$default_filtersop = $params->get('default_filtersop', 'all');
			$filtersop = JRequest::getVar('filtersop', $default_filtersop);
			$filtersop_arr		= array();
			$filtersop_arr[] = JHTML::_('select.option',  'all', JText::_( 'FLEXI_SEARCH_ALL' ) );
			$filtersop_arr[] = JHTML::_('select.option',  'any', JText::_( 'FLEXI_SEARCH_ANY' ) );
			$lists['filtersop']= JHTML::_('select.radiolist',  $filtersop_arr, 'filtersop', '', 'value', 'text', $filtersop );
		}*/
		
		
		// *** Selector of Search Areas
		if( $params->get('show_searchareas', 0) )
		{
			// Get Content Types currently selected in the Search Form
			$form_areas = JRequest::getVar('areas', array());
			if ( !$form_areas || !count($form_areas) ) {
				$form_areas = array('flexicontent');
			}
			
			//$lists['areas'] = JHTML::_('select.genericlist', $types, 'areas[]', 'multiple="true" size="5" class="fc_field_filter"', 'value', 'text', $form_areas, 'areas');
			$lists['areas'] = '';
			
			// DISABLE search areas 'content' and old 'flexisearch', TODO more for flexisearch
			unset($this->searchareas['search']['content']);
			unset($this->searchareas['search']['flexisearch']);
			
			foreach($areas['search'] as $area_name => $area_label) {
				$checked = in_array($area_name, $form_areas);
				$checked_attr = $checked ? 'checked=checked' : '';
				$checked_class = $checked ? 'highlight' : '';
				$lists['areas'] .= '<label class="flexi_radiotab rc5 '.$checked_class.'" style="display:inline-block; white-space:nowrap;" for="area_'.$area_name.'">';
				$lists['areas'] .= ' <input href="javascript:;" onclick="fc_toggleClass(this.parentNode, \'highlight\');" id="area_'.$area_name.'" type="checkbox" name="areas[]" value="'.$area_name.'" '.$checked_attr.' />';
				$lists['areas'] .= '<span style="float:left; display:inline-block;" >'.JText::_($area_label).'</span>';
				$lists['areas'] .= '</label>';
			}
		}
		
		// log the search
		FLEXIadvsearchHelper::logSearch( $searchword);

		//limit searchword
		$min = $params->get('minchars', 2);
		$max = $params->get('maxchars', 200);
		if(FLEXIadvsearchHelper::limitSearchWord($searchword, $min, $max)) {
			$error = JText::sprintf( 'FLEXI_SEARCH_MESSAGE', $min, $max );
		}

		// sanitise searchword
		if (FLEXIadvsearchHelper::santiseSearchWord($searchword, $state->get('match'), $min)) {
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
			
			for ($i=0; $i < count($results); $i++)
			{
				$result = & $results[$i];
				if( strlen($searchwords[0]) )
				{
					$parts = FLEXIadvsearchHelper::prepareSearchContent( $result->text, 200, $searchwords );
					//if( count($parts)>1 ) { echo "<pre>"; print_r($parts); exit;}
					foreach ($parts as $word_found => $part) {
						if (!$word_found) continue;
						$searchRegex = '#('. preg_quote($word_found, '#') .')#iu';
						$parts[$word_found] = preg_replace($searchRegex, '<span class="highlight">\0</span>', $part );
					}
					$result->text = implode($parts, " <br/> ");
					
					$replace_count_total = 0;
					foreach ($searchwords as $_word) {
						$searchRegex = '#('. preg_quote($_word, '#') .'[^\s]*)#iu';
						$result->text = preg_replace($searchRegex, '<span class="highlight">\0</span>', $result->text, 1, $replace_count );
						if ($replace_count) $replace_count_total++;
					}
					
					// Add some message about matches
					/*if ( $state->get('match')=='any' ) {
						$text_search_header = "<u><b>".JText::sprintf('Text Search matched at least %d %% (%d out of %d words)', $replace_count_total/count($searchwords) * 100, $replace_count_total, count($searchwords)).": </b></u><br/>";
					} else if ( $state->get('match')=='all' ) {
						$text_search_header = "<u><b>".JText::sprintf('Text Search (all %d words required)', count($searchwords)).": </b></u><br/>";
					} else if ( $state->get('match')=='exact' ) {
						$text_search_header = "<u><b>".JText::_('Text Search (exact phrase)').": </b></u><br/>";
					} else if ( $state->get('match')=='natural_expanded' ) {
						$text_search_header = "<u><b>".JText::_('Text Search (phrase, guessing related)').": </b></u><br/>";
					} else if ( $state->get('match')=='natural' ) {
						$text_search_header = "<u><b>".JText::_('Text Search (phrase)').": </b></u><br/>";
					}
					$result->text = $text_search_header . $result->text;*/
				} else {
					$parts = FLEXIadvsearchHelper::prepareSearchContent( $result->text, 200, array() );
					$result->text = implode($parts, " <br/> ");
				}
				
				/*if ( !empty($result->fields_text) ) {
					$result->text .= "<br/><u><b>".JText::_('Attribute filters matched')." : </b></u>";
					$result->fields_text = str_replace('[span=highlight]', '<span class="highlight">', $result->fields_text);
					$result->fields_text = str_replace('[/span]', '</span>', $result->fields_text);
					$result->fields_text = str_replace('[br /]', '<br />', $result->fields_text);
					$result->text .= $result->fields_text;
				}*/
				$result->text = str_replace('[[[', '<', $result->text);
				$result->text = str_replace(']]]', '>', $result->text);
				
				$result->created	= $result->created ? JHTML::Date( $result->created ) : '';
				$result->count		= $i + 1;
			}
		}
		$this->result	= JText::sprintf( 'FLEXI_TOTALRESULTSFOUND', $total );
		
		
		// ******************************************************************
		// Create HTML of filters (-AFTER- getData of model have been called)
		// ******************************************************************
		foreach ($fields_filter as $filter)
		{
			$filter->value = JRequest::getVar('filter_'.$filter->id, false);
			//$fieldsearch = $app->getUserStateFromRequest( 'flexicontent.search.'.'filter_'.$filter->id, 'filter_'.$filter->id, array(), 'array' );
			//echo "FIELD value: "; print_r($filter->value);
			
			$field_filename = $filter->iscore ? 'core' : $filter->field_type;
			FLEXIUtilities::call_FC_Field_Func($field_filename, 'onAdvSearchDisplayFilter', array( &$filter, $filter->value, 'searchForm'));
		}
		
		
		$print_link = JRoute::_('&pop=1&tmpl=component&print=1');
		$pageclass_sfx = htmlspecialchars($params->get('pageclass_sfx'));
		
		$this->assignRef('print_link',    $print_link);
		$this->assignRef('pageclass_sfx', $pageclass_sfx);
		$this->assignRef('pageNav',   $pagination);
		$this->assignRef('filters',   $fields_filter);
		$this->assignRef('results',   $results);
		$this->assignRef('lists',     $lists);
		$this->assignRef('params',    $params);

		$this->assign('ordering',     $state->get('ordering'));
		$this->assign('searchword',   $searchword);
		$this->assign('searchphrase', $state->get('match'));
		$this->assign('searchareas',  $areas);

		$this->assign('total',  $total);
		$this->assign('error',  $error);
		$this->assign('action', $uri->toString());
		$this->assignRef('document', $document);
		
		$print_logging_info = $params->get('print_logging_info');
		if ( $print_logging_info ) { global $fc_run_times; $start_microtime = microtime(true); }
		
		parent::display($tpl);
		
		if ( $print_logging_info ) @$fc_run_times['template_render'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
	}
}
