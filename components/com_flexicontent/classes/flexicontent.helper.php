<?php
/**
 * @version 1.5 stable $Id$
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

defined( '_JEXEC' ) or die( 'Restricted access' );

//include constants file
require_once (JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'defineconstants.php');

class flexicontent_html
{
	static function escape($str) {
		return addslashes(htmlspecialchars($str, ENT_COMPAT, 'UTF-8'));
	}

	static function get_basedomain($url)
	{
		$pieces = parse_url($url);
		$domain = isset($pieces['host']) ? $pieces['host'] : '';   echo " ";
		if (preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $regs)) {
			return $regs['domain'];
		}
		return false;
	}

	static function is_safe_url($url, $baseonly=true)
	{
		$cparams = JComponentHelper::getParams( 'com_flexicontent' );
		$allowed_redirecturls = $cparams->get('allowed_redirecturls', 'internal_base');

		// prefix the URL if needed so that parse_url will work
		$has_prefix = preg_match("#^http|^https|^ftp#i", $url);
		$url = (!$has_prefix ? "http://" : "") . $url;

		// Require full internal url
		if ( $allowed_redirecturls == 'internal_full' )
			return parse_url($url, PHP_URL_HOST) == parse_url(JURI::base(), PHP_URL_HOST);

		// Require baseonly internal url
		else //if ( $allowed_redirecturls == 'internal_base' )
			return flexicontent_html::get_basedomain($url) == flexicontent_html::get_basedomain(JURI::base());

		// Allow any URL, (external too) this may be considered a vulnerability for unlogged/logged users, since
		// users may be redirected to an offsite URL despite clicking an internal site URL received e.g. by an email
		//else
		//	return true;
	}


	/**
	 * Function to render the item view of a given item id
	 *
	 * @param 	int 		$item_id
	 * @return 	string  : the HTML of the item view, also the CSS / JS file would have been loaded
	 * @since 1.5
	 */
	function renderItem($item_id, $view=FLEXI_ITEMVIEW) {
		require_once (JPATH_ADMINISTRATOR.DS.'components/com_flexicontent/defineconstants.php');
		JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
		require_once("components/com_flexicontent/classes/flexicontent.fields.php");
		//require_once("components/com_flexicontent/classes/flexicontent.helper.php");
		require_once("components/com_flexicontent/models/".FLEXI_ITEMVIEW.".php");

		$app  = JFactory::getApplication();
		$user = JFactory::getUser();

		$itemmodel = FLEXI_J16GE ? new FlexicontentModelItem() : new FlexicontentModelItems();
		$item = $itemmodel->getItem($item_id, $check_view_access=false);

		$aid = FLEXI_J16GE ? $user->getAuthorisedViewLevels() : (int) $user->get('aid');
		list($item) = FlexicontentFields::getFields($item, $view, $item->parameters, $aid);

		$ilayout = $item->parameters->get('ilayout', '');
		if ($ilayout==='') {
			$type = JTable::getInstance('flexicontent_types', '');
			$type->id = $item->type_id;
			$type->load();
			$type->params = FLEXI_J16GE ? new JRegistry($type->attribs) : new JParameter($type->attribs);
			$ilayout = $type->params->get('ilayout', 'default');
		}

		$this->item = & $item;
		$this->params_saved = @$this->params;
		$this->params = & $item->parameters;
		$this->tmpl = '.item.'.$ilayout;
		$this->print_link = JRoute::_('index.php?view=items&id='.$item->slug.'&pop=1&tmpl=component');
		$this->pageclass_sfx = '';
		$this->item->event->beforeDisplayContent = '';
		$this->item->event->afterDisplayTitle = '';
		$this->item->event->afterDisplayContent = '';
		$this->fields = & $this->item->fields;

		// start capturing output into a buffer
		ob_start();
		// Include the requested template filename in the local scope (this will execute the view logic).
		if ( file_exists(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'html'.DS.'com_flexicontent'.DS.'templates'.DS.$ilayout) )
			include JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'html'.DS.'com_flexicontent'.DS.'templates'.DS.$ilayout.DS.'item.php';
		else if (file_exists(JPATH_COMPONENT.DS.'templates'.DS.$ilayout))
			include JPATH_COMPONENT.DS.'templates'.DS.$ilayout.DS.'item.php';
		else
			include JPATH_COMPONENT.DS.'templates'.DS.'default'.DS.'item.php';

		// done with the requested template; get the buffer and clear it.
		$item_html = ob_get_contents();
		ob_end_clean();
		$this->params = $this->params_saved;

		return $item_html;
	}


	static function limit_selector(&$params, $formname='adminForm', $autosubmit=1)
	{
		if ( !$params->get('limit_override') ) return '';

		$app	= JFactory::getApplication();
		//$orderby = $app->getUserStateFromRequest( $option.'.category'.$category->id.'.filter_order_Dir', 'filter_order', 'i.title', 'string' );
		$limit = $app->getUserStateFromRequest( 'limit', 'limit', $params->get('limit'), 'string' );

		$class    = ' class="inputbox fc_field_filter" ';
		$onchange = !$autosubmit ? '' : ' onchange="adminFormPrepare(document.getElementById(\''.$formname.'\')); document.getElementById(\''.$formname.'\').submit();" ';
		$attribs  = $class . $onchange;

		$limit_options = $params->get('limit_options', '5,10,20,30,50,100,150,200');
		$limit_options = preg_split("/[\s]*,[\s]*/", $limit_options);

		$limiting = array();
		$limiting[] = JHTML::_('select.option', '', JText::_('Default'));
		foreach($limit_options as $limit_option) {
			$limiting[] = JHTML::_('select.option', $limit_option, $limit_option);
		}

		return JHTML::_('select.genericlist', $limiting, 'limit', $attribs, 'value', 'text', $limit );
	}

	static function ordery_selector(&$params, $formname='adminForm', $autosubmit=1, $extra_order_types=array())
	{
		if ( !$params->get('orderby_override') ) return '';

		$app	= JFactory::getApplication();
		//$orderby = $app->getUserStateFromRequest( $option.'.category'.$category->id.'.filter_order_Dir', 'filter_order', 'i.title', 'string' );
		$orderby = $app->getUserStateFromRequest( 'orderby', 'orderby', $params->get('orderby'), 'string' );

		$class    = ' class="inputbox fc_field_filter" ';
		$onchange = !$autosubmit ? '' : ' onchange="adminFormPrepare(document.getElementById(\''.$formname.'\')); document.getElementById(\''.$formname.'\').submit();" ';
		$attribs  = $class . $onchange;

		$orderby_options = $params->get('orderby_options', array('_preconfigured_','date','rdate','modified','alpha','ralpha','author','rauthor','hits','rhits','id','rid','order'));
		$orderby_options = FLEXIUtilities::paramToArray($orderby_options);

		$orderby_names =array('_preconfigured_'=>'FLEXI_ORDER_DEFAULT_INITIAL',
		'date'=>'FLEXI_ORDER_OLDEST_FIRST','rdate'=>'FLEXI_ORDER_MOST_RECENT_FIRST',
		'modified'=>'FLEXI_ORDER_LAST_MODIFIED_FIRST',
		'alpha'=>'FLEXI_ORDER_TITLE_ALPHABETICAL','ralpha'=>'FLEXI_ORDER_TITLE_ALPHABETICAL_REVERSE',
		'author'=>'FLEXI_ORDER_AUTHOR_ALPHABETICAL','rauthor'=>'FLEXI_ORDER_AUTHOR_ALPHABETICAL_REVERSE',
		'hits'=>'FLEXI_ORDER_MOST_HITS','rhits'=>'FLEXI_ORDER_LEAST_HITS',
		'id'=>'FLEXI_ORDER_HIGHEST_ITEM_ID','rid'=>'FLEXI_ORDER_LOWEST_ITEM_ID',
		'order'=>'FLEXI_ORDER_CONFIGURED_ORDER');

		$ordering = array();
		foreach ($extra_order_types as $value => $text) {
			$text = JText::_( $text );
			$ordering[] = JHTML::_('select.option',  $value,  $text);
		}
		foreach ($orderby_options as $orderby_option) {
			if ($orderby_option=='__SAVED__') continue;
			$value = ($orderby_option!='_preconfigured_') ? $orderby_option : '';
			$text = JText::_( $orderby_names[$orderby_option] );
			$ordering[] = JHTML::_('select.option',  $value,  $text);
		}

		return JHTML::_('select.genericlist', $ordering, 'orderby', $attribs, 'value', 'text', $orderby );
	}
	
	
	/**
	 * Utility function to add JQuery to current Document
	 *
	 * @param 	string 		$text
	 * @param 	int 		$nb
	 * @return 	string
	 * @since 1.5
	 */
	static function loadJQuery( $add_jquery = 1, $add_jquery_ui = 1, $add_jquery_ui_css = 1 )
	{
		static $jquery_added = false;
		static $jquery_ui_added = false;
		static $jquery_ui_css_added = false;

		if (FLEXI_J30GE) {
			if (!$jquery_added) JHtml::_('jquery.framework');
			$jquery_added = 1;
			return;
		}

		$document = JFactory::getDocument();
		if ( $add_jquery && !$jquery_added && !JPluginHelper::isEnabled('system', 'jquerysupport') )
		{
			$document->addScript(JURI::root().'components/com_flexicontent/librairies/jquery/js/jquery-'.FLEXI_JQUERY_VER.'.js');
			// The 'noConflict()' statement is inside the above jquery file, to make sure it executed immediately
			//$document->addCustomTag('<script>jQuery.noConflict();</script>');
			$jquery_added = 1;
		}
		if ( $add_jquery_ui && !$jquery_ui_added ) {
			$document->addScript(JURI::root().'components/com_flexicontent/librairies/jquery/js/jquery-ui-'.FLEXI_JQUERY_UI_VER.'.js');
			$jquery_ui_added = 1;
		}
		if ( $add_jquery_ui_css && !$jquery_ui_css_added ) {
			$document->addStyleSheet(JURI::root().'components/com_flexicontent/librairies/jquery/css/ui-lightness/jquery-ui-'.FLEXI_JQUERY_UI_CSS_VER.'.css');
			$jquery_ui_css_added = 1;
		}
	}
	
	
	/**
	 * Utility function to get the Mobile Detector Object
	 *
	 * @param 	string 		$text
	 * @param 	int 		$nb
	 * @return 	string
	 * @since 1.5
	 */
	static function getMobileDetector()
	{
		static $mobileDetector = null;
		
		if ( $mobileDetector===null ) {
			require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'mobiledetect'.DS.'Mobile_Detect.php');
			$mobileDetector = new Mobile_Detect_FC();
		}
		
		return $mobileDetector;
	}
	
	
	/**
	 * Utility function to load each JS Frameworks once
	 *
	 * @param 	string 		$text
	 * @param 	int 		$nb
	 * @return 	string
	 * @since 1.5
	 */
	static function loadFramework( $framework )
	{
		// Detect already loaded framework
		static $_loaded = array();
		if ( isset($_loaded[$framework]) ) return $_loaded[$framework];
		$_loaded[$framework] = false;
		
		// Get frameworks that are configured to be loaded manually in frontend (e.g. via the Joomla template)
		$app = JFactory::getApplication();
		static $load_frameworks = null;
		static $load_jquery = null;
		if ( !isset($load_frameworks[$framework]) ) {
			$flexiparams = JComponentHelper::getParams('com_flexicontent');
			//$load_frameworks = $flexiparams->get('load_frameworks', array('jQuery','image-picker','masonry','select2','inputmask','prettyCheckable','fancybox'));
			//$load_frameworks = FLEXIUtilities::paramToArray($load_frameworks);
			//$load_frameworks = array_flip($load_frameworks);
			//$load_jquery = isset($load_frameworks['jQuery']) || !$app->isSite();
			if ( $load_jquery===null ) $load_jquery = $flexiparams->get('loadfw_jquery', 1)==1  ||  !$app->isSite();
			$load_framework = $flexiparams->get( 'loadfw_'.strtolower(str_replace('-','_',$framework)), 1 );
			$load_frameworks[$framework] = $load_framework==1  ||  ($load_framework==2 && !$app->isSite());
		}
		
		// Set loaded flag
		$_loaded[$framework] = $load_frameworks[$framework];
		// Do not progress further if it is disabled
		if ( !$load_frameworks[$framework] ) return false;
		
		// Load Framework
		$document = JFactory::getDocument();
		$js = "";
		$css = "";
		switch ( $framework )
		{
			case 'jQuery':
				if ($load_jquery) flexicontent_html::loadJQuery();
				break;
			case 'image-picker':
				if ($load_jquery) flexicontent_html::loadJQuery();
				$document->addScript( JURI::root().'components/com_flexicontent/librairies/image-picker/image-picker.min.js' );
				$document->addStyleSheet(JURI::root().'components/com_flexicontent/librairies/image-picker/image-picker.css');
				break;
			
			case 'masonry':
				if ($load_jquery) flexicontent_html::loadJQuery();
				$document->addScript( JURI::root().'components/com_flexicontent/librairies/masonry/jquery.masonry.min.js' );
				break;
			
			case 'select2':
				if ($load_jquery) flexicontent_html::loadJQuery();
				$document->addScript( JURI::root().'components/com_flexicontent/librairies/select2/select2.js' );
				$document->addStyleSheet(JURI::root().'components/com_flexicontent/librairies/select2/select2.css');
				
				// Attach select2 to specific to select elements having specific CSS class
				$js .= "
					jQuery(document).ready(function() {
						jQuery('select.use_select2_lib').select2();
					});
				";
				break;
			
			case 'inputmask':
				if ($load_jquery) flexicontent_html::loadJQuery();
				$document->addScript( JURI::root().'components/com_flexicontent/librairies/inputmask/jquery.inputmask.bundle.min.js' );
				
				// Extra inputmask declarations definitions, e.g. ...
				/*$js .= "
					jQuery.extend(jQuery.inputmask.defaults.definitions, {
					    'f': {
					        \"validator\": \"[0-9\(\)\.\+/ ]\",
					        \"cardinality\": 1,
					        'prevalidator': null
					    }
					});
				";*/
				
				// Attach inputmask to all input fields that have appropriate tag parameters
				$js .= "
					jQuery(document).ready(function(){
					    jQuery(\":input\").inputmask();
					});
				";
				break;
			
			case 'prettyCheckable':
				if ($load_jquery) flexicontent_html::loadJQuery();
				$document->addScript( JURI::root().'components/com_flexicontent/librairies/prettyCheckable/prettyCheckable.js' );
				$document->addStyleSheet(JURI::root().'components/com_flexicontent/librairies/prettyCheckable/prettyCheckable.css');
				$js .= "
					jQuery(document).ready(function(){
						jQuery('input.use_prettycheckable').prettyCheckable();
						jQuery('div.fcradiocheckimage').each(
							function() {
								jQuery(this).find('label').append(jQuery(this).next('label').html());
								jQuery(this).next('label').remove();
							});
					});
				";
				break;
			
			case 'fancybox':
				if ($load_jquery) flexicontent_html::loadJQuery();
				// Add mousewheel plugin (this is optional)
				$document->addScript(JURI::root().'components/com_flexicontent/librairies/fancybox/lib/jquery.mousewheel-3.0.6.pack.js');
				
				// Add fancyBox CSS / JS
				$document->addStyleSheet(JURI::root().'components/com_flexicontent/librairies/fancybox/source/jquery.fancybox.css?v=2.1.1');
				$document->addScript(JURI::root().'components/com_flexicontent/librairies/fancybox/source/jquery.fancybox.pack.js?v=2.1.1');
				
				// Optionally add helpers - button, thumbnail and/or media
				$document->addStyleSheet(JURI::root().'components/com_flexicontent/librairies/fancybox/source/helpers/jquery.fancybox-buttons.css?v=1.0.4');
				$document->addScript(JURI::root().'components/com_flexicontent/librairies/fancybox/source/helpers/jquery.fancybox-buttons.js?v=1.0.4');
				$document->addScript(JURI::root().'components/com_flexicontent/librairies/fancybox/source/helpers/jquery.fancybox-media.js?v=1.0.4');
				$document->addStyleSheet(JURI::root().'components/com_flexicontent/librairies/fancybox/source/helpers/jquery.fancybox-thumbs.css?v=1.0.7');
				$document->addScript(JURI::root().'components/com_flexicontent/librairies/fancybox/source/helpers/jquery.fancybox-thumbs.js?v=1.0.7');
				
				// Attach fancybox to all elements having a specific CSS class
				$js .= "
					jQuery(document).ready(function(){
						jQuery('.fancybox').fancybox();
					});
				";
				break;
			
			case 'galleriffic':
				if ($load_jquery) flexicontent_html::loadJQuery();
				
				// Add galleriffic CSS / JS
				$document->addStyleSheet(JURI::root().'components/com_flexicontent/librairies/galleriffic/css/basic.css');
				$document->addStyleSheet(JURI::root().'components/com_flexicontent/librairies/galleriffic/css/galleriffic-3.css');
				$document->addScript(JURI::root().'components/com_flexicontent/librairies/galleriffic/js/jquery.galleriffic.js');
				$document->addScript(JURI::root().'components/com_flexicontent/librairies/galleriffic/js/jquery.opacityrollover.js');
				
				//$view_width = 500;
				$js = "
				//document.write('<style>.noscript { display: none; }</style>');
				jQuery(document).ready(function() {
					// We only want these styles applied when javascript is enabled
					jQuery('div.navigation').css({'width' : '150px', 'float' : 'left'});
					jQuery('div.content').css({'display' : 'inline-block', 'float' : 'none'});
	
					// Initially set opacity on thumbs and add
					// additional styling for hover effect on thumbs
					var onMouseOutOpacity = 0.67;
					jQuery('#gf_thumbs ul.thumbs li').opacityrollover({
						mouseOutOpacity:   onMouseOutOpacity,
						mouseOverOpacity:  1.0,
						fadeSpeed:         'fast',
						exemptionSelector: '.selected'
					});
					
					// Initialize Advanced Galleriffic Gallery
					var gallery = jQuery('#gf_thumbs').galleriffic({
						delay:                     2500,
						numThumbs:                 4,
						preloadAhead:              10,
						enableTopPager:            true,
						enableBottomPager:         true,
						maxPagesToShow:            20,
						imageContainerSel:         '#gf_slideshow',
						controlsContainerSel:      '#gf_controls',
						captionContainerSel:       '#gf_caption',
						loadingContainerSel:       '#gf_loading',
						renderSSControls:          true,
						renderNavControls:         true,
						playLinkText:              'Play Slideshow',
						pauseLinkText:             'Pause Slideshow',
						prevLinkText:              '&lsaquo; Previous Photo',
						nextLinkText:              'Next Photo &rsaquo;',
						nextPageLinkText:          'Next &rsaquo;',
						prevPageLinkText:          '&lsaquo; Prev',
						enableHistory:             false,
						autoStart:                 false,
						syncTransitions:           true,
						defaultTransitionDuration: 900,
						onSlideChange:             function(prevIndex, nextIndex) {
							// 'this' refers to the gallery, which is an extension of jQuery('#gf_thumbs')
							this.find('ul.thumbs').children()
								.eq(prevIndex).fadeTo('fast', onMouseOutOpacity).end()
								.eq(nextIndex).fadeTo('fast', 1.0);
						},
						onPageTransitionOut:       function(callback) {
							this.fadeTo('fast', 0.0, callback);
						},
						onPageTransitionIn:        function() {
							this.fadeTo('fast', 1.0);
						}
					});
				});
				";
				break;
			
			case 'elastislide':
				if ($load_jquery) flexicontent_html::loadJQuery();
				
				$document->addStyleSheet(JURI::root().'components/com_flexicontent/librairies/elastislide/css/demo.css');
				$document->addStyleSheet(JURI::root().'components/com_flexicontent/librairies/elastislide/css/style.css');
				$document->addStyleSheet(JURI::root().'components/com_flexicontent/librairies/elastislide/css/elastislide.css');
				
				$document->addScript(JURI::root().'components/com_flexicontent/librairies/elastislide/js/jquery.tmpl.min.js');
				$document->addScript(JURI::root().'components/com_flexicontent/librairies/elastislide/js/jquery.easing.1.3.js');
				$document->addScript(JURI::root().'components/com_flexicontent/librairies/elastislide/js/jquery.elastislide.js');
				//$document->addScript(JURI::root().'components/com_flexicontent/librairies/elastislide/js/gallery.js'); // replace with field specific: gallery_tmpl.js
				break;
			
			case 'photoswipe':
				if ($load_jquery) flexicontent_html::loadJQuery();
				
				// Add swipe CSS / JS
				$document->addStyleSheet(JURI::root().'components/com_flexicontent/librairies/photoswipe/photoswipe.css');
				$document->addScript(JURI::root().'components/com_flexicontent/librairies/photoswipe/lib/klass.min.js');
				$document->addScript(JURI::root().'components/com_flexicontent/librairies/photoswipe/code.photoswipe.jq.min.js');
				$js = "
				jQuery(document).ready(function() {
					var myPhotoSwipe = jQuery('.photoswipe_fccontainer a').photoSwipe({ enableMouseWheel: false , enableKeyboard: false }); 
				}); 
				";
				break;
			
			case 'noobSlide':
				// Make sure mootools are loaded
				FLEXI_J30GE ? JHtml::_('behavior.framework') : JHTML::_('behavior.mootools');
				
				// Add swipe CSS / JS
				//$document->addScript(JURI::root().'components/com_flexicontent/librairies/noobSlide/_class.noobSlide.js');
				$document->addScript(JURI::root().'components/com_flexicontent/librairies/noobSlide/_class.noobSlide.packed.js');
				//$document->addStyleSheet(JURI::root().'components/com_flexicontent/librairies/noobSlide/_web.css');
				//$document->addStyleSheet(JURI::root().'components/com_flexicontent/librairies/noobSlide/style.css');
				
				//$js = "";
				break;
			
			default:
				JFactory::getApplication()->enqueueMessage(__FUNCTION__.' Cannot load unknown Framework: '.$framework, 'error');
				break;
		}
		
		// Add custom JS & CSS code
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);
		return $_loaded[$framework];
	}
	
	
	/**
	 * Escape a string so that it can be used directly by JS source code
	 *
	 * @param 	string 		$text
	 * @param 	int 		$nb
	 * @return 	string
	 * @since 1.5
	 */
	static function escapeJsText($string)
	{
		$string = (string)$string;
		$string = str_replace("\r", '', $string);
		$string = addcslashes($string, "\0..\37'\\");
		$string = str_replace('"', '\"', $string);
		$string = str_replace("'", "\'", $string);
		$string = str_replace("\n", ' ', $string);
		return $string;
	}

	/**
	 * Trims whitespace from an array of strings
	 *
	 * @param 	string array			$arr_str
	 * @return 	string array
	 * @since 1.5
	 */
	static function arrayTrim($arr_str) {
		if(!is_array($arr_str)) return false;
		foreach($arr_str as $k=>$a) {
			$arr_str[$k] = trim($a);
		}
		return $arr_str;
	}

	/**
	 * Strip html tags and cut after x characters
	 *
	 * @param 	string 		$text
	 * @param 	int 		$nb
	 * @return 	string
	 * @since 1.5
	 */
	static function striptagsandcut( $text, $chars=null )
	{
		// Convert entiies to characters so that they will not be removed ... by strip_tags
		$text = html_entity_decode ($text, ENT_NOQUOTES, 'UTF-8');
		
		// Strip SCRIPT tags AND their containing code
		$text = preg_replace( '#<script\b[^>]*>(.*?)<\/script>#is', '', $text );
		
		// Add whitespaces at start/end of tags so that words will not be joined
		$text = preg_replace('/(<\/[^>]+>)|(<[^>\/][^>]*>)/', ' $1', $text);
		
		// Strip html tags
		$cleantext = strip_tags($text);

		// clean additionnal plugin tags
		$patterns = array();
		$patterns[] = '#\[(.*?)\]#';
		$patterns[] = '#{(.*?)}#';
		$patterns[] = '#&(.*?);#';
		
		foreach ($patterns as $pattern) {
			$cleantext = preg_replace( $pattern, '', $cleantext );
		}
		
		// Replace multiple spaces, tabs, newlines, etc with a SINGLE whitespace so that text length will be calculated correctly
		$cleantext = preg_replace('/[\p{Z}\s]{2,}/u', ' ', $cleantext);  // Unicode safe whitespace replacing
		
		// Calculate length according to UTF-8 encoding
		$length = JString::strlen(htmlspecialchars( $cleantext ));

		// cut the text if required
		if ($chars) {
			if ($length > $chars) {
				$cleantext = JString::substr( htmlspecialchars($cleantext, ENT_QUOTES, 'UTF-8'), 0, $chars ).'...';
			}
		}

		return $cleantext;
	}

	/**
	 * Make image tag from field or extract image from introtext
	 *
	 * @param 	array 		$row
	 * @return 	string
	 * @since 1.5
	 */
	static function extractimagesrc( $row )
	{
		jimport('joomla.filesystem.file');

		$regex = '#<\s*img [^\>]*src\s*=\s*(["\'])(.*?)\1#im';

		preg_match ($regex, $row->introtext, $matches);

		if(!count($matches)) preg_match ($regex, $row->fulltext, $matches);

		$images = (count($matches)) ? $matches : array();

		$image = '';
		if (count($images)) $image = $images[2];

		if (!preg_match("#^http|^https|^ftp#i", $image)) {
			// local file check that it exists
			$image = JFile::exists( JPATH_SITE . DS . $image ) ? $image : '';
		}

		return $image;
	}


	/**
	 * Logic to change the state of an item
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	static function setitemstate( $controller_obj )
	{
		$id = JRequest::getInt( 'id', 0 );
		JRequest::setVar( 'cid', $id );

		$app = JFactory::getApplication();
		$modelname = $app->isAdmin() ? 'item' : FLEXI_ITEMVIEW;
		$model = $controller_obj->getModel( $modelname );
		$user = JFactory::getUser();
		$state = JRequest::getVar( 'state', 0 );

		// Get owner and other item data
		$db = JFactory::getDBO();
		$q = "SELECT id, created_by, catid FROM #__content WHERE id =".$id;
		$db->setQuery($q);
		$item = $db->loadObject();

		// Determine priveleges of the current user on the given item
		if (FLEXI_J16GE) {
			$asset = 'com_content.article.' . $item->id;
			$has_edit_state = $user->authorise('core.edit.state', $asset) || ($user->authorise('core.edit.state.own', $asset) && $item->created_by == $user->get('id'));
			$has_delete     = $user->authorise('core.delete', $asset) || ($user->authorise('core.delete.own', $asset) && $item->created_by == $user->get('id'));
			// ...
			$permission = FlexicontentHelperPerm::getPerm();
			$has_archive    = $permission->CanArchives;
		} else if ($user->gid >= 25) {
			$has_edit_state = true;
			$has_delete     = true;
			$has_archive    = true;
		} else if (FLEXI_ACCESS) {
			$rights 	= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $item->id, $item->catid);
			$has_edit_state = in_array('publish', $rights) || (in_array('publishown', $rights) && $item->created_by == $user->get('id')) ;
			$has_delete     = in_array('delete', $rights) || (in_array('deleteown', $rights) && $item->created_by == $user->get('id')) ;
			$has_archive    = FAccess::checkComponentAccess('com_flexicontent', 'archives', 'users', $user->gmid);
		} else {
			$has_edit_state = $user->authorize('com_content', 'publish', 'content', 'all');
			$has_delete     = $user->gid >= 23; // is at least manager
			$has_archive    = $user->gid >= 23; // is at least manager
		}

		$has_edit_state = $has_edit_state && in_array($state, array(0,1,-3,-4,-5));
		$has_delete     = $has_delete     && $state == -2;
		$has_archive    = $has_archive    && $state == (FLEXI_J16GE ? 2:-1);

		// check if user can edit.state of the item
		$access_msg = '';
		if ( !$has_edit_state && !$has_delete && !$has_archive )
		{
			//echo JText::_( 'FLEXI_NO_ACCESS_CHANGE_STATE' );
			echo JText::_( 'FLEXI_DENIED' );   // must a few words
			return;
		}
		else if(!$model->setitemstate($id, $state))
		{
			$msg = JText::_('FLEXI_ERROR_SETTING_THE_ITEM_STATE');
			echo $msg . ": " .$model->getError();
			return;
		}

		// Clean cache
		if (FLEXI_J16GE) {
			$cache = FLEXIUtilities::getCache();
			$cache->clean('com_flexicontent_items');
			$cache->clean('com_flexicontent_filters');
		} else {
			$itemcache = JFactory::getCache('com_flexicontent_items');
			$itemcache->clean();
			$filtercache = JFactory::getCache('com_flexicontent_filters');
			$filtercache->clean();
		}

		// Output new state icon and terminate
		$tmpparams = FLEXI_J16GE ? new JRegistry() : new JParameter("");
		$tmpparams->set('stateicon_popup', 'basic');
		$stateicon = flexicontent_html::stateicon( $state, $tmpparams );
		echo $stateicon;
		exit;
	}


	/**
	 * Creates the rss feed button
	 *
	 * @param string $print_link
	 * @param array $params
	 * @since 1.0
	 */
	static function feedbutton($view, &$params, $slug = null, $itemslug = null )
	{
		if ( $params->get('show_feed_icon', 1) && !JRequest::getCmd('print') ) {

			$uri    = JURI::getInstance();
			$base  	= $uri->toString( array('scheme', 'host', 'port'));

			//TODO: clean this static stuff (Probs when determining the url directly with subdomains)
			if($view == 'category') {
				$link 	= $base.JRoute::_( 'index.php?view='.$view.'&cid='.$slug.'&format=feed&type=rss', false );
			} elseif($view == FLEXI_ITEMVIEW) {
				$link 	= $base.JRoute::_( 'index.php?view='.$view.'&cid='.$slug.'&id='.$itemslug.'&format=feed&type=rss', false );
			} elseif($view == 'tags') {
				$link 	= $base.JRoute::_( 'index.php?view='.$view.'&id='.$slug.'&format=feed&type=rss', false );
			} else {
				$link 	= $base.JRoute::_( 'index.php?view='.$view.'&format=feed&type=rss', false );
			}
			// Fix for J1.7+ format variable removed from URL and added as URL suffix
			if (!preg_match('/format\=feed/',$link)) {
				$link .= "&amp;format=feed";
			}

			$status = 'status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,width=800,height=600,directories=no,location=no';

			if ($params->get('show_icons')) 	{
				$image = FLEXI_J16GE ?
					JHTML::image( FLEXI_ICONPATH.'livemarks.png', JText::_( 'FLEXI_FEED' )) :
					JHTML::_('image.site', 'livemarks.png', FLEXI_ICONPATH, NULL, NULL, JText::_( 'FLEXI_FEED' )) ;
			} else {
				$image = '&nbsp;'.JText::_( 'FLEXI_FEED' );
			}

			$overlib = JText::_( 'FLEXI_FEED_TIP' );
			$text = JText::_( 'FLEXI_FEED' );

			$output	= '<a href="'. $link .'" class="editlinktip hasTip" onclick="window.open(this.href,\'win2\',\''.$status.'\'); return false;" title="'.$text.'::'.$overlib.'">'.$image.'</a>';

			return $output;
		}
		return;
	}

	/**
	 * Creates the print button
	 *
	 * @param string $print_link
	 * @param array $params
	 * @since 1.0
	 */
	static function printbutton( $print_link, &$params )
	{
		if ( $params->get('show_print_icon') || JRequest::getCmd('print') ) {

			$status = 'status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,width=640,height=480,directories=no,location=no';

			// checks template image directory for image, if non found default are loaded
			if ( $params->get( 'show_icons' ) ) {
				$image = FLEXI_J16GE ?
					JHTML::image( FLEXI_ICONPATH.'printButton.png', JText::_( 'FLEXI_PRINT' )) :
					JHTML::_('image.site', 'printButton.png', FLEXI_ICONPATH, NULL, NULL, JText::_( 'FLEXI_PRINT' )) ;
			} else {
				$image = JText::_( 'FLEXI_ICON_SEP' ) .'&nbsp;'. JText::_( 'FLEXI_PRINT' ) .'&nbsp;'. JText::_( 'FLEXI_ICON_SEP' );
			}

			if (JRequest::getInt('pop')) {
				//button in popup
				$output = '<a href="javascript:;" onclick="window.print();return false;">'.$image.'</a>';
			} else {
				//button in view
				$overlib = JText::_( 'FLEXI_PRINT_TIP' );
				$text = JText::_( 'FLEXI_PRINT' );

				$output	= '<a href="'. JRoute::_($print_link) .'" class="editlinktip hasTip" onclick="window.open(this.href,\'win2\',\''.$status.'\'); return false;" title="'.$text.'::'.$overlib.'">'.$image.'</a>';
			}

			return $output;
		}
		return;
	}

	/**
	 * Creates the email button
	 *
	 * @param string $print_link
	 * @param array $params
	 * @since 1.0
	 */
	static function mailbutton($view, &$params, $slug = null, $itemslug = null )
	{
		static $initialize = null;
		static $uri, $base;

		if ( !$params->get('show_email_icon') || JRequest::getCmd('print') ) return;

		if ($initialize === null) {
			if (file_exists ( JPATH_SITE.DS.'components'.DS.'com_mailto'.DS.'helpers'.DS.'mailto.php' )) {
				require_once(JPATH_SITE.DS.'components'.DS.'com_mailto'.DS.'helpers'.DS.'mailto.php');
				$uri  = JURI::getInstance();
				$base = $uri->toString( array('scheme', 'host', 'port'));
				$initialize = true;
			} else {
				$initialize = false;
			}
		}
		if ( $initialize === false ) return;

		//TODO: clean this static stuff (Probs when determining the url directly with subdomains)
		if($view == 'category') {
			$link = $base . JRoute::_(FlexicontentHelperRoute::getCategoryRoute($slug));
			//$link = $base . JRoute::_( 'index.php?view='.$view.'&cid='.$slug, false );
		} elseif($view == FLEXI_ITEMVIEW) {
			$link = $base . JRoute::_(FlexicontentHelperRoute::getItemRoute($itemslug, $slug));
			//$link = $base . JRoute::_( 'index.php?view='.$view.'&cid='.$slug.'&id='.$itemslug, false );
		} elseif($view == 'tags') {
			$link = $base . JRoute::_(FlexicontentHelperRoute::getTagRoute($itemslug));
			//$link = $base . JRoute::_( 'index.php?view='.$view.'&id='.$slug, false );
		} else {
			$link 	= $base . JRoute::_( 'index.php?view='.$view, false );
		}

		$url 	= 'index.php?option=com_mailto&tmpl=component&link='.MailToHelper::addLink($link);

		$status = 'width=400,height=300,menubar=yes,resizable=yes';

		if ($params->get('show_icons')) 	{
			$image = FLEXI_J16GE ?
				JHTML::image( FLEXI_ICONPATH.'emailButton.png', JText::_( 'FLEXI_EMAIL' )) :
				JHTML::_('image.site', 'emailButton.png', FLEXI_ICONPATH, NULL, NULL, JText::_( 'FLEXI_EMAIL' )) ;
		} else {
			$image = '&nbsp;'.JText::_( 'FLEXI_EMAIL' );
		}

		$overlib = JText::_( 'FLEXI_EMAIL_TIP' );
		$text = JText::_( 'FLEXI_EMAIL' );

		$output	= '<a href="'. JRoute::_($url) .'" class="editlinktip hasTip" onclick="window.open(this.href,\'win2\',\''.$status.'\'); return false;" title="'.$text.'::'.$overlib.'">'.$image.'</a>';

		return $output;
	}

	/**
	 * Creates the pdf button
	 *
	 * @param int $id
	 * @param array $params
	 * @since 1.0
	 */
	static function pdfbutton( $item, &$params)
	{
		if ( !FLEXI_J16GE && $params->get('show_pdf_icon') && !JRequest::getCmd('print') ) {

			if ( $params->get('show_icons') ) {
				$image = FLEXI_J16GE ?
					JHTML::image( FLEXI_ICONPATH.'pdf_button.png', JText::_( 'FLEXI_CREATE_PDF' )) :
					JHTML::_('image.site', 'pdf_button.png', FLEXI_ICONPATH, NULL, NULL, JText::_( 'FLEXI_CREATE_PDF' ));
			} else {
				$image = JText::_( 'FLEXI_ICON_SEP' ) .'&nbsp;'. JText::_( 'FLEXI_CREATE_PDF' ) .'&nbsp;'. JText::_( 'FLEXI_ICON_SEP' );
			}
			$overlib = JText::_( 'FLEXI_CREATE_PDF_TIP' );
			$text = JText::_( 'FLEXI_CREATE_PDF' );

			$link 	= 'index.php?view='.FLEXI_ITEMVIEW.'&cid='.$item->categoryslug.'&id='.$item->slug.'&format=pdf';
			$output	= '<a href="'.JRoute::_($link).'" class="editlinktip hasTip" title="'.$text.'::'.$overlib.'">'.$image.'</a>';

			return $output;
		}
		return;
	}


	/**
	 * Creates the state selector button
	 *
	 * @param int $id
	 * @param array $params
	 * @since 1.0
	 */
	static function statebutton( $item, &$params, $addToggler=true )
	{
		$user = JFactory::getUser();
		$db   = JFactory::getDBO();
		$config   = JFactory::getConfig();
		$document = JFactory::getDocument();
		$nullDate = $db->getNullDate();
		$app = JFactory::getApplication();

		// Determine priveleges of the current user on the given item
		if (FLEXI_J16GE) {
			$asset = 'com_content.article.' . $item->id;
			$has_edit_state = $user->authorise('core.edit.state', $asset) || ($user->authorise('core.edit.state.own', $asset) && $item->created_by == $user->get('id'));
			$has_delete     = $user->authorise('core.delete', $asset) || ($user->authorise('core.delete.own', $asset) && $item->created_by == $user->get('id'));
			// ...
			$permission = FlexicontentHelperPerm::getPerm();
			$has_archive    = $permission->CanArchives;
		} else if ($user->gid >= 25) {
			$has_edit_state = true;
			$has_delete     = true;
			$has_archive    = true;
		} else if (FLEXI_ACCESS) {
			$rights 	= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $item->id, $item->catid);
			$has_edit_state = in_array('publish', $rights) || (in_array('publishown', $rights) && $item->created_by == $user->get('id')) ;
			$has_delete     = in_array('delete', $rights) || (in_array('deleteown', $rights) && $item->created_by == $user->get('id')) ;
			$has_archive    = FAccess::checkComponentAccess('com_flexicontent', 'archives', 'users', $user->gmid);
		} else {
			$has_edit_state = $user->authorize('com_content', 'publish', 'content', 'all');
			$has_delete     = $user->gid >= 23; // is at least manager
			$has_archive    = $user->gid >= 23; // is at least manager
		}
		$canChangeState = $has_edit_state || $has_delete || $has_archive;

		static $js_and_css_added = false;

	 	if (!$js_and_css_added && $canChangeState && $addToggler )
	 	{
			$document->addScript( JURI::base().'components/com_flexicontent/assets/js/stateselector.js' );
	 		$js ='
				if(MooTools.version>="1.2.4") {
					window.addEvent("domready", function() {stateselector.init()});
				}else{
					window.onDomReady(stateselector.init.bind(stateselector));
				}
				function dostate(state, id)
				{
					var change = new processstate();
					change.dostate( state, id );
				}';
			$document->addScriptDeclaration($js);
			$js_and_css_added = true;
	 	}


		// Create state icon
		$state = $item->state;
		$state_text ='';
		$tmpparams = FLEXI_J16GE ? new JRegistry() : new JParameter("");
		$tmpparams->set('stateicon_popup', 'none');
		$stateicon = flexicontent_html::stateicon( $state, $tmpparams, $state_text );


		$tz_string = JFactory::getApplication()->getCfg('offset');
		if (FLEXI_J16GE) {
			$tz = new DateTimeZone( $tz_string );
			$tz_offset = $tz->getOffset(new JDate()) / 3600;
		} else {
			$tz_offset = $tz_string;
		}

	 	// Calculate common variables used to produce output
		$publish_up = JFactory::getDate($item->publish_up);
		$publish_down = JFactory::getDate($item->publish_down);
		if (FLEXI_J16GE) {
			$publish_up->setTimezone($tz);
			$publish_down->setTimezone($tz);
		} else {
			$publish_up->setOffset($tz_offset);
			$publish_down->setOffset($tz_offset);
		}

		$img_path = JURI::root()."/components/com_flexicontent/assets/images/";


		// Create publish information
		$publish_info = '';
		if (isset($item->publish_up)) {
			if ($item->publish_up == $nullDate) {
				$publish_info .= JText::_( 'FLEXI_START_ALWAYS' );
			} else {
				$publish_info .= JText::_( 'FLEXI_START' ) .": ". JHTML::_('date', FLEXI_J16GE ? $publish_up->toSql() : $publish_up->toMySQL(), FLEXI_J16GE ? 'Y-m-d H:i:s' : '%Y-%m-%d %H:%M:%S');
			}
		}
		if (isset($item->publish_down)) {
			if ($item->publish_down == $nullDate) {
				$publish_info .= "<br />". JText::_( 'FLEXI_FINISH_NO_EXPIRY' );
			} else {
				$publish_info .= "<br />". JText::_( 'FLEXI_FINISH' ) .": ". JHTML::_('date', FLEXI_J16GE ? $publish_down->toSql() : $publish_down->toMySQL(), FLEXI_J16GE ? 'Y-m-d H:i:s' : '%Y-%m-%d %H:%M:%S');
			}
		}
		$publish_info = $state_text.'<br /><br />'.$publish_info;


		// Create the state selector button and return it
		if ( $canChangeState && $addToggler )
		{
			$separators_at = array(-5,-4);
			// Only add user's permitted states on the current item
			if ($has_edit_state) $state_ids   = array(1, -5, 0, -3, -4);
			if ($has_archive)    $state_ids[] = FLEXI_J16GE ? 2:-1;
			if ($has_delete)     $state_ids[]  = -2;

			$state_names = array(1=>'FLEXI_PUBLISHED', -5=>'FLEXI_IN_PROGRESS', 0=>'FLEXI_UNPUBLISHED', -3=>'FLEXI_PENDING', -4=>'FLEXI_TO_WRITE', (FLEXI_J16GE ? 2:-1)=>'FLEXI_ARCHIVED', -2=>'FLEXI_TRASHED');
			$state_descrs = array(1=>'FLEXI_PUBLISH_THIS_ITEM', -5=>'FLEXI_SET_ITEM_IN_PROGRESS', 0=>'FLEXI_UNPUBLISH_THIS_ITEM', -3=>'FLEXI_SET_ITEM_PENDING', -4=>'FLEXI_SET_ITEM_TO_WRITE', (FLEXI_J16GE ? 2:-1)=>'FLEXI_ARCHIVE_THIS_ITEM', -2=>'FLEXI_TRASH_THIS_ITEM');
			$state_imgs = array(1=>'tick.png', -5=>'publish_g.png', 0=>'publish_x.png', -3=>'publish_r.png', -4=>'publish_y.png', (FLEXI_J16GE ? 2:-1)=>'archive.png', -2=>'trash.png');

			$box_css = ''; //$app->isSite() ? 'width:182px; left:-100px;' : '';
			$publish_info .= '<br><br>'.JText::_('FLEXI_CLICK_TO_CHANGE_STATE');
			$output ='
			<ul class="statetoggler">
				<li class="topLevel">
					<a href="javascript:void(0);" class="opener" style="outline:none;">
					<div id="row'.$item->id.'">
						<span class="editlinktip hasTip" title="'.flexicontent_html::escape(JText::_('FLEXI_PUBLISH_INFORMATION')).'::'.flexicontent_html::escape($publish_info).'">
							'.$stateicon.'
						</span>
					</div>
					</a>
					<div class="options" style="'.$box_css.'">
						<ul>';

				foreach ($state_ids as $i => $state_id) {
					$spacer = in_array($state_id,$separators_at) ? '' : '';
					$output .='
							<li>
								<a href="javascript:void(0);" onclick="dostate(\''.$state_id.'\', \''.$item->id.'\')" class="closer hasTip" title="'.JText::_( 'FLEXI_ACTION' ).'::'.JText::_( $state_descrs[$state_id] ).'">
									<img src="'.$img_path.$state_imgs[$state_id].'" width="16" height="16" border="0" alt="'.JText::_( $state_names[$state_id] ).'" />
								</a>
							</li>';
				}
				$output .='
						</ul>
					</div>
				</li>
			</ul>';

		} else if ($app->isAdmin()) {
			if ($canChangeState) $publish_info .= '<br><br>'.JText::_('FLEXI_STATE_CHANGER_DISABLED');
			$output = '
				<div id="row'.$item->id.'">
					<span class="editlinktip hasTip" title="'.JText::_( 'FLEXI_PUBLISH_INFORMATION' ).'::'.$publish_info.'">
						'.$stateicon.'
					</span>
				</div>';
		} else {
			$output = '';  // frontend with no permissions to edit / delete / archive
		}

		return $output;
	}


	/**
	 * Creates the approval button
	 *
	 * @param int $id
	 * @param array $params
	 * @since 1.0
	 */
	static function approvalbutton( $item, &$params)
	{
		$user	= JFactory::getUser();

		// Skip not-owned items, and items not in draft state
		if ( $item->state != -4 || $item->created_by != $user->get('id') )  return;

		// Determine if current user can edit state of the given item
		if (FLEXI_J16GE) {
			$asset = 'com_content.article.' . $item->id;
			$has_edit_state = $user->authorise('core.edit.state', $asset) || ($user->authorise('core.edit.state.own', $asset) && $item->created_by == $user->get('id'));
			// ALTERNATIVE 1
			//$rights = FlexicontentHelperPerm::checkAllItemAccess($user->get('id'), 'item', $item->id);
			//$has_edit_state = in_array('edit.state', $rights) || (in_array('edit.state.own', $rights) && $item->created_by == $user->get('id')) ;
		} else if ($user->gid >= 25) {
			$has_edit_state = true;
		} else if (FLEXI_ACCESS) {
			$rights 	= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $item->id, $item->catid);
			$has_edit_state = in_array('publish', $rights) || (in_array('publishown', $rights) && $item->created_by == $user->get('id')) ;
		} else {
			$has_edit_state = $user->authorize('com_content', 'publish', 'content', 'all');
		}

		// Create the approval button if user cannot edit the item (**note check at top of this method)
		if ( !$has_edit_state ) {
			if ( $params->get('show_icons') ) {
				$attribs = ' style="margin:4px;" width="16" align="top" ';
				$image = FLEXI_J16GE ?
					JHTML::image( 'components/com_flexicontent/assets/images/'.'person2_f2.png', JText::_( 'FLEXI_APPROVAL_REQUEST' ), $attribs) :
					JHTML::_('image.site', 'person2_f2.png', 'components/com_flexicontent/assets/images/', NULL, NULL, JText::_( 'FLEXI_APPROVAL_REQUEST' ), $attribs) ;
			} else {
				$image = JText::_( 'FLEXI_ICON_SEP' ) .'&nbsp;'. JText::_( 'FLEXI_EDIT' ) .'&nbsp;'. JText::_( 'FLEXI_ICON_SEP' );
			}
			$overlib 	= JText::_( 'FLEXI_APPROVAL_REQUEST_INFO' );
			$text 		= JText::_( 'FLEXI_APPROVAL_REQUEST' );

			$link = 'index.php?option=com_flexicontent&task=approval&cid='.$item->id;
			$caption = JText::_( 'FLEXI_APPROVAL_REQUEST' );
			$output	= '<a style="float:right;	padding: 2px 2px 0px 0px !important;" href="'.$link.'" class="fc_bigbutton rc5  editlinktip hasTip" title="'.$text.'::'.$overlib.'">'.$image.$caption.'</a>';

			return $output;
		}

		return;
	}


	/**
	 * Creates the edit button
	 *
	 * @param int $id
	 * @param array $params
	 * @since 1.0
	 */
	static function editbutton( $item, &$params)
	{
		$user	= JFactory::getUser();

		// Determine if current user can edit the given item
		$has_edit = false;
		if (FLEXI_J16GE) {
			$asset = 'com_content.article.' . $item->id;
			$has_edit = $user->authorise('core.edit', $asset) || ($user->authorise('core.edit.own', $asset) && $item->created_by == $user->get('id'));
			// ALTERNATIVE 1
			//$rights = FlexicontentHelperPerm::checkAllItemAccess($user->get('id'), 'item', $item->id);
			//$has_edit = in_array('edit', $rights) || (in_array('edit.own', $rights) && $item->created_by == $user->get('id')) ;
		} else if ($user->gid >= 25) {
			$has_edit = true;
		} else if (FLEXI_ACCESS) {
			$rights 	= FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $item->id, $item->catid);
			$has_edit = in_array('edit', $rights) || (in_array('editown', $rights) && $item->created_by == $user->get('id')) ;
		} else {
			$has_edit = $user->authorize('com_content', 'edit', 'content', 'all') || ($user->authorize('com_content', 'edit', 'content', 'own') && $item->created_by == $user->get('id'));
		}

		// Create the edit button and return it
		if ($has_edit) {
			if ( $params->get('show_icons') ) {
				$image = FLEXI_J16GE ?
					JHTML::image( FLEXI_ICONPATH.'edit.png', JText::_( 'FLEXI_EDIT' )) :
					JHTML::_('image.site', 'edit.png', FLEXI_ICONPATH, NULL, NULL, JText::_( 'FLEXI_EDIT' )) ;
			} else {
				$image = JText::_( 'FLEXI_ICON_SEP' ) .'&nbsp;'. JText::_( 'FLEXI_EDIT' ) .'&nbsp;'. JText::_( 'FLEXI_ICON_SEP' );
			}
			$overlib 	= JText::_( 'FLEXI_EDIT_TIP' );
			$text 		= JText::_( 'FLEXI_EDIT' );

			$link = 'index.php?option=com_flexicontent&view='.FLEXI_ITEMVIEW.'&task=edit'.'&cid='.$item->categoryslug.'&id='.$item->slug; //.'&'.(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken()).'=1'.'&typeid='.$item->type_id
			$output	= '<a href="'.JRoute::_($link).'" class="editlinktip hasTip" title="'.$text.'::'.$overlib.'">'.$image.'</a>';

			return $output;
		}

		return;
	}

	/**
	 * Creates the add button
	 *
	 * @param array $params
	 * @since 1.0
	 */
	static function addbutton(&$params, &$maincat = null)
	{
		$user	= JFactory::getUser();
		if (!$user->id) return '';

		// Check if current view / layout can use ADD button
		$view = JRequest::getVar('view');
		$layout = JRequest::getVar('layout', 'default');
		if ( $view!='category' || !in_array($layout, array('default','mcats','myitems')) ) return '';

		// Check if user can ADD to current category or to any category
		if ($maincat && $layout == 'default') {
			$add_label = JText::_('FLEXI_ADD_NEW_CONTENT_TO_CURR_CAT');
			$permission = FlexicontentHelperPerm::getPerm();
			$canAdd = $permission->CanAdd;
		} else  {
			$add_label = JText::_('FLEXI_ADD_NEW_CONTENT_TO_LIST');
			$specific_catids = $maincat->id ? array( $maincat->id ) : $maincat->ids;
			if ( !empty($specific_catids ) ) {
				$allowedcats = FlexicontentHelperPerm::getAllowedCats( $user, $actions_allowed=array('core.create'), $require_all=true, $check_published = true, $specific_catids );
				$canAdd = count($allowedcats);
			} else {
				$canAdd = FlexicontentHelperPerm::getPerm()->CanAdd;
			}
		}
		if ( !$canAdd) return '';

		// Create the button
		if ( $params->get('show_icons') ) {
			$image = FLEXI_J16GE ?
				JHTML::image( 'components/com_flexicontent/assets/images/'.'add.png', JText::_( 'FLEXI_ADD_NEW_CONTENT' ), ' style="margin-right:4px;" width="16" align="top" ') :
				JHTML::_('image.site', 'add.png', 'components/com_flexicontent/assets/images/', NULL, NULL, JText::_( 'FLEXI_ADD_NEW_CONTENT' ), ' style="margin-right:4px;" width="16" align="top" ') ;
		} else {
			$image = JText::_( 'FLEXI_ICON_SEP' ) .'&nbsp;'. JText::_( 'FLEXI_ADD_NEW_CONTENT' ) .'&nbsp;'. JText::_( 'FLEXI_ICON_SEP' );
		}
		$overlib = $add_label;
		$text = JText::_( 'FLEXI_ADD' );

		$link 	= 'index.php?view='.FLEXI_ITEMVIEW.'&task=add'.($maincat->id ? '&maincat='.$maincat->id : '');
		$output	= '<a href="'.JRoute::_($link).'" class="editlinktip hasTip" title="'.$text.'::'.$overlib.'">'.$image.JText::_( 'FLEXI_ADD_NEW_CONTENT' ).'</a>';

		return $output;
	}

	/**
	 * Creates the stateicon
	 *
	 * @param int $state
	 * @param array $params
	 * @since 1.0
	 */
	static function stateicon( $state, &$params, &$state_text=null )
	{
		// Create image filename and state name
		if ( $state == 1 ) {
			$img = 'tick.png';
			$alt = JText::_( 'FLEXI_PUBLISHED' );
		} else if ( $state == 0 ) {
			$img = 'publish_x.png';
			$alt = JText::_( 'FLEXI_UNPUBLISHED' );
		} else if ( $state == (FLEXI_J16GE ? 2:-1) ) {
			$img = 'archive.png';
			$alt = JText::_( 'FLEXI_ARCHIVED' );
		} else if ( $state == -2 ) {
			$img = 'trash.png';
			$alt = JText::_( 'FLEXI_TRASHED' );
		} else if ( $state == -3 ) {
			$img = 'publish_r.png';
			$alt = JText::_( 'FLEXI_PENDING' );
		} else if ( $state == -4 ) {
			$img = 'publish_y.png';
			$alt = JText::_( 'FLEXI_TO_WRITE' );
		} else if ( $state == -5 ) {
			$img = 'publish_g.png';
			$alt = JText::_( 'FLEXI_IN_PROGRESS' );
		} else {
			$img = 'unknown.png';
			$alt = JText::_( 'FLEXI_UNKNOWN' );
		}


		// Create popup text
		$title = JText::_( 'FLEXI_STATE' );
		$descr = str_replace('::', '-', $alt);
		$state_text = $title.' : '.$descr;
		switch ( $params->get('stateicon_popup', 'full') )
		{
			case 'basic':
				$attribs = 'title="'.$state_text.'"';
				break;
			case 'none':
				$attribs = '';
				break;
			case 'full': default:
				$attribs = 'class="editlinktip hasTip" title="'.$title.'::'.$descr.'"';
				break;
		}

		// Create state icon image
		$app = JFactory::getApplication();
		$path = (!FLEXI_J16GE && $app->isAdmin() ? '../' : '').'components/com_flexicontent/assets/images/';  // no need to prefix this with JURI::root(), it will be done below
		if ( $params->get('show_icons', 1) ) {
			$icon = FLEXI_J16GE ?
				JHTML::image( $path.$img, $alt, $attribs) :
				JHTML::_('image.site', $img, $path, NULL, NULL, $alt, $attribs) ;
		} else {
			$icon = $descr;
		}

		return $icon;
	}


	/**
	 * Creates the ratingbar
	 *
	 * @deprecated
	 * @param array $item
	 * @since 1.0
	 */
	static function ratingbar($item)
	{
		//sql calculation doesn't work with negative values and thus only minus votes will not be taken into account
		if ($item->votes == 0) {
			return JText::_( 'FLEXI_NOT_YET_RATED' );
		}

		//we do the rounding here and not in the query to get better ordering results
		$rating = round($item->votes);

		$output = '<span class="qf_ratingbarcontainer editlinktip hasTip" title="'.JText::_( 'FLEXI_RATING' ).'::'.JText::_( 'FLEXI_SCORE' ).': '.$rating.'%">';
		$output .= '<span class="qf_ratingbar" style="width:'.$rating.'%;">&nbsp;</span></span>';

		return $output;
	}

	/**
	 * Creates the voteicons
	 * Deprecated to ajax votes
	 *
	 * @param array $params
	 * @since 1.0
	 */
	static function voteicons($item, &$params)
	{
		if ( $params->get('show_icons') ) {
			$voteup = FLEXI_J16GE ?
				JHTML::image( 'components/com_flexicontent/assets/images/'.'thumb_up.png', JText::_( 'FLEXI_GOOD' ) ) :
				JHTML::_('image.site', 'thumb_up.png', 'components/com_flexicontent/assets/images/', NULL, NULL, JText::_( 'FLEXI_GOOD' ) ) ;
			$votedown = FLEXI_J16GE ?
				JHTML::image( 'components/com_flexicontent/assets/images/'.'thumb_down.png', JText::_( 'FLEXI_BAD' ) ) :
				JHTML::_('image.site', 'thumb_down.png', 'components/com_flexicontent/assets/images/', NULL, NULL, JText::_( 'FLEXI_BAD' ) ) ;
		} else {
			$voteup = JText::_( 'FLEXI_GOOD' ). '&nbsp;';
			$votedown = '&nbsp;'.JText::_( 'FLEXI_BAD' );
		}

		$output = '<a href="'.JRoute::_('index.php?task=vote&vote=1&cid='.$item->categoryslug.'&id='.$item->slug.'&layout='.$params->get('ilayout')).'"class="editlinktip hasTip" title="'.JText::_( 'FLEXI_VOTE_UP' ).'::'.JText::_( 'FLEXI_VOTE_UP_TIP' ).'">'.$voteup.'</a>';
		$output .= ' - ';
		$output .= '<a href="'.JRoute::_('index.php?task=vote&vote=0&cid='.$item->categoryslug.'&id='.$item->slug.'&layout='.$params->get('ilayout')).'"class="editlinktip hasTip" title="'.JText::_( 'FLEXI_VOTE_DOWN' ).'::'.JText::_( 'FLEXI_VOTE_DOWN_TIP' ).'">'.$votedown.'</a>';

		return $output;
	}

	/**
	 * Creates the ajax voting stars system
	 *
	 * @param array $field
	 * @param int or string $xid
	 * @since 1.0
	 */
	static function ItemVote( &$field, $xid, $vote )
	{
		// Check for invalid xid
		if ($xid!='main' && $xid!='extra' && $xid!='all' && !(int)$xid) {
			$html .= "ItemVote(): invalid xid '".$xid."' was given";
			return;
		}

		$db	= JFactory::getDBO();
  	$id  = $field->item_id;

  	$enable_extra_votes = $field->parameters->get('extra_votes', '');
		$extra_votes = !$enable_extra_votes ? '' : $field->parameters->get('extra_votes', '');
		$main_label  = !$enable_extra_votes ? '' : $field->parameters->get('main_label', '');
		// Set a Default main label if one was not given but extra votes exist
		$main_label  = (!$main_label && $extra_votes) ? JText::_('FLEXI_OVERALL') : $main_label;

		$html = '';

		if (!$vote) {
			// These are mass retrieved for multiple items, to optimize performance
			//$db->setQuery( 'SELECT * FROM #__content_rating WHERE content_id=' . $id );
			//$vote = $db->loadObject();
			$vote = new stdClass();
			$vote->rating_sum = $vote->rating_count = 0;
		} else if (!isset($vote->rating_sum) || !isset($vote->rating_sum)) {
			$vote->rating_sum = $vote->rating_count = 0;
		}

		if ($xid=='main' || $xid=='all') {
			$html .= flexicontent_html::ItemVoteDisplay( $field, $id, $vote->rating_sum, $vote->rating_count, 'main', $main_label );
		}

		if ($xid=='all' || $xid=='extra' || (int)$xid) {

			// Retrieve and split-up extra vote types, (removing last one if empty)
			$extra_votes = preg_split("/[\s]*%%[\s]*/", $extra_votes);
			if ( empty($extra_votes[count($extra_votes)-1]) )  unset( $extra_votes[count($extra_votes)-1] );

			// Split extra voting ids (xid) and their titles
			$xid_arr = array();
			foreach ($extra_votes as $extra_vote) {
				list($extra_id, $extra_title) = explode("##", $extra_vote);
				$xid_arr[$extra_id] = $extra_title;
			}

			// Query the database
			if ( (int)$xid )
			{
				if ( !isset($vote->extra[(int)$xid]) ) {
					$extra_vote = new stdClass();
					$extra_vote->rating_sum = $extra_vote->rating_count = 0;
					$extra_vote->extra_id = (int)$xid;
				} else {
					$extra_vote = $vote->extra[(int)$xid];
				}
				$html .= flexicontent_html::ItemVoteDisplay( $field, $id, $extra_vote->rating_sum, $extra_vote->rating_count, $extra_vote->extra_id, $xid_arr[(int)$xid] );
			}
			else
			{
				foreach ( $xid_arr as $extra_id => $extra_title) {
					if ( !isset($vote->extra[$extra_id]) ) {
						$extra_vote = new stdClass();
						$extra_vote->rating_sum = $extra_vote->rating_count = 0;
						$extra_vote->extra_id = $extra_id;
					} else {
						$extra_vote = $vote->extra[$extra_id];
					}
					$html .= flexicontent_html::ItemVoteDisplay( $field, $id, $extra_vote->rating_sum, $extra_vote->rating_count, $extra_vote->extra_id, $extra_title );
				}
			}
		}

		return $html;
 	}

	/**
	 * Method that creates the stars
	 *
	 * @param array				$field
	 * @param int 				$id
	 * @param int			 	$rating_sum
	 * @param int 				$rating_count
	 * @param int or string 	$xid
	 * @since 1.0
	 */
 	static function ItemVoteDisplay( &$field, $id, $rating_sum, $rating_count, $xid, $label='', $stars_override=0, $allow_vote=true, $vote_counter='default', $show_counter_label=true )
	{
		static $acclvl_names  = null;
		static $star_tooltips = null;
		static $star_classes  = null;
		$user = JFactory::getUser();
		$db   = JFactory::getDBO();
		$cparams = JComponentHelper::getParams( 'com_flexicontent' );
		
		
		// *****************************************************
		// Find if user has the ACCESS level required for voting
		// *****************************************************
		
		if (!FLEXI_J16GE) $aid = (int) $user->get('aid');
		else $aid_arr = $user->getAuthorisedViewLevels();
		$acclvl = (int) $field->parameters->get('submit_acclvl', FLEXI_J16GE ? 1 : 0);
		$has_acclvl = FLEXI_J16GE ? in_array($acclvl, $aid_arr) : $acclvl <= $aid;
		
		
		// *********************************************************
		// Calculate NO access actions, (case that user cannot vote)
		// *********************************************************
		
		if ( !$has_acclvl )
		{
			if ($user->id) {
				$no_acc_msg = $field->parameters->get('logged_no_acc_msg', '');
				$no_acc_url = $field->parameters->get('logged_no_acc_url', '');
				$no_acc_doredirect  = $field->parameters->get('logged_no_acc_doredirect', 0);
				$no_acc_askredirect = $field->parameters->get('logged_no_acc_askredirect', 1);
			} else {
				$no_acc_msg  = $field->parameters->get('guest_no_acc_msg', '');
				$no_acc_url  = $field->parameters->get('guest_no_acc_url', '');
				$no_acc_doredirect  = $field->parameters->get('guest_no_acc_doredirect', 2);
				$no_acc_askredirect = $field->parameters->get('guest_no_acc_askredirect', 1);
			}
			
			// Decide no access Redirect URLs
			if ($no_acc_doredirect == 2) {
				$com_users = FLEXI_J16GE ? 'com_users' : 'com_user';
				$no_acc_url = $cparams->get('login_page', 'index.php?option='.$com_users.'&view=login');
			} else if ($no_acc_doredirect == 0) {
				$no_acc_url = '';
			} // else unchanged
			
			
			// Decide no access Redirect Message
			$no_acc_msg = $no_acc_msg ? JText::_($no_acc_msg) : '';
			if ( !$no_acc_msg )
			{
				// Find name of required Access Level
				if (FLEXI_J16GE) {
					$acclvl_name = '';
					if ($acclvl && empty($acclvl_names)) {  // Retrieve this ONCE (static var)
						$db->setQuery('SELECT title,id FROM #__viewlevels as level');
						$_lvls = $db->loadObjectList();
						$acclvl_names = array();
						if (!empty($_lvls)) foreach ($_lvls as $_lvl) $acclvl_names[$_lvl->id] = $_lvl->title;
					}
				} else {
					$acclvl_names = array(0=>'Public', 1=>'Registered', 2=>'Special');
					$acclvl_name = $acclvl_names[$acclvl];
				}
				$acclvl_name =  !empty($acclvl_names[$acclvl]) ? $acclvl_names[$acclvl] : "Access Level: ".$acclvl." not found/was deleted";
				$no_acc_msg = JText::sprintf( 'FLEXI_NO_ACCESS_TO_VOTE' , $acclvl_name);
			}
			$no_acc_msg_redirect = JText::_($no_acc_doredirect==2 ? 'FLEXI_CONFIM_REDIRECT_TO_LOGIN_REGISTER' : 'FLEXI_CONFIM_REDIRECT');
		}
		
		$counter 	= $field->parameters->get( 'counter', 1 );   // 0: disable showing vote counter, 1: enable for main and for extra votes
		if ($vote_counter != 'default' ) $counter = $vote_counter ? 1 : 0;
		$unrated 	= $field->parameters->get( 'unrated', 1 );
		$dim			= $field->parameters->get( 'dimension', 16 );
		$image		= $field->parameters->get( 'image', 'components/com_flexicontent/assets/images/star-small.png' );
		$class 		= $field->name;
		$img_path	= JURI::base(true) .'/'. $image;
		
		// Get number of displayed stars, configuration
		$rating_resolution = (int)$field->parameters->get('rating_resolution', 5);
		$rating_resolution = $rating_resolution >= 5   ?  $rating_resolution  :  5;
		$rating_resolution = $rating_resolution <= 100  ?  $rating_resolution  :  100;
		
		// Get number of displayed stars, configuration
		$rating_stars = (int) ($stars_override ? $stars_override : $field->parameters->get('rating_stars', 5));
		$rating_stars = $rating_stars > $rating_resolution ? $rating_resolution  :  $rating_stars;  // Limit stars to resolution
		
		static $js_and_css_added = false;

	 	if (!$js_and_css_added)
	 	{
			$document = JFactory::getDocument();
			FLEXI_J30GE ? JHtml::_('behavior.framework') : JHTML::_('behavior.mootools');  // Make sure mootools are loaded before our js
	 		JHTML::_('behavior.tooltip');   // This is also needed
			$css 	= JURI::base(true) .'/components/com_flexicontent/assets/css/fcvote.css';
			$js		= JURI::base(true) .'/components/com_flexicontent/assets/js/fcvote.js';
			$document->addStyleSheet($css);
			$document->addScript($js);

			$document->addScriptDeclaration('var sfolder = "'.JURI::base(true).'";');

			$css = '
			.'.$class.' .fcvote {line-height:'.$dim.'px;}
			.'.$class.' .fcvote-label {margin-right: 6px;}
			.'.$class.' .fcvote ul {height:'.$dim.'px; position:relative !important; left:0px; !important;}
			.'.$class.' .fcvote ul, .'.$class.' .fcvote ul li a:hover, .'.$class.' .fcvote ul li.current-rating {background-image:url('.$img_path.')!important;}
			.'.$class.' .fcvote ul li a, .'.$class.' .fcvote ul li.current-rating {height:'.$dim.'px;line-height:'.$dim.'px;}
			';
			
			$star_tooltips = array();
			$star_classes  = array();
			for ($i=1; $i<=$rating_resolution; $i++) {
				$star_zindex  = $rating_resolution - $i + 2;
				$star_percent = (int) round(100 * ($i / $rating_resolution));
				$css .= '.fcvote li a.star'.$i.' { width: '.$star_percent.'%; z-index: '.$star_zindex.'; }' ."\n";
				$star_classes[$i] = 'star'.$i;
				if ($star_percent < 20)       $star_tooltips[$i] = JText::_( 'FLEXI_VERY_POOR' );
				else if ($star_percent < 40)  $star_tooltips[$i] = JText::_( 'FLEXI_POOR' );
				else if ($star_percent < 60)  $star_tooltips[$i] = JText::_( 'FLEXI_REGULAR' );
				else if ($star_percent < 80)  $star_tooltips[$i] = JText::_( 'FLEXI_GOOD' );
				else                          $star_tooltips[$i] = JText::_( 'FLEXI_VERY_GOOD' );
				$star_tooltips[$i] .= ' '.$i.'/'.$rating_resolution;
			}
			
			$document->addStyleDeclaration($css);
			$js_and_css_added = true;
	 	}
	 	
	 	$percent = 0;
	 	$factor = (int) round(100/$rating_resolution);
		if ($rating_count != 0) {
			$percent = number_format((intval($rating_sum) / intval( $rating_count ))*$factor,2);
		} elseif ($unrated == 0) {
			$counter = -1;
		}

		if ( (int)$xid ) {
			// Disable showing vote counter in extra votes
			if ( $counter == 2 ) $counter = 0;
		} else {
			// Disable showing vote counter in main vote
			if ( $counter == 3 ) $counter = 0;
		}
		$nocursor = !$allow_vote ? 'cursor:auto;' : '';
		
		if ($allow_vote)
		{
			// HAS Voting ACCESS
			if ( $has_acclvl ) {
				$href = 'javascript:;';
				$onclick = '';
			}
			// NO Voting ACCESS
			else {
				// WITHOUT Redirection
				if ( !$no_acc_url ) {
					$href = 'javascript:;';
					$popup_msg = addcslashes($no_acc_msg, "'");
					$onclick = 'alert(\''.$popup_msg.'\');';
				}
				// WITH Redirection
				else {
					$href = $no_acc_url;
					$popup_msg = addcslashes($no_acc_msg . ' ... ' . $no_acc_msg_redirect, "'");
					
					if ($no_acc_askredirect==2)       $onclick = 'return confirm(\''.$popup_msg.'\');';
					else if ($no_acc_askredirect==1)  $onclick = 'alert(\''.$popup_msg.'\'); return true;';
					else                              $onclick = 'return true;';
				}
			}
			
			$dovote_class = $has_acclvl ? 'fc_dovote' : '';
			$html_vote_links = '';
			for ($i=1; $i<=$rating_resolution; $i++) {
				$html_vote_links .= '
					<li><a onclick="'.$onclick.'" href="'.$href.'" title="'.$star_tooltips[$i].'" class="'.$dovote_class.' '.$star_classes[$i].'" rel="'.$id.'_'.$xid.'">'.$i.'</a></li>';
			}
		}
		
		$element_width = $rating_resolution * $dim;
		if ($rating_stars) $element_width = (int) $element_width * ($rating_stars / $rating_resolution);
	 	$html='
		<div class="'.$class.'">
			<div class="fcvote">'
	  		.($label ? '<div id="fcvote_lbl'.$id.'_'.$xid.'" class="fcvote-label xid-'.$xid.'">'.$label.'</div>' : '')
				.'<ul style="width:'.$element_width.'px;">
    				<li id="rating_'.$id.'_'.$xid.'" class="current-rating" style="width:'.(int)$percent.'%;'.$nocursor.'"></li>'
    		.@ $html_vote_links
				.'
				</ul>
	  		<div id="fcvote_cnt_'.$id.'_'.$xid.'" class="fcvote-count">';
		  		if ( $counter != -1 ) {
	  				if ( $counter != 0 ) {
							$html .= "(";
							$html .= $rating_count ? $rating_count : "0";
							if ($show_counter_label) $html .= " ".JText::_( $rating_count!=1 ? 'FLEXI_VOTES' : 'FLEXI_VOTE' );
		 	 				$html .= ")";
						}
					}
	 	 	$html .='
	 	 		</div>
 	 			<div class="clear"></div>
 	 		</div>
 	 	</div>';

	 	return $html;
 	}

	/**
	 * Creates the favourited by user list
	 *
	 * @param array $params
	 * @since 1.0
	 */
	static function favoured_userlist( &$field, &$item,  $favourites)
	{
		$userlisttype = $field->parameters->get('display_favoured_userlist', 0);
		$maxusercount = $field->parameters->get('display_favoured_max', 12);

		$favuserlist = $favourites ? '['.$favourites.' '.JText::_('FLEXI_USERS') : '';

		if ( !$userlisttype ) return $favuserlist ? $favuserlist.']' : '';
		else if ($userlisttype==1) $uname="u.username";
		else /*if ($userlisttype==2)*/ $uname="u.name";

		$db	= JFactory::getDBO();
		$query = "SELECT $uname FROM #__flexicontent_favourites as ff"
			." LEFT JOIN #__users AS u ON u.id=ff.userid "
			." WHERE ff.itemid=" . $item->id;
		$db->setQuery($query);
		$favusers = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
		if (!is_array($favusers) || !count($favusers)) return $favuserlist ? $favuserlist.']' : '';

		$seperator = ': ';
		$count = 0;
		foreach($favusers as $favuser) {
			$favuserlist .= $seperator . $favuser;
			$seperator = ',';
			$count++;
			if ($count >= $maxusercount) break;
		}
		if (count($favusers) > $maxusercount) $favuserlist .=" ...";
		if (!empty($favuserlist)) $favuserlist .="]";
		return $favuserlist;
	}

 	/**
	 * Creates the favourite icons
	 *
	 * @param array $params
	 * @since 1.0
	 */
	static function favicon($field, $favoured, & $item=false)
	{
		$user			= JFactory::getUser();

		static $js_and_css_added = false;

	 	if (!$js_and_css_added)
	 	{
			$document	= JFactory::getDocument();
			FLEXI_J30GE ? JHtml::_('behavior.framework') : JHTML::_('behavior.mootools');  // Make sure mootools are loaded before our js
	 		JHTML::_('behavior.tooltip');   // This is also needed
			$document->addScript( JURI::base(true) .'/components/com_flexicontent/assets/js/fcfav.js' );

			$js = "
				var sfolder = '".JURI::base(true)."';
				var fcfav_text=Array(
					'".JText::_( 'FLEXI_YOUR_BROWSER_DOES_NOT_SUPPORT_AJAX',true )."',
					'".JText::_( 'FLEXI_LOADING',true )."',
					'".JText::_( 'FLEXI_ADDED_TO_YOUR_FAVOURITES',true )."',
					'".JText::_( 'FLEXI_YOU_NEED_TO_LOGIN',true )."',
					'".JText::_( 'FLEXI_REMOVED_FROM_YOUR_FAVOURITES',true )."',
					'".JText::_( 'FLEXI_USERS',true )."'
					);
				";
			$document->addScriptDeclaration($js);

			$js_and_css_added = true;
		}

		$output = "";

		if ($user->id && $favoured)
		{
			$image = FLEXI_J16GE ?
				JHTML::image( 'components/com_flexicontent/assets/images/'.'heart_delete.png', JText::_( 'FLEXI_REMOVE_FAVOURITE' )) :
				JHTML::_('image.site', 'heart_delete.png', 'components/com_flexicontent/assets/images/', NULL, NULL, JText::_( 'FLEXI_REMOVE_FAVOURITE' )) ;
			$text 		= JText::_( 'FLEXI_ADDREMOVE_FAVOURITE' );
			$overlib 	= JText::_( 'FLEXI_ADDREMOVE_FAVOURITE_TIP' );
			$onclick 	= 'javascript:FCFav('.$field->item_id.');';
			$link 		= 'javascript:void(null)';

			$output		.=
				 '<span class="fcfav_delete">'
				.' <a id="favlink'.$field->item_id.'" href="'.$link.'" onclick="'.$onclick.'" class="editlinktip hasTip fcfav-reponse" title="'.$text.'::'.$overlib.'">'.$image.'</a>'
				.' <span class="fav_item_id" style="display:none;">'.$item->id.'</span>'
				.' <span class="fav_item_title" style="display:none;">'.$item->title.'</span>'
				.'</span>';

		}
		elseif($user->id)
		{
			$image = FLEXI_J16GE ?
				JHTML::image( 'components/com_flexicontent/assets/images/'.'heart_add.png', JText::_( 'FLEXI_FAVOURE' )) :
				JHTML::_('image.site', 'heart_add.png', 'components/com_flexicontent/assets/images/', NULL, NULL, JText::_( 'FLEXI_FAVOURE' )) ;
			$text 		= JText::_( 'FLEXI_ADDREMOVE_FAVOURITE' );
			$overlib 	= JText::_( 'FLEXI_ADDREMOVE_FAVOURITE_TIP' );
			$onclick 	= 'javascript:FCFav('.$field->item_id.');';
			$link 		= 'javascript:void(null)';

			$output		.=
				 '<span class="fcfav_add">'
				.' <a id="favlink'.$field->item_id.'" href="'.$link.'" onclick="'.$onclick.'" class="editlinktip hasTip fcfav-reponse" title="'.$text.'::'.$overlib.'">'.$image.'</a>'
				.' <span class="fav_item_id" style="display:none;">'.$item->id.'</span>'
				.' <span class="fav_item_title" style="display:none;">'.$item->title.'</span>'
				.'</span>';
		}
		else
		{
			$overlib 	= JText::_( 'FLEXI_FAVOURE_LOGIN_TIP' );
			$text 		= JText::_( 'FLEXI_FAVOURE' );
			$image = FLEXI_J16GE ?
				JHTML::image( 'components/com_flexicontent/assets/images/'.'heart_login.png', JText::_( 'FLEXI_FAVOURE' )) :
				JHTML::_('image.site', 'heart_login.png', 'components/com_flexicontent/assets/images/', NULL, NULL, $text, 'class="editlinktip hasTip" title="'.$text.'::'.$overlib.'"' ) ;

			$output		= $image;
		}

		return $output;
	}


	/**
	 * Method to build the list for types when performing an edit action
	 *
	 * @return array
	 * @since 1.5
	 */
	static function buildtypesselect($types, $name, $selected, $top, $class = 'class="inputbox"', $tagid='', $check_perms=false)
	{
		$user = JFactory::getUser();
		
		$typelist = array();
		if($top)  $typelist[] = JHTML::_( 'select.option', '', JText::_( 'FLEXI_SELECT_TYPE' ) );
		
		foreach ($types as $type)
		{
			$allowed = 1;
			if ($check_perms)
			{
				if (FLEXI_J16GE)
					$allowed = ! $type->itemscreatable || $user->authorise('core.create', 'com_flexicontent.type.' . $type->id);
				else if (FLEXI_ACCESS && $user->gid < 25)
					$allowed = ! $type->itemscreatable || FAccess::checkAllContentAccess('com_content','submit','users', $user->gmid, 'type', $type->id);
				else
					$allowed = 1;
			}
			
			if ( !$allowed && $type->itemscreatable == 1 ) continue;
			
			if ( !$allowed && $type->itemscreatable == 2 )
				$typelist[] = JHTML::_( 'select.option', $type->id, $type->name, 'value', 'text', $disabled = true );
			else
				$typelist[] = JHTML::_( 'select.option', $type->id, $type->name);
		}
		
		return JHTML::_('select.genericlist', $typelist, $name, $class, 'value', 'text', $selected, $tagid );
	}
	
	
	/**
	 * Method to build the list of the autors
	 *
	 * @return array
	 * @since 1.5
	 */
	static function buildauthorsselect($list, $name, $selected, $top, $class = 'class="inputbox"')
	{
		$typelist 	= array();

		if($top) {
			$typelist[] 	= JHTML::_( 'select.option', '', JText::_( 'FLEXI_SELECT_AUTHOR' ) );
		}

		foreach ($list as $item) {
			$typelist[] = JHTML::_( 'select.option', $item->id, $item->name);
		}
		return JHTML::_('select.genericlist', $typelist, $name, $class, 'value', 'text', $selected );
	}


	/**
	 * Method to build the list for types when performing an edit action
	 *
	 * @return array
	 * @since 1.5
	 */
	static function buildfieldtypeslist($name, $class, $selected)
	{
		global $global_field_types;
		$db = JFactory::getDBO();

		$query = 'SELECT element AS value, REPLACE(name, "FLEXIcontent - ", "") AS text'
		. ' FROM '.(FLEXI_J16GE ? '#__extensions' : '#__plugins')
		. ' WHERE '.(FLEXI_J16GE ? 'enabled = 1' : 'published = 1')
		. (FLEXI_J16GE ? ' AND `type`=' . $db->Quote('plugin') : '')
		. ' AND folder = ' . $db->Quote('flexicontent_fields')
		. ' AND element <> ' . $db->Quote('core')
		. ' ORDER BY text ASC'
		;

		$db->setQuery($query);
		$global_field_types = $db->loadObjectList();

		// This should not be neccessary as, it was already done in DB query above
		foreach($global_field_types as $field_type) {
			$field_type->text = preg_replace("/FLEXIcontent[ \t]*-[ \t]*/i", "", $field_type->text);
			$field_arr[$field_type->text] = $field_type;
		}
		ksort( $field_arr, SORT_STRING );

		$list = JHTML::_('select.genericlist', $field_arr, $name, $class, 'value', 'text', $selected );

		return $list;
	}

	/**
	 * Method to build the file extension list
	 *
	 * @return array
	 * @since 1.5
	 */
	static function buildfilesextlist($name, $class, $selected)
	{
		$db = JFactory::getDBO();

		$query = 'SELECT DISTINCT ext'
		. ' FROM #__flexicontent_files'
		. ' ORDER BY ext ASC'
		;
		$db->setQuery($query);
		$exts = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();

		$options[] = JHTML::_( 'select.option', '', '- '.JText::_( 'FLEXI_ALL_EXT' ).' -');

		foreach ($exts as $ext) {
			$options[] = JHTML::_( 'select.option', $ext, $ext);
		}

		$list = JHTML::_('select.genericlist', $options, $name, $class, 'value', 'text', $selected );

		return $list;
	}

	/**
	 * Method to build the uploader list
	 *
	 * @return array
	 * @since 1.5
	 */
	static function builduploaderlist($name, $class, $selected)
	{
		$db = JFactory::getDBO();

		$query = 'SELECT DISTINCT f.uploaded_by AS uid, u.name AS name'
		. ' FROM #__flexicontent_files AS f'
		. ' LEFT JOIN #__users AS u ON u.id = f.uploaded_by'
		. ' ORDER BY f.ext ASC'
		;
		$db->setQuery($query);
		$exts = $db->loadObjectList();

		$options[] = JHTML::_( 'select.option', '', '- '.JText::_( 'FLEXI_ALL_UPLOADERS' ).' -');

		foreach ($exts as $ext) {
			$options[] = JHTML::_( 'select.option', $ext->uid, $ext->name);
		}

		$list = JHTML::_('select.genericlist', $options, $name, $class, 'value', 'text', $selected );

		return $list;
	}


	/**
	 * Method to build the Joomfish languages list
	 *
	 * @return object
	 * @since 1.5
	 */
	static function buildlanguageslist($name, $class, $selected, $type = 1, $allowed_langs = null)
	{
		$db = JFactory::getDBO();

		$selected_found = false;
		$all_langs = FLEXIUtilities::getlanguageslist();
		$user_langs = array();
		if ($allowed_langs) {
			foreach ($all_langs as $index => $lang)
				if ( in_array($lang->code, $allowed_langs) ) {
					$user_langs[] = $lang;
					// Check if selected language was added to the user langs
					$selected_found = ($lang->code == $selected) ? true : $selected_found;
				}
		}	else {
			$user_langs = & $all_langs;
			$selected_found = true;
		}
		if ( !count($user_langs) )  return "user is not allowed to use any language";
		if (!$selected_found) $selected = $user_langs[0]->code;  // Force first language to be selected


		$langs = array();
		switch ($type)
		{
			case 1:
				foreach ($user_langs as $lang) {
					$langs[] = JHTML::_('select.option',  $lang->code, $lang->name );
				}
				$list = JHTML::_('select.genericlist', $langs, $name, $class, 'value', 'text', $selected );
				break;
			case 2:
				$langs[] = JHTML::_('select.option',  '', JText::_( 'FLEXI_SELECT_LANGUAGE' ));
				foreach ($user_langs as $lang) {
					$langs[] = JHTML::_('select.option',  $lang->code, $lang->name );
				}
				$list = JHTML::_('select.genericlist', $langs, $name, $class, 'value', 'text', $selected );
				break;
			case 3:
				$checked	= '';
				$list		= '';

				foreach ($user_langs as $lang) {
					if ($lang->code == $selected) {
						$checked = ' checked="checked"';
					}
					$list 	.= '<input id="lang'.$lang->id.'" type="radio" name="'.$name.'" value="'.$lang->code.'"'.$checked.' />';
					$list 	.= '<label class="lang_box" for="lang'.$lang->id.'" title="'.$lang->name.'" >';
					if($lang->shortcode=="*") {
						$list 	.= '<span class="lang_lbl">'.JText::_("All").'</span>';  // Can appear in J1.6+ only
					} else if (@$lang->imgsrc) {
						$list 	.= '<img class="lang_lbl" src="'.$lang->imgsrc.'" alt="'.$lang->name.'" />';
					} else {
						$list 	.= $lang->name;
					}
					$list 	.= '</label>';
					$checked	= '';
				}
				break;
			case 4:
				$list 	.= '<input id="lang9999" type="radio" name="'.$name.'" class="lang" value="" checked="checked" />';
				$list 	 = '<label class="lang_box" for="lang9999" title="'.JText::_( 'FLEXI_NOCHANGE_LANGUAGE_DESC' ).'" >';
				$list 	.= JText::_( 'FLEXI_NOCHANGE_LANGUAGE' );
				$list 	.= '</label><div class="clear"></div>';

				foreach ($user_langs as $lang) {
					$list 	.= '<input id="lang'.$lang->id.'" type="radio" name="'.$name.'" class="lang" value="'.$lang->code.'" />';
					$list 	.= '<label class="lang_box" for="lang'.$lang->id.'" title="'.$lang->name.'">';
					if($lang->shortcode=="*") {
						$list 	.= JText::_("All");  // Can appear in J1.6+ only
					} else if (@$lang->imgsrc) {
						$list 	.= '<img src="'.$lang->imgsrc.'" alt="'.$lang->name.'" />';
					} else {
						$list 	.= $lang->name;
					}
					$list 	.= '&nbsp;</label><div class="clear"></div>';
				}
				break;
			case 5:
				$list		= '';
				foreach ($user_langs as $lang) {
					if ($lang->code==$selected) continue;
					$list 	.= '<input id="lang'.$lang->id.'" type="radio" name="'.$name.'" class="lang" value="'.$lang->code.'" />';
					$list 	.= '<label class="lang_box" for="lang'.$lang->id.'" title="'.$lang->name.'">';
					if($lang->shortcode=="*") {
						$list 	.= JText::_("All");  // Can appear in J1.6+ only
					} else if (@$lang->imgsrc) {
						$list 	.= '<img src="'.$lang->imgsrc.'" alt="'.$lang->name.'" />';
					} else {
						$list 	.= $lang->name;
					}
					$list 	.= '</label><div class="clear"></div>';
				}
				break;
			case 6:
				$list		= '';
				foreach ($user_langs as $lang) {
					$list 	.= '<input id="lang'.$lang->id.'" type="radio" name="'.$name.'" class="lang" value="'.$lang->code.'" />';
					$list 	.= '<label class="lang_box" for="lang'.$lang->id.'" title="'.$lang->name.'">';
					if($lang->shortcode=="*") {
						$list 	.= JText::_("All");  // Can appear in J1.6+ only
					} else if (@$lang->imgsrc) {
						$list 	.= '<img src="'.$lang->imgsrc.'" alt="'.$lang->name.'" />';
					} else {
						$list 	.= $lang->name;
					}
					$list 	.= '&nbsp;</label><div class="clear"></div>';
				}
				$list 	.= '<input id="lang9999" type="radio" name="'.$name.'" class="lang" value="" />';
				$list 	.= '<label class="lang_box hasTip" for="lang9999" title="'.JText::_('FLEXI_USE_LANGUAGE_COLUMN').'::'.JText::_('FLEXI_USE_LANGUAGE_COLUMN_TIP').'">';
				$list 	.= JText::_( 'FLEXI_USE_LANGUAGE_COLUMN' );
				$list 	.= '</label><div class="clear"></div>';
				break;
		}
		return $list;
	}

	/**
	 * Method to build the Joomfish languages list
	 *
	 * @return object
	 * @since 1.5
	 */
	static function buildstateslist($name, $class, $selected, $type=1)
	{
		$state[] = JHTML::_('select.option',  '', JText::_( 'FLEXI_DO_NOT_CHANGE' ) );
		$state[] = JHTML::_('select.option',  -4, JText::_( 'FLEXI_TO_WRITE' ) );
		$state[] = JHTML::_('select.option',  -3, JText::_( 'FLEXI_PENDING' ) );
		$state[] = JHTML::_('select.option',  -5, JText::_( 'FLEXI_IN_PROGRESS' ) );
		$state[] = JHTML::_('select.option',   1, JText::_( 'FLEXI_PUBLISHED' ) );
		$state[] = JHTML::_('select.option',   0, JText::_( 'FLEXI_UNPUBLISHED' ) );
		$state[] = JHTML::_('select.option',  (FLEXI_J16GE ? 2:-1), JText::_( 'FLEXI_ARCHIVED' ) );
		$state[] = JHTML::_('select.option',  -2, JText::_( 'FLEXI_TRASHED' ) );

		if ($type==1) {
			$list = JHTML::_('select.genericlist', $state, $name, $class, 'value', 'text', $selected );
		} else if ($type==2) {

			$state_ids   = array(1, -5, 0, -3, -4);
			$state_ids[] = FLEXI_J16GE ? 2:-1;
			$state_ids[]  = -2;

			$state_names = array(1=>'FLEXI_PUBLISHED', -5=>'FLEXI_IN_PROGRESS', 0=>'FLEXI_UNPUBLISHED', -3=>'FLEXI_PENDING', -4=>'FLEXI_TO_WRITE', (FLEXI_J16GE ? 2:-1)=>'FLEXI_ARCHIVED', -2=>'FLEXI_TRASHED');
			$state_descrs = array(1=>'FLEXI_PUBLISH_THIS_ITEM', -5=>'FLEXI_SET_ITEM_IN_PROGRESS', 0=>'FLEXI_UNPUBLISH_THIS_ITEM', -3=>'FLEXI_SET_ITEM_PENDING', -4=>'FLEXI_SET_ITEM_TO_WRITE', (FLEXI_J16GE ? 2:-1)=>'FLEXI_ARCHIVE_THIS_ITEM', -2=>'FLEXI_TRASH_THIS_ITEM');
			$state_imgs = array(1=>'tick.png', -5=>'publish_g.png', 0=>'publish_x.png', -3=>'publish_r.png', -4=>'publish_y.png', (FLEXI_J16GE ? 2:-1)=>'archive.png', -2=>'trash.png');
			$img_path = JURI::root()."/components/com_flexicontent/assets/images/";

			$list = '';
			foreach ($state_ids as $i => $state_id) {
				$list 	.= '<input id="state'.$state_id.'" type="radio" name="state" class="state" value="'.$state_id.'" />';
				$list 	.= '<label class="state_box" for="state'.$state_id.'" title="'.JText::_( $state_names[$state_id] ).'" >';
				$list 	.= '<img src="'.$img_path.$state_imgs[$state_id].'" width="16" height="16" border="0" alt="'.JText::_( $state_names[$state_id] ).'" />';
				$list 	.= '</label>';
			}
			$list 	.= '<input id="state9999" type="radio" name="state" class="state" value="" />';
			$list 	.= '<label class="state_box hasTip" for="state9999" title="'.JText::_('FLEXI_USE_STATE_COLUMN').'::'.JText::_('FLEXI_USE_STATE_COLUMN_TIP').'">';
			$list 	.= JText::_( 'FLEXI_USE_STATE_COLUMN' );
			$list 	.= '</label>';
		} else {
			$list = 'Bad type in buildstateslist()';
		}

		return $list;
	}


	/**
	 * Method to get the user's Current Language
	 *
	 * @return string
	 * @since 1.5
	 */
	static function getUserCurrentLang()
	{
		static $lang = null;      // A two character language tag
		if ($lang) return $lang;

		// Get default content language for J1.5 and CURRENT content language for J2.5
		// NOTE: Content language can be natively switched in J2.5, by using
		// (a) the language switcher module and (b) the Language Filter - System Plugin
		$cntLang = substr(JFactory::getLanguage()->getTag(), 0,2);

		// Language as set in the URL (can be switched via Joomfish in J1.5)
		$urlLang  = JRequest::getWord('lang', '' );

		// Language from URL is used only in J1.5 -- (As said above, in J2.5 the content language can be switched natively)
		$lang = (FLEXI_J16GE || empty($urlLang)) ? $cntLang : $urlLang;

		// WARNING !!!: This variable is wrongly set in J2.5, maybe correct it?
		//JRequest::setVar('lang', $lang );

		return $lang;
	}


	/**
	 * Method to get Site (Frontend) default language
	 * NOTE: ... this is the default language of created content for J1.5, but in J1.6+ is '*' (=all)
	 * NOTE: ... joomfish creates translations in all other languages
	 *
	 * @return string
	 * @since 1.5
	 */
	static function getSiteDefaultLang()
	{
		$languages = JComponentHelper::getParams('com_languages');
		$lang = $languages->get('site', 'en-GB');
		return $lang;
	}

	static function nl2space($string) {
		if(gettype($string)!="string") return false;
		$strlen = strlen($string);
		$array = array();
		$str = "";
		for($i=0;$i<$strlen;$i++) {
			if(ord($string[$i])===ord("\n")) {
				$str .= ' ';
				continue;
			}
			$str .= $string[$i];
		}
		return $str;
	 }


	/**
		Diff implemented in pure php, written from scratch.
		Copyright (C) 2003  Daniel Unterberger <diff.phpnet@holomind.de>
		Copyright (C) 2005  Nils Knappmeier next version

		This program is free software; you can redistribute it and/or
		modify it under the terms of the GNU General Public License
		as published by the Free Software Foundation; either version 2
		of the License, or (at your option) any later version.

		This program is distributed in the hope that it will be useful,
		but WITHOUT ANY WARRANTY; without even the implied warranty of
		MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
		GNU General Public License for more details.

		You should have received a copy of the GNU General Public License
		along with this program; if not, write to the Free Software
		Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.

		http://www.gnu.org/licenses/gpl.html

		About:
		I searched a function to compare arrays and the array_diff()
		was not specific enough. It ignores the order of the array-values.
		So I reimplemented the diff-function which is found on unix-systems
		but this you can use directly in your code and adopt for your needs.
		Simply adopt the formatline-function. with the third-parameter of arr_diff()
		you can hide matching lines. Hope someone has use for this.

		Contact: d.u.diff@holomind.de <daniel unterberger>
	**/

	## PHPDiff returns the differences between $old and $new, formatted
	## in the standard diff(1) output format.

	static function PHPDiff($t1,$t2)
	{
		# split the source text into arrays of lines
		//$t1 = explode("\n",$old);
		$x=array_pop($t1);
		if ($x>'') $t1[]="$x\n\\ No newline at end of file";
		//$t2 = explode("\n",$new);
		$x=array_pop($t2);
		if ($x>'') $t2[]="$x\n\\ No newline at end of file";

		# build a reverse-index array using the line as key and line number as value
		# don't store blank lines, so they won't be targets of the shortest distance
		# search
		foreach($t1 as $i=>$x) if ($x>'') $r1[$x][]=$i;
		foreach($t2 as $i=>$x) if ($x>'') $r2[$x][]=$i;

		$a1=0; $a2=0;   # start at beginning of each list
		$actions=array();

		# walk this loop until we reach the end of one of the lists
		while ($a1<count($t1) && $a2<count($t2))
		{
			# if we have a common element, save it and go to the next
			if ($t1[$a1]==$t2[$a2]) { $actions[]=4; $a1++; $a2++; continue; }

			# otherwise, find the shortest move (Manhattan-distance) from the
			# current location
			$best1=count($t1); $best2=count($t2);
			$s1=$a1; $s2=$a2;
			while(($s1+$s2-$a1-$a2) < ($best1+$best2-$a1-$a2)) {
			$d=-1;
			foreach((array)@$r1[$t2[$s2]] as $n)
			if ($n>=$s1) { $d=$n; break; }
			if ($d>=$s1 && ($d+$s2-$a1-$a2)<($best1+$best2-$a1-$a2))
			{ $best1=$d; $best2=$s2; }
			$d=-1;
			foreach((array)@$r2[$t1[$s1]] as $n)
			if ($n>=$s2) { $d=$n; break; }
			if ($d>=$s2 && ($s1+$d-$a1-$a2)<($best1+$best2-$a1-$a2))
			{ $best1=$s1; $best2=$d; }
			$s1++; $s2++;
			}
			while ($a1<$best1) { $actions[]=1; $a1++; }  # deleted elements
			while ($a2<$best2) { $actions[]=2; $a2++; }  # added elements
		}

		# we've reached the end of one list, now walk to the end of the other
		while($a1<count($t1)) { $actions[]=1; $a1++; }  # deleted elements
		while($a2<count($t2)) { $actions[]=2; $a2++; }  # added elements

		# and this marks our ending point
		$actions[]=8;

		# now, let's follow the path we just took and report the added/deleted
		# elements into $out.
		$op = 0;
		$x0=$x1=0; $y0=$y1=0;
		$out1 = array();
		$out2 = array();
		foreach($actions as $act) {
			if ($act==1) { $op|=$act; $x1++; continue; }
			if ($act==2) { $op|=$act; $y1++; continue; }
			if ($op>0) {
				//$xstr = ($x1==($x0+1)) ? $x1 : ($x0+1).",$x1";
				//$ystr = ($y1==($y0+1)) ? $y1 : ($y0+1).",$y1";
				/*if ($op==1) $out[] = "{$xstr}d{$y1}";
				elseif ($op==3) $out[] = "{$xstr}c{$ystr}";*/
				while ($x0<$x1) { $out1[] = $x0; $x0++; }   # deleted elems
				/*if ($op==2) $out[] = "{$x1}a{$ystr}";
				elseif ($op==3) $out[] = '---';*/
				while ($y0<$y1) { $out2[] = $y0; $y0++; }   # added elems
			}
			$x1++; $x0=$x1;
			$y1++; $y0=$y1;
			$op=0;
		}
		//$out1[] = '';
		//$out2[] = '';
		return array($out1, $out2);
	}

	static function flexiHtmlDiff($old, $new, $mode=0)
	{
		$t1 = explode(" ",$old);
		$t2 = explode(" ",$new);
		$out = flexicontent_html::PHPDiff( $t1, $t2 );
		$html1 = array();
		$html2 = array();
		foreach($t1 as $k=>$o) {
			if(in_array($k, $out[0])) $html1[] = "<s>".($mode?htmlspecialchars($o, ENT_QUOTES):$o)."</s>";
			else $html1[] = ($mode?htmlspecialchars($o, ENT_QUOTES)."<br />":$o);
		}
		foreach($t2 as $k=>$n) {
			if(in_array($k, $out[1])) $html2[] = "<u>".($mode?htmlspecialchars($n, ENT_QUOTES):$n)."</u>";
			else $html2[] = ($mode?htmlspecialchars($n, ENT_QUOTES)."<br />":$n);
		}
		$html1 = implode(" ", $html1);
		$html2 = implode(" ", $html2);
		return array($html1, $html2);
	}


	/**
	 * Method to retrieve mappings of CORE fields (Names to Types and reverse)
	 *
	 * @return object
	 * @since 1.5
	 */
	static function getJCoreFields($ffield=NULL, $map_maintext_to_introtext=false, $reverse=false) {
		if(!$reverse)  // MAPPING core fields NAMEs => core field TYPEs
		{
			$flexifield = array(
				'title'=>'title',
				'categories'=>'categories',
				'tags'=>'tags',
				'text'=>'maintext',
				'created'=>'created',
				'created_by'=>'createdby',
				'modified'=>'modified',
				'modified_by'=>'modifiedby',
				'hits'=>'hits',
				'document_type'=>'type',
				'version'=>'version',
				'state'=>'state'
			);
			if ($map_maintext_to_introtext)
			{
				$flexifield['introtext'] = 'maintext';
			}
		}
		else    // MAPPING core field TYPEs => core fields NAMEs
		{
			$flexifield = array(
				'title'=>'title',
				'categories'=>'categories',
				'tags'=>'tags',
				'maintext'=>'text',
				'created'=>'created',
				'createdby'=>'created_by',
				'modified'=>'modified',
				'modifiedby'=>'modified_by',
				'hits'=>'hits',
				'type'=>'document_type',
				'version'=>'version',
				'state'=>'state'
			);
			if ($map_maintext_to_introtext)
			{
				$flexifield['maintext'] = 'introtext';
			}
		}
		if($ffield===NULL) return $flexifield;
		return isset($flexifield[$ffield])?$flexifield[$ffield]:NULL;
	}

	static function getFlexiFieldId($jfield=NULL) {
		$flexifields = array(
			'introtext'=>1,
			'text'=>1,
			'created'=>2,
			'created_by'=>3,
			'modified'=>4,
			'modified_by'=>5,
			'title'=>6,
			'hits'=>7,
			'version'=>9,
			'state'=>10,
			'catid'=>13,
		);
		if($jfield===NULL) return $flexifields;
		return isset($flexifields[$jfield])?$flexifields[$jfield]:0;
	}

	static function getFlexiField($jfield=NULL) {
		$flexifields = array(
			'introtext'=>'text',
			'fulltext'=>'text',
			'created'=>'created',
			'created_by'=>'createdby',
			'modified'=>'modified',
			'modified_by'=>'modifiedby',
			'title'=>'title',
			'hits'=>'hits',
			'version'=>'version',
			'state'=>'state'
		);
		if($jfield===NULL) return $flexifields;
		return isset($flexifields[$jfield])?$flexifields[$jfield]:0;
	}

	static function getTypesList()
	{
		$db = JFactory::getDBO();

		$query = 'SELECT id, name'
		. ' FROM #__flexicontent_types'
		. ' WHERE published = 1'
		;

		$db->setQuery($query);
		$types = $db->loadAssocList('id');

		return $types;
	}


	/**
	 * Displays a list of the available access view levels
	 *
	 * @param	string	The form field name.
	 * @param	string	The name of the selected section.
	 * @param	string	Additional attributes to add to the select field.
	 * @param	mixed	True to add "All Sections" option or and array of option
	 * @param	string	The form field id
	 *
	 * @return	string	The required HTML for the SELECT tag.
	 */
	static function userlevel($name, $selected, $attribs = '', $params = true, $id = false, $createlist = true) {
		static $options;
		if(!$options) {
			$db		= JFactory::getDbo();
			$query	= $db->getQuery(true);
			$query->select('a.id AS value, a.title AS text');
			$query->from('#__viewlevels AS a');
			if (!$createlist) {
				$query->where('a.id="'.$selected.'"');
			}
			$query->group('a.id');
			$query->order('a.ordering ASC');
			$query->order('`title` ASC');

			// Get the options.
			$db->setQuery($query);
			$options = $db->loadObjectList();

			// Check for a database error.
			if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
			if ( !$options ) return null;

			if (!$createlist) {
				return $options[0]->text;  // return ACCESS LEVEL NAME
			}

			// If params is an array, push these options to the array
			if (is_array($params)) {
				$options = array_merge($params,$options);
			}
			// If all levels is allowed, push it into the array.
			elseif ($params) {
				//array_unshift($options, JHtml::_('select.option', '', JText::_('JOPTION_ACCESS_SHOW_ALL_LEVELS')));
			}
		}

		return JHtml::_('select.genericlist', $options, $name,
			array(
				'list.attr' => $attribs,
				'list.select' => $selected,
				'id' => $id
			)
		);
	}
	
	
	/*
	 * Method to create a Tabset for given label-html arrays
	 * param  string			$date
	 * return boolean			true if valid date, false otherwise
	 */
	static function createFieldTabber( &$field_html, &$field_tab_labels, $class )
	{
		$not_in_tabs = "";

		$output = "<!-- tabber start --><div class='fctabber ".$class."'>"."\n";

		foreach ($field_html as $i => $html) {
			// Hide field when it has no label, and skip creating tab
			$no_label = ! isset( $field_tab_labels[$i] );
			$not_in_tabs .= $no_label ? "<div style='display:none!important'>".$field_html[$i]."</div>" : "";
			if ( $no_label ) continue;

			$output .= "	<div class='tabbertab'>"."\n";
			$output .= "		<h3 class='tabberheading'>".$field_tab_labels[$i]."</h3>"."\n";   // Current TAB LABEL
			$output .= "		".$not_in_tabs."\n";                        // Output hidden fields (no tab created), by placing them inside the next appearing tab
			$output .= "		".$field_html[$i]."\n";                     // Current TAB CONTENTS
			$output .= "	</div>"."\n";

			$not_in_tabs = "";     // Clear the hidden fields variable
		}
		$output .= "</div><!-- tabber end -->";
		$output .= $not_in_tabs;      // Output ENDING hidden fields, by placing them outside the tabbing area
		return $output;
	}

	static function addToolBarButton($text='Button Text', $name='btnname', $full_js='', $err_msg='', $confirm_msg='', $task='btntask', $extra_js='', $list=true, $menu=true, $confirm=true)
	{
		$toolbar = JToolBar::getInstance('toolbar');
		$text  = JText::_( $text );
		$class = 'icon-32-'.$name;

		if ( !$full_js )
		{
			$err_msg = $err_msg ? $err_msg : JText::sprintf( 'FLEXI_SELECT_LIST_ITEMS_TO', $name );
			$err_msg = addslashes($err_msg);
			$confirm_msg = $confirm_msg ? $confirm_msg : JText::_('FLEXI_ARE_YOU_SURE');

			$full_js = $extra_js ."; submitbutton('$task');";
			if ($confirm) {
				$full_js = "if (confirm('".$confirm_msg."')) { ".$full_js." }";
			}
			if (!$menu) {
				$full_js = "hideMainMenu(); " . $full_js;
			}
			if ($list) {
				$full_js = "if (document.adminForm.boxchecked.value==0) { alert('".$err_msg."') ;} else { ".$full_js." }";
			}
		}
		$full_js = "javascript: $full_js";

		$button_html	= "<a href=\"#\" onclick=\"$full_js\" class=\"toolbar\">\n";
		$button_html .= "<span class=\"$class\" title=\"$text\">\n";
		$button_html .= "</span>\n";
		$button_html	.= "$text\n";
		$button_html	.= "</a>\n";

		$toolbar->appendButton('Custom', $button_html, 'archive');
	}
	
	
	// ************************************************************************
	// Calculate CSS classes needed to add special styling markups to the items
	// ************************************************************************
	static function	calculateItemMarkups($items, $params)
	{
		global $globalcats;
		global $globalnoroute;
		$globalnoroute = !is_array($globalnoroute) ? array() : $globalnoroute;
		
		$db   = JFactory::getDBO();
		$user = JFactory::getUser();
		$aids = FLEXI_J16GE ? $user->getAuthorisedViewLevels() : array((int) $user->get('aid'));
		
		
		// **************************************
		// Get configuration about markups to add
		// **************************************
		
		// Get addcss parameters
		$mu_addcss_cats = $params->get('mu_addcss_cats', array('featured'));
		$mu_addcss_cats = FLEXIUtilities::paramToArray($mu_addcss_cats);
		$mu_addcss_acclvl = $params->get('mu_addcss_acclvl', array('needed_acc', 'obtained_acc'));
		$mu_addcss_acclvl = FLEXIUtilities::paramToArray($mu_addcss_acclvl);
		$mu_addcss_radded = $params->get('mu_addcss_radded', 0);
		
		// Calculate addcss flags
		$add_featured_cats = in_array('featured', $mu_addcss_cats);
		$add_other_cats    = in_array('other', $mu_addcss_cats);
		$add_no_acc        = in_array('no_acc', $mu_addcss_acclvl);
		$add_free_acc      = in_array('free_acc', $mu_addcss_acclvl);
		$add_needed_acc    = in_array('needed_acc', $mu_addcss_acclvl);
		$add_obtained_acc  = in_array('obtained_acc', $mu_addcss_acclvl);
		
		// Get addtext parameters
		$mu_addtext_cats   = $params->get('mu_addtext_cats', 1);
		$mu_addtext_acclvl = $params->get('mu_addtext_acclvl', array('no_acc', 'free_acc', 'needed_acc', 'obtained_acc'));
		$mu_addtext_acclvl = FLEXIUtilities::paramToArray($mu_addtext_acclvl);
		$mu_addtext_radded = $params->get('mu_addtext_radded', 1);
		
		// Calculate addtext flags
		$add_txt_no_acc       = in_array('no_acc', $mu_addtext_acclvl);
		$add_txt_free_acc     = in_array('free_acc', $mu_addtext_acclvl);
		$add_txt_needed_acc   = in_array('needed_acc', $mu_addtext_acclvl);
		$add_txt_obtained_acc = in_array('obtained_acc', $mu_addtext_acclvl);
		
		$mu_add_condition_obtainded_acc = $params->get('mu_add_condition_obtainded_acc', 1);
		
		$mu_no_acc_text   = JText::_( $params->get('mu_no_acc_text',   'FLEXI_MU_NO_ACC') );
		$mu_free_acc_text = JText::_( $params->get('mu_free_acc_text', 'FLEXI_MU_NO_ACC') );
		
		
		// *******************************
		// Prepare data needed for markups
		// *******************************
		
		// a. Get Featured categories and language filter their titles
		$featured_cats_parent = $params->get('featured_cats_parent', 0);
		$featured_cats = array();
		if ( $add_featured_cats && $featured_cats_parent )
		{
			$where[] = isset($globalcats[$featured_cats_parent])  ?
				'id IN (' . $globalcats[$featured_cats_parent]->descendants . ')' :
				'parent_id = '. $featured_cats_parent
				;
			$query = 'SELECT c.id'
				. ' FROM #__categories AS c'
				. (count($where) ? ' WHERE ' . implode( ' AND ', $where ) : '')
				;
			$db->setQuery($query);
			
			$featured_cats = FLEXI_J16GE ? $db->loadColumn() : $db->loadResultArray();
			$featured_cats = $featured_cats ? array_flip($featured_cats) : array();
			
			foreach ($featured_cats as $featured_cat => $i)
			{
				$featured_cats_titles[$featured_cat] = JText::_($globalcats[$featured_cat]->title);
			}
		}
		
		
		// b. Get Access Level names (language filter them)
		if ( $add_needed_acc || $add_obtained_acc )
		{
			if (FLEXI_J16GE) {
				$db->setQuery('SELECT id, title FROM #__viewlevels');
				$_arr = $db->loadObjectList();
				$access_names = array();
				foreach ($_arr as $o) $access_names[$o->id] = JText::_($o->title);
			} else {
				$access_names = array(0=>'Public', 1=>'Registered', 2=>'Special');
			}
		}
		
		
		// c. Calculate creation time intervals
		if ( $mu_addcss_radded )
		{
		  $nowdate_secs = time();
			$ra_timeframes = $params->get('mu_ra_timeframe_intervals', '24h,2d,7d,1m,3m,1y,3y');
			$ra_timeframes = preg_split("/\s*,\s*/u", $ra_timeframes);
			
			$ra_names = $params->get('mu_ra_timeframe_names', '24h,2d,7d,1m,3m,1y,3y');
			$ra_names = preg_split("/\s*,\s*/u", $ra_names);
			
			$unit_hour_map = array('h'=>1, 'd'=>24, 'm'=>24*30, 'y'=>24*365);
			$unit_word_map = array('h'=>'hours', 'd'=>'days', 'm'=>'months', 'y'=>'years');
			$unit_text_map = array(
				'h'=>'FLEXI_MU_HOURS', 'd'=>'FLEXI_MU_DAYS', 'm'=>'FLEXI_MU_MONTHS', 'y'=>'FLEXI_MU_YEARS'
			);
			foreach($ra_timeframes as $i => $timeframe) {
				$unit = substr($timeframe, -1);
				if ( !isset($unit_hour_map[$unit]) ) {
					echo "Improper timeframe ': ".$timeframe."' for recently added content, please fix in configuration";
					continue;
				}
				$timeframe  = (int) $timeframe;
				$ra_css_classes[$i] = '_item_added_within_' . $timeframe . $unit_word_map[$unit];
				$ra_timeframe_secs[$i] = $timeframe * $unit_hour_map[$unit] * 3600;
				$ra_timeframe_text[$i] = @ $ra_names[$i] ? JText::_($ra_names[$i]) : JText::sprintf($unit_text_map[$unit], $timeframe);
			}
		}
		
		
		// **********************************
		// Create CSS markup classes per item
		// **********************************
		$public_acclvl = FLEXI_J16GE ? 1 : 0;
		foreach ($items as $item) 
		{
			$item->css_markups = array();
			
			
			// Category markups
			if ( $add_featured_cats || $add_other_cats ) foreach ($item->categories as $item_cat) {
				$is_featured_cat = isset( $featured_cats[$item_cat->id] );
				
				if ( $is_featured_cat && !$add_featured_cats  ) continue;   // not adding featured cats
				if ( !$is_featured_cat && !$add_other_cats  )   continue;   // not adding other cats
				if ( in_array($item_cat->id, $globalnoroute) )	continue;   // non-linkable/routable 'special' category
				
				$item->css_markups['itemcats'][] = '_itemcat_'.$item_cat->id;
				$item->ecss_markups['itemcats'][] = ($is_featured_cat ? ' mu_featured_cat' : ' mu_normal_cat') . ($mu_addtext_cats ? ' mu_has_text' : '');
				$item->title_markups['itemcats'][] = $mu_addtext_cats  ?  ($is_featured_cat ? $featured_cats_titles[$item_cat->id] : $globalcats[$item_cat->id]->title)  :  '';
			}
			
			
			// Timeframe markups
			if ($mu_addcss_radded) {
				$item_timeframe_secs = $nowdate_secs - strtotime($item->created);
				$mr = -1;
				
				foreach($ra_timeframe_secs as $i => $timeframe_secs) {
					// Check if item creation time has surpassed this time frame
					if ( $item_timeframe_secs > $timeframe_secs) continue;
					
					// Check if this time frame is more recent than the best one found so far
					if ($mr != -1 && $timeframe_secs > $ra_timeframe_secs[$mr]) continue;
					
					// Use current time frame
					$mr = $i;
			  }
				if ($mr >= 0) {
					$item->css_markups['timeframe'][] = $ra_css_classes[$mr];
					$item->ecss_markups['timeframe'][] = ' mu_ra_timeframe' . ($mu_addtext_radded ? ' mu_has_text' : '');
					$item->title_markups['timeframe'][] = $mu_addtext_radded ? $ra_timeframe_text[$mr] : '';
				}
			}
			
			
			// Get item's access levels if this is needed
			if ($add_free_acc || $add_needed_acc || $add_obtained_acc) {
				$all_acc_lvls = array();
				$all_acc_lvls[] = $item->access;
				$all_acc_lvls[] = $item->category_access;
				$all_acc_lvls[] = $item->type_access;
				$all_acc_lvls = array_unique($all_acc_lvls);
			}
			
			
			// No access markup
			if ($add_no_acc && !$item->has_access) {
				$item->css_markups['access'][]   = '_item_no_access';
				$item->ecss_markups['access'][] =  ($add_txt_no_acc ? ' mu_has_text' : '');
				$item->title_markups['access'][] = $add_txt_no_acc ? $mu_no_acc_text : '';
			}
			
			
			// Free access markup, Add ONLY if item has a single access level the public one ...
			if ( $add_free_acc && $item->has_access && count($all_acc_lvls)==1 && $public_acclvl == reset($all_acc_lvls) )
			{
				$item->css_markups['access'][]   = '_item_free_access';
				$item->ecss_markups['access'][]  = $add_txt_free_acc ? ' mu_has_text' : '';
				$item->title_markups['access'][] = $add_txt_free_acc ? $mu_free_acc_text : '';
			}
			
			
			// Needed / Obtained access levels markups
			if ($add_needed_acc || $add_obtained_acc)
			{
				foreach($all_acc_lvls as $all_acc_lvl)
				{
					if ($public_acclvl == $all_acc_lvl) continue;  // handled separately above
					
					$has_acclvl = FLEXI_J16GE ? in_array($all_acc_lvl, $aids) : $all_acc_lvl <= $aids;
					if (!$has_acclvl) {
						if (!$add_needed_acc) continue;   // not adding needed levels
						$item->css_markups['access'][] = '_acclvl_'.$all_acc_lvl;
						$item->ecss_markups['access'][] = ' mu_needed_acclvl' . ($add_txt_needed_acc ? ' mu_has_text' : '');
						$item->title_markups['access'][] = $add_txt_needed_acc ? $access_names[$all_acc_lvl] : '';
					} else {
						if (!$add_obtained_acc) continue; // not adding obtained levels
						if ($mu_add_condition_obtainded_acc==0 && !$item->has_access) continue;  // do not add obtained level markups if item is inaccessible
						$item->css_markups['access'][] = '_acclvl_'.$all_acc_lvl;
						$item->ecss_markups['access'][] = ' mu_obtained_acclvl' . ($add_txt_obtained_acc ? ' mu_has_text' : '');
						$item->title_markups['access'][] = $add_txt_obtained_acc ? $access_names[$all_acc_lvl] : '';
					}
				}
			}
		}
	}
	
}

