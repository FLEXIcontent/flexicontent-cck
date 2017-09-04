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
		//initialise variables
		$app      = JFactory::getApplication();
		$document = JFactory::getDocument();
		$jinput   = $app->input;

		$db  = JFactory::getDbo();
		$me  = JFactory::getUser();

		$cid = $jinput->get('cid', array(0), 'array');
		$cid = (int) $cid[0];

		$form = $this->get('Form');
		$form->setValue('password',		null);
		$form->setValue('password2',	null);
		
		$form_folder = 'forms'.DS;
		$isnew = !$cid;

		$view       = $jinput->get('view', '', 'cmd');
		$controller = $jinput->get('controller', '', 'cmd');

		
		// *****************
		// Load JS/CSS files
		// *****************
		
		// Add css to document
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', FLEXI_VHASH);
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x_rtl.css', FLEXI_VHASH);
		
		// Add JS frameworks
		flexicontent_html::loadFramework('select2');
		flexicontent_html::loadFramework('flexi-lib-form');
		
		// Add js function to overload the joomla submitform validation
		JHtml::_('behavior.formvalidation');  // load default validation JS to make sure it is overriden
		$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/admin.js', FLEXI_VHASH);
		$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/validate.js', FLEXI_VHASH);
		
		// load language file for com_users component
		JFactory::getLanguage()->load('com_users', JPATH_ADMINISTRATOR, 'en-GB', true);
		JFactory::getLanguage()->load('com_users', JPATH_ADMINISTRATOR, null, true);

		//create the toolbar
		if ( $isnew ) {
			JToolbarHelper::title( JText::_( 'FLEXI_ADD_USER' ), 'authoradd' );
		} else {
			JToolbarHelper::title( JText::_( 'FLEXI_EDIT_USER' ), 'authoredit' );
		}
		
		$ctrl = 'users.';
		JToolbarHelper::apply( $ctrl.'apply' );
		JToolbarHelper::save( $ctrl.'save' );
		JToolbarHelper::custom( $ctrl.'save2new', 'savenew.png', 'savenew.png', 'FLEXI_SAVE_AND_NEW', false );
		JToolbarHelper::cancel( $ctrl.'cancel' );
		JToolbarHelper::help( 'screen.users.edit' );
		
		$user = $cid ? JUser::getInstance($cid) : JUser::getInstance();

		JText::script("FLEXI_UPDATING_CONTENTS", true);
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
				window.location.reload(false);
				document.body.innerHTML = Joomla.JText._("FLEXI_UPDATING_CONTENTS") + \' <img id="page_loading_img" src="components/com_flexicontent/assets/images/ajax-loader.gif">\';
			}
		');
		
		$modal_title = JText::_('FLEXI_EDIT_JUSER', true);
		$tip_class = ' hasTooltip';
		JToolbarHelper::divider();
		flexicontent_html::addToolBarButton(
			'FLEXI_EDIT_JUSER', $btn_name='edit_juser',
			$full_js="var url = jQuery(this).attr('data-href'); var the_dialog = fc_showDialog(url, 'fc_modal_popup_container', 0, 0, 0, fc_edit_juser_modal_close, {title:'".$modal_title."', loadFunc: fc_edit_juser_modal_load}); return false;",
			$msg_alert='', $msg_confirm='',
			$btn_task='', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false, $btn_class="spaced-btn btn-info".$tip_class, $btn_icon="icon-pencil",
			'data-placement="bottom" data-href="index.php?option=com_users&task=user.edit&id='.$user->get('id').'" title="Edit all details of joomla user"'
		);
		

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
			. ' WHERE user_id = ' . $cid
			;
			$db->setQuery( $query );
			$contact = $db->loadObjectList();
		}
		else
		{
			$contact = NULL;
			// Get the default group id for a new user
			$config = JComponentHelper::getParams( 'com_users' );
		}
		
		
		
		// ********************
		// Initialise variables
		// ********************
		
		$cparams = JComponentHelper::getParams('com_flexicontent');
		
		
		// *************************************************************************************************
		// Get author extended data, basic (described in author.xml) and category (described incategory.xml)
		// *************************************************************************************************
		
		JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
		$flexiauthor_extdata = JTable::getInstance('flexicontent_authors_ext', '');
		$flexiauthor_extdata->load( $cid );
		//echo "<pre>"; print_r($flexiauthor_extdata); echo "</pre>"; exit;
		
		
		// ***********************
		// AUTHOR basic parameters
		// ***********************
		
		// Load the DB parameter values and the XML description from file,
		// NOTE: this is one step for J1.5 via a JParameter object, but in J1.6+ the use of XML file
		// in JParameter is deprecated, instead we will JForm to load XML description and thus be able to render it
		
		$auth_xml = JPATH_COMPONENT.DS.'models'.DS.$form_folder.'author.xml';
		$params_authorbasic = new JRegistry($flexiauthor_extdata->author_basicparams);
		//echo "<pre>"; print_r($params_authorbasic); echo "</pre>"; exit;
		
		// Read XML file
		//$xml_string = str_replace('name="params"', 'name="authorbasicparams"', file_get_contents($auth_xml));
		$xml_string = file_get_contents($auth_xml);
		
		// Load the form description from the XML string
		$jform_authorbasic = new JForm('com_flexicontent.author', array('control' => 'jform', 'load_data' => true));
		$jform_authorbasic->load($xml_string, $isFile=false);
		
		// Set DB parameter values into the JForm object
		foreach ($jform_authorbasic->getFieldset() as $fsetname =>  $field) {
			$jform_authorbasic->setValue($field->fieldname, $group = 'authorbasicparams', $value = $params_authorbasic->get($field->fieldname) );
		}
		
		
		// **************************
		// AUTHOR category parameters
		// **************************
		
		// Load the DB parameter values and the XML description from file,
		// NOTE: this is one step for J1.5 via a JParameter object, but in J1.6+ the use of XML file
		// in JParameter is deprecated, instead we will JForm to load XML description and thus be able to render it
		
		$cat_xml = JPATH_COMPONENT.DS.'models'.DS.$form_folder.'category.xml';
		$params_authorcat = new JRegistry($flexiauthor_extdata->author_catparams);
		//echo "<pre>"; print_r($params_authorcat); echo "</pre>"; exit;
			
		// Read XML file
		$xml_string = str_replace('name="params"', 'name="authorcatparams"', file_get_contents($cat_xml));
		
		// Load the form description from the XML string
		$jform_authorcat = new JForm('com_flexicontent.category', array('control' => 'jform', 'load_data' => true));
		$jform_authorcat->load($xml_string, $isFile=false);
		
		// Set DB parameter values into the JForm object
		foreach ($jform_authorcat->getFieldset() as $fsetname => $field)
		{
			$jform_authorcat->setValue($field->fieldname, $group = 'authorcatparams', $value = $params_authorcat->get($field->fieldname) );
		}
		
		
		// **********************************************************************************
		// Get Templates and apply Template Parameters values into the form fields structures 
		// **********************************************************************************
		
		$themes		= flexicontent_tmpl::getTemplates();
		$tmpls		= $themes->category;
		
		// Load language file of currently selected template
		$_clayout = $params_authorcat->get('clayout');
		if ($_clayout) FLEXIUtilities::loadTemplateLanguageFile( $_clayout );
		
		$params_author = new JRegistry($user->params);
		
		foreach ($tmpls as $tmpl)
		{
			if ($tmpl->name != $_clayout) continue;
			
			$jform = new JForm('com_flexicontent.template.category', array('control' => 'jform', 'load_data' => false));
			$jform->load($tmpl->params);
			$tmpl->params = $jform;
			foreach ($tmpl->params->getGroup('attribs') as $field)
			{
				$fieldname = $field->fieldname;
				$value = $params_authorcat->get($fieldname);
				if (strlen($value)) $tmpl->params->setValue($fieldname, 'attribs', $value);
			}
		}
		
		if ( !$user->get('id') ) {
			$new_usertype = JComponentHelper::getParams('com_users')->get('new_usertype');
			$usergroups = $new_usertype ? array($new_usertype) : array();
		} else {
			$ugrps_qtmpl = 'SELECT group_id FROM #__user_usergroup_map AS ug WHERE ug.user_id = %d';
			$query = sprintf( $ugrps_qtmpl, intval( $user->get('id') ) );
			$db->setQuery( $query );
			$usergroups = $db->loadColumn();
			if ($db->getErrorMsg())	echo $db->getErrorMsg();
		}

		// build the html select list
		$lists['block'] 	= JHtml::_('select.booleanlist',  'block', 'class="inputbox" size="1"', $user->get('block') );

		// build the html select list
		$lists['sendEmail'] = JHtml::_('select.booleanlist',  'sendEmail', 'class="inputbox" size="1"', $user->get('sendEmail') );



		// ************************
		// Assign variables to view
		// ************************

		$this->document = $document;
		$this->lists = $lists;

		$this->view = $view;
		$this->controller = $controller;

		$this->me   = $me;
		$this->user = $user;
		$this->usergroups = $usergroups;
		$this->contact = $contact;

		$this->cparams = $cparams;

		$this->params_authorbasic = $params_authorbasic;
		$this->params_authorcat   = $params_authorcat;

		$this->jform_authorbasic = $jform_authorbasic;
		$this->jform_authorcat   = $jform_authorcat;

		$this->tmpls = $tmpls;
		$this->form  = $form;
		$this->params_author = $params_author;

		parent::display($tpl);
	}
}
