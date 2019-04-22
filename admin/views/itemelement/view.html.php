<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
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
	var $proxy_option   = 'com_content';
	var $title_propname = 'title';
	var $state_propname = 'state';
	var $db_tbl         = 'content';
	var $name_singular  = 'item';

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

		$isAdmin  = $app->isClient('administrator');
		$isCtmpl  = $jinput->getCmd('tmpl') === 'component';

		// Some flags & constants
		$useAssocs = flexicontent_db::useAssociations();

		// Load Joomla language files of other extension
		if (!empty($this->proxy_option))
		{
			JFactory::getLanguage()->load($this->proxy_option, JPATH_ADMINISTRATOR, 'en-GB', true);
			JFactory::getLanguage()->load($this->proxy_option, JPATH_ADMINISTRATOR, null, true);
		}

		// Get model
		$model   = $this->getModel();
		$model_s = $this->getModel($this->name_singular);

		// Performance statistics
		if ($print_logging_info = $cparams->get('print_logging_info'))
		{
			global $fc_run_times;
		}

		if ($assocs_id)
		{
			$type_id     = $app->getUserStateFromRequest( $option.'.'.$view.'.type_id', 'type_id', 0, 'int' );
			$item_lang   = $app->getUserStateFromRequest( $option.'.'.$view.'.item_lang', 'item_lang', '', 'string' );
			$created_by  = $app->getUserStateFromRequest( $option.'.'.$view.'.created_by', 'created_by', 0, 'int' );

			$assocanytrans = $user->authorise('flexicontent.assocanytrans', 'com_flexicontent');

			if (!$assocanytrans && !$created_by)
			{
				$created_by = $user->id;
			}

			$_type_id = null;
			$type_data = $model->getTypeData($assocs_id, $_type_id);

			if (!$assocanytrans && !$type_id)
			{
				$type_id = $_type_id;
			}
		}


		/**
		 * Get filters and ordering
		 */

		$count_filters = 0;

		// Order and order direction
		$filter_order      = $model->getState('filter_order');
		$filter_order_Dir  = $model->getState('filter_order_Dir');

		// Various filters
		$filter_state     = $model->getState('filter_state');
		$filter_cats      = $model->getState('filter_cats');
		$filter_type      = $model->getState('filter_type');
		$filter_access    = $model->getState('filter_access');
		$filter_lang      = $model->getState('filter_lang');
		$filter_author    = $model->getState('filter_author');

		if (strlen($filter_state)) $count_filters++;
		if ($filter_cats) $count_filters++;
		if ($filter_type) $count_filters++;
		if (strlen($filter_access)) $count_filters++;
		if ($filter_lang) $count_filters++;
		if (strlen($filter_author)) $count_filters++;

		// Text search
		$scope  = $model->getState('scope');
		$search = $model->getState('search');
		$search = StringHelper::trim(StringHelper::strtolower($search));


		/**
		 * Add css and js to document
		 */

		if ($layout !== 'indexer')
		{
			// Add css to document
			if ($isAdmin)
			{
				!JFactory::getLanguage()->isRtl()
					? $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', array('version' => FLEXI_VHASH))
					: $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', array('version' => FLEXI_VHASH));
				!JFactory::getLanguage()->isRtl()
					? $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/j3x.css', array('version' => FLEXI_VHASH))
					: $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/j3x_rtl.css', array('version' => FLEXI_VHASH));
			}
			else
			{
				!JFactory::getLanguage()->isRtl()
					? $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontent.css', array('version' => FLEXI_VHASH))
					: $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontent_rtl.css', array('version' => FLEXI_VHASH));
			}

			// Add JS frameworks
			flexicontent_html::loadFramework('select2');

			// Load custom behaviours: form validation, popup tooltips
			JHtml::_('behavior.formvalidation');
			JHtml::_('bootstrap.tooltip');

			// Add js function to overload the joomla submitform validation
			$document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/admin.js', array('version' => FLEXI_VHASH));
			$document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/validate.js', array('version' => FLEXI_VHASH));
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
		 * Get data from the model, note data retrieval must be before 
		 * getTotal() and getPagination() because it also calculates total rows
		 */

		if ( $print_logging_info )  $start_microtime = microtime(true);

		$rows        = $model->getData();
		$authors     = $model->getAuthorslist();
		$types       = $model->getTypeslist();

		$lang_assocs = $useAssocs ? $model->getLangAssocs() : array();
		$langs       = FLEXIUtilities::getLanguages('code');
		$categories  = $globalcats ?: array();

		if ( $print_logging_info ) @$fc_run_times['execute_main_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;

		// Create pagination object
		$pagination = $this->get('Pagination');

		// Ordering active FLAG
		$ordering = $filter_order === 'a.ordering';



		/**
		 * Create List Filters
		 */

		$lists = array();


		// Build category filter
		$fieldname = 'filter_cats';
		$elementid = 'filter_cats';
		$value     = $filter_cats;

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_CATEGORY'),
			'html' => flexicontent_cats::buildcatselect(
				$categories,
				$fieldname,
				$value,
				$displaytype = '-',
				array(
					'class' => $this->select_class,
					'size' => '1',
					'onchange' => 'document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				$check_published = true,
				$check_perms = false
			),
		));


		// Build item type filter
		$fieldname = 'filter_type';
		$elementid = 'filter_type';

		if (!$assocs_id || $assocanytrans || !$type_id)
		{
			$lists[$elementid] = $this->getFilterDisplay(array(
				'label' => JText::_('FLEXI_TYPE'),
				'html' => flexicontent_html::buildtypesselect(
					$types,
					$fieldname,
					$filter_type,
					$displaytype = '-',
					array(
						'class' => $this->select_class,
						'size' => '1',
						'onchange' => 'document.adminForm.limitstart.value=0; Joomla.submitform();',
					),
					$elementid
				),
			));
		}
		else
		{
			$lists[$elementid] = $this->getFilterDisplay(array(
				'label' => JText::_('FLEXI_TYPE'),
				'html' => '<span class="add-on"><i>' . $type_data->name . '</i></span>',
			));
		}


		// Build author filter
		$fieldname = 'filter_author';
		$elementid = 'filter_author';
		$value     = $filter_author;

		if (!$assocs_id || $assocanytrans || !$created_by)
		{
			$lists[$elementid] = $this->getFilterDisplay(array(
				'label' => JText::_('FLEXI_AUTHOR'),
				'html' => flexicontent_html::buildauthorsselect(
					$authors,
					$fieldname,
					$value,
					$displaytype = '-',
					array(
						'class' => $this->select_class,
						'size' => '1',
						'onchange' => 'document.adminForm.limitstart.value=0; Joomla.submitform();',
					)
				),
			));
		}
		else
		{
			$lists[$elementid] = $this->getFilterDisplay(array(
				'label' => JText::_('FLEXI_AUTHOR'),
				'html' => '<span class="add-on"><i>' . JFactory::getUser($created_by)->name . '</i></span>',
			));
		}


		// Build language filter
		$fieldname = 'filter_lang';
		$elementid = 'filter_lang';

		if (!$assocs_id || !$item_lang)
		{
			$lists[$elementid] = $this->getFilterDisplay(array(
				'label' => JText::_('FLEXI_LANGUAGE'),
				'html' => flexicontent_html::buildlanguageslist(
					$fieldname,
					array(
						'class' => $this->select_class,
						'onchange' => 'document.adminForm.limitstart.value=0; Joomla.submitform();',
					),
					$filter_lang,
					$displaytype = '-'
				),
			));
		}
		else
		{
			$lists[$elementid] = $this->getFilterDisplay(array(
				'label' => JText::_('FLEXI_LANGUAGE'),
				'html' => '<span class="add-on"><i>' . $item_lang . '</i></span>',
			));
		}


		// Build publication state filter
		$options = array(
			JHtml::_('select.option',  '', '-'/*'FLEXI_SELECT_STATE'*/),
			JHtml::_('select.option',  'P', 'FLEXI_PUBLISHED'),
			JHtml::_('select.option',  'U', 'FLEXI_UNPUBLISHED'),
			JHtml::_('select.option',  'PE','FLEXI_PENDING'),
			JHtml::_('select.option',  'OQ','FLEXI_TO_WRITE'),
			JHtml::_('select.option',  'IP','FLEXI_IN_PROGRESS'),
			JHtml::_('select.option',  'A', 'FLEXI_ARCHIVED'),
		);

		$fieldname = 'filter_state';
		$elementid = 'filter_state';
		$value     = $filter_state;

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_STATE'),
			'html' => JHtml::_('select.genericlist',
				$options,
				$fieldname,
				array(
					'class' => $this->select_class,
					'size' => '1',
					'onchange' => 'document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				'value',
				'text',
				$value,
				$elementid,
				$translate = true
			),
		));


		// Build access level filter
		$options = JHtml::_('access.assetgroups');
		array_unshift($options, JHtml::_('select.option', '', '-'/*'JOPTION_SELECT_ACCESS'*/));

		$fieldname = 'filter_access';
		$elementid = 'filter_access';
		$value     = $filter_access;

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_ACCESS'),
			'html' => JHtml::_('select.genericlist',
				$options,
				$fieldname,
				array(
					'class' => $this->select_class,
					'size' => '1',
					'onchange' => 'document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				'value',
				'text',
				$value,
				$elementid,
				$translate = true
			),
		));


		// Build text search scope
		$scopes = array(
			'a.title'         => JText::_('FLEXI_TITLE'),
			'_desc_'          => JText::_('FLEXI_DESCRIPTION'),
			'ie.search_index' => JText::_('FLEXI_FIELDS_IN_BASIC_SEARCH_INDEX'),
			//'a.metadesc'      => 'Meta (' . JText::_('FLEXI_DESCRIPTION') . ')',
			//'a.metakey'       => 'Meta (' . JText::_('FLEXI_KEYWORDS') . ')',
			//'_meta_'          => 'Meta (' . JText::_('FLEXI_DESCRIPTION') . ' + ' . JText::_('FLEXI_KEYWORDS') . ')',
		);

		$lists['scope_tip'] = ''; //'<span class="hidden-phone ' . $this->tooltip_class . '" title="'.JText::_('FLEXI_SEARCH_TEXT_INSIDE').'" style="display: inline-block;"><i class="icon-info-2"></i></span>';
		$lists['scope'] = $this->getScopeSelectorDisplay($scopes, $scope);
		$this->scope_title = isset($scopes[$scope]) ? $scopes[$scope] : reset($scopes);


		// Text search filter value
		$lists['search'] = $search;


		// Table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order']     = $filter_order;


		/**
		 * Assign data to template
		 */

		$this->assocs_id = $assocs_id;
		$this->filter_cats = $filter_cats;
		$this->count_filters = $count_filters;

		$this->lists       = $lists;
		$this->rows        = $rows;
		$this->lang_assocs = $lang_assocs;
		$this->langs       = $langs;
		$this->pagination  = $pagination;
		$this->ordering    = $ordering;

		$this->perms  = FlexicontentHelperPerm::getPerm();
		$this->option = $option;
		$this->view   = $view;
		$this->state  = $this->get('State');

		$this->sidebar = null;


		/**
		 * Render view's template
		 */

		if ( $print_logging_info ) { global $fc_run_times; $start_microtime = microtime(true); }

		parent::display($tpl);

		if ( $print_logging_info ) @$fc_run_times['template_render'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
	}
}
