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

defined('_JEXEC') or die;

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

JLoader::register('FlexicontentControllerBaseAdmin', JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'controllers' . DS . 'base' . DS . 'baseadmin.php');

// Manually import models in case used by frontend, then models will not be autoloaded correctly via getModel('name')
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'item.php';
require_once JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_flexicontent' . DS . 'models' . DS . 'items.php';

/**
 * FLEXIcontent Items Controller
 *
 * NOTE: -Only- if this controller is needed by frontend URLs, then create a derived controller in frontend 'controllers' folder
 *
 * @since 3.3
 */
class FlexicontentControllerItems extends FlexicontentControllerBaseAdmin
{
	var $records_dbtbl  = 'content';
	var $records_jtable = 'flexicontent_items';

	var $record_name = 'item';
	var $record_name_pl = 'items';

	var $_NAME = 'ITEM';
	var $record_alias = 'alias';

	var $runMode = 'standalone';

	var $exitHttpHead = null;
	var $exitMessages = array();
	var $exitLogTexts = array();
	var $exitSuccess  = true;

	/**
	 * Constructor
	 *
	 * @param   array   $config    associative array of configuration settings.
	 *
	 * @since 3.3
	 */
	public function __construct($config = array())
	{
		parent::__construct($config);

		/**
		 * Register task aliases
		 */
		$this->registerTask('add',          'edit');
		$this->registerTask('apply_type',   'save');
		$this->registerTask('apply',        'save');
		$this->registerTask('apply_ajax',   'save');
		$this->registerTask('save2new',     'save');
		$this->registerTask('save2copy',    'save');

		$this->registerTask('unfeatured',   'featured');

		$this->registerTask('copy',         'batch');
		$this->registerTask('translate',    'batch');
	}


	/**
	 * Logic to save a record
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function save()
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		// Initialize variables
		$app     = JFactory::getApplication();
		$db      = JFactory::getDbo();
		$user    = JFactory::getUser();
		$config  = JFactory::getConfig();
		$session = JFactory::getSession();
		$perms   = FlexicontentHelperPerm::getPerm();

		$ctrl_task = $app->isClient('site')
			? 'task='
			: 'task=' . $this->record_name_pl . '.';
		$original_task = $this->task;

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
		$model = $this->getModel($this->record_name);
		$model->setId($data['id']);  // Make sure id is correct
		$model->getState();   // Populate state
		$record = $model->getItem($data['id'], $check_view_access = false, $no_cache = true, $force_version = 0);

		// Make sure type is set into the given data, using type from model, if one was not given
		$data['type_id'] = empty($data['type_id'])
			? $model->get('type_id')
			: (int) $data['type_id'];

		// The save2copy task needs to be handled slightly differently.
		if ($this->task === 'save2copy')
		{
			// Check-in the original row.
			if ($model->checkin($data['id']) === false)
			{
				// Check-in failed
				$this->setError(JText::sprintf('JLIB_APPLICATION_ERROR_CHECKIN_FAILED', $model->getError()));
				$this->setMessage($this->getError(), 'error');

				// Set the POSTed form data into the session, so that they get reloaded
				$app->setUserState('com_flexicontent.edit.' . $this->record_name . '.data', $data);      // Save the jform data in the session

				// Skip redirection back to return url if inside a component-area-only view, showing error using current page, since usually we are inside a iframe modal
				if ($this->input->getCmd('tmpl') !== 'component')
				{
					$this->setRedirect($this->returnURL);
				}

				if ($this->input->get('fc_doajax_submit'))
				{
					jexit(flexicontent_html::get_system_messages_html());
				}
				else
				{
					return false;
				}
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

		// The apply_ajax task is treat same as apply (also same redirection in case that AJAX submit is skipped)
		elseif ($this->task === 'apply_ajax')
		{
			$this->task = 'apply';
		}

		// Get merged parameters: component, type, and (FE only) menu
		// We will force using new type_id only for the purpose of getting type parameters
		// Below a check will be made if this type is allowed to the user
		$params = new JRegistry;
		$model_params = $model->getComponentTypeParams($data['type_id']);
		$params->merge($model_params);

		// For frontend merge the active menu parameters
		if ($app->isClient('site'))
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
				$item_submit_conf = $session->get('item_submit_conf', array(), 'flexicontent');

				$submit_conf      = @ $item_submit_conf[$h];
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


		/**
		 * Some default values (further checks for these will be done later)
		 */

