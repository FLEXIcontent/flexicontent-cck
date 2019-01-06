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

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('legacy.view.legacy');
use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'search.php');
require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');

/**
 * HTML View class for the Search View
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FLEXIcontentViewSearch extends JViewLegacy
{
	/**
	 * Creates the page's display
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		// Initialize variables
		$app      = JFactory::getApplication();
		$jinput   = JFactory::getApplication()->input;

		$option = $jinput->getCmd('option', '');
		$view   = $jinput->getCmd('view', '');

		$document = JFactory::getDocument();
		$db       = JFactory::getDbo();
		$menus    = $app->getMenu();
		$menu     = $menus->getActive();
		$uri      = JUri::getInstance();
		$pathway  = $app->getPathway();

		// Get view's Model
		$model  = $this->getModel();

		$error	= '';
		$rows	= null;
		$total	= 0;
		$form_id = $form_name = "searchForm";

		// Get parameters via model
		$params  = $model->getParams();


		/**
		 * Get data from the model
		 */

		$areas	=  $this->get('areas');
		$state	=  $this->get('state');
		$searchword     = $state->get('keyword');
		$searchphrase   = $state->get('match');
		$searchordering = $state->get('ordering');


		/**
		 * Some parameter shortcuts common among search view and advanced search plugin
		 */

		$canseltypes = (int) $params->get('canseltypes', 1);
		$txtmode     = (int) $params->get('txtmode', 0);  // 0: BASIC Index, 1: ADVANCED Index without search fields user selection, 2: ADVANCED Index with search fields user selection

		// Get if text searching according to specific (single) content type
		$show_txtfields = (int) $params->get('show_txtfields', 1);  // 0: hide, 1: according to content, 2: use custom configuration
		$show_txtfields = !$txtmode ? 0 : $show_txtfields;  // disable this flag if using BASIC index for text search

		// Get if filtering according to specific (single) content type
		$show_filters   = (int) $params->get('show_filters', 1);  // 0: hide, 1: according to content, 2: use custom configuration

		// Force single type selection and showing the content type selector
		$type_based_search = $show_filters === 1 || $show_txtfields === 1;
		$canseltypes = $type_based_search ? 1 : $canseltypes;


		/**
		 * Load needed JS libs & CSS styles
		 */

		JHtml::_('behavior.framework', true);
		flexicontent_html::loadFramework('jQuery');
		flexicontent_html::loadFramework('flexi_tmpl_common');

		// Add css files to the document <head> section (also load CSS joomla template override)
		if (!$params->get('disablecss', ''))
		{
			$document->addStyleSheetVersion($this->baseurl.'/components/com_flexicontent/assets/css/flexicontent.css', FLEXI_VHASH);
			$document->addStyleSheetVersion($this->baseurl.'/components/com_flexicontent/assets/css/flexi_filters.css', FLEXI_VHASH);
		}

		if (file_exists(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'css'.DS.'flexicontent.css'))
		{
			$document->addStyleSheetVersion($this->baseurl.'/templates/'.$app->getTemplate().'/css/flexicontent.css', FLEXI_VHASH);
		}


		/**
		 * Calculate a (browser window) page title and a page heading
		 */

		// Verify menu item points to current FLEXIcontent object
		if ($menu)
		{
			$view_ok     = 'search' == @$menu->query['view'];
			$menu_matches = $view_ok;
		}
		else
		{
			$menu_matches = false;
		}

		// MENU ITEM matched, use its page heading (but use menu title if the former is not set)
		if ($menu_matches)
		{
			$default_heading = $menu->title;

			// Cross set (show_) page_heading / page_title for compatibility of J2.5+ with J1.5 template (and for J1.5 with J2.5 template)
			$params->def('page_heading', $params->get('page_title',   $default_heading));
			$params->def('page_title',   $params->get('page_heading', $default_heading));
		  $params->def('show_page_heading', $params->get('show_page_title',   0));
		  $params->def('show_page_title',   $params->get('show_page_heading', 0));
		}

		// MENU ITEM did not match, clear page title (=browser window title) and page heading so that they are calculated below
		else
		{
			// Clear some menu parameters
			//$params->set('pageclass_sfx',	'');  // CSS class SUFFIX is behavior, so do not clear it ?

			// Calculate default page heading (=called page title in J1.5), which in turn will be document title below !! ...
			// meta_params->get('page_title') is meant for <title> but let's use as ... default page heading
			$default_heading = JText::_( 'FLEXI_SEARCH' );

			// Decide to show page heading (=J1.5 page title), this default to no
			$show_default_heading = 0;

			// Set both (show_) page_heading / page_title for compatibility of J2.5+ with J1.5 template (and for J1.5 with J2.5 template)
			$params->set('page_title',   $default_heading);
			$params->set('page_heading', $default_heading);
		  $params->set('show_page_heading', $show_default_heading);
			$params->set('show_page_title',   $show_default_heading);
		}

		// Prevent showing the page heading if ... currently no reason
		if ( 0 )
		{
			$params->set('show_page_heading', 0);
			$params->set('show_page_title',   0);
		}


		/**
		 * Create the document title, by from page title and other data
		 */

		// Use the page heading as document title, (already calculated above via 'appropriate' logic ...)
		$doc_title = $params->get( 'page_title' );

		// Check and prepend or append site name to page title
		if ( $doc_title != $app->getCfg('sitename') ) {
			if ($app->getCfg('sitename_pagetitles', 0) == 1) {
				$doc_title = JText::sprintf('JPAGETITLE', $app->getCfg('sitename'), $doc_title);
			}
			elseif ($app->getCfg('sitename_pagetitles', 0) == 2) {
				$doc_title = JText::sprintf('JPAGETITLE', $doc_title, $app->getCfg('sitename'));
			}
		}

		// Finally, set document title
		$document->setTitle($doc_title);


		/**
		 * Set document's META tags
		 */

		// Workaround for Joomla not setting the default value for 'robots', so component must do it
		$app_params = $app->getParams();
		if (($_mp=$app_params->get('robots')))    $document->setMetadata('robots', $_mp);

		// Overwrite with menu META data if menu matched
		if ($menu_matches)
		{
			if (($_mp=$menu->params->get('menu-meta_description')))  $document->setDescription( $_mp );
			if (($_mp=$menu->params->get('menu-meta_keywords')))     $document->setMetadata('keywords', $_mp);
			if (($_mp=$menu->params->get('robots')))                 $document->setMetadata('robots', $_mp);
			if (($_mp=$menu->params->get('secure')))                 $document->setMetadata('secure', $_mp);
		}



		/**
		 * Get Content Types allowed for user selection in the Search Form
		 * Also retrieve their configuration, plus the currently selected types
		 */

		// Get them from configuration
		$contenttypes = $params->get('contenttypes', array(), 'array');

		// Sanitize them as integers and as an array
		$contenttypes = ArrayHelper::toInteger($contenttypes);

		// Make sure these are unique too
		$contenttypes = array_unique($contenttypes);

		// Check for zero content types (can occur during sanitizing content ids to integers)
		foreach($contenttypes as $i => $v)
		{
			if (!$contenttypes[$i])
			{
				unset($contenttypes[$i]);
			}
		}

		// Force hidden content type selection if only 1 content type was initially configured
		$canseltypes = count($contenttypes) === 1 ? 0 : $canseltypes;
		$params->set('canseltypes', $canseltypes);  // SET "type selection FLAG" back into parameters

		// Type data and configuration (parameters), if no content types specified then all will be retrieved
		$typeData = flexicontent_db::getTypeData($contenttypes);
		$contenttypes = array();

		foreach($typeData as $tdata)
		{
			$contenttypes[] = $tdata->id;
		}

		// Get Content Types to use either those currently selected in the Search Form, or those hard-configured in the search menu item
		if ($canseltypes)
		{
			// Get them from user request data
			$form_contenttypes = $jinput->get('contenttypes', array(), 'array');

			// Sanitize them as integers and as an array
			$form_contenttypes = ArrayHelper::toInteger($form_contenttypes);

			// Make sure these are unique too
			$form_contenttypes = array_unique($form_contenttypes);

			// Check for zero content type (can occur during sanitizing content ids to integers)
			foreach($form_contenttypes as $i => $v)
			{
				if (!$form_contenttypes[$i])
				{
					unset($form_contenttypes[$i]);
				}
			}

			// Limit to allowed item types (configuration) if this is empty
			$form_contenttypes = array_intersect($contenttypes, $form_contenttypes);

			// If we found some allowed content types then use them otherwise keep the configuration defaults
			if (!empty($form_contenttypes))
			{
				$contenttypes = $form_contenttypes;
			}
		}

		// Type based seach, get a single content type (first one, if more than 1 were given ...)
		if ($type_based_search && $canseltypes && !empty($form_contenttypes))
		{
			$single_contenttype = reset($form_contenttypes);
			$form_contenttypes = $contenttypes = array($single_contenttype);
		}
		else
		{
			$single_contenttype = false;
		}



		/**
		 * Text Search Fields of the search form
		 */

		if (!$txtmode)
		{
			$txtflds = array();
			$fields_text = array();
		}

		else
		{
			$txtflds = '';

			if ($show_txtfields === 1)
			{
				$txtflds = $single_contenttype
					? $typeData[$single_contenttype]->params->get('searchable', '')
					: '';
			}
			elseif ($show_txtfields)
			{
				$txtflds = $params->get('txtflds', '');
			}

			// Sanitize them
			$txtflds = preg_replace("/[\"'\\\]/u", "", $txtflds);
			$txtflds = array_unique(preg_split("/\s*,\s*/u", $txtflds));
			if ( !strlen($txtflds[0]) ) unset($txtflds[0]);

			// Create a comma list of them
			$txtflds_list = count($txtflds) ? "'".implode("','", $txtflds)."'" : '';

			// Retrieve field properties/parameters, verifying the support to be used as Text Search Fields
			// This will return all supported fields if field limiting list is empty
			$fields_text = FlexicontentFields::getSearchFields($key='id', $indexer='advanced', $txtflds_list, $contenttypes, $load_params=true, 0, 'search');

			// If all entries of field limiting list were invalid, get ALL
			if (empty($fields_text))
			{
				if (!empty($contenttypes))
				{
					$fields_text = FlexicontentFields::getSearchFields($key='id', $indexer='advanced', null, $contenttypes, $load_params=true, 0, 'search');
				}
				else
				{
					$fields_text = array();
				}
			}
		}


		/**
		 * Filter Fields of the search form
		 */

		// Get them from type configuration or from search menu item
		$filtflds = '';

		if ($show_filters === 1)
		{
			$filtflds = $single_contenttype
				? $typeData[$single_contenttype]->params->get('filters', '')
				: '';
		}
		elseif ($show_filters)
		{
			$filtflds = $params->get('filtflds', '');
		}

		// Sanitize them
		$filtflds = preg_replace("/[\"'\\\]/u", "", $filtflds);
		$filtflds = array_unique(preg_split("/\s*,\s*/u", $filtflds));

		foreach ($filtflds as $i => $v)
		{
			if (!$v)
			{
				unset($filtflds[$i]);
			}
		}

		// Create a comma list of them
		$filtflds_list = count($filtflds) ? "'" . implode("','", $filtflds) . "'" : '';


		/**
		 * Retrieve field properties/parameters, verifying they support to be used as Filter Fields
		 * This will return all supported fields if field limiting list is empty
		 */

		if (count($filtflds))
		{
			$filters_tmp = FlexicontentFields::getSearchFields($key='name', $indexer='advanced', $filtflds_list, $contenttypes, $load_params=true, 0, 'filter');

			// Use custom order
			$filters = array();

			if ($canseltypes && $show_filters)
			{
				foreach ($filtflds as $field_name)
				{
					if (empty($filters_tmp[$field_name]))
					{
						continue;
					}

					$filter_id = $filters_tmp[$field_name]->id;
					$filters[$filter_id] = $filters_tmp[$field_name];
				}
			}

			else
			{
				// Index by filter_id in this case too (for consistency, although we do not use the array index ?)
				foreach( $filters_tmp as $filter)
				{
					$filters[$filter->id] = $filter;
				}
			}

			unset($filters_tmp);
		}


		/**
		 * If configured filters were either not found or were invalid for the current content type(s)
		 * then retrieve all fields marked as filterable for the give content type(s)
		 * this is useful to list per content type filters automatically, even when not set or misconfigured
		 */

		if (empty($filters))
		{
			// If filters are type based and a type was not selected yet, then do not set any filters
			if ($type_based_search && $canseltypes && empty($form_contenttypes))
			{
				$filters = array();
			}

			// Set filters according to currently used content types
			else
			{
				$filters = !empty($contenttypes)
					? FlexicontentFields::getSearchFields($key='id', $indexer='advanced', null, $contenttypes, $load_params=true, 0, 'filter')
					: array();
			}
		}


		/**
		 * Create Form Elements (the 'lists' array)
		 */

		$lists = array();

		// *** Selector of Content Types
		if ($canseltypes)
		{
			$types = array();

			if ($show_filters)
			{
				$types[] = JHtml::_('select.option', '', JText::_('FLEXI_PLEASE_SELECT'));
			}

			foreach($typeData as $type)
			{
				$types[] = JHtml::_('select.option', $type->id, JText::_($type->name));
			}

			$multiple_param = $show_filters ? ' onchange="adminFormPrepare(this.form); this.form.submit();" ' : ' multiple="multiple" ';
			$multiple_class = $show_filters ? ' fc_is_selmultiple' : '';

			$attribs  = $multiple_param.' size="5" class="fc_field_filter use_select2_lib fc_prompt_internal '.$multiple_class.'"';  // class="... fc_label_internal" data-fc_label_text="..."
			$attribs .= ' data-placeholder="'.htmlspecialchars(JText::_('FLEXI_CLICK_TO_LIST', ENT_QUOTES, 'UTF-8')).'"';
			$attribs .= ' data-fc_prompt_text="'.htmlspecialchars(JText::_('FLEXI_TYPE_TO_FILTER', ENT_QUOTES, 'UTF-8')).'"';

			$lists['contenttypes'] = JHtml::_('select.genericlist',
				$types,
				'contenttypes[]',
				$attribs,
				'value',
				'text',
				(empty($form_contenttypes) ? '' : $form_contenttypes),
				'contenttypes'
			);

			/*
			$checked = !count($form_contenttypes) || !strlen($form_contenttypes[0]);
			$checked_attr = $checked ? 'checked="checked"' : '';
			$checked_class = $checked ? 'fc_highlight' : '';

			$lists['contenttypes']  = '<ul class="fc_field_filter fc_checkradio_group">';
			$lists['contenttypes'] .= ' <li class="fc_checkradio_option fc_checkradio_special">';
			$lists['contenttypes'] .= '  <input href="javascript:;" onclick="fc_toggleClass(this, \'fc_highlight\', 1);" ';
			$lists['contenttypes'] .= '    id="_contenttypes_0" type="checkbox" name="contenttypes[0]" ';
			$lists['contenttypes'] .= '    value="" '.$checked_attr.' class="fc_checkradio" />';
			$lists['contenttypes'] .= '  <label class="'.$checked_class.'" for="_contenttypes_0">';
			$lists['contenttypes'] .= '   -'.JText::_('FLEXI_ALL').'-';
			$lists['contenttypes'] .= '  </label>';
			$lists['contenttypes'] .= ' </li>';
			foreach($typeData as $type) {
				$checked = in_array($type->value, $form_contenttypes);
				$checked_attr = $checked ? 'checked=checked' : '';
				$checked_class = $checked ? ' fc_highlight' : '';
				$lists['contenttypes'] .= ' <li class="fc_checkradio_option">';
				$lists['contenttypes'] .= '  <input href="javascript:;" onclick="fc_toggleClass(this, \'fc_highlight\');" ';
				$lists['contenttypes'] .= '    id="_contenttypes_'.$type->value.'" type="checkbox" name="contenttypes[]" ';
				$lists['contenttypes'] .= '    value="'.$type->value.'" '.$checked_attr.' class="fc_checkradio" />';
				$lists['contenttypes'] .= '  <label class="'.$checked_class.'" for="_contenttypes_'.$type->value.'">';
				$lists['contenttypes'] .= '   '.JText::_($type->text);
				$lists['contenttypes'] .= '  </label>';
				$lists['contenttypes'] .= ' </li>';
			}
			$lists['contenttypes'] .= '</ul>';
			*/
		}


		// *** Selector of Fields for text searching
		if( $txtmode==2 && count($fields_text) )
		{
			// Get selected text fields in the Search Form
			$form_txtflds = $jinput->get('txtflds', array(), 'array');

			if ($form_txtflds)
			{
				foreach ($form_txtflds as $i => $form_txtfld)
				{
					$form_txtflds[$i] = JFilterInput::getInstance()->clean($form_txtfld, 'string');
				}
			}

			$lists['txtflds'] = JHtml::_('select.genericlist',
				$fields_text,
				'txtflds[]',
				array(
					'multiple' => 'multiple',
					'size' => '5',
					'class' => 'fc_field_filter use_select2_lib fc_prompt_internal fc_is_selmultiple',
					'data-placeholder' => htmlspecialchars(JText::_('FLEXI_CLICK_TO_LIST', ENT_QUOTES, 'UTF-8')),
					'data-fc_prompt_text' => htmlspecialchars(JText::_('FLEXI_TYPE_TO_FILTER', ENT_QUOTES, 'UTF-8')),
				),
				'name',
				'label',
				$form_txtflds,
				'txtflds'
			);

			/*
			$checked = !count($form_txtflds) || !strlen($form_txtflds[0]);
			$checked_attr = $checked ? 'checked="checked"' : '';
			$checked_class = $checked ? 'fc_highlight' : '';

			$lists['txtflds']  = '<ul class="fc_field_filter fc_checkradio_group">';
			$lists['txtflds'] .= ' <li class="fc_checkradio_option fc_checkradio_special">';
			$lists['txtflds'] .= '  <input href="javascript:;" onclick="fc_toggleClass(this, \'fc_highlight\', 1);" ';
			$lists['txtflds'] .= '    id="_txtflds_0" type="checkbox" name="txtflds[0]" value="" ';
			$lists['txtflds'] .= '    value="" '.$checked_attr.' class="fc_checkradio" />';
			$lists['txtflds'] .= '  <label class="'.$checked_class.'" for="_txtflds_0">';
			$lists['txtflds'] .= '   -'.JText::_('FLEXI_ALL').'-';
			$lists['txtflds'] .= '  </label>';
			$lists['txtflds'] .= ' </li>';
			foreach($fields_text as $field) {
				$checked = in_array($field->name, $form_txtflds);
				$checked_attr = $checked ? 'checked=checked' : '';
				$checked_class = $checked ? ' fc_highlight' : '';
				$lists['txtflds'] .= ' <li class="fc_checkradio_option">';
				$lists['txtflds'] .= '  <input href="javascript:;" onclick="fc_toggleClass(this, \'fc_highlight\');" ';
				$lists['txtflds'] .= '    id="_txtflds_'.$field->id.'" type="checkbox" name="txtflds[]" ';
				$lists['txtflds'] .= '    value="'.$field->name.'" '.$checked_attr.' class="fc_checkradio" />';
				$lists['txtflds'] .= '  <label class="class=""'.$checked_class.'" for="_txtflds_'.$field->id.'">';
				$lists['txtflds'] .= '   '.JText::_($field->label);
				$lists['txtflds'] .= '  </label>';
				$lists['txtflds'] .= ' </li>';
			}
			$lists['txtflds'] .= '</ul>';
			*/
		}


		// *** Selector of FLEXIcontent Results Ordering
		$lists['orderby'] = flexicontent_html::orderby_selector( $params, $form_id, $autosubmit=1, $extra_order_types=array(), $sfx='' );

		// *** Selector of FLEXIcontent Results Ordering (2nd level)
		$lists['orderby_2nd'] = flexicontent_html::orderby_selector( $params, $form_id, $autosubmit=1, $extra_order_types=array(), $sfx='_2nd' );

		// *** Selector of Pagination Limit
		$lists['limit'] = flexicontent_html::limit_selector( $params, $form_id, $autosubmit=0 );


		// *** Selector of non-FLEXIcontent Results Ordering
		if($show_searchordering = $params->get('show_searchordering', 1))
		{
			// built select lists
			$orders = array();
			$orders[] = JHtml::_('select.option',  'newest', JText::_( 'FLEXI_ADV_NEWEST_FIRST' ) );
			$orders[] = JHtml::_('select.option',  'oldest', JText::_( 'FLEXI_ADV_OLDEST_FIRST' ) );
			$orders[] = JHtml::_('select.option',  'popular', JText::_( 'FLEXI_ADV_MOST_POP' ) );
			$orders[] = JHtml::_('select.option',  'alpha', JText::_( 'FLEXI_ADV_ALPHA' ) );
			$orders[] = JHtml::_('select.option',  'category', JText::_( 'FLEXI_ADV_SEARCH_SEC_CAT' ) );
			$lists['ordering'] = JHtml::_('select.genericlist', $orders, 'o',
				'class="fc_field_filter use_select2_lib"', 'value', 'text', $searchordering, 'ordering' );
		}


		// *** Selector for usage of Search Text
		$show_searchphrase = $params->get('show_searchphrase', 1);
		if ($show_searchphrase)
		{
			$searchphrase_names = array(
				'all'=>'FLEXI_ALL_WORDS',
				'any'=>'FLEXI_ANY_WORDS',
				'natural'=>'FLEXI_NATURAL_PHRASE',
				'natural_expanded'=>'FLEXI_NATURAL_PHRASE_MORE_RESULTS',
				'exact'=>'FLEXI_EXACT_PHRASE',
			);

			$phrases = array();
			foreach ($searchphrase_names as $searchphrase_value => $searchphrase_name)
			{
				$_obj = new stdClass();
				$_obj->value = $searchphrase_value;
				$_obj->text  = $searchphrase_name;
				$phrases[] = $_obj;
			}
			$lists['searchphrase'] = JHtml::_('select.genericlist', $phrases, 'p',
				'class="fc_field_filter use_select2_lib"', 'value', 'text', $searchphrase, 'searchphrase', $_translate=true);

			/*$lists['searchphrase']  = '<ul class="fc_field_filter fc_checkradio_group">';
			foreach ($searchphrase_names as $searchphrase_value => $searchphrase_name) {
				$lists['searchphrase'] .= ' <li class="fc_checkradio_option fc_checkradio_special">';
				$checked = $searchphrase_value == $searchphrase;
				$checked_attr = $checked ? 'checked=checked' : '';
				$checked_class = $checked ? 'fc_highlight' : '';
				$lists['searchphrase'] .= '  <input href="javascript:;" onclick="fc_toggleClassGrp(this.parentNode, \'fc_highlight\');" id="searchphrase_'.$searchphrase_value.'" type="radio" name="p" value="'.$searchphrase_value.'" '.$checked_attr.' />';
				$lists['searchphrase'] .= '  <label class="'.$checked_class.'" style="display:inline-block; white-space:nowrap;" for="searchphrase_'.$searchphrase_value.'">';
				$lists['searchphrase'] .=     JText::_($searchphrase_name);
				$lists['searchphrase'] .= '  </label>';
				$lists['searchphrase'] .= ' </li>';
			}
			$lists['searchphrase']  .= '</ul>';*/
		}
		else
		{
			$lists['searchphrase'] = '<input type="hidden" name="p" value="' . $searchphrase . '" />';
		}


		// *** Selector for filter combination
		/*
		if ($show_filtersop = $params->get('show_filtersop', 1))
		{
			$default_filtersop = $params->get('default_filtersop', 'all');
			$filtersop = $jinput->getCmd('filtersop', $default_filtersop);
			$filtersop_arr		= array();
			$filtersop_arr[] = JHtml::_('select.option',  'all', JText::_( 'FLEXI_SEARCH_ALL' ) );
			$filtersop_arr[] = JHtml::_('select.option',  'any', JText::_( 'FLEXI_SEARCH_ANY' ) );
			$lists['filtersop']= JHtml::_('select.radiolist',  $filtersop_arr, 'filtersop', '', 'value', 'text', $filtersop );
		}
		*/


		// *** Selector of Search Areas
		// If showing this is disabled, then FLEXIcontent (advanced) search model will not use all search areas,
		// but instead it will use just 'flexicontent' search area, that is the search area of FLEXIcontent (advanced) search plugin
		if( $params->get('show_searchareas', 0) )
		{
			// Get Content Types currently selected in the Search Form
			$form_areas = $jinput->get('areas', array(), 'array');

			if ($form_areas)
			{
				foreach ($form_areas as $i => $area)
				{
					$form_areas[$i] = JFilterInput::getInstance()->clean($area, 'cmd');
				}
			}

			$checked = empty($form_areas) || !count($form_areas);
			$checked_attr = $checked ? 'checked="checked"' : '';
			$checked_class = $checked ? 'fc_highlight' : '';

			// Create array of area options
			$options = array();

			foreach($areas['search'] as $area => $label)
			{
				$_area = new stdClass();
				$_area->text = $label;
				$_area->value = $area;
				$options[] = $_area;
			}
			$attribs  = ' multiple="multiple" size="5" class="fc_field_filter use_select2_lib fc_prompt_internal fc_is_selmultiple"';  // class="... fc_label_internal" data-fc_label_text="..."
			$attribs .= ' data-placeholder="'.htmlspecialchars(JText::_('FLEXI_CLICK_TO_LIST', ENT_QUOTES, 'UTF-8')).'"';
			$attribs .= ' data-fc_prompt_text="'.htmlspecialchars(JText::_('FLEXI_TYPE_TO_FILTER', ENT_QUOTES, 'UTF-8')).'"';
			$lists['areas'] = JHtml::_('select.genericlist', $options, 'areas[]', $attribs, 'value', 'text', $form_areas, 'areas', $do_jtext=true);
			/*
			$lists['areas']  = '<ul class="fc_field_filter fc_checkradio_group">';
			$lists['areas'] .= ' <li class="fc_checkradio_option fc_checkradio_special">';
			$lists['areas'] .= '  <input href="javascript:;" onclick="fc_toggleClass(this, \'fc_highlight\', 1);" ';
			$lists['areas'] .= '    id="area_0" type="checkbox" name="area[0]" ';
			$lists['areas'] .= '    value="" '.$checked_attr.' class="fc_checkradio" />';
			$lists['areas'] .= '  <label class="'.$checked_class.'" for="_txtflds_0">';
			$lists['areas'] .= '   -'.JText::_('FLEXI_CONTENT_ONLY').'-';
			$lists['areas'] .= '  </label>';
			$lists['areas'] .= ' </li>';
			foreach($areas['search'] as $area_name => $area_label) {
				$checked = in_array($area_name, $form_areas);
				$checked_attr = $checked ? 'checked=checked' : '';
				$checked_class = $checked ? ' fc_highlight' : '';
				$lists['areas'] .= ' <li class="fc_checkradio_option">';
				$lists['areas'] .= '  <input href="javascript:;" onclick="fc_toggleClass(this, \'fc_highlight\');" ';
				$lists['areas'] .= '    id="area_'.$area_name.'" type="checkbox" name="areas[]" ';
				$lists['areas'] .= '    value="'.$area_name.'" '.$checked_attr.' class="fc_checkradio" />';
				$lists['areas'] .= '  <label class="'.$checked_class.'" for="area_'.$area_name.'">';
				$lists['areas'] .= '  '.JText::_($area_label);
				$lists['areas'] .= '  </label>';
				$lists['areas'] .= ' </li>';
			}
			$lists['areas'] .= '</ul>';
			*/
		}

		// Log the search
		FLEXIadvsearchHelper::logSearch($searchword);

		//limit searchword
		$min_word_len = $app->getUserState( $option.'.min_word_len', 0 );
		$min = $min_word_len ? $min_word_len  : $params->get('minchars', 3);
		$max = $params->get('maxchars', 200);

		if (FLEXIadvsearchHelper::limitSearchWord($searchword, $min, $max))
		{
			$error = JText::sprintf( 'FLEXI_SEARCH_MESSAGE', $min, $max );
		}

		// Sanitise searchword
		if (FLEXIadvsearchHelper::santiseSearchWord($searchword, $state->get('match'), $min))
		{
			$error = JText::_( 'IGNOREKEYWORD' );
		}

		// Put the filtered results back into the model
		// TODO: the checks should be done in the model perhaps...
		$state->set('keyword', $searchword);
		$filter_word_like_any = $params->get('filter_word_like_any', 0);

		if ($error)
		{
			$results	= array();
			$total		= 0;
			$pageNav = '';
		}
		else
		{
			$results	= $this->get('data' );
			$total		= $this->get('total');
			$pageNav  = $this->get('pagination');

			// URL-encode filter values
			$_revert = array('%21'=>'!', '%2A'=>'*', '%27'=>"'", '%28'=>'(', '%29'=>')');
			foreach($_GET as $i => $v)
			{
				if (substr($i, 0, 6) === "filter")
				{
					if (is_array($v))
					{
						foreach($v as $ii => &$vv)
						{
							$vv = str_replace('&', '__amp__', $vv);
							$vv = strtr(rawurlencode($vv), $_revert);
							$pageNav->setAdditionalUrlParam($i.'['.$ii.']', $vv);
						}
						unset($vv);
					}
					else
					{
						$v = str_replace('&', '__amp__', $v);
						$v = strtr(rawurlencode($v), $_revert);
						$pageNav->setAdditionalUrlParam($i, $v);
					}
				}

				// Make sure all URL variables are added to the pagination URLs
				else
				{
					if (is_array($v))
					{
						foreach($v as $ii => &$vv)
						{
							$pageNav->setAdditionalUrlParam($i.'['.$ii.']', $vv);
						}
					}
					else
					{
						$pageNav->setAdditionalUrlParam($i, $v);
					}
				}
			}

			$_sh404sef = defined('SH404SEF_IS_RUNNING') && JFactory::getConfig()->get('sef');
			if ($_sh404sef)
			{
				$pageNav->setAdditionalUrlParam('limit', $model->getState('limit'));
			}

			if ($state->get('match') === 'exact')
			{
				$searchwords = array($searchword);
				//$needle = $searchword;
			}
			else
			{
				$searchwords = preg_split("/\s+/u", $searchword);
				//print_r($searchwords);
			}

			// Create regular expressions, for highlighting the matched words
			$w_regexp_highlight = array();

			foreach($searchwords as $n => $_word)
			{
				$w_regexp_highlight[$_word] = StringHelper::strlen($_word) <= 2  ||  $n+1 < count($searchwords)
					? '#\b('. preg_quote($_word, '#') .')\b#iu'   // Non-last word or word too small avoid highlighting non exact matches
					: '#\b('. preg_quote($_word, '#') .')#iu';
			}

			for ($i=0; $i < count($results); $i++)
			{
				$result = & $results[$i];

				if (strlen($searchwords[0]))
				{
					$parts = FLEXIadvsearchHelper::prepareSearchContent( $result->text, $params->get('text_chars', 200), $searchwords );
					//if( count($parts)>1 ) { echo "<pre>"; print_r($parts); exit;}

					foreach ($parts as $word_found => $part)
					{
						if (!$word_found)
						{
							continue;
						}

						$searchRegex = $w_regexp_highlight[$word_found];
						$parts[$word_found] = preg_replace($searchRegex, '_fc_highlight_start_\0_fc_highlight_end_', $part );
					}
					$result->text = implode(' <br/> ', $parts);

					$replace_count_total = 0;

					// This is for LIKE %word% search for languages without spaces
					if ($filter_word_like_any)
					{
						// Do not highlight too small words, since we do not consider spaces
						if (strlen($word_found) <= 2)
						{
							continue;
						}

						foreach ($searchwords as $_word)
						{
							$searchRegex = '#('. preg_quote($_word, '#') .'[^\s]*)#iu';
							$result->text = preg_replace($searchRegex, '_fc_highlight_start_\0_fc_highlight_end_', $result->text, 1, $replace_count );

							if ($replace_count)
							{
								$replace_count_total++;
							}
						}
					}

					$result->text = str_replace('_fc_highlight_start_', '<span class="highlight">', $result->text );
					$result->text = str_replace('_fc_highlight_end_', '</span>', $result->text );

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
					$parts = FLEXIadvsearchHelper::prepareSearchContent( $result->text, $params->get('text_chars', 200), array() );
					$result->text = implode(' <br/> ', $parts);
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

				$result->created	= $result->created ? JHtml::Date( $result->created ) : '';
				$result->count		= $i + 1;
			}
		}
		$this->result	= JText::sprintf( 'FLEXI_TOTALRESULTSFOUND', $total );


		/**
		 * Create HTML of filters (-AFTER- getData of model have been called)
		*/

		$filter_values = array();

		foreach ($filters as $filter)
		{
			$filter->parameters->set('display_label_filter_s', 0);
			$filter->value = $jinput->get('filter_' . $filter->id, '', 'array');

			if (
				(!is_array($filter->value) && strlen($filter->value)) ||
				(is_array($filter->value) && count($filter->value))
			)
			{
				$filter_values[$filter->id] = $filter->value;
			}

			//$fieldsearch = $app->getUserStateFromRequest( 'flexicontent.search.'.'filter_'.$filter->id, 'filter_'.$filter->id, array(), 'array' );
			//echo "Field name: ".$filter->name; echo ":: ". 'filter_'.$filter->id ." :: value: "; print_r($filter->value); echo "<br/>\n";

			$field_filename = $filter->iscore
				? 'core'
				: $filter->field_type;

			FLEXIUtilities::call_FC_Field_Func($field_filename, 'onAdvSearchDisplayFilter', array(
				&$filter,
				$filter->value,
				$form_id
			));
		}
		//echo "<pre>"; print_r($_GET); exit;

		// Create links
		$link = JRoute::_(FlexicontentHelperRoute::getSearchRoute(0, $menu_matches ? $menu->id : 0));

    $curr_url   = str_replace('&', '&amp;', $_SERVER['REQUEST_URI']);
    $print_link = $curr_url .(strstr($curr_url, '?') ? '&amp;'  : '?').'pop=1&amp;tmpl=component&amp;print=1';

		$pageclass_sfx = htmlspecialchars($params->get('pageclass_sfx'));

		$this->action = $link;  // $uri->toString()
		$this->print_link = $print_link;

		$this->type_based_search = $type_based_search;
		$this->contenttypes = $contenttypes;
		$this->filters = $filters;

		$this->results = $results;
		$this->lists = $lists;
		$this->params = $params;
		$this->pageNav = $pageNav;
		$this->pageclass_sfx = $pageclass_sfx;
		$this->typeData = $typeData;

		$this->assign('ordering',     $state->get('ordering'));
		$this->assign('filter_values', $filter_values);
		$this->assign('searchword',   $searchword);
		$this->assign('searchphrase', $state->get('match'));
		$this->assign('searchareas',  $areas);

		$this->assign('total',  $total);
		$this->assign('error',  $error);
		$this->assign('document', $document);
		$this->assign('form_id', $form_id);
		$this->assign('form_name', $form_name);

		$print_logging_info = $params->get('print_logging_info');
		if ( $print_logging_info ) { global $fc_run_times; $start_microtime = microtime(true); }

		parent::display($tpl);

		if ( $print_logging_info ) @$fc_run_times['template_render'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
	}
}
?>