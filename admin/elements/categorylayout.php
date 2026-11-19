<?php
/**
 * @version 1.5 stable $Id: categorylayout.php 1243 2012-04-12 04:59:40Z ggppdk $
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

defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
use Joomla\Utilities\ArrayHelper;

// Load the helper classes
if (!defined('DS'))  define('DS',DIRECTORY_SEPARATOR);
require_once(JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.helper.php');


\Joomla\CMS\Form\FormHelper::loadFieldClass('list');   // \Joomla\CMS\Form\Field\ListField

// Load JS tabber lib
\Joomla\CMS\Factory::getApplication()->getDocument()->addScript(\Joomla\CMS\Uri\Uri::root(true).'/components/com_flexicontent/assets/js/tabber-minimized.js', array('version' => FLEXI_VHASH));
\Joomla\CMS\Factory::getApplication()->getDocument()->addStyleSheet(\Joomla\CMS\Uri\Uri::root(true).'/components/com_flexicontent/assets/css/tabber.css', array('version' => FLEXI_VHASH));
\Joomla\CMS\Factory::getApplication()->getDocument()->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs

/**
 * Renders a categorylayout element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.0
 */
class JFormFieldCategorylayout extends JFormFieldList
{
	/**
	 * The form field type.
	 *
	 * @var		string
	 * @since	1.6
	 */
	protected $type = 'Categorylayout';