class flexicontent_upload
{
	static function makeSafe($file) {//The range \xE01-\xE5B is thai language.
		$file = str_replace(" ", "", $file);
		$regex = array('#(\.){2,}#', '#[^A-Za-z0-9\xE01-\xE5B\.\_\- ]#', '#^\.#');
		//$regex = array('#(\.){2,}#', '#[^A-Za-z0-9\.\_\- ]#', '#^\.#');
		return preg_replace($regex, '', $file);
	}


	/**
	 * Gets the extension of a file name
	 *
	 * @param string $file The file name
	 * @return string The file extension
	 * @since 1.5
	 */
	static function getExt($file) {
		$len = strlen($file);
		$params = JComponentHelper::getParams( 'com_flexicontent' );
		$exts = $params->get('upload_extensions');
		$exts = str_replace(' ', '', $exts);
		$exts = explode(",", $exts);
		//$exts = array('pdf', 'odt', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'tar.gz');
		$ext = '';
		for($i=$len-1;$i>=0;$i--) {
			$c = $file[$i];
			if($c=='.' && in_array($ext, $exts)) {
				return $ext;
			}
			$ext = $c . $ext;
		}
		$dot = strpos($file, '.') + 1;
		return substr($file, $dot);
	}


	/**
	 * Checks uploaded file
	 *
	 * @param string $file The file name
	 * @param string $err  Set (return) the error string in it
	 * @param string $file view 's parameters
	 * @return string The file extension
	 * @since 1.5
	 */
	static function check(&$file, &$err, &$params)
	{
		if (!$params) {
			$params = JComponentHelper::getParams( 'com_flexicontent' );
		}

		if(empty($file['name'])) {
			$err = 'FLEXI_PLEASE_INPUT_A_FILE';
			return false;
		}

		jimport('joomla.filesystem.file');
		$file['altname'] = $file['name'];
		if ($file['name'] !== JFile::makesafe($file['name'])) {
			//$err = JText::_('FLEXI_WARNFILENAME').','.$file['name'].'|'.JFile::makesafe($file['name'])."<br />";
			//return false;
			$file['name'] = date('Y-m-d-H-i-s').".".flexicontent_upload::getExt($file['name']);
		}

		//check if the imagefiletype is valid
		$format 	= strtolower(flexicontent_upload::getExt($file['name']));

		$allowable = explode( ',', $params->get( 'upload_extensions' ));
		$ignored = explode(',', $params->get( 'ignore_extensions' ));
		if (!in_array($format, $allowable) && !in_array($format,$ignored))
		{
			$err = 'FLEXI_WARNFILETYPE';
			return false;
		}

		//Check filesize
		$maxSize = (int) $params->get( 'upload_maxsize', 0 );
		if ($maxSize > 0 && (int) $file['size'] > $maxSize)
		{
			$err = 'FLEXI_WARNFILETOOLARGE';
			return false;
		}

		$imginfo = null;

		$images = explode( ',', $params->get( 'image_extensions' ));

		if($params->get('restrict_uploads', 1) ) {

			if(in_array($format, $images)) { // if its an image run it through getimagesize
				if(($imginfo = getimagesize($file['tmp_name'])) === FALSE) {
					$err = 'FLEXI_WARNINVALIDIMG';
					return false;
				}

			} else if(!in_array($format, $ignored)) {

				// if its not an image...and we're not ignoring it
				$allowed_mime = explode(',', $params->get('upload_mime'));
				$illegal_mime = explode(',', $params->get('upload_mime_illegal'));

				if(function_exists('finfo_open') && $params->get('check_mime',1)) {
					// We have fileinfo
					$finfo = finfo_open(FILEINFO_MIME);
					$type = finfo_file($finfo, $file['tmp_name']);
					if(strlen($type) && !in_array($type, $allowed_mime) && in_array($type, $illegal_mime)) {
						$err = 'FLEXI_WARNINVALIDMIME';
						return false;
					}
					finfo_close($finfo);

				} else if(function_exists('mime_content_type') && $params->get('check_mime',1)) {

					// we have mime magic
					$type = mime_content_type($file['tmp_name']);

					if(strlen($type) && !in_array($type, $allowed_mime) && in_array($type, $illegal_mime)) {
						$err = 'FLEXI_WARNINVALIDMIME';
						return false;
					}

				}
			}
		}
		$xss_check =  JFile::read($file['tmp_name'],false,256);
		$html_tags = array('abbr','acronym','address','applet','area','audioscope','base','basefont',
			'bdo','bgsound','big','blackface','blink','blockquote','body','bq','br','button','caption',
			'center','cite','code','col','colgroup','comment','custom','dd','del','dfn','dir','div','dl','dt',
			'em','embed','fieldset','fn','font','form','frame','frameset','h1','h2','h3','h4','h5','h6','head',
			'hr','html','iframe','ilayer','img','input','ins','isindex','keygen','kbd','label','layer','legend',
			'li','limittext','link','listing','map','marquee','menu','meta','multicol','nobr','noembed','noframes',
			'noscript','nosmartquotes','object','ol','optgroup','option','param','plaintext','pre','rt','ruby','s','samp',
			'script','select','server','shadow','sidebar','small','spacer','span','strike','strong','style','sub','sup','table',
			'tbody','td','textarea','tfoot','th','thead','title','tr','tt','ul','var','wbr','xml','xmp','!DOCTYPE', '!--');
		foreach($html_tags as $tag) {
			// A tag is '<tagname ', so we need to add < and a space or '<tagname>'
			if(stristr($xss_check, '<'.$tag.' ') || stristr($xss_check, '<'.$tag.'>')) {
				$err = 'FLEXI_WARNIEXSS';
				return false;
			}
		}

		return true;
	}

