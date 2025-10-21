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

		$app        = \Joomla\CMS\Factory::getApplication();
		$jinput     = $app->input;
		$document   = \Joomla\CMS\Factory::getDocument();
		$user       = \Joomla\CMS\Factory::getUser();
		$db         = \Joomla\CMS\Factory::getContainer()->get(DatabaseInterface::class);
		$cparams    = \Joomla\CMS\Component\ComponentHelper::getParams('com_flexicontent');
		$perms      = FlexicontentHelperPerm::getPerm();

		// Get url vars and some constants
		$option     = $jinput->get('option', '', 'cmd');
		$view       = $jinput->get('view', '', 'cmd');
		$task       = $jinput->get('task', '', 'cmd');
		$controller = $jinput->get('controller', '', 'cmd');

		$isAdmin  = $app->isClient('administrator');
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

		// Get \Joomla\CMS\Form\Form
		$form = $this->get('Form');

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
			!\Joomla\CMS\Factory::getLanguage()->isRtl()
				? $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', array('version' => FLEXI_VHASH))
				: $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', array('version' => FLEXI_VHASH));
			!\Joomla\CMS\Factory::getLanguage()->isRtl()
				? $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x.css' : 'j3x.css'), array('version' => FLEXI_VHASH))
				: $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x_rtl.css' : 'j3x_rtl.css'), array('version' => FLEXI_VHASH));
		}
		else
		{
			!\Joomla\CMS\Factory::getLanguage()->isRtl()
				? $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/flexicontent.css', array('version' => FLEXI_VHASH))
				: $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/flexicontent_rtl.css', array('version' => FLEXI_VHASH));
		}

		// Add JS frameworks
		flexicontent_html::loadFramework('select2');
		flexicontent_html::loadFramework('touch-punch');
		flexicontent_html::loadFramework('prettyCheckable');
		flexicontent_html::loadFramework('flexi-lib');
		flexicontent_html::loadFramework('flexi-lib-form');

		// Load custom behaviours: form validation, popup tooltips
		\Joomla\CMS\HTML\HTMLHelper::_('behavior.formvalidator');
		\Joomla\CMS\HTML\HTMLHelper::_('bootstrap.tooltip');

		// Add js function to overload the joomla submitform validation
		$document->addScript(\Joomla\CMS\Uri\Uri::root(true).'/components/com_flexicontent/assets/js/admin.js', array('version' => FLEXI_VHASH));
		$document->addScript(\Joomla\CMS\Uri\Uri::root(true).'/components/com_flexicontent/assets/js/validate.js', array('version' => FLEXI_VHASH));


		/**
		 * Create the toolbar
		 */

		$toolbar = \Joomla\CMS\Toolbar\Toolbar::getInstance('toolbar');

		// Creation flag used to decide if adding save and new / save as copy buttons are allowed
		$cancreate = false;  // We will not create new users in our backend

		// SET toolbar title
		!$isnew
			? \Joomla\CMS\Toolbar\ToolbarHelper::title(\Joomla\CMS\Language\Text::_( 'FLEXI_EDIT_USER'), 'user')
			: \Joomla\CMS\Toolbar\ToolbarHelper::title(\Joomla\CMS\Language\Text::_( 'FLEXI_ADD_USER'), 'user');

		$btn_name = 'apply';
		$btn_task = $ctrl.'.apply';
		$btn_title = !$isnew ? 'FLEXI_APPLY_N_RELOAD' : 'FLEXI_ADD';

		//\Joomla\CMS\Toolbar\ToolbarHelper::apply($btn_task, $btn_title, false);

		/*$btn_arr[$btn_name] = */ flexicontent_html::addToolBarButton(
			$btn_title, $btn_name, $full_js="Joomla.submitbutton('".$btn_task."')", $msg_alert='', $msg_confirm='',
			$btn_task, $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
			$btn_class=(FLEXI_J40GE ? ' _DDI_class_ btn-success ' : '') . ' ' . $this->tooltip_class, $btn_icon="icon-save",
			'data-placement="right" title=""', $auto_add = 1);

		$btn_name = 'save';
		$btn_task = $ctrl.'.save';

		//\Joomla\CMS\Toolbar\ToolbarHelper::save($btn_task);  //\Joomla\CMS\Toolbar\ToolbarHelper::custom( $btn_task, 'save.png', 'save.png', 'JSAVE', false );

		/*$btn_arr[$btn_name] = */ flexicontent_html::addToolBarButton(
			'JSAVE', $btn_name, $full_js="Joomla.submitbutton('".$ctrl.".save')", $msg_alert='', $msg_confirm='',
			$btn_task, $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
			$btn_class=(FLEXI_J40GE ? ' _DDI_class_ btn-success ' : '') . ' ' . $this->tooltip_class, $btn_icon="icon-save",
			'data-placement="bottom" title=""', $auto_add = 1);

		$btn_name = 'save2new';
		$btn_task = $ctrl.'.save2new';

		//\Joomla\CMS\Toolbar\ToolbarHelper::save2new($btn_task);  //\Joomla\CMS\Toolbar\ToolbarHelper::custom( $btn_task, 'savenew.png', 'savenew.png', 'FLEXI_SAVE_AND_NEW', false );

		/*$btn_arr[$btn_name] = */ flexicontent_html::addToolBarButton(
			'FLEXI_SAVE_AND_NEW', $btn_name, $full_js="Joomla.submitbutton('".$ctrl.".save2new')", $msg_alert='', $msg_confirm='',
			$btn_task, $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
			$btn_class= (FLEXI_J40GE ? ' _DDI_class_ btn-success ' : '') . ' ' . $this->tooltip_class, $btn_icon="icon-save-new",
			'data-placement="right" title="'.\Joomla\CMS\Language\Text::_('FLEXI_SAVE_AND_NEW_INFO', true).'"', $auto_add = 1);

		\Joomla\CMS\Toolbar\ToolbarHelper::cancel( $ctrl.'cancel' );
		\Joomla\CMS\Toolbar\ToolbarHelper::help( 'screen.users.edit' );

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
				window.location.reload(false);
				document.body.innerHTML = "<div>" + Joomla.JText._("FLEXI_UPDATING_CONTENTS") + \' <img id="page_loading_img" src="components/com_flexicontent/assets/images/ajax-loader.gif"></div>\';
			}
		');

		$modal_title = \Joomla\CMS\Language\Text::_('FLEXI_EDIT_JUSER', true);
		$tip_class = ' hasTooltip';
		\Joomla\CMS\Toolbar\ToolbarHelper::divider();
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
			$config = \Joomla\CMS\Component\ComponentHelper::getParams('com_users');
		}



		// ********************
		// Initialise variables
		// ********************

		$cparams = \Joomla\CMS\Component\ComponentHelper::getParams('com_flexicontent');


		// *************************************************************************************************
		// Get author extended data, basic (described in author.xml) and category (described incategory.xml)
		// *************************************************************************************************

		\Joomla\CMS\Table\Table::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
		$flexiauthor_extdata = \Joomla\CMS\Table\Table::getInstance('flexicontent_authors_ext', '');
		$flexiauthor_extdata->load($row->get('id'));
		//echo "<pre>"; print_r($flexiauthor_extdata); echo "</pre>"; exit;


		// ***********************
		// AUTHOR basic parameters
		// ***********************

		// Load the DB parameter values and the XML description from file,
		// NOTE: this is one step for J1.5 via a JParameter object, but in J1.6+ the use of XML file
		// in JParameter is deprecated, instead we will \Joomla\CMS\Form\Form to load XML description and thus be able to render it

		$auth_xml = JPATH_COMPONENT.DS.'models'.DS.'forms'.DS.'author.xml';
		$params_authorbasic = new \Joomla\Registry\Registry($flexiauthor_extdata->author_basicparams);
		//echo "<pre>"; print_r($params_authorbasic); echo "</pre>"; exit;

		// Read XML file
		//$xml_string = str_replace('name="params"', 'name="authorbasicparams"', file_get_contents($auth_xml));
		$xml_string = file_get_contents($auth_xml);

		// Load the form description from the XML string
		$jform_authorbasic = new \Joomla\CMS\Form\Form('com_flexicontent.author', array('control' => 'jform', 'load_data' => true));
		$jform_authorbasic->load($xml_string, $isFile=false);

		// Set DB parameter values into the \Joomla\CMS\Form\Form object
		foreach ($jform_authorbasic->getFieldset() as $fsetname => $field)
		{
			$jform_authorbasic->setValue($field->fieldname, $group = 'authorbasicparams', $value = $params_authorbasic->get($field->fieldname) );
		}


		// **************************
		// AUTHOR category parameters
		// **************************

		// Load the DB parameter values and the XML description from file,
		// NOTE: this is one step for J1.5 via a JParameter object, but in J1.6+ the use of XML file
		// in JParameter is deprecated, instead we will \Joomla\CMS\Form\Form to load XML description and thus be able to render it

		$cat_xml = JPATH_COMPONENT.DS.'models'.DS.'forms'.DS.'category.xml';
		$params_authorcat = new \Joomla\Registry\Registry($flexiauthor_extdata->author_catparams);
		//echo "<pre>"; print_r($params_authorcat); echo "</pre>"; exit;

		// Read XML file
		$xml_string = str_replace('name="params"', 'name="authorcatparams"', file_get_contents($cat_xml));

		// Load the form description from the XML string
		$jform_authorcat = new \Joomla\CMS\Form\Form('com_flexicontent.category', array('control' => 'jform', 'load_data' => true));
		$jform_authorcat->load($xml_string, $isFile=false);

		// Set DB parameter values into the \Joomla\CMS\Form\Form object
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

		// Create \Joomla\CMS\Form\Form for the layout and apply Layout parameters values into the fields
		foreach ($tmpls as $tmpl)
		{
			if ($tmpl->name != $_clayout) continue;

			$jform = new \Joomla\CMS\Form\Form('com_flexicontent.template.category', array('control' => 'jform', 'load_data' => false));
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
			$new_usertype = \Joomla\CMS\Component\ComponentHelper::getParams('com_users')->get('new_usertype');
			$usergroups = $new_usertype ? array($new_usertype) : array();
		}
		else
		{
			$ugrps_qtmpl = 'SELECT group_id FROM #__user_usergroup_map AS ug WHERE ug.user_id = %d';
			$query = sprintf($ugrps_qtmpl, (int) $row->get('id'));

			$usergroups = $db->setQuery($query)->loadColumn();
		}

		// build the html select list
		$lists['block'] 	= \Joomla\CMS\HTML\HTMLHelper::_('select.booleanlist',  'block', 'class="inputbox" size="1"', $row->get('block') );

		// build the html select list
		$lists['sendEmail'] = \Joomla\CMS\HTML\HTMLHelper::_('select.booleanlist',  'sendEmail', 'class="inputbox" size="1"', $row->get('sendEmail') );


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
		 * NOTE: We do NOT yet use \Joomla\CMS\Form\Form thus this is needed
		 */

		\Joomla\CMS\Filter\OutputFilter::objectHTMLSafe( $row, ENT_QUOTES, $exclude_keys = '' );


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
