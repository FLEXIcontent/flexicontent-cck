<?php
/**
* @version		$Id: view.html.php 1192 2012-03-14 09:00:59Z emmanuel.danan@gmail.com $
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
class FlexicontentViewUser extends JViewLegacy
{
	function display($tpl = null)
	{
		global $mainframe;

		//initialise variables
		$document = & JFactory::getDocument();
		$me       = & JFactory::getUser();
		
		$cid = JRequest::getVar( 'cid', array(0), '', 'array' );
		JArrayHelper::toInteger($cid, array(0));
		$edit = JRequest::getVar('edit',true);
		if (!$cid) $edit = false;
		
		if (FLEXI_J16GE) {
			$form = & $this->get('Form');
			$form->setValue('password',		null);
			$form->setValue('password2',	null);
		}
		$form_folder = FLEXI_J16GE ? 'forms'.DS : '';
		
		JHTML::_('behavior.tooltip');

		//add css to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');
		//add js function to overload the joomla submitform
		$document->addScript('components/com_flexicontent/assets/js/admin.js');
		$document->addScript('components/com_flexicontent/assets/js/validate.js');
		
		// load language file for com_users component
		JFactory::getLanguage()->load('com_users', JPATH_ADMINISTRATOR, 'en-GB', true);
		JFactory::getLanguage()->load('com_users', JPATH_ADMINISTRATOR, null, true);

		//create the toolbar
		if ( $edit ) {
			JToolBarHelper::title( JText::_( 'FLEXI_EDIT_AUTHOR' ), 'authoredit' );
		} else {
			JToolBarHelper::title( JText::_( 'FLEXI_ADD_AUTHOR' ), 'authoradd' );
		}
		
		$ctrl = FLEXI_J16GE ? 'users.' : '';
		JToolBarHelper::apply( $ctrl.'apply' );
		JToolBarHelper::save( $ctrl.'save' );
		JToolBarHelper::custom( $ctrl.'saveandnew', 'savenew.png', 'savenew.png', 'FLEXI_SAVE_AND_NEW', false );
		JToolBarHelper::cancel( $ctrl.'cancel' );
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
			$newGrp	= $config->get( 'new_usertype' );
			if (!FLEXI_J16GE)
				$user->set( 'gid', $acl->get_group_id( $newGrp, null, 'ARO' ) );
			else
				$user->set( 'gid', $newGrp );
		}
		
		// **************************************************
		// Include needed files and add needed js / css files
		// **************************************************
		
		// Load pane behavior
		jimport('joomla.html.pane');
		
		// Load tooltips
		FLEXI_J30GE ? JHtml::_('behavior.framework') : JHTML::_('behavior.mootools');
		JHTML::_('behavior.tooltip');
		
		// Add css to document
		$document->addStyleSheet('components/com_flexicontent/assets/css/flexicontentbackend.css');
		
		// Add js function to overload the joomla submitform
		$document->addScript('components/com_flexicontent/assets/js/admin.js');
		$document->addScript('components/com_flexicontent/assets/js/validate.js');
		
		
		// *************************************************************************************************
		// Get author extended data, basic (described in author.xml) and category (described incategory.xml)
		// *************************************************************************************************
		
		JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
		$author_user_id = (int) $cid[0];
		$flexiauthor_extdata = & JTable::getInstance('flexicontent_authors_ext', '');
		$flexiauthor_extdata->load( $author_user_id );
		//echo "<pre>"; print_r($flexiauthor_extdata); echo "</pre>";
		
		// ***********************
		// AUTHOR basic parameters
		// ***********************
		
		// Load the DB parameter values and the XML description from file,
		// NOTE: this is one step for J1.5 via a JParameter object, but in J1.6+ the use of XML file
		// in JParameter is deprecated, instead we will JForm to load XML description and thus be able to render it
		
		$auth_xml = JPATH_COMPONENT.DS.'models'.DS.$form_folder.'author.xml';
		$params_authorbasic = new JParameter($flexiauthor_extdata->author_basicparams, !FLEXI_J16GE ? $auth_xml : '');
		//echo "<pre>"; print_r($params_authorbasic); echo "</pre>";
		
		if (FLEXI_J16GE)
		{
			// Parse XML file
			$xml = JFactory::getXMLParser('Simple');
			//$xml->loadFile($auth_xml);
			$xml->loadString(str_replace('name="params"', 'name="authorbasicparams"', file_get_contents($auth_xml)));
			$xml_string = $xml->document->toString();
			
			// Load the form description from the XML string
			$jform_authorbasic = new JForm('com_flexicontent.author', array('control' => 'jform', 'load_data' => true));
			$jform_authorbasic->load($xml_string);
			
			// Set DB parameter values into the JForm object
			foreach ($jform_authorbasic->getFieldset() as $field) {
				$jform_authorbasic->setValue($field->fieldname, $group = 'authorbasicparams', $value = $params_authorbasic->get($field->fieldname) );
			}
		}
		
		// **************************
		// AUTHOR category parameters
		// **************************
		
		// Load the DB parameter values and the XML description from file,
		// NOTE: this is one step for J1.5 via a JParameter object, but in J1.6+ the use of XML file
		// in JParameter is deprecated, instead we will JForm to load XML description and thus be able to render it
		
		$cat_xml = JPATH_COMPONENT.DS.'models'.DS.$form_folder.'category.xml';
		$params_authorcat = new JParameter($flexiauthor_extdata->author_catparams, !FLEXI_J16GE ? $cat_xml : '');
		//echo "<pre>"; print_r($params_authorcat); echo "</pre>";
			
		if (FLEXI_J16GE)
		{
			// Parse XML file
			$xml = JFactory::getXMLParser('Simple');
			//$xml->loadFile($cat_xml);
			$xml->loadString(str_replace('name="params"', 'name="authorcatparams"', file_get_contents($cat_xml)));
			$xml_string = $xml->document->toString();
			
			// Load the form description from the XML string
			$jform_authorcat = new JForm('com_flexicontent.category', array('control' => 'jform', 'load_data' => true));
			$jform_authorcat->load($xml_string);
			
			// Set DB parameter values into the JForm object
			foreach ($jform_authorcat->getFieldset() as $field) {
				$jform_authorcat->setValue($field->fieldname, $group = 'authorcatparams', $value = $params_authorcat->get($field->fieldname) );
			}
		}
		
		
		// **********************************************************************************
		// Get Templates and apply Template Parameters values into the form fields structures 
		// **********************************************************************************
		
		$themes		= flexicontent_tmpl::getTemplates();
		$tmpls		= $themes->category;
		
		if (FLEXI_J16GE) {
			$params_author = new JParameter($user->params, '');
		}
		foreach ($tmpls as $tmpl) {
			if (FLEXI_J16GE) {
				$jform = new JForm('com_flexicontent.template.category', array('control' => 'jform', 'load_data' => true));
				$jform->load($tmpl->params);
				$tmpl->params = $jform;
				// ... values applied at the template form file
			} else {
				$tmpl->params->loadINI($user->params);
			}
		}
		
		//$lists = array();
		//$javascript = "onchange=\"javascript:if (document.forms[0].image.options[selectedIndex].value!='') {document.imagelib.src='../images/stories/' + document.forms[0].image.options[selectedIndex].value} else {document.imagelib.src='../images/blank.png'}\"";
		//$lists['imagelist'] 		= JHTML::_('list.images', 'image', $flexiauthor_extdata->image, $javascript, '/images/stories/' );

		if (!FLEXI_J16GE)
			$pane			= & JPane::getInstance('sliders');
		
		
		// *** Get Component's Global Configuration ***
		$fcconfig		= &JComponentHelper::getParams( 'com_flexicontent' );
		$authordetails_itemscat		= $fcconfig->get( 'authordetails_itemscat', 0 );
		
		if (!FLEXI_J16GE) {
			$userObjectID 	= $acl->get_object_id( 'users', $user->get('id'), 'ARO' );
			$userGroups 	= $acl->get_object_groups( $userObjectID, 'ARO' );
			$userGroupName 	= strtolower( $acl->get_group_name( $userGroups[0], 'ARO' ) );
			$userIsAdmin = ($userGroupName == 'administrator');
	
			$myObjectID 	= $acl->get_object_id( 'users', $myuser->get('id'), 'ARO' );
			$myGroups 		= $acl->get_object_groups( $myObjectID, 'ARO' );
			$myGroupName 	= strtolower( $acl->get_group_name( $myGroups[0], 'ARO' ) );;
			$myIsAdmin = ($myGroupName == 'administrator');
		} else {
		}
		
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

			if ( $user->get('id') )
			{
				// get all the groups from the user
				$query 	= 'SELECT group_id'
				. ' FROM #__flexiaccess_members'
				. ' WHERE member_id = '.(int) $cid[0]
				;
				$db->setQuery( $query );
				$usergroups = FLEXI_J30GE ? $db->loadColumn() : $db->loadResultArray();
			}
			else
			{
				$usergroups = array();
			}
			$lists['access'] 	= JHTML::_('select.genericlist',   $allgroups, 'groups[]', 'size="10" multiple="multiple"', 'value', 'text', $usergroups );		
		}
		
		if (!FLEXI_J16GE) {
			if ( $userIsAdmin && $myIsAdmin )
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
		} else {
			if ( !$user->get('id') ) {
				$new_usertype = JComponentHelper::getParams('com_users')->get('new_usertype');
				$usergroups = $new_usertype ? array($new_usertype) : array();
			} else {
				$ugrps_qtmpl = 'SELECT group_id FROM #__user_usergroup_map AS ug WHERE ug.user_id = %d';
				$query = sprintf( $ugrps_qtmpl, intval( $user->get('id') ) );
				$db->setQuery( $query );
				$usergroups = FLEXI_J30GE ? $db->loadColumn() : $db->loadResultArray();
				if ($db->getErrorMsg())	echo $db->getErrorMsg();
			}
		}

		// build the html select list
		$lists['block'] 	= JHTML::_('select.booleanlist',  'block', 'class="inputbox" size="1"', $user->get('block') );

		// build the html select list
		$lists['sendEmail'] = JHTML::_('select.booleanlist',  'sendEmail', 'class="inputbox" size="1"', $user->get('sendEmail') );

		$this->assignRef('me'				, $me);
		$this->assignRef('document'	, $document);
		$this->assignRef('lists'		, $lists);
		if (!FLEXI_J16GE)
			$this->assignRef('pane'		, $pane);
		
		$this->assignRef('user'				, $user);
		$this->assignRef('usergroups'	, $usergroups);
		$this->assignRef('contact'		, $contact);
		
		$this->assignRef('fcconfig',	$fcconfig);
		$this->assignRef('authordetails_itemscat',	$authordetails_itemscat);
		
		$this->assignRef('params_authorbasic'	, $params_authorbasic);
		$this->assignRef('params_authorcat'		, $params_authorcat);
		if (FLEXI_J16GE) {
			$this->assignRef('jform_authorbasic'	, $jform_authorbasic);
			$this->assignRef('jform_authorcat'		, $jform_authorcat);
		}
		
		$this->assignRef('tmpls'		, $tmpls);
		if (FLEXI_J16GE) {
			$this->assignRef('form'		, $form);
			$this->assignRef('params_author'		, $params_author);
		}
		
		parent::display($tpl);
	}
}