		// Auto title for some content types, set it to pass validation. NOTE real value will be created via onBeforeSaveField event
		if ($params->get('auto_title', 0))
		{
			$data['title'] = (int) $data['id'];  // Item id or ZERO for new items
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


		/**
		 * Check for zero tags posted (also considering if tags editing is permitted to current user)
		 */

		// No permission to change tags or tags were not displayed
		$tags_shown = $app->isClient('administrator')
			? 1
			: (int) $params->get('usetags_fe', 1) === 1;

		if (!$perms->CanUseTags || ! $tags_shown)
		{
			unset($data['tag']);
		}


		/**
		 * ENFORCE can change category ACL perms
		 */

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

		if ($featured_cats_parent && !$enable_featured_cid_selector)
		{
			$featured_tree = flexicontent_cats::getCategoriesTree($published_only = 1, $parent_id = $featured_cats_parent, $depth_limit = 0);
			$disabled_cats = $params->get('featured_cats_parent_disable', 1) ? array($featured_cats_parent) : array();

			$featured_cid = array();

			if (!$isnew)
			{
				foreach ($model->get('categories') as $item_cat)
				{
					if (isset($featured_tree[$item_cat]) && !isset($disabled_cats[$item_cat]))
					{
						$featured_cid[] = $item_cat;
					}
				}
			}

			$data['featured_cid'] = $featured_cid;
		}


		/**
		 * Enforce maintaining secondary categories if user is not allowed to change / set secondary cats
		 * or (FE only) if these were not submitted
		 * *** NOTE *** this DOES NOT ENFORCE SUBMIT MENU category configuration, this is done later by the model store()
		 */

		// (FE) No category override active, and no secondary cats were submitted
		$cid_not_submitted = $app->isClient('administrator') ? true : !$overridecatperms && empty($data['cid']);

		if (!$enable_cid_selector && $cid_not_submitted)
		{
			// For new item use default secondary categories from type configuration
			if ($isnew)
			{
				$data['cid'] = $params->get('cid_default');
			}

			// Use featured cats if these are set
			elseif (isset($featured_cid))
			{
				$featured_cid_arr = array_flip($featured_cid);
				$sec_cid = array();

				foreach ($model->get('cats') as $item_cat)
				{
					if (!isset($featured_cid_arr[$item_cat]))
					{
						$sec_cid[] = $item_cat;
					}
				}

				$data['cid'] = $sec_cid;
			}

			// Use already assigned categories (existing item)
			else
			{
				$data['cid'] = $model->get('cats');
			}
		}


		/**
		 * Enforce maintaining main category if user is not allowed to change / set main category
		 * or (FE only) if this was not submitted
		 * *** NOTE *** this DOES NOT ENFORCE SUBMIT MENU category configuration, this is done later by the model store()
		 */

		// (FE) No category override active, and no main category was submitted
		$catid_not_submitted = $app->isClient('administrator') ? true : !$overridecatperms && empty($data['catid']);

		if (!$enable_catid_selector && $catid_not_submitted)
		{
			// For new item use default main category from type configuration
			if ($isnew && $params->get('catid_default'))
			{
				$data['catid'] = $params->get('catid_default');
			}

			// Use already assigned main category (existing item)
			elseif ($model->get('catid'))
			{
				$data['catid'] = $model->get('catid');
			}
		}

		// These need to be an array during validation
		if (!isset($data['rules']) || !is_array($data['rules']))
		{
			$data['rules'] = array();
		}


		/**
		 * Basic Form data validation
		 */

		// Get the JForm object, but do not pass any data we only want the form object,
		// in order to validate the data and not create a filled-in form
		$form = $model->getForm();


		/**
		 * Check custom-injected (non-JForm field) (frontend) captcha field
		 */
		if ($app->isClient('site'))
		{
			$use_captcha    = $params->get('use_captcha', 1);     // 1 for guests, 2 for any user
			$captcha_formop = $params->get('captcha_formop', 0);  // 0 for submit, 1 for submit/edit (aka always)
			$is_submitop = ((int) $data['id']) == 0;
			$display_captcha = $use_captcha >= 2 || ( $use_captcha == 1 &&  $user->guest );
			$display_captcha = $display_captcha && ( $is_submitop || $captcha_formop);  // for submit operation we do not need to check 'captcha_formop' ...
			if ($display_captcha)
			{
				$c_plugin = $params->get('captcha', $app->getCfg('captcha')); // TODO add param to override default
				if ($c_plugin)
				{
					$c_name = 'captcha_response_field';
					$c_value = $this->input->get($c_name, '', 'string');
					$c_id = $c_plugin=='recaptcha' ? 'dynamic_recaptcha_1' : 'fc_dynamic_captcha';
					$c_namespace = 'fc_item_form';

					$captcha_obj = JCaptcha::getInstance($c_plugin, array('namespace' => $c_namespace));
					if (!$captcha_obj->checkAnswer($c_value))
					{
						// Get the captch validation message and push it out to the user
						//$error = $captcha_obj->getError();
						//$app->enqueueMessage($error instanceof Exception ? $error->getMessage() : $error, 'error');
						$app->enqueueMessage(JText::_('FLEXI_CAPTCHA_FAILED') .' '. JText::_('FLEXI_MUST_REFILL_SOME_FIELDS'), 'error');

						// Set the POSTed form data into the session, so that they get reloaded
						$app->setUserState($form->option.'.edit.item.data', $data);      // Save the jform data in the session.
						$app->setUserState($form->option.'.edit.item.custom', $custom);  // Save the custom fields data in the session.
						$app->setUserState($form->option.'.edit.item.jfdata', $jfdata);  // Save the falang translations into the session
						$app->setUserState($form->option.'.edit.item.unique_tmp_itemid', $unique_tmp_itemid);  // Save temporary unique item id into the session

						// Captcha error, reload edit form using referer URL
						$this->setRedirect($this->refererURL);

						if ($this->input->get('fc_doajax_submit'))
						{
							jexit(flexicontent_html::get_system_messages_html());
						}
						else
						{
							return false;
						}
					}
				}
			}
		}

		// Validate Form data (record properties and parameters specified in XML file)
		$validated_data = $model->validate($form, $data);


		/**
		 * Perform validation / manipulation of the already validated data,
		 * run this even if validation failed, in case we want to handle this case too
		 */
		$extraChecks = $this->_afterModelValidation($validated_data, $data, $model);


		/**
		 * Redirect on validation errors or on other checks failing
		 */
		if (!$validated_data || !$extraChecks)
		{
			// Check for validation errors
			if (!$validated_data)
			{
				// Get the validation messages and push up to three validation messages out to the user
				$errors	= $form->getErrors();

				for ($i = 0, $n = count($errors); $i < $n && $i < 3; $i++)
				{
					$app->enqueueMessage($errors[$i] instanceof Exception ? $errors[$i]->getMessage() : $errors[$i], 'error');
				}
			}

			// Check for errors in after-validation handler
			if (!$extraChecks)
			{
				$app->enqueueMessage($model->getError() ?: JText::_('FLEXI_ERROR_SAVING_' . $this->_NAME), 'error');
			}

			// Set the POSTed form data into the session, so that they get reloaded
			$app->setUserState($form->option . '.edit.' . $form->context . '.data', $data);      // Save the jform data in the session
			$app->setUserState($form->option . '.edit.' . $form->context . '.custom', $custom);  // Save the custom fields data in the session
			$app->setUserState($form->option . '.edit.' . $form->context . '.jfdata', $jfdata);  // Save the falang translations into the session
			$app->setUserState($form->option . '.edit.' . $form->context . '.unique_tmp_itemid', $unique_tmp_itemid);  // Save temporary unique item id into the session

			// Validation error, reload edit form using referer URL
			$this->setRedirect($this->refererURL);

			if ($this->input->get('fc_doajax_submit'))
			{
				jexit(flexicontent_html::get_system_messages_html());
			}
			else
			{
				return false;
			}
		}

		// Validate jfdata using same JForm
		$validated_jf = array();

		foreach ($jfdata as $lang_index => $lang_data)
		{
			foreach ($lang_data as $i => $v)
			{
				$validated_jf[$lang_index][$i] = flexicontent_html::dataFilter($v, ($i != 'text' ? 4000 : 0), 2, 0);
			}
		}

		// Some values need to be assigned after validation
		$validated_data['custom']  = & $custom;          // Assign array of custom field values, they are in the 'custom' form array instead of jform (validation will follow at each field)
		$validated_data['jfdata']  = & $validated_jf;    // Assign array of Joomfish field values, they are in the 'jfdata' form array instead of jform (validated above)

		// Assign template parameters of the select ilayout as an sub-array (the DB model will handle the merging of parameters)
		// Always be set in backend, but usually not in frontend, if frontend template editing is not shown
		if ($app->isClient('administrator'))
		{
			$ilayout = $data['attribs']['ilayout'];
		}
		else
		{
			$ilayout = @ $data['attribs']['ilayout'];
		}

		// Give UNVALIDATED data for the case of LAYOUTS to the MODEL. Model will load the
		// XML file of the layout into a JForm object and do validation before merging them
		if ($ilayout && !empty($data['layouts'][$ilayout]))
		{
			$validated_data['attribs']['layouts'] = $data['layouts'];
		}

		// USEFULL FOR DEBUGING (do not remove commented code)
		// $diff_arr = array_diff_assoc ( $data, $validated_data);
		// echo "<pre>"; print_r($diff_arr); jexit();


		/**
		 * PERFORM ACCESS CHECKS, NOTE: we need to check access again, despite having
		 * checked them on edit form load, because user may have tampered with the form ...
		 */

		$itemAccess = $model->getItemAccess();
		$canAdd  = $itemAccess->get('access-create');  // Includes check of creating in at least one category
		$canEdit = $itemAccess->get('access-edit');    // includes privileges edit and edit-own

		$type_id = (int) $validated_data['type_id'];

		// Existing item with Type not being ALTERED, content type can be maintained regardless of privilege
		if (!$isnew && $model->get('type_id') == $type_id)
		{
			$canCreateType = true;
		}

		// New item or existing item with Type is being ALTERED, check privilege to create items of this type
		else
		{
			$canCreateType = $model->canCreateType(array($type_id), true, $types);
		}


		/**
		 * Calculate user's CREATE / EDIT privileges on current content item
		 */

		$hasCoupon = false;  // Normally used in frontend only

		if (!$isnew)
		{
			if (!$canEdit)
			{
				// No edit privilege, check if item is editable till logoff
				if ($session->has('rendered_uneditable', 'flexicontent'))
				{
					$rendered_uneditable = $session->get('rendered_uneditable', array(), 'flexicontent');
					$canEdit = isset($rendered_uneditable[$model->get('id')]) && $rendered_uneditable[$model->get('id')];
					$hasCoupon = isset($rendered_uneditable[$model->get('id')]) && $rendered_uneditable[$model->get('id')] == 2;  // Editable via coupon
				}
			}
		}

		// Special CASEs of overriding CREATE ACL in FrontEnd via menu item
		elseif ($app->isClient('site'))
		{
			// Allow creating via submit menu OVERRIDE
			if ($allowunauthorize)
			{
				$canAdd = true;
				$canCreateType = true;
			}

			// If without create privelege and category override is enabled then only check type and do not check category ACL
			elseif (!$canAdd)
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

			if ($this->input->get('fc_doajax_submit'))
			{
				jexit(flexicontent_html::get_system_messages_html());
			}
			else
			{
				return false;
			}
		}

		// Existing item: Check if user can edit current item
		if (!$isnew && !$canEdit)
		{
			$app->enqueueMessage(JText::_('FLEXI_NO_ACCESS_EDIT'), 'error');
			$app->setHeader('status', 403, true);
			$this->setRedirect($this->returnURL);

			if ($this->input->get('fc_doajax_submit'))
			{
				jexit(flexicontent_html::get_system_messages_html());
			}
			else
			{
				return false;
			}
		}

		if (!$canCreateType)
		{
			$msg = isset($types[$type_id])
				? JText::sprintf('FLEXI_NO_ACCESS_CREATE_CONTENT_OF_TYPE', JText::_($types[$type_id]->name))
				: ' Content Type ' . $type_id . ' was not found OR is not published';

			$app->enqueueMessage($msg, 'error');
			$app->setHeader('status', 403, true);
			$this->setRedirect($this->returnURL);

			if ($this->input->get('fc_doajax_submit'))
			{
				jexit(flexicontent_html::get_system_messages_html());
			}
			else
			{
				return false;
			}
		}

		// Get "BEFORE SAVE" categories for information mail
		$before_cats = array();

		if (!$isnew)
		{
			$query 	= 'SELECT DISTINCT c.id, c.title FROM #__categories AS c'
				. ' JOIN #__flexicontent_cats_item_relations AS rel ON rel.catid = c.id'
				. ' WHERE rel.itemid = ' . (int) $model->get('id');
			$before_cats = $db->setQuery($query)->loadObjectList('id');
			$before_maincat = $model->get('catid');
		}


		/**
		 * Try to store the form data into the item
		 */

		// If saving fails, do any needed cleanup, and then redirect back to record form
		if (!$model->store($validated_data))
		{
			if (empty($model->abort_redirect_url))
			{
				// Set the POSTed form data into the session, so that they get reloaded
				$app->setUserState($form->option . '.edit.' . $form->context . '.data', $data);      // Save the jform data in the session
				$app->setUserState($form->option . '.edit.' . $form->context . '.custom', $custom);  // Save the custom fields data in the session
				$app->setUserState($form->option . '.edit.' . $form->context . '.jfdata', $jfdata);  // Save the falang translations into the session
				$app->setUserState($form->option . '.edit.' . $form->context . '.unique_tmp_itemid', $unique_tmp_itemid);  // Save temporary unique item id into the session
			}

			// Set error message and the redirect URL (back to the record form)
			$app->setHeader('status', '500 Internal Server Error', true);
			$this->setError($model->getError() ?: JText::_('FLEXI_ERROR_SAVING_' . $this->_NAME));
			$this->setMessage($this->getError(), 'error');

			// Skip redirection back to return url if inside a component-area-only view, showing error using current page, since usually we are inside a iframe modal
			if ($this->input->getCmd('tmpl') !== 'component')
			{
				$this->setRedirect(!empty($model->abort_redirect_url) ? $model->abort_redirect_url: $this->returnURL);
			}

			// Try to check-in the record, but ignore any new errors
			try
			{
				!$isnew ? $model->checkin() : true;
			}
			catch (Exception $e)
			{
			}

			if ($this->input->get('fc_doajax_submit'))
			{
				jexit(flexicontent_html::get_system_messages_html());
			}
			else
			{
				return false;
			}
		}

		// Clear dependent cache data
		$this->_cleanCache();

		// Check in the record and get record id in case of new item
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


		/**
		 * Get newly saved -latest- version (store task gets latest) of the item, and also calculate publish privelege
		 */
		$item = $model->getItem($validated_data['id'], $check_view_access = false, $no_cache = true, $force_version = -1);
		$canPublish = $model->canEditState($item) || $hasCoupon;


		/**
		 * Use session to detect multiple item saves to avoid sending notification EMAIL multiple times
		 */
		$is_first_save = true;

		if ($session->has('saved_fcitems', 'flexicontent'))
		{
			$saved_fcitems = $session->get('saved_fcitems', array(), 'flexicontent');
			$is_first_save = $isnew ? true : !isset($saved_fcitems[$model->get('id')]);
		}

		// Add item to saved items of the corresponding session array
		$saved_fcitems[$model->get('id')] = $timestamp = time();  // Current time as seconds since Unix epoc;
		$session->set('saved_fcitems', $saved_fcitems, 'flexicontent');


		/**
		 * Get categories added / removed from the item
		 */
		$query 	= 'SELECT DISTINCT c.id, c.title FROM #__categories AS c'
			. ' JOIN #__flexicontent_cats_item_relations AS rel ON rel.catid = c.id'
			. ' WHERE rel.itemid = ' . (int) $model->get('id');
		$after_cats = $db->setQuery($query)->loadObjectList('id');

		if (!$isnew)
		{
			$cats_added_ids = array_diff(array_keys($after_cats), array_keys($before_cats));

			foreach ($cats_added_ids as $cats_added_id)
			{
				$cats_added_titles[] = $after_cats[$cats_added_id]->title;
			}

			$cats_removed_ids = array_diff(array_keys($before_cats), array_keys($after_cats));

			foreach ($cats_removed_ids as $cats_removed_id)
			{
				$cats_removed_titles[] = $before_cats[$cats_removed_id]->title;
			}

			$cats_altered = count($cats_added_ids) + count($cats_removed_ids);
			$after_maincat = $model->get('catid');
		}


		/**
		 * We need to get emails to notify, from Global/item's Content Type parameters -AND- from item's categories parameters
		 */
		$notify_emails = array();

		if ($is_first_save || $cats_altered || $params->get('nf_enable_debug', 0))
		{
			// Get needed flags regarding the saved items
			$approve_version = 2;
			$pending_approval_state = -3;
			$draft_state = -4;

			$current_version = FLEXIUtilities::getCurrentVersions($item->id, true); // Get current item version
			$last_version    = FLEXIUtilities::getLastVersions($item->id, true);    // Get last version (=latest one saved, highest version id),

			// $validated_data variables vstate & state may have been (a) tampered in the form, and/or (b) altered by save procedure so better not use them
			$needs_version_reviewal     = !$isnew && ($last_version > $current_version) && !$canPublish && !$AutoApproveChanges;
			$needs_publication_approval = $isnew && ($item->state == $pending_approval_state) && !$canPublish;

			$draft_from_non_publisher = $item->state == $draft_state && !$canPublish;

			if ($draft_from_non_publisher)
			{
				// Suppress notifications for draft-state items (new or existing ones), for these each author will publication approval manually via a button
				$nConf = false;
			}
			else
			{
				// Get notifications configuration and select appropriate emails for current saving case
				$nConf = $model->getNotificationsConf($params);  // echo "<pre>"; print_r($nConf); "</pre>";
			}

			if ($nConf)
			{
				$states_notify_new = $params->get('states_notify_new', array(1, 0, 2, -3, -4, -5));

				if (empty($states_notify_new))
				{
					$states_notify_new = array();
				}

				elseif (! is_array($states_notify_new))
				{
					$states_notify_new = !FLEXI_J16GE ? array($states_notify_new) : explode("|", $states_notify_new);
				}

				$states_notify_existing = $params->get('states_notify_existing', array(1, 0, 2, -3, -4, -5));

				if (empty($states_notify_existing))
				{
					$states_notify_existing = array();
				}

				elseif (! is_array($states_notify_existing))
				{
					$states_notify_existing = !FLEXI_J16GE ? array($states_notify_existing) : explode("|", $states_notify_existing);
				}

				$n_state_ok = in_array($item->state, $states_notify_new);
				$e_state_ok = in_array($item->state, $states_notify_existing);

				if ($needs_publication_approval)
				{
					$notify_emails = $nConf->emails->notify_new_pending;
				}
				elseif ($isnew && $n_state_ok)
				{
					$notify_emails = $nConf->emails->notify_new;
				}
				elseif ($isnew)
				{
					$notify_emails = array();
				}
				elseif ($needs_version_reviewal)
				{
					$notify_emails = $nConf->emails->notify_existing_reviewal;
				}
				elseif (!$isnew && $e_state_ok)
				{
					$notify_emails = $nConf->emails->notify_existing;
				}
				elseif (!$isnew)
				{
					$notify_emails = array();
				}

				if ($needs_publication_approval)
				{
					$notify_text = $params->get('text_notify_new_pending');
				}
				elseif ($isnew)
				{
					$notify_text = $params->get('text_notify_new');
				}
				elseif ($needs_version_reviewal)
				{
					$notify_text = $params->get('text_notify_existing_reviewal');
				}
				elseif (!$isnew)
				{
					$notify_text = $params->get('text_notify_existing');
				}

				// print_r($notify_emails); jexit();
			}
		}


		/**
		 * If there are emails to notify for current saving case, then send the notifications emails, but
		 */

		if (!empty($notify_emails))
		{
			$notify_vars = new stdClass;
			$notify_vars->needs_version_reviewal     = $needs_version_reviewal;
			$notify_vars->needs_publication_approval = $needs_publication_approval;
			$notify_vars->isnew         = $isnew;
			$notify_vars->notify_emails = $notify_emails;
			$notify_vars->notify_text   = $notify_text;
			$notify_vars->before_cats   = $before_cats;
			$notify_vars->after_cats    = $after_cats;
			$notify_vars->original_item = $record;

			$model->sendNotificationEmails($notify_vars, $params, $manual_approval_request = 0);
		}


		/**
		 * Recalculate EDIT PRIVILEGE of new item. Reason for needing to do this is because we can have create permission in a category
		 * and thus being able to set this category as item's main category, but then have no edit/editown permission for this category
		 */

		$asset = 'com_content.article.' . $model->get('id');
		$canEdit = $user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && $isOwner);


