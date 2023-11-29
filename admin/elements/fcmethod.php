<?php
/**
 * @version 1.5 stable $Id: fcmethod.php 967 2011-11-21 00:01:36Z ggppdk $
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

jimport('cms.html.html');      // JHtml
jimport('cms.html.select');    // JHtmlSelect

jimport('joomla.form.helper'); // JFormHelper
JFormHelper::loadFieldClass('radio');   // JFormFieldRadio

/**
 * Renders the FC-method radio element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JFormFieldFcmethod extends JFormFieldRadio
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	
  var $type = 'Fcmethod';

	function getInput()
	{
		$doc = JFactory::getDocument();

		$node = & $this->element;
		$attributes = get_object_vars($node->attributes());
		$attributes = $attributes['@attributes'];

		$value = $this->value;
		
		$fieldname	= $this->name;
		$element_id = $this->id;
		
		//$disabled_ff = explode(',', @$attributes['disabled_ff']);
		$disabled_ff = @$attributes['disabled_ff'];
		
		if ($disabled_ff) {
			$dff_idtag = FLEXI_J16GE ? 'jform_params_'.$disabled_ff : 'params'.$disabled_ff;
			$js 	= "
				function filterCategories_".$disabled_ff."(method)
				{
					var cats = jQuery('#".$dff_idtag."');
					var options = cats.find('option');
					if (method == 1) {
						cats.setAttribute('disabled', 'disabled');
						/*options.each(function(el){
								el.setAttribute('selected', 'selected');
						});*/
					} else {
						cats.setAttribute('disabled', '');
					}
				}

				jQuery(document).ready(function(){
					filterCategories_".$disabled_ff."('".$value."');			
				});
			";
			$doc->addScriptDeclaration($js);
			
			$class = 'class="inputbox" onchange="filterCategories_'.$disabled_ff.'(this.value);"';
		} else {
			$class = 'class="inputbox"';
		}
		
		// prepare the options 
		$options = array(); 
		$options[] = JHtml::_('select.option', '1', JText::_('FLEXI_ALL')); 
		$options[] = JHtml::_('select.option', '2', JText::_('FLEXI_EXCLUDE')); 
		$options[] = JHtml::_('select.option', '3', JText::_('FLEXI_INCLUDE')); 
		
		$html = JHtml::_('select.radiolist', $options, $fieldname, $class, 'value', 'text', $value, $element_id);
		$html = '<fieldset id="'.$element_id.'" class="radio">'.$html.'</fieldset>';
		
		return $html;
	}
}