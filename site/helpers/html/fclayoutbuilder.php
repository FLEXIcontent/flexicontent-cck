<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_content
 *
 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;
\Joomla\CMS\HTML\HTMLHelper::_('bootstrap.tooltip');


/**
 * Fclayoutbuilder HTML helper
 *
 * @since  3.2
 */
abstract class JHtmlFclayoutbuilder
{
	/**
	 * Create a new URL hash to avoid caching changed CSS / JS files
	 *
	 * @since   3.3
	 */
	public static function createUrlHash($current_urlHash)
	{
		static $urlHash = null;
		
		if ($urlHash === null)
		{
			$urlHash = md5(time());
		}

		return $urlHash;
	}


	/**
	 * Create CSS file, typical creating a LESS file to prefix the CSS and compiling it
	 *
	 * @param   object  $params       The configuration parameters that also contain the CSS
	 * @param   string  $path         The folder path
	 * @param   string  $css_prefix   The selector to use for prefixing CSS rules
	 * @param   string  $layout_name  The parameter name of the layout
	 *
	 * @since   3.3
	 */
	public static function createCss($module, $params, $config)
	{
		static $cnt = -1;
		$cnt++;
		
		$path        = \Joomla\Filesystem\Path::clean(JPATH_ROOT . $config->location);
		$css_prefix  = $config->css_prefix;
		$layout_name = $config->layout_name;

		flexicontent_html::loadframework('sabberworm');
		$oCssParser = new \Sabberworm\CSS\Parser($params->get($layout_name . '_css'));
		$oCssDocument = $oCssParser->parse();

		//echo '<pre>' . $params->get($layout_name . '_css') . '</pre>';

		foreach($oCssDocument->getAllDeclarationBlocks() as $oBlock)
		{
			foreach($oBlock->getSelectors() as $oSelector)
			{
				// Loop over all selector parts (the comma-separated strings in a selector) and prepend the id
				//echo '<pre>' . $css_prefix . ' ' . $oSelector->getSelector() . '</pre>';
				$oSelector->setSelector($css_prefix . ' ' . $oSelector->getSelector());
			}
		}

		$less_code  = $oCssDocument->render();

		$matches = null;
		preg_match_all("/@keyframes ([0-9a-zA-Z_]+) /", $less_code, $matches);

		foreach ($matches[0] as $i => $v)
		{
			$cssname = $matches[1][$i];
			$less_code = str_replace(': ' . $cssname . ' ', ': ' . $cssname . '_' . $cnt . ' ', $less_code);
			$less_code = str_replace($v, '@keyframes ' . $cssname . '_' . $cnt . ' ', $less_code);
		}

		// Handle rules starting with body rule
		$less_code  = str_replace($css_prefix . ' body', $css_prefix, $less_code);

		//echo '<pre>' . $less_code . '</pre>';
		$css_id     = isset($config->id) && $config->id !== '' ? $config->id : $module->id;
		$less_file  = 'less/' . $layout_name . '_' . $css_id . '.less';
		$less_path  = \Joomla\Filesystem\Path::clean($path . $less_file);

		// Create / update the LESS file when its generated content has changed.
		// Relying on file existence alone left a stale compiled CSS on the page
		// after the layout CSS was edited in the Layout Builder.
		$less_code_changed = true;
		if (file_exists($less_path))
		{
			$less_code_changed = (@file_get_contents($less_path) !== $less_code);
		}

		if ($less_code_changed)
		{
			// Make sure the target folder exists (e.g. /components/com_flexicontent/builder/less/)
			$less_dir = dirname($less_path);
			if (!is_dir($less_dir))
			{
				@mkdir($less_dir, 0755, true);
			}

			$_resource = @fopen($less_path, "w");

			// Do not break the page if the folder is missing / not writable
			if ($_resource === false)
			{
				if (JDEBUG)
				{
					\Joomla\CMS\Factory::getApplication()->enqueueMessage(
						'Could not create LESS file: ' . $less_path, 'warning'
					);
				}
				return;
			}

			fwrite($_resource, $less_code);
			fclose($_resource);

			// Compile LESS file (force recompilation, the LESS content just changed)
			flexicontent_html::checkedLessCompile(
				array($less_file),
				$path,
				$path . 'less/include/',
				true
			);
		}
	}


	/**
	 * Prepare (typical delete) LESS file of the builder layout (so that it gets re-created)
	 *
	 * @param   string  $text  The string to filter
	 *
	 * @return  string  The filtered string
	 *
	 * @since   3.3
	 */
	public static function prepareLess($file_path)
	{
		$jinput    = \Joomla\CMS\Factory::getApplication()->input;
		$id        = $jinput->getInt('id', 0);
		if (!$id)
		{
			// Template (layout) editor pages do not have an item id, they use the template folder name
			$id = $jinput->getCmd('folder', 0);
		}
		if (!$id) $id = $jinput->getCmd('layout', 0);

		if ($id)
		{
			$path = $file_path;
			$path = str_replace(array('{{id}}', '{{folder}}'), (string) $id, $path);
			$path = \Joomla\Filesystem\Path::clean(JPATH_ROOT . $path . '.less');

			if (file_exists($path))
			{
				//\Joomla\CMS\Factory::getApplication()->enqueueMessage('Removing LESS file: ' . $path, 'message');
				unlink($path);
			}
			else
			{
				//\Joomla\CMS\Factory::getApplication()->enqueueMessage('Skipped non-existent LESS file: ' . $path, 'notice');
			}
		}

		return '';
	}


	/**
	 * Deterministically derive the front-end HTML / CSS of a Layout Builder project.
	 *
	 * Rebuilds them from the GrapesJS project JSON (the single source of truth) instead of
	 * capturing the live canvas states, which were transient / partial on some browser saves
	 * and produced degraded "_html" values (e.g. only plain text, no structure).
	 *
	 * @param string $data JSON project data (as returned by editor.getProjectData())
	 * @return array with keys html / css / js; nulls when the project JSON is invalid
	 *         so that callers can keep previously stored values.
	 */
	public static function renderLayoutFromProject($data)
	{
		$out = array('html' => null, 'css' => null, 'js' => null);
		$project = is_string($data) ? json_decode($data, true) : null;
		if (!is_array($project) || empty($project['pages'])) return $out;

		// First frame of the first page: source of the component tree
		$page0   = $project['pages'][0];
		$frame0  = !empty($page0['frames'][0]) ? $page0['frames'][0] : array();
		$wrapper = !empty($frame0['component']) ? $frame0['component'] : array();

		// Collect the classes really used by the components: styling rules attached to
		// unused classes (deleted components) must be skipped, exactly like the editor's
		// CSS export (keepUsedStyles = false).
		$used = array();
		foreach ($project['pages'] as $page)
		{
			foreach (!empty($page['frames']) ? $page['frames'] : array() as $frame)
			{
				if (!empty($frame['component'])) self::_collectClasses($frame['component'], $used);
			}
		}

		// CSS: non-media rules first (project order), then rules grouped by media query
		$css    = '';
		$medias = array();
		if (!empty($project['styles']) && is_array($project['styles']))
		{
			foreach ($project['styles'] as $rule)
			{
				if (empty($rule['selectors']) || !is_array($rule['selectors'])) continue;
				$names = array();
				foreach ($rule['selectors'] as $sel)
				{
					$name = is_array($sel) ? (isset($sel['name']) ? $sel['name'] : '') : $sel;
					// Skip the whole rule when any of its selectors is not used
					if ($name === '' || !isset($used[$name])) continue 2;
					$names[] = $name;
				}
				if (!$names) continue;
				$props = '';
				if (!empty($rule['style']) && is_array($rule['style']))
				{
					foreach ($rule['style'] as $k => $v)
					{
						// data-fc-* keys are only Style Manager carriers for the block-scoped
						// link styles (see _collectLinkRules), never valid CSS: skip them here
						if (strpos($k, 'data-fc-') === 0) continue;
						$props .= $k . ':' . $v . ';';
					}
				}
				if ($props === '') continue;
				$ruleText = '.' . implode('.', $names) . '{' . $props . '}';
				if (!empty($rule['mediaText'])) $medias[$rule['mediaText']][] = $ruleText;
				else $css .= $ruleText;
			}
		}
		foreach ($medias as $mediaText => $rules)
		{
			$css .= '@media ' . $mediaText . '{' . implode('', $rules) . '}';
		}

		// Block-scoped link styles: a container styles all <a> inside it (color, size,
		// weight, decoration) plus its hover color, scoped by the block\'s first class
		// (.classname a) exactly like the canvas overlay, with a data-fc-links fallback.
		$linkRules = array();
		self::_collectLinkRules($wrapper, $linkRules);
		$css .= implode('', $linkRules);

		$out['css'] = $css;

		// HTML: render the first frame of the first page, without the <body> wrapper
		if (!empty($wrapper['components']) && is_array($wrapper['components']))
		{
			foreach ($wrapper['components'] as $comp)
			{
				$out['html'] .= self::_renderComponent($comp);
			}
		}
		if ($out['html'] === null) $out['html'] = '';
		return $out;
	}

	/**
	 * Collect the class names used by a component tree.
	 */
	protected static function _collectClasses($comp, &$used)
	{
		if (is_array($comp) && !empty($comp['classes']))
		{
			foreach ($comp['classes'] as $cl)
			{
				$name = is_array($cl) ? (isset($cl['name']) ? $cl['name'] : '') : $cl;
				if ($name !== '') $used[$name] = 1;
			}
		}
		if (is_array($comp) && !empty($comp['components']) && is_array($comp['components']))
		{
			foreach ($comp['components'] as $ch) self::_collectClasses($ch, $used);
		}
	}

	/**
	 * Collect the block-scoped link style rules (.classname a) of a component tree.
	 */
	protected static function _collectLinkRules($comp, &$rules)
	{
		if (is_array($comp) && !empty($comp['attributes']) && is_array($comp['attributes']))
		{
			$a = $comp['attributes'];
			// Scope by the block's first class so the rules match both the canvas overlay
			// and the regenerated front-end markup; fall back to the data-fc-links
			// attribute (auto-assigned by the editor) when the block has no class.
			$base = '';
			if (!empty($comp['classes']) && is_array($comp['classes']))
			{
				foreach ($comp['classes'] as $cl)
				{
					$n = is_array($cl) ? (isset($cl['name']) ? $cl['name'] : '') : $cl;
					$n = str_replace(array(';', '{', '}', ' '), '', trim((string) $n));
					if ($n !== '') { $base = '.' . $n; break; }
				}
			}
			if ($base === '')
			{
				$key = isset($a['data-fc-links']) ? trim((string) $a['data-fc-links']) : '';
				if ($key !== '') $base = '[data-fc-links="' . $key . '"]';
			}
			if ($base !== '')
			{
				$s = !empty($comp['style']) && is_array($comp['style']) ? $comp['style'] : array();
				$map = array(
					'data-fc-linkstyles-color'  => 'color',
					'data-fc-linkstyles-size'   => 'font-size',
					'data-fc-linkstyles-weight' => 'font-weight',
					'data-fc-linkstyles-deco'   => 'text-decoration',
				);
				$props = '';
				foreach ($map as $attr => $cssprop)
				{
					$v = isset($a[$attr]) && $a[$attr] !== '' ? $a[$attr] : (isset($s[$attr]) && $s[$attr] !== '' ? $s[$attr] : '');
					$v = str_replace(array(';', '{', '}'), '', trim((string) $v));
					// The editor appends the unit via the Size property; a bare number that
					// slipped through (e.g. old saves) is fixed up to px (font-size:133px).
					if ($cssprop === 'font-size' && preg_match('/^\d+(\.\d+)?$/', $v) === 1) $v .= 'px';
					if ($v !== '') $props .= $cssprop . ':' . $v . ';';
				}
				if ($props !== '')
				{
					$rules[] = $base . ' a{' . $props . '}';
				}
				$hv = isset($a['data-fc-linkstyles-hover']) && $a['data-fc-linkstyles-hover'] !== '' ? $a['data-fc-linkstyles-hover'] : (isset($s['data-fc-linkstyles-hover']) ? $s['data-fc-linkstyles-hover'] : '');
				$hv = str_replace(array(';', '{', '}'), '', trim((string) $hv));
				if ($hv !== '') $rules[] = $base . ' a:hover{color:' . $hv . ';}';
			}
		}
		if (is_array($comp) && !empty($comp['components']) && is_array($comp['components']))
		{
			foreach ($comp['components'] as $ch) self::_collectLinkRules($ch, $rules);
		}
	}

	/**
	 * Render a single component into (X)HTML, mimicking the editor's model serialization.
	 */
	protected static function _renderComponent($comp)
	{
		if (!is_array($comp))
		{
			// Raw text / comment nodes are stored as plain strings
			return (string) $comp;
		}
		$tag   = !empty($comp['tagName']) ? $comp['tagName'] : '';
		if ($tag === '')
		{
			// Default tag of core GrapesJS component types (not stored in the project JSON)
			$typeTags = array('image' => 'img', 'video' => 'iframe', 'map' => 'iframe', 'link' => 'a', 'text' => 'div');
			$tag      = isset($comp['type'], $typeTags[$comp['type']]) ? $typeTags[$comp['type']] : 'div';
		}
		$attrs = array();

		if (!empty($comp['attributes']) && is_array($comp['attributes']))
		{
			foreach ($comp['attributes'] as $k => $v)
			{
				// data-fc-linkstyles-* are only Style Manager carriers (see _collectLinkRules),
				// never meant to reach the front-end markup
				if (strpos($k, 'data-fc-linkstyles-') === 0) continue;
				$attrs[$k] = (string) $v;
			}
		}
		if (!empty($comp['classes']) && is_array($comp['classes']))
		{
			$names = array();
			foreach ($comp['classes'] as $cl)
			{
				$name = is_array($cl) ? (isset($cl['name']) ? $cl['name'] : '') : $cl;
				if ($name !== '') $names[] = $name;
			}
			if ($names) $attrs['class'] = implode(' ', $names);
		}
		// The video (iframe) component keeps the final URL in the "src" property
		if (!empty($comp['src']) && !isset($attrs['src']))
		{
			$attrs['src'] = (string) $comp['src'];
		}
		// Inline styles are serialized as a style attribute, like the editor does with
		// avoidInlineStyle: false (layout blocks rely on them for their flex layout)
		if (!empty($comp['style']) && is_array($comp['style']))
		{
			$styleStr = '';
			foreach ($comp['style'] as $k => $v)
			{
				if ($v === '' || $v === null) continue;
				// data-fc-* style keys are only Style Manager carriers for the block-scoped
				// link styles (see _collectLinkRules), never valid inline CSS: skip them
				if (strpos($k, 'data-fc-') === 0) continue;
				$styleStr .= $k . ':' . $v . ';';
			}
			if ($styleStr !== '') $attrs['style'] = $styleStr;
		}

		$attrStr = '';
		foreach ($attrs as $k => $v)
		{
			$attrStr .= ' ' . $k . '="' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '"';
		}

		$inner = '';
		// "Flexicontent Data" custom component types (see the editor's DomComponents.addType):
		// their canvas preview is only cosmetic, the persisted tokens are what the front-end
		// renderer (renderBuilderLayout) resolves at run time.
		if (!empty($comp['type']) && $comp['type'] === 'fc-field')
		{
			$fname = isset($comp['attributes']['data-fc-field']) ? trim((string) $comp['attributes']['data-fc-field']) : '';
			$fpre  = isset($comp['attributes']['data-fc-prefix']) ? (string) $comp['attributes']['data-fc-prefix'] : '';
			$fsuf  = isset($comp['attributes']['data-fc-suffix']) ? (string) $comp['attributes']['data-fc-suffix'] : '';
			$inner = $fname !== '' ? $fpre . '{flexi_field:' . $fname . ' item:current method:display}' . $fsuf : '';
		}
		elseif (!empty($comp['type']) && $comp['type'] === 'fc-link')
		{
			$fid  = isset($comp['attributes']['data-fc-itemid'])   ? trim((string) $comp['attributes']['data-fc-itemid'])   : '';
			$ftxt = isset($comp['attributes']['data-fc-linktext']) ? trim((string) $comp['attributes']['data-fc-linktext']) : '_title_';
			$fid  = $fid === '' ? 'current' : $fid;
			$inner = '{flexi_link:item  item:' . $fid . '  linktext:' . $ftxt . '}';
			if ($ftxt === '_noclose_')
			{
				// Wrapper form: keep anything dropped between the open anchor and {/flexi_link}
				if (!empty($comp['components']) && is_array($comp['components']))
				{
					foreach ($comp['components'] as $fch) $inner .= self::_renderComponent($fch);
				}
				$inner .= '{/flexi_link}';
			}
		}
		elseif (!empty($comp['type']) && $comp['type'] === 'fc-profile-user')
		{
			$fuid = isset($comp['attributes']['data-fc-userid']) ? trim((string) $comp['attributes']['data-fc-userid']) : '';
			$fuid = $fuid === '' ? '%user_id%' : $fuid;
			$inner = '{flexi_item:profile  user:' . $fuid . '  ilayout:%template_name%}';
		}
		elseif (!empty($comp['type']) && $comp['type'] === 'fc-profile-author')
		{
			$inner = '{flexi_item:profile  author_of:[%item_id% | current]  ilayout:%template_name%}';
		}
		elseif (isset($comp['content']) && $comp['content'] !== null && $comp['content'] !== '')
		{
			$inner = (string) $comp['content'];
		}
		elseif (!empty($comp['components']) && is_array($comp['components']))
		{
			foreach ($comp['components'] as $ch) $inner .= self::_renderComponent($ch);
		}

		if (in_array($tag, array('img', 'br', 'hr', 'input', 'meta', 'link', 'source')))
		{
			return '<' . $tag . $attrStr . '/>';
		}
		return '<' . $tag . $attrStr . '>' . $inner . '</' . $tag . '>';
	}


