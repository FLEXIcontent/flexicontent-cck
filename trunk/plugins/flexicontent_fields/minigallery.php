<?php
/**
 * @version 1.0 $Id$
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
	function plgFlexicontent_fieldsMinigallery( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_minigallery', JPATH_ADMINISTRATOR);
	}
	function onAdvSearchDisplayField(&$field, &$item) {
		plgFlexicontent_fieldsMinigallery::onDisplayField($field, $item);
	}

	function onDisplayField(&$field, $item)
	{
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'minigallery') return;

		// some parameter shortcuts
		$size				= $field->parameters->get( 'size', 30 ) ;

		$document		= & JFactory::getDocument();
		$flexiparams 	=& JComponentHelper::getParams('com_flexicontent');
		$mediapath		= $flexiparams->get('media_path', 'components/com_flexicontent/medias');
		$app			= & JFactory::getApplication();
		$client			= $app->isAdmin() ? '../' : '';
		$clientpref		= $app->isAdmin() ? '' : 'administrator/';
		$required 			= $field->parameters->get( 'required', 0 ) ;
		$required 	= $required ? ' required' : '';

		$js = "
		function randomString() {
			var chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXTZabcdefghiklmnopqrstuvwxyz';
			var string_length = 6;
			var randomstring = '';
			for (var i=0; i<string_length; i++) {
				var rnum = Math.floor(Math.random() * chars.length);
				randomstring += chars.substring(rnum,rnum+1);
			}
			return randomstring;
		}

		function qfSelectFile".$field->id."(id, file) {
			var name 	= 'a_name'+id;
			var ixid 	= randomString();			
			var li 		= document.createElement('li');
			var thumb	= document.createElement('img');
			var hid		= document.createElement('input');
			var span	= document.createElement('span');
			var img		= document.createElement('img');
			
			var filelist = document.getElementById('sortables_".$field->id."');
			if(file.substring(0,7)!='http://')
				file = '".str_replace('\\','/', JPATH_ROOT)."/".$mediapath."/'+file;
			$(li).addClass('minigallery');
			$(thumb).addClass('thumbs');
			$(span).addClass('drag".$field->id."');
			
			var button = document.createElement('input');
			button.type = 'button';
			button.name = 'removebutton_'+id;
			button.id = 'removebutton_'+id;
			$(button).addClass('fcbutton');
			$(button).addEvent('click', function() { deleteField".$field->id."(this) });
			button.value = '".JText::_( 'FLEXI_REMOVE_FILE' )."';
			
			thumb.src = '".JURI::root()."/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='+file+'&w=100&h=100&zc=1';
			thumb.alt ='".JText::_( 'FLEXI_CLICK_TO_DRAG' )."';
			
			hid.type = 'hidden';
			//hid.name = '".$field->name."['+ixid+']';
			hid.name = '".$field->name."[]';
			hid.value = id;
			hid.id = ixid;
			
			img.src = '".JURI::root()."/administrator/components/com_flexicontent/assets/images/move3.png';
			img.alt ='".JText::_( 'FLEXI_CLICK_TO_DRAG' )."';
			
			filelist.appendChild(li);
			li.appendChild(thumb);
			li.appendChild(button);
			li.appendChild(hid);
			li.appendChild(span);
			span.appendChild(img);
			
			new Sortables($('sortables_".$field->id."'), {
				'constrain': true,
				'clone': true,
				'handle': '.drag".$field->id."'
			});			
		}
		
			function deleteField".$field->id."(el) {
				var field	= $(el);
				var row		= field.getParent();
				var fx		= row.effects({duration: 300, transition: Fx.Transitions.linear});
				
				fx.start({
					'height': 0,
					'opacity': 0			
				}).chain(function(){
					row.remove();
				});
			}
		";
		$document->addScriptDeclaration($js);

			//add the drag and drop sorting feature
			$js = "
			window.addEvent('domready', function(){
				new Sortables($('sortables_".$field->id."'), {
					'constrain': true,
					'clone': true,
					'handle': '.drag".$field->id."'
					});			
				});
			";
			$document->addScript( JURI::root().'administrator/components/com_flexicontent/assets/js/sortables.js' );
			$document->addScriptDeclaration($js);

			$css = '
			#sortables_'.$field->id.' { margin: 0 0 10px 0; padding: 0px; list-style: none; white-space: nowrap; }
			#sortables_'.$field->id.' li {
				list-style: none;
				height: 100px;
				padding-top:10px;
				}
			#sortables_'.$field->id.' li img.thumbs {
				border: 1px solid silver;
				padding: 0;
				margin: 0 0 -5px 0;
				}
			#sortables_'.$field->id.' li input { cursor: text;}
			#sortables_'.$field->id.' li input.fcbutton, .fcbutton { cursor: pointer; margin-left: 3px; }
			span.drag'.$field->id.' img {
				margin: -4px 8px;
				cursor: move;
			}
			';
			$document->addStyleDeclaration($css);

			$move 	= JHTML::image ( 'administrator/components/com_flexicontent/assets/images/move3.png', JText::_( 'FLEXI_CLICK_TO_DRAG' ) );
				
		JHTML::_('behavior.modal', 'a.modal_'.$field->id);

		$i = 0;
		$field->html = '<ul id="sortables_'.$field->id.'">';
		
		if($field->value) {
			
			foreach($field->value as $file) {
				$field->html .= '<li>';
				$filename = $this->getFileName( $file );
				$img_path = $filename->filename;
				if(substr($filename->filename, 0, 7)!='http://')
					$img_path = JPATH_ROOT . DS . $mediapath . DS . $filename->filename;
				$src = JURI::root() . '/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=' . $img_path . '&w=100&h=100&zc=1';

				$field->html .= '<img class="thumbs" src="'.$src.'"/>';
				$field->html .= '<input type="hidden" id="a_id'.$i.'" name="'.$field->name.'['.$i.']" value="'.$file.'" />';
				$field->html .= '<input class="inputbox fcbutton" type="button" onclick="deleteField'.$field->id.'(this);" value="'.JText::_( 'FLEXI_REMOVE_FILE' ).'" />';
				$field->html .= '<span class="drag'.$field->id.'">'.$move.'</span>';
				$field->html .= '</li>';
				$i++;
			}
		}

		$linkfsel = 'index.php?option=com_flexicontent&amp;view=fileselement&amp;tmpl=component&amp;layout=image&amp;filter_secure=M&amp;index='.$i.'&amp;field='.$field->id.'&amp;'.JUtility::getToken().'=1';
		$field->html .= "
		</ul>
		<div class=\"button-add\">
			<div class=\"blank\">
				<a class=\"modal_".$field->id."\" title=\"".JText::_( 'FLEXI_ADD_FILE' )."\" href=\"".$linkfsel."\" rel=\"{handler: 'iframe', size: {x:window.getSize().scrollSize.x-100, y: window.getSize().size.y-100}}\">".JText::_( 'FLEXI_ADD_FILE' )."</a>
			</div>
		</div>
		";
	}


	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'minigallery') return;

		$values = $values ? $values : $field->value ;
		
		global $mainframe;
		
		$document		= & JFactory::getDocument();
		$flexiparams 	=& JComponentHelper::getParams('com_flexicontent');
		$mediapath		= $flexiparams->get('media_path', 'components/com_flexicontent/medias');

		// some parameter shortcuts
		$thumbposition		= $field->parameters->get( 'thumbposition', 3 ) ;
		$w_l				= $field->parameters->get( 'w_l', 450 ) ;
		$h_l				= $field->parameters->get( 'h_l', 300 ) ;
		$w_s				= $field->parameters->get( 'w_s', 100 ) ;
		$h_s				= $field->parameters->get( 'h_s', 66 ) ;
		
		switch ($thumbposition) {
			case 1: // top
			$marginpos = 'top';
			break;

			case 2: // left
			$marginpos = 'left';
			break;

			case 4: // right
			$marginpos = 'right';
			break;

			case 3:
			default : // bottom
			$marginpos = 'bottom';
			break;
		}

	  static $js_and_css_added = false;
	  
		if ($values)
		{
			if (!$js_and_css_added) {
			  $document->addStyleSheet('plugins/flexicontent_fields/minigallery/minigallery.css');
			  // this allows you to override the default css files
			  $document->addStyleSheet(JURI::base().'/templates/'.$mainframe->getTemplate().'/css/minigallery.css');
			  JHTML::_('behavior.mootools');
			  $document->addScript('plugins/flexicontent_fields/minigallery/backgroundslider.js');
			  $document->addScript('plugins/flexicontent_fields/minigallery/slideshow.js');
			}
		  $js_and_css_added = true;
			
			$htmltag_id = "slideshowContainer_".$field->name."_".$item->id;
			$slidethumb = "slideshowThumbnail_".$field->name."_".$item->id;

		  $js = "
		  	window.addEvent('domready',function(){
					var obj = {
						wait: ".$field->parameters->get( 'wait', 4000 ).", 
						effect: '".$field->parameters->get( 'effect', 'fade' )."',
						direction: '".$field->parameters->get( 'direction', 'right' )."',
						duration: ".$field->parameters->get( 'duration', 1000 ).", 
						loop: ".$field->parameters->get( 'loop', 'true' ).", 
						thumbnails: true,
						backgroundSlider: true
					}
				show = new SlideShow('$htmltag_id','$slidethumb',obj);
				show.play();
				});
			";
			$document->addScriptDeclaration($js);
			
			$css = "
			.$htmltag_id {
				width: ".$w_l."px;
				height: ".$h_l."px;
				margin-".$marginpos.": 5px;
			}
			";
	
			if ($thumbposition == 1 || $thumbposition == 3) {
				$css .= "#thumbnails { width: ".$w_l."px; }";
			}
			if ($thumbposition == 2 || $thumbposition == 4) {
				$css .= ".$htmltag_id { float: left; } #thumbnails { float: left; width: ".($w_s + 10)."px; }";
			}

			$document->addStyleDeclaration($css);
			
			$display = array();
			
			$field->{$prop}  = '';
			$field->{$prop} .= ($thumbposition > 2) ? '<div id="'.$htmltag_id.'" class="'.$htmltag_id.'"></div>' : '';
			$field->{$prop} .= '<div id="thumbnails">';
			$n = 0;
			foreach ($values as $value) {
				$filename = $this->getFileName( $value );
				if ($filename) {
					$img_path = $filename->filename;
					if(substr($filename->filename,0,7)!='http://') {
						$img_path = JURI::base(true) . '/' . $mediapath . '/' . $filename->filename;
					}
					$srcs 		= 'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=' . $img_path . '&w='.$w_s.'&h='.$h_s.'&zc=1';
					$srcb 		= 'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src=' . $img_path . '&w='.$w_l.'&h='.$h_l.'&zc=1';
					
					$display[]	= '<a href="'.$srcb.'" class="'.$slidethumb.' slideshowThumbnail"><img src="'.$srcs.'" border="0" /></a>';
				}
				$n++;
				}
			$field->{$prop} .= implode(' ', $display);
			$field->{$prop} .= '</div>';
			$field->{$prop} .= ($thumbposition < 3) ? '<div id="'.$htmltag_id.'" class="'.$htmltag_id.'"></div>' : '';
		}
	}
	

	function onBeforeSaveField($field, &$post, $file)
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'minigallery') return;
		if(!$post) return;

		global $mainframe;
		
		$post = array_unique($post);
	}


	function getFileName( $value )
	{
		$db =& JFactory::getDBO();

		$query = 'SELECT filename, altname, ext'
				. ' FROM #__flexicontent_files'
				. ' WHERE id = '. (int) $value
				;
		$db->setQuery($query);
		$filename = $db->loadObject();

		return $filename;
	}
	

	function addIcon( &$file )
	{
		switch ($file->ext)
		{
			// Image
			case 'jpg':
			case 'png':
			case 'gif':
			case 'xcf':
			case 'odg':
			case 'bmp':
			case 'jpeg':
				$file->icon = 'components/com_flexicontent/assets/images/mime-icon-16/image.png';
			break;

			// Non-image document
			default:
				$icon = JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'assets'.DS.'images'.DS.'mime-icon-16'.DS.$file->ext.'.png';
				if (file_exists($icon)) {
					$file->icon = 'components/com_flexicontent/assets/images/mime-icon-16/'.$file->ext.'.png';
				} else {
					$file->icon = 'components/com_flexicontent/assets/images/mime-icon-16/unknown.png';
				}
			break;
		}
		return $file;
	}
}