	/**
	* Sanitize the image file name and return an unique string
	*
	* @since 1.0
	*
	* @param string $base_Dir the target directory
	* @param string $filename the unsanitized imagefile name
	*
	* @return string $filename the sanitized and unique file name
	*/
	static function sanitize($base_Dir, $filename)
	{
		jimport('joomla.filesystem.file');

		//check for any leading/trailing dots and remove them (trailing shouldn't be possible cause of the getEXT check)
		$filename = preg_replace( "/^[.]*/", '', $filename );
		$filename = preg_replace( "/[.]*$/", '', $filename ); //shouldn't be necessary, see above

		//we need to save the last dot position cause preg_replace will also replace dots
		$lastdotpos = strrpos( $filename, '.' );

		//replace invalid characters
		$chars = '[^0-9a-zA-Z()_-]';
		$filename 	= strtolower( preg_replace( "/$chars/", '-', $filename ) );

		//get the parts before and after the dot (assuming we have an extension...check was done before)
		$beforedot	= substr( $filename, 0, $lastdotpos );
		$afterdot 	= substr( $filename, $lastdotpos + 1 );

		//make a unique filename for the image and check it is not already taken
		//if it is already taken keep trying till success
		if (JFile::exists( $base_Dir . $beforedot . '.' . $afterdot ))
		{
			$version = 1;
			while( JFile::exists( $base_Dir . $beforedot . '-' . $version . '.' . $afterdot ) )
			{
				$version++;
			}
			//create out of the seperated parts the new filename
			$filename = $beforedot . '-' . $version . '.' . $afterdot;
		} else {
			$filename = $beforedot . '.' . $afterdot;
		}

		return $filename;
	}

