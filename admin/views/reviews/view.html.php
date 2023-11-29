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
 * HTML View class for the reviews backend manager
 */
class FlexicontentViewReviews extends FlexicontentViewBaseRecords
{
	var $proxy_option   = null;
	var $title_propname = 'title';
	var $state_propname = 'state';
	var $db_tbl         = 'flexicontent_reviews';
	var $name_singular  = 'review';

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


		/**
		 * Get filters and ordering
		 */

		$count_filters = 0;

		// Order and order direction
		$filter_order      = $model->getState('filter_order');
		$filter_order_Dir  = $model->getState('filter_order_Dir');

		// Various filters
		$filter_state     = $model->getState('filter_state');
		$filter_approved  = $model->getState('filter_approved');

		if (strlen($filter_state)) $count_filters++;
		if (strlen($filter_approved)) $count_filters++;

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
		FLEXIUtilities::ManagerSideMenu('CanReviews');

		// Create document/toolbar titles
		$doc_title = JText::_('FLEXI_REVIEWS');
		$site_title = $document->getTitle();
		JToolbarHelper::title($doc_title, 'reviews');
		$document->setTitle($doc_title .' - '. $site_title);

		// Create the toolbar
		$this->setToolbar();


		/**
		 * Get data from the model, note data retrieval must be before 
		 * getTotal() and getPagination() because it also calculates total rows
		 */

		if ( $print_logging_info )  $start_microtime = microtime(true);

		$rows        = $model->getItems();

		// Get content items related to reviews
		$contentRows = $model->getContentRows($rows);

