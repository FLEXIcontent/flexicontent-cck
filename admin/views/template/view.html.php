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
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Toolbar\ToolbarFactoryInterface;

/**
 * View class for the FLEXIcontent templates screen
 */
class FlexicontentViewTemplate extends \Joomla\CMS\MVC\View\HtmlView
{

	function display($tpl = null)
	{
		// Initialise variables
		$app      = \Joomla\CMS\Factory::getApplication();
		$jinput   = $app->input;
		$option   = $jinput->getCmd('option');
		$db       = \Joomla\CMS\Factory::getContainer()->get(DatabaseInterface::class);
		$document = \Joomla\CMS\Factory::getApplication()->getDocument();
		$user     = \Joomla\CMS\Factory::getApplication()->getIdentity();

		$use_jquery_sortable = true;  // Connected position lists are enabled.

		$type    = $jinput->getWord('type', 'items');
		$folder  = $jinput->getString('folder', 'table');
		$ismodal = $jinput->getInt('ismodal', 0);

		//Get data from the model
		$layout  = $this->get( 'Data');
		if (!$layout)
		{
			$app->enqueueMessage(\Joomla\CMS\Language\Text::sprintf('FLEXI_TEMPLATE_NOT_FOUND', htmlspecialchars($folder, ENT_QUOTES, 'UTF-8')), 'warning');
			$app->redirect('index.php?option=com_flexicontent&view=templates');
			return;
		}
		$conf    = $this->get( 'LayoutConf');

		$fields  = $this->get( 'Fields');
		$fbypos  = $this->get( 'FieldsByPositions');
		$used    = $this->get( 'UsedFields');

		$contentTypes = $this->get( 'TypesList' );
		//$fieldTypes = $this->get( 'FieldTypesList' );
		$fieldTypes = flexicontent_db::getFieldTypes($_grouped = true, $_usage=false, $_published=false);  // Field types with content type ASSIGNMENT COUNTING

		// Skip fields meant for form
		foreach ($fields as $i => $field)
		{
			if (substr($field->name, 0, 5) === 'form_') unset($fields[$i]);
		}

		// Create CONTENT TYPE SELECTOR
		foreach ($fields as $field) {
			$field->type_ids = !empty($field->reltypes)  ?  explode("," , $field->reltypes)  :  array();
		}
		$options = array();
		$options[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option',  '',  \Joomla\CMS\Language\Text::_( 'FLEXI_ALL' ) );
		foreach ($contentTypes as $contentType) {
			$options[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option', $contentType->id, \Joomla\CMS\Language\Text::_( $contentType->name ) );
		}
		$fieldname = $elementid = 'content_type__au__';
		$attribs = ' onchange="filterFieldList(\'%s\', \'%s\', \'%s\');" class="use_select2_lib" ';
		$content_type_select = \Joomla\CMS\HTML\HTMLHelper::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', '', $elementid );


		// Create FIELD TYPE SELECTOR
		//$ALL = StringHelper::strtoupper(\Joomla\CMS\Language\Text::_( 'FLEXI_ALL' )) . ' : ';
		$fftypes = array();
		$fftypes[] = array('value'=>'', 'text'=>\Joomla\CMS\Language\Text::_( 'FLEXI_ALL' ) );
		//$fftypes[] = array('value' => 'BV', 'text' => $ALL . \Joomla\CMS\Language\Text::_('FLEXI_BACKEND_FIELDS'));
		//$fftypes[] = array('value' => 'C',  'text' => $ALL . \Joomla\CMS\Language\Text::_('FLEXI_CORE_FIELDS'));
		//$fftypes[] = array('value' => 'NC', 'text' => $ALL . \Joomla\CMS\Language\Text::_('FLEXI_CUSTOM_NON_CORE_FIELDS'));
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
		$fieldname = $elementid = 'field_type__au__';
		$attribs = ' class="use_select2_lib" onchange="filterFieldList(\'%s\', \'%s\', \'%s\');"';
		$field_type_select = flexicontent_html::buildfieldtypeslist($fftypes, $fieldname, '', ($_grouped ? 1 : 0), $attribs, $elementid);


		if (isset($layout->positions)) {
			$sort = array();
			$idsort = array();
			$sort = array('sortablecorefields', 'sortableuserfields');
			$count = -1;
			foreach ($layout->positions as $pos)
			{
				$count++;
				if (isset($layout->attributes[$count]['readonly'])) continue;
				$sort[] = 'sortable-' . $pos;
				$idsort[] = $pos;
			}
			$positions = implode(',', $idsort);
			$lists = json_encode($sort, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
			$document->addScriptDeclaration("
				FlexiForm.ready(() => fc_layout_init_positions(" . $lists . "));
			");
		}



		// ***
		// *** Load JS/CSS files
		// ***

		// Add css to document
		!\Joomla\CMS\Factory::getApplication()->getLanguage()->isRtl()
			? $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', array('version' => FLEXI_VHASH))
			: $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', array('version' => FLEXI_VHASH));
		!\Joomla\CMS\Factory::getApplication()->getLanguage()->isRtl()
			? $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x.css' : 'j3x.css'), array('version' => FLEXI_VHASH))
			: $document->addStyleSheet(\Joomla\CMS\Uri\Uri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x_rtl.css' : 'j3x_rtl.css'), array('version' => FLEXI_VHASH));

		// Add JS frameworks
		flexicontent_html::loadFramework('select2');
		flexicontent_html::loadFramework('prettyCheckable');
		flexicontent_html::loadFramework('flexi-lib');
		flexicontent_html::loadFramework('flexi-lib-form');

		// Load custom behaviours: form validation, popup tooltips
		\Joomla\CMS\HTML\HTMLHelper::_('behavior.formvalidator');  // load default validation JS to make sure it is overriden
		\Joomla\CMS\HTML\HTMLHelper::_('bootstrap.tooltip');

		// Add js function to overload the joomla submitform validation
		$document->addScript(\Joomla\CMS\Uri\Uri::root(true).'/components/com_flexicontent/assets/js/admin.js', array('version' => FLEXI_VHASH));
		$document->addScript(\Joomla\CMS\Uri\Uri::root(true).'/components/com_flexicontent/assets/js/validate.js', array('version' => FLEXI_VHASH));


		// *****************************
		// Get user's global permissions
		// *****************************

		$perms = FlexicontentHelperPerm::getPerm();

		if (!$perms->CanTemplates) {
			$app->enqueueMessage(\Joomla\CMS\Language\Text::_('FLEXI_NO_ACCESS'), 'warning');
			$app->redirect('index.php?option=com_flexicontent');
		}



		// ************************
		// Create Submenu & Toolbar
		// ************************

		// Create Submenu (and also check access to current view)
		FLEXIUtilities::ManagerSideMenu('CanTemplates');

		//create the toolbar
		$bar = \Joomla\CMS\Factory::getApplication()->getDocument()->getToolbar('toolbar');
		\Joomla\CMS\Toolbar\ToolbarHelper::title( \Joomla\CMS\Language\Text::_( 'FLEXI_EDIT_TEMPLATE' ), 'paint-brush thememanager' );
		if (!$ismodal) {
			\Joomla\CMS\Toolbar\ToolbarHelper::apply('templates.apply');
			\Joomla\CMS\Toolbar\ToolbarHelper::save('templates.save');
			\Joomla\CMS\Toolbar\ToolbarHelper::cancel('templates.cancel');
		} else {
			\Joomla\CMS\Toolbar\ToolbarHelper::apply('templates.apply_modal');
			echo $bar->render();
		}


		// Create / load layout parameters if not already done above
		if (!is_object($layout->params))
		{
			$jform = new \Joomla\CMS\Form\Form('com_flexicontent.template', array('control' => 'jform', 'load_data' => false));
			$jform->load($layout->params);
			$layout->params = $jform;
		}
		// ... values applied at the template form file

		// Viewing the editor must not regenerate CSS from saved/default parameters.
		// The model compiles the layout after saving its LESS configuration.
		// Loading translations has no compilation side effects.
		FLEXIUtilities::loadTemplateLanguageFile($folder);

		//print_r($layout);

		//assign data to template
		//print_r($conf);
		$this->conf = $conf;
		$this->layout = $layout;
		$this->fields = $fields;
		$this->user = $user;
		$this->type = $type;
		$this->folder = $folder;
		$this->positions = $positions;
		$this->used = $used;
		$this->fbypos = $fbypos;
		$this->use_jquery_sortable = $use_jquery_sortable;
		$this->content_type_select = $content_type_select;
		$this->field_type_select = $field_type_select;

		parent::display($tpl);
	}



	/** Compatibility entry points; compilation is initiated by the save model. */
	function check_xml_to_less($layout = null)
	{
		$model = new FlexicontentModelTemplate();
		$model->check_xml_to_less($layout);
	}

	function check_less_files($folder)
	{
		$model = new FlexicontentModelTemplate();
		$model->check_less_files($folder);
	}

}
?>
