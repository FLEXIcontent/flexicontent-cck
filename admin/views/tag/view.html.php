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
 * HTML View class for the Tag screen
 */
class FlexicontentViewTag extends FlexicontentViewBaseRecord
{
	var $proxy_option = 'com_tags';

	/**
	 * Display the view
	 */
	public function display($tpl = null)
	{
		/**
		 * Initialize variables, flags, etc
		 */

		$app        = \Joomla\CMS\Factory::getApplication();
		$jinput     = $app->input;
		$document   = \Joomla\CMS\Factory::getDocument();
		$user       = \Joomla\CMS\Factory::getUser();
		$db         = \Joomla\CMS\Factory::getDbo();
		$cparams    = \Joomla\CMS\Component\ComponentHelper::getParams('com_flexicontent');
		$perms      = FlexicontentHelperPerm::getPerm();

		// Get url vars and some constants
		$option     = $jinput->get('option', '', 'cmd');
		$view       = $jinput->get('view', '', 'cmd');
		$task       = $jinput->get('task', '', 'cmd');
		$controller = $jinput->get('controller', '', 'cmd');

		$isAdmin  = $app->isClient('administrator');
		$isCtmpl  = $jinput->getCmd('tmpl') === 'component';

		$tip_class = ' hasTooltip';
		$manager_view = 'tags';
		$ctrl = 'tags';
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

		// Get \Joomla\CMS\Form\Form
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
			$app->enqueueMessage(\Joomla\CMS\Language\Text::_( 'FLEXI_EDITED_BY_ANOTHER_ADMIN' ), 'warning');

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
			!\Joomla\CMS\Factory::getLanguage()->isRtl()
				? $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', array('version' => FLEXI_VHASH))
				: $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', array('version' => FLEXI_VHASH));
			!\Joomla\CMS\Factory::getLanguage()->isRtl()
				? $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x.css' : 'j3x.css'), array('version' => FLEXI_VHASH))
				: $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x_rtl.css' : 'j3x_rtl.css'), array('version' => FLEXI_VHASH));
		}
		else
		{
			!\Joomla\CMS\Factory::getLanguage()->isRtl()
				? $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/flexicontent.css', array('version' => FLEXI_VHASH))
				: $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/flexicontent_rtl.css', array('version' => FLEXI_VHASH));
		}

		// Add JS frameworks
		flexicontent_html::loadFramework('select2');
		flexicontent_html::loadFramework('flexi-lib');
		flexicontent_html::loadFramework('flexi-lib-form');

		// Load custom behaviours: form validation, popup tooltips
		\Joomla\CMS\HTML\HTMLHelper::_('behavior.formvalidator');
		\Joomla\CMS\HTML\HTMLHelper::_('bootstrap.tooltip');

		// Add js function to overload the joomla submitform validation
		$document->addScript(\Joomla\CMS\Uri\Uri::root(true).'/components/com_flexicontent/assets/js/admin.js', array('version' => FLEXI_VHASH));
		$document->addScript(\Joomla\CMS\Uri\Uri::root(true).'/components/com_flexicontent/assets/js/validate.js', array('version' => FLEXI_VHASH));


		/**
		 * Create the toolbar
		 */

		$toolbar = \Joomla\CMS\Toolbar\Toolbar::getInstance('toolbar');

		// Creation flag used to decide if adding save and new / save as copy buttons are allowed
		$cancreate = true;

