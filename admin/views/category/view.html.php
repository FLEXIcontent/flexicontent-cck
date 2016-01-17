<?php
/**
 * @version 1.5 stable $Id: view.html.php 1823 2013-12-23 03:27:29Z ggppdk $
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

jimport('legacy.view.legacy');

/**
 * View class for the FLEXIcontent category screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewCategory extends JViewLegacy
{
	function display($tpl = null)
	{
		global $globalcats;
		$app      = JFactory::getApplication();
		$user     = JFactory::getUser();
		$document = JFactory::getDocument();
		
		JFactory::getLanguage()->load('com_categories', JPATH_ADMINISTRATOR, 'en-GB', true);
		JFactory::getLanguage()->load('com_categories', JPATH_ADMINISTRATOR, null, true);
		
		// ***********************************************************
		// Get category data, and check if item is already checked out
		// ***********************************************************
		
		// Get data from the model
		$model		= $this->getModel();
		if (FLEXI_J16GE) {
			$row  = $this->get( 'Item' );
			$form = $this->get( 'Form' );
		} else {
			$row  = $this->get( 'Category' );
		}
		$catparams = new JRegistry($row->params);
		
		$cid    =	$row->id;
		$isnew  = !$cid;
		
		// Check category is checked out by different editor / administrator
		if ( !$isnew && $model->isCheckedOut( $user->get('id') ) ) {
			JError::raiseWarning( 'SOME_ERROR_CODE', $row->title.' '.JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ));
			$app->redirect( 'index.php?option=com_flexicontent&view=categories' );
		}
		
		
		// ***************************************************************************
		// Currently access checking for category add/edit form , it is done here, for
		// most other views we force going though the controller and checking it there
		// ***************************************************************************
		
		// *********************************************************************************************
		// Global Permssions checking (needed because this view can be called without a controller task)
		// *********************************************************************************************
		
		// Get global permissions
		$perms = FlexicontentHelperPerm::getPerm();  // handles super admins correctly
		
		// Check no access to categories management (Global permission)
		if ( !$perms->CanCats ) {
			$app->redirect('index.php?option=com_flexicontent', JText::_( 'FLEXI_NO_ACCESS' ));
		}
		
		// Check no privilege to create new categories (Global permission)
		if ( $isnew && !$perms->CanAddCats ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_NO_ACCESS_CREATE' ) );
			$app->redirect( 'index.php?option=com_flexicontent' );
		}
		
		
		// ************************************************************************************
		// Record Permssions (needed because this view can be called without a controller task)
		// ************************************************************************************
				
		// Get edit privilege for current category
		if (!$isnew) {
			if (FLEXI_J16GE) {
				$isOwner = $row->get('created_by') == $user->id;
				$rights = FlexicontentHelperPerm::checkAllItemAccess($user->id, 'category', $cid);
				$canedit_cat   = in_array('edit', $rights) || (in_array('edit.own', $rights) && $isOwner);
			} else if (FLEXI_ACCESS) {
				$rights = FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, 0,$row->id);
				$canedit_cat = ($user->gid < 25) ? (in_array('edit', $rights) || in_array('editown', $rights)) : 1;
			} else {
				$canedit_cat = true;
			}
		}
		
		// Get if we can create inside at least one (com_content) category
		if ( $user->authorise('core.create', 'com_flexicontent') ) {
			$cancreate_cat = true;
		} else {
			$usercats = FlexicontentHelperPerm::getAllowedCats($user, $actions_allowed = array('core.create')
				, $require_all = true, $check_published = true, $specific_catids = false, $find_first = true
			);
			$cancreate_cat  = count($usercats) > 0;
		}
		
		// Creating new category: Check if user can create inside any existing category
		if ( $isnew && !$cancreate_cat ) {
			$acc_msg = JText::_( 'FLEXI_NO_ACCESS_CREATE' ) ."<br/>". (FLEXI_J16GE ? JText::_( 'FLEXI_CANNOT_ADD_CATEGORY_REASON' ) : ""); 
			JError::raiseWarning( 403, $acc_msg);
			$app->redirect('index.php?option=com_flexicontent&view=categories');
		}
		
		// Editing existing category: Check if user can edit existing (current) category
		if ( !$isnew && !$canedit_cat ) {
			$acc_msg = JText::_( 'FLEXI_NO_ACCESS_EDIT' ) ."<br/>". JText::_( 'FLEXI_CANNOT_EDIT_CATEGORY_REASON' );
			JError::raiseWarning( 403, $acc_msg);
			$app->redirect( 'index.php?option=com_flexicontent&view=categories' );
		}
		
		
		// **************************************************
		// Include needed files and add needed js / css files
		// **************************************************
		
		// Add css to document
		$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VHASH);
		$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH);
		
		// Add JS frameworks
		flexicontent_html::loadFramework('select2');
		
		// Add js function to overload the joomla submitform validation
		JHTML::_('behavior.formvalidation');  // load default validation JS to make sure it is overriden
		$document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/admin.js', FLEXI_VHASH);
		$document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/validate.js', FLEXI_VHASH);
		
		
		// ********************
		// Initialise variables
		// ********************
		
		$editor_name = $user->getParam('editor', $app->getCfg('editor'));
		$editor  = JFactory::getEditor($editor_name);
		$cparams = JComponentHelper::getParams('com_flexicontent');
		$categories = $globalcats;
		
		$bar     = JToolBar::getInstance('toolbar');
		$tip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
		
		
		// ******************
		// Create the toolbar
		// ******************
		
		// Create Toolbar title and add the preview button
		if ( !$isnew ) {
			JToolBarHelper::title( JText::_( 'FLEXI_EDIT_CATEGORY' ), 'fc_categoryedit' );
		} else {
			JToolBarHelper::title( JText::_( 'FLEXI_NEW_CATEGORY' ), 'fc_categoryadd' );
		}
		
		// Add apply and save buttons
		JToolBarHelper::apply('category.apply', 'FLEXI_APPLY');
		/*if ( !$isnew ) flexicontent_html::addToolBarButton(
			'FLEXI_FAST_APPLY', $btn_name='apply_ajax', $full_js="Joomla.submitbutton('category.apply_ajax')", $msg_alert='', $msg_confirm='',
			$btn_task='category.apply_ajax', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false, $btn_class="", $btn_icon="icon-loop");*/
		JToolBarHelper::save('category.save');
		
		// Add a save and new button, if user can create inside at least one (com_content) category
		if ( $cancreate_cat ) {
			JToolBarHelper::save2new('category.save2new');
		}
		
		// Add a save as copy button, if editing an existing category (J2.5 only)
		if (!$isnew && $cancreate_cat) {
			JToolBarHelper::save2copy('category.save2copy');
		}
		
		// Add a cancel or close button
		if ($isnew)  {
			JToolBarHelper::cancel('category.cancel');
		} else {
			JToolBarHelper::cancel('category.cancel', 'JTOOLBAR_CLOSE');
		}
		
		
		// ******************
		// Add preview button
		// ******************
		
		if ( !$isnew ) {
			JToolBarHelper::divider();
			
			$autologin		= ''; //$cparams->get('autoflogin', 1) ? '&fcu='.$user->username . '&fcp='.$user->password : '';
			$previewlink 	= JRoute::_(JURI::root(). FlexicontentHelperRoute::getCategoryRoute($categories[$cid]->slug)) . $autologin;
			// Add a preview button
			$bar->appendButton( 'Custom', '<a class="preview btn btn-small btn-info spaced-btn" href="'.$previewlink.'" target="_blank" ><span title="'.JText::_('Preview').'" class="icon-screen"></span>'.JText::_('Preview').'</a>', 'preview' );
		}
					
		// ************************
		// Add modal layout editing
		// ************************
		
		if (!$isnew && $perms->CanTemplates)
		{
			$inheritcid_comp = $cparams->get('inheritcid', -1);
			$inheritcid = $catparams->get('inheritcid', '');
			$inherit_parent = $inheritcid==='-1' || ($inheritcid==='' && $inheritcid_comp);
			

			if (!$inherit_parent || $row->parent_id==='1')
				$row_clayout = $catparams->get('clayout', $cparams->get('clayout', 'blog'));
			else {
				$row_clayout = $catparams->get('clayout', '');

				if (!$row_clayout)
				{
					$_ancestors = $this->getModel()->getParentParams($row->id);  // This is ordered by level ASC
					$row_clayout = $cparams->get('clayout', 'blog');
					$cats_params = array();
					foreach($_ancestors as $_cid => $_cat)
					{
						$cats_params = new JRegistry($_cat->params);
						$row_clayout = $cats_params->get('clayout', '') ? $cats_params->get('clayout', '') : $row_clayout;
					}
				}
			}
			
			flexicontent_html::addToolBarButton(
				'FLEXI_EDIT_LAYOUT', $btn_name='apply_ajax', $full_js="var url = jQuery(this).attr('data-href'); fc_showDialog(url, 'fc_modal_popup_container'); return false;", $msg_alert='', $msg_confirm='',
				$btn_task='items.apply_ajax', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false, $btn_class="btn-info".$tip_class, $btn_icon="icon-pencil",
				'data-placement="bottom" data-href="index.php?option=com_flexicontent&amp;view=template&amp;type=category&amp;tmpl=component&amp;ismodal=1&amp;folder='.$row_clayout.
				'" title="Edit the display layout of this category. <br/><br/>Note: this layout maybe assigned to other categories, thus changing it will effect them too"'
			);
		}
		
		
		// *******************************************
		// Prepare data to pass to the form's template
		// *******************************************
		
		if ( !FLEXI_J16GE )
		{
			// Encode (UTF-8 charset) HTML entities form data so that they can be set as form field values
			JFilterOutput::objectHTMLSafe( $row, ENT_QUOTES, $exclude_keys = 'description' );
			
			// Create the form
			$form = new JParameter($row->params, JPATH_COMPONENT.DS.'models'.DS.'category.xml');
			//$form->loadINI($row->attribs);
			
			//echo "<pre>"; print_r($form->_xml['templates']->_children[0]);  echo "<pre>"; print_r($form->_xml['templates']->param[0]); exit;
			foreach($form->_xml['templates']->_children as $i => $child) {
				if ( isset($child->_attributes['enableparam']) && !$cparams->get($child->_attributes['enableparam']) ) {
					unset($form->_xml['templates']->_children[$i]);
					unset($form->_xml['templates']->param[$i]);
				}
			}
			
			foreach($form->_xml['special']->_children as $i => $child) {
				if ( isset($child->_attributes['enableparam']) && !$cparams->get($child->_attributes['enableparam']) ) {
					unset($form->_xml['special']->_children[$i]);
					unset($form->_xml['special']->param[$i]);
				}
			}
		}
		
		
		// **********************************************************************************
		// Get Templates and apply Template Parameters values into the form fields structures 
		// **********************************************************************************
		
		$themes		= flexicontent_tmpl::getTemplates();
		$tmpls		= $themes->category;
		foreach ($tmpls as $tmpl) {
			$jform = new JForm('com_flexicontent.template.category', array('control' => 'jform', 'load_data' => true));
			$jform->load($tmpl->params);
			$tmpl->params = $jform;
			// ... values applied at the template form file
		}
		
		//build selectlists
		$Lists = array();
		
		if ( !FLEXI_J16GE ) {
			$javascript = "onchange=\"javascript:if (document.forms[0].image.options[selectedIndex].value!='') {document.imagelib.src='../images/stories/' + document.forms[0].image.options[selectedIndex].value} else {document.imagelib.src='../images/blank.png'}\"";
			$Lists['imagelist']	= JHTML::_('list.images', 'image', $row->image, $javascript, '/images/stories/' );
			$Lists['access']		= JHTML::_('list.accesslevel', $row );
			
			// build granular access list
			if (FLEXI_ACCESS) {
				$Lists['access'] = FAccess::TabGmaccess( $row, 'category', 1, 1, 1, 1, 1, 1, 1, 1, 1 );
			}
		}
		
		$check_published = false;  $check_perms = true;  $actions_allowed=array('core.create');
		$fieldname = FLEXI_J16GE ? 'jform[parent_id]' : 'parent_id';
		$Lists['parent_id'] = flexicontent_cats::buildcatselect($categories, $fieldname, $row->parent_id, $top=1, 'class="use_select2_lib"',
			$check_published, $check_perms, $actions_allowed, $require_all=true, $skip_subtrees=array(), $disable_subtrees=array($row->id));
		
		$check_published = false;  $check_perms = true;  $actions_allowed=array('core.edit', 'core.edit.own');
		$fieldname = FLEXI_J16GE ? 'jform[copycid]' : 'copycid';
		$Lists['copycid']    = flexicontent_cats::buildcatselect($categories, $fieldname, '', $top=2, 'class="use_select2_lib"', $check_published, $check_perms, $actions_allowed, $require_all=false);
		
		$custom_options[''] = 'FLEXI_USE_GLOBAL';
		$custom_options['0'] = 'FLEXI_COMPONENT_ONLY';
		$custom_options['-1'] = 'FLEXI_PARENT_CAT_MULTI_LEVEL';
		
		$check_published = false;  $check_perms = true;  $actions_allowed=array('core.edit', 'core.edit.own');
		$fieldname = FLEXI_J16GE ? 'jform[special][inheritcid]' : 'params[inheritcid]';
		$Lists['inheritcid'] = flexicontent_cats::buildcatselect($categories, $fieldname, $catparams->get('inheritcid', ''),$top=false, 'class="use_select2_lib"',
			$check_published, $check_perms, $actions_allowed, $require_all=false, $skip_subtrees=array(), $disable_subtrees=array(), $custom_options);
		
		
		// ************************
		// Assign variables to view
		// ************************
		
		$this->assignRef('document'	, $document);
		$this->assignRef('Lists'		, $Lists);
		$this->assignRef('row'			, $row);
		$this->assignRef('form'			, $form);
		$this->assignRef('perms'		, $perms);
		$this->assignRef('editor'		, $editor);
		$this->assignRef('tmpls'		, $tmpls);
		$this->assignRef('cparams'	, $cparams);

		parent::display($tpl);
	}
}
?>