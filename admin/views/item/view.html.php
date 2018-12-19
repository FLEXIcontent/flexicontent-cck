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
 * HTML View class for the Item Screen
 */
class FlexicontentViewItem extends FlexicontentViewBaseRecord
{
	var $proxy_option = null;

	/**
	 * Creates the item page
	 *
	 * @since 1.0
	 */
	public function display($tpl = null)
	{
		if (1)
		{
			$this->_displayForm($tpl);
			return;
		}
	}



	/**
	 * Creates the item create / edit form
	 *
	 * @since 1.0
	 */
	function _displayForm($tpl)
	{
		/**
		 * Initialize variables, flags, etc
		 */

		global $globalcats;

		$app        = JFactory::getApplication();
		$jinput     = $app->input;
		$dispatcher = JEventDispatcher::getInstance();
		$document   = JFactory::getDocument();
		$config     = JFactory::getConfig();
		$session    = JFactory::getSession();
		$user       = JFactory::getUser();
		$db         = JFactory::getDbo();
		$uri        = JUri::getInstance();
		$cparams    = JComponentHelper::getParams('com_flexicontent');

		// Get url vars and some constants
		$option     = $jinput->get('option', '', 'cmd');
		$nullDate   = $db->getNullDate();
		$useAssocs  = flexicontent_db::useAssociations();

		if ($app->isSite())
		{
			$menu = $app->getMenu()->getActive();
		}

		// Get the COMPONENT only parameter, since we do not have item parameters yet, but we need to do some work before creating the item
		$page_params  = new JRegistry();
		$page_params->merge($cparams);

		// Runtime stats
		$print_logging_info = $page_params->get('print_logging_info');
		if ( $print_logging_info )  global $fc_run_times;


		// ***
		// *** Get session data before record form is created, because during form creation the session data are loaded and then they are cleared
		// ***

		$session_data = $app->getUserState('com_flexicontent.edit.item.data');


		// ***
		// *** Get item data and create item form (that loads item data)
		// ***

		if ( $print_logging_info )  $start_microtime = microtime(true);

		// Get model and indicate to model that current view IS item form
		$model = $this->getModel();
		$model->isForm = true;


		// FORCE model to load versioned data (URL specified version or latest version (last saved))
		$version = $jinput->get('version', 0, 'int');   // Load specific item version (non-zero), 0 version: is unversioned data, -1 version: is latest version (=default for edit form)

		// Get the item, loading item data and doing parameters merging
		$item = $model->getItem(null, $check_view_access=false, $no_cache=false, $force_version=($version!=0 ? $version : -1));  // -1 version means latest

		if ( $print_logging_info ) $fc_run_times['get_item_data'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;


		// ***
		// *** Frontend form: replace component/menu 'params' with the merged component/category/type/item/menu ETC ... parameters
		// ***

		if ($app->isSite())
		{
			$page_params = $item->parameters;
		}


		// ***
		// *** Get (CORE & CUSTOM) fields and their VERSIONED values and then
		// ***

		if ( $print_logging_info )  $start_microtime = microtime(true);

		$fields = $this->get( 'Extrafields' );
		$item->fields = & $fields;

		if ( $print_logging_info ) $fc_run_times['get_field_vals'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;

		// Load permissions (used by form template)
		$perms = $this->_getItemPerms();

		// Most core field are created via calling methods of the form (J2.5)
		$form = $this->get('Form');
		if (!$form)
		{
			$app->enqueueMessage($model->getError(), 'warning');
			$returnURL = isset($_SERVER['HTTP_REFERER']) && flexicontent_html::is_safe_url($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : JUri::base();
			$app->redirect( $returnURL );
		}

		$cid = $model->getId();
		$isnew = ! $cid;
		$manager_view = $ctrl = 'items';





		// ***
		// *** Get Associated Translations
		// ***

		if ($useAssocs)  $langAssocs = $this->get( 'LangAssocs' );
		$langs = FLEXIUtilities::getLanguages('code');


		// Create and set a unique item id for plugins that needed it
		if ($cid) {
			$unique_tmp_itemid = $cid;
		} else {
			$unique_tmp_itemid = $app->getUserState($form->option.'.edit.item.unique_tmp_itemid');
			$unique_tmp_itemid = $unique_tmp_itemid ? $unique_tmp_itemid : date('_Y_m_d_h_i_s_', time()) . uniqid(true);
		}
		//print_r($unique_tmp_itemid);
		$jinput->set('unique_tmp_itemid', $unique_tmp_itemid);

		// Get number of subscribers
		$subscribers = $model->getSubscribersCount();



		// ***
		// *** Version Panel data
		// ***

		// Get / calculate some version related variables
		$versioncount    = $model->getVersionCount();
		$versionsperpage = $page_params->get('versionsperpage', 10);
		$pagecount = (int) ceil( $versioncount / $versionsperpage );

		// Data need by version panel: (a) current version page, (b) currently active version
		$current_page = 1;  $k=1;
		$allversions  = $model->getVersionList();
		foreach($allversions as $v)
		{
			if ( $k > 1 && (($k-1) % $versionsperpage) == 0 )
				$current_page++;
			if ( $v->nr == $item->version ) break;
			$k++;
		}

		// Finally fetch the version data for versions in current page
		$versions = $model->getVersionList( ($current_page-1)*$versionsperpage, $versionsperpage );

		// Create display of average rating
		$ratings = $model->getRatingDisplay();



		// ***
		// *** Type related data
		// ***

		// Get available types and the currently selected/requested type
		$types         = $model->getTypeslist();
		$typesselected = $model->getItemType();

		// Get and merge type parameters
		$tparams = $model->getTypeparams();
		$tparams = new JRegistry($tparams);
		$page_params->merge($tparams);       // Apply type configuration if it type is set


		// ***
		// *** Load JS/CSS files
		// ***

		// Add css to document
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', FLEXI_VHASH);
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x_rtl.css', FLEXI_VHASH);

		// Fields common CSS
		$document->addStyleSheetVersion(JUri::root(true).'/components/com_flexicontent/assets/css/flexi_form_fields.css', FLEXI_VHASH);

		// Add JS frameworks
		flexicontent_html::loadFramework('jQuery');
		flexicontent_html::loadFramework('select2');
		flexicontent_html::loadFramework('touch-punch');
		flexicontent_html::loadFramework('prettyCheckable');
		flexicontent_html::loadFramework('flexi-lib');
		flexicontent_html::loadFramework('flexi-lib-form');

		// Load custom behaviours: form validation, popup tooltips
		JHtml::_('behavior.formvalidation');  // load default validation JS to make sure it is overriden
		JHtml::_('bootstrap.tooltip');

		// Add js function to overload the joomla submitform validation
		$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/admin.js', FLEXI_VHASH);
		$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/validate.js', FLEXI_VHASH);

		// Add js function for custom code used by FLEXIcontent item form
		$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/itemscreen.js', FLEXI_VHASH);


		// ***
		// *** Add frontend CSS override files to the document (also load CSS joomla template override)
		// ***

		if ( $app->isSite() )
		{
			$document->addStyleSheetVersion($this->baseurl.'/components/com_flexicontent/assets/css/flexicontent.css', FLEXI_VHASH);
			if (file_exists(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'css'.DS.'flexicontent.css'))
			{
				$document->addStyleSheetVersion($this->baseurl.'/templates/'.$app->getTemplate().'/css/flexicontent.css', FLEXI_VHASH);
			}
		}



		/**
		 * Create toolbar and toolbar title
		 */

		// SET toolbar title
		$cid
			? JToolbarHelper::title( JText::_( 'FLEXI_EDIT_ITEM' ), 'itemedit' )   // Editing existing item
			: JToolbarHelper::title( JText::_( 'FLEXI_NEW_ITEM' ), 'itemadd' );    // Creating new item



		// Create the toolbar
		$this->setToolbar($item, $model, $page_params);



		// ***
		// *** Load field values from session (typically during a form reload after a server-side form validation failure)
		// *** NOTE: Because of fieldgroup rendering other fields, this step must be done in seperate loop, placed before FIELD HTML creation
		// ***

		$jcustom = $app->getUserState($form->option.'.edit.item.custom');
		foreach ($fields as $field)
		{
			if (!$field->iscore)
			{
				if ( isset($jcustom[$field->name]) )
				{
					$field->value = array();
					foreach ($jcustom[$field->name] as $i => $_val)  $field->value[$i] = $_val;
				}
			}
		}


		// ***
		// *** (a) Apply Content Type Customization to CORE fields (label, description, etc)
		// *** (b) Create the edit html of the CUSTOM fields by triggering 'onDisplayField'
		// ***

		if ( $print_logging_info )  $start_microtime = microtime(true);
		foreach ($fields as $field)
		{
			FlexicontentFields::getFieldFormDisplay($field, $item, $user);
		}
		if ( $print_logging_info ) $fc_run_times['render_field_html'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;



		// ***
		// *** Get tags used by the item and quick selection tags
		// ***

		$usedtagsIds  = $this->get( 'UsedtagsIds' );  // NOTE: This will normally return the already set versioned value of tags ($item->tags)
		$usedtagsdata = $model->getTagsByIds($usedtagsIds, $_indexed = false);

		$quicktagsIds = $page_params->get('quick_tags', array());
		$quicktagsdata = !empty($quicktagsIds) ? $model->getTagsByIds($quicktagsIds, $_indexed = true) : array();


		// Get the edit lists
		$lists = $this->_buildEditLists($perms, $page_params, $session_data);

		// Label for current item state: published, unpublished, archived etc
		switch ($item->state) {
			case 0:
			$published = JText::_( 'FLEXI_UNPUBLISHED' );
			break;
			case 1:
			$published = JText::_( 'FLEXI_PUBLISHED' );
			break;
			case -1:
			$published = JText::_( 'FLEXI_ARCHIVED' );
			break;
			case -3:
			$published = JText::_( 'FLEXI_PENDING' );
			break;
			case -5:
			$published = JText::_( 'FLEXI_IN_PROGRESS' );
			break;
			case -4:
			default:
			$published = JText::_( 'FLEXI_TO_WRITE' );
			break;
		}



		// ***
		// *** SET INTO THE FORM, parameter values for various parameter groups
		// ***

		if ( JHtml::_('date', $item->publish_down , 'Y') <= 1969 || $item->publish_down == $nullDate || empty($item->publish_down) )
		{
			$item->publish_down = '';//JText::_( 'FLEXI_NEVER' );
			$form->setValue('publish_down', null, ''/*JText::_( 'FLEXI_NEVER' )*/);  // Setting to text will break form date element
		}


		// ***
		// *** Handle Template related work
		// ***

		// (a) Get Content Type allowed templates
		$allowed_tmpls = $tparams->get('allowed_ilayouts');
		$type_default_layout = $tparams->get('ilayout', 'default');

		// (b) Load language file of currently selected template
		$_ilayout = $item->itemparams->get('ilayout', $type_default_layout);
		if ($_ilayout) FLEXIUtilities::loadTemplateLanguageFile( $_ilayout );

		// (c) Get the item layouts, checking template of current layout for modifications
		$themes			= flexicontent_tmpl::getTemplates($_ilayout);
		$tmpls_all	= $themes->items;

		// (d) Get allowed layouts adding default layout (unless all templates are already allowed ... array is empty)
		if ( empty($allowed_tmpls) )
		{
			$allowed_tmpls = array();
		}
		if ( ! is_array($allowed_tmpls) )
		{
			$allowed_tmpls = explode("|", $allowed_tmpls);
		}
		if ( count ($allowed_tmpls) && !in_array( $type_default_layout, $allowed_tmpls ) )
		{
			$allowed_tmpls[] = $type_default_layout;
		}

		// (e) Create array of template data according to the allowed templates for current content type
		if ( count($allowed_tmpls) )
		{
			foreach ($tmpls_all as $tmpl)
			{
				if (in_array($tmpl->name, $allowed_tmpls) )
				{
					$tmpls[]= $tmpl;
				}
			}
		}
		else
		{
			$tmpls = $tmpls_all;
		}

		// (f) Create JForm for the layout and apply Layout parameters values into the fields
		foreach ($tmpls as $tmpl)
		{
			if ($tmpl->name != $_ilayout) continue;

			$jform = new JForm('com_flexicontent.template.item', array('control' => 'jform', 'load_data' => false));
			$jform->load($tmpl->params);
			$tmpl->params = $jform;
			foreach ($tmpl->params->getGroup('attribs') as $field)
			{
				$fieldname = $field->fieldname;
				$value = $item->itemparams->get($fieldname);
				if (strlen($value)) $tmpl->params->setValue($fieldname, 'attribs', $value);
			}
		}


		// Force com_content buttons on editor for description field
		/*
		$jinput->set('option', 'com_content');
		$jinput->set('view', 'article');
		$form->getField('description');
		$jinput->set('option', 'com_flexicontent');
		$jinput->set('view', 'item');
		*/


		// ***
		// *** Assign data to VIEW's template
		// ***

		$this->item   = $item;
		$this->form   = $form;  // most core field are created via calling JForm methods

		if ($useAssocs)  $this->lang_assocs = $langAssocs;
		$this->langs   = $langs;
		$this->params  = $page_params;
		$this->iparams = $model->getComponentTypeParams();
		$this->lists   = $lists;
		$this->typesselected = $typesselected;

		$this->published     = $published;
		$this->subscribers   = $subscribers;
		$this->usedtagsdata  = $usedtagsdata;
		$this->quicktagsdata = $quicktagsdata;

		$this->fields     = $fields;
		$this->versions   = $versions;
		$this->ratings    = $ratings;
		$this->pagecount  = $pagecount;
		$this->tparams    = $tparams;
		$this->tmpls      = $tmpls;
		$this->perms      = $perms;
		$this->document   = $document;
		$this->nullDate   = $nullDate;
		$this->current_page = $current_page;



		// ***
		// *** Clear custom form data from session
		// ***

		$app->setUserState($form->option.'.edit.item.custom', false);
		$app->setUserState($form->option.'.edit.item.jfdata', false);
		$app->setUserState($form->option.'.edit.item.unique_tmp_itemid', false);

		if ( $print_logging_info ) $start_microtime = microtime(true);
		parent::display($tpl);
		if ( $print_logging_info ) $fc_run_times['form_rendering'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
	}



	/**
	 * Creates the HTML of various form fields used in the item edit form
	 *
	 * @since 1.0
	 */
	function _buildEditLists(&$perms, &$page_params, &$session_data)
	{
		$app      = JFactory::getApplication();
		$jinput   = $app->input;
		$db       = JFactory::getDbo();
		$user     = JFactory::getUser();	// get current user
		$model    = $this->getModel();
		$item     = $model->getItem(null, $check_view_access=false, $no_cache=false, $force_version=0);  // ZERO force_version means unversioned data
		$document = JFactory::getDocument();
		$session  = JFactory::getSession();
		$option   = $jinput->get('option', '', 'cmd');

		global $globalcats;

		$categories    = $globalcats;
		$types         = $model->getTypeslist();
		$typesselected = $model->getItemType();
		$subscribers   = $model->getSubscribersCount();

		$isnew = !$item->id;


		// ***
		// *** Get categories used by the item
		// ***

		if ($isnew)
		{
			// Case for preselected main category for new items
			$maincat = $item->catid
				? $item->catid
				: $jinput->get('maincat', 0, 'int');

			// For backend form also try the items manager 's category filter
			if ( $app->isAdmin() && !$maincat )
			{
				$maincat = $app->getUserStateFromRequest( $option.'.items.filter_cats', 'filter_cats', '', 'int' );
			}
			if ($maincat)
			{
				$item->categories = array($maincat);
				$item->catid = $maincat;
			}
			else
			{
				$item->categories = array();
			}

			if ( $page_params->get('cid_default') )
			{
				$item->categories = $page_params->get('cid_default');
			}
			if ( $page_params->get('catid_default') )
			{
				$item->catid = $page_params->get('catid_default');
			}

			$item->cats = $item->categories;
		}

		// Main category and secondary categories from session
		$form_catid = !empty($session_data['catid'])
			? (int) $session_data['catid']
			: $item->catid;

		$form_cid = !empty($session_data['cid'])
			? $session_data['cid']
			: $item->categories;
		$form_cid = ArrayHelper::toInteger($form_cid);


		// ***
		// *** Build select lists for the form field. Only few of them are used in J1.6+, since we will use:
		// ***  (a) form XML file to declare them and then (b) getInput() method form field to create them
		// ***

		// Encode (UTF-8 charset) HTML entities form data so that they can be set as form field values
		// we do this after creating the description field which is used un-encoded inside 'textarea' tags
		JFilterOutput::objectHTMLSafe( $item, ENT_QUOTES, $exclude_keys = '' );  // Maybe exclude description text ?

		$lists = array();
		$prettycheckable_added = flexicontent_html::loadFramework('prettyCheckable');  // Get if prettyCheckable was loaded

		// build state list
		$non_publishers_stategrp    = $perms['canconfig'] || $item->state==-3 || $item->state==-4 ;
		$special_privelege_stategrp = ($item->state==2 || $perms['canarchive']) || ($item->state==-2 || $perms['candelete']) ;

		$state = array();


		// States for publishers
		$ops = array(
			array('value' =>  1, 'text' => JText::_('FLEXI_PUBLISHED')),
			array('value' =>  0, 'text' => JText::_('FLEXI_UNPUBLISHED')),
			array('value' => -5, 'text' => JText::_('FLEXI_IN_PROGRESS'))
		);
		if ($non_publishers_stategrp || $special_privelege_stategrp)
		{
			$grp = 'publishers_workflow_states';
			$state[$grp] = array();
			$state[$grp]['id'] = 'publishers_workflow_states';
			$state[$grp]['text'] = JText::_('FLEXI_PUBLISHERS_WORKFLOW_STATES');
			$state[$grp]['items'] = $ops;
		}
		else
		{
			$state[]['items'] = $ops;
		}


		// States reserved for workflow
		$ops = array();
		if ($item->state==-3 || $perms['canconfig'])  $ops[] = array('value' => -3, 'text' => JText::_('FLEXI_PENDING'));
		if ($item->state==-4 || $perms['canconfig'])  $ops[] = array('value' => -4, 'text' => JText::_('FLEXI_TO_WRITE'));

		if ( $ops )
		{
			if ($non_publishers_stategrp)
			{
				$grp = 'non_publishers_workflow_states';
				$state[$grp] = array();
				$state[$grp]['id'] = 'non_publishers_workflow_states';
				$state[$grp]['text'] = JText::_('FLEXI_NON_PUBLISHERS_WORKFLOW_STATES');
				$state[$grp]['items'] = $ops;
			}
			else
			{
				$state[]['items'] = $ops;
			}
		}


		// Special access states
		$ops = array();
		if ($item->state==2  || $perms['canarchive']) $ops[] = array('value' =>  2, 'text' => JText::_('FLEXI_ARCHIVED'));
		if ($item->state==-2 || $perms['candelete'])  $ops[] = array('value' => -2, 'text' => JText::_('FLEXI_TRASHED'));

		if ( $ops )
		{
			if ( $special_privelege_stategrp )
			{
				$grp = 'special_action_states';
				$state[$grp] = array();
				$state[$grp]['id'] = 'special_action_states';
				$state[$grp]['text'] = JText::_('FLEXI_SPECIAL_ACTION_STATES');
				$state[$grp]['items'] = $ops;
			}
			else
			{
				$state[]['items'] = $ops;
			}
		}


		$fieldname = 'jform[state]';
		$elementid = 'jform_state';
		$class = 'use_select2_lib';
		$attribs = 'class="'.$class.'"';
		$lists['state'] = JHtml::_('select.groupedlist', $state, $fieldname,
			array(
				'id' => $elementid,
				'group.id' => 'id',
				'list.attr' => $attribs,
				'list.select' => $item->state,
			)
		);


		// ***
		// *** Build featured flag
		// ***

		if ( $app->isAdmin() )
		{
			$fieldname = 'jform[featured]';
			$elementid = 'jform_featured';
			/*
			$options = array();
			$options[] = JHtml::_('select.option',  0, JText::_( 'FLEXI_NO' ) );
			$options[] = JHtml::_('select.option',  1, JText::_( 'FLEXI_YES' ) );
			$attribs = '';
			$lists['featured'] = JHtml::_('select.radiolist', $options, $fieldname, $attribs, 'value', 'text', $item->featured, $elementid);
			*/
			$classes = !$prettycheckable_added ? '' : ' use_prettycheckable ';
			$attribs = ' class="'.$classes.'" ';
			$i = 1;
			$options = array(0=>JText::_( 'FLEXI_NO' ), 1=>JText::_( 'FLEXI_YES' ) );
			$lists['featured'] = '';
			foreach ($options as $option_id => $option_label)
			{
				$checked = $option_id==$item->featured ? ' checked="checked"' : '';
				$elementid_no = $elementid.'_'.$i;
				if (!$prettycheckable_added) $lists['featured'] .= '<label class="fccheckradio_lbl" for="'.$elementid_no.'">';
				$extra_params = !$prettycheckable_added ? '' : ' data-labeltext="'.JText::_($option_label).'" data-labelPosition="right" data-customClass="fcradiocheck"';
				$lists['featured'] .= ' <input type="radio" id="'.$elementid_no.'" data-element-grpid="'.$elementid
					.'" name="'.$fieldname.'" '.$attribs.' value="'.$option_id.'" '.$checked.$extra_params.' />';
				if (!$prettycheckable_added) $lists['featured'] .= '&nbsp;'.JText::_($option_label).'</label>';
				$i++;
			}
		}

		// build version approval list
		$fieldname = 'jform[vstate]';
		$elementid = 'jform_vstate';
		/*
		$options = array();
		$options[] = JHtml::_('select.option',  1, JText::_( 'FLEXI_NO' ) );
		$options[] = JHtml::_('select.option',  2, JText::_( 'FLEXI_YES' ) );
		$attribs = FLEXI_J16GE ? ' style ="float:left!important;" '  :  '';   // this is not right for J1.5' style ="float:left!important;" ';
		$lists['vstate'] = JHtml::_('select.radiolist', $options, $fieldname, $attribs, 'value', 'text', 2, $elementid);
		*/
		$classes = !$prettycheckable_added ? '' : ' use_prettycheckable ';
		$attribs = ' class="'.$classes.'" ';
		$i = 1;
		$options = array(1=>JText::_( 'FLEXI_NO' ), 2=>JText::_( 'FLEXI_YES' ) );
		$lists['vstate'] = '';
		foreach ($options as $option_id => $option_label) {
			$checked = $option_id==2 ? ' checked="checked"' : '';
			$elementid_no = $elementid.'_'.$i;
			if (!$prettycheckable_added) $lists['vstate'] .= '<label class="fccheckradio_lbl" for="'.$elementid_no.'">';
			$extra_params = !$prettycheckable_added ? '' : ' data-labeltext="'.JText::_($option_label).'" data-labelPosition="right" data-customClass="fcradiocheck"';
			$lists['vstate'] .= ' <input type="radio" id="'.$elementid_no.'" data-element-grpid="'.$elementid
				.'" name="'.$fieldname.'" '.$attribs.' value="'.$option_id.'" '.$checked.$extra_params.' />';
			if (!$prettycheckable_added) $lists['vstate'] .= '&nbsp;'.JText::_($option_label).'</label>';
			$i++;
		}


		// check access level exists
		$level_name = flexicontent_html::userlevel(null, $item->access, null, null, null, $_createlist = false);
		if (empty($level_name))
		{
			JFactory::getApplication()->enqueueMessage(JText::sprintf('FLEXI_ABOUT_INVALID_ACCESS_LEVEL_PLEASE_SAVE_NEW', $item->access, 'Public'), 'warning');
			$document->addScriptDeclaration("jQuery(document).ready(function() { jQuery('#jform_access').val(1).trigger('change'); });");
		}


		// build field for notifying subscribers
		if ( !$subscribers )
		{
			$lists['notify'] = !$isnew ? '<div class="alert alert-info fc-small fc-iblock">'.JText::_('FLEXI_NO_SUBSCRIBERS_EXIST').'</div>' : '';
		}
		else
		{
			// b. Check if notification emails to subscribers , were already sent during current session
			$subscribers_notified = $session->get('subscribers_notified', array(),'flexicontent');
			if ( !empty($subscribers_notified[$item->id]) )
			{
				$lists['notify'] = '<div class="alert alert-info fc-small fc-iblock">'.JText::_('FLEXI_SUBSCRIBERS_ALREADY_NOTIFIED').'</div>';
			}
			else
			{
				// build favs notify field
				$fieldname = 'jform[notify]';
				$elementid = 'jform_notify';
				/*
				$attribs = '';
				$lists['notify'] = '<input type="checkbox" name="jform[notify]" id="jform_notify" '.$attribs.' /> '. $lbltxt;
				*/
				$classes = !$prettycheckable_added ? '' : ' use_prettycheckable ';
				$attribs = ' class="'.$classes.'" ';
				$lbltxt = $subscribers .' '. JText::_( $subscribers>1 ? 'FLEXI_SUBSCRIBERS' : 'FLEXI_SUBSCRIBER' );
				if (!$prettycheckable_added) $lists['notify'] .= '<label class="fccheckradio_lbl" for="'.$elementid.'">';
				$extra_params = !$prettycheckable_added ? '' : ' data-labeltext="'.$lbltxt.'" data-labelPosition="right" data-customClass="fcradiocheck"';
				$lists['notify'] = ' <input type="checkbox" id="'.$elementid.'" data-element-grpid="'.$elementid
					.'" name="'.$fieldname.'" '.$attribs.' value="1" '.$extra_params.' checked="checked" />';
				if (!$prettycheckable_added) $lists['notify'] .= '&nbsp;'.$lbltxt.'</label>';
			}
		}

		// Retrieve author configuration
		$authorparams = flexicontent_db::getUserConfig($user->id);

		// Get author's maximum allowed categories per item and set js limitation
		$max_cat_assign = !$authorparams ? 0 : intval($authorparams->get('max_cat_assign',0));
		$document->addScriptDeclaration('
			max_cat_assign_fc = '.$max_cat_assign.';
			existing_cats_fc  = ["'.implode('","', $form_cid).'"];
		');
		JText::script('FLEXI_TOO_MANY_ITEM_CATEGORIES',true);


		// Creating categorories tree for item assignment, we use the 'create' privelege
		$actions_allowed = array('core.create');

		// Featured categories form field
		$featured_cats_parent = $page_params->get('featured_cats_parent', 0);
		$featured_cats = array();
		$enable_featured_cid_selector = $perms['multicat'] && $perms['canchange_featcat'];
		if ( $featured_cats_parent )
		{
			$featured_tree = flexicontent_cats::getCategoriesTree($published_only=1, $parent_id=$featured_cats_parent, $depth_limit=0);
			$disabled_cats = $page_params->get('featured_cats_parent_disable', 1) ? array($featured_cats_parent) : array();

			$featured_sel = array();
			foreach($form_cid as $item_cat)
			{
				if (isset($featured_tree[$item_cat])) $featured_sel[] = $item_cat;
			}

			$class  = "use_select2_lib";
			$attribs  = 'class="'.$class.'" multiple="multiple" size="8"';
			$attribs .= $enable_featured_cid_selector ? '' : ' disabled="disabled"';
			$fieldname = 'jform[featured_cid][]';

			// Skip main category from the selected cats to allow easy change of it
			$featured_sel_nomain = array();
			foreach($featured_sel as $cat_id)
			{
				if ($cat_id != $form_catid)
				{
					$featured_sel_nomain[] = $cat_id;
				}
			}

			$lists['featured_cid'] = ($enable_featured_cid_selector ? '' : '<label class="label" style="float:none; margin:0 6px 0 0 !important;">locked</label>').
				flexicontent_cats::buildcatselect($featured_tree, $fieldname, $featured_sel_nomain, 3, $attribs, true, ($item->id ? 'edit' : 'create'),	$actions_allowed,
					$require_all=true, $skip_subtrees=array(), $disable_subtrees=array(), $custom_options=array(), $disabled_cats
				);
		}
		else{
			// Do not display, if not configured or not allowed to the user
			$lists['featured_cid'] = false;
		}


		// Multi-category form field, for user allowed to use multiple categories
		$lists['cid'] = '';
		$enable_cid_selector = $perms['multicat'] && $perms['canchange_seccat'];
		if ( 1 )
		{
			if ($page_params->get('cid_allowed_parent'))
			{
				$cid_tree = flexicontent_cats::getCategoriesTree($published_only=1, $parent_id=$page_params->get('cid_allowed_parent'), $depth_limit=0);
				$disabled_cats = $page_params->get('cid_allowed_parent_disable', 1) ? array($page_params->get('cid_allowed_parent')) : array();
			}
			else
			{
				$cid_tree = & $categories;
				$disabled_cats = array();
			}

			// Get author's maximum allowed categories per item and set js limitation
			$max_cat_assign = !$authorparams ? 0 : intval($authorparams->get('max_cat_assign',0));
			$document->addScriptDeclaration('
				max_cat_assign_fc = '.$max_cat_assign.';
				existing_cats_fc  = ["'.implode('","', $form_cid).'"];
			');

			$class  = "mcat use_select2_lib";
			$class .= $max_cat_assign ? " validate-fccats" : " validate";

			$attribs  = 'class="'.$class.'" multiple="multiple" size="20"';
			$attribs .= $enable_cid_selector ? '' : ' disabled="disabled"';

			$fieldname = 'jform[cid][]';
			$skip_subtrees = $featured_cats_parent ? array($featured_cats_parent) : array();

			// Skip main category from the selected secondary cats to allow easy change of it
			$form_cid_nomain = array();
			foreach($form_cid as $cat_id)
			{
				if ($cat_id != $form_catid) $form_cid_nomain[] = $cat_id;
			}

			$lists['cid'] = ($enable_cid_selector ? '' : '<label class="label" style="float:none; margin:0 6px 0 0 !important;">locked</label>').
				flexicontent_cats::buildcatselect($cid_tree, $fieldname, $form_cid_nomain, false, $attribs, true, ($item->id ? 'edit' : 'create'), $actions_allowed,
					$require_all=true, $skip_subtrees, $disable_subtrees=array(), $custom_options=array(), $disabled_cats
				);
		}

		else
		{
			if ( count($form_cid) > 1 )
			{
				foreach ($form_cid as $catid)
				{
					$cat_titles[$catid] = $globalcats[$catid]->title;
				}
				$lists['cid'] .= implode(', ', $cat_titles);
			}
			else
			{
				$lists['cid'] = false;
			}
		}


		// Main category form field
		$class = 'scat use_select2_lib'
			.($perms['multicat']
				? ' validate-catid'
				: ' required'
			);
		$attribs = ' class="' . $class . '" ';
		$fieldname = 'jform[catid]';

		$enable_catid_selector = ($isnew && !$page_params->get('catid_default')) || (!$isnew && empty($item->catid)) || $perms['canchange_cat'];

		if ($page_params->get('catid_allowed_parent'))
		{
			$catid_tree = flexicontent_cats::getCategoriesTree($published_only=1, $parent_id=$page_params->get('catid_allowed_parent'), $depth_limit=0);
			$disabled_cats = $page_params->get('catid_allowed_parent_disable', 1) ? array($page_params->get('catid_allowed_parent')) : array();
		} else {
			$catid_tree = & $categories;
			$disabled_cats = array();
		}

		$lists['catid'] = false;
		if ( !empty($catid_tree) )
		{
			$disabled = $enable_catid_selector ? '' : ' disabled="disabled"';
			$attribs .= $disabled;
			$lists['catid'] = ($enable_catid_selector ? '' : '<label class="label" style="float:none; margin:0 6px 0 0 !important;">locked</label>').
				flexicontent_cats::buildcatselect($catid_tree, $fieldname, $item->catid, 2, $attribs, true, ($item->id ? 'edit' : 'create'), $actions_allowed,
					$require_all=true, $skip_subtrees=array(), $disable_subtrees=array(), $custom_options=array(), $disabled_cats,
					$empty_errmsg=JText::_('FLEXI_FORM_NO_MAIN_CAT_ALLOWED')
				);
		} else if ( !$isnew && $item->catid ) {
			$lists['catid'] = $globalcats[$item->catid]->title;
		}


		//buid types selectlist
		$class   = 'required use_select2_lib';
		$attribs = 'class="'.$class.'"';
		$fieldname = 'jform[type_id]';
		$elementid = 'jform_type_id';
		$lists['type'] = flexicontent_html::buildtypesselect($types, $fieldname, $typesselected->id, 1, $attribs, $elementid, $check_perms=true );


		// ***
		// *** Build disable comments selector
		// ***

		if ( $app->isSite() && $page_params->get('allowdisablingcomments_fe') )
		{
			// Set to zero if disabled or to "" (aka use default) for any other value.  THIS WILL FORCE comment field use default Global/Category/Content Type setting or disable it,
			// thus a per item commenting system cannot be selected. This is OK because it makes sense to have a different commenting system per CONTENT TYPE by not per Content Item
			$isdisabled = !$page_params->get('comments') && strlen($page_params->get('comments'));
			$fieldvalue = $isdisabled ? 0 : "";

			$fieldname = 'jform[attribs][comments]';
			$elementid = 'jform_attribs_comments';
			/*
			$options = array();
			$options[] = JHtml::_('select.option', "",  JText::_( 'FLEXI_DEFAULT_BEHAVIOR' ) );
			$options[] = JHtml::_('select.option', 0, JText::_( 'FLEXI_DISABLE' ) );
			$attribs = '';
			$lists['disable_comments'] = JHtml::_('select.radiolist', $options, $fieldname, $attribs, 'value', 'text', $fieldvalue, $elementid);
			*/
			$classes = !$prettycheckable_added ? '' : ' use_prettycheckable ';
			$attribs = ' class="'.$classes.'" ';
			$i = 1;
			$options = array(""=>JText::_( 'FLEXI_DEFAULT_BEHAVIOR' ), 0=>JText::_( 'FLEXI_DISABLE' ) );
			$lists['disable_comments'] = '';
			foreach ($options as $option_id => $option_label)
			{
				$checked = $option_id===$fieldvalue ? ' checked="checked"' : '';
				$elementid_no = $elementid.'_'.$i;
				if (!$prettycheckable_added) $lists['disable_comments'] .= '<label class="fccheckradio_lbl" for="'.$elementid_no.'">';
				$extra_params = !$prettycheckable_added ? '' : ' data-labeltext="'.JText::_($option_label).'" data-labelPosition="right" data-customClass="fcradiocheck"';
				$lists['disable_comments'] .= ' <input type="radio" id="'.$elementid_no.'" data-element-grpid="'.$elementid
					.'" name="'.$fieldname.'" '.$attribs.' value="'.$option_id.'" '.$checked.$extra_params.' />';
				if (!$prettycheckable_added) $lists['disable_comments'] .= '&nbsp;'.JText::_($option_label).'</label>';
				$i++;
			}
		}

		// ***
		// *** Build languages list
		// ***

		// We will not use the default getInput() JForm method, since we want to customize display of language selection according to configuration
		// probably we should create a new form element and use it in record's XML ... but maybe this is an overkill, we may do it in the future

		// Find user's allowed languages
		$allowed_langs = !$authorparams ? null : $authorparams->get('langs_allowed',null);
		$allowed_langs = !$allowed_langs ? null : FLEXIUtilities::paramToArray($allowed_langs);
		if (!$isnew && $allowed_langs) $allowed_langs[] = $item->language;

		if ( $app->isSite() )
		{
			// Find globaly or per content type disabled languages
			$disable_langs = $page_params->get('disable_languages_fe', array());

			$langdisplay = $page_params->get('langdisplay_fe', 2);
			$langconf = array();
			$langconf['flags'] = $page_params->get('langdisplay_flags_fe', 1);
			$langconf['texts'] = $page_params->get('langdisplay_texts_fe', 1);
			$field_attribs = $langdisplay==2 ? 'class="use_select2_lib"' : '';
			$lists['languages'] = flexicontent_html::buildlanguageslist( 'jform[language]', $field_attribs, $item->language, $langdisplay, $allowed_langs, $published_only=1, $disable_langs, $add_all=true, $langconf);
		}
		else
		{
			$lists['languages'] = flexicontent_html::buildlanguageslist('jform[language]', 'class="use_select2_lib"', $item->language, 2, $allowed_langs);
		}

		return $lists;
	}



	/**
	 * Calculates the user permission on the given item
	 *
	 * @since 1.0
	 */
	function _getItemPerms()
	{
		// Get view's model
		$model      = $this->getModel();

		// Return cached result
		static $perms_cache = array();

		if (isset($perms_cache[$model->get('id')]))
		{
			return $perms_cache[$model->get('id')];
		}

		// Get user, user's global permissions
		$permission = FlexicontentHelperPerm::getPerm();
		$user       = JFactory::getUser();

		$perms = array();
		$perms['isSuperAdmin'] = $permission->SuperAdmin;
		$perms['canconfig']    = $permission->CanConfig;
		$perms['multicat']     = $permission->MultiCat;
		$perms['cantags']      = $permission->CanUseTags;
		$perms['cancreatetags']= $permission->CanCreateTags;
		$perms['canparams']    = $permission->CanParams;
		$perms['cantemplates'] = $permission->CanTemplates;
		$perms['canarchive']   = $permission->CanArchives;
		$perms['canright']     = $permission->CanRights;
		$perms['canacclvl']    = $permission->CanAccLvl;
		$perms['canversion']   = $permission->CanVersion;
		$perms['editcreationdate'] = $permission->EditCreationDate;
		$perms['editcreator']  = $permission->EditCreator;
		$perms['editpublishupdown'] = $permission->EditPublishUpDown;

		// Get general edit/publish/delete permissions (we will override these for existing items)
		$perms['canedit']    = $permission->CanEdit    || $permission->CanEditOwn;
		$perms['canpublish'] = $permission->CanPublish || $permission->CanPublishOwn;
		$perms['candelete']  = $permission->CanDelete  || $permission->CanDeleteOwn;

		// Get permissions for changing item's category assignments
		$perms['canchange_cat'] = $permission->CanChangeCat;
		$perms['canchange_seccat'] = $permission->CanChangeSecCat;
		$perms['canchange_featcat'] = $permission->CanChangeFeatCat;

		// OVERRIDE global with existing item's atomic settings
		if ($model->get('id'))
		{
			// the following include the "owned" checks too
			$itemAccess = $model->getItemAccess();
			$perms['canedit']    = $itemAccess->get('access-edit');  // includes temporary editable via session's 'rendered_uneditable'
			$perms['canpublish'] = $itemAccess->get('access-edit-state');  // includes (frontend) check (and allows) if user is editing via a coupon and has 'edit.state.own'
			$perms['candelete']  = $itemAccess->get('access-delete');
		}

		// Get can change categories ACL access
		$type = $model->getItemType();
		if ($type->id)
		{
			$perms['canchange_cat']     = $user->authorise('flexicontent.change.cat', 'com_flexicontent.type.' . $type->id);
			$perms['canchange_seccat']  = $user->authorise('flexicontent.change.cat.sec', 'com_flexicontent.type.' . $type->id);
			$perms['canchange_featcat'] = $user->authorise('flexicontent.change.cat.feat', 'com_flexicontent.type.' . $type->id);
		}

		// Cache and return result
		$perms_cache[$model->get('id')] = $perms;
		return $perms;
	}


	/**
	 * Method to configure the toolbar for this view.
	 *
	 * @access	public
	 * @return	void
	 */
	function setToolbar($item, $model, $page_params)
	{
		global $globalcats;
		$categories = & $globalcats;

		$user    = JFactory::getUser();
		$toolbar = JToolbar::getInstance('toolbar');

		$perms   = $this->_getItemPerms();
		$tparams = $model->getTypeparams();
		$tparams = new JRegistry($tparams);

		$typesselected = $model->getItemType();

		$cid = $model->getId();
		$isnew = ! $cid;
		$ctrl = 'items';


		/**
		 * Apply buttons
		 */

		// Apply button
		$btn_arr = array();
		if (!$isnew || $item->version)
		{
			$btn_name = 'apply_ajax';
			$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
				'FLEXI_APPLY', $btn_name, $full_js="Joomla.submitbutton('".$ctrl.".apply_ajax')", $msg_alert='', $msg_confirm='',
				$btn_task=$ctrl.'.apply_ajax', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
				$btn_class= (FLEXI_J40GE ? ' btn-success ' : '') . ' ' . $this->tooltip_class, $btn_icon="icon-loop",
				'data-placement="bottom" title="'.JText::_('FLEXI_FAST_SAVE_INFO', true).'"', $auto_add = 0);
		}

		// Apply & Reload button   ***   (Apply Type, is a special case of new that has not loaded custom fieds yet, due to type not defined on initial form load)
		$btn_name = $item->type_id ? 'apply' : 'apply_type';
		$btn_task = $item->type_id ? $ctrl.'.apply' : $ctrl.'.apply_type';
		$btn_title = !$isnew ? 'FLEXI_APPLY_N_RELOAD' : ($typesselected->id ? 'FLEXI_ADD' : 'FLEXI_APPLY_TYPE');

		//JToolbarHelper::apply($btn_task, $btn_title, false);

		$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
			$btn_title, $btn_name, $full_js="Joomla.submitbutton('".$btn_task."')", $msg_alert='', $msg_confirm='',
			$btn_task, $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
			$btn_class= (FLEXI_J40GE ? ' btn-success ' : '') . ' ' . $this->tooltip_class, $btn_icon="icon-save",
			'data-placement="right" title=""', $auto_add = 0);

		flexicontent_html::addToolBarDropMenu(
			$btn_arr,
			'apply_btns_group',
			null,
			array('drop_class_extra' => (FLEXI_J40GE ? 'btn-success' : ''))
		);


		// ***
		// *** Save buttons
		// ***
		$btn_arr = array();
		if (!$isnew || $item->version)
		{
			//JToolbarHelper::save($ctrl.'.save');
			//JToolbarHelper::save2new($ctrl.'.save2new'); //JToolbarHelper::custom( $ctrl.'.save2new', 'savenew.png', 'savenew.png', 'FLEXI_SAVE_AND_NEW', false );
			//JToolbarHelper::save2copy($ctrl.'.save2copy'); //JToolbarHelper::custom( $ctrl.'.save2copy', 'save2copy.png', 'save2copy.png', 'FLEXI_SAVE_AS_COPY', false );

			$btn_name = 'save';
			$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
				'JSAVE', $btn_name, $full_js="Joomla.submitbutton('".$ctrl.".save')", $msg_alert='', $msg_confirm='',
				$btn_task=$ctrl.'.save', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
				$btn_class= (FLEXI_J40GE ? ' btn-success ' : '') . ' ' . $this->tooltip_class, $btn_icon="icon-save",
				'data-placement="bottom" title=""', $auto_add = 0);

			$btn_name = 'save2new';
			$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
				'FLEXI_SAVE_AND_NEW', $btn_name, $full_js="Joomla.submitbutton('".$ctrl.".save2new')", $msg_alert='', $msg_confirm='',
				$btn_task=$ctrl.'.save2new', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
				$btn_class= (FLEXI_J40GE ? ' btn-success ' : '') . ' ' . $this->tooltip_class, $btn_icon="icon-save-new",
				'data-placement="right" title="'.JText::_('FLEXI_SAVE_AND_NEW_INFO', true).'"', $auto_add = 0);

			$btn_name = 'save2copy';
			$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
				'FLEXI_SAVE_AS_COPY', $btn_name, $full_js="Joomla.submitbutton('".$ctrl.".save2copy')", $msg_alert='', $msg_confirm='',
				$btn_task=$ctrl.'.save2copy', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
				$btn_class= (FLEXI_J40GE ? ' btn-success ' : '') . ' ' . $this->tooltip_class, $btn_icon="icon-save-copy",
				'data-placement="right" title="'.JText::_('FLEXI_SAVE_AS_COPY_INFO', true).'"', $auto_add = 0);
		}
		flexicontent_html::addToolBarDropMenu(
			$btn_arr,
			'save_btns_group',
			null,
			array('drop_class_extra' => (FLEXI_J40GE ? 'btn-success' : ''))
		);

		JToolbarHelper::cancel($ctrl.'.cancel');



		/**
		 * Add a preview button(s)
		 */

		//$_sh404sef = JPluginHelper::isEnabled('system', 'sh404sef') && JFactory::getConfig()->get('sef');
		$_sh404sef = defined('SH404SEF_IS_RUNNING') && JFactory::getConfig()->get('sef');
		if ( $cid )
		{
			// Create the non-SEF URL
			$site_languages = FLEXIUtilities::getLanguages();
			$sef_lang = $item->language != '*' && isset($site_languages->{$item->language}) ? $site_languages->{$item->language}->sef : '';
			$item_url =
				// Route the record URL to an appropriate menu item
				FlexicontentHelperRoute::getItemRoute($item->id.':'.$item->alias, $categories[$item->catid]->slug, 0, $item)

				// Force language to be switched to the language of the record, thus showing the record (and not its associated translation of current FE language)
				. ($sef_lang ? '&lang=' . $sef_lang : '');

			// Build a frontend SEF url
			$item_url = flexicontent_html::getSefUrl($item_url);

			$previewlink = $item_url . (strstr($item_url, '?') ? '&amp;' : '?') .'preview=1';

			// PREVIEW for latest version
			if ( !$page_params->get('use_versioning', 1) || ($item->version == $item->current_version && $item->version == $item->last_version) )
			{
				$toolbar->appendButton( 'Custom', '<button class="preview ' . $this->btn_sm_class . ' btn-fcaction btn-info spaced-btn" onclick="window.open(\''.$previewlink.'\'); return false;"><span title="'.JText::_('FLEXI_PREVIEW').'" class="icon-screen"></span>'.JText::_('FLEXI_PREVIEW').'</button>', 'preview' );
			}

			// PREVIEW for non-approved versions of the item, if they exist
			else
			{
				$btn_arr = array();

				// Add a preview button for (currently) LOADED version of the item
				$previewlink_loaded_ver = $previewlink .'&amp;version='.$item->version;
				$btn_arr['preview_current'] = '
					<a class="toolbar ' . $this->btn_sm_class . '" href="javascript:;" onclick="window.open(\''.$previewlink_loaded_ver.'\'); return false;">
						<span title="'.JText::_('FLEXI_PREVIEW').'" class="icon-screen"></span>
						'.JText::_('FLEXI_PREVIEW' /*'FLEXI_PREVIEW_FORM_LOADED_VERSION'*/).' ['.$item->version.']
					</a>';
				//$toolbar->appendButton( 'Custom', $btn_arr['preview_current'], 'preview_current' );

				// Add a preview button for currently ACTIVE version of the item
				$previewlink_active_ver = $previewlink .'&amp;version='.$item->current_version;
				$btn_arr['preview_active'] = '
					<a class="toolbar ' . $this->btn_sm_class . '" href="javascript:;" onclick="window.open(\''.$previewlink_active_ver.'\'); return false;">
						<span title="'.JText::_('FLEXI_PREVIEW').'" class="icon-screen"></span>
						'.JText::_('FLEXI_PREVIEW_FRONTEND_ACTIVE_VERSION').' ['.$item->current_version.']
					</a>';
				//$toolbar->appendButton( 'Custom', $btn_arr['preview_active'], 'preview_active' );

				// Add a preview button for currently LATEST version of the item
				$previewlink_last_ver = $previewlink; //'&amp;version='.$item->last_version;
				$btn_arr['preview_latest'] = '
					<a class="toolbar ' . $this->btn_sm_class . '" href="javascript:;" onclick="window.open(\''.$previewlink_last_ver.'\'); return false;">
						<span title="'.JText::_('FLEXI_PREVIEW').'" class="icon-screen"></span>
						'.JText::_('FLEXI_PREVIEW_LATEST_SAVED_VERSION').' ['.$item->last_version.']
					</a>';
				//$toolbar->appendButton( 'Custom', $btn_arr['preview_latest'], 'preview_latest' );

				flexicontent_html::addToolBarDropMenu($btn_arr, 'preview_btns_group', null);
			}
		}


		$btn_arr = array();
		$btn_arr['fc_actions'] = '';

		/**
		 * Add modal layout editing
		 */

		if ($perms['cantemplates'])
		{
			$edit_layout = htmlspecialchars(JText::_('FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS'), ENT_QUOTES, 'UTF-8');
			if (!$isnew || $item->version)
			{
				$btn_name='edit_layout';
				$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
					'FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS', $btn_name, $full_js="var url = jQuery(this).attr('data-href'); fc_showDialog(url, 'fc_modal_popup_container', 0, 0, 0, 0, {title:'".$edit_layout."'}); return false;",
					$msg_alert='', $msg_confirm='',
					$btn_task='', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
					$btn_class='btn-fcaction ' . (FLEXI_J40GE ? $this->btn_iv_class : '') . ' ' . $this->tooltip_class, $btn_icon="icon-pencil",
					'data-placement="right" data-href="index.php?option=com_flexicontent&amp;view=template&amp;type=items&amp;tmpl=component&amp;ismodal=1&amp;folder=' . $item->itemparams->get('ilayout', $tparams->get('ilayout', 'default'))
						. '&amp;' . JSession::getFormToken() . '=1' .
					'" title="Edit the display layout of this item. <br/><br/>Note: this layout maybe assigned to content types or other items, thus changing it will effect them too"', $auto_add = 0
				);
			}
		}


		/**
		 * Add collaboration button
		 */

		$has_pro = JPluginHelper::isEnabled($extfolder = 'system', $extname = 'flexisyspro');
		$com_mailto_found = file_exists(JPATH_SITE.DS.'components'.DS.'com_mailto'.DS.'helpers'.DS.'mailto.php');

		if ($com_mailto_found)
		{
			require_once(JPATH_SITE.DS.'components'.DS.'com_mailto'.DS.'helpers'.DS.'mailto.php');
			$status = 'width=700,height=360,menubar=yes,resizable=yes';
			$btn_title = JText::_('FLEXI_COLLABORATE_EMAIL_ABOUT_THIS_ITEM');
			$btn_info  = flexicontent_html::encodeHTML(JText::_('FLEXI_COLLABORATE_EMAIL_ABOUT_THIS_ITEM_INFO'), 2);
			$task_url = 'index.php?option=com_flexicontent&tmpl=component'
				.'&task=call_extfunc&exttype=plugins&extfolder=system&extname=flexisyspro&extfunc=collaborate_form'
				.'&content_id='.$item->id;
			$full_js = $has_pro
				? "var url = jQuery(this).attr('data-href'); fc_showDialog(url, 'fc_modal_popup_container', 0, 800, 800, 0, {title:'" . JText::_($btn_title) . "'}); return false;"
				: "var box = jQuery('#fc_available_in_pro'); fc_file_props_handle = fc_showAsDialog(box, 480, 320, null, {title:'" . JText::_($btn_title) . "'}); return false;";

			$btn_name='collaborate';
			$btn_arr[$btn_name] = '<div id="fc_available_in_pro" style="display: none;">' . JText::_('FLEXI_AVAILABLE_IN_PRO_VERSION') . '</div>' . flexicontent_html::addToolBarButton(
					$btn_title, $btn_name, $full_js ,
					$msg_alert='', $msg_confirm='',
					$btn_task='', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
					$btn_class='btn-fcaction ' . (FLEXI_J40GE ? $this->btn_iv_class : '') . ' ' . $this->tooltip_class, $btn_icon="icon-mail",
					'data-placement="right" data-href="' . $task_url . '" title="' . $btn_info . '"', $auto_add = 0
				);
		}

		// Add Extra actions drop-down menu
		if (count($btn_arr) <= 2)
		{
			array_shift($btn_arr);
		}
		$drop_btn = '
			<button type="button" class="' . $this->btn_sm_class . ' btn-info dropdown-toggle" data-toggle="dropdown">
				<span title="'.JText::_('FLEXI_ACTIONS').'" class="icon-menu"></span>
				'.JText::_('FLEXI_MORE').'
				<span class="caret"></span>
			</button>';
		flexicontent_html::addToolBarDropMenu($btn_arr, 'action_btns_group', $drop_btn);
	}
}