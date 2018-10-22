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
 * HTML View class for the FLEXIcontent users screen
 */
class FlexicontentViewUsers extends FlexicontentViewBaseRecords
{
	var $proxy_option   = 'com_users';
	var $title_propname = 'username';
	var $state_propname = 'block';
	var $db_tbl         = 'users';

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
		if ($filter_usergrp)    $count_filters++;
		if (strlen($filter_logged))     $count_filters++;
		if (strlen($filter_state))      $count_filters++;
		if (strlen($filter_active))     $count_filters++;

		$date       = $model->getState('date');
		$startdate  = $model->getState('startdate');
		$enddate    = $model->getState('enddate');

		$startdate = $db->escape( StringHelper::trim(StringHelper::strtolower( $startdate ) ) );
		$enddate   = $db->escape( StringHelper::trim(StringHelper::strtolower( $enddate ) ) );
		if ($startdate) $count_filters++;
		if ($enddate)   $count_filters++;

		// Record ID filter
		$filter_id = $model->getState('filter_id');
		if (strlen($filter_id)) $count_filters++;


		// Text search
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

		$js = '';
		if ($search)            $js .= "jQuery('.col_title').addClass('filtered_column');";
		if ($filter_itemscount) $js .= "jQuery('.col_itemscount').addClass('filtered_column');";
		if ($filter_usergrp)    $js .= "jQuery('.col_usergrp').addClass('filtered_column');";
		if ($filter_logged)     $js .= "jQuery('.col_logged').addClass('filtered_column');";
		if (strlen($filter_state))    $js .= "jQuery('.col_status').addClass('filtered_column');";
		if (strlen($filter_active))   $js .= "jQuery('.col_active').addClass('filtered_column');";
		if ($filter_id)         $js .= "jQuery('.col_id').addClass('filtered_column');";
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
		$doc_title = JText::_('FLEXI_AUTHORS');
		$site_title = $document->getTitle();
		JToolbarHelper::title( $doc_title, 'authors' );
		$document->setTitle($doc_title .' - '. $site_title);

		// Create the toolbar
		$this->setToolbar();


		/**
		 * Get data from the model
		 */

		if ( $print_logging_info )  $start_microtime = microtime(true);

		$rows = $this->get('Items');

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

		$conf_link = '<a href="index.php?option=com_config&amp;view=component&amp;component=com_flexicontent&amp;path=" class="' . $this->btn_sm_class . ' btn-info">'.JText::_("FLEXI_CONFIG").'</a>';

		/*if ( $cparams->get('show_usability_messages', 1) )
		{
			$notice_author_with_items_only	= $app->getUserStateFromRequest( $option.'.users.notice_author_with_items_only',	'notice_author_with_items_only',	0, 'int' );

			if (!$notice_author_with_items_only)
			{
				$app->setUserState( $option.'.users.notice_author_with_items_only', 1 );
				JFactory::getDocument()->addStyleDeclaration("#system-message-container .alert.alert-info > .alert-heading { display:none; }");

				$disable_use_notices = '<span class="fc-nowrap-box fc-disable-notices-box">'. JText::_('FLEXI_USABILITY_MESSAGES_TURN_OFF_IN').' '.$conf_link.'</span><div class="fcclear"></div>';
				$app->enqueueMessage(JText::_('FLEXI_BY_DEFAULT_ONLY_AUTHORS_WITH_ITEMS_SHOWN') .' '. $disable_use_notices, 'notice');
			}
		}*/

		$this->minihelp = '
			<div id="fc-mini-help" class="fc-mssg fc-info" style="display:none; min-width: 600px;">
				'.JText::_('FLEXI_BY_DEFAULT_ONLY_AUTHORS_WITH_ITEMS_SHOWN') .'
			</div>
		';


		/**
		 * Create List Filters
		 */

		$lists = array();

		// Get list of Groups for dropdown filter
		$query = 'SELECT *, id AS value, title AS text FROM #__usergroups';
		$usergroups = $db->setQuery($query)->loadObjectList('id');


