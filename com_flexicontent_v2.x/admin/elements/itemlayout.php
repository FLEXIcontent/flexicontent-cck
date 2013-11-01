<?php
/**
 * @version 1.5 stable $Id: itemlayout.php 967 2011-11-21 00:01:36Z ggppdk $
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

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');
require_once(JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');
if (FLEXI_J16GE) {
	jimport('joomla.html.html');
	jimport('joomla.form.formfield');
	jimport('joomla.form.helper');
	JFormHelper::loadFieldClass('list');
} else {
	require_once(JPATH_ROOT.DS.'libraries'.DS.'joomla'.DS.'html'.DS.'html'.DS.'select.php');
}

/**
 * Renders a author element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.0
 */
class JFormFieldItemlayout extends JFormFieldList
{
	/**
	 * The form field type.
	 *
	 * @var		string
	 * @since	1.6
	 */
	protected $type = 'Itemlayout';

	protected function getInput()
	{
		if (FLEXI_J16GE) {
			$node = & $this->element;
			$attributes = get_object_vars($node->attributes());
			$attributes = $attributes['@attributes'];
		} else {
			$attributes = & $node->_attributes;
		}
		
		$themes	= flexicontent_tmpl::getTemplates();
		$tmpls_all	= $themes->items ? $themes->items : array();
		$value = FLEXI_J16GE ? $this->value : $value;
		
		$view	= JRequest::getVar('view');
		$controller	= JRequest::getVar('controller');
		$app = JFactory::getApplication();
		$db  = JFactory::getDBO();
		$cparams = JComponentHelper::getParams('com_flexicontent');
		
		// GET LIMITING to specific templates according to item's type, or according to type of new item
		$allowed_tmpls = array();
		$all_tmpl_allowed = true;
		$type_default_layout = '';
		$type_default_layout_mobile = '';
		if ( $view==FLEXI_ITEMVIEW || ($app->isAdmin() && 'items'==$controller) )
		{
			// Get item id
			if (!$app->isAdmin()) {
				// FRONTEND, use "id" from request
				$pk = JRequest::getVar('id', 0, 'default', 'int');
			} else {
				// BACKEND, use "cid" array from request
				$cid = JRequest::getVar( 'cid', array(0), $hash='default', 'array' );
				$pk = (int)$cid[0];
			}
			
			// Get type attibutes
			if ($pk) {
				$query = 'SELECT t.id as id, t.attribs attribs'
					. ' FROM #__flexicontent_items_ext as ie'
					. ' JOIN #__flexicontent_types as t ON ie.type_id=t.id'
					. ' WHERE ie.item_id = ' . (int)$pk;
			} else {
				$typeid = (int)JRequest::getInt('typeid', 0);
				$query = 'SELECT t.id,t.attribs'
					. ' FROM #__flexicontent_types as t'
					. ' WHERE t.id = ' . (int)$typeid;
			}
			$db->setQuery($query);
			$typedata = $db->loadObject();
			
			// Finally get allowed templates
			if ($typedata) {
				$tparams = FLEXI_J16GE ? new JRegistry($typedata->attribs) : new JParameter($typedata->attribs);
				$type_default_layout = $tparams->get('ilayout', 'default');
				$type_default_layout_mobile = $tparams->get('ilayout_mobile', JText::_('FLEXI_USE_DESKTOP'));
				$allowed_tmpls = $tparams->get('allowed_ilayouts');
				if ( empty($allowed_tmpls) )							$allowed_tmpls = array();
				else if ( ! is_array($allowed_tmpls) )		$allowed_tmpls = !FLEXI_J16GE ? array($allowed_tmpls) : explode("|", $allowed_tmpls);
				$all_tmpl_allowed = count($allowed_tmpls) == 0;
				if ( !in_array( $type_default_layout, $allowed_tmpls ) ) $allowed_tmpls[] = $type_default_layout;
				
				$use_mobile_layouts = $cparams->get('use_mobile_layouts', 0 );
				if ($use_mobile_layouts && $type_default_layout_mobile)
					if ( !in_array( $type_default_layout_mobile, $allowed_tmpls ) ) $allowed_tmpls[] = $type_default_layout_mobile;
				//echo "Allowed Templates: "; print_r($allowed_tmpls); echo "<br>\n";
			}
		}
		
		$tmpls = array();
		$lays = array();
		foreach ($tmpls_all as $tmpl) {
			if ( $all_tmpl_allowed || in_array($tmpl->name, $allowed_tmpls) ) {
				$tmpls[] = $tmpl;
				$lays[] = $tmpl->name;
			}
		}
		$lays = implode("','", $lays);
		
		if ( @$attributes['enableparam'] ) {
			$cparams = JComponentHelper::getParams( 'com_flexicontent' );
			if ( !$cparams->get($attributes['enableparam']) ) return FLEXI_J16GE ? '' : JText::_('FLEXI_DISABLED');
		}
		
if ( ! @$attributes['skipparams'] ) {
		$doc 	= JFactory::getDocument();
		$js 	= "
var tmpl = ['".$lays."'];	

function disablePanel(element) {
	if ( ! $(element+'-attribs-options') ) return;
	
	var panel 	= $(element+'-attribs-options').getNext();
	var selects = panel.getElements('select');
	var inputs 	= panel.getElements('input');
	panel.getParent().addClass('pane-disabled');
	selects.each(function(el){
		el.setProperty('disabled', 'disabled');
	});
	inputs.each(function(el){
		el.setProperty('disabled', 'disabled');
	});
	panel.getParent().setStyle('display','none');
}

function enablePanel(element) {
	if ( ! $(element+'-attribs-options') ) return;
	
	var panel 	= $(element+'-attribs-options').getNext();
	var selects = panel.getElements('select');
	var inputs 	= panel.getElements('input');
	panel.getParent().removeClass('pane-disabled');
	selects.each(function(el){
    	el.setProperty('disabled', '');
	});
	inputs.each(function(el){
    	el.setProperty('disabled', '');
	});
	panel.getParent().setStyle('display','');
}

function activatePanel(active) {
	var inactives = tmpl.filter(function(item, index){
		return item != active;
	});

	inactives.each(function(el){
		disablePanel(el);
	});
	
	if (active) {
		enablePanel(active);
		if ( $('__content_type_default_layout__') ) $('__content_type_default_layout__').setStyle('display','none');
	} else {
		if ( $('__content_type_default_layout__') ) $('__content_type_default_layout__').setStyle('display','');
	}
}
window.addEvent('domready', function() {
	activatePanel('".$value."');
});
";
		$doc->addScriptDeclaration($js);
}
		
		$layouts = array();
		if ($view != 'type') {
			$type_layout = ($attributes['name'] == 'ilayout_mobile') ? $type_default_layout_mobile : $type_default_layout;
			$layouts[] = JHTMLSelect::option('', JText::_( 'FLEXI_TYPE_DEFAULT' ) .' :: '. $type_layout .' ::' );
		}
		else if (  @$attributes['firstoption'] ) {
			$layouts[] = JHTMLSelect::option('', JText::_( $attributes['firstoption'] ));
		}
		foreach ($tmpls as $tmpl) {
			$layouts[] = JHTMLSelect::option( $tmpl->name, ':: ' . $tmpl->name . ' ::');
		}
		
		$fieldname	= FLEXI_J16GE ? $this->name : $control_name.'['.$name.']';
		$element_id = FLEXI_J16GE ? $this->id : $control_name.$name;
		
		$attribs = !FLEXI_J16GE ? ' style="float:left;" ' : '';
		if (@$attributes['multiple']=='multiple' || @$attributes['multiple']=='true' ) {
			$attribs .= ' multiple="true" ';
			$attribs .= (@$attributes['size']) ? ' size="'.@$attributes['size'].'" ' : ' size="6" ';
			$fieldname .= !FLEXI_J16GE ? "[]" : "";  // NOTE: this added automatically in J2.5
		}
		if (@$attributes['class']) {
			$attribs .= 'class="'.$attributes['class'].'"';
		}
		
		if ( ! @$attributes['skipparams'] )
		{
			$attribs .= ' onchange="activatePanel(this.value);"';
		}
		
		return JHTML::_('select.genericlist', $layouts, $fieldname, $attribs, 'value', 'text', $value, $element_id);
	}
	
	
	function getLabel()
	{
		if (FLEXI_J16GE) {
			$node = & $this->element;
			$attributes = get_object_vars($node->attributes());
			$attributes = $attributes['@attributes'];
		} else {
			$attributes = & $node->_attributes;
		}
		
		if ( @$attributes['enableparam'] ) {
			$cparams = JComponentHelper::getParams( 'com_flexicontent' );
			if ( !$cparams->get($attributes['enableparam']) ) return '';
		}
		
		$label = $this->element['label'];
		$class = "hasTip flex_label"; $title = "";
		if ($this->element['description']) {
			$class = "hasTip flexi_label";
			$title = JText::_($label)."::".JText::_($this->element['description']);
		}
		return '<label style=""  class="'.$class.'" title="'.$title.'" >'.JText::_($label).'</label> &nbsp; ';
	}
}
?>