	/**
	* Sanitize folders and return an unique string
	*
	* @since 1.5
	*
	* @param string $base_Dir the target directory
	* @param string $foler the unsanitized folder name
	*
	* @return string $foldername the sanitized and unique file name
	*/
	static function sanitizedir($base_Dir, $folder)
	{
		jimport('joomla.filesystem.folder');

		//replace invalid characters
		$chars = '[^0-9a-zA-Z()_-]';
		$folder 	= strtolower( preg_replace( "/$chars/", '-', $folder ) );

		//make a unique folder name for the image and check it is not already taken
		if (JFolder::exists( $base_Dir . $folder ))
		{
			$version = 1;
			while( JFolder::exists( $base_Dir . $folder . '-' . $version )) {
				$version++;
			}
			//create out of the seperated parts the new folder name
			$foldername = $folder . '-' . $version;
		} else {
			$foldername = $folder;
		}

		return $foldername;
	}
}



class flexicontent_tmpl
{
	/**
	 * Parse all FLEXIcontent templates files
	 *
	 * @return 	object	object of templates
	 * @since 1.5
	 */
	static function parseTemplates($tmpldir='')
	{
		jimport('joomla.filesystem.folder');
		jimport('joomla.filesystem.file');
		jimport('joomla.form.form');
		$themes = new stdClass();
		$themes->items = new stdClass();
		$themes->category = new stdClass();

		$tmpldir = $tmpldir ? $tmpldir : JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'templates';
		$templates = JFolder::folders($tmpldir);

		foreach ($templates as $tmpl)
		{
			// Parse & Load ITEM layout of current template
			$tmplxml = $tmpldir.DS.$tmpl.DS.'item.xml';
			if (JFile::exists($tmplxml))
			{
				// Parse the XML file
				if (FLEXI_J30GE) {
					$xml = simplexml_load_file($tmplxml);
					$document = & $xml;
				} else {
					$xml = JFactory::getXMLParser('Simple');
					$xml->loadFile($tmplxml);
					$document = & $xml->document;
				}

				$themes->items->{$tmpl} = new stdClass();
				$themes->items->{$tmpl}->name 		= $tmpl;
				$themes->items->{$tmpl}->view 		= FLEXI_ITEMVIEW;
				$themes->items->{$tmpl}->tmplvar 	= '.items.'.$tmpl;
				$themes->items->{$tmpl}->thumb		= 'components/com_flexicontent/templates/'.$tmpl.'/item.png';
				if (!FLEXI_J16GE) {
					$themes->items->{$tmpl}->params	= new JParameter('', $tmplxml);
				} else {
					// *** This can be serialized and thus Joomla Cache will work
					$themes->items->{$tmpl}->params = FLEXI_J30GE ? $document->asXML() : $document->toString();

					// *** This was moved into the template files of the forms, because JForm contains 'JXMLElement',
					// which extends the PHP built-in Class 'SimpleXMLElement', (built-in Classes cannot be serialized
					// but serialization is used by Joomla 's cache, causing problem with caching the output of this function

					//$themes->items->{$tmpl}->params		= new JForm('com_flexicontent.template.item', array('control' => 'jform', 'load_data' => true));
					//$themes->items->{$tmpl}->params->loadFile($tmplxml);
				}
				if (FLEXI_J30GE) {
					$themes->items->{$tmpl}->author 		= @$document->author;
					$themes->items->{$tmpl}->website 		= @$document->website;
					$themes->items->{$tmpl}->email 			= @$document->email;
					$themes->items->{$tmpl}->license 		= @$document->license;
					$themes->items->{$tmpl}->version 		= @$document->version;
					$themes->items->{$tmpl}->release 		= @$document->release;
					$themes->items->{$tmpl}->description= @$document->description;
					$groups = & $document->fieldgroups;
					$pos    = & $groups->group;
					if ($pos) {
						for ($n=0; $n<count($pos); $n++) {
							$themes->items->{$tmpl}->attributes[$n] = $pos[$n]->attributes();
							$themes->items->{$tmpl}->positions[$n] = $pos[$n]->getName();
						}
					}

					$css     = & $document->cssitem;
					$cssfile = & $css->file;
					if ($cssfile) {
						for ($n=0; $n<count($cssfile); $n++) {
							$themes->items->{$tmpl}->css = new stdClass();
							$themes->items->{$tmpl}->css->$n = 'components/com_flexicontent/templates/'.$tmpl.'/'.$cssfile[$n]->getName();
						}
					}
					$js 		= & $document->jsitem;
					$jsfile	= & $js->file;
					if ($jsfile) {
						for ($n=0; $n<count($jsfile); $n++) {
							$themes->items->{$tmpl}->js = new stdClass();
							$themes->items->{$tmpl}->js->$n = 'components/com_flexicontent/templates/'.$tmpl.'/'.$jsfile[$n]->getName();
						}
					}

				} else {
					$themes->items->{$tmpl}->author 		= @$document->author[0] ? $document->author[0]->data() : '';
					$themes->items->{$tmpl}->website 		= @$document->website[0] ? $document->website[0]->data() : '';
					$themes->items->{$tmpl}->email 			= @$document->email[0] ? $document->email[0]->data() : '';
					$themes->items->{$tmpl}->license 		= @$document->license[0] ? $document->license[0]->data() : '';
					$themes->items->{$tmpl}->version 		= @$document->version[0] ? $document->version[0]->data() : '';
					$themes->items->{$tmpl}->release 		= @$document->release[0] ? $document->release[0]->data() : '';
					$themes->items->{$tmpl}->description= @$document->description[0] ? $document->description[0]->data() : '';
					$groups = $document->getElementByPath('fieldgroups');
					$pos    = & $groups->group;
					if ($pos) {
						for ($n=0; $n<count($pos); $n++) {
							$themes->items->{$tmpl}->attributes[$n] = $pos[$n]->_attributes;
							$themes->items->{$tmpl}->positions[$n] = $pos[$n]->data();
						}
					}

					$css     = $document->getElementByPath('cssitem');
					$cssfile = & $css->file;
					if ($cssfile) {
						for ($n=0; $n<count($cssfile); $n++) {
							$themes->items->{$tmpl}->css = new stdClass();
							$themes->items->{$tmpl}->css->$n = 'components/com_flexicontent/templates/'.$tmpl.'/'.$cssfile[$n]->data();
						}
					}
					$js 		= $document->getElementByPath('jsitem');
					$jsfile	=& $js->file;
					if ($jsfile) {
						for ($n=0; $n<count($jsfile); $n++) {
							$themes->items->{$tmpl}->js = new stdClass();
							$themes->items->{$tmpl}->js->$n = 'components/com_flexicontent/templates/'.$tmpl.'/'.$jsfile[$n]->data();
						}
					}
				}
			}

			// Parse & Load CATEGORY layout of current template
			$tmplxml = $tmpldir.DS.$tmpl.DS.'category.xml';
			if (JFile::exists($tmplxml))
			{
				// Parse the XML file
				if (FLEXI_J30GE) {
					$xml = simplexml_load_file($tmplxml);
					$document = & $xml;
				} else {
					$xml = JFactory::getXMLParser('Simple');
					$xml->loadFile($tmplxml);
					$document = & $xml->document;
				}

				$themes->category->{$tmpl} = new stdClass();
				$themes->category->{$tmpl}->name 		= $tmpl;
				$themes->category->{$tmpl}->view 		= 'category';
				$themes->category->{$tmpl}->tmplvar 	= '.category.'.$tmpl;
				$themes->category->{$tmpl}->thumb		= 'components/com_flexicontent/templates/'.$tmpl.'/category.png';
				if (!FLEXI_J16GE) {
					$themes->category->{$tmpl}->params		= new JParameter('', $tmplxml);
				} else {
					// *** This can be serialized and thus Joomla Cache will work
					$themes->category->{$tmpl}->params = FLEXI_J30GE ? $document->asXML() : $document->toString();

					// *** This was moved into the template files of the forms, because JForm contains 'JXMLElement',
					// which extends the PHP built-in Class 'SimpleXMLElement', (built-in Classes cannot be serialized
					// but serialization is used by Joomla 's cache, causing problem with caching the output of this function

					//$themes->category->{$tmpl}->params		= new JForm('com_flexicontent.template.category', array('control' => 'jform', 'load_data' => true));
					//$themes->category->{$tmpl}->params->loadFile($tmplxml);
				}
				if (FLEXI_J30GE) {
					$themes->category->{$tmpl}->author 		= @$document->author;
					$themes->category->{$tmpl}->website 	= @$document->website;
					$themes->category->{$tmpl}->email 		= @$document->email;
					$themes->category->{$tmpl}->license 	= @$document->license;
					$themes->category->{$tmpl}->version 	= @$document->version;
					$themes->category->{$tmpl}->release 	= @$document->release;
					$themes->category->{$tmpl}->description= @$document->description;

					$groups = & $document->fieldgroups;
					$pos    = & $groups->group;
					if ($pos) {
						for ($n=0; $n<count($pos); $n++) {
							$themes->category->{$tmpl}->attributes[$n] = $pos[$n]->attributes;
							$themes->category->{$tmpl}->positions[$n] = $pos[$n]->getName();
						}
					}
					$css     = & $document->csscategory;
					$cssfile = & $css->file;
					if ($cssfile) {
						for ($n=0; $n<count($cssfile); $n++) {
							$themes->category->{$tmpl}->css = new stdClass();
							$themes->category->{$tmpl}->css->$n = 'components/com_flexicontent/templates/'.$tmpl.'/'.$cssfile[$n]->getName();
						}
					}
					$js     = & $document->jscategory;
					$jsfile = & $js->file;
					if ($jsfile) {
						for ($n=0; $n<count($jsfile); $n++) {
							$themes->category->{$tmpl}->js = new stdClass();
							$themes->category->{$tmpl}->js->$n = 'components/com_flexicontent/templates/'.$tmpl.'/'.$jsfile[$n]->getName();
						}
					}
				} else {
					$themes->category->{$tmpl}->author 		= @$document->author[0] ? $document->author[0]->data() : '';
					$themes->category->{$tmpl}->website 	= @$document->website[0] ? $document->website[0]->data() : '';
					$themes->category->{$tmpl}->email 		= @$document->email[0] ? $document->email[0]->data() : '';
					$themes->category->{$tmpl}->license 	= @$document->license[0] ? $document->license[0]->data() : '';
					$themes->category->{$tmpl}->version 	= @$document->version[0] ? $document->version[0]->data() : '';
					$themes->category->{$tmpl}->release 	= @$document->release[0] ? $document->release[0]->data() : '';
					$themes->category->{$tmpl}->description = @$document->description[0] ? $document->description[0]->data() : '';

						$groups 	= $document->getElementByPath('fieldgroups');
						$pos	 	=& $groups->group;
						if ($pos) {
							for ($n=0; $n<count($pos); $n++) {
								$themes->category->{$tmpl}->attributes[$n] = $pos[$n]->_attributes;
								$themes->category->{$tmpl}->positions[$n] = $pos[$n]->data();
							}
						}
						$css 		= $document->getElementByPath('csscategory');
						$cssfile	=& $css->file;
						if ($cssfile) {
							for ($n=0; $n<count($cssfile); $n++) {
								$themes->category->{$tmpl}->css = new stdClass();
								$themes->category->{$tmpl}->css->$n = 'components/com_flexicontent/templates/'.$tmpl.'/'.$cssfile[$n]->data();
							}
						}
						$js 		= $document->getElementByPath('jscategory');
						$jsfile	=& $js->file;
						if ($jsfile) {
							for ($n=0; $n<count($jsfile); $n++) {
								$themes->category->{$tmpl}->js = new stdClass();
								$themes->category->{$tmpl}->js->$n = 'components/com_flexicontent/templates/'.$tmpl.'/'.$jsfile[$n]->data();
							}
						}
				}

			}
		}
		return $themes;
	}