		/**
		 * Check if user can not edit item further (due to changed main category, without edit/editown permission)
		 */

		if (!$canEdit)
		{
			// APPLY TASK: Temporarily set item to be editable till closing it and not through all session
			// (we will/should clear this flag when item is closed, since we have another flag to indicate new items
			if ($this->task === 'apply' || $this->task === 'apply_type')
			{
				$rendered_uneditable = $session->get('rendered_uneditable', array(), 'flexicontent');
				$rendered_uneditable[$model->get('id')] = -1;
				$session->set('rendered_uneditable', $rendered_uneditable, 'flexicontent');
				$canEdit = 1;
			}

			// NEW ITEM: Do not use editable till logoff behaviour
			// ALSO: Clear editable FLAG set in the case that 'apply' button was used during new item creation
			elseif ($newly_submitted_item)
			{
				if (!$params->get('items_session_editable', 0))
				{
					$rendered_uneditable = $session->get('rendered_uneditable', array(), 'flexicontent');

					if (isset($rendered_uneditable[$model->get('id')]))
					{
						unset($rendered_uneditable[$model->get('id')]);
						$session->set('rendered_uneditable', $rendered_uneditable, 'flexicontent');
					}
				}
			}

			// EXISTING ITEM: (if enabled) Use the editable till logoff behaviour
			else
			{
				if ($params->get('items_session_editable', 0))
				{
					// Set notice for existing item being editable till logoff
					$app->enqueueMessage(JText::_('FLEXI_CANNOT_EDIT_AFTER_LOGOFF'), 'notice');

					// Allow item to be editable till logoff
					$rendered_uneditable = $session->get('rendered_uneditable', array(), 'flexicontent');
					$rendered_uneditable[$model->get('id')]  = 1;
					$session->set('rendered_uneditable', $rendered_uneditable, 'flexicontent');
					$canEdit = 1;
				}
			}

			// Set notice about saving an item that cannot be changed further
			if (!$canEdit)
			{
				$app->enqueueMessage(JText::_('FLEXI_CANNOT_MAKE_FURTHER_CHANGES_TO_CONTENT'), 'notice');
			}
		}


