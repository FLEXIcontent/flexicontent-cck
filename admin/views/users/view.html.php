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
 * HTML View class for the FLEXIcontent users screen
 */
class FlexicontentViewUsers extends FlexicontentViewBaseRecords
{
	var $proxy_option   = 'com_users';
	var $title_propname = 'username';
	var $state_propname = 'block';
	var $db_tbl         = 'users';
	var $name_singular  = 'user';

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
		$filter_itemscount= $model->getState('filter_itemscount');
		$filter_usergrp   = $model->getState('filter_usergrp');
		$filter_logged    = $model->getState('filter_logged');
		$filter_state     = $model->getState('filter_state');
		$filter_active    = $model->getState('filter_active');

		if ($filter_itemscount) $count_filters++;
		if ($filter_usergrp) $count_filters++;
		if (strlen($filter_logged)) $count_filters++;
		if (strlen($filter_state)) $count_filters++;
		if (strlen($filter_active)) $count_filters++;

		// Date filters
		$date      = $model->getState('date');
		$startdate = $model->getState('startdate');
		$enddate   = $model->getState('enddate');

		$startdate = $db->escape( StringHelper::trim(StringHelper::strtolower( $startdate ) ) );
		$enddate   = $db->escape( StringHelper::trim(StringHelper::strtolower( $enddate ) ) );
		if ($startdate) $count_filters++;
		if ($enddate)   $count_filters++;

		// Record ID filter
		$filter_id = $model->getState('filter_id');
		if (strlen($filter_id)) $count_filters++;


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

		$js =

			($search ? "jQuery('.col_title').addClass('filtered_column');" : '') .

			($filter_itemscount ? "jQuery('.col_itemscount').addClass('filtered_column');" : '') .

			($filter_usergrp ? "jQuery('.col_usergrp').addClass('filtered_column');" : '') .

			($filter_logged ? "jQuery('.col_logged').addClass('filtered_column');" : '') .

			(strlen($filter_state) ? "jQuery('.col_status').addClass('filtered_column');" : '') .

			(strlen($filter_active) ? "jQuery('.col_active').addClass('filtered_column');" : '') .

			($filter_id ? "jQuery('.col_id').addClass('filtered_column');" : '');

		if ($startdate || $enddate)
		{
			if ($date == 1) {
				$js .= "jQuery('.col_registered').addClass('filtered_column');";
			} else if ($date == 2) {
				$js .= "jQuery('.col_visited').addClass('filtered_column');";
			}
		}

