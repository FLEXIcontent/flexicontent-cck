<?php
/**
 * @version 0.6.0 stable $Id: default.php yannick berges
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2015 Berges Yannick - www.com3elles.com
 * @license GNU/GPL v2
 
 * special thanks to ggppdk and emmanuel dannan for flexicontent
 * special thanks to my master Marc Studer
 
 * This is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 **/
// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;

jimport('cms.html.html');      // JHtml
jimport('cms.html.select');    // \Joomla\CMS\HTML\Helpers\Select
jimport('joomla.form.field');  // \Joomla\CMS\Form\FormField

//jimport('joomla.form.helper'); // \Joomla\CMS\Form\FormHelper
//\Joomla\CMS\Form\FormHelper::loadFieldClass('...');   // \Joomla\CMS\Form\FormField...

class JFormFieldMicrodatatype extends \Joomla\CMS\Form\FormField {

	protected $type = 'microdatatype';
	protected $_inherited;

	// getLabel() left out

	public function getInput()
	{
		$node = & $this->element;
		$attributes = get_object_vars($node->attributes());
		$attributes = $attributes['@attributes'];
		
		// Get microdata types
		static $types = null;
		
		if ($types === null)
		{
			jimport('joomla.microdata.microdata');
			$jm = new \Joomla\CMS\Microdata\Microdata();
			$jm_types = $jm->getTypes();
			$types = array_keys($jm_types);
		}
		
		/*$types = array(
			'NewsArticle',
			'Person',
			'Product',
			'Event',
			'Recipe',
			'Organization',
			'Movie',
			'Book',
			'Review',
			'SoftwareApplication'
		);*/
		
		$first_option = @$attributes['first_option'];
		
		## Initialize array adding FIRST option and also indicating the inherited value
		$prompt_text = \Joomla\CMS\Language\Text::_($first_option ? $first_option : 'FLEXI_USE_GLOBAL');
		if ( $this->_inherited!==null && !is_array($this->_inherited) && isset($jm_types[$this->_inherited]) )
		{
			$prompt_text = StringHelper::strtoupper($prompt_text). ' ... '. $this->_inherited;
		}
		$options = array();
		$options[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option', '', $prompt_text);

		foreach($types as $v) :
			## Create $value ##
			if (!$v) continue;
			$options[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option', $v, $v);
		endforeach;

		## Create <select name="icons" class="inputbox"></select> ##
		$dropdown = \Joomla\CMS\HTML\HTMLHelper::_('select.genericlist', $options, $this->name, 'class="use_select2_lib"', 'value', 'text', $this->value, $this->id);

		## Output created <select> list ##
		return $dropdown;
	}

	function setInherited($values)
	{
		$this->_inherited = $values;
	}
}