		/**
		 * Check for new Content Item is being closed, and clear some flags
		 */

		if ($this->task != 'apply' && $this->task != 'apply_type' && $newly_submitted_item)
		{
			// Clear item from being marked as newly submitted
			unset($newly_submitted[$model->get('id')]);
			$session->set('newly_submitted', $newly_submitted, 'flexicontent');

			// The 'apply' task may set 'editable till logoff' FLAG ...
			// CLEAR IT, since NEW content this is meant to be used temporarily
			if (!$params->get('items_session_editable', 0))
			{
				$rendered_uneditable = $session->get('rendered_uneditable', array(), 'flexicontent');

				if (isset($rendered_uneditable[$model->get('id')]))
				{
					unset($rendered_uneditable[$model->get('id')]);
					$session->set('rendered_uneditable', $rendered_uneditable, 'flexicontent');
				}
			}
		}


		/**
		 * Saving is done, decide where to redirect
		 */

		$msg = JText::_('FLEXI_' . $this->_NAME . '_SAVED');

		switch ($this->task)
		{
			// REDIRECT CASE FOR APPLY / SAVE AS COPY: Save and reload the edit form
			case 'apply':
			case 'apply_type':
				if ($app->isClient('administrator'))
				{
					$link = 'index.php?option=com_flexicontent&' . $ctrl_task . 'edit&view=' . $this->record_name . '&id=' . (int) $model->get('id');
				}
				else
				{
					// Create the URL, maintain current menu item if this was given
					$Itemid = $this->input->get('Itemid', 0, 'int');
					$item_url = JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug, $Itemid));