		if ($js)
		{
			$document->addScriptDeclaration('
				jQuery(document).ready(function(){
					' . $js . '
				});
			');
		}


		/**
		 * Create Submenu & Toolbar
		 */

		// Create Submenu (and also check access to current view)
		FLEXIUtilities::ManagerSideMenu('CanAuthors');

		// Create document/toolbar titles
		$doc_title = \Joomla\CMS\Language\Text::_('FLEXI_AUTHORS');
		$site_title = $document->getTitle();
		\Joomla\CMS\Toolbar\ToolbarHelper::title( $doc_title, 'users' );
		$document->setTitle($doc_title .' - '. $site_title);

		// Create the toolbar
		$this->setToolbar();


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
		 * DB Query to get -mulitple- user group ids for all authors,
		 * Get user-To-usergoup mapping for users in current page
		 */

		if (1)
		{
			$user_ids = array();

			foreach ($rows as $row)
			{
				$row->usergroups = array();

				if ($row->id)
				{
					$user_ids[] = $row->id;
				}
			}

			$query = 'SELECT user_id, group_id FROM #__user_usergroup_map ' . (count($user_ids) ? 'WHERE user_id IN ('.implode(',',$user_ids).')'  :  '');
			$ugdata_arr = $db->setQuery($query)->loadObjectList();

			foreach ($ugdata_arr as $ugdata)
			{
				$usergroups[$ugdata->user_id][] = $ugdata->group_id;
			}

			foreach ($rows as $row)
			{
				if ($row->id)
				{
					$row->usergroups = $usergroups[$row->id];
				}
			}
		}


		/**
		 * Add usage information notices if these are enabled
		 */

		$conf_link = '<a href="index.php?option=com_config&amp;view=component&amp;component=com_flexicontent&amp;path=" class="' . $this->btn_sm_class . ' btn-info">'.\Joomla\CMS\Language\Text::_("FLEXI_CONFIG").'</a>';

		if ($cparams->get('show_usability_messages', 1))
		{
			/*$notice_author_with_items_only	= $app->getUserStateFromRequest( $option.'.users.notice_author_with_items_only',	'notice_author_with_items_only',	0, 'int' );

			if (!$notice_author_with_items_only)
			{
				$app->setUserState( $option.'.users.notice_author_with_items_only', 1 );
				\Joomla\CMS\Factory::getApplication()->getDocument()->addStyleDeclaration("#system-message-container .alert.alert-info > .alert-heading { display:none; }");

				$disable_use_notices = '<span class="fc-nowrap-box fc-disable-notices-box">'. \Joomla\CMS\Language\Text::_('FLEXI_USABILITY_MESSAGES_TURN_OFF_IN').' '.$conf_link.'</span><div class="fcclear"></div>';
				$app->enqueueMessage(\Joomla\CMS\Language\Text::_('FLEXI_BY_DEFAULT_ONLY_AUTHORS_WITH_ITEMS_SHOWN') .' '. $disable_use_notices, 'notice');
			}*/
		}

		$this->minihelp = '
			<div id="fc-mini-help" class="fc-mssg fc-info" style="display:none; min-width: 600px;">
				'.\Joomla\CMS\Language\Text::_('FLEXI_BY_DEFAULT_ONLY_AUTHORS_WITH_ITEMS_SHOWN') .'
			</div>
		';


		/**
		 * Create List Filters
		 */

		$lists = array();


		// Build number of owned items filter
		$fieldname = 'filter_itemscount';
		$elementid = 'filter_itemscount';
		$value     = $filter_itemscount;

		$options = array(
			\Joomla\CMS\HTML\HTMLHelper::_('select.option',  '', '-' /*'# Owned items'*/),
			\Joomla\CMS\HTML\HTMLHelper::_('select.option',  1, 'FLEXI_NONE'),
			\Joomla\CMS\HTML\HTMLHelper::_('select.option',  2, 'FLEXI_ONE_OR_MORE'),
		);

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => '# ' . \Joomla\CMS\Language\Text::_('FLEXI_ITEMS'),
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


		// Build logged users filter
		$fieldname = 'filter_usergrp';
		$elementid = 'filter_usergrp';
		$value     = $filter_usergrp;

		$options = array(
			\Joomla\CMS\HTML\HTMLHelper::_('select.option',  '', '-' /*'Select Group'*/),
		);

		$usergroups = $db->setQuery('SELECT * FROM #__usergroups')->loadObjectList('id');

		foreach($usergroups as $ugrp)
		{
			$options[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option', $ugrp->id, $ugrp->title);
		}

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => \Joomla\CMS\Language\Text::_('FLEXI_USERGROUPS'),
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


		// Build logged users filter
		$fieldname = 'filter_logged';
		$elementid = 'filter_logged';
		$value     = $filter_logged;

		$options = array(
			\Joomla\CMS\HTML\HTMLHelper::_('select.option',  '', '-' /*'Select Log Status'*/),
			\Joomla\CMS\HTML\HTMLHelper::_('select.option',  '1', 'JYES'),
			\Joomla\CMS\HTML\HTMLHelper::_('select.option',  '0', 'JNO'),
		);

		$lists[$elementid] =$this->getFilterDisplay(array(
			'label' => \Joomla\CMS\Language\Text::_('FLEXI_USER_LOGGED'),
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

		// Build enabled users filter
		$fieldname = 'filter_state';
		$elementid = 'filter_state';
		$value     = $filter_state;

		//$options = \Joomla\CMS\HTML\HTMLHelper::_('jgrid.publishedOptions');
		$options = array(
			\Joomla\CMS\HTML\HTMLHelper::_('select.option',  '', '-' /*'COM_USERS_FILTER_STATE'*/),
			\Joomla\CMS\HTML\HTMLHelper::_('select.option', '0', 'JENABLED'),
			\Joomla\CMS\HTML\HTMLHelper::_('select.option', '1', 'JDISABLED'),
		);

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => \Joomla\CMS\Language\Text::_('COM_USERS_HEADING_ENABLED'),
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


		// Build activated users filter
		$fieldname = 'filter_active';
		$elementid = 'filter_active';
		$value     = $filter_active;

		$options = array(
			\Joomla\CMS\HTML\HTMLHelper::_('select.option',  '', '-' /*'COM_USERS_FILTER_ACTIVE'*/),
			\Joomla\CMS\HTML\HTMLHelper::_('select.option', '0', 'COM_USERS_ACTIVATED'),
			\Joomla\CMS\HTML\HTMLHelper::_('select.option', '1', 'COM_USERS_UNACTIVATED'),
		);

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => \Joomla\CMS\Language\Text::_('COM_USERS_HEADING_ACTIVATED'),
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


		// Build id list filter
		$fieldname = 'filter_id';
		$elementid = 'filter_id';
		$value     = $filter_id;

		$lists[$elementid] = $this->getFilterDisplay(array(
			'label' => \Joomla\CMS\Language\Text::_('FLEXI_ID'),
			'html' => '<input type="text" name="' . $fieldname . '" id="' . $elementid . '" size="6" value="' . $filter_id . '" class="inputbox" style="width:auto;" />',
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
		 * Create note about dates displayed using current user's timezone
		 */

		$site_zone = $app->getCfg('offset');
		$user_zone = \Joomla\CMS\Factory::getApplication()->getIdentity()->getParam('timezone', $site_zone);

		$tz = new DateTimeZone( $user_zone );
		$tz_offset = $tz->getOffset(new \Joomla\CMS\Date\Date()) / 3600;
		$tz_info = $tz_offset
			? ' UTC +' . $tz_offset . ' (' . $user_zone . ')'
			: ' UTC ' . $tz_offset . ' (' . $user_zone . ')';

		$date_note_msg = \Joomla\CMS\Language\Text::sprintf(FLEXI_J16GE ? 'FLEXI_DATES_IN_USER_TIMEZONE_NOTE' : 'FLEXI_DATES_IN_SITE_TIMEZONE_NOTE', ' ', $tz_info);


		// Build date filter scope
		$fieldname = 'date';
		$elementid = 'date';
		$value     = $date;

		$options = array(
			\Joomla\CMS\HTML\HTMLHelper::_('select.option',  '1', 'FLEXI_REGISTERED'),
			\Joomla\CMS\HTML\HTMLHelper::_('select.option',  '2', 'FLEXI_USER_LAST_VISIT'),
		);

		$lists['filter_date'] = $this->getFilterDisplay(array(
			'label' => null, //\Joomla\CMS\Language\Text::_('FLEXI_DATE'),
			'html' => \Joomla\CMS\HTML\HTMLHelper::_('select.genericlist',
				$options,
				$fieldname,
				array(
					'size' => '1',
					'style' => 'margin: 0',
					'class' => $this->select_class . ' ' . $this->tooltip_class,
					'data-placement' => 'bottom',
					'title' => flexicontent_html::getToolTip(null, $date_note_msg, 0, 1),
				),
				'value',
				'text',
				$value,
				$elementid,
				$translate = true
			)
			. \Joomla\CMS\HTML\HTMLHelper::_('calendar', $startdate, 'startdate', 'startdate', '%Y-%m-%d', array('class'=>'', 'size'=>'8',  'maxlength'=>'19', 'style'=>'width:auto', 'placeholder'=>\Joomla\CMS\Language\Text::_('FLEXI_FROM')))
			. \Joomla\CMS\HTML\HTMLHelper::_('calendar', $enddate, 'enddate', 'enddate', '%Y-%m-%d', array('class'=>'', 'size'=>'8',  'maxlength'=>'19', 'style'=>'width:auto', 'placeholder'=>\Joomla\CMS\Language\Text::_('FLEXI_TO')))
		));



		/**
		 * Assign data to template
		 */

		$this->count_filters = $count_filters;

		$this->lists       = $lists;
		$this->rows        = $rows;
		$this->pagination  = $pagination;
		$this->usergroups  = $usergroups;

		// filters
		$this->date = $date;
		$this->startdate = $startdate;
		$this->enddate = $enddate;

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
		$canDo    = UsersHelper::getActions();

		$js = '';

		$contrl = $this->ctrl . '.';
		$contrl_s = $this->name_singular . '.';

		$loading_msg = flexicontent_html::encodeHTML(\Joomla\CMS\Language\Text::_('FLEXI_LOADING') .' ... '. \Joomla\CMS\Language\Text::_('FLEXI_PLEASE_WAIT'), 2);

		\Joomla\CMS\Language\Text::script("FLEXI_UPDATING_CONTENTS", true);
		$document->addScriptDeclaration('
			function fc_edit_juser_modal_load( container )
			{
				if ( container.find("iframe").get(0).contentWindow.location.href.indexOf("view=users") != -1 )
				{
					container.dialog("close");
				}
			}
			function fc_edit_juser_modal_close()
			{
				//window.location.reload(false);
				window.location.href = \'index.php?option=com_flexicontent&view=users\';
				document.body.innerHTML = "<div>" + Joomla.JText._("FLEXI_UPDATING_CONTENTS") + \' <img id="page_loading_img" src="components/com_flexicontent/assets/images/ajax-loader.gif"></div>\';
			}
		');

		if ($canDo->get('core.create'))
		{
			//\Joomla\CMS\Toolbar\ToolbarHelper::addNew($contrl.'add');

			$modal_title = \Joomla\CMS\Language\Text::_('FLEXI_NEW', true);
			\Joomla\CMS\Toolbar\ToolbarHelper::divider();
			flexicontent_html::addToolBarButton(
				'FLEXI_NEW', $btn_name='add_juser',
				$full_js="var url = jQuery(this).attr('data-href'); var the_dialog = fc_showDialog(url, 'fc_modal_popup_container', 0, 0, 0, fc_edit_juser_modal_close, {title:'".$modal_title."', loadFunc: fc_edit_juser_modal_load}); return false;",
				$msg_alert='', $msg_confirm='',
				$btn_task='', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false, $btn_class=$this->btn_sm_class . " btn-success " . $this->tooltip_class, $btn_icon="icon-new icon-white",
				'data-placement="bottom" data-href="index.php?option=com_users&amp;task=user.edit&amp;id=0" title="Add new Joomla user"'
			);
		}

		\Joomla\CMS\Toolbar\ToolbarHelper::custom('logout', 'cancel.png', 'cancel_f2.png', 'Logout');

		if (0 && $canDo->get('core.edit'))
		{
			\Joomla\CMS\Toolbar\ToolbarHelper::editList($contrl.'edit');
		}

		if ($canDo->get('core.delete'))
		{
			//\Joomla\CMS\Toolbar\ToolbarHelper::deleteList(\Joomla\CMS\Language\Text::_('FLEXI_ARE_YOU_SURE'), $contrl.'remove');
			$msg_alert   = \Joomla\CMS\Language\Text::sprintf('FLEXI_SELECT_LIST_ITEMS_TO', \Joomla\CMS\Language\Text::_('FLEXI_DELETE'));
			$msg_confirm = \Joomla\CMS\Language\Text::_('FLEXI_ITEMS_DELETE_CONFIRM');
			$btn_task    = $contrl.'remove';
			$extra_js    = "";
			flexicontent_html::addToolBarButton(
				'FLEXI_DELETE', 'delete', '', $msg_alert, $msg_confirm,
				$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true);
		}

		if ($canDo->get('core.admin'))
		{
			\Joomla\CMS\Toolbar\ToolbarHelper::preferences('com_users');
			\Joomla\CMS\Toolbar\ToolbarHelper::divider();
		}

		\Joomla\CMS\Toolbar\ToolbarHelper::help('JHELP_USERS_GROUPS');

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
