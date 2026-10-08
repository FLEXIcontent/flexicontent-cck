<?php
/**
 * @package     FLEXIcontent
 * @subpackage  Form element
 * @license     GNU/GPL v2 or later
 *
 * Sortable multi-value selector: choose values from the <option> list of the field element, drag the chips to set their order.
 * The value is saved as a comma separated list, in the order of the chips, e.g. "tags,words,titles".
 * Vanilla JS, no jQuery needed.
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

class JFormFieldFcSortOpts extends \Joomla\CMS\Form\FormField
{
	/**
	 * Element name
	 *
	 * @var  string
	 */
	protected $type = 'FcSortOpts';

	/**
	 * Value to show when the stored value is empty, child classes may override it
	 *
	 * @return  string  Comma separated list
	 */
	protected function getFallbackValue()
	{
		return '';
	}

	/**
	 * Convert a stored value of an older format, child classes may override it
	 *
	 * @param   string  $value  The stored value
	 *
	 * @return  string  Comma separated list
	 */
	protected function normalizeValue($value)
	{
		return $value;
	}

	protected function getInput()
	{
		static $assets_added = false;

		if (!$assets_added)
		{
			$assets_added = true;

			Factory::getDocument()->addStyleDeclaration('
				.fc-sortopts-list { list-style: none; margin: 8px 0 0; padding: 6px; min-height: 36px; border: 1px dashed #bbb; background: #f4f4f4; display: flex; flex-wrap: wrap; gap: 6px; }
				.fc-sortopts-list:empty::before { content: attr(data-empty); color: #888; padding: 4px; }
				.fc-sortopts-list li { display: inline-flex; align-items: center; gap: 6px; padding: 4px 8px; border: 1px solid #aaa; border-radius: 3px; background: #fff; cursor: move; user-select: none; }
				.fc-sortopts-list li.fc-dragging { opacity: .4; }
				.fc-sortopts-list .fc-sortopts-del { border: 0; background: none; color: #c00; font-size: 16px; line-height: 1; padding: 0 2px; cursor: pointer; }
				.fc-sortopts-add { width: auto; display: inline-block; }
			');

			Factory::getDocument()->addScriptDeclaration("
				document.addEventListener('DOMContentLoaded', function () {
					document.querySelectorAll('.fc-sortopts').forEach(function (box) {
						var hidden = box.querySelector('input[type=hidden]');
						var select = box.querySelector('.fc-sortopts-add');
						var list   = box.querySelector('.fc-sortopts-list');
						var dragged = null;

						function update() {
							var vals = [];
							list.querySelectorAll('li').forEach(function (li) { vals.push(li.getAttribute('data-value')); });
							hidden.value = vals.join(',');
							hidden.dispatchEvent(new Event('change', { bubbles: true }));
						}

						function makeChip(value, label) {
							var li = document.createElement('li');
							li.setAttribute('data-value', value);
							li.setAttribute('draggable', 'true');
							var span = document.createElement('span');
							span.textContent = label;
							var del = document.createElement('button');
							del.type = 'button';
							del.className = 'fc-sortopts-del';
							del.title = box.getAttribute('data-remove');
							del.innerHTML = '&times;';
							li.appendChild(span);
							li.appendChild(del);
							return li;
						}

						select.addEventListener('change', function () {
							var opt = select.options[select.selectedIndex];
							if (!opt || !opt.value) return;
							list.appendChild(makeChip(opt.value, opt.textContent));
							opt.parentNode.removeChild(opt);
							select.selectedIndex = 0;
							update();
						});

						list.addEventListener('click', function (e) {
							if (!e.target.classList.contains('fc-sortopts-del')) return;
							var li = e.target.closest('li');
							var opt = document.createElement('option');
							opt.value = li.getAttribute('data-value');
							opt.textContent = li.querySelector('span').textContent;
							select.appendChild(opt);
							li.parentNode.removeChild(li);
							update();
						});

						list.addEventListener('dragstart', function (e) {
							dragged = e.target.closest('li');
							if (!dragged) return;
							dragged.classList.add('fc-dragging');
							e.dataTransfer.effectAllowed = 'move';
							e.dataTransfer.setData('text/plain', dragged.getAttribute('data-value'));
						});

						list.addEventListener('dragover', function (e) {
							if (!dragged) return;
							e.preventDefault();
							var before = null;
							list.querySelectorAll('li:not(.fc-dragging)').forEach(function (li) {
								var r = li.getBoundingClientRect();
								if (!before && e.clientY < r.bottom && e.clientX < r.left + r.width / 2) before = li;
							});
							if (before) list.insertBefore(dragged, before); else list.appendChild(dragged);
						});

						list.addEventListener('dragend', function () {
							if (dragged) dragged.classList.remove('fc-dragging');
							dragged = null;
							update();
						});
					});
				});
			");
		}

		// Options of the element
		$options = array();
		foreach ($this->element->children() as $opt)
		{
			if ($opt->getName() === 'option')
			{
				$options[(string) $opt['value']] = Text::_((string) $opt);
			}
		}

		// Selected values, in their stored order
		$stored = is_string($this->value) ? $this->normalizeValue(trim($this->value)) : '';
		if ($stored === '')
		{
			$stored = $this->getFallbackValue();
		}

		$selected = array();
		foreach (preg_split('/\s*,\s*/', $stored) as $v)
		{
			if ($v !== '' && isset($options[$v]) && !in_array($v, $selected))
			{
				$selected[] = $v;
			}
		}

		$esc = function ($s)
		{
			return htmlspecialchars((string) $s, ENT_COMPAT, 'UTF-8');
		};

		$html = '<div class="fc-sortopts" data-remove="' . $esc(Text::_('FLEXI_REMOVE')) . '">'
			. '<input type="hidden" name="' . $esc($this->name) . '" id="' . $esc($this->id) . '" value="' . $esc(implode(',', $selected)) . '" />'
			. '<select class="form-select fc-sortopts-add"><option value="">' . $esc(Text::_('FLEXI_ADD_MORE')) . '</option>';

		foreach ($options as $value => $text)
		{
			if (!in_array($value, $selected))
			{
				$html .= '<option value="' . $esc($value) . '">' . $esc($text) . '</option>';
			}
		}

		$html .= '</select>'
			. '<ul class="fc-sortopts-list" data-empty="' . $esc(Text::_('FLEXI_NONE')) . '">';

		foreach ($selected as $value)
		{
			$html .= '<li data-value="' . $esc($value) . '" draggable="true"><span>' . $esc($options[$value]) . '</span>'
				. '<button type="button" class="fc-sortopts-del" title="' . $esc(Text::_('FLEXI_REMOVE')) . '">&times;</button></li>';
		}

		$html .= '</ul></div>';

		return $html;
	}
}