	/**
	 * Create the HTML of Layout (page) Builder, a button is added to initialize and show the builder
	 *
	 * @since   3.3
	 */
	public static function getBuilderHtml($options)
	{
		$fieldname  = $options->fieldname;
		$element_id = $options->element_id;
		$lessfile   = $options->lessfile;
		$editor_sfx = $options->editor_sfx;
		$jinput     = \Joomla\CMS\Factory::getApplication()->input;
		$html       = '';

		// Remove less files so that it gets recreated, this happens on form load, ideally it should happen on form save ...
		if (!$lessfile)
		{
			\Joomla\CMS\Factory::getApplication()->enqueueMessage('lessfile not given for the layout builder');
		}
		else
		{
			$matches = null;
			preg_match_all("/{{([0-9a-zA-Z_]+)}}/", $lessfile, $matches);
			//\Joomla\CMS\Factory::getApplication()->enqueueMessage(print_r($matches, true));

			foreach ($matches[0] as $i => $replacement_string)
			{
				$url_variable = $matches[1][$i];
				$url_value    = $jinput->getCmd($url_variable, '');

				if (!strlen($url_value))
				{
					\Joomla\CMS\Factory::getApplication()->enqueueMessage($replacement_string . ' (for less file path creation), respective URL variable ' . $url_variable . ' is empty');
				}
				else
				{
					$lessfile = str_replace($replacement_string, $url_value, $lessfile);
				}
			}

			//\Joomla\CMS\Factory::getApplication()->enqueueMessage($lessfile);
			//unlink(\Joomla\Filesystem\Path::clean(JPATH_ROOT . '/' . $lessfile));
		}

		static $framework_added = null;

		if ($framework_added === null)
		{
			$framework_added = true;

			$document = \Joomla\CMS\Factory::getDocument();
			flexicontent_html::loadframework('grapejs');

			$document->addStyleDeclaration('

		/* We can remove the border we have set at the beginning */
		#gjs {
		  border: none;
		}

		/* Theming */

		/* Primary color for the background */
		.gjs-one-bg {
		  background-color: #404040;
		}

		/* Secondary color for the text color */
		.gjs-two-color {
		  color: #ffffff;
		}

		/* Tertiary color for the background */
		.gjs-three-bg {
		  background-color: #6EA22B;
		  color: white;
		}
		.gjs-color-warn {
			color: #6EA22B;
			fill: #6EA22B;
		}

		/* Quaternary color for the text color */
		.gjs-four-color,
		.gjs-four-color-h:hover {
		  color: #6EA22B;
		}

		.gjs-radio-item input {
		    display: none !important;
		}
		.gjs-radio-item-label {
		    margin: 0;
		}


		/**
		 * Top Panels container and top panels
		 */

		.gjs-pn-panels {
		}

		.gjs-pn-panel-basic-actions,
		.gjs-pn-panel-devices,
		.gjs-pn-panel-switcher {
			position: relative;
		}


		/**
		 * Editor Row (containing):
		 * - editor canvas
		 * - right panel
		 */
		.editor-row,
		.editor-canvas,
		.panel__right {
			height: 100%;
		}

		/*.editor-row {
			display: flex;
			justify-content: flex-start;
			align-items: flexi-end;
			flex-wrap: nowrap;
			align-content: flexi-end;
		}

		.editor-canvas {
			position: relative;
			flex-grow: 1;
		}

		.panel__right {
			flex-basis: 230px;
			position: relative;
			overflow-y: auto;
		}*/

		button#gjs-sm-add {
			color: #000 !important;
	}

		/**
		 * Editor outer container (.editor-row > .editor-canvas > #gjs_*)
		 */
		.editor-row > .editor-canvas > * {
			border: 2px solid #444;
			float:left;
			box-sizing: border-box;
		}

		/**
		 * Editor Canvas (.editor-row > .editor-canvas > #gjs_* > .gjs-editor > .gjs-cv-canvas)
		 */
		.gjs-editor {
			min-height: 600px;
		}
		.gjs-cv-canvas {
		}


		/**
		 * Blocks
		 */
		#blocks {
		}
		.gjs-blocks-cs {
		}
		.gjs-block {
		}
		.gjs-block > .gjs-block-label > svg {
			max-width: 54px;
		}


		/**
		 * Buttons
		 */
		/*.gjs-pn-buttons > * {
			margin: 0 4x;
		}*/
		.gjs-field select.gjs-devices {
			padding: 2px 16px 2px 0;
		}

		body textarea.fc_layout_data {
			display: inline-block;
			height: 100%;
			width: 25%;
			margin: 0px;
			box-sizing: border-box;
			float: left;
		}
		body {
			overflow: scroll;
		}
		// .fa, .fas, [class^=icon-], [class*=" icon-"] {
		// 	font-family: \'FontAwesome\';
		// 	font-weight: normal;
		// }
		.gjs-field.gjs-field-fc-field-select .fc-combo {
			position: relative;
			width: 100%;
		}
		.gjs-field.gjs-field-fc-field-select .fc-combo-input {
			width: 100%;
			box-sizing: border-box;
			min-height: 22px;
			padding: 3px 20px 3px 6px;
			border: 1px solid rgba(0, 0, 0, .2);
			border-radius: 2px;
			background: #fff;
			color: #444;
			font-size: 12px;
			outline: none;
			cursor: pointer;
		}
		.gjs-field.gjs-field-fc-field-select .fc-combo-input:focus {
			border-color: #78909c;
			cursor: text;
		}
		.gjs-field.gjs-field-fc-field-select .fc-combo-arrow {
			position: absolute;
			top: 0;
			right: 3px;
			height: 100%;
			width: 14px;
			display: flex;
			align-items: center;
			justify-content: center;
			pointer-events: none;
			color: #888;
			font-size: 9px;
		}
		.gjs-field.gjs-field-fc-field-select .fc-combo-list {
			position: absolute;
			top: calc(100% + 2px);
			left: 0;
			right: 0;
			z-index: 60;
			max-height: 210px;
			overflow-y: auto;
			background: #fff;
			border: 1px solid #ddd;
			border-radius: 2px;
			box-shadow: 0 4px 14px rgba(0, 0, 0, .3);
			color: #333;
			font-size: 12px;
			text-align: left;
		}
		.gjs-field.gjs-field-fc-field-select .fc-combo-grp > b {
			display: block;
			padding: 4px 8px 3px;
			font-size: 10px;
			letter-spacing: .04em;
			text-transform: uppercase;
			background: #f4f4f4;
			color: #7a7a7a;
		}
		.gjs-field.gjs-field-fc-field-select .fc-combo-opt {
			padding: 4px 10px;
			cursor: pointer;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
			line-height: 1.3;
		}
		.gjs-field.gjs-field-fc-field-select .fc-combo-opt:hover,
		.gjs-field.gjs-field-fc-field-select .fc-combo-active {
			background: #e8f1fb;
			color: #1c3d5f;
		}
		.gjs-field.gjs-field-fc-field-select .fc-search-nomatch {
			padding: 6px 10px;
			font-style: italic;
			color: #a55;
		}
		label.gjs-sm-icon {
			color: #fff;
		}
		');

		// The published FlexiContent fields (name + label + type), used by the "Flexicontent Data"
		// blocks (fc-field trait picker / canvas badges). Same sources as the backend template
		// view (FlexicontentModelTemplate::getFields), excluding form-only fields.
		// 'group' is the human readable field-type label used to group the picker options.
		$fcl_field_options = array();
		$fcl_field_type_groups = array(
			'core' => 'Core fields',             'coreprops'   => 'Core properties',
			'text' => 'Text',                    'textarea'    => 'Textarea',
			'textselect' => 'Text + Select',     'select'      => 'Select',
			'selectmultiple' => 'Multiple select','checkbox'   => 'Checkbox',
			'checkboximage' => 'Checkbox image', 'radio'       => 'Radio',
			'radioimage' => 'Radio image',       'image'       => 'Image / Gallery',
			'mediafile' => 'Media file',         'file'        => 'File',
			'date' => 'Date',                    'color'       => 'Color',
			'email' => 'E-mail',                 'weblink'     => 'Web link',
			'linkslist' => 'Links list',         'addressint'  => 'Address',
			'phonenumbers' => 'Phone numbers',   'relation'    => 'Relation',
			'relation_reverse' => 'Relation reverse', 'termlist' => 'Terms',
			'subform' => 'Sub-form',             'fieldgroup'  => 'Field group',
			'comments' => 'Comments',            'fcpagenav'   => 'Page navigation',
			'fcloadmodule' => 'Load module',     'jprofile'    => 'User profile',
			'toolbar' => 'Toolbar',              'sharedmedia' => 'Shared media',
			'account_via_submit' => 'Account via submit',
		);
		try
		{
			$fcl_db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
			$fcl_query = $fcl_db->getQuery(true);
			$fcl_query->select(array('f.id', 'f.name', 'f.label', 'f.field_type'))
				->from($fcl_db->quoteName('#__flexicontent_fields', 'f'))
				->join('LEFT', $fcl_db->quoteName('#__flexicontent_fields_type_relations', 'rel') . ' ON rel.field_id = f.id')
				->where($fcl_db->quoteName('f.published') . ' = 1')
				->where($fcl_db->quoteName('f.field_type') . ' <> ' . $fcl_db->quote('custom_form_html'))
				->group($fcl_db->quoteName('f.id'))
				->order('f.label ASC');
			$fcl_db->setQuery($fcl_query);

			foreach ($fcl_db->loadObjectList() as $fcl_field)
			{
				if (substr($fcl_field->name, 0, 5) === 'form_') continue;
				$fcl_ftype = str_replace(array('_', '-'), ' ', $fcl_field->field_type);
				$fcl_group_label = isset($fcl_field_type_groups[$fcl_field->field_type])
					? $fcl_field_type_groups[$fcl_field->field_type]
					: ucwords($fcl_ftype);
				$fcl_field_options[] = array(
					'value' => $fcl_field->name,
					'name'  => $fcl_field->name,
					'label' => (($fcl_field->label && $fcl_field->label !== $fcl_field->name) ? \Joomla\CMS\Language\Text::_($fcl_field->label) : $fcl_field->name) . ' [' . $fcl_field->field_type . ']',
					'type'  => $fcl_field->field_type,
					'group' => $fcl_group_label,
				);
			}
		}
		catch (\Exception $e)
		{
			$fcl_field_options = array();
		}

		$html .= '<script>window.fcl_builder_fields_' . $editor_sfx . ' = ' . json_encode($fcl_field_options) . ';</script>';

		/**
		 * Preview item data for the Layout Builder canvas.
		 * A list of recent items is offered in the builder toolbar (below); the chosen item id
		 * is kept in the 'fcl_preview_item' URL parameter (persisted across reloads) and in
		 * sessionStorage (persisted across the builder session) and is sent to the getfieldpreview
		 * endpoint (AJAX, batch of fields) so that every fc-field block shows its real rendered
		 * value on the canvas.
		 */
		$fcl_preview_url_set = ($jinput->get('fcl_preview_item', null, 'int') !== null);
		$fcl_preview_item_id = $jinput->getInt('fcl_preview_item', 0);
		$fcl_recent_items    = array();
		$fcl_preview_html    = '';
		$fcl_preview_warn    = '';
		$fcl_db2             = null;

		/**
		 * Recent items for the preview selector. Preferred source is the FC items table
		 * (join ensures only FC items), with a graceful fallback to plain #__content rows
		 * if that query fails or is empty (missing table / no FC items), so the toolbar is
		 * always rendered.
		 */
		try
		{
			$fcl_db2 = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
			$fcl_pq  = $fcl_db2->getQuery(true);
			$fcl_pq->select('a.id, a.title')
				->from($fcl_db2->quoteName('#__content', 'a'))
				->innerJoin($fcl_db2->quoteName('#__flexicontent_items', 'fi') . ' ON fi.item_id = a.id')
				->where($fcl_db2->quoteName('a.state') . ' = 1')
				->order('a.modified DESC, a.id DESC')
				->setLimit(30);
			$fcl_db2->setQuery($fcl_pq);
			$fcl_recent_items = $fcl_db2->loadObjectList();
		}
		catch (\Exception $e)
		{
			$fcl_preview_warn .= ' flexicontent_items query: ' . $e->getMessage();
			$fcl_recent_items = array();
		}

		if ($fcl_db2 && !count($fcl_recent_items))
		{
			try
			{
				$fcl_pq = $fcl_db2->getQuery(true);
				$fcl_pq->select('a.id, a.title')
					->from($fcl_db2->quoteName('#__content', 'a'))
					->where($fcl_db2->quoteName('a.state') . ' = 1')
					->order('a.modified DESC, a.id DESC')
					->setLimit(30);
				$fcl_db2->setQuery($fcl_pq);
				$fcl_recent_items = $fcl_db2->loadObjectList();
			}
			catch (\Exception $e)
			{
				$fcl_preview_warn .= ' content query: ' . $e->getMessage();
				$fcl_recent_items = array();
			}
		}

		if (!count($fcl_recent_items))
		{
			$fcl_preview_item_id = 0;
		}
		elseif (!$fcl_preview_item_id)
		{
			$fcl_preview_item_id = (int) $fcl_recent_items[0]->id;
		}

		$fcl_preview_cfg = array(
			'endpoint'   => 'index.php?option=com_flexicontent&task=templates.getfieldpreview&format=raw',
			'token_name' => \Joomla\CMS\Session\Session::getFormToken(),
			'token'      => '1',
			'item_id'    => (int) $fcl_preview_item_id,
			'url_set'    => (bool) $fcl_preview_url_set,
			'warn'       => $fcl_preview_warn,
		);
		$html .= '<script>window.fcl_builder_preview_' . $editor_sfx . ' = ' . json_encode($fcl_preview_cfg) . ';</script>';

		$fcl_preview_options = '<option value="0">' . (count($fcl_recent_items) ? '(badges only)' : '(badges only - no items found)') . '</option>';

		foreach ($fcl_recent_items as $fcl_ri)
		{
			$fcl_sel = ((int) $fcl_ri->id === $fcl_preview_item_id) ? ' selected="selected"' : '';
			$fcl_preview_options .= '<option value="' . (int) $fcl_ri->id . '"' . $fcl_sel . '>' . htmlspecialchars($fcl_ri->title, ENT_QUOTES, 'UTF-8') . ' (' . (int) $fcl_ri->id . ')</option>';
		}

		$fcl_preview_html = '
			<div class="fcl-preview-toolbar" style="display:flex;align-items:center;gap:8px;padding:6px 10px;background:#2d3556;color:#cbd2e8;font-size:12px;border-bottom:1px solid #22294a;">
				<label for="fcl_preview_item_' . $editor_sfx . '" style="white-space:nowrap;">Preview item</label>
				<select id="fcl_preview_item_' . $editor_sfx . '" class="inputbox" style="font-size:12px;max-width:480px;">' . $fcl_preview_options . '</select>
				<span id="fcl_preview_status_' . $editor_sfx . '" class="fcl-preview-status" style="white-space:nowrap;">&nbsp;</span>
			</div>';