					// Set task to 'edit', and pass original referer back to avoid making the form itself the referer, but also check that it is safe enough
					$link = $item_url
						. ( strstr($item_url, '?') ? '&' : '?' ) . 'task=edit'
						. '&return='.base64_encode($this->returnURL);
				}
				break;

			// REDIRECT CASE FOR SAVE and NEW: Save and load new record form
			case 'save2new':
				if ($app->isClient('administrator'))
				{
					$link = 'index.php?option=com_flexicontent&view=' . $this->record_name
						. '&typeid=' . $model->get('type_id')
						. '&filter_cats=' . $model->get('catid');
				}
				else
				{
					// Create the URL, maintain current menu item if this was given
					$Itemid = $this->input->get('Itemid', 0, 'int');
					$item_url = 'index.php?option=com_flexicontent&view=item&task=add' .
						'&typeid=' . $model->get('type_id') .
						'&maincat=' . $model->get('catid') .
						'&Itemid=' . $Itemid .
						'&return='.base64_encode($this->returnURL);

					// Set task to 'edit', and pass original referer back to avoid making the form itself the referer, but also check that it is safe enough
					$link = JRoute::_($item_url, false);
				}
				break;

			// REDIRECT CASES FOR SAVING
			default:
				if ($app->isClient('administrator'))
				{
					$link = $this->returnURL;
				}

				// REDIRECT CASE: Return to a custom page after creating a new item (e.g. a thanks page)
				elseif ($newly_submitted_item && $submit_redirect_url_fe)
				{
					$link = $submit_redirect_url_fe;
				}

				// REDIRECT CASE: Save and preview the latest version
				elseif ($this->task === 'save_a_preview')
				{
					$link = JRoute::_(FlexicontentHelperRoute::getItemRoute($model->get('id') . ':' . $model->get('alias'), $model->get('catid'), 0, $model->_item) . '&amp;preview=1', false);
				}

				// REDIRECT CASE: Return to the form 's original referer after item saving
				else
				{
					$msg = $newly_submitted_item
						? JText::_('FLEXI_THANKS_SUBMISSION')
						: JText::_('FLEXI_ITEM_SAVED');
					$link = $this->returnURL;
				}
				break;
		}

		$app->enqueueMessage($msg, 'message');
		$this->setRedirect($link);

		// return;  // comment above and decomment this one to profile the saving operation

		if ($this->input->get('fc_doajax_submit'))
		{
			jexit(flexicontent_html::get_system_messages_html());
		}
	}


	/**
	 * Logic to order up/down a record
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function reorder($dir = null)
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		$app   = JFactory::getApplication();
		$model = $this->getModel($this->record_name_pl);
		$user  = JFactory::getUser();

		// Calculate ACL access
		$is_authorised = $user->authorise('flexicontent.orderitems', 'com_flexicontent');

		// Check access
		if (!$is_authorised)
		{
			$app->setHeader('status', '403 Forbidden', true);
			$app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');
			$app->redirect($this->returnURL);
		}

		// Get record id and ordering group
		$cid         = $this->input->get('cid', array(0), 'array');
		$filter_cats = $this->input->get('filter_cats', array(0), 'array');

		$cid = ArrayHelper::toInteger($cid);
		$filter_cats = ArrayHelper::toInteger($filter_cats);

		// Make sure direction is set
		$dir = $dir ?: ($this->task === 'orderup' ? -1 : 1);

		if (!$model->move($dir, reset($filter_cats)))
		{
			$app->setHeader('status', '500 Internal Server Error', true);
			$app->enqueueMessage(JText::_('FLEXI_ERROR_SAVING_ORDER') . ': ' . $model->getError(), 'error');
			$app->redirect($this->returnURL);
		}

		// Note we no longer set the somewhat redundant message: JText::_('FLEXI_NEW_ORDERING_SAVED')
		$this->setRedirect($this->returnURL);
	}


	/**
	 * Logic to orderup a record, wrapper for reorder method
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function orderup()
	{
		$this->reorder($dir = -1);
	}


	/**
	 * Logic to orderdown a record, wrapper for reorder method
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function orderdown()
	{
		$this->reorder($dir = 1);
	}


	/**
	 * Logic to mass order records
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function saveorder()
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		$app   = JFactory::getApplication();
		$model = $this->getModel($this->record_name_pl);
		$user  = JFactory::getUser();

		// Calculate ACL access
		$is_authorised = $user->authorise('flexicontent.orderitems', 'com_flexicontent');

		// Check access
		if (!$is_authorised)
		{
			$app->setHeader('status', 403);
			$app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');
			$app->redirect($this->returnURL);
		}

		// Get record ids, new orderings and the ordering group
		$cid         = $this->input->get('cid', array(0), 'array');
		$order       = $this->input->get('order', array(0), 'array');
		$filter_cats = $this->input->get('filter_cats', array(0), 'array');

		$cid = ArrayHelper::toInteger($cid);
		$order = ArrayHelper::toInteger($order);
		$filter_cats = ArrayHelper::toInteger($filter_cats);

		if (!$model->saveorder($cid, $order, reset($filter_cats)))
		{
			$app->setHeader('status', 500);
			$app->enqueueMessage(JText::_('FLEXI_ERROR_SAVING_ORDER') . ': ' . $model->getError(), 'error');
			$app->redirect($this->returnURL);
		}

		// Note we no longer set the somewhat redundant message: JText::_('FLEXI_NEW_ORDERING_SAVED')
		$this->setRedirect($this->returnURL);
	}


	/**
	 * Logic to handle batch actions on records: copy / move / update
	 *
	 * @return void
	 *
	 * @since 1.5
	 */
	public function batchprocess()
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		$app   = JFactory::getApplication();
		$db    = JFactory::getDbo();
		$model = $this->getModel($this->record_name_pl);
		$user  = JFactory::getUser();

		$cid = $this->input->get('cid', array(), 'array');
		$cid = ArrayHelper::toInteger($cid);

		// Check at least one item was selected
		if (!count($cid))
		{
			$app->setHeader('status', '500', true);
			$app->enqueueMessage(JText::_('FLEXI_NO_ITEMS_SELECTED'), 'error');
			$this->setRedirect($this->returnURL);

			return;
		}

		// Calculate access of copyitems task
		$canCopy = $user->authorise('flexicontent.copyitems', 'com_flexicontent');

		// Check access
		if (!$canCopy)
		{
			$app->setHeader('status', 403);
			$app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');
			$this->setRedirect($this->returnURL);

			return false;
		}

		$method   = $this->input->get('method', 1, 'int');
		$keeepcats = $this->input->get('keeepcats', 1, 'int');
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
		$copytask_allow_uneditable = JComponentHelper::getParams('com_flexicontent')->get('copytask_allow_uneditable', 1);

		if (!$copytask_allow_uneditable || $method == 2)  // If method is 2 (move) we will deny moving uneditable items
		{
			// Remove uneditable items
			$auth_cid = array();
			$non_auth_cid = array();

			// Get record owner and other record data
			$q = $this->_getRecordsQuery($cid, array('id', 'created_by', 'catid'));
			$itemdata = $db->setQuery($q)->loadObjectList('id');

			// Check authorization for edit operation
			foreach ($cid as $id)
			{
				$isOwner = $itemdata[$id]->created_by == $user->id;
				$asset = 'com_content.article.' . $id;
				$canEdit = $user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && $isOwner);

				if ($canEdit)
				{
					$auth_cid[] = $id;
				}
				else
				{
					$non_auth_cid[] = $id;
				}
			}

			// echo "<pre>"; echo "authorized:\n"; print_r($auth_cid); echo "\n\nNOT authorized:\n"; print_r($non_auth_cid); echo "</pre>"; exit;
		}
		else
		{
			$auth_cid = & $cid;
			$non_auth_cid = array();
		}

		// Set warning for uneditable items
		if (count($non_auth_cid))
		{
			$msg_noauth = JText::_('FLEXI_CANNOT_COPY_ASSETS') . ' ' . JText::_('FLEXI_REASON_NO_EDIT_PERMISSION')
				. '<br>' . JText::_('FLEXI_ROWS_SKIPPED') . ' : '. implode(',', $non_auth_cid);
			$app->enqueueMessage($msg_noauth, 'warning');

			if (!count($auth_cid))  // Cancel task if no items can be copied
			{
				$this->setRedirect($this->returnURL);

				return false;
			}
		}

		// Set only authenticated item ids for the copyitems() method
		$auth_cid = $cid;
		$clean_cache_flag = false;


		/**
		 * Execute batch
		 */
		switch ($method)
		{
			// Copy CASE
			case 1:
				if ($model->copyitems($auth_cid, $keeptags, $prefix, $suffix, $copynr, $lang, $state))
				{
					$msg = JText::sprintf('FLEXI_ITEMS_COPY_SUCCESS', count($auth_cid));
					$clean_cache_flag = true;
				}
				else
				{
					$app->setHeader('status', 500);
					$app->enqueueMessage(JText::_('FLEXI_ERROR_COPY_ITEMS') . " " . $model->getError(), 'error');
					$msg = '';
				}
				break;

			// Update CASE (optionally moving)
			case 2:
				$msg = JText::sprintf('FLEXI_ITEMS_MOVE_SUCCESS', count($auth_cid));

				foreach ($auth_cid as $itemid)
				{
					if (!$model->moveitem($itemid, $maincat, $seccats, $lang, $state, $type_id, $access))
					{
						$msg = JText::_('FLEXI_ERROR_MOVE_ITEMS');
						JError::raiseWarning(500, $msg . " " . $model->getError());
						$msg = '';
					}
				}

				$clean_cache_flag = true;
				break;

			// Copy and update CASE (optionally moving)
			default:
				if ($model->copyitems($auth_cid, $keeptags, $prefix, $suffix, $copynr, $lang, $state, $method, $maincat, $seccats, $type_id, $access))
				{
					$msg = JText::sprintf('FLEXI_ITEMS_COPYMOVE_SUCCESS', count($auth_cid));
					$clean_cache_flag = true;
				}
				else
				{
					$msg = JText::_('FLEXI_ERROR_COPYMOVE_ITEMS');
					JError::raiseWarning(500, $msg . " " . $model->getError());
					$msg = '';
				}
				break;
		}

		$link = 'index.php?option=com_flexicontent&view=items';

		// CLEAN THE CACHE so that our changes appear realtime
		if ($clean_cache_flag)
		{
			$this->_cleanCache();
		}

		$this->setRedirect($link, $msg);
	}


	/**
	 * Check in a record
	 *
	 * @since	3.3
	 */
	public function checkin()
	{
		parent::checkin();
	}


	/**
	 * Cancel the edit, check in the record and return to the records manager
	 *
	 * @return bool
	 *
	 * @since 3.3
	 */
	public function cancel()
	{
		return parent::cancel();
	}


	/**
	 * Import Joomla com_content datas
	 *
	 * @return void
	 *
	 * @since 1.5
	 */
	public function import()
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		$user  = JFactory::getUser();
		$model = $this->getModel($this->record_name_pl);

		$permission = FlexicontentHelperPerm::getPerm();
		$canImport = $permission->CanConfig;

		if (!$canImport)
		{
			echo JText::_('ALERTNOTAUTH');

			return;
		}

		$logs = $model->import();
		$cache = FLEXIUtilities::getCache($group = '', 0);
		$cache->clean('com_flexicontent_cats');
		$cache = FLEXIUtilities::getCache($group = '', 1);
		$cache->clean('com_flexicontent_cats');

		$msg  = JText::_('FLEXI_IMPORT_SUCCESSFUL');
		$msg .= '<ul class="import-ok">';

		if (!FLEXI_J16GE)
		{
			$msg .= '<li>' . $logs->sec . ' ' . JText::_('FLEXI_IMPORT_SECTIONS') . '</li>';
		}

		$msg .= '<li>' . $logs->cat . ' ' . JText::_('FLEXI_IMPORT_CATEGORIES') . '</li>';
		$msg .= '<li>' . $logs->art . ' ' . JText::_('FLEXI_IMPORT_ARTICLES') . '</li>';
		$msg .= '</ul>';

		if (isset($logs->err))
		{
			$msg .= JText::_('FLEXI_IMPORT_FAILED');
			$msg .= '<ul class="import-failed">';

			foreach ($logs->err as $err)
			{
				$msg .= '<li>' . $err->type . ' ' . $err->id . ': ' . $err->title . '</li>';
			}

			$msg .= '</ul>';
		}
		else
		{
			$msg .= JText::_('FLEXI_IMPORT_NO_ERROR');
		}

			$msg .= '<p class="button-close"><input type="button" class="fc_button" onclick="window.parent.document.adminForm.submit();" value="' . JText::_('FLEXI_CLOSE_REFRESH_DASHBOARD') . '" /><p>';

		echo $msg;
	}


	/**
	 * Logic to publish records
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function publish()
	{
		parent::publish();
	}


	/**
	 * Logic to unpublish records
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function unpublish()
	{
		parent::unpublish();
	}


	/**
	 * Method to toggle the featured setting of a list of records
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
		$value  = ArrayHelper::getValue($values, $this->task, 0, 'int');

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
			$app->setHeader('status', '500', true);
			$app->enqueueMessage(JText::_('FLEXI_NO_ITEMS_SELECTED'), 'error');
			$this->setRedirect($this->returnURL);

			return;
		}

		// Get the model.
		$record_model = $this->getModel($this->record_name);

		// Update featured flag (model will also handle cache cleaning)
		if (!$record_model->featured($cid, $value))
		{
			$app->enqueueMessage($record_model->getError(), 'error');
			$app->redirect($this->returnURL);
		}

		$message = $value == 1
			? JText::plural('COM_CONTENT_N_ITEMS_FEATURED', count($cid))
			: JText::plural('COM_CONTENT_N_ITEMS_UNFEATURED', count($cid));
		$this->setRedirect($this->returnURL, $message);
	}


	/**
	 * Logic to modify the state of records, other state modifications tasks are wrappers to this task
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function changestate($state = null)
	{
		return parent::changestate($state);
	}


	/**
	 * Logic to submit item to approval
	 *
	 * @return void
	 *
	 * @since 1.5
	 */
	function approval()
	{
		// Check for request forgeries
		JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

		$app   = JFactory::getApplication();

		$cid = $this->input->get('cid', array(), 'array');
		$cid = ArrayHelper::toInteger($cid);

		if (!count($cid))
		{
			$app->enqueueMessage(JText::_('FLEXI_APPROVAL_SELECT_ITEM_SUBMIT'), 'error');
			$app->redirect($this->returnURL);
		}

		// Approve item(s) (model will also handle cache cleaning)
		$record_model = $this->getModel($this->record_name);
		$msg = $record_model->approval($cid);

		$this->setRedirect($this->returnURL, $msg);
	}


	/**
	 * Logic to delete records
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function remove()
	{
		parent::remove();
	}


	/**
	 * logic for restore an old version
	 *
	 * @return void
	 *
	 * @since 1.5
	 */
	function restore()
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		$id			= $this->input->getInt('id', 0);
		$version	= $this->input->getInt('version', 0);
		$record_model = $this->getModel($this->record_name);

		// First checkin the open item
		$item = JTable::getInstance($this->records_jtable, '');
		$item->load($id);
		$item->checkin();

		if ($version)
		{
			$msg = JText::sprintf('FLEXI_VERSION_RESTORED', $version);
			$record_model->restore($version, $id);
		}
		else
		{
			$msg = JText::_('FLEXI_NOTHING_TO_RESTORE');
		}

		$ctrlTask = 'task=items.edit';
		$this->setRedirect('index.php?option=com_flexicontent&' . $ctrlTask . '&cid[]=' . $id, $msg);
	}


	/**
	 * Logic to create the view for record editing
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function edit()
	{
		$app      = JFactory::getApplication();
		$user     = JFactory::getUser();
		$session  = JFactory::getSession();
		$document = JFactory::getDocument();

		$this->input->set('view', $this->record_name);
		$this->input->set('hidemainmenu', 1);

		// Get/Create the view
		$viewType   = $document->getType();
		$viewName   = $this->input->get('view', $this->default_view, 'cmd');
		$viewLayout = $this->input->get('layout', $app->isClient('administrator') ? 'default' : 'form', 'string');
		$view = $this->getView($viewName, $viewType, '', array('base_path' => $this->basePath, 'layout' => $viewLayout));

		// Get/Create the model
		$model = $this->getModel($this->record_name);

		// Try to load review by attributes in HTTP Request
		if (0)
		{
			$record = $model->getRecord(array(
				$this->record_alias => '',
			));
		}

		// Try to load by unique ID or NAME
		else
		{
			$model->isForm = true;

			// Force model to load versioned data (URL specified version or latest version (last saved))
			$version = $this->input->get('version', 0, 'int');   // Load specific item version (non-zero), 0 version: is unversioned data, -1 version: is latest version (=default for edit form)
			$item = $model->getItem(null, $check_view_access = false, $no_cache = true, $force_version = ($version != 0 ? $version : -1));  // -1 version means latest

			if (!$item)
			{
				$app->setHeader('status', '404', true);
				$app->enqueueMessage(JText::_('FLEXI_OPERATION_FAILED') . ' : ' . $model->getError(), 'error');

				if ($this->input->getCmd('tmpl') !== 'component')
				{
					$this->setRedirect($this->returnURL);
				}

				return;
			}
		}

		// Push the model into the view (as default), later we will call the view display method instead of calling parent's display task, because it will create a 2nd model instance !!
		$view->setModel($model, true);
		$view->document = $document;

		$isnew  = !$model->getId();

		// Calculate access
		$canAdd  = $model->getItemAccess()->get('access-create');
		$canEdit = $model->getItemAccess()->get('access-edit');

		if (!$canEdit)
		{
			// No edit privilege, check if item is editable till logoff
			if ($session->has('rendered_uneditable', 'flexicontent'))
			{
				$rendered_uneditable = $session->get('rendered_uneditable', array(), 'flexicontent');
				$canEdit = isset($rendered_uneditable[$model->get('id')]) && $rendered_uneditable[$model->get('id')];
			}
		}

		// New item: check if user can create in at least one category
		if ($isnew)
		{
			// A. Check create privilege
			if (!$canAdd)
			{
				$app->setHeader('status', '403 Forbidden', true);
				$this->setRedirect($this->returnURL, JText::_('FLEXI_NO_ACCESS_CREATE'), 'error');

				$model->enqueueMessages($_exclude = array('showAfterLoad' => 1));

				return;
			}

			// Get User Group / Author parameters
			$db = JFactory::getDbo();
			$authorparams = flexicontent_db::getUserConfig($user->id);
			$max_auth_limit = intval($authorparams->get('max_auth_limit', 0));  // Maximum number of content items the user can create

			// B. Check if max authored content limit reached
			if ($max_auth_limit)
			{
				$db->setQuery('SELECT COUNT(id) FROM #__content WHERE created_by = ' . $user->id);
				$authored_count = $db->loadResult();

				if ($authored_count >= $max_auth_limit)
				{
					$app->setHeader('status', '403 Forbidden', true);
					$this->setRedirect($this->returnURL, JText::sprintf('FLEXI_ALERTNOTAUTH_CREATE_MORE', $max_auth_limit), 'warning');

					$model->enqueueMessages($_exclude = array('showAfterLoad' => 1));

					return;
				}
			}

			// C. Check if Content Type can be created by current user
			$typeid = $this->input->get('typeid', 0, 'int');
			$canCreateType = $typeid
				? $model->canCreateType(array($typeid), true, $types)  // Can create given Content Type
				: $model->canCreateType();  // Can create at least one Content Type

			if (!$canCreateType)
			{
				$type_name = isset($types[$$typeid]) ? '"' . JText::_($types[$$typeid]->name) . '"' : JText::_('FLEXI_ANY');
				$msg = JText::sprintf('FLEXI_NO_ACCESS_CREATE_CONTENT_OF_TYPE', $type_name);

				$app->setHeader('status', '403 Forbidden', true);
				$this->setRedirect($this->returnURL, $msg, 'error');

				$model->enqueueMessages($_exclude = array('showAfterLoad' => 1));

				return;
			}
		}

		// Existing item: Check if user can edit current item
		else
		{
			if (!$canEdit)
			{
				$app->setHeader('status', '403 Forbidden', true);
				$this->setRedirect($this->returnURL, JText::_('FLEXI_NO_ACCESS_EDIT'), 'error');

				$model->enqueueMessages($_exclude = array('showAfterLoad' => 1));

				return;
			}
		}

		// Check if record is checked out by other editor
		if ($model->isCheckedOut($user->get('id')))
		{
			$app->setHeader('status', '400 Bad Request', true);
			$app->enqueueMessage(JText::_('FLEXI_EDITED_BY_ANOTHER_ADMIN'), 'warning');

			if ($this->input->getCmd('tmpl') !== 'component')
			{
				$this->setRedirect($this->returnURL);
			}

			// Do not add messages meant only if form load succeeds
			$model->enqueueMessages($_exclude = array('showAfterLoad' => 1));

			return;
		}

		// Checkout the record and proceed to edit form
		if (!$model->checkout())
		{
			$app->setHeader('status', '400 Bad Request', true);
			$app->enqueueMessage(JText::_('FLEXI_OPERATION_FAILED') . ' : ' . $model->getError(), 'error');

			if ($this->input->getCmd('tmpl') !== 'component')
			{
				$this->setRedirect($this->returnURL);
			}

			// Do not add messages meant only if form load succeeds
			$model->enqueueMessages($_exclude = array('showAfterLoad' => 1));

			return;
		}

		// We succeeded, enqueue all minor model messages / notices
		$model->enqueueMessages();

		// Call display method of the view, instead of calling parent's display task, because it will create a 2nd model instance !!
		$view->display();
	}


	/**
	 * Logic to set the access level of the records
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function access()
	{
		parent::access();
	}


	/**
	 * Method to fetch the tags edit field for the edit form, this is currently NOT USED
	 *
	 * @since 1.5
	 */
	public function gettags()
	{
		// Check for request forgeries
		JSession::checkToken() or die(JText::_('JINVALID_TOKEN'));

		$id    = $this->input->get('id', 0, 'int');
		$record_model = $this->getModel($this->record_name);
		$tags  = $record_model->gettags();
		$user  = JFactory::getUser();

		// Get tag ids if non-new item
		$used = $id ? $record_model->getUsedtagsIds($id) : null;
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

			for ($i = 0, $n; $i < $n; $i++)
			{
				$tag = $tags[$i];

				if (!in_array($tag->id, $used))
				{
					continue; // Tag not assigned to item
				}

				if ($CanUseTags && in_array($tag->id, $used))
				{
					$html .= '
					<li class="tagitem">
						<span>' . $tag->name . '</span>
						<input type="hidden" name="jform[tag][]" value="' . $tag->tid . '" />
						<a href="javascript:;" class="deletetag" onclick="javascript:deleteTag(this);" title="' . JText::_('FLEXI_DELETE_TAG') . '"></a>
					</li>';
				}
				else
				{
					$html .= '
					<li class="tagitem plain">
						<span>' . $tag->name . '</span>
						<input type="hidden" name="jform[tag][]" value="' . $tag->tid . '" />
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
				<label for="addtags">' . JText::_('FLEXI_ADD_TAG') . '</label>
				<input type="text" id="tagname" class="inputbox" size="30" />
				<input type="button" class="fc_button" value="' . JText::_('FLEXI_ADD') . '" onclick="addtag()" />
			</div>';
		}

		echo $html;
	}


	/**
	 * Method for clearing cache of data depending on records type
	 *
	 * @return void
	 *
	 * @since 3.2.0
	 */
	protected function _cleanCache()
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		parent::_cleanCache();

		$cache_site = FLEXIUtilities::getCache($group = '', $client = 0);
		$cache_site->clean('com_flexicontent_items');
		$cache_site->clean('com_flexicontent_filters');

		$cache_admin = FLEXIUtilities::getCache($group = '', $client = 1);
		$cache_admin->clean('com_flexicontent_items');
		$cache_admin->clean('com_flexicontent_filters');

		// Also clean this as it contains Joomla frontend view cache of the component)
		$cache_site->clean('com_flexicontent');
	}


	/**
	 * Method for extra form validation after JForm validation is executed
	 *
	 * @param   array     $validated_data  The already jform-validated data of the record
	 * @param   object    $model            The Model object of current controller instance
	 * @param   array     $data            The original posted data of the record
	 *
	 * @return  boolean   true on success, false on failure
	 *
	 * @since 3.3
	 */
	protected function _afterModelValidation(& $validated_data, & $data, $model)
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		return true;
	}


	/**
	 * Method for doing some record type specific work before calling model store
	 *
	 * @return  boolean   true on success, false on failure
	 *
	 * @since 3.3
	 */
	protected function _beforeModelStore(& $validated_data, & $data, $model)
	{
		$this->input->get('task', '', 'cmd') !== __FUNCTION__ or die(__FUNCTION__ . ' : direct call not allowed');

		return true;
	}


	/**
	 * Logic to display batch form for modifying multiple records
	 *
	 * @return void
	 *
	 * @since 3.3
	 */
	public function batch()
	{
		// Check for request forgeries
		JSession::checkToken('request') or die(JText::_('JINVALID_TOKEN'));

		// Initialize variables
		$app   = JFactory::getApplication();
		$db    = JFactory::getDbo();
		$user  = JFactory::getUser();

		$cid = $this->input->get('cid', array(), 'array');
		$cid = ArrayHelper::toInteger($cid);

		// Check at least one item was selected
		if (!count($cid))
		{
			$app->setHeader('status', '500', true);
			$app->enqueueMessage(JText::_('FLEXI_NO_ITEMS_SELECTED'), 'error');
			$this->setRedirect($this->returnURL);

			return;
		}

		// Calculate access of copyitems task
		$canCopy = $user->authorise('flexicontent.copyitems', 'com_flexicontent');

		// Check access
		if (!$canCopy)
		{
			$app->setHeader('status', 403);
			$app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'error');
			$app->redirect($this->returnURL);
		}

		// Access check
		$copytask_allow_uneditable = JComponentHelper::getParams('com_flexicontent')->get('copytask_allow_uneditable', 1);

		if (!$copytask_allow_uneditable)
		{
			// Remove uneditable items
			$auth_cid = array();
			$non_auth_cid = array();

			// Get record owner and other record data
			$q = $this->_getRecordsQuery($cid, array('id', 'created_by', 'catid'));
			$itemdata = $db->setQuery($q)->loadObjectList('id');

			// Check authorization for edit operation
			foreach ($cid as $id)
			{
				$isOwner = $itemdata[$id]->created_by == $user->id;
				$asset = 'com_content.article.' . $id;
				$canEdit = $user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && $isOwner);

				if ($canEdit)
				{
					$auth_cid[] = $id;
				}
				else
				{
					$non_auth_cid[] = $id;
				}
			}

			// echo "<pre>"; echo "authorized:\n"; print_r($auth_cid); echo "\n\nNOT authorized:\n"; print_r($non_auth_cid); echo "</pre>"; exit;
		}
		else
		{
			$auth_cid = & $cid;
			$non_auth_cid = array();
		}

		// Set warning for uneditable items
		if (count($non_auth_cid))
		{
			$msg_noauth = JText::_('FLEXI_CANNOT_COPY_ASSETS') . ' ' . JText::_('FLEXI_REASON_NO_EDIT_PERMISSION')
				. '<br>' . JText::_('FLEXI_ROWS_SKIPPED') . ' : '. implode(',', $non_auth_cid);
			$app->enqueueMessage($msg_noauth, 'warning');

			if (!count($auth_cid))  // Cancel task if no items can be copied
			{
				$app->redirect($this->returnURL);
			}
		}

		// Set only authenticated item ids, to be used by the parent display method ...
		$cid = $this->input->set('cid', $auth_cid);

		// Display the form of the task
		parent::display();
	}
}
