<?php
/**
 * @package     Joomla.Platform
 * @subpackage  Form
 *
 * @copyright   Copyright (C) 2005 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_PLATFORM') or die;

jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.file');
if (FLEXI_J16GE) {
	JFormHelper::loadFieldClass('list');
}

/**
 * Renders an HTML select list of FLEXIcontent layouts
 *
 * @package     FLEXIcontent
 * @subpackage  Form
 * @since       1.5
 */
class JFormFieldFclayout extends JFormFieldList
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  1.5
	 */
	public $type = 'Fclayout';

	/**
	 * Method to get the list of files for the field options.
	 * Specify the target directory with a directory attribute
	 * Attributes allow an exclude mask and stripping of extensions from file name.
	 * Default attribute may optionally be set to null (no file) or -1 (use a default).
	 *
	 * @return  array  The field option objects.
	 *
	 * @since   1.5
	 */
	protected function getInput()
	{
		// element params
		if (FLEXI_J16GE) {
			$node = & $this->element;
			$attributes = get_object_vars($node->attributes());
			$attributes = $attributes['@attributes'];
		} else {
			$attributes = & $node->_attributes;
		}
		// value
		$value = FLEXI_J16GE ? $this->value : $value;
		$value = $value ? $value : $attributes['default'];
		
		// Get current extension and id being edited
		$view   = JRequest::getVar('view');
		$option = JRequest::getVar('option');
		if ($option == 'com_modules' || $option == 'com_advancedmodules') $view = 'module';
		
		$cid = JRequest::getVar( 'cid', array(0), $hash='default', 'array' );
		JArrayHelper::toInteger($cid, array(0));
		$pk = $cid[0];
		if (!$pk) $pk = JRequest::getInt( 'id', 0 );
		
		
		// Initialize variables.
		$options = array();
		
		// Initialize some field attributes.
		$filter   = (string) @ $attributes['filter'];
		$exclude  = (string) @ $attributes['exclude'];
		$stripExt = (string) @ $attributes['stripext'];
		$hideNone    = (string) @ $attributes['hide_none'];
		$hideDefault = (string) @ $attributes['hide_default'];
		
		// Get the path which contains layouts
		$directory = (string) @ $attributes['directory'];
		$path = (!is_dir($directory) ? JPATH_ROOT : '') . $directory;
		
		// For using directory in url
		$directory = str_replace('\\', '/', $directory);
		
		// Prepend some default options based on field attributes.
		if (!$hideNone)    $options[] = JHTML::_('select.option', '-1', JText::alt('JOPTION_DO_NOT_USE', preg_replace('/[^a-zA-Z0-9_\-]/', '_', $this->fieldname)));
		if (!$hideDefault) $options[] = JHTML::_('select.option', '', JText::alt('JOPTION_USE_DEFAULT', preg_replace('/[^a-zA-Z0-9_\-]/', '_', $this->fieldname)));
		
		// Get a list of files in the search path with the given filter.
		$files = JFolder::files($path, $filter);
		
		// Build the options list from the list of files.
		if ( is_array($files) )  foreach ($files as $file)
		{
			// Check to see if the file is in the exclude mask.
			if ( $exclude && preg_match(chr(1) . $exclude . chr(1), $file) )  continue;
			
			// If the extension is to be stripped, do it.
			if ($stripExt)  $file = JFile::stripExt($file);
			
			$options[] = JHTML::_('select.option', $file, $file);
			$layouts[] = $file;
		}
		
		// Merge any additional options in the XML definition.
		if (FLEXI_J16GE) $options = array_merge(parent::getOptions(), $options);
		
		// Element name and id
		$_name	= FLEXI_J16GE ? $this->fieldname : $name;
		$fieldname	= FLEXI_J16GE ? $this->name : $control_name.'['.$name.']';
		$element_id = FLEXI_J16GE ? $this->id : $control_name.$name;
		
		// Add tag attributes
		$attribs = !FLEXI_J16GE ? ' style="float:left;" ' : '';
		if (@$attributes['multiple']=='multiple' || @$attributes['multiple']=='true' ) {
			$attribs .= ' multiple="multiple" ';
			$attribs .= (@$attributes['size']) ? ' size="'.@$attributes['size'].'" ' : ' size="6" ';
			$fieldname .= !FLEXI_J16GE ? "[]" : "";  // NOTE: this added automatically in J2.5
		} else {
			$attribs .= 'class="inputbox"';
		}
		
		// Container of parameters
		$tmpl_container = (string) @ $attributes['tmpl_container'];
		// Add JS code to display parameters, either via 'file' or 'inline'
		// For modules we can not use method 'file' (external xml file), because J2.5+ does form validation on the XML file ...
		$params_source = (string) @ $attributes['params_source'];
		$container_sx = FLEXI_J16GE ? '-options' : '-page';
		
if ( ! @$attributes['skipparams'] ) {
		$doc 	= JFactory::getDocument();
		$js 	= "

".($params_source=="file" ? "

function fc_getLayout(el) {
	var layouts = new Array('".implode("','", $layouts)."');
	for (i=0; i<layouts.length; i++) {
		var container = $('".$tmpl_container."_'+ layouts[i] + '".$container_sx."');
		if (container) container.getParent().setStyle('display', 'none');
  }
	
	var layout_name = el.value;
	var panel_header = $('".$tmpl_container."' + '".$container_sx."');
	var panel = panel_header.getNext();
	var _loading_img = '<img src=\"components/com_flexicontent/assets/images/ajax-loader.gif\" align=\"center\">';
	panel_header.set('html', _loading_img);
	panel_header.set('html', '<a href=\"javascript:void(0);\"><span>Layout: '+_loading_img+'</span></a>');
	panel.set('html', '');
	new Request.HTML({
		url: 'index.php?option=com_flexicontent&task=templates.getlayoutparams&ext_view=".$view."&ext_id=".$pk."&directory=".$directory."&layout_name='+layout_name+'&format=raw',
		method: 'get',
		update: panel,
		evalScripts: false,
		onComplete:function(response) {
			panel_header.set('html', '<a href=\"javascript:void(0);\"><span>Layout: <small>'+layout_name+'</small></span></a>');
			/* nothing to do */
		}
	}).send();
}

":"

function fc_getLayout(el) {
	var container = $('" . $tmpl_container . $container_sx ."');
	if (container) container.getParent().setStyle('display', 'none');
	
	var layouts = new Array('".implode("','", $layouts)."');
	for (i=0; i<layouts.length; i++) {
		var container = $('" . $tmpl_container."_' + layouts[i] + '".$container_sx."');
		if (container) container.getParent().setStyle('display', 'none');
  }
	
  var layout_name = el.value;
  var container = $('".$tmpl_container."_'+ layout_name + '".$container_sx."');
  if (container) container.getParent().setStyle('display', '');
}

")."

window.addEvent('domready', function(){
	fc_getLayout($('jform_params_".$_name."'));
});

window.addEvent('domready', function() {
	$$('#jform_params_".$_name."').addEvent('change', function(){
		fc_getLayout(this);
	});
});

";
		$doc->addScriptDeclaration($js);
}
		
		// Create form element
		return JHTML::_('select.genericlist', $options, $fieldname, $attribs, 'value', 'text', $value, $element_id);
	}
}
