<?php
/**
 * @version 1.0 $Id: file.php 175 2009-11-07 10:24:30Z vistamedia $
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

class plgFlexicontent_fieldsFile extends JPlugin
{
	function plgFlexicontent_fieldsFile( &$subject, $params )
	{
		parent::__construct( $subject, $params );
        JPlugin::loadLanguage('plg_flexicontent_fields_file', JPATH_ADMINISTRATOR);
	}

	function onDisplayField(&$field, $item)
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'file') return;

		// some parameter shortcuts
		$size				= $field->parameters->get( 'size', 30 ) ;
						
		$document	= & JFactory::getDocument();

		$js = "
		function qfSelectFile".$field->id."(id, file) {
		
			var name 	= 'a_name'+id;
			var ixid 	= 'a_id'+id;			
			var li 		= document.createElement('li');
			var txt		= document.createElement('input');
			var hid		= document.createElement('input');
			var span	= document.createElement('span');
			var img		= document.createElement('img');
			
			var filelist = document.getElementById('sortables_".$field->id."');
			
			$(li).addClass('sortabledisabled');
			$(span).addClass('drag');
			
			var button = document.createElement('input');
			button.type = 'button';
			button.name = 'removebutton_'+id;
			button.id = 'removebutton_'+id;
			$(button).addClass('fcbutton');
			$(button).addEvent('click', function() { deleteField".$field->id."(this) });
			button.value = '".JText::_( 'FLEXI_REMOVE_FILE' )."';
			
			txt.type = 'text';
			txt.size = '".$size."';
			txt.disabled = 'disabled';
			txt.id	= name;
			txt.value	= file;
			
			hid.type = 'hidden';
			hid.name = '".$field->name."[]';
			hid.value = id;
			hid.id = ixid;
			
			img.src = 'components/com_flexicontent/assets/images/move3.png';
			img.alt ='".JText::_( 'FLEXI_CLICK_TO_DRAG' )."';
			
			filelist.appendChild(li);
			li.appendChild(txt);
			li.appendChild(button);
			li.appendChild(hid);
			li.appendChild(span);
			span.appendChild(img);
			
			new Sortables($('sortables_".$field->id."'), {
				'handles': $('sortables_".$field->id."').getElements('span.drag'),
				'onDragStart': function(element, ghost){
					ghost.setStyles({
					'list-style-type': 'none',
					'opacity': 1
					});
					element.setStyle('opacity', 0.3);
				},
				'onDragComplete': function(element, ghost){
					element.setStyle('opacity', 1);
					ghost.remove();
					this.trash.remove();
				}
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
					'handles': $('sortables_".$field->id."').getElements('span.drag'),
					'onDragStart': function(element, ghost){
						ghost.setStyles({
						   'list-style-type': 'none',
						   'opacity': 1
						});
						element.setStyle('opacity', 0.3);
					},
					'onDragComplete': function(element, ghost){
						element.setStyle('opacity', 1);
						ghost.remove();
						this.trash.remove();
					}
					});			
				});
			";
			$document->addScriptDeclaration($js);


			$css = '
			#sortables_'.$field->id.' { margin: 0px; padding: 0px; list-style: none; white-space: nowrap; }
			#sortables_'.$field->id.' li {
				list-style: none;
				height: 20px;
				}
			#sortables_'.$field->id.' li input { cursor: text;}
			#sortables_'.$field->id.' li input.fcbutton, .fcbutton { cursor: pointer; margin-left: 3px; }
			span.drag img {
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
				$field->html .= "<input size=\"".$size."\" style=\"background: #ffffff;\" type=\"text\" id=\"a_name".$i."\" value=\"".$filename->filename."\" disabled=\"disabled\" />";
				$field->html .= "<input type=\"hidden\" id=\"a_id".$i."\" name=\"".$field->name."[]\" value=\"".$file."\" />";
				$field->html .= "<input class=\"inputbox fcbutton\" type=\"button\" onclick=\"deleteField".$field->id."(this);\" value=\"".JText::_( 'FLEXI_REMOVE_FILE' )."\" />";
				$field->html .= "<span class=\"drag\">".$move."</span>";
				$field->html .= '</li>';
				$i++;
			}
		}

		$linkfsel = 'index.php?option=com_flexicontent&amp;view=fileselement&amp;tmpl=component&amp;index='.$i.'&amp;field='.$field->id;
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
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'file') return;

		$values = $values ? $values : $field->value ;
		
		global $mainframe;

		// some parameter shortcuts
		$separatorf			= $field->parameters->get( 'separatorf', 3 ) ;
		$useicon			= $field->parameters->get( 'useicon', 1 ) ;
		$usebutton			= $field->parameters->get( 'usebutton', 0 ) ;
						
		switch($separatorf)
		{
			case 0:
			$separatorf = ' ';
			break;

			case 1:
			$separatorf = '<br />';
			break;

			case 2:
			$separatorf = ' | ';
			break;

			case 3:
			$separatorf = ', ';
			break;

			default:
			$separatorf = ' ';
			break;
		}
		
		// initialise property
		$field->{$prop} = array();
		
		if ($usebutton && $values) {
			$field->{$prop} = '
			<form id="form-download-'.$field->id.'" method="post" action="'.JRoute::_( 'index.php?id='. $values[0] .'&cid='.$field->item_id.'&fid='.$field->id.'&task=download' ).'">
				<input type="submit" name="download-'.$field->id.'" class="button" value="'.JText::_('Download').'"/>
			</form>';
		
		} else {

			$n = 0;
			foreach ($values as $value) {
				$icon = '';
				$filename = $this->getFileName( $value );
				if ($filename) {
					if ($useicon) {
						$filename	= $this->addIcon( $filename );
						$icon		= JHTML::image($filename->icon, $filename->ext, 'class="icon-mime"') .'&nbsp;';
					}
					$field->{$prop}[]	= $icon . '<a href="' . JRoute::_( 'index.php?id='. $value .'&cid='.$field->item_id.'&fid='.$field->id.'&task=download' ) . '">' . $filename->altname . '</a>';
				}
				$n++;
				}
			$field->{$prop} = implode($separatorf, $field->{$prop});
		}
	}
	

	function onBeforeSaveField($field, &$post, &$file)
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'file') return;

		global $mainframe;
		
		$newpost = array();
		
		for ($n=0, $c=count($post); $n<$c; $n++)
		{
			if ($post[$n] != '') $newpost[] = $post[$n];
		}
		
		$post = array_unique($newpost);
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