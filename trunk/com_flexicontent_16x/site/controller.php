<?php
/**
 * @version 1.5 stable $Id: controller.php 291 2010-06-13 05:46:19Z enjoyman $
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
		$view = & $this->getView('item', 'html');

		// Get/Create the model
		$model = & $this->getModel('item');

		// first verify it's an edit action
		if ($model->get('id') > 1)
		{
			$canEdit	= $user->authorize('flexicontent.editall', 'com_flexicontent');
			$canEditOwn	= $user->authorize('flexicontent.editown', 'com_flexicontent');
			if ( !($canEdit || ($canEditOwn && ($model->get('created_by') == $user->get('id')))) )
			{
				// user isn't authorize to edit
				JError::raiseError( 403, JText::_( 'FLEXI_ALERTNOTAUTH' ) );
			}
		}

		//checked out?
		if ( $model->isCheckedOut($user->get('id')))
		{
			$msg = JText::sprintf('FLEXI_DESCBEINGEDITTED', $model->get('title'));
			$this->setRedirect(JRoute::_('index.php?view=item&id='.$model->get('id'), false), $msg);
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
		$view = & $this->getView('item', 'html');

		//general access check
		$canAdd	= $user->authorize('flexicontent.create', 'com_flexicontent');
		$canAddCat = $user->authorize('flexicontent.createcat', 'com_flexicontent');
		if (!$canAdd && !$canAddCat)
		{
			// user isn't authorize to edit
			JError::raiseError( 403, JText::_( 'FLEXI_ALERTNOTAUTH' ) );
		}

		// Get/Create the model
		$model = & $this->getModel('Item');

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

		//get model
		$model = $this->getModel('Item');

		//get data from request
		$post = JRequest::get('post');
		$post['text'] = JRequest::getVar('text', '', 'post', 'string', JREQUEST_ALLOWRAW);

		//perform access checks
		$isNew = ((int) $post['id'] < 1);

		// Must be logged in
		if ($user->get('id') < 1) {
			JError::raiseError( 403, JText::_( 'FLEXI_ALERTNOTAUTH' ) );
			return;
		}

		if ($model->store($post)) {
			if($isNew) {
				$post['id'] = (int) $model->get('id');
			}
		} else {
			$msg = JText::_( 'FLEXI_ERROR_STORING_ITEM' );
			JError::raiseError( 500, $model->getError() );
		}

		$model->checkin();

		if ($isNew) {

			//Get categories for information mail
			$query 	= 'SELECT DISTINCT c.id, c.title,'
					. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as slug'
					. ' FROM #__categories AS c'
					. ' LEFT JOIN #__flexicontent_cats_item_relations AS rel ON rel.catid = c.id'
					. ' WHERE rel.itemid = '.(int) $model->get('id')
					;

			$db->setQuery( $query );

			$categories = $db->loadObjectList();

			//loop through the categories to create a string
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

			//get list of admins who receive system mails
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

			// send email notification to admins
			foreach ($adminRows as $adminRow) {

				//Not really  needed cause in com_message you can set to be notified about new messages by email
				//JUtility::sendAdminMail($adminRow->name, $adminRow->email, '', JText::_( 'FLEXI_NEW ITEM' ), $post['title'], $user->get('username'), JURI::base());

				//Send a message to the admins personal message boxes
				$message = new TableMessage($db);
				$message->send($user->get('id'), $adminRow->id, JText::_( 'FLEXI_NEW_ITEM' ), JText::sprintf('FLEXI_ON_NEW_ITEM', $post['title'], $user->get('username'), $catstring));
			}

		} else {
			// If the item isn't new, then we need to clean the cache so that our changes appear realtime
			$cache = &JFactory::getCache('com_flexicontent');
			$cache->clean();
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

		// Get an item table object and bind post variabes to it
		$item = & JTable::getInstance('flexicontent_items', '');
		$item->bind(JRequest::get('post'));

		// todo: add task checks
		if ($user->authorize('com_flexicontent', 'edit') || $user->authorize('com_flexicontent', 'edit', 'own')) {
			$item->checkin();
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
		global $mainframe;

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

			$model 	= $this->getModel('item');
			if ($model->storevote($id, $vote)) {
				$msg = JText::_( 'FLEXI_VOTE COUNTED' );
			} else {
				$msg = JText::_( 'FLEXI_VOTE FAILURE' );
				JError::raiseError( 500, $model->getError() );
			}
		}
		
		$cache = &JFactory::getCache('com_flexicontent');
		$cache->clean();

		$this->setRedirect(JRoute::_('index.php?view=item&cid='.$cid.'&id='.$id.'&layout='.$layout, false), $msg );
	}

	/**
	 *  Ajax favourites
	 *
	 * @access public
	 * @since 1.0
	 */
	function ajaxfav()
	{
		global $mainframe;
		$user 	=& JFactory::getUser();
		$id 	=  JRequest::getInt('id', 0);
		$db  	=& JFactory::getDBO();
		$model 	=  $this->getModel('Item');

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
	function ajaxvote()
	{
		global $mainframe;
		$user = &JFactory::getUser();

		/*
		$plugin = &JPluginHelper::getPlugin('content', 'extravote');
		$params = new JParameter($plugin->params);

		if ( $params->get('access') == 1 && !$user->get('id') ) {
			echo 'login';
		} else {
		*/
		$user_rating	= JRequest::getInt('user_rating');
		$cid 			= JRequest::getInt('cid');
		$xid 			= JRequest::getVar('xid');
		$db  			= &JFactory::getDBO();
	
		if (($user_rating >= 1) and ($user_rating <= 5))
		{
			$currip = ( phpversion() <= '4.2.1' ? @getenv( 'REMOTE_ADDR' ) : $_SERVER['REMOTE_ADDR'] );
		
			if ( !(int)$xid )
			{
				$query 	= 'SELECT * FROM #__content_rating'
						. ' WHERE content_id = ' . $cid
						;
				$db->setQuery( $query );
				$votesdb = $db->loadObject();
			
				if ( !$votesdb )
				{
					$query 	= 'INSERT INTO #__content_rating ( content_id, lastip, rating_sum, rating_count )'
							. ' VALUES ( ' . $cid . ', ' . $db->Quote( $currip ) . ', ' . $user_rating . ', 1 )'
							;
					$db->setQuery( $query );
					$db->query() or die( $db->stderr() );;
			
				} else {
			
					if ($currip != ($votesdb->lastip))
					{
						$query	= 'UPDATE #__content_rating'
								. ' SET rating_count = rating_count + 1, rating_sum = rating_sum + ' .   $user_rating . ', lastip = ' . $db->Quote( $currip )
								. ' WHERE content_id = ' . $cid
								;
						$db->setQuery( $query );
						$db->query() or die( $db->stderr() );
				
					} else {
				
					echo 'voted';
					exit();
				}
			}
			
			} else {
			
				$query 	= 'SELECT * FROM #__content_extravote'
						. ' WHERE content_id='.$cid.' AND extra_id='.$xid
						;
				$db->setQuery( $query );
				$votesdb = $db->loadObject();
				
				if ( !$votesdb )
				{
					$query	= 'INSERT INTO #__content_extravote  (content_id, extra_id, lastip, rating_sum, rating_count)'
							. ' VALUES ('.$cid.', '.$xid.', '.$db->Quote($currip).', '.$user_rating.', 1)'
							;
					$db->setQuery( $query );
					$db->query() or die( $db->stderr() );
				
				} else {
				
					if ($currip != ($votesdb->lastip))
					{
						$query	= 'UPDATE #__content_extravote'
								. ' SET rating_count = rating_count + 1, rating_sum = rating_sum + ' .  $user_rating . ', lastip = ' . $db->Quote( $currip )
								. ' WHERE content_id='.$cid
								. ' AND extra_id='.$xid
								;
						$db->setQuery( $query );
						$db->query() or die( $db->stderr() );
				
					} else {
				
					echo 'voted';
					exit();
					}
				}
			}
		echo 'thanks';
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
		$model 	= $this->getModel('Item');
		$tags 	= $model->getAlltags();

		$used = null;

		if ($id) {
			//$used 	= $model->getUsedtags($id);
			$used 	= $model->getUsedtags();
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
			$model 	= $this->getModel('item');
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
			$result = $model->addtag($name);
			if($result)
				echo $model->_tag->id."|".$model->_tag->name;
		} else {
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

		$model 	= $this->getModel('item');
		if ($model->addfav()) {
			$msg = JText::_( 'FLEXI_FAVOURITE_ADDED' );
		} else {
			JError::raiseError( 500, $model->getError() );
			$msg = JText::_( 'FLEXI_FAVOURITE_NOT_ADDED' );
		}
		
		$cache = &JFactory::getCache('com_flexicontent');
		$cache->clean();

		$this->setRedirect(JRoute::_('index.php?view=item&cid='.$cid.'&id='. $id, false), $msg );

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

		$model 	= $this->getModel('item');
		if ($model->removefav()) {
			$msg = JText::_( 'FLEXI_FAVOURITE_REMOVED' );
		} else {
			JError::raiseError( 500, $model->getError() );
			$msg = JText::_( 'FLEXI_FAVOURITE_NOT_REMOVED' );
		}
		
		$cache = &JFactory::getCache('com_flexicontent');
		$cache->clean();
		
		if ($cid) {
			$this->setRedirect(JRoute::_('index.php?view=item&cid='.$cid.'&id='. $id, false), $msg );
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

		$model = $this->getModel('item');

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
		$gid		= (int) $user->get('aid');

		// is the field available
		$andaccess 		= FLEXI_ACCESS ? ' AND (gi.aro IN ( '.$user->gmid.' ) OR fi.access <= '. (int) $gid . ')' : ' AND fi.access <= '.$gid ;
		$joinaccess		= FLEXI_ACCESS ? ' LEFT JOIN #__flexiaccess_acl AS gi ON fi.id = gi.axo AND gi.aco = "read" AND gi.axosection = "field"' : '' ;
		// is the item available
		$andaccess2 	= FLEXI_ACCESS ? ' AND (gc.aro IN ( '.$user->gmid.' ) OR c.access <= '. (int) $gid . ')' : ' AND c.access <= '.$gid ;
		$joinaccess2	= FLEXI_ACCESS ? ' LEFT JOIN #__flexiaccess_acl AS gc ON c.id = gc.axo AND gc.aco = "read" AND gc.axosection = "item"' : '' ;

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
		global $mainframe;
		
		$user		= & JFactory::getUser();
		$gid		= (int) $user->get('aid');
		$db			= &JFactory::getDBO();

		$fieldid 	= JRequest::getInt( 'fid', 0 );
		$contentid 	= JRequest::getInt( 'cid', 0 );
		$order 		= JRequest::getInt( 'ord', 0 );

		// is the field available
		$andaccess 		= FLEXI_ACCESS ? ' AND (gi.aro IN ( '.$user->gmid.' ) OR fi.access <= '. (int) $gid . ')' : ' AND fi.access <= '.$gid ;
		$joinaccess		= FLEXI_ACCESS ? ' LEFT JOIN #__flexiaccess_acl AS gi ON fi.id = gi.axo AND gi.aco = "read" AND gi.axosection = "field"' : '' ;
		// is the item available
		$andaccess2 	= FLEXI_ACCESS ? ' AND (gc.aro IN ( '.$user->gmid.' ) OR c.access <= '. (int) $gid . ')' : ' AND c.access <= '.$gid ;
		$joinaccess2	= FLEXI_ACCESS ? ' LEFT JOIN #__flexiaccess_acl AS gc ON c.id = gc.axo AND gc.aco = "read" AND gc.axosection = "item"' : '' ;

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
		if (FLEXI_ACCESS) {
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
			$model 		=  $this->getModel('item');
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
}
?>
