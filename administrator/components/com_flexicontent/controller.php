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
	function __construct()
	{
		parent::__construct();
		$view = JRequest::getVar('view');
		if($view && !FLEXI_SECTION) {
			$msg = JText::_( 'FLEXI_NO_SECTION_CHOOSEN' );
			$link 	= 'index.php?option=com_flexicontent';
			$this->setRedirect($link, $msg);
		}
		$session  =& JFactory::getSession();
		
		$dopostinstall =& $session->get('flexicontent.postinstall');
		if(($dopostinstall===NULL) || ($dopostinstall===false)) {
			$session->set('flexicontent.postinstall', $dopostinstall = $this->getPostinstallState());
		}

		$allplgpublish =& $session->get('flexicontent.allplgpublish');
		if(($allplgpublish===NULL) || ($allplgpublish===false)) {
			$model 			= $this->getModel('flexicontent');
			$allplgpublish 		= & $model->getAllPluginsPublished();
			$session->set('flexicontent.allplgpublish', $allplgpublish);
		}
		
		if($view && in_array($view, array('items', 'item', 'types', 'type', 'categories', 'category', 'fields', 'field', 'tags', 'tag', 'archive', 'filemanager', 'templates', 'stats')) && !$dopostinstall) {
			$msg = JText::_( 'FLEXI_PLEASE_COMPLETE_POST_INSTALL' );
			$link 	= 'index.php?option=com_flexicontent';
			$this->setRedirect($link, $msg);
		}
		
		// Register Extra task
		$this->registerTask( 'apply'					, 'save' );
		$this->registerTask( 'applyacl'					, 'saveacl' );
		$this->registerTask( 'createdefaultfields'		, 'createDefaultFields' );
		$this->registerTask( 'createdefaultype'			, 'createDefaultType' );
		$this->registerTask( 'publishplugins'			, 'publishplugins' );
		$this->registerTask( 'createlangcolumn'			, 'createLangColumn' );
		$this->registerTask( 'createversionstable'		, 'createVersionsTable' );
		$this->registerTask( 'populateversionstable'	, 'populateVersionsTable' );
		$this->registerTask( 'deleteoldfiles'			, 'deleteOldBetaFiles' );
		$this->registerTask( 'cleanupoldtables'			, 'cleanupOldTables' );
		$this->registerTask( 'addcurrentversiondata'	, 'addCurrentVersionData' );
	}

	function getPostinstallState() {
		$model 			= $this->getModel('flexicontent');
		$existtype 		= & $model->getExistType();
		$existfields 		= & $model->getExistFields();
		$existlang	 	= & $model->getExistLanguageColumn();
		$existversions 		= & $model->getExistVersionsTable();
		$existversionsdata	= & $model->getExistVersionsPopulated();
		$oldbetafiles		= & $model->getOldBetaFiles();
		$nooldfieldsdata	= & $model->getNoOldFieldsData();
		$params 	= & JComponentHelper::getParams('com_flexicontent');
		$use_versioning = $params->get('use_versioning', 1);
		$missingversion		= ($use_versioning&&$model->checkCurrentVersionData());
		$dopostinstall = true;
		if ((!$existfields) || (!$existtype) || (!$existlang) || (!$existversions) || (!$existversionsdata) || (!$oldbetafiles) || (!$nooldfieldsdata) || ($missingversion)) {
			$dopostinstall = false;
		}
		return $dopostinstall;
	}
	/**
	 * Display the view
	 */
	function display()
	{
		parent::display();

	}

	/**
	 * Saves the acl file
	 *
	 */
	function saveacl()
	{
		global $mainframe, $option;

		JRequest::checkToken() or jexit( 'Invalid Token' );

		// Initialize some variables
		$option			= JRequest::getVar('option');
		$filename		= JRequest::getVar('filename', '', 'post', 'cmd');
		$filecontent	= JRequest::getVar('filecontent', '', '', '', JREQUEST_ALLOWRAW);

		if (!$filecontent) {
			$mainframe->redirect('index.php?option='.$option, JText::_( 'FLEXI_OPERATION_FAILED' ).': '.JText::_( 'FLEXI_CONTENT_EMPTY' ));
		}

		// Set FTP credentials, if given
		jimport('joomla.client.helper');
		JClientHelper::setCredentialsFromRequest('ftp');
		$ftp = JClientHelper::getCredentials('ftp');

		$file = JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.$filename;

		// Try to make the acl file writeable
		if (!$ftp['enabled'] && JPath::isOwner($file) && !JPath::setPermissions($file, '0755')) {
			JError::raiseNotice('SOME_ERROR_CODE', JText::_( 'FLEXI_COULD_NOT_MAKE_ACL_FILE_WRITABLE' ));
		}

		jimport('joomla.filesystem.file');
		$return = JFile::write($file, $filecontent);

		// Try to make the acl file unwriteable
		if (!$ftp['enabled'] && JPath::isOwner($file) && !JPath::setPermissions($file, '0555')) {
			JError::raiseNotice('SOME_ERROR_CODE', JText::_( 'FLEXI_COULD_NOT_MAKE_ACL_FILE_UNWRITABLE' ));
		}

		if ($return)
		{
			$task = JRequest::getVar('task');
			switch($task)
			{
				case 'applyacl' :
					$mainframe->redirect('index.php?option='.$option.'&view=editacl', JText::_( 'FLEXI_ACL_FILE_SUCCESSFULLY_ALTERED' ));
					break;

				case 'saveacl'  :
				default         :
					$mainframe->redirect('index.php?option='.$option, JText::_( 'FLEXI_ACL_FILE_SUCCESSFULLY_ALTERED' ) );
					break;
			}
		} else {
			$mainframe->redirect('index.php?option='.$option, JText::_( 'FLEXI_OPERATION_FAILED' ).': '.JText::sprintf('FLEXI_FAILED_TO_OPEN_FILE_FOR_WRITING', $file));
		}
	}
	
	/**
	 * Method to create default type : article
	 * 
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function createDefaultType()
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		$db 	=& JFactory::getDBO();

		$query 	=	"INSERT INTO `#__flexicontent_types` VALUES(1, 'Article', 'article', 1, 0, '0000-00-00 00:00:00', 0, 'ilayout=default\nhide_maintext=0\nhide_html=0\nmaintext_label=\nmaintext_desc=\ncomments=\ntop_cols=two\nbottom_cols=two')" ;
		$db->setQuery($query);
		if (!$db->query()) {
			echo '<span class="install-notok"></span><span class="button-add"><a id="existtype" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>';
		} else {
			$query 	=	"INSERT INTO `#__flexicontent_fields_type_relations` (`field_id`,`type_id`,`ordering`)
						VALUES
							(1,1,1),
							(2,1,2),
							(3,1,3),
							(4,1,4),
							(5,1,5),
							(6,1,6),
							(7,1,7),
							(8,1,8),
							(9,1,9),
							(10,1,10),
							(11,1,11),
							(12,1,12),
							(13,1,13),
							(14,1,14)" ;
			$db->setQuery($query);
			if (!$db->query()) {
				echo '<span class="install-notok"></span><span class="button-add"><a id="existtype" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>';
			} else {
				echo '<span class="install-ok"></span>';
			}
		}
	}

	/**
	 * Method to create default fields data
	 * 
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function createDefaultFields()
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		$db 	=& JFactory::getDBO();

		$query 	=	"INSERT INTO #__flexicontent_fields (`id`,`field_type`,`name`,`label`,`description`,`isfilter`,`iscore`,`issearch`,`isadvsearch`,`positions`,`published`,`attribs`,`checked_out`,`checked_out_time`,`access`,`ordering`)
VALUES
	(1,'maintext','text','Description','The main description text (introtext/fulltext)',0,1,1,0,'description.items.default',1,'display_label=0\ntrigger_onprepare_content=0',0,'0000-00-00 00:00:00',0,2),
	(2,'created','created','Created','Creation date',0,1,1,0,'top.items.default\nabove-description-line1-nolabel.category.blog',1,'display_label=1\ndate_format=DATE_FORMAT_LC1\ncustom_date=\npretext=\nposttext=',0,'0000-00-00 00:00:00',0,3),
	(3,'createdby','created_by','Created by','Item author',0,1,1,0,'top.items.default\nabove-description-line1-nolabel.category.blog',1,'display_label=1\npretext=\nposttext=',0,'0000-00-00 00:00:00',0,4),
	(4,'modified','modified','Last modified','Date of the last modification',0,1,1,0,'top.items.default',1,'display_label=1\ndate_format=DATE_FORMAT_LC1\ncustom_date=\npretext=\nposttext=',0,'0000-00-00 00:00:00',0,5),
	(5,'modifiedby','modified_by','Revised by','Name of the user which last edited the item',0,1,1,0,'top.items.default',1,'display_label=1\npretext=\nposttext=',0,'0000-00-00 00:00:00',0,6),
	(6,'title','title','Title','The item title',0,1,1,0,'',1,'display_label=1',0,'0000-00-00 00:00:00',0,1),
	(7,'hits','hits','Hits','Number of hits',0,1,1,0,'',1,'display_label=1\npretext=\nposttext=views',0,'0000-00-00 00:00:00',0,7),
	(8,'type','document_type','Document type','Document type',0,1,1,0,'',1,'display_label=1\npretext=\nposttext=',0,'0000-00-00 00:00:00',0,8),
	(9,'version','version','Version','Number of version',0,1,1,0,'',1,'display_label=1\npretext=\nposttext=',0,'0000-00-00 00:00:00',0,9),
	(10,'state','state','State','State',0,1,1,0,'',1,'display_label=1',0,'0000-00-00 00:00:00',0,10),
	(11,'voting','voting','Voting','The up and down voting buttons',0,1,1,0,'top.items.default\nabove-description-line2-nolabel.category.blog',1,'display_label=1\ndimension=16\nimage=components/com_flexicontent/assets/images/star-small.png',0,'0000-00-00 00:00:00',0,11),
	(12,'favourites','favourites','Favourites','The add to favourites button',0,1,1,0,'top.items.default\nabove-description-line2-nolabel.category.blog',1,'display_label=1',0,'0000-00-00 00:00:00',0,12),
	(13,'categories','categories','Categories','The categories assigned to this item',0,1,1,0,'top.items.default\nunder-description-line1.category.blog',1,'display_label=1\nseparatorf=2',0,'0000-00-00 00:00:00',0,13),
	(14,'tags','tags','Tags','The tags assigned to this item',0,1,1,0,'top.items.default\nunder-description-line2.category.blog',1,'display_label=1\nseparatorf=2',0,'0000-00-00 00:00:00',0,14)" ;
		$db->setQuery($query);
		if (!$db->query()) {
			echo '<span class="install-notok"></span><span class="button-add"><a id="existfields" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>';
		} else {
			echo '<span class="install-ok"></span>';
		}
	}

	/**
	 * Publish FLEXIcontent plugins
	 *
	 * @access	public
	 * @return	boolean	True on success
	 * @since	1.5
	 */
	function publishplugins()
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		$format		= JRequest::getVar('format', '');
		$db =& JFactory::getDBO();
		
		$query	= 'UPDATE #__plugins'
				. ' SET published = 1'
				. ' WHERE folder = ' . $db->Quote('flexicontent_fields')
				. ' OR element = ' . $db->Quote('flexisearch')
				. ' OR element = ' . $db->Quote('flexisystem')
				. ' OR element = ' . $db->Quote('flexiadvsearch')
				. ' OR element = ' . $db->Quote('flexiadvroute')
				;
		
		$db->setQuery($query);
		if (!$db->query()) {
			if ($format == 'raw') {
				echo '<span class="install-notok"></span><span class="button-add"><a id="publishplugins" href="index.php?option=com_flexicontent&task=publishplugins&format=raw">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>';
			} else {
				JError::raiseNotice(1, JText::_( 'FLEXI_COULD_NOT_PUBLISH_PLUGINS' ));
				return false;
			}
		} else {
			if ($format == 'raw') {
				echo '<span class="install-ok"></span>';
			} else {
				return true;
			}
		}
	}

	/**
	 * Method to set the default site language the items with no language
	 * 
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function getItemsNoLang()
	{
		$db =& JFactory::getDBO();

		$query 	= "SELECT item_id FROM #__flexicontent_items_ext"
				. " WHERE language = ''"
				;
		$db->setQuery($query);
		$cid = $db->loadResultArray();
		
		return $cid;
	}

	/**
	 * Method to set the default site language the items with no language
	 * 
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function setItemsDefaultLang($cid, $lang)
	{
		$db =& JFactory::getDBO();

		$query 	= 'UPDATE #__flexicontent_items_ext'
				. ' SET language = ' . $db->Quote($lang)
				. ' WHERE item_id IN ( ' . implode(',', $cid) . ' )'
				;
		$db->setQuery($query);
		$db->query();
		
		return true;
	}

	/**
	 * Method to create the language datas
	 * 
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function createLangColumn()
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		$db 		=& JFactory::getDBO();
		$nullDate	= $db->getNullDate();

		$query 	=	"ALTER TABLE #__flexicontent_items_ext ADD `language` VARCHAR( 11 ) NOT NULL DEFAULT '' AFTER `type_id`" ;
		$db->setQuery($query);
		if (!$db->query()) {
			echo '<span class="install-notok"></span><span class="button-add"><a id="existversionsdata" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>';
		} else {
			// Add site default language to the language field if empty
			$languages 	=& JComponentHelper::getParams('com_languages');
			$lang 		= $languages->get('site', 'en-GB');
			$cid 		= $this->getItemsNoLang();
		
			if (!$this->setItemsDefaultLang($cid, $lang)) {
				echo '<span class="install-notok"></span><span class="button-add"><a id="existversionsdata" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>';
			} else {
				echo '<span class="install-ok"></span>';
			}
		}
	}
	
	/**
	 * Method to create the versions table
	 * 
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function createVersionsTable()
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		$db 		=& JFactory::getDBO();
		$nullDate	= $db->getNullDate();

		$query 	= " CREATE TABLE IF NOT EXISTS #__flexicontent_versions (
	  				`id` int(11) unsigned NOT NULL auto_increment,
					`item_id` int(11) unsigned NOT NULL default '0',
					`version_id` int(11) unsigned NOT NULL default '0',
					`comment` mediumtext NOT NULL,
					`created` datetime NOT NULL default '0000-00-00 00:00:00',
					`created_by` int(11) unsigned NOT NULL default '0',
					`state` int(3) NOT NULL default '0',
					PRIMARY KEY  (`id`),
					KEY `version2item` (`item_id`,`version_id`)
					) TYPE=MyISAM CHARACTER SET `utf8` COLLATE `utf8_general_ci`"
					;
		$db->setQuery($query);
		
		if (!$db->query()) {
			echo '<span class="install-notok"></span><span class="button-add"><a id="existversions" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>';
		} else {
			echo '<span class="install-ok"></span>';
		}
	}

	/**
	 * Method to handle the versions data and to populate the new beta4 versions table
	 * From #__flexicontent_items_versions to #__flexicontent_versions
	 * 
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function populateVersionsTable()
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		$db 		=& JFactory::getDBO();
		$nullDate	= $db->getNullDate();

		$query 	= 'SELECT item_id, version FROM #__flexicontent_items_versions'
				. ' WHERE field_id = 2'
				. ' ORDER BY item_id, version'
				;
		$db->setQuery($query);
		$cvs = $db->loadObjectList();

		$query 	= 'SELECT * FROM #__flexicontent_items_versions'
				. ' WHERE field_id IN ( 2,3,4,5 )'
				. ' ORDER BY item_id, version, field_id'
				;
		$db->setQuery($query);
		$fvs = $db->loadObjectList();
		
		for ($i=0; $i<count($cvs); $i++) {
			foreach ($fvs as $fv) {
				if ($fv->item_id == $cvs[$i]->item_id && $fv->version == $cvs[$i]->version && $fv->field_id == '2') $cvs[$i]->created = $fv->value;
				if ($fv->item_id == $cvs[$i]->item_id && $fv->version == $cvs[$i]->version && $fv->field_id == '3') $cvs[$i]->created_by = $fv->value;
				if ($fv->item_id == $cvs[$i]->item_id && $fv->version == $cvs[$i]->version && $fv->field_id == '4') $cvs[$i]->modified = $fv->value;
				if ($fv->item_id == $cvs[$i]->item_id && $fv->version == $cvs[$i]->version && $fv->field_id == '5') $cvs[$i]->modified_by = $fv->value;
			}
		}
		
		$versions = new stdClass();
		$n = 0;
		foreach ($cvs as $cv) {
			$versions->$n->item_id 		= $cv->item_id;
			$versions->$n->version_id 	= $cv->version;
			$versions->$n->comment	 	= '';
			$versions->$n->created 		= (isset($cv->modified) && ($cv->modified != $nullDate)) ? $cv->modified : $cv->created;
			$versions->$n->created_by 	= (isset($cv->modified_by) && $cv->modified_by) ? $cv->modified_by : $cv->created_by;
			$versions->$n->state	 	= 1;
			$db->insertObject('#__flexicontent_versions', $versions->$n);
			$n++;
		}

		echo '<span class="install-ok"></span>';
	}

	/**
	 * Method to check if the files from beta3 still exist in the category and item view
	 *
	 * @access public
	 * @return	boolean	True on success
	 */
	function deleteOldBetaFiles()
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		jimport('joomla.filesystem.file');

		$files 	= array (
			'default.xml',
			'default.php',
			'index.html',
			'form.php',
			'form.xml'
			);
		$catdir 	= JPath::clean(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'views'.DS.'category'.DS.'tmpl');
		$cattmpl 	= JFolder::files($catdir);		
		$ctmpl 		= array_diff($cattmpl,$files);
		foreach ($ctmpl as $c) {
			JFile::delete($catdir.DS.$c);
		}
		
		$itemdir 	= JPath::clean(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'views'.DS.'items'.DS.'tmpl');
		$itemtmpl 	= JFolder::files($itemdir);		
		$itmpl 		= array_diff($itemtmpl,$files);
		foreach ($itmpl as $i) {
			JFile::delete($itemdir.DS.$i);
		}

		$model = $this->getModel('flexicontent');
		if ($model->getOldBetaFiles()) {
			echo '<span class="install-ok"></span>';
		} else {
			echo '<span class="install-notok"></span><span class="button-add"><a id="oldbetafiles" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>';
		}
	}

	/**
	 * Method to delete old core fields data in the fields_items_relations table
	 * Delete also old versions fields data
	 * Alter value fields to mediumtext in order to store large items
	 * 
	 * @access	public
	 * @return	boolean	True on success
	 * @since 1.5
	 */
	function cleanupOldTables()
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		$db =& JFactory::getDBO();

		$queries 	= array();
		// alter some table field types
		$queries[] 	= "ALTER TABLE #__flexicontent_fields_item_relations CHANGE `value` `value` MEDIUMTEXT" ;
		$queries[] 	= "ALTER TABLE #__flexicontent_items_versions CHANGE `value` `value` MEDIUMTEXT" ;
		$queries[] 	= "ALTER TABLE #__flexicontent_items_ext CHANGE `search_index` `search_index` MEDIUMTEXT" ;
		$queries[] 	= "ALTER TABLE #__flexicontent_items_ext CHANGE `sub_items` `sub_items` TEXT" ;
		$queries[] 	= "ALTER TABLE #__flexicontent_items_ext CHANGE `sub_categories` `sub_categories` TEXT" ;
		$queries[] 	= "ALTER TABLE #__flexicontent_items_ext CHANGE `related_items` `related_items` TEXT" ;

		foreach ($queries as $query) {
			$db->setQuery($query);
			$db->query();
		}
		$query = "SELECT id,version,created,created_by FROM #__content WHERE sectionid='".FLEXI_SECTION."';";
		$db->setQuery($query);
		$rows = $db->loadObjectList();
		global $mainframe;
		foreach($rows as $row) {
			$lastversion = FLEXIUtilities::getLastVersions($row->id, true);
			if($row->version > $lastversion) {
				$query = "SELECT f.id,fir.value,f.field_type,f.name,fir.valueorder "
						." FROM #__flexicontent_fields_item_relations as fir"
						//." LEFT JOIN #__flexicontent_items_versions as iv ON iv.field_id="
						." LEFT JOIN #__flexicontent_fields as f on f.id=fir.field_id "
						." WHERE fir.item_id='".$row->id."';";
				$db->setQuery($query);
				$fields = $db->loadObjectList();
				$jcorefields = flexicontent_html::getJCoreFields();
				$catflag = false;
				$tagflag = false;
				/*$clean_database = true;
				if(!$clean_database && $fields) {
					$query = 'DELETE FROM #__flexicontent_fields_item_relations WHERE item_id = '.$row->id;
					$db->setQuery($query);
					$db->query();
				}*/
				foreach($fields as $field) {
					//JPluginHelper::importPlugin('flexicontent_fields', $field->field_type);
					
					// process field mambots onBeforeSaveField
					//$results = $dispatcher->trigger('onBeforeSaveField', array( $field, &$post[$field->name], &$files[$field->name] ));

					// add the new values to the database 
					$obj = new stdClass();
					$obj->field_id 		= $field->id;
					$obj->item_id 		= $row->id;
					$obj->valueorder	= $field->valueorder;
					$obj->version		= (int)$row->version;
					// @TODO : move in the plugin code
					if( ($field->field_type=='categories') && ($field->name=='categories') ) {
						continue;
						//$obj->value = serialize($item->categories);
						//$catflag = true;
					}elseif( ($field->field_type=='tags') && ($field->name=='tags') ) {
						continue;
						//$obj->value = serialize($item->tags);
						//$tagflag = true;
					}else{
						$obj->value			= $field->value;
					}
					//echo "version: ".$obj->version.",fieldid : ".$obj->field_id.",value : ".$obj->value.",valueorder : ".$obj->valueorder."<br />";
					$db->insertObject('#__flexicontent_items_versions', $obj);
					//echo "insert into __flexicontent_items_versions<br />";
					if( !isset($jcorefields[$field->name]) && !in_array($field->field_type, $jcorefields)) {
						unset($obj->version);
						$db->insertObject('#__flexicontent_fields_item_relations', $obj);
						//echo "insert into __flexicontent_fields_item_relations<br />";
					}
					// process field mambots onAfterSaveField
					//$results		 = $dispatcher->trigger('onAfterSaveField', array( $field, &$post[$field->name], &$files[$field->name] ));
					//$searchindex 	.= @$field->search;
				}
				if(!$catflag) {
					$query = "SELECT catid FROM #__flexicontent_cats_item_relations WHERE itemid='".$row->id."';";
					$db->setQuery($query);
					$categories = $db->loadResultArray();
					$obj = new stdClass();
					$obj->field_id 		= 13;
					$obj->item_id 		= $row->id;
					$obj->valueorder	= 1;
					$obj->version		= (int)$row->version;
					$obj->value		= serialize($categories);
					$db->insertObject('#__flexicontent_items_versions', $obj);
					//unset($obj->version);
					//$this->_db->insertObject('#__flexicontent_fields_item_relations', $obj);
				}
				if(!$tagflag) {
					$query = "SELECT tid FROM #__flexicontent_tags_item_relations WHERE itemid='".$row->id."';";
					$db->setQuery($query);
					$tags = $db->loadResultArray();
					$obj = new stdClass();
					$obj->field_id 		= 14;
					$obj->item_id 		= $row->id;
					$obj->valueorder	= 1;
					$obj->version		= (int)$row->version;
					$obj->value		= serialize($tags);
					$db->insertObject('#__flexicontent_items_versions', $obj);
					//unset($obj->version);
					//$this->_db->insertObject('#__flexicontent_fields_item_relations', $obj);
				}
				$v = new stdClass();
				$v->item_id 		= (int)$row->id;
				$v->version_id		= (int)$row->version;
				$v->created 	= $row->created;
				$v->created_by 	= $row->created_by;
				//$v->comment		= 'kept current version to version table.';
				//echo "insert into __flexicontent_versions<br />";
				$db->insertObject('#__flexicontent_versions', $v);
			}
		}
		$queries 	= array();
		// delete unused records
		$queries[] 	= "DELETE FROM #__flexicontent_fields_item_relations WHERE field_id < 15" ;
		$queries[] 	= "DELETE FROM #__flexicontent_items_versions WHERE field_id IN (2, 3, 4, 5, 6, 7, 9, 10 )" ;

		foreach ($queries as $query) {
			$db->setQuery($query);
			$db->query();
		}

		$catscache 	=& JFactory::getCache('com_flexicontent_cats');
		$catscache->clean();

		$model = $this->getModel('flexicontent');
		if ($model->getNoOldFieldsData()) {
			echo '<span class="install-ok"></span>';
		} else {
			echo '<span class="install-notok"></span><span class="button-add"><a id="oldfieldsdata" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>';
		}
	}
	
	function addCurrentVersionData()
	{
		// Check for request forgeries
		JRequest::checkToken( 'request' ) or jexit( 'Invalid Token' );

		$model = $this->getModel('flexicontent');
		if ($model->addCurrentVersionData()) {
			echo '<span class="install-ok"></span>';
		} else {
			echo '<span class="install-notok"></span><span class="button-add"><a id="missingversion" href="#">'.JText::_( 'FLEXI_UPDATE' ).'</a></span>';
		}
	}
}
?>
