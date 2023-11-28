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
	 * Creates the item view or item form
	 *
	 * @since 1.0
	 */
	public function display($tpl = null)
	{
		$app    = JFactory::getApplication();
		$jinput = $app->input;

		// Check for form layout
		$isForm = $app->isClient('site')
			? ($this->getLayout() === 'form' || in_array($jinput->getCmd('task', ''), array('add','edit')))
			: true;

		if ($isForm)
		{
			/**
			 * In case of FRONTENT: Important set layout to be form,
			 * since various category view SEF links may have this variable set
			 */
			$layout = $app->isClient('site') ? 'form' : 'default';
			$this->setLayout($layout);
			$this->_displayForm($tpl);
			return;
		}
		else
		{
			$this->setLayout('item');
		}

		$this->_displayItem($tpl);
	}



	/**
	 * Creates the item create / edit form
	 *
	 * @since 1.0
	 */
	function _displayForm($tpl)
	{
		if (JFactory::getApplication()->isClient('site'))
		{
			// Note : we use some strings from administrator part, so we will also load administrator language file
			// TODO: remove this need by moving common language string to different file ?

			// Load english language file for 'com_content' component then override with current language file
			JFactory::getLanguage()->load('com_content', JPATH_ADMINISTRATOR, 'en-GB', true);
			JFactory::getLanguage()->load('com_content', JPATH_ADMINISTRATOR, null, true);

			// Load english language file for 'com_flexicontent' component then override with current language file
			JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, 'en-GB', true);
			JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, null, true);
		}

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
		$task       = $jinput->getCmd('task');
		$cparams    = JComponentHelper::getParams('com_flexicontent');
		$isAdmin    = $app->isClient('administrator');
		$isSite     = $app->isClient('site');
		$CFGsfx     = $isSite ? '_fe' : '_be';

		// Get url vars and some constants
		$option     = $jinput->get('option', '', 'cmd');
		$nullDate   = $db->getNullDate();
		$useAssocs  = flexicontent_db::useAssociations();

		$menu_fe = $isSite
			? $app->getMenu()->getActive()
			: false;

		// Get the COMPONENT only parameter, since we do not have item parameters yet, but we need to do some work before creating the item
		$page_params  = new JRegistry();
		$page_params->merge($cparams);

		// Runtime stats
		$print_logging_info = $page_params->get('print_logging_info');
		if ( $print_logging_info )  global $fc_run_times;


		/**
		 * Get session data before record form is created, because during form creation the session data are loaded and then they are cleared
		 */

		$session_data = $app->getUserState('com_flexicontent.edit.item.data');


		/**
		 * Get item data and create item form (that loads item data)
		 */

		if ( $print_logging_info )  $start_microtime = microtime(true);

		// Get model and indicate to model that current view IS item form
		$model = $this->getModel();


		/**
		 * Get data of current version ($model->get() will load the item version 0 (unversioned data), since item has not been loaded yet)
		 */

		$cid = $model->_cid ?: $model->get('catid');


		/**
		 * WE NEED TO get OR decide the Content Type, before we call the getItem
		 * - we rely on typeid Request variable to decide type for new items so make sure this is set,
		 * - ZERO means allow user to select type, but if user is only allowed a single type, then autoselect it!
		 */

		// Try type from session
		if (!empty($session_data['type_id']))
		{
			// This also forces zero if value not set
			$jinput->set('typeid', (int) $session_data['type_id']);
		}

		// Try type from active menu
		elseif (!empty($menu_fe) && isset($menu_fe->query['typeid']))
		{
			// This also forces zero if value not set
			$jinput->set('typeid', (int) $menu_fe->query['typeid']);
		}


		/**
		 * Frontend only
		 */
		if ($isSite)
		{
			/**
			 * Verify type ID is exists and terminate if it does not
			 * NOTE: This in not need in backend, because we have no type's ACL override
			 * via menu in backend, thus controller already does this
			 */

			// NOTE: about -new_typeid-, this is it used only for CREATING new item (ignored for EDIT existing item)

			$new_typeid = $jinput->get('typeid', 0, 'int');
			$type_data = $model->getTypeslist(array($new_typeid), $check_perms = false, $_published=true);

			if ($new_typeid && empty($type_data))
			{
				$app->setHeader('status', '400 Bad Request', true);
				$app->enqueueMessage('Type ID: '.$new_typeid.' not found', 'error');
				$app->redirect('index.php');
			}


			/*
			 * Check if type is allowed to the user, (in any case we continue because there may be menu override)
			 * NOTE: This in not need in backend, because we have no type's ACL override
			 * via menu in backend, thus controller already does this
			 */

			if (!$new_typeid)
			{
				$types = $model->getTypeslist($type_ids_arr = false, $check_perms = true, $_published=true);
				if ( $types && count($types)==1 ) {
					$single_type = reset($types);
					$new_typeid = $single_type->id;
				}
				$jinput->set('typeid', $new_typeid);
				$canCreateType = true;
			}

			// For new items, set into the model the decided item type
			if (!$model->_id)
			{
				$model->setId(0, null, $new_typeid, null);
			}
		}

		// FORCE model to load versioned data (URL specified version or latest version (last saved))
		$version = $jinput->getInt('version', 0);   // Load specific item version (non-zero), 0 version: is unversioned data, -1 version: is latest version (=default for edit form)

		// Get the item, loading item data and doing parameters merging,
		// check_view_access: false, will return false if item is not found
		// $no_cache: true, forces to reload item even if it has already been loaded
		$item = $model->getItem(null, $check_view_access=false, $no_cache=true, $force_version=($version!=0 ? $version : -1));  // -1 version means latest

		if (!$item)
		{
			$app->enqueueMessage($model->getError(), 'warning');
			$returnURL = isset($_SERVER['HTTP_REFERER']) && flexicontent_html::is_safe_url($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : JUri::base();
			$app->redirect( $returnURL );
		}

		if ( $print_logging_info ) $fc_run_times['get_item_data'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;


		// ***
		// *** Frontend form: replace component/menu 'params' with the merged component/category/type/item/menu ETC ... parameters
		// ***

		if ($isSite)
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

			if ($jinput->getCmd('tmpl') !== 'component')
			{
				$returnURL = isset($_SERVER['HTTP_REFERER']) && flexicontent_html::is_safe_url($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : JUri::base();
				$app->redirect( $returnURL );
			}
			return;
		}

		// IS new FLAG
		$isnew   = ! $item->id;

		// Create and set (into HTTP request) a unique item id for plugins that needed it
		if ($item->id)
		{
			$unique_tmp_itemid = $item->id;
		}
		else
		{
			$unique_tmp_itemid = $app->getUserState($form->option.'.edit.item.unique_tmp_itemid');
			$unique_tmp_itemid = $unique_tmp_itemid ? $unique_tmp_itemid : date('_Y_m_d_h_i_s_', time()) . uniqid(true);
		}

		//print_r($unique_tmp_itemid);
		$jinput->set('unique_tmp_itemid', $unique_tmp_itemid);


		/**
		 * Get Associated Translations and languages
		 * also (frontend) load Template-Specific language file to override or add new language
		 */

		$uselang = (int) $page_params->get('uselang' . $CFGsfx, 1);

		$langAssocs = $useAssocs && $uselang
			? $model->getLangAssocs()
			: false;
		$langs = FLEXIUtilities::getLanguages('code');

		// In frontend also load language override from template folder
		if ($isSite)
		{
			FLEXIUtilities::loadTemplateLanguageFile($page_params->get('ilayout') ?: 'default');
		}


		/**
		 * Allowed language modifications
		 */
		$allowlangmods = $page_params->get('allowlangmods' . $CFGsfx , ($isSite
			? array('mod_item_lang')
			: array('mod_item_lang', 'mod_original_content_assoc')
		));
		if ( empty($allowlangmods) )						$allowlangmods = array();
		else if ( ! is_array($allowlangmods) )	$allowlangmods = explode("|", $allowlangmods);


		/**
		 * Type related data
		 */

		// Get available types and the currently selected/requested type
		$types        = $model->getTypeslist();
		$typeselected = $model->getItemType();

		// Get type parameters, these are needed besides the 'merged' item parameters, e.g. to get Type's default layout
		$tparams = $model->getTypeparams();
		$tparams = new JRegistry($tparams);

		// Backend: Apply type configuration if it type is set
		$isAdmin
			? $page_params->merge($tparams)
			: false;


		// ***
		// *** Load JS/CSS files
		// ***

		// Add css to document
		if ($isAdmin)
		{
			!JFactory::getLanguage()->isRtl()
				? $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', array('version' => FLEXI_VHASH))
				: $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', array('version' => FLEXI_VHASH));
		}
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x.css' : 'j3x.css'), array('version' => FLEXI_VHASH))
			: $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x_rtl.css' : 'j3x_rtl.css'), array('version' => FLEXI_VHASH));

		// Fields common CSS
		$document->addStyleSheet(JUri::root(true).'/components/com_flexicontent/assets/css/flexi_form_fields.css', array('version' => FLEXI_VHASH));

		// Add JS frameworks
		flexicontent_html::loadFramework('jQuery');
		flexicontent_html::loadFramework('select2');
		flexicontent_html::loadFramework('touch-punch');
		flexicontent_html::loadFramework('prettyCheckable');
		flexicontent_html::loadFramework('flexi-lib');
		flexicontent_html::loadFramework('flexi-lib-form');

		// Load custom behaviours: form validation, popup tooltips
		JHtml::_('behavior.formvalidator');  // load default validation JS to make sure it is overriden
		JHtml::_('bootstrap.tooltip');

		// Add js function to overload the joomla submitform validation
		$document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/admin.js', array('version' => FLEXI_VHASH));
		$document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/validate.js', array('version' => FLEXI_VHASH));

		// Add js function for custom code used by FLEXIcontent item form
		$document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/itemscreen.js', array('version' => FLEXI_VHASH));


		if ($isSite)
		{
			/**
			 * Add frontend CSS override files to the document (also load CSS joomla template override)
			 */
			$document->addStyleSheet($this->baseurl.'/components/com_flexicontent/assets/css/flexicontent.css', array('version' => FLEXI_VHASH));
			if (FLEXI_J40GE && file_exists(JPATH_SITE.DS.'media/templates/site'.DS.$app->getTemplate().DS.'css'.DS.'flexicontent.css'))
			{
				$document->addStyleSheet($this->baseurl.'/media/templates/site/'.$app->getTemplate().'/css/flexicontent.css', array('version' => FLEXI_VHASH));
			}
			elseif (file_exists(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'css'.DS.'flexicontent.css'))
			{
				$document->addStyleSheet($this->baseurl.'/templates/'.$app->getTemplate().'/css/flexicontent.css', array('version' => FLEXI_VHASH));
			}

			/**
			 * Create captcha field via custom logic
			 */

			// Create captcha field or messages
			// Maybe some code can be removed by using Joomla's built-in form element (in XML file), instead of calling the captcha plugin ourselves
			$use_captcha    = $page_params->get('use_captcha', 1);     // 1 for guests, 2 for any user
			$captcha_formop = $page_params->get('captcha_formop', 0);  // 0 for submit, 1 for submit/edit (aka always)
			$display_captcha = $use_captcha >= 2 || ( $use_captcha == 1 &&  $user->guest );
			$display_captcha = $display_captcha && ($isnew || $captcha_formop);

			// Trigger the configured captcha plugin
			if ($display_captcha)
			{
				// Get configured captcha plugin
				$c_plugin = $page_params->get('captcha', $app->getCfg('captcha')); // TODO add param to override default
				if ($c_plugin)
				{
					$c_name = 'captcha_response_field';
					$c_id = $c_plugin=='recaptcha' ? 'dynamic_recaptcha_1' : 'fc_dynamic_captcha';
					$c_class = ' required';
					$c_namespace = 'fc_item_form';

					// Try to load the configured captcha plugin, (check if disabled or uninstalled), Joomla will enqueue an error message if needed
					$captcha_obj = JCaptcha::getInstance($c_plugin, array('namespace' => $c_namespace));
					if ($captcha_obj)
					{
						$captcha_field = $captcha_obj->display($c_name, $c_id, $c_class);
						$label_class  = 'label';
						$label_class .= FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
						$label_tooltip = flexicontent_html::getToolTip(null, 'FLEXI_CAPTCHA_ENTER_CODE_DESC', 1, 1);
						$captcha_field = '
							<label id="'.$c_name.'-lbl" data-for="'.$c_name.'" class="'.$label_class.'" title="'.$label_tooltip.'" >
							'. JText::_( 'FLEXI_CAPTCHA_ENTER_CODE' ).'
							</label>
							<div id="container_fcfield_'.$c_plugin.'" class="container_fcfield container_fcfield_name_'.$c_plugin.'">
								<div class="fcfieldval_container valuebox fcfieldval_container_'.$c_plugin.'">
								'.$captcha_field.'
								</div>
							</div>';
					}
				}
			}


			// ***
			// *** CHECK EDIT / CREATE PERMISSIONS
			// ***

			// Component / Menu Item parameters
			$allowunauthorize   = $page_params->get('allowunauthorize', 0);     // allow unauthorised user to submit new content
			$unauthorized_page  = $page_params->get('unauthorized_page', '');   // page URL for unauthorized users (via global configuration)
			$notauth_itemid     = $page_params->get('notauthurl', '');          // menu itemid (to redirect) when user is not authorized to create content

			// User Group / Author parameters
			$authorparams   = flexicontent_db::getUserConfig($user->id);
			$max_auth_limit = intval($authorparams->get('max_auth_limit', 0));  // maximum number of content items the user can create

			$hasTmpEdit = false;
			$hasCoupon  = false;
			// Check session
			if ($session->has('rendered_uneditable', 'flexicontent'))
			{
				$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
				$hasTmpEdit = !empty( $rendered_uneditable[$model->get('id')] );
				$hasCoupon  = !empty( $rendered_uneditable[$model->get('id')] ) && $rendered_uneditable[$model->get('id')] == 2;  // editable via coupon
			}

			if (!$isnew)
			{
				// EDIT action

				// Get edit access, this includes privileges edit and edit-own and the temporary EDIT flag ('rendered_uneditable')
				$canEdit = $model->getItemAccess()->get('access-edit');

				// If no edit privilege, check if edit COUPON was provided
				if (!$canEdit)
				{
					$edittok = $jinput->get('edittok', null, 'cmd');
					if ($edittok)
					{
						$query = 'SHOW TABLES LIKE "' . $app->getCfg('dbprefix') . 'flexicontent_edit_coupons"';
						$db->setQuery($query);
						$tbl_exists = (boolean) count($db->loadObjectList());
						if ($tbl_exists)
						{
							$query = 'SELECT * FROM #__flexicontent_edit_coupons '
								. ' WHERE token = ' . $db->Quote($edittok) . ' AND id = ' . $model->get('id')	;
							$db->setQuery( $query );
							$tokdata = $db->loadObject();
							if ($tokdata)
							{
								$hasCoupon = true;
								$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
								$rendered_uneditable[$model->get('id')]  = 2;   // 2: indicates, that has edit via EDIT Coupon
								$session->set('rendered_uneditable', $rendered_uneditable, 'flexicontent');
								$canEdit = 1;
							}
							else
							{
								$app->enqueueMessage(JText::_('EDIT_TOKEN_IS_INVALID') . ' : ' . $edittok, 'warning');
							}
						}
					}
				}

				// Edit check finished, throw error if needed
				if (!$canEdit)
				{
					// Unlogged user, redirect to login page, also setting a return URL
					if ($user->guest)
					{
						$return   = strtr(base64_encode($uri->toString()), '+/=', '-_,');          // Current URL as return URL (but we will for id / cid)
						$fcreturn = serialize( array('id' => $model->get('id'), 'cid' => $cid) );  // a special url parameter, used by some SEF code
						$url = $page_params->get('login_page', 'index.php?option=com_users&view=login')
							. '&return='.$return
							. '&fcreturn='.base64_encode($fcreturn);

						$app->setHeader('status', 403);
						$app->enqueueMessage(JText::sprintf('FLEXI_LOGIN_TO_ACCESS', $url), 'warning');
						$app->redirect($url);
					}

					// Logged user, redirect to the unauthorized page (if this has been configured)
					elseif ($unauthorized_page)
					{
						$app->setHeader('status', 403);
						$app->enqueueMessage(JText::_('FLEXI_ALERTNOTAUTH_TASK'), 'warning');
						$app->redirect($unauthorized_page);
					}

					// Logged user, no unauthorized page has been configured, throw no access exception
					else
					{
						$msg = JText::_('FLEXI_ALERTNOTAUTH_TASK');
						throw new Exception($msg, 403);
					}
				}

				// Finally check if item is currently being checked-out (currently being edited)
				if ($model->isCheckedOut($user->get('id')))
				{
					$msg = JText::sprintf('FLEXI_DESCBEINGEDITTED', $model->get('title'));
					$app->redirect(JRoute::_('index.php?view='.FLEXI_ITEMVIEW.'&cid='.$model->get('catid').'&id='.$model->get('id'), false), $msg);
				}

				//Checkout the item
				if ( !$model->checkout() )
				{
					$app->setHeader('status', '400 Bad Request', true);
					$app->redirect(JRoute::_('index.php?view='.FLEXI_ITEMVIEW.'&cid='.$model->get('catid').'&id='.$model->get('id'), false), JText::_('FLEXI_OPERATION_FAILED') . ' : Cannot checkout file for editing', 'error');
				}
			}

			else
			{
				// CREATE action
				// Get create access, this includes check of creating in at least one category, and type's "create items"
				$canAdd = $model->getItemAccess()->get('access-create');
				$overrideCategoryACL = $page_params->get("overridecatperms", 1) && ($page_params->get("cid") || $page_params->get("maincatid"));
				$canAssignToCategory = $canAdd || $overrideCategoryACL;  // can create in any category -OR- category ACL override is enabled

				// Check if Content Type can be created by current user
				if ( empty($canCreateType) )
				{
					// Not needed, already done be model when type_id is set, check and remove
					if ($new_typeid)
					{
						// If can create given Content Type
						$canCreateType = $model->canCreateType(array($new_typeid));
					}

					// Needed not done be model yet
					else
					{
						// If can create at least one Content Type
						$canCreateType = $model->canCreateType();
					}
				}

				// Not authorized if can not assign item to category or can not create type
				$not_authorised = !$canAssignToCategory || !$canCreateType;

				// Allow item submission by unauthorized users, ... even guests ...
				if ($allowunauthorize == 2) $allowunauthorize = ! $user->guest;

				if ($not_authorised && !$allowunauthorize)
				{
					$msg = '';
					if (!$canCreateType)
					{
						$type_name = isset($types[$new_typeid]) ? '"'.JText::_($types[$new_typeid]->name).'"' : JText::_('FLEXI_ANY');
						$msg .= ($msg ? '<br/>' : ''). JText::sprintf( 'FLEXI_NO_ACCESS_CREATE_CONTENT_OF_TYPE', $type_name );
					}
					if (!$canAssignToCategory)
					{
						$msg .= ($msg ? '<br/>' : ''). JText::_( 'FLEXI_ALERTNOTAUTH_CREATE_IN_ANY_CAT' );
					}
				}

				elseif ($max_auth_limit)
				{
					$db->setQuery('SELECT COUNT(id) FROM #__content WHERE created_by = ' . $user->id);
					$authored_count = $db->loadResult();
					$content_is_limited = $authored_count >= $max_auth_limit;
					$msg = $content_is_limited ? JText::sprintf( 'FLEXI_ALERTNOTAUTH_CREATE_MORE', $max_auth_limit ) : '';
				}

				// User isn't authorize to add ANY content
				if ( ($not_authorised && !$allowunauthorize) || @ $content_is_limited )
				{
					// a. custom unauthorized submission page via menu item
					if ($notauth_menu = $app->getMenu()->getItem($notauth_itemid))
					{
						$internal_link_vars = !empty($notauth_menu->component) ? '&Itemid=' . $notauth_itemid . '&option=' . $notauth_menu->component : '';
						$notauthurl = JRoute::_($notauth_menu->link . $internal_link_vars, false);

						$app->setHeader('status', 403);
						$app->enqueueMessage($msg, 'notice');
						$app->redirect($notauthurl);
					}

					// b. General unauthorized page via global configuration
					elseif ($unauthorized_page)
					{
						$app->setHeader('status', 403);
						$app->enqueueMessage($msg, 'notice');
						$app->redirect($unauthorized_page);
					}

					// c. Finally fallback to raising a 403 Exception/Error that will redirect to site's default 403 unauthorized page
					else
					{
						throw new Exception($msg, 403);
					}
				}

			}
		}


		/**
		 * Allowed buttons and Allowed language modifications
		 */
		$allowbuttons = $page_params->get('allowbuttons' . $CFGsfx, ($isSite
			? array('apply', 'apply_ajax', 'save2new', 'save2copy', 'save_preview', 'preview_latest')
			: array()
		));
		if ( empty($allowbuttons) )						$allowbuttons = array();
		else if ( ! is_array($allowbuttons) )	$allowbuttons = explode("|", $allowbuttons);


		/**
		 * Create toolbar and toolbar title
		 */

		// Create the toolbar
		$toolbar = $this->setToolbar($item, $model, $page_params, $allowbuttons);

		// Set toolbar title
		$item->id
			? JToolbarHelper::title( JText::_( 'FLEXI_EDIT_ITEM' ), 'icon-pencil-alt' )   // Editing existing item
			: JToolbarHelper::title( JText::_( 'FLEXI_NEW_ITEM' ), 'icon-file-alt' );    // Creating new item

		// Hide default toolbar
		$buttons_placement = (int) $page_params->get('buttons_placement' . $CFGsfx, ($isSite ? 0 : -1));
		if ($buttons_placement >= 0)
		{
			// Not important just save space
			$this->document->addStyleDeclaration('#toolbar, #isisJsData{display:none !important;} div.container-main {margin-top: 16px;}');
			$this->document->addScriptDeclaration('jQuery(document).ready(function(){ var jtoolbar_box = jQuery(\'#toolbar\').closest(\'.subhead-collapse\').hide();  jtoolbar_box.prev().remove(); });');
		}



		/**
		 * Load field values from session (typically during a form reload after a server-side form validation failure)
		 * NOTE: Because of fieldgroup rendering other fields, this step must be done in separate loop, placed before FIELD HTML creation
		 */

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

		$usedtagsIds  = $model->getUsedtagsIds();  // NOTE: This will normally return the already set versioned value of tags ($item->tags)
		$usedtagsdata = $model->getTagsByIds($usedtagsIds, $_indexed = false);

		$quicktagsIds  = $page_params->get('quick_tags', array());
		$quicktagsdata = !empty($quicktagsIds) ? $model->getTagsByIds($quicktagsIds, $_indexed = true) : array();


		// Get number of subscribers
		$subscribers = $model->getSubscribersCount();

		// Get the edit lists
		$lists = $this->_buildEditLists($perms, $page_params, $session_data);

		// Create placement configuration for CORE properties
		$placementConf = $this->_createPlacementConf($item, $fields, $page_params, $typeselected);

		// Get menu overridden categories/main category fields, (FALSE IN BACKEND, no menu configuration in backend)
		$menuCats = !$isSite ? false : $this->_getMenuCats($item, $perms, $page_params);

		// Create submit configuration (for new items) into the session, (FALSE IN BACKEND, no menu configuration in backend)
		$submitConf = !$isSite ? false : $this->_createSubmitConf($item, $perms, $page_params);


		/**
		 * Check for zero allowed categories and terminate
		 */
		if (!$lists['catid'] && !$menuCats)
		{
			$app->setHeader('status', 403);
			$app->enqueueMessage(JText::sprintf('FLEXI_LOGIN_TO_ACCESS', $url), 'warning');
			$app->redirect($url, JText::_("FLEXI_CANNOT_SUBMIT_IN_TYPE_ALLOWED_CATS"), 'warning');
		}

		// Item language related vars
		$languages = FLEXIUtilities::getLanguages();
		$itemlang = new stdClass();
		$itemlang->shortcode = substr($item->language ,0,2);
		$itemlang->name = $languages->{$item->language}->name;
		$itemlang->image = '<img src="'.@$languages->{$item->language}->imgsrc.'" alt="'.$languages->{$item->language}->name.'" />';

		// Label for current item state
		$state_labels = array(
			 1 => 'FLEXI_PUBLISHED',
			 0 => 'FLEXI_UNPUBLISHED',
			-5 => 'FLEXI_IN_PROGRESS',
			-3 => 'FLEXI_PENDING',
			-4 => 'FLEXI_TO_WRITE',
			 2 => 'FLEXI_ARCHIVED',
			-2 => 'FLEXI_TRASHED',
		);
		$published = isset($state_labels[$item->state])
			? JText::_($state_labels[$item->state])
			: JText::_('FLEXI_UNKNOWN');


		/**
		 * Frontend specific code
		 */

		if ($isSite)
		{
			// ***
			// *** Calculate a (browser window) page title and a page heading
			// ***

			// This was done inside model, because we have set the merge parameters flag


			// ***
			// *** Create the document title, by from page title and other data
			// ***

			// Use the page heading as document title, (already calculated above via 'appropriate' logic ...)
			$doc_title = $page_params->get( 'page_title' );

			// Check and prepend or append site name
			// Add Site Name to page title
			if ($app->getCfg('sitename_pagetitles', 0) == 1) {
				$doc_title = $app->getCfg('sitename') ." - ". $doc_title ;
			}
			elseif ($app->getCfg('sitename_pagetitles', 0) == 2) {
				$doc_title = $doc_title ." - ". $app->getCfg('sitename') ;
			}

			// Finally, set document title
			$document->setTitle($doc_title);


			// Add title to pathway
			$pathway = $app->getPathWay();
			$pathway->addItem($doc_title, '');

			// Get pageclass suffix
			$pageclass_sfx = htmlspecialchars($page_params->get('pageclass_sfx'));
		}

		/**
		 * Backend specific code
		 */
		else
		{
		}


		/**
		 * Version Panel data
		 */

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
		// *** SET INTO THE FORM, parameter values for various parameter groups
		// ***

		if ( JHtml::_('date', $item->publish_down ?? '' , 'Y') <= 1969 || $item->publish_down == $nullDate || empty($item->publish_down) )
		{
			$item->publish_down = '';//JText::_( 'FLEXI_NEVER' );
			$form->setValue('publish_down', null, ''/*JText::_( 'FLEXI_NEVER' )*/);  // Setting to text will break form date element
		}


		// ***
		// *** Handle Template related work
		// ***

		// (a) Get Content Type allowed templates
		$allowed_tmpls = $tparams->get('allowed_ilayouts');
		$type_default_layout = $tparams->get('ilayout', 'grid');

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
				$value = $item->itemparams->get($fieldname, '');
				if (strlen($value)) $tmpl->params->setValue($fieldname, 'attribs', $value);
			}
		}


		/**
		 * Assign data to VIEW's template
		 */

		$this->item   = $item;
		$this->form   = $form;  // most core field are created via calling JForm methods

		if ($useAssocs)  $this->lang_assocs = $langAssocs;
		$this->langs   = $langs;
		$this->params  = $page_params;
		$this->lists   = $lists;

		$this->subscribers   = $subscribers;
		$this->usedtagsdata  = $usedtagsdata;
		$this->quicktagsdata = $quicktagsdata;
		$this->typeselected  = $typeselected;

		$this->fields     = $fields;
		$this->tparams    = $tparams;
		$this->tmpls      = $tmpls;
		$this->perms      = $perms;
		$this->document   = $document;
		$this->nullDate   = $nullDate;

		$this->menuCats         = $menuCats;
		$this->submitConf       = $submitConf;
		$this->placementConf    = $placementConf;

		$this->placeViaLayout = $placementConf['placeViaLayout'];
		$this->placementMsgs  = $placementConf['placementMsgs'];

		$this->iparams   = $model->getComponentTypeParams();
		$this->itemlang  = $itemlang;
		$this->action    = $isSite ? $uri->toString() : 'index.php';
		$this->published = $published;

		$this->allowbuttons  = $allowbuttons;
		$this->allowlangmods = $allowlangmods;

		// Frontend only
		if ($isSite)
		{
			$this->pageclass_sfx = $pageclass_sfx;

			$this->captcha_errmsg = isset($captcha_errmsg) ? $captcha_errmsg : null;
			$this->captcha_field  = isset($captcha_field)  ? $captcha_field  : null;

			$this->referer = $this->_getReturnUrl();
		}

		// Backend only
		else
		{
			$this->referer = null;
		}

		// The toolbar object that contains form's buttons
		$this->toolbar = $toolbar;

		// Version related: version data, current page, total pages
		$this->versions     = $versions;
		$this->current_page = $current_page;
		$this->pagecount    = $pagecount;

		// Ratings
		$this->ratings = $ratings;


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
	private function _buildEditLists(&$perms, &$page_params, &$session_data)
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
		$isAdmin  = $app->isClient('administrator');
		$isSite   = $app->isClient('site');
		$CFGsfx   = $isSite ? '_fe' : '_be';

		global $globalcats;

		$categories    = $globalcats;
		$types         = $model->getTypeslist();
		$typeselected = $model->getItemType();
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
			if ( $app->isClient('administrator') && !$maincat )
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

		if ( $app->isClient('administrator') )
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
		$level_name = flexicontent_html::userlevel(null, $item->access, null, null, '', $_createlist = false);
		if (empty($level_name))
		{
			JFactory::getApplication()->enqueueMessage(JText::sprintf('FLEXI_ABOUT_INVALID_ACCESS_LEVEL_PLEASE_SAVE_NEW', $item->access, 'Public'), 'warning');
			$document->addScriptDeclaration("jQuery(document).ready(function() { jQuery('#jform_access').val(1).trigger('change'); });");
		}


		// Build field for notifying subscribers
		if (!$subscribers)
		{
			$lists['notify'] = !$isnew ? '<div class="alert alert-info fc-small fc-iblock">'.JText::_('FLEXI_NO_SUBSCRIBERS_EXIST').'</div>' : '';
		}
		else
		{
			// Check if notification emails to subscribers , were already sent during current session
			$subscribers_notified = $session->get('subscribers_notified', array(),'flexicontent');
			if ( !empty($subscribers_notified[$item->id]) )
			{
				$lists['notify'] = '<div class="alert alert-info fc-small fc-iblock">'.JText::_('FLEXI_SUBSCRIBERS_ALREADY_NOTIFIED').'</div>';
			}
			else
			{
				$fieldname = 'jform[notify]';
				$elementid = 'jform_notify';

				$lbltxt   = $subscribers . ' ' . JText::_($subscribers > 1 ? 'FLEXI_SUBSCRIBERS' : 'FLEXI_SUBSCRIBER');
				$classes  = !$prettycheckable_added ? '' : ' use_prettycheckable ';
				$attribs  = ' class="' . $classes . '" '
					. (!$prettycheckable_added ? '' : ' data-labeltext="' . $lbltxt . '" data-labelPosition="right" data-customClass="fcradiocheck"');

				$lists['notify'] = ''
					. ($prettycheckable_added ? '' : '<label class="fccheckradio_lbl" for="'.$elementid.'">')
					. ' <input type="checkbox" id="' . $elementid . '" data-element-grpid="' . $elementid . '" name="' . $fieldname . '" ' . $attribs . ' value="1" checked="checked" />'
					. ($prettycheckable_added ? '' : '&nbsp;' . $lbltxt . '</label>');
			}
		}


		// Build field for notifying owner of changes
		$isOwner = (int) $item->created_by === (int) $user->id;
		if (!$isOwner)
		{
			$fieldname = 'jform[notify_owner]';
			$elementid = 'jform_notify_owner';

			$lbltxt   = JText::_('FLEXI_NOTIFY_OWNER');
			$classes  = !$prettycheckable_added ? '' : ' use_prettycheckable ';
			$attribs  = ' class="' . $classes . '" '
				. (!$prettycheckable_added ? '' : ' data-labeltext="' . $lbltxt . '" data-labelPosition="right" data-customClass="fcradiocheck"');

			$lists['notify_owner'] = ''
				. ($prettycheckable_added ? '' : '<label class="fccheckradio_lbl" for="'.$elementid.'">')
				. ' <input type="checkbox" id="' . $elementid . '" data-element-grpid="' . $elementid . '" name="' . $fieldname . '" ' . $attribs . ' value="1" checked="checked" />'
				. ($prettycheckable_added ? '' : '&nbsp;' . $lbltxt . '</label>');

			$this->ownerCanEdit     = $model->canEdit(null, JFactory::getUser($item->created_by));
			$this->ownerCanEditState = $model->canEditState(null, JFactory::getUser($item->created_by));
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


		// Allowed category language and category states
		$allowed_catlangs = $page_params->get('iform_cats_not_in_itemlang' . $CFGsfx, 1)
			? array()
			: array('*', $item->language);

		$allowed_catstates = $page_params->get('iform_catstates_allowed' . $CFGsfx, ($isSite
			? array(1)
			: array(1, 0)
		));
		$allowed_catstates = FLEXIUtilities::paramToArray($allowed_catstates, false, false, true);


		/**
		 * Featured categories form field
		 */

		$featured_cats_parent = $page_params->get('featured_cats_parent', 0);
		$featured_cats = array();

		// ACL Permission for using featured cats
		$canchange_featcat = $perms['multicat'] && $perms['canchange_featcat'];

		// SHOW/HIDE Configuration -- 0: hide, 1: hide if no ACL, 2: show
		$show_featcats = (int) $page_params->get('show_featcats' . $CFGsfx, 2);

		// Do not display selector if featured cats are disabled or if selector is configured to be hidden via ACL
		$feat_cids_hidden = $show_featcats === 0 || ($show_featcats === 1 && !$canchange_featcat);

		if (!$featured_cats_parent || $feat_cids_hidden)
		{
			$lists['featured_cid'] = false;
		}

		// Display selector (but show as disabled if no ACL to change it)
		else
		{
			$featured_tree = flexicontent_cats::getCategoriesTree($allowed_catstates, $parent_id=$featured_cats_parent, $depth_limit=0);
			$disabled_cats = $page_params->get('featured_cats_parent_disable', 1) ? array($featured_cats_parent) : array();

			$featured_sel = array();
			foreach($form_cid as $item_cat)
			{
				if (isset($featured_tree[$item_cat])) $featured_sel[] = $item_cat;
			}

			$class  = "use_select2_lib";
			$attribs  = 'class="'.$class.'" multiple="multiple" size="8"';
			$attribs .= $canchange_featcat ? '' : ' disabled="disabled"';
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

			$lists['featured_cid'] = ($canchange_featcat ? '' : '<label class="label" style="float:none; margin:0 6px 0 0 !important;">locked</label>').
				flexicontent_cats::buildcatselect($featured_tree, $fieldname, $featured_sel_nomain, 3, $attribs,
					$allowed_catstates, ($item->id ? 'edit' : 'create'),	$actions_allowed,
					$require_all=true, $skip_subtrees=array(), $disable_subtrees=array(), $custom_options=array(), $disabled_cats,
					$empty_errmsg=false, $show_viewable=false, $allowed_catlangs
				);
		}


		/**
		 * Multi-category form field, for user allowed to use multiple categories
		 */

		// ACL Permission for using secondaty cats
		$canchange_seccat = $perms['multicat'] && $perms['canchange_seccat'];

		// SHOW/HIDE Configuration -- 0: hide, 1: hide if no ACL, 2: show
		$show_seccats = (int) $page_params->get('show_seccats' . $CFGsfx, 2);

		// Do not display selector if secondary cats are disabled or if selector is configured to be hidden via ACL
		$sec_cids_hidden = $show_seccats === 0 || ($show_seccats === 1 && !$canchange_seccat);

		if ($sec_cids_hidden)
		{
			$lists['cid'] = false;
		}

		// Display selector (but show as disabled if no ACL to change it)
		else
		{
			if ($page_params->get('cid_allowed_parent'))
			{
				$cid_tree = flexicontent_cats::getCategoriesTree($allowed_catstates, $parent_id=$page_params->get('cid_allowed_parent'), $depth_limit=0);
				$disabled_cats = $page_params->get('cid_allowed_parent_disable', 1) ? array($page_params->get('cid_allowed_parent')) : array();
			}
			else
			{
				$cid_tree = flexicontent_cats::getCategoriesTree($allowed_catstates, $parent_id=0, $depth_limit=0);
				// Commented out to get a titles translated and also get the description texts too
				//$cid_tree = & $categories;
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
			$attribs .= $canchange_seccat ? '' : ' disabled="disabled"';

			$fieldname = 'jform[cid][]';
			$skip_subtrees = $featured_cats_parent ? array($featured_cats_parent) : array();

			// Skip main category from the selected secondary cats to allow easy change of it
			$form_cid_nomain = array();
			foreach($form_cid as $cat_id)
			{
				if ($cat_id != $form_catid) $form_cid_nomain[] = $cat_id;
			}

			$lists['cid'] = ($canchange_seccat ? '' : '<label class="label" style="float:none; margin:0 6px 0 0 !important;">locked</label>').
				flexicontent_cats::buildcatselect($cid_tree, $fieldname, $form_cid_nomain, false, $attribs,
					$allowed_catstates, ($item->id ? 'edit' : 'create'), $actions_allowed,
					$require_all=true, $skip_subtrees, $disable_subtrees=array(), $custom_options=array(), $disabled_cats,
					$empty_errmsg=false, $show_viewable=false, $allowed_catlangs
				);
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
			$catid_tree = flexicontent_cats::getCategoriesTree($allowed_catstates, $parent_id=$page_params->get('catid_allowed_parent'), $depth_limit=0);
			$disabled_cats = $page_params->get('catid_allowed_parent_disable', 1) ? array($page_params->get('catid_allowed_parent')) : array();
		}
		else
		{
			$catid_tree = flexicontent_cats::getCategoriesTree($allowed_catstates, $parent_id=0, $depth_limit=0);
			// Commented out to get a titles translated and also get the description texts too
			//$catid_tree = & $categories;
			$disabled_cats = array();
		}

		$lists['catid'] = false;
		if ( !empty($catid_tree) )
		{
			$disabled = $enable_catid_selector ? '' : ' disabled="disabled"';
			$attribs .= $disabled;
			$lists['catid'] = ($enable_catid_selector ? '' : '<label class="label" style="float:none; margin:0 6px 0 0 !important;">locked</label>').
				flexicontent_cats::buildcatselect($catid_tree, $fieldname, $item->catid, 2, $attribs,
					$allowed_catstates, ($item->id ? 'edit' : 'create'), $actions_allowed,
					$require_all=true, $skip_subtrees=array(), $disable_subtrees=array(), $custom_options=array(), $disabled_cats,
					$empty_errmsg=JText::_('FLEXI_FORM_NO_MAIN_CAT_ALLOWED'),
					$show_viewable=false, $allowed_catlangs
				);
		} else if ( !$isnew && $item->catid ) {
			$lists['catid'] = $globalcats[$item->catid]->title;
		}


		//buid types selectlist
		$class   = 'required use_select2_lib';
		$attribs = 'class="'.$class.'"';
		$fieldname = 'jform[type_id]';
		$elementid = 'jform_type_id';
		$lists['type'] = flexicontent_html::buildtypesselect($types, $fieldname, $typeselected->id, 1, $attribs, $elementid, $check_perms=true );


		// ***
		// *** Build disable comments selector
		// ***
		if ( (int) $page_params->get('allowdisablingcomments' . $CFGsfx, ($isSite ? 0 : 1)) )
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

		if ($isSite)
		{
			// Find globaly or per content type disabled languages
			$disable_langs = $page_params->get('disable_languages' . $CFGsfx, array());

			$langdisplay = $page_params->get('langdisplay' . $CFGsfx, 2);
			$langconf = array();
			$langconf['flags'] = $page_params->get('langdisplay_flags' . $CFGsfx, 1);
			$langconf['texts'] = $page_params->get('langdisplay_texts' . $CFGsfx, 1);
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



	/*
	 * BACKEND only METHODS
	 */


	/**
	 * Method to configure the toolbar for this view.
	 *
	 * @access	public
	 * @return	void
	 */
	function setToolbar($item, $model, $page_params, $allowbuttons)
	{
		global $globalcats;
		$categories = & $globalcats;

		$app     = JFactory::getApplication();
		$user    = JFactory::getUser();
		$session = JFactory::getSession();

		$perms   = $this->_getItemPerms();
		$tparams = $model->getTypeparams();
		$tparams = new JRegistry($tparams);

		$typeselected = $model->getItemType();

		$cid    = $model->getId();
		$isnew  = ! $cid;
		$ctrl   = 'items';
		$isSite = $app->isClient('site');
		$CFGsfx = $isSite ? '_fe' : '_be';


		$buttons_placement = (int) $page_params->get('buttons_placement' . $CFGsfx, ($isSite ? 0 : -1));

		// If tmpl is component and toolbar is inside header (outside component area) then force the toolbar at top of form
		if ($app->input->get('tmpl') === 'component' && $buttons_placement === -1)
		{
			$buttons_placement = 0;
			$page_params->set('buttons_placement' . $CFGsfx, $buttons_placement);
		}

		$tbname  = $buttons_placement === -1 ? 'toolbar' : 'fctoolbar';  // -1 : Place at page header
		$toolbar = JToolbar::getInstance($tbname);

		$isSideBtns = in_array($buttons_placement, array(2,3));  // Side placement (left, right)
		$add_inline = false; // $isSideBtns

		$tip_place_subbtn  = $buttons_placement !== 3 ? 'right' : 'left';
		$tip_place_mainbtn = $isSideBtns
			? $tip_place_subbtn
			: ($buttons_placement === 1 ? 'top' : 'bottom');


		/**
		 * These are used in FRONTEND, to create
		 * (A) a thanks message and
		 * (B) redirect to a specific url when item is closed
		 * (C) We check these to avoid adding preview buttons in the case of redirection
		 */
		$newly_submitted      = $session->get('newly_submitted', array(), 'flexicontent');
		$newly_submitted_item = isset($newly_submitted[$item->id])
			? $newly_submitted[$item->id]
			: $isnew;
		$submit_message            = $page_params->get('submit_message' . (!$isSite ? $CFGsfx  : ''));
		$submit_redirect_url       = $isSite ? $page_params->get('submit_redirect_url') . $CFGsfx  : '';
		$isredirected_after_submit = $newly_submitted_item && $submit_redirect_url;


		/**
		 * Apply buttons
		 */

		$btn_arr = $add_inline ? array('fc_actions' => '') : array();

		// Apply button
		if ( in_array( 'apply_ajax', $allowbuttons) && !$isnew && $typeselected->id  )
		{
			$btn_name = 'apply_ajax';
			$btn_task = $ctrl.'.apply_ajax';

			$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
				'FLEXI_APPLY', $btn_name, $full_js="Joomla.submitbutton('".$ctrl.".apply_ajax')", $msg_alert='', $msg_confirm='',
				$btn_task, $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
				$btn_class=(FLEXI_J40GE ? ' _DDI_class_ btn-success ' : '') . ' ' . $this->tooltip_class, $btn_icon="icon-loop",
				'data-placement="'.$tip_place_mainbtn.'" title="'.JText::_('FLEXI_FAST_SAVE_INFO', true).'"', $auto_add = 0, $tbname);
		}

		// Apply & Reload button   ***   (Apply Type, is a special case of new that has not loaded custom fieds yet, due to type not defined on initial form load)
		if ( in_array( 'apply', $allowbuttons) || !$typeselected->id  )
		{
			$btn_name = $item->type_id ? 'apply' : 'apply_type';
			$btn_task = $item->type_id ? $ctrl.'.apply' : $ctrl.'.apply_type';

			// If apply_ajax is disabled then just call the button "Save"
			$btn_title = !$isnew
				? (in_array( 'apply_ajax', $allowbuttons) ? 'FLEXI_APPLY_N_RELOAD' : 'FLEXI_SAVE')
				: ($typeselected->id ? 'FLEXI_ADD' : 'FLEXI_APPLY_TYPE');

			//JToolbarHelper::apply($btn_task, $btn_title, false);

			$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
				$btn_title, $btn_name, $full_js="Joomla.submitbutton('".$btn_task."')", $msg_alert='', $msg_confirm='',
				$btn_task, $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
				$btn_class=(FLEXI_J40GE ? ' _DDI_class_ btn-success ' : '') . ' ' . $this->tooltip_class, $btn_icon="icon-save",
				'data-placement="'.$tip_place_subbtn.'" title=""', $auto_add = 0, $tbname);
		}

		flexicontent_html::addToolBarDropMenu(
			$btn_arr,
			'apply_btns_group',
			null,
			array('drop_class_extra' => (FLEXI_J40GE ? 'btn-success' : ''), 'add_inline' => $add_inline),
			$tbname
		);


		/**
		 * Save buttons
		 */

		$btn_arr = $add_inline ? array('fc_actions' => '') : array();

		if ($typeselected->id)
		{
			$btn_name = 'save';
			$btn_task = $ctrl.'.save';

			//JToolbarHelper::save($btn_task);  //JToolbarHelper::custom( $btn_task, 'save.png', 'save.png', 'JSAVE', false );

			$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
				'JSAVE', $btn_name, $full_js="Joomla.submitbutton('".$btn_task."')", $msg_alert='', $msg_confirm='',
				$btn_task, $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
				$btn_class=(FLEXI_J40GE ? ' _DDI_class_ btn-success ' : '') . ' ' . $this->tooltip_class, $btn_icon="icon-save",
				'data-placement="'.$tip_place_mainbtn.'" title=""', $auto_add = 0, $tbname);

			if ( !$isredirected_after_submit )
			{
				if ( in_array( 'save_preview', $allowbuttons) )
				{
					$btn_name = 'save_a_preview';
					$btn_task = $ctrl.'.save_a_preview';

					//JToolbarHelper::save($btn_task);  //JToolbarHelper::custom( $btn_task, 'save.png', 'save.png', 'JSAVE', false );

					$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
						(!$isnew ? 'FLEXI_SAVE_A_PREVIEW' : 'FLEXI_ADD_A_PREVIEW'), $btn_name, $full_js="Joomla.submitbutton('".$btn_task."')", $msg_alert='', $msg_confirm='',
						$btn_task, $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
						$btn_class=(FLEXI_J40GE ? ' _DDI_class_ btn-success ' : '') . ' ' . $this->tooltip_class, $btn_icon="icon-save",
						'data-placement="'.$tip_place_subbtn.'" title=""', $auto_add = 0, $tbname);
				}

				if (!$isnew)
				{
					if ( in_array( 'save2new', $allowbuttons) )
					{
						$btn_name = 'save2new';
						$btn_task = $ctrl.'.save2new';

						//JToolbarHelper::save2new($btn_task);  //JToolbarHelper::custom( $btn_task, 'savenew.png', 'savenew.png', 'FLEXI_SAVE_AND_NEW', false );

						$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
							'FLEXI_SAVE_AND_NEW', $btn_name, $full_js="Joomla.submitbutton('".$btn_task."')", $msg_alert='', $msg_confirm='',
							$btn_task, $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
							$btn_class= (FLEXI_J40GE ? ' _DDI_class_ btn-success ' : '') . ' ' . $this->tooltip_class, $btn_icon="icon-save-new",
							'data-placement="'.$tip_place_subbtn.'" title="'.JText::_('FLEXI_SAVE_AND_NEW_INFO', true).'"', $auto_add = 0, $tbname);
					}

					// Also if an existing item, can save to a copy
					if ( in_array( 'save2copy', $allowbuttons) )
					{
						$btn_name = 'save2copy';
						$btn_task = $ctrl.'.save2copy';

						//JToolbarHelper::save2copy($btn_task);  //JToolbarHelper::custom( $btn_task, 'save2copy.png', 'save2copy.png', 'FLEXI_SAVE_AS_COPY', false );

						$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
							'FLEXI_SAVE_AS_COPY', $btn_name, $full_js="Joomla.submitbutton('".$btn_task."')", $msg_alert='', $msg_confirm='',
							$btn_task, $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
							$btn_class= (FLEXI_J40GE ? ' _DDI_class_ btn-success ' : '') . ' ' . $this->tooltip_class, $btn_icon="icon-save-copy",
							'data-placement="'.$tip_place_subbtn.'" title="'.JText::_('FLEXI_SAVE_AS_COPY_INFO', true).'"', $auto_add = 0, $tbname);
					}
				}
			}
		}

		// This will add a vertical spacer before for cancel button
		$isSideBtns ? $btn_arr['fc_actions_before_cancel'] = '' : false;

		flexicontent_html::addToolBarDropMenu(
			$btn_arr,
			'save_btns_group',
			null,
			array('drop_class_extra' => (FLEXI_J40GE ? 'btn-success' : ''), 'add_inline' => $add_inline),
			$tbname
		);

		//JToolbarHelper::cancel($ctrl.'.cancel');   // This add to default 'toolbar' object, instead we need to use the custom toolbar object
		$toolbar->appendButton('Standard', 'cancel', 'JCANCEL', $ctrl.'.cancel', false);


		/**
		 * Add a preview button(s)
		 */

		//$_sh404sef = JPluginHelper::isEnabled('system', 'sh404sef') && JFactory::getConfig()->get('sef');
		$_sh404sef = defined('SH404SEF_IS_RUNNING') && JFactory::getConfig()->get('sef');
		if ( !$isnew && in_array( 'preview_latest', $allowbuttons) )
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
			$item_url    = flexicontent_html::getSefUrl($item_url);
			$previewlink = $item_url . (strstr($item_url, '?') ? '&amp;' : '?') .'preview=1';
			$link_params = 'status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,left=50,width=\'+((screen.width-100) > 1360 ? 1360 : (screen.width-100))+\',top=20,height=\'+((screen.width-160) > 100 ? 1000 : (screen.width-160))+\',directories=no,location=no';

			// Buttom HTML with replacements for the preview buttons
			$preview_btn_html = '
				<a class="toolbar ' . $this->btn_sm_class . (FLEXI_J40GE ? ' _DDI_class_ ' : '') .' btn-fcaction spaced-btn" href="javascript:;" '
				. ' onclick="window.open(\'_PREVIEW_LINK_\', \'preview2\', \''.$link_params.'\'); return false;">'
				. '<span class="icon-screen"></span>_LBL_TEXT_</a>';
			$inline_txt = '(' . JText::_('FLEXI_INLINE') . ') - ';

			// PREVIEW for latest version
			$use_versioning = $page_params->get('use_versioning', 1);
			if ( !$use_versioning || ($item->version == $item->current_version && $item->version == $item->last_version) )
			{
				$btn_arr = array();
				$btn_arr['fc_actions'] = '';

				$lbl_txt = JText::_($use_versioning ? 'FLEXI_PREVIEW_LATEST' :'FLEXI_PREVIEW');
				$btn_arr['preview_current'] = str_replace('_PREVIEW_LINK_', $previewlink . '&amp;tmpl=component',
					 str_replace('_LBL_TEXT_', $lbl_txt, $preview_btn_html));
				$btn_arr['preview_current_insite'] = str_replace('_PREVIEW_LINK_', $previewlink,
					 str_replace('_LBL_TEXT_', $inline_txt . $lbl_txt, $preview_btn_html));
			}

			// PREVIEW for non-approved versions of the item, if they exist
			else
			{
				$btn_arr = array();
				$btn_arr['fc_actions'] = '';

				$prvlink_loaded_ver = $previewlink .'&amp;version='.$item->version;
				$lbl_txt_loaded_ver = JText::_('FLEXI_PREVIEW_FORM_LOADED_VERSION') . ' ' . JText::_('JVERSION') . ': ' . $item->version;

				$prvlink_active_ver = $previewlink .'&amp;version='.$item->current_version;
				$lbl_txt_active_ver = JText::_('FLEXI_PREVIEW_FRONTEND_ACTIVE_VERSION'). ' ' . JText::_('JVERSION') . ': ' . $item->current_version;

				$prvlink_last_ver = $previewlink; //'&amp;version='.$item->last_version;
				$lbl_txt_last_ver = JText::_('FLEXI_PREVIEW_LATEST_SAVED_VERSION'). ' ' . JText::_('JVERSION') . ': ' . $item->last_version;

				// Add a preview button for (currently) LOADED version of the item
				$btn_arr['preview_current'] = str_replace('_PREVIEW_LINK_', $prvlink_loaded_ver . '&amp;tmpl=component',
					 str_replace('_LBL_TEXT_', $lbl_txt_loaded_ver, $preview_btn_html));
				// Add a preview button for currently ACTIVE version of the item
				$btn_arr['preview_active'] = str_replace('_PREVIEW_LINK_', $prvlink_active_ver . '&amp;tmpl=component',
					 str_replace('_LBL_TEXT_', $lbl_txt_active_ver, $preview_btn_html));
				// Add a preview button for currently LATEST version of the item
				$btn_arr['preview_latest'] = str_replace('_PREVIEW_LINK_', $prvlink_last_ver . '&amp;tmpl=component',
					 str_replace('_LBL_TEXT_', $lbl_txt_last_ver, $preview_btn_html));

				// Add a preview button for (currently) LOADED version of the item
				$btn_arr['preview_current_insite'] = str_replace('_PREVIEW_LINK_', $prvlink_loaded_ver,
					 str_replace('_LBL_TEXT_', $inline_txt . $lbl_txt_loaded_ver, $preview_btn_html));
				// Add a preview button for currently ACTIVE version of the item
				$btn_arr['preview_active_insite'] = str_replace('_PREVIEW_LINK_', $prvlink_active_ver,
					 str_replace('_LBL_TEXT_', $inline_txt . $lbl_txt_active_ver, $preview_btn_html));
				// Add a preview button for currently LATEST version of the item
				$btn_arr['preview_latest_insite'] = str_replace('_PREVIEW_LINK_', $prvlink_last_ver,
					 str_replace('_LBL_TEXT_', $inline_txt . $lbl_txt_last_ver, $preview_btn_html));

				//$toolbar->appendButton( 'Custom', $btn_arr['preview_current'], 'preview_current' );
				//$toolbar->appendButton( 'Custom', $btn_arr['preview_active'], 'preview_active' );
				//$toolbar->appendButton( 'Custom', $btn_arr['preview_latest'], 'preview_latest' );
			}

			$drop_btn = '
				<button type="button" class="' . $this->btn_sm_class . ' btn-info dropdown-toggle" data-toggle="dropdown" data-bs-toggle="dropdown">
					<span title="'.JText::_('FLEXI_ACTIONS').'" class="icon-menu"></span>
					'.JText::_('FLEXI_PREVIEW').'
					<span class="caret"></span>
				</button>';

			flexicontent_html::addToolBarDropMenu(
				$btn_arr,
				'preview_btns_group',
				$drop_btn,
				array('drop_class_extra' => '', 'add_inline' => $add_inline),
				$tbname
			);
		}


		$btn_arr = array();
		$btn_arr['fc_actions'] = '';

		/**
		 * Add modal layout editing
		 */

		if ($perms['cantemplates'] && !$isSite)
		{
			$edit_layout = htmlspecialchars(JText::_('FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS'), ENT_QUOTES, 'UTF-8');
			if (!$isnew)
			{
				$btn_name='edit_layout';
				$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
					'FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS', $btn_name, $full_js="var url = jQuery(this).attr('data-href'); fc_showDialog(url, 'fc_modal_popup_container', 0, 0, 0, 0, {title:'".$edit_layout."'}); return false;",
					$msg_alert='', $msg_confirm='',
					$btn_task='', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
					$btn_class='btn-fcaction ' . (FLEXI_J40GE ? ' _DDI_class_ ' . $this->btn_iv_class : '') . ' ' . $this->tooltip_class, $btn_icon="icon-pencil",
					'data-placement="right" data-href="index.php?option=com_flexicontent&amp;view=template&amp;type=items&amp;tmpl=component&amp;ismodal=1&amp;folder=' . $item->itemparams->get('ilayout', $tparams->get('ilayout', 'default'))
						. '&amp;' . JSession::getFormToken() . '=1' .
					'" title="Edit the display layout of this item. <br/><br/>Note: this layout maybe assigned to content types or other items, thus changing it will effect them too"',
					$auto_add = 0,$tbname
				);
			}
		}


		/**
		 * Add collaboration button
		 */

		$has_pro = JPluginHelper::isEnabled($extfolder = 'system', $extname = 'flexisyspro');

		if ($has_pro && $item->id)
		{
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
					$btn_class='btn-fcaction ' . (FLEXI_J40GE ? ' _DDI_class_' . $this->btn_iv_class : '') . ' ' . $this->tooltip_class, $btn_icon="icon-mail",
					'data-placement="right" data-href="' . $task_url . '" title="' . $btn_info . '"',
					$auto_add = 0, $tbname
				);
		}

		// Add Extra actions drop-down menu
		if (count($btn_arr) <= 2)
		{
			array_shift($btn_arr);
		}
		$drop_btn = '
			<button type="button" class="' . $this->btn_sm_class . ' btn-info dropdown-toggle" data-toggle="dropdown" data-bs-toggle="dropdown">
				<span title="'.JText::_('FLEXI_ACTIONS').'" class="icon-menu"></span>
				'.JText::_('FLEXI_MORE').'
				<span class="caret"></span>
			</button>';
		flexicontent_html::addToolBarDropMenu(
			$btn_arr,
			'action_btns_group',
			$drop_btn,
			array('drop_class_extra' => '', 'add_inline' => $add_inline),
			$tbname
		);

		// Return the new custom toolbar object, we will use it to display toolbar at custom place
		return $toolbar;
	}



	/*
	 * FRONTEND only METHODS
	 */


	/**
	 * Creates the item page
	 *
	 * @since 1.0
	 */
	public function _displayItem($tpl = null)
	{
		// Get Content Types with no category links in item view pathways, and for unroutable (non-linkable) categories
		global $globalnoroute, $globalnopath, $globalcats;
		if (!is_array($globalnopath))  $globalnopath  = array();
		if (!is_array($globalnoroute)) $globalnoroute = array();

		// Initialize variables
		$app    = JFactory::getApplication();
		$jinput = $app->input;

		$dispatcher = JEventDispatcher::getInstance();
		$session  = JFactory::getSession();
		$document = JFactory::getDocument();
		$menus = $app->getMenu();
		$menu  = $menus->getActive();
		$uri   = JUri::getInstance();
		$user  = JFactory::getUser();
		$aid   = JAccess::getAuthorisedViewLevels($user->id);
		$db    = JFactory::getDbo();
		$nullDate = $db->getNullDate();


		// ***
		// *** Get item, model and create form (that loads item data)
		// ***

		// Get model
		$model  = $this->getModel();

		// Indicate to model that current view IS NOT item form (anyway default is false)
		$model->isForm = false;

		// Indicate to model to merge menu parameters if menu matches
		$model->mergeMenuParams = true;

		// Get current category id
		$cid = $model->_cid ? $model->_cid : $model->get('catid');

		/**
		 * Decide version to load,
		 * Note: A non zero version forces a login, version meaning is
		 *   0 : is currently active version,
		 *  -1: preview latest version (this is also the default for edit form),
		 *  -2: preview currently active (version 0)
		 * > 0: is a specific version
		 * Preview flag forces a specific item version if version is not set
		 */
		$version = $jinput->getInt('version', 0);
		/**
		 * Preview versioned data FLAG ... if preview is set and version is not then
		 *  1: load version -1 (version latest)
		 *  2: load version -2 (version currently active (0))
		 */
		$preview = $jinput->getInt('preview', 0);
		$version = $preview && !$version ? - $preview : $version;

		// Allow ilayout from HTTP request, this will be checked during loading item parameters
		$model->setItemLayout('__request__');


		/**
		 * Try to load existing item, an 404 error will be raised if item is not found. Also value 2 for check_view_access
		 * indicates to raise 404 error for ZERO primary key too, instead of creating and returning a new item object
		 * Get the item, loading item data and doing parameters merging
		 */

		$start_microtime = microtime(true);

		$item = $model->getItem(null, $_check_view_access=2, $_no_cache=$version, $_force_version=$version);  // ZERO version means unversioned data
		$_run_time = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;

		// Get item parameters as VIEW's parameters (item parameters are merged parameters in order: layout(template-manager)/component/category/type/item/menu/access)
		$params = $item->parameters;

		$print_logging_info = $params->get('print_logging_info');
		if ( $print_logging_info )  global $fc_run_times;
		if ( $print_logging_info ) $fc_run_times['get_item_data'] = $_run_time;


		// ***
		// *** Load needed JS libs & CSS styles
		// ***

		flexicontent_html::loadFramework('jQuery');  // for other views this is done at entry point

		// Add css files to the document <head> section (also load CSS joomla template override)
		if (!$params->get('disablecss', ''))
		{
			$document->addStyleSheet($this->baseurl.'/components/com_flexicontent/assets/css/flexicontent.css', array('version' => FLEXI_VHASH));
			!JFactory::getLanguage()->isRtl()
				? $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x.css' : 'j3x.css'), array('version' => FLEXI_VHASH))
				: $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x_rtl.css' : 'j3x_rtl.css'), array('version' => FLEXI_VHASH));
		}

		if (FLEXI_J40GE && file_exists(JPATH_SITE.DS.'media/templates/site'.DS.$app->getTemplate().DS.'css'.DS.'flexicontent.css'))
		{
			$document->addStyleSheet($this->baseurl.'/media/templates/site/'.$app->getTemplate().'/css/flexicontent.css', array('version' => FLEXI_VHASH));
		}
		elseif (file_exists(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'css'.DS.'flexicontent.css'))
		{
			$document->addStyleSheet($this->baseurl.'/templates/'.$app->getTemplate().'/css/flexicontent.css', array('version' => FLEXI_VHASH));
		}

		// Add extra css/js for the item view
		if ($params->get('view_extra_css_fe')) $document->addStyleDeclaration($params->get('view_extra_css_fe'));
		if ($params->get('view_extra_js_fe'))  $document->addScriptDeclaration($params->get('view_extra_js_fe'));



		// ***
		// *** Create pathway, if automatic pathways is enabled, then path will be cleared before populated
		// ***

		// Get category titles needed by pathway (and optionally by document title too), this will allow Falang to translate them
		$catshelper = new flexicontent_cats($cid);
		$parents    = $catshelper->getParentlist($all_cols=false);

		// Get current pathway
		$pathway = $app->getPathWay();

		// Clear pathway, if automatic pathways are enabled
		if ( $params->get('automatic_pathways', 0) ) {
			$pathway_arr = $pathway->getPathway();
			$pathway->setPathway( array() );
			//$pathway->set('_count', 0);  // not needed ??
			$item_depth = 0;  // menu item depth is now irrelevant ???, ignore it
		} else {
			$item_depth = $params->get('item_depth', 0);
		}

		// Respect menu item depth, defined in menu item
		$p = $item_depth;
		while ( $p < count($parents) ) {
			// For some Content Types the pathway should not be populated with category links
			if ( in_array($item->type_id, $globalnopath) )  break;

			// Do not add to pathway unroutable categories
			if ( in_array($parents[$p]->id, $globalnoroute) )  { $p++; continue; }

			// Add current parent category
			$pathway->addItem( $parents[$p]->title, JRoute::_( FlexicontentHelperRoute::getCategoryRoute($parents[$p]->slug) ) );
			$p++;
		}
		if ($params->get('add_item_pathway', 1)) {
			$pathway->addItem( $item->title, JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug, 0, $item)) );
		}


		// ***
		// *** ITEM LAYOUT handling
		// ***

		// Get item 's layout as this may have been altered by model's decideLayout()
		$ilayout = $params->get('ilayout');

		// Get cached template data, re-parsing XML/LESS files, also loading any template language files of a specific template
		$themes = flexicontent_tmpl::getTemplates( array($ilayout) );

		// Compatibility for content plugins that use this
		$item->readmore_link = JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug, 0, $item));


		/**
		 * Get Item's Fields
		 */

		$_items = array(&$item);

		FlexicontentFields::getFields($_items, FLEXI_ITEMVIEW, $params, $aid);

		if (isset($item->fields))
		{
			$fields = & $item->fields;
		}
		else
		{
			$fields = array();
		}



		/**
		 * Render a basic display for field value data
		 */
		FlexicontentFields::getBasicFieldData($item, $item->fields);



		/**
		 * Calculate a (browser window) page title and a page heading
		 */

		// This was done inside model, because we have set the merge parameters flag


		/**
		 * Create the document title, using page title and other data
		 */
		if (file_exists(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'templates'.DS.$ilayout.DS.'seo'.DS.'item'.DS.'layouts'.DS.'title.php'))
		{
			include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'templates'.DS.$ilayout.DS.'seo'.DS.'item'.DS.'layouts'.DS.'title.php');
		}
		else
		{
			include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'tmpl_common'.DS.'seo'.DS.'item'.DS.'layouts'.DS.'title.php');
		}


		/**
		 * Set document's META tags
		 */
		if (file_exists(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'templates'.DS.$ilayout.DS.'seo'.DS.'item'.DS.'layouts'.DS.'meta.php'))
		{
			include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'templates'.DS.$ilayout.DS.'seo'.DS.'item'.DS.'layouts'.DS.'meta.php');
		}
		else
		{
			include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'tmpl_common'.DS.'seo'.DS.'item'.DS.'layouts'.DS.'meta.php');
		}




		/**
		 * Increment the hit counter
		 */

		// MOVED to flexisystem plugin to avoid view caching preventing its updating
		/*if (FLEXIUtilities::count_new_hit($item->id) )
		{
			$model->hit();
		}*/



		/**
		 * Load template css/js and set template data variable
		 */

		$tmplvar = $themes->items->{$ilayout}->tmplvar;

		if ($ilayout)
		{
			// Add the templates css files if availables
			if (isset($themes->items->{$ilayout}->css))
			{
				foreach ($themes->items->{$ilayout}->css as $css)
				{
					$document->addStyleSheet($this->baseurl.'/'.$css);
				}
			}

			// Add the templates js files if availables
			if (isset($themes->items->{$ilayout}->js))
			{
				foreach ($themes->items->{$ilayout}->js as $js)
				{
					$document->addScript($this->baseurl.'/'.$js);
				}
			}

			// Set the template var
			$tmpl = $themes->items->{$ilayout}->tmplvar;
		}
		else
		{
			$tmpl = '.items.grid';
		}

		// Just put item's text (description field) inside property 'text' in case the events modify the given text,
		$item->text = isset($item->fields['text']->display) ? $item->fields['text']->display : '';

		// Maybe here not to import all plugins but just those for description field ???
		// Anyway these events are usually not very time consuming, so lets trigger all of them ???
		JPluginHelper::importPlugin('content');

		// Suppress some plugins from triggering for compatibility reasons, e.g.
		// (a) jcomments, jom_comment_bot plugins, because we will get comments HTML manually inside the template files
		$suppress_arr = array('jcomments', 'jom_comment_bot');
		FLEXIUtilities::suppressPlugins($suppress_arr, 'suppress' );

		// Do some compatibility steps, Set view and option to 'article' and 'com_content'
		// but set a flag 'isflexicontent' to indicate triggering from inside FLEXIcontent ... code
		$jinput->set('view', 'article');
		$jinput->set('option', 'com_content');
		$jinput->set('isflexicontent', 'yes');

		// Needed by legacy non-updated plugins
		!FLEXI_J40GE ? JRequest::setVar('view', 'article') : null;
		!FLEXI_J40GE ? JRequest::setVar('option', 'com_content') : null;

		$limitstart = $jinput->get('limitstart', 0, 'int');

		// These events return text that could be displayed at appropriate positions by our templates
		$item->event = new stdClass();

		$results = FLEXI_J40GE
			? $app->triggerEvent('onContentAfterTitle', array('com_content.article', &$item, &$params, $limitstart))
			: $dispatcher->trigger('onContentAfterTitle', array('com_content.article', &$item, &$params, $limitstart));
		$item->event->afterDisplayTitle = trim(implode("\n", $results));

		$results = FLEXI_J40GE
			? $app->triggerEvent('onContentBeforeDisplay', array('com_content.article', &$item, &$params, $limitstart))
			: $dispatcher->trigger('onContentBeforeDisplay', array('com_content.article', &$item, &$params, $limitstart));
		$item->event->beforeDisplayContent = trim(implode("\n", $results));

		$results = FLEXI_J40GE
			? $app->triggerEvent('onContentAfterDisplay', array('com_content.article', &$item, &$params, $limitstart))
			: $dispatcher->trigger('onContentAfterDisplay', array('com_content.article', &$item, &$params, $limitstart));
		$item->event->afterDisplayContent = trim(implode("\n", $results));

		// Reverse the compatibility steps, set the view and option back to 'items' and 'com_flexicontent'
		$jinput->set('view', 'item');
		$jinput->set('option', 'com_flexicontent');

		// Needed by legacy non-updated plugins
		!FLEXI_J40GE ? JRequest::setVar('view', 'item') : null;
		!FLEXI_J40GE ? JRequest::setVar('option', 'com_flexicontent') : null;

		// Restore suppressed plugins
		FLEXIUtilities::suppressPlugins($suppress_arr, 'restore' );

		// Put text back into the description field, THESE events SHOULD NOT modify the item text, but some plugins may do it anyway... , so we assign text back for compatibility
		if (!empty($item->positions))
		{
			foreach($item->positions as $pos_fields)
			{
				foreach($pos_fields as $pos_field)
				{
					if ($pos_field->name!=='text') continue;
					$pos_field->display = & $item->text;
				}
			}
		}
		$item->fields['text']->display = & $item->text;

		// (TOC) TABLE OF Contents has been created inside description field (named 'text') by
		// the pagination plugin, this should be assigned to item as a property with same name
		if(isset($item->fields['text']->toc)) {
			$item->toc = &$item->fields['text']->toc;
		}



		// ***
		// *** Add canonical link (if needed and different than current URL), also preventing Joomla default (SEF plugin)
		// *** For item view this must be after the TOC table creation
		// ***

		if ($params->get('add_canonical'))
		{
			// Create desired REL canonical URL
			$ucanonical = JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $globalcats[$item->maincatid]->slug, 0, $item));  // $item->categoryslug
			flexicontent_html::setRelCanonical($ucanonical);
		}



		// ***
		// *** Print link ... must include layout and current filtering url vars, etc
		// ***

    $curr_url   = str_replace('&', '&amp;', $_SERVER['REQUEST_URI']);
    $print_link = $curr_url .(strstr($curr_url, '?') ? '&amp;'  : '?').'pop=1&amp;tmpl=component&amp;print=1';
		$pageclass_sfx = htmlspecialchars($params->get('pageclass_sfx', ''));

		$this->item    = $item;
		$this->user    = $user;
		$this->params  = $params;
		$this->parents = $parents;
		$this->print_link    = $print_link;
		$this->pageclass_sfx = $pageclass_sfx;
		$this->fields        = $item->fields;
		$this->tmpl          = $tmpl;


		// NOTE: Moved decision of layout into the model, function decideLayout() layout variable should never be empty
		// It will consider things like: template exists, is allowed, client is mobile, current frontend user override, etc

		// !!! The following method of loading layouts, is Joomla legacy view loading of layouts
		// TODO: EXAMINE IF NEEDED to re-use these layouts, and use JLayout ??

		// Despite layout variable not being empty, there may be missing some sub-layout files,
		// e.g. item_somefilename.php for this reason we will use a fallback layout that surely has these files
		$fallback_layout = $params->get('item_fallback_layout', 'grid');  // parameter does not exist yet
		if ($ilayout != $fallback_layout) {
			$this->addTemplatePath(JPATH_COMPONENT.DS.'templates'.DS.$fallback_layout);
			$this->addTemplatePath(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'html'.DS.'com_flexicontent'.DS.'templates'.DS.$fallback_layout);
		}

		$this->addTemplatePath(JPATH_COMPONENT.DS.'templates'.DS.$ilayout);
		$this->addTemplatePath(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'html'.DS.'com_flexicontent'.DS.'templates'.DS.$ilayout);


		if ( $print_logging_info ) $start_microtime = microtime(true);
		parent::display($tpl);
		if ( $print_logging_info ) $fc_run_times['template_render'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
	}



	/**
	 * Creates the (menu-overridden) categories/main category form fields for NEW item submission form
	 *
	 * @since 1.0
	 */
	function _getMenuCats( $item, & $perms, $page_params )
	{
		global $globalcats;
		$isnew = !$item->id;

		// Get menu parameters related to category overriding
		$cid       = $page_params->get("cid");              // Overriden categories list
		$maincatid = $page_params->get("maincatid");        // Default main category out of the overriden categories
		$postcats  = $page_params->get("postcats", 0);      // Behavior of override, submit to ONE Or MULTIPLE or to FIXED categories
		$override  = $page_params->get("overridecatperms", 1);   // Default to 1 for compatibilty with previous-version saved menu items

		$maincat_show  = $page_params->get("maincat_show", 2);      // Select to hide: 1 or show: 2 main category selector
		$maincat_show  = !$maincatid ? 2 : $maincat_show;      // Can not hide if default was not configured

		$postcats_show  = $page_params->get("postcats_show", 1);      // If submitting to fixed cats then show or not the category titles
		$override_mulcatsperms  = $page_params->get("override_mulcatsperms", 0);

		// Check if item is new and overridden cats defined (cid or maincatid) and cat overriding enabled
		if ( !$isnew || (empty($cid) && empty($maincatid)) || !$override ) return false;

		// Check if overriding multi-category ACL permission for submitting to multiple categories
		if ( !$perms['multicat'] && !$override_mulcatsperms && $postcats==2 ) $postcats = 1;

		// OVERRIDE item categories, using the ones specified specified by the MENU item, instead of categories that user has CREATE (=add) Permission
		$cids = empty($cid) ? array() : $cid;
		$cids = !is_array($cids) ? explode(",", $cids) : $cids;

		// Add default main category to the overridden category list if not already there
		if ($maincatid && !in_array($maincatid, $cids)) $cids[] = $maincatid;

		// Create 2 arrays with category info used for creating the of select list of (a) multi-categories select field (b) main category select field
		$categories = array();
		$options 	= array();
		foreach ($cids as $catid)
		{
			$categories[] = $globalcats[$catid];
		}

		// Field names for (a) multi-categories field and (b) main category field
		$cid_form_fieldname   = 'jform[cid][]';
		$catid_form_fieldname = 'jform[catid]';
		$catid_form_tagid     = 'jform_catid';

		$mo_maincat = $maincat_show==1 ? '<input type="hidden" name="'.$catid_form_fieldname.'" id="'.$catid_form_tagid.'" value="'.$maincatid.'" />' : false;

		// Create form field HTML for the menu-overridden categories fields
		switch($postcats)
		{
			case 0:  // no categories selection, submit to a MENU SPECIFIED categories list
			default:
				// Do not create multi-category field if only one category was selected
				if ( count($cids)>1 && $postcats_show==2 )
				{
					$mo_cats = '';
					foreach ($cids as $catid)
					{
						if ($catid == $maincatid) continue;
						$cat_titles[$catid] = $globalcats[$catid]->title;
						$mo_cats .= '<!-- only used for form validation ignored during store --><input type="hidden" name="'.$cid_form_fieldname.'" value="'.$catid.'" />';
					}
					$mo_cats .= implode(', ', $cat_titles);
				}
				else
				{
					$mo_cats = false;
				}

				if (!$mo_maincat)
				{
					$mo_maincat = $maincatid ?
						$globalcats[$maincatid]->title :
						flexicontent_cats::buildcatselect($categories, $catid_form_fieldname, $maincatid, 2, ' class="scat use_select2_lib required" ', $check_published=true, $check_perms=false);
				}
				$mo_maincat .= '<!-- only used for form validation ignored during store --><input type="hidden" name="'.$catid_form_fieldname.'" value="'.$maincatid.'" />';
				$mo_cancid  = false;
				break;
			case 1:  // submit to a single category, selecting from a MENU SPECIFIED categories subset
				$mo_cats    = false;
				$mo_maincat = $mo_maincat ? $mo_maincat : flexicontent_cats::buildcatselect($categories, $catid_form_fieldname, $maincatid, 2, ' class="scat use_select2_lib required" ', $check_published=true, $check_perms=false);
				$mo_cancid  = false;
				break;
			case 2:  // submit to multiple categories, selecting from a MENU SPECIFIED categories subset
				$attribs = 'class="validate use_select2_lib" multiple="multiple" size="8"';
				$mo_cats    = flexicontent_cats::buildcatselect($categories, $cid_form_fieldname, array(), false, $attribs, $check_published=true, $check_perms=false);
				$mo_maincat = $mo_maincat ? $mo_maincat : flexicontent_cats::buildcatselect($categories, $catid_form_fieldname, $maincatid, 2, ' class="scat use_select2_lib validate-catid" ', $check_published=true, $check_perms=false);
				$mo_cancid  = true;
				break;
		}
		$menuCats = new stdClass();
		$menuCats->cid    = $mo_cats;
		$menuCats->catid  = $mo_maincat;
		$menuCats->cancid = $mo_cancid;
		$menuCats->cancatid = $maincat_show==2;

		return $menuCats;
	}



	/**
	 * Calculates the submit configuration defined in the active menu item
	 *
	 * @since 1.0
	 */
	function _createSubmitConf( $item, & $perms, $page_params )
	{
		if ( $item->id ) return '';

		// Overriden categories list
		$cid = $page_params->get("cid");
		$maincatid = $page_params->get("maincatid");

		$cids = empty($cid) ? array() : $cid;
		$cids = !is_array($cids) ? explode(",", $cids) : $cids;

		// Behavior of override, submit to ONE Or MULTIPLE or to FIXED categories
		$postcats = $page_params->get("postcats");
		if ( !$perms['multicat'] && $postcats==2 ) $postcats = 1;

		// Default to 1 for compatibilty with previous-version saved menu items
		$overridecatperms  = $page_params->get("overridecatperms", 1);
		if ( empty($cid) && empty($maincatid) ) $overridecatperms = 0;
		$override_mulcatsperms  = $page_params->get("override_mulcatsperms", 0);

		// Get menu parameters override parameters
		$submit_conf = array(
			'cids'            => $cids,
			'maincatid'       => $page_params->get("maincatid"),        // Default main category out of the overriden categories
			'maincat_show'    => $page_params->get("maincat_show", 2),
			'postcats'        => $postcats,
			'overridecatperms'=> $overridecatperms,
			'override_mulcatsperms' => $override_mulcatsperms,
			'autopublished'   => $page_params->get('autopublished', 0),  // Publish the item
			'autopublished_up_interval'   => $page_params->get('autopublished_up_interval', 0),
			'autopublished_down_interval' => $page_params->get('autopublished_down_interval', 0)
		);
		$submit_conf_hash = md5(serialize($submit_conf));

		$session = JFactory::getSession();
		$item_submit_conf = $session->get('item_submit_conf', array(),'flexicontent');
		$item_submit_conf[$submit_conf_hash] = $submit_conf;
		$session->set('item_submit_conf', $item_submit_conf, 'flexicontent');
		$item->submit_conf = $submit_conf;

		return '<input type="hidden" name="jform[submit_conf]" value="'.$submit_conf_hash.'" >';
	}



	/**
	 * Calculates the (per item type) custom placement of fields
	 *
	 * @since 1.0
	 */
	private function _createPlacementConf( $item, & $fields, $page_params, $typeselected )
	{
		$app    = JFactory::getApplication();
		$CFGsfx = $app->isClient('site') ? '' : '_be';


		/**
		 * 1. Find fields (of type 'coreprops') that are used to place core form elements
		 *    like language associations, permissions, versions etc
		 */
		$coreprops_fields = array();
		foreach($fields as $field)
		{
			if ($field->field_type === 'coreprops' && (int) $field->published === 1 && substr( $field->name, 0, 5 ) === "form_")
			{
				$coreprops_fields[$field->parameters->get('props_type')] = $field;
			}
		}


		/**
		 * 2. Field name arrays:  (a) placeable and  (b) placeable via placer  (c) above tabs fields
		 */
		$via_core_field  = array_flip(array
		(
			'text', 'created', 'created_by', 'modified', 'modified_by',
			'title', 'hits', 'document_type', 'version', 'state',
			'voting', 'favourites', 'categories', 'tags',
		));

		$via_core_prop = array_flip(array
		(
			// Single properties
			'id', 'alias', 'category', 'lang', 'vstate', 'disable_comments',
			'notify_subscribers', 'notify_owner', 'captcha', 'layout_selection',

			//Publishing
			'timezone_info', 'created_by_alias', 'publish_up', 'publish_down', 'access',

			// Attibute groups or other composite data
			'item_screen', 'lang_assocs', 'jimages', 'jurls', 'metadata', 'seoconf', 'display_params', 'layout_params', 'perms', 'versions',

			// Deprecated: 'language',
		));


		/**
		 * Parse layout parameters
		 */
		$form_ilayout = $page_params->get('form_ilayout_be', 'tabs');
		$params_file  = JPATH_ADMINISTRATOR . '/components/com_flexicontent/views/item/tmpl/layouts/' . $form_ilayout . '/parse_parameters.php';

		if (file_exists($params_file))
		{
			// Get custom placement and placement forced to be via fields manager
			require_once($params_file);
			$fcForm_layout_params = new FcFormLayoutParameters();
			$placementConf = $fcForm_layout_params->createPlacementConf( $item, $fields, $page_params, $coreprops_fields, $via_core_field, $via_core_prop, $typeselected);
		}
		else
		{
			JFactory::getApplication()->enqueueMessage('A layout file is missing : ' . $params_file, 'warning');
			$placementConf = array('placeViaLayout' => array(), 'coreprop_missing' => array() );
		}

		/**
		 * Add message about and core properties fields that are missing
		 */
		$placementConf['placementMsgs'] = array();

		if ( count($placementConf ['coreprop_missing']) && $typeselected->id )
		{
			$placementConf['placementMsgs']['warning'] = array();
			$placementConf['placementMsgs']['warning'][] = JText::sprintf( 'FLEXI_FORM_FIELDSMAN_PLACING_FIELDS_MISSING',
				'<span class="badge">'. JText::_($typeselected->name) . '</span>',
				'<br><span class="fc_elements_listed_small">' . implode(', ', array_keys($placementConf ['coreprop_missing'])) . '</span><br>',
				'<a href="javascript:;" class="btn btn-primary"
					onclick="alert(\'Not implemented yet, please create manually\'); return false;"
				>Create \'Core Property\' Fields</a>'
			);
		}

		return $placementConf;
	}



	/**
	 * Get return URL via a client request variable, checking if it is safe (otherwise home page will be used)
	 *
	 * @return  string  A validated URL to be used typical as redirect URL when a task completes
	 *
	 * @since 3.3
	 */
	protected function _getReturnUrl()
	{
		$app         = JFactory::getApplication();
		$this->input = $app->input;

		// Try 'return' from the GET / POST data (base64 encoded)
		$return = $this->input->get('return', null, 'base64');

		if ($return)
		{
			$return = base64_decode($return);
		}

		else
		{
			// Try 'referer' from the GET / POST data (htmlspecialchars encoded)
			$referer = $this->input->getString('referer', null);

			if ($referer)
			{
				$referer = htmlspecialchars_decode($referer);
			}

			// Try WEB SERVER variable 'HTTP_REFERER'
			else
			{
				$referer = !empty($_SERVER['HTTP_REFERER']) && flexicontent_html::is_safe_url($_SERVER['HTTP_REFERER'])
					? $_SERVER['HTTP_REFERER']
					: JUri::base();
			}

			$return = $referer;
		}

		// Check return URL if empty or not safe and set a default one
		if (!$return || !flexicontent_html::is_safe_url($return))
		{
			if ($app->isClient('administrator') && ($this->view === $this->record_name || $this->view === $this->record_name_pl))
			{
				$return = 'index.php?option=com_flexicontent&view=' . $this->record_name_pl;
			}
			else
			{
				$return = $app->isClient('administrator') ? null : JUri::base();
			}
		}

		return $return;
	}

}
