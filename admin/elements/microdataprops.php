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

jimport('cms.html.html');      // JHtml
jimport('cms.html.select');    // JHtmlSelect
jimport('joomla.form.field');  // JFormField

//jimport('joomla.form.helper'); // JFormHelper
//JFormHelper::loadFieldClass('...');   // JFormField...


class JFormFieldMicrodataprops extends JFormField
{
	protected $type = 'microdataprops';
	
	public function getInput()
	{
		// Get microdata types
		static $jm_types = null;
		
		if ($jm_types === null)
		{
			jimport('joomla.microdata.microdata');
			$jm = new JMicrodata();
			$jm_types = $jm->getTypes();
		}

		// Initialize the options array
		$options = array();
		$options[] = JHtml::_('select.option','', '-- '.JText::_('FLEXI_DISABLE').' --');
		
		foreach($jm_types as $type => $tdata)
		{
			$options[] = JHtml::_('select.optgroup', JText::_( $type ) );
			foreach($tdata['properties'] as $propname => $props) {
				$options[] = JHtml::_('select.option', $propname, $propname);
			}
			$options[] = JHtml::_('select.optgroup', '' );
		}

		// Render and return the drop down select
		return JHtml::_('select.genericlist', $options, $this->name, 'class="use_select2_lib inputbox"', 'value', 'text', $this->value, $this->id);
	}
}