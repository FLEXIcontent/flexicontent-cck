<?php
/**
 * @version 1.5 stable $Id: view.html.php 1902 2014-05-10 16:06:11Z ggppdk $ 
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

defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.application.component.view');

/**
 * View class for the FLEXIcontent users screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewUsers extends JViewLegacy
{
	function display( $tpl = null )
	{
		//initialise variables
		$app      = JFactory::getApplication();
		$cparams  = JComponentHelper::getParams( 'com_flexicontent' );
		$user     = JFactory::getUser();
		$db       = JFactory::getDBO();
		$document = JFactory::getDocument();
		$option   = JRequest::getCmd('option');
		$view     = JRequest::getVar('view');
		
		$print_logging_info = $cparams->get('print_logging_info');
		if ( $print_logging_info )  global $fc_run_times;
		
		flexicontent_html::loadFramework('select2');
		JHTML::_('behavior.tooltip');

		// Get filters
		$count_filters = 0;
		
		//get vars
		$filter_order      = $app->getUserStateFromRequest( $option.$view.'.filter_order',		'filter_order',		'a.name',	'cmd' );
		$filter_order_Dir  = $app->getUserStateFromRequest( $option.$view.'.filter_order_Dir',	'filter_order_Dir',	'',			'word' );
		
		$filter_itemscount = $app->getUserStateFromRequest( $option.$view.'.filter_itemscount',		'filter_itemscount', 		'',			'int' );
		$filter_usergrp = $app->getUserStateFromRequest( $option.$view.'.filter_usergrp',		'filter_usergrp', 		'',			'string' );
		$filter_logged  = $app->getUserStateFromRequest( $option.$view.'.filter_logged',		'filter_logged', 	'',			'int' );
		
		if ($filter_itemscount) $count_filters++;  if (strlen($filter_usergrp)) $count_filters++;    if ($filter_logged) $count_filters++;
		
		$date       = $app->getUserStateFromRequest( $option.$view.'.date', 				'date', 			1, 				'int' );
		$startdate  = $app->getUserStateFromRequest( $option.$view.'.startdate', 		'startdate', 		'', 			'cmd' );
		$enddate    = $app->getUserStateFromRequest( $option.$view.'.enddate', 			'enddate', 			'', 			'cmd' );
		if ($startdate == JText::_('FLEXI_FROM')) { $startdate	= $app->setUserState( $option.$view.'.startdate', '' ); }
		if ($enddate   == JText::_('FLEXI_TO'))   { $enddate	= $app->setUserState( $option.$view.'.enddate', '' ); }
		
		$filter_id  = $app->getUserStateFromRequest( $option.$view.'.filter_id', 		'filter_id', 		'', 			'int' );
		$filter_id  = $filter_id ? $filter_id : '';
		$search = $app->getUserStateFromRequest( $option.'.'.$view.'.search', 			'search', 			'', 'string' );
		$search = FLEXI_J16GE ? $db->escape( trim(JString::strtolower( $search ) ) ) : $db->getEscaped( trim(JString::strtolower( $search ) ) );
		$search = JString::strtolower($search);
		
		if (strlen($startdate)) $count_filters++;  if (strlen($enddate)) $count_filters++;  if (strlen($filter_id)) $count_filters++;
		
		if ( $cparams->get('show_usability_messages', 1) )     // Important usability messages
		{
			$notice_author_with_items_only	= $app->getUserStateFromRequest( $option.'.users.notice_author_with_items_only',	'notice_author_with_items_only',	0, 'int' );
			if (!$notice_author_with_items_only) {
				$app->setUserState( $option.'.users.notice_author_with_items_only', 1 );
				$app->enqueueMessage(JText::_('FLEXI_BY_DEFAULT_ONLY_AUTHORS_WITH_ITEMS_SHOWN'), 'notice');
				$app->enqueueMessage(JText::_('FLEXI_USABILITY_MESSAGES_TURN_OFF'), 'message');
			}
		}
		
		// Add custom css and js to document
		$document->addStyleSheet(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css');
		if      (FLEXI_J30GE) $document->addStyleSheet(JURI::base(true).'/components/com_flexicontent/assets/css/j3x.css');
		else if (FLEXI_J16GE) $document->addStyleSheet(JURI::base(true).'/components/com_flexicontent/assets/css/j25.css');
		
		$js = "jQuery(document).ready(function(){";
		if ($filter_usergrp) {
			$js .= "jQuery('.col_usergrp').each(function(){ jQuery(this).addClass('yellow'); });";
		}
		if ($filter_logged) {
			$js .= "jQuery('.col_logged').each(function(){ jQuery(this).addClass('yellow'); });";
		}
		if ($filter_id) {
			$js .= "jQuery('.col_id').each(function(){ jQuery(this).addClass('yellow'); });";
		}
		if ($startdate || $enddate) {
			if ($date == 1) {
				$js .= "jQuery('.col_registered').each(function(){ jQuery(this).addClass('yellow'); });";
			} else if ($date == 2) {
				$js .= "jQuery('.col_visited').each(function(){ jQuery(this).addClass('yellow'); });";
			}
		}
		if ($filter_itemscount) {
			$js .= "jQuery('.col_itemscount').each(function(){ jQuery(this).addClass('yellow'); });";
		}
		if ($search) {
			$js .= "jQuery('.col_title').each(function(){ jQuery(this).addClass('yellow'); });";
		}
		$js .= "});";
		$document->addScriptDeclaration($js);

		// Get User's Global Permissions
		$perms = FlexicontentHelperPerm::getPerm();
		
		// Create Submenu (and also check access to current view)
		FLEXISubmenu('CanAuthors');
		
		
		// ******************
		// Create the toolbar
		// ******************
		
		// Create document/toolbar titles
		$doc_title = JText::_( 'FLEXI_AUTHORS' );
		$site_title = $document->getTitle();
		JToolBarHelper::title( $doc_title, 'authors' );
		$document->setTitle($doc_title .' - '. $site_title);
		
		$contrl = "users.";
		JToolBarHelper::custom( 'logout', 'cancel.png', 'cancel_f2.png', 'Logout' );
		JToolBarHelper::addNew($contrl.'add');
		JToolBarHelper::editList($contrl.'edit');
		
		//JToolBarHelper::deleteList(JText::_('FLEXI_ARE_YOU_SURE'), $contrl.'remove');
		// This will work in J2.5+ too and is offers more options (above a little bogus in J1.5, e.g. bad HTML id tag)
		$msg_alert   = JText::sprintf( 'FLEXI_SELECT_LIST_ITEMS_TO', JText::_('FLEXI_DELETE') );
		$msg_confirm = JText::_('FLEXI_ITEMS_DELETE_CONFIRM');
		$btn_task    = $contrl.'remove';
		$extra_js    = "";
		flexicontent_html::addToolBarButton(
			'FLEXI_DELETE', 'delete', '', $msg_alert, $msg_confirm,
			$btn_task, $extra_js, $btn_list=true, $btn_menu=true, $btn_confirm=true);
		
		JToolBarHelper::divider(); JToolBarHelper::spacer();
		JToolBarHelper::help('JHELP_USERS_USER_MANAGER');
		if ($perms->CanConfig) {
			JToolBarHelper::divider(); JToolBarHelper::spacer();
			$session = JFactory::getSession();
			$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
			$_width  = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
			$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
			$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
			JToolBarHelper::preferences('com_flexicontent', $_height, $_width, 'Configuration');
		}

		$limit		= $app->getUserStateFromRequest( 'global.list.limit', 'limit', $app->getCfg('list_limit'), 'int' );
		$limitstart = $app->getUserStateFromRequest( $option.'.limitstart', 'limitstart', 0, 'int' );

		$where = array(); $having = array(); $extra_joins = array();
		if (isset( $search ) && $search!= '')
		{
			$searchEscaped = FLEXI_J16GE ? $db->escape( $search, true ) : $db->getEscaped( $search, true );
			$searchEscaped = $db->Quote( '%'.$searchEscaped.'%', false );
			$where[] = 'a.username LIKE '.$searchEscaped.' OR a.email LIKE '.$searchEscaped.' OR a.name LIKE '.$searchEscaped;
		}

		// visited date filtering
		if ($date == 1) {
			if ($startdate && !$enddate) {  // from only
				$where[] = ' a.registerDate >= ' . $db->Quote($startdate);
			}
			if (!$startdate && $enddate) { // to only
				$where[] = ' a.registerDate <= ' . $db->Quote($enddate);
			}
			if ($startdate && $enddate) { // date range
				$where[] = '( a.registerDate >= ' . $db->Quote($startdate) . ' AND a.registerDate <= ' . $db->Quote($enddate) . ' )';
			}
		}
		if ($date == 2) {
			if ($startdate && !$enddate) {  // from only
				$where[] = ' a.lastvisitDate >= ' . $db->Quote($startdate);
			}
			if (!$startdate && $enddate) { // to only
				$where[] = ' a.lastvisitDate <= ' . $db->Quote($enddate);
			}
			if ($startdate && $enddate) { // date range
				$where[] = '( a.lastvisitDate >= ' . $db->Quote($startdate) . ' AND a.lastvisitDate <= ' . $db->Quote($enddate) . ' )';
			}
		}
		
		
		if ($filter_id)
		{
			$where[] = 'a.id = '.$filter_id;
		}
		
		if ( $filter_usergrp )
		{
			// Added as right join, see query bellow
			$extra_joins[] = ' RIGHT JOIN #__user_usergroup_map AS ug ON ug.user_id = a.id AND ug.group_id='.$filter_usergrp;
		}
		if ( $filter_logged == 1 )
		{
			$where[] = 's.userid IS NOT NULL';
		}
		else if ($filter_logged == 2)
		{
			$where[] = 's.userid IS NULL';
		}

		if ( $filter_itemscount==2 )
		{
			$having[] = ' itemscount > 0 ';
		} else if ( $filter_itemscount==1 )
		{
			$having[] = ' itemscount = 0 ';
		}
		
		// ensure filter_order has a valid value.
		$allowed_order_cols = array('a.name', 'itemscount', 'a.username', 'loggedin',
			'a.block', 'groupname','a.email', 'a.lastvisitDate', 'a.lastvisitDate', 'a.id');
		if (!in_array($filter_order, $allowed_order_cols)) {
			$filter_order = 'a.name';
		}
		
		if (!in_array(strtoupper($filter_order_Dir), array('ASC', 'DESC'))) {
			$filter_order_Dir = '';
		}
		
		$orderby = ' ORDER BY '. $filter_order .' '. $filter_order_Dir;
		$where = ( count( $where ) ? ' WHERE (' . implode( ') AND (', $where ) . ')' : '' );
		$having = ( count( $having ) ? ' HAVING (' . implode( ') AND (', $having ) . ')' : '' );
		$extra_joins = ( count( $extra_joins ) ? implode( ' ', $extra_joins ) : '' );
		
		// Do main query to get the authors
		$query = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT a.*, s.userid IS NOT NULL AS loggedin'
			. ', (SELECT COUNT(*) FROM #__content AS i WHERE i.created_by = a.id) AS itemscount '
			. ' FROM #__users AS a'
			. ' LEFT JOIN #__flexicontent_authors_ext AS ue ON a.id = ue.user_id'
			. ' LEFT JOIN #__session AS s ON s.userid = a.id'
			. $extra_joins
			. $where
			//. ' GROUP BY a.id'
			. $having
			. $orderby
		;
		$db->setQuery( $query, $limitstart, $limit );
		if ( $print_logging_info )  $start_microtime = microtime(true);
		$rows = $db->loadObjectList();
		if ( $print_logging_info ) @$fc_run_times['execute_main_query'] += round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		if ($db->getErrorMsg())	echo $db->getErrorMsg();
		
		// Get total and create pagination controls
		$db->setQuery("SELECT FOUND_ROWS()");
		$total = $db->loadResult();
		if (!$total) echo $db->getErrorMsg();
		
		// Create pagination
		jimport('joomla.html.pagination');
		$pagination = new JPagination( $total, $limitstart, $limit );
		
		// DB Query to get -mulitple- user group ids for all authors,
		// this is needed because user-To-usergoup mapping are stored in separate table
		$user_ids = array();
		foreach ($rows as $row) {
			$row->usergroups = array();
			if ($row->id) $user_ids[] = $row->id;
		}
		$query = 'SELECT user_id, group_id FROM #__user_usergroup_map ' . (count($user_ids) ? 'WHERE user_id IN ('.implode(',',$user_ids).')'  :  '');
		$db->setQuery( $query );
		$ugdata_arr = $db->loadObjectList();
		foreach ($ugdata_arr as $ugdata) $usergroups[$ugdata->user_id][] = $ugdata->group_id;
		foreach ($rows as $row) if ($row->id) $row->usergroups = $usergroups[$row->id];
		
		// get list of Groups for dropdown filter
		$query = 'SELECT *, id AS value, title AS text FROM #__usergroups';
		$db->setQuery( $query );
		$usergroups = $db->loadObjectList('id');
		$types[]		= JHTML::_('select.option',  '', '-' /*JText::_( 'Select Group' )*/ );
		foreach( $usergroups as $ugrp )
		{
			$types[]	= JHTML::_('select.option',  $ugrp->value, JText::_( $ugrp->text ) );
		}
		
		$itemscount_options[] = JHTML::_('select.option',  '', '-');
		$itemscount_options[] = JHTML::_('select.option',  1, JText::_( 'None' ) );
		$itemscount_options[] = JHTML::_('select.option',  2, JText::_( 'One or more' ) );
		$lists['filter_itemscount'] = ($filter_itemscount || 1 ? '<label class="label"># '.JText::_('FLEXI_ITEMS').'</label>' : '').
			JHTML::_('select.genericlist', $itemscount_options, 'filter_itemscount', 'class="use_select2_lib" size="1" onchange="document.adminForm.submit( );"', 'value', 'text', $filter_itemscount );
		
		$lists['filter_usergrp'] = ($filter_usergrp || 1 ? '<label class="label">'.JText::_('Select Group').'</label>' : '').
			JHTML::_('select.genericlist', $types, 'filter_usergrp', 'class="use_select2_lib" style="width:auto;" size="1" onchange="document.adminForm.submit( );"', 'value', 'text', $filter_usergrp );

		// get list of Log Status for dropdown filter
		$logged[] = JHTML::_('select.option',  '', '-' /*JText::_( 'Select Log Status' )*/);
		$logged[] = JHTML::_('select.option',  1, JText::_( 'Logged In' ) );
		$logged[] = JHTML::_('select.option',  2, JText::_( 'Logged Out' ) );
		$lists['filter_logged'] = ($filter_logged || 1 ? '<label class="label">'.JText::_('Log Status').'</label>' : '').
			JHTML::_('select.genericlist', $logged, 'filter_logged', 'class="use_select2_lib" size="1" onchange="document.adminForm.submit( );"', 'value', 'text', $filter_logged );

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order'] = $filter_order;

		// build dates option list
		$dates = array();
		$dates[] = JHTML::_('select.option',  '1', JText::_( 'Registered' ) );
		$dates[] = JHTML::_('select.option',  '2', JText::_( 'Last Visit' ) );
		
		$lists['date'] = '<label class="label">'.JText::_('FLEXI_DATE').'</label>'.
			JHTML::_('select.genericlist', $dates, 'date', 'size="1" class="use_select2_lib fc_skip_highlight"', 'value', 'text', $date, 'date' );
		
		$lists['startdate'] =
			JHTML::_('calendar', $startdate, 'startdate', 'startdate', '%Y-%m-%d', array('class'=>'inputbox', 'size'=>'11',  'maxlength'=>'20'));
		$lists['enddate'] 	=
			JHTML::_('calendar', $enddate, 'enddate', 'enddate', '%Y-%m-%d', array('class'=>'inputbox', 'size'=>'11',  'maxlength'=>'20'));
		
		// search filter
		$lists['search']= $search;
		// search id
		$lists['filter_id'] = $filter_id;

		$this->assignRef('count_filters', $count_filters);
		$this->assignRef('lists'	, $lists);
		$this->assignRef('rows'		, $rows);
		$this->assignRef('usergroups',	$usergroups);
		$this->assignRef('pagination'	, $pagination);

		// filters
		$this->assignRef('filter_id'		, $filter_id);
		$this->assignRef('filter_itemscount'		, $filter_itemscount);
		$this->assignRef('filter_usergrp'		, $filter_usergrp);
		$this->assignRef('filter_logged'	, $filter_logged);
		$this->assignRef('search'			, $search);
		$this->assignRef('filter_id'			, $filter_id);
		$this->assignRef('date'				, $date);
		$this->assignRef('startdate'		, $startdate);
		$this->assignRef('enddate'			, $enddate);
		
		$this->assignRef('option', $option);
		$this->assignRef('view', $view);
		
		$this->sidebar = FLEXI_J30GE ? JHtmlSidebar::render() : null;
		parent::display($tpl);
	}
}
