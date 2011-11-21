<?php
// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');
jimport('joomla.html.html');
jimport('joomla.form.formfield');

if(!class_exists('FLEXIUtilities')) {
	require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.utilities.php');
}
if(!class_exists('flexicontent_cats')) {
	require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.categories.php');
}
class JFormFieldCategories extends JFormField{
	/**
	 * The field type.
	 *
	 * @var		string
	 */
	public $type = 'Categories';
	//public $group = 'request';

	/**
	 * Method to get a list of options for a list input.
	 *
	 * @return	array		An array of JHtml options.
	 */
	protected function getInput() {
		$fieldName	= $this->name;
		$fieldValue	= $this->value;
		$tree = flexicontent_cats::getCategoriesTree();
		$html = "<select name=\"$fieldName\">\n";
		foreach($tree as $ll) {
			if($fieldValue==$ll->id) $html .= "<option value=\"".$ll->id."\" selected=\"selected\">".$ll->title."</option>\n";
			else $html .= "<option value=\"".$ll->id."\">".$ll->title."</option>\n";
		}
		$html .= "</select>\n";
		return $html;
	}
}
?>