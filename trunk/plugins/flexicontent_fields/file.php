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

class plgFlexicontent_fieldsFile extends JPlugin
{
	function plgFlexicontent_fieldsFile( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_file', JPATH_ADMINISTRATOR);
	}
	function onAdvSearchDisplayField(&$field, &$item) {
		plgFlexicontent_fieldsFile::onDisplayField($field, $item);
	}
	function onDisplayField(&$field, &$item)
	{
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'file') return;

		// some parameter shortcuts
		$size		= $field->parameters->get( 'size', 30 ) ;
						
		$document	=& JFactory::getDocument();
		$app		=& JFactory::getApplication();
		$prefix		= $app->isSite() ? 'administrator/' : '';
		$required 			= $field->parameters->get( 'required', 0 ) ;
		$required 	= $required ? ' required' : '';

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
			$(span).addClass('drag".$field->id."');
			
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
			
			img.src = '".$prefix."components/com_flexicontent/assets/images/move3.png';
			img.alt = '".JText::_( 'FLEXI_CLICK_TO_DRAG' )."';
			
			filelist.appendChild(li);
			li.appendChild(txt);
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

			// Add the drag and drop sorting feature
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
			#sortables_'.$field->id.' { margin: 0px; padding: 0px; list-style: none; white-space: nowrap; }
			#sortables_'.$field->id.' li {
				list-style: none;
				height: 20px;
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
				$field->html .= "<input size=\"".$size."\" class=\"{$required}\" style=\"background: #ffffff;\" type=\"text\" id=\"a_name".$i."\" value=\"".$filename->filename."\" disabled=\"disabled\" />";
				$field->html .= "<input type=\"hidden\" id=\"a_id".$i."\" name=\"".$field->name."[]\" value=\"".$file."\" />";
				$field->html .= "<input class=\"inputbox fcbutton\" type=\"button\" onclick=\"deleteField".$field->id."(this);\" value=\"".JText::_( 'FLEXI_REMOVE_FILE' )."\" />";
				$field->html .= "<span class=\"drag".$field->id."\">".$move."</span>";
				$field->html .= '</li>';
				$i++;
			}
		}
		$files = implode(":", $field->value);
		$user = & JFactory::getUser();
		//$linkfsel = 'index.php?option=com_flexicontent&amp;view=fileselement&amp;tmpl=component&amp;index='.$i.'&amp;field='.$field->id.'&amp;items='.$item->id.'&amp;filter_uploader='.$user->id;
		$linkfsel = 'index.php?option=com_flexicontent&amp;view=fileselement&amp;tmpl=component&amp;index='.$i.'&amp;field='.$field->id.'&amp;itemid='.$item->id.'&amp;items=0&amp;filter_uploader='.$user->id.'&amp;'.JUtility::getToken().'=1';
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
		if($field->field_type != 'file') return;

		$values = $values ? $values : $field->value ;
		
		global $mainframe;

		// some parameter shortcuts
		$separatorf			= $field->parameters->get( 'separatorf', 3 ) ;
		$useicon			= $field->parameters->get( 'useicon', 1 ) ;
		$usebutton			= $field->parameters->get( 'usebutton', 0 ) ;
		$display_filename			= $field->parameters->get( 'display_filename', 0 ) ;

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

		$n = 0;
		foreach ($values as $value) {
			$icon = '';
			$filename = $this->getFileName( $value );
			if ($filename) {
				if ($useicon) {
					$filename	= $this->addIcon( $filename );
					$icon		= JHTML::image($filename->icon, $filename->ext, 'class="icon-mime"') .'&nbsp;';
				}
				if($usebutton) {
					$str = '<form id="form-download-'.$field->id.'-'.($n+1).'" method="post" action="'.JRoute::_( 'index.php?id='. $value .'&cid='.$field->item_id.'&fid='.$field->id.'&task=download' ).'">';
						$str .= $icon.'<input type="submit" name="download-'.$field->id.'[]" class="button" value="'.JText::_('Download').'"/>'.($display_filename?'&nbsp;'.$filename->altname:'');
					$str .= '</form>';
					$field->{$prop}[] = $str;
				}else
					$field->{$prop}[]	= $icon . '<a href="' . JRoute::_( 'index.php?id='. $value .'&cid='.$field->item_id.'&fid='.$field->id.'&task=download' ) . '">' . $filename->altname . '</a>';
			}
			$n++;
		}
		$field->{$prop} = implode($separatorf, $field->{$prop});
	}
	

	function onBeforeSaveField($field, &$post, &$file)
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'file') return;
		if(!$post) return;

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
		$session = & JFactory::getSession();
		jimport('joomla.database.table.session');
		$sessiontable =new JTableSession( $db );
		$sessiontable->load($session->getId());
		$and = '';
		if(!$sessiontable->client_id) 
			$and = ' AND published = 1';
		$query = 'SELECT filename, altname, ext'
				. ' FROM #__flexicontent_files'
				. ' WHERE id = '. (int) $value . $and
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
