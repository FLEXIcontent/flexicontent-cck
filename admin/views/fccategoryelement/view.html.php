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
 * HTML View class for the fccategoryelement screen
 */
class FlexicontentViewFccategoryelement extends FlexicontentViewBaseRecords
{
	var $proxy_option   = 'com_categories';
	var $title_propname = 'title';
	var $state_propname = 'published';
	var $db_tbl         = 'categories';
	var $name_singular  = 'category';

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
		$perms    = FlexicontentHelperPerm::getPerm();

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

		if (JFactory::getApplication()->isClient('site'))
		{
			// Note : we use some strings from administrator part, so we will also load administrator language file
			// TODO: remove this need by moving common language string to different file ?

			// Load english language file for 'com_flexicontent' component then override with current language file
			JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, 'en-GB', true);
			JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, null, true);
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
			$item_lang   = $app->getUserStateFromRequest( $option.'.'.$view.'.item_lang', 'item_lang', '', 'string' );
			$created_by  = $app->getUserStateFromRequest( $option.'.'.$view.'.created_by', 'created_by', 0, 'int' );

			$assocanytrans = $user->authorise('flexicontent.assocanytrans', 'com_flexicontent');

			if (!$assocanytrans && !$created_by)
			{
				$created_by = $user->id;
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
		$filter_level     = $model->getState('filter_level');
		$filter_access    = $model->getState('filter_access');
		$filter_lang      = $model->getState('filter_lang');
		$filter_author    = $model->getState('filter_author');

		if (strlen($filter_state)) $count_filters++;
		if ($filter_cats) $count_filters++;
		if ($filter_level) $count_filters++;
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
					? $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x.css' : 'j3x.css'), array('version' => FLEXI_VHASH))
					: $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x_rtl.css' : 'j3x_rtl.css'), array('version' => FLEXI_VHASH));
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
			JHtml::_('behavior.formvalidator');
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
		$pagination  = $model->getPagination();
		$authors     = $model->getAuthorslist();

		$lang_assocs = $useAssocs ? $model->getLangAssocs() : array();
		$langs       = FLEXIUtilities::getLanguages('code');
		$categories  = $globalcats ?: array();

		if ( $print_logging_info ) @$fc_run_times['execute_main_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;


		// Ordering active FLAG
		$ordering = $filter_order === 'a.lft';

		// Parse configuration for every category
   	foreach ($rows as $cat)
		{
			$cat->config = new JRegistry($cat->params);
		}



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
			'label_extra_class' => ($value ? ' fc-lbl-inverted' : ''),
			'html' => flexicontent_cats::buildcatselect(
				$categories,
				$fieldname,
				$value,
				$displaytype = '-',
				array(
					'class' => $this->select_class,
					'size' => '1',
					'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				$check_published = ! ($perms->ViewAllCats || $perms->CanCats),
				$check_perms = false
			),
		));


		// Build depth level filter
		$options = array(
			JHtml::_('select.option', '', '-'/*'FLEXI_SELECT_MAX_DEPTH'*/),
		);

		for ($i = 1; $i <= 10; $i++)
		{
			$options[]	= JHtml::_('select.option', $i, $i);
		}

		$fieldname = 'filter_level';
		$elementid = 'filter_level';
		$value     = $filter_level;

		if (1)
		{
			$lists[$elementid] = $this->getFilterDisplay(array(
				'label' => JText::_('FLEXI_MAX_DEPTH'),
				'label_extra_class' => ($value ? ' fc-lbl-inverted' : ''),
				'html' => JHtml::_('select.genericlist',
					$options,
					$fieldname,
					array(
						'class' => $this->select_class,
						'size' => '1',
						'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
					),
					'value',
					'text',
					$value,
					$elementid,
					$translate = true
				),
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
				'label_extra_class' => ($value ? ' fc-lbl-inverted' : ''),
				'html' => flexicontent_html::buildauthorsselect(
					$authors,
					$fieldname,
					$value,
					$displaytype = '-',
					array(
						'class' => $this->select_class,
						'size' => '1',
						'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
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
		$value     = $filter_lang;

		if (!$assocs_id || !$item_lang)
		{
			$lists[$elementid] = $this->getFilterDisplay(array(
				'label' => JText::_('FLEXI_LANGUAGE'),
				'label_extra_class' => ($value ? ' fc-lbl-inverted' : ''),
				'html' => flexicontent_html::buildlanguageslist(
					$fieldname,
					array(
						'class' => $this->select_class,
						'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
					),
					$value,
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
		//$options = JHtml::_('jgrid.publishedOptions');
		$options = array();

		foreach ($model_s->supported_conditions as $condition_value => $condition_name)
		{
			$options[] = JHtml::_('select.option', $condition_value, $condition_name);
		}
		array_unshift($options, JHtml::_('select.option', '', '-'/*'FLEXI_SELECT_STATE'*/));

		$fieldname = 'filter_state';
		$elementid = 'filter_state';
		$value     = $filter_state;

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_STATE'),
			'label_extra_class' => ($value ? ' fc-lbl-inverted' : ''),
			'html' => JHtml::_('select.genericlist',
				$options,
				$fieldname,
				array(
					'class' => $this->select_class,
					'size' => '1',
					'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
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
			'label_extra_class' => ($value ? ' fc-lbl-inverted' : ''),
			'html' => JHtml::_('select.genericlist',
				$options,
				$fieldname,
				array(
					'class' => $this->select_class,
					'size' => '1',
					'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				'value',
				'text',
				$value,
				$elementid,
				$translate = true
			),
		));


		// Build text search scope
		$scopes = null;

		$lists['scope_tip'] = '';
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
		$this->count_filters = $count_filters;

		$this->lists       = $lists;
		$this->rows        = $rows;
		$this->lang_assocs = $lang_assocs;
		$this->langs       = $langs;
		$this->pagination  = $pagination;
		$this->ordering    = $ordering;

		$this->perms  = $perms;
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
