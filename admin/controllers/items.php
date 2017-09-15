<?php
/**
 * @version 1.5 stable $Id$
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

// Register autoloader for parent controller, in case controller is executed by another component
JLoader::register('FlexicontentController', JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'controller.php');

/**
 * FLEXIcontent Component Item Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerItems extends FlexicontentController
{
	var $records_dbtbl  = 'content';
	var $records_jtable = 'flexicontent_items';
	var $record_name = 'item';
	var $record_name_pl = 'items';
	var $_NAME = 'ITEM';

	var $runMode = 'standalone';

	var $exitHttpHead = null;
	var $exitMessages = array();
	var $exitLogTexts = array();
	var $exitSuccess  = true;

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();

		// Register task aliases
		$this->registerTask( 'add',            'edit' );
		$this->registerTask( 'apply_type',     'save' );
		$this->registerTask( 'apply',          'save' );
		$this->registerTask( 'apply_ajax',     'save' );
		$this->registerTask( 'save2new',       'save' );
		$this->registerTask( 'save2copy',      'save' );

		$this->registerTask( 'unfeatured',     'featured' );

		$this->option = $this->input->get('option', '', 'cmd');
		$this->task   = $this->input->get('task', '', 'cmd');
		$this->view   = $this->input->get('view', '', 'cmd');
		$this->format = $this->input->get('format', '', 'cmd');

		// Get custom return URL, if this was present in the HTTP request
		$this->returnURL = $this->input->get('return-url', null, 'base64');
		$this->returnURL = $this->returnURL ? base64_decode($this->returnURL) : $this->returnURL;

		// Check return URL if empty or not safe and set a default one
		if ( ! $this->returnURL || ! flexicontent_html::is_safe_url($this->returnURL) )
		{
			if ($this->view == $this->record_name)
			{
				$this->returnURL = 'index.php?option=com_flexicontent&view=' . $this->record_name_pl;
			}
			else if ( !empty($_SERVER['HTTP_REFERER']) && flexicontent_html::is_safe_url($_SERVER['HTTP_REFERER']) )
			{
				$this->returnURL = $_SERVER['HTTP_REFERER'];
			}
			else
			{
				$this->returnURL = null;
			}
		}
	}


	/**
	 * Logic to save a record
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function save()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));
		
		// Initialize variables
		$app     = JFactory::getApplication();
		$db      = JFactory::getDbo();
		$user    = JFactory::getUser();
		$config  = JFactory::getConfig();
		$session = JFactory::getSession();
		$perms   = FlexicontentHelperPerm::getPerm();

		$ctrl_task = 'task=items.';
		$original_task = $this->task;
		
		
		// ***
		// *** Get data from request
		// ***
		
		// Retrieve form data these are subject to basic filtering
		$data   = $this->input->post->get('jform', array(), 'array');  // Unfiltered data, (Core Fields) validation will follow via jform
		$custom = $this->input->post->get('custom', array(), 'array');  // Unfiltered data, (Custom Fields) validation will be done onBeforeSaveField() of every field
		$jfdata = $this->input->post->get('jfdata', array(), 'array');  // Unfiltered data, (Core Fields) validation can be done via same jform as main data

		// Set into model: id (needed for loading correct item), and type id (e.g. needed for getting correct type parameters for new items)
		$data['id'] = (int) $data['id'];
		$isnew = $data['id'] == 0;

		// Extra steps before creating the model
		if ($isnew)
		{
			// Nothing needed
		}

		// Get the model
		$model = $this->getModel('item');
		$model->setId($data['id']);  // Make sure id is correct
		$model->getState();   // Populate state
		$record = $model->getItem($data['id'], $check_view_access=false, $no_cache=true, $force_version=0);

		// Make sure type is set into the given data, using type from model, if one was not given
		$data['type_id'] = empty($data['type_id'])
			? $model->get('type_id')
			: (int) $data['type_id'];

		// Set frontend item form as default return URL
		if ($app->isSite())
		{
			$Itemid = $this->input->get('Itemid', 0, 'int');  // maintain current menu item if this was given
			if (!$isnew)
			{
				$item_url = JRoute::_(FlexicontentHelperRoute::getItemRoute($record->slug, $record->categoryslug, $Itemid));
				$this->returnURL = $item_url . ( strstr($item_url, '?') ? '&' : '?' ) . 'task=edit';
			}
			elseif($Itemid)
			{
				$this->returnURL = JRoute::_('index.php?Itemid=' . $Itemid);
			}
		}

		// The save2copy task needs to be handled slightly differently.
		if ($this->task == 'save2copy')
		{
			// Check-in the original row.
			if ($model->checkin($data['id']) === false)
			{
				// Check-in failed
				$this->setError(JText::sprintf('JLIB_APPLICATION_ERROR_CHECKIN_FAILED', $model->getError()));
				$this->setMessage($this->getError(), 'error');

				// Redirect back to the edit form
				$this->setRedirect($this->returnURL);
				return false;
			}

			// Reset the ID, the multilingual associations and then treat the request as for Apply.
			$isnew = 1;
			$data['id'] = 0;
			$data['associations'] = array();
			$this->task = 'apply';

			// Keep existing model data (only clear ID)
			$model->set('id', 0);
			$model->setProperty('_id', 0);
			$model->setState('item.id', 0);
		}

		// Get merged parameters: component, type, and (FE only) menu
		// We will force using new type_id only for the purpose of getting type parameters
		// Below a check will be made if this type is allowed to the user
		$params = new JRegistry();
		$model_params = $model->getComponentTypeParams($data['type_id']);
		$params->merge($model_params);

		// For frontend merge the active menu parameters
		if ($app->isSite())
		{
			$menu = $app->getMenu()->getActive();
			if ($menu)
			{
				$params->merge($menu->params);
			}

			// Get some needed parameters
			$submit_redirect_url_fe = $params->get('submit_redirect_url_fe', '');
			$dolog = $params->get('print_logging_info');

			// Get submit configuration override
			if ($isnew && $original_task != 'save2copy')
			{
				$h = $data['submit_conf'];
				$item_submit_conf = $session->get('item_submit_conf', array(),'flexicontent');
			
				$submit_conf      = @ $item_submit_conf[$h] ;
				$allowunauthorize = $params->get('allowunauthorize', 0);
				$autopublished    = @ $submit_conf['autopublished'];     // Override flag for both TYPE and CATEGORY ACL
				$overridecatperms = @ $submit_conf['overridecatperms'];  // Override flag for CATEGORY ACL
			}
			else
			{
				$submit_conf      = false;
				$allowunauthorize = false;
				$autopublished    = false;
				$overridecatperms = false;
			}

			// We use some strings from administrator part, load english language file
			// for 'com_flexicontent' component then override with current language file
			JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, 'en-GB', true);
			JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, null, true);
		}

		// Get some flags this will also trigger item loading if not already loaded
		$isOwner = $model->get('created_by') == $user->get('id');

		// Unique id for new items, needed by some fields for temporary data
		$unique_tmp_itemid = $this->input->get('unique_tmp_itemid', '', 'string');
		$unique_tmp_itemid = substr($unique_tmp_itemid, 0, 1000);


		// ***
		// *** Some default values (further checks for these will be done later)
		// ***

		// Auto title for some content types, set it to pass validation. NOTE real value will be created via onBeforeSaveField event
		if ( $params->get('auto_title', 0) )
		{
			$data['title'] = (int) $data['id'];  // item id or ZERO for new items
		}

		// Check of empty tags, (later we will check if these are allowed to be changed or they were not shown !)
		if (!isset($data['tag']))
		{
			$data['tag'] = array();
		}

		// Check of empty categories, (later we will check if these are allowed to be changed or they were not shown !)
		if (!isset($data['cid']))
		{
			$data['cid'] = array();
		}


		// ***
		// *** Check for zero tags posted (also considering if tags editing is permitted to current user)
		// ***

		// No permission to change tags or tags were not displayed
		$tags_shown = $app->isAdmin()
			? 1
			: (int) $params->get('usetags_fe', 1) === 1;

		if (!$perms->CanUseTags || ! $tags_shown)
		{
			unset($data['tag']);
		}


		// ***
		// *** ENFORCE can change category ACL perms
		// ***


		// Per content type change category permissions
		$current_type_id  = $model->get('type_id') ?: (int) @ $data['type_id'];
		$CanChangeFeatCat = $user->authorise('flexicontent.change.cat.feat', 'com_flexicontent.type.' . $current_type_id);
		$CanChangeSecCat  = $user->authorise('flexicontent.change.cat.sec', 'com_flexicontent.type.' . $current_type_id);
		$CanChangeCat     = $user->authorise('flexicontent.change.cat', 'com_flexicontent.type.' . $current_type_id);
		
		$AutoApproveChanges = $perms->AutoApproveChanges;
		
		$enable_featured_cid_selector = $perms->MultiCat && $CanChangeFeatCat;
		$enable_cid_selector   = $perms->MultiCat && $CanChangeSecCat;
		$enable_catid_selector = ($isnew && !$params->get('catid_default')) || (!$isnew && !$model->get('catid')) || $CanChangeCat;
		
		// Enforce featured categories if user is not allowed to changed
		$featured_cats_parent = $params->get('featured_cats_parent', 0);
		$featured_cats = array();
		if ( $featured_cats_parent && !$enable_featured_cid_selector )
		{
			$featured_tree = flexicontent_cats::getCategoriesTree($published_only=1, $parent_id=$featured_cats_parent, $depth_limit=0);
			$disabled_cats = $params->get('featured_cats_parent_disable', 1) ? array($featured_cats_parent) : array();
			
			$featured_cid = array();
			if (!$isnew)
			{
				foreach($model->get('categories') as $item_cat)
				{
					if (isset($featured_tree[$item_cat]) && !isset($disabled_cats[$item_cat])) $featured_cid[] = $item_cat;
				}
			}
			$data['featured_cid'] = $featured_cid;
		}


		// Enforce maintaining secondary categories if user is not allowed to changed
		if (
			!$enable_cid_selector   // user can not change / set secondary cats
		) {
			// For new item use default secondary categories from type configuration
			if ($isnew)
			{
				$data['cid'] = $params->get('cid_default');
			}

			// Use featured cats if these are set
			else if ( isset($featured_cid) )
			{
				$featured_cid_arr = array_flip($featured_cid);
				$sec_cid = array();
				foreach($model->get('cats') as $item_cat) if (!isset($featured_cid_arr[$item_cat])) $sec_cid[] = $item_cat;
				$data['cid'] = $sec_cid;
			}

			// Use already assigned categories (existing item)
			else
			{
				$data['cid'] = $model->get('cats');
			}
		}


		// Enforce maintaining main category if user is not allowed to change
		if (
			!$enable_catid_selector   // user can not change / set main category
		) {
			if ($isnew && $params->get('catid_default'))
			  // For new item use default main category from type configuration
				$data['catid'] = $params->get('catid_default');
			else if ($model->get('catid'))
				// Use already assigned main category (existing item)
				$data['catid'] = $model->get('catid');
		}

		// These need to be an array during validation
		if (!isset($data['rules']) || !is_array($data['rules']))
		{
			$data['rules'] = array();
		}


		// ***
		// *** Basic Form data validation
		// ***

		// Get the JForm object, but do not pass any data we only want the form object,
		// in order to validate the data and not create a filled-in form
		$form = $model->getForm();
		$fc_doajax_submit = $this->input->get('fc_doajax_submit', 0, 'int');
		
		// Validate Form data for core fields and for parameters
		$validated_data = $model->validate($form, $data);

		// Check for validation error
		if (!$validated_data)
		{
			// Get the validation messages and push up to three validation messages out to the user
			$errors	= $form->getErrors();
			for ($i = 0, $n = count($errors); $i < $n && $i < 3; $i++)
			{
				$app->enqueueMessage($errors[$i] instanceof Exception ? $errors[$i]->getMessage() : $errors[$i], 'error');
			}

			// Set the POSTed form data into the session, so that they get reloaded
			$app->setUserState($form->option.'.edit.item.data', $data);      // Save the jform data in the session
			$app->setUserState($form->option.'.edit.item.custom', $custom);  // Save the custom fields data in the session
			$app->setUserState($form->option.'.edit.item.jfdata', $jfdata);  // Save the falang translations into the session
			$app->setUserState($form->option.'.edit.item.unique_tmp_itemid', $unique_tmp_itemid);  // Save temporary unique item id into the session

			// Redirect back to the edit form
			$this->setRedirect($this->returnURL);

			if ( $fc_doajax_submit )
				jexit(flexicontent_html::get_system_messages_html());
			else
				return false;
		}

		// Validate jfdata using same JForm
		$validated_jf = array();
		foreach ($jfdata as $lang_index => $lang_data)
		{
			foreach($lang_data as $i => $v)
			{
				$validated_jf[$lang_index][$i] = flexicontent_html::dataFilter($v, ($i!='text' ? 4000 : 0), 2, 0);
			}
		}

		// Some values need to be assigned after validation
		$validated_data['custom']  = & $custom;          // Assign array of custom field values, they are in the 'custom' form array instead of jform (validation will follow at each field)
		$validated_data['jfdata']  = & $validated_jf;    // Assign array of Joomfish field values, they are in the 'jfdata' form array instead of jform (validated above)
		
		// Assign template parameters of the select ilayout as an sub-array (the DB model will handle the merging of parameters)
		// Always be set in backend, but usually not in frontend, if frontend template editing is not shown
		if ($app->isAdmin())
			$ilayout = $data['attribs']['ilayout'];
		else
			$ilayout = @ $data['attribs']['ilayout'];

		// Give UNVALIDATED data for the case of LAYOUTS to the MODEL. Model will load the
		// XML file of the layout into a JForm object and do validation before merging them
		if( $ilayout && !empty($data['layouts'][$ilayout]) )
		{
			$validated_data['attribs']['layouts'] = $data['layouts'];
		}
		
		// USEFULL FOR DEBUGING (do not remove commented code)
		//$diff_arr = array_diff_assoc ( $data, $validated_data);
		//echo "<pre>"; print_r($diff_arr); jexit();
		
		
		// ***
		// *** PERFORM ACCESS CHECKS, NOTE: we need to check access again, despite having
		// *** checked them on edit form load, because user may have tampered with the form ... 
		// ***
		
		$itemAccess = $model->getItemAccess();
		$canAdd  = $itemAccess->get('access-create');  // includes check of creating in at least one category
		$canEdit = $itemAccess->get('access-edit');    // includes privileges edit and edit-own

		$type_id = (int) $validated_data['type_id'];

		// Existing item with Type not being ALTERED, content type can be maintained regardless of privilege
		if ( !$isnew && $model->get('type_id') == $type_id )
		{
			$canCreateType = true;
		}

		// New item or existing item with Type is being ALTERED, check privilege to create items of this type
		else
		{
			$canCreateType = $model->canCreateType( array($type_id), true, $types );
		}


		// ***
		// *** Calculate user's CREATE / EDIT privileges on current content item
		// ***
		
		$hasCoupon = false;  // Normally used in frontend only
		if (!$isnew)
		{
			if ( !$canEdit )
			{
				// No edit privilege, check if item is editable till logoff
				if ($session->has('rendered_uneditable', 'flexicontent'))
				{
					$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
					$canEdit = isset($rendered_uneditable[$model->get('id')]) && $rendered_uneditable[$model->get('id')];
					$hasCoupon = isset($rendered_uneditable[$model->get('id')]) && $rendered_uneditable[$model->get('id')] == 2;  // editable via coupon
				}
			}
		}
		
		// Special CASEs of overriding CREATE ACL in FrontEnd via menu item
		elseif ($app->isSite())
		{
			// Allow creating via submit menu OVERRIDE
			if ( $allowunauthorize )
			{
				$canAdd = true;
				$canCreateType = true;
			}

			// If without create privelege and category override is enabled then only check type and do not check category ACL
			else if (!$canAdd)
			{
				$canAdd = $overridecatperms && $canCreateType;
			}
		}
		
		// New item: check if user can create in at least one category
		if ($isnew && !$canAdd)
		{
			$app->enqueueMessage(JText::_('FLEXI_NO_ACCESS_CREATE'), 'error');
			$app->setHeader('status', 403, true);
			$this->setRedirect($this->returnURL);

			if ( $fc_doajax_submit )
				jexit(flexicontent_html::get_system_messages_html());
			else
				return false;
		}
		
		
		// Existing item: Check if user can edit current item
		if (!$isnew && !$canEdit)
		{
			$app->enqueueMessage(JText::_('FLEXI_NO_ACCESS_EDIT'), 'error');
			$app->setHeader('status', 403, true);
			$this->setRedirect($this->returnURL);

			if ( $fc_doajax_submit )
				jexit(flexicontent_html::get_system_messages_html());
			else
				return false;
		}

		if ( !$canCreateType )
		{
			$msg = isset($types[$type_id])
				? JText::sprintf( 'FLEXI_NO_ACCESS_CREATE_CONTENT_OF_TYPE', JText::_($types[$type_id]->name) )
				: ' Content Type '.$type_id.' was not found OR is not published';

			$app->enqueueMessage($msg, 'error');
			$app->setHeader('status', 403, true);
			$this->setRedirect($this->returnURL);

			if ( $fc_doajax_submit )
				jexit(flexicontent_html::get_system_messages_html());
			else
				return false;
		}


		// Get "BEFORE SAVE" categories for information mail
		$before_cats = array();
		if ( !$isnew )
		{
			$query 	= 'SELECT DISTINCT c.id, c.title FROM #__categories AS c'
				. ' JOIN #__flexicontent_cats_item_relations AS rel ON rel.catid = c.id'
				. ' WHERE rel.itemid = '.(int) $model->get('id');
			$db->setQuery( $query );
			$before_cats = $db->loadObjectList('id');
			$before_maincat = $model->get('catid');
		}
		
		
		// ***
		// *** Try to store the form data into the item
		// ***

		// If saving fails, do any needed cleanup, and then redirect back to item form
		if ( ! $model->store($validated_data) )
		{
			// Set the POSTed form data into the session, so that they get reloaded
			$app->setUserState($form->option.'.edit.item.data', $data);      // Save the jform data in the session
			$app->setUserState($form->option.'.edit.item.custom', $custom);  // Save the custom fields data in the session
			$app->setUserState($form->option.'.edit.item.jfdata', $jfdata);  // Save the falang translations into the session
			$app->setUserState($form->option.'.edit.item.unique_tmp_itemid', $unique_tmp_itemid);  // Save temporary unique item id into the session
			
			// Set error message and the redirect URL (back to the item form)
			$app->setHeader('status', '500 Internal Server Error', true);
			$this->setError($model->getError() ?: JText::_('FLEXI_ERROR_STORING_ITEM'));
			$this->setMessage($this->getError(), 'error');

			// For errors, we redirect back to refer
			$this->setRedirect($this->returnURL);
			
			// Try to check-in the record, but ignore any new errors
			try {
				!isnew ? $model->checkin() : true;
			}
			catch (Exception $e) {}

			if ( $fc_doajax_submit )
				jexit(flexicontent_html::get_system_messages_html());
			else
				return false;
		}
		
		
		// ***
		// *** Check in model and get item id in case of new item
		// ***
		$model->checkin();
		$validated_data['id'] = $isnew ? (int) $model->get('id') : $validated_data['id'];
		
		// Get items marked as newly submitted
		$newly_submitted = $session->get('newly_submitted', array(), 'flexicontent');
		if ($isnew)
		{
			// Mark item as newly submitted, to allow to a proper "THANKS" message after final save & close operation (since user may have clicked add instead of add & close)
			$newly_submitted[$model->get('id')] = 1;
			$session->set('newly_submitted', $newly_submitted, 'flexicontent');
		}
		$newly_submitted_item = @ $newly_submitted[$model->get('id')];
		
		
		// ***********************************************************************************************************
		// Get newly saved -latest- version (store task gets latest) of the item, and also calculate publish privelege
		// ***********************************************************************************************************
		$item = $model->getItem($validated_data['id'], $check_view_access=false, $no_cache=true, $force_version=-1);
		$canPublish = $model->canEditState( $item ) || $hasCoupon;
		
		
		// ***
		// *** Use session to detect multiple item saves to avoid sending notification EMAIL multiple times
		// ***
		$is_first_save = true;
		if ($session->has('saved_fcitems', 'flexicontent'))
		{
			$saved_fcitems = $session->get('saved_fcitems', array(), 'flexicontent');
			$is_first_save = $isnew ? true : !isset($saved_fcitems[$model->get('id')]);
		}
		// Add item to saved items of the corresponding session array
		$saved_fcitems[$model->get('id')] = $timestamp = time();  // Current time as seconds since Unix epoc;
		$session->set('saved_fcitems', $saved_fcitems, 'flexicontent');
		
		
		// ***
		// *** Get categories added / removed from the item
		// ***
		$query 	= 'SELECT DISTINCT c.id, c.title FROM #__categories AS c'
			. ' JOIN #__flexicontent_cats_item_relations AS rel ON rel.catid = c.id'
			. ' WHERE rel.itemid = '.(int) $model->get('id');
		$db->setQuery( $query );
		$after_cats = $db->loadObjectList('id');
		if ( !$isnew )
		{
			$cats_added_ids = array_diff(array_keys($after_cats), array_keys($before_cats));
			foreach($cats_added_ids as $cats_added_id)
			{
				$cats_added_titles[] = $after_cats[$cats_added_id]->title;
			}
			
			$cats_removed_ids = array_diff(array_keys($before_cats), array_keys($after_cats));
			foreach($cats_removed_ids as $cats_removed_id)
			{
				$cats_removed_titles[] = $before_cats[$cats_removed_id]->title;
			}
			$cats_altered = count($cats_added_ids) + count($cats_removed_ids);
			$after_maincat = $model->get('catid');
		}
		
		
		// ***
		// *** We need to get emails to notify, from Global/item's Content Type parameters -AND- from item's categories parameters
		// ***
		$notify_emails = array();
		if ( $is_first_save || $cats_altered || $params->get('nf_enable_debug',0) )
		{
			// Get needed flags regarding the saved items
			$approve_version = 2;
			$pending_approval_state = -3;
			$draft_state = -4;
			
			$current_version = FLEXIUtilities::getCurrentVersions($item->id, true); // Get current item version
			$last_version    = FLEXIUtilities::getLastVersions($item->id, true);    // Get last version (=latest one saved, highest version id),
			
			// $validated_data variables vstate & state may have been (a) tampered in the form, and/or (b) altered by save procedure so better not use them
			$needs_version_reviewal     = !$isnew && ($last_version > $current_version) && !$canPublish && !$AutoApproveChanges;
			$needs_publication_approval =  $isnew && ($item->state == $pending_approval_state) && !$canPublish;
			
			$draft_from_non_publisher = $item->state==$draft_state && !$canPublish;
			
			if ($draft_from_non_publisher) {
				// Suppress notifications for draft-state items (new or existing ones), for these each author will publication approval manually via a button
				$nConf = false;
			} else {
				// Get notifications configuration and select appropriate emails for current saving case
				$nConf = $model->getNotificationsConf($params);  //echo "<pre>"; print_r($nConf); "</pre>";
			}
			
			if ($nConf)
			{
				$states_notify_new = $params->get('states_notify_new', array(1,0,(FLEXI_J16GE ? 2:-1),-3,-4,-5));
				if ( empty($states_notify_new) )						$states_notify_new = array();
				else if ( ! is_array($states_notify_new) )	$states_notify_new = !FLEXI_J16GE ? array($states_notify_new) : explode("|", $states_notify_new);
				
				$states_notify_existing = $params->get('states_notify_existing', array(1,0,(FLEXI_J16GE ? 2:-1),-3,-4,-5));
				if ( empty($states_notify_existing) )						$states_notify_existing = array();
				else if ( ! is_array($states_notify_existing) )	$states_notify_existing = !FLEXI_J16GE ? array($states_notify_existing) : explode("|", $states_notify_existing);

				$n_state_ok = in_array($item->state, $states_notify_new);
				$e_state_ok = in_array($item->state, $states_notify_existing);
				
				if ($needs_publication_approval)   $notify_emails = $nConf->emails->notify_new_pending;
				else if ($isnew && $n_state_ok)    $notify_emails = $nConf->emails->notify_new;
				else if ($isnew)                   $notify_emails = array();
				else if ($needs_version_reviewal)  $notify_emails = $nConf->emails->notify_existing_reviewal;
				else if (!$isnew && $e_state_ok)   $notify_emails = $nConf->emails->notify_existing;
				else if (!$isnew)                  $notify_emails = array();
				
				if ($needs_publication_approval)   $notify_text = $params->get('text_notify_new_pending');
				else if ($isnew)                   $notify_text = $params->get('text_notify_new');
				else if ($needs_version_reviewal)  $notify_text = $params->get('text_notify_existing_reviewal');
				else if (!$isnew)                  $notify_text = $params->get('text_notify_existing');
				//print_r($notify_emails); jexit();
			}
		}
		
		
		// ***
		// *** If there are emails to notify for current saving case, then send the notifications emails, but 
		// ***
		if ( !empty($notify_emails) )
		{
			$notify_vars = new stdClass();
			$notify_vars->needs_version_reviewal     = $needs_version_reviewal;
			$notify_vars->needs_publication_approval = $needs_publication_approval;
			$notify_vars->isnew         = $isnew;
			$notify_vars->notify_emails = $notify_emails;
			$notify_vars->notify_text   = $notify_text;
			$notify_vars->before_cats   = $before_cats;
			$notify_vars->after_cats    = $after_cats;
			$notify_vars->original_item = $record;
			
			$model->sendNotificationEmails($notify_vars, $params, $manual_approval_request=0);
		}
		
		
		// ***
		// *** CLEAN THE CACHE so that our changes appear realtime
		// ***
		$cache = FLEXIUtilities::getCache($group='', 0);
		$cache->clean('com_flexicontent_items');
		$cache->clean('com_flexicontent_filters');
		$cache = FLEXIUtilities::getCache($group='', 1);
		$cache->clean('com_flexicontent_items');
		$cache->clean('com_flexicontent_filters');
		
		
		// ***
		// *** Recalculate EDIT PRIVILEGE of new item. Reason for needing to do this is because we can have create permission in a category
		// *** and thus being able to set this category as item's main category, but then have no edit/editown permission for this category
		// ***
		$asset = 'com_content.article.' . $model->get('id');
		$canEdit = $user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && $isOwner);
		
		
		// ***
		// *** Check if user can not edit item further (due to changed main category, without edit/editown permission)
		// ***
		if (!$canEdit)
		{
			// APPLY TASK: Temporarily set item to be editable till closing it and not through all session
			// (we will/should clear this flag when item is closed, since we have another flag to indicate new items
			if ($this->task=='apply' || $this->task=='apply_type')
			{
				$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
				$rendered_uneditable[$model->get('id')] = -1;
				$session->set('rendered_uneditable', $rendered_uneditable, 'flexicontent');
				$canEdit = 1;
			}
			
			// NEW ITEM: Do not use editable till logoff behaviour
			// ALSO: Clear editable FLAG set in the case that 'apply' button was used during new item creation
			else if ( $newly_submitted_item )
			{
				if ( !$params->get('items_session_editable', 0) )
				{
					$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
					if ( isset($rendered_uneditable[$model->get('id')]) )
					{
						unset( $rendered_uneditable[$model->get('id')] );
						$session->set('rendered_uneditable', $rendered_uneditable, 'flexicontent');
					}
				}
			}
			
			// EXISTING ITEM: (if enabled) Use the editable till logoff behaviour
			else
			{
				if ( $params->get('items_session_editable', 0) )
				{
					// Set notice for existing item being editable till logoff 
					$app->enqueueMessage( JText::_( 'FLEXI_CANNOT_EDIT_AFTER_LOGOFF' ), 'notice' );

					// Allow item to be editable till logoff
					$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
					$rendered_uneditable[$model->get('id')]  = 1;
					$session->set('rendered_uneditable', $rendered_uneditable, 'flexicontent');
					$canEdit = 1;
				}
			}

			// Set notice about saving an item that cannot be changed further
			if ( !$canEdit )
			{
				$app->enqueueMessage( JText::_( 'FLEXI_CANNOT_MAKE_FURTHER_CHANGES_TO_CONTENT' ), 'notice' );
			}
		}
		
		
		// ***
		// *** Check for new Content Item is being closed, and clear some flags
		// ***
		
		if ($this->task!='apply' && $this->task!='apply_type' && $newly_submitted_item )
		{
			// Clear item from being marked as newly submitted
			unset($newly_submitted[$model->get('id')]);
			$session->set('newly_submitted', $newly_submitted, 'flexicontent');
			
			// The 'apply' task may set 'editable till logoff' FLAG ...
			// CLEAR IT, since NEW content this is meant to be used temporarily
			if ( !$params->get('items_session_editable', 0) )
			{
				$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
				if ( isset($rendered_uneditable[$model->get('id')]) )
				{
					unset( $rendered_uneditable[$model->get('id')] );
					$session->set('rendered_uneditable', $rendered_uneditable, 'flexicontent');
				}
			}
		}
		
		
		// ***
		// *** Saving is done, decide where to redirect
		// ***
		switch ($this->task)
		{
			// REDIRECT CASE FOR APPLY / SAVE AS COPY: Save and reload the item edit form
			case 'apply':
			case 'apply_type':
				$msg = JText::_( 'FLEXI_ITEM_SAVED' );
				$link = 'index.php?option=com_flexicontent&'.$ctrl_task.'edit&view=item&id='.(int) $model->get('id');
				break;

			// REDIRECT CASE FOR SAVE and NEW: Save and load new item form
			case 'save2new':
				$msg = JText::_( 'FLEXI_ITEM_SAVED' );
				$link =
					'index.php?option=com_flexicontent&view=item' .
					'&typeid=' . $model->get('type_id') .
					'&filter_cats=' . $model->get('catid')
				;
				break;

			// REDIRECT CASES FOR SAVING
			default:
				$msg = JText::_( 'FLEXI_ITEM_SAVED' );
				$link = 'index.php?option=com_flexicontent&view=items';
				break;
		}

		$this->setRedirect($link, $msg);
		//return;  // comment above and decomment this one to profile the saving operation

		if ($fc_doajax_submit)
		{
			JFactory::getApplication()->enqueueMessage($msg, 'message');
			jexit(flexicontent_html::get_system_messages_html());
		}
	}
	
	
	/**
	 * Logic to order up/down an item
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function reorder($dir=null)
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		// Get variables: model, user, item id
		$model = $this->getModel('items');
		$user  = JFactory::getUser();

		$cid = $this->input->get('cid', array(0), 'array');
		$ord_catid  = $this->input->get('ord_catid', array(0), 'array');
		$prev_order = $this->input->get('prev_order', array(0), 'array');
		$item_cb    = $this->input->get('item_cb', array(0), 'array');

		JArrayHelper::toInteger($cid);
		JArrayHelper::toInteger($ord_catid);
		JArrayHelper::toInteger($prev_order);
		JArrayHelper::toInteger($item_cb);

		// Calculate access of orderitems ACL
		$canOrder = $user->authorise('flexicontent.orderitems', 'com_flexicontent');
		
		// Check access
		if ( !$canOrder )
		{
			$app->setHeader('status', '403 Forbidden', true);
			$app->enqueueMessage( JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ), 'error' );
			$app->redirect($this->returnURL);
		}

		if ( !$model->move($dir, $ord_catid, $prev_order, $item_cb) )
		{
			$app->setHeader('status', '500 Internal Server Error', true);
			$app->enqueueMessage( JText::_( 'FLEXI_ERROR_SAVING_ORDER' ) . ': ' . $model->getError(), 'error' );
			$app->redirect($this->returnURL);
		}

		$this->setRedirect($this->returnURL/*, JText::_( 'FLEXI_NEW_ORDERING_SAVED' )*/);
	}
	
	
	/**
	 * Logic to orderup an item
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function orderup()
	{
		$this->reorder($dir=-1);
	}

	/**
	 * Logic to orderdown an item
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function orderdown()
	{
		$this->reorder($dir=1);
	}
	
	
	/**
	 * Logic to mass ordering items
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function saveorder()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));
		
		// Get variables: model, user, item id, new ordering
		$model = $this->getModel('items');
		$user  = JFactory::getUser();

		$cid = $this->input->get('cid', array(0), 'array');
		$order = $this->input->get('order', array(0), 'array');
		$ord_catid = $this->input->get('ord_catid', array(0), 'array');
		$prev_order = $this->input->get('prev_order', array(0), 'array');

		JArrayHelper::toInteger($cid);
		JArrayHelper::toInteger($order);
		JArrayHelper::toInteger($ord_catid);
		JArrayHelper::toInteger($prev_order);
		
		// Calculate access of orderitems ACL
		$canOrder = $user->authorise('flexicontent.orderitems', 'com_flexicontent');
		
		// Check access
		if ( !$canOrder )
		{
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
			$app->redirect($this->returnURL);
		}

		if ( !$model->saveorder($cid, $order, $ord_catid, $prev_order) )
		{
			JError::raiseWarning( 500, JText::_( 'FLEXI_ERROR_SAVING_ORDER' ) . ': ' . $model->getError() );
			$app->redirect($this->returnURL);
		}

		$this->setRedirect($this->returnURL/*, JText::_( 'FLEXI_NEW_ORDERING_SAVED' )*/);
	}


	/**
	 * Logic to display form for copy/move items
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function copy()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$app   = JFactory::getApplication();
		$db    = JFactory::getDbo();
		$user  = JFactory::getUser();

		$cid = $this->input->get('cid', array(), 'array');
		JArrayHelper::toInteger($cid);

		// *** Check at least one item was selected
		if (!count($cid))
		{
			$app->enqueueMessage(JText::_('FLEXI_NO_ITEMS_SELECTED'), 'error');
			$app->redirect($this->returnURL);
		}

		// Calculate access of copyitems task
		$canCopy = $user->authorise('flexicontent.copyitems', 'com_flexicontent');

		// Check access
		if ( !$canCopy )
		{
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
			$app->redirect($this->returnURL);
		}
		
		// Access check
		$copytask_allow_uneditable = JComponentHelper::getParams( 'com_flexicontent' )->get('copytask_allow_uneditable', 1);
		if (!$copytask_allow_uneditable)
		{
			// Remove uneditable items
			$auth_cid = array();
			$non_auth_cid = array();
			
			// Get owner and other item data
			$q = "SELECT id, created_by, catid FROM #__content WHERE id IN (". implode(',', $cid) .")";
			$db->setQuery($q);
			$itemdata = $db->loadObjectList('id');
				
			// Check authorization for edit operation
			foreach ($cid as $id)
			{
				$isOwner = $itemdata[$id]->created_by == $user->id;
				$asset = 'com_content.article.' . $id;
				$canEdit = $user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && $isOwner);

				if ( $canEdit )
					$auth_cid[] = $id;
				else
					$non_auth_cid[] = $id;
			}
			//echo "<pre>"; echo "authorized:\n"; print_r($auth_cid); echo "\n\nNOT authorized:\n"; print_r($non_auth_cid); echo "</pre>"; exit;
		}
		else
		{
			$auth_cid = & $cid;
			$non_auth_cid = array();
		}
		
		// Set warning for uneditable items
		if (count($non_auth_cid)) {
			$msg_noauth = JText::_( 'FLEXI_CANNOT_COPY_ASSETS' );
			$msg_noauth .= ": " . implode(',', $non_auth_cid) ." - ". JText::_( 'FLEXI_REASON_NO_EDIT_PERMISSION' ) ." - ". JText::_( 'FLEXI_IDS_SKIPPED' );
			JError::raiseNotice(500, $msg_noauth);
			if ( !count($auth_cid) )  // Cancel task if no items can be copied
			{
				$app->redirect($this->returnURL);
			}
		}
		
		// Set only authenticated item ids, to be used by the parent display method ...
		$cid = $this->input->set('cid', $auth_cid);
		
		// display the form of the task
		parent::display();
	}
	
	/**
	 * Logic to copy/move the items
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function copymove()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$app   = JFactory::getApplication();
		$db    = JFactory::getDbo();
		$model = $this->getModel('items');
		$user  = JFactory::getUser();
		
		$cid = $this->input->get('cid', array(), 'array');
		JArrayHelper::toInteger($cid);

		// *** Check at least one item was selected
		if (!count($cid))
		{
			$app->enqueueMessage(JText::_('FLEXI_NO_ITEMS_SELECTED'), 'error');
			$app->redirect($this->returnURL);
		}

		// Calculate access of copyitems task
		$canCopy = $user->authorise('flexicontent.copyitems', 'com_flexicontent');

		// Check access
		if ( !$canCopy )
		{
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH_TASK' ) );
			$this->setRedirect($this->returnURL);
			return false;
		}

		$method   = $this->input->get('method', 1, 'int');
		$keeepcats= $this->input->get('keeepcats', 1, 'int');
		$keeptags = $this->input->get('keeptags', 1, 'int');
		$prefix   = $this->input->get('prefix', 1, 'string');
		$suffix   = $this->input->get('suffix', 1, 'string');
		$copynr   = $this->input->get('copynr', 1, 'int');
		$maincat  = $this->input->get('maincat', '', 'int');
		$seccats  = $this->input->get('seccats', array(), 'array');
		$keepseccats = $this->input->get('keepseccats', 0, 'int');
		$lang    = $this->input->get('language', '', 'string');
		
		$state   = $this->input->get('state', '', 'string');
		$state   = strlen($state) ? (int) $state : null;
		
		$type_id = $this->input->get('type_id', '', 'int');
		
		$access  = $this->input->get('access', '', 'string');
		$access  = strlen($access) ? (int) $access : null;
		
		// Set $seccats to --null-- to indicate that we will maintain secondary categories
		$seccats = $keepseccats ? null : $seccats;

		// Access check
		$copytask_allow_uneditable = JComponentHelper::getParams( 'com_flexicontent' )->get('copytask_allow_uneditable', 1);
		if (!$copytask_allow_uneditable || $method==2)  // if method is 2 (move) we will deny moving uneditable items
		{
			// Remove uneditable items
			$auth_cid = array();
			$non_auth_cid = array();
			
			// Get owner and other item data
			$q = "SELECT id, created_by, catid FROM #__content WHERE id IN (". implode(',', $cid) .")";
			$db->setQuery($q);
			$itemdata = $db->loadObjectList('id');
				
			// Check authorization for edit operation
			foreach ($cid as $id)
			{
				$isOwner = $itemdata[$id]->created_by == $user->id;
				$asset = 'com_content.article.' . $id;
				$canEdit = $user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && $isOwner);

				if ( $canEdit )
					$auth_cid[] = $id;
				else
					$non_auth_cid[] = $id;
			}
			//echo "<pre>"; echo "authorized:\n"; print_r($auth_cid); echo "\n\nNOT authorized:\n"; print_r($non_auth_cid); echo "</pre>"; exit;
		}
		else
		{
			$auth_cid = & $cid;
			$non_auth_cid = array();
		}
		
		// Set warning for uneditable items
		if (count($non_auth_cid))
		{
			$msg_noauth = JText::_( 'FLEXI_CANNOT_COPY_ASSETS' );
			$msg_noauth .= ": " . implode(',', $non_auth_cid) ." - ". JText::_( 'FLEXI_REASON_NO_EDIT_PERMISSION' ) ." - ". JText::_( 'FLEXI_IDS_SKIPPED' );
			JError::raiseNotice(500, $msg_noauth);
			if ( !count($auth_cid) )  // Cancel task if no items can be copied
			{
				$this->setRedirect($this->returnURL);
				return false;
			}
		}
		
		// Set only authenticated item ids for the copyitems() method
		$auth_cid = $cid;
		$clean_cache_flag = false;
		
		// Try to copy/move items
		if ($this->task == 'copymove')
		{
			if ($method == 1) // copy
			{
				if ( $model->copyitems($auth_cid, $keeptags, $prefix, $suffix, $copynr, $lang, $state) )
				{
					$msg = JText::sprintf( 'FLEXI_ITEMS_COPY_SUCCESS', count($auth_cid) );
					$clean_cache_flag = true;
				}
				else
				{
					$msg = JText::_( 'FLEXI_ERROR_COPY_ITEMS' );
					JError::raiseWarning( 500, $msg ." " . $model->getError() );
					$msg = '';
				}
			}
			else if ($method == 2) // update (optionally moving)
			{
				$msg = JText::sprintf( 'FLEXI_ITEMS_MOVE_SUCCESS', count($auth_cid) );
				
				foreach ($auth_cid as $itemid)
				{
					if ( !$model->moveitem($itemid, $maincat, $seccats, $lang, $state, $type_id, $access) )
					{
						$msg = JText::_( 'FLEXI_ERROR_MOVE_ITEMS' );
						JError::raiseWarning( 500, $msg ." " . $model->getError() );
						$msg = '';
					}
				}
				
				$clean_cache_flag = true;
			}
			else // copy and update (optionally moving)
			{
				if ( $model->copyitems($auth_cid, $keeptags, $prefix, $suffix, $copynr, $lang, $state, $method, $maincat, $seccats, $type_id, $access) )
				{
					$msg = JText::sprintf( 'FLEXI_ITEMS_COPYMOVE_SUCCESS', count($auth_cid) );
					$clean_cache_flag = true;
				}
				else
				{
					$msg = JText::_( 'FLEXI_ERROR_COPYMOVE_ITEMS' );
					JError::raiseWarning( 500, $msg ." " . $model->getError() );
					$msg = '';
				}
			}
			$link 	= 'index.php?option=com_flexicontent&view=items';
		}
		
		// CLEAN THE CACHE so that our changes appear realtime
		if ($clean_cache_flag) {
			$cache = FLEXIUtilities::getCache($group='', 0);
			$cache->clean('com_flexicontent_items');
			$cache->clean('com_flexicontent_filters');
			$cache = FLEXIUtilities::getCache($group='', 1);
			$cache->clean('com_flexicontent_items');
			$cache->clean('com_flexicontent_filters');
		}
		
		$this->setRedirect($link, $msg);
	}
	
	
	/**
	 * Check in a record
	 *
	 * @since	1.5
	 */
	function checkin()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$redirect_url = $this->returnURL;
		flexicontent_db::checkin($this->records_jtable, $redirect_url, $this);
	}
	
	
	/**
	 * Import Joomla com_content datas
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function import()
	{		
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$user  = JFactory::getUser();
		$model = $this->getModel('items');
		
		$permission = FlexicontentHelperPerm::getPerm();
		$canImport = $permission->CanConfig;
		
		if(!$canImport) {
			echo JText::_( 'ALERTNOTAUTH' );
			return;
		}
		
		$logs = $model->import();
		$cache = FLEXIUtilities::getCache($group='', 0);
		$cache->clean('com_flexicontent_cats');
		$cache = FLEXIUtilities::getCache($group='', 1);
		$cache->clean('com_flexicontent_cats');
		
		$msg  = JText::_( 'FLEXI_IMPORT_SUCCESSFUL' );
		$msg .= '<ul class="import-ok">';
		if (!FLEXI_J16GE) {
			$msg .= '<li>' . $logs->sec . ' ' . JText::_( 'FLEXI_IMPORT_SECTIONS' ) . '</li>';
		}
		$msg .= '<li>' . $logs->cat . ' ' . JText::_( 'FLEXI_IMPORT_CATEGORIES' ) . '</li>';
		$msg .= '<li>' . $logs->art . ' ' . JText::_( 'FLEXI_IMPORT_ARTICLES' ) . '</li>';
		$msg .= '</ul>';

		if (isset($logs->err)) {
			$msg .= JText::_( 'FLEXI_IMPORT_FAILED' );
			$msg .= '<ul class="import-failed">';
			foreach ($logs->err as $err) {
				$msg .= '<li>' . $err->type . ' ' . $err->id . ': ' . $err->title . '</li>';
			}
			$msg .= '</ul>';
		} else {
			$msg .= JText::_( 'FLEXI_IMPORT_NO_ERROR' );		
		}
    
		$msg .= '<p class="button-close"><input type="button" class="fc_button" onclick="window.parent.document.adminForm.submit();" value="'.JText::_( 'FLEXI_CLOSE_REFRESH_DASHBOARD' ).'" /><p>';

		echo $msg;
	}


	/* WRAPPER method for changestate TASK */
	function publish()
	{
		JFactory::getApplication()->input->set('newstate', 'P');
		$this->changestate();
	}

	/* WRAPPER method for changestate TASK */
	function unpublish()
	{
		JFactory::getApplication()->input->set('newstate', 'U');
		$this->changestate();
	}


	/**
	 * Method to toggle the featured setting of a list of articles.
	 *
	 * @return  void
	 *
	 * @since   3.2
	 */
	public function featured()
	{
		// Check for request forgeries
		JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

		$app   = JFactory::getApplication();
		$user  = JFactory::getUser();

		$cid    = $this->input->get('cid', array(), 'array');
		$values = array('featured' => 1, 'unfeatured' => 0);
		$value  = JArrayHelper::getValue($values, $this->task, 0, 'int');

		// Access checks.
		foreach ($cid as $i => $id)
		{
			if (!$user->authorise('core.edit.state', 'com_content.article.' . (int) $id))
			{
				// Prune items that you can't change.
				unset($cid[$i]);
				JError::raiseNotice(403, JText::_('JLIB_APPLICATION_ERROR_EDITSTATE_NOT_PERMITTED'));
			}
		}

		if (empty($cid))
		{
			$app->enqueueMessage(JText::_('FLEXI_NO_ITEMS_SELECTED'), 'error');
			$app->redirect($this->returnURL);
		}

		// Get the model.
		$itemmodel = $this->getModel('item');

		// Update featured flag (model will also handle cache cleaning)
		if (!$itemmodel->featured($cid, $value))
		{
			$app->enqueueMessage($itemmodel->getError(), 'error');
			$app->redirect($this->returnURL);
		}

		$message = $value == 1
			? JText::plural('COM_CONTENT_N_ITEMS_FEATURED', count($cid))
			: JText::plural('COM_CONTENT_N_ITEMS_UNFEATURED', count($cid));
		$this->setRedirect($this->returnURL, $message);
	}


	/**
	 * Logic to change state of multiple items
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function changestate()
	{
		// Check for request forgeries
		JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

		$app   = JFactory::getApplication();
		$db    = JFactory::getDbo();
		$user  = JFactory::getUser();
		
		$itemmodel = $this->getModel('item');
		$msg = '';

		$cid = $this->input->get('cid', array(), 'array');
		JArrayHelper::toInteger($cid);

		// *** Check at least one item was selected
		if (!count($cid))
		{
			$app->enqueueMessage(JText::_('FLEXI_NO_ITEMS_SELECTED'), 'error');
			$app->redirect($this->returnURL);
		}

		$newstate = $this->input->get('newstate', '', 'string');
		$stateids = array ( 'PE' => -3, 'OQ' => -4, 'IP' => -5, 'P' => 1, 'U' => 0, 'A' => (FLEXI_J16GE ? 2:-1), 'T' => -2 );
		$statenames = array ( 'PE' => 'FLEXI_PENDING', 'OQ' => 'FLEXI_TO_WRITE', 'IP' => 'FLEXI_IN_PROGRESS', 'P' => 'FLEXI_PUBLISHED', 'U' => 'FLEXI_UNPUBLISHED', 'A' => 'FLEXI_ARCHIVED', 'T' => 'FLEXI_TRASHED' );

		// *** Check for valid state
		if (!isset($stateids[$newstate]))
		{
			$app->enqueueMessage(JText::_('Invalid State') . ': ' . $newstate, 'error');
			$app->redirect($this->returnURL);
		}

		// Remove unauthorized (undeletable) items
		$auth_cid = array();
		$non_auth_cid = array();

		// Get owner and other item data
		$q = "SELECT id, created_by, catid FROM #__content WHERE id IN (". implode(',', $cid) .")";
		$db->setQuery($q);
		$itemdata = $db->loadObjectList('id');

		// Check authorization for publish operation
		foreach ($cid as $id)
		{
			// Determine priveleges of the current user on the given item
			$asset = 'com_content.article.' . $itemdata[$id]->id;
			$has_edit_state = $user->authorise('core.edit.state', $asset) || ($user->authorise('core.edit.state.own', $asset) && $itemdata[$id]->created_by == $user->get('id'));
			$has_delete     = $user->authorise('core.delete', $asset) || ($user->authorise('core.delete.own', $asset) && $itemdata[$id]->created_by == $user->get('id'));
			// ...
			$permission = FlexicontentHelperPerm::getPerm();
			$has_archive    = $permission->CanArchives;

			$has_edit_state = $has_edit_state && in_array($stateids[$newstate], array(0,1,-3,-4,-5));
			$has_delete     = $has_delete     && $stateids[$newstate] == -2;
			$has_archive    = $has_archive    && $stateids[$newstate] == (FLEXI_J16GE ? 2:-1);

			if ( $has_edit_state || $has_delete || $has_archive ) {
				$auth_cid[] = $id;
			} else {
				$non_auth_cid[] = $id;
			}
		}

		//echo "<pre>"; echo "authorized:\n"; print_r($auth_cid); echo "\n\nNOT authorized:\n"; print_r($non_auth_cid); echo "</pre>"; exit;
		
		// Set warning for undeletable items
		if (count($non_auth_cid)) {
			$msg_noauth = JText::_( 'FLEXI_CANNOT_CHANGE_STATE_ASSETS' );
			$msg_noauth .= ": " . implode(',', $non_auth_cid) ." - ". JText::_( 'FLEXI_REASON_NO_PUBLISH_PERMISSION' ) ." - ". JText::_( 'FLEXI_IDS_SKIPPED' );
			JError::raiseNotice(500, $msg_noauth);
		}
		
		// Set state, (model will also handle cache cleaning)
		if (count($auth_cid))
		{
			foreach ($auth_cid as $item_id)
			{
				$itemmodel->setitemstate($item_id, $stateids[$newstate], $_cleanCache = false);
			}
			$msg = count($auth_cid) ." ". JText::_('FLEXI_ITEMS') ." : &nbsp; ". JText::_( 'FLEXI_ITEMS_STATE_CHANGED_TO')." -- ".JText::_( $statenames[$newstate] ) ." --";
			if ($newstate=='T') $msg .= '<br/> '.JText::_('FLEXI_NOTES').': '.JText::_('FLEXI_DELETE_PERMANENTLY');

			$cache = FLEXIUtilities::getCache($group='', 0);
			$cache->clean('com_flexicontent_items');
			$cache->clean('com_flexicontent_filters');
			$cache = FLEXIUtilities::getCache($group='', 1);
			$cache->clean('com_flexicontent_items');
			$cache->clean('com_flexicontent_filters');
		}

		$this->setRedirect($this->returnURL, $msg);
	}

	
	/**
	 * Logic to submit item to approval
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function approval()
	{
		// Check for request forgeries
		JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

		$app   = JFactory::getApplication();

		$cid = $this->input->get('cid', array(), 'array');
		JArrayHelper::toInteger($cid);

		if (!count($cid))
		{
			$app->enqueueMessage(JText::_('FLEXI_APPROVAL_SELECT_ITEM_SUBMIT'), 'error');
			$app->redirect($this->returnURL);
		}

		// Approve item(s) (model will also handle cache cleaning)
		$itemmodel = $this->getModel('item');
		$msg = $itemmodel->approval($cid);

		$this->setRedirect($this->returnURL, $msg);
	}


	/**
	 * Logic to delete items
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function remove()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));
		
		$app   = JFactory::getApplication();
		$db    = JFactory::getDbo();
		$user  = JFactory::getUser();
		
		$model = $this->getModel('items');
		$itemmodel = $this->getModel('item');
		$msg = '';

		$cid = $this->input->get('cid', array(), 'array');
		JArrayHelper::toInteger($cid);

		if (!count($cid))
		{
			$app->enqueueMessage(JText::_('FLEXI_SELECT_ITEM_DELETE'), 'error');
			$app->redirect($this->returnURL);
		}


		// Remove unauthorized (undeletable) items
		$auth_cid = array();
		$non_auth_cid = array();

		// Get owner and other item data
		$q = "SELECT id, created_by, catid FROM #__content WHERE id IN (". implode(',', $cid) .")";
		$db->setQuery($q);
		$itemdata = $db->loadObjectList('id');

		// Check authorization for delete operation
		foreach ($cid as $id)
		{
			$isOwner = $itemdata[$id]->created_by == $user->id;
			$asset = 'com_content.article.' . $id;
			$canDelete = $user->authorise('core.delete', $asset) || ($user->authorise('core.delete.own', $asset) && $isOwner);

			if ( $canDelete )
				$auth_cid[] = $id;
			else
				$non_auth_cid[] = $id;
		}
		//echo "<pre>"; echo "authorized:\n"; print_r($auth_cid); echo "\n\nNOT authorized:\n"; print_r($non_auth_cid); echo "</pre>"; exit;
		
		// Set warning for undeletable items
		if (count($non_auth_cid))
		{
			$msg_noauth = count($non_auth_cid) < 2
				? JText::_('FLEXI_CANNOT_DELETE_ITEM')
				: JText::_('FLEXI_CANNOT_DELETE_ITEMS');
			$msg_noauth .= ": " . implode(',', $non_auth_cid) ." - ". JText::_( 'FLEXI_REASON_NO_DELETE_PERMISSION' ) ." - ". JText::_( 'FLEXI_IDS_SKIPPED' );

			$app->enqueueMessage($msg_noauth, 'error');
			$app->redirect($this->returnURL);
		}

		// Try to delete 
		if (count($auth_cid) && !$model->delete($auth_cid, $itemmodel))
		{
			$app->enqueueMessage(JText::_('FLEXI_OPERATION_FAILED'), 'error');
			$app->redirect($this->returnURL);
		}

		$cache = FLEXIUtilities::getCache($group='', 0);
		$cache->clean('com_flexicontent_items');
		$cache->clean('com_flexicontent_filters');
		$cache = FLEXIUtilities::getCache($group='', 1);
		$cache->clean('com_flexicontent_items');
		$cache->clean('com_flexicontent_filters');

		$msg = count($auth_cid).' '.JText::_( 'FLEXI_ITEMS_DELETED' );
		$this->setRedirect($this->returnURL, $msg);
	}
	
	
	/**
	 * Logic to set the access level of the Items
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function access()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$app   = JFactory::getApplication();
		$user  = JFactory::getUser();

		$cid = $this->input->get('cid', array(), 'array');
		JArrayHelper::toInteger($cid);

		// *** Check at least one item was selected
		if (!count($cid))
		{
			$app->enqueueMessage(JText::_('FLEXI_NO_ITEMS_SELECTED'), 'error');
			$app->redirect($this->returnURL);
		}
		$id = reset($cid);

		// Check if user can edit the item
		$itemmodel = $this->getModel('item');
		$canEdit = $itemmodel->getItemAccess()->get('access-edit');
		if (!$canEdit)
		{
			$msg_noauth = JText::_( 'FLEXI_CANNOT_CHANGE_ACCLEVEL_ASSETS' );
			$msg_noauth .= ": " . implode(',', $non_auth_cid) ." - ". JText::_( 'FLEXI_REASON_NO_PUBLISH_PERMISSION' );

			$app->enqueueMessage($msg_noauth, 'error');
			$app->redirect($this->returnURL);
		}

		// Get and check new access level
		$accesses	= $this->input->get('access', array(), 'array');

		// *** Check at least one item was selected
		if (!isset($accesses[$id]))
		{
			$app->enqueueMessage('No access level for item id: ' . $id, 'error');
			$app->redirect($this->returnURL);
		}
		$access = (int) $accesses[$id];

		$model = $this->getModel('items');
		if (!$model->saveaccess($id, $access))
		{
			$app->enqueueMessage(JText::_('FLEXI_ERROR_SETTING_ITEM_ACCESS_LEVEL') . ' ' . $model->getError(), 'error');
			$app->redirect($this->returnURL);
		}

		$cache = FLEXIUtilities::getCache($group='', 0);
		$cache->clean('com_flexicontent_items');
		$cache->clean('com_flexicontent_filters');
		$cache = FLEXIUtilities::getCache($group='', 1);
		$cache->clean('com_flexicontent_items');
		$cache->clean('com_flexicontent_filters');

		$this->setRedirect($this->returnURL);
	}


	/**
	 * logic for cancel an action
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function cancel()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$data = $this->input->get('jform', array(), 'array');  // Unfiltered data (no need for filtering)
		$this->input->set('cid', (int) $data['id']);

		$this->checkin();
	}


	/**
	 * logic for restore an old version
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function restore()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$id			= $this->input->get('id', 0, 'int');
		$version	= $this->input->get('version', '', 'int');
		$itemmodel = $this->getModel('item');

		// First checkin the open item
		$item = JTable::getInstance($this->records_jtable, '');
		$item->bind(JRequest::get('request'));
		$item->checkin();
		if ($version)
		{
			$msg = JText::sprintf( 'FLEXI_VERSION_RESTORED', $version );
			$itemmodel->restore($version, $id);
		}
		else
		{
			$msg = JText::_( 'FLEXI_NOTHING_TO_RESTORE' );
		}
		$ctrlTask  = !FLEXI_J16GE ? 'controller=items&task=edit' : 'task=items.edit';
		$this->setRedirect( 'index.php?option=com_flexicontent&'.$ctrlTask.'&cid[]='.$id, $msg );
	}


	/**
	 * Logic to create the view for the edit item screen
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function edit()
	{
		$app      = JFactory::getApplication();
		$user     = JFactory::getUser();
		$session  = JFactory::getSession();
		$document = JFactory::getDocument();

		$this->input->set('view', 'item');
		$this->input->set('hidemainmenu', 1);

		// Get/Create the view
		$viewType   = $document->getType();
		$viewName   = $this->input->get('view', $this->default_view, 'cmd');
		$viewLayout = $this->input->get('layout', 'default', 'string');
		$view = $this->getView($viewName, $viewType, '', array('base_path' => $this->basePath, 'layout' => $viewLayout));
		
		// Get/Create the model
		$model = $this->getModel('item');
		
		// Push the model into the view (as default), later we will call the view display method instead of calling parent's display task, because it will create a 2nd model instance !!
		$view->setModel($model, true);
		$view->document = $document;
		
		// FORCE model to load versioned data (URL specified version or latest version (last saved))
		$version = $this->input->get('version', 0, 'int');   // Load specific item version (non-zero), 0 version: is unversioned data, -1 version: is latest version (=default for edit form)
		$item = $model->getItem(null, $check_view_access=false, $no_cache=true, $force_version=($version!=0 ? $version : -1));  // -1 version means latest
		
		$isnew  = !$model->getId();
		
		$canAdd  = $model->getItemAccess()->get('access-create');
		$canEdit = $model->getItemAccess()->get('access-edit');

		if ( !$canEdit )
		{
			// No edit privilege, check if item is editable till logoff
			if ($session->has('rendered_uneditable', 'flexicontent'))
			{
				$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
				$canEdit = isset($rendered_uneditable[$model->get('id')]) && $rendered_uneditable[$model->get('id')];
			}
		}
		
		// New item: check if user can create in at least one category
		if ($isnew)
		{
			// A. Check create privilege
			if( !$canAdd )
			{
				$app->setHeader('status', '403 Forbidden', true);
				$this->setRedirect($this->returnURL, JText::_( 'FLEXI_NO_ACCESS_CREATE' ), 'error');

				$model->enqueueMessages($_exclude = array('showAfterLoad'=>1));
				return;
			}
			
			// Get User Group / Author parameters
			$db = JFactory::getDbo();
			$authorparams = flexicontent_db::getUserConfig($user->id);
			$max_auth_limit = intval($authorparams->get('max_auth_limit', 0));  // maximum number of content items the user can create
			
			// B. Check if max authored content limit reached
			if ($max_auth_limit)
			{
				$db->setQuery('SELECT COUNT(id) FROM #__content WHERE created_by = ' . $user->id);
				$authored_count = $db->loadResult();
				if ($authored_count >= $max_auth_limit)
				{
					$app->setHeader('status', '403 Forbidden', true);
					$this->setRedirect($this->returnURL, JText::sprintf( 'FLEXI_ALERTNOTAUTH_CREATE_MORE', $max_auth_limit ), 'warning');

					$model->enqueueMessages($_exclude = array('showAfterLoad'=>1));
					return;
				}
			}
			
			// C. Check if Content Type can be created by current user
			$typeid = $this->input->get('typeid', 0, 'int');
			$canCreateType = $typeid
				? $model->canCreateType( array($typeid), true, $types )  // Can create given Content Type
				: $model->canCreateType( );  // Can create at least one Content Type

			if( !$canCreateType )
			{
				$type_name = isset($types[$$typeid]) ? '"'.JText::_($types[$$typeid]->name).'"' : JText::_('FLEXI_ANY');
				$msg = JText::sprintf( 'FLEXI_NO_ACCESS_CREATE_CONTENT_OF_TYPE', $type_name );

				$app->setHeader('status', '403 Forbidden', true);
				$this->setRedirect($this->returnURL, $msg, 'error');

				$model->enqueueMessages($_exclude = array('showAfterLoad'=>1));
				return;
			}
		}
		
		// Existing item: Check if user can edit current item
		else
		{
			if ( !$canEdit )
			{
				$app->setHeader('status', '403 Forbidden', true);
				$this->setRedirect($this->returnURL, JText::_( 'FLEXI_NO_ACCESS_EDIT' ), 'error');

				$model->enqueueMessages($_exclude = array('showAfterLoad'=>1));
				return;
			}
		}
		
		// Check if record is checked out by other editor
		if ( $model->isCheckedOut($user->get('id')) )
		{
			$app->setHeader('status', '500', true);
			$this->setRedirect($this->returnURL, JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ), 'notice');

			$model->enqueueMessages($_exclude = array('showAfterLoad'=>1));
			return;
		}
		
		// Checkout the record and proceed to edit form
		if ( !$model->checkout() )
		{
			$app->setHeader('status', '400 Bad Request', true);
			$this->setRedirect($this->returnURL, JText::_('FLEXI_OPERATION_FAILED') . ' : ' . $model->getError(), 'error');

			$model->enqueueMessages($_exclude = array('showAfterLoad'=>1));
			return;
		}

		// Enqueue minor model messages / notices
		$model->enqueueMessages();

		// Call display method of the view, instead of calling parent's display task, because it will create a 2nd model instance !!
		$view->display();
	}


	/**
	 * Method to fetch the tags edit field for the edit form, this is currently NOT USED
	 * 
	 * @since 1.5
	 */
	function gettags()
	{
		// Check for request forgeries
		JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

		$id    = $this->input->get('id', 0, 'int');
		$itemmodel = $this->getModel('item');
		$tags  = $itemmodel->gettags();
		$user  = JFactory::getUser();

		// Get tag ids if non-new item
		$used = $id ? $itemmodel->getUsedtagsIds($id) : null;
		$used = is_array($used) ? $used : array();

		$permission = FlexicontentHelperPerm::getPerm();
		$CanCreateTags = $permission->CanCreateTags;
		$CanUseTags    = $permission->CanUseTags;

		$CanUseTags = $CanUseTags ? '' : ' disabled="disabled"';
		$n = count($tags);
		$html = '';

		// Create list of current item's already assigned tags
		if ($n)
		{
			$html .= '<div class="fc_tagbox" id="fc_tagbox">';
			$html .= '<ul id="ultagbox">';
			for( $i = 0, $n; $i < $n; $i++ ){
				$tag = $tags[$i];
				if (!in_array($tag->id, $used)) continue; // tag not assigned to item
				if ( $CanUseTags && in_array($tag->id, $used) ) {
					$html .='
					<li class="tagitem">
						<span>'.$tag->name.'</span>
						<input type="hidden" name="jform[tag][]" value="'.$tag->tid.'" />
						<a href="javascript:;" class="deletetag" onclick="javascript:deleteTag(this);" title="'.JText::_('FLEXI_DELETE_TAG').'"></a>
					</li>';
				} else {
					$html .='
					<li class="tagitem plain">
						<span>'.$tag->name.'</span>
						<input type="hidden" name="jform[tag][]" value="'.$tag->tid.'" />
					</li>';
				}
			}
			$html .= '</ul>';
			$html .= '</div>';
			$html .= '<div class="fcclear"></div>';
		}

		if ($CanCreateTags)
		{
			$html .= '
			<div class="fc_addtag">
				<label for="addtags">'.JText::_( 'FLEXI_ADD_TAG' ).'</label>
				<input type="text" id="tagname" class="inputbox" size="30" />
				<input type="button" class="fc_button" value="'.JText::_( 'FLEXI_ADD' ).'" onclick="addtag()" />
			</div>';
		}
		echo $html;
	}


	/**
	 * Method to fetch the votes
	 * 
	 * @since 1.5
	 */
	function getvotes()
	{
		$id = JFactory::getApplication()->input->get('id', 0, 'int');

		@ob_end_clean();
		$votes = $this->getModel('item')->getRatingDisplay($id);

		jexit($votes ?: '0');
	}


	/**
	 * Method to get hits
	 * 
	 * @since 1.5
	 */
	function gethits()
	{
		$id = JFactory::getApplication()->input->get('id', 0, 'int');

		@ob_end_clean();
		$hits = $this->getModel('item')->gethits($id);

		jexit($hits ?: '0');
	}
	
}