		//\Joomla\CMS\Factory::getDocument()->addScriptDeclaration(
		// TODO add template.css file in editor for better display
		$html .= '
		<script>
function fclayout_init_builder(editor_sfx, element_id)
		{
			/**
			 * Lets say, for instance, you start with your already defined HTML template
			 * and you\'d like to import it on fly for the user
			 * REMOVED: data-gjs-dragMode="absolute"
			 */
			var LandingPage = {
				html: \'<div style="display: inline-block" data-gjs-resizable="true"><h2>This is the layout area, you may drag and drop to add your blocks here, to load flexicontent field use data panel to insert flexicontent block</h2></div>\',
				css: null,
				components: null,
				style: null,
			};

			var lp = \'./img/\';
			var plp = \'//placehold.it/350x250/\';
			var images = [
				lp+\'flexicontent.png\',
				lp+\'grapesjs-logo-cl.png\'
			];

			// Field values accessors, aware of `<input>`/`<textarea>` (`.value`) vs other elements (`.textContent`)
			function fcl_get(el)
			{
				if (!el) return \'\';
				var t = (el.tagName || \'\').toLowerCase();
				return (t === \'input\' || t === \'textarea\') ? (el.value || \'\') : (el.textContent || \'\');
			}

			function fcl_set(el, v)
			{
				if (!el) return;
				var t = (el.tagName || \'\').toLowerCase();
				if (t === \'input\' || t === \'textarea\')
				{
					el.value = v || \'\';
				}
				else
				{
					el.innerHTML = v || \'\';
				}
			}

			// Register every plugins used below (modern UMD builds expose a global, eg. gjs-blocks-basic/custom-code/tooltip)
			// and isolate any failure so the editor still initialises.
			[ \'grapesjs-preset-webpage\', \'gjs-blocks-basic\', \'grapesjs-tabs\', \'grapesjs-custom-code\',
			  \'grapesjs-touch\', \'grapesjs-tooltip\', \'grapesjs-blocks-bootstrap4\'
			].forEach(function(id)
			{
				var fn = grapesjs.plugins.get(id) || window[id];
				if (fn)
				{
					fn = fn.default || fn;
					if (typeof fn === \'function\')
					{
						grapesjs.plugins.add(id, function(ed, opts)
						{
							try { fn(ed, opts); }
							catch(e) { console.error(\'Plugin \' + id + \' init failed:\', e); }
						});
					}
				}
				else
				{
					console.warn(\'Plugin \' + id + \' not found on page\');
				}
			});

			// Plugins list (kept separate so the core can stay as-is if the list grows)
			var fcl_builder_plugins = [
				\'grapesjs-preset-webpage\',
				\'gjs-blocks-basic\',
				\'grapesjs-tabs\',
				\'grapesjs-custom-code\',
				\'grapesjs-touch\',
				\'grapesjs-tooltip\',
				\'grapesjs-blocks-bootstrap4\',
			];

			var editor = grapesjs.init({

				// Do NOT show the browser "Changes you made may not be saved" dialog:
				// this is a TOP-LEVEL editor option (the storageManager section does not read it),
				// the layout fields are always written by saveToForm() on form submit
				noticeOnUnload: false,

				// TODO check loading css in canvas and real url
				canvas: {
					styles: [\'https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css\']
				},
				scripts: [
					\'https://code.jquery.com/jquery-3.3.1.slim.min.js\',
					\'https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js\',
					\'https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js\'
				],

				avoidInlineStyle: false,

				// Allow to move 
				//dragMode: \'absolute\',

				// Indicate where to init the editor. You can also pass an HTMLElement
				// Size of the editor
				height: \'100%\',
				container: \'#gjs_\' + editor_sfx,

				// We use `fromElement` or `components` to get the HTML
				// `components` accepts an HTML string or a JSON string of components
				// Start from an EMPTY canvas; the saved project data is loaded next by the
				// storage manager (autoload) and must be the ONLY source of content.
				fromElement: 0,
				components: \'\',

				// We might want to make the same check for styles
				style: \'\',
				protectedCss: \'\',

				showOffsets: 1,

				assetManager: {
					embedAsBase64: 1,
					assets: images
				},

				styleManager: { clearProperties: 1 },

				plugins: fcl_builder_plugins,

				pluginsOpts: {
					\'grapesjs-tooltip\': {
						blockTooltip: {
							category: \'Extra\'
						}
					},
					\'grapesjs-tabs\': {
						tabsBlock: {
							category: \'Extra\'
						}
					},
					\'grapesjs-blocks-bootstrap4\': {
						blocks: {
						},
						blockCategories: {
						},
						labels: {
						},
					},
					\'grapesjs-preset-webpage\': {
						modalImportTitle: \'Import Template\',
						modalImportLabel: \'<div style="margin-bottom: 10px; font-size: 13px;">Paste here your HTML/CSS and click Import</div>\',
						modalImportContent: function(editor)
						{
							//\'<script>\n\' + document.querySelector(\'#\' + element_id + \'_js\').textContent.trim() + \'\n<\/script>\n\'
							return document.querySelector(\'#\' + element_id + \'_html\').textContent.trim() + \'\n\' +
								\'<style>\n\' + document.querySelector(\'#\' + element_id + \'_css\').textContent.trim() + \'\n<\/style>\n\';
						},

						// The customStyleManager below is ignored by preset-webpage v1 (kept as reference),
						// the Style Manager uses its default sectors
						customStyleManager: [{
							name: \'General\',
							buildProps: [\'float\', \'display\', \'position\', \'top\', \'right\', \'left\', \'bottom\'],
							properties:[{
									name: \'Alignment\',
									property: \'float\',
									type: \'radio\',
									defaults: \'none\',
									list: [
										{ value: \'none\', className: \'fa fa-times\'},
										{ value: \'left\', className: \'fa fa-align-left\'},
										{ value: \'right\', className: \'fa fa-align-right\'}
									],
								},
								{ property: \'position\', type: \'select\'}
							],
						},{
								name: \'Dimension\',
								open: false,
								buildProps: [\'width\', \'flex-width\', \'height\', \'max-width\', \'min-height\', \'margin\', \'padding\'],
								properties: [{
									id: \'flex-width\',
									type: \'integer\',
									name: \'Width\',
									units: [\'px\', \'%\'],
									property: \'flex-basis\',
									toRequire: 1,
								},{
									property: \'margin\',
									properties:[
										{ name: \'Top\', property: \'margin-top\'},
										{ name: \'Right\', property: \'margin-right\'},
										{ name: \'Bottom\', property: \'margin-bottom\'},
										{ name: \'Left\', property: \'margin-left\'}
									],
								},{
									property  : \'padding\',
									properties:[
										{ name: \'Top\', property: \'padding-top\'},
										{ name: \'Right\', property: \'padding-right\'},
										{ name: \'Bottom\', property: \'padding-bottom\'},
										{ name: \'Left\', property: \'padding-left\'}
									],
								}],
							},{
								name: \'Typography\',
								open: false,
								buildProps: [\'font-family\', \'font-size\', \'font-weight\', \'letter-spacing\', \'color\', \'line-height\', \'text-align\', \'text-decoration\', \'text-shadow\'],
								properties:[
									{ name: \'Font\', property: \'font-family\'},
									{ name: \'Weight\', property: \'font-weight\'},
									{ name:  \'Font color\', property: \'color\'},
									{
										property: \'text-align\',
										type: \'radio\',
										defaults: \'left\',
										list: [
											{ value : \'left\',  name : \'Left\',    className: \'fa fa-align-left\'},
											{ value : \'center\',  name : \'Center\',  className: \'fa fa-align-center\' },
											{ value : \'right\',   name : \'Right\',   className: \'fa fa-align-right\'},
											{ value : \'justify\', name : \'Justify\',   className: \'fa fa-align-justify\'}
										],
									},{
										property: \'text-decoration\',
										type: \'radio\',
										defaults: \'none\',
										list: [
											{ value: \'none\', name: \'None\', className: \'fa fa-times\'},
											{ value: \'underline\', name: \'underline\', className: \'fa fa-underline\' },
											{ value: \'line-through\', name: \'Line-through\', className: \'fa fa-strikethrough\'}
										],
									},{
										property: \'text-shadow\',
										properties: [
											{ name: \'X position\', property: \'text-shadow-h\'},
											{ name: \'Y position\', property: \'text-shadow-v\'},
											{ name: \'Blur\', property: \'text-shadow-blur\'},
											{ name: \'Color\', property: \'text-shadow-color\'}
										],
								}],
							},{
								name: \'Decorations\',
								open: false,
								buildProps: [\'opacity\', \'background-color\', \'border-radius\', \'border\', \'box-shadow\', \'background\'],
								properties: [{
									type: \'slider\',
									property: \'opacity\',
									defaults: 1,
									step: 0.01,
									max: 1,
									min:0,
								},{
									property: \'border-radius\',
									properties  : [
										{ name: \'Top\', property: \'border-top-left-radius\'},
										{ name: \'Right\', property: \'border-top-right-radius\'},
										{ name: \'Bottom\', property: \'border-bottom-left-radius\'},
										{ name: \'Left\', property: \'border-bottom-right-radius\'}
									],
								},{
									property: \'box-shadow\',
									properties: [
										{ name: \'X position\', property: \'box-shadow-h\'},
										{ name: \'Y position\', property: \'box-shadow-v\'},
										{ name: \'Blur\', property: \'box-shadow-blur\'},
										{ name: \'Spread\', property: \'box-shadow-spread\'},
										{ name: \'Color\', property: \'box-shadow-color\'},
										{ name: \'Shadow type\', property: \'box-shadow-type\'}
									],
								},{
									property: \'background\',
									properties: [
										{ name: \'Image\', property: \'background-image\'},
										{ name: \'Repeat\', property:   \'background-repeat\'},
										{ name: \'Position\', property: \'background-position\'},
										{ name: \'Attachment\', property: \'background-attachment\'},
										{ name: \'Size\', property: \'background-size\'}
									],
								},],
							},{
								name: \'Extra\',
								open: false,
								buildProps: [\'transition\', \'perspective\', \'transform\'],
								properties: [{
									property: \'transition\',
									properties:[
										{ name: \'Property\', property: \'transition-property\'},
										{ name: \'Duration\', property: \'transition-duration\'},
										{ name: \'Easing\', property: \'transition-timing-function\'}
									],
								},{
									property: \'transform\',
									properties:[
										{ name: \'Rotate X\', property: \'transform-rotate-x\'},
										{ name: \'Rotate Y\', property: \'transform-rotate-y\'},
										{ name: \'Rotate Z\', property: \'transform-rotate-z\'},
										{ name: \'Scale X\', property: \'transform-scale-x\'},
										{ name: \'Scale Y\', property: \'transform-scale-y\'},
										{ name: \'Scale Z\', property: \'transform-scale-z\'}
									],
								}]
							},{
								name: \'Flex\',
								open: false,
								properties: [{
									name: \'Flex Container\',
									property: \'display\',
									type: \'select\',
									defaults: \'block\',
									list: [
										{ value: \'block\', name: \'Disable\'},
										{ value: \'flex\', name: \'Enable\'}
									],
								},{
									name: \'Flex Parent\',
									property: \'label-parent-flex\',
									type: \'integer\',
								},{
									name      : \'Direction\',
									property  : \'flex-direction\',
									type    : \'radio\',
									defaults  : \'row\',
									list    : [{
										value   : \'row\',
										name    : \'Row\',
										className : \'icons-flex icon-dir-row\',
										title   : \'Row\',
									},{
										value   : \'row-reverse\',
										name    : \'Row reverse\',
										className : \'icons-flex icon-dir-row-rev\',
										title   : \'Row reverse\',
									},{
										value   : \'column\',
										name    : \'Column\',
										title   : \'Column\',
										className : \'icons-flex icon-dir-col\',
									},{
										value   : \'column-reverse\',
										name    : \'Column reverse\',
										title   : \'Column reverse\',
										className : \'icons-flex icon-dir-col-rev\',
									}],
								},{
									name      : \'Justify\',
									property  : \'justify-content\',
									type    : \'radio\',
									defaults  : \'flex-start\',
									list    : [{
										value   : \'flex-start\',
										className : \'icons-flex icon-just-start\',
										title   : \'Start\',
									},{
										value   : \'flex-end\',
										title    : \'End\',
										className : \'icons-flex icon-just-end\',
									},{
										value   : \'space-between\',
										title    : \'Space between\',
										className : \'icons-flex icon-just-sp-bet\',
									},{
										value   : \'space-around\',
										title    : \'Space around\',
										className : \'icons-flex icon-just-sp-ar\',
									},{
										value   : \'center\',
										title    : \'Center\',
										className : \'icons-flex icon-just-sp-cent\',
									}],
								},{
									name      : \'Align\',
									property  : \'align-items\',
									type    : \'radio\',
									defaults  : \'center\',
									list    : [{
										value   : \'flex-start\',
										title    : \'Start\',
										className : \'icons-flex icon-al-start\',
									},{
										value   : \'flex-end\',
										title    : \'End\',
										className : \'icons-flex icon-al-end\',
									},{
										value   : \'stretch\',
										title    : \'Stretch\',
										className : \'icons-flex icon-al-str\',
									},{
										value   : \'center\',
										title    : \'Center\',
										className : \'icons-flex icon-al-center\',
									}],
								},{
									name: \'Flex Children\',
									property: \'label-parent-flex\',
									type: \'integer\',
								},{
									name:     \'Order\',
									property:   \'order\',
									type:     \'integer\',
									defaults :  0,
									min: 0
								},{
									name    : \'Flex\',
									property  : \'flex\',
									type    : \'composite\',
									properties  : [{
										name:     \'Grow\',
										property:   \'flex-grow\',
										type:     \'integer\',
										defaults :  0,
										min: 0
									},{
										name:     \'Shrink\',
										property:   \'flex-shrink\',
										type:     \'integer\',
										defaults :  0,
										min: 0
									},{
										name:     \'Basis\',
										property:   \'flex-basis\',
										type:     \'integer\',
										units:    [\'px\',\'%\',\'\'],
										unit: \'\',
										defaults :  \'auto\',
									}],
								},{
									name      : \'Align\',
									property  : \'align-self\',
									type      : \'radio\',
									defaults  : \'auto\',
									list    : [{
										value   : \'auto\',
										name    : \'Auto\',
									},{
										value   : \'flex-start\',
										title    : \'Start\',
										className : \'icons-flex icon-al-start\',
									},{
										value   : \'flex-end\',
										title    : \'End\',
										className : \'icons-flex icon-al-end\',
									},{
										value   : \'stretch\',
										title    : \'Stretch\',
										className : \'icons-flex icon-al-str\',
									},{
										value   : \'center\',
										title    : \'Center\',
										className : \'icons-flex icon-al-center\',
									}],
								}]
							}
						],
					},
				},

				// Disable the storage manager for the moment
				storageManager: {
					id: \'fc-gjs-\' + editor_sfx,             // Prefix identifier that will be used inside storing and loading
					type: \'form-storage\',  // Type of the storage: local, remote, ... custom: SimpleStorage

					//type: \'remote\',
					//urlStore: \'http://localhost/endpoint_store\',
					//urlLoad: \'http://localhost/endpoint_load\',

					stepsBeforeSave: 1,
					storeComponents: true,  // Enable/Disable storing of components in JSON format
					storeStyles: true,      // Enable/Disable storing of rules in JSON format
					storeHtml: true,        // Enable/Disable storing of components as HTML string
					storeCss: true,         // Enable/Disable storing of rules as CSS string

					params: {}, // Custom parameters to pass with the remote storage request, eg. CSRF token
					headers: {}, // Custom headers for the remote storage request

					// Autosave is disabled on purpose: the form fields are synchronously written
					// by saveToForm() when the Joomla "adminForm" form is submitted (and by the
					// "Save Layout into form" button), avoiding any race with the async store.
					autosave: false,
					autoload: true,        // Whether to auto-load stored data on init

					// Do NOT show the browser "Changes you made may not be saved" dialog
					// (the layout fields are always written by saveToForm() on form submit)
noticeOnUnload: false,

				// Autosave must be DISABLED at the top level too (like noticeOnUnload): when active,
				// the storage manager store() re-writes the form fields on every edit step with the
				// transient text-only canvas state, silently clobbering the layout structure.
				autosave: false,
				stepsBeforeSave: 1000000,
				},


				// Block manager, define the available blocks to drag and drop into the layout area
				/*blockManager: {
					//appendTo: \'#gjs_\' + editor_sfx + \' #blocks\',
					//blocks: []
				},*/


				// We define a default panel as a sidebar to contain layers
				//panels: {
					//defaults: []
				//},



				/**
				 * The Selector Manager allows to assign classes and different states (eg. :hover) on components.
				 * Generally, it\'s used in conjunction with Style Manager but it\'s not mandatory
				 */
				/*selectorManager: {
					appendTo: \'#gjs_\' + editor_sfx + \' .styles-container\'
				},*/

				/*styleManager: {
					appendTo: \'#gjs_\' + editor_sfx + \' .styles-container\'
				},*/

				/*layerManager: {
					appendTo:\'#gjs_\' + editor_sfx + \' .layers-container\'
				},*/

				/*traitManager: {
					appendTo: \'#gjs_\' + editor_sfx + \' .traits-container\',
				},*/


				// Device manager: Desktop, Tablet, Mobile
				// NOTE mediaCondition \'min-width\' would INVERT the responsive workflow:
				// rules styled on a device get `@media (min-width: Xpx)` which targets all
				// LARGER viewports, not the selected one. With the default \'max-width\' a rule
				// created on e.g. Mobile landscape becomes `@media (max-width: 768px)`, i.e.
				// it only overrides the base (desktop) styles below that breakpoint.
				mediaCondition: \'max-width\',
				deviceManager: {
					devices: [{
						title: \'Mobiles in Portrait Mode\',
						name: \'Mobile Portrait\',
						height: \'568px\', // this value will be used on canvas width
						heightMedia: \'768px\', // this value will be used in CSS @media
						width: \'320px\', // this value will be used on canvas width
						widthMedia: \'480px\', // this value will be used in CSS @media
					}, {
						title: \'Mobiles in Landscape Mode\',
						name: \'Mobile Landscape\',
						height: \'320px\', // this value will be used on canvas height
						heightMedia: \'480px\', // this value will be used in CSS @media
						width: \'568px\', // this value will be used on canvas width
						widthMedia: \'768px\', // this value will be used in CSS @media
					}, {
						title: \'Tablets, (and Large Mobiles in Landscapes mode)\',
						name: \'Tablet\',
						width: \'768px\', // this value will be used on canvas width
						widthMedia: \'992px\', // this value will be used in CSS @media
					}, {
						name: \'Desktop\',
						width: \'\', // this value will be used on canvas width
						widthMedia: \'\',
					}]
				},

			});


			// On a device, GrapesJS writes the edits (drag AND StyleManager numeric) into the CSS rule
			// of the CURRENT device @media (e.g. Tablet -> @media (max-width:992px)), which is exactly
			// what we want for the responsive save (front renders per media query).
			// To preview those edits inside the builder, the canvas frame is sized to the device
			// width/height so its @media rules actually apply in the canvas (WYSIWYG editing).
			editor.on(\'change:device\', function()
			{
				var fclDev = editor.getModel().getDeviceModel();
				var fclW = fclDev ? (fclDev.get(\'width\') || \'\') : \'\';
				var fclH = fclDev ? (fclDev.get(\'height\') || \'\') : \'\';
				var fclFrame = editor.Canvas.getFrameEl();
				if (!fclFrame) return;
				fclFrame.style.width = fclW;
				fclFrame.style.height = fclH;
				var fclWrap = fclFrame.parentElement;
				if (fclWrap) { fclWrap.style.width = fclW; fclWrap.style.height = fclH; }
				editor.Canvas.refresh();
			});

			// The canvas <style> GrapesJS generates puts @media rules BEFORE the base rules,
			// so equal-specificity base rules win inside the canvas and per-device overrides
			// (width, padding, margin...) are invisible while editing (colors still won,
			// because they are absent from the base rules).
			// => Keep our own <style> (LAST element of the frame BODY, correct order = base then
			// @media) refreshed with editor.getCss() on every rule change. The saved CSS is not
			// affected (save order stays base -> @media, as GrapesJS produces on export).
			(function()
			{
				var fclRefCss = function()
				{
					var fclFrame = editor.Canvas.getFrameEl();
					if (!fclFrame || !fclFrame.contentDocument) return;
					var fclDoc = fclFrame.contentDocument;
					var fclRoot = fclDoc.body || fclDoc.documentElement;
					var fclSt = fclDoc.getElementById("fcl-builder-style");
					if (!fclSt)
					{
						fclSt = fclDoc.createElement("style");
						fclSt.id = "fcl-builder-style";
						fclRoot.appendChild(fclSt);
					}
					fclSt.textContent = editor.getCss();
				};
				var fclTryInstall = function()
				{
					var fclRules = editor.Css.getAll();
					if (!fclRules || !fclRules.length) return; // retried below until rules exist
					if (fclRules.__fclCssHooked === true) return;
					fclRules.__fclCssHooked = true;
					fclRules.on("add change remove", fclRefCss);
					fclRefCss();
				};
				var fclTries = 0;
				fclTryInstall();
				var fclInt = setInterval(function()
				{
					fclTries++;
					fclTryInstall();
					if (fclTries > 40) clearInterval(fclInt);
				}, 150);
				// guarantee our style tag stays the LAST element of the frame body, whatever
				// GrapesJS inserts (it appends its own component css <style> at the body end)
				var fclEnsureLast = function()
				{
					var fclFrame = editor.Canvas.getFrameEl();
					if (!fclFrame || !fclFrame.contentDocument) return;
					var fclDoc = fclFrame.contentDocument;
					var fclSt = fclDoc.getElementById("fcl-builder-style");
					var fclRoot = fclDoc.body || fclDoc.documentElement;
					if (fclSt && fclRoot.lastElementChild !== fclSt) fclRoot.appendChild(fclSt);
				};
				setInterval(fclEnsureLast, 150);
			})();

			var pn = editor.Panels;
			var modal = editor.Modal;
			editor.Commands.add(\'canvas-clear\', function()
			{
				if (confirm(\'Are you sure to clean the canvas?\'))
				{
					var comps = editor.DomComponents.clear();
					var composer = editor.CssComposer.clear();
					setTimeout(function()
					{
						editor.store().catch(e => console.error(e));
					}, 0);
				}
			});


			editor.Commands.add(\'store-data\', function(editor, sender) {
				editor.store();
				console.log(\'Stored data\');
				setTimeout(function()
				{
					sender && sender.set(\'active\', false);
				}, 300);
			});


			// Add info command
			// To read this uncomment the respective DIV at ... $html
			/*
			var cmdm = editor.Commands;
			var mdlClass = \'gjs-mdl-dialog-sm\';
			var infoContainer = document.getElementById(\'info-panel\');
			cmdm.add(\'open-info\', function() {
				var mdlDialog = document.querySelector(\'.gjs-mdl-dialog\');
				mdlDialog.className += \' \' + mdlClass;
				infoContainer.style.display = \'block\';
				modal.setTitle(\'About this demo\');
				modal.setContent(infoContainer);
				modal.open();
				modal.getModel().once(\'change:open\', function() {
					mdlDialog.className = mdlDialog.className.replace(mdlClass, \'\');
				})
			});
			pn.addButton(\'options\', {
				id: \'open-info\',
				className: \'fa fa-question-circle\',
				command: function() { editor.runCommand(\'open-info\') },
				attributes: {
					\'title\': \'About\',
					\'data-tooltip-pos\': \'bottom\',
				},
			});
			*/


			// Simple warn notifier
			var origWarn = console.warn;
			toastr.options = {
				closeButton: true,
				preventDuplicates: true,
				showDuration: 250,
				hideDuration: 150
			};
			console.warn = function (msg) {
				if (msg.indexOf(\'[undefined]\') == -1 && msg.indexOf(\'writeDynaList\') == -1)
				{
					toastr.warning(msg);
				}
				origWarn(msg);
			};


			// Add and beautify tooltips
			[[\'sw-visibility\', \'Show Borders\'], [\'preview\', \'Preview\'], [\'fullscreen\', \'Fullscreen\'],
			 [\'export-template\', \'Export\'], [\'undo\', \'Undo\'], [\'redo\', \'Redo\'],
			 [\'gjs-open-import-webpage\', \'Import\'], [\'canvas-clear\', \'Clear canvas\']]
			.forEach(function(item) {
				var b = pn.getButton(\'options\', item[0]);
				b && b.set(\'attributes\', {title: item[1], \'data-tooltip-pos\': \'bottom\'});
			});
			[[\'open-sm\', \'Style Manager\'], [\'open-layers\', \'Layers\'], [\'open-blocks\', \'Blocks\']]
			.forEach(function(item) {
				var b = pn.getButton(\'views\', item[0]);
				b && b.set(\'attributes\', {title: item[1], \'data-tooltip-pos\': \'bottom\'});
			});
			var titles = document.querySelectorAll(\'*[title]\');

			for (var i = 0; i < titles.length; i++)
			{
				var el = titles[i];
				var title = el.getAttribute(\'title\');
				title = title ? title.trim(): \'\';

				if (!title)
				{
					break;
				}

				el.setAttribute(\'data-tooltip\', title);
				el.setAttribute(\'title\', \'\');
			}

			// Show borders by default
			var swVisBtn = pn.getButton(\'options\', \'sw-visibility\');
			swVisBtn && swVisBtn.set(\'active\', 1);


			// Start and end events
			editor.on(\'storage:start\', console.log(\'storage:start\'));
			editor.on(\'storage:end\', console.log(\'storage:end  \'));

			// Store and load events
			editor.on(\'storage:load\', function(e) { console.log(\'Loaded \') });
			editor.on(\'storage:store\', function(e) { console.log(\'Stored \') });


			// Do stuff on load
editor.on(\'load\', function()
				{
					// Add the missing flex properties to the "Flex" sector (guaranteed present
					// whatever the active Style Manager sectors are: custom or default ones)
					var sm = editor.StyleManager;
					var flexSector = null;
					var secs = sm.getSectors().models;
					for (var si = 0; si < secs.length; si++)
					{
						if (secs[si].getName() == \'Flex\') { flexSector = secs[si]; break; }
					}
					if (!flexSector)
					{
						flexSector = sm.addSector(\'flex\', { name: \'Flex\', open: false, properties: [] });
					}
					if (!flexSector.getProperty(\'flex-wrap\'))
					{
						sm.addProperty(flexSector.get(\'id\'), {
							name: \'Wrap\',
							property: \'flex-wrap\',
							type: \'radio\',
							defaults: \'nowrap\',
							list: [
								{ value: \'wrap\', name: \'Wrap\' },
								{ value: \'nowrap\', name: \'No wrap\' }
							]
						});
					}
					if (!flexSector.getProperty(\'gap\'))
					{
						sm.addProperty(flexSector.get(\'id\'), {
							name: \'Gap\',
							property: \'gap\',
							type: \'integer\',
							units: [\'px\', \'em\'],
							defaults: 0,
							min: 0
						});
					}

				// Load and show settings and style manager
				var openTmBtn = pn.getButton(\'views\', \'open-tm\');
				openTmBtn && openTmBtn.set(\'active\', 1);
				var openSm = pn.getButton(\'views\', \'open-sm\');
				openSm && openSm.set(\'active\', 1);

				// Add Settings Sector (Traits) above the Style Manager sectors
				var smSectors = document.querySelector(\'.gjs-sm-sectors\');
				if (smSectors)
				{
					var traitsSector = document.createElement(\'div\');
					traitsSector.className = \'gjs-sm-sector no-select\';
					traitsSector.innerHTML =
						\'<div class="gjs-sm-title"><span class="icon-settings fa fa-cog"></span> Settings</div>\' +
						\'<div class="gjs-sm-properties" style="display: none;"></div>\';
					smSectors.parentNode.insertBefore(traitsSector, smSectors);
					var traitsProps = traitsSector.querySelector(\'.gjs-sm-properties\');
					var trtTraits = document.querySelector(\'.gjs-trt-traits\');
					if (trtTraits) traitsProps.appendChild(trtTraits);
					traitsSector.querySelector(\'.gjs-sm-title\').addEventListener(\'click\', function()
					{
						var traitStyle = traitsProps.style;
						traitStyle.display = traitStyle.display == \'none\' ? \'block\' : \'none\';
					});
				}

				// Open block manager
				var openBlocksBtn = editor.Panels.getButton(\'views\', \'open-blocks\');
				openBlocksBtn && openBlocksBtn.set(\'active\', 1);
			});

			/**
			 * Stray \'textnode\' entries (debris from older/lossy saves) sit directly inside a
			 * non-text component and cannot be selected / edited / deleted in the canvas.
			 * Wrap each into a proper \'text\' component so it becomes a normal element.
			 */
			function fcl_fix_stray_textnodes(comp)
			{
				if (!comp || typeof comp !== \'object\' || !Array.isArray(comp.components)) return comp;
				var textChildren = comp.type === \'text\';
				var children = comp.components;
				for (var i = 0; i < children.length; i++)
				{
					var child = children[i];
					if (child && child.type === \'textnode\' && !textChildren)
					{
						children[i] = { type: \'text\', content: typeof child.content === \'string\' ? child.content : \'\' };
					}
					else if (child && typeof child === \'object\')
					{
						fcl_fix_stray_textnodes(child);
					}
				}
				return comp;
			}

			editor.StorageManager.add(\'form-storage\', {
				/**
				 * Load the saved layout and return a GrapesJS project object to load.
				 * Preferred source: the full project data (_data field), saved verbatim by
				 * getProjectData(). Legacy fallback: minimal project rebuilt from _html/_css.
				 */
				async load()
				{
					const qs = id => document.querySelector(\'#\' + element_id + \'_\' + id);

					// Preferred: the full GrapesJS project data, saved verbatim by getProjectData().
					// It is the exact, lossless representation (pages/frames/components/styles/assets),
					// so feeding it back to the editor\'s own loadProjectData() round-trips faithfully.
					var raw = fcl_get(qs(\'data\'));
					if (raw)
					{
						try
						{
							var project = JSON.parse(raw);
							console.log(\'Load layout: project data\');
							if (project && Array.isArray(project.pages))
							{
								for (var p = 0; p < project.pages.length; p++)
								{
									var pf = project.pages[p];
									if (!pf.frames) continue;
									for (var g = 0; g < pf.frames.length; g++)
									{
										if (pf.frames[g] && pf.frames[g].component)
										{
											pf.frames[g].component = fcl_fix_stray_textnodes(pf.frames[g].component);
										}
									}
								}
							}
							return project;
						}
						catch(e)
						{
							console.warn(\'Invalid saved project data, falling back to HTML\', e);
						}
					}

					// Legacy fallback: build a minimal project from the rendered HTML/CSS fields
					var html = fcl_get(qs(\'html\'));
					var css  = fcl_get(qs(\'css\'));
					var result = {};

					if (html || css)
					{
						var frame = {};
						if (html) frame.component = { components: html };
						if (css)  frame.styles = css;

						result.pages = [{
							id: \'fc-page-main\',
							name: \'Main\',
							frames: [Object.assign({ id: \'fc-frame-1\' }, frame)]
						}];
					}

					return result;
				},

				/**
				 * Store layout data into the form fields (legacy format) for the backend.
				 */
				async store(projectData)
				{
					// NOTE: intentional NO-OP. saveToForm() is the ONLY writer of the form
					// fields, and it runs exactly once when the form is actually submitted.
					return projectData;
				},
			});


			/**
			 * All controls carrying the same form NAME as the "#<id>" field.
			 * The backend reads fields by NAME; if the field element is rendered more
			 * than once, PHP keeps the LAST occurrence -> we must write ALL of them.
			 */
			function fcl_controlsFor(id)
			{
				var el = document.querySelector(\'#\' + id);
				if (!el) return [];
				var name = el.getAttribute(\'name\');
				if (name)
				{
					var all = document.querySelectorAll(\'[name="\' + name + \'"]\');
					if (all.length) return Array.prototype.slice.call(all);
				}
				return [el];
			}

			function fcl_setField(id, val)
			{
				var list = fcl_controlsFor(id);
				for (var i = 0; i < list.length; i++)
				{
					try { list[i].value = val; } catch(e) {}
				}
				return list.length;
			}

			function fcSyncTraitInputsToModel()
			{
				// GrapesJS commits text-trait values on the input "change"/blur event, so at the
				// moment the Joomla form is submitted the component attributes can still hold the
				// previous (empty) value while the Settings panel input shows the freshly typed
				// text. Force the panel values back into the model before serialization.
				var comp = editor.getSelected();
				if (!comp || !comp.get || comp.get(\'type\') !== \'fc-field\') return;
				if (!comp.get(\'traits\') || !comp.get(\'traits\').models) return;
				var rows = document.querySelectorAll(\'.gjs-trt-trait\');
				var attrMap = { \'Prefix\': \'data-fc-prefix\', \'Suffix\': \'data-fc-suffix\' };
				for (var i = 0; i < rows.length; i++)
				{
					var row = rows[i];
					var lblEl = row.querySelector(\'.gjs-trt-trait__lbl\');
					var input = row.querySelector(\'input, textarea\');
					if (!lblEl || !input) continue;
					var attrName = attrMap[lblEl.textContent.trim()];
					if (!attrName) continue;
					var t = null;
					for (var j = 0; j < comp.get(\'traits\').models.length; j++)
					{
						if (comp.get(\'traits\').models[j].get(\'name\') === attrName) { t = comp.get(\'traits\').models[j]; break; }
					}
					if (!t) continue;
					var current = comp.getAttributes()[attrName] || \'\';
					if (String(input.value) !== String(current))
					{
						t.setValue(input.value);
					}
				}
			}

			// Style Manager: replace the generic default sectors with a set tailored to
			// the layout blocks (spacing, flex, typography, background, border & effects).
			var fcl_sm_sectors = [
				{
					id: \'fcl-layout\',
					name: \'Layout & Spacing\',
					open: 1,
					buildProps: [\'margin\', \'padding\'],
					properties: [
						{ name: \'Display\', property: \'display\', type: \'select\', defaults: \'block\',
							options: [
								{ value: \'block\', name: \'Block\' },
								{ value: \'inline\', name: \'Inline\' },
								{ value: \'inline-block\', name: \'Inline block\' },
								{ value: \'flex\', name: \'Flex\' },
								{ value: \'inline-flex\', name: \'Inline flex\' },
								{ value: \'grid\', name: \'Grid\' },
								{ value: \'inline-grid\', name: \'Inline grid\' },
								{ value: \'none\', name: \'None\' },
								{ value: \'contents\', name: \'Contents\' },
							] },
						{ name: \'Position\', property: \'position\', type: \'select\', defaults: \'static\',
							options: [
								{ value: \'static\', name: \'Static\' },
								{ value: \'relative\', name: \'Relative\' },
								{ value: \'absolute\', name: \'Absolute\' },
								{ value: \'fixed\', name: \'Fixed\' },
								{ value: \'sticky\', name: \'Sticky\' },
							] },
						{ name: \'Float\', property: \'float\', type: \'select\', defaults: \'none\',
							options: [
								{ value: \'none\', name: \'None\' },
								{ value: \'left\', name: \'Left\' },
								{ value: \'right\', name: \'Right\' },
							] },
						{ name: \'Width\', property: \'width\', type: \'number\', defaults: \'auto\', units: [\'px\', \'em\', \'rem\', \'%\'] },
						{ name: \'Height\', property: \'height\', type: \'number\', defaults: \'auto\', units: [\'px\', \'em\', \'rem\', \'%\'] },
						{ name: \'Min width\', property: \'min-width\', type: \'number\', defaults: \'0\', units: [\'px\', \'em\', \'rem\', \'%\'] },
						{ name: \'Min height\', property: \'min-height\', type: \'number\', defaults: \'0\', units: [\'px\', \'em\', \'rem\', \'%\'] },
						{ name: \'Max width\', property: \'max-width\', type: \'number\', defaults: \'none\', units: [\'px\', \'em\', \'rem\', \'%\'] },
						{ name: \'Max height\', property: \'max-height\', type: \'number\', defaults: \'none\', units: [\'px\', \'em\', \'rem\', \'%\'] },
						{ name: \'Box sizing\', property: \'box-sizing\', type: \'select\', defaults: \'content-box\',
							options: [
								{ value: \'content-box\', name: \'Content box\' },
								{ value: \'border-box\', name: \'Border box\' },
								{ value: \'inherit\', name: \'Inherit\' },
							] },
						{ name: \'Overflow\', property: \'overflow\', type: \'select\', defaults: \'visible\',
							options: [
								{ value: \'visible\', name: \'Visible\' },
								{ value: \'hidden\', name: \'Hidden\' },
								{ value: \'scroll\', name: \'Scroll\' },
								{ value: \'auto\', name: \'Auto\' },
							] },
						{ name: \'Z-index\', property: \'z-index\', type: \'number\', defaults: \'auto\' },
					],
				},
				{
					id: \'fcl-flex\',
					name: \'Flex Container\',
					properties: [
						{ name: \'Flex direction\', property: \'flex-direction\', type: \'select\', defaults: \'row\',
							options: [
								{ value: \'row\', name: \'Row\' },
								{ value: \'row-reverse\', name: \'Row reverse\' },
								{ value: \'column\', name: \'Column\' },
								{ value: \'column-reverse\', name: \'Column reverse\' },
							] },
						{ name: \'Wrap\', property: \'flex-wrap\', type: \'select\', defaults: \'nowrap\',
							options: [
								{ value: \'nowrap\', name: \'No wrap\' },
								{ value: \'wrap\', name: \'Wrap\' },
								{ value: \'wrap-reverse\', name: \'Wrap reverse\' },
							] },
						{ name: \'Justify content\', property: \'justify-content\', type: \'select\', defaults: \'flex-start\',
							options: [
								{ value: \'flex-start\', name: \'Flex start\' },
								{ value: \'flex-end\', name: \'Flex end\' },
								{ value: \'center\', name: \'Center\' },
								{ value: \'space-between\', name: \'Space between\' },
								{ value: \'space-around\', name: \'Space around\' },
								{ value: \'space-evenly\', name: \'Space evenly\' },
							] },
						{ name: \'Align items\', property: \'align-items\', type: \'select\', defaults: \'stretch\',
							options: [
								{ value: \'stretch\', name: \'Stretch\' },
								{ value: \'flex-start\', name: \'Flex start\' },
								{ value: \'flex-end\', name: \'Flex end\' },
								{ value: \'center\', name: \'Center\' },
								{ value: \'baseline\', name: \'Baseline\' },
							] },
						{ name: \'Align content\', property: \'align-content\', type: \'select\', defaults: \'stretch\',
							options: [
								{ value: \'stretch\', name: \'Stretch\' },
								{ value: \'flex-start\', name: \'Flex start\' },
								{ value: \'flex-end\', name: \'Flex end\' },
								{ value: \'center\', name: \'Center\' },
								{ value: \'space-between\', name: \'Space between\' },
								{ value: \'space-around\', name: \'Space around\' },
							] },
						{ name: \'Gap\', property: \'gap\', type: \'number\', defaults: \'0px\', units: [\'px\', \'em\', \'rem\'] },
					],
				},
				{
					id: \'fcl-flex-item\',
					name: \'Flex Item\',
					properties: [
						{ name: \'Flex grow\', property: \'flex-grow\', type: \'number\', defaults: \'0\' },
						{ name: \'Flex shrink\', property: \'flex-shrink\', type: \'number\', defaults: \'1\' },
						{ name: \'Flex basis\', property: \'flex-basis\', type: \'select\', defaults: \'auto\',
							options: [
								{ value: \'auto\', name: \'Auto\' },
								{ value: \'0px\', name: \'0px\' },
								{ value: \'25%\', name: \'25%\' },
								{ value: \'33.33%\', name: \'33.33%\' },
								{ value: \'50%\', name: \'50%\' },
								{ value: \'66.66%\', name: \'66.66%\' },
								{ value: \'75%\', name: \'75%\' },
								{ value: \'100%\', name: \'100%\' },
							] },
						{ name: \'Align self\', property: \'align-self\', type: \'select\', defaults: \'auto\',
							options: [
								{ value: \'auto\', name: \'Auto\' },
								{ value: \'stretch\', name: \'Stretch\' },
								{ value: \'flex-start\', name: \'Flex start\' },
								{ value: \'flex-end\', name: \'Flex end\' },
								{ value: \'center\', name: \'Center\' },
								{ value: \'baseline\', name: \'Baseline\' },
							] },
						{ name: \'Order\', property: \'order\', type: \'number\', defaults: \'0\' },
					],
				},
				{
					id: \'fcl-typography\',
					name: \'Typography\',
					properties: [
						{ name: \'Color\', property: \'color\', type: \'color\', defaults: \'#000000\' },
						{ name: \'Font size\', property: \'font-size\', type: \'number\', defaults: \'1rem\', units: [\'px\', \'rem\', \'em\', \'pt\', \'%\'] },
						{ name: \'Font weight\', property: \'font-weight\', type: \'select\', defaults: \'normal\',
							options: [
								{ value: \'normal\', name: \'Normal\' },
								{ value: \'bold\', name: \'Bold\' },
								{ value: \'bolder\', name: \'Bolder\' },
								{ value: \'lighter\', name: \'Lighter\' },
								{ value: \'100\', name: \'100\' },
								{ value: \'200\', name: \'200\' },
								{ value: \'300\', name: \'300\' },
								{ value: \'400\', name: \'400\' },
								{ value: \'500\', name: \'500\' },
								{ value: \'600\', name: \'600\' },
								{ value: \'700\', name: \'700\' },
								{ value: \'800\', name: \'800\' },
								{ value: \'900\', name: \'900\' },
							] },
						{ name: \'Font style\', property: \'font-style\', type: \'select\', defaults: \'normal\',
							options: [
								{ value: \'normal\', name: \'Normal\' },
								{ value: \'italic\', name: \'Italic\' },
								{ value: \'oblique\', name: \'Oblique\' },
							] },
						{ name: \'Line height\', property: \'line-height\', type: \'number\', defaults: \'normal\', units: [\'px\', \'rem\', \'em\', \'%\'] },
						{ name: \'Letter spacing\', property: \'letter-spacing\', type: \'number\', defaults: \'normal\', units: [\'px\', \'em\'] },
						{ name: \'Text align\', property: \'text-align\', type: \'select\', defaults: \'inherit\',
							options: [
								{ value: \'inherit\', name: \'Inherit\' },
								{ value: \'left\', name: \'Left\' },
								{ value: \'center\', name: \'Center\' },
								{ value: \'right\', name: \'Right\' },
								{ value: \'justify\', name: \'Justify\' },
								{ value: \'start\', name: \'Start\' },
								{ value: \'end\', name: \'End\' },
							] },
						{ name: \'Text decoration\', property: \'text-decoration\', type: \'select\', defaults: \'none\',
							options: [
								{ value: \'none\', name: \'None\' },
								{ value: \'underline\', name: \'Underline\' },
								{ value: \'overline\', name: \'Overline\' },
								{ value: \'line-through\', name: \'Line through\' },
							] },
						{ name: \'Text transform\', property: \'text-transform\', type: \'select\', defaults: \'none\',
							options: [
								{ value: \'none\', name: \'None\' },
								{ value: \'capitalize\', name: \'Capitalize\' },
								{ value: \'uppercase\', name: \'Uppercase\' },
								{ value: \'lowercase\', name: \'Lowercase\' },
							] },
						{ name: \'White space\', property: \'white-space\', type: \'select\', defaults: \'normal\',
							options: [
								{ value: \'normal\', name: \'Normal\' },
								{ value: \'nowrap\', name: \'No wrap\' },
								{ value: \'pre\', name: \'Pre\' },
								{ value: \'pre-wrap\', name: \'Pre wrap\' },
								{ value: \'pre-line\', name: \'Pre line\' },
								{ value: \'break-spaces\', name: \'Break spaces\' },
							] },
						{ name: \'Text shadow\', property: \'text-shadow\', type: \'composite\',
							properties: [
								{ name: \'Offset X\', property: \'text-shadow-x\', type: \'number\', defaults: \'0px\', units: [\'px\', \'em\', \'rem\'] },
								{ name: \'Offset Y\', property: \'text-shadow-y\', type: \'number\', defaults: \'0px\', units: [\'px\', \'em\', \'rem\'] },
								{ name: \'Blur\', property: \'text-shadow-blur\', type: \'number\', defaults: \'0px\', units: [\'px\', \'em\', \'rem\'] },
								{ name: \'Color\', property: \'text-shadow-color\', type: \'color\', defaults: \'#000000\' },
							] },
						{ name: \'Link styles\', property: \'data-fc-linkstyles\', type: \'composite\', defaults: \'\',
							properties: [
								{ name: \'Link color\', property: \'data-fc-linkstyles-color\', type: \'color\', defaults: \'\' },
								{ name: \'Link hover color\', property: \'data-fc-linkstyles-hover\', type: \'color\', defaults: \'\' },
								{ name: \'Link font size\', property: \'data-fc-linkstyles-size\', type: \'number\', defaults: \'\', units: [\'px\', \'rem\', \'em\'] },
								{ name: \'Link font weight\', property: \'data-fc-linkstyles-weight\', type: \'select\', defaults: \'\',
									options: [
										{ value: \'\', name: \'Default\' },
										{ value: \'normal\', name: \'Normal\' },
										{ value: \'bold\', name: \'Bold\' },
										{ value: \'300\', name: \'300\' },
										{ value: \'400\', name: \'400\' },
										{ value: \'500\', name: \'500\' },
										{ value: \'600\', name: \'600\' },
										{ value: \'700\', name: \'700\' },
										{ value: \'800\', name: \'800\' },
									] },
								{ name: \'Link decoration\', property: \'data-fc-linkstyles-deco\', type: \'select\', defaults: \'\',
									options: [
										{ value: \'\', name: \'Default\' },
										{ value: \'none\', name: \'None\' },
										{ value: \'underline\', name: \'Underline\' },
										{ value: \'overline\', name: \'Overline\' },
										{ value: \'line-through\', name: \'Line through\' },
									] },
							] },
					],
				},
				{
					id: \'fcl-background\',
					name: \'Background\',
					properties: [
						{ name: \'Background color\', property: \'background-color\', type: \'color\', defaults: \'transparent\' },
						{ name: \'Background repeat\', property: \'background-repeat\', type: \'select\', defaults: \'no-repeat\',
							options: [
								{ value: \'no-repeat\', name: \'No repeat\' },
								{ value: \'repeat\', name: \'Repeat\' },
								{ value: \'repeat-x\', name: \'Repeat x\' },
								{ value: \'repeat-y\', name: \'Repeat y\' },
								{ value: \'space\', name: \'Space\' },
								{ value: \'round\', name: \'Round\' },
							] },
						{ name: \'Background position\', property: \'background-position\', type: \'select\', defaults: \'top left\',
							options: [
								{ value: \'top left\', name: \'Top left\' },
								{ value: \'top center\', name: \'Top center\' },
								{ value: \'top right\', name: \'Top right\' },
								{ value: \'center left\', name: \'Center left\' },
								{ value: \'center center\', name: \'Center\' },
								{ value: \'center right\', name: \'Center right\' },
								{ value: \'bottom left\', name: \'Bottom left\' },
								{ value: \'bottom center\', name: \'Bottom center\' },
								{ value: \'bottom right\', name: \'Bottom right\' },
							] },
						{ name: \'Background size\', property: \'background-size\', type: \'select\', defaults: \'auto\',
							options: [
								{ value: \'auto\', name: \'Auto\' },
								{ value: \'cover\', name: \'Cover\' },
								{ value: \'contain\', name: \'Contain\' },
								{ value: \'100%\', name: \'100%\' },
								{ value: \'100% 100%\', name: \'Stretch\' },
							] },
						{ name: \'Background attachment\', property: \'background-attachment\', type: \'select\', defaults: \'scroll\',
							options: [
								{ value: \'scroll\', name: \'Scroll\' },
								{ value: \'fixed\', name: \'Fixed\' },
								{ value: \'local\', name: \'Local\' },
							] },
					],
				},
				{
					id: \'fcl-border\',
					name: \'Border & Effects\',
					properties: [
						{ name: \'Border width\', property: \'border-width\', type: \'composite\',
							// Per-side border widths (same pattern as the padding/margin grids in
							// GrapesJS Studio): native CSS sub-properties, so the canvas preview
							// and the front-end inline styles both apply them directly.
							properties: [
								{ name: \'Top\', property: \'border-top-width\', type: \'number\', defaults: \'0px\', units: [\'px\', \'em\', \'rem\'] },
								{ name: \'Right\', property: \'border-right-width\', type: \'number\', defaults: \'0px\', units: [\'px\', \'em\', \'rem\'] },
								{ name: \'Bottom\', property: \'border-bottom-width\', type: \'number\', defaults: \'0px\', units: [\'px\', \'em\', \'rem\'] },
								{ name: \'Left\', property: \'border-left-width\', type: \'number\', defaults: \'0px\', units: [\'px\', \'em\', \'rem\'] },
							] },
						{ name: \'Border style\', property: \'border-style\', type: \'select\', defaults: \'none\',
							options: [
								{ value: \'none\', name: \'None\' },
								{ value: \'solid\', name: \'Solid\' },
								{ value: \'dashed\', name: \'Dashed\' },
								{ value: \'dotted\', name: \'Dotted\' },
								{ value: \'double\', name: \'Double\' },
								{ value: \'groove\', name: \'Groove\' },
								{ value: \'ridge\', name: \'Ridge\' },
								{ value: \'inset\', name: \'Inset\' },
								{ value: \'outset\', name: \'Outset\' },
							] },
						{ name: \'Border color\', property: \'border-color\', type: \'color\', defaults: \'#000000\' },
						{ name: \'Border radius\', property: \'border-radius\', type: \'number\', defaults: \'0px\', units: [\'px\', \'em\', \'rem\', \'%\'] },
						{ name: \'Opacity\', property: \'opacity\', type: \'number\', defaults: \'1\' },
						{ name: \'Cursor\', property: \'cursor\', type: \'select\', defaults: \'default\',
							options: [
								{ value: \'default\', name: \'Default\' },
								{ value: \'pointer\', name: \'Pointer\' },
								{ value: \'move\', name: \'Move\' },
								{ value: \'text\', name: \'Text\' },
								{ value: \'grab\', name: \'Grab\' },
								{ value: \'crosshair\', name: \'Crosshair\' },
								{ value: \'help\', name: \'Help\' },
								{ value: \'wait\', name: \'Wait\' },
								{ value: \'zoom-in\', name: \'Zoom in\' },
							] },
						{ name: \'Pointer events\', property: \'pointer-events\', type: \'select\', defaults: \'auto\',
							options: [
								{ value: \'auto\', name: \'Auto\' },
								{ value: \'none\', name: \'None\' },
							] },
					],
				},
			];

			function fcl_configureStyleManager()
			{
				var sm = editor.StyleManager;
				// The core/preset default sectors are generic; replace them all with ours.
				// This runs on every editor load, so it is idempotent (reset clears first).
				var s = sm.getSectors();
				s.reset();
				var i;
				for (i = 0; i < fcl_sm_sectors.length; i++)
				{
					sm.addSector(fcl_sm_sectors[i].id, fcl_sm_sectors[i]);
				}
				// data-fc-linkstyles-* are not real CSS properties, so GrapesJS silently skips
				// their style write. Instead each value is persisted as an ATTRIBUTE on the
				// selected component (the source of truth for the canvas overlay and the
				// front-end rules generated by _collectLinkRules) AND in its style map (so
				// the Style Manager can re-display the values when the block is re-selected).
				// The StyleManager emits \'style:property:update\' for ANY property change
				// (whatever its CSS validity, and regardless of the lazily created property
				// models), with the Property model that changed: either a sub-property of the
				// "Link styles" composite or the composite itself, both handled here.
				editor.on(\'style:property:update\', function(ev)
				{
					var prop = ev && ev.property ? ev.property : null;
					if (!prop || typeof prop.get !== \'function\') return;
					var pname = prop.get(\'property\') || \'\';
					var isGroup = pname === \'data-fc-linkstyles\';
					var isSub = String(pname).indexOf(\'data-fc-linkstyles-\') === 0;
					if (!isGroup && !isSub) return;
					var target = null;
					try { target = sm.getTarget ? sm.getTarget() : null; } catch (e) {}
					if (!target && typeof editor.getSelected === \'function\') target = editor.getSelected();
					if (!target || typeof target.addAttributes !== \'function\') return;
					var attrs = {};
					var style = {};
					var collect = function(p)
					{
						if (!p || typeof p.get !== \'function\') return;
						var sv = \'\';
						try { sv = p.getValue ? p.getValue() : \'\'; } catch (e) {}
						var u = p.get(\'unit\');
						var spn = p.get(\'property\') || \'\';
						if (spn === \'data-fc-linkstyles-size\' && u && String(sv) !== \'\' && String(sv).indexOf(u) === -1)
						{
							sv = String(sv) + String(u);
						}
						attrs[spn] = String(sv);
						if (String(sv) !== \'\') style[spn] = String(sv);
					};
					if (isGroup)
					{
						// Composite-level event: collect every sub-property to keep the
						// attributes in sync whatever sub-value actually changed.
						var subs = prop.getProperties ? prop.getProperties() : null;
						var list = subs && subs.models ? subs.models : subs;
						if (list && typeof list.length === \'number\')
						{
							for (var j = 0; j < list.length; j++) collect(list[j]);
						}
					}
					else collect(prop);
					if (target.addStyle) target.addStyle(style);
					target.addAttributes(attrs);
					fcl_ensureCanvasLinkStyles();
				});
			}

			function saveToForm()
			{
				// The GrapesJS project JSON is the single source of truth for the layout.
				// The front-end HTML/CSS/JS are regenerated DETERMINISTICALLY server-side
				// from it at save time (see JHtmlFclayoutbuilder::renderLayoutFromProject),
				// because capturing the live canvas states produced transient/partial values.
				try { fcSyncTraitInputsToModel(); } catch(e) { console.error(\'fcSyncTraitInputsToModel error:\', e); }
				var project = null;
				try { project = editor.getProjectData(); }
				catch(e) { console.error(\'getProjectData error:\', e); }
				if (project) fcl_setField(element_id + \'_data\', JSON.stringify(project));
			}

			// Ensure the layout fields are written when the Joomla form is submitted.
			// NOTE: the Joomla toolbar Save button calls form.submit() programmatically,
			// which does NOT fire the "submit" event -> we must intercept HTMLFormElement.prototype.submit.
			var _fcOrigSubmit = HTMLFormElement.prototype.submit;
			HTMLFormElement.prototype.submit = function()
			{
				if (this && this.querySelector(\'#\' + element_id + \'_html\'))
				{
					try { saveToForm(); } catch(e) { console.error(\'saveToForm error:\', e); }
				}
				return _fcOrigSubmit.apply(this, arguments);
			};

			// Catch the native-submit-button path too (fires a "submit" event; the prototype
			// intercept above does not run then). Added in capture phase on document.
			document.addEventListener(\'submit\', function(e)
			{
				if (e && e.target && e.target.querySelector && e.target.querySelector(\'#\' + element_id + \'_html\'))
				{
					try { saveToForm(); } catch(err) { console.error(\'saveToForm (capture) error:\', err); }
				}
			}, true);

			// NOTE: no continuous field sync on purpose. Writing the fields on every edit
			// step captured transient/partial editor states during load and clobbered the
			// layout structure. saveToForm() runs once, when the form is actually submitted.

			var adminForm2 = document.querySelector(\'form#adminForm\');
			if (adminForm2)
			{
				adminForm2.addEventListener(\'submit\', function()
				{
					try { saveToForm(); } catch(e) { console.error(\'saveToForm error:\', e); }
				}, true);
			}


			/*
			// Define commands to show / hide traits
			editor.Commands.add(\'show-traits\', {
				getTraitsEl(editor) {
					console.log(\'show-traits\');
					var row = editor.getContainer().closest(\'.editor-row\');
					return row.querySelector(\'.gjs-pn-views-container\');
				},
				run(editor, sender) {
					this.getTraitsEl(editor).style.display = \'\';
				},
				stop(editor, sender) {
					this.getTraitsEl(editor).style.display = \'none\';
				},
			});

			// Define commands to show / hide layers
			editor.Commands.add(\'show-layers\', {
				getLayersEl(row) {
					console.log(\'show-layers\');
					var row = editor.getContainer().closest(\'.editor-row\'); 
					return row.querySelector(\'.gjs-pn-views-container\')
				},
				run(editor, sender) {
					var lmEl = this.getLayersEl(editor);
					lmEl.style.display = \'\';
				},
				stop(editor, sender) {
					var lmEl = this.getLayersEl(editor);
					lmEl.style.display = \'none\';
				},
			});

			// Define command to show / hide styles
			editor.Commands.add(\'show-styles\', {
				getStylesEl(row) {
					console.log(\'show-styles\');
					var row = editor.getContainer().closest(\'.editor-row\');
					return row.querySelector(\'.gjs-pn-views-container \')
				},

				run(editor, sender) {
					var smEl = this.getStylesEl(editor);
					smEl.style.display = \'\';
				},
				stop(editor, sender) {
					var smEl = this.getStylesEl(editor);
					smEl.style.display = \'none\';
				},
			});
			*/


			/**
			 * Commands for switch Device
			 */
			editor.Commands.add(\'set-device-desktop\',
			{
				run: editor => {
					editor.setDevice(\'Desktop\');
				}
			});
			editor.Commands.add(\'set-device-tablet\',
			{
				run: editor => {
					editor.setDevice(\'Tablet\');
				}
			});
			editor.Commands.add(\'set-device-mobile-landscape\',
			{
				run: editor => {
					editor.setDevice(\'Mobile Landscape\');
				}
			});
			editor.Commands.add(\'set-device-mobile\',
			{
				run: editor => {
					editor.setDevice(\'Mobile Portrait\');
				}
			});


			// Set initial device as Desktop
			editor.setDevice(\'Desktop\');


			/*editor.BlockManager.add(\'example-block-1-id\', {
				label: \'example-block-1\',
				content: {
					tagName: \'div\',
					draggable: true,
					attributes: { \'some-attribute\': \'some-value\' },
					components: [
						{
							tagName: \'span\',
							content: \'<b>Some static content</b>\',
						}, {
							tagName: \'div\',
							// use `content` for static strings, `components` string will be parsed
							// and transformed in Components
							components: {
								tagName: \'span\',
								content: \'<span>HTML at some point</span>\',
							}
						}      
					]
				}
			});*/

			/*editor.BlockManager.add(\'section\', {
				label: \'<b>Section</b>\', // You can use HTML/SVG inside labels
				attributes: { class:\'gjs-block-section\' },
				select: true,
				activate: true,
				content:
				\'\
				<section>\
					<h1>This is a simple title</h1>\
					<div>This is just a Lorem text: Lorem ipsum dolor sit amet</div>\
				</section>\
				\',
			});*/

			/*editor.BlockManager.add(\'plain-text\', {
				label: \'Text\',
				content: \'<div data-gjs-type="text">Insert your text here</div>\',
				select: true,
			});*/

			/*editor.BlockManager.add(\'custom-image\', {
				label: \'Image\',
				// Select the component once it\'s dropped
				select: true,
				// You can pass components as a JSON instead of a simple HTML string,
				// in this case we also use a defined component type `image`
				content: { type: \'image\' },
				// This triggers `active` event on dropped components and the `image`
				// reacts by opening the AssetManager
				activate: true,
			});*/


			/**
			 * Custom "Flexicontent Data" component types.
			 *
			 * These replace the raw placeholder-text blocks: the canvas shows a friendly,
			 * non-editable badge box and the field/item/user is configured with the Settings
			 * (traits) panel. The project data (_data) stays clean (type + data-fc-* attributes
			 * only, no preview markup), and the real placeholder tokens are regenerated
			 * server-side at save time by JHtmlFclayoutbuilder::renderLayoutFromProject,
			 * then resolved at run time by JHtmlFlexicontent::renderBuilderLayout.
			 */
			var fcl_field_opts = window[\'fcl_builder_fields_\' + editor_sfx] || [];

			// ---------------------------------------------------------------------
			// Real-data preview: every fc-field block can show the actual rendered
			// value of its field for a chosen preview item, fetched via AJAX (batch)
			// from the getfieldpreview endpoint. Fetches are batched and cached per
			// item; the badge overlay stays visible until a real value is available.
			// ---------------------------------------------------------------------
			var fcl_preview_cfg    = window[\'fcl_builder_preview_\' + editor_sfx] || null;
			var fcl_preview_cache  = {};
			var fcl_preview_timer  = null;

			// Adopt the session-stored preview item when no item was chosen via the URL:
			// the PHP config defaults item_id to the first recent item for display only,
			// so the stored value has to override it (including the 0 = badges only case).
			if (fcl_preview_cfg && !fcl_preview_cfg.url_set)
			{
				try
				{
					var fcl_stored_preview = window.sessionStorage.getItem(\'fcl_preview_item_\' + editor_sfx);
					if (fcl_stored_preview !== null)
					{
						var fcl_stored_num = parseInt(fcl_stored_preview, 10) || 0;
						if (fcl_stored_num !== (fcl_preview_cfg.item_id | 0)) fcSetPreviewItem(fcl_stored_num);
					}
				}
				catch (e) {}
			}

			function fcPreviewItemId()
			{
				return fcl_preview_cfg ? (fcl_preview_cfg.item_id | 0) : 0;
			}

			function fcLoadFieldPreviews(names, cb)
			{
				var item_id = fcPreviewItemId();
				names = names.filter(function(n) { return typeof n === \'string\' && n; });

				if (!fcl_preview_cfg || !item_id || !names.length)
				{
					if (cb) cb(null);
					return;
				}

				var key = \'item_\' + item_id;
				if (!fcl_preview_cache[key]) fcl_preview_cache[key] = {};

				var missing = names.filter(function(n) { return !(n in fcl_preview_cache[key]); });

				if (!missing.length)
				{
					if (cb) cb(fcl_preview_cache[key]);
					return;
				}

				var body = new URLSearchParams();
				body.set(fcl_preview_cfg.token_name, fcl_preview_cfg.token || \'1\');
				body.set(\'item_id\', item_id);
				missing.forEach(function(n) { body.append(\'fields[]\', n); });

				fetch(fcl_preview_cfg.endpoint, {
					method: \'POST\',
					headers: { \'X-Requested-With\': \'XMLHttpRequest\' },
					body: body,
					credentials: \'same-origin\',
				})
				.then(function(r) { return r.json(); })
				.then(function(data)
				{
					if (data && data.ok && data.html)
					{
						var k = Object.keys(data.html);
						for (var i = 0; i < k.length; i++) fcl_preview_cache[key][k[i]] = data.html[k[i]];
					}
					if (data && data.errors)
					{
						var ek = Object.keys(data.errors);
						for (var i = 0; i < ek.length; i++) fcl_preview_cache[key][ek[i]] = null;
					}
					if (cb) cb(fcl_preview_cache[key]);
				})
				.catch(function(err)
				{
					console.error(\'fc preview fetch error\', err);
					if (cb) cb(null);
				});
			}

			function fcApplyPreviews(previews)
			{
				if (!previews) return;
				var doc = editor.Canvas.getDocument();
				if (!doc) return;
				var boxes = doc.querySelectorAll(\'.fc-field-preview\');
				for (var i = 0; i < boxes.length; i++)
				{
					var name = boxes[i].getAttribute(\'data-fc-field\');
					if (!name || !(name in previews)) continue;
					// Wrap the value with the block prefix/suffix (admin HTML) if any; the
					// attributes live on the fc-field block itself, the parent of the slot
					var host = boxes[i].parentElement;
					var pre = host ? (host.getAttribute(\'data-fc-prefix\') || \'\') : \'\';
					var suf = host ? (host.getAttribute(\'data-fc-suffix\') || \'\') : \'\';
					var v = previews[name];
					boxes[i].innerHTML = v ? (pre + v + suf) : \'\';
					boxes[i].classList[v ? \'add\' : \'remove\'](\'fc-has-value\');
				}
			}

			function fcCollectFieldNames()
			{
				var doc = editor.Canvas.getDocument();
				if (!doc) return [];
				var els = doc.querySelectorAll(\'[data-fc-field]\');
				var seen = {}, names = [];
				for (var i = 0; i < els.length; i++)
				{
					var n = els[i].getAttribute(\'data-fc-field\');
					if (n && !seen[n]) { seen[n] = 1; names.push(n); }
				}
				return names;
			}

			function fcClearPreviewSlots()
			{
				var doc = (typeof editor !== \'undefined\' && editor.Canvas) ? editor.Canvas.getDocument() : null;
				if (!doc) return;
				var boxes = doc.querySelectorAll(\'.fc-field-preview\');
				for (var i = 0; i < boxes.length; i++)
				{
					boxes[i].innerHTML = \'\';
					boxes[i].classList.remove(\'fc-has-value\');
				}
			}

			function fcSchedulePreviewRefresh(delay)
			{
				if (!fcl_preview_cfg) return;
				if (fcl_preview_timer) clearTimeout(fcl_preview_timer);
				fcl_preview_timer = setTimeout(function()
				{
					fcl_preview_timer = null;
					if (!fcPreviewItemId())
					{
						// No preview item: get rid of any previously fetched values so the
						// badge overlays come back (badges only mode must not keep data)
						fcClearPreviewSlots();
						return;
					}
					fcLoadFieldPreviews(fcCollectFieldNames(), fcApplyPreviews);
				}, delay || 300);
			}

			function fcSetPreviewItem(item_id)
			{
				if (!fcl_preview_cfg) return;
				fcl_preview_cfg.item_id = (item_id | 0) || 0;
				var status = document.getElementById(\'fcl_preview_status_\' + editor_sfx);
				if (status) status.textContent = fcl_preview_cfg.item_id ? \'...\' : \'\';
				// Persist the choice in the URL (reloads) and in sessionStorage (builder session)
				var url = new URL(window.location.href);
				if (fcl_preview_cfg.item_id) url.searchParams.set(\'fcl_preview_item\', fcl_preview_cfg.item_id);
				else url.searchParams.delete(\'fcl_preview_item\');
				window.history.replaceState(null, \'\', url.toString());
				try
				{
					// Store the raw value (including 0 = badges only) so a reload restores it
					window.sessionStorage.setItem(\'fcl_preview_item_\' + editor_sfx, fcl_preview_cfg.item_id);
				}
				catch (e) {}
				// Sync the toolbar select when the value was adopted programmatically (not via the select)
				var sel = document.getElementById(\'fcl_preview_item_\' + editor_sfx);
				if (sel && sel.querySelector(\'option[value="\' + fcl_preview_cfg.item_id + \'"]\')) sel.value = String(fcl_preview_cfg.item_id);
				fcSchedulePreviewRefresh(50);
			}

			// Inject the editor-only overlay/styles into the canvas frame head. These rules are NOT
			// part of the saved project (they are injected only into the editing iframe), so the
			// front-end code/CSS is never affected.
			var fcl_canvas_bx_css =
				\'[data-fc-field]{position:relative}\' +
				\'[data-fc-field] .fc-field-badge{position:absolute;top:0;left:0;right:0;bottom:0;z-index:1;transition:opacity .15s;pointer-events:none}\' +
				\'[data-fc-field] .fc-field-preview.fc-has-value + .fc-field-badge{opacity:0}\' +
				\'[data-fc-field]:hover .fc-field-badge,[data-fc-field].gjs-selected .fc-field-badge{opacity:1}\' +
				\'[data-fc-field] .fc-field-preview{min-height:18px;word-wrap:break-word}\' +
				\'[data-fc-field] .fc-field-preview:empty{min-height:48px}\' +
				// Empty layout containers stay visible & droppable in the canvas;
				// these helper rules are injected only into the canvas head (not the project)
				\'section.fc-empty-box:empty,div.fc-empty-box:empty{min-height:80px}\' +
				\'div.fc-empty-box div.fc-empty-box:empty{min-height:60px}\' +
				// Neutralize the backend admin j4x.css #flexicontent select rule (border,
				// width) inside the editing canvas so dropped field <select>s render cleanly.
				// [data-fc-field] is the per-block host without relying on a wrapper id.
				// Canvas-only (injected in the iframe head, never saved, never on the front).
				\'#flexicontent select,[data-fc-field] select{border:none!important;box-shadow:none!important}\';

			// The canvas iframe document is recreated on refresh()/device changes, so the
			// injected <style> must be re-added on every frame load (the guard is the element
			// presence itself, not a one-shot flag).
			function fcl_ensureCanvasInserts()
			{
				var doc = editor.Canvas.getDocument();
				if (!doc || !doc.head) return;
				var st = doc.getElementById(\'fcl-canvas-preview\');
				if (!st)
				{
					st = doc.createElement(\'style\');
					st.id = \'fcl-canvas-preview\';
					st.textContent = fcl_canvas_bx_css;
					doc.head.appendChild(st);
				}
			}

			var fcl_updating_link_styles = false;
			function fcl_ensureCanvasLinkStyles()
			{
				// Rebuild the scoped link-style overlay for the canvas: the values live in the
				// data-fc-linkstyles-* ATTRIBUTES (written by the Style Manager property models,
				// see fcl_configureStyleManager) and a data-fc-links key (the ccid) anchors the
				// scope on both canvas and front. Guarded so the key assignment does not
				// re-enter this loop.
				if (fcl_updating_link_styles) return;
				fcl_updating_link_styles = true;
				var doc = editor.Canvas.getDocument();
				if (doc && doc.head)
				{
					var rules = [];
					var walk = function(comp)
					{
						if (!comp || typeof comp.get !== \'function\') return;
						// Accept the value whether it landed in attributes or in the style map.
						var attrs = comp.getAttributes ? (comp.getAttributes() || {}) : {};
						var style = comp.getStyle ? (comp.getStyle() || {}) : {};
						var gv = function(n)
						{
							var v = attrs[n] !== undefined && attrs[n] !== null ? attrs[n] : (style[n] !== undefined && style[n] !== null ? style[n] : \'\');
							return String(v);
						};
						var hasStyle = gv(\'data-fc-linkstyles-color\') !== \'\' || gv(\'data-fc-linkstyles-hover\') !== \'\' ||
							gv(\'data-fc-linkstyles-size\') !== \'\' || gv(\'data-fc-linkstyles-weight\') !== \'\' || gv(\'data-fc-linkstyles-deco\') !== \'\';
						var key = attrs[\'data-fc-links\'] ?
							String(attrs[\'data-fc-links\']) : (style[\'data-fc-links\'] ? String(style[\'data-fc-links\']) : \'\');
						if (hasStyle && !key && typeof comp.getId === \'function\')
						{
							key = comp.getId() || \'\';
							if (key && typeof comp.addAttributes === \'function\') comp.addAttributes({ \'data-fc-links\': key });
						}
						// Scope by the block\'s first class (.classname a), the same selector the
						// front-end CSS regenerates server-side; fall back to the data-fc-links
						// attribute when the block carries no class.
						var base = \'\';
						var cls = comp.getClasses ? (comp.getClasses() || []) : [];
						for (var x = 0; x < cls.length; x++) { if (cls[x]) { base = \'.\' + cls[x]; break; } }
						if (!base && key) base = \'[data-fc-links="\' + key + \'"]\';
						if (base)
						{
							var sel = base + \' a\';
							var props = \'\';
							if (gv(\'data-fc-linkstyles-color\') !== \'\')  props += \'color:\' + gv(\'data-fc-linkstyles-color\') + \';\';
							if (gv(\'data-fc-linkstyles-size\') !== \'\')   props += \'font-size:\' + gv(\'data-fc-linkstyles-size\') + \';\';
							if (gv(\'data-fc-linkstyles-weight\') !== \'\') props += \'font-weight:\' + gv(\'data-fc-linkstyles-weight\') + \';\';
							if (gv(\'data-fc-linkstyles-deco\') !== \'\')   props += \'text-decoration:\' + gv(\'data-fc-linkstyles-deco\') + \';\';
							if (props) rules.push(sel + \'{\' + props + \'}\');
							if (gv(\'data-fc-linkstyles-hover\') !== \'\') rules.push(sel + \':hover{color:\' + gv(\'data-fc-linkstyles-hover\') + \';}\');
						}
						var children = comp.components && comp.components();
						if (children) children.each(function(c) { walk(c); });
					};
					if (editor.getWrapper) walk(editor.getWrapper());
					var st = doc.getElementById(\'fcl-canvas-links\');
					if (!st)
					{
						st = doc.createElement(\'style\');
						st.id = \'fcl-canvas-links\';
						doc.head.appendChild(st);
					}
					st.textContent = rules.join(\'\');
				}
				fcl_updating_link_styles = false;
			}

			// Wire the toolbar item selector and keep the insert injection synced.
			editor.on(\'load\', function()
			{
				var sel = document.getElementById(\'fcl_preview_item_\' + editor_sfx);
				if (sel)
				{
					sel.addEventListener(\'change\', function() { fcSetPreviewItem(sel.value | 0); }, false);
				}
				fcl_configureStyleManager();
				fcl_ensureCanvasInserts();
				fcl_ensureCanvasLinkStyles();
				fcSchedulePreviewRefresh(0);
			});
			editor.on(\'frame:load\', fcl_ensureCanvasInserts);
			editor.on(\'frame:load\', fcl_ensureCanvasLinkStyles);
			editor.on(\'component:update\', fcl_ensureCanvasLinkStyles);
			editor.on(\'component:styleUpdate\', fcl_ensureCanvasLinkStyles);
			editor.on(\'component:add\', fcl_ensureCanvasLinkStyles);
			editor.on(\'component:remove\', fcl_ensureCanvasLinkStyles);

			function fcl_opt_label(v)
			{
				for (var i = 0; i < fcl_field_opts.length; i++)
				{
					if (fcl_field_opts[i].value === v) return fcl_field_opts[i].label;
				}
				return v;
			}

			function fcl_data_box(tag, main, sub)
			{
				return \'<div class="fc-data-box" style="pointer-events:none;user-select:none;box-sizing:border-box;min-height:48px;padding:7px 12px 7px 34px;position:relative;border:1px dashed #b5b5b5;border-radius:4px;background:#f4f4f4;color:#333;font-family:Arial,Helvetica,sans-serif;">\' +
					\'<span style="position:absolute;top:7px;left:9px;width:18px;height:18px;border-radius:9px;background:#6EA22B;color:#fff;text-align:center;line-height:18px;font-size:11px;font-weight:bold;">\' + tag + \'</span>\' +
					\'<strong style="display:block;font-size:13px;line-height:1.2;">\' + main + \'</strong>\' +
					(sub ? \'<em style="display:block;font-size:11px;color:#777777;font-style:normal;line-height:1.2;">\' + sub + \'</em>\' : \'\') +
					\'</div>\';
			}

			// Run the base (default) component render FIRST so the element gets its id/classes/
			// attributes/style, then replace the (empty) inner content with the label box.
			// NB: not overriding render entirely would skip attribute/class application.
			function fcl_boxed_view(badgeBuilder)
			{
				return {
					init()
					{
						this.listenTo(this.model, \'change:attributes\', () => this.render());
					},
					render()
					{
						var defView = editor.DomComponents.getType(\'default\').view.prototype;
						defView.render.call(this);
						badgeBuilder.call(this);
						return this;
					},
				};
			}

			// Integrated combobox trait (searchable, grouped) for the fc-field picker.
			// GrapesJS 0.23: trait types are VIEW classes extending the base text trait view.
			// TraitManager.addType clones the base trait view and merges the methods below.
			// The underlying Trait MODEL still maps trait value to the component attribute
			// (trait name = the attribute key), so the selection keeps updating data-fc-field.
			editor.TraitManager.addType(\'fc-field-select\', {
				eventCapture: [\'change\'],
				templateInput()
				{
					return \'<div class="\' + this.clsField + \'"><div class="fc-combo">\' +
						\'<input type="text" class="fc-combo-input" placeholder="Select a field or type to filter... " autocomplete="off" spellcheck="false">\' +
						\'<div class="fc-combo-arrow">&#9662;</div>\' +
						\'<div class="fc-combo-list" style="display:none;">\' +
							\'<div class="fc-combo-groups"></div>\' +
							\'<div class="fc-search-nomatch" style="display:none;">No matching field</div>\' +
						\'</div>\' +
						\'<div data-input style="display:none;"></div>\' +
					\'</div></div>\';
				},
				getInputEl()
				{
					if (!this.$input)
					{
						var model = this.model;
						var opts = model.get(\'options\') || [];
						var select = document.createElement(\'select\');
						var groups = {};
						var vals = [];
						for (var i = 0; i < opts.length; i++)
						{
							var o = opts[i];
							var v = (typeof o.value === \'undefined\' ? o.id : o.value);
							v = String(v).replace(/"/g, \'&quot;\');
							var label = o.name || o.label || v;
							var g = o.group || \'Flexicontent\';
							if (!groups[g]) groups[g] = [];
							groups[g].push({ v: v, t: label });
							vals.push(v);
						}
						Object.keys(groups).sort().forEach(function(g)
						{
							var og = document.createElement(\'optgroup\');
							og.setAttribute(\'label\', g);
							groups[g].forEach(function(o)
							{
								var opt = document.createElement(\'option\');
								opt.value = o.v;
								opt.textContent = o.t;
								og.appendChild(opt);
							});
							select.appendChild(og);
						});
						var cur = model.getTargetValue();
						var found = false;
						for (var j = 0; j < vals.length; j++)
						{
							if (String(vals[j]) === String(cur)) { found = true; break; }
						}
						var curVal = found ? cur : model.get(\'default\');
						if (typeof curVal !== \'undefined\') select.value = String(curVal);
						this.$input = { get: function() { return select; } };
						this.input = select;
					}
					return this.$input.get(0);
				},
				setInputValue(value)
				{
					var el = this.getInputElem();
					if (el) el.value = (value == null ? \'\' : String(value));
					this.fcReflect();
				},
				onRender()
				{
					this.fcSetup();
				},
				fcSetup()
				{
					var el = this.el;
					if (!el) return;
					this._fcOpts = [];
					this._fcActive = -1;
					this._fcLabel = \'\';
					var sel = this.$input ? this.$input.get(0) : null;
					var groupEl = el.querySelector(\'.fc-combo-groups\');
					var inp = el.querySelector(\'.fc-combo-input\');
					if (!sel || !groupEl || !inp) return;
					this.fcBuildList(sel, groupEl);
					this.fcReflectEl(inp, sel);
					var self = this;
					inp.addEventListener(\'focus\', function() { self.fcOpen(); });
					inp.addEventListener(\'click\', function() { self.fcOpen(); });
					inp.addEventListener(\'input\', function() { self.fcFilter(inp.value); self.fcFocus(0); });
					inp.addEventListener(\'keydown\', function(e) {
						if (e.key === \'Escape\') { e.preventDefault(); self.fcClose(); inp.blur(); }
						else if (e.key === \'ArrowDown\') { e.preventDefault(); self.fcOpen(); self.fcFocus(1); }
						else if (e.key === \'ArrowUp\') { e.preventDefault(); self.fcOpen(); self.fcFocus(-1); }
						else if (e.key === \'Enter\') { e.preventDefault(); self.fcCommit(self.fcPickCurrent()); }
					});
					inp.addEventListener(\'blur\', function() { self.fcClose(); });
					var list = el.querySelector(\'.fc-combo-list\');
					if (list)
					{
						list.addEventListener(\'mousedown\', function(e) { e.preventDefault(); });
						list.addEventListener(\'click\', function(e) {
							var o = e.target && e.target.closest ? e.target.closest(\'.fc-combo-opt\') : null;
							var v = o && o.getAttribute(\'data-value\');
							if (o && v != null) self.fcCommit(v);
						});
					}
					var arrow = el.querySelector(\'.fc-combo-arrow\');
					if (arrow) arrow.addEventListener(\'mousedown\', function(e) { e.preventDefault(); self.fcOpen(); });
				},
				fcBuildList(sel, groupEl)
				{
					var map = {};
					this._fcOpts = [];
					var ogs = sel.querySelectorAll(\'optgroup\');
					for (var gi = 0; gi < ogs.length; gi++)
					{
						var og = ogs[gi];
						var gname = og.getAttribute(\'label\') || \'\';
						var grp = map[gname];
						if (!grp)
						{
							grp = document.createElement(\'div\');
							grp.className = \'fc-combo-grp\';
							var b = document.createElement(\'b\');
							b.textContent = gname;
							grp.appendChild(b);
							groupEl.appendChild(grp);
							map[gname] = grp;
						}
						var opts = og.querySelectorAll(\'option\');
						for (var oi = 0; oi < opts.length; oi++)
						{
							var opt = opts[oi];
							var row = document.createElement(\'div\');
							row.className = \'fc-combo-opt\';
							row.setAttribute(\'data-value\', opt.value);
							row.textContent = opt.textContent;
							grp.appendChild(row);
							this._fcOpts.push({ value: opt.value, text: opt.textContent.toLowerCase(), el: row, grp: grp, pos: this._fcOpts.length });
						}
					}
				},
				fcFilter(q)
				{
					var el = this.el;
					if (!el) return;
					q = String(q || \'\').toLowerCase().trim();
					var opts = this._fcOpts;
					var total = 0;
					for (var i = 0; i < opts.length; i++)
					{
						var o = opts[i];
						var hit = !q
							|| String(o.text).indexOf(q) !== -1
							|| String(o.value).toLowerCase().indexOf(q) !== -1;
						o.el.style.display = hit ? \'\' : \'none\';
						if (hit) total++;
					}
					var seen = {};
					for (var j = 0; j < opts.length; j++)
					{
						var g = opts[j].grp;
						if (seen[g]) continue;
						seen[g] = 1;
						var shown = 0;
						for (var k = 0; k < opts.length; k++)
						{
							if (opts[k].grp === g && opts[k].el.style.display !== \'none\') shown++;
						}
						g.style.display = shown ? \'\' : \'none\';
					}
					var marker = el.querySelector(\'.fc-search-nomatch\');
					if (marker) marker.style.display = total ? \'none\' : \'block\';
				},
				fcOpen()
				{
					var el = this.el;
					if (!el) return;
					var list = el.querySelector(\'.fc-combo-list\');
					var inp = el.querySelector(\'.fc-combo-input\');
					if (!list || !inp) return;
					if (list.style.display === \'block\') return;
					this._fcLabel = inp.value;
					inp.value = \'\';
					this.fcFilter(\'\');
					list.style.display = \'block\';
					this.fcFocus(0);
				},
				fcClose()
				{
					var el = this.el;
					if (!el) return;
					var list = el.querySelector(\'.fc-combo-list\');
					var inp = el.querySelector(\'.fc-combo-input\');
					if (list) list.style.display = \'none\';
					if (inp && typeof this._fcLabel === \'string\')
					{
						this.fcReflectEl(inp, this.$input ? this.$input.get(0) : null);
					}
					this._fcActive = -1;
				},
				fcReflect()
				{
					this.fcReflectEl(this.el && this.el.querySelector(\'.fc-combo-input\'), this.$input ? this.$input.get(0) : null);
				},
				fcReflectEl(inp, sel)
				{
					if (!inp || !sel) return;
					var txt = \'\';
					var saw = false;
					var opts = sel.options || [];
					for (var i = 0; i < opts.length; i++)
					{
						if (String(opts[i].value) === String(sel.value)) { txt = opts[i].textContent; saw = true; break; }
					}
					inp.value = saw ? txt : \'\';
				},
				fcFocus(d)
				{
					var opts = this._fcOpts;
					var vis = [];
					if (!opts) return;
					for (var i = 0; i < opts.length; i++)
					{
						if (opts[i].el.style.display !== \'none\') vis.push(opts[i]);
					}
					if (!vis.length) return;
					var cur = -1;
					for (var k = 0; k < vis.length; k++)
					{
						if (vis[k].pos === this._fcActive) { cur = k; break; }
					}
					var next = 0;
					if (cur !== -1) next = cur + (typeof d === \'undefined\' ? 0 : d);
					else next = (typeof d === \'undefined\' || d >= 0) ? 0 : vis.length - 1;
					if (next < 0) next = 0;
					if (next >= vis.length) next = vis.length - 1;
					this._fcActive = vis[next].pos;
					for (var m = 0; m < opts.length; m++)
					{
						if (opts[m].el) opts[m].el.classList.toggle(\'fc-combo-active\', m === this._fcActive);
					}
					if (vis[next].el.scrollIntoView) vis[next].el.scrollIntoView({ block: \'nearest\' });
				},
				fcPickCurrent()
				{
					var opts = this._fcOpts;
					var vis = [];
					if (!opts) return null;
					for (var i = 0; i < opts.length; i++)
					{
						if (opts[i].el.style.display !== \'none\') vis.push(opts[i]);
					}
					if (!vis.length) return null;
					for (var k = 0; k < vis.length; k++)
					{
						if (vis[k].pos === this._fcActive) return vis[k].value;
					}
					return vis[0].value;
				},
				fcCommit(v)
				{
					if (v == null) return;
					var sel = this.$input ? this.$input.get(0) : null;
					if (sel)
					{
						sel.value = String(v);
						sel.dispatchEvent(new Event(\'change\', { bubbles: true }));
						this.fcReflect();
					}
					this.fcClose();
				},
			});

			editor.DomComponents.addType(\'fc-field\', {
				// Also recognized when old HTML saves contain a data-fc-field attribute
				isComponent: function(el)
				{
					if (el && el.hasAttribute && el.hasAttribute(\'data-fc-field\')) return { type: \'fc-field\' };
				},
				model: {
					defaults: {
						name: \'Flexicontent Field\',
						editable: false,
						droppable: false,
						resizable: true,
						attributes: { \'data-fc-field\': \'\' },
						traits: [{
							type: \'fc-field-select\',
							// NB: in GrapesJS the trait \'name\' IS the attribute key written to the
							// component (the legacy \'attribute\' key is ignored), so use it directly.
							name: \'data-fc-field\',
							label: \'Field\',
							options: fcl_field_opts.map(function(o) { return { value: o.value, name: o.label, group: o.group }; }),
						}, {
							type: \'text\',
							name: \'data-fc-prefix\',
							label: \'Prefix\',
							placeholder: \'ex: <h1>\',
						}, {
							type: \'text\',
							name: \'data-fc-suffix\',
							label: \'Suffix\',
							placeholder: \'ex: </h1>\',
						}],
					},
				},
				view: fcl_boxed_view(function()
				{
					var name = this.model.getAttributes()[\'data-fc-field\'] || \'\';
					// NB: the preview slot is in NORMAL FLOW with NO inline color/background/
					// font styles, so styles applied to the block on the canvas cascade into
					// the displayed value (parity with the frontend). The badge is a purely
					// decorative overlay (dev marker), shown only until a value arrives or on
					// hover/selection, so it never fights the user styles.
					this.el.innerHTML =
						\'<div class="fc-field-preview" data-fc-field="\' + name + \'" style="pointer-events:none;"></div>\' +
						\'<div class="fc-field-badge">\' + fcl_data_box(\'F\',
							name ? (\'Field: \' + name) : \'Flexicontent Field\',
							name ? fcl_opt_label(name) : \'Choose a field in the Settings panel\') + \'</div>\';
					if (name) fcSchedulePreviewRefresh(120);
				}),
			});

			editor.DomComponents.addType(\'fc-link\', {
				isComponent: function(el)
				{
					if (el && el.hasAttribute && el.hasAttribute(\'data-fc-link\')) return { type: \'fc-link\' };
				},
				model: {
					defaults: {
						name: \'Flexicontent Item Link\',
						editable: false,
						droppable: false,
						resizable: true,
						attributes: { \'data-fc-link\': \'item\', \'data-fc-itemid\': \'\', \'data-fc-linktext\': \'_title_\' },
						traits: [
							{ type: \'text\', name: \'data-fc-itemid\', label: \'Item ID\', placeholder: \'ex: 122|current|{{fc-item-id}}\' },
							{ type: \'text\', name: \'data-fc-linktext\', label: \'Link text\', placeholder: \'_title_, text or _noclose_\' },
						],
					},
				},
				view: fcl_boxed_view(function()
				{
					var attrs = this.model.getAttributes() || {};
					var id  = attrs[\'data-fc-itemid\'] || \'current\';
					var txt = attrs[\'data-fc-linktext\'] || \'_title_\';
					this.el.innerHTML = fcl_data_box(\'L\', \'Link to item \' + id,
						(txt === \'_title_\') ? \'Item title\'
							: (txt === \'_noclose_\') ? \'Custom content (wrapper)\'
							: (\'Text: \' + txt));
				}),
			});

			editor.DomComponents.addType(\'fc-profile-user\', {
				isComponent: function(el)
				{
					if (el && el.hasAttribute && el.hasAttribute(\'data-fc-profile\') && el.getAttribute(\'data-fc-profile\') === \'user\') return { type: \'fc-profile-user\' };
				},
				model: {
					defaults: {
						name: \'User Profile\',
						editable: false,
						droppable: false,
						resizable: true,
						attributes: { \'data-fc-profile\': \'user\', \'data-fc-userid\': \'\' },
						traits: [
							{ type: \'text\', name: \'data-fc-userid\', label: \'User ID\', placeholder: \'empty = current user\' },
						],
					},
				},
				view: fcl_boxed_view(function()
				{
					var uid = this.model.getAttributes()[\'data-fc-userid\'] || \'\';
					this.el.innerHTML = fcl_data_box(\'U\', \'User profile\', uid ? (\'user: \' + uid) : \'current user\');
				}),
			});

			editor.DomComponents.addType(\'fc-profile-author\', {
				isComponent: function(el)
				{
					if (el && el.hasAttribute && el.hasAttribute(\'data-fc-profile\') && el.getAttribute(\'data-fc-profile\') === \'author\') return { type: \'fc-profile-author\' };
				},
				model: {
					defaults: {
						name: \'Author Profile\',
						editable: false,
						droppable: false,
						resizable: true,
						attributes: { \'data-fc-profile\': \'author\' },
					},
				},
				view: fcl_boxed_view(function()
				{
					this.el.innerHTML = fcl_data_box(\'A\', \'Author profile\', \'author of current item\');
				}),
			});

			editor.BlockManager.add(\'fcfield\', {
				label: \'Flexicontent Field\',
				category: \'Flexicontent Data\',
				content: { type: \'fc-field\', attributes: { \'data-fc-field\': fcl_field_opts.length ? fcl_field_opts[0].value : \'\' } },
				select: true,
				activate: true,
				attributes: { class:\'fc-iblock fa fa-database\' },
			});

			editor.BlockManager.add(\'fcitemlink\', {
				label: \'Flexicontent item title with link\',
				category: \'Flexicontent Data\',
				content: { type: \'fc-link\', attributes: { \'data-fc-itemid\': \'566|current|{{fc-item-id}}\', \'data-fc-linktext\': \'_title_\' } },
				select: true,
				activate: true,
				attributes: { class:\'fc-iblock fa fa-database\' },
			});

			editor.BlockManager.add(\'fcitemlink2\', {
				label: \'Flexicontent item link with custom text\',
				category: \'Flexicontent Data\',
				content: { type: \'fc-link\', attributes: { \'data-fc-itemid\': \'577|current|{{fc-item-id}}\', \'data-fc-linktext\': \'_noclose_\' } },
				select: true,
				activate: true,
				attributes: { class:\'fc-iblock fa fa-database\' },
			});

			editor.BlockManager.add(\'fcitemprofil\', {
				label: \'User profil\',
				category: \'Flexicontent Data\',
				content: { type: \'fc-profile-user\', attributes: { \'data-fc-userid\': \'\' } },
				select: true,
				activate: true,
				attributes: { class:\'fc-iblock fa fa-database\' },
			});

			editor.BlockManager.add(\'fcauthor\', {
				label: \'Author profil\',
				category: \'Flexicontent Data\',
				content: { type: \'fc-profile-author\', attributes: {} },
				select: true,
				activate: true,
				attributes: { class:\'fc-iblock fa fa-database\' },
			});

			function fcl_applyLinkTraits()
			{
				// Block-scoped link styles are driven by the Style Manager "Link styles"
				// composite in the Typography sector (values stored in the component style map
				// as data-fc-link* keys, with a data-fc-links anchor attribute). No traits here.
			}


			// --- Basic layout blocks: Section, Flex Row, pre-configured flex columns ---
			// Flex layouts are built with inline styles (display:flex + flex-wrap), so they are
			// self-contained: they survive the save (inline styles are stored in the project
			// data and re-rendered by the server) and wrap gracefully on narrow viewports.
			// data-gjs-resizable="true" makes the dropped divs resizable (consumed at import,
			// it is NOT exported to the front-end HTML).
			editor.BlockManager.add(\'flex-section\', {
				label: \'Section\',
				category: \'Basic\',
				select: true,
				activate: true,
				attributes: { class: \'fa fa-square-o\' },
				content: \'<section data-gjs-resizable="true" class="fc-empty-box"></section>\',
			});

			editor.BlockManager.add(\'flex-row\', {
				label: \'Row (flex)\',
				category: \'Basic\',
				select: true,
				activate: true,
				attributes: { class: \'fa fa-arrows-h\' },
				content: \'<div data-gjs-resizable="true" class="fc-empty-box" style="display:flex;flex-wrap:wrap;gap:10px;"></div>\',
			});

			editor.BlockManager.add(\'flex-cols-2\', {
				label: \'2 Columns (50/50)\',
				category: \'Basic\',
				select: true,
				activate: true,
				attributes: { class: \'fa fa-columns\' },
				content: \'<div data-gjs-resizable="true" class="fc-empty-box" style="display:flex;flex-wrap:wrap;gap:10px;"><div data-gjs-resizable="true" class="fc-empty-box" style="flex:0 1 auto;width:50%;min-width:200px;"></div><div data-gjs-resizable="true" class="fc-empty-box" style="flex:0 1 auto;width:50%;min-width:200px;"></div></div>\',
			});

			editor.BlockManager.add(\'flex-cols-3\', {
				label: \'3 Columns (33/33/33)\',
				category: \'Basic\',
				select: true,
				activate: true,
				attributes: { class: \'fa fa-th-large\' },
				content: \'<div data-gjs-resizable="true" class="fc-empty-box" style="display:flex;flex-wrap:wrap;gap:10px;"><div data-gjs-resizable="true" class="fc-empty-box" style="flex:0 1 auto;width:33.33%;min-width:150px;"></div><div data-gjs-resizable="true" class="fc-empty-box" style="flex:0 1 auto;width:33.33%;min-width:150px;"></div><div data-gjs-resizable="true" class="fc-empty-box" style="flex:0 1 auto;width:33.33%;min-width:150px;"></div></div>\',
			});

			editor.BlockManager.add(\'flex-cols-4\', {
				label: \'4 Columns (25% each)\',
				category: \'Basic\',
				select: true,
				activate: true,
				attributes: { class: \'fa fa-th\' },
				content: \'<div data-gjs-resizable="true" class="fc-empty-box" style="display:flex;flex-wrap:wrap;gap:10px;"><div data-gjs-resizable="true" class="fc-empty-box" style="flex:0 1 auto;width:25%;min-width:120px;"></div><div data-gjs-resizable="true" class="fc-empty-box" style="flex:0 1 auto;width:25%;min-width:120px;"></div><div data-gjs-resizable="true" class="fc-empty-box" style="flex:0 1 auto;width:25%;min-width:120px;"></div><div data-gjs-resizable="true" class="fc-empty-box" style="flex:0 1 auto;width:25%;min-width:120px;"></div></div>\',
			});

			editor.BlockManager.add(\'flex-cols-mainbar\', {
				label: \'Main + Sidebar (2/3 - 1/3)\',
				category: \'Basic\',
				select: true,
				activate: true,
				attributes: { class: \'fa fa-bars\' },
				content: \'<div data-gjs-resizable="true" class="fc-empty-box" style="display:flex;flex-wrap:wrap;gap:10px;"><div data-gjs-resizable="true" class="fc-empty-box" style="flex:0 1 auto;width:66.66%;min-width:250px;"></div><div data-gjs-resizable="true" class="fc-empty-box" style="flex:0 1 auto;width:33.33%;min-width:200px;"></div></div>\',
			});


			//console.log(editor.Panels.getPanels());
			//var deviceManager = editor.DeviceManager;
			//var devices = deviceManager.getAll();
			//console.log(devices);


			/**
			 * Switch devices (Desktop, Tablet, Mobile)
			 */
			editor.Panels.addPanel({
				id: \'panel-devices\',
				appendTo: \'#gjs_\' + editor_sfx + \' .gjs-pn-panels\',
				buttons: [
					/*{
						id: \'devices-label\',
						label: \'Device\',
						attributes: { style: \'pointer-events: none; font-family: Arial;\'},
					},*/ {
						id: \'device-desktop\',
						//label: \'Desktop\',
						className: \'fa fa-desktop\',
						command: \'set-device-desktop\',
						active: true,
						togglable: false,
						attributes: {
							\'title\': \'Desktop\',
							\'data-tooltip-pos\': \'bottom\',
						},
					}, {
						id: \'device-tablet\',
						//label: \'Tablet\',
						className: \'fa fa-tablet\',
						command: \'set-device-tablet\',
						active: false,
						togglable: false,
						attributes: {
							\'title\': \'Tablet\',
							\'data-tooltip-pos\': \'bottom\',
						},
					}, {
						id: \'device-mobile-landscape\',
						//label: \'Mobile Landscape\',
						className: \'fa fa-mobile fa-rotate-90\',
						command: \'set-device-mobile-landscape\',
						active: false,
						togglable: false,
						attributes: {
							\'title\': \'Mobile Landscape\',
							\'data-tooltip-pos\': \'bottom\',
						},
					}, {
						id: \'device-mobile\',
						//label: \'Mobile\',
						className: \'fa fa-mobile\',
						command: \'set-device-mobile\',
						active: false,
						togglable: false,
						attributes: {
							\'title\': \'Mobile\',
							\'data-tooltip-pos\': \'bottom\',
						},
					},
					
				],
			});


			// Add to default Buttons panel
			pn.addButton(\'options\', [{
				id: \'save2\',
				className: \'fa fa-floppy-o\',
				command: function(editor1, sender) {
					sender && sender.set(\'active\', true);
					saveToForm();
					setTimeout(function()
					{
						sender && sender.set(\'active\', false);
					}, 300);
				},
				attributes: {
					title: \'Save Layout into form\'
				}
			}]);


			// Switch components tools (their buttons are inside .gjs-pn-panels)
			/*editor.Panels.addPanel({
				id: \'panel-switcher\',
				appendTo: \'#gjs_\' + editor_sfx + \' .gjs-pn-panels\',
				buttons: [
					{
						id: \'show-layers\',
						label: \' Layers\',
						className: \'fa fa-bars\',
						command: \'show-layers\',
						active: false,
						togglable: false,  // Once activated disable the possibility to turn it off
					}, {
						id: \'show-style\',
						label: \' Styles\',
						className: \'fa fa-paint-brush\',
						command: \'show-styles\',
						active: false,
						togglable: false,
					}, {
						id: \'show-traits\',
						label: \' Properties\',
						className: \'fa fa-cog\',
						command: \'show-traits\',
						active: false,
						togglable: false,
					}
				],
			});*/


			editor.Panels.addPanel({
				id: \'panel-basic-actions\',
				appendTo: \'#gjs_\' + editor_sfx + \' .gjs-pn-panels\',
				buttons: [
					/*{
						id: \'store-data\',
						label: \' Store\',
						className: \'fa fa-save\',
						command: \'store-data\',
						active: false,
						togglable: false,
					}, {
						id: \'visibility\',
						active: true, // active by default
						className: \'btn-toggle-borders\',
						//label: \'<u>B</u>\',
						className: \'fa fa-square-o\',
						command: \'sw-visibility\', // Built-in command
					}, {
						id: \'export\',
						className: \'btn-open-export\',
						//label: \'&lt;/&gt;\',
						className: \'fa fa-code\',
						command: \'export-template\',
						context: \'export-template\', // For grouping context of buttons from the same panel
					}, {
						id: \'show-json\',
						className: \'btn-show-json\',
						label: \'{}\',
						context: \'show-json\',
						command(editor) {
							editor.Modal.setTitle(\'Components JSON\')
								.setContent(\'<textarea style="width:100%; height: 250px;">\
									${JSON.stringify(editor.getComponents())}\
								</textarea>\
								\')
								.open();
						},
					}, {
						id: \'show-code\',
						className: \'btn-show-code\',
						label: \' Show code\',
						className: \'fa fa-eye\',
						context: \'show-code\',
						command(editor) {
							editor.Modal.setTitle(\'Show Code\')
								.setContent(\'\
								<h4>HTML</h4>\
								<textarea style="width:100%; height: 200px;">\' + editor.getHtml().trim() + \'</textarea>\
								<h4>CSS</h4>\
								<textarea style="width:100%; height: 200px;">\' + editor.getCss().trim() + \'</textarea>\
								<h4>JS</h4>\
								<textarea style="width:100%; height: 200px;">\' + editor.getJs().trim() + \'</textarea>\
								\')
								.open();
						},
					}*/
				],
			});

			/*jQuery(document).ready(function(){
				editor.store(res => console.log(\'Store callback\'));
			});*/

			// NOTE: no explicit editor.load() here: loading is done by the storageManager autoload
			// (autoload: true above). Doing both would double-load the stored layout.

			// Safety net: never show the browser "Changes you made may not be saved" dialog
			// (GrapesJS re-sets window.onbeforeunload on every change step)
			window.onbeforeunload = null;
			setInterval(function() { window.onbeforeunload = null; }, 300);

			editor.on(\'storage:error\', (err) => {
				alert(`Error: ${err}`);
			});

			editor.on(\'device:update\', () => console.log(\'Current device: \', editor.getDevice()));

			editor.on(\'run:export-template:before\', opts =>
			{
				console.log(\'Before the command run\');
				if (0 /* some condition */)
				{
					opts.abort = 1;
				}
			});
			editor.on(\'run:export-template\', () => console.log(\'run:export-template\'));
			editor.on(\'abort:export-template\', () => console.log(\'abort:export-template\'));

			// The wrapper is the root Component
			const wrapper = editor.DomComponents.getWrapper();
			const myComponent = wrapper.find(\'div.my-component\')[0];
			//myComponent.components().forEach(component => /* ... do something ... */);
			//myComponent.components(\'<div>New content</div>\');

			const categories = editor.BlockManager.getCategories();

			categories.each(category =>
			{
				category.set(\'open\', false).on(\'change:open\', opened =>
				{
					opened.get(\'open\') && categories.each(category =>
					{
						category !== opened && category.set(\'open\', false)
					});
				});
			});

			/*const prefix = \'.flexicontent \';

			editor.on(\'selector:add\', selector =>
			{
				console.log(selector);
				var name = selector.get(\'name\');

				if (!!name && selector.get(\'type\') === editor.SelectorManager.Selector.TYPE_CLASS && name.indexOf(prefix) !== 0)
				{
					console.log(name);
					selector.set(\'name\', prefix + name);
				}
			});*/
		}
		</script>
		';
		}

		$html .= $fcl_preview_html . '
		<div style="height: 90%; margin: 0px;">

			<div class="editor-row">

				<div class="editor-canvas">
					<div id="gjs_' . $editor_sfx . '"></div>
				</div>

				<!--div class="panel__right">
					<div class="styles-container"></div>
					<div class="layers-container"></div>
					<div class="traits-container"></div>
				</div-->

			</div>

			<!--div id="blocks"></div-->
		</div>

		<script>
		(function()
		{
			function fclayout_builder_autostart()
			{
				fclayout_init_builder(\'' . $editor_sfx . '\', \'' . $element_id . '\');
			}

			if (document.readyState === \'loading\')
			{
				document.addEventListener(\'DOMContentLoaded\', fclayout_builder_autostart);
			}
			else
			{
				fclayout_builder_autostart();
			}
		})();
		</script>
		'
		/*. '
		<div id="info-panel" style="display:none">
			<br/>
			<svg class="info-panel-logo" xmlns="//www.w3.org/2000/svg" version="1"><g id="gjs-logo">
				<path d="M40 5l-12.9 7.4 -12.9 7.4c-1.4 0.8-2.7 2.3-3.7 3.9 -0.9 1.6-1.5 3.5-1.5 5.1v14.9 14.9c0 1.7 0.6 3.5 1.5 5.1 0.9 1.6 2.2 3.1 3.7 3.9l12.9 7.4 12.9 7.4c1.4 0.8 3.3 1.2 5.2 1.2 1.9 0 3.8-0.4 5.2-1.2l12.9-7.4 12.9-7.4c1.4-0.8 2.7-2.2 3.7-3.9 0.9-1.6 1.5-3.5 1.5-5.1v-14.9 -12.7c0-4.6-3.8-6-6.8-4.2l-28 16.2" style="fill:none;stroke-linecap:round;stroke-linejoin:round;stroke-miterlimit:10;stroke-width:10;stroke:#fff"/>
			</g></svg>
			<br/>
			<div class="info-panel-label">
				<b>GrapesJS Webpage Builder</b> is a simple showcase of what is possible to achieve with the
				<a class="info-panel-link gjs-four-color" target="_blank" href="https://github.com/artf/grapesjs">GrapesJS</a>
				core library
			</div>
		</div>
		'*/
		;

		return $html;
	}
}
