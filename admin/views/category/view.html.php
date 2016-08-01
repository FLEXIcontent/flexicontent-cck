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
		$row  = $this->get( 'Item' );
		$form = $this->get( 'Form' );
		
		// Get category parameters and inherited parameters
		$catparams = new JRegistry($row->params);
		$iparams   = $this->get( 'InheritedParams' );
		
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
		
		// Check no privilege to create new category under any category
		if ( $isnew && (!$perms->CanCats || !FlexicontentHelperPerm::getPermAny('core.create')) ) {
			JError::raiseWarning( 403, JText::_( 'FLEXI_NO_ACCESS_CREATE' ) );
			$app->redirect( 'index.php?option=com_flexicontent' );
		}


		// ************************************************************************************
		// Record Permissions (needed because this view can be called without a controller task)
		// ************************************************************************************
				
		// Get edit privilege for current category
		if (!$isnew) {
			$isOwner = $row->get('created_by') == $user->id;
			$rights = FlexicontentHelperPerm::checkAllItemAccess($user->id, 'category', $cid);
			$canedit_cat   = in_array('edit', $rights) || (in_array('edit.own', $rights) && $isOwner);
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
		flexicontent_html::loadFramework('flexi-lib-form');
		
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
		if ( !$isnew ) flexicontent_html::addToolBarButton(
			'FLEXI_FAST_APPLY', $btn_name='apply_ajax', $full_js="Joomla.submitbutton('category.apply_ajax')", $msg_alert='', $msg_confirm='',
			$btn_task='category.apply_ajax', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false, $btn_class="", $btn_icon="icon-loop");
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
			$bar->appendButton( 'Custom', '<a class="preview btn btn-small btn-info spaced-btn" href="'.$previewlink.'" target="_blank" ><span title="'.JText::_('FLEXI_PREVIEW').'" class="icon-screen"></span>'.JText::_('FLEXI_PREVIEW').'</a>', 'preview' );
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
			
			$edit_layout = JText::_('FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS', true);
			flexicontent_html::addToolBarButton(
				'FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS', $btn_name='edit_layout_params', $full_js="var url = jQuery(this).attr('data-href'); fc_showDialog(url, 'fc_modal_popup_container', 0, 0, 0, 0, {title:'".$edit_layout."'}); return false;", $msg_alert='', $msg_confirm='',
				$btn_task='', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false, $btn_class="btn-info".$tip_class, $btn_icon="icon-pencil",
				'data-placement="bottom" data-href="index.php?option=com_flexicontent&amp;view=template&amp;type=category&amp;tmpl=component&amp;ismodal=1&amp;folder='.$row_clayout.
				'" title="Edit the display layout of this category. <br/><br/>Note: this layout maybe assigned to other categories, thus changing it will effect them too"'
			);
		}


		// **********************************************************************************************************
		// Get Layouts, load language of current selected template and apply Layout parameters values into the fields
		// **********************************************************************************************************

		// Load language file of currently selected template
		$_clayout = $catparams->get('clayout');
		if ($_clayout) FLEXIUtilities::loadTemplateLanguageFile( $_clayout );

		// Get the category layouts, checking template of current layout for modifications
		$themes		= flexicontent_tmpl::getTemplates($_clayout);
		$tmpls		= $themes->category;

		// Create JForm for the layout and apply Layout parameters values into the fields
		foreach ($tmpls as $tmpl)
		{
			if ($tmpl->name != $_clayout) continue;
			
			$jform = new JForm('com_flexicontent.template.category', array('control' => 'jform', 'load_data' => true));
			$jform->load($tmpl->params);
			$tmpl->params = $jform;
			foreach ($tmpl->params->getGroup('attribs') as $field)
			{
				$fieldname = $field->fieldname;
				$value = $catparams->get($fieldname);
				if (strlen($value)) $tmpl->params->setValue($fieldname, 'attribs', $value);
			}
		}

		//build selectlists
		$Lists = array();

		$check_published = false;  $check_perms = true;  $actions_allowed=array('core.create');
		$fieldname = 'jform[parent_id]';
		$Lists['parent_id'] = flexicontent_cats::buildcatselect($categories, $fieldname, $row->parent_id, $top=1, 'class="use_select2_lib"',
			$check_published, $check_perms, $actions_allowed, $require_all=true, $skip_subtrees=array(), $disable_subtrees=array($row->id));
		
		$check_published = false;  $check_perms = true;  $actions_allowed=array('core.edit', 'core.edit.own');
		$fieldname = 'jform[copycid]';
		$Lists['copycid']    = flexicontent_cats::buildcatselect($categories, $fieldname, '', $top=2, 'class="use_select2_lib"', $check_published, $check_perms, $actions_allowed, $require_all=false);
		
		$custom_options[''] = 'FLEXI_USE_GLOBAL';
		$custom_options['0'] = 'FLEXI_COMPONENT_ONLY';
		$custom_options['-1'] = 'FLEXI_PARENT_CAT_MULTI_LEVEL';
		
		$check_published = false;  $check_perms = true;  $actions_allowed=array('core.edit', 'core.edit.own');
		$fieldname = 'jform[special][inheritcid]';
		$Lists['inheritcid'] = flexicontent_cats::buildcatselect($categories, $fieldname, $catparams->get('inheritcid', ''),$top=false, 'class="use_select2_lib"',
			$check_published, $check_perms, $actions_allowed, $require_all=false, $skip_subtrees=array(), $disable_subtrees=array(), $custom_options);


		// ************************
		// Assign variables to view
		// ************************
		
		$this->document = $document;
		$this->Lists    = $Lists;
		$this->row      = $row;
		$this->form     = $form;
		$this->perms    = $perms;
		$this->editor   = $editor;
		$this->tmpls    = $tmpls;
		$this->cparams  = $cparams;
		$this->iparams  = $iparams;

		parent::display($tpl);
	}
	
	
	
	/**
	 * Method to diplay field showing inherited value
	 *
	 * @access	private
	 * @return	void
	 * @since	1.5
	 */
	function getInheritedFieldDisplay($field, $params)
	{
		$_v = $params->get($field->fieldname);
		
		if ($_v==='' || $_v===null)
			return $field->input;
		else if ($field->getAttribute('type')=='radio' || ($field->getAttribute('type')=='multilist' && $field->getAttribute('subtype')=='radio'))
		{
			return str_replace(
				'value="'.$_v.'"',
				'value="'.$_v.'" class="fc-inherited-value" ',
				$field->input);
		}
		else if ($field->getAttribute('type')=='fccheckbox' && is_array($_v))
		{
			$_input = $field->input;
			foreach ($_v as $v) {
				$_input = str_replace(
					'value="'.$v.'"',
					'value="'.$v.'" class="fc-inherited-value" ',
					$_input);
			}
			return $_input;
		}
		else if ($field->getAttribute('type')=='text')
		{
			return str_replace(
				'<input ',
				'<input placeholder="'.preg_replace('/[\n\r]/', ' ', $_v).'" ',
				$field->input);
		}
		else if ($field->getAttribute('type')=='textarea')
		{
			return str_replace('<textarea ', '<textarea placeholder="'.preg_replace('/[\n\r]/', ' ', $_v).'" ', $field->input);
		}
		else if ( method_exists($field, 'setInherited') )
		{
			$field->setInherited($_v);
			return $field->input;
		}
		else
			return $field->input;
	}
}
?>