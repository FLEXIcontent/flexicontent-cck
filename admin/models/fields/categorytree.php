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

jimport('cms.html.html');      // JHtml
jimport('cms.html.select');    // JHtmlSelect

jimport('joomla.form.helper'); // JFormHelper
JFormHelper::loadFieldClass('list');   // JFormFieldList

/**
 * Form Field class for the Joomla Framework.
 *
 * @package		Joomla.Administrator
 * @subpackage	com_categories
 * @since		1.6
 */
class JFormFieldCategorytree extends JFormFieldList
{
	/**
	 * The form field type.
	 *
	 * @var		string
	 * @since	1.6
	 */
	protected $type = 'Categorytree';

	/**
	 * Method to get the field input markup.
	 *
	 * @return	string	The field input markup.
	 * @since	1.6
	 */
	protected function getInput()
	{
		// Initialize variables.
		$html = array();
		$attr = '';

		if(!is_array($this->value)) $this->value = array($this->value);
		// Initialize some field attributes.
		$attr .= $this->element['class'] ? ' class="'.(string) $this->element['class'].'"' : '';

		// To avoid user's confusion, readonly="true" should imply disabled="true".
		if ( (string) $this->element['readonly'] == 'true' || (string) $this->element['disabled'] == 'true') {
			$attr .= ' disabled="disabled"';
		}

		$attr .= $this->element['size'] ? ' size="'.(int) $this->element['size'].'"' : '';
		$attr .= $this->multiple ? ' multiple="multiple"' : '';

		// Initialize JavaScript field attributes.
		$attr .= $this->element['onchange'] ? ' onchange="'.(string) $this->element['onchange'].'"' : '';

		// Get the field options.
		$options = (array) $this->getOptions();

		// Create a read-only list (no name) with a hidden input to store the value.
		if ((string) $this->element['readonly'] == 'true') {
			$html[] = '<select name="" '.trim($attr).'>';
			foreach($options as $opt) {
				$disabled = '';
				$selected = '';
				if( @$opt->disable )
					$disabled = ' disabled="disabled"';
				if(in_array($opt->value, $this->value))
					$selected = ' selected="selected"';
				$html[] = '<option value="'.$opt->value.'"'.$disabled.$selected.'>'.$opt->text.'</option>';
			}
			$html[] = '</select>';
			foreach($this->value as $v)
				$html[] = '<input type="hidden" name="'.$this->name.'" value="'.$value.'"/>';
		}
		// Create a regular list.
		else {

			//$html[] = JHtml::_('select.genericlist', $options, $this->name, trim($attr), 'value', 'text', $this->value, $this->id);
			$html[] = '<select name="'.$this->name.'" '.trim($attr).'>';
			foreach($options as $opt) {
				$disabled = '';
				$selected = '';
				if( @$opt->disable )
					$disabled = ' disabled="disabled"';
				if(in_array($opt->value, $this->value))
					$selected = ' selected="selected"';
				$html[] = '<option value="'.$opt->value.'"'.$disabled.$selected.'>'.$opt->text.'</option>';
			}
			$html[] = '</select>';
		}

		return implode("\n", $html);
	}

	/**
	 * Method to get the field options.
	 *
	 * @return	array	The field option objects.
	 * @since	1.6
	 */
	protected function getOptions()
	{
		global $globalcats;
		$jinput = JFactory::getApplication()->input;
		$user   = JFactory::getUser();
		$cid    = $jinput->getInt('cid', 0);

		$usercats = array();
		$viewallcats = FlexicontentHelperPerm::getPerm()->ViewAllCats;

		$top = (int) $this->element['top'];
		$published = $this->element['published'] && $this->element['published']!='false' ? true : false;
		$filter = $this->element['filter'] && $this->element['filter']!='false' ? true : false;

		$catlist 	= array();
		if($top == 1)
		{
			$obj = new stdClass;
			$obj->value = $ROOT_CATEGORY_ID = 1;
			$obj->level = 0;
			$obj->text = JText::_( 'FLEXI_TOPLEVEL' );
			$catlist[] 	= $obj;
		}
		else if($top == 2)
		{
			$obj = new stdClass;
			$obj->value = '';
			$obj->level = 0;
			$obj->text = JText::_( 'FLEXI_SELECT_CATEGORY' );
			$catlist[] 	= $obj;
		}

		foreach ($globalcats as $item) {
			if ( !$published || ($published && $item->published) )
			{
				//if (($jinput->getCmd('controller') == 'categories') && ($jinput->getCmd('task') == 'edit') && ($cid[0] == $item->id)) {
				if (($jinput->getCmd('controller') == 'categories') && ($jinput->getCmd('task') == 'edit') && ($item->lft >= @$globalcats[$cid[0]]->lft && $item->rgt <= @$globalcats[$cid[0]]->rgt)) {
					if ($top == 2)
					{
						if ($cid[0] != $item->id)
						{
							$obj = new stdClass;
							$obj->value = $item->id;
							$obj->text = $item->treename;
							$obj->level = $item->level;
							$catlist[] = $obj;
						}
						else
						{
							$catlist[] = JHtml::_('select.option', $item->id, $item->treename, 'value', 'text', true);
						}
					}
				}
				else if ($filter)
				{
					if ( !in_array($item->id, $usercats) )
					{
						// Only disable cats in the list else don't show them at all
						if ($viewallcats)
						{
							$catlist[] = JHtml::_( 'select.option', $item->id, $item->treename, 'value', 'text', true );
						}
					}
					else
					{
						$item->treename = str_replace("&nbsp;", "_", strip_tags($item->treename));
						$catlist[] = JHtml::_( 'select.option', $item->id, $item->treename );
					}
				}
				else
				{
					$obj = new stdClass;
					$obj->value = $item->id;
					$obj->text = $item->treename;
					$obj->level = $item->level;
					$catlist[] = $obj;
				}
			}
		}

		// Merge any additional options in the XML definition.
		$catlist = array_merge(parent::getOptions(), $catlist);
		return $catlist;
	}
}
