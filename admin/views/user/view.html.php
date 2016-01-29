<?php
/**
* @version		$Id: view.html.php 1901 2014-05-07 02:37:25Z ggppdk $
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

jimport('legacy.view.legacy');

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
		$document = JFactory::getDocument();
		$db       = JFactory::getDBO();
		$me       = JFactory::getUser();
		
		$cid = JRequest::getVar( 'cid', array(0), '', 'array' );
		JArrayHelper::toInteger($cid, array(0));
		$edit = JRequest::getVar('edit',true);
		if (!$cid) $edit = false;
		
		if (FLEXI_J16GE) {
			$form = $this->get('Form');
			$form->setValue('password',		null);
			$form->setValue('password2',	null);
		}
		$form_folder = FLEXI_J16GE ? 'forms'.DS : '';


		
		// *****************
		// Load JS/CSS files
		// *****************
		
		// Add css to document
		$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VHASH);
		$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH);
		
		// Add JS frameworks
		flexicontent_html::loadFramework('select2');
		//JHTML::_('behavior.tooltip');
		
		// Add js function to overload the joomla submitform validation
		JHTML::_('behavior.formvalidation');  // load default validation JS to make sure it is overriden
		$document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/admin.js', FLEXI_VHASH);
		$document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/validate.js', FLEXI_VHASH);
		
		// load language file for com_users component
		JFactory::getLanguage()->load('com_users', JPATH_ADMINISTRATOR, 'en-GB', true);
		JFactory::getLanguage()->load('com_users', JPATH_ADMINISTRATOR, null, true);

		//create the toolbar
		if ( $edit ) {
			JToolBarHelper::title( JText::_( 'FLEXI_EDIT_USER' ), 'authoredit' );
		} else {
			JToolBarHelper::title( JText::_( 'FLEXI_ADD_USER' ), 'authoradd' );
		}
		
		$ctrl = FLEXI_J16GE ? 'users.' : '';
		JToolBarHelper::apply( $ctrl.'apply' );
		JToolBarHelper::save( $ctrl.'save' );
		JToolBarHelper::custom( $ctrl.'saveandnew', 'savenew.png', 'savenew.png', 'FLEXI_SAVE_AND_NEW', false );
		JToolBarHelper::cancel( $ctrl.'cancel' );
		JToolBarHelper::help( 'screen.users.edit' );

		$user   = $edit  ?  JUser::getInstance($cid[0])  :  JUser::getInstance();
		$myuser = JFactory::getUser();
		$acl    = JFactory::getACL();

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
			$contact = NULL;
			// Get the default group id for a new user
			$config = JComponentHelper::getParams( 'com_users' );
			$newGrp = $config->get( 'new_usertype' );
			if (!FLEXI_J16GE)
				$user->set( 'gid', $acl->get_group_id( $newGrp, null, 'ARO' ) );
			else
				$user->set( 'gid', $newGrp );
		}
		
		
		
		// ********************
		// Initialise variables
		// ********************
		
		$cparams = JComponentHelper::getParams('com_flexicontent');
		
		
		// *************************************************************************************************
		// Get author extended data, basic (described in author.xml) and category (described incategory.xml)
		// *************************************************************************************************
		
		JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
		$author_user_id = (int) $cid[0];
		$flexiauthor_extdata = JTable::getInstance('flexicontent_authors_ext', '');
		$flexiauthor_extdata->load( $author_user_id );
		//echo "<pre>"; print_r($flexiauthor_extdata); echo "</pre>"; exit;
		
		// ***********************
		// AUTHOR basic parameters
		// ***********************
		
		// Load the DB parameter values and the XML description from file,
		// NOTE: this is one step for J1.5 via a JParameter object, but in J1.6+ the use of XML file
		// in JParameter is deprecated, instead we will JForm to load XML description and thus be able to render it
		
		$auth_xml = JPATH_COMPONENT.DS.'models'.DS.$form_folder.'author.xml';
		if (FLEXI_J16GE)
			$params_authorbasic = new JRegistry($flexiauthor_extdata->author_basicparams);
		else
			$params_authorbasic = new JParameter($flexiauthor_extdata->author_basicparams, $auth_xml);
		//echo "<pre>"; print_r($params_authorbasic); echo "</pre>"; exit;
		
		if (FLEXI_J16GE)
		{
			// Read XML file
			$xml_string = str_replace('name="params"', 'name="authorbasicparams"', file_get_contents($auth_xml));
			
			// Load the form description from the XML string
			$jform_authorbasic = new JForm('com_flexicontent.author', array('control' => 'jform', 'load_data' => true));
			$jform_authorbasic->load($xml_string, $isFile=false);
			
			// Set DB parameter values into the JForm object
			foreach ($jform_authorbasic->getFieldset() as $fsetname =>  $field) {
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
		if (FLEXI_J16GE)
			$params_authorcat = new JRegistry($flexiauthor_extdata->author_catparams);
		else
			$params_authorcat = new JParameter($flexiauthor_extdata->author_catparams, $cat_xml);
		//echo "<pre>"; print_r($params_authorcat); echo "</pre>"; exit;
			
		if (FLEXI_J16GE)
		{
			// Read XML file
			$xml_string = str_replace('name="params"', 'name="authorcatparams"', file_get_contents($cat_xml));
			
			// Load the form description from the XML string
			$jform_authorcat = new JForm('com_flexicontent.category', array('control' => 'jform', 'load_data' => true));
			$jform_authorcat->load($xml_string, $isFile=false);
			
			// Set DB parameter values into the JForm object
			foreach ($jform_authorcat->getFieldset() as $fsetname => $field) {
				$jform_authorcat->setValue($field->fieldname, $group = 'authorcatparams', $value = $params_authorcat->get($field->fieldname) );
			}
		}
		
		
		// **********************************************************************************
		// Get Templates and apply Template Parameters values into the form fields structures 
		// **********************************************************************************
		
		$themes		= flexicontent_tmpl::getTemplates();
		$tmpls		= $themes->category;
		
		if (FLEXI_J16GE) {
			$params_author = new JRegistry($user->params);
		}
		foreach ($tmpls as $tmpl) {
			if (FLEXI_J16GE) {
				$jform = new JForm('com_flexicontent.template.category', array('control' => 'jform', 'load_data' => true));
				$jform->load($tmpl->params);
				$tmpl->params = $jform;
				// ... values applied at the template form file
			} else {
				$tmpl->params->loadINI($flexiauthor_extdata->author_catparams);
			}
		}
		
		//$lists = array();
		//$javascript = "onchange=\"javascript:if (document.forms[0].image.options[selectedIndex].value!='') {document.imagelib.src='../images/stories/' + document.forms[0].image.options[selectedIndex].value} else {document.imagelib.src='../images/blank.png'}\"";
		//$lists['imagelist'] 		= JHTML::_('list.images', 'image', $flexiauthor_extdata->image, $javascript, '/images/stories/' );
		
		if ( !$user->get('id') ) {
			$new_usertype = JComponentHelper::getParams('com_users')->get('new_usertype');
			$usergroups = $new_usertype ? array($new_usertype) : array();
		} else {
			$ugrps_qtmpl = 'SELECT group_id FROM #__user_usergroup_map AS ug WHERE ug.user_id = %d';
			$query = sprintf( $ugrps_qtmpl, intval( $user->get('id') ) );
			$db->setQuery( $query );
			$usergroups = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
			if ($db->getErrorMsg())	echo $db->getErrorMsg();
		}

		// build the html select list
		$lists['block'] 	= JHTML::_('select.booleanlist',  'block', 'class="inputbox" size="1"', $user->get('block') );

		// build the html select list
		$lists['sendEmail'] = JHTML::_('select.booleanlist',  'sendEmail', 'class="inputbox" size="1"', $user->get('sendEmail') );

		$this->assignRef('me'				, $me);
		$this->assignRef('document'	, $document);
		$this->assignRef('lists'		, $lists);
		
		$this->assignRef('user'				, $user);
		$this->assignRef('usergroups'	, $usergroups);
		$this->assignRef('contact'		, $contact);
		
		$this->assignRef('cparams',	$cparams);
		
		$this->assignRef('params_authorbasic'	, $params_authorbasic);
		$this->assignRef('params_authorcat'		, $params_authorcat);
		
		$this->assignRef('jform_authorbasic'	, $jform_authorbasic);
		$this->assignRef('jform_authorcat'		, $jform_authorcat);
		
		$this->assignRef('tmpls'		, $tmpls);
		$this->assignRef('form'		, $form);
		$this->assignRef('params_author'		, $params_author);
		
		parent::display($tpl);
	}
}
