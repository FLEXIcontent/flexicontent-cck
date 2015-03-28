<?php
/**
 * @version 1.5 stable $Id: fields.php 1640 2013-02-28 14:45:19Z ggppdk $
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
 * FLEXIcontent Component Fields Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerFields extends FlexicontentController
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
		$this->registerTask( 'add',          'edit' );
		$this->registerTask( 'apply',        'save' );
		$this->registerTask( 'saveandnew',   'save' );
		$this->registerTask( 'copy',         'copy' );
		$this->registerTask( 'copy_wvalues', 'copy' );
	}

	/**
	 * Logic to save a record
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function save()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );

		$task  = JRequest::getVar('task');
		$model = $this->getModel('field');
		$post  = JRequest::get( 'post' );
		$user  = JFactory::getUser();
		$cid   = JRequest::getVar( 'cid', array(0), 'default', 'array' );
		$field_id		= (int)$cid[0];
		
		// calculate access
		$asset = 'com_flexicontent.field.' . $field_id;
		if (!$field_id) {
			$is_authorised = $user->authorise('flexicontent.createfield', 'com_flexicontent');
		} else {
			$is_authorised = $user->authorise('flexicontent.editfield', $asset);
		}
		
		// check access
		if ( !$is_authorised ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=fields', '');
			return;
		}
		
		$data = $post['jform'];
		if ( $model->store($data) )
		{
			switch ($task)
			{
				case 'apply' :
					$link = 'index.php?option=com_flexicontent&view=field&cid[]='.(int) $model->get('id');
					break;

				case 'saveandnew' :
					$link = 'index.php?option=com_flexicontent&view=field';
					break;

				default :
					$link = 'index.php?option=com_flexicontent&view=fields';
					break;
			}
			$msg = JText::_( 'FLEXI_FIELD_SAVED' );

			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();
			$itemcache = JFactory::getCache('com_flexicontent_items');
			$itemcache->clean();
			$filtercache = JFactory::getCache('com_flexicontent_filters');
			$filtercache->clean();
			
		} else {

			$msg = JText::_( 'FLEXI_ERROR_SAVING_FIELD' );
			JError::raiseWarning( 500, $model->getError() );
			$link = 'index.php?option=com_flexicontent&view=field';
		}

		$model->checkin();
		$this->setRedirect($link, $msg);
	}
	
	
	/**
	 * Check in a record
	 *
	 * @since	1.5
	 */
	function checkin()
	{
		$tbl = 'flexicontent_fields';
		$redirect_url = 'index.php?option=com_flexicontent&view=fields';
		flexicontent_db::checkin($tbl, $redirect_url, $this);
		return;// true;
	}
	
	
	/**
	 * Logic to publish records
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function publish()
	{
		$user	 = JFactory::getUser();
		$cid   = JRequest::getVar( 'cid', array(0), 'default', 'array' );
		$model = $this->getModel('fields');
		$field_id = (int)$cid[0];
		
		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_PUBLISH' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=fields', '');
			return;
		}
		
		// calculate access
		$asset = 'com_flexicontent.field.' . $field_id;
		$is_authorised = $user->authorise('flexicontent.publishfield', $asset);
		
		// check access
		if ( !$is_authorised ) {
			JError::raiseNotice( 403, JText::_( 'FLEXI_ALERTNOTAUTH' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=fields', '');
			return;
		}
		
		$msg = '';
		if(!$model->publish($cid, 1)) {
			$msg = JText::_( 'FLEXI_OPERATION_FAILED' ).' : '.$model->getError();
			if (FLEXI_J16GE) throw new Exception($msg, 500); else JError::raiseError(500, $msg);
		}
		
		$total = count( $cid );
		$msg 	= $total.' '.JText::_( 'FLEXI_FIELD_PUBLISHED' );
		$cache = JFactory::getCache('com_flexicontent');
		$cache->clean();
		$itemcache = JFactory::getCache('com_flexicontent_items');
		$itemcache->clean();
		
		$this->setRedirect( 'index.php?option=com_flexicontent&view=fields', $msg );
	}
	
	
	/**
	 * Logic to unpublish records
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function unpublish()
	{
		$cid   = JRequest::getVar( 'cid', array(0), 'default', 'array' );
		$model = $this->getModel('fields');
		$user  = JFactory::getUser();
		$field_id = (int)$cid[0];
		
		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_UNPUBLISH' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=fields', '');
			return;
		}
		
		if (!$model->canunpublish($cid)) {
			JError::raiseWarning(500, JText::_( 'FLEXI_YOU_CANNOT_UNPUBLISH_THESE_FIELDS' ));
			$this->setRedirect( 'index.php?option=com_flexicontent&view=fields', '');
			return;
		}
		
		// calculate access
		$asset = 'com_flexicontent.field.' . $field_id;
		$is_authorised = $user->authorise('flexicontent.publishfield', $asset);
		
		// check access
		if ( !$is_authorised ) {
			JError::raiseNotice( 403, JText::_( 'FLEXI_ALERTNOTAUTH' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=fields', '');
			return;
		}
		
		$msg = '';
		if (!$model->publish($cid, 0)) {
			$msg = JText::_( 'FLEXI_OPERATION_FAILED' ).' : '.$model->getError();
			if (FLEXI_J16GE) throw new Exception($msg, 500); else JError::raiseError(500, $msg);
		}
		$total = count( $cid );
		$msg 	= $total.' '.JText::_( 'FLEXI_FIELD_UNPUBLISHED' );
		$cache = JFactory::getCache('com_flexicontent');
		$cache->clean();
		$itemcache = JFactory::getCache('com_flexicontent_items');
		$itemcache->clean();
		
		$this->setRedirect( 'index.php?option=com_flexicontent&view=fields', $msg );
	}

	
	/**
	 * Logic to toggele boolean property of fields
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function toggleprop()
	{
		$user   = JFactory::getUser();
		$model  = $this->getModel('fields');
		$cid    = JRequest::getVar( 'cid', array(0), 'default', 'array' );
		$propname = JRequest::getCmd( 'propname', null, 'default' );
		$field_id = (int)$cid[0];

		if (!is_array( $cid ) || count( $cid ) < 1) {
			$msg = '';
			JError::raiseWarning(500, JText::_( 'FLEXI_SELECT_ITEM_TOGGLE_PROPERTY' ) );
		/*} else if (!$model->cantoggleprop($cid, $propname)) {
			$msg = '';
			JError::raiseWarning(500, JText::_( 'FLEXI_YOU_CANNOT_TOGGLE_PROPERTIES_THESE_FIELDS' ));*/
		} else {
			
			$asset = 'com_flexicontent.field.' . $field_id;
			$is_authorised = $user->authorise('flexicontent.publishfield', $asset);
			
			if ( !$is_authorised ) {
				$msg = '';
				JError::raiseNotice( 403, JText::_( 'FLEXI_ALERTNOTAUTH' ) );
			} else {
				$unsupported = 0; $locked = 0;
				$affected = $model->toggleprop($cid, $propname, $unsupported, $locked);
				if ($affected === false) {
					$msg = JText::_( 'FLEXI_OPERATION_FAILED' );
					JError::raiseWarning( 500, $model->getError() );
				} else {
					// A message about total count of affected rows , and about skipped fields (unsupported or locked)
					$prop_map = array('issearch'=>'FLEXI_TOGGLE_TEXT_SEARCHABLE', 'isfilter'=>'FLEXI_TOGGLE_FILTERABLE',
						'isadvsearch'=>'FLEXI_TOGGLE_ADV_TEXT_SEARCHABLE', 'isadvfilter'=>'FLEXI_TOGGLE_ADV_FILTERABLE');
					$property_fullname = isset($prop_map[$propname]) ? "'".JText::_($prop_map[$propname])."'" : '';
					$msg = JText::sprintf( 'FLEXI_FIELDS_TOGGLED_PROPERTY', $property_fullname, $affected);
					if ($affected < count($cid))
						$msg .= '<br/>'.JText::sprintf( 'FLEXI_FIELDS_TOGGLED_PROPERTY_FIELDS_SKIPPED', $unsupported + $locked, $unsupported, $locked);
					
					// Clean cache as needed
					$cache = JFactory::getCache('com_flexicontent');
					$cache->clean();
					$itemcache = JFactory::getCache('com_flexicontent_items');
					$itemcache->clean();
				}
			}
		}
		
		$this->setRedirect( 'index.php?option=com_flexicontent&view=fields', $msg );
	}

	
	/**
	 * Logic to delete records
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function remove()
	{
		$cid   = JRequest::getVar( 'cid', array(0), 'default', 'array' );
		$model = $this->getModel('fields');
		$user  = JFactory::getUser();
		$field_id = (int)$cid[0];

		if (!is_array( $cid ) || count( $cid ) < 1) {
			$msg = '';
			JError::raiseNotice(500, JText::_( 'FLEXI_SELECT_ITEM_DELETE' ) );
		} else if (!$model->candelete($cid)) {
			$msg = '';
			JError::raiseNotice(500, JText::_( 'FLEXI_YOU_CANNOT_REMOVE_CORE_FIELDS' ));
		} else {
			
			$asset = 'com_flexicontent.field.' . $field_id;
			$is_authorised = $user->authorise('flexicontent.deletefield', $asset);
			
			if ( !$is_authorised ) {
				$msg = '';
				JError::raiseNotice( 403, JText::_( 'FLEXI_ALERTNOTAUTH' ) );
			} else if (!$model->delete($cid)) {
				$msg = JText::_( 'FLEXI_OPERATION_FAILED' );
				JError::raiseWarning( 500, $model->getError() );
			} else {
				$msg = count($cid).' '.JText::_( 'FLEXI_FIELDS_DELETED' );
				$cache = JFactory::getCache('com_flexicontent');
				$cache->clean();
				$itemcache = JFactory::getCache('com_flexicontent_items');
				$itemcache->clean();
			}
		}
		
		$this->setRedirect( 'index.php?option=com_flexicontent&view=fields', $msg );
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
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$post = JRequest::get('post');
		$post = FLEXI_J16GE ? $post['jform'] : $post;
		JRequest::setVar('cid', $post['id']);
		$this->checkin();
	}
	
	
	/**
	 * Logic to create the view for the edit field screen
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function edit()
	{
		JRequest::setVar( 'view', 'field' );
		JRequest::setVar( 'hidemainmenu', 1 );
		
		$user     = JFactory::getUser();
		$session  = JFactory::getSession();
		$document = JFactory::getDocument();
		
		// Get/Create the view
		$viewType   = $document->getType();
		$viewName   = FLEXI_J30GE ? $this->input->get('view', $this->default_view) : JRequest::getVar('view');
		$viewLayout = FLEXI_J30GE ? $this->input->get('layout', 'default', 'string') : JRequest::getVar('layout', 'default', 'string');
		$view = $this->getView($viewName, $viewType, '', array('base_path' => $this->basePath, 'layout' => $viewLayout));
		
		// Get/Create the model
		$model = $this->getModel('field');
		
		// Push the model into the view (as default), later we will call the view display method instead of calling parent's display task, because it will create a 2nd model instance !!
		$view->setModel($model, true);
		$view->document = $document;
		
		$cid   = JRequest::getVar( 'cid', array(0), 'default', 'array' );
		$field_id = (int)$cid[0];

		// calculate access
		$asset = 'com_flexicontent.field.' . $field_id;
		if (!$field_id) {
			$is_authorised = $user->authorise('flexicontent.createfield', 'com_flexicontent');
		} else {
			$is_authorised = $user->authorise('flexicontent.editfield', $asset);
		}
		
		// check access
		if ( !$is_authorised ) {
			JError::raiseNotice( 403, JText::_( 'FLEXI_ALERTNOTAUTH' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=fields', '');
			return;
		}
		
		// Check if record is checked out by other editor
		if ( $model->isCheckedOut( $user->get('id') ) ) {
			JError::raiseNotice( 500, JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ));
			$this->setRedirect( 'index.php?option=com_flexicontent&view=fields', '');
			return;
		}
		
		// Checkout the record and proceed to edit form
		if ( !$model->checkout() ) {
			JError::raiseWarning( 500, $model->getError() );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=fields', '');
			return;
		}
		
		$view->display();
	}


	/**
	 * Logic to order up/down a field
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function reorder($dir=null)
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		// Get variables: model, user, field id, new ordering
		$model = $this->getModel('fields');
		$user  = JFactory::getUser();
		$cid   = JRequest::getVar( 'cid', array(0), 'default', 'array' );
		
		// calculate access
		$is_authorised = $user->authorise('flexicontent.orderfields', 'com_flexicontent');
		
		// check access
		if ( !$is_authorised ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH' ) );
		} else if ( $model->move($dir) ){
			// success
		} else {
			$msg = JText::_( 'FLEXI_ERROR_SAVING_ORDER' );
			JError::raiseWarning( 500, $model->getError() );
		}

		$this->setRedirect( 'index.php?option=com_flexicontent&view=fields');
	}


	/**
	 * Logic to orderup a field
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
	 * Logic to orderdown a field
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
	 * Logic to mass ordering fields
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function saveorder()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		// Get variables: model, user, field id, new ordering
		$model = $this->getModel('fields');
		$user  = JFactory::getUser();
		$cid   = JRequest::getVar( 'cid', array(0), 'default', 'array' );
		$order = JRequest::getVar( 'order', array(0), 'post', 'array' );
		
		// calculate access
		$is_authorised = $user->authorise('flexicontent.orderfields', 'com_flexicontent');
		
		// check access
		if ( !$is_authorised ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH' ) );
		} else if(!$model->saveorder($cid, $order)) {
			$msg = JText::_( 'FLEXI_OPERATION_FAILED' );
			JError::raiseWarning( 500, $model->getError() );
		} else {
			$msg = JText::_( 'FLEXI_NEW_ORDERING_SAVED' );
		}

		$this->setRedirect( 'index.php?option=com_flexicontent&view=fields', $msg );
	}

	/**
	 * Logic to set the access level of the Fields
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function access( )
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );

		$user  = JFactory::getUser();
		$model = $this->getModel('fields');
		$task  = JRequest::getVar( 'task' );
		$cid   = JRequest::getVar( 'cid', array(0), 'default', 'array' );
		$field_id = (int)$cid[0];
		
		// calculate access
		$asset = 'com_flexicontent.field.' . $field_id;
		$is_authorised = $user->authorise('flexicontent.publishfield', $asset);
		
		// check access
		if ( !$is_authorised ) {
			JError::raiseNotice( 403, JText::_( 'FLEXI_ALERTNOTAUTH' ) );
			$this->setRedirect( 'index.php?option=com_flexicontent&view=fields', '');
			return;
		}
		
		$accesses	= JRequest::getVar( 'access', array(0), 'post', 'array' );
		$access = $accesses[$field_id];
		
		if(!$model->saveaccess( $field_id, $access )) {
			$msg = JText::_( 'FLEXI_OPERATION_FAILED' );
			JError::raiseWarning( 500, $model->getError() );
		} else {
			$msg = '';
			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();
		}
		
		$this->setRedirect('index.php?option=com_flexicontent&view=fields', $msg);
	}

	/**
	 * Logic to copy the fields
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function copy( )
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		// Get model, user, ids of copied fields
		$model = $this->getModel('fields');
		$user  = JFactory::getUser();
		$cid   = JRequest::getVar( 'cid', array(0), 'default', 'array' );
		$task  = JRequest::getVar( 'task', 'copy' );
		
		// calculate access
		$is_authorised = $user->authorise('flexicontent.copyfields', 'com_flexicontent');
		
		// check access
		if ( !$is_authorised ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_ALERTNOTAUTH' ) );
			$this->setRedirect('index.php?option=com_flexicontent&view=fields');
			return;
		}
		
		// Remove core fields
		$core_cid = array();
		$non_core_cid = array();
		
		// Copying of core fields is not allowed
		foreach ($cid as $id) {
			if ($id < 15) {
				$core_cid[] = $id;
			} else {
				$non_core_cid[] = $id;
			}
		}
		
		// Remove uneditable fields
		$auth_cid = array();
		$non_auth_cid = array();
		
		// Cannot copy fields you cannot edit
		foreach ($non_core_cid as $id)
		{
			$asset = 'com_flexicontent.field.' . $id;
			$is_authorised = $user->authorise('flexicontent.editfield', $asset);
			
			if ($is_authorised) {
				$auth_cid[] = $id;
			} else {
				$non_auth_cid[] = $id;
			}
		}
		
		// Try to copy fields
		$ids_map = $model->copy( $auth_cid, $task == 'copy_wvalues');
		if ( !$ids_map ) {
			$msg = JText::_( 'FLEXI_FIELDS_COPY_FAILED' );
			JError::raiseWarning( 500, $model->getError() );
		} else {
			$msg = '';
			if (count($ids_map)) {
				$msg .= JText::sprintf('FLEXI_FIELDS_COPY_SUCCESS', count($ids_map)) . ' ';
			}
			if ( count($auth_cid)-count($ids_map) ) {
				//$msg .= JText::sprintf('FLEXI_FIELDS_SKIPPED_DURING_COPY', count($auth_cid)-count($ids_map)) . ' ';
			}
			if (count($core_cid)) {
				$msg .= JText::sprintf('FLEXI_FIELDS_CORE_FIELDS_NOT_COPIED', count($core_cid)) . ' ';
			}
			if (count($non_auth_cid)) {
				$msg .= JText::sprintf('FLEXI_FIELDS_UNEDITABLE_FIELDS_NOT_COPIED', count($non_auth_cid)) . ' ';
			}
			$cache = JFactory::getCache('com_flexicontent');
			$cache->clean();
		}
		
		$app = JFactory::getApplication();
		$option = JRequest::getVar('option');
		
		$filter_type = $app->getUserStateFromRequest( $option.'.fields.filter_type', 'filter_type', '', 'int' );
		if ($filter_type) {
			$app->setUserState( $option.'.fields.filter_type', '' );
			$msg .= ' '.JText::_('FLEXI_TYPE_FILTER_CLEARED_TO_VIEW_NEW_FIELDS');
		}
		$this->setRedirect('index.php?option=com_flexicontent&view=fields', $msg );
	}
	
	
	function exportcsv()
	{
		$cid = JRequest::getVar( 'cid' );
		$db  = JFactory::getDBO();
		$query = 'SELECT *'
				. ' FROM #__flexicontent_fields'
				. ($cid ? ' WHERE id = '.$cid : '')
				;
		$db->setQuery($query);
		$_fields = $db->loadObjectList('id');
		
		$fp = fopen('php://output', 'w');
		if ($fp && $_fields) {
			header('Content-Type: text/csv');
			header('Content-Disposition: attachment; filename="export.csv"');
			foreach($_fields as $row) {
				fputcsv($fp, array_values((array)$row));
			}
			die;
		}
	}
	
	
	function exportsql()
	{
		$targetfolder = JPATH_SITE.DS.'tmp';
		$filename = "field_export_".time().".sql";
		$abspath  = $targetfolder.DS.$filename;
		$abspath  = str_replace(DS, '/', $abspath);
		
		$cid = JRequest::getInt( 'cid' );
		$db  = JFactory::getDBO();
		$query = 'SELECT * INTO OUTFILE "'.$abspath.'" '
				. ' FROM #__flexicontent_fields'
				. ($cid ? ' WHERE id = '.$cid : '')
				;
		$db->setQuery($query);
		if (!$db->query()) {
			echo $db->getError();
			exit;
		}
		
		
		// Get file filesize and extension
		$dlfile = new stdClass();
		$dlfile->filename = $filename;
		$dlfile->abspath  = $abspath;
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
		$download_filename = $dlfile->filename;
		header("Content-Disposition: attachment; filename=\"".$download_filename."\";" );
		header("Content-Transfer-Encoding: binary");
		header("Content-Length: ".$dlfile->size);
		
		
		$handle = @fopen($abspath,"rb");
		while(!feof($handle))
		{
			print(@fread($handle, 1024*8));
			ob_flush();
			flush();
		}
		
		unlink($file);
		$app->close();
	}
}