	static function getTemplates($lang_files = 'all')
	{
		$flexiparams = JComponentHelper::getParams('com_flexicontent');
		$print_logging_info = $flexiparams->get('print_logging_info');

		// Log content plugin and other performance information
		if ($print_logging_info) { global $fc_run_times; $start_microtime = microtime(true); }

		if ( !FLEXI_J30GE && FLEXI_CACHE) {
			// add the templates to templates cache
			$tmplcache = JFactory::getCache('com_flexicontent_tmpl');
			$tmplcache->setCaching(1); 		//force cache
			$tmplcache->setLifeTime(84600); //set expiry to one day
			$tmpls = $tmplcache->call(array('flexicontent_tmpl', 'parseTemplates'));
			$cached = 1;
		}
		else {
			$tmpls = flexicontent_tmpl::parseTemplates();
		}

		// Load Template-Specific language file(s) to override or add new language strings
		if (FLEXI_FISH || FLEXI_J16GE) {
			if ( $lang_files == 'all' ) foreach ($tmpls->category as $tmpl => $d) FLEXIUtilities::loadTemplateLanguageFile( $tmpl );
			else if ( is_array($lang_files) )  foreach ($lang_files as $tmpl) FLEXIUtilities::loadTemplateLanguageFile( $tmpl );
			else if ( is_string($lang_files) && $load_lang ) FLEXIUtilities::loadTemplateLanguageFile( $lang_files );
		}

		if ($print_logging_info) $fc_run_times[$cached ? 'templates_parsing_cached' : 'templates_parsing_noncached'] = round(1000000 * 10 * (microtime(true) - $start_microtime)) / 10;
		return $tmpls;
	}

