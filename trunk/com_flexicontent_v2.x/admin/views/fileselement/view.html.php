<?php
/**
 * @version 1.5 stable $Id: view.html.php 1604 2012-12-16 11:55:43Z ggppdk $
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

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.application.component.view');

/**
 * HTML View class for the Fileselement View
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewFileselement extends JViewLegacy
{
	/**
	 * Creates the Filemanagerview
	 *
	 * @since 1.0
	 */
	function display( $tpl = null )
	{
		// Check for request forgeries
		JRequest::checkToken('request') or jexit( 'Invalid Token' );

		//Load pane behavior
		jimport('joomla.html.pane');

		JHTML::_('behavior.tooltip');
		// Load the form validation behavior
		JHTML::_('behavior.formvalidation');
		
		//initialise variables
		$mainframe = &JFactory::getApplication();
		$option = JRequest::getVar('option');
		$document	= & JFactory::getDocument();
		$app			= & JFactory::getApplication();
		$pane   	= & JPane::getInstance('Tabs');
		$db  			= & JFactory::getDBO();
		$user			= & JFactory::getUser();
		$params 	= & JComponentHelper::getParams('com_flexicontent');
		
		$fieldid	= JRequest::getVar( 'field', null, 'request', 'int' );
		$client		= $app->isAdmin() ? '../' : '';
		
		//get vars
		$filter_order     = $mainframe->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.filter_order',     'filter_order',    'f.filename', 'cmd' );
		$filter_order_Dir = $mainframe->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.filter_order_Dir', 'filter_order_Dir', '',          'word' );
		$filter           = $mainframe->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.filter',           'filter',           1,           'int' );
		$filter_uploader  = $mainframe->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.filter_uploader',  'filter_uploader',  0,           'int' );
		$filter_url       = $mainframe->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.filter_url',       'filter_url',       '',          'word' );
		$filter_secure    = $mainframe->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.filter_secure',    'filter_secure',    '',          'word' );
		$filter_ext       = $mainframe->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.filter_ext',       'filter_ext',       '',          'alnum' );
		$search           = $mainframe->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.search',           'search',           '',          'string' );
		$filter_item      = $mainframe->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.item_id',          'item_id',          0,           'int' );
		$itemid 	      	= $mainframe->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.itemid',           'itemid',           0,           'string' );
		$autoselect       = $mainframe->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.autoselect',       'autoselect',       0, 				  'int' );
		$autoassign       = $mainframe->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.autoassign',       'autoassign',       0, 				  'int' );
		
		$folder_mode			= $mainframe->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.folder_mode',      'folder_mode',      0, 				  'int' );
		$folder_param			= $mainframe->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.folder_param',     'folder_param',		 'dir',				'string' );
		$append_item			= $mainframe->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.append_item',      'append_item',      1, 				  'int' );
		$append_field			= $mainframe->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.append_field',     'append_field',     1, 				  'int' );
		$targetid					= $mainframe->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.targetid',    		 'targetid',     		 '', 				  'string' );
		$thumb_w					= $mainframe->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.thumb_w',    			 'thumb_w',     		 120, 				'int' );
		$thumb_h					= $mainframe->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.thumb_h',    			 'thumb_h',     		 90, 				  'int' );
		
		$search				= $db->getEscaped( trim(JString::strtolower( $search ) ) );
		$newfileid		= JRequest::getInt('newfileid');
		$newfilename	= base64_decode(JRequest::getVar('newfilename', ''));
		$delfilename	= base64_decode(JRequest::getVar('delfilename', ''));
		
		//add css and submenu to document
		$document->addStyleSheet( ($mainframe->isSite() ? 'administrator/' : '' ) . 'components/com_flexicontent/assets/css/flexicontentbackend.css');
		$document->addStyleSheet( ($mainframe->isSite() ? 'administrator/' : '' ) . 'templates/system/css/system.css');
		// include khepri stylesheet only if we are in frontend
		if ($mainframe->isSite()) {
			$document->addStyleSheet('administrator/templates/khepri/css/general.css');
			$searchcss = '.adminform #search {border:1px solid silver;font-size:10px;margin:0;padding:0;float:none;height:14px;width:250px;}';
			$document->addStyleDeclaration($searchcss);
		}
		//a trick to avoid loosing general style in modal window
		$css = 'body, td, th { font-size: 11px; }
		a.striketext {
			text-decoration: line-through;
			color:red;
			font-style:italic;
		}
		';
		$document->addStyleDeclaration($css);
		
		if (FLEXI_J16GE || FLEXI_ACCESS) {
			$permission = FlexicontentHelperPerm::getPerm();
			$CanFiles         = $permission->CanFiles;
			$CanUpload        = $permission->CanUpload;
			$CanViewAllFiles  = $permission->CanViewAllFiles;
		} else {
			$CanFiles         = 1;
			$CanUpload				= 1;
			$CanViewAllFiles	= 1;
		}
		
		// ***********************
		// Get data from the model
		// ***********************
		$model		= & $this->getModel();
		if ($folder_mode) {
			$rows = & $model->getFilesFromPath($itemid, $fieldid, $append_item, $append_field, $folder_param);
			$img_folder = & $model->getFieldFolderPath($itemid, $fieldid, $append_item, $append_field, $folder_param);
			$img_path = str_replace('\\', '/', $img_folder . DS . $newfilename);
			$thumb = JURI::root() . 'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=' . $img_path . '&w='.$thumb_w.'&h='.$thumb_h;
		} else {
			$rows = & $this->get( 'Data');
			$img_folder = '';
		}
		$upload_path_var = 'fc_upload_path_'.$fieldid.'_'.$itemid;
		$mainframe->setUserState( $upload_path_var, $img_folder );
		//echo $upload_path_var . "<br>";
		//echo $mainframe->getUserState( $upload_path_var, 'noset' );
		
		$pageNav	= & $this->get('Pagination');
		//$users = & $this->get('Users');
		
		// Get item using at least one file (-of- the currently listed files)
		$items_single	= & $model->getItemsSingleprop( array('file','minigallery') );
		$items_multi	= & $model->getItemsMultiprop ( $field_props=array('image'=>'originalname'), $value_props=array('image'=>'filename') );
		$items = array();
		foreach ($items_single as $item_id => $_item) $items[$item_id] = $_item;
		foreach ($items_multi  as $item_id => $_item) $items[$item_id] = $_item;
		ksort($items);
		
		
		$fname = $model->getFieldName($fieldid);
		$formfieldname = FLEXI_J16GE ? 'custom['.$fname.'][]' : $fname.'[]';
		
		//add js to document
		//$document->addScript( JURI::base().'components/com_flexicontent/assets/js/fileselement.js' );
		if ($folder_mode) {
			$js = "
			
			window.addEvent('domready', function() {

		    function closest (obj, el) {
		        var find = obj.getElement(el);
		        var self = obj;
		        
		        while (self && !find) {
		            self = self.getParent();
		            find = self ? self.getElement(el) : null;
		        }
		        return find;
		    }

				var delfilename = '".$delfilename."';
				var remove_existing_files_from_list = 0;
				var remove_new_files_from_list = 0;
				original_objs = $(window.parent.document.body).getElement('#sortables_".$fieldid."').getElements('.originalname');
				existing_objs = $(window.parent.document.body).getElement('#sortables_".$fieldid."').getElements('.existingname');
				
				var imgobjs = Array();
				for(i=0,n=original_objs.length; i<n; i++)  {
					if (original_objs[i].value) imgobjs.push(original_objs[i].value);
					if ( delfilename!='' && original_objs[i].value == delfilename)
					{
						window.parent.deleteField".$fieldid."( original_objs[i].getParent() );
						remove_existing_files_from_list = 1;
					}
				}
				for(i=0,n=existing_objs.length; i<n; i++) {
					if ( existing_objs[i].value) imgobjs.push(existing_objs[i].value);
					if ( delfilename!='' && existing_objs[i].value == delfilename)
					{
						window.parent.deleteField".$fieldid."(
							(MooTools.version>='1.2.4')  ?  existing_objs[i].getParent('.img_value_props')  :  closest (existing_objs[i] , '.img_value_props')
						);
						remove_new_files_from_list = 1;
					}
				}
				
				if ( remove_existing_files_from_list || remove_new_files_from_list ) {
					mssg = '".JText::_('FLEXI_DELETE_FILE_IN_LIST_WINDOW_MUST_CLOSE')."';
					mssg = mssg + '\\n' + (remove_existing_files_from_list ? '".JText::_('FLEXI_EXISTING_FILE_REMOVED_SAVE_RECOMMENEDED')."' : '');
					alert( mssg );
					(MooTools.version>='1.2.4') ?  window.parent.SqueezeBox.close()  :  window.parent.document.getElementById('sbox-window').close();
				}
				
				for(i=0,n=imgobjs.length; i<n; i++) {
					var rows = $(document.body).getElements('a[rel='+ imgobjs[i] +']');
					rows.addClass('striketext');
					
					//if( (typeof rows) != 'undefined' && rows != null) {
						//alert(rows[0]);
						//row.className = 'striketext';
					//}
				}
				"
				.($autoassign && $newfilename ? "window.parent.qmAssignFile".$fieldid."('".$targetid."', '".$newfilename."', '".$thumb."');" : "")
				."
			});
			";
		} else {
			$js = "
			function qffileselementadd(obj, id, file) {
				obj.className = 'striketext';//work
				document.adminForm.file.value=id;
				window.parent.qfSelectFile".$fieldid."(id, file);
			}
			window.addEvent('domready', function() {
				fileobjs = window.parent.document.getElementsByName('{$formfieldname}');
				for(i=0,n=fileobjs.length; i<n; i++) {
					row = document.getElementById('file'+fileobjs[i].value);
					if( (typeof row) != 'undefined' && row != null) {
						row.className = 'striketext';
					}
				}
				"
				.(($autoselect && $newfileid) ? "qffileselementadd( document.getElementById('file".$newfileid."'), '".$newfileid."', '".$newfilename."');" : "")
				."
			});
			";
		}
		
		$document->addScriptDeclaration($js);
		if ($autoselect && $newfileid) {
			$app->enqueueMessage(JText::_( 'FLEXI_UPLOADED_FILE_WAS_SELECTED' ), 'message');
		}
		
		$files_selected = $model->getItemFiles($itemid);
		
		// search
		$lists 				= array();
		$lists['search'] 	= $search;
		
		//search filter
		$filters = array();
		$filters[] = JHTML::_('select.option', '1', JText::_( 'FLEXI_FILENAME' ) );
		$filters[] = JHTML::_('select.option', '2', JText::_( 'FLEXI_DISPLAY_NAME' ) );
		$lists['filter'] = JHTML::_('select.genericlist', $filters, 'filter', 'size="1" class="inputbox"', 'value', 'text', $filter );

		//build url/file filterlist
		$url 	= array();
		$url[] 	= JHTML::_('select.option',  '', '- '. JText::_( 'FLEXI_ALL_FILES' ) .' -' );
		$url[] 	= JHTML::_('select.option',  'F', JText::_( 'FLEXI_FILE' ) );
		$url[] 	= JHTML::_('select.option',  'U', JText::_( 'FLEXI_URL' ) );

		$lists['url'] = JHTML::_('select.genericlist', $url, 'filter_url', 'class="inputbox" size="1" onchange="submitform( );"', 'value', 'text', $filter_url );

		//item lists
		$items_list = array();
		$items_list[] = JHTML::_('select.option', '', '- '. JText::_( 'FLEXI_FILTER_BY_ITEM' ) .' -' );
		foreach($items as $item) {
			$items_list[] = JHTML::_('select.option', $item->id, JText::_( $item->title ) . ' (#' . $item->id . ')' );
		}
		$lists['item_id'] = JHTML::_('select.genericlist', $items_list, 'item_id', 'size="1" class="inputbox" onchange="submitform( );"', 'value', 'text', $filter_item );
		
		//build secure/media filterlist
		$secure 	= array();
		$secure[] 	= JHTML::_('select.option',  '', '- '. JText::_( 'FLEXI_ALL_DIRECTORIES' ) .' -' );
		$secure[] 	= JHTML::_('select.option',  'S', JText::_( 'FLEXI_SECURE_DIR' ) );
		$secure[] 	= JHTML::_('select.option',  'M', JText::_( 'FLEXI_MEDIA_DIR' ) );

		$lists['secure'] = JHTML::_('select.genericlist', $secure, 'filter_secure', 'class="inputbox" size="1" onchange="submitform( );"', 'value', 'text', $filter_secure );

		//build ext filterlist
		$lists['ext'] = flexicontent_html::buildfilesextlist('filter_ext', 'class="inputbox" size="1" onchange="submitform( );"', $filter_ext);

		//build uploader filterlist
		$lists['uploader'] = flexicontent_html::builduploaderlist('filter_uploader', 'class="inputbox" size="1" onchange="submitform( );"', $filter_uploader);

		// table ordering
		$lists['order_Dir']	= $filter_order_Dir;
		$lists['order']			= $filter_order;

		// removed files
		$filelist = JRequest::getString('files');
		$file = JRequest::getInt('file');

		$filelist = explode(',', $filelist);
		$files = array();
		foreach ($filelist as $fileid) {
			
			if ($fileid && $fileid != $file) {
			$files[] = (int)$fileid;
			}
			
		}

		$files = implode(',', $files);
		if (strlen($files) > 0) {
			$files .= ',';
		}
		$files .= $file;

		//assign data to template
		
		$this->assignRef('session'    , JFactory::getSession());
		$this->assignRef('params'     , $params);
		$this->assignRef('client'     , $client);
		$this->assignRef('pane'       , $pane);
		$this->assignRef('lists'      , $lists);
		$this->assignRef('rows'       , $rows);
		$this->assignRef('folder_mode', $folder_mode);
		$this->assignRef('img_folder' , $img_folder);
		$this->assignRef('thumb_w'    , $thumb_w);
		$this->assignRef('thumb_h'    , $thumb_h);
		
		$this->assignRef('pageNav'    , $pageNav);
		$this->assignRef('files' 			, $files);
		$this->assignRef('fieldid' 		, $fieldid);
		$this->assignRef('itemid' 		, $itemid);
		$this->assignRef('targetid' 	, $targetid);
		$this->assignRef('CanFiles'        , $CanFiles);
		$this->assignRef('CanUpload'       , $CanUpload);
		$this->assignRef('CanViewAllFiles' , $CanViewAllFiles);
		$this->assignRef('files_selected'  , $files_selected);
		
		parent::display($tpl);
	}
}
?>
