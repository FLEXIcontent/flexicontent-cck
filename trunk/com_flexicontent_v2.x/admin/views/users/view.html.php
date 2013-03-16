<?php
/**
* @version		$Id: view.html.php 1579 2012-12-03 03:37:21Z ggppdk $
* @package		Joomla
* @subpackage	Users
* @copyright	Copyright (C) 2005 - 2010 Open Source Matters. All rights reserved.
* @license		GNU/GPL, see LICENSE.php
* Joomla! is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*/

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die( 'Restricted access' );

jimport('joomla.application.component.view');

/**
 * HTML View class for the Users component
 *
 * @static
 * @package		Joomla
 * @subpackage	Users
 * @since 1.0
 */
class FlexicontentViewUsers extends JViewLegacy
{
	function display($tpl = null)
	{
		$mainframe = JFactory::getApplication();
		$cparams   = JComponentHelper::getParams( 'com_flexicontent' );
		
		$db				= JFactory::getDBO();
		$document	= JFactory::getDocument();
		$option   = JRequest::getCmd('option');
		$user     = JFactory::getUser();
		$acl      = JFactory::getACL();
		
		JHTML::_('behavior.tooltip');

		//get vars
		$filter_order		= $mainframe->getUserStateFromRequest( "$option.authors.filter_order",		'filter_order',		'a.name',	'cmd' );
		$filter_order_Dir	= $mainframe->getUserStateFromRequest( "$option.authors.filter_order_Dir",	'filter_order_Dir',	'',			'word' );
		
		$filter_itemscount		= $mainframe->getUserStateFromRequest( "$option.authors.filter_itemscount",		'filter_itemscount', 		'',			'int' );
		
		$filter_type		= $mainframe->getUserStateFromRequest( "$option.authors.filter_type",		'filter_type', 		'',			'string' );
		$filter_logged		= $mainframe->getUserStateFromRequest( "$option.authors.filter_logged",		'filter_logged', 	'',			'int' );
		$date	 			= $mainframe->getUserStateFromRequest( "$option.authors.date", 				'date', 			1, 				'int' );
		$startdate	 		= $mainframe->getUserStateFromRequest( "$option.authors.startdate", 		'startdate', 		'', 			'cmd' );
		if ($startdate == JText::_('FLEXI_FROM')) { $startdate	= $mainframe->setUserState( "$option.authors.startdate", '' ); }
		$enddate	 		= $mainframe->getUserStateFromRequest( "$option.authors.enddate", 			'enddate', 			'', 			'cmd' );
		if ($enddate == JText::_('FLEXI_TO')) { $enddate	= $mainframe->setUserState( "$option.authors.enddate", '' ); }
		$filter_id 			= $mainframe->getUserStateFromRequest( "$option.authors.filter_id", 		'filter_id', 		'', 			'int' );
		$search				= $mainframe->getUserStateFromRequest( "$option.authors.search",			'search', 			'',			'string' );
		if (strpos($search, '"') !== false) {
			$search = str_replace(array('=', '<'), '', $search);
		}
		$search = JString::strtolower($search);
		
		if ( $cparams->get('show_usability_messages', 1) )     // Important usability messages
		{
			$notice_author_with_items_only	= $mainframe->getUserStateFromRequest( $option.'.users.notice_author_with_items_only',	'notice_author_with_items_only',	0, 'int' );
			if (!$notice_author_with_items_only) {
				$mainframe->setUserState( $option.'.users.notice_author_with_items_only', 1 );
				$mainframe->enqueueMessage(JText::_('FLEXI_BY_DEFAULT_ONLY_AUTHORS_WITH_ITEMS_SHOWN'), 'notice');
				$mainframe->enqueueMessage(JText::_('FLEXI_USABILITY_MESSAGES_TURN_OFF'), 'notice');
			}
		}

		//add css and submenu to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');


		$js = "window.addEvent('domready', function(){";
		if ($filter_type) {
			$js .= "$$('.col_type').each(function(el){ el.addClass('yellow'); });";
		}
		if ($filter_logged) {
			$js .= "$$('.col_logged').each(function(el){ el.addClass('yellow'); });";
		}
		if ($filter_id) {
			$js .= "$$('.col_id').each(function(el){ el.addClass('yellow'); });";
		}
		if ($startdate || $enddate) {
			if ($date == 1) {
				$js .= "$$('.col_registered').each(function(el){ el.addClass('yellow'); });";
			} else if ($date == 2) {
				$js .= "$$('.col_visited').each(function(el){ el.addClass('yellow'); });";
			}
		}
		if ($search || $filter_itemscount) {
			$js .= "$$('.col_itemscount').each(function(el){ el.addClass('yellow'); });";
		} else {
			$js .= "$$('.col_itemscount').each(function(el){ el.removeClass('yellow'); });";
		}
		$js .= "});";
		$document->addScriptDeclaration($js);

		// Get User's Global Permissions
		$perms = FlexicontentHelperPerm::getPerm();
		
		// Create Submenu (and also check access to current view)
		FLEXISubmenu('CanAuthors');

		//create the toolbar
		$contrl = FLEXI_J16GE ? "users." : "";
		JToolBarHelper::title( JText::_( 'FLEXI_AUTHORS' ), 'authors' );
		JToolBarHelper::custom( 'logout', 'cancel.png', 'cancel_f2.png', 'Logout' );
		JToolBarHelper::deleteList('Are you sure?', $contrl.'remove');
		JToolBarHelper::addNew($contrl.'add');
		JToolBarHelper::editList($contrl.'edit');
		JToolBarHelper::divider(); JToolBarHelper::spacer();
		if (!FLEXI_J16GE)
			JToolBarHelper::help( 'screen.users' );
		else
			JToolBarHelper::help('JHELP_USERS_USER_MANAGER');
		if ($perms->CanConfig) {
			JToolBarHelper::divider(); JToolBarHelper::spacer();
			JToolBarHelper::preferences('com_flexicontent', '550', '850', 'Configuration');
		}

		$limit		= $mainframe->getUserStateFromRequest( 'global.list.limit', 'limit', $mainframe->getCfg('list_limit'), 'int' );
		$limitstart = $mainframe->getUserStateFromRequest( $option.'.limitstart', 'limitstart', 0, 'int' );

		$where = array(); $having = array(); $extra_joins = array();
		if (isset( $search ) && $search!= '')
		{
			$searchEscaped = $db->Quote( '%'.$db->getEscaped( $search, true ).'%', false );
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
		
		if ( $filter_type )
		{
			if ( !FLEXI_J16GE ) {
				if ( $filter_type == 'Public Frontend' )     $where[] = ' a.usertype = \'Registered\' OR a.usertype = \'Author\' OR a.usertype = \'Editor\' OR a.usertype = \'Publisher\' ';
				else if ( $filter_type == 'Public Backend' ) $where[] = 'a.usertype = \'Manager\' OR a.usertype = \'Administrator\' OR a.usertype = \'Super Administrator\' ';
				else                                         $where[] = 'a.usertype = LOWER( '.$db->Quote($filter_type).' ) ';
			} else {
				// Added as right join, see query bellow
				$extra_joins[] = ' RIGHT JOIN #__user_usergroup_map AS ug ON ug.user_id = a.id AND ug.group_id='.$filter_type;
			}
		}
		if ( $filter_logged == 1 )
		{
			$where[] = 's.userid = a.id';
		}
		else if ($filter_logged == 2)
		{
			$where[] = 's.userid IS NULL';
		}

		if ( !$filter_itemscount )
		{
			$having[] = ' itemscount > 0 ';
		} else if ( $filter_itemscount==1 )
		{
			$having[] = ' itemscount = 0 ';
		}

		// exclude any child group id's for this user, this applicable only in J1.5, and not for J16/J1.7/J2.5+
		if (!FLEXI_J16GE) {
			$pgids = $acl->get_group_children( $user->get('gid'), 'ARO', 'RECURSE' );

			if (is_array( $pgids ) && count( $pgids ) > 0)
			{
				JArrayHelper::toInteger($pgids);
				$where[] = 'a.gid NOT IN (' . implode( ',', $pgids ) . ')';
			}
		}
		$filter = '';
		if ($filter_logged == 1 || $filter_logged == 2)
		{
			$filter = ' INNER JOIN #__session AS s ON s.userid = a.id';
		}

		// ensure filter_order has a valid value.
		if (!in_array($filter_order, array('a.name', 'a.username', 'a.block', 'groupname', 'a.email', 'a.lastvisitDate', 'a.id'))) {
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
		$query = 'SELECT SQL_CALC_FOUND_ROWS a.*, COUNT(i.id) as itemscount'
			. (!FLEXI_J16GE ? ', g.name AS groupname' : '')
			. ' FROM #__users AS a'
			. (!FLEXI_J16GE ? ' INNER JOIN #__core_acl_aro AS aro ON aro.value = a.id' : '')
			. (!FLEXI_J16GE ? ' INNER JOIN #__core_acl_groups_aro_map AS gm ON gm.aro_id = aro.id' : '')
			. (!FLEXI_J16GE ? ' INNER JOIN #__core_acl_aro_groups AS g ON g.id = gm.group_id' : '')
			. ' LEFT JOIN #__flexicontent_authors_ext AS ue ON a.id = ue.user_id'
			. ' LEFT JOIN #__content AS i ON i.created_by = a.id '
			. $extra_joins
			. $filter
			. $where
			. ' GROUP BY a.id'
			. $having
			. $orderby
		;
		$db->setQuery( $query, $limitstart, $limit );
		$rows = $db->loadObjectList();
		if ($db->getErrorMsg())	echo $db->getErrorMsg();
		
		// Get total and create pagination controls
		$db->setQuery("SELECT FOUND_ROWS()");
		$total = $db->loadResult();
		if (!$total) echo $db->getErrorMsg();
		
		jimport('joomla.html.pagination');
		$pagination = new JPagination( $total, $limitstart, $limit );
		
		// Query string to get loggedin property for all authors
		$login_qtmpl = 'SELECT COUNT(s.userid) FROM #__session AS s WHERE s.userid = %d';
		
		// Query string (J2.5 only) to get -mulitple- user groups for all authors,
		//this is needed because user-To-Usergoup mapping are stored in separate table
		if (FLEXI_J16GE) {
			$ugrps_qtmpl = 'SELECT group_id FROM #__user_usergroup_map AS ug WHERE ug.user_id = %d';
		}
		
		$n = count( $rows );
		for ($i = 0; $i < $n; $i++)
		{
			$row = &$rows[$i];
			
			// Get Author's loggedin property
			$query = sprintf( $login_qtmpl, intval( $row->id ) );
			$db->setQuery( $query );
			$row->loggedin = $db->loadResult();
			if ($db->getErrorMsg())	echo $db->getErrorMsg();
			
			// Get Author's Usergroups (needed for J2.5)
			if (FLEXI_J16GE) {
				$query = sprintf( $ugrps_qtmpl, intval( $row->id ) );
				$db->setQuery( $query );
				$row->usergroups = FLEXI_J30GE ? $db->loadColumn() : $db->loadResultArray();
				if ($db->getErrorMsg())	echo $db->getErrorMsg();
			}
		}

		// get list of Groups for dropdown filter
		if (FLEXI_J16GE) {
			$query = 'SELECT *, id AS value, title AS text FROM #__usergroups';
		} else {
			$query = 'SELECT *, name AS value, name AS text FROM #__core_acl_aro_groups WHERE name != "ROOT" AND name != "USERS"';
		}
		$db->setQuery( $query );
		$usergroups = $db->loadObjectList('id');
		$types[]		= JHTML::_('select.option',  '', '- '. JText::_( 'Select Group' ) .' -' );
		foreach( $usergroups as $ugrp )
		{
			$types[]	= JHTML::_('select.option',  $ugrp->value, JText::_( $ugrp->text ) );
		}
		
		$itemscount_options[] = JHTML::_('select.option',  '', '- '. JText::_( 'One or more' ) .' -');
		$itemscount_options[] = JHTML::_('select.option',  1, JText::_( 'None' ) );
		$itemscount_options[] = JHTML::_('select.option',  2, JText::_( 'Any number' ) );
		$lists['filter_itemscount'] = JHTML::_('select.genericlist',   $itemscount_options, 'filter_itemscount', 'class="inputbox" size="1" onchange="document.adminForm.submit( );"', 'value', 'text', "$filter_itemscount" );
		
		$lists['filter_type'] 	= JHTML::_('select.genericlist',   $types, 'filter_type', 'class="inputbox" style="width:auto;" size="1" onchange="document.adminForm.submit( );"', 'value', 'text', "$filter_type" );

		// get list of Log Status for dropdown filter
		$logged[] = JHTML::_('select.option',  '', '- '. JText::_( 'Select Log Status' ) .' -');
		$logged[] = JHTML::_('select.option',  1, JText::_( 'Logged In' ) );
		$lists['filter_logged'] = JHTML::_('select.genericlist',   $logged, 'filter_logged', 'class="inputbox" size="1" onchange="document.adminForm.submit( );"', 'value', 'text', "$filter_logged" );

		// table ordering
		$lists['order_Dir']	= $filter_order_Dir;
		$lists['order']		= $filter_order;

		// build dates option list
		$dates = array();
		$dates[] = JHTML::_('select.option',  '1', JText::_( 'Registered' ) );
		$dates[] = JHTML::_('select.option',  '2', JText::_( 'Last Visit' ) );
		$lists['date'] = JHTML::_('select.radiolist', $dates, 'date', 'size="1" class="inputbox"', 'value', 'text', $date );
		
		$lists['startdate'] = JHTML::_('calendar', $startdate, 'startdate', 'startdate', '%Y-%m-%d', array('class'=>'inputbox', 'size'=>'11',  'maxlength'=>'20'));
		$lists['enddate'] 	= JHTML::_('calendar', $enddate, 'enddate', 'enddate', '%Y-%m-%d', array('class'=>'inputbox', 'size'=>'11',  'maxlength'=>'20'));
		
		// search filter
		$lists['search']= $search;
		// search id
		$lists['filter_id'] = $filter_id;

		$this->assignRef('lists',		$lists);
		$this->assignRef('items',		$rows);
		$this->assignRef('usergroups',	$usergroups);
		$this->assignRef('pagination',	$pagination);

		// filters
		$this->assignRef('filter_id'		, $filter_id);
		$this->assignRef('filter_itemscount'		, $filter_itemscount);
		$this->assignRef('filter_type'		, $filter_type);
		$this->assignRef('filter_logged'	, $filter_logged);
		$this->assignRef('search'			, $search);
		$this->assignRef('filter_id'			, $filter_id);
		$this->assignRef('date'				, $date);
		$this->assignRef('startdate'		, $startdate);
		$this->assignRef('enddate'			, $enddate);
		
		parent::display($tpl);
	}
}