		if ( $print_logging_info ) @$fc_run_times['execute_main_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;

		// Create pagination object
		$pagination = $this->get('Pagination');


		/**
		 * Add usage information notices if these are enabled
		 */

		$conf_link = '<a href="index.php?option=com_config&amp;view=component&amp;component=com_flexicontent&amp;path=" class="' . $this->btn_sm_class . ' btn-info">'.JText::_("FLEXI_CONFIG").'</a>';

		if ($cparams->get('show_usability_messages', 1))
		{
		}


		/**
		 * Create List Filters
		 */

		$lists = array();


		// Build publication state filter
		//$options = JHtml::_('jgrid.publishedOptions');
		$options = array();

		foreach ($model_s->supported_conditions as $condition_value => $condition_name)
		{
			$options[] = JHtml::_('select.option', $condition_value, JText::_($condition_name));
		}
		array_unshift($options, JHtml::_('select.option', '', '-'/*'FLEXI_STATE'*/));

		$fieldname = 'filter_state';
		$elementid = 'filter_state';

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_STATE'),
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
				$filter_state,
				$elementid,
				$translate = true
			),
		));


		// Build publication approval filter
		$options 	= array();
		$options[] = JHtml::_('select.option',  '', '-'/*JText::_( 'FLEXI_SELECT_STATE' )*/ );
		$options[] = JHtml::_('select.option',  '1', JText::_( 'JYES' ) );
		$options[] = JHtml::_('select.option',  '0', JText::_( 'JNO' ) );

		$fieldname = 'filter_approved';
		$elementid = 'filter_approved';

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => JText::_('FLEXI_REVIEW_APPROVED'),
			'html' => JHtml::_('select.genericlist',
				$options,
				$fieldname,
				array(
					'class' => $this->select_class,
					'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				'value',
				'text',
				$filter_approved,
				$elementid,
				$translate = true
			)
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
		$this->contentRows = $contentRows;
		$this->pagination  = $pagination;

		$this->perms  = FlexicontentHelperPerm::getPerm();
		$this->option = $option;
		$this->view   = $view;
		$this->state  = $this->get('State');

		if (!$jinput->getCmd('nosidebar'))
		{
			$this->sidebar = FLEXI_J30GE ? JHtmlSidebar::render() : null;
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
		$user     = JFactory::getUser();
		$document = JFactory::getDocument();
		$toolbar  = JToolbar::getInstance('toolbar');
		$perms    = FlexicontentHelperPerm::getPerm();
		$session  = JFactory::getSession();
		$useAssocs= flexicontent_db::useAssociations();

		$js = '';

		$contrl = $this->ctrl . '.';
		$contrl_s = $this->name_singular . '.';

		$loading_msg = flexicontent_html::encodeHTML(JText::_('FLEXI_LOADING') .' ... '. JText::_('FLEXI_PLEASE_WAIT'), 2);

		// Get if state filter is active
		$model   = $this->getModel();
		$model_s = $this->getModel($this->name_singular);
		$filter_state = $model->getState('filter_state');

		$hasCreate    = $perms->CanCreateReviews;
		$hasEdit      = $perms->CanReviews;
		$hasEditState = $perms->CanReviews;
		$hasDelete    = $perms->CanReviews;

		if ($hasCreate)
		{
			JToolbarHelper::addNew($contrl.'add');
		}

		if (0 && $hasEdit)
		{
			JToolbarHelper::editList($contrl.'edit');
		}

		$btn_arr = array();
		$states_applicable = array();

		if ($hasEditState)
		{
			$states_applicable = $model_s->supported_conditions;
			unset($states_applicable[-2]);

			/*
			JToolbarHelper::publishList($contrl.'publish', 'JTOOLBAR_PUBLISH', true);
			JToolbarHelper::unpublishList($contrl.'unpublish', 'JTOOLBAR_UNPUBLISH', true);
			JToolbarHelper::archiveList($contrl.'archive', 'JTOOLBAR_ARCHIVE', true);
			*/
		}


		/**
		 * Delete data buttons (Record , Assignments, Assignments + Record)
		 */
		if ($filter_state == -2 && $hasDelete)
		{
			$btn_arr = array();

			//JToolbarHelper::deleteList(JText::_('FLEXI_ARE_YOU_SURE'), $contrl.'remove');
			$msg_alert   = JText::sprintf('FLEXI_SELECT_LIST_ITEMS_TO', JText::_('FLEXI_DELETE'));
			$msg_confirm = JText::_('FLEXI_ARE_YOU_SURE');
			$btn_task    = $contrl.'remove';
			$extra_js    = "";
			$btn_arr[] = flexicontent_html::addToolBarButton(
				'FLEXI_RECORDS', 'delete', '', $msg_alert, $msg_confirm,
				$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true,
				$this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? '_DDI_class_ ' . 'btn-warning' : '') . ' ' . $this->tooltip_class, 'icon-remove',
				'data-placement="right" title="' . flexicontent_html::encodeHTML(JText::_('FLEXI_ABOUT_DELETING_RECORDS_WITHOUT_ASSIGNMENTS'), 2) . '"', $auto_add = 0, $tag_type='button'
			);

			if ($model::canDelRelated)
			{
				$msg_alert   = JText::sprintf('FLEXI_SELECT_LIST_ITEMS_TO', JText::_('FLEXI_ASSIGNMENTS'));
				$msg_confirm = JText::_('FLEXI_ARE_YOU_SURE');
				$btn_task    = $contrl.'remove_relations';
				$extra_js    = "";
				$btn_arr[] = flexicontent_html::addToolBarButton(
					'FLEXI_ASSIGNMENTS', 'delete', '', $msg_alert, $msg_confirm,
					$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true,
					$this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? '_DDI_class_ ' . 'btn-warning' : '') . ' ' . $this->tooltip_class, 'icon-remove',
					'data-placement="right" title="' . flexicontent_html::encodeHTML(JText::_('FLEXI_ABOUT_DELETING_ASSIGNMENTS'), 2) . '"', $auto_add = 0, $tag_type='button'
				);

				$msg_alert   = JText::sprintf('FLEXI_SELECT_LIST_ITEMS_TO', JText::_('FLEXI_ASSIGNMENTS_N_RECORDS'));
				$msg_confirm = JText::_('FLEXI_ARE_YOU_SURE');
				$btn_task    = $contrl.'remove_cascade';
				$extra_js    = "";
				$btn_arr[] = flexicontent_html::addToolBarButton(
					'FLEXI_ASSIGNMENTS_N_RECORDS', 'delete', '', $msg_alert, $msg_confirm,
					$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true,
					$this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? '_DDI_class_ ' . 'btn-warning' : '') . ' ' . $this->tooltip_class, 'icon-remove',
					'data-placement="right" title="' . flexicontent_html::encodeHTML(JText::_('FLEXI_ABOUT_DELETING_ASSIGNMENTS_N_RECORDS'), 2) . '"', $auto_add = 0, $tag_type='button'
				);
			}

			$drop_btn = '
				<button id="toolbar-delete" class="' . $this->btn_sm_class . ' dropdown-toggle btn-fcaction" data-toggle="dropdown" data-bs-toggle="dropdown">
					<span title="'.JText::_('FLEXI_DELETE').'" class="icon-delete"></span>
					'.JText::_('FLEXI_DELETE').'
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
				//JToolbarHelper::trash($contrl.'trash');
			}
		}

		$btn_arr = $this->getStateButtons($states_applicable);
		$this->addStateButtons($btn_arr);


		/**
		 * Maintenance button (Check-in, Verify Tag mappings, Assignments + Record)
		 */

		$btn_arr = array();

		//JToolbarHelper::checkin($contrl . 'checkin');
		$btn_task  = $contrl . 'checkin';
		$btn_arr[] = flexicontent_html::addToolBarButton(
			'JTOOLBAR_CHECKIN', $btn_name = 'checkin', $full_js = '',
			$msg_alert = '', $msg_confirm = '',
			$btn_task, $extra_js = '', $btn_list=true, $btn_menu=true, $btn_confirm=false,
			$this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? '_DDI_class_ ' . $this->btn_iv_class : '') . ' ' . $this->popover_class, $btn_icon='icon-checkin',
			'data-placement="right" data-content="' . flexicontent_html::encodeHTML(JText::_('FLEXI_MAINTENANCE_CHECKIN_DESC'), 2) . '"', $auto_add = 0, $tag_type='button'
		);

		if ($perms->CanConfig)
		{
			$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
			$_width  = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
			$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
			$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
			JToolbarHelper::preferences('com_flexicontent', $_height, $_width, 'Configuration');
		}


		if (count($btn_arr))
		{
			$drop_btn = '
				<button id="toolbar-maintenance" class="' . $this->btn_sm_class . ' dropdown-toggle btn-fcaction" data-toggle="dropdown" data-bs-toggle="dropdown">
					<span title="'.JText::_('FLEXI_MAINTENANCE').'" class="icon-tools"></span>
					'.JText::_('FLEXI_MAINTENANCE').'
					<span class="caret"></span>
				</button>';

			array_unshift($btn_arr, $drop_btn);
			flexicontent_html::addToolBarDropMenu($btn_arr, 'maintenance-btns-group', ' ');
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
