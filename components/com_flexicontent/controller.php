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
class FlexicontentController extends JController
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
	}

	/**
	 * Display the view
	 */
	function display()
	{
		// View caching logic -- simple... are we logged in?
		$user = &JFactory::getUser();
		if ($user->get('id')) {
			parent::display(false);
		} else {
			parent::display(true);
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
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		$user	=& JFactory::getUser();

		// Create the view
		$view = & $this->getView(FLEXI_ITEMVIEW, 'html');

		// Get/Create the model
		$model = & $this->getModel(FLEXI_ITEMVIEW);

		// first verify it's an edit action
		if ($model->get('id') > 1)
		{
			if (FLEXI_J16GE) {
				$asset = 'com_content.article.' . $model->get('id');
				$has_edit = $user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && $model->get('created_by') == $user->get('id'));
				// ALTERNATIVE 1
				//$has_edit = $model->getItemAccess()->get('access-edit'); // includes privileges edit and edit-own
				// ALTERNATIVE 2
				//$rights = FlexicontentHelperPerm::checkAllItemAccess($user->get('id'), 'item', $model->get('id'));
				//$has_edit = in_array('edit', $rights) || (in_array('edit.own', $rights) && $model->get('created_by') == $user->get('id')) ;
			} else if (FLEXI_ACCESS) {
				$rights 	= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $model->get('id'), $model->get('catid'));
				$has_edit = in_array('edit', $rights) || (in_array('editown', $rights) && $model->get('created_by') == $user->get('id')) ;
			} else {
				$has_edit = $user->authorize('com_content', 'edit', 'content', 'all') || ($user->authorize('com_content', 'edit', 'content', 'own') && $model->get('created_by') == $user->get('id'));
			}
			
			if (!$has_edit) {
				// user isn't authorize to edit THIS item
				JError::raiseError( 403, JText::_( 'FLEXI_ALERTNOTAUTH' ) );
			}
		} else {
			JError::raiseError( 500, 'Can not edit item, because item id is not set' );
		}

		//checked out?
		if ($model->isCheckedOut($user->get('id')))
		{
			$msg = JText::sprintf('FLEXI_DESCBEINGEDITTED', $model->get('title'));
			$this->setRedirect(JRoute::_('index.php?view='.FLEXI_ITEMVIEW.'&cid='.$model->get('catid').'&id='.$model->get('id'), false), $msg);
			return;
		}

		//Checkout the item
		$model->checkout();

		// Push the model into the view (as default)
		$view->setModel($model, true);

		// Set the layout
		$view->setLayout('form');

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
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		$user	=& JFactory::getUser();

		// Create the view
		$view = & $this->getView(FLEXI_ITEMVIEW, 'html');

		//general access check
		if (FLEXI_J16GE) {
			$canAdd	= $user->authorize('core.create', 'com_flexicontent');
			$canAddCat = $user->authorize('flexicontent.createcat', 'com_flexicontent');
			$not_authorised = !($canAdd || $canAddCat);
		} else if (FLEXI_ACCESS) {
			$canAdd = FAccess::checkUserElementsAccess($user->gmid, 'submit');
			$not_authorised = ! ( @$canAdd['content'] || @$canAdd['category'] );
		} else {
			$canAdd	= $user->authorize('com_content', 'add', 'content', 'all');
			$not_authorised = ! $canAdd;
		}
		
		if ($not_authorised) {
			// user isn't authorize to add ANY content
			JError::raiseError( 403, JText::_( 'FLEXI_ALERTNOTAUTH' ) );
		}

		// Get/Create the model
		$model = & $this->getModel(FLEXI_ITEMVIEW);

		// Push the model into the view (as default)
		$view->setModel($model, true);

		// Set the layout
		$view->setLayout('form');

		// Display the view
		$view->display();
	}

	/**
	* Saves the item
	*
	* @access	public
	* @since	1.0
	*/
	function save()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );

		// Initialize variables
		$db			= & JFactory::getDBO();
		$user		= & JFactory::getUser();
		$model = $this->getModel(FLEXI_ITEMVIEW);

		// Get data from request and validate them
		if (FLEXI_J16GE) {
			$data	= JRequest::getVar('jform', array(), 'post', 'array');
			$form = $model->getForm($data, false);
			$post = & $model->validate($form, $data);
			if (!$post) echo $model->getError();
		} else {
			$post = JRequest::get('post');
			$post['text'] = JRequest::getVar('text', '', 'post', 'string', JREQUEST_ALLOWRAW);
		}

		// USEFULL FOR DEBUGING for J2.5 (do not remove commented code)
		//$diff_arr = array_diff_assoc ( $data, $post);
		//echo "<pre>"; print_r($diff_arr); exit();
		
		//perform access checks
		$isNew = ((int) $post['id'] < 1);
		$allowunauthorize = JFactory::getApplication()->getParams('com_flexicontent')->get('allowunauthorize', 0);

		// Must be logged in
		if (!$allowunauthorize &&  ($user->get('id') < 1) ) {
			JError::raiseError( 403, JText::_( 'FLEXI_ALERTNOTAUTH' ) );
			return;
		}
		
		// Store the form data into the item and check it in
		if ($model->store($post)) {
			if($isNew) {
				$post['id'] = (int) $model->get('id');
			}
		} else {
			$msg = JText::_( 'FLEXI_ERROR_STORING_ITEM' );
			JError::raiseError( 500, $model->getError() );
		}
		$model->checkin();
		
		// SEND notification EMAIL only once when editing items, (we use session to detect multiple saves)
		$is_first_unapproved_revise = false;
		
		if ($post['vstate']!=2) {
			$session 	=& JFactory::getSession();
			$items_saved = array();
			if ($session->has('unapproved_revises', 'flexicontent')) {
				$unapproved_revises	= $session->get('unapproved_revises', array(), 'flexicontent');
				$is_first_unapproved_revise = ! isset($unapproved_revises[$model->get('id')]);
			}
			//add item to unapproved revises of corresponding session array
			$unapproved_revises[$model->get('id')] = $timestamp = time();  // Current time as seconds since Unix epoc;
			$session->set('unapproved_revises', $unapproved_revises, 'flexicontent');
		}

		// SEND the notification EMAIL
		if ( $isNew || $is_first_unapproved_revise )
		{
			//Get categories for information mail
			$query 	= 'SELECT DISTINCT c.id, c.title,'
				. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as slug'
				. ' FROM #__categories AS c'
				. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.catid = c.id'
				. ' WHERE rel.itemid = '.(int) $model->get('id')
				;

			$db->setQuery( $query );

			$categories = $db->loadObjectList();

			//Loop through the categories to create a string
			$n = count($categories);
			$i = 0;
			$catstring = '';
			foreach ($categories as $category) {
				$catstring .= $category->title;
				$i++;
				if ($i != $n) {
					$catstring .= ', ';
				}
			}

			//Get list of admins who receive system mails
			$query 	= 'SELECT id, email, name'
				. ' FROM #__users'
				. ' WHERE sendEmail = 1';
			$db->setQuery($query);
			if (!$db->query()) {
				JError::raiseError( 500, $db->stderr(true));
				return;
			}
			$adminRows = $db->loadObjectList();

			require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_messages'.DS.'tables'.DS.'message.php');
			if (FLEXI_J16GE) {
				require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_messages'.DS.'models'.DS.'message.php');
				require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_messages'.DS.'models'.DS.'config.php');
				$message = new MessagesModelMessage();
			} else {
				$message = new TableMessage($db);
			}

			// send email notification to admins
			foreach ($adminRows as $adminRow) {

				//Not really needed cause in com_message you can set to be notified about new messages by email
				//JUtility::sendAdminMail($adminRow->name, $adminRow->email, '', JText::_( 'FLEXI_NEW ITEM' ), $post['title'], $user->get('username'), JURI::base());

				$msgdata["user_id_to"] = $adminRow->id;
				$msgdata["subject"] = ($isNew) ? JText::_( 'FLEXI_NEW_ITEM' ) : JText::_( 'FLEXI_ITEM_REVISED' ) ;
				if ($isNew) {
					$msgdata["message"] = JText::sprintf('FLEXI_ON_NEW_ITEM', $post['title'], $user->get('username'), $catstring);
				} else {
					$msgdata["message"] = JText::sprintf('FLEXI_ON_REVISED_ITEM', $post['title'], $catstring, $user->get('username'));
				}
				
				if (FLEXI_J16GE) {
					$message->save($msgdata);
				} else {
					$message->send($user->get('id'), $adminRow->id, $msgdata["subject"], $msgdata["message"]);
				}
			}
		}
		
		if (!$isNew) {
			// If the item isn't new, then we need to clean the cache so that our changes appear realtime
			$cache = &JFactory::getCache('com_flexicontent');
			$cache->clean();
		}
		$task = JRequest::getVar('task');
		if($task=='save_a_preview') {
			$link = JRoute::_(FlexicontentHelperRoute::getItemRoute($model->_item->id.':'.$model->_item->alias, $model->_item->catid).'&preview=1', false);
			$this->setRedirect($link);
			return;
		}
		if ($user->authorize('com_flexicontent', 'state') )
		{
			$msg = JText::_( 'FLEXI_ITEM_SAVED' );
		}
		else
		{
			$msg = $isNew ? JText::_( 'FLEXI_THANKS_SUBMISSION' ) : JText::_( 'FLEXI_ITEM_SAVED' );
		}

		$link = JRequest::getString('referer', JURI::base(), 'post');
		$this->setRedirect($link, $msg);
	}

	/**
	* Cancels an edit item operation
	*
	* @access	public
	* @since	1.0
	*/
	function cancel()
	{
		// Initialize some variables
		$user	= & JFactory::getUser();

		// Get an item model
		$model = & $this->getModel(FLEXI_ITEMVIEW);
		
		// CHECK-IN the item if user can edit
		if ($model->get('id') > 1)
		{
			if (FLEXI_J16GE) {
				$asset = 'com_content.article.' . $item->id;
				$has_edit = $user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && $item->created_by == $user->get('id'));
				// ALTERNATIVE 1
				//$has_edit = $model->getItemAccess()->get('access-edit'); // includes privileges edit and edit-own
				// ALTERNATIVE 2
				//$rights = FlexicontentHelperPerm::checkAllItemAccess($user->get('id'), 'item', $model->get('id'));
				//$has_edit = in_array('edit', $rights) || (in_array('edit.own', $rights) && $model->get('created_by') == $user->get('id')) ;
			} else if (FLEXI_ACCESS) {
				$rights 	= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $model->get('id'), $model->get('catid'));
				$has_edit = in_array('edit', $rights) || (in_array('editown', $rights) && $model->get('created_by') == $user->get('id')) ;
			} else {
				$has_edit = $user->authorize('com_content', 'edit', 'content', 'all') || ($user->authorize('com_content', 'edit', 'content', 'own') && $model->get('created_by') == $user->get('id'));
			}
			
			if ($has_edit) {
				$item = & JTable::getInstance('flexicontent_items', '');
				$item_id = FLEXI_J16GE ? (int)JRequest::getVar('cid', 0) : (int)JRequest::getVar('id', 0);
				$item->load( $item_id );
				$item->checkin();
			}
		}
		
		// If the task was edit or cancel, we go back to the item
		$referer = JRequest::getString('referer', JURI::base(), 'post');
		$this->setRedirect($referer);
	}

	/**
	 * Method of the voting
	 * Deprecated to ajax voting
	 *
	 * @access public
	 * @since 1.0
	 */
	function vote()
	{
		$mainframe =& JFactory::getApplication();

		$id 		= JRequest::getInt('id', 0);
		$cid 		= JRequest::getInt('cid', 0);
		$layout		= JRequest::getCmd('layout', 'default');
		$vote		= JRequest::getInt('vote', 0);
		$session 	=& JFactory::getSession();
		$params 	= & $mainframe->getParams('com_flexicontent');

		$cookieName	= JUtility::getHash( $mainframe->getName() . 'flexicontentvote' . $id );
		$voted = JRequest::getVar( $cookieName, '0', 'COOKIE', 'INT');

		$votecheck = false;
		if ($session->has('vote', 'flexicontent')) {
			$votecheck = $session->get('vote', 0,'flexicontent');
			$votecheck = in_array($id, $votecheck);
		}

		if ( $voted || $votecheck )	{
			JError::raiseWarning(JText::_( 'SOME_ERROR_CODE' ), JText::_( 'FLEXI_YOU_ALLREADY_VOTED' ));
		} else {
			setcookie( $cookieName, '1', time()+1*24*60*60*60 );

			$stamp = array();
			$stamp[] = $id;
			$session->set('vote', $stamp, 'flexicontent');

			$model 	= $this->getModel(FLEXI_ITEMVIEW);
			if ($model->storevote($id, $vote)) {
				$msg = JText::_( 'FLEXI_VOTE COUNTED' );
			} else {
				$msg = JText::_( 'FLEXI_VOTE FAILURE' );
				JError::raiseError( 500, $model->getError() );
			}
		}
		
		$cache = &JFactory::getCache('com_flexicontent');
		$cache->clean();

		$this->setRedirect(JRoute::_('index.php?view='.FLEXI_ITEMVIEW.'&cid='.$cid.'&id='.$id.'&layout='.$layout, false), $msg );
	}

	/**
	 *  Ajax favourites
	 *
	 * @access public
	 * @since 1.0
	 */
	function ajaxfav()
	{
		$mainframe =& JFactory::getApplication();
		$user 	=& JFactory::getUser();
		$id 	=  JRequest::getInt('id', 0);
		$db  	=& JFactory::getDBO();
		$model 	=  $this->getModel(FLEXI_ITEMVIEW);

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
		$app 	=& JFactory::getApplication();
		$user 	= &JFactory::getUser();
		$db  	= &JFactory::getDBO();
		
		$user_rating	= JRequest::getInt('user_rating');
		$cid 			= JRequest::getInt('cid');
		$xid 			= JRequest::getVar('xid');

		$result	= new JObject;

		if (($user_rating >= 1) and ($user_rating <= 5))
		{
			$currip = ( phpversion() <= '4.2.1' ? @getenv( 'REMOTE_ADDR' ) : $_SERVER['REMOTE_ADDR'] );
			$currip_quoted = $db->Quote( $currip );
			$dbtbl = !(int)$xid ? '#__content_rating' : '#__content_extravote';
			$and_extra_id = (int)$xid ? ' AND extra_id = '.(int)$xid : '';
			
			$query = ' SELECT *'
				. ' FROM '.$dbtbl.' AS a '
				. ' WHERE content_id = '.(int)$cid.' '.$and_extra_id;
			
			$db->setQuery( $query );
			$votesdb = $db->loadObject();

			if ( !$votesdb )
			{
				$query = ' INSERT '.$dbtbl
					. ' SET content_id = '.(int)$cid.', '
					. '  lastip = '.$currip_quoted.', '
					. '  rating_sum = '.(int)$user_rating.', '
					. '  rating_count = 1 '
					. ( (int)$xid ? ', extra_id = '.(int)$xid : '' );
					
				$db->setQuery( $query );
				$db->query() or die( $db->stderr() );
				$result->ratingcount = 1;
				$result->htmlrating = '(' . $result->ratingcount .' '. JText::_( 'FLEXI_VOTE' ) . ')';
			} 
			else 
			{
				if ($currip != ($votesdb->lastip))
				{
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
					// avoid setting percentage ... since it may confuse the user because someone from same ip may have voted and
					// despite telling user that she/he has voted already, user will see a change in the percentage of highlighted stars
					//$result->percentage = ( $votesdb->rating_sum / $votesdb->rating_count ) * 20;
					$result->htmlrating = '(' . $votesdb->rating_count .' '. JText::_( 'FLEXI_VOTES' ) . ')';
					$result->html = JText::_( 'FLEXI_YOU_HAVE_ALREADY_VOTED' );
					echo json_encode($result);
					exit();
				}
			}
			$result->percentage = ( ((isset($votesdb->rating_sum) ? $votesdb->rating_sum : 0) + (int)$user_rating) / $result->ratingcount ) * 20;
			$result->html 		= JText::_( 'FLEXI_THANK_YOU_FOR_VOTING' );
			echo json_encode($result);
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

		if (!$user->authorize('com_flexicontent', 'newtags')) {
			return;
		}

		$id 	= JRequest::getInt('id', 0);
		$model 	= $this->getModel(FLEXI_ITEMVIEW);
		$tags 	= $model->getAlltags();

		$used = null;

		if ($id) {
			$used = FLEXI_J16GE ? $model->getUsedtagsIds($id) : $model->getUsedtags();
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

		$name 	= JRequest::getString('name', '');

		if ($user->authorize('com_flexicontent', 'newtags')) {
			$model 	= $this->getModel(FLEXI_ITEMVIEW);
			$model->addtag($name);
		}
		return;
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
		exit;
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
			JError::raiseError( 500, $model->getError() );
			$msg = JText::_( 'FLEXI_FAVOURITE_NOT_ADDED' );
		}
		
		$cache = &JFactory::getCache('com_flexicontent');
		$cache->clean();

		$this->setRedirect(JRoute::_('index.php?view='.FLEXI_ITEMVIEW.'&cid='.$cid.'&id='. $id, false), $msg );

		return;
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
			JError::raiseError( 500, $model->getError() );
			$msg = JText::_( 'FLEXI_FAVOURITE_NOT_REMOVED' );
		}
		
		$cache = &JFactory::getCache('com_flexicontent');
		$cache->clean();
		
		if ($cid) {
			$this->setRedirect(JRoute::_('index.php?view='.FLEXI_ITEMVIEW.'&cid='.$cid.'&id='. $id, false), $msg );
		} else {
			$this->setRedirect(JRoute::_('index.php?view=favourites', false), $msg );
		}
				
		return;
	}

	/**
	 * Logic to change the state
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function setstate()
	{
		$id 	= JRequest::getInt( 'id', 0 );
		$state 	= JRequest::getInt( 'state', 0 );

		$model = $this->getModel(FLEXI_ITEMVIEW);

		if(!$model->setitemstate($id, $state)) {
			JError::raiseError( 500, $model->getError() );
		}

		if ( $state == 1 ) {
			$img = 'tick.png';
			$alt = JText::_( 'FLEXI_PUBLISHED' );
		} else if ( $state == 0 ) {
			$img = 'publish_x.png';
			$alt = JText::_( 'FLEXI_UNPUBLISHED' );
		} else if ( $state == -1 ) {
			$img = 'disabled.png';
			$alt = JText::_( 'FLEXI_ARCHIVED' );
		} else if ( $state == -3 ) {
			$img = 'publish_r.png';
			$alt = JText::_( 'FLEXI_PENDING' );
		} else if ( $state == -4 ) {
			$img = 'publish_y.png';
			$alt = JText::_( 'FLEXI_TO_WRITE' );
		} else if ( $state == -5 ) {
			$img = 'publish_g.png';
			$alt = JText::_( 'FLEXI_IN_PROGRESS' );
		}
		
		$cache = &JFactory::getCache('com_flexicontent');
		$cache->clean();

		echo JHTML::image('components/com_flexicontent/assets/images/'.$img, $alt );
	}

	/**
	 * Download logic
	 *
	 * @access public
	 * @since 1.0
	 */
	function download()
	{
		$mainframe = &JFactory::getApplication();
		
		jimport('joomla.filesystem.file');

		$id 		= JRequest::getInt( 'id', 0 );
		$fieldid 	= JRequest::getInt( 'fid', 0 );
		$contentid 	= JRequest::getInt( 'cid', 0 );
		$db			= &JFactory::getDBO();
		$user		= & JFactory::getUser();

		$joinaccess = $andaccess = $joinaccess2 = $andaccess2 = '';
		if (FLEXI_J16GE) {
			$aid_arr = $user->getAuthorisedViewLevels();
			$aid_list = implode(",", $aid_arr);
			$andaccess	= ' AND fi.access IN ('.$aid_list.')';
			$andaccess2 = ' AND c.access IN ('.$aid_list.')';
		} else {
			$aid = (int) $user->get('aid');
			if (FLEXI_ACCESS) {
				// is the field available
				$joinaccess  = ' LEFT JOIN #__flexiaccess_acl AS gi ON fi.id = gi.axo AND gi.aco = "read" AND gi.axosection = "field"';
				$andaccess   = ' AND (gi.aro IN ( '.$user->gmid.' ) OR fi.access <= '. $aid . ')';
				// is the item available
				$joinaccess2 = ' LEFT JOIN #__flexiaccess_acl AS gc ON c.id = gc.axo AND gc.aco = "read" AND gc.axosection = "item"';
				$andaccess2  = ' AND (gc.aro IN ( '.$user->gmid.' ) OR c.access <= '. $aid . ')';
			} else {
				$andaccess  = ' AND fi.access <= '.$aid ;
				$andaccess2 = ' AND c.access <= '.$aid ;
			}
		}

		$query  = 'SELECT f.id, f.filename, f.secure, f.url'
				.' FROM #__flexicontent_fields_item_relations AS rel'
				.' LEFT JOIN #__flexicontent_files AS f ON f.id = rel.value'
				.' LEFT JOIN #__flexicontent_fields AS fi ON fi.id = rel.field_id'
				.' LEFT JOIN #__content AS c ON c.id = rel.item_id'
				. $joinaccess
				. $joinaccess2
				.' WHERE rel.item_id = ' . (int)$contentid
				.' AND rel.field_id = ' . (int)$fieldid
				.' AND f.id = ' . (int)$id
				.' AND f.published= 1'
				. $andaccess
				. $andaccess2
				;
		$db->setQuery($query);
		$file = $db->loadObject();

		if ($file->url) {
			//update hitcount
			$filetable = & JTable::getInstance('flexicontent_files', '');
			$filetable->hit($id);
			
			// redirect to the file download link
			@header("Location: ".$file->filename."");
			$mainframe->close();
		}
		
		$basePath = $file->secure ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH;

		$abspath = str_replace(DS, '/', JPath::clean($basePath.DS.$file->filename));
		
		if (!JFile::exists($abspath)) {
			$msg 	= JText::_( 'FLEXI_REQUESTED_FILE_DOES_NOT_EXIST_ANYMORE' );
			$link 	= 'index.php';
			$this->setRedirect($link, $msg);
			return;
		}
		
		//get filesize and extension
		$size 	= filesize($abspath);
		$ext 	= strtolower(JFile::getExt($file->filename));
		
		//update hitcount
		$filetable = & JTable::getInstance('flexicontent_files', '');
		$filetable->hit($id);

		// required for IE, otherwise Content-disposition is ignored
		if(ini_get('zlib.output_compression')) {
			ini_set('zlib.output_compression', 'Off');
		}

		switch( $ext )
		{
			case "pdf":
				$ctype = "application/pdf";
				break;
			case "exe":
				$ctype="application/octet-stream";
				break;
			case "rar":
			case "zip":
				$ctype = "application/zip";
				break;
			case "txt":
				$ctype = "text/plain";
				break;
			case "doc":
				$ctype = "application/msword";
				break;
			case "xls":
				$ctype = "application/vnd.ms-excel";
				break;
			case "ppt":
				$ctype = "application/vnd.ms-powerpoint";
				break;
			case "gif":
				$ctype = "image/gif";
				break;
			case "png":
				$ctype = "image/png";
				break;
			case "jpeg":
			case "jpg":
				$ctype = "image/jpg";
				break;
			case "mp3":
				$ctype = "audio/mpeg";
				break;
			default:
				$ctype = "application/force-download";
		}
/*		
		JResponse::setHeader('Pragma', 'public');
		JResponse::setHeader('Expires', 0);
		JResponse::setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0');
		JResponse::setHeader('Cache-Control', 'private', false);
		JResponse::setHeader('Content-Type', $ctype);
		JResponse::setHeader('Content-Disposition', 'attachment; filename="'.$file.'";');
		JResponse::setHeader('Content-Transfer-Encoding', 'binary');
		JResponse::setHeader('Content-Length', $size);
*/
		header("Pragma: public"); // required
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private",false); // required for certain browsers
		header("Content-Type: $ctype");
		//quotes to allow spaces in filenames
		header("Content-Disposition: attachment; filename=\"".$file->filename."\";" );
		header("Content-Transfer-Encoding: binary");
		header("Content-Length: ".$size);

		readfile($abspath);
		$mainframe->close();
	}

	/**
	 * External link logic
	 *
	 * @access public
	 * @since 1.5
	 */
	function weblink()
	{
		$mainframe =& JFactory::getApplication();
		
		$user		= & JFactory::getUser();
		$db			= &JFactory::getDBO();

		$fieldid 	= JRequest::getInt( 'fid', 0 );
		$contentid 	= JRequest::getInt( 'cid', 0 );
		$order 		= JRequest::getInt( 'ord', 0 );

		$joinaccess = $andaccess = $joinaccess2 = $andaccess2 = '';
		if (FLEXI_J16GE) {
			$aid_arr = $user->getAuthorisedViewLevels();
			$aid_list = implode(",", $aid_arr);
			$andaccess	= ' AND fi.access IN ('.$aid_list.')';
			$andaccess2 = ' AND c.access IN ('.$aid_list.')';
		} else {
			$aid = (int) $user->get('aid');
			if (FLEXI_ACCESS) {
				// is the field available
				$joinaccess  = ' LEFT JOIN #__flexiaccess_acl AS gi ON fi.id = gi.axo AND gi.aco = "read" AND gi.axosection = "field"';
				$andaccess   = ' AND (gi.aro IN ( '.$user->gmid.' ) OR fi.access <= '. $aid . ')';
				// is the item available
				$joinaccess2 = ' LEFT JOIN #__flexiaccess_acl AS gc ON c.id = gc.axo AND gc.aco = "read" AND gc.axosection = "item"';
				$andaccess2  = ' AND (gc.aro IN ( '.$user->gmid.' ) OR c.access <= '. $aid . ')';
			} else {
				$andaccess  = ' AND fi.access <= '.$aid ;
				$andaccess2 = ' AND c.access <= '.$aid ;
			}
		}

		$query  = 'SELECT value'
				.' FROM #__flexicontent_fields_item_relations AS rel'
				.' LEFT JOIN #__flexicontent_fields AS fi ON fi.id = rel.field_id'
				.' LEFT JOIN #__content AS c ON c.id = rel.item_id'
				. $joinaccess
				. $joinaccess2
				.' WHERE rel.item_id = ' . (int)$contentid
				.' AND rel.field_id = ' . (int)$fieldid
				.' AND rel.valueorder = ' . (int)$order
				. $andaccess
				. $andaccess2
				;
		$db->setQuery($query);
		$link = $db->loadResult();

		// if the query result is empty it means that the user doesn't have the required access level
		if (empty($link)) {
			$msg 	= JText::_( 'FLEXI_ALERTNOTAUTH' );
			$link 	= 'index.php';
			$this->setRedirect($link, $msg);
			return;
		}

		// recover the link array (url|title|hits)
		$link = unserialize($link);
		
		// get the url from the array
		$url = $link['link'];
		
		// update the hit count
		$link['hits'] = (int)$link['hits'] + 1;
		$value = serialize($link);
		
		// update the array in the DB
		$query 	= 'UPDATE #__flexicontent_fields_item_relations'
				.' SET value = ' . $db->Quote($value)
				.' WHERE item_id = ' . (int)$contentid
				.' AND field_id = ' . (int)$fieldid
				.' AND valueorder = ' . (int)$order
				;
		$db->setQuery($query);
		if (!$db->query()) {
			return JError::raiseWarning( 500, $db->getError() );
		}
		
		@header("Location: ".$url."","target=blank");
		$mainframe->close();
	}

	/**
	 * Method to fetch the tags form
	 * 
	 * @since 1.5
	 */
	function viewtags() {
		// Check for request forgeries
		JRequest::checkToken('request') or jexit( 'Invalid Token' );

		$user	=& JFactory::getUser();
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
			exit;
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
			$menu = &JSite::getMenu();
			$items	= $menu->getItems('link', 'index.php?option=com_flexicontent&view=search');
	
			if(isset($items[0])) {
				JRequest::setVar('Itemid', $items[0]->id);
			}
		}
		
		$itemmodel = &$this->getModel(FLEXI_ITEMVIEW);
		$view  = &$this->getView('search', 'html');
		$view->_models['items'] = &$itemmodel;
		
		JRequest::setVar('view', 'search');
		parent::display(true);
	}
	
	function doPlgAct() {
		FLEXIUtilities::doPlgAct();
	}
}
?>