		// SET toolbar title
		!$isnew
			? \Joomla\CMS\Toolbar\ToolbarHelper::title( \Joomla\CMS\Language\Text::_( 'FLEXI_EDIT_TAG' ), 'tags' )
			: \Joomla\CMS\Toolbar\ToolbarHelper::title( \Joomla\CMS\Language\Text::_( 'FLEXI_NEW_TAG' ), 'tags' );


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
				'data-placement="bottom" title="'.\Joomla\CMS\Language\Text::_('FLEXI_FAST_SAVE_INFO', true).'"', $auto_add = 0);
		}

		// Apply & Reload button   ***   (Apply Type, is a special case of new that has not loaded custom fieds yet, due to type not defined on initial form load)
		if ($isAdmin && !$isCtmpl)
		{
			$btn_name = 'apply';
			$btn_task = $ctrl.'.apply';
			$btn_title = !$isnew ? 'FLEXI_APPLY_N_RELOAD' : 'FLEXI_ADD';

			//\Joomla\CMS\Toolbar\ToolbarHelper::apply($btn_task, $btn_title, false);

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

			//\Joomla\CMS\Toolbar\ToolbarHelper::save($btn_task);  //\Joomla\CMS\Toolbar\ToolbarHelper::custom( $btn_task, 'save.png', 'save.png', 'JSAVE', false );

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

			//\Joomla\CMS\Toolbar\ToolbarHelper::save2new($btn_task);  //\Joomla\CMS\Toolbar\ToolbarHelper::custom( $btn_task, 'savenew.png', 'savenew.png', 'FLEXI_SAVE_AND_NEW', false );

			$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
				'FLEXI_SAVE_AND_NEW', $btn_name, $full_js="Joomla.submitbutton('".$ctrl.".save2new')", $msg_alert='', $msg_confirm='',
				$btn_task, $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
				$btn_class= (FLEXI_J40GE ? ' _DDI_class_ btn-success ' : '') . ' ' . $this->tooltip_class, $btn_icon="icon-save-new",
				'data-placement="right" title="'.\Joomla\CMS\Language\Text::_('FLEXI_SAVE_AND_NEW_INFO', true).'"', $auto_add = 0);

			// Also if an existing item, can save to a copy
			if (!$isnew)
			{
				$btn_name = 'save2copy';
				$btn_task = $ctrl.'.save2copy';

				//\Joomla\CMS\Toolbar\ToolbarHelper::save2copy($btn_task);  //\Joomla\CMS\Toolbar\ToolbarHelper::custom( $btn_task, 'save2copy.png', 'save2copy.png', 'FLEXI_SAVE_AS_COPY', false );

				$btn_arr[$btn_name] = flexicontent_html::addToolBarButton(
					'FLEXI_SAVE_AS_COPY', $btn_name, $full_js="Joomla.submitbutton('".$ctrl.".save2copy')", $msg_alert='', $msg_confirm='',
					$btn_task, $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false,
					$btn_class= (FLEXI_J40GE ? ' _DDI_class_ btn-success ' : '') . ' ' . $this->tooltip_class, $btn_icon="icon-save-copy",
					'data-placement="right" title="'.\Joomla\CMS\Language\Text::_('FLEXI_SAVE_AS_COPY_INFO', true).'"', $auto_add = 0);
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
				? \Joomla\CMS\Toolbar\ToolbarHelper::cancel($ctrl.'.cancel', $isAdmin ? 'JTOOLBAR_CANCEL' : 'FLEXI_CANCEL')
				: \Joomla\CMS\Toolbar\ToolbarHelper::cancel($ctrl.'.cancel', $isAdmin ? 'JTOOLBAR_CLOSE' : 'FLEXI_CLOSE_FORM');
		}


		// Preview button
		if (!$isnew)
		{
			// Create preview link (with xhtml to false ... we will do it manually) (at least for the ampersand)
			$record_link = str_replace('&', '&amp;', FlexicontentHelperRoute::getTagRoute($row->id));
			$previewlink = \Joomla\CMS\Router\Route::_(\Joomla\CMS\Uri\Uri::root() . $record_link, $xhtml=false)
				;
			$toolbar->appendButton( 'Custom', '
				<button class="preview btn btn-small btn-fcaction btn-info spaced-btn" onclick="window.open(\''.$previewlink.'\'); return false;">
					<span title="'.\Joomla\CMS\Language\Text::_('FLEXI_PREVIEW').'" class="icon-screen"></span>
					'.\Joomla\CMS\Language\Text::_('FLEXI_PREVIEW').'
				</button>', 'preview'
			);
		}

		$jtag_id = $row->jtag_id;

		// Find the respective Joomla Tag Id by using its title, creating a new Joomla tag if it does not exist already, and then mapping it to current FLEXIcontent tag
		if (!$isnew && !$jtag_id)
		{
			$jtag_id_arr = flexicontent_db::createFindJoomlaTags(array('#new#' . $row->name));

			if (!empty($jtag_id_arr))
			{
				$jtag     = reset($jtag_id_arr);
				$jtag_id  = $jtag->id;

				$tagTable = \Joomla\CMS\Table\Table::getInstance($_type = 'flexicontent_tags', $_prefix = '', $_config = array());
				$tagTable->load($row->id);
				$tagTable->jtag_id = $jtag_id;
				$tagTable->store();
			}
		}

		if ($jtag_id)
		{
			\Joomla\CMS\Language\Text::script("FLEXI_UPDATING_CONTENTS", true);
			$document->addScriptDeclaration('
				function fc_edit_jtag_modal_load( container )
				{
					if ( container.find("iframe").get(0).contentWindow.location.href.indexOf("view=tags") != -1 )
					{
						container.dialog("close");
					}
				}
				function fc_edit_jtag_modal_close()
				{
					window.location.reload(false);
					document.body.innerHTML = "<div>" + Joomla.JText._("FLEXI_UPDATING_CONTENTS") + \' <img id="page_loading_img" src="components/com_flexicontent/assets/images/ajax-loader.gif"></div>\';
				}
			');

			$modal_title = \Joomla\CMS\Language\Text::_('FLEXI_EDIT_JTAG', true);
			$tip_class = ' hasTooltip';
			\Joomla\CMS\Toolbar\ToolbarHelper::divider();
			flexicontent_html::addToolBarButton(
				'FLEXI_EDIT_JTAG', $btn_name='edit_jtag',
				$full_js="var url = jQuery(this).attr('data-href'); var the_dialog = fc_showDialog(url, 'fc_modal_popup_container', 0, 0, 0, fc_edit_jtag_modal_close, {title:'".$modal_title."', loadFunc: fc_edit_jtag_modal_load}); return false;",
				$msg_alert='', $msg_confirm='',
				$btn_task='', $extra_js='', $btn_list=false, $btn_menu=true, $btn_confirm=false, $btn_class="spaced-btn btn-info".$tip_class, $btn_icon="icon-pencil",
				'data-placement="bottom" data-href="index.php?option=com_tags&task=tag.edit&id='.$jtag_id.'" title="Edit all details of joomla tag"'
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
		 * NOTE: we will use \Joomla\CMS\Form\Form to output fields so this is redundant
		 */

		//\Joomla\CMS\Filter\OutputFilter::objectHTMLSafe( $row, ENT_QUOTES, $exclude_keys = '' );


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
