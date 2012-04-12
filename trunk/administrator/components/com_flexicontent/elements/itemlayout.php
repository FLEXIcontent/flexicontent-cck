<?php
/**
 * @version 1.5 stable $Id$
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
require_once(JPATH_ROOT.DS.'libraries'.DS.'joomla'.DS.'html'.DS.'html'.DS.'select.php');
require_once(JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');

/**
 * Renders a author element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.0
 */
class JElementItemlayout extends JElement
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 * @since	1.5
	 */
	var	$_name = 'Itemlayout';

	function fetchElement($name, $value, &$node, $control_name)
	{
		$themes	= flexicontent_tmpl::getTemplates();
		$tmpls_all	= $themes->items ? $themes->items : array();
		$class 	= 'class="inputbox" onchange="activatePanel(this.value);"';
		
		$view	= JRequest::getVar('view');
		$controller	= JRequest::getVar('controller');
		$app = &JFactory::getApplication();
		$db =& JFactory::getDBO();
		
		// GET LIMITING to specific templates according to item's type, or according to type of new item
		$allowed_tmpls = array();
		$type_default_layout = '';
		if ( $view==FLEXI_ITEMVIEW || ($app->isAdmin() && 'items'==$controller) ){
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
				$tparams = new JParameter($typedata->attribs);
				$type_default_layout = $tparams->get('ilayout');
				$allowed_tmpls = $tparams->get('allowed_ilayouts');
				if ( empty($allowed_tmpls) )							$allowed_tmpls = array();
				else if ( ! is_array($allowed_tmpls) )		$allowed_tmpls = !FLEXI_J16GE ? array($allowed_tmpls) : explode("|", $allowed_tmpls);
				else																			$allowed_tmpls = $allowed_tmpls;
				if ( !in_array( $type_default_layout, $allowed_tmpls ) ) $allowed_tmpls[] = $type_default_layout;
				//echo "Allowed Templates: "; print_r($allowed_tmpls); echo "<br>\n";
			}
		}
		
		$tmpls = array();
		$lays = array();
		foreach ($tmpls_all as $tmpl) {
			if ( !count($allowed_tmpls) || in_array($tmpl->name, $allowed_tmpls) ) {
				$tmpls[] = $tmpl;
				$lays[] = $tmpl->name;
			}
		}
		$lays = implode("','", $lays);
		
		$doc 	= & JFactory::getDocument();
		$js 	= "
var tmpl = ['".$lays."'];	

function disablePanel(element) {
	if ( ! $('params-'+element) ) return;
	
	var panel 	= $('params-'+element).getNext();
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
	if ( ! $('params-'+element) ) return;
	
	var panel 	= $('params-'+element).getNext();
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
		
		$layouts = array();
		if ($view != 'type') {
			$layouts[] = JHTMLSelect::option('', JText::_( 'FLEXI_TYPE_DEFAULT' ) .' :: '. $type_default_layout .' ::' );
		}
		foreach ($tmpls as $tmpl) {
			$layouts[] = JHTMLSelect::option( $tmpl->name, ':: ' . $tmpl->name . ' ::');
		}
		
		return JHTMLSelect::genericList($layouts, $control_name.'['.$name.']', $class, 'value', 'text', $value, $control_name.$name);
	}
}
?>
