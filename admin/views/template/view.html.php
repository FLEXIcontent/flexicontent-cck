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

jimport('legacy.view.legacy');
use Joomla\String\StringHelper;

/**
 * View class for the FLEXIcontent templates screen
 */
class FlexicontentViewTemplate extends JViewLegacy
{

	function display($tpl = null)
	{
		// Initialise variables
		$app      = JFactory::getApplication();
		$jinput   = $app->input;
		$option   = $jinput->getCmd('option');
		$db       = JFactory::getDbo();
		$document = JFactory::getDocument();
		$user     = JFactory::getUser();

		$use_jquery_sortable = true;

		$type    = $jinput->getWord('type', 'items');
		$folder  = $jinput->getString('folder', 'table');
		$ismodal = $jinput->getInt('ismodal', 0);

		//Get data from the model
		$layout  = $this->get( 'Data');
		if (!$layout)
		{
			$app->redirect('index.php?option=com_flexicontent', JText::_( 'Template not found: <b>' ) . $jinput->getString('folder', 'table') . '</b>');
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
		$options[] = JHtml::_('select.option',  '',  JText::_( 'FLEXI_ALL' ) );
		foreach ($contentTypes as $contentType) {
			$options[] = JHtml::_('select.option', $contentType->id, JText::_( $contentType->name ) );
		}
		$fieldname = $elementid = 'content_type__au__';
		$attribs = ' onchange="filterFieldList(\'%s\', \'%s\', \'%s\');" class="use_select2_lib" ';
		$content_type_select = JHtml::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', '', $elementid );


		// Create FIELD TYPE SELECTOR
		//$ALL = StringHelper::strtoupper(JText::_( 'FLEXI_ALL' )) . ' : ';
		$fftypes = array();
		$fftypes[] = array('value'=>'', 'text'=>JText::_( 'FLEXI_ALL' ) );
		//$fftypes[] = array('value' => 'BV', 'text' => $ALL . JText::_('FLEXI_BACKEND_FIELDS'));
		//$fftypes[] = array('value' => 'C',  'text' => $ALL . JText::_('FLEXI_CORE_FIELDS'));
		//$fftypes[] = array('value' => 'NC', 'text' => $ALL . JText::_('FLEXI_CUSTOM_NON_CORE_FIELDS'));
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
			$jssort = array();
			$idsort = array();
			$sort[0] = 'sortablecorefields';
			$sort[1] = 'sortableuserfields';
			$i = 2;
			$count=-1;
			foreach ($layout->positions as $pos) {
				$count++;
				if ( isset($layout->attributes[$count]) && isset($layout->attributes[$count]['readonly']) ) {
					continue;
				}
				$sort[$i] 	= 'sortable-'.$pos;
				$idsort[$i] = $pos;
				$i++;
			}
			foreach ($idsort as $k => $v) {
				if ($k > 1) {
					$jssort[] = 'tmpls_fcfield_store_ordering(jQuery("#sortable-'.$v.'"))';
				}
			}
			$positions = implode(',', $idsort);

			$jssort = implode("; ", $jssort);
			$sortable_ids = "#".implode(",#", $sort);

			$js = "
			jQuery(function() {
				my = jQuery( \"$sortable_ids\" ).sortable({
					connectWith: \"".$sortable_ids."\",
					update: function(event, ui) {
						if (ui.sender)
							tmpls_fcfield_store_ordering(jQuery(ui.sender));
						else
							tmpls_fcfield_store_ordering(jQuery(ui.item).parent());
					}
				});
				tmpls_fcfield_init_ordering();
			});
			function tmpls_fcfield_store_ordering(parent_element) {
				hidden_id = '#'+jQuery.trim(parent_element.attr('id').replace('sortable-',''));
				fields = new Array();
				i = 0;
				parent_element.children('li').each(function(){
					fields[i++] = jQuery(this).attr('id').replace('field_', '');
				});
				jQuery(hidden_id).val(fields.join(','))
			}
			";

			$js .= '
			var fieldListFilters = new Array( "content_type", "field_type" );
			function filterFieldList (containerID, method, group)
			{
				var needed_classes = "";
				for (i=0; i<fieldListFilters.length; i++)
				{
					filter_name = fieldListFilters[i];

					var filter_val = jQuery("#" + filter_name + "_" + group).val();
					if (filter_val) {
						needed_classes += "."+filter_name+"_"+filter_val;
					}
				}

				if (needed_classes) {
					(method=="hide") ?
						jQuery("#"+containerID).find("li").show().filter(":not("+needed_classes+")").hide() :
						jQuery("#"+containerID).find("li").css({"color":"red"}).filter(":not("+needed_classes+")").css({"color":"black"});
				} else {
					(method=="hide") ?
						jQuery("#"+containerID).find("li").show() :
						jQuery("#"+containerID).find("li").css({"color":"black"});
				}
			}

			';

			$document->addScriptDeclaration( $js );
		}



