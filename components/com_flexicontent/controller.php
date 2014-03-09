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

jimport('joomla.application.component.controller');

/**
 * FLEXIcontent Component Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentController extends JControllerLegacy
{
	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();
		
		// Register Extra task
		$this->registerTask( 'save_a_preview', 'save');
		$this->registerTask( 'apply', 'save');
		$this->registerTask( 'download_tree', 'download');
	}
	
	
	/**
	 * Logic to create SEF urls via AJAX requests
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function getsefurl() {
		$view = JRequest::getVar('view');
		if ($view=='category') {
			$cid = (int) JRequest::getVar('cid');
			if ($cid) {
				$db = JFactory::getDBO();
				$query 	= 'SELECT CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
					.' FROM #__categories AS c WHERE c.id = '.$cid;
				$db->setQuery( $query );
				$categoryslug = $db->loadResult();
				echo JRoute::_(FlexicontentHelperRoute::getCategoryRoute($categoryslug), false);
			}
		}
		jexit();
	}
	
	
	/**
	 * Logic to get text search autocomplete strings
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function txtautocomplete() {
		$app    = JFactory::getApplication();
		$cparams = JComponentHelper::getParams( 'com_flexicontent' );
		$option = JRequest::getVar('option');
		
		$min_word_len = $app->getUserState( $option.'.min_word_len', 0 );
		$filtercat  = $cparams->get('filtercat', 0);      // Filter items using currently selected language
		$show_noauth = $cparams->get('show_noauth', 0);   // Show unauthorized items
		
		// Get request variables
		$type = JRequest::getVar('type');
		$text = JRequest::getVar('text');
		$pageSize = JRequest::getInt('pageSize', 20);
		$pageNum  = JRequest::getInt('pageNum', 1);
		$lang = flexicontent_html::getUserCurrentLang();
		
		// Nothing to do
		if ( $type!='basic_index' && $type!='adv_index' ) jexit();
		if ( !strlen($text) ) jexit();
		
		// All starting words are exact words but last word is a ... word prefix
		$words = preg_split('/\s\s*/u', $text);
		$newtext = '+' . implode( ' +', $words ) .'*';
		
		// Query CLAUSE for match the given text
		$db = JFactory::getDBO();
		$quoted_text = FLEXI_J16GE ? $db->escape($newtext, true) : $db->getEscaped($newtext, true);
		$quoted_text = $db->Quote( $quoted_text, false );
		$_text_match  = ' MATCH (si.search_index) AGAINST ('.$quoted_text.' IN BOOLEAN MODE) ';
		
		// Query retieval limits
		$limitstart = $pageSize * ($pageNum - 1);
		$limit      = $pageSize;
		
		$lang_where = '';
		if ((FLEXI_FISH || FLEXI_J16GE) && $filtercat) {
			$lta = FLEXI_J16GE ? 'i': 'ie';
			$lang_where .= '   AND ( '.$lta.'.language LIKE ' . $db->Quote( $lang .'%' ) . (FLEXI_J16GE ? ' OR '.$lta.'.language="*" ' : '') . ' ) ';
		}
		
		$access_where = '';
		$joinaccess = '';
		/*if (!$show_noauth) {
			$user = JFactory::getUser();
			if (FLEXI_J16GE) {
				$aid_arr = $user->getAuthorisedViewLevels();
				$aid_list = implode(",", $aid_arr);
				$access_where .= ' AND ty.access IN (0,'.$aid_list.')';
				$access_where .= ' AND mc.access IN (0,'.$aid_list.')';
				$access_where .= ' AND  i.access IN (0,'.$aid_list.')';
			} else {
				$aid = (int) $user->get('aid');
				if (FLEXI_ACCESS) {
					$joinaccess .= ' LEFT JOIN #__flexiaccess_acl AS gt ON ty.id = gt.axo AND gt.aco = "read" AND gt.axosection = "type"';
					$joinaccess .= ' LEFT JOIN #__flexiaccess_acl AS gc ON mc.id = gc.axo AND gc.aco = "read" AND gc.axosection = "category"';
					$joinaccess .= ' LEFT JOIN #__flexiaccess_acl AS gi ON  i.id = gi.axo AND gi.aco = "read" AND gi.axosection = "item"';
					$access_where .= ' AND (gt.aro IN ( '.$user->gmid.' ) OR ty.access <= '. $aid . ')';
					$access_where .= ' AND (gc.aro IN ( '.$user->gmid.' ) OR mc.access <= '. $aid . ')';
					$access_where .= ' AND (gi.aro IN ( '.$user->gmid.' ) OR  i.access <= '. $aid . ')';
				} else {
					$access_where .= ' AND ty.access <= '.$aid;
					$access_where .= ' AND mc.access <= '.$aid;
					$access_where .= ' AND  i.access <= '.$aid;
				}
			}
		}*/
		
		
		// Do query ...
		$tbl = $type=='basic_index' ? 'flexicontent_items_ext' : 'flexicontent_advsearch_index';
		$query 	= 'SELECT si.item_id, si.search_index'    //.', '. $_text_match. ' AS score'  // THIS MAYBE SLOW
			.' FROM #__' . $tbl . ' AS si'
			.' JOIN #__content AS i ON i.id = si.item_id'
			.($access_where || ($lang_where && !FLEXI_J16GE && $type!='basic_index') ?
				' JOIN #__flexicontent_items_ext AS ie ON i.id = ie.item_id ' : '')
			.($access_where ? ' JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id' : '')
			.($access_where ? ' JOIN #__categories AS mc ON mc.id = i.catid' : '')
			.$joinaccess
			.' WHERE '. $_text_match
			.'   AND i.state IN (1,-5) '   //(FLEXI_J16GE ? 2:-1) // TODO search archived
			. $lang_where
			. $access_where
			//.' ORDER BY score DESC'  // THIS MAYBE SLOW
			.' LIMIT '.$limitstart.', '.$limit
			;
		$db->setQuery( $query  );
		$data = $db->loadAssocList();
		//if ($db->getErrorNum())  echo __FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg());
		
		// Get last word (this is a word prefix) and remove it from words array
		$word_prefix = array_pop($words);
		
		// Reconstruct search text with complete words (not including last)
		$complete_words = implode(' ', $words);
		
		// Find out the words that matched
		$words_found = array();
		$regex = '/(\b)('.$word_prefix.'\w*)(\b)/iu';
		
		foreach ($data as $_d) {
			//echo $_d['item_id'] . ' ';
			if (preg_match_all($regex, $_d['search_index'], $matches) ) {
				foreach ($matches[2] as $_m) {
					$_m_low = mb_strtolower($_m, 'UTF-8');
					$words_found[$_m_low] = 1;
				}
			}
		}
		
		// Pagination not appropriate when using autocomplete ...
		$options = array();
		$options['Total'] = count($words_found);
		
		// Create responce and JSON encode it
		$options['Matches'] = array();
		$n = 0;
		foreach ($words_found as $_w => $i) {
			if ( mb_strlen($_w) < $min_word_len ) continue;  // word too short
			if ( $this->isStopWord($_w, $tbl) ) continue;  // stopword or too common
			
			$options['Matches'][] = array(
				'text' => $complete_words.' '.$_w,
				'id' => $complete_words.' '.$_w
			);
			$n++;
			if ($n >= $pageSize) break;
		}
		echo json_encode($options);
		jexit();
	}
	
	
	function isStopWord($word, $tbl='flexicontent_items_ext', $col='search_index') {
		$db = JFactory::getDBO();
		$quoted_word = FLEXI_J16GE ? $db->escape($word, true) : $db->getEscaped($word, true);
		$query = 'SELECT '.$col
			.' FROM #__'.$tbl
			.' WHERE MATCH ('.$col.') AGAINST ("+'.$quoted_word.'")'
			.' LIMIT 1';
		$db->setQuery($query);
		$result = $db->loadAssocList();
		return !empty($return) ? true : false;
	}
	
	
	/**
	 * Logic to save an item
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function save()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		// Initialize variables
		$app     = JFactory::getApplication();
		$db      = JFactory::getDBO();
		$user    = JFactory::getUser();
		$menu    = $app->getMenu()->getActive();
		$config  = JFactory::getConfig();
		$session = JFactory::getSession();
		$task	   = JRequest::getVar('task');
		$model   = $this->getModel(FLEXI_ITEMVIEW);
		$isnew   = !$model->getId();
		$ctrl_task = FLEXI_J16GE ? 'task=items.' : 'controller=items&task=';
		
		$fc_params  = JComponentHelper::getParams( 'com_flexicontent' );
		$dolog      = $fc_params->get('print_logging_info');
		
		// Get the COMPONENT only parameters
		$comp_params = JComponentHelper::getComponent('com_flexicontent')->params;
		$params = FLEXI_J16GE ? clone ($comp_params) : new JParameter( $comp_params ); // clone( JComponentHelper::getParams('com_flexicontent') );
		
		// Merge the type parameters
		$tparams = $model->getTypeparams();
		$tparams = FLEXI_J16GE ? new JRegistry($tparams) : new JParameter($tparams);
		$params->merge($tparams);
		
		// Merge the menu parameters
		if ($menu) {
			$menu_params = FLEXI_J16GE ? $menu->params : new JParameter($menu->params);
			$params->merge($menu_params);
		}
		
		// Get needed parameters
		$submit_redirect_url_fe = $params->get('submit_redirect_url_fe', '');
		$allowunauthorize       = $params->get('allowunauthorize', 0);
		
		// Get data from request and validate them
		if (FLEXI_J16GE) {
			// Retrieve form data these are subject to basic filtering
			$data   = JRequest::getVar('jform', array(), 'post', 'array');   // Core Fields and and item Parameters
			$custom = JRequest::getVar('custom', array(), 'post', 'array');  // Custom Fields
			$jfdata = JRequest::getVar('jfdata', array(), 'post', 'array');  // Joomfish Data
			if ( ! @ $data['rules'] ) $data['rules'] = array();
			
			// *** MANUALLY CHECK CAPTCHA ***
			$use_captcha    = $params->get('use_captcha', 1);     // 1 for guests, 2 for any user
			$captcha_formop = $params->get('captcha_formop', 0);  // 0 for submit, 1 for submit/edit (aka always)
			$display_captcha = $use_captcha >= 2 || ( $use_captcha == 1 &&  $user->guest );
			$display_captcha = $display_captcha && ((int) $data['id'] || $captcha_formop);
			if ($display_captcha)
			{
				// Try to force the use of recaptcha plugin
				JFactory::getConfig()->set('captcha', 'recaptcha');
				
				if ( $app->getCfg('captcha') == 'recaptcha' && JPluginHelper::isEnabled('captcha', 'recaptcha') ) {
					JPluginHelper::importPlugin('captcha');
					$dispatcher = JDispatcher::getInstance();
					$result = $dispatcher->trigger('onCheckAnswer', JRequest::getString('recaptcha_response_field'));
					if (!$result[0]) {
						$errmsg  = JText::_('FLEXI_CAPTCHA_FAILED');
						$errmsg .= ' '.JText::_('FLEXI_MUST_REFILL_SOME_FIELDS');
						echo "<script>alert('".$errmsg."');";
						echo "window.history.back();";
						echo "</script>";
						jexit();
					}
				}
			}
			
			// Validate Form data for core fields and for parameters
			$model->setId((int) $data['id']);   // Set data id into model in case some function tries to get a property and item gets loaded
			$form = $model->getForm();          // Do not pass any data we only want the form object in order to validate the data and not create a filled-in form
			$post = $model->validate($form, $data);
			if (!$post) {
				//JError::raiseWarning( 500, "Error while validating data: " . $model->getError() );
				echo "Error while validating data: " . $model->getError();
				echo '<span class="fc_return_msg">'.JText::sprintf('FLEXI_CLICK_HERE_TO_RETURN', '"JavaScript:window.history.back();"').'</span>';
				jexit();
			}
			
			// Some values need to be assigned after validation
			$post['attribs'] = @$data['attribs'];  // Workaround for item's template parameters being clear by validation since they are not present in item.xml
			$post['custom']  = & $custom;          // Assign array of custom field values, they are in the 'custom' form array instead of jform
			$post['jfdata']  = & $jfdata;          // Assign array of Joomfish field values, they are in the 'jfdata' form array instead of jform
		} else {
			// Retrieve form data these are subject to basic filtering
			$post = JRequest::get( 'post' );  // Core & Custom Fields and item Parameters
			
			// Some values need to be assigned after validation
			$post['text'] = JRequest::getVar( 'text', '', 'post', 'string', JREQUEST_ALLOWRAW ); // Workaround for allowing raw text field
		}
		
		// USEFULL FOR DEBUGING for J2.5 (do not remove commented code)
		//$diff_arr = array_diff_assoc ( $data, $post);
		//echo "<pre>"; print_r($diff_arr); jexit();
		
		
		// ********************************************************************************
		// PERFORM ACCESS CHECKS, NOTE: we need to check access again, despite having
		// checked them on edit form load, because user may have tampered with the form ... 
		// ********************************************************************************
		
		$type_id = (int) @ $post['type_id'];  // Typecast to int, (already done for J2.5 via validating)
		if ( !$isnew && $model->get('type_id') == $type_id ) {
			// Existing item with Type not being ALTERED, content type can be maintained regardless of privilege
			$canCreateType = true;
		} else {
			// New item or existing item with Type is being ALTERED, check privilege to create items of this type
			$canCreateType = $model->canCreateType( array($type_id), true, $types );
		}
		
		
		// ****************************************************************
		// Calculate user's privileges on current content item
		// ... canPublish IS RECALCULATED after saving, maybe comment out ?
		// ****************************************************************
		
		if (!$isnew) {
			
			if (FLEXI_J16GE) {
				$asset = 'com_content.article.' . $model->get('id');
				$canPublish = $user->authorise('core.edit.state', $asset) || ($user->authorise('core.edit.state.own', $asset) && $model->get('created_by') == $user->get('id'));
				$canEdit = $user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && $model->get('created_by') == $user->get('id'));
				// ALTERNATIVE 1
				//$canEdit = $model->getItemAccess()->get('access-edit'); // includes privileges edit and edit-own
				// ALTERNATIVE 2
				//$rights = FlexicontentHelperPerm::checkAllItemAccess($user->get('id'), 'item', $model->get('id'));
				//$canEdit = in_array('edit', $rights) || (in_array('edit.own', $rights) && $model->get('created_by') == $user->get('id')) ;
			} else if ($user->gid >= 25) {
				$canPublish = true;
				$canEdit = true;
			} else if (FLEXI_ACCESS) {
				$rights 	= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $model->get('id'), $model->get('catid'));
				$canPublish = in_array('publish', $rights) || (in_array('publishown', $rights) && $model->get('created_by') == $user->get('id')) ;
				$canEdit = in_array('edit', $rights) || (in_array('editown', $rights) && $model->get('created_by') == $user->get('id')) ;
			} else {
				$canPublish = $user->authorize('com_content', 'publish', 'content', 'all');
				$canEdit = $user->authorize('com_content', 'edit', 'content', 'all') || ($user->authorize('com_content', 'edit', 'content', 'own') && $model->get('created_by') == $user->get('id'));
				//$canPublish = ($user->gid >= 21);  // At least J1.5 Publisher
				//$canEdit = ($user->gid >= 20);  // At least J1.5 Editor
			}
			
			if ( !$canEdit ) {
				// No edit privilege, check if item is editable till logoff
				if ($session->has('rendered_uneditable', 'flexicontent')) {
					$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
					$canEdit = isset($rendered_uneditable[$model->get('id')]) && $rendered_uneditable[$model->get('id')];
				}
			}

		} else {
			
			if (FLEXI_J16GE) {
				$canAdd = $model->getItemAccess()->get('access-create'); // includes check of creating in at least one category
				$not_authorised = !$canAdd;
				
				$canPublish	= $user->authorise('core.edit.state', 'com_flexicontent') || $user->authorise('core.edit.state.own', 'com_flexicontent');
			} else if ($user->gid >= 25) {
				$canAdd = 1;
			} else if (FLEXI_ACCESS) {
				$canAdd = FAccess::checkUserElementsAccess($user->gmid, 'submit');
				$canAdd = @$canAdd['content'] || @$canAdd['category'];
				
				$canPublishAll 		= FAccess::checkAllContentAccess('com_content','publish','users',$user->gmid,'content','all');
				$canPublishOwnAll	= FAccess::checkAllContentAccess('com_content','publishown','users',$user->gmid,'content','all');
				$canPublish	= ($user->gid < 25) ? $canPublishAll || $canPublishOwnAll : 1;
			} else {
				$canAdd	= $user->authorize('com_content', 'add', 'content', 'all');
				//$canAdd = ($user->gid >= 19);  // At least J1.5 Author
				$not_authorised = ! $canAdd;
				$canPublish	= ($user->gid >= 21);
			}
			
			if ( $allowunauthorize ) {
				$canAdd = true;
				$canCreateType = true;
			}
		}
		
		// ... we use some strings from administrator part
		// load english language file for 'com_flexicontent' component then override with current language file
		JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, 'en-GB', true);
		JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, null, true);
		
		// Check for new content
		if ( ($isnew && !$canAdd) || (!$isnew && !$canEdit)) {
			$msg = JText::_( 'FLEXI_ALERTNOTAUTH' );
			if (FLEXI_J16GE) throw new Exception($msg, 403); else JError::raiseError(403, $msg);
		}
		
		if ( !$canCreateType ) {
			$msg = isset($types[$type_id]) ?
				JText::sprintf( 'FLEXI_NO_ACCESS_CREATE_CONTENT_OF_TYPE', JText::_($types[$type_id]->name) ) :
				' Content Type '.$type_id.' was not found OR is not published';
			if (FLEXI_J16GE) throw new Exception($msg, 403); else JError::raiseError(403, $msg);
			return;
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
			$original_item = $model->getItem($post['id'], $check_view_access=false, $no_cache=true, $force_version=0);
		}
		
		
		// ****************************************
		// Try to store the form data into the item
		// ****************************************
		if ( ! $model->store($post) )
		{
			// Set error message about saving failed, and also the reason (=model's error message)
			$msg = JText::_( 'FLEXI_ERROR_STORING_ITEM' );
			JError::raiseWarning( 500, $msg .": " . $model->getError() );

			// Since an error occured, check if (a) the item is new and (b) was not created
			if ($isnew && !$model->get('id')) {
				$msg = '';
				$link = 'index.php?option=com_flexicontent&'.$ctrl_task.'add&id=0&typeid='.$type_id.'&'. (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken()) .'=1';
				$this->setRedirect($link, $msg);
			} else {
				$msg = '';
				$link = 'index.php?option=com_flexicontent&'.$ctrl_task.'edit&id='.$model->get('id').'&'. (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken()) .'=1';
				$this->setRedirect($link, $msg);
			}
			
			// Saving has failed check-in and return, (above redirection will be used)
			$model->checkin();
			return;
		}
		
		
		// **************************************************
		// Check in model and get item id in case of new item
		// **************************************************
		$model->checkin();
		$post['id'] = $isnew ? (int) $model->get('id') : $post['id'];
		
		// Get items marked as newly submitted
		$newly_submitted = $session->get('newly_submitted', array(), 'flexicontent');
		if ($isnew) {
			// Mark item as newly submitted, to allow to a proper "THANKS" message after final save & close operation (since user may have clicked add instead of add & close)
			$newly_submitted[$model->get('id')] = 1;
			$session->set('newly_submitted', $newly_submitted, 'flexicontent');
		}
		$newly_submitted_item = @ $newly_submitted[$model->get('id')];
		
		
		// ***********************************************************************************************************
		// Get newly saved -latest- version (store task gets latest) of the item, and also calculate publish privelege
		// ***********************************************************************************************************
		$item = $model->getItem($post['id'], $check_view_access=false, $no_cache=true, $force_version=-1);
		$canPublish = $model->canEditState( $item, $check_cat_perm=true );
		
		
		// ********************************************************************************************
		// Use session to detect multiple item saves to avoid sending notification EMAIL multiple times
		// ********************************************************************************************
		$is_first_save = true;
		if ($session->has('saved_fcitems', 'flexicontent')) {
			$saved_fcitems = $session->get('saved_fcitems', array(), 'flexicontent');
			$is_first_save = $isnew ? true : !isset($saved_fcitems[$model->get('id')]);
		}
		// Add item to saved items of the corresponding session array
		$saved_fcitems[$model->get('id')] = $timestamp = time();  // Current time as seconds since Unix epoc;
		$session->set('saved_fcitems', $saved_fcitems, 'flexicontent');
		
		
		// ********************************************
		// Get categories added / removed from the item
		// ********************************************
		$query 	= 'SELECT DISTINCT c.id, c.title FROM #__categories AS c'
			. ' JOIN #__flexicontent_cats_item_relations AS rel ON rel.catid = c.id'
			. ' WHERE rel.itemid = '.(int) $model->get('id');
		$db->setQuery( $query );
		$after_cats = $db->loadObjectList('id');
		if ( !$isnew ) {
			$cats_added_ids = array_diff(array_keys($after_cats), array_keys($before_cats));
			foreach($cats_added_ids as $cats_added_id) {
				$cats_added_titles[] = $after_cats[$cats_added_id]->title;
			}
			
			$cats_removed_ids = array_diff(array_keys($before_cats), array_keys($after_cats));
			foreach($cats_removed_ids as $cats_removed_id) {
				$cats_removed_titles[] = $before_cats[$cats_removed_id]->title;
			}
			$cats_altered = count($cats_added_ids) + count($cats_removed_ids);
			$after_maincat = $model->get('catid');
		}
		
		
		// *******************************************************************************************************************
		// We need to get emails to notify, from Global/item's Content Type parameters -AND- from item's categories parameters
		// *******************************************************************************************************************
		$notify_emails = array();
		if ( $is_first_save || $cats_altered || $params->get('nf_enable_debug',0) )
		{
			// Get needed flags regarding the saved items
			$approve_version = 2;
			$pending_approval_state = -3;
			$draft_state = -4;
			
			$current_version = FLEXIUtilities::getCurrentVersions($item->id, true); // Get current item version
			$last_version    = FLEXIUtilities::getLastVersions($item->id, true);    // Get last version (=latest one saved, highest version id),
			
			// $post variables vstate & state may have been (a) tampered in the form, and/or (b) altered by save procedure so better not use them
			$needs_version_reviewal     = !$isnew && ($last_version > $current_version) && !$canPublish;
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
		
		
		// *********************************************************************************************************************
		// If there are emails to notify for current saving case, then send the notifications emails, but 
		// *********************************************************************************************************************
		if ( !empty($notify_emails) && count($notify_emails) ) {
			$notify_vars = new stdClass();
			$notify_vars->needs_version_reviewal     = $needs_version_reviewal;
			$notify_vars->needs_publication_approval = $needs_publication_approval;
			$notify_vars->isnew         = $isnew;
			$notify_vars->notify_emails = $notify_emails;
			$notify_vars->notify_text   = $notify_text;
			$notify_vars->before_cats   = $before_cats;
			$notify_vars->after_cats    = $after_cats;
			$notify_vars->original_item = @ $original_item;
			
			$model->sendNotificationEmails($notify_vars, $params, $manual_approval_request=0);
		}
		
		
		// ***************************************************
		// CLEAN THE CACHE so that our changes appear realtime
		// ***************************************************
		if (FLEXI_J16GE) {
			$cache = FLEXIUtilities::getCache();
			$cache->clean('com_flexicontent_items');
			$cache->clean('com_flexicontent_filters');
		} else {
			$itemcache = JFactory::getCache('com_flexicontent_items');
			$itemcache->clean();
			$filtercache = JFactory::getCache('com_flexicontent_filters');
			$filtercache->clean();
		}
		
		
		// ****************************************************************************************************************************
		// Recalculate EDIT PRIVILEGE of new item. Reason for needing to do this is because we can have create permission in a category
		// and thus being able to set this category as item's main category, but then have no edit/editown permission for this category
		// ****************************************************************************************************************************
		if (FLEXI_J16GE) {
			$asset = 'com_content.article.' . $model->get('id');
			$canEdit = $user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && $model->get('created_by') == $user->get('id'));
			// ALTERNATIVE 1
			//$canEdit = $model->getItemAccess()->get('access-edit'); // includes privileges edit and edit-own
			// ALTERNATIVE 2
			//$rights = FlexicontentHelperPerm::checkAllItemAccess($user->get('id'), 'item', $model->get('id'));
			//$canEdit = in_array('edit', $rights) || (in_array('edit.own', $rights) && $model->get('created_by') == $user->get('id')) ;
		} else if (FLEXI_ACCESS && $user->gid < 25) {
			$rights 	= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $model->get('id'), $model->get('catid'));
			$canEdit = in_array('edit', $rights) || (in_array('editown', $rights) && $model->get('created_by') == $user->get('id')) ;
		} else {
			// This is meaningful when executed in frontend, since all backend users (managers and above) can edit items
			$canEdit = $user->authorize('com_content', 'edit', 'content', 'all') || ($user->authorize('com_content', 'edit', 'content', 'own') && $model->get('created_by') == $user->get('id'));
		}
		
		
		// *******************************************************************************************************
		// Check if user can not edit item further (due to changed main category, without edit/editown permission)
		// *******************************************************************************************************
		if (!$canEdit)
		{
			if ($task=='apply') {
				// APPLY TASK: Temporarily set item to be editable till closing it
				$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
				$rendered_uneditable[$model->get('id')]  = 1;
				$session->set('rendered_uneditable', $rendered_uneditable, 'flexicontent');
				$canEdit = 1;
			}
			
			else if ( $newly_submitted_item ) {
				// NEW ITEM: Do not use editable till logoff behaviour
				// ALSO: Clear editable FLAG set in the case that 'apply' button was used during new item creation
				if ( !$params->get('items_session_editable', 0) ) {
					$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
					if ( isset($rendered_uneditable[$model->get('id')]) ) {
						unset( $rendered_uneditable[$model->get('id')] );
						$session->set('rendered_uneditable', $rendered_uneditable, 'flexicontent');
					}
				}
			}
			
			else {
				// EXISTING ITEM: (if enabled) Use the editable till logoff behaviour
				if ( $params->get('items_session_editable', 0) ) {
					
					// Set notice for existing item being editable till logoff 
					JError::raiseNotice( 403, JText::_( 'FLEXI_CANNOT_EDIT_AFTER_LOGOFF' ) );
					
					// Allow item to be editable till logoff
					$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
					$rendered_uneditable[$model->get('id')]  = 1;
					$session->set('rendered_uneditable', $rendered_uneditable, 'flexicontent');
					$canEdit = 1;
				}
			}
			
			// Set notice about saving an item that cannot be changed further
			if ( !$canEdit ) {
				$app->enqueueMessage(JText::_( 'FLEXI_CANNOT_MAKE_FURTHER_CHANGES_TO_CONTENT' ), 'message' );
			}
		}
		
		
		// ****************************************************************
		// Check for new Content Item is being closed, and clear some flags
		// ****************************************************************
		
		if ($task!='apply' && $newly_submitted_item )
		{
			// Clear item from being marked as newly submitted
			unset($newly_submitted[$model->get('id')]);
			$session->set('newly_submitted', $newly_submitted, 'flexicontent');
			
			// The 'apply' task may set 'editable till logoff' FLAG ...
			// CLEAR IT, since NEW content this is meant to be used temporarily
			if ( !$params->get('items_session_editable', 0) ) {
				$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
				if ( isset($rendered_uneditable[$model->get('id')]) ) {
					unset( $rendered_uneditable[$model->get('id')] );
					$session->set('rendered_uneditable', $rendered_uneditable, 'flexicontent');
				}
			}
		}
		
		
		// ****************************************
		// Saving is done, decide where to redirect
		// ****************************************
		
		// REDIRECT CASE FOR APPLYING: Save and reload the item edit form
		if ($task=='apply') {
			
			$msg = JText::_( 'FLEXI_ITEM_SAVED' );
			$link = 'index.php?option=com_flexicontent&view='.FLEXI_ITEMVIEW.'&task=edit&id='.(int) $model->_item->id .'&'. (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken()) .'=1';
			
			// Important pass referer back to avoid making the form itself the referer
			$referer = JRequest::getString('referer', JURI::base(), 'post');
			$return = '&return='.base64_encode( $referer );
			$link .= $return;
		}
		
		// REDIRECT CASES FOR SAVING
		else {
			
			// REDIRECT CASE: Return to a custom page after creating a new item (e.g. a thanks page)
			if ( $newly_submitted_item && $submit_redirect_url_fe ) {
				$link = $submit_redirect_url_fe;
				$msg = JText::_( 'FLEXI_ITEM_SAVED' );
			}
			// REDIRECT CASE: Save and preview the latest version
			else if ($task=='save_a_preview') {
				$msg = JText::_( 'FLEXI_ITEM_SAVED' );
				$link = JRoute::_(FlexicontentHelperRoute::getItemRoute($model->_item->id.':'.$model->_item->alias, $model->_item->catid).'&preview=1', false);
			}
			// REDIRECT CASE: Return to the form 's referer (previous page) after item saving
			else {
				$msg = $newly_submitted_item ? JText::_( 'FLEXI_THANKS_SUBMISSION' ) : JText::_( 'FLEXI_ITEM_SAVED' );
				
				// Check that referer URL is 'safe' (allowed) , e.g. not an offsite URL, otherwise for returning to HOME page
				$link = JRequest::getString('referer', JURI::base(), 'post');
				if ( ! flexicontent_html::is_safe_url($link) ) {
					if ( $dolog ) JFactory::getApplication()->enqueueMessage( 'refused redirection to possible unsafe URL: '.$link, 'notice' );
					$link = JURI::base();
				}
			}
		}
		
		$this->setRedirect($link, $msg);
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
		$cid = JRequest::getInt( 'cid', 0 );
		
		if ( !$cid ) {
			$msg = '';
			JError::raiseWarning(500, JText::_( 'FLEXI_APPROVAL_SELECT_ITEM_SUBMIT' ) );
		} else {
			// ... we use some strings from administrator part
			// load english language file for 'com_flexicontent' component then override with current language file
			JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, 'en-GB', true);
			JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, null, true);
			
			$model = $this->getModel( FLEXI_ITEMVIEW );
			$msg = $model->approval( array($cid) );
		}
		
		$this->setRedirect( $_SERVER['HTTP_REFERER'], $msg );
	}
	
	
	/**
	 * Display the view
	 */
	function display($cachable = false, $urlparams = false)
	{
		// Debuging message
		//JError::raiseNotice(500, 'IN display()'); // TOREMOVE
		
		// Access checking for --items-- viewing, will be handled by the items model, this is because THIS display() TASK is used by other views too
		// in future it maybe moved here to the controller, e.g. create a special task item_display() for item viewing, or insert some IF bellow
		
		if ( JRequest::getVar('layout', false) == "form" && !JRequest::getVar('task', false)) {
			// Compatibility check: Layout is form and task is not set:  this is new item submit ...
			JRequest::setVar('task', 'add');
			$this->add();
		} else {
			// Display a FLEXIcontent frontend view (category, item, favourites, etc)
			if (JFactory::getUser()->get('id')) {
				// WITHOUT CACHING (logged users)
				parent::display(false);
			} else {
				// WITH CACHING (guests)
				parent::display(true);
			}
			
		}
	}

	/**
	* Edits an item
	*
	* @access	public
	* @since	1.0
	*/
	function edit()
	{
		//JError::raiseNotice(500, 'IN edit()');   // Debuging message
		
		$view  = $this->getView(FLEXI_ITEMVIEW, 'html');   // Get/Create the view
		$model = $this->getModel(FLEXI_ITEMVIEW);   // Get/Create the model
		
		// Push the model into the view (as default)
		$view->setModel($model, true);

		// Set the layout
		$view->setLayout( JRequest::getVar('layout','form') );

		// Display the view
		$view->display();
	}
	
	/**
	* Logic to add an item
	* Deprecated in 1.5.3 stable
	*
	* @access	public
	* @since	1.0
	*/
	function add()
	{
		//JError::raiseNotice(500, 'IN ADD()');   // Debuging message
		
		$view  =  $this->getView(FLEXI_ITEMVIEW, 'html');   // Get/Create the view
		$model = $this->getModel(FLEXI_ITEMVIEW);    // Get/Create the model
		
		// Push the model into the view (as default)
		$view->setModel($model, true);
		
		// Set the layout
		$view->setLayout( JRequest::getVar('layout','form') );
		
		// Display the view
		$view->display();
	}


	/**
	* Cancels an edit item operation
	*
	* @access	public
	* @since	1.0
	*/
	function cancel()
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );		
		
		// Initialize some variables
		$user    = JFactory::getUser();
		$session = JFactory::getSession();
		$dolog = JComponentHelper::getParams( 'com_flexicontent' )->get('print_logging_info');

		// Get an item model
		$model = $this->getModel(FLEXI_ITEMVIEW);
		
		// CHECK-IN the item if user can edit
		if ($model->get('id') > 1)
		{
			if (FLEXI_J16GE) {
				$asset = 'com_content.article.' . $model->get('id');
				$canEdit = $user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && $model->get('created_by') == $user->get('id'));
				// ALTERNATIVE 1
				//$canEdit = $model->getItemAccess()->get('access-edit'); // includes privileges edit and edit-own
				// ALTERNATIVE 2
				//$rights = FlexicontentHelperPerm::checkAllItemAccess($user->get('id'), 'item', $model->get('id'));
				//$canEdit = in_array('edit', $rights) || (in_array('edit.own', $rights) && $model->get('created_by') == $user->get('id')) ;
			} else if ($user->gid >= 25) {
				$canEdit = true;
			} else if (FLEXI_ACCESS) {
				$rights 	= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $model->get('id'), $model->get('catid'));
				$canEdit = in_array('edit', $rights) || (in_array('editown', $rights) && $model->get('created_by') == $user->get('id')) ;
			} else {
				$canEdit = $user->authorize('com_content', 'edit', 'content', 'all') || ($user->authorize('com_content', 'edit', 'content', 'own') && $model->get('created_by') == $user->get('id'));
			}
			
			if ( !$canEdit ) {
				// No edit privilege, check if item is editable till logoff
				if ($session->has('rendered_uneditable', 'flexicontent')) {
					$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
					$canEdit = isset($rendered_uneditable[$model->get('id')]) && $rendered_uneditable[$model->get('id')];
				}
			}
			if ($canEdit) $model->checkin();
		}
		
		// If the task was edit or cancel, we go back to the form referer
		$referer = JRequest::getString('referer', JURI::base(), 'post');
		
		// Check that referer URL is 'safe' (allowed) , e.g. not an offsite URL, otherwise for returning to HOME page
		if ( ! flexicontent_html::is_safe_url($referer) ) {
			if ( $dolog ) JFactory::getApplication()->enqueueMessage( 'refused redirection to possible unsafe URL: '.$referer, 'notice' );
			$referer = JURI::base();
		}
		
		$this->setRedirect($referer);
	}

	/**
	 * Method of the voting without AJAX. Exists for compatibility reasons, since it can be called by Joomla's content vote plugin.
	 *
	 * @access public
	 * @since 1.0
	 */
	function vote()
	{
		$id = JRequest::getInt('id', 0);
		$cid = JRequest::getInt('cid', 0);
		$url = JRequest::getString('url', '');
		$dolog = JComponentHelper::getParams( 'com_flexicontent' )->get('print_logging_info');
		
		// Check that the pased URL variable is 'safe' (allowed) , e.g. not an offsite URL, otherwise for returning to HOME page
		if ( ! $url || ! flexicontent_html::is_safe_url($url) ) {
			if ( $dolog ) JFactory::getApplication()->enqueueMessage( 'refused redirection to possible unsafe URL: '.$url, 'notice' );
			$url = JRoute::_('index.php?view='.FLEXI_ITEMVIEW.'&cid='.$cid.'&id='.$id);
		}
		
		// Finally store the vote
		JRequest::setVar('no_ajax', 1);
		$this->ajaxvote();
		
		$msg = '';
		$this->setRedirect($url, $msg );
	}

	/**
	 *  Ajax favourites
	 *
	 * @access public
	 * @since 1.0
	 */
	function ajaxfav()
	{
		$user  = JFactory::getUser();
		$db    = JFactory::getDBO();
		$model = $this->getModel(FLEXI_ITEMVIEW);
		$id    = JRequest::getInt('id', 0);
		
		if (!$user->get('id'))
		{
			echo 'login';
		}
		else
		{
			$isfav = $model->getFavoured();

			if ($isfav)
			{
				$model->removefav();
				$favs 	= $model->getFavourites();
				if ($favs == 0) {
					echo 'removed';
				} else {
					echo '-'.$favs;
				}
			}
			else
			{
				$model->addfav();
				$favs 	= $model->getFavourites();
				if ($favs == 0) {
					echo 'added';
				} else {
					echo '+'.$favs;
				}
			}
		}
	}

	/**
	 *  Method for voting (ajax)
	 *
	 * @TODO move the query part to the item model
	 * @access public
	 * @since 1.5
	 */
	public function ajaxvote()
	{
		$app  = JFactory::getApplication();
		$user = JFactory::getUser();
		$db   = JFactory::getDBO();
		$session = JFactory::getSession();
		$cparams = JComponentHelper::getParams( 'com_flexicontent' );
		
		$no_ajax			= JRequest::getInt('no_ajax');
		$user_rating	= JRequest::getInt('user_rating');
		$cid 			= JRequest::getInt('cid');
		$xid 			= JRequest::getVar('xid');
		
		// Compatibility in case the voting originates from joomla's voting plugin
		if ($no_ajax) {
			// Joomla 's content plugin uses 'id' HTTP request variable
			$cid = JRequest::getInt('id');
		}
		
		
		// ******************************
		// Get voting field configuration
		// ******************************
		
		$db->setQuery('SELECT * FROM #__flexicontent_fields WHERE field_type="voting"');
		$field = $db->loadObject();
		$item = JTable::getInstance( $type = 'flexicontent_items', $prefix = '', $config = array() );
		$item->load( $cid );
		FlexicontentFields::loadFieldConfig($field, $item);
		
		$rating_resolution = (int)$field->parameters->get('rating_resolution', 5);
		$rating_resolution = $rating_resolution >= 5   ?  $rating_resolution  :  5;
		$rating_resolution = $rating_resolution <= 100  ?  $rating_resolution  :  100;
		
		$min_rating = 1;
		$max_rating = $rating_resolution;
		
		
		// *****************************************************
		// Find if user has the ACCESS level required for voting
		// *****************************************************
		
		if (!FLEXI_J16GE) $aid = (int) $user->get('aid');
		else $aid_arr = $user->getAuthorisedViewLevels();
		$acclvl = (int) $field->parameters->get('submit_acclvl', FLEXI_J16GE ? 1 : 0);
		$has_acclvl = FLEXI_J16GE ? in_array($acclvl, $aid_arr) : $acclvl <= $aid;
		
		
		// *********************************
		// Create no access Redirect Message
		// *********************************
		
		if ( !$has_acclvl )
		{
			$logged_no_acc_msg = $field->parameters->get('logged_no_acc_msg', '');
			$guest_no_acc_msg  = $field->parameters->get('guest_no_acc_msg', '');
			$no_acc_msg = $user->id ? $logged_no_acc_msg : $guest_no_acc_msg;
			$no_acc_msg = $no_acc_msg ? JText::_($no_acc_msg) : '';
			
			// Message not set create a Default Message
			if ( !$no_acc_msg )
			{
				// Find name of required Access Level
				if (FLEXI_J16GE) {
					$acclvl_name = '';
					if ($acclvl) {
						$db->setQuery('SELECT title FROM #__viewlevels as level WHERE level.id='.$acclvl);
						$acclvl_name = $db->loadResult();
						if ( !$acclvl_name ) $acclvl_name = "Access Level: ".$acclvl." not found/was deleted";
					}
				} else {
					$acclvl_names = array(0=>'Public', 1=>'Registered', 2=>'Special');
					$acclvl_name = $acclvl_names[$acclvl];
				}
				$no_acc_msg = JText::sprintf( 'FLEXI_NO_ACCESS_TO_VOTE' , $acclvl_name);
			}
		}
		
		
		// ****************************************************
		// NO voting Access OR rating is NOT within valid range
		// ****************************************************
		
		if ( !$has_acclvl  ||  ($user_rating < $min_rating && $user_rating > $max_rating) )
		{
			// Voting REJECTED, avoid setting BAR percentage and HTML rating text ... someone else may have voted for the item ...
			$result	= new stdClass();
			$result->percentage = '';
			$result->htmlrating = '';
			$result->html = !$has_acclvl ? $no_acc_msg : JText::sprintf( 'FLEXI_VOTING_OUT_OF_RANGE', $min_rating, $max_rating);
			
			// Set responce
			if ($no_ajax) {
				$app->enqueueMessage( $result->html, 'notice' );
				return;
			} else {
				echo json_encode($result);
				jexit();
			}
		}
		
		
		// *************************************
		// Retreive last vote for the given item
		// *************************************
		
		$currip = ( phpversion() <= '4.2.1' ? @getenv( 'REMOTE_ADDR' ) : $_SERVER['REMOTE_ADDR'] );
		$currip_quoted = $db->Quote( $currip );
		$dbtbl = !(int)$xid ? '#__content_rating' : '#__flexicontent_items_extravote';  // Choose db table to store vote (normal or extra)
		$and_extra_id = (int)$xid ? ' AND field_id = '.(int)$xid : '';     // second part is for defining the vote type in case of extra vote
			
		$query = ' SELECT *'
			. ' FROM '.$dbtbl.' AS a '
			. ' WHERE content_id = '.(int)$cid.' '.$and_extra_id;
			
		$db->setQuery( $query );
		$votesdb = $db->loadObject();
		
		
		// ********************************************************************************************
		// Check: item id exists in our voting logging SESSION (array) variable, to avoid double voting
		// ********************************************************************************************
		
		$votestamp = $session->get('votestamp', array(),'flexicontent');
		if ( !isset($votestamp[$cid]) || !is_array($votestamp[$cid]) )
		{
			$votestamp[$cid] = array();
		}
		$votecheck = isset($votestamp[$cid][$xid]);
			
		
		// ***********************************************************
		// Voting access allowed and valid, but we will need to make
		// some more checks (IF voting record exists AND double voting)
		// ***********************************************************
		$result	= new stdClass();
		
		// Voting record does not exist for this item, accept user's vote and insert new voting record in the db
		if ( !$votesdb ) {
			$query = ' INSERT '.$dbtbl
				. ' SET content_id = '.(int)$cid.', '
				. '  lastip = '.$currip_quoted.', '
				. '  rating_sum = '.(int)$user_rating.', '
				. '  rating_count = 1 '
				. ( (int)$xid ? ', field_id = '.(int)$xid : '' );
				
			$db->setQuery( $query );
			$db->query() or die( $db->stderr() );
			$result->ratingcount = 1;
			$result->htmlrating = '(' . $result->ratingcount .' '. JText::_( 'FLEXI_VOTE' ) . ')';
		}
		
		// Voting record exists for this item, check if user has already voted
		else {
			
			// NOTE: it is not so good way to check using ip, since 2 users may have same IP,
			// but for compatibility with standard joomla and for stronger security we will do it
			if ( !$votecheck && $currip!=$votesdb->lastip ) 
			{
				// vote accepted update DB
				$query = " UPDATE ".$dbtbl
				. ' SET rating_count = rating_count + 1, '
				. '  rating_sum = rating_sum + '.(int)$user_rating.', '
				. '  lastip = '.$currip_quoted
				. ' WHERE content_id = '.(int)$cid.' '.$and_extra_id;
				
				$db->setQuery( $query );
				$db->query() or die( $db->stderr() );
				$result->ratingcount = $votesdb->rating_count + 1;
				$result->htmlrating = '(' . $result->ratingcount .' '. JText::_( 'FLEXI_VOTES' ) . ')';
			} 
			else 
			{
				// Voting REJECTED, avoid setting BAR percentage and HTML rating text ... someone else may have voted for the item ...
				
				//$result->percentage = ( $votesdb->rating_sum / $votesdb->rating_count ) * (100/$rating_resolution);
				//$result->htmlrating = '(' . $votesdb->rating_count .' '. JText::_( 'FLEXI_VOTES' ) . ')';
				$result->html = JText::_( 'FLEXI_YOU_HAVE_ALREADY_VOTED' );
				if ($no_ajax) {
					$app->enqueueMessage( $result->html, 'notice' );
					return;
				} else {
					echo json_encode($result);
					jexit();
				}
			}
		}
		
		// Set the current item id, in our voting logging SESSION (array) variable, to avoid future double voting
		$votestamp[$cid][$xid] = 1;
		$session->set('votestamp', $votestamp, 'flexicontent');
		
		// Prepare responce
		$rating_sum = (@ $votesdb ? $votesdb->rating_sum : 0) + (int) $user_rating;
		$result->percentage = ($rating_sum / $result->ratingcount) * (100 / $rating_resolution);
		$result->html = JText::_( 'FLEXI_THANK_YOU_FOR_VOTING' );
		
		// Finally set responce
		if ($no_ajax) {
			$app->enqueueMessage( $result->html, 'notice' );
			return;
		} else {
			echo json_encode($result);
			jexit();
		}
	}


	/**
	 * Get the new tags and outputs html (ajax)
	 *
	 * @TODO cleanup this mess
	 * @access public
	 * @since 1.0
	 */
	function getajaxtags()
	{
		$user = JFactory::getUser();
		$authorized = FLEXI_J16GE ? $user->authorise('com_flexicontent', 'newtags') : $user->authorize('com_flexicontent', 'newtags');

		if (!$authorized) return;
		
		$id 	= JRequest::getInt('id', 0);
		$model 	= $this->getModel(FLEXI_ITEMVIEW);
		$tags 	= $model->getAlltags();

		$used = null;

		if ($id) {
			$used = $model->getUsedtagsIds($id);
		}
		if(!is_array($used)){
			$used = array();
		}

		$rsp = '';
		$n = count($tags);
		for( $i = 0, $n; $i < $n; $i++ ){
			$tag = $tags[$i];

			if( ( $i % 5 ) == 0 ){
				if( $i != 0 ){
					$rsp .= '</div>';
				}
				$rsp .=  '<div class="qf_tagline">';
			}
			$rsp .=  '<span class="qf_tag"><span class="qf_tagidbox"><input type="checkbox" name="tag[]" value="'.$tag->id.'"' . (in_array($tag->id, $used) ? 'checked="checked"' : '') . ' /></span>'.$tag->name.'</span>';
		}
		$rsp .= '</div>';
		$rsp .= '<div class="clear"></div>';
		$rsp .= '<div class="qf_addtag">';
		$rsp .= '<label for="addtags">'.JText::_( 'FLEXI_ADD_TAG' ).'</label>';
		$rsp .= '<input type="text" id="tagname" class="inputbox" size="30" />';
		$rsp .=	'<input type="button" class="button" value="'.JText::_( 'FLEXI_ADD' ).'" onclick="addtag()" />';
		$rsp .= '</div>';

		echo $rsp;
	}

	/**
	 *  Add new Tag from item screen
	 *
	 * @access public
	 * @since 1.0
	 */
	function addtagx()
	{

		$user = JFactory::getUser();
		$name = JRequest::getString('name', '');
		$authorized = FLEXI_J16GE ? $user->authorise('com_flexicontent', 'newtags') : $user->authorize('com_flexicontent', 'newtags');

		if (!$authorized) return;
		
		$model 	= $this->getModel(FLEXI_ITEMVIEW);
		$model->addtag($name);
	}
	
	
	/**
	 *  Add new Tag from item screen
	 *
	 */
	function addtag() {
		// Check for request forgeries
		JRequest::checkToken('request') or jexit( 'Invalid Token' );

		$name 	= JRequest::getString('name', '');
		$model 	= $this->getModel('tags');
		$array = JRequest::getVar('cid',  0, '', 'array');
		$cid = (int)$array[0];
		$model->setId($cid);
		if($cid==0) {
			// Add the new tag and output it so that it gets loaded by the form
			$result = $model->addtag($name);
			if($result)
				echo $model->_tag->id."|".$model->_tag->name;
		} else {
			// Since an id was given, just output the loaded tag, instead of adding a new one
			$id = $model->get('id');
			$name = $model->get('name');
			echo $id."|".$name;
		}
		jexit();
	}

	/**
	 * Add favourite
	 * deprecated to ajax favs 
	 *
	 * @access public
	 * @since 1.0
	 */
	function addfavourite()
	{
		$cid 	= JRequest::getInt('cid', 0);
		$id 	= JRequest::getInt('id', 0);

		$model 	= $this->getModel(FLEXI_ITEMVIEW);
		if ($model->addfav()) {
			$msg = JText::_( 'FLEXI_FAVOURITE_ADDED' );
		} else {
			$msg = JText::_( 'FLEXI_FAVOURITE_NOT_ADDED' ).': '.$model->getError();
			if (FLEXI_J16GE) throw new Exception($msg, 500); else JError::raiseError(500, $msg);
		}
		
		$cache = JFactory::getCache('com_flexicontent');
		$cache->clean();

		$this->setRedirect(JRoute::_('index.php?view='.FLEXI_ITEMVIEW.'&cid='.$cid.'&id='. $id, false), $msg );
	}

	/**
	 * Remove favourite
	 * deprecated to ajax favs
	 *
	 * @access public
	 * @since 1.0
	 */
	function removefavourite()
	{
		$cid 	= JRequest::getInt('cid', 0);
		$id 	= JRequest::getInt('id', 0);

		$model 	= $this->getModel(FLEXI_ITEMVIEW);
		if ($model->removefav()) {
			$msg = JText::_( 'FLEXI_FAVOURITE_REMOVED' );
		} else {
			$msg = JText::_( 'FLEXI_FAVOURITE_NOT_REMOVED' ).': '.$model->getError();
			if (FLEXI_J16GE) throw new Exception($msg, 500); else JError::raiseError(500, $msg);
		}
		
		$cache = JFactory::getCache('com_flexicontent');
		$cache->clean();
		
		if ($cid) {
			$this->setRedirect(JRoute::_('index.php?view='.FLEXI_ITEMVIEW.'&cid='.$cid.'&id='. $id, false), $msg );
		} else {
			$this->setRedirect(JRoute::_('index.php?view=favourites', false), $msg );
		}
	}

	/**
	 * Logic to change the state of an item
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function setitemstate()
	{
		flexicontent_html::setitemstate($this);
	}
	
	
	/**
	 * Traverse Tree to create folder structure and get/prepare file objects
	 *
	 * @access public
	 * @since 1.0
	 */
	function _traverseFileTree($nodes, $targetpath)
	{
		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.archive');
		$all_files = array();
		
		foreach ($nodes as $node)
		{
			// Folder (Parent node)
			if ( $node->isParent ) {
				$targetpath_node = JPath::clean($targetpath.DS.$node->name);
				JFolder::create($targetpath_node, 0755);
				
				// Folder has sub-contents
				if ( !empty($node->children) ) {
					$node_files = $this->_traverseFileTree($node->children, $targetpath_node);
					foreach ($node_files as $nodeID => $file)  $all_files[$nodeID] = $file;
				}
			}
			
			// File (Leaf node)
			else {
				$file = new stdClass();
				$nodeID = $node->id;
				$file->fieldid    = (int) $node->fieldid;  // sql security ...
				$file->contentid  = (int) $node->contentid; // sql security ...
				$file->fileid     = (int) $node->fileid; // sql security ...
				$file->filename   = $node->name;
				// (of course) for each file the target path includes the filename,
				// which can be different than original filename (user may have renamed it)
				$file->targetpath = $targetpath.DS.$file->filename;
				$all_files[$nodeID] = $file;
			}
		}
		return $all_files;
	}
	
	
	function call_extfunc() {
		$exttype = JRequest::getVar( 'exttype', 'modules' );
		$extname = JRequest::getVar( 'extname', '' );
		$extfunc = JRequest::getVar( 'extfunc', '' );
		$extfolder = JRequest::getVar( 'extfolder', '' );
		
		if ($exttype!='modules' && $exttype!='plugins') { echo 'only modules and plugins are supported'; jexit(); }  // currently supporting only module and plugins
		if (!$extname || !$extfunc) { echo 'function or extension name not set'; jexit(); }  // require variable not set
		if ($exttype=='plugins' && $extfolder=='') { echo 'only plugin folder is not set'; jexit(); }  // currently supporting only module and plugins		
		
		if ($exttype=='modules') {
			// Import module helper file
			$helper_path = JPATH_SITE.DS.$exttype.DS.'mod_'.$extname.DS.'helper.php';
			if ( !file_exists($helper_path) ) { echo "no helper file found at expected path, filepath is ".$helper_path; jexit(); }
			require_once ($helper_path);
			
			// Create object
			$classname = 'mod'.ucwords($extname).'Helper';
			if ( !class_exists($classname) ) { echo "no correctly named class inside helper file"; jexit(); }
			$obj = new $classname();
		}
		
		else {  // exttype is 'plugins'
			// Load Flexicontent Field (the Plugin file) if not already loaded
			$plgfolder = !FLEXI_J16GE ? '' : DS.strtolower($extname);
			$path = JPATH_ROOT.DS.'plugins'.DS.$extfolder.$plgfolder.DS.strtolower($extname).'.php';
			if ( !file_exists($path) ) { echo "no plugin file found at expected path, filepath is ".$path; jexit(); }
			require_once ($path);
			
			// Create class name of the plugin
			$classname = 'plg'. ucfirst($extfolder).$extname;
			if ( !class_exists($classname) ) { echo "no correctly named class inside plugin file"; jexit(); }
			
			// Create a plugin instance
			$dispatcher = JDispatcher::getInstance();
			$obj = new $classname($dispatcher, array());
			
			// Assign plugin parameters, (most FLEXI plugins do not have plugin parameters), CHECKING if parameters exist
			$plugin_db_data = JPluginHelper::getPlugin($extfolder,$extname);
			$obj->params = FLEXI_J16GE ? new JRegistry( @ $plugin_db_data->params ) : new JParameter( @ $plugin_db_data->params );
		}
		
		// Security concern, only 'confirmed' methods will be callable
		if ( !in_array($extfunc, $obj->task_callable) ) { echo "non-allowed method called"; jexit(); }
		
		// Method actually exists
		if ( !method_exists($obj, $extfunc) ) { echo "non-existing method called "; jexit(); }
		
		// Load extension's english language file then override with current language file
		if ($exttype=='modules')
			$extension_name = 'mod_'.strtolower($extname);
		else
			$extension_name = 'plg_'.strtolower($extname);
		JFactory::getLanguage()->load($extension_name, JPATH_SITE, 'en-GB', true);
		JFactory::getLanguage()->load($extension_name, JPATH_SITE, null, true);
		
		// Call the method
		$obj->$extfunc();
	}
	
	
	/**
	 * Download logic
	 *
	 * @access public
	 * @since 1.0
	 */
	function download()
	{
		// Import and Initialize some joomla API variables
		jimport('joomla.filesystem.file');
		$app   = JFactory::getApplication();
		$db    = JFactory::getDBO();
		$user  = JFactory::getUser();
		$task  = JRequest::getVar( 'task', 'download' );
		$session = JFactory::getSession();
		$method  = JRequest::getVar( 'method', 'download' );
		if ($method!='view' && $method!='download') die('unknown download method:' . $method);
		
		
		// *******************************************************************************************************************
		// Single file download (via HTTP request) or multi-file downloaded (via a folder structure in session or in DB table)
		// *******************************************************************************************************************
		
		if ($task == 'download_tree')
		{
			// TODO: maybe move this part in module
			$cart_id = JRequest::getVar( 'cart_id', 0 );
			if (!$cart_id) {
				// Get zTree data and parse JSON string
				$tree_var = JRequest::getVar( 'tree_var', "" );
				if ($session->has($tree_var, 'flexicontent')) {
					$ztree_nodes_json = $session->get($tree_var, false,'flexicontent');
				}
				$nodes = json_decode($ztree_nodes_json);
			} else {
				$cart_token = JRequest::getVar( 'cart_token', '' );
				
				$query = ' SELECT * FROM #__flexicontent_downloads_cart WHERE id='. $cart_id;
				$db->setQuery( $query );
				$cart = $db->loadObject();
				
				if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
				if (!$cart) { echo JText::_('cart id no '.$cart_id.', was not found'); jexit(); }
				
				$cart_token_matches = $cart_token==$cart->token;  // no access will be checked
				$nodes = json_decode($cart->json);
			}
			
			
			// Some validation check
			if ( !is_array($nodes) ) {
				$app->enqueueMessage("Tree structure is empty or invalid", 'notice');
				$this->setRedirect('index.php', '');
				return;
			}
			
			$app = JFactory::getApplication();
			$tmp_ffname = 'fcmd_uid_'.$user->id.'_'.date('Y-m-d__H-i-s');
			$targetpath = JPath::clean($app->getCfg('tmp_path') .DS. $tmp_ffname);
			
			$tree_files = $this->_traverseFileTree($nodes, $targetpath);
			//echo "<pre>"; print_r($tree_files); jexit();
			
			if ( empty($tree_files) ) {
				$app->enqueueMessage("No files selected for download", 'notice');
				$this->setRedirect('index.php', '');
				return;
			}
		} else {
			$file_node = new stdClass();
			$file_node->fieldid   = JRequest::getInt( 'fid', 0 );
			$file_node->contentid = JRequest::getInt( 'cid', 0 );
			$file_node->fileid    = JRequest::getInt( 'id', 0 );
			
			$coupon_id    = JRequest::getInt( 'conid', 0 );
			$coupon_token = JRequest::getString( 'contok', '' );
			
			if ( $coupon_id )
			{
				$_nowDate = 'UTC_TIMESTAMP()';
				$_nullDate = $db->Quote( $db->getNullDate() );
				$query = ' SELECT *'
					.', CASE WHEN '
					.'   expire_on = '.$_nullDate.'   OR   expire_on > '.$_nowDate
					.'  THEN 0 ELSE 1 END AS has_expired'
					.', CASE WHEN '
					.'   hits_limit = -1   OR   hits < hits_limit'
					.'  THEN 0 ELSE 1 END AS has_reached_limit'
					.' FROM #__flexicontent_download_coupons'
					.' WHERE id='. $coupon_id .' AND token='. $db->Quote( $coupon_token )
					;
				$db->setQuery( $query );
				$coupon = $db->loadObject();
				
				if ($db->getErrorNum())  {
					echo __FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg());
					jexit();
				}
				
				if ($coupon) {
					$slink_valid_coupon = !$coupon->has_reached_limit && !$coupon->has_expired ;
					if ( !$slink_valid_coupon ) {
						$query = ' DELETE FROM #__flexicontent_download_coupons WHERE id='. $coupon->id;
						$db->setQuery( $query );
						$db->query();
					}
				}
				
				$file_node->coupon = !empty($coupon) ? $coupon : false;  // NULL will not be catched by isset()
			}
			$tree_files = array($file_node);
		}
		
		
		// **************************************************
		// Create and Execute SQL query to retrieve file info
		// **************************************************
		
		// Create SELECT OR JOIN / AND clauses for checking Access
		$access_clauses['select'] = '';
		$access_clauses['join']   = '';
		$access_clauses['and']    = '';
		$using_access = empty($cart_token_matches) && empty($slink_valid_coupon);
		if ( $using_access ) {
			// note CURRENTLY multi-download feature does not use coupons
			$access_clauses = $this->_createFieldItemAccessClause( $get_select_access = true, $include_file = true );
		}
		
		
		// ***************************
		// Get file data for all files
		// ***************************
		
		$fields_props = array();
		$fields_conf  = array();
		$valid_files  = array();
		$email_recipients = array();
		foreach ($tree_files as $file_node)
		{
			// Get file variable shortcuts (reforce being int)
			$field_id   = (int) $file_node->fieldid;
			$content_id = (int) $file_node->contentid;
			$file_id    = (int) $file_node->fileid;
			
			if ( !isset($fields_conf[$field_id]) ) {
				$q = 'SELECT attribs, name, field_type FROM #__flexicontent_fields WHERE id = '.(int) $field_id;
				$db->setQuery($q);
				$fld = $db->loadObject();
				$fields_conf[$field_id] = FLEXI_J16GE ? new JRegistry($fld->attribs) : new JParameter($fld->attribs);
				$fields_props[$field_id] = $fld;
			}
			$field_type = $fields_props[$field_id]->field_type;
			
			$query  = 'SELECT f.id, f.filename, f.altname, f.secure, f.url'
					. ', i.title as item_title, i.introtext as item_introtext, i.fulltext as item_fulltext, u.email as item_owner_email'
					
					// item and current category slugs (for URL in notifications)
					. ', CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as itemslug'
					. ', CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug'
					
					. ', dh.id as history_id'  // download history
					. $access_clauses['select']  // has access
					
					.' FROM #__flexicontent_files AS f '
					.($field_type=='file' ? ' LEFT JOIN #__flexicontent_fields_item_relations AS rel ON rel.field_id = '. $field_id : '')  // Only check value usage for 'file' field
					.' LEFT JOIN #__flexicontent_fields AS fi ON fi.id = '. $field_id
					.' LEFT JOIN #__content AS i ON i.id = '. $content_id
					.' LEFT JOIN #__categories AS c ON c.id = i.catid'
					.' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
					.' LEFT JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
					.' LEFT JOIN #__users AS u ON u.id = i.created_by'
					.' LEFT JOIN #__flexicontent_download_history AS dh ON dh.file_id = f.id AND dh.user_id = '. (int)$user->id
					. $access_clauses['join']
					.' WHERE i.id = ' . $content_id
					.' AND fi.id = ' . $field_id
					.' AND f.id = ' . $file_id
					.' AND f.published= 1'
					. $access_clauses['and']
					;
			$db->setQuery($query);
			$file = $db->loadObject();
			if ($db->getErrorNum())  {
				echo __FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg());
				jexit();
			}
			//echo "<pre>". print_r($file, true) ."</pre>"; exit;
			
			
			// **************************************************************
			// Check if file was found AND IF user has required Access Levels
			// **************************************************************
			
			if ( empty($file) || ($using_access && (!$file->has_content_access || !$file->has_field_access || !$file->has_file_access)) )
			{
				if (empty($file)) {
					$msg = JText::_('FLEXI_FDC_FAILED_TO_FIND_DATA');     // Failed to match DB data to the download URL data
				}
				
				else {
					$msg = JText::_( 'FLEXI_ALERTNOTAUTH' );
					
					if ( !empty($file_node->coupon) ) {
						if ( $file_node->coupon->has_expired )              $msg .= JText::_('FLEXI_FDC_COUPON_HAS_EXPIRED');         // No access and given coupon has expired
						else if ( $file_node->coupon->has_reached_limit )   $msg .= JText::_('FLEXI_FDC_COUPON_REACHED_USAGE_LIMIT'); // No access and given coupon has reached download limit
						else $msg = "unreachable code in download coupon handling";
					}
					
					else {
						if ( isset($file_node->coupon) )  $msg .= "<br/> <small>".JText::_('FLEXI_FDC_COUPON_NO_LONGER_USABLE')."</small>";
						$msg .= ''
							.(!$file->has_content_access ? "<br/><br/> ".JText::_('FLEXI_FDC_NO_ACCESS_TO')
									." -- ".JText::_('FLEXI_FDC_CONTENT_CONTAINS')." ".JText::_('FLEXI_FDC_WEBLINK')
									."<br/><small>(".JText::_('FLEXI_FDC_CONTENT_EXPLANATION').")</small>"
								: '')
							.(!$file->has_field_access ? "<br/><br/> ".JText::_('FLEXI_FDC_NO_ACCESS_TO')
									." -- ".JText::_('FLEXI_FDC_FIELD_CONTAINS')." ".JText::_('FLEXI_FDC_WEBLINK')
								: '')
							.(!$file->has_file_access ? "<br/><br/> ".JText::_('FLEXI_FDC_NO_ACCESS_TO') ." -- ".JText::_('FLEXI_FDC_FILE')." " : '')
						;
					}
					$msg .= "<br/><br/> ". JText::sprintf('FLEXI_FDC_FILE_DATA', $file_id, $content_id, $field_id);
					$app->enqueueMessage($msg,'notice');
				}
				
				// Only abort for single file download
				if ($task != 'download_tree') {
					$this->setRedirect('index.php', '');
					return;
				}
			}
			
			
			// ****************************************************
			// (for non-URL) Create file path and check file exists
			// ****************************************************
			
			if ( !$file->url )
			{
				$basePath = $file->secure ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH;
				$file->abspath = str_replace(DS, '/', JPath::clean($basePath.DS.$file->filename));
				
				if ( !JFile::exists($file->abspath) )
				{
					$msg = JText::_( 'FLEXI_REQUESTED_FILE_DOES_NOT_EXIST_ANYMORE' );
					$app->enqueueMessage($msg, 'notice');
					
					// Only abort for single file download
					if ($task != 'download_tree') { $this->setRedirect('index.php', ''); return; }
				}
			}
			
			
			// *********************************************************************
			// Increment hits counter of file, and hits counter of file-user history
			// *********************************************************************
			
			$filetable = JTable::getInstance('flexicontent_files', '');
			$filetable->hit($file_id);
			if ( empty($file->history_id) ) {
				$query = ' INSERT #__flexicontent_download_history '
					. ' SET user_id = ' . (int)$user->id
					. '  , file_id = ' . $file_id
					. '  , last_hit_on = NOW()'
					. '  , hits = 1'
					;
			} else {
				$query = ' UPDATE #__flexicontent_download_history '
					. ' SET last_hit_on = NOW()'
					. '  , hits = hits + 1'
					. ' WHERE id = '. (int)$file->history_id
					;
			}
			$db->setQuery( $query );
			$db->query();
			
			
			// **************************************************************************************************
			// Increment hits on download coupon or delete the coupon if it has expired due to date or hits limit 
			// **************************************************************************************************
			if ( !empty($file_node->coupon) ) {
				if ( !$file_node->coupon->has_reached_limit && !$file_node->coupon->has_expired ) {
					$query = ' UPDATE #__flexicontent_download_coupons'
						.' SET hits = hits + 1'
						.' WHERE id='. $file_node->coupon->id
						;
					$db->setQuery( $query );
					$db->query();
				}
			}
			
			
			// **************************
			// Special case file is a URL
			// **************************
			
			if ($file->url)
			{
				// skip url-based file if downloading multiple files
				if ($task=='download_tree') {
					$msg = "Skipped URL based file: ".$file->url;
					$app->enqueueMessage($msg, 'notice');
					continue;
				}
				
				// redirect to the file download link
				@header("Location: ".$file->filename."");
				$app->close();
			}
			
			
			// *********************************************************************
			// Set file (tree) node and assign file into valid files for downloading
			// *********************************************************************
			
			$file->node = $file_node;
			$valid_files[$file_id] = $file;
			
			if ( $fields_conf[$field_id]->get('send_notifications') ) {
				
				// Calculate (once per file) some text used for notifications
				$file->__file_title__ = $file->altname && $file->altname != $file->filename ? 
					$file->altname . ' ['.$file->filename.']'  :  $file->filename;
				
				$file->__item_url__ = JRoute::_(FlexicontentHelperRoute::getItemRoute($file->itemslug, $file->catslug));
				
				// Parse and identify language strings and then make language replacements
				$notification_tmpl = $fields_conf[$field_id]->get('notification_tmpl');
				if ( empty($notification_tmpl) ) {
					$notification_tmpl = '%%FLEXI_FDN_FILE_NO%% __file_id__:  "__file_title__" '."\n";
					$notification_tmpl .= '%%FLEXI_FDN_FILE_IN_ITEM%% "__item_title__":' ."\n";
					$notification_tmpl .= '__item_url__';
				}
				
				$result = preg_match_all("/\%\%([^%]+)\%\%/", $notification_tmpl, $translate_matches);
				$translate_strings = $result ? $translate_matches[1] : array();
				foreach ($translate_strings as $translate_string)
					$notification_tmpl = str_replace('%%'.$translate_string.'%%', JText::_($translate_string), $notification_tmpl);
				$file->notification_tmpl = $notification_tmpl;
				
				// Send to hard-coded email list
				$send_all_to_email = $fields_conf[$field_id]->get('send_all_to_email');
				if ($send_all_to_email) {
					$emails = preg_split("/[\s]*;[\s]*/", $send_all_to_email);
					foreach($emails as $email) $email_recipients[$email][] = $file;
				}
				
				// Send to item owner
				$send_to_current_item_owner = $fields_conf[$field_id]->get('send_to_current_item_owner');
				if ($send_to_current_item_owner) {
					$email_recipients[$file->item_owner_email][] = $file;
				}
				
				// Send to email assigned to email field in same content item
				$send_to_email_field = (int) $fields_conf[$field_id]->get('send_to_email_field');
				if ($send_to_email_field) {

					$q  = 'SELECT value '
						.' FROM #__flexicontent_fields_item_relations '
						.' WHERE field_id = ' . $send_to_email_field .' AND item_id='.$content_id;
					$db->setQuery($q);
					$email_values = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
					
					foreach ($email_values as $i => $email_value) {
						if ( @unserialize($email_value)!== false || $email_value === 'b:0;' ) {
							$email_values[$i] = unserialize($email_value);
						} else {
							$email_values[$i] = array('addr' => $email_value, 'text' => '');
						}
						$addr = @ $email_values[$i]['addr'];
						if ( $addr ) {
							$email_recipients[$addr][] = $file;
						}
					}
				}
			}
		}
		//echo "<pre>". print_r($valid_files, true) ."</pre>";
		//echo "<pre>". print_r($email_recipients, true) ."</pre>";
		//sjexit();
		
		
		if ( !empty($email_recipients) ) {
			ob_start();
			$sendermail	= $app->getCfg('mailfrom');
			$sendermail	= JMailHelper::cleanAddress($sendermail);
			$sendername	= $app->getCfg('sitename');
			$subject    = JText::_('FLEXI_FDN_FILE_DOWNLOAD_REPORT');
			$message_header = JText::_('FLEXI_FDN_FILE_DOWNLOAD_REPORT_BY') .': '. $user->name .' ['.$user->username .']';
			
			
			// ****************************************************
			// Send email notifications about file being downloaded
			// ****************************************************
			
			// Personalized email per subscribers
			foreach ($email_recipients as $email_addr => $files_arr)
			{
				$to = JMailHelper::cleanAddress($email_addr);
				$_message = $message_header;
				foreach($files_arr as $filedata) {
					$_mssg_file = $filedata->notification_tmpl;
					$_mssg_file = str_ireplace('__file_id__', $filedata->id, $_mssg_file);
					$_mssg_file = str_ireplace('__file_title__', $filedata->__file_title__, $_mssg_file);
					$_mssg_file = str_ireplace('__item_title__', $filedata->item_title, $_mssg_file);
					//$_mssg_file = str_ireplace('__item_title_linked__', $filedata->password, $_mssg_file);
					$_mssg_file = str_ireplace('__item_url__', $filedata->__item_url__, $_mssg_file);
					$_message .= "\n\n" . $_mssg_file;
				}
				//echo "<pre>". $_message ."</pre>";
				
				$from = $sendermail;
				$fromname = $sendername;
				$recipient = array($to);
				$html_mode=false; $cc=null; $bcc=null;
				$attachment=null; $replyto=null; $replytoname=null;
				
				$send_result = FLEXI_J16GE ?
					JFactory::getMailer()->sendMail( $from, $fromname, $recipient, $subject, $_message, $html_mode, $cc, $bcc, $attachment, $replyto, $replytoname ) :
					JUtility::sendMail( $from, $fromname, $recipient, $subject, $_message, $html_mode, $cc, $bcc, $attachment, $replyto, $replytoname );
			}
			ob_end_clean();
		}
		
		
		// * Required for IE, otherwise Content-disposition is ignored
		if (ini_get('zlib.output_compression')) {
			ini_set('zlib.output_compression', 'Off');
		}
		
		if ($task=='download_tree') {
			// Create target (top level) folder
			JFolder::create($targetpath, 0755);
			// Copy Files
			foreach ($valid_files as $file) JFile::copy($file->abspath, $file->node->targetpath);
			
			// Create text/html file with ITEM title / descriptions
			// TODO replace this with a TEMPLATE file ...
			$desc_filename = $targetpath .DS. "_descriptions";
			$handle_txt = fopen($desc_filename.".txt", "w");
			$handle_htm = fopen($desc_filename.".htm", "w");
			fprintf($handle_htm, '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en-gb" lang="en-gb" dir="ltr" >
<head>
  <meta http-equiv="content-type" content="text/html; charset=utf-8" />		
</head>
<body>
'
			);
			foreach ($valid_files as $file) {
				fprintf($handle_txt, $file->item_title."\n\n");
				fprintf($handle_txt, flexicontent_html::striptagsandcut($file->item_introtext) ."\n\n" );
				if ( strlen($file->item_fulltext) ) fprintf($handle_txt, flexicontent_html::striptagsandcut($file->item_fulltext)."\n\n" );
				
				fprintf($handle_htm, "<h2>".$file->item_title."</h2>");
				fprintf($handle_htm, "<blockquote>".$file->item_introtext."</blockquote><br/>");
				if ( strlen($file->item_fulltext) ) fprintf($handle_htm, "<blockquote>".$file->item_fulltext."</blockquote><br/>");
				fprintf($handle_htm, "<hr/><br/>");
			}
			fclose($handle_txt);
			fclose($handle_htm);
			
			// Get file list recursively, and calculate archive filename
			$fileslist   = JFolder::files($targetpath, '.', $recurse=true, $fullpath=true);
			$archivename = $tmp_ffname . (FLEXI_J16GE ? '.zip' : '.tar.gz');
			$archivepath = JPath::clean( $app->getCfg('tmp_path').DS.$archivename );
			
			// Create the archive
			if (!FLEXI_J16GE) {
				JArchive::create($archivepath, $fileslist, 'gz', '', $targetpath);
			} else {
				/*$app = JFactory::getApplication('administrator');
				$files = array();
				foreach ($fileslist as $i => $filename) {
					$files[$i]=array();
					$files[$i]['name'] = preg_replace("%^(\\\|/)%", "", str_replace($targetpath, "", $filename) );  // STRIP PATH for filename inside zip
					$files[$i]['data'] = implode('', file($filename));   // READ contents into string, here we use full path
					$files[$i]['time'] = time();
				}
				
				$packager = JArchive::getAdapter('zip');
				if (!$packager->create($archivepath, $files)) {
					$msg = JText::_('FLEXI_OPERATION_FAILED'). ": compressed archive could not be created";
					$app->enqueueMessage($msg, 'notice');
					$this->setRedirect('index.php', '');
					return;
				}*/
				
				$za = new flexicontent_zip();
				$res = $za->open($archivepath, ZipArchive::CREATE);
				if($res !== true) {
					$msg = JText::_('FLEXI_OPERATION_FAILED'). ": compressed archive could not be created";
					$app->enqueueMessage($msg, 'notice');
					$this->setRedirect('index.php', '');
					return;
				}
				$za->addDir($targetpath, "");
				$za->close();
			}
			
			// Remove temporary folder structure
			if (!JFolder::delete(($targetpath)) ) {
				$msg = "Temporary folder ". $targetpath ." could not be deleted";
				$app->enqueueMessage($msg, 'notice');
			}
			
			// Delete old files (they can not be deleted during download time ...)
			$tmp_path = JPath::clean($app->getCfg('tmp_path'));
			$matched_files = JFolder::files($tmp_path, 'fcmd_uid_.*', $recurse=false, $fullpath=true);
			foreach ($matched_files as $archive_file) {
				//echo "Seconds passed:". (time() - filemtime($tmp_folder)) ."<br>". "$filename was last modified: " . date ("F d Y H:i:s.", filemtime($tmp_folder)) . "<br>";
				if (time() - filemtime($archive_file) > 3600) JFile::delete($archive_file);
			}
			
			// Delete old tmp folder (in case that the some archiving procedures were interrupted thus their tmp folder were not deleted)
			$matched_folders = JFolder::folders($tmp_path, 'fcmd_uid_.*', $recurse=false, $fullpath=true);
			foreach ($matched_folders as $tmp_folder) {
				//echo "Seconds passed:". (time() - filemtime($tmp_folder)) ."<br>". "$filename was last modified: " . date ("F d Y H:i:s.", filemtime($tmp_folder)) . "<br>";
				JFolder::delete($tmp_folder);
			}
			
			$dlfile = new stdClass();
			$dlfile->filename = 'cart_files_'.date('m-d-Y_H-i-s').(FLEXI_J16GE ? '.zip' : '.tar.gz');   // a friendly name instead of  $archivename
			$dlfile->abspath  = $archivepath;
		} else {
			$dlfile = reset($valid_files);
		}
		
		// Get file filesize and extension
		$dlfile->size = filesize($dlfile->abspath);
		$dlfile->ext  = strtolower(JFile::getExt($dlfile->filename));
		
		// Set content type of file (that is an archive for multi-download)
		$ctypes = array(
			"pdf" => "application/pdf", "exe" => "application/octet-stream", "rar" => "application/zip", "zip" => "application/zip",
			"txt" => "text/plain", "doc" => "application/msword", "xls" => "application/vnd.ms-excel", "ppt" => "application/vnd.ms-powerpoint",
			"gif" => "image/gif", "png" => "image/png", "jpeg" => "image/jpg", "jpg" => "image/jpg", "mp3" => "audio/mpeg"
		);
		$dlfile->ctype = isset($ctypes[$dlfile->ext]) ? $ctypes[$dlfile->ext] : "application/force-download";
		
		
		// *****************************************
		// Output an appropriate Content-Type header
		// *****************************************
		header("Pragma: public"); // required
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private", false); // required for certain browsers
		header("Content-Type: ".$dlfile->ctype);
		//quotes to allow spaces in filenames
		if ($method == 'view') {
			header("Content-Disposition: inline; filename=\"".$dlfile->filename."\";" );
		} else {
			header("Content-Disposition: attachment; filename=\"".$dlfile->filename."\";" );
		}
		header("Content-Transfer-Encoding: binary");
		header("Content-Length: ".$dlfile->size);
		
		
		// *******************************
		// Finally read file and output it
		// *******************************
		
		//readfile($dlfile->abspath);  // this will read an output the file but it will cause a memory exhausted error on large files
		
		set_time_limit(0);
		$handle = @fopen($dlfile->abspath,"rb");
		while(!feof($handle))
		{
			print(@fread($handle, 1024*8));
			ob_flush();
			flush();
		}
		
		// ****************************************************
		// In case of multi-download clear the session variable
		// ****************************************************
		//if ($task=='download_tree') $session->set($tree_var, false,'flexicontent');
		
		// Done ... terminate execution
		$app->close();
	}
	
	
	/**
	 * External link logic
	 *
	 * @access public
	 * @since 1.5
	 */
	function weblink()
	{
		// Import and Initialize some joomla API variables
		$app   = JFactory::getApplication();
		$db    = JFactory::getDBO();
		$user  = JFactory::getUser();
		
		// Get HTTP REQUEST variables
		$field_id   = JRequest::getInt( 'fid', 0 );
		$content_id = JRequest::getInt( 'cid', 0 );
		$order      = JRequest::getInt( 'ord', 0 );
		
		
		// **************************************************
		// Create and Execute SQL query to retrieve file info
		// **************************************************
		
		// Create SELECT OR JOIN / AND clauses for checking Access
		$access_clauses['select'] = '';
		$access_clauses['join']   = '';
		$access_clauses['and']    = '';
		$access_clauses = $this->_createFieldItemAccessClause( $get_select_access = true, $include_file = false );
		
		$query  = 'SELECT value'
				. $access_clauses['select']
				.' FROM #__flexicontent_fields_item_relations AS rel'
				.' LEFT JOIN #__flexicontent_fields AS fi ON fi.id = rel.field_id'
				.' LEFT JOIN #__content AS i ON i.id = rel.item_id'
				.' LEFT JOIN #__categories AS c ON c.id = i.catid'
				.' LEFT JOIN #__flexicontent_items_ext AS ie ON ie.item_id = i.id'
				.' LEFT JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id'
				. $access_clauses['join']
				.' WHERE rel.item_id = ' . $content_id
				.' AND rel.field_id = ' . $field_id
				.' AND rel.valueorder = ' . $order
				. $access_clauses['and']
				;
		$db->setQuery($query);
		$link_data = $db->loadObject();
		if ($db->getErrorNum()) {
			echo __FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg());
			jexit();
		}
		
		
		// **************************************************************
		// Check if file was found AND IF user has required Access Levels
		// **************************************************************
		
		if ( empty($link_data) || (!$link_data->has_content_access || !$link_data->has_field_access) )
		{
			if (empty($link_data)) {
				$msg = JText::_('FLEXI_FDC_FAILED_TO_FIND_DATA');     // Failed to match DB data to the download URL data
			}
			
			else {
				$msg  = JText::_('FLEXI_ALERTNOTAUTH');
				$msg .= ""
					.(!$link_data->has_content_access ? "<br/><br/> ".JText::_('FLEXI_FDC_NO_ACCESS_TO')
							." -- ".JText::_('FLEXI_FDC_CONTENT_CONTAINS')." ".JText::_('FLEXI_FDC_WEBLINK')
							."<br/><small>(".JText::_('FLEXI_FDC_CONTENT_EXPLANATION').")</small>"
						: '')
					.(!$link_data->has_field_access ? "<br/><br/> ".JText::_('FLEXI_FDC_NO_ACCESS_TO')
							." -- ".JText::_('FLEXI_FDC_FIELD_CONTAINS')." ".JText::_('FLEXI_FDC_WEBLINK')
						: '')
				;
				$msg .= "<br/><br/> ". JText::sprintf('FLEXI_FDC_WEBLINK_DATA', $order, $content_id, $field_id);
				$app->enqueueMessage($msg,'notice');
			}
			// Abort redirecting to home
			$this->setRedirect('index.php', '');
			return;
		}
		
		
		// **********************
		// Increment hits counter
		// **********************
		
		// recover the link array (url|title|hits)
		$link = unserialize($link_data->value);
		
		// get the url from the array
		$url = $link['link'];
		
		// update the hit count
		$link['hits'] = (int)$link['hits'] + 1;
		$value = serialize($link);
		
		// update the array in the DB
		$query 	= 'UPDATE #__flexicontent_fields_item_relations'
				.' SET value = ' . $db->Quote($value)
				.' WHERE item_id = ' . $content_id
				.' AND field_id = ' . $field_id
				.' AND valueorder = ' . $order
				;
		$db->setQuery($query);
		if (!$db->query()) {
			return JError::raiseWarning( 500, $db->getError() );
		}
		
		
		// ***************************
		// Finally redirect to the URL
		// ***************************
		
		@header("Location: ".$url."","target=blank");
		$app->close();
	}
	
	
	// Private common method to create join + and-where SQL CLAUSEs, for checking access of field - item pair(s), IN FUTURE maybe moved
	function _createFieldItemAccessClause($get_select_access = false, $include_file = false )
	{
		$user  = JFactory::getUser();
		$select_access = $joinacc = $andacc = '';
		
		// Access Flags for: content item and field
		if ( $get_select_access ) {
			$select_access = '';
			if (FLEXI_J16GE) {
				$aid_arr = $user->getAuthorisedViewLevels();
				$aid_list = implode(",", $aid_arr);
				if ($include_file) $select_access .= ', CASE WHEN'.
					'   f.access IN (0,'.$aid_list.')  THEN 1 ELSE 0 END AS has_file_access';
				$select_access .= ', CASE WHEN'.
					'  fi.access IN (0,'.$aid_list.')  THEN 1 ELSE 0 END AS has_field_access';
				$select_access .= ', CASE WHEN'.
					'  ty.access IN (0,'.$aid_list.') AND '.
					'   c.access IN (0,'.$aid_list.') AND '.
					'   i.access IN (0,'.$aid_list.')'.
					' THEN 1 ELSE 0 END AS has_content_access';
			} else {
				$aid = (int) $user->get('aid');
				if (FLEXI_ACCESS) {
					if ($include_file) $select_access .= ', CASE WHEN'.
						'   (gf.aro IN ( '.$user->gmid.' ) OR  f.access <= '. $aid . ')  THEN 1 ELSE 0 END AS has_file_access';
					$select_access .= ', CASE WHEN'.
						'  (gfi.aro IN ( '.$user->gmid.' ) OR fi.access <= '. $aid . ')  THEN 1 ELSE 0 END AS has_field_access';
					$select_access .= ', CASE WHEN'.
						'   (gt.aro IN ( '.$user->gmid.' ) OR ty.access <= '. $aid . ') AND '.
						'   (gc.aro IN ( '.$user->gmid.' ) OR  c.access <= '. $aid . ') AND '.
						'   (gi.aro IN ( '.$user->gmid.' ) OR  i.access <= '. $aid . ')'.
						' THEN 1 ELSE 0 END AS has_content_access';
				} else {
					if ($include_file) $select_access .= ', CASE WHEN'.
						'   f.access <= '. $aid . '  THEN 1 ELSE 0 END AS has_file_access';
					$select_access .= ', CASE WHEN'.
						' fi.access <= '. $aid . '  THEN 1 ELSE 0 END AS has_field_access';
					$select_access .= ', CASE WHEN'.
						'  ty.access <= '. $aid . ' AND '.
						'   c.access <= '. $aid . ' AND '.
						'   i.access <= '. $aid .
						' THEN 1 ELSE 0 END AS has_content_access';
				}
			}
		}
		
		else {
			if (FLEXI_J16GE) {
				$aid_arr = $user->getAuthorisedViewLevels();
				$aid_list = implode(",", $aid_arr);
				if ($include_file)
					$andacc .= ' AND  f.access IN (0,'.$aid_list.')';  // AND file access
				$andacc   .= ' AND fi.access IN (0,'.$aid_list.')';  // AND field access
				$andacc   .= ' AND ty.access IN (0,'.$aid_list.')  AND  c.access IN (0,'.$aid_list.')  AND  i.access IN (0,'.$aid_list.')';  // AND content access
			} else {
				$aid = (int) $user->get('aid');
				if (FLEXI_ACCESS) {
					if ($include_file) $andacc .=
						' AND  (gf.aro IN ( '.$user->gmid.' ) OR f.access <= '. $aid . ' OR f.access IS NULL)';  // AND file access
					$andacc   .=
						' AND (gfi.aro IN ( '.$user->gmid.' ) OR fi.access <= '. $aid . ')';  // AND field access
					$andacc   .=
						' AND (gt.aro IN ( '.$user->gmid.' ) OR ty.access <= '. $aid . ')';   // AND content access: type, cat, item
						' AND  (gc.aro IN ( '.$user->gmid.' ) OR  c.access <= '. $aid . ')';
						' AND  (gi.aro IN ( '.$user->gmid.' ) OR  i.access <= '. $aid . ')';
				} else {
					if ($include_file)
						$andacc .= ' AND (f.access <= '.$aid .' OR f.access IS NULL)';  // AND file access
					$andacc   .= ' AND fi.access <= '.$aid ;                          // AND field access
					$andacc   .= ' AND ty.access <= '.$aid . ' AND  c.access <= '.$aid . ' AND  i.access <= '.$aid ;  // AND content access
				}
			}
		}
		
		if (FLEXI_ACCESS) {
			if ($include_file)
				$joinacc .= ' LEFT JOIN #__flexiaccess_acl AS gf ON f.id = gf.axo AND gf.aco = "read" AND gf.axosection = "file"';        // JOIN file access
			$joinacc   .= ' LEFT JOIN #__flexiaccess_acl AS gfi ON fi.id = gfi.axo AND gfi.aco = "read" AND gfi.axosection = "field"';  // JOIN field access
			$joinacc   .= ' LEFT JOIN #__flexiaccess_acl AS gt ON ty.id = gt.axo AND gt.aco = "read" AND gt.axosection = "type"';       // JOIN content access: type, cat, item
			$joinacc   .= ' LEFT JOIN #__flexiaccess_acl AS gc ON  c.id = gc.axo AND gc.aco = "read" AND gc.axosection = "category"';
			$joinacc   .= ' LEFT JOIN #__flexiaccess_acl AS gi ON  i.id = gi.axo AND gi.aco = "read" AND gi.axosection = "item"';
		}
		
		$clauses['select'] = $select_access;
		$clauses['join']   = $joinacc;
		$clauses['and']    = $andacc;
		return $clauses;
	}
	
	
	/**
	 * Method to fetch the tags form
	 * 
	 * @since 1.5
	 */
	function viewtags() {
		// Check for request forgeries
		JRequest::checkToken('request') or jexit( 'Invalid Token' );

		$user = JFactory::getUser();
		if (FLEXI_J16GE) {
			$CanUseTags = FlexicontentHelperPerm::getPerm()->CanUseTags;
		} else if (FLEXI_ACCESS) {
			$CanUseTags = ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'usetags', 'users', $user->gmid) : 1;
		} else {
			$CanUseTags = 1;
		}

		if($CanUseTags) {
			//header('Content-type: application/json');
			@ob_end_clean();
			header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
			header("Cache-Control: no-cache");
			header("Pragma: no-cache");
			//header("Content-type:text/json");
			$model 		=  $this->getModel(FLEXI_ITEMVIEW);
			$tagobjs 	=  $model->gettags(JRequest::getVar('q'));
			$array = array();
			echo "[";
			foreach($tagobjs as $tag) {
				$array[] = "{\"id\":\"".$tag->id."\",\"name\":\"".$tag->name."\"}";
			}
			echo implode(",", $array);
			echo "]";
			jexit();
		}
	}
	
	function search()
	{
		// Strip characteres that will cause errors
		$badchars = array('#','>','<','\\'); 
		$searchword = trim(str_replace($badchars, '', JRequest::getString('searchword', null, 'post')));
		
		// If searchword is enclosed in double quotes, then strip quotes and do exact phrase matching
		if (substr($searchword,0,1) == '"' && substr($searchword, -1) == '"') { 
			$searchword = substr($searchword,1,-1);
			JRequest::setVar('searchphrase', 'exact');
			JRequest::setVar('searchword', $searchword);
		}
		
		// If no current menu itemid, then set it using the first menu item that points to the search view
		if (!JRequest::getVar('Itemid', 0)) {
			$menus = JFactory::getApplication()->getMenu();
			$items = $menus->getItems('link', 'index.php?option=com_flexicontent&view=search');
	
			if(isset($items[0])) {
				JRequest::setVar('Itemid', $items[0]->id);
			}
		}
		
		$model = $this->getModel(FLEXI_ITEMVIEW);
		$view  = $this->getView('search', 'html');
		$view->setModel($model);
		
		JRequest::setVar('view', 'search');
		parent::display(true);
	}
	
	
	function doPlgAct() {
		FLEXIUtilities::doPlgAct();
	}
}
