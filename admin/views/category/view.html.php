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
		// ***
		// *** Initialise variables
		// ***

		global $globalcats;
		$app      = JFactory::getApplication();
		$jinput   = $app->input;
		$document = JFactory::getDocument();
		$user     = JFactory::getUser();
		$cparams  = JComponentHelper::getParams('com_flexicontent');

		// Get url vars and some constants
		$option     = $jinput->get('option', '', 'cmd');
		$view       = $jinput->get('view', '', 'cmd');
		$controller = $jinput->get('controller', '', 'cmd');
		$tip_class = ' hasTooltip';

		// Load Joomla 'com_categories' language files
		JFactory::getLanguage()->load('com_categories', JPATH_ADMINISTRATOR, 'en-GB', true);
		JFactory::getLanguage()->load('com_categories', JPATH_ADMINISTRATOR, null, true);



		// ***
		// *** Get record data, and check if record is already checked out
		// ***
		
		// Get data from the model
		$model = $this->getModel();
		$row   = $this->get('Item');
		$form  = $this->get('Form');
		$isnew = ! $row->id;
		$manager_view = 'categories';
		$ctrl = 'category';
		$js = '';


		// Fail if an existing record is checked out by someone else
		if ($row->id && $model->isCheckedOut($user->get('id')))
		{
			JError::raiseWarning( 'SOME_ERROR_CODE', $row->title.' '.JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ));
			$app->redirect( 'index.php?option=com_flexicontent&view=' . $manager_view );
		}


		// Get category parameters NOTE: These are used for case that we DO NOT want inherited params
		$catparams = & $row->params;

		// Get category inherited parameters (Component + parent categories)
		$iparams = $this->get('InheritedParams');



		// ***
		// *** Currently access checking for category add/edit form , it is done here, for
		// *** most other views we force going though a controller task and checking it there
		// ***


		// ***
		// *** Global permissions checking
		// ***
		
		// Get global permissions
		$perms = FlexicontentHelperPerm::getPerm();
		
		// Check no access to categories management (Global permission)
		if ( !$perms->CanCats )
		{
			$app->redirect('index.php?option=com_flexicontent', JText::_( 'FLEXI_NO_ACCESS' ));
		}
		
		// Check no privilege to create new category under any category
		if ( $isnew && (!$perms->CanCats || !FlexicontentHelperPerm::getPermAny('core.create')) )
		{
			JError::raiseWarning( 403, JText::_( 'FLEXI_NO_ACCESS_CREATE' ) );
			$app->redirect( 'index.php?option=com_flexicontent' );
		}


		// ***
		// *** Record Permissions (needed because this view can be called without a controller task)
		// ***
				
		// Get edit privilege for current category
		if (!$isnew)
		{
			$isOwner = $row->get('created_by') == $user->id;
			$rights = FlexicontentHelperPerm::checkAllItemAccess($user->id, 'category', $row->id);
			$canedit_cat = in_array('edit', $rights) || (in_array('edit.own', $rights) && $isOwner);
		}
		
		// Get if we can create inside at least one (com_content) category
		if ( $user->authorise('core.create', 'com_flexicontent') )
		{
			$cancreate_cat = true;
		}
		else
		{
			$usercats = FlexicontentHelperPerm::getAllowedCats($user, $actions_allowed = array('core.create')
				, $require_all = true, $check_published = true, $specific_catids = false, $find_first = true
			);
			$cancreate_cat = count($usercats) > 0;
		}
		
		// Creating new category: Check if user can create inside any existing category
		if ( $isnew && !$cancreate_cat )
		{
			$acc_msg = JText::_( 'FLEXI_NO_ACCESS_CREATE' ) ."<br/>". (FLEXI_J16GE ? JText::_( 'FLEXI_CANNOT_ADD_CATEGORY_REASON' ) : ""); 
			JError::raiseWarning( 403, $acc_msg);
			$app->redirect('index.php?option=com_flexicontent&view=categories');
		}
		
		// Editing existing category: Check if user can edit existing (current) category
		if ( !$isnew && !$canedit_cat )
		{
			$acc_msg = JText::_( 'FLEXI_NO_ACCESS_EDIT' ) ."<br/>". JText::_( 'FLEXI_CANNOT_EDIT_CATEGORY_REASON' );
			JError::raiseWarning( 403, $acc_msg);
			$app->redirect( 'index.php?option=com_flexicontent&view=categories' );
		}


		// ***
		// *** Include needed files and add needed js / css files
		// ***
		
		// Add css to document
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', FLEXI_VHASH);
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH)
			: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x_rtl.css', FLEXI_VHASH);
		
		// Add JS frameworks
		flexicontent_html::loadFramework('select2');
		flexicontent_html::loadFramework('flexi-lib-form');
		
		// Add js function to overload the joomla submitform validation
		JHtml::_('behavior.formvalidation');  // load default validation JS to make sure it is overriden
		$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/admin.js', FLEXI_VHASH);
		$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/validate.js', FLEXI_VHASH);



		// ***
		// *** Create the toolbar
		// ***
		$toolbar = JToolbar::getInstance('toolbar');

		// Creation flag used to decide if adding save and new / save as copy buttons are allowed
		$cancreate = $cancreate_cat;
		
		// SET toolbar title
		!$isnew
			? JToolbarHelper::title( JText::_( 'FLEXI_EDIT_CATEGORY' ), 'fc_categoryedit' )   // Editing existing review
			: JToolbarHelper::title( JText::_( 'FLEXI_NEW_CATEGORY' ), 'fc_categoryadd' );    // Creating new review



		// ***
		// *** Apply buttons
		// ***

		// Apply button
		$btn_arr = array();

		// Add ajax apply only for existing records
		if ( !$isnew )
		{
			$btn_name = 'apply_ajax';
			$btn_task = $ctrl.'.apply_ajax';

			$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
				'FLEXI_APPLY', $btn_name, $full_js="Joomla.submitbutton('".$ctrl.".apply_ajax')", $msg_alert='', $msg_confirm='',
				$btn_task, $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false, $btn_class="".$tip_class, $btn_icon="icon-loop",
				'data-placement="bottom" title="'.JText::_('FLEXI_FAST_SAVE_INFO', true).'"', $auto_add = 0);
		}

		// Apply & Reload button   ***   (Apply Type, is a special case of new that has not loaded custom fieds yet, due to type not defined on initial form load)
		$btn_name = 'apply';
		$btn_task = $ctrl.'.apply';
		$btn_title = !$isnew ? 'FLEXI_APPLY_N_RELOAD' : 'FLEXI_ADD';

		//JToolbarHelper::apply($btn_task, $btn_title, false);

		$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
			$btn_title, $btn_name, $full_js="Joomla.submitbutton('".$btn_task."')", $msg_alert='', $msg_confirm='',
			$btn_task, $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false, $btn_class="".$tip_class, $btn_icon="icon-save",
			'data-placement="right" title=""', $auto_add = 0);

		flexicontent_html::addToolBarDropMenu($btn_arr, 'apply_btns_group');



		// ***
		// *** Save buttons
		// ***
		$btn_arr = array();

		$btn_name = 'save';
		$btn_task = $ctrl.'.save';

		//JToolbarHelper::save($btn_task);  //JToolbarHelper::custom( $btn_task, 'save.png', 'save.png', 'JSAVE', false );
		
		$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
			'JSAVE', $btn_name, $full_js="Joomla.submitbutton('".$ctrl.".save')", $msg_alert='', $msg_confirm='',
			$btn_task, $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false, $btn_class="".$tip_class, $btn_icon="icon-save",
			'data-placement="bottom" title=""', $auto_add = 0);


		// Add a save and new button, if user can create new records
		if ($cancreate)
		{
			$btn_name = 'save2new';
			$btn_task = $ctrl.'.save2new';

			//JToolbarHelper::save2new($btn_task);  //JToolbarHelper::custom( $btn_task, 'savenew.png', 'savenew.png', 'FLEXI_SAVE_AND_NEW', false );

			$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
				'FLEXI_SAVE_AND_NEW', $btn_name, $full_js="Joomla.submitbutton('".$ctrl.".save2new')", $msg_alert='', $msg_confirm='',
				$btn_task, $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false, $btn_class="".$tip_class, $btn_icon="icon-save-new",
				'data-placement="right" title="'.JText::_('FLEXI_SAVE_AND_NEW_INFO', true).'"', $auto_add = 0);

			// Also if an existing item, can save to a copy
			if (!$isnew)
			{
				$btn_name = 'save2copy';
				$btn_task = $ctrl.'.save2copy';

				//JToolbarHelper::save2copy($btn_task);  //JToolbarHelper::custom( $btn_task, 'save2copy.png', 'save2copy.png', 'FLEXI_SAVE_AS_COPY', false );

				$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
					'FLEXI_SAVE_AS_COPY', $btn_name, $full_js="Joomla.submitbutton('".$ctrl.".save2copy')", $msg_alert='', $msg_confirm='',
					$btn_task, $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false, $btn_class="".$tip_class, $btn_icon="icon-save-copy",
					'data-placement="right" title="'.JText::_('FLEXI_SAVE_AS_COPY_INFO', true).'"', $auto_add = 0);
			}
		}
		flexicontent_html::addToolBarDropMenu($btn_arr, 'save_btns_group');


		// Cancel button
		$isnew
			? JToolbarHelper::cancel($ctrl.'.cancel')
			: JToolbarHelper::cancel($ctrl.'.cancel', 'JTOOLBAR_CLOSE');


		// Preview button
		if (!$isnew)
		{
			// Create preview link (with xhtml to false ... we will do it manually) (at least for the ampersand)
			$record_link = str_replace('&', '&amp;', FlexicontentHelperRoute::getCategoryRoute($globalcats[$row->id]->slug));
			$previewlink = JRoute::_(JUri::root() . $record_link, $xhtml=false)
				;
			$toolbar->appendButton( 'Custom', '
				<button class="preview btn btn-small btn-fcaction btn-info spaced-btn" onClick="window.open(\''.$previewlink.'\');">
					<span title="'.JText::_('FLEXI_PREVIEW').'" class="icon-screen"></span>
					'.JText::_('FLEXI_PREVIEW').'
				</button>', 'preview'
			);
		}



		// ***
		// *** Add modal editing of template layout
		// ***
		
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
			
			$edit_layout = htmlspecialchars(JText::_('FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS'), ENT_QUOTES, 'UTF-8');
			flexicontent_html::addToolBarButton(
				'FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS', $btn_name='edit_layout_params',
				$full_js="var url = jQuery(this).attr('data-href'); fc_showDialog(url, 'fc_modal_popup_container', 0, 0, 0, 0, {title:'".$edit_layout."'}); return false;",
				$msg_alert='', $msg_confirm='',
				$btn_task='', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false, $btn_class="btn-info".$tip_class, $btn_icon="icon-pencil",
				'data-placement="bottom" data-href="index.php?option=com_flexicontent&amp;view=template&amp;type=category&amp;tmpl=component&amp;ismodal=1&amp;folder='.$row_clayout.
				'" title="Edit the display layout of this category. <br/><br/>Note: this layout maybe assigned to other categories, thus changing it will effect them too"'
			);
		}


		// ***
		// *** Get Layouts, load language of current selected template and apply Layout parameters values into the fields
		// ***

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
			
			$jform = new JForm('com_flexicontent.template.category', array('control' => 'jform', 'load_data' => false));
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

		// Build category selectors
		$check_published = false;  $check_perms = true;  $actions_allowed=array('core.create');
		$fieldname = 'jform[parent_id]';
		$Lists['parent_id'] = flexicontent_cats::buildcatselect($globalcats, $fieldname, $row->parent_id, $top=1, 'class="use_select2_lib"',
			$check_published, $check_perms, $actions_allowed, $require_all=true, $skip_subtrees=array(), $disable_subtrees=array($row->id));
		
		$check_published = false;  $check_perms = true;  $actions_allowed=array('core.edit', 'core.edit.own');
		$fieldname = 'jform[copycid]';
		$Lists['copycid']    = flexicontent_cats::buildcatselect($globalcats, $fieldname, '', $top=2, 'class="use_select2_lib"', $check_published, $check_perms, $actions_allowed, $require_all=false);
		
		$custom_options[''] = 'FLEXI_USE_GLOBAL';
		$custom_options['0'] = 'FLEXI_COMPONENT_ONLY';
		$custom_options['-1'] = 'FLEXI_PARENT_CAT_MULTI_LEVEL';
		
		$check_published = false;  $check_perms = true;  $actions_allowed=array('core.edit', 'core.edit.own');
		$fieldname = 'jform[special][inheritcid]';
		$Lists['inheritcid'] = flexicontent_cats::buildcatselect($globalcats, $fieldname, $catparams->get('inheritcid', ''),$top=false, 'class="use_select2_lib"',
			$check_published, $check_perms, $actions_allowed, $require_all=false, $skip_subtrees=array(), $disable_subtrees=array(), $custom_options);

		// check access level exists
		$level_name = flexicontent_html::userlevel(null, $row->access, null, null, null, $_createlist = false);
		if (empty($level_name))
		{
			JFactory::getApplication()->enqueueMessage(JText::sprintf('FLEXI_ABOUT_INVALID_ACCESS_LEVEL_PLEASE_SAVE_NEW', $row->access, 'Public'), 'warning');
			$document->addScriptDeclaration("jQuery(document).ready(function() { jQuery('#jform_access').val(1).trigger('change'); });");
		}
		


		// ***
		// *** Add inline js to head
		// ***
		if ($js)
		{
			$document->addScriptDeclaration('jQuery(document).ready(function(){'
				.$js.
			'});');
		}
		
		
		// Get editor instance
		$editor_name = $user->getParam('editor', $app->getCfg('editor'));
		$editor  = JFactory::getEditor($editor_name);


		// ***
		// *** Assign variables to view
		// ****
		
		$this->document = $document;
		$this->Lists    = $Lists;
		$this->row      = $row;
		$this->form     = $form;
		$this->perms    = $perms;
		$this->editor   = $editor;
		$this->tmpls    = $tmpls;
		$this->cparams  = $cparams;
		$this->iparams  = $iparams;
		$this->view     = $view;
		$this->controller = $controller;

		parent::display($tpl);
	}
	
	
	
	/**
	 * Method to diplay field showing inherited value
	 *
	 * @access	private
	 * @return	void
	 * @since	1.5
	 */
	function getInheritedFieldDisplay($field, $params, $_v = null)
	{
		$_v = $params ? $params->get($field->fieldname) : $_v;
		
		if ($_v==='' || $_v===null)
			return $field->input;
		else if ($field->getAttribute('type')=='radio' || $field->getAttribute('type')=='fcradio' || ($field->getAttribute('type')=='multilist' && $field->getAttribute('subtype')=='radio'))
		{
			$_v = htmlspecialchars( $_v, ENT_COMPAT, 'UTF-8' );
			return str_replace(
				'value="'.$_v.'"',
				'value="'.$_v.'" class="fc-inherited-value" ',
				$field->input);
		}
		else if ($field->getAttribute('type')=='fccheckbox' && is_array($_v))
		{
			$_input = $field->input;
			foreach ($_v as $v)
			{
				$v = htmlspecialchars( $v, ENT_COMPAT, 'UTF-8' );
				$_input = str_replace(
					'value="'.$v.'"',
					'value="'.$v.'" class="fc-inherited-value" ',
					$_input);
			}
			return $_input;
		}
		else if ($field->getAttribute('type')=='text')
		{
			$_v = htmlspecialchars( preg_replace('/[\n\r]/', ' ', $_v), ENT_COMPAT, 'UTF-8' );
			return str_replace(
				'<input ',
				'<input placeholder="'.$_v.'" ',
				$field->input);
		}
		else if ($field->getAttribute('type')=='textarea')
		{
			$_v = htmlspecialchars(preg_replace('/[\n\r]/', ' ', $_v), ENT_COMPAT, 'UTF-8' );
			return str_replace('<textarea ', '<textarea placeholder="'.$_v.'" ', $field->input);
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