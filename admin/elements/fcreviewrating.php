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
 * Renders a star picker for the rating of a review, the rating is stored as a percentage (0 - 100)
 */
class JFormFieldFcreviewrating extends \Joomla\CMS\Form\FormField
{
	/**
	 * Element name
	 *
	 * @var  string
	 */
	protected $type = 'Fcreviewrating';

	function getInput()
	{
		$stars   = flexicontent_html::reviewStarsCount();

		// Composite voting: the overall rating is calculated from the characteristics (read-only)
		$composite = (bool) flexicontent_html::getVotingCharacteristics(flexicontent_html::getVotingFieldConfig());
		$percent = max(0, min(100, (int) $this->value));
		$value   = (int) round($percent * $stars / 100);

		$star_svg = '<svg viewBox="0 0 576 512" aria-hidden="true" focusable="false" style="width:1.8em;height:1.8em;display:block;"><path fill="currentColor" d="M316.9 18C311.6 7 300.4 0 288.1 0s-23.4 7-28.8 18L195 150.3 51.4 171.5c-12 1.8-22 10.2-25.7 21.7s-.7 24.2 7.9 32.7L137.8 327 113.2 469.7c-2 12 3 24.2 12.9 31.3s23 8 33.8 2.3l128.3-68.5 128.3 68.5c10.8 5.7 23.9 4.9 33.8-2.3s14.9-19.3 12.9-31.3L438.5 327 542.7 225.9c8.6-8.5 11.7-21.2 7.9-32.7s-13.7-19.9-25.7-21.7L381.2 150.3 316.9 18z"/></svg>';

		// Clicking a star sets the hidden input (percentage) and colors the stars
		$onclick = "var w=this.parentNode;var v=parseInt(this.getAttribute('data-value'),10);"
			. "w.querySelector('input').value=Math.round(100*v/parseInt(w.getAttribute('data-stars'),10));"
			. "var s=w.querySelectorAll('button');for(var i=0;i<s.length;i++){s[i].style.color=(i<v)?'#f5a623':'#ccc';}return false;";

		$html = '<div class="fc-review-rating-input" data-stars="' . $stars . '"' . ($composite ? ' data-main="1"' : '') . ' style="display:flex;gap:2px;' . ($composite ? 'pointer-events:none;' : '') . '">'
			. '<input type="hidden" name="' . htmlspecialchars($this->name, ENT_COMPAT, 'UTF-8') . '" id="' . htmlspecialchars($this->id, ENT_COMPAT, 'UTF-8') . '" value="' . $percent . '" />';

		for ($i = 1; $i <= $stars; $i++)
		{
			$html .= '<button type="button" data-value="' . $i . '" title="' . $i . ' / ' . $stars . '" aria-label="' . $i . ' / ' . $stars . '"'
				. ' onclick="' . htmlspecialchars($onclick, ENT_COMPAT, 'UTF-8') . '"'
				. ' style="background:none;border:0;padding:0;margin:0;cursor:pointer;color:' . ($i <= $value ? '#f5a623' : '#ccc') . ';">' . $star_svg . '</button>';
		}

		return $html . '</div>';
	}
}
