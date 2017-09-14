<?php
/**
 * @version 1.5 stable $Id: view.html.php 1577 2012-12-02 15:10:44Z ggppdk $
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
 * View class for the FLEXIcontent file screen
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentViewFile extends JViewLegacy
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
		//$authorparams = flexicontent_db::getUserConfig($user->id);

		// Get url vars and some constants
		$option     = $jinput->get('option', '', 'cmd');
		$view       = $jinput->get('view', '', 'cmd');

		$tip_class = ' hasTooltip';
		$manager_view = $ctrl = 'filemanager';
		$js = '';



		// ***
		// *** Get record data, and check if record is already checked out
		// ***
		
		// Get model and load the record data
		$model = $this->getModel();
		$row   = $this->get('File');
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
		$cancreate = false;
		
		// SET toolbar title
		!$isnew
			? JToolbarHelper::title( JText::_( 'FLEXI_EDIT_FILE' ), 'fileedit' )
			: JToolbarHelper::title( JText::_( 'FLEXI_EDIT_FILE' ), 'fileedit' );



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
		// *** Add inline js to head
		// ***
		if ($js)
		{
			$document->addScriptDeclaration('jQuery(document).ready(function(){'
				.$js.
			'});');
		}


		// ***
		// *** Get real file size (currently)
		// ***

		if (!$row->url)
		{
			$path = $this->row->secure ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH;  // JPATH_ROOT . DS . <media_path | file_path>
			$file_path = $path . DS . $this->row->filename;
			
			$file_size = file_exists($file_path) ? filesize($file_path) : 0;
			$file_size_str = $file_size < 1024 * 1024 ?
				number_format(filesize($file_path) / (1024), 2) .' KBs' :
				number_format(filesize($file_path) / (1024 * 1024), 2) .' MBs';
		}

		else
		{
			$url = $row->filename_original ?: $row->filename;
			$filesize = $model->get_file_size_from_url($url);

			if ($filesize === -999)
			{
				$app->enqueueMessage($model->getError(), 'warning');
			}

			if (empty($row->size))
			{
				$row->size = $filesize < 0 ? 0 : $filesize;
			}

			$row->calculated_size = $filesize < 0 ? 0 : $filesize;
		}


		//***
		//*** Build access level list
		//***
		$options = JHtml::_('access.assetgroups');
		//$lists['access'] = JHtml::_('access.assetgrouplist', 'access', $row->access, $attribs=' class="use_select2_lib" ', $config=array(/*'title' => JText::_('FLEXI_SELECT'), */'id' => 'access'));

		// Add current row access level number, if it has does not exist
		$found = false;
		foreach ($options as $o)
		{
			if ($o->value == $row->access)
			{
				$found = true;
				break;
			}
		}
		if (!$found) array_unshift($options, JHtml::_('select.option', $row->access, /*JText::_('FLEXI_ACCESS').': '.*/$row->access) );

		$elementid = $fieldname = 'access';
		$attribs = 'class="use_select2_lib"';
		$lists['access'] = JHtml::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', $row->access, $elementid, $translate=true );

		$options = array(1=> JText::_( 'FLEXI_YES' ), 0=> JText::_( 'FLEXI_NO' ));
		$elementid = $fieldname = 'stamp';
		$lists['stamp'] = flexicontent_html::buildradiochecklist($options, $fieldname, $row->stamp, 0, ' class="fc_filestamp" ', $elementid);

		// Build languages list
		//$allowed_langs = !$authorparams ? null : $authorparams->get('langs_allowed',null);
		//$allowed_langs = !$allowed_langs ? null : FLEXIUtilities::paramToArray($allowed_langs);
		$allowed_langs = null;
		$lists['language'] = flexicontent_html::buildlanguageslist('language', ' class="use_select2_lib" ', $row->language, 2, $allowed_langs, $published_only=false);
		
		// check access level exists
		$level_name = flexicontent_html::userlevel(null, $row->access, null, null, null, $_createlist = false);
		if (empty($level_name))
		{
			JFactory::getApplication()->enqueueMessage(JText::sprintf('FLEXI_ABOUT_INVALID_ACCESS_LEVEL_PLEASE_SAVE_NEW', $row->access, 'Public'), 'warning');
			$document->addScriptDeclaration("jQuery(document).ready(function() { jQuery('#access').val(1).trigger('change'); });");
		}

		// Encode (UTF-8 charset) HTML entities form data so that they can be set as form field values
		// NOTE: we will use JForm to output fields so this is redundant
		//JFilterOutput::objectHTMLSafe( $row, ENT_QUOTES, $exclude_keys = '' );

		// Assign data to template
		$this->row    = $row;
		$this->form   = $form;
		$this->lists    = $lists;
		$this->document = $document;

		parent::display($tpl);
	}
}
?>