	static function getThemes($tmpldir='')
	{
		jimport('joomla.filesystem.folder');

		$tmpldir = $tmpldir?$tmpldir:JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'templates';
		$themes = JFolder::folders($tmpldir);

		return $themes;
	}

	/**
	 * Method to get all available fields for a template in a view
	 *
	 * @access public
	 * @return object
	 */
	static function getFieldsByPositions($folder, $type) {
		if ($type=='item') $type='items';

		static $templates;
		if(!isset($templates[$folder])) {
			$templates[$folder] = array();
		}
		if(!isset($templates[$folder][$type])) {
			$db = JFactory::getDBO();
			$query  = 'SELECT *'
					. ' FROM #__flexicontent_templates'
					. ' WHERE template = ' . $db->Quote($folder)
					. ' AND layout = ' . $db->Quote($type)
					;
			$db->setQuery($query);
			$positions = $db->loadObjectList('position');
			foreach ($positions as $pos) {
				$pos->fields = explode(',', $pos->fields);
			}
			$templates[$folder][$type] = & $positions;
		}
		return $templates[$folder][$type];
	}
}

class flexicontent_images
{
	/**
	 * Get file size and icons
	 *
	 * @since 1.5
	 */
	static function BuildIcons($rows)
	{
		jimport('joomla.filesystem.file');

		for ($i=0, $n=count($rows); $i < $n; $i++) {

			$basePath = $rows[$i]->secure ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH;

			if (is_file($basePath.DS.$rows[$i]->filename)) {
				$path = str_replace(DS, '/', JPath::clean($basePath.DS.$rows[$i]->filename));

				$size = filesize($path);

				if ($size < 1024) {
					$rows[$i]->size = $size . ' bytes';
				} else {
					if ($size >= 1024 && $size < 1024 * 1024) {
						$rows[$i]->size = sprintf('%01.2f', $size / 1024.0) . ' Kb';
					} else {
						$rows[$i]->size = sprintf('%01.2f', $size / (1024.0 * 1024)) . ' Mb';
					}
				}
			} else {
				$rows[$i]->size = 'N/A';
			}

			if ($rows[$i]->url == 1)
			{
				$ext = $rows[$i]->ext;
			} else {
				$ext = strtolower(JFile::getExt($rows[$i]->filename));
			}
			switch ($ext)
			{
				// Image
				case 'jpg':
				case 'png':
				case 'gif':
				case 'xcf':
				case 'odg':
				case 'bmp':
				case 'jpeg':
					$rows[$i]->icon = 'components/com_flexicontent/assets/images/mime-icon-16/image.png';
					break;

				// Non-image document
				default:
					$icon = JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'assets'.DS.'images'.DS.'mime-icon-16'.DS.$ext.'.png';
					if (file_exists($icon)) {
						$rows[$i]->icon = 'components/com_flexicontent/assets/images/mime-icon-16/'.$ext.'.png';
					} else {
						$rows[$i]->icon = 'components/com_flexicontent/assets/images/mime-icon-16/unknown.png';
					}
					break;
			}

		}

		return $rows;
	}

}


