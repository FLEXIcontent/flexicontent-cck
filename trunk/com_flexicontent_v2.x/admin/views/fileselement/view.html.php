<?php
/**
 * @version 1.5 stable $Id: view.html.php 1925 2014-06-25 01:50:14Z ggppdk $
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
		
		flexicontent_html::loadJQuery();
		flexicontent_html::loadFramework('select2');
		JHTML::_('behavior.tooltip');
		// Load the form validation behavior
		JHTML::_('behavior.formvalidation');
		
		//initialise variables
		$app      = JFactory::getApplication();
		$option   = JRequest::getVar('option');
		$document = JFactory::getDocument();
		$db       = JFactory::getDBO();
		$user     = JFactory::getUser();
		$params   = JComponentHelper::getParams('com_flexicontent');
		//$authorparams = flexicontent_db::getUserConfig($user->id);
		$langs = FLEXIUtilities::getLanguages('code');
		
		$fieldid	= JRequest::getVar( 'field', null, 'request', 'int' );
		$client		= $app->isAdmin() ? '../' : '';
		
		//get vars
		$filter_order     = $app->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.filter_order',     'filter_order',    'f.filename', 'cmd' );
		$filter_order_Dir = $app->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.filter_order_Dir', 'filter_order_Dir', '',          'word' );
		$filter           = $app->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.filter',           'filter',           1,           'int' );
		$filter_lang			= $app->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.filter_lang',      'filter_lang',      '',          'string' );
		$filter_uploader  = $app->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.filter_uploader',  'filter_uploader',  0,           'int' );
		$filter_url       = $app->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.filter_url',       'filter_url',       '',          'word' );
		$filter_secure    = $app->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.filter_secure',    'filter_secure',    '',          'word' );
		$filter_ext       = $app->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.filter_ext',       'filter_ext',       '',          'alnum' );
		$search           = $app->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.search',           'search',           '',          'string' );
		$filter_item      = $app->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.item_id',          'item_id',          '',           'int' );
		$u_item_id 	      = $app->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.u_item_id',        'u_item_id',        0,           'string' );
		$autoselect       = $app->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.autoselect',       'autoselect',       0, 				  'int' );
		$autoassign       = $app->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.autoassign',       'autoassign',       0, 				  'int' );
		
		$folder_mode			= $app->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.folder_mode',      'folder_mode',      0, 				  'int' );
		$folder_param			= $app->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.folder_param',     'folder_param',		 'dir',				'string' );
		$append_item			= $app->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.append_item',      'append_item',      1, 				  'int' );
		$append_field			= $app->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.append_field',     'append_field',     1, 				  'int' );
		$targetid					= $app->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.targetid',    		 'targetid',     		 '', 				  'string' );
		$thumb_w					= $app->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.thumb_w',    			 'thumb_w',     		 120, 				'int' );
		$thumb_h					= $app->getUserStateFromRequest( $option.'.fileselement'.$fieldid.'.thumb_h',    			 'thumb_h',     		 90, 				  'int' );
		
		$search				= FLEXI_J16GE ? $db->escape( trim(JString::strtolower( $search ) ) ) : $db->getEscaped( trim(JString::strtolower( $search ) ) );
		$newfileid		= JRequest::getInt('newfileid');
		$newfilename	= base64_decode(JRequest::getVar('newfilename', ''));
		$delfilename	= base64_decode(JRequest::getVar('delfilename', ''));
		
		//add css and submenu to document
		if ($app->isSite()) {
			$document->addStyleSheet( JURI::base().'components/com_flexicontent/assets/css/flexicontent.css' );
			$document->addStyleSheet( JURI::base().'components/com_flexicontent/assets/css/flexi_shared.css' );  // NOTE: this is imported by main Frontend CSS file
		} else {
			$document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/flexicontentbackend.css');
		}
		if      (FLEXI_J30GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j3x.css');
		else if (FLEXI_J16GE) $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j25.css');
		else                  $document->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/j15.css');
		$document->addStyleSheet( JURI::root() . 'administrator/templates/system/css/system.css');
		
		// include backend CSS template CSS file , access to backend folder may not be allowed but ...
		if ($app->isSite()) {
			$template = !FLEXI_J16GE ? 'khepri' : (FLEXI_J30GE ? 'hathor' : 'bluestork');
			$document->addStyleSheet(JURI::root().'administrator/templates/'.$template.(FLEXI_J16GE ? '/css/template.css': '/css/general.css'));
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
		
		// Get User's Global Permissions
		$perms = FlexicontentHelperPerm::getPerm();
		
		
		// ***********************
		// Get data from the model
		// ***********************
		$model   = $this->getModel();
		if ( !$folder_mode ) {
			$rows  = $this->get('Data');
			$img_folder = '';
		} else {
			$rows = $model->getFilesFromPath($u_item_id, $fieldid, $append_item, $append_field, $folder_param);
			$img_folder = $model->getFieldFolderPath($u_item_id, $fieldid, $append_item, $append_field, $folder_param);
			$img_path = str_replace('\\', '/', $img_folder . DS . $newfilename);
			$thumb = JURI::root() . 'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=' . $img_path . '&w='.$thumb_w.'&h='.$thumb_h;
		}
		$upload_path_var = 'fc_upload_path_'.$fieldid.'_'.$u_item_id;
		$app->setUserState( $upload_path_var, $img_folder );
		//echo $upload_path_var . "<br>";
		//echo $app->getUserState( $upload_path_var, 'noset' );
		
		$pagination = $this->get('Pagination');
		//$users = $this->get('Users');
		
		// Get item using at least one file (-of- the currently listed files)
		/*$items_single	= $model->getItemsSingleprop( array('file','minigallery') );
		$items_multi	= $model->getItemsMultiprop ( $field_props=array('image'=>'originalname'), $value_props=array('image'=>'filename') );
		$items = array();
		foreach ($items_single as $item_id => $_item) $items[$item_id] = $_item;
		foreach ($items_multi  as $item_id => $_item) $items[$item_id] = $_item;
		ksort($items);*/
		
		$fname = $model->getFieldName($fieldid);
		$files_selected = $model->getItemFiles($u_item_id);
		$formfieldname = FLEXI_J16GE ? 'custom['.$fname.'][]' : $fname.'[]';
		
		//add js to document
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
				original_objs = $(window.parent.document.body).getElement('#container_fcfield_".$fieldid."').getElements('.originalname');
				existing_objs = $(window.parent.document.body).getElement('#container_fcfield_".$fieldid."').getElements('.existingname');
				
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
					mssg = mssg + '\\n' + (remove_existing_files_from_list ? '".JText::_('FLEXI_EXISTING_FILE_REMOVED_SAVE_RECOMMENEDED',true)."' : '');
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
				var result = window.parent.qfSelectFile".$fieldid."(id, file);
				if ((typeof result) != 'undefined' && result == 'cancel') return;
				obj.className = 'striketext';
				document.adminForm.file.value=id;
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
		
		
		/*****************
		 ** BUILD LISTS **
		 *****************/
		
		$lists 				= array();
		
		// ** FILE UPLOAD FORM **
		
		// Build languages list
		//$allowed_langs = !$authorparams ? null : $authorparams->get('langs_allowed',null);
		//$allowed_langs = !$allowed_langs ? null : FLEXIUtilities::paramToArray($allowed_langs);
		$display_file_lang_as = $params->get('display_file_lang_as', 3);
		
		$allowed_langs = null;
		if (FLEXI_FISH || FLEXI_J16GE) {
			$lists['file-lang'] = flexicontent_html::buildlanguageslist('file-lang', '', '*', $display_file_lang_as, $allowed_langs, $published_only=false);
		} else {
			$lists['file-lang'] = flexicontent_html::getSiteDefaultLang() . '<input type="hidden" name="file-lang" value="'.flexicontent_html::getSiteDefaultLang().'" />';
		}
		
		
		/*************
		 ** FILTERS **
		 *************/
		
		// language filter
		$lists['language'] = flexicontent_html::buildlanguageslist('filter_lang', 'class="use_select2_lib" onchange="submitform();" size="1" ', $filter_lang, 2);
		
		// search
		$lists['search'] 	= $search;
		
		//search filter
		$filters = array();
		$filters[] = JHTML::_('select.option', '1', JText::_( 'FLEXI_FILENAME' ) );
		$filters[] = JHTML::_('select.option', '2', JText::_( 'FLEXI_FILE_TITLE' ) );
		$lists['filter'] = JHTML::_('select.genericlist', $filters, 'filter', 'size="1" class="use_select2_lib"', 'value', 'text', $filter );

		//build url/file filterlist
		$url 	= array();
		$url[] 	= JHTML::_('select.option',  '', '- '. JText::_( 'FLEXI_ALL_FILES' ) .' -' );
		$url[] 	= JHTML::_('select.option',  'F', JText::_( 'FLEXI_FILE' ) );
		$url[] 	= JHTML::_('select.option',  'U', JText::_( 'FLEXI_URL' ) );

		$lists['url'] = JHTML::_('select.genericlist', $url, 'filter_url', 'class="use_select2_lib" size="1" onchange="submitform( );"', 'value', 'text', $filter_url );

		//item lists
		/*$items_list = array();
		$items_list[] = JHTML::_('select.option', '', '- '. JText::_( 'FLEXI_FILTER_BY_ITEM' ) .' -' );
		foreach($items as $item) {
			$items_list[] = JHTML::_('select.option', $item->id, JText::_( $item->title ) . ' (#' . $item->id . ')' );
		}
		$lists['item_id'] = JHTML::_('select.genericlist', $items_list, 'item_id', 'size="1" class="use_select2_lib" onchange="submitform( );"', 'value', 'text', $filter_item );*/
		$lists['item_id'] = '<input type="text" name="item_id" size="1" class="inputbox" onchange="submitform( );" value="'.$filter_item.'" />';
		
		//build secure/media filterlist
		$secure 	= array();
		$secure[] 	= JHTML::_('select.option',  '', '- '. JText::_( 'FLEXI_ALL_DIRECTORIES' ) .' -' );
		$secure[] 	= JHTML::_('select.option',  'S', JText::_( 'FLEXI_SECURE_DIR' ) );
		$secure[] 	= JHTML::_('select.option',  'M', JText::_( 'FLEXI_MEDIA_DIR' ) );

		$lists['secure'] = JHTML::_('select.genericlist', $secure, 'filter_secure', 'class="use_select2_lib" size="1" onchange="submitform( );"', 'value', 'text', $filter_secure );

		//build ext filterlist
		$lists['ext'] = flexicontent_html::buildfilesextlist('filter_ext', 'class="use_select2_lib" size="1" onchange="submitform( );"', $filter_ext);

		//build uploader filterlist
		$lists['uploader'] = flexicontent_html::builduploaderlist('filter_uploader', 'class="use_select2_lib" size="1" onchange="submitform( );"', $filter_uploader);

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
		$this->assignRef('params'     , $params);
		$this->assignRef('client'     , $client);
		//Load pane behavior
		if (!FLEXI_J16GE) {
			jimport('joomla.html.pane');
			$pane = JPane::getInstance('Tabs');
			$this->assignRef('pane'       , $pane);
		}
		$this->assignRef('lists'      , $lists);
		$this->assignRef('rows'       , $rows);
		$this->assignRef('folder_mode', $folder_mode);
		$this->assignRef('img_folder' , $img_folder);
		$this->assignRef('thumb_w'    , $thumb_w);
		$this->assignRef('thumb_h'    , $thumb_h);
		$this->assignRef('pagination' , $pagination);
		$this->assignRef('files' 			, $files);
		$this->assignRef('fieldid' 		, $fieldid);
		$this->assignRef('u_item_id' 	, $u_item_id);
		$this->assignRef('targetid' 	, $targetid);
		$this->assignRef('CanFiles'        , $perms->CanFiles);
		$this->assignRef('CanUpload'       , $perms->CanUpload);
		$this->assignRef('CanViewAllFiles' , $perms->CanViewAllFiles);
		$this->assignRef('files_selected'  , $files_selected);
		$this->assignRef('langs', $langs);
		
		parent::display($tpl);
	}
}
?>
