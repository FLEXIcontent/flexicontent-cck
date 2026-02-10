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
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Toolbar\ToolbarFactoryInterface;

JLoader::register('FlexicontentViewBaseRecords', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/base/view_records.php');

/**
 * HTML View class for the FLEXIcontent types screen
 */
class FlexicontentViewTypes extends FlexicontentViewBaseRecords
{
	var $proxy_option   = null;
	var $title_propname = 'name';
	var $state_propname = 'published';
	var $db_tbl         = 'flexicontent_types';
	var $name_singular  = 'type';

	public function display($tpl = null)
	{
		/**
		 * Initialise variables
		 */

		global $globalcats;
		$app      = \Joomla\CMS\Factory::getApplication();
		$jinput   = $app->input;
		$document = \Joomla\CMS\Factory::getApplication()->getDocument();
		$user     = \Joomla\CMS\Factory::getApplication()->getIdentity();
		$cparams  = \Joomla\CMS\Component\ComponentHelper::getParams('com_flexicontent');
		$session  = \Joomla\CMS\Factory::getApplication()->getSession();
		$db       = \Joomla\CMS\Factory::getContainer()->get(DatabaseInterface::class);

		$option   = $jinput->getCmd('option', '');
		$view     = $jinput->getCmd('view', '');
		$task     = $jinput->getCmd('task', '');
		$layout   = $jinput->getString('layout', 'default');

		$isAdmin  = $app->isClient('administrator');
		$isCtmpl  = $jinput->getCmd('tmpl') === 'component';

		// Some flags & constants
		$useAssocs = flexicontent_db::useAssociations();

		// Load Joomla language files of other extension
		if (!empty($this->proxy_option))
		{
			\Joomla\CMS\Factory::getApplication()->getLanguage()->load($this->proxy_option, JPATH_ADMINISTRATOR, 'en-GB', true);
			\Joomla\CMS\Factory::getApplication()->getLanguage()->load($this->proxy_option, JPATH_ADMINISTRATOR, null, true);
		}

		// Get model
		$model   = $this->getModel();
		$model_s = $this->getModel($this->name_singular);

		// Performance statistics
		if ($print_logging_info = $cparams->get('print_logging_info'))
		{
			global $fc_run_times;
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
		$filter_access    = $model->getState('filter_access');

		if (strlen($filter_state)) $count_filters++;
		if (strlen($filter_access)) $count_filters++;

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
				!\Joomla\CMS\Factory::getApplication()->getLanguage()->isRtl()
					? $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', array('version' => FLEXI_VHASH))
					: $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', array('version' => FLEXI_VHASH));
				!\Joomla\CMS\Factory::getApplication()->getLanguage()->isRtl()
					? $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x.css' : 'j3x.css'), array('version' => FLEXI_VHASH))
					: $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x_rtl.css' : 'j3x_rtl.css'), array('version' => FLEXI_VHASH));
			}
			else
			{
				!\Joomla\CMS\Factory::getApplication()->getLanguage()->isRtl()
					? $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/flexicontent.css', array('version' => FLEXI_VHASH))
					: $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/flexicontent_rtl.css', array('version' => FLEXI_VHASH));
			}

			// Add JS frameworks
			flexicontent_html::loadFramework('select2');

			// Load custom behaviours: form validation, popup tooltips
			\Joomla\CMS\HTML\HTMLHelper::_('behavior.formvalidator');
			\Joomla\CMS\HTML\HTMLHelper::_('bootstrap.tooltip');

			// Add js function to overload the joomla submitform validation
			$document->addScript(\Joomla\CMS\Uri\Uri::root(true).'/components/com_flexicontent/assets/js/admin.js', array('version' => FLEXI_VHASH));
			$document->addScript(\Joomla\CMS\Uri\Uri::root(true).'/components/com_flexicontent/assets/js/validate.js', array('version' => FLEXI_VHASH));
		}


		/**
		 * Create Submenu & Toolbar
		 */

		// Create Submenu (and also check access to current view)
		if ($layout !== 'typeslist')
		{
			FLEXIUtilities::ManagerSideMenu('CanTypes');
		}

		// Create document/toolbar titles
		$doc_title = \Joomla\CMS\Language\Text::_('FLEXI_TYPES');
		$site_title = $document->getTitle();
		\Joomla\CMS\Toolbar\ToolbarHelper::title($doc_title, 'briefcase');
		$document->setTitle($doc_title .' - '. $site_title);

		// Create the toolbar
		if (! $app->isClient('site'))
		{
			$this->setToolbar();
		}


		/**
		 * Get data from the model, note data retrieval must be before 
		 * getTotal() and getPagination() because it also calculates total rows
		 */

		if ( $print_logging_info )  $start_microtime = microtime(true);

		$rows        = $model->getItems();

		if ( $print_logging_info ) @$fc_run_times['execute_main_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;

		// Create pagination object
		$pagination = $this->get('Pagination');


		/**
		 * Create type's parameters
		 */

		foreach($rows as $row)
		{
			$row->config = new \Joomla\Registry\Registry($row->config);
		}


		/**
		 * Add usage information notices if these are enabled
		 */

		$conf_link = '<a href="index.php?option=com_config&amp;view=component&amp;component=com_flexicontent&amp;path=" class="' . $this->btn_sm_class . ' btn-info">'.\Joomla\CMS\Language\Text::_("FLEXI_CONFIG").'</a>';

		if ($cparams->get('show_usability_messages', 1))
		{
		}


		/**
		 * Create List Filters
		 */

		$lists = array();


		// Build publication state filter
		$fieldname = 'filter_state';
		$elementid = 'filter_state';
		$value     = $filter_state;

		//$options = \Joomla\CMS\HTML\HTMLHelper::_('jgrid.publishedOptions');
		$options = array();

		foreach ($model_s->supported_conditions as $condition_value => $condition_name)
		{
			$options[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option', $condition_value, \Joomla\CMS\Language\Text::_($condition_name));
		}
		array_unshift($options, \Joomla\CMS\HTML\HTMLHelper::_('select.option', '', '-'/*'FLEXI_STATE'*/));

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => \Joomla\CMS\Language\Text::_('FLEXI_STATE'),
			'html' => \Joomla\CMS\HTML\HTMLHelper::_('select.genericlist',
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
		$options = \Joomla\CMS\HTML\HTMLHelper::_('access.assetgroups');
		array_unshift($options, \Joomla\CMS\HTML\HTMLHelper::_('select.option', '', '-'/*'JOPTION_SELECT_ACCESS'*/));

		$fieldname = 'filter_access';
		$elementid = 'filter_access';
		$value     = $filter_access;

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => \Joomla\CMS\Language\Text::_('FLEXI_ACCESS'),
			'html' => \Joomla\CMS\HTML\HTMLHelper::_('select.genericlist',
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

		$this->count_filters = $count_filters;

		$this->lists       = $lists;
		$this->rows        = $rows;
		$this->pagination  = $pagination;

		$this->perms  = FlexicontentHelperPerm::getPerm();
		$this->option = $option;
		$this->view   = $view;
		$this->state  = $this->get('State');

		if (!$jinput->getCmd('nosidebar'))
		{
			$this->sidebar = null;

			if(FLEXI_J30GE && !FLEXI_J40GE) $this->sidebar = JHtmlSidebar::render();
			if(FLEXI_J40GE) $this->sidebar = \Joomla\CMS\HTML\Helpers\Sidebar::render();
		}

		/**
		 * Render view's template
		 */

		if ( $print_logging_info ) { global $fc_run_times; $start_microtime = microtime(true); }

		parent::display($tpl);

		if ( $print_logging_info ) @$fc_run_times['template_render'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
	}



	/**
	 * Method to configure the toolbar for this view.
	 *
	 * @access	public
	 * @return	void
	 */
	function setToolbar()
	{
		$user     = \Joomla\CMS\Factory::getApplication()->getIdentity();
		$document = \Joomla\CMS\Factory::getApplication()->getDocument();
		$toolbar  = $toolbar = \Joomla\CMS\Toolbar\Toolbar::getInstance('toolbar');
		$perms    = FlexicontentHelperPerm::getPerm();
		$session  = \Joomla\CMS\Factory::getApplication()->getSession();
		$useAssocs= flexicontent_db::useAssociations();

		$js = '';

		$contrl = $this->ctrl . '.';
		$contrl_s = $this->name_singular . '.';

		$loading_msg = flexicontent_html::encodeHTML(\Joomla\CMS\Language\Text::_('FLEXI_LOADING') .' ... '. \Joomla\CMS\Language\Text::_('FLEXI_PLEASE_WAIT'), 2);

		// Get if state filter is active
		$model   = $this->getModel();
		$model_s = $this->getModel($this->name_singular);
		$filter_state = $model->getState('filter_state');

		$hasCreate    = $perms->CanTypes;
		$hasEdit      = $perms->CanTypes;
		$hasEditState = $perms->CanTypes;
		$hasDelete    = $perms->CanTypes;
		$hasCopy      = $perms->CanTypes;

		if ($hasCreate)
		{
			\Joomla\CMS\Toolbar\ToolbarHelper::addNew($contrl.'add');
		}

		if (0 && $hasEdit)
		{
			\Joomla\CMS\Toolbar\ToolbarHelper::editList($contrl.'edit');
		}

		$btn_arr = array();
		$states_applicable = array();

		if ($hasEditState)
		{
			$states_applicable = $model_s->supported_conditions;
			unset($states_applicable[-2]);

			/*
			\Joomla\CMS\Toolbar\ToolbarHelper::publishList($contrl.'publish', 'JTOOLBAR_ENABLE', true);
			\Joomla\CMS\Toolbar\ToolbarHelper::unpublishList($contrl.'unpublish', 'JTOOLBAR_DISABLE', true);
			\Joomla\CMS\Toolbar\ToolbarHelper::archiveList($contrl.'archive', 'JTOOLBAR_ARCHIVE', true);
			*/
		}


		/**
		 * Delete data buttons (Record , Assignments, Assignments + Record)
		 */
		if ($filter_state == -2 && $hasDelete)
		{
			$btn_arr = array();

			//\Joomla\CMS\Toolbar\ToolbarHelper::deleteList(\Joomla\CMS\Language\Text::_('FLEXI_ARE_YOU_SURE'), $contrl.'remove');
			$msg_alert   = \Joomla\CMS\Language\Text::sprintf('FLEXI_SELECT_LIST_ITEMS_TO', \Joomla\CMS\Language\Text::_('FLEXI_DELETE'));
			$msg_confirm = \Joomla\CMS\Language\Text::_('FLEXI_ARE_YOU_SURE');
			$btn_task    = $contrl.'remove';
			$extra_js    = "";
			$btn_arr[] = flexicontent_html::addToolBarButton(
				'FLEXI_RECORDS', 'delete', '', $msg_alert, $msg_confirm,
				$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true,
				$this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? 'btn-warning' : '') . ' ' . $this->tooltip_class, 'icon-remove',
				'data-placement="right" title="' . flexicontent_html::encodeHTML(\Joomla\CMS\Language\Text::_('FLEXI_ABOUT_DELETING_RECORDS_WITHOUT_ASSIGNMENTS'), 2) . '"', $auto_add = 0, $tag_type='button'
			);

			if ($model::canDelRelated)
			{
				$msg_alert   = \Joomla\CMS\Language\Text::sprintf('FLEXI_SELECT_LIST_ITEMS_TO', \Joomla\CMS\Language\Text::_('FLEXI_ASSIGNMENTS'));
				$msg_confirm = \Joomla\CMS\Language\Text::_('FLEXI_ARE_YOU_SURE');
				$btn_task    = $contrl.'remove_relations';
				$extra_js    = "";
				$btn_arr[] = flexicontent_html::addToolBarButton(
					'FLEXI_ASSIGNMENTS', 'delete', '', $msg_alert, $msg_confirm,
					$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true,
					$this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? 'btn-warning' : '') . ' ' . $this->tooltip_class, 'icon-remove',
					'data-placement="right" title="' . flexicontent_html::encodeHTML(\Joomla\CMS\Language\Text::_('FLEXI_ABOUT_DELETING_ASSIGNMENTS'), 2) . '"', $auto_add = 0, $tag_type='button'
				);

				$msg_alert   = \Joomla\CMS\Language\Text::sprintf('FLEXI_SELECT_LIST_ITEMS_TO', \Joomla\CMS\Language\Text::_('FLEXI_ASSIGNMENTS_N_RECORDS'));
				$msg_confirm = \Joomla\CMS\Language\Text::_('FLEXI_ARE_YOU_SURE');
				$btn_task    = $contrl.'remove_cascade';
				$extra_js    = "";
				$btn_arr[] = flexicontent_html::addToolBarButton(
					'FLEXI_ASSIGNMENTS_N_RECORDS', 'delete', '', $msg_alert, $msg_confirm,
					$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true,
					$this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? 'btn-warning' : '') . ' ' . $this->tooltip_class, 'icon-remove',
					'data-placement="right" title="' . flexicontent_html::encodeHTML(\Joomla\CMS\Language\Text::_('FLEXI_ABOUT_DELETING_ASSIGNMENTS_N_RECORDS'), 2) . '"', $auto_add = 0, $tag_type='button'
				);
			}

			$drop_btn = '
				<button id="toolbar-delete" class="' . $this->btn_sm_class . ' dropdown-toggle btn-fcaction" data-toggle="dropdown" data-bs-toggle="dropdown">
					<span title="'.\Joomla\CMS\Language\Text::_('FLEXI_DELETE').'" class="icon-delete"></span>
					'.\Joomla\CMS\Language\Text::_('FLEXI_DELETE').'
					<span class="caret"></span>
				</button>';
			array_unshift($btn_arr, $drop_btn);
			flexicontent_html::addToolBarDropMenu($btn_arr, 'action_btns_group', ' ');
		}
		elseif ($hasEdit)
		{
			if (isset($model_s->supported_conditions[-2]))
			{
				$states_applicable['T'] = 0;
				//\Joomla\CMS\Toolbar\ToolbarHelper::trash($contrl.'trash');
			}
		}

		$btn_arr = $this->getStateButtons($states_applicable);
		$this->addStateButtons($btn_arr);


		/**
		 * Copy record
		 */
		if ($hasCopy)
		{
			\Joomla\CMS\Toolbar\ToolbarHelper::custom($contrl.'copy', 'copy.png', 'copy_f2.png', 'FLEXI_COPY');
		}


		/**
		 * Maintenance button (Check-in, Verify Tag mappings, Assignments + Record)
		 */

		$btn_arr = array();

		//\Joomla\CMS\Toolbar\ToolbarHelper::checkin($contrl . 'checkin');
		$btn_task  = $contrl . 'checkin';
		$btn_arr[] = flexicontent_html::addToolBarButton(
			'JTOOLBAR_CHECKIN', $btn_name = 'checkin', $full_js = '',
			$msg_alert = '', $msg_confirm = '',
			$btn_task, $extra_js = '', $btn_list=true, $btn_menu=true, $btn_confirm=false,
			$this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? '_DDI_class_ ' . $this->btn_iv_class : '') . ' ' . $this->popover_class, $btn_icon='icon-checkin',
			'data-placement="right" data-content="' . flexicontent_html::encodeHTML(\Joomla\CMS\Language\Text::_('FLEXI_MAINTENANCE_CHECKIN_DESC'), 2) . '"', $auto_add = 0, $tag_type='button'
		);

		$appsman_path = JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'views'.DS.'appsman';
		if (file_exists($appsman_path))
		{
			$btn_icon = 'icon-download';
			$btn_name = 'download';
			$btn_task = 'appsman.exportxml';
			$extra_js = " var f=document.getElementById('adminForm'); f.elements['view'].value='appsman'; jQuery('<input>').attr({type: 'hidden', name: 'table', value: '" . $this->db_tbl . "'}).appendTo(jQuery(f));";
			$btn_arr[] = flexicontent_html::addToolBarButton(
				'Export now',
				$btn_name, $full_js='', $msg_alert='', $msg_confirm=\Joomla\CMS\Language\Text::_('FLEXI_EXPORT_NOW_AS_XML'),
				$btn_task, $extra_js, $btn_list=false, $btn_menu=true, $btn_confirm=true,
				$this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? '_DDI_class_ ' . $this->btn_iv_class : '') . ' ' . $this->popover_class, $btn_icon,
				'data-placement="right" data-content="' . flexicontent_html::encodeHTML(\Joomla\CMS\Language\Text::_(''), 2) . '"', $auto_add = 0, $tag_type='button'
			);

			$btn_icon = 'icon-box-add';
			$btn_name = 'box-add';
			$btn_task = 'appsman.addtoexport';
			$extra_js = " var f=document.getElementById('adminForm'); f.elements['view'].value='appsman'; jQuery('<input>').attr({type: 'hidden', name: 'table', value: '" . $this->db_tbl . "'}).appendTo(jQuery(f));";
			$btn_arr[] = flexicontent_html::addToolBarButton(
				'Add to export',
				$btn_name, $full_js='', $msg_alert='', $msg_confirm=\Joomla\CMS\Language\Text::_('FLEXI_ADD_TO_EXPORT_LIST'),
				$btn_task, $extra_js, $btn_list=false, $btn_menu=true, $btn_confirm=true,
				$this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? '_DDI_class_ ' . $this->btn_iv_class : '') . ' ' . $this->popover_class, $btn_icon,
				'data-placement="right" data-content="' . flexicontent_html::encodeHTML(\Joomla\CMS\Language\Text::_(''), 2) . '"', $auto_add = 0, $tag_type='button'
			);
		}

		if (count($btn_arr))
		{
			$drop_btn = '
				<button id="toolbar-maintenance" class="' . $this->btn_sm_class . ' dropdown-toggle btn-fcaction" data-toggle="dropdown" data-bs-toggle="dropdown">
					<span title="'.\Joomla\CMS\Language\Text::_('FLEXI_MAINTENANCE').'" class="icon-tools"></span>
					'.\Joomla\CMS\Language\Text::_('FLEXI_MAINTENANCE').'
					<span class="caret"></span>
				</button>';

			array_unshift($btn_arr, $drop_btn);
			flexicontent_html::addToolBarDropMenu($btn_arr, 'maintenance-btns-group', ' ');
		}


		if ($perms->CanConfig)
		{
			$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
			$_width  = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
			$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
			$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
			\Joomla\CMS\Toolbar\ToolbarHelper::preferences('com_flexicontent', $_height, $_width, 'Configuration');
		}


		if ($js)
		{
			$document->addScriptDeclaration('
				jQuery(document).ready(function(){
					' . $js . '
				});
			');
		}
	}
}