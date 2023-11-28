<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

JLoader::register('FlexicontentViewBaseRecord', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/base/view_record.php');

/**
 * HTML View class for the File screen
 */
class FlexicontentViewFile extends FlexicontentViewBaseRecord
{
	var $proxy_option = null;

	/**
	 * Display the view
	 */
	public function display($tpl = null)
	{
		/**
		 * Initialize variables, flags, etc
		 */

		$app        = JFactory::getApplication();
		$jinput     = $app->input;
		$document   = JFactory::getDocument();
		$user       = JFactory::getUser();
		$db         = JFactory::getDbo();
		$cparams    = JComponentHelper::getParams('com_flexicontent');
		$perms      = FlexicontentHelperPerm::getPerm();
		//$authorparams = flexicontent_db::getUserConfig($user->id);

		// Get url vars and some constants
		$option     = $jinput->get('option', '', 'cmd');
		$view       = $jinput->get('view', '', 'cmd');
		$task       = $jinput->get('task', '', 'cmd');
		$controller = $jinput->get('controller', '', 'cmd');

		$isAdmin  = $app->isClient('administrator');
		$isCtmpl  = $jinput->getCmd('tmpl') === 'component';

		$tip_class = ' hasTooltip';
		$manager_view = 'filemanager';
		$ctrl = 'filemanager';
		$js = '';


		/**
		 * Common view
		 */

		$this->prepare_common_fcview();


		/**
		 * Get record data, and check if record is already checked out
		 */

		// Get model and load the record data
		$model = $this->getModel();
		$row   = $this->get('File');
		$isnew = ! $row->id;

		// Get JForm
		$form = $this->get('Form');

		if (!$form)
		{
			$app->enqueueMessage($model->getError(), 'warning');

			if ($jinput->getCmd('tmpl') !== 'component')
			{
				$app->redirect( 'index.php?option=com_flexicontent&view=' . $manager_view );
			}
			return;
		}

		// Fail if an existing record is checked out by someone else
		if ($row->id && $model->isCheckedOut($user->get('id')))
		{
			$app->enqueueMessage(JText::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ), 'warning');

			if ($jinput->getCmd('tmpl') !== 'component')
			{
				$app->redirect( 'index.php?option=com_flexicontent&view=' . $manager_view );
			}
			return;
		}

		// Fail if an existing record is file being moved to external storage
		if ($row->id && $row->estorage_fieldid < 0)
		{
			$app->enqueueMessage(JText::_( 'File is being moved to external storage, please edit later' ), 'warning');

			if ($jinput->getCmd('tmpl') !== 'component')
			{
				$app->redirect( 'index.php?option=com_flexicontent&view=' . $manager_view );
			}
			return;
		}


		/**
		 * Include needed files and add needed js / css files
		 */

		// Add css to document
		if ($isAdmin)
		{
			!JFactory::getLanguage()->isRtl()
				? $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', array('version' => FLEXI_VHASH))
				: $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', array('version' => FLEXI_VHASH));
			!JFactory::getLanguage()->isRtl()
				? $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x.css' : 'j3x.css'), array('version' => FLEXI_VHASH))
				: $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x_rtl.css' : 'j3x_rtl.css'), array('version' => FLEXI_VHASH));
		}
		else
		{
			!JFactory::getLanguage()->isRtl()
				? $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontent.css', array('version' => FLEXI_VHASH))
				: $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontent_rtl.css', array('version' => FLEXI_VHASH));
		}

		// Add JS frameworks
		flexicontent_html::loadFramework('select2');
		flexicontent_html::loadFramework('flexi-lib');
		flexicontent_html::loadFramework('flexi-lib-form');

		// Load custom behaviours: form validation, popup tooltips
		JHtml::_('behavior.formvalidator');
		JHtml::_('bootstrap.tooltip');

		// Add js function to overload the joomla submitform validation
		$document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/admin.js', array('version' => FLEXI_VHASH));
		$document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/validate.js', array('version' => FLEXI_VHASH));


		/**
		 * Create the toolbar
		 */

		$toolbar = JToolbar::getInstance('toolbar');

		// Creation flag used to decide if adding save and new / save as copy buttons are allowed
		$cancreate = false;

		// SET toolbar title
		!$isnew
			? JToolbarHelper::title( JText::_( 'FLEXI_EDIT_FILE' ), 'fileedit' )
			: JToolbarHelper::title( JText::_( 'FLEXI_EDIT_FILE' ), 'fileedit' );


		/**
		 * Apply buttons
		 */

		// Apply button
		$btn_arr = array();

		// Add ajax apply only for existing records
		if (!$isnew)
		{
			$btn_name = 'apply_ajax';
			$btn_task = $ctrl.'.apply_ajax';

			$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
				'FLEXI_APPLY', $btn_name, $full_js="Joomla.submitbutton('".$ctrl.".apply_ajax')", $msg_alert='', $msg_confirm='',
				$btn_task, $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
				$btn_class=(FLEXI_J40GE ? ' _DDI_class_ btn-success ' : '') . ' ' . $this->tooltip_class, $btn_icon="icon-loop",
				'data-placement="bottom" title="'.JText::_('FLEXI_FAST_SAVE_INFO', true).'"', $auto_add = 0);
		}

		// Apply & Reload button   ***   (Apply Type, is a special case of new that has not loaded custom fieds yet, due to type not defined on initial form load)
		if ($isAdmin && !$isCtmpl)
		{
			$btn_name = 'apply';
			$btn_task = $ctrl.'.apply';
			$btn_title = !$isnew ? 'FLEXI_APPLY_N_RELOAD' : 'FLEXI_ADD';

			//JToolbarHelper::apply($btn_task, $btn_title, false);

			$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
				$btn_title, $btn_name, $full_js="Joomla.submitbutton('".$btn_task."')", $msg_alert='', $msg_confirm='',
				$btn_task, $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
				$btn_class=(FLEXI_J40GE ? ' _DDI_class_ btn-success ' : '') . ' ' . $this->tooltip_class, $btn_icon="icon-save",
				'data-placement="right" title=""', $auto_add = 0);
		}

		flexicontent_html::addToolBarDropMenu(
			$btn_arr,
			'apply_btns_group',
			null,
			array('drop_class_extra' => (FLEXI_J40GE ? 'btn-success' : ''))
		);


		/**
		 * Save buttons
		 */

		$btn_arr = array();
		if (1)
		{
			$btn_name = 'save';
			$btn_task = $ctrl.'.save';

			//JToolbarHelper::save($btn_task);  //JToolbarHelper::custom( $btn_task, 'save.png', 'save.png', 'JSAVE', false );

			$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
				'JSAVE', $btn_name, $full_js="Joomla.submitbutton('".$ctrl.".save')", $msg_alert='', $msg_confirm='',
				$btn_task, $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
				$btn_class=(FLEXI_J40GE ? ' _DDI_class_ btn-success ' : '') . ' ' . $this->tooltip_class, $btn_icon="icon-save",
				'data-placement="bottom" title=""', $auto_add = 0);
			}


		// Add a save and new button, if user can create new records
		if (!$isCtmpl && $cancreate)
		{
			$btn_name = 'save2new';
			$btn_task = $ctrl.'.save2new';

			//JToolbarHelper::save2new($btn_task);  //JToolbarHelper::custom( $btn_task, 'savenew.png', 'savenew.png', 'FLEXI_SAVE_AND_NEW', false );

			$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
				'FLEXI_SAVE_AND_NEW', $btn_name, $full_js="Joomla.submitbutton('".$ctrl.".save2new')", $msg_alert='', $msg_confirm='',
				$btn_task, $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
				$btn_class= (FLEXI_J40GE ? ' _DDI_class_ btn-success ' : '') . ' ' . $this->tooltip_class, $btn_icon="icon-save-new",
				'data-placement="right" title="'.JText::_('FLEXI_SAVE_AND_NEW_INFO', true).'"', $auto_add = 0);

			// Also if an existing item, can save to a copy
			if (!$isnew)
			{
				$btn_name = 'save2copy';
				$btn_task = $ctrl.'.save2copy';

				//JToolbarHelper::save2copy($btn_task);  //JToolbarHelper::custom( $btn_task, 'save2copy.png', 'save2copy.png', 'FLEXI_SAVE_AS_COPY', false );

				$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
					'FLEXI_SAVE_AS_COPY', $btn_name, $full_js="Joomla.submitbutton('".$ctrl.".save2copy')", $msg_alert='', $msg_confirm='',
					$btn_task, $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
					$btn_class= (FLEXI_J40GE ? ' _DDI_class_ btn-success ' : '') . ' ' . $this->tooltip_class, $btn_icon="icon-save-copy",
					'data-placement="right" title="'.JText::_('FLEXI_SAVE_AS_COPY_INFO', true).'"', $auto_add = 0);
			}
		}

		flexicontent_html::addToolBarDropMenu(
			$btn_arr,
			'save_btns_group',
			null,
			array('drop_class_extra' => (FLEXI_J40GE ? 'btn-success' : ''))
		);


		// Cancel button, TODO frontend modal close
		if ($isAdmin && !$isCtmpl)
		{
			$isnew
				? JToolbarHelper::cancel($ctrl.'.cancel', $isAdmin ? 'JTOOLBAR_CANCEL' : 'FLEXI_CANCEL')
				: JToolbarHelper::cancel($ctrl.'.cancel', $isAdmin ? 'JTOOLBAR_CLOSE' : 'FLEXI_CLOSE_FORM');
		}


		/**
		 * Add inline js to head
		 */

		if ($js)
		{
			$document->addScriptDeclaration('jQuery(document).ready(function(){'
				.$js.
			'});');
		}


		// ***
		// *** Get real file size (currently)
		// ***

		$rowdata = new stdclass();

		switch ((int) $row->url)
		{
			case 0:
				$path = $row->secure ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH;  // JPATH_ROOT . DS . <media_path | file_path>
				$rowdata->path = JPath::clean($path . DS . $row->filename);

				$rowdata->calculated_size = file_exists($rowdata->path) ? filesize($rowdata->path) : 0;
				break;

			case 1:
				$url = $row->filename;
				$rowdata->calculated_size = $model->get_file_size_from_url($url);

				if ($rowdata->calculated_size === -999)
				{
					$app->enqueueMessage($model->getError(), 'warning');
				}

				// Maintain current size if size could not be calulcated
				$rowdata->calculated_size = $rowdata->calculated_size < 0
					? $row->size
					: $rowdata->calculated_size;
				break;

			case 2:
				$path = $row->filename_original ?: $row->filename;
				$rowdata->path = JPath::clean(JPATH_ROOT . DS . $path);

				$rowdata->calculated_size = file_exists($rowdata->path) ? filesize($rowdata->path) : 0;
				break;
		}

		$rowdata->calculated_size_display = number_format(ceil($rowdata->calculated_size / (1024)), 0) .' KBs';
		$rowdata->size_display = number_format(ceil($row->size / (1024)), 0) .' KBs';

		// This is typically displayed only if type is URL
		$rowdata->size_warning = $rowdata->calculated_size_display !== $rowdata->size_display
			? '<span class="fc-mssg fc-mssg fc-nobgimage fc-warning">'
				. JText::_('FLEXI_REAL_SIZE') . ': ' . $rowdata->calculated_size_display . ' <br/> '
				. JText::_('FLEXI_SIZE_IN_DB') . ': ' . $rowdata->size_display . ' <br/> '
				. JText::_('FLEXI_PLEASE_SAVE_TO_UPDATE')
				. '</span>'
			: '';


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
		$level_name = flexicontent_html::userlevel(null, $row->access, null, null, '', $_createlist = false);
		if (empty($level_name))
		{
			JFactory::getApplication()->enqueueMessage(JText::sprintf('FLEXI_ABOUT_INVALID_ACCESS_LEVEL_PLEASE_SAVE_NEW', $row->access, 'Public'), 'warning');
			$document->addScriptDeclaration("jQuery(document).ready(function() { jQuery('#access').val(1).trigger('change'); });");
		}


		/**
		 * Encode (UTF-8 charset) HTML entities form data so that they can be set as form field values
		 * NOTE: we will use JForm to output fields so this is redundant
		 */

		//JFilterOutput::objectHTMLSafe( $row, ENT_QUOTES, $exclude_keys = '' );


		/**
		 * Assign variables to view
		 */

		$this->document = $document;
		$this->row      = $row;
		$this->form     = $form;
		$this->lists    = $lists;
		$this->perms    = $perms;
		$this->cparams  = $cparams;
		$this->view     = $view;
		$this->controller = $controller;
		$this->rowdata  = $rowdata;

		parent::display($tpl);
	}
}