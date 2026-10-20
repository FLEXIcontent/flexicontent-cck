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
		// One-shot per request, per layout/css id: a category list calls this once per item
		// of the loop and only the first call may rewrite / recompile the LESS (each call
		// would also advance the keyframes counter, making the compiled CSS unstable).
		static $cnt           = -1;
		static $fcl_css_built = array();
		$css_id      = isset($config->id) && $config->id !== '' ? $config->id : $module->id;
		$layout_name = $config->layout_name;
		$build_key   = $layout_name . '_' . $css_id;
		if (isset($fcl_css_built[$build_key])) return;
		$fcl_css_built[$build_key] = true;
		$cnt++;

		$path        = \Joomla\Filesystem\Path::clean(JPATH_ROOT . $config->location);
		$css_prefix  = $config->css_prefix;

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

		// Per-block Custom CSS: raw rules entered in the Settings panel of each block,
		// concatenated in project order (target a block by its class, ex: .c893 a{...}).
		$customCss = '';
		self::_collectCustomCss($wrapper, $customCss);
		if ($customCss !== '') $css .= "\n" . $customCss;

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
	protected static function _collectLinkRules($comp, &$rules, $parent = null)
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
				// The styled block can sit in three positions relative to the link(s):
				//  - as a normal container with anchors inside it  ->  BASE a
				//  - the block itself IS the <a> element           ->  BASE
				//  - the block is wrapped by a manual <a href>     ->  a:has(BASE)
				$typeTags = array('image' => 'img', 'video' => 'iframe', 'map' => 'iframe', 'link' => 'a', 'text' => 'div');
				$tag = isset($comp['type'], $typeTags[$comp['type']]) ? $typeTags[$comp['type']] : (isset($comp['tagName']) && $comp['tagName'] !== '' ? $comp['tagName'] : '');
				$tag = strtolower((string) $tag);
				$ptag = '';
				if (is_array($parent))
				{
					$ptag = isset($parent['type'], $typeTags[$parent['type']]) ? $typeTags[$parent['type']] : (isset($parent['tagName']) && $parent['tagName'] !== '' ? $parent['tagName'] : '');
					$ptag = strtolower((string) $ptag);
				}
				$sels = array($base . ' a');
				if ($tag === 'a') $sels[] = $base;
				elseif ($ptag === 'a') $sels[] = 'a:has(' . $base . ')';
				foreach ($sels as $sel)
				{
					if ($props !== '') $rules[] = $sel . '{' . $props . '}';
					$hv = isset($a['data-fc-linkstyles-hover']) && $a['data-fc-linkstyles-hover'] !== '' ? $a['data-fc-linkstyles-hover'] : (isset($s['data-fc-linkstyles-hover']) ? $s['data-fc-linkstyles-hover'] : '');
					$hv = str_replace(array(';', '{', '}'), '', trim((string) $hv));
					if ($hv !== '') $rules[] = $sel . ':hover{color:' . $hv . ';}';
				}
			}
		}
		if (is_array($comp) && !empty($comp['components']) && is_array($comp['components']))
		{
			foreach ($comp['components'] as $ch) self::_collectLinkRules($ch, $rules, $comp);
		}
	}

	/**
	 * Collect the per-block Custom CSS snippets (data-fc-customcss attribute) of a tree,
	 * concatenated in project order. These are raw, designer-authored rules.
	 */
	protected static function _collectCustomCss($comp, &$out)
	{
		if (is_array($comp) && !empty($comp['attributes']) && is_array($comp['attributes']))
		{
			$v = isset($comp['attributes']['data-fc-customcss']) ? trim((string) $comp['attributes']['data-fc-customcss']) : '';
			if ($v !== '') $out .= $v . "\n";
		}
		if (is_array($comp) && !empty($comp['components']) && is_array($comp['components']))
		{
			foreach ($comp['components'] as $ch) self::_collectCustomCss($ch, $out);
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
		if (!empty($comp['type']) && $comp['type'] === 'textnode')
		{
			// GrapesJS text nodes: plain text, never wrapped in a tag
			return isset($comp['content']) ? (string) $comp['content'] : '';
		}
		$tag   = !empty($comp['tagName']) ? $comp['tagName'] : '';
		if ($tag === '')
		{
			// Default tag of core GrapesJS component types (not stored in the project JSON)
			$typeTags = array('image' => 'img', 'video' => 'iframe', 'map' => 'iframe', 'link' => 'a', 'fc-lien' => 'a', 'text' => 'div');
			$tag      = isset($comp['type'], $typeTags[$comp['type']]) ? $typeTags[$comp['type']] : 'div';
		}
		$attrs = array();

		if (!empty($comp['attributes']) && is_array($comp['attributes']))
		{
			foreach ($comp['attributes'] as $k => $v)
			{
				// data-fc-linkstyles-* are only Style Manager carriers (see _collectLinkRules)
				// and data-fc-customcss is per-block Custom CSS (see _collectCustomCss):
				// neither is meant to reach the front-end markup
				if (strpos($k, 'data-fc-linkstyles-') === 0 || $k === 'data-fc-customcss') continue;
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

		// "Flexicontent Link" block: the chosen target becomes an href placeholder resolved
		// at run time (renderBuilderLayout -> FlexicontentHelperRoute). Keep the raw
		// data-fc-target attribute too, so the block is still detected when re-imported.
		if (!empty($comp['type']) && $comp['type'] === 'fc-lien' && !empty($attrs['data-fc-target']))
		{
			$attrs['href'] = '{flexi_lien:' . $attrs['data-fc-target'] . '}';
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
		elseif (!empty($comp['type']) && $comp['type'] === 'fc-lien')
		{
			// Editable link text = the component's own children (a text span)
			if (!empty($comp['components']) && is_array($comp['components']))
			{
				foreach ($comp['components'] as $fch) $inner .= self::_renderComponent($fch);
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
		elseif (!empty($comp['type']) && $comp['type'] === 'fc-cat-element')
		{
			$cname = isset($comp['attributes']['data-fc-cat']) ? trim((string) $comp['attributes']['data-fc-cat']) : '';
			$inner = $cname !== '' ? '{flexi_cat:' . $cname . '}' : '';
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
		.gjs-block > .gjs-block__media > svg {
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
		}

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

		// The category page elements that the "Category page" blocks embed via {flexi_cat:..}
		// placeholders. Their real render happens on the front-end (grapesjs/category.php
		// pre-renders each element into $options['cat_elements']); the canvas only shows a badge.
		$fcl_cat_options = array(
			array('value' => 'title',          'label' => 'Page title & description',  'group' => 'Category page'),
			array('value' => 'category',       'label' => 'Category description',  'group' => 'Category page'),
			array('value' => 'subcategories',  'label' => 'Sub-categories',        'group' => 'Category page'),
			array('value' => 'peercategories', 'label' => 'Peer categories',       'group' => 'Category page'),
			array('value' => 'alpha',          'label' => 'Alpha index',           'group' => 'Category page'),
			array('value' => 'filters',        'label' => 'Filters & selectors',   'group' => 'Category page'),
			array('value' => 'pagination',     'label' => 'Pagination',            'group' => 'Category page'),
			array('value' => 'items',          'label' => 'Item grid',             'group' => 'Category page'),
		);
		$html .= '<script>window.fcl_builder_catels_' . $editor_sfx . ' = ' . json_encode($fcl_cat_options) . ';</script>';

		// Options for the "Lien Flexicontent" block: ONE "Lien vers" picker listing the
		// current content plus the Flexicontent categories, items and files. Same data
		// sources as the JCE flexicontentlinks adapter (see site/librairies/JCE/links).
		// Values are self-describing: 'current', 'cat:<id>', 'item:<id>', 'file:<id>'.
		$fcl_link_opts = array(
			array('value' => 'current', 'label' => 'Contenu courant', 'group' => 'Contenu courant'),
		);

		try
		{
			$fcl_ldb = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

			// Flexicontent categories (Joomla categories used by com_flexicontent)
			$fcl_lq = $fcl_ldb->getQuery(true)
				->select(array('id', 'title'))
				->from($fcl_ldb->quoteName('#__categories'))
				->where($fcl_ldb->quoteName('extension') . ' = ' . $fcl_ldb->quote('com_content'))
				->where($fcl_ldb->quoteName('published') . ' = 1')
				->order('lft ASC')
				->setLimit(300);
			$fcl_ldb->setQuery($fcl_lq);

			foreach ($fcl_ldb->loadObjectList() as $fcl_lc)
			{
				$fcl_link_opts[] = array('value' => 'cat:' . (int) $fcl_lc->id, 'label' => $fcl_lc->title, 'group' => 'Catégories');
			}

			// Flexicontent items (inner join ensures only real FC items)
			$fcl_lq = $fcl_ldb->getQuery(true)
				->select(array('a.id', 'a.title'))
				->from($fcl_ldb->quoteName('#__content', 'a'))
				->innerJoin($fcl_ldb->quoteName('#__flexicontent_items_ext', 'fi') . ' ON fi.item_id = a.id')
				->where($fcl_ldb->quoteName('a.state') . ' = 1')
				->order('a.title ASC')
				->setLimit(300);
			$fcl_ldb->setQuery($fcl_lq);

			foreach ($fcl_ldb->loadObjectList() as $fcl_li)
			{
				$fcl_link_opts[] = array('value' => 'item:' . (int) $fcl_li->id, 'label' => $fcl_li->title, 'group' => 'Articles');
			}

			// Files uploaded in the Flexicontent file manager
			$fcl_lq = $fcl_ldb->getQuery(true)
				->select(array('id', 'altname', 'filename'))
				->from($fcl_ldb->quoteName('#__flexicontent_files'))
				->where($fcl_ldb->quoteName('published') . ' = 1')
				->order('id DESC')
				->setLimit(300);
			$fcl_ldb->setQuery($fcl_lq);

			foreach ($fcl_ldb->loadObjectList() as $fcl_lf)
			{
				$fcl_lfname = trim((string) $fcl_lf->altname) !== '' ? $fcl_lf->altname : $fcl_lf->filename;
				$fcl_link_opts[] = array('value' => 'file:' . (int) $fcl_lf->id, 'label' => $fcl_lfname, 'group' => 'Fichiers');
			}
		}
		catch (\Throwable $e)
		{
			// Keep only the "current content" option so the block still works
		}

		$html .= '<script>window.fcl_builder_linkopts_' . $editor_sfx . ' = ' . json_encode($fcl_link_opts) . ';</script>';

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
		// Joomla web root (e.g. "/" or "/subdir/"), used to load same-origin canvas assets
		// (Bootstrap CSS) instead of a CDN, so the canvas never depends on an external host.
		$html .= '<script>window.fcl_builder_root = ' . json_encode(\Joomla\CMS\Uri\Uri::root(true)) . ';</script>';

		$fcl_preview_options = '<option value="0">' . (count($fcl_recent_items) ? '(badges only)' : '(badges only - no items found)') . '</option>';

		foreach ($fcl_recent_items as $fcl_ri)
		{
			$fcl_sel = ((int) $fcl_ri->id === $fcl_preview_item_id) ? ' selected="selected"' : '';
			$fcl_preview_options .= '<option value="' . (int) $fcl_ri->id . '"' . $fcl_sel . '>' . htmlspecialchars($fcl_ri->title, ENT_QUOTES, 'UTF-8') . ' (' . (int) $fcl_ri->id . ')</option>';
		}

		$fcl_preview_html = '
			<div class="fcl-preview-toolbar" style="display:flex;align-items:center;gap:8px;padding:6px 10px;color:#333;font-size:12px;border-bottom:1px solid #ddd;">
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
			  \'grapesjs-touch\', \'grapesjs-tooltip\', \'grapesjs-icons\'
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
				\'grapesjs-icons\',
			];

			var editor = grapesjs.init({

				// Do NOT show the browser "Changes you made may not be saved" dialog:
				// this is a TOP-LEVEL editor option (the storageManager section does not read it),
				// the layout fields are always written by saveToForm() on form submit
				noticeOnUnload: false,

				// Bootstrap 5 in the canvas, matching the Joomla 6 front-end (media/vendor/
				// bootstrap ships 5.3.8). Only the CSS is loaded here: the Bootstrap JS bundle
				// is NOT injected inside the canvas iframe (so the native data-api is not
				// available there). Tabs/accordion opening in the editor is handled by the
				// canvas-only shim injected in fcl_ensureCanvasInserts().
				canvas: {
					// Same-origin Bootstrap 5.3.8 (Joomla media/vendor), matching the front-end.
					// Avoids any CDN dependency from inside the canvas iframe.
					styles: [ (window.fcl_builder_root || \'/\') + \'media/vendor/bootstrap/css/bootstrap.min.css\' ]
				},

				avoidInlineStyle: false,

				// Style changes are applied to the selected component itself (inline) instead
				// of creating a new generated class/rule on every edit: one style identity per
				// block. The classes exposed in the Selector Manager stay fully usable.
				selectorManager: { componentFirst: true },

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
					// "Basic" category: only keep text, link, image, video, map (the plugin
					// otherwise also adds column1/2/3/3-7 layout blocks).
					\'gjs-blocks-basic\': {
						blocks: [\'text\', \'link\', \'image\', \'video\', \'map\']
					},
					\'grapesjs-tooltip\': {
						blockTooltip: {
							category: \'Extra\'
						}
					},
					\'grapesjs-tabs\': {
						// Block disabled: superseded by the native BS5 "Tabs" block (nav-tabs).
						// The plugin stays registered so existing layouts using its tab component
						// type keep rendering; a falsy tabsBlock skips BlockManager.add().
						tabsBlock: false
					},
					// Iconify icon picker (grapesjs-icons). The block is dropped into the "Basic"
					// category; a double-click (or a drop) opens the icon picker modal listing the
					// collections below. The chosen icon is inlined as SVG, so the front-end needs
					// no runtime (Iconify is only queried while editing). Add/remove collection
					// prefixes from https://icon-sets.iconify.design/ as needed.
					\'grapesjs-icons\': {
						collections: [\'bi\', \'ri\', \'mdi\', \'fa6-solid\', \'fa6-brands\'],
						block: { category: \'Basic\' }
					},
					\'grapesjs-preset-webpage\': {
						// No preset blocks: preset-webpage would add link-block / quote / text-basic
						// to the "Basic" category (which must only keep text, link, image, video, map).
						blocks: [],
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


			// The "grapesjs-icons" plugin registers its "Icon" block without media, so it shows
			// in the block panel as a bare label. Give it an inline SVG (the block view re-renders
			// on a model change, so this is picked up even after init).
			(function()
			{
				var fclIconBlk = editor.BlockManager.get(\'icon\');
				if (fclIconBlk)
				{
					fclIconBlk.set(\'media\', \'<svg viewBox="0 0 24 24"><path fill="currentColor" d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>\');
				}
			})();


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

			/**
			 * Layouts saved before the fc-accordion component existed keep the plain
			 * \'default\' type, so the new Mode trait would not show up on them. Retag every
			 * root Bootstrap accordion (class \'accordion\' / \'accordion-flush\') as
			 * \'fc-accordion\' when a project is loaded, leaving its inner items untouched.
			 */
			function fcl_migrate_accordions(comp)
			{
				if (!comp || typeof comp !== \'object\') return comp;
				var classes = [];
				if (Array.isArray(comp.classes)) classes = classes.concat(comp.classes);
				else if (typeof comp.classes === \'string\') classes = classes.concat(comp.classes.split(/\s+/));
				if (comp.attributes && comp.attributes.class) classes = classes.concat(String(comp.attributes.class).split(/\s+/));
				var isRoot = classes.indexOf(\'accordion\') >= 0 || classes.indexOf(\'accordion-flush\') >= 0;
				if (isRoot && comp.type !== \'fc-accordion\') comp.type = \'fc-accordion\';
				if (Array.isArray(comp.components))
				{
					for (var i = 0; i < comp.components.length; i++)
					{
						var child = comp.components[i];
						if (child && typeof child === \'object\') fcl_migrate_accordions(child);
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
											pf.frames[g].component = fcl_migrate_accordions(pf.frames[g].component);
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
				if (!comp || !comp.get) return;
				if (!comp.get(\'traits\') || !comp.get(\'traits\').models) return;
				var rows = document.querySelectorAll(\'.gjs-trt-trait\');
				var attrMap = {
					\'Prefix\': \'data-fc-prefix\',
					\'Suffix\': \'data-fc-suffix\',
					\'Custom CSS\': \'data-fc-customcss\',
					\'Item ID\': \'data-fc-itemid\',
					\'Link text\': \'data-fc-linktext\',
					\'User ID\': \'data-fc-userid\',
				};
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

			/**
			 * Clean-up of the auto-generated per-rule classes: GrapesJS creates a brand new
			 * class + rule on each styling operation (c784, c810, ...), so a single block ends
			 * up carrying many class names for basically the same styles. This walks the saved
			 * project and, for each component holding several "generated" classes (matching
			 * c<digits> and used by exactly ONE component), merges all its base rules back into
			 * a single class + rule; state rules (:hover) and @media rules are preserved, their
			 * class name is only rewritten. User classes (shared by >1 components) are never
			 * touched. In-place mutation of the project object.
			 */
			function fcConsolidateProjectStyles(project)
			{
				if (!project || !Array.isArray(project.styles) || !Array.isArray(project.pages)) return project;
				var styles = project.styles;

				var users = {};
				var all = [];
				function walk(node)
				{
					if (!node) return;
					var arr = node.classes;
					if (Array.isArray(arr))
					{
						all.push(node);
						for (var i = 0; i < arr.length; i++)
						{
							var s = arr[i];
							var nmI = s && typeof s === \'object\' ? s.name : s;
							if (nmI) users[nmI] = (users[nmI] || 0) + 1;
						}
					}
					var kids = node.components;
					if (Array.isArray(kids)) for (var j = 0; j < kids.length; j++) walk(kids[j]);
				}
				for (var p = 0; p < project.pages.length; p++)
				{
					var frs = project.pages[p].frames || [];
					for (var f = 0; f < frs.length; f++) walk(frs[f].component);
				}

				var reGen = /^c\d+$/;
				var closes = {};   // generated class -> { target }
				var targets = {};  // retained target classes
				for (var a = 0; a < all.length; a++)
				{
					var cls = all[a].classes;
					var gens = [];
					for (var c = 0; c < cls.length; c++)
					{
						var sC = cls[c];
						var nmC = sC && typeof sC === \'object\' ? sC.name : sC;
						if (nmC && reGen.test(nmC) && users[nmC] === 1) gens.push(nmC);
					}
					// Only consolidate components that accumulated >= 2 generated classes
					if (gens.length > 1)
					{
						var tgt = gens[0];
						targets[tgt] = 1;
						for (var o = 1; o < gens.length; o++) closes[gens[o]] = tgt;
					}
				}
				var closeKeys = Object.keys(closes);
				if (!closeKeys.length) return project;

				// Rewrite the component classes we just collapsed (keep first occurrence, dedupe)
				for (var b = 0; b < all.length; b++)
				{
					var ncl = all[b].classes;
					var out = [];
					var seenCl = {};
					for (var q = 0; q < ncl.length; q++)
					{
						var selQ = ncl[q];
						var nmQ = selQ && typeof selQ === \'object\' ? selQ.name : selQ;
						if (!nmQ) continue;
						if (closes[nmQ]) nmQ = closes[nmQ];
						if (seenCl[nmQ]) continue;
						seenCl[nmQ] = 1;
						if (selQ && typeof selQ === \'object\') selQ.name = nmQ;
						else selQ = nmQ;
						out.push(selQ);
					}
					ncl.length = 0;
					for (var w = 0; w < out.length; w++) ncl.push(out[w]);
				}

				// Rewrite class references in the rules and merge pure base rules per target
				var firstIdx = {};      // target -> index of its first base rule
				var mergedStyle = {};   // target -> merged style object
				var drop = {};          // indices of consumed base rules
				var targetAt = {};      // index -> target to (re)emit a merged rule at
				for (var r = 0; r < styles.length; r++)
				{
					var rule = styles[r];
					if (!rule || !Array.isArray(rule.selectors)) continue;
					var isBase = !rule.mediaText && !rule.state && !rule.atRuleType && !rule.selectorsAdd;
					var pure = isBase;
					var names = [];
					for (var z = 0; z < rule.selectors.length; z++)
					{
						var selR = rule.selectors[z];
						if (!selR || (selR.type && selR.type !== \'class\')) { pure = false; continue; }
						var nn = selR.name || \'\';
						if (closes[nn]) { selR.name = closes[nn]; nn = selR.name; }
						names.push(nn);
					}
					if (!isBase) continue;
					if (!pure || names.length !== 1 || !targets[names[0]]) continue;
					// Nested @media / & selectors inside a style object cannot be merged
					var st = rule.style || {};
					var mergeable = true;
					for (var sk in st)
					{
						if (sk.charAt(0) === \'@\' || sk.charAt(0) === \'&\') { mergeable = false; break; }
					}
					var tk = names[0];
					if (firstIdx[tk] === undefined) { firstIdx[tk] = r; targetAt[r] = tk; }
					if (mergeable)
					{
						mergedStyle[tk] = Object.assign(mergedStyle[tk] || {}, st);
						drop[r] = 1;
					}
				}
				var outS = [];
				var pushedT = {};
				for (var x = 0; x < styles.length; x++)
				{
					var tv = targetAt[x];
					if (tv !== undefined)
					{
						if (!pushedT[tv])
						{
							outS.push({
								selectors: [{ name: tv, type: \'class\' }],
								style: mergedStyle[tv] || {},
								mediaText: \'\', state: \'\', atRuleType: \'\', singleAtRule: false,
							});
							pushedT[tv] = 1;
						}
						continue;
					}
					if (drop[x]) continue;
					outS.push(styles[x]);
				}
				project.styles = outS;
				return project;
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
				if (project)
				{
					try { fcConsolidateProjectStyles(project); } catch(e) { console.warn(\'fcConsolidateProjectStyles error:\', e); }
					fcl_setField(element_id + \'_data\', JSON.stringify(project));
				}
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
			var fcl_cat_opts   = window[\'fcl_builder_catels_\' + editor_sfx] || [];
			var fcl_link_opts  = window[\'fcl_builder_linkopts_\' + editor_sfx] || [];

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
				// Visual helpers for the BS5 structural blocks (canvas only, never exported):
				// keep an empty Spacer / Section identifiable while editing.
				\'.fc-section{outline:1px dashed rgba(110,162,43,.55);outline-offset:-1px}\' +
				\'.fc-spacer{outline:1px dashed rgba(110,162,43,.55);background:rgba(110,162,43,.08);min-height:10px}\' +
				\'.fc-spacer:hover,.fc-spacer.gjs-selected{background:rgba(110,162,43,.18)}\' +
				// Bootstrap tabs: keep the native switch (only the active pane is shown);
				// switch to a tab, then drop components into its (now active) pane. On the front the
				// native BS behaviour keeps only the active pane visible; rules are canvas-only.
				\'.tab-content>.tab-pane{min-height:40px}\' +
				\'.tab-content>.tab-pane.active{outline:1px dashed rgba(110,162,43,.5);outline-offset:-1px}\' +
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

				// Canvas-only interactivity for the BS5 Tabs / Accordion blocks. The Bootstrap
				// JS bundle is NOT loaded inside the iframe, so the native data-api never runs
				// there. This tiny delegated shim reproduces the switch/open behaviour for the
				// editor preview only; it lives in the iframe head and is never exported (the
				// front-end keeps the genuine Bootstrap JS via loadframework(\'bootstrap_js\')).
				var bs = doc.getElementById(\'fcl-canvas-bsjs\');
				if (!bs)
				{
					bs = doc.createElement(\'script\');
					bs.id = \'fcl-canvas-bsjs\';
					bs.textContent =
						\'(function(){if(window.__fclCanvasBs)return;window.__fclCanvasBs=1;\' +
						\'document.addEventListener("click",function(e){\' +
						\'var el=e.target;\' +
						\'while(el&&el!==document&&!el.getAttribute)el=el.parentNode;\' +
						\'if(!el||!el.getAttribute||!el.getAttribute("data-bs-toggle"))return;\' +
						\'var kind=el.getAttribute("data-bs-toggle");\' +
						\'var sel=el.getAttribute("data-bs-target")||el.getAttribute("href")||"";\' +
						\'if(sel.charAt(0)!=="#")return;\' +
						\'var tgt=document.getElementById(sel.slice(1));if(!tgt)return;\' +
						\'if(kind==="tab"||kind==="pill"||kind==="list"){\' +
						\'var nav=el.closest(".nav,[role=tablist]")||el.parentNode;\' +
						\'var lk=nav?nav.querySelectorAll("[data-bs-toggle=tab],[data-bs-toggle=pill],[data-bs-toggle=list]"):[el];\' +
						\'for(var i=0;i<lk.length;i++){lk[i].classList.remove("active");lk[i].setAttribute("aria-selected","false");}\' +
						\'el.classList.add("active");el.setAttribute("aria-selected","true");\' +
						\'var host=tgt.closest?tgt.closest(".tab-content"):null;\' +
						\'var pn=(host||document).querySelectorAll(".tab-pane");\' +
						\'for(var j=0;j<pn.length;j++){pn[j].classList.remove("active","show");}\' +
						\'tgt.classList.add("active","show");e.preventDefault();\' +
						\'}else if(kind==="collapse"){\' +
						\'var isOpen=tgt.classList.contains("show");\' +
						\'var ps=el.getAttribute("data-bs-parent");\' +
						\'if(ps&&ps.charAt(0)==="#"){var par=document.getElementById(ps.slice(1));\' +
						\'if(par){var cs=par.querySelectorAll(".collapse.show");for(var k=0;k<cs.length;k++)cs[k].classList.remove("show");\' +
						\'var bt=par.querySelectorAll("[data-bs-toggle=collapse]");for(var m=0;m<bt.length;m++){bt[m].classList.add("collapsed");bt[m].setAttribute("aria-expanded","false");}}}\' +
						\'if(isOpen){tgt.classList.remove("show");el.classList.add("collapsed");el.setAttribute("aria-expanded","false");}\' +
						\'else{tgt.classList.add("show");el.classList.remove("collapsed");el.setAttribute("aria-expanded","true");}\' +
						\'e.preventDefault();}\' +
						\'},true);})();\';
					doc.head.appendChild(bs);
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
					var walk = function(comp, par)
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
							// Mirror the PHP _collectLinkRules: style descendant anchors, plus
							// the block itself when it IS an <a>, or the wrapping <a> when the
							// block is nested inside a manual link.
							var tagOf = function(c)
							{
								var el = c.getEl ? c.getEl() : null;
								if (el) return String(el.tagName).toLowerCase();
								return String(c.get(\'tagName\') || (c.get(\'type\') === \'link\' ? \'a\' : \'div\')).toLowerCase();
							};
							var tag = tagOf(comp);
							var sels = [base + \' a\'];
							if (tag === \'a\') sels.push(base);
							else if (par) { var pTag = tagOf(par); if (pTag === \'a\') sels.push(\'a:has(\' + base + \')\'); }
							var props = \'\';
							if (gv(\'data-fc-linkstyles-color\') !== \'\')  props += \'color:\' + gv(\'data-fc-linkstyles-color\') + \';\';
							if (gv(\'data-fc-linkstyles-size\') !== \'\')   props += \'font-size:\' + gv(\'data-fc-linkstyles-size\') + \';\';
							if (gv(\'data-fc-linkstyles-weight\') !== \'\') props += \'font-weight:\' + gv(\'data-fc-linkstyles-weight\') + \';\';
							if (gv(\'data-fc-linkstyles-deco\') !== \'\')   props += \'text-decoration:\' + gv(\'data-fc-linkstyles-deco\') + \';\';
							for (var y = 0; y < sels.length; y++)
							{
								if (props) rules.push(sels[y] + \'{\' + props + \'}\');
								if (gv(\'data-fc-linkstyles-hover\') !== \'\') rules.push(sels[y] + \':hover{color:\' + gv(\'data-fc-linkstyles-hover\') + \';}\');
							}
						}
						var children = comp.components && comp.components();
						if (children) children.each(function(c) { walk(c, comp); });
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

			// Reflect the per-block Custom CSS (data-fc-customcss attribute) into the canvas
			// iframe (raw rules, concatenated in project order), so editing shows the result.
			function fcl_ensureCanvasCustomCss()
			{
				var doc = editor.Canvas.getDocument();
				if (!doc || !doc.head) return;
				var rules = [];
				var walk = function(comp)
				{
					if (!comp || typeof comp.get !== \'function\') return;
					var attrs = comp.getAttributes ? (comp.getAttributes() || {}) : {};
					var css = attrs[\'data-fc-customcss\'] ? String(attrs[\'data-fc-customcss\']) : \'\';
					if (css) rules.push(css);
					var children = comp.components && comp.components();
					if (children) children.each(function(c) { walk(c); });
				};
				if (editor.getWrapper) walk(editor.getWrapper());
				var st = doc.getElementById(\'fcl-canvas-customcss\');
				if (!st)
				{
					st = doc.createElement(\'style\');
					st.id = \'fcl-canvas-customcss\';
					doc.head.appendChild(st);
				}
				st.textContent = rules.join(\'\n\');
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
				fcl_ensureCanvasCustomCss();
				fcSchedulePreviewRefresh(0);
			});
			editor.on(\'frame:load\', fcl_ensureCanvasInserts);
			editor.on(\'frame:load\', fcl_ensureCanvasLinkStyles);
			editor.on(\'component:update\', fcl_ensureCanvasLinkStyles);
			editor.on(\'component:styleUpdate\', fcl_ensureCanvasLinkStyles);
			editor.on(\'component:add\', fcl_ensureCanvasLinkStyles);
			editor.on(\'component:remove\', fcl_ensureCanvasLinkStyles);
			editor.on(\'frame:load\', fcl_ensureCanvasCustomCss);
			editor.on(\'component:update\', fcl_ensureCanvasCustomCss);
			editor.on(\'component:styleUpdate\', fcl_ensureCanvasCustomCss);
			editor.on(\'component:add\', fcl_ensureCanvasCustomCss);
			editor.on(\'component:remove\', fcl_ensureCanvasCustomCss);

			function fcl_opt_label(v)
			{
				for (var i = 0; i < fcl_field_opts.length; i++)
				{
					if (fcl_field_opts[i].value === v) return fcl_field_opts[i].label;
				}
				return v;
			}

			function fcl_cat_opt_label(v)
			{
				for (var i = 0; i < fcl_cat_opts.length; i++)
				{
					if (fcl_cat_opts[i].value === v) return fcl_cat_opts[i].label;
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
				onRender(m)
				{
					this.fcSetup(m || {});
				},
				fcBuildSelect()
				{
					// Native <select> carrying the options, kept inside the trait DOM so its
					// bubbling "change" event reaches GrapesJS (component attribute updates).
					// Built on demand: custom getInputEl() is NOT guaranteed to be invoked by
					// every GrapesJS version/trait flow.
					var model = this.model;
					var opts = (model && model.get) ? (model.get(\'options\') || []) : [];
					var select = document.createElement(\'select\');
					var groups = {};
					for (var i = 0; i < opts.length; i++)
					{
						var o = opts[i];
						var v = (typeof o.value === \'undefined\' ? o.id : o.value);
						v = String(v).replace(/"/g, \'&quot;\');
						var label = o.name || o.label || v;
						var g = o.group || \'Flexicontent\';
						if (!groups[g]) groups[g] = [];
						groups[g].push({ v: v, t: label });
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
					return select;
				},
				fcSetup(m)
				{
					var el = this.el;
					if (!el) return;
					// Resolve the combo root defensively: modern GrapesJS calls onRender with
					// ({ elInput, component, trait }); elInput may be the hidden <select> (the
					// .fc-combo then lives as a sibling inside the [data-input] container) or
					// the rendered trait field wrapping the combo.
					var combo = (m && m.elInput && m.elInput.closest)
						? m.elInput.closest(\'.fc-combo\')
						: null;
					if (!combo && el.querySelector) combo = el.querySelector(\'.fc-combo\');
					if (!combo) return;
					combo.__fclTrait = this;
					this._fcCombo = combo;
					this._fcOpts = [];
					this._fcActive = -1;
					this._fcLabel = \'\';
					var sel = this.$input ? this.$input.get(0) : null;
					var groupEl = combo.querySelector(\'.fc-combo-groups\');
					var inp = combo.querySelector(\'.fc-combo-input\');
					var list = combo.querySelector(\'.fc-combo-list\');
					if (!groupEl || !inp || !list) return;
					if (!sel)
					{
						sel = this.fcBuildSelect();
						if (!sel) return;
						this.$input = { get: function() { return sel; } };
						this.input = sel;
						if (list.parentNode) list.parentNode.appendChild(sel);
					}
					sel.style.display = \'none\';
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
					list.addEventListener(\'mousedown\', function(e) { e.preventDefault(); });
					list.addEventListener(\'click\', function(e) {
						var o = e.target && e.target.closest ? e.target.closest(\'.fc-combo-opt\') : null;
						var v = o && o.getAttribute(\'data-value\');
						if (o && v != null) self.fcCommit(v);
					});
					var arrow = combo.querySelector(\'.fc-combo-arrow\');
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
					var el = this._fcCombo || this.el;
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
					var el = this._fcCombo || this.el;
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
					var el = this._fcCombo || this.el;
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
					var el = this._fcCombo || this.el;
					this.fcReflectEl(el && el.querySelector(\'.fc-combo-input\'), this.$input ? this.$input.get(0) : null);
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

			// Safety net: guarantee the field combo opens even if a given GrapesJS version
			// diverges on trait hook wiring / input flow. Delegated capture listener; uses the
			// registered trait instance when available (full filter/commit handling), else
			// falls back to a raw reveal of the list DOM.
			if (!window.__fclComboDelegated)
			{
				window.__fclComboDelegated = true;
				function fclComboEnsureOpen(target)
				{
					if (!target || !target.closest) return;
					var inp = target.closest ? target.closest(\'.fc-combo-input\') : null;
					if (!inp) return;
					var combo = inp.closest ? inp.closest(\'.fc-combo\') : null;
					if (!combo) return;
					var t = combo.__fclTrait;
					if (t && typeof t.fcOpen === \'function\') { t.fcOpen(); return; }
					var list = combo.querySelector(\'.fc-combo-list\');
					if (list && list.style.display !== \'block\') list.style.display = \'block\';
				}
				document.addEventListener(\'click\', function(e) { fclComboEnsureOpen(e.target); }, true);
				document.addEventListener(\'focusin\', function(e) { fclComboEnsureOpen(e.target); }, true);
			}

			// --- CSS autocomplete (show-hint + css-hint) for the Custom CSS editor ---------
			// The CodeMirror bundled inside GrapesJS is 5.63.0 but ships WITHOUT the hint
			// addons. We load them from the same CDN the GrapesJS assets come from, after
			// pointing the global CodeMirror at the bundled constructor so the addons register
			// against it (the addons only touch the global).
			var fc_cm_hint_css_injected = false;
			function fcCMEnableHints(cm)
			{
				if (!cm || typeof cm.getOption !== \'function\' || cm._fcHintsEnabled) return;
				var CM = window.CodeMirror;
				if (!CM || typeof CM.showHint !== \'function\' || !CM.hint || typeof CM.hint.css !== \'function\') return;
				try
				{
					var ek = cm.getOption(\'extraKeys\') || {};
					if (Array.isArray(ek)) ek.push({ \'Ctrl-Space\': \'autocomplete\' });
					else ek = Object.assign({}, ek, { \'Ctrl-Space\': \'autocomplete\' });
					cm.setOption(\'extraKeys\', ek);
					cm.on(\'inputRead\', function(c, change)
					{
						if (change.origin !== \'complete\' && change.text && change.text.length)
						{
							CM.showHint(c, CM.hint.css, { completeSingle: false });
						}
					});
					cm._fcHintsEnabled = true;
				}
				catch (e) {}
			}
			function fcCMLoadHints()
			{
				if (window.fcCMHintsReady === true || window.fcCMHintsReady === \'loading\') return;
				window.fcCMHintsReady = \'loading\';
				var base = \'https://unpkg.com/codemirror@5.63.0/addon/hint/\';
				var files = [ \'show-hint.js\', \'css-hint.js\' ];
				(function next(i)
				{
					if (i >= files.length)
					{
						window.fcCMHintsReady = true;
						if (!fc_cm_hint_css_injected)
						{
							fc_cm_hint_css_injected = true;
							var st = document.createElement(\'style\');
							st.id = \'fc-cm-hint-css\';
							st.textContent = \'.CodeMirror-hints{position:absolute;z-index:10;overflow:hidden;list-style:none;margin:0;padding:2px;box-shadow:.5px .5px 3px rgba(0,0,0,.35);border-radius:3px;border:1px solid #ddd;font-size:90%;max-height:20em;overflow-y:auto;background:#fff}.CodeMirror-hint{cursor:pointer;white-space:pre;color:inherit}.CodeMirror-hint-active{background:#08f;color:#fff}\';
							document.head.appendChild(st);
						}
						var editors = window.fcCMEditors || [];
						for (var j = 0; j < editors.length; j++) fcCMEnableHints(editors[j]);
						return;
					}
					var s = document.createElement(\'script\');
					s.src = base + files[i];
					s.onload = function() { next(i + 1); };
					s.onerror = function() { window.fcCMHintsReady = \'error\'; };
					document.head.appendChild(s);
				})(0);
			}

			// Per-block Custom CSS, edited in the injected "Settings" sector (the traits
			// panel of the Style Manager). GrapesJS 0.23 has no native textarea trait, so
			// register a lightweight one cloning the base text trait view (trait model still
			// maps the value to the component attribute, here data-fc-customcss).
			editor.TraitManager.addType(\'textarea\', {
				eventCapture: [\'change\'],
				getInputEl: function()
				{
					if (!this.input)
					{
						var ta = document.createElement(\'textarea\');
						ta.rows = 6;
						ta.spellcheck = false;
						ta.style.width = \'100%\';
						var ph = this.model && this.model.get ? (this.model.get(\'placeholder\') || \'\') : \'\';
						if (ph) ta.placeholder = ph;
						this.input = ta;
						var self = this;
						// Wire up once the field is actually in the DOM (CodeMirror needs it)
						setTimeout(function() { self.fcAttachCM(); }, 0);
					}
					// The value lives in the component attribute (data-fc-customcss) and
					// getModelValue() reads it directly: populate on render so reloaded values
					// show up. Never touch it while the user is typing in this very field.
					var v = this.getModelValue ? this.getModelValue() : \'\';
					if (typeof v !== \'undefined\' && v !== null)
					{
						v = String(v);
						if (this.fcCmEditor)
						{
							if (!this.fcCmFocused && String(this.fcCmEditor.getValue()) !== v) this.fcCmEditor.setValue(v);
						}
						else if (String(this.input.value) !== v) this.input.value = v;
					}
					return this.input;
				},
				// Replaces the raw textarea by the CodeMirror that ships INSIDE GrapesJS (it is
				// the CodeManager "CodeMirror" viewer, css mode bundled): no extra asset to load.
				fcAttachCM: function()
				{
					if (this.fcCmReady || !this.input || !this.input.parentNode) return;
					this.fcCmReady = true;
					var viewer = null;
					try
					{
						var base = editor.CodeManager && typeof editor.CodeManager.getViewer === \'function\' ? editor.CodeManager.getViewer(\'CodeMirror\') : null;
						if (base && typeof base.clone === \'function\') viewer = base.clone();
					}
					catch (err) {}
					if (!viewer) return;
					var self = this;
					try
					{
						viewer.set({ codeName: \'css\', readOnly: false, theme: \'hopscotch\', lineNumbers: true });
						viewer.init(this.input);
						if (!viewer.editor) return;
						this.fcCmEditor = viewer.editor;
						this.fcCmEditor.setOption(\'mode\', \'css\');
						this.fcCmEditor.setOption(\'lineWrapping\', true);
						this.fcCmEditor.getWrapperElement().style.minHeight = \'120px\';
						this.fcCmEditor.getWrapperElement().style.width = \'100%\';
						var ph = this.model && this.model.get ? this.model.get(\'placeholder\') : \'\';
						if (ph) { try { this.fcCmEditor.setOption(\'placeholder\', ph); } catch (err) {} }
						this.fcCmEditor.on(\'change\', function() { self.onCMChange(); });
						this.fcCmEditor.on(\'focus\', function() { self.fcCmFocused = true; });
						this.fcCmEditor.on(\'blur\', function() { self.fcCmFocused = false; });
						this.fcCmEditor.refresh();
						this.input.style.display = \'none\';
						// Expose the bundled CodeMirror constructor (statics-only usage) and hook
						// the CSS autocomplete (loaded lazily from the addon CDN).
						window.CodeMirror = window.CodeMirror || this.fcCmEditor.constructor;
						window.fcCMEditors = window.fcCMEditors || [];
						window.fcCMEditors.push(this.fcCmEditor);
						fcCMEnableHints(this.fcCmEditor);
						fcCMLoadHints();
					}
					catch (err)
					{
						if (this.fcCmEditor)
						{
							try { this.fcCmEditor.toTextArea(); } catch (err2) {}
						}
						this.fcCmEditor = null;
						this.input.style.display = \'\';
					}
				},
				onCMChange: function()
				{
					if (!this.fcCmEditor || !this.input) return;
					var v = this.fcCmEditor.getValue();
					// Keep the raw textarea (source of truth for the delegated change handler) in sync
					if (String(this.input.value) !== String(v)) this.input.value = v;
					if (this.model && typeof this.model.set === \'function\' && String(this.model.get(\'value\') || \'\') !== String(v))
					{
						this.model.set(\'value\', v);
					}
				},
				// Base setInputValue only touches the hidden textarea; mirror into CodeMirror too
				setInputValue: function(value)
				{
					var el = this.getInputElem && this.getInputElem();
					value = (value == null ? \'\' : String(value));
					if (el) el.value = value;
					if (this.fcCmEditor && !this.fcCmFocused && String(this.fcCmEditor.getValue()) !== value)
					{
						this.fcCmEditor.setValue(value);
					}
				},
				// Tears the CodeMirror instance down when the trait view is removed
				removed: function()
				{
					if (this.fcCmEditor && typeof this.fcCmEditor.toTextArea === \'function\')
					{
						try { this.fcCmEditor.toTextArea(); } catch (err) {}
					}
					if (window.fcCMEditors)
					{
						var idx = window.fcCMEditors.indexOf(this.fcCmEditor);
						if (idx > -1) window.fcCMEditors.splice(idx, 1);
					}
					this.fcCmEditor = null;
					this.fcCmFocused = false;
				},
			});

			// --- BS5 grid / spacing helpers -------------------------------------------------
			// Widths and gutters are edited through BS5 utility classes (never inline styles),
			// so the saved HTML stays responsive and clean. These helpers back the structural
			// Section/Row/Column/Spacer components registered below.
			var FCL_COL_BP_RE = {
				\'\': /^col(?:-[0-9]{1,2}|-auto)?$/,
				sm: /^col-sm(?:-[0-9]{1,2}|-auto)?$/,
				md: /^col-md(?:-[0-9]{1,2}|-auto)?$/,
				lg: /^col-lg(?:-[0-9]{1,2}|-auto)?$/,
				xl: /^col-xl(?:-[0-9]{1,2}|-auto)?$/,
				xxl: /^col-xxl(?:-[0-9]{1,2}|-auto)?$/
			};
			var FCL_COL_ANY_RE = /^col(?:-(?:sm|md|lg|xl|xxl))?(?:-[0-9]{1,2}|-auto)?$/;

			function fcl_colParse(cls)
			{
				var m = String(cls || \'\').match(/^col(?:-(sm|md|lg|xl|xxl))?(?:-([0-9]{1,2}|auto))?$/);
				return m ? { bp: m[1] || \'\', span: m[2] || \'\' } : null;
			}

			function fcl_colHasAny(comp)
			{
				var cls = (comp.getClasses && comp.getClasses()) || [];
				for (var i = 0; i < cls.length; i++) { if (FCL_COL_ANY_RE.test(cls[i])) return true; }
				return false;
			}

			// Map a real pixel width to a BS5 breakpoint key (largest bp <= width).
			function fcl_bpFromWidth(w)
			{
				if (w >= 1400) return \'xxl\';
				if (w >= 1200) return \'xl\';
				if (w >= 992) return \'lg\';
				if (w >= 768) return \'md\';
				if (w >= 576) return \'sm\';
				return \'\';
			}

			// BS5 breakpoint of the current canvas. The iframe viewport width (innerWidth) is what
			// the @media rules actually use, so it is the authoritative source; the frame element and
			// the device width are only fallbacks (the Desktop device has width \'\' in this builder,
			// which used to yield bp=\'\' and made resized columns fall back to 50/50).
			function fcl_deviceBp(widthOverride)
			{
				var w = parseInt(widthOverride, 10) || 0;
				if (!w)
				{
					try
					{
						var win = editor.Canvas.getWindow && editor.Canvas.getWindow();
						if (win && win.innerWidth) w = win.innerWidth;
					}
					catch (e) {}
				}
				if (!w)
				{
					try
					{
						var frame = editor.Canvas.getFrameEl();
						if (frame && frame.offsetWidth) w = frame.offsetWidth;
					}
					catch (e) {}
				}
				if (!w)
				{
					try
					{
						var doc = editor.Canvas.getDocument();
						if (doc && doc.documentElement && doc.documentElement.clientWidth) w = doc.documentElement.clientWidth;
					}
					catch (e) {}
				}
				if (!w)
				{
					try { var dm = editor.getModel().getDeviceModel(); w = dm ? (parseInt(dm.get(\'width\'), 10) || 0) : 0; } catch (e) {}
				}
				return fcl_bpFromWidth(w);
			}

			function fcl_colRankOf(bp) { return !bp ? 1 : ({ sm: 2, md: 3, lg: 4, xl: 5, xxl: 6 }[bp] || 1); }

			// Effective column class at the given device breakpoint (the largest breakpoint
			// present that is <= the device one), mirroring how the browser applies BS5.
			function fcl_colEffective(comp, bp)
			{
				var rank = fcl_colRankOf(bp), best = \'\', bestRank = -1;
				var cls = (comp.getClasses && comp.getClasses()) || [];
				for (var i = 0; i < cls.length; i++)
				{
					var p = fcl_colParse(cls[i]);
					if (!p) continue;
					var r = fcl_colRankOf(p.bp);
					if (r <= rank && r > bestRank) { best = cls[i]; bestRank = r; }
				}
				return best;
			}

			// Replace the column class for ONE breakpoint only (other breakpoints preserved).
			function fcl_colSetAtBp(comp, bp, span)
			{
				var re = FCL_COL_BP_RE[bp] || FCL_COL_BP_RE[\'\'];
				var cls = (comp.getClasses ? comp.getClasses() : []).slice();
				for (var i = 0; i < cls.length; i++) { if (re.test(cls[i])) comp.removeClass(cls[i]); }
				comp.addClass(\'col\' + (bp ? \'-\' + bp : \'\') + (span ? \'-\' + span : \'\'));
			}

			// Generic "one class per family" helpers used by the fc-class-select traits.
			function fcl_classMatch(comp, re)
			{
				var cls = (comp.getClasses && comp.getClasses()) || [];
				for (var i = 0; i < cls.length; i++) { if (re.test(cls[i])) return cls[i]; }
				return \'\';
			}
			function fcl_classReplace(comp, re, value)
			{
				var cls = (comp.getClasses ? comp.getClasses() : []).slice();
				for (var i = 0; i < cls.length; i++) { if (re.test(cls[i])) comp.removeClass(cls[i]); }
				if (value) comp.addClass(value);
			}

			var FCL_SPACING_LABELS = { 0: \'0\', 1: \'0.25rem\', 2: \'0.5rem\', 3: \'1rem\', 4: \'1.5rem\', 5: \'3rem\' };
			function fcl_spacingOptions(prefix)
			{
				var out = [{ value: \'\', label: \'None\' }];
				for (var i = 0; i <= 5; i++) out.push({ value: prefix + \'-\' + i, label: FCL_SPACING_LABELS[i] });
				return out;
			}
			function fcl_gutterOptions(prefix)
			{
				var p = prefix || \'g\';
				var out = [{ value: \'\', label: p === \'g\' ? \'Default (1.5rem)\' : \'Default\' }];
				for (var i = 0; i <= 5; i++) out.push({ value: p + \'-\' + i, label: FCL_SPACING_LABELS[i] });
				return out;
			}
			function fcl_colWidthOptions()
			{
				var out = [
					{ value: \'\', label: \'None\', group: \'Auto\' },
					{ value: \'col\', label: \'Equal width (.col)\', group: \'Auto\' },
					{ value: \'col-auto\', label: \'Fit content (.col-auto)\', group: \'Auto\' }
				];
				var bps = [
					{ bp: \'\', label: \'Base / xs (all)\' },
					{ bp: \'sm\', label: \'sm (>=576)\' },
					{ bp: \'md\', label: \'md (>=768)\' },
					{ bp: \'lg\', label: \'lg (>=992)\' },
					{ bp: \'xl\', label: \'xl (>=1200)\' },
					{ bp: \'xxl\', label: \'xxl (>=1400)\' }
				];
				for (var i = 0; i < bps.length; i++)
				{
					for (var n = 1; n <= 12; n++)
					{
						out.push({ value: \'col\' + (bps[i].bp ? \'-\' + bps[i].bp : \'\') + \'-\' + n, label: n + \'/12\', group: bps[i].label });
					}
				}
				return out;
			}

			// Grouped <select> trait that maps its value to a BS5 utility CLASS instead of an
			// attribute: the trait definition supplies a custom getValue()/setValue(), which
			// short-circuits the base trait model attribute write (nothing pollutes the HTML).
			editor.TraitManager.addType(\'fc-class-select\', {
				getInputEl: function()
				{
					if (!this.input)
					{
						var opts = (this.model && this.model.get) ? (this.model.get(\'options\') || []) : [];
						var sel = document.createElement(\'select\');
						var groups = {}, order = [];
						for (var i = 0; i < opts.length; i++)
						{
							var o = opts[i];
							if (typeof o === \'string\') o = { value: o, label: o };
							var val = String(typeof o.value === \'undefined\' ? (o.id || \'\') : o.value);
							var lab = o.label || o.name || val;
							var g = o.group || \'\';
							if (!groups[g]) { groups[g] = []; order.push(g); }
							groups[g].push({ v: val, t: lab });
						}
						for (var gi = 0; gi < order.length; gi++)
						{
							var gname = order[gi], host = sel;
							if (gname)
							{
								host = document.createElement(\'optgroup\');
								host.setAttribute(\'label\', gname);
								sel.appendChild(host);
							}
							var list = groups[gname];
							for (var oi = 0; oi < list.length; oi++)
							{
								var op = document.createElement(\'option\');
								op.value = list[oi].v;
								op.textContent = list[oi].t;
								host.appendChild(op);
							}
						}
						this.input = sel;
					}
					return this.input;
				},
				onRender: function()
				{
					// Reflect the current (possibly computed) value once the field is in the DOM.
					try
					{
						var v = (this.model && typeof this.model.getTargetValue === \'function\') ? this.model.getTargetValue() : \'\';
						this.setInputValue(v == null ? \'\' : v);
					}
					catch (e) {}
				},
			});

			// Every block gets the Custom CSS trait on selection (any component type).
			editor.on(\'component:selected\', function(comp)
			{
				if (!comp || typeof comp.get !== \'function\') return;
				var tr = comp.get(\'traits\');
				if (!tr || typeof tr.add !== \'function\') return;

				var fcl_hasTrait = function(name)
				{
					if (!tr.models) return false;
					for (var j = 0; j < tr.models.length; j++)
					{
						if (tr.models[j] && tr.models[j].get && tr.models[j].get(\'name\') === name) return true;
					}
					return false;
				};

				var added = false;

				// Mode trait for a Bootstrap accordion root. Added on selection so it also shows
				// up on existing / legacy accordions, whatever component type they were saved with.
				var cls = comp.getClasses ? (comp.getClasses() || []) : [];
				if ((cls.indexOf(\'accordion\') >= 0 || cls.indexOf(\'accordion-flush\') >= 0) && !fcl_hasTrait(\'data-fc-accmode\'))
				{
					tr.add(fcl_accModeTrait());
					added = true;
				}

				if (!fcl_hasTrait(\'data-fc-customcss\'))
				{
					tr.add({ type: \'textarea\', name: \'data-fc-customcss\', label: \'Custom CSS\',
						placeholder: \'.my-css { color:red }  (target this block by its class, see Selector Manager)\' });
					added = true;
				}

				// The TraitsView renders on component:toggled only: re-trigger it so the
				// freshly added trait(s) show up immediately (idempotent, no loop).
				if (added) editor.trigger(\'component:toggled\', comp);
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

			// Category page element: a pre-built block of the category view (page title,
			// category description, sub-categories, peer categories, alpha index, filters,
			// pagination, item grid). Only meaningful in the category page layout editor
			// (editor_sfx = builder_page1), other editors ignore these blocks.
			editor.DomComponents.addType(\'fc-cat-element\', {
				isComponent: function(el)
				{
					if (el && el.hasAttribute && el.hasAttribute(\'data-fc-cat\')) return { type: \'fc-cat-element\' };
				},
				model: {
					defaults: {
						name: \'Category Page Element\',
						editable: false,
						droppable: false,
						resizable: true,
						attributes: { \'data-fc-cat\': \'\' },
						traits: [{
							type: \'fc-field-select\',
							name: \'data-fc-cat\',
							label: \'Category element\',
							options: fcl_cat_opts.map(function(o) { return { value: o.value, name: o.label, group: o.group }; }),
						}],
					},
				},
				view: fcl_boxed_view(function()
				{
					var el = this.model.getAttributes()[\'data-fc-cat\'] || \'\';
					var label = fcl_cat_opt_label(el);
					this.el.innerHTML = fcl_data_box(\'C\',
						label ? (\'Category: \' + label) : \'Category page element\',
						el ? (\'Renders "\' + el + \'" of the category page\') : \'Choose an element in the Settings panel\');
				}),
			});

			// Flexicontent link block: an editable <a class="btn"> whose target is chosen
			// with a single "Lien vers" picker (Contenu courant / Catégories / Articles /
			// Fichiers). No custom view: the default one keeps the inner text editable.
			// At save time the value becomes an href placeholder {flexi_lien:<value>}
			// resolved at run time by JHtmlFlexicontent::renderBuilderLayout.
			editor.DomComponents.addType(\'fc-lien\', {
				isComponent: function(el)
				{
					if (el && el.hasAttribute && el.hasAttribute(\'data-fc-target\')) return { type: \'fc-lien\' };
				},
				model: {
					defaults: {
						tagName: \'a\',
						name: \'Flexicontent Link\',
						classes: [\'btn\'],
						attributes: { \'data-fc-target\': \'current\' },
						traits: [{
							type: \'fc-field-select\',
							name: \'data-fc-target\',
							label: \'Lien vers\',
							options: fcl_link_opts.map(function(o) { return { value: o.value, name: o.label, group: o.group }; }),
						}],
					},
				},
			});

			editor.BlockManager.add(\'fclien\', {
				label: \'Lien Flexicontent\',
				category: \'Flexicontent Data\',
				content: \'<a class="btn" data-gjs-type="fc-lien" data-fc-target="current"><span data-gjs-type="text">En savoir plus</span></a>\',
				select: true,
				activate: true,
				attributes: { class:\'fc-iblock fa fa-link\' },
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

			// Category page blocks: each one embeds a pre-rendered element of the category
			// page (see _renderComponent -> {flexi_cat:...} tokens). Shown only in the page
			// layout editor of the category template (editor_sfx = builder_page1).
			if (editor_sfx === \'builder_page1\')
			{
				editor.BlockManager.add(\'fccat-title\', {
					label: \'Page title & description\',
					category: \'Category page\',
					content: { type: \'fc-cat-element\', attributes: { \'data-fc-cat\': \'title\' } },
					select: true,
					activate: true,
					attributes: { class:\'fc-iblock fa fa-database\' },
				});

				editor.BlockManager.add(\'fccat-category\', {
					label: \'Category description\',
					category: \'Category page\',
					content: { type: \'fc-cat-element\', attributes: { \'data-fc-cat\': \'category\' } },
					select: true,
					activate: true,
					attributes: { class:\'fc-iblock fa fa-database\' },
				});

				editor.BlockManager.add(\'fccat-subcategories\', {
					label: \'Sub-categories\',
					category: \'Category page\',
					content: { type: \'fc-cat-element\', attributes: { \'data-fc-cat\': \'subcategories\' } },
					select: true,
					activate: true,
					attributes: { class:\'fc-iblock fa fa-database\' },
				});

				editor.BlockManager.add(\'fccat-peercategories\', {
					label: \'Peer categories\',
					category: \'Category page\',
					content: { type: \'fc-cat-element\', attributes: { \'data-fc-cat\': \'peercategories\' } },
					select: true,
					activate: true,
					attributes: { class:\'fc-iblock fa fa-database\' },
				});

				editor.BlockManager.add(\'fccat-alpha\', {
					label: \'Alpha index\',
					category: \'Category page\',
					content: { type: \'fc-cat-element\', attributes: { \'data-fc-cat\': \'alpha\' } },
					select: true,
					activate: true,
					attributes: { class:\'fc-iblock fa fa-database\' },
				});

				editor.BlockManager.add(\'fccat-filters\', {
					label: \'Filters & selectors\',
					category: \'Category page\',
					content: { type: \'fc-cat-element\', attributes: { \'data-fc-cat\': \'filters\' } },
					select: true,
					activate: true,
					attributes: { class:\'fc-iblock fa fa-database\' },
				});

				editor.BlockManager.add(\'fccat-pagination\', {
					label: \'Pagination\',
					category: \'Category page\',
					content: { type: \'fc-cat-element\', attributes: { \'data-fc-cat\': \'pagination\' } },
					select: true,
					activate: true,
					attributes: { class:\'fc-iblock fa fa-database\' },
				});

				editor.BlockManager.add(\'fccat-items\', {
					label: \'Item grid\',
					category: \'Category page\',
					content: { type: \'fc-cat-element\', attributes: { \'data-fc-cat\': \'items\' } },
					select: true,
					activate: true,
					attributes: { class:\'fc-iblock fa fa-database\' },
				});
			}

			function fcl_applyLinkTraits()
			{
				// Block-scoped link styles are driven by the Style Manager "Link styles"
				// composite in the Typography sector (values stored in the component style map
				// as data-fc-link* keys, with a data-fc-links anchor attribute). No traits here.
			}

			// --- Bootstrap 5 structural components (Section / Row / Column / Spacer) ------
			// Column widths + row gutters + section/spacer rhythm are all edited through BS5
			// utility classes via the fc-class-select trait above (no inline styles).

			editor.DomComponents.addType(\'fc-row\', {
				isComponent: function(el)
				{
					if (el && el.classList && el.classList.contains(\'row\')) return { type: \'fc-row\' };
				},
				model: {
					defaults: {
						traits: [{
							type: \'fc-class-select\',
							name: \'data-fc-rowgx\',
							label: \'Gutter horizontal\',
							options: fcl_gutterOptions(\'gx\'),
							getValue: function(o) { return fcl_classMatch(o.component, /^gx-[0-5]$/); },
							setValue: function(o) { fcl_classReplace(o.component, /^gx-[0-5]$/, o.value || \'\'); fcl_classReplace(o.component, /^g-[0-5]$/, \'\'); },
						}, {
							type: \'fc-class-select\',
							name: \'data-fc-rowgy\',
							label: \'Gutter vertical\',
							options: fcl_gutterOptions(\'gy\'),
							getValue: function(o) { return fcl_classMatch(o.component, /^gy-[0-5]$/); },
							setValue: function(o) { fcl_classReplace(o.component, /^gy-[0-5]$/, o.value || \'\'); fcl_classReplace(o.component, /^g-[0-5]$/, \'\'); },
						}, {
							type: \'fc-class-select\',
							name: \'data-fc-rowmt\',
							label: \'Space above\',
							options: fcl_spacingOptions(\'mt\'),
							getValue: function(o) { return fcl_classMatch(o.component, /^mt-[0-5]$/); },
							setValue: function(o) { fcl_classReplace(o.component, /^mt-[0-5]$/, o.value || \'\'); },
						}, {
							type: \'fc-class-select\',
							name: \'data-fc-rowmb\',
							label: \'Space below\',
							options: fcl_spacingOptions(\'mb\'),
							getValue: function(o) { return fcl_classMatch(o.component, /^mb-[0-5]$/); },
							setValue: function(o) { fcl_classReplace(o.component, /^mb-[0-5]$/, o.value || \'\'); },
						}],
					},
					init: function()
					{
						// Migrate the legacy combined gutter (g-N = both axes) to the split
						// gx-N / gy-N so it can then be edited per axis.
						var cls = this.getClasses();
						var g = null;
						for (var i = 0; i < cls.length; i++)
						{
							var m = /^g-([0-5])$/.exec(cls[i]);
							if (m) { g = m[1]; break; }
						}
						if (g === null) return;
						var hasX = false, hasY = false;
						for (var j = 0; j < cls.length; j++)
						{
							if (/^gx-[0-5]$/.test(cls[j])) hasX = true;
							if (/^gy-[0-5]$/.test(cls[j])) hasY = true;
						}
						this.removeClass(\'g-\' + g);
						if (!hasX) this.addClass(\'gx-\' + g);
						if (!hasY) this.addClass(\'gy-\' + g);
					},
				},
			});

			editor.DomComponents.addType(\'fc-col\', {
				isComponent: function(el)
				{
					if (!el || !el.className) return;
					var parts = String(el.className).split(/\s+/);
					for (var i = 0; i < parts.length; i++) { if (FCL_COL_ANY_RE.test(parts[i])) return { type: \'fc-col\' }; }
				},
				model: {
					defaults: {
						// Magnet resizer: only the right handle is shown (every other handle must
						// be explicitly disabled, they all default to true in GrapesJS 0.23);
						// on release the width is snapped to the 12-col grid and re-expressed as
						// classes (the inline width written live by the resizer is dropped by the
						// canvas refresh).
						resizable: { tl: false, tc: false, tr: false, cl: false, cr: true, bl: false, bc: false, br: false, onEnd: function() { fcl_colResizeEnd(); } },
						traits: [{
							type: \'fc-class-select\',
							name: \'data-fc-colwidth\',
							label: \'Column width\',
							options: fcl_colWidthOptions(),
							getValue: function(o) { return fcl_colEffective(o.component, fcl_deviceBp()); },
							setValue: function(o)
							{
								var comp = o.component, value = o.value || \'\';
								if (!value)
								{
									// "None" resets every breakpoint of the column family.
									var cls = (comp.getClasses ? comp.getClasses() : []).slice();
									for (var i = 0; i < cls.length; i++) { if (FCL_COL_ANY_RE.test(cls[i])) comp.removeClass(cls[i]); }
									return;
								}
								var p = fcl_colParse(value);
								if (p) fcl_colSetAtBp(comp, p.bp, p.span);
							},
						}],
					},
				},
			});

			editor.DomComponents.addType(\'fc-section\', {
				isComponent: function(el)
				{
					if (el && el.tagName === \'SECTION\') return { type: \'fc-section\' };
				},
				model: {
					defaults: {
						tagName: \'section\',
						droppable: true,
						traits: [{
							type: \'fc-class-select\',
							name: \'data-fc-secpad\',
							label: \'Vertical padding\',
							options: fcl_spacingOptions(\'py\'),
							getValue: function(o) { return fcl_classMatch(o.component, /^py-[0-5]$/); },
							setValue: function(o) { fcl_classReplace(o.component, /^py-[0-5]$/, o.value || \'\'); },
						}, {
							type: \'fc-class-select\',
							name: \'data-fc-secmb\',
							label: \'Bottom margin\',
							options: fcl_spacingOptions(\'mb\'),
							getValue: function(o) { return fcl_classMatch(o.component, /^mb-[0-5]$/); },
							setValue: function(o) { fcl_classReplace(o.component, /^mb-[0-5]$/, o.value || \'\'); },
						}],
					},
				},
			});

			editor.DomComponents.addType(\'fc-spacer\', {
				isComponent: function(el)
				{
					if (el && el.classList && el.classList.contains(\'fc-spacer\')) return { type: \'fc-spacer\' };
				},
				model: {
					defaults: {
						tagName: \'div\',
						droppable: false,
						selectable: true,
						traits: [{
							type: \'fc-class-select\',
							name: \'data-fc-spacerh\',
							label: \'Height (padding)\',
							options: fcl_spacingOptions(\'py\'),
							getValue: function(o) { return fcl_classMatch(o.component, /^py-[0-5]$/); },
							setValue: function(o) { fcl_classReplace(o.component, /^py-[0-5]$/, o.value || \'\'); },
						}],
					},
				},
			});

			// BS5 Alert. Two traits drive it: "Variant" maps to a single alert-<context> class and
			// "Dismissible" toggles the alert-dismissible class together with the .btn-close
			// button. Unlike the attribute-oriented fc-class-select default both act on classes /
			// children, so they supply their own getValue() / setValue().
			var FCL_ALERT_VARIANTS = [\'primary\', \'secondary\', \'success\', \'danger\', \'warning\', \'info\', \'light\', \'dark\'];
			var FCL_ALERT_VARIANT_RE = /^alert-(primary|secondary|success|danger|warning|info|light|dark)$/;

			function fcl_alertVariantOptions()
			{
				var out = [{ value: \'\', label: \'Default (no variant)\' }];
				for (var i = 0; i < FCL_ALERT_VARIANTS.length; i++)
				{
					out.push({ value: \'alert-\' + FCL_ALERT_VARIANTS[i], label: FCL_ALERT_VARIANTS[i] });
				}
				return out;
			}

			function fcl_alertCloseBtn(comp)
			{
				var found = null;
				var kids = (comp && comp.components) ? comp.components() : null;
				if (kids && kids.forEach)
				{
					kids.forEach(function(c)
					{
						var a = (c.getAttributes ? c.getAttributes() : null) || {};
						if (!found && a[\'data-bs-dismiss\'] === \'alert\') found = c;
					});
				}
				return found;
			}

			function fcl_alertDismiss(comp, on)
			{
				var cls = (comp.getClasses ? (comp.getClasses() || []) : []);
				var has = cls.indexOf(\'alert-dismissible\') >= 0;
				var btn = fcl_alertCloseBtn(comp);
				if (on)
				{
					if (!has) comp.addClass(\'alert-dismissible\');
					if (!btn)
					{
						comp.append(\'<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>\');
					}
				}
				else
				{
					if (has) comp.removeClass(\'alert-dismissible\');
					if (btn) btn.remove();
				}
			}

			editor.DomComponents.addType(\'fc-alert\', {
				isComponent: function(el)
				{
					if (el && el.classList && el.classList.contains(\'alert\')) return { type: \'fc-alert\' };
				},
				model: {
					defaults: {
						tagName: \'div\',
						droppable: true,
						selectable: true,
						attributes: { role: \'alert\' },
						traits: [
							{
								type: \'fc-class-select\',
								name: \'data-fc-alertvariant\',
								label: \'Variant\',
								options: fcl_alertVariantOptions(),
								getValue: function(o) { return fcl_classMatch(o.component, FCL_ALERT_VARIANT_RE); },
								setValue: function(o) { fcl_classReplace(o.component, FCL_ALERT_VARIANT_RE, o.value || \'\'); },
							},
							{
								type: \'fc-class-select\',
								name: \'data-fc-alertdismiss\',
								label: \'Dismissible\',
								options: [{ value: \'\', label: \'No\' }, { value: \'yes\', label: \'Yes\' }],
								getValue: function(o)
								{
									var c = o && o.component;
									var cl = (c && c.getClasses) ? (c.getClasses() || []) : [];
									return cl.indexOf(\'alert-dismissible\') >= 0 ? \'yes\' : \'\';
								},
								setValue: function(o)
								{
									if (o && o.component) fcl_alertDismiss(o.component, o.value === \'yes\');
								},
							},
						],
					},
				},
			});

			// Bootstrap tab panes are plain droppable containers. The canvas-only CSS above keeps
			// every pane visible while editing, so components can be dropped into each one; the
			// native BS behaviour (only the active pane visible) is restored on the front-end.
			editor.DomComponents.addType(\'fc-tab-pane\', {
				isComponent: function(el)
				{
					if (el && el.classList && el.classList.contains(\'tab-pane\')) return { type: \'fc-tab-pane\' };
				},
				model: {
					defaults: {
						tagName: \'div\',
						droppable: true,
						selectable: true,
					},
				},
			});

			// BS5 Accordion root. A "Mode" trait toggles between the exclusive accordion
			// (every .accordion-collapse carries data-bs-parent="#id": opening one closes the
			// others) and the independent mode (data-bs-parent removed: several panels may stay
			// open and all can be closed). The attribute is written on the child components so
			// both the canvas and the exported front-end HTML follow the native BS behaviour.
			var fcl_accSeq = 0;

			function fcl_accCollapses(comp)
			{
				var out = [];
				var walk = function(c)
				{
					var cls = c.getClasses ? (c.getClasses() || []) : [];
					if (cls.indexOf(\'accordion-collapse\') >= 0) out.push(c);
					var kids = c.components && c.components();
					if (kids && kids.forEach) kids.forEach(walk);
				};
				var kids = comp.components && comp.components();
				if (kids && kids.forEach) kids.forEach(walk);
				return out;
			}

			function fcl_accRootId(comp)
			{
				var attrs = comp.getAttributes ? (comp.getAttributes() || {}) : {};
				var id = attrs.id ? String(attrs.id) : \'\';
				if (!id)
				{
					id = \'fcAcc\' + (++fcl_accSeq) + \'_\' + Date.now().toString(36);
					comp.addAttributes({ id: id });
				}
				return id;
			}

			function fcl_accModeTrait()
			{
				return {
					type: \'fc-class-select\',
					name: \'data-fc-accmode\',
					label: \'Mode\',
					options: [
						{ value: \'exclusive\', label: \'Exclusive (one open)\' },
						{ value: \'independent\', label: \'Independent (all closable)\' }
					],
					getValue: function(o)
					{
						var comp = o && o.component;
						if (!comp) return \'exclusive\';
						var cs = fcl_accCollapses(comp);
						for (var i = 0; i < cs.length; i++)
						{
							var a = cs[i].getAttributes ? (cs[i].getAttributes() || {}) : {};
							if (a[\'data-bs-parent\']) return \'exclusive\';
						}
						return \'independent\';
					},
					setValue: function(o)
					{
						var comp = o && o.component;
						if (!comp) return;
						var cs = fcl_accCollapses(comp);
						if (o.value === \'independent\')
						{
							for (var i = 0; i < cs.length; i++) cs[i].removeAttributes([\'data-bs-parent\']);
						}
						else
						{
							var id = fcl_accRootId(comp);
							for (var j = 0; j < cs.length; j++) cs[j].addAttributes({ \'data-bs-parent\': \'#\' + id });
						}
					},
				};
			}

			editor.DomComponents.addType(\'fc-accordion\', {
				isComponent: function(el)
				{
					if (el && el.classList && el.classList.contains(\'accordion\')) return { type: \'fc-accordion\' };
				},
				model: {
					defaults: {
						tagName: \'div\',
						droppable: true,
						traits: [fcl_accModeTrait()],
					},
				},
			});

			// Keep each Tabs / Accordion instance self-contained. Both BS5 blocks ship fixed ids
			// (#fcAccordion / #fcAcc1.., #fcTabs / #fcTab1..). GrapesJS de-duplicates the *id
			// attributes* when a second instance is dropped (it appends "-2": #fcAccordion ->
			// #fcAccordion-2) but it leaves the *references* untouched (data-bs-target /
			// data-bs-parent, aria-controls / aria-labelledby, href="#..."), so the new instance
			// keeps driving the first one. A group is therefore broken whenever one of its
			// references resolves to an element that is NOT part of the group (or an id is
			// duplicated). Broken groups get re-scoped positionally: ids and references are
			// regenerated together, pairing the Nth toggle with the Nth panel. Healthy groups are
			// left untouched. Runs on load (fixes already-saved layouts) and after a component is
			// added (block drop, clone, paste).
			var fcl_bsScopeSeq = 0;
			var FCL_BS_REF_ATTRS = [\'data-bs-target\', \'data-bs-parent\', \'aria-controls\', \'aria-labelledby\', \'href\'];

			function fcl_bsBase(prefix)
			{
				return prefix + (++fcl_bsScopeSeq) + \'_\' + Date.now().toString(36);
			}

			function fcl_directComps(comp)
			{
				var out = [];
				var kk = (comp && comp.components) ? comp.components() : null;
				if (kk && kk.forEach) kk.forEach(function(c) { out.push(c); });
				return out;
			}

			function fcl_walkComps(comp, fn)
			{
				if (!comp) return;
				fn(comp);
				var kids = (typeof comp.components === \'function\') ? comp.components() : null;
				if (kids && kids.forEach) kids.forEach(function(c) { fcl_walkComps(c, fn); });
			}

			function fcl_compClasses(comp)
			{
				return (comp && comp.getClasses) ? (comp.getClasses() || []) : [];
			}

			function fcl_hasTabToggle(comp)
			{
				var found = false;
				fcl_walkComps(comp, function(c)
				{
					var a = (c.getAttributes ? c.getAttributes() : null) || {};
					var t = a[\'data-bs-toggle\'];
					if (t === \'tab\' || t === \'pill\' || t === \'list\') found = true;
				});
				return found;
			}

			function fcl_tabContentSibling(nav)
			{
				var parent = (nav && nav.parent) ? nav.parent() : null;
				if (!parent) return null;
				var models = (parent.components && parent.components().models) || [];
				var idx = models.indexOf(nav);
				if (idx < 0) return null;
				for (var i = idx + 1; i < models.length; i++)
				{
					var cls = fcl_compClasses(models[i]);
					if (cls.indexOf(\'tab-content\') >= 0) return models[i];
					if (cls.indexOf(\'nav\') >= 0) break;
				}
				// Fallback: a tab-content placed before its nav list (still its own group).
				for (var b = idx - 1; b >= 0; b--)
				{
					var cls2 = fcl_compClasses(models[b]);
					if (cls2.indexOf(\'tab-content\') >= 0) return models[b];
					if (cls2.indexOf(\'nav\') >= 0) break;
				}
				return null;
			}

			function fcl_bsGroups(root)
			{
				var groups = [];
				fcl_walkComps(root, function(c)
				{
					var cls = fcl_compClasses(c);
					if (cls.indexOf(\'accordion\') >= 0 || cls.indexOf(\'accordion-flush\') >= 0)
					{
						groups.push({ kind: \'accordion\', root: c });
					}
				});
				fcl_walkComps(root, function(c)
				{
					var cls = fcl_compClasses(c);
					if (cls.indexOf(\'nav\') < 0 || !fcl_hasTabToggle(c)) return;
					var content = fcl_tabContentSibling(c);
					if (content) groups.push({ kind: \'tabs\', nav: c, content: content });
				});
				return groups;
			}

			function fcl_groupComps(g)
			{
				var comps = [];
				if (g.kind === \'accordion\')
				{
					fcl_walkComps(g.root, function(c) { comps.push(c); });
				}
				else
				{
					fcl_walkComps(g.nav, function(c) { comps.push(c); });
					fcl_walkComps(g.content, function(c) { comps.push(c); });
				}
				return comps;
			}

			function fcl_contains(root, node)
			{
				if (!root || !node) return false;
				if (root === node) return true;
				var k = (typeof root.components === \'function\') ? root.components() : null;
				var found = false;
				if (k && k.forEach) k.forEach(function(c) { if (!found && fcl_contains(c, node)) found = true; });
				return found;
			}

			// A group is broken when an id is duplicated, or when one of its references resolves
			// to a component that exists but lives outside the group. Dangling references (target
			// id absent from the tree, e.g. a plain external link) are ignored.
			function fcl_groupBroken(g, idOwner, counts)
			{
				var comps = fcl_groupComps(g);
				for (var i = 0; i < comps.length; i++)
				{
					var a = (comps[i].getAttributes ? comps[i].getAttributes() : null) || {};
					if (a.id && counts[a.id] > 1) return true;
					for (var k = 0; k < FCL_BS_REF_ATTRS.length; k++)
					{
						var v = a[FCL_BS_REF_ATTRS[k]];
						if (typeof v !== \'string\' || !v) continue;
						var t = v.charAt(0) === \'#\' ? v.slice(1) : v;
						var owner = t ? idOwner[t] : null;
						if (owner && comps.indexOf(owner) < 0) return true;
					}
				}
				return false;
			}

			function fcl_scopeAccordion(root)
			{
				var base = fcl_bsBase(\'fcAcc\');
				root.addAttributes({ id: base });
				var btns = [], panes = [];
				fcl_walkComps(root, function(c)
				{
					var cls = fcl_compClasses(c);
					var a = (c.getAttributes ? c.getAttributes() : null) || {};
					if (a[\'data-bs-toggle\'] === \'collapse\') btns.push(c);
					if (cls.indexOf(\'accordion-collapse\') >= 0) panes.push(c);
				});
				var n = Math.max(btns.length, panes.length);
				for (var i = 0; i < n; i++)
				{
					var paneId = base + \'-\' + (i + 1);
					if (panes[i])
					{
						var pa = (panes[i].getAttributes ? panes[i].getAttributes() : null) || {};
						var set = { id: paneId };
						if (pa[\'data-bs-parent\']) set[\'data-bs-parent\'] = \'#\' + base;
						panes[i].addAttributes(set);
					}
					if (btns[i]) btns[i].addAttributes({ \'data-bs-target\': \'#\' + paneId, \'aria-controls\': paneId });
				}
			}

			function fcl_scopeTabs(nav, content)
			{
				var base = fcl_bsBase(\'fcTabs\');
				nav.addAttributes({ id: base });
				if (content) content.addAttributes({ id: base + \'-content\' });
				var btns = [], panes = [];
				fcl_walkComps(nav, function(c)
				{
					var a = (c.getAttributes ? c.getAttributes() : null) || {};
					var t = a[\'data-bs-toggle\'];
					if (t === \'tab\' || t === \'pill\' || t === \'list\') btns.push(c);
				});
				if (content)
				{
					fcl_directComps(content).forEach(function(c)
					{
						if (fcl_compClasses(c).indexOf(\'tab-pane\') >= 0) panes.push(c);
					});
					if (!panes.length)
					{
						fcl_walkComps(content, function(c) { if (fcl_compClasses(c).indexOf(\'tab-pane\') >= 0) panes.push(c); });
					}
				}
				var n = Math.max(btns.length, panes.length);
				for (var i = 0; i < n; i++)
				{
					var btnId = base + \'-tab-\' + (i + 1);
					var paneId = base + \'-pane-\' + (i + 1);
					if (btns[i]) btns[i].addAttributes({ id: btnId, \'data-bs-target\': \'#\' + paneId, \'aria-controls\': paneId });
					if (panes[i]) panes[i].addAttributes({ id: paneId, \'aria-labelledby\': btnId });
				}
			}

			function fcl_fixBsGroups()
			{
				var root = (editor.getWrapper ? editor.getWrapper() : null);
				if (!root) return;
				var idOwner = {}, counts = {};
				fcl_walkComps(root, function(c)
				{
					var a = (c.getAttributes ? c.getAttributes() : null) || {};
					if (!a.id) return;
					counts[a.id] = (counts[a.id] || 0) + 1;
					if (!idOwner[a.id]) idOwner[a.id] = c;
				});
				var groups = fcl_bsGroups(root);
				for (var i = 0; i < groups.length; i++)
				{
					var g = groups[i];
					if (!fcl_groupBroken(g, idOwner, counts)) continue;
					if (g.kind === \'accordion\') fcl_scopeAccordion(g.root);
					else fcl_scopeTabs(g.nav, g.content);
				}
			}

			var fcl_bsUniqTimer = null;
			function fcl_scheduleBsUniq()
			{
				if (fcl_bsUniqTimer) return;
				fcl_bsUniqTimer = setTimeout(function()
				{
					fcl_bsUniqTimer = null;
					try { fcl_fixBsGroups(); } catch (e) {}
				}, 50);
			}

			editor.on(\'component:add\', fcl_scheduleBsUniq);
			editor.on(\'block:drag:stop\', fcl_scheduleBsUniq);
			editor.on(\'load\', fcl_scheduleBsUniq);

			// Magnetic column resizer: snap the dragged width to the 12-col grid and hand the
			// overflow to the next sibling column so the row always totals 12 (at the current
			// device breakpoint). Falls back to width-derived spans for auto/equal columns.
			function fcl_colResizeEnd()
			{
				try
				{
					var comp = editor.getSelected();
					if (comp && comp.get(\'type\') === \'fc-col\')
					{
						// Snap BEFORE removing the inline width: fcl_colSnapSiblings needs the live
						// (dragged) width to compute the ratio. Only then drop the inline size the
						// resizer wrote (columns are always class-driven).
						var parent = comp.parent();
						if (parent && parent.get(\'type\') === \'fc-row\') fcl_colSnapSiblings(comp, parent);
						fcl_stripSizeStyles(comp);
					}
				}
				catch (e) {}
				try { editor.Canvas.refresh({ all: true }); } catch (e2) {}
			}

			function fcl_stripSizeStyles(comp)
			{
				try
				{
					if (!comp || !comp.getStyle) return;
					var names = [\'width\', \'min-width\', \'max-width\', \'height\', \'min-height\', \'max-height\'];
					var style = comp.getStyle() || {};
					for (var i = 0; i < names.length; i++)
					{
						if (style[names[i]] != null) comp.removeStyle(names[i]);
					}
				}
				catch (e) {}
			}

			// Drop every bootstrap column-width class on the component and set ONE base span.
			// The base (no breakpoint) class keeps the column defined at every width; writing a
			// breakpoint-specific class would leave lower widths without any width (=> stacked).
			function fcl_colWriteBase(comp, span)
			{
				var cls = (comp.getClasses ? comp.getClasses() : []).slice();
				for (var i = 0; i < cls.length; i++) { if (FCL_COL_ANY_RE.test(cls[i])) comp.removeClass(cls[i]); }
				comp.addClass(\'col\' + (span ? \'-\' + span : \'\'));
			}

			// Snap the dragged row back to the 12-col grid. The reference is the ROW width: it stays
			// constant during the drag even when the live column overflows/wraps, so the ratio is
			// reliable (reading the sibling columns instead breaks as soon as one wraps to a new
			// line). The dragged column takes its snapped span and the others share the remainder
			// (>=1 each) so the row always totals exactly 12. Result is written as clean base classes
			// (col-N), never accumulated per breakpoint.
			function fcl_colSnapSiblings(comp, parent)
			{
				var cols = [];
				parent.components().each(function(c) { if (c.get && c.get(\'type\') === \'fc-col\') cols.push(c); });
				if (cols.length < 2) return;
				var idx = cols.indexOf(comp);
				if (idx < 0) return;
				var rowEl = parent.getEl && parent.getEl();
				var elIdx = cols[idx].getEl && cols[idx].getEl();
				var rowW = rowEl ? rowEl.getBoundingClientRect().width : 0;
				var wIdx = elIdx ? elIdx.getBoundingClientRect().width : 0;
				if (!rowW || !wIdx) return;
				var target = Math.round((wIdx / rowW) * 12);
				var maxTarget = 12 - (cols.length - 1); // keep >=1 free for each other column
				if (target < 1) target = 1;
				if (target > maxTarget) target = maxTarget;
				// Live widths of the other columns, for a proportional share of the remainder.
				var others = [], otherTotal = 0;
				for (var i = 0; i < cols.length; i++)
				{
					if (i === idx) continue;
					var e = cols[i].getEl && cols[i].getEl();
					var w = e ? e.getBoundingClientRect().width : 0;
					others.push({ comp: cols[i], w: w });
					otherTotal += w;
				}
				var remaining = 12 - target, alloc = 0;
				for (var k = 0; k < others.length; k++)
				{
					var s = otherTotal ? Math.round((others[k].w / otherTotal) * remaining) : Math.floor(remaining / others.length);
					if (s < 1) s = 1;
					others[k].span = s;
					alloc += s;
				}
				// Fix rounding so the shares sum to exactly \'remaining\' (keeping every share >=1).
				var diff = alloc - remaining, guard = 0;
				while (diff !== 0 && guard++ < 100)
				{
					var moved = false;
					for (var m = 0; m < others.length && diff !== 0; m++)
					{
						if (diff > 0 && others[m].span > 1) { others[m].span--; diff--; moved = true; }
						else if (diff < 0) { others[m].span++; diff++; moved = true; }
					}
					if (!moved) break;
				}
				fcl_colWriteBase(comp, target);
				for (var n = 0; n < others.length; n++) fcl_colWriteBase(others[n].comp, others[n].span);
			}

			// --- Bootstrap 5 blocks (BS5.3 markup, same version as the canvas + the front) ---
			// Interactivity (tabs, accordion, ...) relies on the native BS5 data-api, which is
			// available both in the canvas (bootstrap.bundle is loaded) and on the front-end
			// (loadframework(\'bootstrap_js\') -> Bootstrap::framework()). The per-item id suffix
			// applied by renderBuilderLayout() keeps #id / #target references unique per item.
			var fcl_bs5 = { id: \'bs5\', label: \'Bootstrap 5\', open: false, order: 4 };

			editor.BlockManager.add(\'bs5-container\', {
				label: \'Container\',
				category: fcl_bs5,
				select: true,
				activate: true,
				attributes: { class: \'fa fa-square-o\' },
				content: \'<div class="container"><div class="fc-empty-box"></div></div>\',
			});

			editor.BlockManager.add(\'bs5-section\', {
				label: \'Section\',
				category: fcl_bs5,
				select: true,
				activate: true,
				attributes: { class: \'fa fa-window-maximize\' },
				content: \'<section class="fc-section py-4"><div class="fc-empty-box"></div></section>\',
			});

			editor.BlockManager.add(\'bs5-spacer\', {
				label: \'Spacer\',
				category: fcl_bs5,
				select: true,
				activate: true,
				attributes: { class: \'fa fa-arrows-v\' },
				content: \'<div class="fc-spacer py-3"></div>\',
			});

			editor.BlockManager.add(\'bs5-row\', {
				label: \'Row (empty)\',
				category: fcl_bs5,
				select: true,
				activate: true,
				attributes: { class: \'fa fa-ellipsis-h\' },
				content: \'<div class="container"><div class="row fc-empty-box"></div></div>\',
			});

			editor.BlockManager.add(\'bs5-column\', {
				label: \'Column\',
				category: fcl_bs5,
				select: true,
				activate: true,
				attributes: { class: \'fa fa-columns\' },
				content: \'<div class="col fc-empty-box"></div>\',
			});

			editor.BlockManager.add(\'bs5-row-col-2\', {
				label: \'Row: 2 columns\',
				category: fcl_bs5,
				select: true,
				activate: true,
				attributes: { class: \'fa fa-columns\' },
				content: \'<div class="container"><div class="row"><div class="col-md-6 fc-empty-box"></div><div class="col-md-6 fc-empty-box"></div></div></div>\',
			});

			editor.BlockManager.add(\'bs5-row-col-3\', {
				label: \'Row: 3 columns\',
				category: fcl_bs5,
				select: true,
				activate: true,
				attributes: { class: \'fa fa-th-large\' },
				content: \'<div class="container"><div class="row"><div class="col-md-4 fc-empty-box"></div><div class="col-md-4 fc-empty-box"></div><div class="col-md-4 fc-empty-box"></div></div></div>\',
			});

			editor.BlockManager.add(\'bs5-row-col-4\', {
				label: \'Row: 4 columns\',
				category: fcl_bs5,
				select: true,
				activate: true,
				attributes: { class: \'fa fa-th\' },
				content: \'<div class="container"><div class="row"><div class="col-md-3 fc-empty-box"></div><div class="col-md-3 fc-empty-box"></div><div class="col-md-3 fc-empty-box"></div><div class="col-md-3 fc-empty-box"></div></div></div>\',
			});

			editor.BlockManager.add(\'bs5-row-mainbar\', {
				label: \'Row: main + sidebar\',
				category: fcl_bs5,
				select: true,
				activate: true,
				attributes: { class: \'fa fa-bars\' },
				content: \'<div class="container"><div class="row"><div class="col-md-8 fc-empty-box"></div><div class="col-md-4 fc-empty-box"></div></div></div>\',
			});

			editor.BlockManager.add(\'bs5-card\', {
				label: \'Card\',
				category: fcl_bs5,
				select: true,
				activate: true,
				attributes: { class: \'fa fa-square\' },
				content: \'<div class="card"><div class="card-body"><h5 class="card-title">Card title</h5><p class="card-text">Some quick example text to build on the card title and make up the bulk of the card content.</p><a href="#" class="btn btn-primary">Go somewhere</a></div></div>\',
			});

			editor.BlockManager.add(\'bs5-card-image\', {
				label: \'Card with image\',
				category: fcl_bs5,
				select: true,
				activate: true,
				attributes: { class: \'fa fa-picture-o\' },
				content: \'<div class="card"><img class="card-img-top" src="\' + plp + \'600x400" alt="Card image"><div class="card-body"><h5 class="card-title">Card title</h5><p class="card-text">Some quick example text to build on the card title.</p><a href="#" class="btn btn-primary">Go somewhere</a></div></div>\',
			});

			editor.BlockManager.add(\'bs5-button\', {
				label: \'Button\',
				category: fcl_bs5,
				select: true,
				activate: true,
				attributes: { class: \'fa fa-hand-pointer-o\' },
				content: \'<a href="#" class="btn btn-primary">Button</a>\',
			});

			editor.BlockManager.add(\'bs5-button-outline\', {
				label: \'Button (outline)\',
				category: fcl_bs5,
				select: true,
				activate: true,
				attributes: { class: \'fa fa-square-o\' },
				content: \'<a href="#" class="btn btn-outline-primary">Button</a>\',
			});

			editor.BlockManager.add(\'bs5-alert\', {
				label: \'Alert\',
				category: fcl_bs5,
				select: true,
				activate: true,
				attributes: { class: \'fa fa-exclamation-triangle\' },
				content: \'<div class="alert alert-info" role="alert"><span>A simple info alert, check it out!</span></div>\',
			});

			editor.BlockManager.add(\'bs5-accordion\', {
				label: \'Accordion\',
				category: fcl_bs5,
				select: true,
				activate: true,
				attributes: { class: \'fa fa-list\' },
				content: \'<div class="accordion" id="fcAccordion"><div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#fcAcc1" aria-expanded="true" aria-controls="fcAcc1">Accordion Item #1</button></h2><div id="fcAcc1" class="accordion-collapse collapse show" data-bs-parent="#fcAccordion"><div class="accordion-body">First item body content.</div></div></div><div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#fcAcc2" aria-expanded="false" aria-controls="fcAcc2">Accordion Item #2</button></h2><div id="fcAcc2" class="accordion-collapse collapse" data-bs-parent="#fcAccordion"><div class="accordion-body">Second item body content.</div></div></div><div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#fcAcc3" aria-expanded="false" aria-controls="fcAcc3">Accordion Item #3</button></h2><div id="fcAcc3" class="accordion-collapse collapse" data-bs-parent="#fcAccordion"><div class="accordion-body">Third item body content.</div></div></div></div>\',
			});

			editor.BlockManager.add(\'bs5-tabs\', {
				label: \'Tabs\',
				category: fcl_bs5,
				select: true,
				activate: true,
				attributes: { class: \'fa fa-folder-o\' },
				content: \'<ul class="nav nav-tabs" id="fcTabs" role="tablist"><li class="nav-item" role="presentation"><button class="nav-link active" id="fcTab1-tab" data-bs-toggle="tab" data-bs-target="#fcTab1" type="button" role="tab" aria-controls="fcTab1" aria-selected="true">Tab 1</button></li><li class="nav-item" role="presentation"><button class="nav-link" id="fcTab2-tab" data-bs-toggle="tab" data-bs-target="#fcTab2" type="button" role="tab" aria-controls="fcTab2" aria-selected="false">Tab 2</button></li><li class="nav-item" role="presentation"><button class="nav-link" id="fcTab3-tab" data-bs-toggle="tab" data-bs-target="#fcTab3" type="button" role="tab" aria-controls="fcTab3" aria-selected="false">Tab 3</button></li></ul><div class="tab-content" id="fcTabsContent"><div class="tab-pane fade show active" id="fcTab1" role="tabpanel" aria-labelledby="fcTab1-tab"></div><div class="tab-pane fade" id="fcTab2" role="tabpanel" aria-labelledby="fcTab2-tab"></div><div class="tab-pane fade" id="fcTab3" role="tabpanel" aria-labelledby="fcTab3-tab"></div></div>\',
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
