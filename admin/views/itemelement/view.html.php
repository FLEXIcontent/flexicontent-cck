<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            http://www.flexicontent.com
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

JLoader::register('FlexicontentViewBaseRecords', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/base/view_records.php');

/**
 * HTML View class for the FLEXIcontent itemelement screen
 */
class FlexicontentViewItemelement extends FlexicontentViewBaseRecords
{
	var $proxy_option   = null;
	var $title_propname = null;
	var $state_propname = null;
	var $db_tbl         = null;

	public function display($tpl = null)
	{
		/**
		 * Initialise variables
		 */

		global $globalcats;
		$app      = JFactory::getApplication();
		$jinput   = $app->input;
		$document = JFactory::getDocument();
		$user     = JFactory::getUser();
		$cparams  = JComponentHelper::getParams('com_flexicontent');
		$session  = JFactory::getSession();
		$db       = JFactory::getDbo();

		$option   = $jinput->getCmd('option', '');
		$view     = $jinput->getCmd('view', '');
		$task     = $jinput->getCmd('task', '');
		$layout   = $jinput->getString('layout', 'default');
		$assocs_id= $jinput->getInt('assocs_id', 0);

		$isAdmin  = $app->isAdmin();
		$isCtmpl  = $jinput->getCmd('tmpl') === 'component';

		// Some flags & constants
		;

		// Load Joomla language files of other extension
		if (!empty($this->proxy_option))
		{
			JFactory::getLanguage()->load($this->proxy_option, JPATH_ADMINISTRATOR, 'en-GB', true);
			JFactory::getLanguage()->load($this->proxy_option, JPATH_ADMINISTRATOR, null, true);
		}

		// Get model
		$model = $this->getModel();

		// Performance statistics
		if ($print_logging_info = $cparams->get('print_logging_info'))
		{
			global $fc_run_times;
		}

		if ($assocs_id)
		{
			$language    = $app->getUserStateFromRequest( $option.'.'.$view.'.language', 'language', '', 'string' );
			$type_id     = $app->getUserStateFromRequest( $option.'.'.$view.'.type_id', 'type_id', 0, 'int' );
			$created_by  = $app->getUserStateFromRequest( $option.'.'.$view.'.created_by', 'created_by', 0, 'int' );

			$type_data = $model->getTypeData( $assocs_id, $type_id );
			$assocanytrans = $user->authorise('flexicontent.assocanytrans', 'com_flexicontent');
			if (!$assocanytrans && !$created_by)  $created_by = $user->id;
		}

		// get filter values
		$filter_order     = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_order', 	  'filter_order', 	 'i.ordering', 'cmd' );
		$filter_order_Dir = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_order_Dir',	'filter_order_Dir',	''				 , 'cmd' );

		$filter_state  = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_state',  'filter_state',   '',    'cmd' );
		$filter_cats   = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_cats',   'filter_cats',    0,     'int' );
		$filter_type   = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_type',   'filter_type',    0,     'int' );
		$filter_access = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_access', 'filter_access',  '',    'string' );
		$filter_lang   = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_lang',   'filter_lang',    '',    'string' );
		$filter_author = $app->getUserStateFromRequest( $option.'.'.$view.'.filter_author', 'filter_author',  '',    'cmd' );

		$search = $app->getUserStateFromRequest( $option.'.'.$view.'.search', 			'search', 			'', 'string' );
		$search = $db->escape( StringHelper::trim(StringHelper::strtolower( $search ) ) );


		/**
		 * Add css and js to document
		 */

		if ($layout !== 'indexer')
		{
			// Add css to document
			if ($isAdmin)
			{
				!JFactory::getLanguage()->isRtl()
					? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VHASH)
					: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', FLEXI_VHASH);
				!JFactory::getLanguage()->isRtl()
					? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH)
					: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x_rtl.css', FLEXI_VHASH);
			}
			else
			{
				!JFactory::getLanguage()->isRtl()
					? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontent.css', FLEXI_VHASH)
					: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontent_rtl.css', FLEXI_VHASH);
			}

			// Add JS frameworks
			flexicontent_html::loadFramework('select2');

			// Load custom behaviours: form validation, popup tooltips
			JHtml::_('behavior.formvalidation');
			JHtml::_('bootstrap.tooltip');

			// Add js function to overload the joomla submitform validation
			$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/admin.js', FLEXI_VHASH);
			$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/validate.js', FLEXI_VHASH);
		}


		/**
		 * Create Submenu & Toolbar
		 */

		// Create Submenu (and also check access to current view)
		// NA

		// Create document/toolbar titles
		$doc_title = JText::_( 'FLEXI_SELECTITEM' );
		$document->setTitle($doc_title);

		// Create the toolbar
		// NA


		/**
		 * Get data from the model
		 */

		if ( $print_logging_info )  $start_microtime = microtime(true);

		$rows = $this->get('Data');

		$types   = $this->get('Typeslist');
		$authors = $this->get('Authorslist');
		$langs   = FLEXIUtilities::getLanguages('code');
		$lang_assocs = $assocs_id ? $this->get('LangAssocs') : array();

		if ( $print_logging_info ) @$fc_run_times['execute_main_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;

		// Create pagination object
		$pagination = $this->get('Pagination');

		// Ordering active FLAG
		$ordering = ($filter_order == 'i.ordering');



		/**
		 * Create List Filters
		 */


		// Text search filter value
		$lists['search'] = $search;


		// Table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order']     = $filter_order;

		// Build the categories filter
		$categories = $globalcats;
		$lists['filter_cats'] =  '<label class="label">'.JText::_('FLEXI_CATEGORY').'</label>'.
			flexicontent_cats::buildcatselect($categories, 'filter_cats', $filter_cats, '-'/*2*/, 'class="use_select2_lib" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', $check_published=true, $check_perms=false);


		// Build type filter
		$lists['filter_type'] = '<label class="label">'.JText::_('FLEXI_TYPE').'</label>'.
			($assocs_id && !empty($type_data) ?
				'<span class="badge badge-info">'.$type_data->name.'</span>' :
				flexicontent_html::buildtypesselect($types, 'filter_type', $filter_type, '-'/*true*/, 'class="use_select2_lib" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'filter_type')
			);

		// Build author filter
		$lists['filter_author'] = '<label class="label">'.JText::_('FLEXI_AUTHOR').'</label>'.
			($assocs_id && $created_by ?
				'<span class="badge badge-info">'.JFactory::getUser($created_by)->name.'</span>' :
				flexicontent_html::buildauthorsselect($authors, 'filter_author', $filter_author, '-'/*true*/, 'class="use_select2_lib" size="3" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"')
			);

		// Build publication state filter
		$states[] = JHtml::_('select.option',  '', '-'/*'FLEXI_SELECT_STATE'*/ );
		$states[] = JHtml::_('select.option',  'P', 'FLEXI_PUBLISHED' );
		$states[] = JHtml::_('select.option',  'U', 'FLEXI_UNPUBLISHED' );
		$states[] = JHtml::_('select.option',  'PE','FLEXI_PENDING' );
		$states[] = JHtml::_('select.option',  'OQ','FLEXI_TO_WRITE' );
		$states[] = JHtml::_('select.option',  'IP','FLEXI_IN_PROGRESS' );
		$states[] = JHtml::_('select.option',  'A', 'FLEXI_ARCHIVED' );

		$fieldname =  $elementid = 'filter_state';
		$attribs = ' class="use_select2_lib" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()" ';
		$lists['filter_state'] = '<label class="label">'.JText::_('FLEXI_STATE').'</label>'.
			JHtml::_('select.genericlist', $states, $fieldname, $attribs, 'value', 'text', $filter_state, $elementid
		, $translate=true );

		// Build access level filter
		$levels = JHtml::_('access.assetgroups');
		array_unshift($levels, JHtml::_('select.option', '', '-'/*'FLEXI_SELECT_ACCESS'*/));
		$fieldname =  $elementid = 'filter_access';
		$attribs = ' class="use_select2_lib" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()" ';
		$lists['filter_access']	= '<label class="label">'.JText::_('FLEXI_ACCESS').'</label>'.
			JHtml::_('select.genericlist', $levels, $fieldname, $attribs, 'value', 'text', $filter_access, $elementid
		, $translate=true );

		// Build language filter
		$lists['filter_lang'] = '<label class="label">'.JText::_('FLEXI_LANGUAGE').'</label>'.
			($assocs_id && $language ?
				'<span class="badge badge-info">'.$language.'</span>' :
				flexicontent_html::buildlanguageslist('filter_lang', 'class="use_select2_lib" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', $filter_lang, '-'/*2*/)
			);


		/**
		 * Assign data to template
		 */

		$this->assocs_id = $assocs_id;
		$this->filter_cats = $filter_cats;

		$this->lists       = $lists;
		$this->rows        = $rows;
		$this->langs       = $langs;
		$this->lang_assocs = $lang_assocs;
		$this->pagination  = $pagination;
		$this->ordering    = $ordering;


		/**
		 * Render view's template
		 */

		if ( $print_logging_info ) { global $fc_run_times; $start_microtime = microtime(true); }

		parent::display($tpl);

		if ( $print_logging_info ) @$fc_run_times['template_render'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
	}
}
