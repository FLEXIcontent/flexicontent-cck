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

JLoader::register('FlexicontentViewBaseRecord', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/base/view_record.php');

/**
 * HTML View class for the User screen
 */
class FlexicontentViewUser extends FlexicontentViewBaseRecord
{
	var $proxy_option = 'com_users';

	/**
	 * Display the view
	 */
	public function display($tpl = null)
	{
		/**
		 * Initialize variables, flags, etc
		 */

		$app        = JFactory::getApplication();
		$jinput     = $app->input;
		$document   = JFactory::getDocument();
		$user       = JFactory::getUser();
		$db         = JFactory::getDbo();
		$cparams    = JComponentHelper::getParams('com_flexicontent');
		$perms      = FlexicontentHelperPerm::getPerm();

		// Get url vars and some constants
		$option     = $jinput->get('option', '', 'cmd');
		$view       = $jinput->get('view', '', 'cmd');
		$task       = $jinput->get('task', '', 'cmd');
		$controller = $jinput->get('controller', '', 'cmd');

		$isAdmin  = $app->isAdmin();
		$isCtmpl  = $jinput->getCmd('tmpl') === 'component';

		$tip_class = ' hasTooltip';
		$manager_view = 'users';
		$ctrl = 'users';
		$js = '';


		/**
		 * Common view
		 */

		$this->prepare_common_fcview();


		/**
		 * Get record data, and check if record is already checked out
		 */

		// Get model and load the record data
		$model = $this->getModel();
		$row   = $this->get('Item');
		$isnew = ! $row->id;

		// Get JForm
		$form  = $this->get('Form');
		if (!$form)
		{
			$app->enqueueMessage($model->getError(), 'warning');
			$app->redirect( 'index.php?option=com_flexicontent&view=' . $manager_view );
		}



		/**
		 * Include needed files and add needed js / css files
		 */

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
		flexicontent_html::loadFramework('touch-punch');
		flexicontent_html::loadFramework('prettyCheckable');
		flexicontent_html::loadFramework('flexi-lib');
		flexicontent_html::loadFramework('flexi-lib-form');

		// Load custom behaviours: form validation, popup tooltips
		JHtml::_('behavior.formvalidation');
		JHtml::_('bootstrap.tooltip');

		// Add js function to overload the joomla submitform validation
		$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/admin.js', FLEXI_VHASH);
		$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/validate.js', FLEXI_VHASH);


		/**
		 * Create the toolbar
		 */

		$toolbar = JToolbar::getInstance('toolbar');

		// Creation flag used to decide if adding save and new / save as copy buttons are allowed
		$cancreate = false;  // We will not create new users in our backend

		// SET toolbar title
		!$isnew
			? JToolbarHelper::title(JText::_( 'FLEXI_EDIT_USER'), 'authoredit')
			: JToolbarHelper::title(JText::_( 'FLEXI_ADD_USER'), 'authoradd');

		$ctrl = 'users.';
		JToolbarHelper::apply( $ctrl.'apply' );
		JToolbarHelper::save( $ctrl.'save' );
		JToolbarHelper::custom( $ctrl.'save2new', 'savenew.png', 'savenew.png', 'FLEXI_SAVE_AND_NEW', false );
		JToolbarHelper::cancel( $ctrl.'cancel' );
		JToolbarHelper::help( 'screen.users.edit' );

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
			'data-placement="bottom" data-href="index.php?option=com_users&task=user.edit&id='.$row->get('id').'" title="Edit all details of joomla user"'
		);


		// Get contact data
		if ($row->get('id'))
		{
			$query = 'SELECT *'
				. ' FROM #__contact_details'
				. ' WHERE user_id = ' . (int) $row->get('id');
			$contact = $db->setQuery($query)->loadObjectList();
		}

		else
		{
			$contact = NULL;
			// Get the default group id for a new user
			$config = JComponentHelper::getParams('com_users');
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
		$flexiauthor_extdata->load($row->get('id'));
		//echo "<pre>"; print_r($flexiauthor_extdata); echo "</pre>"; exit;


		// ***********************
		// AUTHOR basic parameters
		// ***********************

		// Load the DB parameter values and the XML description from file,
		// NOTE: this is one step for J1.5 via a JParameter object, but in J1.6+ the use of XML file
		// in JParameter is deprecated, instead we will JForm to load XML description and thus be able to render it

		$auth_xml = JPATH_COMPONENT.DS.'models'.DS.'forms'.DS.'author.xml';
		$params_authorbasic = new JRegistry($flexiauthor_extdata->author_basicparams);
		//echo "<pre>"; print_r($params_authorbasic); echo "</pre>"; exit;

		// Read XML file
		//$xml_string = str_replace('name="params"', 'name="authorbasicparams"', file_get_contents($auth_xml));
		$xml_string = file_get_contents($auth_xml);

		// Load the form description from the XML string
		$jform_authorbasic = new JForm('com_flexicontent.author', array('control' => 'jform', 'load_data' => true));
		$jform_authorbasic->load($xml_string, $isFile=false);

		// Set DB parameter values into the JForm object
		foreach ($jform_authorbasic->getFieldset() as $fsetname => $field)
		{
			$jform_authorbasic->setValue($field->fieldname, $group = 'authorbasicparams', $value = $params_authorbasic->get($field->fieldname) );
		}


		// **************************
		// AUTHOR category parameters
		// **************************

		// Load the DB parameter values and the XML description from file,
		// NOTE: this is one step for J1.5 via a JParameter object, but in J1.6+ the use of XML file
		// in JParameter is deprecated, instead we will JForm to load XML description and thus be able to render it

		$cat_xml = JPATH_COMPONENT.DS.'models'.DS.'forms'.DS.'category.xml';
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


		/**
		 * Get Layouts, load language of current selected template and apply Layout parameters values into the fields
		 */

		// Load language file of currently selected template
		$_clayout = $params_authorcat->get('clayout');
		if ($_clayout)
		{
			FLEXIUtilities::loadTemplateLanguageFile($_clayout);
		}

		// Get the category layouts, checking template of current layout for modifications
		$themes		= flexicontent_tmpl::getTemplates($_clayout);
		$tmpls		= $themes->category;

		// Create JForm for the layout and apply Layout parameters values into the fields
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

				if (strlen($value))
				{
					$tmpl->params->setValue($fieldname, 'attribs', $value);
				}
			}
		}

		if (!$row->get('id'))
		{
			$new_usertype = JComponentHelper::getParams('com_users')->get('new_usertype');
			$usergroups = $new_usertype ? array($new_usertype) : array();
		}
		else
		{
			$ugrps_qtmpl = 'SELECT group_id FROM #__user_usergroup_map AS ug WHERE ug.user_id = %d';
			$query = sprintf($ugrps_qtmpl, (int) $row->get('id'));

			$usergroups = $db->setQuery($query)->loadColumn();
		}

		// build the html select list
		$lists['block'] 	= JHtml::_('select.booleanlist',  'block', 'class="inputbox" size="1"', $row->get('block') );

		// build the html select list
		$lists['sendEmail'] = JHtml::_('select.booleanlist',  'sendEmail', 'class="inputbox" size="1"', $row->get('sendEmail') );


		/**
		 * Add inline js to head
		 */

		if ($js)
		{
			$document->addScriptDeclaration('jQuery(document).ready(function(){'
				.$js.
			'});');
		}


		/**
		 * Encode (UTF-8 charset) HTML entities form data so that they can be set as form field values
		 * NOTE: We do NOT yet use JForm thus this is needed
		 */

		JFilterOutput::objectHTMLSafe( $row, ENT_QUOTES, $exclude_keys = '' );


		/**
		 * Assign variables to view
		 */

		$this->document = $document;
		$this->lists = $lists;

		$this->row      = $row;
		$this->form     = $form;
		$this->perms    = $perms;
		$this->tmpls    = $tmpls;

		$this->usergroups = $usergroups;
		$this->contact    = $contact;

		$this->cparams  = $cparams;
		$this->iparams  = $cparams;

		$this->params_authorbasic = $params_authorbasic;
		$this->params_authorcat   = $params_authorcat;

		$this->jform_authorbasic = $jform_authorbasic;
		$this->jform_authorcat   = $jform_authorcat;

		parent::display($tpl);
	}
}
