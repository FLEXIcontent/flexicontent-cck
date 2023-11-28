<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_content
 *
 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;
JHtml::_('bootstrap.tooltip');


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
		
		$path        = JPath::clean(JPATH_ROOT . $config->location);
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
		$less_file  = 'less/' . $layout_name . '_' . $module->id . '.less';
		$less_path  = JPath::clean($path . $less_file);

		// Create LESS file
		if (!file_exists($less_path))
		{
			$_resource = fopen($less_path, "w");
			fwrite($_resource, $less_code);
			fclose($_resource);

			// Compile LESS file
			$force = flexicontent_html::checkedLessCompile(
				array($less_file),
				$path,
				$inc_path = $path . 'less/include/',
				$force = false
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
		$jinput    = JFactory::getApplication()->input;
		$id        = $jinput->getInt('id', 0);

		if ($id)
		{
			$path = $file_path;
			$path = str_replace('{{id}}', $id, $path);
			$path = JPath::clean(JPATH_ROOT . $path . '.less');

			if (file_exists($path))
			{
				//JFactory::getApplication()->enqueueMessage('Removing LESS file: ' . $path, 'message');
				unlink($path);
			}
			else
			{
				//JFactory::getApplication()->enqueueMessage('Skipped non-existent LESS file: ' . $path, 'notice');
			}
		}

		return '';
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
		$jinput     = JFactory::getApplication()->input;
		$html       = '';

		// Remove less files so that it gets recreated, this happens on form load, ideally it should happen on form save ...
		if (!$lessfile)
		{
			JFactory::getApplication()->enqueueMessage('lessfile not given for the layout builder');
		}
		else
		{
			$matches = null;
			preg_match_all("/{{([0-9a-zA-Z_]+)}}/", $lessfile, $matches);
			//JFactory::getApplication()->enqueueMessage(print_r($matches, true));

			foreach ($matches[0] as $i => $replacement_string)
			{
				$url_variable = $matches[1][$i];
				$url_value    = $jinput->getCmd($url_variable, '');

				if (!strlen($url_value))
				{
					JFactory::getApplication()->enqueueMessage($replacement_string . ' (for less file path creation), respective URL variable ' . $url_variable . ' is empty');
				}
				else
				{
					$lessfile = str_replace($replacement_string, $url_value, $lessfile);
				}
			}

			//JFactory::getApplication()->enqueueMessage($lessfile);
			//unlink(JPath::clean(JPATH_ROOT . '/' . $lessfile));
		}

		static $framework_added = null;

		if ($framework_added === null)
		{
			$framework_added = true;

			$document = JFactory::getDocument();
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
		label.gjs-sm-icon {
			color: #fff;
		}
		');
		
		//JFactory::getDocument()->addScriptDeclaration(
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

			var editor = grapesjs.init({

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
				// Here, at first, we check and use components if are already defined
				// otherwise the HTML string gonna be used
				fromElement: 0,
				components: LandingPage.components || LandingPage.html,

				// We might want to make the same check for styles
				style: LandingPage.style || LandingPage.css,
				protectedCss: \'\',

				showOffsets: 1,

				assetManager: {
					embedAsBase64: 1,
					assets: images
				},

				styleManager: { clearProperties: 1 },

				plugins: [
					\'gjs-preset-webpage\',
					\'grapesjs-lory-slider\',
					\'grapesjs-tabs\',
					\'grapesjs-custom-code\',
					\'grapesjs-touch\',
					\'grapesjs-parser-postcss\',
					\'grapesjs-tooltip\',
					\'gjs-plugin-ckeditor\',
					//\'grapesjs-shape-divider\',
					//\'grapesjs-plugin-header\',
					\'grapesjs-blocks-bootstrap4\',
				],

				pluginsOpts: {
					\'grapesjs-tooltip\': {
						sliderBlock: {
							category: \'Extra\'
						}
					},
					\'grapesjs-lory-slider\': {
						sliderBlock: {
							category: \'Extra\'
						}
					},
					\'grapesjs-tabs\': {
						tabsBlock: {
							category: \'Extra\'
						}
					},
					\'gjs-plugin-ckeditor\': {
						//need to specific tool bar
					},
					\'grapesjs-blocks-bootstrap4\': {
						blocks: {
						},
						blockCategories: {
						},
						labels: {
						},
					},
					\'gjs-preset-webpage\': {
						modalImportTitle: \'Import Template\',
						modalImportLabel: \'<div style="margin-bottom: 10px; font-size: 13px;">Paste here your HTML/CSS and click Import</div>\',
						modalImportContent: function(editor)
						{
							//\'<script>\n\' + document.querySelector(\'#\' + element_id + \'_js\').textContent.trim() + \'\n<\/script>\n\'
							return document.querySelector(\'#\' + element_id + \'_html\').textContent.trim() + \'\n\' +
								\'<style>\n\' + document.querySelector(\'#\' + element_id + \'_css\').textContent.trim() + \'\n<\/style>\n\';
						},

						filestackOpts: null, //{ key: \'AYmqZc2e8RLGLE7TGkX3Hz\' },
						aviaryOpts: false,
						blocksBasicOpts: { flexGrid: 1 },
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
					autosave: true,        // Whether to store data automatically
					autoload: true,        // Whether to auto-load stored data on init
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
				mediaCondition: \'min-width\', // default is `max-width`
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
						editor.store(res => console.log(\'Store callback\'));
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
				pn.getButton(\'options\', item[0]).set(\'attributes\', {title: item[1], \'data-tooltip-pos\': \'bottom\'});
			});
			[[\'open-sm\', \'Style Manager\'], [\'open-layers\', \'Layers\'], [\'open-blocks\', \'Blocks\']]
			.forEach(function(item) {
				pn.getButton(\'views\', item[0]).set(\'attributes\', {title: item[1], \'data-tooltip-pos\': \'bottom\'});
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
			pn.getButton(\'options\', \'sw-visibility\').set(\'active\', 1);


			// Start and end events
			editor.on(\'storage:start\', console.log(\'storage:start\'));
			editor.on(\'storage:end\', console.log(\'storage:end  \'));

			// Store and load events
			editor.on(\'storage:load\', function(e) { console.log(\'Loaded \') });
			editor.on(\'storage:store\', function(e) { console.log(\'Stored \') });


			// Do stuff on load
			editor.on(\'load\', function()
			{
				var $ = grapesjs.$;

				// Load and show settings and style manager
				var openTmBtn = pn.getButton(\'views\', \'open-tm\');
				openTmBtn && openTmBtn.set(\'active\', 1);
				var openSm = pn.getButton(\'views\', \'open-sm\');
				openSm && openSm.set(\'active\', 1);

				// Add Settings Sector
				var traitsSector = $(\'<div class="gjs-sm-sector no-select">\'+
					\'<div class="gjs-sm-title"><span class="icon-settings fa fa-cog"></span> Settings</div>\' +
					\'<div class="gjs-sm-properties" style="display: none;"></div></div>\');
				var traitsProps = traitsSector.find(\'.gjs-sm-properties\');
				traitsProps.append($(\'.gjs-trt-traits\'));
				$(\'.gjs-sm-sectors\').before(traitsSector);

				traitsSector.find(\'.gjs-sm-title\').on(\'click\', function()
				{
					var traitStyle = traitsProps.get(0).style;
					var hidden = traitStyle.display == \'none\';
					traitStyle.display = hidden ? \'block\' : \'none\';
				});

				// Open block manager
				var openBlocksBtn = editor.Panels.getButton(\'views\', \'open-blocks\');
				openBlocksBtn && openBlocksBtn.set(\'active\', 1);
			});

			editor.StorageManager.add(\'form-storage\', {
				/**
				 * Load the data
				 * @param  {Array} keys Array containing values to load, eg, [\'gjs-components\', \'gjs-style\', ...]
				 * @param  {Function} clb Callback function to call when the load is ended
				 * @param  {Function} clbErr Callback function to call in case of errors
				 */
				load(keys, clb, clbErr)
				{
					const result = {};

					keys.forEach(key => {
						var el_id = key.replace(\'fc-gjs-\' + editor_sfx, \'\');
						var value = document.querySelector(\'#\' + element_id + \'_\' +  el_id).textContent;

						console.log(\'Load: \' + key + \' \' + el_id);

						/*if (key == \'html\')
						{
							var js   = document.querySelector(\'#\' + element_id + \'_js\').textContent.trim();

							if (js)
							{
								value = value + \'\n<script>\n\' + document.querySelector(\'#\' + element_id + \'_js\').textContent + \'\n<\/script>\n\';
							}
						}*/

						if (value)
						{
							result[key] = value;
						}
					});

					// Might be called inside some async method
					clb(result);
				},

				/**
				 * Store the data
				 * @param  {Object} data Data object to store
				 * @param  {Function} clb Callback function to call when the load is ended
				 * @param  {Function} clbErr Callback function to call in case of errors
				 */
				store(data, clb, clbErr)
				{
					for (let key in data)
					{
						var el_id = key.replace(\'fc-gjs-\' + editor_sfx, \'\');

						console.log(\'Store: \' + key + \' \' + el_id);

						if (0) //(el_id == \'html\')
						{
							document.querySelector(\'#\' + element_id + \'_html\').innerHTML = editor.getHtml();
							document.querySelector(\'#\' + element_id + \'_js\').innerHTML = editor.getJs();
						}
						else
						{
							document.querySelector(\'#\' + element_id + \'_\' +  el_id).innerHTML = data[key];
						}
					}

					// Might be called inside some async method
					clb();
				},
			});


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


			editor.BlockManager.add(\'fcfield\', {
				label: \'Flexicontent Field\',
				category: \'Flexicontent Data\',
				content: \'<div style="display: inline-block" data-gjs-resizable="true" data-gjs-dragMode="absolute">{flexi_field:FIELDNAME  item:122|current|{{fc-item-id}}  method:display}</div>\',
				select: true,
				activate: true,
				attributes: { class:\'fc-iblock fa fa-database\' },
			});

			editor.BlockManager.add(\'fcitemlink\', {
				label: \'Flexicontent item title with link\',
				category: \'Flexicontent Data\',
				content: \'<div style="display: inline-block" data-gjs-resizable="true" data-gjs-dragMode="absolute">{flexi_link:item  item:566|current|{{fc-item-id}}  linktext:_title_}</div>\',
				select: true,
				activate: true,
				attributes: { class:\'fc-iblock fa fa-database\' },
			});

			editor.BlockManager.add(\'fcitemlink2\', {
				label: \'Flexicontent item link with custom text\',
				category: \'Flexicontent Data\',
				content: \'<div style="display: inline-block" data-gjs-resizable="true" data-gjs-dragMode="absolute">{flexi_link:item  id:577|current|{{fc-item-id}}  linktext:_noclose_} Some HTML {/flexi_link}</div>\',
				select: true,
				activate: true,
				attributes: { class:\'fc-iblock fa fa-database\' },
			});

			editor.BlockManager.add(\'fcitemprofil\', {
				label: \'User profil\',
				category: \'Flexicontent Data\',
				content: \'<div style="display: inline-block" data-gjs-resizable="true" data-gjs-dragMode="absolute">{flexi_item:profile  user:%user_id%  ilayout:%template_name%}</div>\',
				select: true,
				activate: true,
				attributes: { class:\'fc-iblock fa fa-database\' },
			});

			editor.BlockManager.add(\'fcauthor\', {
				label: \'Author profil\',
				category: \'Flexicontent Data\',
				content: \'<div style="display: inline-block" data-gjs-resizable="true" data-gjs-dragMode="absolute"> {flexi_item:profile  author_of:[%item_id% | current]  ilayout:%template_name%}</div>\',
				select: true,
				activate: true,
				attributes: { class:\'fc-iblock fa fa-database\' },
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
					document.querySelector(\'#\' + element_id + \'_html\').innerHTML = editor.getHtml().trim();
					//document.querySelector(\'#\' + element_id + \'_js\').innerHTML = editor.getJs().trim();
					document.querySelector(\'#\' + element_id + \'_css\').innerHTML = editor.getCss().trim();
					document.querySelector(\'#\' + element_id + \'_styles\').innerHTML = JSON.stringify(editor.getStyle());
					document.querySelector(\'#\' + element_id + \'_components\').innerHTML = JSON.stringify(editor.getComponents());
					document.querySelector(\'#\' + element_id + \'_assets\').innerHTML = JSON.stringify(editor.AssetManager.getAll());

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

			editor.load(res => console.log(\'Load callback\'));

			editor.on(\'storage:error\', (err) => {
				alert(`Error: ${err}`);
			});

			editor.on(\'change:device\', () => console.log(\'Current device: \', editor.getDevice()));

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

		$html .= '
		<span style="pointer: cursor; font-size: 48px;" class="btn"
			onclick="this.style.display = \'none\'; this.nextElementSibling.style.display = \'\'; fclayout_init_builder(\'' . $editor_sfx . '\', \'' . $element_id . '\', ); return false;"
		>
			<img alt="Layout Builer" src="' . JUri::root(true) . '/components/com_flexicontent/assets/images/layout_builder.png" style="width: 64px; height: 64px; line-height: 100%;" />
			<span style="font-size: 24px;">' . JText::_('FLEXI_EDIT') . '</span>
		</span>

		<div style="height: 90%; margin: 0px; display: none;">

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
