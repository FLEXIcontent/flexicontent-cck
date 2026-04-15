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

// jimport removed J5: use Joomla\CMS\HTML\HTMLHelper; // TODO: add use statement at top      // JHtml
// jimport removed J5: use Joomla\CMS\...  /* cms.html.select */; // TODO: add use statement at top    // \Joomla\CMS\HTML\Helpers\Select
// jimport removed J5: use Joomla\CMS\...  /* joomla.form.field */; // TODO: add use statement at top  // \Joomla\CMS\Form\FormField

// jimport removed J5: use Joomla\CMS\...  /* joomla.form.helper */; // TODO: add use statement at top // \Joomla\CMS\Form\FormHelper
\Joomla\CMS\Form\FormHelper::loadFieldClass('groupedlist');   // \Joomla\CMS\Form\Field\GroupedlistField


class JFormFieldMicrodataprops extends JFormFieldGroupedList
{
	protected $type = 'microdataprops';
	
	public function getInput()
	{
		// Get microdata types
		static $jm_types = null;
		
		if ($jm_types === null)
		{
			// jimport removed J5: use Joomla\CMS\...  /* joomla.microdata.microdata */; // TODO: add use statement at top
			$jm = new \Joomla\CMS\Microdata\Microdata();
			$jm_types = $jm->getTypes();
		}

		// Prepare the grouped list
		$groups = array();
		$groups[0]['items'] = array(
			\Joomla\CMS\HTML\HTMLHelper::_('select.option','', '-- '.\Joomla\CMS\Language\Text::_('FLEXI_DISABLE').' --')
		);

		foreach($jm_types as $type => $tdata)
		{
			$options = array();
			foreach($tdata['properties'] as $propname => $props)
			{
				$options[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option', $propname, $propname);
			}

			$grp = (string) $type;
			$groups[$grp] = array();
			$groups[$grp]['id'] = null;
			$groups[$grp]['text'] = \Joomla\CMS\Language\Text::_($type);
			$groups[$grp]['items'] = $options;
		}

		// Render and return the drop down select
		return \Joomla\CMS\HTML\HTMLHelper::_('select.groupedlist', $groups, $this->name,
			array(
				'id' => $this->id,
				'group.id' => 'id',
				'list.attr' => array('class'=> 'use_select2_lib inputbox'),
				'list.select' => $this->value,
				'option.attr' => 'attr',  // need to set the name we use for options attributes
			)
		);
	}
}