		$types[]		= JHtml::_('select.option',  '', '-' /*JText::_( 'Select Group' )*/ );
		foreach( $usergroups as $ugrp )
		{
			$types[]	= JHtml::_('select.option',  $ugrp->value, JText::_( $ugrp->text ) );
		}

		$itemscount_options[] = JHtml::_('select.option',  '', '-');
		$itemscount_options[] = JHtml::_('select.option',  1, JText::_( 'None' ) );
		$itemscount_options[] = JHtml::_('select.option',  2, JText::_( 'One or more' ) );
		$lists['filter_itemscount'] = ($filter_itemscount || 1 ? '<div class="add-on"># '.JText::_('FLEXI_ITEMS').'</div>' : '').
			JHtml::_('select.genericlist', $itemscount_options, 'filter_itemscount', 'class="use_select2_lib" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_itemscount );

		$lists['filter_usergrp'] = ($filter_usergrp || 1 ? '<div class="add-on">'.JText::_('Select Group').'</div>' : '').
			JHtml::_('select.genericlist', $types, 'filter_usergrp', 'class="use_select2_lib" style="width:auto;" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_usergrp );

		// get list of Log Status for dropdown filter
		$logged[] = JHtml::_('select.option',  '', '-' /*JText::_( 'Select Log Status' )*/);
		$logged[] = JHtml::_('select.option',  '1', JText::_( 'JYES' ) );
		$logged[] = JHtml::_('select.option',  '0', JText::_( 'JNO' ) );
		$lists['filter_logged'] = ($filter_logged || 1 ? '<div class="add-on">'.JText::_('FLEXI_USER_LOGGED').'</div>' : '').
			JHtml::_('select.genericlist', $logged, 'filter_logged', 'class="use_select2_lib" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_logged );

		// get list of Log Status for dropdown filter
		$state[] = JHtml::_('select.option',  '', '-' /*JText::_( 'COM_USERS_FILTER_STATE' )*/);
		$state[] = JHtml::_('select.option', '0', JText::_('JENABLED'));
		$state[] = JHtml::_('select.option', '1', JText::_('JDISABLED'));
		$lists['filter_state'] = ($filter_state || 1 ? '<div class="add-on">'.JText::_('COM_USERS_HEADING_ENABLED').'</div>' : '').
			JHtml::_('select.genericlist', $state, 'filter_state', 'class="use_select2_lib" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_state );

		// get list of Log Status for dropdown filter
		$active[] = JHtml::_('select.option',  '', '-' /*JText::_( 'COM_USERS_FILTER_ACTIVE' )*/);
		$active[] = JHtml::_('select.option', '0', JText::_('COM_USERS_ACTIVATED'));
		$active[] = JHtml::_('select.option', '1', JText::_('COM_USERS_UNACTIVATED'));
		$lists['filter_active'] = ($filter_active || 1 ? '<div class="add-on">'.JText::_('COM_USERS_HEADING_ACTIVATED').'</div>' : '').
			JHtml::_('select.genericlist', $active, 'filter_active', 'class="use_select2_lib" size="1" onchange="document.adminForm.limitstart.value=0; Joomla.submitform()"', 'value', 'text', $filter_active );


		// Text search filter value
		$lists['search'] = $search;

		// Search id
		$lists['filter_id'] = $filter_id;

		// Table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order']     = $filter_order;

		// build dates option list
		$dates = array();
		$dates[] = JHtml::_('select.option',  '1', JText::_( 'Registered' ) );
		$dates[] = JHtml::_('select.option',  '2', JText::_( 'Last Visit' ) );

		$lists['date'] = //'<div class="add-on">'.JText::_('FLEXI_DATE').'</div>'.
			JHtml::_('select.genericlist', $dates, 'date', 'size="1" class="use_select2_lib"', 'value', 'text', $date, 'date' );

		$lists['startdate'] = JHtml::_('calendar', $startdate, 'startdate', 'startdate', '%Y-%m-%d', array('class'=>'', 'size'=>'8',  'maxlength'=>'19', 'style'=>'width:auto', 'placeholder'=>JText::_('FLEXI_FROM')));
		$lists['enddate'] 	= JHtml::_('calendar', $enddate, 'enddate', 'enddate', '%Y-%m-%d', array('class'=>'', 'size'=>'8',  'maxlength'=>'19', 'style'=>'width:auto', 'placeholder'=>JText::_('FLEXI_TO')));


		/**
		 * Assign data to template
		 */

		$this->count_filters = $count_filters;

		$this->lists       = $lists;
		$this->rows        = $rows;
		$this->pagination  = $pagination;
		$this->usergroups  = $usergroups;

		// filters
		$this->search = $search;
		$this->date = $date;
		$this->startdate = $startdate;
		$this->enddate = $enddate;

		$this->perms  = FlexicontentHelperPerm::getPerm();
		$this->option = $option;
		$this->view   = $view;
		$this->state  = $this->get('State');

		$this->sidebar = FLEXI_J30GE ? JHtmlSidebar::render() : null;


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
		$canDo    = UsersHelper::getActions();

		$js = '';

		$contrl = "users.";

		$loading_msg = flexicontent_html::encodeHTML(JText::_('FLEXI_LOADING') .' ... '. JText::_('FLEXI_PLEASE_WAIT'), 2);

		JToolbarHelper::custom( 'logout', 'cancel.png', 'cancel_f2.png', 'Logout' );

		//JToolbarHelper::addNew($contrl.'add');
		JText::script("FLEXI_UPDATING_CONTENTS", true);

		$js .= '
			function fc_edit_juser_modal_load( container )
			{
				if ( container.find("iframe").get(0).contentWindow.location.href.indexOf("view=users") != -1 )
				{
					container.dialog("close");
				}
			}
			function fc_edit_juser_modal_close()
			{
				window.location.reload(false);
				document.body.innerHTML = Joomla.JText._("FLEXI_UPDATING_CONTENTS") + \' <img id="page_loading_img" src="components/com_flexicontent/assets/images/ajax-loader.gif">\';
			}
		';

		$modal_title = JText::_('Add new Joomla user', true);
		$tip_class = ' hasTooltip';
		JToolbarHelper::divider();
		flexicontent_html::addToolBarButton(
			'FLEXI_NEW', $btn_name='add_juser',
			$full_js="var url = jQuery(this).attr('data-href'); var the_dialog = fc_showDialog(url, 'fc_modal_popup_container', 0, 0, 0, fc_edit_juser_modal_close, {title:'".$modal_title."', loadFunc: fc_edit_juser_modal_load}); return false;",
			$msg_alert='', $msg_confirm='',
			$btn_task='', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false, $btn_class=$this->btn_sm_class . " btn-success " . $this->tooltip_class, $btn_icon="icon-new icon-white",
			'data-placement="bottom" data-href="index.php?option=com_users&amp;task=user.edit&amp;id=0" title="Add new Joomla user"'
		);

		JToolbarHelper::editList($contrl.'edit');

		if ($canDo->get('core.delete'))
		{
			//JToolbarHelper::deleteList(JText::_('FLEXI_ARE_YOU_SURE'), $contrl.'remove');
			$msg_alert   = JText::sprintf('FLEXI_SELECT_LIST_ITEMS_TO', JText::_('FLEXI_DELETE'));
			$msg_confirm = JText::_('FLEXI_ITEMS_DELETE_CONFIRM');
			$btn_task    = $contrl.'remove';
			$extra_js    = "";
			flexicontent_html::addToolBarButton(
				'FLEXI_DELETE', 'delete', '', $msg_alert, $msg_confirm,
				$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true);
		}

		if ($canDo->get('core.admin'))
		{
			JToolbarHelper::preferences('com_users');
			JToolbarHelper::divider();
		}

		JToolbarHelper::help('JHELP_USERS_GROUPS');

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
