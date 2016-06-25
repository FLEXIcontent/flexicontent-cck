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

jimport('legacy.controller.legacy');
use Joomla\String\StringHelper;

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
		$this->registerTask( 'apply_type',     'save');
		$this->registerTask( 'apply',          'save');
		$this->registerTask( 'download_tree',  'download');
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
	function txtautocomplete()
	{
		global $globalcats;
		$app    = JFactory::getApplication();
		$cparams = JComponentHelper::getParams( 'com_flexicontent' );
		$option = JRequest::getVar('option');
		$use_tmp = true;
		
		$min_word_len = $app->getUserState( $option.'.min_word_len', 0 );
		$filtercat  = $cparams->get('filtercat', 0);      // Filter items using currently selected language
		$show_noauth = $cparams->get('show_noauth', 0);   // Show unauthorized items
		
		// Get request variables
		$type = JRequest::getVar('type');
		$text = JRequest::getVar('text');
		$pageSize = JRequest::getInt('pageSize', 20);
		$pageNum  = JRequest::getInt('pageNum', 1);
		
		$usesubs = JRequest::getInt('usesubs', 1);
		
		$cid   = JRequest::getInt('cid', 0);
		$cids  = JRequest::getVar('cids', '');
		
		// CASE 1: Single category view, zero or string means ignore and use 'cids'
		if ( $cid )
		{
			$_cids = array($cid);
		}
		
		// CASE 2: Multi category view
		else if ( !empty($cids) )
		{
			if ( !is_array($cids) ) {
				$_cids = preg_replace( '/[^0-9,]/i', '', (string) $cids );
				$_cids = explode(',', $_cids);
			} else $_cids = $cids;
		}
		
		// No category id was given
		else $_cids = array();
		
		
		// Make sure given data are integers ...
		$cids = array();
		if ($_cids) foreach ($_cids as $i => $_id)  if ((int)$_id) $cids[] = (int)$_id;
		
		// Sub - cats
		if ($usesubs)
		{
			// Find descendants of the categories
			$subcats = array();
			foreach ($cids as $_id) {
				if ( !isset($globalcats[$_id]) ) continue;
				$subcats = array_merge($subcats, $globalcats[$_id]->descendantsarray);
			}
			$cids = array_unique($subcats);
		}
		
		$cid_list = implode(',', $cids);
		
		$lang = flexicontent_html::getUserCurrentLang();
		
		// Nothing to do
		if ( $type!='basic_index' && $type!='adv_index' ) jexit();
		if ( !strlen($text) ) jexit();
		
		
		// All starting words are exact words but last word is a ... word prefix
		$search_prefix = JComponentHelper::getParams( 'com_flexicontent' )->get('add_search_prefix') ? 'vvv' : '';   // SEARCH WORD Prefix
		$words = preg_split('/\s\s*/u', $text);
		
		$_words = array();
		foreach ($words as & $_w)
			$_words[] = !$search_prefix  ?  trim($_w)  :  preg_replace('/(\b[^\s,\.]+\b)/u', $search_prefix.'$0', trim($_w));
		$newtext = '+' . implode( ' +', $_words ) .'*';  //print_r($_words); exit;
		
		// Query CLAUSE for match the given text
		$db = JFactory::getDBO();
		$quoted_text = $db->escape($newtext, true);
		$quoted_text = $db->Quote( $quoted_text, false );
		$_text_match  = ' MATCH (si.search_index) AGAINST ('.$quoted_text.' IN BOOLEAN MODE) ';
		
		// Query retieval limits
		$limitstart = $pageSize * ($pageNum - 1);
		$limit      = $pageSize;
		
		$lang_where = '';
		if ($filtercat) {
			$lang_where .= '   AND ( i.language LIKE ' . $db->Quote( $lang .'%' ) . (FLEXI_J16GE ? ' OR i.language="*" ' : '') . ' ) ';
		}
		
		$access_where = '';
		$joinaccess = '';
		/*if (!$show_noauth) {
			$user = JFactory::getUser();
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
			$aid_list = implode(",", $aid_arr);
			$access_where .= ' AND ty.access IN (0,'.$aid_list.')';
			$access_where .= ' AND mc.access IN (0,'.$aid_list.')';
			$access_where .= ' AND  i.access IN (0,'.$aid_list.')';
		}*/
		
		// Dates for publish up / down
		$_nowDate = 'UTC_TIMESTAMP()'; //$db->Quote($now);
		$nullDate = $db->getNullDate();
		
		// Do query ...
		$tbl = $type=='basic_index' ? 'flexicontent_items_ext' : 'flexicontent_advsearch_index';
		$query 	= 'SELECT si.item_id, si.search_index'    //.', '. $_text_match. ' AS score'  // THIS MAYBE SLOW
			.' FROM #__' . $tbl . ' AS si'
			.' JOIN '. ($use_tmp ? '#__flexicontent_items_tmp' : '#__content') .' AS i ON i.id = si.item_id'
			.(($access_where && !$use_tmp) || ($lang_where && !FLEXI_J16GE && !$use_tmp) || $type!='basic_index' ?
				' JOIN #__flexicontent_items_ext AS ie ON i.id = ie.item_id ' : '')
			.($access_where ? ' JOIN #__flexicontent_types AS ty ON ie.type_id = ty.id' : '')
			.($access_where ? ' JOIN #__categories AS mc ON mc.id = i.catid' : '')
			.($cid_list ? ' JOIN #__flexicontent_cats_item_relations AS rel ON i.id = rel.itemid AND rel.catid IN ('.$cid_list.')' : '')
			.$joinaccess
			.' WHERE '. $_text_match
			.'   AND i.state IN (1,-5) '   //(FLEXI_J16GE ? 2:-1) // TODO search archived
			.'   AND ( i.publish_up = '.$db->Quote($nullDate).' OR i.publish_up <= '.$_nowDate.' ) '
			.'   AND ( i.publish_down = '.$db->Quote($nullDate).' OR i.publish_down >= '.$_nowDate.' ) '
			. $lang_where
			. $access_where
			//.' ORDER BY score DESC'  // THIS MAYBE SLOW
			.' LIMIT '.$limitstart.', '.$limit
			;
		$db->setQuery( $query  );
		$data = $db->loadAssocList();
		//print_r($data); exit;
		//if ($db->getErrorNum())  echo __FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg());
		
		// Get last word (this is a word prefix) and remove it from words array
		$word_prefix = array_pop($words);
		
		// Reconstruct search text with complete words (not including last)
		$complete_words = implode(' ', $words);
		
		// Find out the words that matched
		$words_found = array();
		$regex = '/(\b)('.$search_prefix.$word_prefix.'\w*)(\b)/iu';
		
		foreach ($data as $_d) {
			//echo $_d['item_id'] . ' ';
			if (preg_match_all($regex, $_d['search_index'], $matches) ) {
				//print_r($matches[2]); exit;
				foreach ($matches[2] as $_m) {
					if ($search_prefix)
						$_m = preg_replace('/\b'.$search_prefix.'/u', '', $_m);
					$_m_low = StringHelper::strtolower($_m, 'UTF-8');
					$words_found[$_m_low] = 1;
				}
			}
		}
		//print_r($words_found); exit;
		
		// Pagination not appropriate when using autocomplete ...
		$options = array();
		$options['Total'] = count($words_found);
		
		// Create responce and JSON encode it
		$options['Matches'] = array();
		$n = 0;
		foreach ($words_found as $_w => $i) {
			if (!$search_prefix) {
				if ( StringHelper::strlen($_w) < $min_word_len ) continue;  // word too short
				if ( $this->isStopWord($_w, $tbl) ) continue;  // stopword or too common
			}
			
			$options['Matches'][] = array(
				'text' => $complete_words.($complete_words ? ' ' : '').$_w,
				'id' => $complete_words.($complete_words ? ' ' : '').$_w
			);
			$n++;
			if ($n >= $pageSize) break;
		}
		echo json_encode($options);
		jexit();
	}
	
	
	function isStopWord($word, $tbl='flexicontent_items_ext', $col='search_index') {
		$db = JFactory::getDBO();
		$quoted_word = $db->escape($word, true);
		$query = 'SELECT '.$col
			.' FROM #__'.$tbl
			.' WHERE MATCH ('.$col.') AGAINST ("+'.$quoted_word.'")'
			.' LIMIT 1';
		$db->setQuery($query);
		$result = $db->loadAssocList();
		return !empty($return) ? true : false;
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
		$db    = JFactory::getDBO();
		$user  = JFactory::getUser();
		$cid   = JRequest::getVar( 'id', array(), '', 'array' );
		JArrayHelper::toInteger($cid);
		
		require_once(JPATH_ROOT.DS."administrator".DS."components".DS."com_flexicontent".DS."models".DS."items.php");
		$model = new FlexicontentModelItems;
		$itemmodel = $this->getModel('item');
		$msg = '';

		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseNotice(500, JText::_( 'FLEXI_SELECT_ITEM_DELETE' ) );
		} else {
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
				$has_delete = false;
				$asset = 'com_content.article.' . $itemdata[$id]->id;
				$canDelete 		= $user->authorise('core.delete', $asset);
				$canDeleteOwn = $user->authorise('core.delete.own', $asset) && $itemdata[$id]->created_by == $user->get('id');
				
				if ( $canDelete || $canDeleteOwn ) {
					$auth_cid[] = $id;
				} else {
					$non_auth_cid[] = $id;
				}
			}
		}
		//echo "<pre>"; echo "authorized:\n"; print_r($auth_cid); echo "\n\nNOT authorized:\n"; print_r($non_auth_cid); echo "</pre>"; exit;
		
		// Set warning for undeletable items
		if (count($non_auth_cid)) {
			if (count($non_auth_cid) < 2) {
				$msg_noauth = JText::_( 'FLEXI_CANNOT_DELETE_ITEM' );
			} else {
				$msg_noauth = JText::_( 'FLEXI_CANNOT_DELETE_ITEMS' );
			}
			$msg_noauth .= ": " . implode(',', $non_auth_cid) ." - ". JText::_( 'FLEXI_REASON_NO_DELETE_PERMISSION' ) ." - ". JText::_( 'FLEXI_IDS_SKIPPED' );
			JError::raiseNotice(500, $msg_noauth);
		}
		
		// Try to delete 
		if ( count($auth_cid) && !$model->delete($auth_cid, $itemmodel) )
		{
			// Item not deleted set error message, and return url to the item url
			JError::raiseWarning(500, JText::_( 'FLEXI_OPERATION_FAILED' ));
			$link = JRoute::_(FlexicontentHelperRoute::getItemRoute($itemmodel->get('id').':'.$itemmodel->get('alias'), $globalcats[$itemmodel->get('catid')]->slug));
		}
		
		else {
			// Item deleted clean item-related caches
			$cache = FLEXIUtilities::getCache($group='', 0);
			$cache->clean('com_flexicontent_items');
			$cache->clean('com_flexicontent_filters');
			$cache = FLEXIUtilities::getCache($group='', 1);
			$cache->clean('com_flexicontent_items');
			$cache->clean('com_flexicontent_filters');
			
			// Item deleted set message, and return url to the home page // TODO return to category or other page
			$msg = count($auth_cid).' '.JText::_( 'FLEXI_ITEMS_DELETED' );
			$link = JRoute::_('index.php');
		}
		
		$this->setRedirect( $link, $msg );
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
		//echo '<html>  <meta http-equiv="content-type" content="text/html; charset=utf-8" /> <body>';
		
		// Initialize variables
		$app     = JFactory::getApplication();
		$db      = JFactory::getDBO();
		$user    = JFactory::getUser();
		$config  = JFactory::getConfig();
		$session = JFactory::getSession();
		$task	   = JRequest::getVar('task');
		$ctrl_task = 'task=items.';
		
		
		
		// *********************
		// Get data from request
		// *********************
		
		// Retrieve form data these are subject to basic filtering
		$data   = JRequest::getVar('jform', array(), 'post', 'array');   // Core Fields and and item Parameters
		$custom = JRequest::getVar('custom', array(), 'post', 'array');  // Custom Fields
		$jfdata = JRequest::getVar('jfdata', array(), 'post', 'array');  // Joomfish Data
		
		// Set into model: id (needed for loading correct item), and type id (e.g. needed for getting correct type parameters for new items)
		$data_id = (int) $data['id'];
		$isnew   = $data_id == 0;
		
		// If new make sure that type id is set too, before creating the model
		if ($isnew)
		{
			$typeid = JRequest::setvar('typeid', (int) @ $data['type_id']);
		}
		
		// Get the model
		$model = $this->getModel('item');
		$model->setId($data_id);  // Make sure id is correct
		
		// Get some flags this will also trigger item loading if not already loaded
		$isOwner = $model->get('created_by') == $user->get('id');
		
		
		// Get merged parameters: component, type, and (FE only) menu
		$params = new JRegistry();
		$model_params = $model->getComponentTypeParams();
		$params->merge($model_params);
		
		// Merge the active menu parameters
		$menu = $app->getMenu()->getActive();
		if ($menu) {
			$params->merge($menu->params);
		}
		
		// Get some needed parameters
		$submit_redirect_url_fe = $params->get('submit_redirect_url_fe', '');
		$dolog = $params->get('print_logging_info');
		
		// Get submit configuration override
		if ($isnew) {
			$h = $data['submit_conf'];
			$item_submit_conf = $session->get('item_submit_conf', array(),'flexicontent');
			
			$submit_conf      = @ $item_submit_conf[$h] ;
			$allowunauthorize = $params->get('allowunauthorize', 0);
			$autopublished    = @ $submit_conf['autopublished'];     // Override flag for both TYPE and CATEGORY ACL
			$overridecatperms = @ $submit_conf['overridecatperms'];  // Override flag for CATEGORY ACL
		}
		else {
			$submit_conf      = false;
			$allowunauthorize = false;
			$autopublished    = false;
			$overridecatperms = false;
		}
		
		// Unique id for new items, needed by some fields for temporary data
		$unique_tmp_itemid = JRequest::getVar( 'unique_tmp_itemid' );
		
		// Auto title for some content types
		if ( $params->get('auto_title', 0) )  $data['title'] = (int) $data['id'];  // item id or ZERO for new items
		
		if ( ! @ $data['rules'] ) $data['rules'] = array();
		
		
		// We use some strings from administrator part, load english language file
		// for 'com_flexicontent' component then override with current language file
		JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, 'en-GB', true);
		JFactory::getLanguage()->load('com_flexicontent', JPATH_ADMINISTRATOR, null, true);
		
		
		
		// *************************************
		// ENFORCE can change category ACL perms
		// *************************************
		
		$perms = FlexicontentHelperPerm::getPerm();
		
		// Per content type change category permissions
		$current_type_id  = ($isnew || !$model->get('type_id')) ? (int) @ $data['type_id'] : $model->get('type_id');  // GET current (existing/old) item TYPE ID
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
			if (!$isnew) {
				foreach($model->get('categories') as $item_cat) {
					if (isset($featured_tree[$item_cat]) && !isset($disabled_cats[$item_cat])) $featured_cid[] = $item_cat;
				}
			}
			$data['featured_cid'] = $featured_cid;
		}
		
		// Enforce maintaining secondary categories if user is not allowed to changed
		// or (FE only) if these were not submitted
		// *** NOTE *** this DOES NOT ENFORCE SUBMIT MENU category configuration, this is done later by the model store()
		if (
			!$enable_cid_selector   // user can not change / set secondary cats
			&& !$overridecatperms   // (FE) no submit menu override for category ACL
			&& empty($data['cid'])  // (FE) no secondary cats were submitted
		) {
			if ($isnew) {
			  // For new item use default secondary categories from type configuration
				$data['cid'] = $params->get('cid_default');
			}
			else if ( isset($featured_cid) ) {
				// Use featured cats if these are set
				$featured_cid_arr = array_flip($featured_cid);
				$sec_cid = array();
				foreach($model->get('cats') as $item_cat) if (!isset($featured_cid_arr[$item_cat])) $sec_cid[] = $item_cat;
				$data['cid'] = $sec_cid;
			}
			else {
				// Use already assigned categories (existing item)
				$data['cid'] = $model->get('cats');
			}
		}
		
		// Enforce maintaining main category if user is not allowed to change
		// or (FE only) if this was not submitted
		// *** NOTE *** this DOES NOT ENFORCE SUBMIT MENU category configuration, this is done later by the model store()
		if (
			!$enable_catid_selector   // user can not change / set main category
			&& !$overridecatperms     // (FE) no submit menu override for category ACL
			&& empty($data['catid'])  // (FE) no main category was submitted (FE)
		) {
			if ($isnew && $params->get('catid_default'))
			  // For new item use default main category from type configuration
				$data['catid'] = $params->get('catid_default');
			else if ($model->get('catid'))
				// Use already assigned main category (existing item)
				$data['catid'] = $model->get('catid');
		}
		
		
		
		// **************************
		// Basic Form data validation
		// **************************
		
		// Get the JForm object, but do not pass any data we only want the form object,
		// in order to validate the data and not create a filled-in form
		$form = $model->getForm();
		
		// *** MANUALLY CHECK CAPTCHA ***
		$use_captcha    = $params->get('use_captcha', 1);     // 1 for guests, 2 for any user
		$captcha_formop = $params->get('captcha_formop', 0);  // 0 for submit, 1 for submit/edit (aka always)
		$is_submitop = ((int) $data['id']) == 0;
		$display_captcha = $use_captcha >= 2 || ( $use_captcha == 1 &&  $user->guest );
		$display_captcha = $display_captcha && ( $is_submitop || $captcha_formop);  // for submit operation we do not need to check 'captcha_formop' ...
		if ($display_captcha)
		{
			$c_plugin = $params->get('captcha', $app->getCfg('captcha')); // TODO add param to override default
			if ($c_plugin) {
				$c_name = 'captcha_response_field';
				$c_value = JRequest::getString($c_name);
				$c_id = $c_plugin=='recaptcha' ? 'dynamic_recaptcha_1' : 'fc_dynamic_captcha';
				$c_namespace = 'fc_item_form';
				
				$captcha_obj = JCaptcha::getInstance($c_plugin, array('namespace' => $c_namespace));
				if (!$captcha_obj->checkAnswer($c_value))
				{
					// Get the captch validation message and push it out to the user
					//$error = $captcha_obj->getError();
					//$app->enqueueMessage($error instanceof Exception ? $error->getMessage() : $error, 'error');
					$app->enqueueMessage(JText::_('FLEXI_CAPTCHA_FAILED') .' '. JText::_('FLEXI_MUST_REFILL_SOME_FIELDS'), 'error');
					
					// Set POST form date into the session, so that they get reloaded
					$app->setUserState($form->option.'.edit.'.$form->context.'.data', $data);      // Save the jform data in the session.
					$app->setUserState($form->option.'.edit.'.$form->context.'.custom', $custom);  // Save the custom fields data in the session.
					$app->setUserState($form->option.'.edit.'.$form->context.'.jfdata', $jfdata);  // Save the falang translations into the session
					$app->setUserState($form->option.'.edit.'.$form->context.'.unique_tmp_itemid', $unique_tmp_itemid);  // Save temporary unique item id into the session
					
					// Redirect back to the item form
					$this->setRedirect( $_SERVER['HTTP_REFERER'] );
					
					if ( JRequest::getVar('fc_doajax_submit') )
					{
						echo flexicontent_html::get_system_messages_html();
						exit();  // Ajax submit, do not rerender the view
					}
					return false;
				}
			}
		}
		
		// Validate Form data for core fields and for parameters
		$post = $model->validate($form, $data);
		
		// Check for validation error
		if (!$post) {
			// Get the validation messages and push up to three validation messages out to the user
			$errors	= $form->getErrors();
			for ($i = 0, $n = count($errors); $i < $n && $i < 3; $i++) {
				$app->enqueueMessage($errors[$i] instanceof Exception ? $errors[$i]->getMessage() : $errors[$i], 'error');
			}
			
			// Set POST form date into the session, so that they get reloaded
			$app->setUserState($form->option.'.edit.'.$form->context.'.data', $data);      // Save the jform data in the session
			$app->setUserState($form->option.'.edit.'.$form->context.'.custom', $custom);  // Save the custom fields data in the session
			$app->setUserState($form->option.'.edit.'.$form->context.'.jfdata', $jfdata);  // Save the falang translations into the session
			$app->setUserState($form->option.'.edit.'.$form->context.'.unique_tmp_itemid', $unique_tmp_itemid);  // Save temporary unique item id into the session
			
			// Redirect back to the item form
			$this->setRedirect( $_SERVER['HTTP_REFERER'] );
			
			if ( JRequest::getVar('fc_doajax_submit') )
			{
				echo flexicontent_html::get_system_messages_html();
				exit();  // Ajax submit, do not rerender the view
			}
			return false; //die('error');
		}
		
		// Some values need to be assigned after validation
		$post['attribs'] = @$data['attribs'];  // Workaround for item's template parameters being clear by validation since they are not present in item.xml
		$post['custom']  = & $custom;          // Assign array of custom field values, they are in the 'custom' form array instead of jform
		$post['jfdata']  = & $jfdata;          // Assign array of Joomfish field values, they are in the 'jfdata' form array instead of jform
		
		// Assign template parameters of the select ilayout as an sub-array (the DB model will handle the merging of parameters)
		$ilayout = @ $data['attribs']['ilayout'];  // normal not be set if frontend template editing is not shown
		if( $ilayout && !empty($data['layouts'][$ilayout]) )
		{
			$post['attribs']['layouts'] = $data['layouts'];
			//echo "<pre>"; print_r($post['attribs']); exit;
		}
		
		// USEFULL FOR DEBUGING for J2.5 (do not remove commented code)
		//$diff_arr = array_diff_assoc ( $data, $post);
		//echo "<pre>"; print_r($diff_arr); jexit();
		
		
		// ********************************************************************************
		// PERFORM ACCESS CHECKS, NOTE: we need to check access again, despite having
		// checked them on edit form load, because user may have tampered with the form ... 
		// ********************************************************************************
		
		$itemAccess = $model->getItemAccess();
		$canAdd  = $itemAccess->get('access-create');  // includes check of creating in at least one category
		$canEdit = $itemAccess->get('access-edit');    // includes privileges edit and edit-own
		
		$type_id = (int) @ $post['type_id'];  // Typecast to int, (already done for J2.5 via validating)
		if ( !$isnew && $model->get('type_id') == $type_id ) {
			// Existing item with Type not being ALTERED, content type can be maintained regardless of privilege
			$canCreateType = true;
		} else {
			// New item or existing item with Type is being ALTERED, check privilege to create items of this type
			$canCreateType = $model->canCreateType( array($type_id), true, $types );
		}
		
		
		// *****************************************************************
		// Calculate user's CREATE / EDIT privileges on current content item
		// *****************************************************************
		
		$hasCoupon = false;  // Normally used in frontend only
		if (!$isnew)
		{
			// If no edit privilege, check if item is editable till logoff
			if ( !$canEdit ) {
				if ($session->has('rendered_uneditable', 'flexicontent')) {
					$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
					$canEdit = isset($rendered_uneditable[$model->get('id')]) && $rendered_uneditable[$model->get('id')];
					$hasCoupon = isset($rendered_uneditable[$model->get('id')]) && $rendered_uneditable[$model->get('id')] == 2;  // editable via coupon
				}
			}
		}
		
		else
		{
			// Allow creating via submit menu OVERRIDE
			if ( $allowunauthorize )
			{
				$canAdd = true;
				$canCreateType = true;
			}
			else {
				// If category override is enabled then only check type and do not check category ACL
				$canAdd = $canAdd ? $canAdd : ($overridecatperms && $canCreateType);
			}
		}
		
		// New item: check if user can create in at least one category
		if ($isnew && !$canAdd)
		{
			JError::raiseWarning( 403, JText::_( 'FLEXI_NO_ACCESS_CREATE' ) );
			$this->setRedirect( $_SERVER['HTTP_REFERER'] );
			if ( JRequest::getVar('fc_doajax_submit') ) {
				echo flexicontent_html::get_system_messages_html();
				exit();  // Ajax submit, do not rerender the view
			}
			return;
		}
		
		
		// Existing item: Check if user can edit current item
		if (!$isnew && !$canEdit)
		{
			JError::raiseWarning( 403, JText::_( 'FLEXI_NO_ACCESS_EDIT' ) );
			$this->setRedirect( $_SERVER['HTTP_REFERER'] );
			if ( JRequest::getVar('fc_doajax_submit') ) {
				echo flexicontent_html::get_system_messages_html();
				exit();  // Ajax submit, do not rerender the view
			}
			return;
		}

		if ( !$canCreateType ) {
			$msg = isset($types[$type_id]) ?
				JText::sprintf( 'FLEXI_NO_ACCESS_CREATE_CONTENT_OF_TYPE', JText::_($types[$type_id]->name) ) :
				' Content Type '.$type_id.' was not found OR is not published';
			JError::raiseWarning( 403, $msg );
			$this->setRedirect( $_SERVER['HTTP_REFERER'] );
			if ( JRequest::getVar('fc_doajax_submit') ) {
				echo flexicontent_html::get_system_messages_html();
				exit();  // Ajax submit, do not rerender the view
			}
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
			
			// Set POST form date into the session, so that they get reloaded
			$app->setUserState($form->option.'.edit.'.$form->context.'.data', $data);      // Save the jform data in the session
			$app->setUserState($form->option.'.edit.'.$form->context.'.custom', $custom);  // Save the custom fields data in the session
			$app->setUserState($form->option.'.edit.'.$form->context.'.jfdata', $jfdata);  // Save the falang translations into the session
			$app->setUserState($form->option.'.edit.'.$form->context.'.unique_tmp_itemid', $unique_tmp_itemid);  // Save temporary unique item id into the session
			
			// Saving has failed check-in and redirect back to the item form,
			// redirect back to the item form reloading the posted data
			$model->checkin();
			$this->setRedirect( $_SERVER['HTTP_REFERER'] );
			
			if ( JRequest::getVar('fc_doajax_submit') )
			{
				echo flexicontent_html::get_system_messages_html();
				exit();  // Ajax submit, do not rerender the view
			}
			return; //die('save error');
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
		$canPublish = $model->canEditState( $item, $check_cat_perm=true ) || $hasCoupon;
		
		
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
		$cache = FLEXIUtilities::getCache($group='', 0);
		$cache->clean('com_flexicontent_items');
		$cache->clean('com_flexicontent_filters');
		$cache = FLEXIUtilities::getCache($group='', 1);
		$cache->clean('com_flexicontent_items');
		$cache->clean('com_flexicontent_filters');
		
		
		// ****************************************************************************************************************************
		// Recalculate EDIT PRIVILEGE of new item. Reason for needing to do this is because we can have create permission in a category
		// and thus being able to set this category as item's main category, but then have no edit/editown permission for this category
		// ****************************************************************************************************************************
		$asset = 'com_content.article.' . $model->get('id');
		$canEdit = $user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && $isOwner);
		// ALTERNATIVE 1
		//$canEdit = $model->getItemAccess()->get('access-edit'); // includes privileges edit and edit-own
		// ALTERNATIVE 2
		//$rights = FlexicontentHelperPerm::checkAllItemAccess($user->get('id'), 'item', $model->get('id'));
		//$canEdit = in_array('edit', $rights) || (in_array('edit.own', $rights) && $isOwner) ;
		
		
		// *******************************************************************************************************
		// Check if user can not edit item further (due to changed main category, without edit/editown permission)
		// *******************************************************************************************************
		if (!$canEdit)
		{
			if ($task=='apply' || $task=='apply_type') {
				// APPLY TASK: Temporarily set item to be editable till closing it and not through all session
				// (we will/should clear this flag when item is closed, since we have another flag to indicate new items
				$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
				$rendered_uneditable[$model->get('id')] = -1;
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
		
		if ($task!='apply' && $task!='apply_type' && $newly_submitted_item )
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
		if ($task=='apply' || $task=='apply_type') {
			$msg = JText::_( 'FLEXI_ITEM_SAVED' );
			
			// Create the URL
			global $globalcats;
			$Itemid = JRequest::getInt('Itemid', 0);  // maintain current menu item if this was given
			$item_url = JRoute::_(FlexicontentHelperRoute::getItemRoute($item->id.':'.$item->alias, $globalcats[$item->catid]->slug, $Itemid));
			$link = $item_url
				.(strstr($item_url, '?') ? '&' : '?').'task=edit'
				;
			
			// Important pass referer back to avoid making the form itself the referer
			// but also check that referer URL is 'safe' (allowed) , e.g. not an offsite URL, otherwise set referer to HOME page
			$referer = JRequest::getString('referer', JURI::base(), 'post');
			if ( ! flexicontent_html::is_safe_url($referer) ) $referer = JURI::base();
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
				$link = JRoute::_(FlexicontentHelperRoute::getItemRoute($model->_item->id.':'.$model->_item->alias, $model->_item->catid, 0, $model->_item).'&amp;preview=1', false);
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
	function display($cachable = null, $urlparams = false)
	{
		// Debuging message
		//JError::raiseNotice(500, 'IN display()'); // TOREMOVE
		$CLIENT_CACHEABLE_PUBLIC = 1; $CLIENT_CACHEABLE_PRIVATE = 2;
		
		$jinput = JFactory::getApplication()->input;
		$userid = JFactory::getUser()->get('id');
		$cc     = $jinput->get('cc', null);
		$view   = $jinput->get('view', '', 'cmd');
		$layout = $jinput->get('layout', '', 'cmd');
		
		
		// Access checking for --items-- viewing, will be handled by the items model, this is because THIS display() TASK is used by other views too
		// in future it maybe moved here to the controller, e.g. create a special task item_display() for item viewing, or insert some IF bellow
		
		
		// ///////////////////////
		// Display case: ITEM FORM
		// ///////////////////////
		
		// Also a compatibility check: Layout is form and task is not set:  this is new item submit ...
		if ( $jinput->get('layout', false) == "form" && !$jinput->get('task', false)) {
			$jinput->set('browser_cachable', 0);
			$jinput->set('task', 'add');
			$this->add();
			return;
		}
		
		
		
		// //////////////////////////////////////////////////////////////////////////
		// Display case: FLEXIcontent frontend view (category, item, favourites, etc)
		// //////////////////////////////////////////////////////////////////////////
		
		
		// *******************
		// Handle SERVER Cache
		// *******************
		
		// SHOW RECENT FAVOURED ITEMS IMMEDIATELY: do not cache the view
		if ($view=='favourites' || ($view=='category' && $layout=='favs')) $cachable = false;
		
		// AVOID MAKING TOO LARGE (case 1): search view or other view with TEXT search active
		else if ($view=='search' || $jinput->get('filter')) $cachable = false;
				
		// AVOID MAKING TOO LARGE: (case 2) some field filters are active
		else {
			$cachable = true;
			foreach($_GET as $i => $v) {
				if (substr($i, 0, 7) === "filter_") {   $cachable = false;   break;   }
			}
		}
		
		
		// ********************
		// Handle browser Cache
		// ********************
		
		if ( $cc !== null ) {
			// Currently our plugin will ignore this and force 'private', because of risk to break 3rd party extensions doing cookie-based content per guest
			$browser_cachable = $userid ? $CLIENT_CACHEABLE_PRIVATE : $CLIENT_CACHEABLE_PUBLIC;
		} else {
			$browser_cachable = 0;
		}
		
		
		// CASE: urlparams were explicitely given
		if (!empty($urlparams)) $safeurlparams = & $urlparams;
		
		// CASE: urlparams are empty, use the FULL URL request array (_GET)
		else {
			$safeurlparams = array();
			
			// Add menu URL variables
			$menu = JFactory::getApplication()->getMenu()->getActive();
			if ($menu)
			{
				// Add menu Itemid to make sure that the menu items with --different-- parameter values, will display differently
				$safeurlparams['Itemid'] = 'STRING';
				
				// Add menu's HTTP query variables so that we match the non-SEF URL exactly, thus we create the same cache-ID for both SEF / non-SEF urls (purpose: save some cache space)
				foreach($menu->query as $_varname => $_ignore) $safeurlparams[$_varname] = 'STRING';
			}
			
			// Add any existing URL variables (=submitted via GET),  ... we only need variable names, (values are ignored)
			foreach($_GET as $_varname => $_ignore) $safeurlparams[$_varname] = 'STRING';
		}
		
		
		// If component is serving different pages to logged users, this will avoid
		// having users seeing same page after login/logout when conservative caching is used
		if ( $userid = JFactory::getUser()->get('id') )
		{
			$jinput->set('__fc_user_id__', $userid);
			$safeurlparams['__fc_user_id__'] = 'STRING';
		}
		
		$cparams = JComponentHelper::getParams( 'com_flexicontent' );
		$use_mobile_layouts  = $cparams->get('use_mobile_layouts', 0);
		$tabletSameAsDesktop = $cparams->get('force_desktop_layout', 0) == 1;
		
		// If component is serving different pages for mobile devices, this will avoid
		// having users seeing the same page regardless of being on desktop or mobile
		$mobileDetector = flexicontent_html::getMobileDetector();  //$client = JFactory::getApplication()->client; $isMobile = $client->mobile;
		$isMobile = $mobileDetector->isMobile();
		$isTablet = $mobileDetector->isTablet();
		if ( $use_mobile_layouts && $isMobile && (!$isTablet || !$tabletSameAsDesktop) )
		{
			$jinput->set('__fc_client__', 'Mobile' );
			$safeurlparams['__fc_client__'] = 'STRING';
		}
		
		// Moved code for browser's cache control to system plugin to do at the latest possible point
		// =0, NOT user brower CACHEABLE
		// >0, user browser CACHEABLE, ask browser to store and redisplay it, without revalidating
		// *** Intermediary Cache control
		// 1 means CACHEABLE, PUBLIC  content, proxies can cache: 'Cache-Control:public'
		// 2 means CACHEABLE, PRIVATE (logged user) content, proxies must not cache: 'Cache-Control:private'
		// null will let default (Joomla website) HTTP headers, e.g. re-validate
		$jinput->set('browser_cachable', $browser_cachable);
		
		//echo "cacheable: ".(int)$cachable." - " . print_r($safeurlparams, true) ."<br/>";
		parent::display($cachable, $safeurlparams);
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
		$document = JFactory::getDocument();
		
		// Get/Create the view
		$viewType   = $document->getType();
		$viewName   = $this->input->get('view', $this->default_view, 'cmd');
		$viewLayout = $this->input->get('layout', 'form', 'string');
		$view = $this->getView($viewName, $viewType, '', array('base_path' => $this->basePath, 'layout' => $viewLayout));
		
		// Get/Create the model
		$model = $this->getModel($viewName);
		
		// Push the model into the view (as default), later we will call the view display method instead of calling parent's display task, because it will create a 2nd model instance !!
		$view->setModel($model, true);
		$view->document = $document;
		
		// Call display method of the view, instead of calling parent's display task, because it will create a 2nd model instance !!
		$view->display();
		//parent::display();
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
		$document = JFactory::getDocument();
		
		// Get/Create the view
		$viewType   = $document->getType();
		$viewName   = $this->input->get('view', $this->default_view, 'cmd');
		$viewLayout = $this->input->get('layout', 'form', 'string');
		$view = $this->getView($viewName, $viewType, '', array('base_path' => $this->basePath, 'layout' => $viewLayout));
		
		// Get/Create the model
		$model = $this->getModel($viewName);
		
		// Push the model into the view (as default), later we will call the view display method instead of calling parent's display task, because it will create a 2nd model instance !!
		$view->setModel($model, true);
		$view->document = $document;
		
		// Call display method of the view, instead of calling parent's display task, because it will create a 2nd model instance !!
		$view->display();
		//parent::display();
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
		$isOwner = $model->get('created_by') == $user->get('id');
		
		// CHECK-IN the item if user can edit
		if ($model->get('id') > 1)
		{
			$asset = 'com_content.article.' . $model->get('id');
			$canEdit = $user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && $isOwner);
			// ALTERNATIVE 1
			//$canEdit = $model->getItemAccess()->get('access-edit'); // includes privileges edit and edit-own
			// ALTERNATIVE 2
			//$rights = FlexicontentHelperPerm::checkAllItemAccess($user->get('id'), 'item', $model->get('id'));
			//$canEdit = in_array('edit', $rights) || (in_array('edit.own', $rights) && $isOwner) ;
			
			if ( !$canEdit ) {
				// No edit privilege, check if item is editable till logoff
				if ($session->has('rendered_uneditable', 'flexicontent')) {
					$rendered_uneditable = $session->get('rendered_uneditable', array(),'flexicontent');
					$canEdit = isset($rendered_uneditable[$model->get('id')]) && $rendered_uneditable[$model->get('id')];
				}
			}
			if ($canEdit) $model->checkin();
		}
		
		// since the task is cancel, we go back to the form referer
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
		$id    = JRequest::getInt('id', 0);
		$type  = JRequest::getCMD('type', 'item');
		if ($type!='item' && $type!='category') {
			echo 'Type: '. $type .' not supported';
			jexit();
		}
		$model = $this->getModel($type);
		
		if (!$user->get('id'))
		{
			echo 'login';
			jexit();
		}
		else
		{
			$isfav = $model->getFavoured();
			
			if ($isfav)
			{
				$model->removefav();
				$favs = $model->getFavourites();
				if ($favs == 0) {
					echo 'removed';
				} else {
					echo '-'.$favs;
				}
			}
			else
			{
				$model->addfav();
				$favs = $model->getFavourites();
				if ($favs == 0) {
					echo 'added';
				} else {
					echo '+'.$favs;
				}
			}
		}
		jexit();
	}
	
	
	/**
	 *  Ajax review form
	 *
	 * @access public
	 * @since 3.0
	 */
	function getreviewform()
	{
		$html_tagid = JRequest::getCmd('tagid', '' );
		$content_id  = JRequest::getInt('content_id', '' );
		$review_type = JRequest::getCmd('review_type', 'item' );
		
		$user = JFactory::getUser();
		$db	= JFactory::getDBO();
		
		
		// ******************************
		// Get voting field configuration
		// ******************************
		
		if (!$content_id)  $error = "Content_id is zero";
		else if ($review_type!='item')  $error = "review_type <> item is not yet supported";
		else {
			// Check content item exists
			$item = JTable::getInstance( $type = 'flexicontent_items', $prefix = '', $config = array() );
			if ( !$item->load( $content_id ) )  $error = 'ID: '.$pk. ': '.$item->getError();
			
			// Check voting is enabled
			else {
				$db->setQuery('SELECT * FROM #__flexicontent_fields WHERE field_type="voting"');
				$field = $db->loadObject();
				FlexicontentFields::loadFieldConfig($field, $item);  // This will also load type configuration
				$allow_reviews = (int)$field->parameters->get('allow_reviews', 1);
				if (!$allow_reviews)  $error = "Reviews are disabled";
			}
		}
		
		if (!empty($error)) {
			$result	= new stdClass();
			$error = '
			<div class="fc-mssg fc-warning fc-nobgimage">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
				'.$error.'
			</div>';
			$result->html = $error;
			echo json_encode($result);
			jexit();
		}
		
		// Load review of a logged user
		$review = false;
		if ($user->id)
		{
			$query = "SELECT * "
				." FROM #__flexicontent_reviews_dev AS r"
				." WHERE r.content_id=" . $content_id
				."  AND r.type=". $db->Quote($review_type)
				."  AND r.user_id=". $user->id;				
			$db->setQuery($query);
			$review = $db->loadObject();
		}
		
		$result	= new stdClass();
		$result->html = '
		<form id="fcvote_review_form_'.$content_id.'" name="fcvote_form_'.$content_id.'">
			<input type="hidden" name="review_id"  value="'. ($review ? $review->id : '').'"/>
			<input type="hidden" name="content_id"  value="'.$content_id.'"/>
			<input type="hidden" name="review_type" value="'.$review_type.'"/>
			<table class="fc-form-tbl">
				<tr class="fcvote_review_form_title">
					<td class="key"><label class="label">'.JText::_('FLEXI_VOTE_REVIEW_TITLE').'</label></td>
					<td>
						<input type="text" name="title" size="200" value="'.htmlspecialchars( ($review ? $review->title : ''), ENT_COMPAT, 'UTF-8' ).'" />
					</td>
				</tr>
				<tr class="fcvote_review_form_email">
					<td class="key"><label class="label">'.JText::_('FLEXI_VOTE_REVIEW_EMAIL').'</label></td>
					<td>'.( !$user->id ? '
						<input type="text" name="email" size="200" value="'.htmlspecialchars( ($review ? $review->email : ''), ENT_COMPAT, 'UTF-8' ).'" />
						' : '<span class=badge>'.$user->email.'</span>' ).'
					</td>
				</tr>
				<tr class="fcvote_review_form_text">
					<td class="key"><label class="label">'.JText::_('FLEXI_VOTE_REVIEW_TEXT').'</label></td>
					<td class="top">
						<textarea name="text" rows="4" cols="200">'.($review ? $review->text : '').'</textarea>
					</td>
				</tr>
				<tr class="fcvote_review_form_text">
					<td></td>
					<td><input type="button" class="btn btn-primary fcvote_review_form_submit_btn" onclick="fcvote_submit_review_form(\''.$html_tagid.'\', this.form)" value="'.JText::_('FLEXI_VOTE_REVIEW_SUMBIT').'"/></td>
				</tr>
			</table>
		</form>';
		
		echo json_encode($result);
		jexit();
	}
	
	
	function storereviewform()
	{
		$review_id   = JRequest::getInt('review_id', 0);
		$content_id  = JRequest::getInt('content_id', '' );
		$review_type = JRequest::getCmd('review_type', 'item' );
		
		$user = JFactory::getUser();
		$db	= JFactory::getDBO();
		$error = false;
		
		// Validate title
		$title = flexicontent_html::dataFilter(JRequest::getVar('title'), $maxlength=255, 'STRING', 0);  // Decode entities, and strip HTML
		
		// Validate email
		$email = $user->id ? $user->email : flexicontent_html::dataFilter(JRequest::getVar('email'), $maxlength=255, 'EMAIL', 0);  // Validate email
		
		// Validate text
		$text = flexicontent_html::dataFilter(JRequest::getVar('text'), $maxlength=10000, 'STRING', 0);  // Validate text only: decode entities and strip HTML
		
		
		// ******************************
		// Get voting field configuration
		// ******************************
		
		if (!$content_id)  $error = "Content_id is zero";
		else if (!$email)  $error = "Email is invalid or empty";
		else if ($review_type!='item')  $error = "review_type <> item is not yet supported";
		else {
			// Check content item exists
			$item = JTable::getInstance( $type = 'flexicontent_items', $prefix = '', $config = array() );
			if ( !$item->load( $content_id ) )  $error = 'ID: '.$pk. ': '.$item->getError();
			
			// Check voting is enabled
			else {
				$db->setQuery('SELECT * FROM #__flexicontent_fields WHERE field_type="voting"');
				$field = $db->loadObject();
				FlexicontentFields::loadFieldConfig($field, $item);  // This will also load type configuration
				$allow_reviews = (int)$field->parameters->get('allow_reviews', 1);
				if (!$allow_reviews)  $error = "Reviews are disabled";
			}
		}
		
		if (!empty($error)) {
			$result	= new stdClass();
			$error = '
			<div class="fc-mssg fc-warning fc-nobgimage">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
				'.$error.'
			</div>';
			$result->html = $error;
			echo json_encode($result);
			jexit();
		}
		
		// Check if review exists
		$review = JTable::getInstance( $type = 'flexicontent_reviews', $prefix = '', $config = array() );
		
		if ($user->id && $review_id)
		{
			if ( !$review->load( $review_id ) )  
			{
				$result	= new stdClass();
				$error = 'ID: '.$review_id. ': '.$review->getError();
				$result->html = $error;
				echo json_encode($result);
				jexit();
			}
			
			if ( $review.content_id == $content_id )  $error = "Found content_id <> given content_id: "  .$content_id;
			if ( !$review.type == $review_type )      $error = "Found review_type: " .$review.type.       " <> given review_type: " .$review_type;
			if ( $review.user_id == $user->id )       $error = "Found user_id <> given user_id: "     .$user->id;
			if ( ! empty($error) )
			{
				$result	= new stdClass();
				$result->html = $error;
				echo json_encode($result);
				jexit();
			}
		}
		
		$review->id = $review_id;
		$review->content_id = $content_id;
		$review->type  = $review_type;
		$review->title = $title;
		$review->email = $user->id ? '' : $email;
		$review->text  = $text;
		
		if ( !$review->store() )
		{
			$result	= new stdClass();
			$error = 'ID: '.$review_id. ': '.$review->getError();
			$result->html = $error;
			echo json_encode($result);
			jexit();
		}
		
		$result	= new stdClass();
		$result->html = $review_id ?
			'Existing review updated' :
			'New review saved' ;
		//$result->html .= '<pre>'.print_r($_REQUEST, true).'</pre>';
		
		echo json_encode($result);
		jexit();
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
		if ($no_ajax && !$cid)
		{
			$cid = JRequest::getInt('id'); // Joomla 's content plugin uses 'id' HTTP request variable
		}
		
		
		// *******************************************************************
		// Check for invalid xid (according to voting field/type configuration
		// *******************************************************************
		
		$xid = empty($xid) ? 'main' : $xid;
		$int_xid  = (int)$xid;
		if ($xid!='main' && !$int_xid)
		{
			// Rare/unreachable voting ERROR
			$error = "ajaxvote(): invalid xid '".$xid."' was given";
			
			// Set responce
			if ($no_ajax) {
				$app->enqueueMessage( $error, 'notice' );
				return;
			} else {
				$result	= new stdClass();
				$result->percentage = '';
				$result->htmlrating = '';
				$error = '
				<div class="fc-mssg fc-warning fc-nobgimage">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					'.$error.'
				</div>';
				if ($int_xid) $result->message = $error;  else $result->message_main = $error;
				echo json_encode($result);
				jexit();
			}
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
		
		$main_counter  = (int)$field->parameters->get('main_counter', 1);
		$extra_counter = (int)$field->parameters->get('extra_counter', 1);
		$main_counter_show_label  = (int)$field->parameters->get('main_counter_show_label', 1);
		$extra_counter_show_label = (int)$field->parameters->get('extra_counter_show_label', 1);
		$main_counter_show_percentage  = (int)$field->parameters->get('main_counter_show_percentage', 0);
		$extra_counter_show_percentage = (int)$field->parameters->get('extra_counter_show_percentage', 0);
		
		
		// *****************************************************
		// Find if user has the ACCESS level required for voting
		// *****************************************************
		
		$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
		$acclvl = (int) $field->parameters->get('submit_acclvl', 1);
		$has_acclvl = in_array($acclvl, $aid_arr);
		
		
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
				$acclvl_name = '';
				if ($acclvl) {
					$db->setQuery('SELECT title FROM #__viewlevels as level WHERE level.id='.$acclvl);
					$acclvl_name = $db->loadResult();
					if ( !$acclvl_name ) $acclvl_name = "Access Level: ".$acclvl." not found/was deleted";
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
			$error = !$has_acclvl ? $no_acc_msg : JText::sprintf( 'FLEXI_VOTE_OUT_OF_RANGE', $min_rating, $max_rating);
			
			// Set responce
			if ($no_ajax) {
				$app->enqueueMessage( $error, 'notice' );
				return;
			} else {
				$result	= new stdClass();
				$result->percentage = '';
				$result->htmlrating = '';
				$error = '
				<div class="fc-mssg fc-warning fc-nobgimage">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					'.$error.'
				</div>';
				if ($int_xid) $result->message = $error;  else $result->message_main = $error;
				echo json_encode($result);
				jexit();
			}
		}
		
		
		// *************************************************
		// Check extra vote exists and get extra votes types
		// *************************************************
		
		$xids = array();
		$enable_extra_votes = $field->parameters->get('enable_extra_votes', '');
		if ($enable_extra_votes)
		{
			// Retrieve and split-up extra vote types, (removing last one if empty)
			$extra_votes = $field->parameters->get('extra_votes', '');
			$extra_votes = preg_split( "/[\s]*%%[\s]*/", $field->parameters->get('extra_votes', '') );
			if ( empty($extra_votes[count($extra_votes)-1]) )
			{
				unset( $extra_votes[count($extra_votes)-1] );
			}
			
			// Split extra voting ids (xid) and their titles
			foreach ($extra_votes as $extra_vote) {
				@list($extra_id, $extra_title, $extra_desc) = explode("##", $extra_vote);
				$xids[$extra_id] = 1;
			}
		}
		
		if ( !$int_xid && count($xids) )
		{
			$error = JText::_('FLEXI_VOTE_AVERAGE_RATING_CALCULATED_AUTOMATICALLY');
		}
		
		if ( $int_xid && !isset($xids[$int_xid]) )
		{
			// Rare/unreachable voting ERROR
			$error = !$enable_extra_votes ? JText::_('FLEXI_VOTE_COMPOSITE_VOTING_IS_DISABLED') : 'Voting characteristic with id: '.$int_xid .' was not found';
		}
		
		if ( isset($error) ) {
			// Set responce
			if ($no_ajax) {
				$app->enqueueMessage( $error, 'notice' );
				return;
			} else {
				$result	= new stdClass();
				$result->percentage = '';
				$result->htmlrating = '';
				$error = '
				<div class="fc-mssg fc-warning fc-nobgimage">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					'.$error.'
				</div>';
				if ($int_xid) $result->message = $error;  else $result->message_main = $error;
				echo json_encode($result);
				jexit();
			}
		}
		
		
		// ********************************************************************************************
		// Check: item id exists in our voting logging SESSION (array) variable, to avoid double voting
		// ********************************************************************************************
		
		$vote_history = $session->get('vote_history', array(),'flexicontent');
		if ( !isset($vote_history[$cid]) || !is_array($vote_history[$cid]) )
		{
			$vote_history[$cid] = array();
		}
		
		// Allow user to change his vote
		$old_rating = isset($vote_history[$cid][$xid]) ? (int) $vote_history[$cid][$xid] : 0;
		$old_main_rating = isset($vote_history[$cid]['main']) ? (int) $vote_history[$cid]['main'] : 0;
		
		// For the case that the browser was not close we can get rating from user's session and allow to change the vote
		$rating_diff = $user_rating - $old_rating;
		
		// Accept votes only if user has voted for all cases, but do not store session yet
		$main_rating = 0;
		if (!count($xids))
		{
			$voteIsComplete = true;
			$main_rating = $user_rating;
			$vote_history[$cid]['main'] = $main_rating;
		}
		else
		{
			if (!$int_xid) die('unreachable int_xid is zero');
			
			$voteIsComplete = true;
			$main_rating = 0;
			$rating_completed = 0;
			
			// Add current vote
			$vote_history[$cid][$int_xid] = $user_rating;
			
			foreach($xids as $_xid => $i) {
				if ( !isset($vote_history[$cid][$_xid]) ) {
					$voteIsComplete = false;
					continue;
				}
				$rating_completed++;
				$main_rating += (int)$vote_history[$cid][$_xid];
			}
			if ($voteIsComplete) {
				$main_rating = (int)($main_rating / count($xids));
				$vote_history[$cid]['main'] = $main_rating;
			}
		}
		$main_rating_diff = $main_rating - $old_main_rating;
		
		
		// *************************************
		// Retreive last vote for the given item
		// *************************************
		
		$currip = $_SERVER['REMOTE_ADDR'];
		$currip_quoted = $db->Quote( $currip );
		$result	= new stdClass();
		foreach($vote_history[$cid] as $_xid => $_rating)
		{
			if (!$voteIsComplete && $_xid!=$xid) continue; // nothing todo
			//echo $_xid."\n";
			
			$dbtbl = !(int)$_xid ? '#__content_rating' : '#__flexicontent_items_extravote';  // Choose db table to store vote (normal or extra)
			$and_extra_id = (int)$_xid ? ' AND field_id = '.(int)$_xid : '';     // second part is for defining the vote type in case of extra vote
			
			$query = ' SELECT *'
				. ' FROM '.$dbtbl.' AS a '
				. ' WHERE content_id = '.(int)$cid
				. ' '.$and_extra_id;
			$db->setQuery( $query );
			$db_itemratings = $db->loadObject();
			
			
			// ***********************************************************
			// Voting access allowed and valid, but we will need to make
			// some more checks (IF voting record exists AND double voting)
			// ***********************************************************
			
			// Voting record does not exist for this item, accept user's vote and insert new voting record in the db
			if ( !$db_itemratings ) {
				if ($voteIsComplete) {
					$query = ' INSERT '.$dbtbl
						. ' SET content_id = '.(int)$cid.', '
						. '  lastip = '.$currip_quoted.', '
						. '  rating_sum = '.(int)$user_rating.', '
						. '  rating_count = 1 '
						. ( (int)$_xid ? ', field_id = '.(int)$_xid : '' );
						
					$db->setQuery( $query );
					$db->execute() or die( $db->stderr() );
				}
			}
			
			// Voting record exists for this item, check if user has already voted
			else {
				if ( (int)$_xid && !isset($xids[$_xid]) && $_xid!='main' ) continue;  // just in case there are some old records in session table 'vote_history'
				//echo $db_itemratings->rating_sum. " - ".$rating_diff. "\n";
				
				// If item is not in the user's voting history (session), then we check if this IP has voted for this item recently and refuse to accept vote
				if ( $_xid==$xid && !$old_rating && $currip==$db_itemratings->lastip ) 
				{
					// Voting REJECTED, avoid setting BAR percentage and HTML rating text ... someone else may have voted for the item ...
					//$result->percentage = ( $db_itemratings->rating_sum / $db_itemratings->rating_count ) * (100/$rating_resolution);
					//$result->htmlrating = $db_itemratings->rating_count .' '. JText::_( 'FLEXI_VOTES' );
					$error = JText::_( 'FLEXI_YOU_HAVE_ALREADY_VOTED' );//.', IP: '.$db_itemratings->lastip;
					if ($int_xid) $result->message = $error;  else $result->message_main = $error;
					
					if ($no_ajax) {
						$app->enqueueMessage( $int_xid ? $result->html : $result->html_main, 'notice' );
						return;
					} else {
						$result	= new stdClass();
						$result->percentage = '';
						$result->htmlrating = '';
						$error = '
						<div class="fc-mssg fc-warning fc-nobgimage">
							<button type="button" class="close" data-dismiss="alert">&times;</button>
							'.$error.'
						</div>';
						if ($int_xid) $result->message = $error;  else $result->message_main = $error;
						echo json_encode($result);
						jexit();
					}
				}
				
				// If voting is completed, add all rating into DB -OR- if user has updated existing vote (update in DB only the current sub-vote and the main vote)
				if ( $voteIsComplete && (!$old_main_rating || $_xid=='main' || (int)$_xid==$xid) )
				{
					// vote accepted update DB
					$query = " UPDATE ".$dbtbl
					. ' SET rating_count = rating_count + '.($old_rating ? 0 : 1)
					. '  , rating_sum = rating_sum + '.( $_xid=='main'  ?  ($old_main_rating ? $main_rating_diff : $main_rating)  :  ($_xid==$xid && $old_main_rating ? $rating_diff : $_rating) )
					. '  , lastip = '.$currip_quoted
					. ' WHERE content_id = '.(int)$cid.' '.$and_extra_id;
					
					$db->setQuery( $query );
					$db->execute() or die( $db->stderr() );
				}
			}
			
			if ($_xid=='main') {
				$result->rating_sum_main  = (@ (int) $db_itemratings->rating_sum)   + ($old_main_rating ? $main_rating_diff : $main_rating);
				$result->ratingcount_main = (@ (int) $db_itemratings->rating_count) + ($old_main_rating ? 0 : 1);
				$result->percentage_main  = !$result->ratingcount_main ? 0 : (($result->rating_sum_main / $result->ratingcount_main) * (100 / $rating_resolution));
				$result->htmlrating_main  = ($main_counter ?
					$result->ratingcount_main .($main_counter_show_label ? ' '. JText::_( @ $db_itemratings ? 'FLEXI_VOTES' : 'FLEXI_VOTE' ) : '') .($main_counter_show_percentage ? ' - ' : '')
					: '')
					.($main_counter_show_percentage ? (int)$result->percentage_main.'%' : '');
			}
			// In case of composite voting being OFF only the above will be added
			else if ($_xid==$xid) {
				$result->rating_sum  = (@ (int) $db_itemratings->rating_sum)   + ($old_main_rating ? $rating_diff : $_rating);
				$result->ratingcount = (@ (int) $db_itemratings->rating_count) + ($old_main_rating ? 0 : 1);
				$result->percentage  = !$result->ratingcount ? 0 : (($result->rating_sum / $result->ratingcount) * (100 / $rating_resolution));
				$result->htmlrating  = ($extra_counter ?
					$result->ratingcount . ($extra_counter_show_label ? ' '. JText::_( @ $db_itemratings ? 'FLEXI_VOTES' : 'FLEXI_VOTE' ) : '')	.($extra_counter_show_percentage ? ' - ' : '')
					: '')
					.($extra_counter_show_percentage ? (int)$result->percentage.'%' : '');
			}
		}
		
		
		// Prepare responce
		$html = ($old_rating ?
			''.(100*($old_rating / $max_rating)) .'% => '. (100*($user_rating / $max_rating)).'%' :
			''.(100*($user_rating / $max_rating)).'%');
		if ($xid=='main') $result->html_main = $html;
		else $result->html = $html;
		
		if ($int_xid) {
			$result->message = '
				<div class="fc-mssg fc-warning fc-nobgimage">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					'.JText::_('FLEXI_VOTE_YOUR_RATING').': '.(100*($user_rating / $max_rating)).'%
				</div>';
			if ( ! $voteIsComplete ) {
				$result->message_main = '
					<div class="fc-mssg fc-warning fc-nobgimage">
						<button type="button" class="close" data-dismiss="alert">&times;</button>
						'.JText::sprintf('FLEXI_VOTE_PLEASE_COMPLETE_VOTING', $rating_completed, count($xids)).'
					</div>';
			} else {
				$result->html_main = JText::_($old_main_rating ? 'FLEXI_VOTE_AVERAGE_RATING_UPDATED' : 'FLEXI_VOTE_AVERAGE_RATING_SUBMITTED');
				$result->message_main = '
				<div class="fc-mssg fc-success fc-nobgimage">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					'.JText::_( $old_rating ? 'FLEXI_VOTE_YOUR_OLD_AVERAGE_RATING_WAS_UPDATED' : 'FLEXI_VOTE_YOUR_AVERAGE_RATING_STORED' ).':
					<b>'.($old_main_rating ? (100*($old_main_rating / $max_rating)) .'% => ' : '').  (100*($main_rating / $max_rating)).'%</b>
				</div>';
			}
		} else {
			$result->message_main ='
				<div class="fc-mssg fc-success fc-nobgimage">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					'.JText::_( $old_rating ? 'FLEXI_VOTE_YOUR_OLD_RATING_WAS_CHANGED' : 'FLEXI_THANK_YOU_FOR_VOTING' ).'
				</div>';
		}
		
		// Set the voting data, into SESSION
		$session->set('vote_history', $vote_history, 'flexicontent');
		
		// Finally set responce
		if ($no_ajax) {
			$app->enqueueMessage( $int_xid ? $result->message_main.'<br/>'.$result->message : $result->message_main, 'notice' );
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
		$authorized = $user->authorise('flexicontent.createtags',	'com_flexicontent');

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
			$rsp .=  '<span class="qf_tag"><span class="fc_tagidbox"><input type="checkbox" name="tag[]" value="'.$tag->id.'"' . (in_array($tag->id, $used) ? 'checked="checked"' : '') . ' /></span>'.$tag->name.'</span>';
		}
		$rsp .= '</div>';
		$rsp .= '<div class="fcclear"></div>';
		$rsp .= '<div class="fc_addtag">';
		$rsp .= '<label for="addtags">'.JText::_( 'FLEXI_ADD_TAG' ).'</label>';
		$rsp .= '  <input type="text" id="tagname" class="inputbox" size="30" />';
		$rsp .=	'  <input type="button" class="button" value="'.JText::_( 'FLEXI_ADD' ).'" onclick="addtag()" />';
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
		$authorized = $user->authorise('flexicontent.createtags',	'com_flexicontent');

		if (!$authorized) return;
		
		$model 	= $this->getModel(FLEXI_ITEMVIEW);
		$model->addtag($name);
	}
	
	
	/**
	 *  Add new Tag from item screen
	 *
	 */
	function addtag()
	{
		// Check for request forgeries
		JRequest::checkToken('request') or jexit( 'Invalid Token' );
		
		$name 	= JRequest::getString('name', '');
		$array = JRequest::getVar('cid',  0, '', 'array');
		$cid   = (int)$array[0];
		
		// Check if tag exists (id exists or name exists)
		JLoader::register("FlexicontentModelTag", JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'tag.php');
		$model 	= new FlexicontentModelTag();
		//$model 	= $this->getModel('tag');
		$model->setId($cid);
		$tag = $model->getTag($name);
		
		if ($tag && $tag->id)
		{
			// Since tag was found just output the loaded tag
			$id   = $model->get('id');
			$name = $model->get('name');
			echo $id."|".$name;
			jexit();
		}
		
		if ($cid)
		{
			echo "0|Tag not found";
			jexit();
		}
		
		if (!FlexicontentHelperPerm::getPerm()->CanCreateTags)
		{
			echo "0|".JText::_('FLEXI_NO_AUTH_CREATE_NEW_TAGS');
			jexit();
		}
		
		// Add the new tag and output it so that it gets loaded by the form
		try {
			$result = $model->addtag($name);
			echo  $result  ?  $model->_tag->id."|".$model->_tag->name :  "0|New tag was not created" ;
		} catch (Exception $e) {
			echo "0|New tag creation failed";
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
			JError::raiseWarning(500, $msg);
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
			JError::raiseWarning(500, $msg);
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
	
	
	function call_extfunc()
	{
		flexicontent_ajax::call_extfunc();
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
						$db->execute();
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
				$fields_conf[$field_id] = new JRegistry($fld->attribs);
				$fields_props[$field_id] = $fld;
			}
			$field_type = $fields_props[$field_id]->field_type;
			
			$query  = 'SELECT f.id, f.filename, f.filename_original, f.altname, f.secure, f.url, f.hits'
					. ', i.title as item_title, i.introtext as item_introtext, i.fulltext as item_fulltext, u.email as item_owner_email'
					. ', i.access as item_access, i.language as item_language, ie.type_id as item_type_id'
					
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
			$db->execute();
			
			
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
					$db->execute();
				}
			}
			
			
			// **************************
			// Special case file is a URL
			// **************************
			
			if ($file->url)
			{
				// Check for empty URL
				$url = $file->filename_original ? $file->filename_original : $file->filename;
				if (empty($url)) {
					$msg = "File URL is empty: ".$file->url;
					$app->enqueueMessage($msg, 'error');
					return false;
				}
				
				// skip url-based file if downloading multiple files
				if ($task=='download_tree') {
					$msg = "Skipped URL based file: ".$url;
					$app->enqueueMessage($msg, 'notice');
					continue;
				}
				
				// redirect to the file download link
				@header("Location: ".$url."");
				$app->close();
			}
			
			
			// *********************************************************************
			// Set file (tree) node and assign file into valid files for downloading
			// *********************************************************************
			
			$file->node = $file_node;
			$valid_files[$file_id] = $file;
			
			$file->hits++;
			$per_downloads = $fields_conf[$field_id]->get('notifications_hits_step', 20);
			if ( $fields_conf[$field_id]->get('send_notifications') && ($file->hits % $per_downloads == 0) ) {
				
				// Calculate (once per file) some text used for notifications
				$file->__file_title__ = $file->altname && $file->altname != $file->filename ? 
					$file->altname . ' ['.$file->filename.']'  :  $file->filename;
				
				$item = new stdClass();
				$item->access = $file->item_access;
				$item->type_id = $file->item_type_id;
				$item->language = $file->item_language;
				$file->__item_url__ = JRoute::_(FlexicontentHelperRoute::getItemRoute($file->itemslug, $file->catslug, 0, $item));
				
				// Parse and identify language strings and then make language replacements
				$notification_tmpl = $fields_conf[$field_id]->get('notification_tmpl');
				if ( empty($notification_tmpl) ) {
					$notification_tmpl = JText::_('FLEXI_HITS') .": ".$file->hits;
					$notification_tmpl .= '%%FLEXI_FDN_FILE_NO%% __file_id__:  "__file_title__" '."\n";
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
					$email_values = $db->loadColumn();
					
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
					$count = 0;
					$_mssg_file = str_ireplace('__file_hits__', $filedata->hits, $_mssg_file, $count);
					if ($count == 0) $_mssg_file = JText::_('FLEXI_HITS') .": ".$file->hits ."\n". $_mssg_file;
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
				fprintf($handle_txt, "%s", $file->item_title."\n\n");
				fprintf($handle_txt, "%s", flexicontent_html::striptagsandcut($file->item_introtext) ."\n\n" );
				if ( strlen($file->item_fulltext) ) fprintf($handle_txt, "%s", flexicontent_html::striptagsandcut($file->item_fulltext)."\n\n" );
				
				fprintf($handle_htm, "%s", "<h2>".$file->item_title."</h2>");
				fprintf($handle_htm, "%s", "<blockquote>".$file->item_introtext."</blockquote><br/>");
				if ( strlen($file->item_fulltext) ) fprintf($handle_htm, "%s", "<blockquote>".$file->item_fulltext."</blockquote><br/>");
				fprintf($handle_htm, "<hr/><br/>");
			}
			fclose($handle_txt);
			fclose($handle_htm);
			
			// Get file list recursively, and calculate archive filename
			$fileslist   = JFolder::files($targetpath, '.', $recurse=true, $fullpath=true);
			$archivename = $tmp_ffname . '.zip';
			$archivepath = JPath::clean( $app->getCfg('tmp_path').DS.$archivename );
			
			
			// ******************
			// Create the archive
			// ******************
			
			/*$app = JFactory::getApplication('administrator');
			$files = array();
			foreach ($fileslist as $i => $filename) {
				$files[$i]=array();
				$files[$i]['name'] = preg_replace("%^(\\\|/)%", "", str_replace($targetpath, "", $filename) );  // STRIP PATH for filename inside zip
				$files[$i]['data'] = implode('', file($filename));   // READ contents into string, here we use full path
				$files[$i]['time'] = time();
			}
			
			jimport('joomla.archive.archive');
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
			
			
			// *********************************
			// Remove temporary folder structure
			// *********************************
			
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
			$dlfile->filename = 'cart_files_'.date('m-d-Y_H-i-s'). '.zip';   // a friendly name instead of  $archivename
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
		$download_filename = strlen($dlfile->filename_original) ? $dlfile->filename_original : $dlfile->filename;
		if ($method == 'view') {
			header("Content-Disposition: inline; filename=\"".$download_filename."\";" );
		} else {
			header("Content-Disposition: attachment; filename=\"".$download_filename."\";" );
		}
		header("Content-Transfer-Encoding: binary");
		header("Content-Length: ".$dlfile->size);
		
		
		// *******************************
		// Finally read file and output it
		// *******************************
		
		if ( !FLEXIUtilities::funcIsDisabled('set_time_limit') ) @set_time_limit(0);
		
		$chunksize = 1 * (1024 * 1024); // 1MB, highest possible for fread should be 8MB
		if (1 || $dlfile->size > $chunksize)
		{
			$handle = @fopen($dlfile->abspath,"rb");
			while(!feof($handle))
			{
				print(@fread($handle, $chunksize));
				ob_flush();
				flush();
			}
			fclose($handle);
		} else {
			// This is good for small files, it will read an output the file into
			// memory and output it, it will cause a memory exhausted error on large files
			ob_clean();
			flush();
			readfile($dlfile->abspath);
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
		
		// Check if url is absolute aka has protocol, if not check and prepend Joomla 's current root
		$protocol = parse_url($url, PHP_URL_SCHEME);
		if (!$protocol) {
			$url .= (substr($url, 0, 1) == '/')  ?  ''  :  JURI::root(true) . '/';
		}
		
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
		try {
			$db->execute();
		}
		catch (Exception $e) {
			JError::raiseWarning( 500, $e->getMessage() );
			return;
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
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
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
		}
		
		else {
			$aid_arr = JAccess::getAuthorisedViewLevels($user->id);
			$aid_list = implode(",", $aid_arr);
			if ($include_file)
				$andacc .= ' AND  f.access IN (0,'.$aid_list.')';  // AND file access
			$andacc   .= ' AND fi.access IN (0,'.$aid_list.')';  // AND field access
			$andacc   .= ' AND ty.access IN (0,'.$aid_list.')  AND  c.access IN (0,'.$aid_list.')  AND  i.access IN (0,'.$aid_list.')';  // AND content access
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
	function viewtags()
	{
		// Check for request forgeries
		JRequest::checkToken('request') or jexit( 'Invalid Token' );
		
		@ob_end_clean();
		//header("Content-type:text/json");
		//header('Content-type: application/json');
		//header('Content-type: text/plain; charset=utf-8');  // this text/plain is browser's default
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Cache-Control: no-cache");
		header("Pragma: no-cache");
		
		$perms = FlexicontentHelperPerm::getPerm();
		if ( !$perms->CanUseTags ) {
			$array =  array("{\"id\":\"0\",\"name\":\"You have no access\"}");
		} else {
			$model   = $this->getModel(FLEXI_ITEMVIEW);
			$tagobjs = $model->gettags(JRequest::getVar('q'));
			$array   = array();
			if ($tagobjs) foreach($tagobjs as $tag) {
				$array[] = "{\"id\":\"".$tag->id."\",\"name\":\"".$tag->name."\"}";
			}
			if (empty($array)) $array   = array("{\"id\":\"0\",\"name\":\"".($perms->CanCreateTags ? 'New tag, click enter to create' : 'No tags found')."\"}");
		}
		
		echo "[\n" . implode(",\n", $array) . "\n]";
		exit;
	}
	
	
	function search()
	{
		// Strip characters that will cause errors
		$badchars = array('#','>','<','\\'); 
		$searchword = trim(str_replace($badchars, '', JRequest::getString('searchword', JRequest::getString('q'))));
		
		// If searchword is enclosed in double quotes, then strip quotes and do exact phrase matching
		if (substr($searchword,0,1) == '"' && substr($searchword, -1) == '"') { 
			$searchword = substr($searchword,1,-1);
			JRequest::setVar('p', 'exact');
			JRequest::setVar('searchphrase', 'exact');
			JRequest::setVar('q', $searchword);
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
		
		// Go through display task of this controller instead of parent class, so that cacheable and safeurlparams can be decided properly
		JRequest::setVar('view', 'search');
		$this->display();
	}
	
	
	function doPlgAct() {
		FLEXIUtilities::doPlgAct();
	}
}