		// ***
		// *** Load JS/CSS files
		// ***

		// Add css to document
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css', array('version' => FLEXI_VHASH))
			: $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend_rtl.css', array('version' => FLEXI_VHASH));
		!JFactory::getLanguage()->isRtl()
			? $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x.css' : 'j3x.css'), array('version' => FLEXI_VHASH))
			: $document->addStyleSheet(JUri::base(true).'/components/com_flexicontent/assets/css/' . (FLEXI_J40GE ? 'j4x_rtl.css' : 'j3x_rtl.css'), array('version' => FLEXI_VHASH));

		// Add JS frameworks
		flexicontent_html::loadJQuery();
		flexicontent_html::loadFramework('select2');
		flexicontent_html::loadFramework('touch-punch');
		flexicontent_html::loadFramework('prettyCheckable');
		flexicontent_html::loadFramework('flexi-lib');
		flexicontent_html::loadFramework('flexi-lib-form');

		// Load custom behaviours: form validation, popup tooltips
		JHtml::_('behavior.formvalidator');  // load default validation JS to make sure it is overriden
		JHtml::_('bootstrap.tooltip');

		// Add js function to overload the joomla submitform validation
		$document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/admin.js', array('version' => FLEXI_VHASH));
		$document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/validate.js', array('version' => FLEXI_VHASH));


		// *****************************
		// Get user's global permissions
		// *****************************

		$perms = FlexicontentHelperPerm::getPerm();

		if (!$perms->CanTemplates) {
			$app->redirect('index.php?option=com_flexicontent', JText::_( 'FLEXI_NO_ACCESS' ));
		}



		// ************************
		// Create Submenu & Toolbar
		// ************************

		// Create Submenu (and also check access to current view)
		FLEXIUtilities::ManagerSideMenu('CanTemplates');

		//create the toolbar
		$bar = JToolbar::getInstance('toolbar');
		JToolbarHelper::title( JText::_( 'FLEXI_EDIT_TEMPLATE' ), 'eye' );
		if (!$ismodal) {
			JToolbarHelper::apply('templates.apply');
			JToolbarHelper::save('templates.save');
			JToolbarHelper::cancel('templates.cancel');
		} else {
			JToolbarHelper::apply('templates.apply_modal');
			echo $bar->render();
		}


		// Check that less files for all layouts of current template (=folder) exist and are up-to-date
		$this->check_less_files($folder);

		// Create / load layout parameters if not already done above
		if (!is_object($layout->params))
		{
			$jform = new JForm('com_flexicontent.template', array('control' => 'jform', 'load_data' => false));
			$jform->load($layout->params);
			$layout->params = $jform;
		}
		// ... values applied at the template form file

		// Load the template again but ... this time allow triggering less compiling if needed
		flexicontent_tmpl::getTemplates($folder, $skip_less=false);

		// Load language file (this will also load the template and also trigger less compiling)
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
		$this->jssort = $jssort;
		$this->positions = $positions;
		$this->used = $used;
		$this->fbypos = $fbypos;
		$this->use_jquery_sortable = $use_jquery_sortable;
		$this->content_type_select = $content_type_select;
		$this->field_type_select = $field_type_select;

