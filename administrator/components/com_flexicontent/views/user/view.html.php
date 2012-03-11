<?php
/**
* @version		$Id: view.html.php 14401 2010-01-26 14:10:00Z louis $
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
class FlexicontentViewUser extends JView
{
	function display($tpl = null)
	{
		global $mainframe;

		//Load pane behavior
		jimport('joomla.html.pane');

		//initialise variables
		$document	= & JFactory::getDocument();
		$cid		= JRequest::getVar( 'cid', array(0), '', 'array' );
		$edit		= JRequest::getVar('edit',true);
		$me 		= JFactory::getUser();
		JArrayHelper::toInteger($cid, array(0));

		JHTML::_('behavior.tooltip');

		//add css to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');
		//add js function to overload the joomla submitform
		$document->addScript('components/com_flexicontent/assets/js/admin.js');
		$document->addScript('components/com_flexicontent/assets/js/validate.js');

		//create the toolbar
		if ( $edit ) {
			JToolBarHelper::title( JText::_( 'FLEXI_EDIT_AUTHOR' ), 'authoredit' );
		} else {
			JToolBarHelper::title( JText::_( 'FLEXI_ADD_AUTHOR' ), 'authoradd' );
		}
		JToolBarHelper::apply();
		JToolBarHelper::save();
		JToolBarHelper::custom( 'saveandnew', 'savenew.png', 'savenew.png', 'FLEXI_SAVE_AND_NEW', false );
		JToolBarHelper::cancel();
		JToolBarHelper::help( 'screen.users.edit' );

		$db 		=& JFactory::getDBO();
		if($edit)
			$user 		=& JUser::getInstance( $cid[0] );
		else
			$user 		=& JUser::getInstance();

		$myuser		=& JFactory::getUser();
		$acl		=& JFactory::getACL();

		// Check for post data in the event that we are returning
		// from a unsuccessful attempt to save data
		$post = JRequest::get('post');
		if ( $post ) {
			$user->bind($post);
		}

		if ( $user->get('id'))
		{
			$query = 'SELECT *'
			. ' FROM #__contact_details'
			. ' WHERE user_id = '.(int) $cid[0]
			;
			$db->setQuery( $query );
			$contact = $db->loadObjectList();
		}
		else
		{
			$contact 	= NULL;
			// Get the default group id for a new user
			$config		= &JComponentHelper::getParams( 'com_users' );
			$newGrp		= $config->get( 'new_usertype' );
			$user->set( 'gid', $acl->get_group_id( $newGrp, null, 'ARO' ) );
		}
		
		// *** Get author extended data ***
		JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
		$author_user_id = (int) $cid[0];
		$flexiauthor_extdata = & JTable::getInstance('flexicontent_authors_ext', '');
		$flexiauthor_extdata->load( $author_user_id );
		//echo "<pre>"; print_r($flexiauthor_extdata); echo "</pre>";
		
		$themes		= flexicontent_tmpl::getTemplates();
		$tmpls		= $themes->category;
		
		$form_authorbasic = new JParameter($flexiauthor_extdata->author_basicparams, JPATH_COMPONENT.DS.'models'.DS.'author.xml');
		$form_authorcat = new JParameter($flexiauthor_extdata->author_catparams, JPATH_COMPONENT.DS.'models'.DS.'category.xml');
		
		foreach ($tmpls as $tmpl) {
			$tmpl->params->loadINI($flexiauthor_extdata->author_catparams);
		}
		
		//$Lists = array();
		//$javascript = "onchange=\"javascript:if (document.forms[0].image.options[selectedIndex].value!='') {document.imagelib.src='../images/stories/' + document.forms[0].image.options[selectedIndex].value} else {document.imagelib.src='../images/blank.png'}\"";
		//$Lists['imagelist'] 		= JHTML::_('list.images', 'image', $flexiauthor_extdata->image, $javascript, '/images/stories/' );

		jimport('joomla.html.pane');
		$pane 		= & JPane::getInstance('sliders');
		$document	= & JFactory::getDocument();
		JHTML::_('behavior.tooltip');
		
		//add css to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');
		//add js function to overload the joomla submitform
		$document->addScript('components/com_flexicontent/assets/js/admin.js');
		$document->addScript('components/com_flexicontent/assets/js/validate.js');
				
		
		// *** Get Component's Global Configuration ***
		$fcconfig		= &JComponentHelper::getParams( 'com_flexicontent' );
		$authordetails_itemscat		= $fcconfig->get( 'authordetails_itemscat', 0 );
		
		
		$userObjectID 	= $acl->get_object_id( 'users', $user->get('id'), 'ARO' );
		$userGroups 	= $acl->get_object_groups( $userObjectID, 'ARO' );
		$userGroupName 	= strtolower( $acl->get_group_name( $userGroups[0], 'ARO' ) );

		$myObjectID 	= $acl->get_object_id( 'users', $myuser->get('id'), 'ARO' );
		$myGroups 		= $acl->get_object_groups( $myObjectID, 'ARO' );
		$myGroupName 	= strtolower( $acl->get_group_name( $myGroups[0], 'ARO' ) );;

		// ensure user can't add/edit group higher than themselves
		/* NOTE : This check doesn't work commented out for the time being
		if ( is_array( $myGroups ) && count( $myGroups ) > 0 )
		{
			$excludeGroups = (array) $acl->get_group_children( $myGroups[0], 'ARO', 'RECURSE' );
		}
		else
		{
			$excludeGroups = array();
		}

		if ( in_array( $userGroups[0], $excludeGroups ) )
		{
			echo 'not auth';
			$mainframe->redirect( 'index.php?option=com_flexicontent&amp;controller=users&amp;view=users', JText::_('NOT_AUTH') );
		}
		*/

		/*
		if ( $userGroupName == 'super administrator' )
		{
			// super administrators can't change
	 		$lists['gid'] = '<input type="hidden" name="gid" value="'. $currentUser->gid .'" /><strong>'. JText::_( 'Super Administrator' ) .'</strong>';
		}
		else if ( $userGroupName == $myGroupName && $myGroupName == 'administrator' ) {
		*/
		if (FLEXI_ACCESS) 
		{
			// Create the list of all groups except public and registered
			$query	= 'SELECT id AS value, name AS text, level, ordering'
					. ' FROM #__flexiaccess_groups'
					. ' WHERE level > 1'
					. ' ORDER BY ordering ASC'
					;
			$db->setQuery( $query );
			$allgroups = $db->loadObjectList();

			if ( $user->get('id'))
			{
				// get all the groups from the user
				$query = 'SELECT group_id'
				. ' FROM #__flexiaccess_members'
				. ' WHERE member_id = '.(int) $cid[0]
				;
				$db->setQuery( $query );
				$usergroups = $db->loadResultArray();
			}
			else
			{
				$usergroups = array();
			}
			$lists['access'] 	= JHTML::_('select.genericlist',   $allgroups, 'groups[]', 'size="10" multiple="multiple"', 'value', 'text', $usergroups );		
		}

		if ( $userGroupName == $myGroupName && $myGroupName == 'administrator' )
		{
			// administrators can't change each other
			$lists['gid'] = '<input type="hidden" name="gid" value="'. $user->get('gid') .'" /><strong>'. JText::_( 'Administrator' ) .'</strong>';
		}
		else
		{
			$gtree = $acl->get_group_children_tree( null, 'USERS', false );

			// remove users 'above' me
			//$i = 0;
			//while ($i < count( $gtree )) {
			//	if ( in_array( $gtree[$i]->value, (array)$excludeGroups ) ) {
			//		array_splice( $gtree, $i, 1 );
			//	} else {
			//		$i++;
			//	}
			//}

			$lists['gid'] 	= JHTML::_('select.genericlist',   $gtree, 'gid', 'size="10"', 'value', 'text', $user->get('gid') );
		}
		

		// build the html select list
		$lists['block'] 	= JHTML::_('select.booleanlist',  'block', 'class="inputbox" size="1"', $user->get('block') );

		// build the html select list
		$lists['sendEmail'] = JHTML::_('select.booleanlist',  'sendEmail', 'class="inputbox" size="1"', $user->get('sendEmail') );

		$this->assignRef('me', 		$me);
		$this->assignRef('lists',	$lists);
		$this->assignRef('user',	$user);
		$this->assignRef('contact',	$contact);
		$this->assignRef('flexiauthor_extdata',	$flexiauthor_extdata);
		$this->assignRef('fcconfig',	$fcconfig);
		$this->assignRef('authordetails_itemscat',	$authordetails_itemscat);
		
		$this->assignRef('document'     , $document);
		$this->assignRef('form_authorbasic'	, $form_authorbasic);
		$this->assignRef('form_authorcat'		, $form_authorcat);
		$this->assignRef('pane'			, $pane);
		$this->assignRef('tmpls'		, $tmpls);
		//$this->assignRef('Lists'		, $Lists);

		parent::display($tpl);
	}
}