class FLEXIUtilities
{

	/**
	 * Load Template-Specific language file to override or add new language strings
	 *
	 * @return object
	 * @since 1.5
	 */

	static function loadTemplateLanguageFile( $tmplname='default', $view='' )
	{
		// Check that template name was given
		$tmplname = empty($tmplname) ? 'default' : $tmplname;

		// This is normally component/module/plugin name, we could use 'category', 'items', etc to have a view specific language file
		// e.g. en/en.category.ini, but this is an overkill and make result into duplication of strings ... better all in one file
		$extension = '';  // JRequest::get('view');

		// Current language, we decided to use LL-CC (language-country) format mapping SEF shortcode, e.g. 'en' to 'en-GB'
		$user_lang = flexicontent_html::getUserCurrentLang();
		$languages = FLEXIUtilities::getLanguages($hash='shortcode');
		if ( !isset($languages->$user_lang->code) ) return;  // Language has been disabled
		$language_tag = $languages->$user_lang->code;

		// We will use template folder as BASE of language files instead of joomla's language folder
		// Since FLEXIcontent templates are meant to be user-editable it makes sense to place language files inside them
		$base_dir = JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'templates'.DS.$tmplname;

		// Final use joomla's API to load our template's language files -- (load english template language file then override with current language file)
		JFactory::getLanguage()->load($extension, $base_dir, 'en-GB', $reload=true);        // Fallback to english language template file
		JFactory::getLanguage()->load($extension, $base_dir, $language_tag, $reload=true);  // User's current language template file
	}

	/**
	 * Method to get information of site languages
	 *
	 * @return object
	 * @since 1.5
	 */
	static function getlanguageslist()
	{
		$app = JFactory::getApplication();
		$db = JFactory::getDBO();
		static $languages = null;
		if ($languages) return $languages;

		// ******************
		// Retrieve languages
		// ******************
		if (FLEXI_J16GE) {   // Use J1.6+ language info
			$query = 'SELECT DISTINCT le.*, lc.lang_id as id, lc.image as image_prefix'
					.', CASE WHEN CHAR_LENGTH(lc.title_native) THEN lc.title_native ELSE le.name END as name'
					.' FROM #__extensions as le'
					.' JOIN #__languages as lc ON lc.lang_code=le.element AND lc.published=1'  // INNER Join to get only languages having content entries
					.' WHERE le.type="language" '
					.' GROUP BY le.element';
		} else if (FLEXI_FISH) {   // Use joomfish languages table
			$query = 'SELECT l.* '
				. ( FLEXI_FISH_22GE ? ", lext.* " : "" )
				. ( FLEXI_FISH_22GE ? ", l.lang_id as id " : ", l.id " )
				. ( FLEXI_FISH_22GE ? ", l.lang_code as code, l.sef as shortcode" : ", l.code, l.shortcode" )
				. ( FLEXI_FISH_22GE ? ", CASE WHEN CHAR_LENGTH(l.title) THEN l.title ELSE l.title_native END as name" : ", l.name " )
				. ' FROM #__languages as l'
				. ( FLEXI_FISH_22GE ? ' LEFT JOIN #__jf_languages_ext as lext ON l.lang_id=lext.lang_id ' : '')
				. ' WHERE '.    (FLEXI_FISH_22GE ? ' l.published=1 ' : ' l.active=1 ')
				. ' ORDER BY '. (FLEXI_FISH_22GE ? ' lext.ordering ASC ' : ' l.ordering ASC ')
					;
		} else {
			JError::raiseWarning(500, 'getlanguageslist(): ERROR no joomfish installed');
			return array();
		}
		$db->setQuery($query);
		$languages = $db->loadObjectList('id');
		//echo "<pre>"; print_r($languages); echo "</pre>"; exit;
		if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
		if ( !$languages )  return array();


		// *********************
		// Calculate image paths
		// *********************
		if (FLEXI_J16GE)  {  // FLEXI_J16GE, use J1.6+ images
			$imgpath	= $app->isAdmin() ? '../images/':'images/';
			$mediapath	= $app->isAdmin() ? '../media/mod_languages/images/' : 'media/mod_languages/images/';
		} else {      // Use joomfish images
			$imgpath	= $app->isAdmin() ? '../images/':'images/';
			$mediapath	= $app->isAdmin() ? '../components/com_joomfish/images/flags/' : 'components/com_joomfish/images/flags/';
		}


		// ************************
		// Prepare language objects
		// ************************
		if (FLEXI_J16GE)  // FLEXI_J16GE, based on J1.6+ language data and images
		{
			$lang_all = new stdClass();
			$lang_all->code = '*';
			$lang_all->name = 'All';
			$lang_all->shortcode = '*';
			$lang_all->id = 0;
			$_languages = array( 0 => $lang_all);

			foreach ($languages as $lang) {
				// Calculate/Fix languages data
				$lang->code = $lang->element;
				$lang->shortcode = substr($lang->code, 0, strpos($lang->code,'-'));
				//$lang->id = $lang->extension_id;
				$image_prefix = $lang->image_prefix ? $lang->image_prefix : $lang->shortcode;
				// $lang->image, holds a custom image path
				$lang->imgsrc = @$lang->image ? $imgpath . $lang->image : $mediapath . $image_prefix . '.gif';
				$_languages[$lang->id] = $lang;
			}
			$languages = $_languages;

			// Also prepend '*' (ALL) language to language array
			//echo "<pre>"; print_r($languages); echo "</pre>"; exit;

			// Select language -ALL- if none selected
			//$selected = $selected ? $selected : '*';    // WRONG behavior commented out
		}
		else if (FLEXI_FISH_22GE)  // JoomFish v2.2+
		{
			require_once(JPATH_ROOT.DS.'administrator'.DS.'components'.DS.'com_joomfish'.DS.'helpers'.DS.'extensionHelper.php' );
			foreach ($languages as $lang) {
				// Get image path via helper function
				$lang->imgsrc = JURI::root().JoomfishExtensionHelper::getLanguageImageSource($lang);
			}
		}
		else      // JoomFish until v2.1
		{
			foreach ($languages as $lang) {
				// $lang->image, holds a custom image path
				$lang->imgsrc = @$lang->image ? $imgpath . $lang->image : $mediapath . $lang->shortcode . '.gif';
			}
		}

		return $languages;
	}


	/**
	 * Method to build an array of languages hashed by id or by language code
	 *
	 * @return object
	 * @since 1.5
	 */
	static function getLanguages($hash='code')
	{
		$langs = new stdClass();

		$languages = FLEXIUtilities::getlanguageslist();
		foreach ($languages as $language)
			$langs->{$language->$hash} = $language;

		return $langs;
	}


	/**
	 * Method to get the last version kept
	 *
	 * @return int
	 * @since 1.5
	 */
	static function &getLastVersions ($id=NULL, $justvalue=false, $force=false)
	{
		static $g_lastversions = NULL;
		static $all_retrieved  = false;

		if(
			$g_lastversions===NULL || $force ||
			($id && !isset($g_lastversions[$id])) ||
			(!$id && !$all_retrieved)
		) {
			if (!$id) $all_retrieved = true;
			$g_lastversions =  array();
			$db = JFactory::getDBO();
			$query = "SELECT item_id as id, max(version_id) as version"
									." FROM #__flexicontent_versions"
									." WHERE 1"
									.($id ? " AND item_id=".(int)$id : "")
									." GROUP BY item_id";
			$db->setQuery($query);
			$rows = $db->loadAssocList('id');
			foreach($rows as $row_id => $row) {
				$g_lastversions[$row_id] = $row;
			}
			unset($rows);
		}

		// Special case (version number of new item): return version zero
		if (!$id && $justvalue) { $v = 0; return $v; }

		// an item id was given return item specific data
		if ($id) {
			$return = $justvalue ? @$g_lastversions[$id]['version'] : @$g_lastversions[$id];
			return $return;
		}

		// no item id was given return all version data
		return $g_lastversions;
	}


	static function &getCurrentVersions ($id=NULL, $justvalue=false, $force=false)
	{
		static $g_currentversions;  // cache ...

		if( $g_currentversions==NULL || $force )
		{
			$db = JFactory::getDBO();
			if (!FLEXI_J16GE) {
				$query = "SELECT i.id, i.version FROM #__content AS i"
					." WHERE i.sectionid=".FLEXI_SECTION
					. ($id ? " AND i.id=".(int)$id : "")
					;
			} else {
				$query = "SELECT i.id, i.version FROM #__content as i"
						. " JOIN #__categories AS c ON i.catid=c.id"
						. " WHERE c.extension='".FLEXI_CAT_EXTENSION."'"
						. ($id ? " AND i.id=".(int)$id : "")
						;
			}
			$db->setQuery($query);
			$rows = $db->loadAssocList();
			$g_currentversions = array();
			foreach($rows as $row) {
				$g_currentversions[$row["id"]] = $row;
			}
			unset($rows);
		}

		// Special case (version number of new item): return version zero
		if (!$id && $justvalue) { $v = 0; return $v; }

		// an item id was given return item specific data
		if($id) {
			$return = $justvalue ? @$g_currentversions[$id]['version'] : @$g_currentversions[$id];
			return $return;
		}

		// no item id was given return all version data
		return $g_currentversions;
	}


	static function &getLastItemVersion($id)
	{
		$db = JFactory::getDBO();
		$query = 'SELECT max(version) as version'
				.' FROM #__flexicontent_items_versions'
				.' WHERE item_id = ' . (int)$id
				;
		$db->setQuery($query, 0, 1);
		$lastversion = $db->loadResult();

		return (int)$lastversion;
	}


	static function &currentMissing()
	{
		static $status;
		if(!$status) {
			$db = JFactory::getDBO();
			$query = "SELECT c.id,c.version,iv.version as iversion FROM #__content as c "
				." LEFT JOIN #__flexicontent_items_versions as iv ON c.id=iv.item_id AND c.version=iv.version"
				.(FLEXI_J16GE ? " JOIN #__categories as cat ON c.catid=cat.id" : "")
				." WHERE c.version > '1' AND iv.version IS NULL"
				.(!FLEXI_J16GE ? " AND sectionid='".FLEXI_SECTION."'" : " AND cat.extension='".FLEXI_CAT_EXTENSION."'")
				." LIMIT 0,1";
			$db->setQuery($query);
			$rows = $db->loadObjectList("id");
			if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');

			$rows = is_array($rows) ? $rows : array();
			$status = false;
			if(count($rows)>0) {
				$status = true;
			}
			unset($rows);
		}
		return $status;
	}


	/**
	 * Method to get the first version kept
	 *
	 * @return int
	 * @since 1.5
	 */
	static function &getFirstVersion($id, $max, $current_version)
	{
		$db = JFactory::getDBO();
		$query = 'SELECT version_id'
				.' FROM #__flexicontent_versions'
				.' WHERE item_id = ' . (int)$id
				.' AND version_id!=' . (int)$current_version
				.' ORDER BY version_id DESC'
				;
		$db->setQuery($query, ($max-1), 1);
		$firstversion = $db->loadResult();
		return $firstversion;
	}


	/**
	 * Method to get the versions count
	 *
	 * @return int
	 * @since 1.5
	 */
	static function &getVersionsCount($id)
	{
		$db = JFactory::getDBO();
		$query = 'SELECT COUNT(*)'
				.' FROM #__flexicontent_versions'
				.' WHERE item_id = ' . (int)$id
				;
		$db->setQuery($query);
		$versionscount = $db->loadResult();

		return $versionscount;
	}


	static function doPlgAct()
	{
		$plg = JRequest::getVar('plg');
		$act = JRequest::getVar('act');
		if($plg && $act) {
			$plgfolder = !FLEXI_J16GE ? '' : DS.strtolower($plg);
			$path = JPATH_ROOT.DS.'plugins'.DS.'flexicontent_fields'.$plgfolder.DS.strtolower($plg).'.php';
			if(file_exists($path)) require_once($path);
			$class = "plgFlexicontent_fields{$plg}";
			if(class_exists($class) && in_array($act, get_class_methods($class))) {
				//call_user_func("$class::$act");
				call_user_func(array($class, $act));
			}
		}
	}


	static function getCache($group='', $client=0)
	{
		$conf = JFactory::getConfig();
		//$client = 0;//0 is site, 1 is admin
		$options = array(
			'defaultgroup'	=> $group,
			'storage' 		=> $conf->get('cache_handler', ''),
			'caching'		=> true,
			'cachebase'		=> ($client == 1) ? JPATH_ADMINISTRATOR . '/cache' : $conf->get('cache_path', JPATH_SITE . '/cache')
		);

		jimport('joomla.cache.cache');
		$cache = JCache::getInstance('', $options);
		return $cache;
	}


	static function call_FC_Field_Func( $fieldtype, $func, $args=null )
	{
		static $fc_plgs;

		if ( !isset( $fc_plgs[$fieldtype] ) ) {
			// 1. Load Flexicontent Field (the Plugin file) if not already loaded
			$plgfolder = !FLEXI_J16GE ? '' : DS.strtolower($fieldtype);
			$path = JPATH_ROOT.DS.'plugins'.DS.'flexicontent_fields'.$plgfolder.DS.strtolower($fieldtype).'.php';
			if(file_exists($path)) require_once($path);
			else {
				JFactory::getApplication()->enqueueMessage(nl2br("While calling field method: $func(): cann find field type: $fieldtype. This is internal error or wrong field name"),'error');
				return;
			}

			// 2. Create plugin instance
			$class = "plgFlexicontent_fields{$fieldtype}";
			if( class_exists($class) ) {
				// Create class name of the plugin
				$className = 'plg'.'flexicontent_fields'.$fieldtype;
				// Create a plugin instance
				$dispatcher = JDispatcher::getInstance();
				$fc_plgs[$fieldtype] =  new $className($dispatcher, array());
				// Assign plugin parameters, (most FLEXI plugins do not have plugin parameters), CHECKING if parameters exist
				$plugin_db_data = JPluginHelper::getPlugin('flexicontent_fields',$fieldtype);
				$fc_plgs[$fieldtype]->params = FLEXI_J16GE ? new JRegistry( @$plugin_db_data->params ) : new JParameter( @$plugin_db_data->params );
			} else {
				JFactory::getApplication()->enqueueMessage(nl2br("Could not find class: $className in file: $path\n Please correct field name"),'error');
				return;
			}
		}

		// 3. Execute only if it exists
		if (!$func) return;
		$class = "plgFlexicontent_fields{$fieldtype}";
		if(in_array($func, get_class_methods($class))) {
			return call_user_func_array(array($fc_plgs[$fieldtype], $func), $args);
		}
	}


	/* !!! FUNCTION NOT DONE YET */
	static function call_Content_Plg_Func( $plgname, $func, $args=null )
	{
		static $content_plgs;

		if ( !isset( $content_plgs[$plgname] ) ) {
			// 1. Load Flexicontent Field (the Plugin file) if not already loaded
			$plgfolder = !FLEXI_J16GE ? '' : DS.strtolower($plgname);
			$path = JPATH_ROOT.DS.'plugins'.DS.'content'.$plgfolder.DS.strtolower($plgname).'.php';
			if(file_exists($path)) require_once($path);
			else {
				JFactory::getApplication()->enqueueMessage(nl2br("Cannot load CONTENT Plugin: $plgname\n Plugin may have been uninistalled"),'error');
				return;
			}

			// 2. Create plugin instance
			$class = "plgContent{$plgname}";
			if( class_exists($class) ) {
				// Create class name of the plugin
				$className = 'plg'.'content'.$plgname;
				// Create a plugin instance
				$dispatcher = JDispatcher::getInstance();
				$content_plgs[$plgname] =  new $className($dispatcher, array());
				// Assign plugin parameters, (most FLEXI plugins do not have plugin parameters)
				$plugin_db_data = JPluginHelper::getPlugin('content',$plgname);
				$content_plgs[$plgname]->params = FLEXI_J16GE ? new JRegistry( @$plugin_db_data->params ) : new JParameter( @$plugin_db_data->params );
			} else {
				JFactory::getApplication()->enqueueMessage(nl2br("Could not find class: $className in file: $path\n Please correct field name"),'error');
				return;
			}
		}

		// 3. Execute only if it exists
		$class = "plgContent{$plgname}";
		if(in_array($func, get_class_methods($class))) {
			return call_user_func_array(array($content_plgs[$plgname], $func), $args);
		}
	}


	/**
	 * Return unicode char by its code
	 * Credits: ?
	 *
	 * @param int $dec
	 * @return utf8 char
	 */
	static function unichr($dec) {
	  if ($dec < 128) {
	    $utf = chr($dec);
	  } else if ($dec < 2048) {
	    $utf = chr(192 + (($dec - ($dec % 64)) / 64));
	    $utf .= chr(128 + ($dec % 64));
	  } else {
	    $utf = chr(224 + (($dec - ($dec % 4096)) / 4096));
	    $utf .= chr(128 + ((($dec % 4096) - ($dec % 64)) / 64));
	    $utf .= chr(128 + ($dec % 64));
	  }
	  return $utf;
	}


	/**
	 * Return unicode code of a utf8 char
	 * Credits: ?
	 *
	 * @param int $c
	 * @return utf8 ord
	 */
	static function uniord($c) {
		$h = ord($c{0});
		if ($h <= 0x7F) {
			return $h;
		} else if ($h < 0xC2) {
			return false;
		} else if ($h <= 0xDF) {
			return ($h & 0x1F) << 6 | (ord($c{1}) & 0x3F);
		} else if ($h <= 0xEF) {
			return ($h & 0x0F) << 12 | (ord($c{1}) & 0x3F) << 6
			| (ord($c{2}) & 0x3F);
		} else if ($h <= 0xF4) {
			return ($h & 0x0F) << 18 | (ord($c{1}) & 0x3F) << 12
			| (ord($c{2}) & 0x3F) << 6
			| (ord($c{3}) & 0x3F);
		} else {
			return false;
		}
	}


	/**
	 * Return unicode string when giving an array of utf8 ords
	 * Credits: Darien Hager
	 *
	 * @param  $ords   utf8 ord arrray
	 * @return $str    utf8 string
	 */
	static function ords_to_unistr($ords, $encoding = 'UTF-8'){
		// Turns an array of ordinal values into a string of unicode characters
		$str = '';
		for($i = 0; $i < sizeof($ords); $i++){
			// Pack this number into a 4-byte string
			// (Or multiple one-byte strings, depending on context.)
			$v = $ords[$i];
			$str .= pack("N",$v);
		}
		$str = mb_convert_encoding($str,$encoding,"UCS-4BE");
		return($str);
	}


