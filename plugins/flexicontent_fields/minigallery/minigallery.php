<?php
/**
 * @version 1.0 $Id: minigallery.php 1800 2013-11-01 04:30:57Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.file
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
defined( '_JEXEC' ) or die( 'Restricted access' );

//jimport('joomla.plugin.plugin');
jimport('joomla.event.plugin');

class plgFlexicontent_fieldsMinigallery extends JPlugin
{
	static $field_types = array('minigallery');

	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsMinigallery( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_minigallery', JPATH_ADMINISTRATOR);
	}
	
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		$use_ingroup = 0;  // Not supported  //$field->parameters->get('use_ingroup', 0);
		if ($use_ingroup) $field->formhidden = 3;
		if ($use_ingroup && empty($field->ingroup)) return;
		
		// initialize framework objects and other variables
		$document = JFactory::getDocument();
		$app  = JFactory::getApplication();
		
		
		// ****************
		// Number of values
		// ****************
		$multiple   = $use_ingroup || 1;
		$max_values = $use_ingroup ? 0 : (int) $field->parameters->get( 'max_values', 0 ) ;
		$required   = $field->parameters->get( 'required', 0 ) ;
		$required   = $required ? ' required' : '';
		
		// Input field configuration
		$size = (int) $field->parameters->get( 'size', 30 ) ;
		$client = $app->isAdmin() ? '../' : '';
		$flexiparams = JComponentHelper::getParams('com_flexicontent');
		$mediapath   = $flexiparams->get('media_path', 'components/com_flexicontent/medias');
		
		// Load file data
		if ( !$field->value ) {
			$files_data = array();
			$field->value = array();
		} else {
			$files_data = $this->getFileData( $field->value, $published=false );
			$field->value = array();
			foreach($files_data as $file_id => $file_data) $field->value[] = $file_id;
		}
		
		// CSS classes of value container
		$value_classes  = 'fcfieldval_container valuebox fcfieldval_container_'.$field->id;
		$value_classes .= ' floated';
		
		// Field name and HTML TAG id
		$fieldname = 'custom['.$field->name.'][]';
		//$elementid = 'custom_'.$field->name;
		
		$js = "";
		$css = "";
		
		if ($multiple) // handle multiple records
		{
			// Add the drag and drop sorting feature
			if (!$use_ingroup) $js .= "
			jQuery(document).ready(function(){
				jQuery('#sortables_".$field->id."').sortable({
					handle: '.fcfield-drag-handle',
					containment: 'parent',
					tolerance: 'pointer'
				});
			});
			";
			
			if ($max_values) FLEXI_J16GE ? JText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true) : fcjsJText::script("FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED", true);
			$js .= "
			var uniqueRowNum".$field->id."	= ".count($field->value).";  // Unique row number incremented only
			var rowCount".$field->id."	= ".count($field->value).";      // Counts existing rows to be able to limit a max number of values
			var maxValues".$field->id." = ".$max_values.";
			
			function qfSelectFile".$field->id."(obj, id, file)
			{
				var insert_before   = (typeof params!== 'undefined' && typeof params.insert_before   !== 'undefined') ? params.insert_before   : 0;
				var remove_previous = (typeof params!== 'undefined' && typeof params.remove_previous !== 'undefined') ? params.remove_previous : 0;
				var scroll_visible  = (typeof params!== 'undefined' && typeof params.scroll_visible  !== 'undefined') ? params.scroll_visible  : 1;
				var animate_visible = (typeof params!== 'undefined' && typeof params.animate_visible !== 'undefined') ? params.animate_visible : 1;
				
				if((rowCount".$field->id." >= maxValues".$field->id.") && (maxValues".$field->id." != 0)) {
					alert(Joomla.JText._('FLEXI_FIELD_MAX_ALLOWED_VALUES_REACHED') + maxValues".$field->id.");
					return 'cancel';
				}
				
				if (1)
				{
					// A non-empty container is being removed ... get counter (which is optionally used as 'required' form element and empty it if is 1, or decrement if 2 or more)
					var valcounter = document.getElementById('".$field->name."');
					if ( typeof valcounter.value === 'undefined' || valcounter.value=='' ) valcounter.value = '1';
					else valcounter.value = parseInt(valcounter.value) + 1;
					//if(window.console) window.console.log ('valcounter.value: ' + valcounter.value);
				}
				
				if (file.substring(0,7)!='http://' || file.substring(0,8)!='https://') {
					file = '".str_replace('\\','/', JPATH_ROOT)."/".$mediapath."/'+file;
				}
				thumb_src = '".JURI::root(true)."/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='+file+'&w=100&h=100&zc=1';
				
				var lastField = null;
				var newField = jQuery('\
				<li class=\"".$value_classes."\">\
					<img alt=\"Thumbnail\" src=\"'+thumb_src+'\" class=\"thumbs\">\
					<input type=\"hidden\" id=\"a_id'+id+'_".$field->id."\" name=\"".$fieldname."\" value=\"'+id+'\" class=\"contains_fileid\"/> \
					<span class=\"fcfield-drag-handle\" title=\"".JText::_( 'FLEXI_CLICK_TO_DRAG' )."\"></span> \
					<span class=\"fcfield-button fcfield-delvalue\" title=\"".JText::_( 'FLEXI_REMOVE_VALUE' )."\" onclick=\"deleteField".$field->id."(this);\"></span> \
				</li>\
				');
					";
			
			// Add new field to DOM
			$js .= "
				lastField ?
					(insert_before ? newField.insertBefore( lastField ) : newField.insertAfter( lastField ) ) :
					newField.appendTo( jQuery('#sortables_".$field->id."') ) ;
				if (remove_previous) lastField.remove();
				";
			
			// Add new element to sortable objects (if field not in group)
			if (!$use_ingroup) $js .= "
				jQuery('#sortables_".$field->id."').sortable({
					handle: '.fcfield-drag-handle',
					containment: 'parent',
					tolerance: 'pointer'
				});
				";
			
			// Show new field, increment counters
			$js .="
				//newField.fadeOut({ duration: 400, easing: 'swing' }).fadeIn({ duration: 200, easing: 'swing' });
				if (scroll_visible) fc_scrollIntoView(newField, 1);
				if (animate_visible) newField.css({opacity: 0.1}).animate({ opacity: 1 }, 800);
				
				rowCount".$field->id."++;       // incremented / decremented
				uniqueRowNum".$field->id."++;   // incremented only
			}

			function deleteField".$field->id."(el, groupval_box, fieldval_box)
			{
				// Find field value container
				var row = fieldval_box ? fieldval_box : jQuery(el).closest('li');
				
				if ( 1 )
				{
					// A deleted container always has a value, thus decrement (or empty) the counter value in the 'required' form element
					var valcounter = document.getElementById('".$field->name."');
					valcounter.value = ( !valcounter.value || valcounter.value=='1' )  ?  ''  :  parseInt(valcounter.value) - 1;
					//if(window.console) window.console.log ('valcounter.value: ' + valcounter.value);
				}
				
				// Add empty container if last element, instantly removing the given field value container
				if(rowCount".$field->id." == 0)
					addField".$field->id."(null, groupval_box, row, {remove_previous: 1, scroll_visible: 0, animate_visible: 0});
				
				// Remove if not last one, if it is last one, we issued a replace (copy,empty new,delete old) above
				if(rowCount".$field->id." > 0) {
					// Destroy the remove/add/etc buttons, so that they are not reclicked, while we do the hide effect (before DOM removal of field value)
					row.find('.fcfield-delvalue').remove();
					row.find('.fcfield-insertvalue').remove();
					row.find('.fcfield-drag-handle').remove();
					// Do hide effect then remove from DOM
					row.slideUp(400, function(){ this.remove(); });
					rowCount".$field->id."--;
				}
			}
			";
			
			$css .= '
			#sortables_'.$field->id.' li img.thumbs { border:1px solid silver; padding:0;  margin:0px 0px 6px 0px; float:left; clear:both; }
			';
			
			$remove_button = '<span class="fcfield-delvalue" title="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);"></span>';
			$move2 = '<span class="fcfield-drag-handle" title="'.JText::_( 'FLEXI_CLICK_TO_DRAG' ).'"></span>';
		} else {
			$remove_button = '';
			$move2 = '';
			$js .= '';
			$css .= '';
		}
		
		if ($js)  $document->addScriptDeclaration($js);
		if ($css) $document->addStyleDeclaration($css);
		JHTML::_('behavior.modal', 'a.modal_'.$field->id);
		
		$field->html = array();
		$n = 0;
		foreach($files_data as $file_id => $file_data)
		{
			$img_path = (substr($file_data->filename, 0,7)!='http://' || substr($file_data->filename, 0,8)!='https://') ?
				JPATH_ROOT . DS . $mediapath . DS . $file_data->filename :
				$file_data->filename ;
			$src = JURI::root(true).'/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=' . $img_path . '&w=100&h=100&zc=1';
			
			$field->html[] = '
				<img class="thumbs" src="'.$src.'" alt="Thumbnail" />
				'.'
				'.'<input type="hidden" id="a_id'.$file_id.'_'.$field->id.'" name="'.$fieldname.'" value="'.$file_id.'"  class="contains_fileid" />'.'
				'.($use_ingroup ? '' : $move2).'
				'.($use_ingroup ? '' : $remove_button).'
				';
			
			$n++;
			//if ($max_values && $n >= $max_values) break;  // break out of the loop, if maximum file limit was reached
		}
		
		if ($use_ingroup) { // do not convert the array to string if field is in a group
		} else if ($multiple) { // handle multiple records
			$field->html =
				'<li class="'.$value_classes.'">'.
					implode('</li><li class="'.$value_classes.'">', $field->html).
				'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
		} else {  // handle single values
			$field->html = '<div class="fcfieldval_container valuebox fcfieldval_container_'.$field->id.'">' . $field->html[0] .'</div>';
		}
		
		// Add button for popup file selection
		$autoselect = $field->parameters->get( 'autoselect', 1 ) ;
		$linkfsel = JURI::base(true).'/index.php?option=com_flexicontent&amp;view=fileselement&amp;tmpl=component&amp;layout=image&amp;filter_secure=M&amp;index='.$n.'&amp;autoselect='.$autoselect.'&amp;field='.$field->id.'&amp;'.(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken()).'=1';
		$field->html .= '
			<span class="fcfield-button-add">
				<a class="modal_'.$field->id.'" title="'.JText::_( 'FLEXI_ADD_FILE' ).'" href="'.$linkfsel.'" rel="{handler: \'iframe\', size: {x:(MooTools.version>=\'1.2.4\' ? window.getSize().x : window.getSize().size.x)-100, y: (MooTools.version>=\'1.2.4\' ? window.getSize().y : window.getSize().size.y)-100}}">'.JText::_( 'FLEXI_ADD_FILE' ).'</a>
			</span>
			';
		
		$field->html .= '<input id="'.$field->name.'" class="'.$required.'" type="hidden" name="__fcfld_valcnt__['.$field->name.']" value="'.($n ? $n : '').'" />';
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		
		$field->label = JText::_($field->label);
		
		$values = $values ? $values : $field->value;
		// Load file data
		if ( !$values ) {
			$files_data = array();
			$values = array();
		} else {
			$files_data = $this->getFileData( $values, $published=false );
			$values = array();
			foreach($files_data as $file_id => $file_data) $values[] = $file_id;
		}

		$mainframe = JFactory::getApplication();

		$document    = JFactory::getDocument();
		$flexiparams = JComponentHelper::getParams('com_flexicontent');
		$mediapath   = $flexiparams->get('media_path', 'components/com_flexicontent/medias');
		$usepopup  = $flexiparams->get('usepopup', 1);
		$popuptype = $flexiparams->get('popuptype', 4);

		// some parameter shortcuts
		$thumbposition		= $field->parameters->get( 'thumbposition', 3 ) ;
		$w_l				= $field->parameters->get( 'w_l', 450 ) ;
		$h_l				= $field->parameters->get( 'h_l', 300 ) ;
		$w_s				= $field->parameters->get( 'w_s', 100 ) ;
		$h_s				= $field->parameters->get( 'h_s', 66 ) ;

		switch ($thumbposition) {
			case 1: // top
			$marginpos = 'top';
			$marginval = $h_s;
			break;

			case 2: // left
			$marginpos = 'left';
			$marginval = $w_s;
			break;

			case 4: // right
			$marginpos = 'right';
			$marginval = $w_s;
			break;

			case 3:
			default : // bottom
			$marginpos = 'bottom';
			$marginval = $h_s;
			break;
		}

		$scroll_thumbnails = $field->parameters->get( 'scroll_thumbnails', 1 ) ;
		switch ($thumbposition) {
			case 1: // top
			case 3:	default : // bottom
			$rows = ceil( (count($values) * ($w_s+8) ) / $w_l );  // thumbnail rows
			$series = ($scroll_thumbnails) ? 1: $rows;
			$series_size = ($h_s+8) * $series;
			break;

			case 2: // left
			case 4: // right
			$cols = ceil( (count($values) * ($h_s+8) ) / $h_l );  // thumbnail columns
			$series = ($scroll_thumbnails) ? 1: $cols;
			$series_size = ($w_s+8) * $series;
			break;
		}

		static $item_field_arr = null;
		static $js_and_css_added = false;

		$slideshowtype = $field->parameters->get( 'slideshowtype', 'Flash' );// default is normal slideshow
		$slideshowClass = 'Slideshow';

		if (empty($values)) return;
		
		if (!$js_and_css_added) {
			if (FLEXI_J16GE) {
				$document->addStyleSheet(JURI::root(true).'/plugins/flexicontent_fields/minigallery/css/minigallery.css');
			  FLEXI_J16GE ? JHtml::_('behavior.framework', true) : JHTML::_('behavior.mootools');
			  $document->addScript(JURI::root(true).'/plugins/flexicontent_fields/minigallery/js/slideshow.js');
			  if($slideshowtype!='slideshow') {
			  	$document->addScript(JURI::root(true).'/plugins/flexicontent_fields/minigallery/js/slideshow.'.strtolower($slideshowtype).'.js');
			  	$slideshowClass .= '.'.$slideshowtype;
			  }
			} else {
				$document->addStyleSheet(JURI::root(true).'/plugins/flexicontent_fields/minigallery/minigallery.css');
			  FLEXI_J16GE ? JHtml::_('behavior.framework', true) : JHTML::_('behavior.mootools');
			  $document->addScript(JURI::root(true).'/plugins/flexicontent_fields/minigallery/backgroundslider.js');
			  $document->addScript(JURI::root(true).'/plugins/flexicontent_fields/minigallery/slideshow.js');
			}
		  // this allows you to override the default css files
		  $csspath = JPATH_ROOT.'/templates/'.$mainframe->getTemplate().'/css/minigallery.css';
		  if(file_exists($csspath)) {
				$document->addStyleSheet(JURI::root(true).'/templates/'.$mainframe->getTemplate().'/css/minigallery.css');
		  }
			if ($usepopup && $popuptype==4) flexicontent_html::loadFramework('fancybox');
		}
		$js_and_css_added = true;

		$htmltag_id = "slideshowContainer_".$field->name."_".$item->id;
		$slidethumb = "slideshowThumbnail_".$field->name."_".$item->id;
		$transition = $field->parameters->get( 'transition', 'back' );
		$t_dir = $field->parameters->get( 't_dir', 'in' );
		$thumbnails = $field->parameters->get( 'thumbnails', '1' );
		$thumbnails = $thumbnails ? 'true' : 'false';
		$controller = $field->parameters->get( 'controller', '1' );
		$controller = $controller ? 'true' : 'false';
		$otheroptions = $field->parameters->get( 'otheroptions', '' );

		if ( !isset($item_field_arr[$item->id][$field->id]) )
		{
			$item_field_arr[$item->id][$field->id] = 1;

			$css = "
			#$htmltag_id {
				width: ".$w_l."px;
				height: ".$h_l."px;
				margin-".$marginpos.": ".(($marginval+8)*$series)."px;
			}
				";

			if ($thumbposition == 2 || $thumbposition == 4) {
				$css .= "div .slideshow-thumbnails { ".$marginpos.": -".($series_size+4)."px; height: 100%; width: ".($series_size+4)."px; top:0px; }";
				$css .= "div .slideshow-thumbnails ul { width: ".$series_size."px; }";
				$css .= "div .slideshow-thumbnails ul li {  }";
			} else if ($thumbposition==1 || $thumbposition==3) {
				$css .= "div .slideshow-thumbnails { ".$marginpos.": -".($series_size+4)."px; height: ".$series_size."px; }";
				if ($series > 1) $css .= "div .slideshow-thumbnails ul { width:100%!important; }";
				$css .= "div .slideshow-thumbnails ul li { float: left!important;}";
			} else { // inside TODO
				$css .= "div .slideshow-thumbnails { ".$marginpos.": -".($marginval+8)."px; height: ".($h_s+8)."px; top:0px; z-index:100; }";
				$css .= "div .slideshow-thumbnails ul { width: 100%!important;}";
				$css .= "div .slideshow-thumbnails ul li { float: left!important;}";
			}

			$document->addStyleDeclaration($css);

			$otheroptions = ($otheroptions?','.$otheroptions:'');
			$js = "
		  	window.addEvent('domready',function(){
				var options = {
					delay: ".$field->parameters->get( 'delay', 4000 ).",
					hu:'{$mediapath}/',
					transition:'{$transition}:{$t_dir}',
					duration: ".$field->parameters->get( 'duration', 1000 ).",
					width: {$w_l},
					height: {$h_l},
					thumbnails: {$thumbnails},
					controller: {$controller}
					{$otheroptions}
				}
				show = new {$slideshowClass}('{$htmltag_id}', null, options);
			});
			";
			$document->addScriptDeclaration($js);
		}

		$display = array();
		$thumbs = array();

		$usecaptions = (int)$field->parameters->get( 'usecaptions', 1 );
		$captions = '';
		if($usecaptions===2)
			$captions = $field->parameters->get( 'customcaptions', 'This is a caption' );
		
		$group_str = 'data-fancybox-group="fcitem_'.$item->id.'_fcfield_'.$field->id.'"';
		$n = 0;
		foreach($files_data as $file_id => $file_data)
		{
			if ($file_data) {
				$img_path = (substr($file_data->filename, 0,7)!='http://' || substr($file_data->filename, 0,8)!='https://') ?
					JURI::root(true) . '/' . $mediapath . '/' . $file_data->filename :
					$file_data->filename ;
				$srcs	= JURI::root(true).'/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=' . $img_path . '&w='.$w_s.'&h='.$h_s.'&zc=1';
				$srcb	= JURI::root(true).'/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=' . $img_path . '&w='.$w_l.'&h='.$h_l.'&zc=1';
				$ext = pathinfo($img_path, PATHINFO_EXTENSION);
				if ( in_array( $ext, array('png', 'ico', 'gif') ) ) {
					$srcs .= '&f='. $ext;
					$srcb .= '&f='. $ext;
				}

				if ($usecaptions===1) $captions = $file_data->altname;
				if ($usepopup && $popuptype == 4) {
					$display[] = '
						<a href="'.$img_path.'" class="fc_image_thumb fancybox" '.$group_str.' title="'.$captions.'" >
							<img src="'.$srcb.'" id="'.$htmltag_id.'_'.$n.'" alt="'.$captions.'" border="0" />
						</a>';
				} else {
					$display[] = '
						<a href="javascript:;"><img src="'.$srcb.'" id="'.$htmltag_id.'_'.$n.'" alt="'.$captions.'" border="0" /></a>';
				}
				$thumbs[] = '
					<li><a href="#'.$htmltag_id.'_'.$n.'"><img src="'.$srcs.'" border="0" /></a></li>';
				$n++;
			}
		}
		
		$field->{$prop} = '
		<div id="'.$htmltag_id.'" class="slideshow">
			<div class="slideshow-images">
				'.implode("\n", $display).'
			</div>
			<div class="slideshow-thumbnails">
				<ul>
				'.implode("\n", $thumbs).'
				</ul>
			</div>
		</div>
		<div class="clr"></div>
		<div class="clear"></div>
		';
	}
	
	
	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************
	
	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if(!is_array($post) && !strlen($post)) return;

		$mainframe = JFactory::getApplication();

		$post = array_unique($post);
	}
	
	
	// Method to take any actions/cleanups needed after field's values are saved into the DB
	function onAfterSaveField( &$field, &$post, &$file, &$item ) {
	}
	
	
	// Method called just before the item is deleted to remove custom item data related to the field
	function onBeforeDeleteField(&$field, &$item) {
	}



	// **********************
	// VARIOUS HELPER METHODS
	// **********************
	
	function getFileData( $value, $published=1, $extra_select='' )
	{
		// Find which file data are already cached, and if no new file ids to query, then return cached only data
		static $cached_data = array();
		$return_data = array();
		$new_ids = array();
		$values = is_array($value) ? $value : array($value);
		foreach ($values as $file_id) {
			$f = (int)$file_id;
			if ( !isset($cached_data[$f]) && $f)
				$new_ids[] = $f;
		}
		
		// Get file data not retrieved already
		if ( count($new_ids) )
		{
			// Only query files that are not already cached
			$db = JFactory::getDBO();
			$query = 'SELECT * '. $extra_select //filename, filename_original, altname, description, ext, id'
					. ' FROM #__flexicontent_files'
					. ' WHERE id IN ('. implode(',', $new_ids) . ')'
					. ($published ? '  AND published = 1' : '')
					;
			$db->setQuery($query);
			$new_data = $db->loadObjectList('id');

			if ($new_data) foreach($new_data as $file_id => $file_data) {
				$cached_data[$file_id] = $file_data;
			}
		}
		
		// Finally get file data in correct order
		foreach($values as $file_id) {
			$f = (int)$file_id;
			if ( isset($cached_data[$f]) && $f)
				$return_data[$file_id] = $cached_data[$f];
		}

		return !is_array($value) ? @$return_data[(int)$value] : $return_data;
	}


}
