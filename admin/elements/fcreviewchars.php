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

// Load the helper classes
if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);
require_once(JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');

jimport('joomla.form.field');  // \Joomla\CMS\Form\FormField

/**
 * Renders star pickers for the ratings of the voting characteristics of a review (composite voting), stored as percentages (JSON)
 */
class JFormFieldFcreviewchars extends \Joomla\CMS\Form\FormField
{
	/**
	 * Element name
	 *
	 * @var  string
	 */
	protected $type = 'Fcreviewchars';

	function getInput()
	{
		$field = flexicontent_html::getVotingFieldConfig();
		$chars = flexicontent_html::getVotingCharacteristics($field);

		if (!$chars)
		{
			return '';
		}

		$stars    = flexicontent_html::reviewStarsCount();
		$readonly = (string) $this->element['readonly'] === 'true' || (string) $this->element['disabled'] === 'true';

		// The value is JSON (xid => percent), or an array when the form is redisplayed after a failed save
		$values = is_array($this->value) ? $this->value : (array) json_decode((string) $this->value, true);

		$star_svg = '<svg viewBox="0 0 576 512" aria-hidden="true" focusable="false" style="width:1.4em;height:1.4em;display:block;"><path fill="currentColor" d="M316.9 18C311.6 7 300.4 0 288.1 0s-23.4 7-28.8 18L195 150.3 51.4 171.5c-12 1.8-22 10.2-25.7 21.7s-.7 24.2 7.9 32.7L137.8 327 113.2 469.7c-2 12 3 24.2 12.9 31.3s23 8 33.8 2.3l128.3-68.5 128.3 68.5c10.8 5.7 23.9 4.9 33.8-2.3s14.9-19.3 12.9-31.3L438.5 327 542.7 225.9c8.6-8.5 11.7-21.2 7.9-32.7s-13.7-19.9-25.7-21.7L381.2 150.3 316.9 18z"/></svg>';

		// Clicking a star sets the hidden input (percentage), colors the stars and calculates the overall rating (average of the characteristics)
		$onclick = "var w=this.parentNode;var v=parseInt(this.getAttribute('data-value'),10);"
			. "w.querySelector('input').value=Math.round(100*v/parseInt(w.getAttribute('data-stars'),10));"
			. "var s=w.querySelectorAll('button');for(var i=0;i<s.length;i++){s[i].style.color=(i<v)?'#f5a623':'#ccc';}"
			. "var f=this.form;if(!f)return false;var ins=f.querySelectorAll('.fc-review-chars-input input');var sum=0,n=0;"
			. "for(var j=0;j<ins.length;j++){var p=parseInt(ins[j].value,10)||0;if(p>0){sum+=p;n++;}}"
			. "var m=f.querySelector('.fc-review-rating-input[data-main]');"
			. "if(m&&n===ins.length&&n>0){var avg=Math.round(sum/n);m.querySelector('input').value=avg;"
			. "var mv=Math.round(avg*parseInt(m.getAttribute('data-stars'),10)/100);var ms=m.querySelectorAll('button');"
			. "for(var k=0;k<ms.length;k++){ms[k].style.color=(k<mv)?'#f5a623':'#ccc';}}return false;";

		$html = '<div class="fc-review-chars" style="display:grid;grid-template-columns:repeat(' . (count($chars) % 2 ? 3 : 2) . ',minmax(0,max-content));gap:10px 40px;">';

		foreach ($chars as $xid => $char)
		{
			$percent = isset($values[$xid]) ? max(0, min(100, (int) $values[$xid])) : 0;
			$value   = (int) round($percent * $stars / 100);
			$name    = htmlspecialchars($this->name, ENT_COMPAT, 'UTF-8') . '[' . (int) $xid . ']';

			$html .= '<div><div style="margin-bottom:2px;"' . ($char->desc !== '' ? ' title="' . htmlspecialchars($char->desc, ENT_COMPAT, 'UTF-8') . '"' : '') . '>'
				. htmlspecialchars($char->title !== '' ? $char->title : (string) $xid, ENT_COMPAT, 'UTF-8') . '</div>'
				. '<div class="fc-review-chars-input" data-stars="' . $stars . '" style="display:flex;gap:2px;' . ($readonly ? 'pointer-events:none;' : '') . '">'
				. '<input type="hidden" name="' . $name . '" value="' . $percent . '"' . ($readonly ? ' disabled' : '') . ' />';

			for ($i = 1; $i <= $stars; $i++)
			{
				$html .= '<button type="button" data-value="' . $i . '" title="' . $i . ' / ' . $stars . '" aria-label="' . $i . ' / ' . $stars . '"'
					. ' onclick="' . htmlspecialchars($onclick, ENT_COMPAT, 'UTF-8') . '"'
					. ' style="background:none;border:0;padding:0;margin:0;cursor:pointer;color:' . ($i <= $value ? '#f5a623' : '#ccc') . ';">' . $star_svg . '</button>';
			}

			$html .= '</div></div>';
		}

		return $html . '</div>';
	}
}
