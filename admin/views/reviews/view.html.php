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
		$app      = \Joomla\CMS\Factory::getApplication();
		$jinput   = $app->input;
		$document = \Joomla\CMS\Factory::getDocument();
		$user     = \Joomla\CMS\Factory::getUser();
		$cparams  = \Joomla\CMS\Component\ComponentHelper::getParams('com_flexicontent');
		$session  = \Joomla\CMS\Factory::getApplication()->getSession();
		$db       = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

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
			\Joomla\CMS\Factory::getLanguage()->load($this->proxy_option, JPATH_ADMINISTRATOR, 'en-GB', true);
			\Joomla\CMS\Factory::getLanguage()->load($this->proxy_option, JPATH_ADMINISTRATOR, null, true);
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
		$filter_hastext   = $model->getState('filter_hastext');

		$filter_item      = (array) $model->getState('filter_item');
		$filter_user      = (array) $model->getState('filter_user');

		if (strlen($filter_state)) $count_filters++;
		if (strlen($filter_approved)) $count_filters++;
		if (strlen($filter_hastext)) $count_filters++;
		if ($filter_item) $count_filters++;
		if ($filter_user) $count_filters++;

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
				!\Joomla\CMS\Factory::getLanguage()->isRtl()
					? $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', array('version' => FLEXI_VHASH))
					: $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', array('version' => FLEXI_VHASH));
				!\Joomla\CMS\Factory::getLanguage()->isRtl()
					? $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x.css' : 'j3x.css'), array('version' => FLEXI_VHASH))
					: $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x_rtl.css' : 'j3x_rtl.css'), array('version' => FLEXI_VHASH));

				// Modern (multi-column) layout of the filters box, selected in component configuration
				if ((int) \Joomla\CMS\Component\ComponentHelper::getParams('com_flexicontent')->get('backend_filters_style', 1) === 1)
				{
					$document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/flexicontent_filters_modern.css', array('version' => FLEXI_VHASH));

					// Filter presets (stored in the browser)
					foreach (array('FLEXI_PRESET_SAVE', 'FLEXI_PRESET_SAVE_TITLE', 'FLEXI_PRESET_NAME', 'FLEXI_PRESET_MAX', 'FLEXI_PRESET_DELETE', 'FLEXI_PRESET_DELETE_CONFIRM', 'FLEXI_PRESET_HELP') as $_preset_txt)
					{
						\Joomla\CMS\Language\Text::script($_preset_txt);
					}
					$document->addScript(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/js/filter_presets.js', array('version' => FLEXI_VHASH), array('defer' => true));
				}
			}
			else
			{
				!\Joomla\CMS\Factory::getLanguage()->isRtl()
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
		FLEXIUtilities::ManagerSideMenu('CanReviews');

		// Create document/toolbar titles
		$doc_title = \Joomla\CMS\Language\Text::_('FLEXI_REVIEWS');
		$site_title = $document->getTitle();
		\Joomla\CMS\Toolbar\ToolbarHelper::title($doc_title, 'reviews');
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

		// Number of reviews of each listed item
		$reviewCounts = $model->getReviewCounts($rows);

		if ( $print_logging_info ) @$fc_run_times['execute_main_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;

		// Create pagination object
		$pagination = $this->get('Pagination');


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
		//$options = \Joomla\CMS\HTML\HTMLHelper::_('jgrid.publishedOptions');
		$options = array();

		foreach ($model_s->supported_conditions as $condition_value => $condition_name)
		{
			$options[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option', $condition_value, \Joomla\CMS\Language\Text::_($condition_name));
		}
		array_unshift($options, \Joomla\CMS\HTML\HTMLHelper::_('select.option', '', '-'/*'FLEXI_STATE'*/));

		$fieldname = 'filter_state';
		$elementid = 'filter_state';

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
				$filter_state,
				$elementid,
				$translate = true
			),
		));


		// Build publication approval filter
		$options 	= array();
		$options[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option',  '', '-'/*\Joomla\CMS\Language\Text::_( 'FLEXI_SELECT_STATE' )*/ );
		$options[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option',  '1', \Joomla\CMS\Language\Text::_( 'JYES' ) );
		$options[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option',  '0', \Joomla\CMS\Language\Text::_( 'JNO' ) );

		$fieldname = 'filter_approved';
		$elementid = 'filter_approved';

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => \Joomla\CMS\Language\Text::_('FLEXI_REVIEW_APPROVED'),
			'html' => \Joomla\CMS\HTML\HTMLHelper::_('select.genericlist',
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


		// Build review text filter: reviews with text, or only with rating (voting with the stars)
		$options 	= array();
		$options[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option',  '', '-');
		$options[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option',  'text', \Joomla\CMS\Language\Text::_('FLEXI_REVIEW_FILTER_WITH_TEXT'));
		$options[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option',  'rating', \Joomla\CMS\Language\Text::_('FLEXI_REVIEW_FILTER_RATING_ONLY'));

		$fieldname = 'filter_hastext';
		$elementid = 'filter_hastext';

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => \Joomla\CMS\Language\Text::_('FLEXI_REVIEW_FILTER_TEXT'),
			'html' => \Joomla\CMS\HTML\HTMLHelper::_('select.genericlist',
				$options,
				$fieldname,
				array(
					'class' => $this->select_class,
					'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				'value',
				'text',
				$filter_hastext,
				$elementid,
				$translate = true
			)
		));


		// Build reviewed items filter (only items having reviews)
		$options = array();

		foreach ($model->getReviewedItems() as $reviewed_item)
		{
			$options[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option', $reviewed_item->id, $reviewed_item->title ?: (\Joomla\CMS\Language\Text::_('FLEXI_ID') . ': ' . $reviewed_item->id));
		}

		$fieldname = 'filter_item[]';
		$elementid = 'filter_item';

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => \Joomla\CMS\Language\Text::_('FLEXI_ITEM'),
			'label_extra_class' => ($filter_item ? ' fc-lbl-inverted' : ''),
			'html' => \Joomla\CMS\HTML\HTMLHelper::_('select.genericlist',
				$options,
				$fieldname,
				array(
					'class' => $this->select_class,
					'multiple' => 'multiple',
					'size' => '3',
					'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				'value',
				'text',
				$filter_item,
				$elementid,
				$translate = false
			)
		));


		// Build reviewers filter (only users having reviews, guests have id 0)
		$options = array();

		foreach ($model->getReviewers() as $reviewer)
		{
			$options[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option', $reviewer->id, $reviewer->id
				? ($reviewer->name ?: (\Joomla\CMS\Language\Text::_('FLEXI_ID') . ': ' . $reviewer->id))
				: \Joomla\CMS\Language\Text::_('FLEXI_GUEST')
			);
		}

		$fieldname = 'filter_user[]';
		$elementid = 'filter_user';

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => \Joomla\CMS\Language\Text::_('FLEXI_USER'),
			'label_extra_class' => ($filter_user ? ' fc-lbl-inverted' : ''),
			'html' => \Joomla\CMS\HTML\HTMLHelper::_('select.genericlist',
				$options,
				$fieldname,
				array(
					'class' => $this->select_class,
					'multiple' => 'multiple',
					'size' => '3',
					'onchange' => 'if (!!document.adminForm.limitstart) document.adminForm.limitstart.value=0; Joomla.submitform();',
				),
				'value',
				'text',
				$filter_user,
				$elementid,
				$translate = false
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
		$this->reviewCounts = $reviewCounts;
		$this->pagination  = $pagination;

		$this->perms  = FlexicontentHelperPerm::getPerm();
		$this->option = $option;
		$this->view   = $view;
		$this->state  = $this->get('State');

		// The reviews manager is displayed full width, without the FLEXIcontent sidebar menu
		$this->sidebar = null;

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
		$user     = \Joomla\CMS\Factory::getUser();
		$document = \Joomla\CMS\Factory::getDocument();
		$toolbar  = \Joomla\CMS\Toolbar\Toolbar::getInstance('toolbar');
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

		$hasCreate    = $perms->CanCreateReviews;
		$hasEdit      = $perms->CanReviews;
		$hasEditState = $perms->CanReviews;
		$hasDelete    = $perms->CanReviews;

		if ($hasCreate)
		{
			\Joomla\CMS\Toolbar\ToolbarHelper::addNew($contrl.'add');
		}

		// Batch approve / unapprove of the selected reviews
		if ($hasEditState)
		{
			\Joomla\CMS\Toolbar\ToolbarHelper::custom($contrl.'approved', 'checkmark', '', 'FLEXI_REVIEW_APPROVE', true);
			\Joomla\CMS\Toolbar\ToolbarHelper::custom($contrl.'unapproved', 'cancel', '', 'FLEXI_REVIEW_UNAPPROVE', true);
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
			\Joomla\CMS\Toolbar\ToolbarHelper::publishList($contrl.'publish', 'JTOOLBAR_PUBLISH', true);
			\Joomla\CMS\Toolbar\ToolbarHelper::unpublishList($contrl.'unpublish', 'JTOOLBAR_UNPUBLISH', true);
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
				$this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? '_DDI_class_ ' . 'btn-warning' : '') . ' ' . $this->tooltip_class, 'icon-remove',
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
					$this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? '_DDI_class_ ' . 'btn-warning' : '') . ' ' . $this->tooltip_class, 'icon-remove',
					'data-placement="right" title="' . flexicontent_html::encodeHTML(\Joomla\CMS\Language\Text::_('FLEXI_ABOUT_DELETING_ASSIGNMENTS'), 2) . '"', $auto_add = 0, $tag_type='button'
				);

				$msg_alert   = \Joomla\CMS\Language\Text::sprintf('FLEXI_SELECT_LIST_ITEMS_TO', \Joomla\CMS\Language\Text::_('FLEXI_ASSIGNMENTS_N_RECORDS'));
				$msg_confirm = \Joomla\CMS\Language\Text::_('FLEXI_ARE_YOU_SURE');
				$btn_task    = $contrl.'remove_cascade';
				$extra_js    = "";
				$btn_arr[] = flexicontent_html::addToolBarButton(
					'FLEXI_ASSIGNMENTS_N_RECORDS', 'delete', '', $msg_alert, $msg_confirm,
					$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true,
					$this->btn_sm_class . ' btn-fcaction ' . (FLEXI_J40GE ? '_DDI_class_ ' . 'btn-warning' : '') . ' ' . $this->tooltip_class, 'icon-remove',
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

		if ($perms->CanConfig)
		{
			$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
			$_width  = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
			$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
			$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
			\Joomla\CMS\Toolbar\ToolbarHelper::preferences('com_flexicontent', $_height, $_width, 'Configuration');
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
