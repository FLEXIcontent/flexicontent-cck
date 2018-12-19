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
 * HTML View class for the Review screen
 */
class FlexicontentViewReview extends FlexicontentViewBaseRecord
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

		// Get url vars and some constants
		$option     = $jinput->get('option', '', 'cmd');
		$view       = $jinput->get('view', '', 'cmd');
		$task       = $jinput->get('task', '', 'cmd');
		$controller = $jinput->get('controller', '', 'cmd');

		$isAdmin  = $app->isAdmin();
		$isCtmpl  = $jinput->getCmd('tmpl') === 'component';

		$tip_class = ' hasTooltip';
		$manager_view = 'reviews';
		$ctrl = 'reviews';
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


		/**
		 * Include needed files and add needed js / css files
		 */

		// Add css to document
		if ($isAdmin)
		{
			!JFactory::getLanguage()->isRtl()
				? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', FLEXI_VHASH)
				: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', FLEXI_VHASH);
			!JFactory::getLanguage()->isRtl()
				? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x.css', FLEXI_VHASH)
				: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/j3x_rtl.css', FLEXI_VHASH);
		}
		else
		{
			!JFactory::getLanguage()->isRtl()
				? $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontent.css', FLEXI_VHASH)
				: $document->addStyleSheetVersion(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontent_rtl.css', FLEXI_VHASH);
		}

		// Add JS frameworks
		flexicontent_html::loadFramework('select2');
		flexicontent_html::loadFramework('flexi-lib');
		flexicontent_html::loadFramework('flexi-lib-form');

		// Load custom behaviours: form validation, popup tooltips
		JHtml::_('behavior.formvalidation');
		JHtml::_('bootstrap.tooltip');

		// Add js function to overload the joomla submitform validation
		$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/admin.js', FLEXI_VHASH);
		$document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/validate.js', FLEXI_VHASH);


		/**
		 * Create the toolbar
		 */

		$toolbar = JToolbar::getInstance('toolbar');

		// Creation flag used to decide if adding save and new / save as copy buttons are allowed
		$cancreate = true;

		// SET toolbar title
		!$isnew
			? JToolbarHelper::title(JText::_('FLEXI_EDIT_REVIEW'), 'reviewedit')
			: JToolbarHelper::title(JText::_('FLEXI_NEW_REVIEW'), 'reviewadd');


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
				$btn_task, $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false, $btn_class="".$tip_class, $btn_icon="icon-loop",
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
				$btn_task, $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false, $btn_class="".$tip_class, $btn_icon="icon-save",
				'data-placement="right" title=""', $auto_add = 0);
		}

		flexicontent_html::addToolBarDropMenu($btn_arr, 'apply_btns_group');


		/**
		 * Save buttons
		 */

		$btn_arr = array();

		$btn_name = 'save';
		$btn_task = $ctrl.'.save';

		//JToolbarHelper::save($btn_task);  //JToolbarHelper::custom( $btn_task, 'save.png', 'save.png', 'JSAVE', false );

		$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
			'JSAVE', $btn_name, $full_js="Joomla.submitbutton('".$ctrl.".save')", $msg_alert='', $msg_confirm='',
			$btn_task, $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false, $btn_class="".$tip_class, $btn_icon="icon-save",
			'data-placement="bottom" title=""', $auto_add = 0);


		// Add a save and new button, if user can create new records
		if (!$isCtmpl && $cancreate)
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


		// Cancel button, TODO frontend modal close
		if ($isAdmin && !$isCtmpl)
		{
			$isnew
				? JToolbarHelper::cancel($ctrl.'.cancel', $isAdmin ? 'JTOOLBAR_CANCEL' : 'FLEXI_CANCEL')
				: JToolbarHelper::cancel($ctrl.'.cancel', $isAdmin ? 'JTOOLBAR_CLOSE' : 'FLEXI_CLOSE_FORM');
		}


		// Preview button
		if (!$isnew)
		{
			// Create preview link (with xhtml to false ... we will do it manually) (at least for the ampersand)
			$record_link = str_replace('&', '&amp;', FlexicontentHelperRoute::getItemRoute($row->content_id));
			$previewlink = JRoute::_(JUri::root() . $record_link, $xhtml=false)
				. "#review_id_".$row->id
				;
			$toolbar->appendButton( 'Custom', '
				<button class="preview btn btn-small btn-fcaction btn-info spaced-btn" onclick="window.open(\''.$previewlink.'\'); return false;">
					<span title="'.JText::_('FLEXI_PREVIEW').'" class="icon-screen"></span>
					'.JText::_('FLEXI_PREVIEW').'
				</button>', 'preview'
			);
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
		$this->perms    = $perms;
		$this->cparams  = $cparams;
		$this->view     = $view;
		$this->controller = $controller;

		parent::display($tpl);
	}
}