	/**
	 * Return unicode string when giving an array of utf8 ords
	 * Credits: Darien Hager
	 *
	 * @param  $str    utf8 string
	 * @return $ords   utf8 ord arrray
	 */
	static function unistr_to_ords($str, $encoding = 'UTF-8')
	{
		// Turns a string of unicode characters into an array of ordinal values,
		// Even if some of those characters are multibyte.
		$str = mb_convert_encoding($str,"UCS-4BE",$encoding);
		$ords = array();

		// Visit each unicode character
		//for($i = 0; $i < mb_strlen($str,"UCS-4BE"); $i++){
		//for($i = 0; $i < utf8_strlen($str); $i++){
		for($i = 0; $i < JString::strlen($str,"UCS-4BE"); $i++){
			// Now we have 4 bytes. Find their total
			// numeric value.
			$s2 = JString::substr($str,$i,1,"UCS-4BE");
			$val = unpack("N",$s2);
			$ords[] = $val[1];
		}
		return($ords);
	}


	static function count_new_hit(&$item) // If needed to modify params then clone them !! ??
	{
		$params = JComponentHelper::getParams( 'com_flexicontent' );
		if (!$params->get('hits_count_unique', 0)) return 1; // Counting unique hits not enabled

		$db = JFactory::getDBO();
		$visitorip = $_SERVER['REMOTE_ADDR'];  // Visitor IP
		$current_secs = time();  // Current time as seconds since Unix epoch
		if ($item->id==0) {
			JFactory::getApplication()->enqueueMessage(nl2br("Invalid item id or item id is not set in http request"),'error');
			return 1; // Invalid item id ?? (do not try to decrement hits in content table)
		}


		// CHECK RULE 1: Skip if visitor is from the specified ips
		$hits_skip_ips = $params->get('hits_skip_ips', 1);   // Skip ips enabled
		$hits_ips_list = $params->get('hits_ips_list', '127.0.0.1');  // List of ips, by default localhost
		if($hits_skip_ips)
		{
			// consider as blocked ip , if remote address is not set (is this correct behavior?)
			if( !isset($_SERVER['REMOTE_ADDR']) ) return 0;

			$remoteaddr = $_SERVER['REMOTE_ADDR'];
			$ips_array = explode(",", $hits_ips_list);
			foreach($ips_array as $blockedip)
			{
				if (preg_match('/'.trim($blockedip).'/i', $remoteaddr)) return 0;  // found blocked ip, do not count new hit
			}
		}


		// CHECK RULE 2: Skip if visitor is a bot
		$hits_skip_bots = $params->get('hits_skip_bots', 1);  // Skip bots enabled
		$hits_bots_list = $params->get('hits_bots_list', 'bot,spider,crawler,search,libwww,archive,slurp,teoma');   // List of bots
		if($hits_skip_bots)
		{
			// consider as bot , if user agent name is not set (is this correct behavior?)
			if( !isset($_SERVER['HTTP_USER_AGENT']) ) return 0;

			$useragent = $_SERVER['HTTP_USER_AGENT'];
			$bots_array = explode(",", $hits_bots_list);
			foreach($bots_array as $botname)
			{
				if (preg_match('/'.trim($botname).'/i', $useragent)) return 0;  // found bot, do not count new hit
			}
		}

		// CHECK RULE 3: item hit does not exist in current session
		$hit_method = 'use_session';  // 'use_db_table', 'use_session'
		if ($hit_method == 'use_session') {
			$session 	= JFactory::getSession();
			$hit_accounted = false;
			$hit_arr = array();
			if ($session->has('hit', 'flexicontent')) {
				$hit_arr 	= $session->get('hit', array(), 'flexicontent');
				$hit_accounted = isset($hit_arr[$item->id]);
			}
			if (!$hit_accounted) {
				//add hit to session hit array
				$hit_arr[$item->id] = $timestamp = time();  // Current time as seconds since Unix epoc;
				$session->set('hit', $hit_arr, 'flexicontent');
				return 1;
			}

		} else {  // ALTERNATIVE METHOD (above is better, this will be removed?), by using db table to account hits, instead of user session

			// CHECK RULE 3: minimum time to consider as unique visitor aka count hit
			$secs_between_unique_hit = 60 * $params->get('hits_mins_to_unique', 10);  // Seconds between counting unique hits from an IP

			// Try to find matching records for visitor's IP, that is within time limit of unique hit
			$query = "SELECT COUNT(*) FROM #__flexicontent_hits_log WHERE ip=".$db->quote($visitorip)." AND (timestamp + ".$db->quote($secs_between_unique_hit).") > ".$db->quote($current_secs). " AND item_id=". $item->id;
			$db->setQuery($query);
			$result = $db->query();
			if ($db->getErrorNum()) {
				$query_create = "CREATE TABLE #__flexicontent_hits_log (item_id INT PRIMARY KEY, timestamp INT NOT NULL, ip VARCHAR(16) NOT NULL DEFAULT '0.0.0.0')";
				$db->setQuery($query_create);
				$result = $db->query();
				if ($this->_db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($this->_db->getErrorMsg()),'error');
				return 1; // on select error e.g. table created, count a new hit
			}
			$count = $db->loadResult();

			// Log the visit into the hits logging db table
			if(empty($count))
			{
				$query = "INSERT INTO #__flexicontent_hits_log (item_id, timestamp, ip) "
						."  VALUES (".$db->quote($item->id).", ".$db->quote($current_secs).", ".$db->quote($visitorip).")"
						." ON DUPLICATE KEY UPDATE timestamp=".$db->quote($current_secs).", ip=".$db->quote($visitorip);
				$db->setQuery($query);
				$result = $db->query();
				if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
				return 1;  // last visit not found or is beyond time limit, count a new hit
			}
		}

		// Last visit within time limit, do not count new hit
		return 0;
	}


	/*
	 * Method to confirm if a given string is a valid MySQL date
	 * param  string			$date
	 * return boolean			true if valid date, false otherwise
	 */
	static function isSqlValidDate($date)
	{
		$db = JFactory::getDBO();
		$q = "SELECT day(".$db->Quote($date).")";
		$db->setQuery($q);
		$num = $db->loadResult();
		$valid = $num > 0;
		return $valid;
	}

	/*
	 * Converts a string (containing a csv file) into a array of records ( [row][col] )and returns it
	 * @author: Klemen Nagode (in http://stackoverflow.com/)
	 */
	static function csvstring_to_array($string, $field_separator = ',', $enclosure_char = '"', $record_separator = "\n")
	{
		$array = array();   // [row][cols]
		$size = strlen($string);
		$columnIndex = 0;
		$rowIndex = 0;
		$fieldValue="";
		$isEnclosured = false;
		// Field separator
		$fld_sep_start = $field_separator{0};
		$fld_sep_size  = strlen( $field_separator );
		// Record (item) separator
		$rec_sep_start = $record_separator{0};
		$rec_sep_size  = strlen( $record_separator );

		for($i=0; $i<$size;$i++)
		{
			$char = $string{$i};
			$addChar = "";

			if($isEnclosured) {
				if($char==$enclosure_char) {
					if($i+1<$size && $string{$i+1}==$enclosure_char) {
						// escaped char
						$addChar=$char;
						$i++; // dont check next char
					} else {
						$isEnclosured = false;
					}
				} else {
					$addChar=$char;
				}
			}
			else
			{
				if($char==$enclosure_char) {
					$isEnclosured = true;
				} else {
					if( $char==$fld_sep_start && $i+$fld_sep_size < $size && substr($string, $i,$fld_sep_size) == $field_separator ) {
						$i = $i + ($fld_sep_size-1);
						$array[$rowIndex][$columnIndex] = $fieldValue;
						$fieldValue="";

						$columnIndex++;
					} else if( $char==$rec_sep_start && $i+$rec_sep_size < $size && substr($string, $i,$rec_sep_size) == $record_separator ) {
						$i = $i + ($rec_sep_size-1);
						echo "\n";
						$array[$rowIndex][$columnIndex] = $fieldValue;
						$fieldValue="";
						$columnIndex=0;
						$rowIndex++;
					} else {
						$addChar=$char;
					}
				}
			}
			if($addChar!="") {
				$fieldValue.=$addChar;
			}
		}

		if($fieldValue) { // save last field
			$array[$rowIndex][$columnIndex] = $fieldValue;
		}
		return $array;
	}

	/**
	 * Helper method to format a parameter value as array
	 *
	 * @return object
	 * @since 1.5
	 */
	static function paramToArray($value, $regex = "", $filterfunc = "")
	{
		if ($regex) {
			$value = trim($value);
			$value = !$value  ?  array()  :  preg_split($regex, $value);
		}
		if ($filterfunc) {
			array_map($filterfunc, $value);
		}

		if (FLEXI_J16GE && !is_array($value)) {
			$value = explode("|", $value);
			$value = ($value[0]=='') ? array() : $value;
		} else {
			$value = !is_array($value) ? array($value) : $value;
		}
		return $value;
	}

	/**
	 * Suppresses given plugins (= prevents them from triggering)
	 *
	 * @return void
	 * @since 1.5
	 */
	static function suppressPlugins( $name_arr, $action ) {
		static $plgs = array();

		foreach	($name_arr as $name)
		{
			if (!isset($plgs[$name])) {
				JPluginHelper::importPlugin('content', $name);
				$plgs[$name] = JPluginHelper::getPlugin('content', $name);
			}
			if ($plgs[$name] && $action=='suppress') {
				$plgs[$name]->type = '_suppress';
			}
			if ($plgs[$name] && $action=='restore') {
				$plgs[$name]->type = 'content';
			}
		}
	}
}


/*
 * CLASS with common methods for handling interaction with DB
 */
class flexicontent_db
{
	/**
	 * Helper method to execute a query directly, bypassing Joomla DB Layer
	 *
	 * @return object
	 * @since 1.5
	 */
	static function & directQuery($query)
	{
		$db     = JFactory::getDBO();
		$config = JFactory::getConfig();
		$dbprefix = $config->getValue('config.dbprefix');
		$dbtype   = $config->getValue('config.dbtype');

		if (FLEXI_J16GE) {
			$query = $db->replacePrefix($query);
			$db_connection = $db->getConnection();
		} else {
			$query = str_replace("#__", $dbprefix, $query);
			$db_connection = $db->_resource;
		}
		//echo "<pre>"; print_r($query); echo "\n\n";
		
		if ($dbtype == 'mysqli') {
			$result = mysqli_query( $db_connection , $query );
			if ($result===false) throw new Exception('error '.__FUNCTION__.'():: '.mysqli_error($db_connection));
			while($row = mysqli_fetch_object($result)) {
				$data[] = $row;
			}
			mysqli_free_result($result);
		} else if ($dbtype == 'mysql') {
			$result = mysql_query( $query, $db_connection  );
			if ($result===false) throw new Exception('error '.__FUNCTION__.'():: '.mysql_error($db_connection));
			while($row = mysql_fetch_object($result)) {
				$data[] = $row;
			}
			mysql_free_result($result);
		} else {
			throw new Exception( 'unreachable code in '.__FUNCTION__.'(): direct db query, unsupported DB TYPE' );
		}

		return $data;
	}


	/**
	 * Build the order clause of item listings
	 * precedence: $request_var ==> $order ==> $config_param ==> $default_order (& $default_order_dir)
	 * @access private
	 * @return string
	 */
	static function buildItemOrderBy(&$params=null, &$order='', $request_var='orderby', $config_param='orderby', $i_as='i', $rel_as='rel', $default_order='', $default_order_dir='')
	{
		// Use global params ordering if parameters were not given
		if (!$params) $params = JComponentHelper::getParams( 'com_flexicontent' );

		// 1. If forced ordering not given, then use ordering parameters from configuration
		if (!$order) {
			$order = $params->get('orderbycustomfieldid', 0) ? 'field' : $params->get($config_param, 'default');
		}

		// 2. If allowing user ordering override, then get ordering from HTTP request variable
		$order = $request_var && ($request_order = JRequest::getVar($request_var)) ? $request_order : $order;

		if ($order=='commented') {
			if (!file_exists(JPATH_SITE.DS.'components'.DS.'com_jcomments'.DS.'jcomments.php')) {
				echo "jcomments not installed, you need jcomments to use 'Most commented' ordering OR display comments information.<br>\n";
				$order='';
			} 
		}
		
		// 'order' contains a symbolic order name to indicate using the category / global ordering setting
		switch ($order) {
			case 'date': case 'addedrev': /* 2nd is for module */
				$filter_order		= $i_as.'.created';
				$filter_order_dir	= 'ASC';
				break;
			case 'rdate': case 'added': /* 2nd is for module */
				$filter_order		= $i_as.'.created';
				$filter_order_dir	= 'DESC';
				break;
			case 'modified': case 'updated': /* 2nd is for module */
				$filter_order		= $i_as.'.modified';
				$filter_order_dir	= 'DESC';
				break;
			case 'alpha':
				$filter_order		= $i_as.'.title';
				$filter_order_dir	= 'ASC';
				break;
			case 'ralpha': case 'alpharev': /* 2nd is for module */
				$filter_order		= $i_as.'.title';
				$filter_order_dir	= 'DESC';
				break;
			case 'author':
				$filter_order		= 'u.name';
				$filter_order_dir	= 'ASC';
				break;
			case 'rauthor':
				$filter_order		= 'u.name';
				$filter_order_dir	= 'DESC';
				break;
			case 'hits':
				$filter_order		= $i_as.'.hits';
				$filter_order_dir	= 'ASC';
				break;
			case 'rhits': case 'popular': /* 2nd is for module */
				$filter_order		= $i_as.'.hits';
				$filter_order_dir	= 'DESC';
				break;
			case 'order': case 'catorder': /* 2nd is for module */
				$filter_order		= $rel_as.'.catid, '.$rel_as.'.ordering';
				$filter_order_dir	= 'ASC';
				break;

			// SPECIAL case custom field
			case 'field':
				$filter_order = $params->get('orderbycustomfieldint', 0) ? 'CAST(f.value AS UNSIGNED)' : 'f.value';
				$filter_order_dir	= $params->get('orderbycustomfielddir', 'ASC');
				break;

			// NEW ADDED
			case 'random':
				$filter_order = 'RAND()';
				$filter_order_dir	= '';
				break;
			case 'commented':
				$filter_order = 'comments_total';
				$filter_order_dir	= 'DESC';
				break;
			case 'rated':
				$filter_order = 'votes';
				$filter_order_dir	= 'DESC';
				break;
			case 'id':
				$filter_order = $i_as.'.id';
				$filter_order_dir	= 'DESC';
				break;
			case 'rid':
				$filter_order = $i_as.'.id';
				$filter_order_dir	= 'ASC';
				break;

			case 'default':
			default:
				$filter_order     = $default_order ? $default_order : $i_as.'.title';
				$filter_order_dir = $default_order_dir ? $default_order_dir : 'ASC';
				break;
		}

		$orderby 	= ' ORDER BY '.$filter_order.' '.$filter_order_dir;
		$orderby .= ($filter_order!=$i_as.'.title')  ?  ', '.$i_as.'.title'  :  '';   // Order by title after default ordering

		return $orderby;
	}


	/**
	 * Build the order clause of category listings
	 *
	 * @access private
	 * @return string
	 */
	static function buildCatOrderBy(&$params, $order='', $request_var='', $config_param='cat_orderby', $c_as='c', $u_as='u', $default_order='', $default_order_dir='')
	{
		// Use global params ordering if parameters were not given
		if (!$params) $params = JComponentHelper::getParams( 'com_flexicontent' );

		// 1. If forced ordering not given, then use ordering parameters from configuration
		if (!$order) {
			$order = $params->get($config_param, 'default');
		}

		// 2. If allowing user ordering override, then get ordering from HTTP request variable
		$order = $request_var && ($request_order = JRequest::getVar($request_var)) ? $request_order : $order;

		switch ($order) {
			case 'date' :                  // *** J2.5 only ***
				$filter_order		= $c_as.'.created_time';
				$filter_order_dir	= 'ASC';
				break;
			case 'rdate' :                 // *** J2.5 only ***
				$filter_order		= $c_as.'.created_time';
				$filter_order_dir	= 'DESC';
				break;
			case 'modified' :              // *** J2.5 only ***
				$filter_order		= $c_as.'.modified_time';
				$filter_order_dir	= 'DESC';
				break;
			case 'alpha' :
				$filter_order		= $c_as.'.title';
				$filter_order_dir	= 'ASC';
				break;
			case 'ralpha' :
				$filter_order		= $c_as.'.title';
				$filter_order_dir	= 'DESC';
				break;
			case 'author' :                // *** J2.5 only ***
				$filter_order		= $u_as.'.name';
				$filter_order_dir	= 'ASC';
				break;
			case 'rauthor' :               // *** J2.5 only ***
				$filter_order		= $u_as.'.name';
				$filter_order_dir	= 'DESC';
				break;
			case 'hits' :                  // *** J2.5 only ***
				$filter_order		= $c_as.'.hits';
				$filter_order_dir	= 'ASC';
				break;
			case 'rhits' :                 // *** J2.5 only ***
				$filter_order		= $c_as.'.hits';
				$filter_order_dir	= 'DESC';
				break;
			case 'order' :
				$filter_order		= !FLEXI_J16GE ? $c_as.'.ordering' : $c_as.'.lft';
				$filter_order_dir	= 'ASC';
				break;
			case 'default' :
			default:
				$filter_order     = $default_order ? $default_order : $i_as.'.title';
				$filter_order_dir = $default_order_dir ? $default_order_dir : 'ASC';
				break;
		}

		$orderby 	= ' ORDER BY '.$filter_order.' '.$filter_order_dir;
		$orderby .= $filter_order!=$c_as.'.title' ? ', '.$c_as.'.title' : '';   // Order by title after default ordering

		return $orderby;
	}


	/**
	 * Check in a record
	 *
	 * @since	1.5
	 */
	static function checkin($tbl, $redirect_url, & $controller)
	{
		$cid  = JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$pk   = (int)$cid[0];
		$user = JFactory::getUser();
		$controller->setRedirect( $redirect_url, '' );

		static $canCheckin = null;
		if ($canCheckin === null) {
			if (FLEXI_J16GE) {
				$canCheckin = $user->authorise('core.admin', 'checkin');
			} else if (FLEXI_ACCESS) {
				$canCheckin = ($user->gid < 25) ? FAccess::checkComponentAccess('com_checkin', 'manage', 'users', $user->gmid) : 1;
			} else {
				// Only admin or super admin can check-in
				$canCheckin = $user->gid >= 24;
			}
		}

		// Only attempt to check the row in if it exists.
		if ($pk)
		{
			// Get an instance of the row to checkin.
			$table = JTable::getInstance($tbl, '');
			if (!$table->load($pk))
			{
				$controller->setError($table->getError());
				return;// false;
			}

			// Record check-in is allowed if either (a) current user has Global Checkin privilege OR (a) record checked out by current user
			if ($table->checked_out) {
				if ( !$canCheckin && $table->checked_out != $user->id) {
					$controller->setError(JText::_( 'FLEXI_RECORD_CHECKED_OUT_DIFF_USER'));
					return;// false;
				}
			}

			// Attempt to check the row in.
			if (!$table->checkin($pk))
			{
				$controller->setError($table->getError());
				return;// false;
			}
		}

		$controller->setRedirect( $redirect_url, JText::sprintf('FLEXI_RECORD_CHECKED_IN_SUCCESSFULLY', 1) );
		return;// true;
	}

}


function FLEXISubmenu($cando)
{
	$perms   = FlexicontentHelperPerm::getPerm();
	$app     = JFactory::getApplication();
	$session = JFactory::getSession();
	
	// Check access to current management tab
	$not_authorized = isset($perms->$cando) && !$perms->$cando;
	if ( $not_authorized ) {
		$app->redirect('index.php?option=com_flexicontent', JText::_( 'FLEXI_NO_ACCESS' ));
	}
	
	// Get post-installation FLAG (session variable), and current view (HTTP request variable)
	$dopostinstall = $session->get('flexicontent.postinstall');
	$view = JRequest::getVar('view', 'flexicontent');
	
	// Create Submenu, Dashboard (HOME is always added, other will appear only if post-installation tasks are done)
	JSubMenuHelper::addEntry( JText::_( 'FLEXI_HOME' ), 'index.php?option=com_flexicontent', !$view || $view=='flexicontent');
	if ($dopostinstall && version_compare(PHP_VERSION, '5.0.0', '>'))
	{
		JSubMenuHelper::addEntry( JText::_( 'FLEXI_ITEMS' ), 'index.php?option=com_flexicontent&view=items', $view=='items');
		if ($perms->CanTypes)			JSubMenuHelper::addEntry( JText::_( 'FLEXI_TYPES' ), 'index.php?option=com_flexicontent&view=types', $view=='types');
		if ($perms->CanCats) 			JSubMenuHelper::addEntry( JText::_( 'FLEXI_CATEGORIES' ), 'index.php?option=com_flexicontent&view=categories', $view=='categories');
		if ($perms->CanFields) 		JSubMenuHelper::addEntry( JText::_( 'FLEXI_FIELDS' ), 'index.php?option=com_flexicontent&view=fields', $view=='fields');
		if ($perms->CanTags) 			JSubMenuHelper::addEntry( JText::_( 'FLEXI_TAGS' ), 'index.php?option=com_flexicontent&view=tags', $view=='tags');
		if ($perms->CanAuthors)		JSubMenuHelper::addEntry( JText::_( 'FLEXI_AUTHORS' ), 'index.php?option=com_flexicontent&view=users', $view=='users');
	//if ($perms->CanArchives)	JSubMenuHelper::addEntry( JText::_( 'FLEXI_ARCHIVE' ), 'index.php?option=com_flexicontent&view=archive', $view=='archive');
		if ($perms->CanFiles) 		JSubMenuHelper::addEntry( JText::_( 'FLEXI_FILEMANAGER' ), 'index.php?option=com_flexicontent&view=filemanager', $view=='filemanager');
		if ($perms->CanIndex)			JSubMenuHelper::addEntry( JText::_( 'FLEXI_SEARCH_INDEXES' ), 'index.php?option=com_flexicontent&view=search', $view=='search');
		if ($perms->CanTemplates)	JSubMenuHelper::addEntry( JText::_( 'FLEXI_TEMPLATES' ), 'index.php?option=com_flexicontent&view=templates', $view=='templates');
		if ($perms->CanImport)		JSubMenuHelper::addEntry( JText::_( 'FLEXI_IMPORT' ), 'index.php?option=com_flexicontent&view=import', $view=='import');
		if ($perms->CanStats)			JSubMenuHelper::addEntry( JText::_( 'FLEXI_STATISTICS' ), 'index.php?option=com_flexicontent&view=stats', $view=='stats');
	}
}

?>
