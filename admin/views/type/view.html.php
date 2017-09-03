<?php
/**
 * @version 1.5 stable $Id: view.html.php 1608 2012-12-25 04:31:58Z ggppdk $
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
 * View class for the FLEXIcontent type screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewType extends JViewLegacy
{
	function display($tpl = null)
	{
		// ***
		// *** Initialise variables
		// ***

		$app      = JFactory::getApplication();
		$jinput   = $app->input;
		$document = JFactory::getDocument();
		$user     = JFactory::getUser();
		$cparams  = JComponentHelper::getParams('com_flexicontent');

		// Get url vars and some constants
		$option     = $jinput->get('option', '', 'cmd');
		$view       = $jinput->get('view', '', 'cmd');

		$tip_class = ' hasTooltip';
		$manager_view = $ctrl = 'types';
		$js = '';



		// ***
		// *** Get record data, and check if record is already checked out
		// ***
		
		// Get model and load the record data
		$model = $this->getModel();
		$row   = $this->get('Item');
		$isnew = ! $row->id;

		// Get JForm
		$form  = $this->get('Form');
		if (!$form)
		{
			$app->enqueueMessage($model->getError(), 'warning');
			$app->redirect( 'index.php?option=com_flexicontent&view=' . $manager_view );
		}

		// Fail if an existing record is checked out by someone else
		if ($row->id && $model->isCheckedOut($user->get('id')))
		{
			$app->enqueueMessage(JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ), 'warning');
			$app->redirect( 'index.php?option=com_flexicontent&view=' . $manager_view );
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
		$cancreate = true;
		
		// SET toolbar title
		!$isnew
			? JToolbarHelper::title( JText::_( 'FLEXI_EDIT_TYPE' ), 'typeedit' )   // Editing existing type
			: JToolbarHelper::title( JText::_( 'FLEXI_ADD_TYPE' ), 'typeadd' );    // Creating new type



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



		// ***
		// *** Get Layouts, load language of current selected template and apply Layout parameters values into the fields
		// ***

		// Load language file of currently selected template
		$_ilayout = $row->attribs->get('ilayout');
		if ($_ilayout)
		{
			FLEXIUtilities::loadTemplateLanguageFile( $_ilayout );
		}

		// Get item layouts
		$themes = flexicontent_tmpl::getTemplates($_ilayout);
		$tmpls  = $themes->items;

		// Create JForm for the layout and apply Layout parameters values into the fields
		foreach ($tmpls as $tmpl)
		{
			if ($tmpl->name != $_ilayout) continue;
			
			$jform = new JForm('com_flexicontent.template.item', array('control' => 'jform', 'load_data' => false));
			$jform->load($tmpl->params);
			$tmpl->params = $jform;
			foreach ($tmpl->params->getGroup('attribs') as $field)
			{
				$fieldname = $field->fieldname;
				$value = $row->attribs->get($fieldname);
				if (strlen($value)) $tmpl->params->setValue($fieldname, 'attribs', $value);
			}
		}
		
		// Check access level exists
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


		// Encode (UTF-8 charset) HTML entities form data so that they can be set as form field values
		// NOTE: we will use JForm to output fields so this is redundant
		//JFilterOutput::objectHTMLSafe( $row, ENT_QUOTES, $exclude_keys = '' );

		// Assign data to template
		$this->perms    = FlexicontentHelperPerm::getPerm();
		$this->document = $document;
		$this->row      = $row;
		$this->form     = $form;
		$this->tmpls    = $tmpls;
		$this->cparams  = $cparams;

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