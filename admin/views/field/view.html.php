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
jimport('joomla.filesystem.file');

/**
 * HTML View class for the Field screen
 */
class FlexicontentViewField extends FlexicontentViewBaseRecord
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

		$isAdmin  = $app->isClient('administrator');
		$isCtmpl  = $jinput->getCmd('tmpl') === 'component';

		$tip_class = ' hasTooltip';
		$manager_view = 'fields';
		$ctrl = 'fields';
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
		flexicontent_html::loadFramework('touch-punch');
		flexicontent_html::loadFramework('prettyCheckable');
		flexicontent_html::loadFramework('flexi-lib');
		flexicontent_html::loadFramework('flexi-lib-form');

		// Load custom behaviours: form validation, popup tooltips
		JHtml::_('behavior.formvalidator');
		JHtml::_('bootstrap.tooltip');

		// Load subform JS, // JHtml::_('jquery.ui', array('core', 'sortable'));  // This is already loaded
		JHtml::_('script', 'system/subform-repeatable.js', array('version' => 'auto', 'relative' => true));

		// Load minicolors JS
		JHtml::_('script', 'jui/jquery.minicolors.min.js', array('version' => 'auto', 'relative' => true));
		JHtml::_('stylesheet', 'jui/jquery.minicolors.css', array('version' => 'auto', 'relative' => true));

		// Add js function to overload the joomla submitform validation
		$document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/admin.js', array('version' => FLEXI_VHASH));
		$document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/validate.js', array('version' => FLEXI_VHASH));


		/**
		 * Create the toolbar
		 */

		$toolbar = JToolbar::getInstance('toolbar');

		// Creation flag used to decide if adding save and new / save as copy buttons are allowed
		$cancreate = true;

		// SET toolbar title
		!$isnew
			? JToolbarHelper::title( JText::_( 'FLEXI_EDIT_FIELD' ), 'puzzle' )
			: JToolbarHelper::title( JText::_( 'FLEXI_ADD_FIELD' ), 'puzzle' );


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
			if (!$isnew && !$row->iscore)
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


		if (!empty($model->helpURL))
		{
			$onclick_js = empty($_SERVER['HTTPS']) && $model->helpModal
				? 'var url = jQuery(this).attr(\'data-href\'); fc_showDialog(url, \'fc_modal_popup_container\', 0, 0, 0, false, {\'title\': \''.flexicontent_html::encodeHTML(JText::_($model->helpTitle), 2).'\'}); return false;'
				: 'var url = jQuery(this).attr(\'data-href\'); window.open(url); return false;';
			$js .= "
				jQuery('#toolbar-help a.toolbar, #toolbar-help button').attr('data-href', '".$model->helpURL."').attr('onclick', \"".$onclick_js."\");
			";
			JToolbarHelper::custom( $btn_task='', 'help.png', 'help_f2.png', $model->helpTitle, false );
		}


		/**
		 * Display appropriate messages: import and check current field
		 */
		
		// Import Joomla plugin that implements the type of current field
		$extfolder = 'flexicontent_fields';
		$extname = $row->iscore ? 'core' : $row->field_type;
		JPluginHelper::importPlugin('flexicontent_fields', ($row->iscore ? 'core' : $row->field_type) );

		// Create class name of the plugin and then create a plugin instance
		$classname = 'plg'. ucfirst($extfolder).$extname;

		// Check max allowed version
		if ( property_exists ($classname, 'prior_to_version') )
		{
			// Set a system message with warning of failed PHP limits
			$prior_to_version = $app->getUserStateFromRequest( $option.'.flexicontent.prior_to_version_'.$row->field_type,	'prior_to_version_'.$row->field_type,	0, 'int' );
			$app->setUserState( $option.'.flexicontent.prior_to_version_'.$row->field_type, $prior_to_version+1 );
			if ($prior_to_version < 2)
			{
				$close_btn = FLEXI_J30GE ? '<a class="close" data-dismiss="alert">&#215;</a>' : '<a class="fc-close" onclick="this.parentNode.parentNode.removeChild(this.parentNode);">&#215;</a>';
				
				$manifest_path = JPATH_ADMINISTRATOR .DS. 'components' .DS. 'com_flexicontent' .DS. 'flexicontent.xml';
				$com_xml = JInstaller::parseXMLInstallFile( $manifest_path );
				$ver_exceeded = version_compare( str_replace(' ', '.', $com_xml['version']), str_replace(' ', '.', $classname::$prior_to_version), '>=');
				echo $ver_exceeded ? '
					<span class="fc-note fc-mssg-inline">
						'.$close_btn.'
						Warning: installed version of Field: \'<b>'.$extname.'</b>\' was given to be free for FLEXIcontent versions prior to: v'.$classname::$prior_to_version.' <br/> It may or may not work properly in later versions
					</span>' : '
					<span class="fc-info fc-mssg-inline">
						'.$close_btn.'
						Note: installed version of Field: \'<b>'.$extname.'</b>\' is given free for FLEXIcontent versions prior to: v'.$classname::$prior_to_version.', &nbsp; &nbsp; nevertheless it will continue to function after FLEXIcontent is upgraded.
					</span>';
			}
		}



		// Because 'site-default' language file may not have all needed language strings, or it may be syntactically broken
		// we load the ENGLISH language file (without forcing it, to avoid overwritting site-default), and then current language file
		$extension_name = 'plg_flexicontent_fields_'. ($row->iscore ? 'core' : $row->field_type);
		JFactory::getLanguage()->load($extension_name, JPATH_ADMINISTRATOR, 'en-GB', $force_reload = false, $load_default = true);  // force_reload OFF
		JFactory::getLanguage()->load($extension_name, JPATH_ADMINISTRATOR, null, $force_reload = true, $load_default = true);



		/**
		 * Check which properties are supported by current field
		 */

		$ft_support = FlexicontentFields::getPropertySupport($row->field_type, $row->iscore);
		
		$supportsearch          = $ft_support->supportsearch;
		$supportadvsearch       = $ft_support->supportadvsearch;
		$supportfilter          = $ft_support->supportfilter;
		$supportadvfilter       = $ft_support->supportadvfilter;
		$supportuntranslatable  = $ft_support->supportuntranslatable;
		$supportvalueseditable  = $ft_support->supportvalueseditable;
		$supportformhidden      = $ft_support->supportformhidden;
		$supportedithelp        = $ft_support->supportedithelp;



		// Check access level exists
		$level_name = flexicontent_html::userlevel(null, $row->access, null, null, '', $_createlist = false);
		if (empty($level_name))
		{
			JFactory::getApplication()->enqueueMessage(JText::sprintf('FLEXI_ABOUT_INVALID_ACCESS_LEVEL_PLEASE_SAVE_NEW', $row->access, 'Public'), 'warning');
			$document->addScriptDeclaration("jQuery(document).ready(function() { jQuery('#jform_access').val(1).trigger('change'); });");
		}


		/**
		 * Add JS for AJAX reloading field parameters after field type change
		 */
	
		if (!$row->iscore)
		{
			$_field_id = 'jform_field_type';
			$_row_id = $form->getValue("id");
			$_ctrl_task = 'task=fields.getfieldspecificproperties';
			$js .= "
				jQuery('#".$_field_id."').on('change', function() {
					jQuery('#fieldspecificproperties').html('<p class=\"centerimg\"><img src=\"components/com_flexicontent/assets/images/ajax-loader.gif\" style=\"vertical-align: middle;\"></p>');
					jQuery.ajax({
						type: \"GET\",
						url: 'index.php?option=com_flexicontent&".$_ctrl_task."&cid=".$_row_id."&field_type='+this.value+'&format=raw',
						success: function(str)
						{
							// Initialize JS and CSS of the layout
							const container_id  = 'fieldspecificproperties';
							fc_initDynamicLayoutJsCss(container_id, ['subform-row-add'], str);
							jQuery('#field_typename').html(jQuery('#".$_field_id."').val());
						}
					});
				});
			";
		}
		
		// Core field
		else
		{
			// Set name property as readonly
			// ... done by model's preprocessForm() method
			//$form->setFieldAttribute('name', 'readonly', 'true');
		}



		// ***
		// *** Build selectlists, (some of these are defined via XML file, but some are not, possibly we may add more re-usable form elements later)
		// ***
		$lists = array();

		// Build field_type select list
		$fieldTypes = flexicontent_db::getFieldTypes($_grouped=true, $_usage=false, $_published=true);
		$fftypes = array();
		$n = 0;
		foreach ($fieldTypes as $field_group => $ft_types)
		{
			$fftypes[$field_group] = array();
			$fftypes[$field_group]['id'] = 'field_group_' . ($n++);
			$fftypes[$field_group]['text'] = $field_group;
			$fftypes[$field_group]['items'] = array();
			foreach ($ft_types as $field_type => $ftdata)
			{
				$fftypes[$field_group]['items'][] = array('value' => $ftdata->field_type, 'text' => $ftdata->friendly);
			}
		}
		$_attribs = ' class="use_select2_lib" ' . ($row->iscore ? ' disabled="disabled" ' : '');
		$lists['field_type'] = flexicontent_html::buildfieldtypeslist($fftypes, 'jform[field_type]', $row->field_type, ($_grouped ? 1 : 0), $_attribs);

		// Build (content) type select list
		$types = $this->get('Typeslist');
		$typesselected = $this->get('FieldType');
		$attribs  = 'class="use_select2_lib" multiple="multiple" size="6"';
		$attribs .= $row->iscore && !in_array($row->field_type, array('voting', 'favourites'), true)
			? ' disabled="disabled"'
			: '';
		$types_fieldname = 'jform[tid][]';
		$lists['tid'] = flexicontent_html::buildtypesselect($types, $types_fieldname, $typesselected, false, $attribs);


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
		$this->lists    = $lists;
		$this->perms    = $perms;
		$this->cparams  = $cparams;
		$this->view     = $view;
		$this->controller = $controller;

		$this->typesselected            = $typesselected;
		$this->supportsearch            = $supportsearch;
		$this->supportadvsearch         = $supportadvsearch;
		$this->supportfilter            = $supportfilter;
		$this->supportadvfilter         = $supportadvfilter;
		$this->supportuntranslatable    = $supportuntranslatable;
		$this->supportvalueseditable    = $supportvalueseditable;
		$this->supportformhidden        = $supportformhidden;
		$this->supportedithelp          = $supportedithelp;

		parent::display($tpl);
	}
}
