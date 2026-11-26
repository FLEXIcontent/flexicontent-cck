<?php
/** Native colour control with an editable CSS value, including alpha and keywords. */
defined('_JEXEC') or die;

class JFormFieldFccolor extends \Joomla\CMS\Form\Field\ColorField
{
	protected $type = 'Fccolor';

	protected function getInput()
	{
		$document = \Joomla\CMS\Factory::getApplication()->getDocument();
		$document->addScript(\Joomla\CMS\Uri\Uri::root(true) . '/components/com_flexicontent/assets/js/color-input.js', array('version' => 'auto'), array('defer' => true));
		$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
		$disabled = $this->disabled ? ' disabled' : '';
		$readonly = $this->readonly ? ' readonly' : '';
		$label = \Joomla\CMS\Language\Text::_((string) $this->element['label']);
		return '<div class="input-group flex-nowrap" data-fc-color-input data-format="' . $escape($this->format) . '">'
			. '<input type="text" class="form-control ' . $escape($this->class) . '" id="' . $escape($this->id) . '" name="' . $escape($this->name)
			. '" value="' . $escape($this->value) . '"' . $disabled . $readonly . ($this->required ? ' required' : '') . '>'
			. '<input type="color" class="form-control form-control-color" style="flex:0 0 3rem" aria-label="' . $escape($label) . '"'
			. ($this->disabled || $this->readonly ? ' disabled' : '') . '>'
			. ($this->format === 'rgba' ? '<input type="range" class="form-range align-self-center mx-2" style="max-width:7rem" min="0" max="1" step="0.01" aria-label="'
				. $escape(\Joomla\CMS\Language\Text::_('FLEXI_COLOR_OPACITY')) . '"' . ($this->disabled || $this->readonly ? ' disabled' : '') . '>' : '')
			. '</div>';
	}
}