		parent::display($tpl);
	}



	/*
	 * Check that the LESS layout file that stores parameter values exists and is up to date (its modification time after XML file modification)
	 */
	function check_xml_to_less($layout = null)
	{
		$tmpldir = JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'templates'.DS.$layout->name;

		// ****************************************************************************************************************************
		// Create / Update the "variable defaults" FILEs (using defaults from XML files): config_auto_item.less / config_auto_item.less
		// ****************************************************************************************************************************

		$view = $layout->view;
		$less_data = "/* This is created automatically, do NOT edit this manually! \nThis is used by _layout_type_ layout to save parameters as less variables. \nNOTE: Make sure that this is imported by 'config.less' \n to make a parameter be a LESS variable, edit parameter in _layout_type_.xml and add cssprep=\"less\" \n created parameters will be like: @FCLL_parameter_name: value; */\n\n";
		$_less_auto = false;
		if ( !JFile::exists($tmpldir . '/less/include/config_auto_'.$view.'.less') || filemtime($tmpldir . '/less/include/config_auto_'.$view.'.less') < filemtime($tmpldir . '/'.$view.'.xml') ) {
			$_less_auto = $tmpldir . '/less/include/config_auto_'.$view.'.less';
			file_put_contents($_less_auto, str_replace("FCLL_", "FCI_", str_replace("_layout_type_", $view, $less_data)));
		}
		if (!$_less_auto) return;

		$layout_type = $view === 'item' ? 'items' : 'category';
		$model = new FlexicontentModelTemplate();
		$model->setLayoutType($layout_type);
		$_layout = $model->getData();
		$_conf = $model->getLayoutConf();
		$_attribs = $_conf->attribs;
		$model->storeLessConf($layout->name, $cfgname='', $layout_type, $_attribs);
	}

	/*
	 * Check that less files for all layouts of current template (=folder) exist and are up-to-date
	 */
	function check_less_files($folder)
	{
		// **************************************************************************************
		// Get both single item ('items') and multi-item ('category') layouts of current template
		// 'folder' and apply Template Parameters values into the form fields structures
		// **************************************************************************************

		$tmpl = flexicontent_tmpl::getTemplates( $folder, $skip_less=true );

		$tmpldir = JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'templates'.DS.$folder;

		// Create less folders if they do not exist already
		if ( !JFolder::exists( $tmpldir . '/less' ) ) if ( !JFolder::create( $tmpldir . '/less') )  JError::raiseWarning(100, JText::_('Unable to create "/less/" folder'));

		// Abort if directory creation failed
		if ( ! JFolder::exists( $tmpldir . '/less' ) ) return;


		// ***********************************************************************
		// Create CUSTOM config.less, that is include by item.less / category.less
		// ************************************************************ ***********

		if ( !JFolder::exists( $tmpldir . '/less/include' ) ) if ( !JFolder::create( $tmpldir . '/less/include') )  JError::raiseWarning(100, JText::_('Unable to create "/less/include" folder'));
		if ( !JFile::exists($tmpldir . '/less/include/config.less') ) {
			file_put_contents($tmpldir . '/less/include/config.less', "/* Place your less variables, mixins, etc, here \n1. This is commonly imported by files: item.less and category.less, \n2. If you add extra less file imports, then place files \ninside same folder for automatic compiling to be triggered */\n\n@import 'config_auto_item.less';\n@import 'config_auto_category.less';\n");
		}


		// *************************************************************************
		// Create files item.less / category.less by COPYING item.css / category.css
		// *************************************************************************

		$less_files = array('/css/item.css'=>'/less/item.less', '/css/category.css'=>'/less/category.less');
		foreach($less_files as $css_name => $less_name) {
			if ( !JFile::exists($tmpldir . $css_name) )  continue;  // Do not try to copy CSS file that does not exist
			if ( !JFile::exists($tmpldir . $less_name) ) {
				if ( !JFile::copy($tmpldir.$css_name, $tmpldir.$less_name) ) {
					JError::raiseWarning(100, JText::_('Unable to create file: "'.$tmpldir.$less_name.'"'));
				} else {
					$file_data = "@import 'include/config.less';\n\n";
					$file_data .= preg_replace("/[ \t]*\*zoom[\s]*:[\s]*expression[^\r\n]+[\r\n]+/u", "", file_get_contents($tmpldir.$less_name));  // copy and replace old invalid code
					file_put_contents($tmpldir.$less_name, $file_data);
				}
			}
		}


		// Check that the LESS layout files that stores parameter values exist
		// and they are up to date (their modification time after XML file modification)

		$_layout = !isset($tmpl->items->$folder)  ?  false  :  $tmpl->items->$folder;
		$this->check_xml_to_less($_layout);

		$_layout = !isset($tmpl->category->$folder)  ?  false  :  $tmpl->category->$folder;
		$this->check_xml_to_less($_layout);
	}
}
?>