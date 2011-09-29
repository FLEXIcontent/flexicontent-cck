<?php
/**
* @version		$Id: view.html.php 19343 2010-11-03 18:12:02Z ian $
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

jimport( 'joomla.application.component.view');

/**
 * HTML View class for the Users component
 *
 * @static
 * @package		Joomla
 * @subpackage	Users
 * @since 1.0
 */
class FlexicontentViewUsers extends JView
{
	function display($tpl = null)
	{
		global $mainframe, $option;

		$db				=& JFactory::getDBO();
		$document	= & JFactory::getDocument();
		$currentUser	=& JFactory::getUser();
		$acl			=& JFactory::getACL();
		
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
			$js .= "$$('.col_title').each(function(el){ el.addClass('yellow'); });";
		} else {
			$js .= "$$('.col_title').each(function(el){ el.removeClass('yellow'); });";
		}
		$js .= "});";
		$document->addScriptDeclaration($js);



		FLEXISubmenu('CanAuthors');

		//create the toolbar
		JToolBarHelper::title( JText::_( 'FLEXI_AUTHORS' ), 'users' );
		JToolBarHelper::title( JText::_( 'User Manager' ), 'user.png' );
		JToolBarHelper::custom( 'logout', 'cancel.png', 'cancel_f2.png', 'Logout' );
		JToolBarHelper::deleteList();
		JToolBarHelper::editListX();
		JToolBarHelper::addNewX();
		JToolBarHelper::help( 'screen.users' );

		$limit		= $mainframe->getUserStateFromRequest( 'global.list.limit', 'limit', $mainframe->getCfg('list_limit'), 'int' );
		$limitstart = $mainframe->getUserStateFromRequest( $option.'.limitstart', 'limitstart', 0, 'int' );

		$where = array(); $having = array();
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
			if ( $filter_type == 'Public Frontend' )
			{
				$where[] = ' a.usertype = \'Registered\' OR a.usertype = \'Author\' OR a.usertype = \'Editor\' OR a.usertype = \'Publisher\' ';
			}
			else if ( $filter_type == 'Public Backend' )
			{
				$where[] = 'a.usertype = \'Manager\' OR a.usertype = \'Administrator\' OR a.usertype = \'Super Administrator\' ';
			}
			else
			{
				$where[] = 'a.usertype = LOWER( '.$db->Quote($filter_type).' ) ';
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

		// exclude any child group id's for this user
		$pgids = $acl->get_group_children( $currentUser->get('gid'), 'ARO', 'RECURSE' );

		if (is_array( $pgids ) && count( $pgids ) > 0)
		{
			JArrayHelper::toInteger($pgids);
			$where[] = 'a.gid NOT IN (' . implode( ',', $pgids ) . ')';
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
		
		// Do main query to get the authors
		$query = 'SELECT SQL_CALC_FOUND_ROWS a.*, g.name AS groupname, COUNT(i.id) as itemscount '
			. ' FROM #__users AS a'
			. ' INNER JOIN #__core_acl_aro AS aro ON aro.value = a.id'
			. ' INNER JOIN #__core_acl_groups_aro_map AS gm ON gm.aro_id = aro.id'
			. ' INNER JOIN #__core_acl_aro_groups AS g ON g.id = gm.group_id'
			. ' LEFT JOIN #__flexicontent_authors_ext AS ue ON g.id = ue.user_id'
			. ' LEFT JOIN #__content AS i ON i.created_by = a.id '
			. $filter
			. $where
			. ' GROUP BY a.id'
			. $having
			. $orderby
		;
		$db->setQuery( $query, $limitstart, $limit );
		$rows = $db->loadObjectList();
		
		// Get total and create pagination controls
		$db->setQuery("SELECT FOUND_ROWS()");
		$total = $db->loadResult();
		if (!$total) echo $db->getErrorMsg();
		
		jimport('joomla.html.pagination');
		$pagination = new JPagination( $total, $limitstart, $limit );
		
		// Set loggedin property for all authors
		$n = count( $rows );
		$template = 'SELECT COUNT(s.userid)'
			. ' FROM #__session AS s'
			. ' WHERE s.userid = %d'
		;
		for ($i = 0; $i < $n; $i++)
		{
			$row = &$rows[$i];
			$query = sprintf( $template, intval( $row->id ) );
			$db->setQuery( $query );
			$row->loggedin = $db->loadResult();
		}

		// get list of Groups for dropdown filter
		$query = 'SELECT name AS value, name AS text'
			. ' FROM #__core_acl_aro_groups'
			. ' WHERE name != "ROOT"'
			. ' AND name != "USERS"'
		;
		$db->setQuery( $query );
		$types[] 		= JHTML::_('select.option',  '', '- '. JText::_( 'Select Group' ) .' -' );
		foreach( $db->loadObjectList() as $obj )
		{
			$types[] = JHTML::_('select.option',  $obj->value, JText::_( $obj->text ) );
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

		$this->assignRef('user',		JFactory::getUser());
		$this->assignRef('lists',		$lists);
		$this->assignRef('items',		$rows);
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
