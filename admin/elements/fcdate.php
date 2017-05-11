<?php
/**
 * @version 1.5 beta 4 $Id: fcdate.php 967 2011-11-21 00:01:36Z ggppdk $
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
jimport('joomla.form.field');  // JFormField

//jimport('joomla.form.helper'); // JFormHelper
//JFormHelper::loadFieldClass('calendar');   // JFormField...

/**
 * Renders a date element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JFormFieldFcdate extends JFormField
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	
	var $type = 'Fcdate';

	public function getInput()
	{
		$node = & $this->element;
		$attributes = get_object_vars($node->attributes());
		$attributes = $attributes['@attributes'];

		$value = $this->value;
		$fieldname	= $this->name;
		$element_id = $this->id;
		$date_format = @$attributes['date_format'] ? $attributes['date_format'] : '%Y-%m-%d';

		$attribs_arr = array();

		if ($class = @$attributes['class'])
		{
			$attribs_arr['class'] = $class;
		}

		if ($hint = @$attributes['hint'])
		{
			$attribs_arr['hint'] = $hint;
			$attribs_arr['placeholder'] = $hint;
		}

		$attribs_arr['size']     = isset($attributes['size']) ? $attributes['size'] : 18;
		$attribs_arr['showTime'] = isset($attributes['showTime']) ? $attributes['showTime'] : 0;
		$calendar_class = isset($attributes['calendar_class']) ? $attributes['calendar_class'] : null;

		//return JHTML::_('calendar', $value, $fieldname, $element_id, $format, $attribs_arr);
		return $this->calendar($value, $attribs_arr['showTime'], $fieldname, $element_id, $attribs_arr, $skip_on_invalid=true, $timezone=false, $date_format);
	}


	// Method to create a calendar form field according to a given configuation
	function calendar($value, $date_allowtime, $fieldname, $elementid, $attribs=array(), $skip_on_invalid=false, $timezone=false, $date_format='%Y-%m-%d')
	{
		// 'false' timezone means ==> use server setting (=joomla site configured TIMEZONE),
		// in J1.5 this must be null for using server setting (=joomla site configured OFFSET)
		$timezone = ($timezone === false && !FLEXI_J16GE) ? null : $timezone;
		
		@list($date, $time) = preg_split('#\s+#', $value, $limit=2);
		$time = ($date_allowtime==2 && !$time) ? '00:00' : $time;
		
		try {
			// we check if date has no SYNTAX error (=being invalid) so use $gregorian = true,
			// to avoid it being change according to CALENDAR of current user
			// because user already entered the date in his/her calendar
			if ( !$value ) {
				$date = '';
			} else if (!$date_allowtime || !$time) {
				$date = JHTML::_('date',  $date, 'Y-m-d', $timezone, $gregorian = true);
			} else {
				$date = JHTML::_('date',  $value, 'Y-m-d H:i', $timezone, $gregorian = true);
			}
		} catch ( Exception $e ) {
			if (!$skip_on_invalid) return '';
			else $date = '';
		}
		
		// Create JS calendar
		$time_formats_map = array('0'=>'', '1'=>' %H:%M', '2'=>' 00:00');
		$date_time_format = $date_format . $time_formats_map[$date_allowtime];
		$attribs['showTime'] = $date_allowtime ? 1 : 0;
		return JHTML::_('calendar', $date, $fieldname, $elementid, $date_time_format, $attribs);
	}
}