	protected function getInput()
	{
		// Element params
		$node = & $this->element;
		$attributes = get_object_vars($node->attributes());
		$attributes = $attributes['@attributes'];
		
		$themes	= flexicontent_tmpl::getTemplates();
		$tmpls_all	= $themes->category ? $themes->category : array();
		$value = $this->value;
		//$value = $value ? $value : @$attributes['default'];
		
		// Get current extension and id being edited
		$app    = \Joomla\CMS\Factory::getApplication();
		$db     = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
		$jinput = $app->input;
		$option = $jinput->get('option', '', 'CMD');
		$view   = $jinput->get('view', '', 'CMD');

		$cparams = \Joomla\CMS\Component\ComponentHelper::getParams('com_flexicontent');

		// Get RECORD id of current view
		$id = $jinput->get('id', array(0), 'array');
		$id = ArrayHelper::toInteger($id, array(0));
		$pk = (int) $id[0];
		
		if (!$pk)
		{
			$cid = $jinput->get('cid', array(0), 'array');
			$cid = ArrayHelper::toInteger($cid, array(0));
			$pk = (int) $cid[0];
		}
		
		// GET LIMITING to specific templates according to item's type, or according to type of new item
		$allowed_tmpls = array();
		$all_tmpl_allowed = true;
		$conf_default_layout = '';
		$conf_default_layout_mobile = '';
		
		$tmpls = array();
		$lays = array();
		foreach ($tmpls_all as $tmpl)
		{
			if ($all_tmpl_allowed || in_array($tmpl->name, $allowed_tmpls))
			{
				$tmpls[] = $tmpl;
				$lays[] = $tmpl->name;
			}
		}
		$lays = implode("','", $lays);
		
		if (@$attributes['enableparam'])
		{
			if (!$cparams->get($attributes['enableparam']))
			{
				return '';
			}
		}
		
if (!@$attributes['skipparams'])
{
		$ext_option = 'com_flexicontent';
		$ext_view   = $view;
		$doc 	= \Joomla\CMS\Factory::getApplication()->getDocument();
		$options = json_encode([
			'layouts' => array_column($tmpls, 'name'),
			'view' => $ext_view, 'option' => $ext_option, 'id' => (int) $pk,
			'token' => \Joomla\CMS\Session\Session::getFormToken(),
			'loading' => \Joomla\CMS\Language\Text::_('FLEXI_LOADING'),
			'label' => \Joomla\CMS\Language\Text::_('FLEXI_PARAMETERS_THEMES_SPECIFIC'),
			'active' => (string) $value,
		], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
		$js = <<<JS
(function(config) {
	let request;
	function getPanel(name) {
		const target = document.getElementById(name + '-attribs-options');
		if (!target) return null;
		const panel = target.nextElementSibling || target;
		const header = panel === target ? target.previousElementSibling : target;
		if (!panel.id) panel.id = name + '-attribs-options-panel';
		return {panel, header, wrapper: panel.parentElement, link: header?.querySelector('a')};
	}

	window.clayout_activatePanel = async function(active) {
		request?.abort();
		for (const name of config.layouts) {
			if (name === active) continue;
			const box = getPanel(name);
			if (!box || box.wrapper.classList.contains('pane-disabled')) continue;
			box.panel.querySelectorAll('textarea:enabled, select:enabled, input[type=radio]:enabled:checked, input[type=checkbox]:enabled:checked, input:not([type=button]):not([type=radio]):not([type=checkbox]):enabled')
				.forEach(input => { input.disabled = true; input.classList.add('fclayout_disabled_element'); });
			box.wrapper.classList.add('pane-disabled');
			FlexiForm.show(box.wrapper, false);
		}
		FlexiForm.show(document.getElementById('__category_inherited_layout__'), !active);
		const box = getPanel(active);
		if (!box) return;
		box.wrapper.classList.remove('pane-disabled');
		FlexiForm.show(box.wrapper);
		box.panel.querySelectorAll('.fclayout_disabled_element').forEach(input => {
			input.disabled = false;
			input.classList.remove('fclayout_disabled_element');
		});
		const preload = box.panel.closest('.fc_preloaded');
		if (preload) {
			preload.classList.remove('fc_preloaded');
			box.panel.classList.add('fc_layout_loaded');
			return;
		}
		if (!box.panel.classList.contains('fc_layout_loaded')) {
			const current = request = new AbortController();
			box.panel.setAttribute('aria-busy', 'true');
			if (box.link) box.link.textContent = config.loading;
			const url = new URL('index.php', document.baseURI);
			url.search = new URLSearchParams({
				option: 'com_flexicontent', task:'templates.getlayoutparams', ext_view: config.view,
				ext_option: config.option, ext_name: active, ext_id: config.id, layout_name: 'category',
				ext_type: 'templates', directory: active, format: 'raw', [config.token]: '1'
			});
			try {
				const response = await fetch(url, {credentials:'same-origin', signal:current.signal});
				if (!response.ok || response.redirected) throw Error(response.statusText);
				const html = await response.text();
				if (current.signal.aborted) return;
				fc_initDynamicLayoutJsCss(box.panel.id, ['subform-row-add'], html);
				box.panel.classList.add('fc_layout_loaded');
			} catch (error) {
				if (!current.signal.aborted) Joomla.renderMessages({error:[error.message || FlexiForm.text('JERROR_AN_ERROR_HAS_OCCURRED')]});
				return;
			} finally {
				if (request === current) box.panel.removeAttribute('aria-busy');
				if (box.link) box.link.textContent = config.label + ' ' + active;
			}
		}
		if (box.link?.classList.contains('collapsed')) box.link.click();
		else if (box.header?.classList.contains('pane-toggler')) box.header.click();
	};
	FlexiForm.ready(() => window.clayout_activatePanel(config.active));
})({$options});
JS;
		flexicontent_html::loadFramework('flexi-lib');
		$doc->addScript(\Joomla\CMS\Uri\Uri::root(true) . '/components/com_flexicontent/assets/js/color-input.js', array('version' => 'auto'), array('defer' => true));
		$doc->addScriptDeclaration($js);
}
		
		$layouts = array();

		if (@$attributes['firstoption'])
		{
			$layouts[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option', '', \Joomla\CMS\Language\Text::_($attributes['firstoption']));
		}
		else
		{
			$layouts[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option', '', '-- '.\Joomla\CMS\Language\Text::_( 'FLEXI_USE_GLOBAL' ). ' --');
		}

		foreach ($tmpls as $tmpl)
		{
			$layouts[] = \Joomla\CMS\HTML\HTMLHelper::_('select.option', $tmpl->name, $tmpl->name);
		}
		
		$fieldname	= $this->name;
		$element_id = $this->id;
		
		$attribs = '';
		if (@$attributes['multiple']=='multiple' || @$attributes['multiple']=='true' ) {
			$attribs .= ' multiple="multiple" ';
			$attribs .= (@$attributes['size']) ? ' size="'.@$attributes['size'].'" ' : ' size="6" ';
		}
		if (@$attributes['class']) {
			$attribs .= 'class="'.$attributes['class'].'"';
		}
		
		if ( ! @$attributes['skipparams'] )
		{
			$attribs .= ' onchange="clayout_activatePanel(this.value);"';
		}
		
		if ($inline_tip = @$attributes['inline_tip'])
		{
			$tip_text = flexicontent_html::getInlineTip($inline_tip, @$attributes['tip_img'], @$attributes['tip_class'], @$attributes['preview_img']);
		}
		if ($inline_tip = @$attributes['inline_tip2'])
		{
			$tip_text2 = flexicontent_html::getInlineTip($inline_tip, @$attributes['tip_img2'], @$attributes['tip_class2'], @$attributes['preview_img2']);
		}
		return
			\Joomla\CMS\HTML\HTMLHelper::_('select.genericlist', $layouts, $fieldname, $attribs, 'value', 'text', $value, $element_id)
			. @ $tip_text . @ $tip_text2;
	}
	
	
	function getLabel()
	{
		$node = & $this->element;
		$attributes = get_object_vars($node->attributes());
		$attributes = $attributes['@attributes'];
		
		if ( @$attributes['enableparam'] ) {
			if ( !\Joomla\CMS\Component\ComponentHelper::getParams('com_flexicontent')->get($attributes['enableparam']) ) return '';
		}
		
		$label = $this->element['label'];
		
		$class = (FLEXI_J30GE ? ' hasTooltip' : ' hasTip');
		if ( @$attributes['labelclass'] ) {
			$class .= ' '.$attributes['labelclass'];
		}

		$title = (string) $this->element['description']
			? flexicontent_html::getToolTip($label, (string) $this->element['description'], 1, 1)
			: '...';

		// Joomla 4+ attaches Bootstrap tooltips only for a given selector; these labels are also shown outside
		// FLEXIcontent's own pages (its Options, menu items, modules), where admin.flexicontent.php does not run
		\Joomla\CMS\HTML\HTMLHelper::_('bootstrap.tooltip', '.hasTooltip');

		return '<label style=""  class="'.$class.'" title="'.$title.'" >'.\Joomla\CMS\Language\Text::_($label).'</label>';
	}

	function set($property, $value)
	{
		$this->$property = $value;
	}
}
