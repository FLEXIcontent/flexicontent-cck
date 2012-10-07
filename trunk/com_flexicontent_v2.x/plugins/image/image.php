<?php
/**
 * @version 1.0 $Id: image.php 1262 2012-04-27 12:52:36Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.image
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

class plgFlexicontent_fieldsImage extends JPlugin
{
	function plgFlexicontent_fieldsImage( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_image', JPATH_ADMINISTRATOR);
	}
	
	function onAdvSearchDisplayField(&$field, &$item) {
		plgFlexicontent_fieldsImage::onDisplayField($field, $item);
	}
	
	function onDisplayField(&$field, $item)
	{
		if($field->field_type != 'image') return;
		$field->label = JText::_($field->label);
		
		static $common_js_css_added = false;
		
		// some parameter shortcuts
		$multiple   = $field->parameters->get( 'allow_multiple', 1 ) ;
		$maxval     = $field->parameters->get( 'max_values', 0 ) ;
		$image_source= $field->parameters->get( 'image_source', 0 ) ;
		
		$required   = $field->parameters->get( 'required', 0 ) ;
		$required   = $required ? ' required' : '';
		$autoupload = $field->parameters->get('autoupload', 0);
		$autoassign = $field->parameters->get('autoassign', 0);
		$always_allow_removal = $field->parameters->get('always_allow_removal', 0);
		
		// optional properies configuration
		$linkto_url = $field->parameters->get('linkto_url',0);
		$alt_usage   = $field->parameters->get( 'alt_usage', 0 ) ;
		$title_usage = $field->parameters->get( 'title_usage', 0 ) ;
		$desc_usage  = $field->parameters->get( 'desc_usage', 0 ) ;
		
		$default_alt    = ($item->version == 0 || $alt_usage > 0) ? $field->parameters->get( 'default_alt', '' ) : '';
		$default_title  = ($item->version == 0 || $title_usage > 0) ? JText::_($field->parameters->get( 'default_title', '' )) : '';
		$default_desc   = ($item->version == 0 || $desc_usage > 0) ? $field->parameters->get( 'default_desc', '' ) : '';
		
		$usealt    = $field->parameters->get( 'use_alt', 1 ) ;
		$usetitle  = $field->parameters->get( 'use_title', 1 ) ;
		$usedesc   = $field->parameters->get( 'use_desc', 1 ) ;
		
		
		$app      = & JFactory::getApplication();
		$document = & JFactory::getDocument();
		$adminprefix = $app->isAdmin() ? '../' : '';
		
		if ( !$common_js_css_added ) {
			$js = "
				function fx_img_toggle_required (obj_changed, obj_req_toggle) {
					if (obj_changed.value!='') {
						obj_changed.className='';
						obj_req_toggle.className='';
					} else {
						obj_changed.className='required';
						obj_req_toggle.className='required';
					}
				}
				
				function fx_toggle_upload_select_tbl (obj_changed, obj_disp_toggle) {
					if (obj_changed.checked)
						obj_disp_toggle.setStyle('display', 'table');
					else
						obj_disp_toggle.setStyle('display', 'none');
				}
				
				function imgfld_fileelement_url (target, fieldid, itemid, thumb_w, thumb_h) {
					targetid = target.getParent().getParent().getParent().getElement('.existingname').id;
					linkfsel = '".JURI::base().'index.php?option=com_flexicontent&view=fileselement&tmpl=component&layout=image&filter_secure=M&folder_mode=1&'.JUtility::getToken().'=1'."';
					linkfsel = linkfsel + '&field='+fieldid+'&itemid='+itemid+'&targetid='+targetid+'&thumb_w='+thumb_w+'&thumb_h='+thumb_h+'&autoassign=".$autoassign."';
					//alert(linkfsel);
					return linkfsel;
				}
				";
			$document->addScriptDeclaration($js);
			$common_js_css_added = true;
		}
		
		$n = 0;
		$field->html = '';
		
		// Make sure value is an array of values
		if ( !$field->value ) {
			$field->value = array();
			$field->value[0]['originalname'] = '';
			$field->value[0] = serialize($field->value[0]);
		}
		
		
		if ($multiple) // handle multiple records
		{
			//add the drag and drop sorting feature
			$js = "
			window.addEvent('domready', function(){
				new Sortables($('sortables_".$field->id."'), {
					'constrain': true,
					'clone': true,
					'handle': '.fcfield-drag'
					});			
				});
			";
			if (!FLEXI_J16GE) $document->addScript( JURI::root().'administrator/components/com_flexicontent/assets/js/sortables.js' );
			$document->addScriptDeclaration($js);
			
			$fieldname = FLEXI_J16GE ? 'custom['.$field->name.']' : $field->name;
			$elementid = FLEXI_J16GE ? 'custom_'.$field->name : $field->name;
			
			// WARNING: bellow we also use $field->name which is different than $fieldname
			
			$js = "
			var uniqueRowNum".$field->id."	= ".count($field->value).";  // Unique row number incremented only
			var rowCount".$field->id."	= ".count($field->value).";      // Counts existing rows to be able to limit a max number of values
			var maxVal".$field->id."		= ".$maxval.";

			function addField".$field->id."(el) {
				if((rowCount".$field->id." < maxVal".$field->id.") || (maxVal".$field->id." == 0)) {

					var thisField 	 = $(el).getPrevious().getLast();
					var thisNewField = thisField.clone();
					if (MooTools.version>='1.2.4') {
						var fx = new Fx.Morph(thisNewField, {duration: 0, transition: Fx.Transitions.linear});
					} else {
						var fx = thisNewField.effects(thisNewField, {duration: 0, transition: Fx.Transitions.linear});
					}
					
					thisNewField.getElements('input.newfile').setProperty('value','');
					thisNewField.getElements('input.newfile').setProperty('name','".$field->name."['+uniqueRowNum".$field->id."+']');
					
					thisNewField.getElements('input.originalname').setProperty('value','');
					thisNewField.getElements('input.originalname').setProperty('name','".$fieldname."['+uniqueRowNum".$field->id."+'][originalname]');
					
					thisNewField.getElements('.existingname').setProperty('value','');
					thisNewField.getElements('.existingname').setProperty('name','".$fieldname."['+uniqueRowNum".$field->id."+'][existingname]');
					thisNewField.getElements('.existingname').setProperty('id','".$elementid."_'+uniqueRowNum".$field->id."+'_existingname');
					
					thisNewField.getElements('a.addfile_".$field->id."').setProperty('id','".$elementid."_'+uniqueRowNum".$field->id."+'_addfile');
					
					// COPYING an existing value
					if (thisNewField.getElement('img.preview_image')) {
						if (MooTools.version>='1.2.4') {
							var tmpDiv = new Element('div',{html:'<div class=\"empty_image\" style=\"height:".$field->parameters->get('h_s')."px; width:".$field->parameters->get('w_s')."px;\"></div>'});
							tmpDiv.getFirst().setProperty('id','".$elementid."_'+uniqueRowNum".$field->id."+'_preview_image');
							tmpDiv.getFirst().replaces( thisNewField.getElement('img.preview_image') );
						} else {
							var tmpDiv = new Element('div', {}).setHTML('<div class=\"empty_image\" style=\"height:".$field->parameters->get('h_s')."px; width:".$field->parameters->get('w_s')."px;\"></div>');
							tmpDiv.getFirst().setProperty('id','".$elementid."_'+uniqueRowNum".$field->id."+'_preview_image');
							tmpDiv.getFirst().injectAfter(thisNewField.getElement('img.preview_image'));
							thisNewField.getElement('img.preview_image').remove();
						}
					
					// COPYING an empty value
					} else if (thisNewField.getElement('div.empty_image')) {
						thisNewField.getElement('div.empty_image').setProperty('id','".$elementid."_'+uniqueRowNum".$field->id."+'_preview_image');
					}
					
					if ( thisNewField.getElement('div.delrem_box') ) {
						(MooTools.version>='1.2.4') ? thisNewField.getElement('div.delrem_box').destroy()  :  thisNewField.getElement('div.delrem_box').remove();
					}
					
					thisNewField.getElements('table.img_upload_select').setProperty('id','".$field->name."_upload_select_tbl_'+uniqueRowNum".$field->id.");
					thisNewField.getElements('table.img_upload_select').setStyle('display', 'table');
					";
					
			if ($linkto_url) $js .= "
					thisNewField.getElements('input.imglink').setProperty('value','');
					thisNewField.getElements('input.imglink').setProperty('name','".$fieldname."['+uniqueRowNum".$field->id."+'][urllink]');
					";
					
			if ($usealt) $js .= "
					thisNewField.getElements('input.imgalt').setProperty('value','".$default_alt."');
					thisNewField.getElements('input.imgalt').setProperty('name','".$fieldname."['+uniqueRowNum".$field->id."+'][alt]');
					";
					
			if ($usetitle) $js .= "
					thisNewField.getElements('input.imgtitle').setProperty('value','".$default_title."');
					thisNewField.getElements('input.imgtitle').setProperty('name','".$fieldname."['+uniqueRowNum".$field->id."+'][title]');
					";
					
			if ($usedesc) $js .= "
					thisNewField.getElements('textarea.imgdesc').setProperty('value','".$default_desc."');
					thisNewField.getElements('textarea.imgdesc').setProperty('name','".$fieldname."['+uniqueRowNum".$field->id."+'][desc]');
					";
					
			$js .= "
					thisNewField.injectAfter(thisField);
					".// We need to re-execute setting of modal popup since when this run the current element did not exist
					"
					
					SqueezeBox.initialize({});
					if (MooTools.version>='1.2.4') {
						SqueezeBox.assign($$('a.addfile_".$field->id."'), {
							parse: 'rel'
						});
					} else {
						$$('a.addfile_".$field->id."').each(function(el) {
							el.addEvent('click', function(e) {
								new Event(e).stop();
								SqueezeBox.fromElement(el);
							});
						});
					}
					
					new Sortables($('sortables_".$field->id."'), {
						'constrain': true,
						'clone': true,
						'handle': '.fcfield-drag'
					});			

					fx.start({ 'opacity': 1 }).chain(function(){
						this.setOptions({duration: 600});
						this.start({ 'opacity': 0 });
						})
						.chain(function(){
							this.setOptions({duration: 300});
							this.start({ 'opacity': 1 });
						});

					rowCount".$field->id."++;       // incremented / decremented
					uniqueRowNum".$field->id."++;   // incremented only
				}
			}

			function deleteField".$field->id."(el)
			{
				if(rowCount".$field->id." > 1)
				{
					var field	= $(el);
					var row		= field.getParent();
					if (MooTools.version>='1.2.4') {
						var fx = new Fx.Morph(row, {duration: 300, transition: Fx.Transitions.linear});
					} else {
						var fx = row.effects(row, {duration: 300, transition: Fx.Transitions.linear});
					}
					
					fx.start({
						'height': 0,
						'opacity': 0
						}).chain(function(){
							(MooTools.version>='1.2.4')  ?  row.destroy()  :  row.remove();
						});
					rowCount".$field->id."--;
				}
			}
			
			function qmAssignFile".$field->id."(tagid, file, file_url) {
				var obj = $(tagid).getParent().getParent().getParent().getElement('.existingname');
				var prv_obj = $(tagid.replace('existingname','preview_image'));
				var elementid = tagid.replace('_existingname','');
				
				if (file != '') obj.value = file;
				
				if (prv_obj) {
					if (MooTools.version>='1.2.4') {
						var tmpDiv = new Element('div',{html:'<img class=\"preview_image\" id=\"'+elementid+'_preview_image\" src=\"'+file_url+'\" style=\"border: 1px solid silver; float:left;\" />'});
						tmpDiv.getFirst().replaces( prv_obj );
					} else {
						var tmpDiv = new Element('div', {}).setHTML('<img class=\"preview_image\" id=\"'+elementid+'_preview_image\" src=\"'+file_url+'\" style=\"border: 1px solid silver; float:left;\" />');
						tmpDiv.getFirst().injectAfter( prv_obj );
						prv_obj.remove();
					}
				}
				(MooTools.version>='1.2.4') ?  window.SqueezeBox.close()  :  window.document.getElementById('sbox-window').close();
			}
			";
			$document->addScriptDeclaration($js);
			
			$css = '
			#sortables_'.$field->id.' { float:left; margin: 0px; padding: 0px; list-style: none; white-space: nowrap; }
			#sortables_'.$field->id.' li {
				clear: both;
				display: block;
				list-style: none;
				position: relative;
			}
			#sortables_'.$field->id.' li input { cursor: text;}
			#add'.$field->name.' { margin-top: 5px; clear: both; display:block; }
			#sortables_'.$field->id.' li .admintable { text-align: left; }
			#sortables_'.$field->id.' li:only-child span.fcfield-drag, #sortables_'.$field->id.' li:only-child input.fcfield-button { display:none; }
			';
			
			$remove_button = '<input class="fcfield-button" type="button" value="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);" />';
			$move2 	= '<span class="fcfield-drag">'.JHTML::image ( JURI::root().'administrator/components/com_flexicontent/assets/images/move3.png', JText::_( 'FLEXI_CLICK_TO_DRAG' ) ) .'</span>';
		} else {
			$remove_button = '';
			$move2 = '';
			$css = '';
		}
		
		$document->addStyleDeclaration($css);
		
		if ( $image_source ) {
			JHTML::_('behavior.modal', 'a.addfile_'.$field->id);
		} else {
			$select = $this->buildSelectList( $field );
		}
		
		$class = ' class="'.$required.' "';
		$onchange= ' onchange="';
		$onchange .= ($required) ? ' fx_img_toggle_required(this,$(\''.$field->name.'originalname\')); ' : '';
		$js_submit = FLEXI_J16GE ? "Joomla.submitbutton('items.apply')" : "submitbutton('apply')";
		$onchange .= ($autoupload && $app->isAdmin()) ? $js_submit : '';
		$onchange .= ' "';
		$thumb_w = $field->parameters->get( 'w_small', 120 );
		$thumb_h = $field->parameters->get( 'h_small', 90 );
		
		$n = 0;
		foreach ($field->value as $value) {
			$value = unserialize($value);
			
			$fieldname = FLEXI_J16GE ? 'custom['.$field->name.']['.$n.']' : $field->name.'['.$n.']';
			$elementid = FLEXI_J16GE ? 'custom_'.$field->name.'_'.$n : $field->name.'_'.$n;
			
			if ( $image_source ) {
				$select = "
				<input class='existingname' id='".$elementid."_existingname' name='".$fieldname."[existingname]' value='' readonly='readonly' style='float:left;' />
				<div class=\"fcfield-button-add\">
					<div class=\"blank\">
						<a class=\"addfile_".$field->id."\" id='".$elementid."_addfile' title=\"".JText::_( 'FLEXI_ADD_FILE' )."\"
							href=\"#\" onmouseover=\"this.href=imgfld_fileelement_url(this,".$field->id.",".$item->id.",".$thumb_w.",".$thumb_h.")\"
							rel=\"{handler: 'iframe', size: {x:window.getSize().x-100, y: window.getSize().y-100}}\">".JText::_( 'FLEXI_ADD_FILE' )."</a>
					</div>
				</div>
				";
			}
			
			$image_name = trim($value['originalname']);
			
			if ( $image_name ) {
				// Check and rebuild thumbnails if needed
				$rebuild_res = $this->rebuildThumbs($field,$value);
				
				// Skip delete files or file that could not have their thumbs created
				if ( $rebuild_res===false ) continue;
				
				$delete = $this->canDeleteImage( $field, $image_name ) ? '' : ' disabled="disabled"';
				$delete = '<input class="imgdelete" type="checkbox" name="'.$fieldname.'[delete]" id="'.$elementid.'_delete" value="1"'.$delete.' style="display:inline;">';
				$delete .= '<label style="display:inline;" for="'.$elementid.'_delete">'.JText::_( 'FLEXI_FIELD_DELETE' ).'</label>';
				
				$remove = $always_allow_removal ? '' : $this->canDeleteImage( $field, $image_name ) ? ' disabled="disabled"' : '';
				$remove = '<input class="imgremove" type="checkbox" name="'.$fieldname.'[remove]" id="'.$elementid.'_remove" value="1"'.$remove.' style="display:inline;">';
				$remove .= '<label style="display:inline;" for="'.$elementid.'_remove">'.JText::_( 'FLEXI_FIELD_REMOVE' ).'</label>';
				
				$change = '<input class="imgchange" type="checkbox" name="'.$fieldname.'[change]" id="'.$elementid.'_change" onchange="fx_toggle_upload_select_tbl(this, $(\''.$field->name.'_upload_select_tbl_'.$n.'\'))" value="1" style="display:inline;">';
				$change .= '<label style="display:inline;" for="'.$elementid.'_change">'.JText::_( 'Change' ).'</label>';
				
				$originalname = '<input name="'.$fieldname.'[originalname]" type="hidden" class="originalname" value="'.$value['originalname'].'" />';
				
				$img_link  = $adminprefix.$field->parameters->get('dir');
				$img_link .= ($image_source ? '/item_'.$item->id . '_field_'.$field->id : "");
				$img_link .= '/s_'.$value['originalname'];
				$imgpreview = '<img class="preview_image" id="'.$elementid.'_preview_image" src="'.$img_link.'" style="border: 1px solid silver; float:left;" />';
			} else {
				$delete = $remove = $change = '';
				$originalname = '';
				$imgpreview = '<div class="empty_image" id="'.$elementid.'_preview_image" style="height:'.$field->parameters->get('h_s').'px; width:'.$field->parameters->get('w_s').'px;"></div>';
			}
			
			if ($linkto_url) $urllink =
				'<tr>
					<td class="key">'.JText::_( 'FLEXI_FIELD_LINKTO_URL' ).':</td>
					<td><input class="imglink" size="40" name="'.$fieldname.'[urllink]" value="'.(isset($value['urllink']) ? $value['urllink'] : '').'" type="text" /></td>
				</tr>';
			if ($usealt) $alt =
				'<tr>
					<td class="key">'.JText::_( 'FLEXI_FIELD_ALT' ).': ('.JText::_('FLEXI_FIELD_IMAGE').')</td>
					<td><input class="imgalt" size="40" name="'.$fieldname.'[alt]" value="'.(isset($value['alt']) ? $value['alt'] : $default_alt).'" type="text" /></td>
				</tr>';
			if ($usetitle) $title =
				'<tr>
					<td class="key">'.JText::_( 'FLEXI_FIELD_TITLE' ).': ('.JText::_('FLEXI_FIELD_TOOLTIP').')</td>
					<td><input class="imgtitle" size="40" name="'.$fieldname.'[title]" value="'.(isset($value['title']) ? $value['title'] : $default_title).'" type="text" /></td>
				</tr>';
			if ($usedesc) $desc =
				'<tr>
					<td class="key">'.JText::_( 'FLEXI_FIELD_LONGDESC' ).': ('.JText::_('FLEXI_FIELD_TOOLTIP').')</td>
					<td><textarea class="imgdesc" name="'.$fieldname.'[desc]" rows="5" cols="30" />'.(isset($value['desc']) ? $value['desc'] : $default_desc).'</textarea></td>
				</tr>';
			
			$curr_select = str_replace('__FORMFLDNAME__', $fieldname.'[existingname]', $select);
			$curr_select = str_replace('__FORMFLDID__', $elementid.'_existingname', $curr_select);
			
			$field->html[] = '
			<div style="float:left; margin-right: 5px;">
				'.$imgpreview.'
				'.$originalname.'
				<div style="float:left; clear:both;" class="delrem_box">
					'.$remove.'<br/>
					'.$delete.'<br/>
					'.$change.'
				</div>
			</div>
			'.$remove_button.'
			<div style="float:right;" class="img_value_props">
				<table class="admintable img_upload_select" id="'.$field->name.'_upload_select_tbl_'.$n.'" style="'.($image_name ? "display:none;" : "").'" ><tbody>'.
				
				( !$image_source ? '
					<tr>
						<td class="key">'.JText::_( 'FLEXI_FIELD_MAXSIZE' ).':</td>
						<td>'.($field->parameters->get('upload_maxsize') / 1000000).' M</td>
					</tr>
					<tr>
						<td class="key">'.JText::_( 'FLEXI_FIELD_ALLOWEDEXT' ).':</td>
						<td>'.$field->parameters->get('upload_extensions').'</td>
					</tr>
					<tr>
						<td class="key">'.JText::_( 'FLEXI_FIELD_NEWFILE' ).':</td>
						<td><input name="'.$field->name.'['.$n.']" id="'.$field->name.'_newfile"  class="newfile '.$required.'" '.$onchange.' type="file" /></td>
					</tr>'  :  '')
					.'
					<tr>
						<td class="key">'.JText::_( !$image_source ? 'FLEXI_FIELD_EXISTINGFILE' : 'FLEXI_SELECT' ).':</td>
						<td>'.$curr_select.'</td>
					</tr>
				</tbody></table>
				<table class="admintable"><tbody>
					'.@$urllink.'
					'.@$alt.'
					'.@$title.'
					'.@$desc.'
				</tbody></table>
			</div>
			'.$move2.'
			';
			
			$n++;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}
		
		if ($multiple) { // handle multiple records
			$field->html = '<li>'. implode('</li><li>', $field->html) .'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
			$field->html .= '<input type="button" class="fcfield-addvalue" style="float:left; clear:both;" onclick="addField'.$field->id.'(this);" value=" -- '.JText::_( 'FLEXI_ADD' ).' -- " />';
		} else {  // handle single values
			$field->html = $field->html[0];
		}
	}

	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'image') return;

		static $multiboxadded = false;
		
		$values = $values ? $values : $field->value;
		
		$multiple    = $field->parameters->get( 'allow_multiple', 0 ) ;
		$image_source = $field->parameters->get('image_source', 0);
		
		$usealt      = $field->parameters->get( 'use_alt', 1 ) ;
		$alt_usage   = $field->parameters->get( 'alt_usage', 0 ) ;
		$default_alt = ($alt_usage == 2)  ?  $field->parameters->get( 'default_alt', '' ) : '';
		
		$usetitle      = $field->parameters->get( 'use_title', 1 ) ;
		$title_usage   = $field->parameters->get( 'title_usage', 0 ) ;
		$default_title = ($title_usage == 2)  ?  JText::_($field->parameters->get( 'default_title', '' )) : '';
		
		$usedesc       = $field->parameters->get( 'use_desc', 1 ) ;
		$desc_usage    = $field->parameters->get( 'desc_usage', 0 ) ;
		$default_desc  = ($desc_usage == 2)  ?  $field->parameters->get( 'default_desc', '' ) : '';
		
		// Separators / enclosing characters
		$pretext			= $field->parameters->get( 'pretext', '' ) ;
		$posttext			= $field->parameters->get( 'posttext', '' ) ;
		$separatorf		= $field->parameters->get( 'separatorf', 0 ) ;  // 0 is space, 1 is line break
		$opentag			= $field->parameters->get( 'opentag', '' ) ;
		$closetag			= $field->parameters->get( 'closetag', '' ) ;
		
		if($pretext) { $pretext = $remove_space ? $pretext : $pretext . ' '; }
		if($posttext) {	$posttext = $remove_space ? $posttext : ' ' . $posttext; }
		
		switch($separatorf)
		{
			case 0:
			$separatorf = '&nbsp;';
			break;

			case 1:
			$separatorf = '<br />';
			break;

			case 2:
			$separatorf = '&nbsp;|&nbsp;';
			break;

			case 3:
			$separatorf = ',&nbsp;';
			break;

			case 4:
			$separatorf = $closetag . $opentag;
			break;

			default:
			$separatorf = '&nbsp;';
			break;
		}

		// Allow for thumbnailing of the default image
		if (!$values || $values[0] == '') {
			$default_image = $field->parameters->get( 'default_image', '');
			if ( $default_image !== '' ) {
				$values[0] = array();
				$values[0]['is_default_image'] = true;
				$values[0]['default_image'] = $default_image;
				$values[0]['originalname'] = basename($default_image);
				$values[0]['alt'] = $default_alt;
				$values[0]['title'] = $default_title;
				$values[0]['desc'] = $default_desc;
				$values[0]['urllink'] = '';
				$values[0] = serialize($values[0]);
				$field->value[0] = $values[0];
			}
		}
		
		// Check for no values and return empty display
		if ( !$values || !$values[0]) {
			$field->{$prop} = '';
			return;
		}
		
		$app       = & JFactory::getApplication();
		$document	 = & JFactory::getDocument();
		$view			 = JRequest::getVar('flexi_callview', JRequest::getVar('view', FLEXI_ITEMVIEW));
		$option    = JRequest::getVar('option');
		jimport('joomla.filesystem');
		
		$isItemsManager = $app->isAdmin() && $view=='items' && $option=='com_flexicontent';
		
		// some parameter shortcuts
		$uselegend  = $field->parameters->get( 'uselegend', 1 ) ;
		$usepopup   = $field->parameters->get( 'usepopup',  1 ) ;
		$popuptype  = $field->parameters->get( 'popuptype', 1 ) ;
		$grouptype  = $field->parameters->get( 'grouptype', 1 ) ;
		$grouptype = $multiple ? 0 : $grouptype;  // Field in gallery mode: Force grouping of images per field (current item)
		
		// Check and disable 'uselegend'
		$legendinview = $field->parameters->get('legendinview', array(FLEXI_ITEMVIEW,'category'));
		$legendinview  = FLEXIUtilities::paramToArray($legendinview);
		if ($view==FLEXI_ITEMVIEW && !in_array(FLEXI_ITEMVIEW,$legendinview)) $uselegend = 0;
		if ($view=='category' && !in_array('category',$legendinview)) $uselegend = 0;
		if ($isItemsManager && !in_array('backend',$legendinview)) $uselegend = 0;
		
		// Check and disable 'usepopup'
		$popupinview = $field->parameters->get('popupinview', array(FLEXI_ITEMVIEW,'category','backend'));
		$popupinview  = FLEXIUtilities::paramToArray($popupinview);
		if ($view==FLEXI_ITEMVIEW && !in_array(FLEXI_ITEMVIEW,$popupinview)) $usepopup = 0;
		if ($view=='category' && !in_array('category',$popupinview)) $usepopup = 0;
		if ($isItemsManager && !in_array('backend',$popupinview)) $usepopup = 0;
		
		// FORCE multibox popup in backend ...
		if ($isItemsManager) $popuptype = 1;
		
		// remaining parameters shortcuts
		$showtitle = $field->parameters->get( 'showtitle', 0 ) ;
		$showdesc  = $field->parameters->get( 'showdesc', 0 ) ;
		
		$linkto_url	= $field->parameters->get('linkto_url',0);
		$url_target = $field->parameters->get('url_target','_self');
		
		$useogp     = $field->parameters->get('useogp', 0);
		$ogpinview  = $field->parameters->get('ogpinview', array());
		$ogpinview  = FLEXIUtilities::paramToArray($ogpinview);
		$ogpthumbsize= $field->parameters->get('ogpthumbsize', 2);
		
		// load the tooltip library if redquired
		if ($uselegend) JHTML::_('behavior.tooltip');
		
		if ( ($app->isSite() || $isItemsManager)
					&& !$multiboxadded
					&&	(  ($linkto_url && $url_target=='multibox')  ||  ($usepopup && $popuptype == 1)  )
			 )
		{
			JHTML::_('behavior.mootools');
			
			// Multibox integration use different version for FC v2x
			if (FLEXI_J16GE) {
				
				// Include MultiBox CSS files
				$document->addStyleSheet(JURI::root().'components/com_flexicontent/librairies/multibox/Styles/multiBox.css');
				
				// NEW ie6 hack
				if (substr($_SERVER['HTTP_USER_AGENT'],0,34)=="Mozilla/4.0 (compatible; MSIE 6.0;") {
					$document->addStyleSheet(JURI::root().'components/com_flexicontent/librairies/multibox/Styles/multiBoxIE6.css');
				}  // This is the new code for new multibox version, old multibox hack is the following lines
				
				// Include MultiBox Javascript files
				$document->addScript(JURI::root().'components/com_flexicontent/librairies/multibox/Scripts/overlay.js');
				$document->addScript(JURI::root().'components/com_flexicontent/librairies/multibox/Scripts/multiBox.js');
				
				// Add js code for creating a multibox instance
				$extra_options = '';
				if ($isItemsManager) $extra_options .= ''
						.',showNumbers: false'  //show numbers such as "4 of 12"
						.',showControls: false' //show the previous/next, title, download etc
						;
				$box = "
					window.addEvent('domready', function(){
						//call multiBox
						var initMultiBox = new multiBox({
							mbClass: '.mb',//class you need to add links that you want to trigger multiBox with (remember and update CSS files)
							container: $(document.body),//where to inject multiBox
							descClassName: 'multiBoxDesc',//the class name of the description divs
							path: './Files/',//path to mp3 and flv players
							useOverlay: true,//use a semi-transparent background. default: false;
							maxSize: {w:600, h:400},//max dimensions (width,height) - set to null to disable resizing
							addDownload: false,//do you want the files to be downloadable?
							pathToDownloadScript: './Scripts/forceDownload.asp',//if above is true, specify path to download script (classicASP and ASP.NET versions included)
							addRollover: true,//add rollover fade to each multibox link
							addOverlayIcon: true,//adds overlay icons to images within multibox links
							addChain: true,//cycle through all images fading them out then in
							recalcTop: true,//subtract the height of controls panel from top position
							addTips: true,//adds MooTools built in 'Tips' class to each element (see: http://mootools.net/docs/Plugins/Tips)
							autoOpen: 0//to auto open a multiBox element on page load change to (1, 2, or 3 etc)
							".$extra_options."
						});
					});
				";
				$document->addScriptDeclaration($box);
			} else {
				
				// Include MultiBox CSS files
				$document->addStyleSheet(JURI::root().'components/com_flexicontent/librairies/multibox/multibox.css');
				
				// OLD ie6 hack
				$csshack = '
				<!--[if lte IE 6]>
				<style type="text/css">
				.MultiBoxClose, .MultiBoxPrevious, .MultiBoxNext, .MultiBoxNextDisabled, .MultiBoxPreviousDisabled { 
					behavior: url('.'components/com_flexicontent/librairies/multibox/iepngfix.htc); 
				}
				</style>
				<![endif]-->
				';
				$document->addCustomTag($csshack);
				
				// Include MultiBox Javascript files
				$document->addScript(JURI::root().'components/com_flexicontent/librairies/multibox/js/overlay.js');
				$document->addScript(JURI::root().'components/com_flexicontent/librairies/multibox/js/multibox.js');
				
				// Add js code for creating a multibox instance
				$extra_options = $isItemsManager ? ', showNumbers: false, showControls: false' : '';
				$box = "
					var box = {};
					window.addEvent('domready', function(){
						box = new MultiBox('mb', {descClassName: 'multiBoxDesc', useOverlay: true".$extra_options." });
					});
				";
				$document->addScriptDeclaration($box);
			}

			$multiboxadded = 1;
		}
		
		
		$i = -1;
		$field->{$prop} = array();
		foreach ($values as $value)
		{
			// Unserialize value's properties and check for empty original name property
			$value	= unserialize($value);
			if ( !strlen(trim(@$value['originalname'])) ) continue;
			$i++;
			
			
			// Check and rebuild thumbnails if needed
			$rebuild_res = $this->rebuildThumbs($field,$value);
			
			
			// Skip delete files or file that could not have their thumbs created
			if ( $rebuild_res===false ) continue;
			
			
			// Create path to the image file
			if ( $image_source ) {
				$img_folder  = $field->parameters->get('dir') .DS. 'item_'.$item->id . '_field_'.$field->id;
				$img_urlpath = $field->parameters->get('dir') . '/item_'.$item->id . '_field_'.$field->id;
			} else {
				$img_folder  = $field->parameters->get('dir');
				$img_urlpath = $field->parameters->get('dir');
			}
			$path	= JPath::clean(JPATH_SITE .DS. $img_folder .DS. 'l_' . $value['originalname']);
			
			
			// Create thumbnails urls 
			$size	= getimagesize($path);
			$hl 	= $size[1];
			$wl 	= $size[0];
			$title	= @$value['title'] ? $value['title'] : '';
			$alt	= @$value['alt'] ? $value['alt'] : flexicontent_html::striptagsandcut($item->title, 60);
			$desc	= @$value['desc'] ? $value['desc'] : '';

			$srcb	= $img_urlpath . '/b_' . $value['originalname'];  // backend
			$srcs	= $img_urlpath . '/s_' . $value['originalname'];  // small
			$srcm	= $img_urlpath . '/m_' . $value['originalname'];  // medium
			$srcl	= $img_urlpath . '/l_' . $value['originalname'];  // large
			
			
			// Create a popup url link
			$urllink = @$value['urllink'] ? $value['urllink'] : '';
			if ($urllink && false === strpos($urllink, '://')) $urllink = 'http://' . $urllink;
			
			// Create a popup tooltip (legend)
			$tip     = $title . '::' . $desc;
			$legend  = ($uselegend && (!empty($title) || !empty($desc) ) )? ' class="hasTip" title="'.$tip.'"' : '' ;
			
			// Create a unique id for the link tags, and a class name for image tags
			$uniqueid = $field->item_id . '_' . $field->id . '_' . $i;
			$class_img_field = 'fc_field_image';
			
			
			// Decide thumbnail to use
			$thumb_size = 0;
			if ($view == 'category')
				$thumb_size =  $field->parameters->get('thumbincatview',2);
			if($view == FLEXI_ITEMVIEW)
				$thumb_size =  $field->parameters->get('thumbinitemview',1);
			switch ($thumb_size)
			{
				case 1: $src = $srcs; break;
				case 2: $src = $srcm; break;
				case 3: $src = $srcl; break;   // this makes little sense, since both thumbnail and popup image are size 'large'
				default: $src = $srcs; break;
			}
			
			
			// Create a grouping name
			switch ($grouptype)
			{
				case 0: $group_name = 'fcview_'.$view.'_fcitem_'.$field->item_id.'_fcfield_'.$field->id; break;
				case 1: $group_name = 'fcview_'.$view.'_fcitem_'.$field->item_id; break;
				case 2: $group_name = 'fcview_'.$view; break;
				default: $group_name = ''; break;
			}
			
			
			// ADD some extra (display) properties that point to all sizes, currently SINGLE IMAGE only
			$field->{"display_backend_src"} = JURI::root().$srcb;
			$field->{"display_small_src"} = JURI::root().$srcs;
			$field->{"display_medium_src"} = JURI::root().$srcm;
			$field->{"display_large_src"} = JURI::root().$srcl;
			
			
			// Suggest image for external use, e.g. for Facebook etc
			if (!$isItemsManager && $useogp) {
				if ( in_array($view, $ogpinview) ) {
					switch ($ogpthumbsize)
					{
						case 1: $ogp_src = $field->{"display_small_src"}; break;   // this maybe problematic, since it maybe too small or not accepted by social website
						case 2: $ogp_src = $field->{"display_medium_src"}; break;
						case 3: $ogp_src = $field->{"display_large_src"}; break;
						default: $ogp_src = $field->{"display_medium_src"}; break;
					}
					$document->addCustomTag('<link rel="image_src" href="'.$ogp_src.'" />');
					$document->addCustomTag('<meta property="og:image" content="'.$ogp_src.'" />');
				}
			}
			
			
			// Check if a custom URL-only (display) variable was requested and return it here,
			// without rendering the extra image parameters like legend, pop-up, etc
			if ( in_array($prop, array("display_backend_src", "display_small_src", "display_medium_src", "display_large_src") ) ) {
				return $field->{$prop};
			}
			
			
			// Create image tags (according to configuration parameters) that will be used for the requested 'display' variable
			switch ($prop)
			{
				case 'display_backend':
					$img_legend   = '<img src="'.JURI::root().$srcb.'" alt ="'.$alt.'"'.$legend.' class="'.$class_img_field.'" />';
					$img_nolegend = '<img src="'.JURI::root().$srcb.'" alt ="'.$alt.'" class="'.$class_img_field.'" />';
					break;
				case 'display_small':
					$img_legend   = '<img src="'.JURI::root().$srcs.'" alt ="'.$alt.'"'.$legend.' class="'.$class_img_field.'" />';
					$img_nolegend = '<img src="'.JURI::root().$srcs.'" alt ="'.$alt.'" class="'.$class_img_field.'" />';
					break;
				case 'display_medium':
					$img_legend   = '<img src="'.JURI::root().$srcm.'" alt ="'.$alt.'"'.$legend.' class="'.$class_img_field.'" />';
					$img_nolegend = '<img src="'.JURI::root().$srcm.'" alt ="'.$alt.'" class="'.$class_img_field.'" />';
					break;
				case 'display_large':
					$img_legend   = '<img src="'.JURI::root().$srcl.'" alt ="'.$alt.'"'.$legend.' class="'.$class_img_field.'" />';
					$img_nolegend = '<img src="'.JURI::root().$srcl.'" alt ="'.$alt.'" class="'.$class_img_field.'" />';
					break;
				case 'display': default:
					$_src = $isItemsManager ? $srcb : $src;
					$img_legend   = '<img src="'.JURI::root().$_src.'" alt ="'.$alt.'"'.$legend.' class="'.$class_img_field.'" />';
					$img_nolegend = '<img src="'.JURI::root().$_src.'" alt ="'.$alt.'" class="'.$class_img_field.'" />';
					break;
			}
			
			
			// *********************************************
			// FINALLY CREATE the field display variable ...
			// *********************************************
			
			if ($isItemsManager) {
				
				// CASE 1: Handle image displayed in backend items manager
				
				if ($usepopup) {
					$field->{$prop} = '
					<a href="../'.$srcl.'" id="mb'.$uniqueid.'" class="mb" rel="[images]" >
						'.$img_legend.'
					</a>
					<div class="multiBoxDesc mb'.$uniqueid.'">'.($desc ? $desc : $title).'</div>
					';
				} else {
					$field->{$prop} = $img_legend;
				}
				return;  // Single image always ...
				
			} else if ($linkto_url && $urllink) {
				
				// CASE 2: Handle linking to a URL instead of image zooming popup
				
				if ($url_target=='multibox') {  // (a) Link to URL that opens inside a popup
					$field->{$prop}[] = '
					<script>document.write(\'<a href="'.$urllink.'" id="mb'.$uniqueid.'" class="mb" rel="width:\'+(window.getSize().x-150)+\',height:\'+(window.getSize().y-150)+\'">\')</script>
						'.$img_legend.'
					<script>document.write(\'</a>\')</script>
					<div class="multiBoxDesc mbox_img_url mb'.$uniqueid.'">'.($desc ? $desc : $title).'</div>
					';
				} else {    // (b) Just link to URL
					$field->{$prop}[] = '
					<a href="'.$urllink.'" target="'.$url_target.'">
						'.$img_legend.'
					</a>
					';
				}
				
			} else if ($usepopup) {
				
				// CASE 3: Handle image zooming popup
				
				// no popup if image is the largest one
				if ($prop=='display_large') {
					$field->{$prop}[] = $img_legend;
					continue;
				}
					
				if ($usepopup && $popuptype == 1) {   // Multibox image popup
					$group_str = $group_name ? 'rel="['.$group_name.']"' : '';
					$field->{$prop}[] = '
						<a href="'.$srcl.'" id="mb'.$uniqueid.'" class="mb" '.$group_str.' >
							'.$img_legend.'
						</a>
						<div class="multiBoxDesc mb'.$uniqueid.'">'.($desc ? $desc : $title).'</div>
						';
				} else if ($usepopup && $popuptype == 2) {   // Rokbox image popup
					$group_str = '';   // no support for image grouping
					$field->{$prop}[] = '
						<a href="'.$srcl.'" rel="rokbox['.$wl.' '.$hl.']" '.$group_str.' title="'.($desc ? $desc : $title).'">
							'.$img_nolegend.'
						</a>
						';
				} else if ($usepopup && $popuptype == 3) {   // JCE popup image popup
					$group_str = $group_name ? 'rel="group['.$group_name.']"' : '';
					$field->{$prop}[] = '
						<a href="'.$srcl.'" class="jcepopup" '.$group_str.' title="'.($desc ? $desc : $title).'">
							'.$img_nolegend.'
						</a>
						';
				} else if ($usepopup && $popuptype == 4) {   // Fancybox image popup
					$group_str = $group_name ? 'data-fancybox-group="'.$group_name.'"' : '';
					$field->{$prop}[] = '
						<a href="'.$srcl.'" class="fancybox" '.$group_str.' title="'.($desc ? $desc : $title).'">
							'.$img_nolegend.'
						</a>
						';
				}
				
			} else {
				// CASE 4: Thumbnail without popup
				$field->{$prop}[] = $img_nolegend;
			}
			
			if ( ($showtitle && $title ) || ($showdesc && $desc) )
				$field->{$prop}[$i] = '<div class="fc_img_tooltip_data" style="float:left; margin-right:8px;" >'.$field->{$prop}[$i];
				
			if ( $showtitle && $title )
				$field->{$prop}[$i] .= '<div class="fc_img_tooltip_title" style="line-height:1em; font-weight:bold;">'.$title.'</div>';
			if ( $showdesc && $desc )
				$field->{$prop}[$i] .= '<div class="fc_img_tooltip_desc" style="line-height:1em;">'.$desc.'</div>';
				
			if ( ($showtitle && $title ) || ($showdesc && $desc) )
				$field->{$prop}[$i] .= '</div>';
		}
		
		// Apply seperator and open/close tags
		if(count($field->{$prop})) {
			$field->{$prop}  = implode($separatorf, $field->{$prop});
			$field->{$prop}  = $opentag . $field->{$prop} . $closetag;
		} else {
			$field->{$prop} = '';
		}
	}
	
	
	function onBeforeSaveField($field, &$post, &$file)
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'image') return;
		if(!$post) return;

		$app = &JFactory::getApplication();
		
		// Rearrange file array so that file properties are group per image number
		$files = array();
		foreach( $file as $key => $all ) {
			foreach( $all as $i => $val ) {
				$files[$i][$key] = $val;
			}
		}
		
		//echo "<pre>"; print_r($file);
		//echo "<pre>"; print_r($post); exit;
		
		// Handle uploading / removing / deleting / replacing image files
		$n = 0;
		$new = array();
    foreach ($post as $i => $data)
    {
			// (a) Handle uploading a new original file
			$this->uploadOriginalFile($field, $data, $files[$i]);
			
			if ( $data['originalname'] || $data['existingname'] ) {
				// (b) Handle removing image assignment OR deleting the image file
				if ($data['originalname'])
				{
					if ( @$data['delete'] )
					{
						$filename = $data['originalname'];
						$this->removeOriginalFile( $field, $filename );
						//$app->enqueueMessage($field->label . ' ['.$i.'] : ' . JText::_('Deleted image from server storage'));
					}
					elseif ( @$data['remove'] )
					{
						//if ( !$data['existingname'] ) $app->enqueueMessage($field->label . ' ['.$i.'] : ' . JText::_('Removed image assignment to the field'));
					}
				}
				
				// (c) Handle replacing image with a new existing image
				if ( $data['existingname'] ) {
					//echo "<pre>"; print_r($data); exit;
					$data['originalname'] = $data['existingname'];
					unset($data['existingname']);
					$post[$i] = $data;
				} else if ( @$data['delete'] || @$data['remove'] ) {
					$post[$i] = '';
				} else {
					$post[$i] = $data;
				}
				
			} else {
				// No original file posted discard current image row
				$post[$i] = '';
			}
			
			// Add image entry to a new array skipping empty image entries
			if ($post[$i]) {
				$new[$n] = serialize($post[$i]);
				$n++;
			}
		}
		
		
		// create the fulltext search index
		if ($field->issearch) {
			$searchindex = '';
			foreach ($post as $value) {
				if ( !$value ) continue;
				
				$searchindex .= @$value['alt'];
				$searchindex .= ' ';
				$searchindex .= @$value['title'];
				$searchindex .= ' ';
				$searchindex .= @$value['desc'];
				$searchindex .= ' | ';
			}
			$field->search = $searchindex;
		} else {
			$field->search = '';
		}
		
		$data	= JRequest::getVar('jform', array(), 'post', 'array');
		if($field->isadvsearch && $data['vstate']==2) {
			plgFlexicontent_fieldsExtendedweblink::onIndexAdvSearch($field, $post);
		}
		
		// Assigned the new image data array
		$post = $new;
	}
	
	function uploadOriginalFile($field, &$post, $file)
	{
		$app = &JFactory::getApplication();
		
		$format		= JRequest::getVar( 'format', 'html', '', 'cmd');
		$err		= null;

		$cparams =& JComponentHelper::getParams( 'com_flexicontent' );
		// Get the component configuration
		$params = clone($cparams);
		// Merge field parameters into the global parameters
		$fparams = $field->parameters;
		$params->merge($fparams);
				
		jimport('joomla.utilities.date');

		// Set FTP credentials, if given
		jimport('joomla.client.helper');
		JClientHelper::setCredentialsFromRequest('ftp');

		// Make the filename safe
		jimport('joomla.filesystem.file');
		$file['name'] = JFile::makeSafe($file['name']);

		if ( isset($file['name']) && $file['name'] != '' )
		{
			// only handle the secure folder
			$path = COM_FLEXICONTENT_FILEPATH.DS;

			//sanitize filename further and make unique
			$filename = flexicontent_upload::sanitize($path, $file['name']);
			$filepath = JPath::clean(COM_FLEXICONTENT_FILEPATH.DS.strtolower($filename));
			
			//perform security check according
			if (!flexicontent_upload::check( $file, $err, $params )) {
				if ($format == 'json') {
					jimport('joomla.error.log');
					$log = &JLog::getInstance('com_flexicontent.error.php');
					$log->addEntry(array('comment' => 'Invalid: '.$filepath.': '.$err));
					header('HTTP/1.0 415 Unsupported Media Type');
					die('Error. Unsupported Media Type!');
				} else {
					JError::raiseNotice(100, $field->label . ' : ' . JText::_($err));
					return;
				}
			}
			
			//get the extension to record it in the DB
			$ext		= strtolower(JFile::getExt($filename));

			if (!JFile::upload($file['tmp_name'], $filepath)) {
				if ($format == 'json') {
					jimport('joomla.error.log');
					$log = &JLog::getInstance('com_flexicontent.error.php');
					$log->addEntry(array('comment' => 'Cannot upload: '.$filepath));
					header('HTTP/1.0 409 Conflict');
					jexit('Error. File already exists');
				} else {
					JError::raiseWarning(100, $field->label . ' : ' . JText::_('Error. Unable to upload file'));
					return;
				}
			} else {
				if ($format == 'json') {
					jimport('joomla.error.log');
					$log = &JLog::getInstance();
					$log->addEntry(array('comment' => $filepath));
					
					$db 	= &JFactory::getDBO();
					$user	= &JFactory::getUser();
					$config = &JFactory::getConfig();

					$tzoffset = $config->getValue('config.offset');
					$date = & JFactory::getDate( 'now', -$tzoffset);

					$obj = new stdClass();
					$obj->filename 			= $filename;
					$obj->altname 			= $file['name'];
					$obj->url				= 0;
					$obj->secure			= 1;
					$obj->ext				= $ext;
					$obj->hits				= 0;
					$obj->uploaded			= $date->toMySQL();
					$obj->uploaded_by		= $user->get('id');

					$db->insertObject('#__flexicontent_files', $obj);
					
					jexit('Upload complete');
				} else {

					$db 	= &JFactory::getDBO();
					$user	= &JFactory::getUser();
					$config = &JFactory::getConfig();

					$tzoffset = $config->getValue('config.offset');
					$date = & JFactory::getDate( 'now', -$tzoffset);

					$obj = new stdClass();
					$obj->filename 			= $filename;
					$obj->altname 			= $file['name'];
					$obj->url				= 0;
					$obj->secure			= 1;
					$obj->ext				= $ext;
					$obj->hits				= 0;
					$obj->uploaded			= $date->toMySQL();
					$obj->uploaded_by		= $user->get('id');

					$db->insertObject('#__flexicontent_files', $obj);

					$app->enqueueMessage($field->label . ' : ' . JText::_('Upload complete'));
					
					$sizes 		= array('l','m','s','b');
					foreach ($sizes as $size)
					{
						// create the thumbnail
						$this->create_thumb( $field, $filename, $size );
						// set the filename for posting
						$post['originalname'] = $filename;
					}
					return;
				}
			}
		}
	}


	function create_thumb( &$field, $filename, $size, $onlypath='' ) {
		// some parameters for phpthumb
		jimport('joomla.filesystem.file');
		$ext 		= strtolower(JFile::getExt($filename));
		$onlypath 	= $onlypath ? $onlypath : JPath::clean(COM_FLEXICONTENT_FILEPATH.DS);
		
		if ( $field->parameters->get('image_source', 0) ) {
			$destpath = JPATH_SITE .DS. $field->parameters->get('dir') .DS. 'item_'.$field->item_id . '_field_'.$field->id;
			$destpath = JPath::clean(str_replace('\\','/', $destpath)).DS;
		} else {
			$destpath	= JPath::clean(JPATH_SITE . DS . $field->parameters->get('dir') . DS);
		}
		
		$prefix		= $size . '_';
		$default_widths = array('l'=>800,'m'=>400,'s'=>120,'b'=>40);
		$default_heights = array('l'=>600,'m'=>300,'s'=>90,'b'=>30);
		$w			= $field->parameters->get('w_'.$size, $default_widths[$size]);
		$h			= $field->parameters->get('h_'.$size, $default_heights[$size]);
		$crop		= $field->parameters->get('method_'.$size);
		$quality	= $field->parameters->get('quality');
		$usewm		= $field->parameters->get('use_watermark_'.$size);
		$wmfile		= JPath::clean(JPATH_SITE . DS . $field->parameters->get('wm_'.$size));
		$wmop		= $field->parameters->get('wm_opacity');
		$wmpos		= $field->parameters->get('wm_position');
	
		// create the folder if it doesnt exists
		if (!JFolder::exists($destpath)) 
		{ 
			if (!JFolder::create($destpath)) 
			{ 
				JError::raiseWarning(100, $field->label . ' : ' . JText::_('Error. Unable to create folders'));
				return;
			} 
		}
		
		// because phpthumb is an external class we need to make the folder writable
		if (JPath::canChmod($destpath)) 
		{ 
				JPath::setPermissions($destpath, '0666', '0777'); 
		}
		
		// create the thumnails using phpthumb $filename
		$this->imagePhpThumb( $onlypath, $destpath, $prefix, $filename, $ext, $w, $h, $quality, $size, $crop, $usewm, $wmfile, $wmop, $wmpos );
	}


	function imagePhpThumb( $origpath, $destpath, $prefix, $filename, $ext, $width, $height, $quality, $size, $crop, $usewm, $wmfile, $wmop, $wmpos )
	{
		$lib = JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'librairies'.DS.'phpthumb'.DS.'phpthumb.class.php';		
		require_once ( $lib );

		unset ($phpThumb);
		$phpThumb = new phpThumb();
		
		$filepath = $origpath . $filename;
		
		$phpThumb->setSourceFilename($filepath);
		$phpThumb->setParameter('config_output_format', "$ext");
		$phpThumb->setParameter('w', $width);
		$phpThumb->setParameter('h', $height);
		if ($usewm == 1)
		{
			$phpThumb->setParameter('fltr', 'wmi|'.$wmfile.'|'.$wmpos.'|'.$wmop);
		}
		$phpThumb->setParameter('q', $quality);
		if ($crop == 1)
		{
			$phpThumb->setParameter('zc', 1);
		}
		
		$ext = pathinfo($filename, PATHINFO_EXTENSION);
		if ( in_array( $ext, array('png', 'ico', 'gif') ) )
		{
			$phpThumb->setParameter('f', $ext);
		}
		
		$output_filename = $destpath . $prefix . $filename ;

		if ($phpThumb->GenerateThumbnail())
		{
			//echo "generated!<br />";
			//die();
			if ($phpThumb->RenderToFile($output_filename))
			{
				 //echo "rendered!<br />";
				// die();
			} else {
				echo 'Failed:<pre>' . implode("\n\n", $phpThumb->debugmessages) . '</pre><br />';
				//die();
			}
		} else {
			echo 'Failed2:<pre>' . $phpThumb->fatalerror . "\n\n" . implode("\n\n", $phpThumb->debugmessages) . '</pre><br />';
			//echo 'Failed:<div class="error">Size is too big!</pre><br />';
			//die();
		}
	}

	function removeOriginalFile( $field, $filename )
	{
		jimport('joomla.filesystem.file');	

		$db =& JFactory::getDBO();
		$image_source = $field->parameters->get('image_source', 0);

		if ( $image_source ) {
			$origfolder  = JPATH_SITE .DS. $field->parameters->get('dir') .DS. 'item_'.$field->item_id . '_field_'.$field->id .DS. 'original';
			$origfolder  = JPath::clean(str_replace('\\','/', $origfolder)).DS;
			$thumbfolder = JPATH_SITE .DS. $field->parameters->get('dir') .DS. 'item_'.$field->item_id . '_field_'.$field->id;
		} else {
			$origfolder  = JPath::clean( COM_FLEXICONTENT_FILEPATH );
			$thumbfolder = JPath::clean( JPATH_SITE .DS. $field->parameters->get('dir') );
		}
		
		// a. Delete the thumbnails
		$errors		= array();
		$sizes 		= array('l','m','s','b');
		foreach ($sizes as $size)
		{
			$thumbpath = $thumbfolder . DS . $size . '_' . $filename;
			if ( JFile::exists($thumbpath) && !JFile::delete($thumbpath) )
			{ 
				// Handle failed delete, currently this is not outputed, since thumbnails may not have been created, or may have been deleted manually ??
				JError::raiseNotice(100, JText::_('FLEXI_FIELD_UNABLE_TO_DELETE_FILE') .": ". $thumbpath);
			}
		}
		
		// b. Delete the original image from file manager
		$origpath = JPath::clean($origfolder.DS.$filename);
		if (!JFile::delete($origpath)) {
			JError::raiseNotice(100, JText::_('FLEXI_FIELD_UNABLE_TO_DELETE_FILE') .": ". $origpath);
		}

		if ( $image_source ) {
			// Done nothing more to clean
		} else {
			$query = 'DELETE FROM #__flexicontent_files'
				. ' WHERE ' . $db->nameQuote('filename') . ' = ' . $db->Quote($filename);
			$db->setQuery( $query );
			if(!$db->query())
			{
				$this->setError($db->getErrorMsg());
				return false;
			}
		}
		
		return true;
	}


	function rebuildThumbs( &$field, $value )
	{
		$filename = trim($value['originalname']);
		if ( !$filename ) return;  // check for empty filename
		
		
		// ******************************
		// Find out path to original file
		// ******************************
		if (empty($value['is_default_image'])) {
			
			if ( $field->parameters->get('image_source', 0) ) {
				$onlypath = JPATH_SITE .DS. $field->parameters->get('dir') .DS. 'item_'.$field->item_id . '_field_'.$field->id .DS. 'original';
				$onlypath = JPath::clean(str_replace('\\','/', $onlypath)).DS;
			} else {
				$onlypath 	= JPath::clean(COM_FLEXICONTENT_FILEPATH.DS);
			}
			$filepath = $onlypath . $filename;
			
		} else {
			
			$onlypath = JPATH_BASE.DS.dirname($value['default_image']).DS;
			$filepath = JPATH_BASE.DS.$value['default_image'];
		}
		
		if ( !file_exists($filepath) || !is_file($filepath) ) {
			//echo "Original file seems to have been deleted or is not a file, cannot find image file: ".$filepath ."<br />\n";
			return false;
		}
		
		
		// ***********************************************
		// Check dimension of thumbs and rebuild as needed
		// ***********************************************
		$filesize	= getimagesize($filepath);
		$origsize_h = $filesize[1];
		$origsize_w = $filesize[0];
		
		$sizes = array('l','m','s','b');
		$default_widths = array('l'=>800,'m'=>400,'s'=>120,'b'=>40);
		$default_heights = array('l'=>600,'m'=>300,'s'=>90,'b'=>30);
		
		foreach ($sizes as $size)
		{
			$path	= JPath::clean(JPATH_SITE .DS. $field->parameters->get('dir') .DS. $size . '_' . $filename);
			$thumbnail_exists = false;
			if (file_exists($path)) {
				$filesize = getimagesize($path);
				$filesize_w = $filesize[0];
				$filesize_h = $filesize[1];
				$param_w = $field->parameters->get( 'w_'.$size, $default_widths[$size] );
				$param_h = $field->parameters->get( 'h_'.$size, $default_heights[$size] );
				$crop = $field->parameters->get('method_'.$size);
				$thumbnail_exists = true;
			}
			
			// Check if size of file is not same as parameters and recreate the thumbnail
			if (
					!$thumbnail_exists ||
					( $crop==0 && (
													($origsize_w >= $param_w && $filesize_w != $param_w) &&  // scale width can be larger than it is currently
													($origsize_h >= $param_h && $filesize_h != $param_h)     // scale height can be larger than it is currently
												)
					) ||
					( $crop==1 && (
													($param_w <= $origsize_w && $filesize_w != $param_w) ||  // crop width can be smaller than it is currently
													($param_h <= $origsize_h && $filesize_h != $param_h)     // crop height can be smaller than it is currently
												)
					)
				 )
			 {
				//echo "SIZE: $size CROP: $crop OLDSIZE(w,h): $filesize_w,$filesize_h  NEWSIZE(w,h): $param_w,$param_h <br />";
				$this->create_thumb( $field, $filename, $size, $onlypath );
			}
		}
	}


	function buildSelectList( $field )
	{
		$db   = & JFactory::getDBO();
		$app  = & JFactory::getApplication();
		$user = & JFactory::getUser();
		
		// Get configuration parameters
		$required		= $field->parameters->get( 'required', 0 ) ;
		$required		= $required ? ' required' : '';
		$autoupload = $field->parameters->get('autoupload', 1);
		$any_field_records = $field->parameters->get('list_all_media_files', 0);
		$limit_by_uploader = $field->parameters->get('limit_by_uploader', 0);  // USED ONLY WHEN any_field_records is ENABLED
		$adminprefix = $app->isAdmin() ? '../' : '';
		
		// Retrieve available (and appropriate) images from the DB
		if ($any_field_records) {
			$query = 'SELECT filename'
				. ' FROM #__flexicontent_files'
				. ' WHERE secure=1 AND ext IN ("jpg","gif","png","jpeg") '
				.(($limit_by_uploader)?" AND uploaded_by={$user->id}":"")
				;
		} else {
			$query = 'SELECT value'
				. ' FROM #__flexicontent_fields_item_relations'
				. ' WHERE field_id = '. (int) $field->id
				;
		}
		$db->setQuery($query);
		$values = $db->loadResultArray();
		
		// Create original filenames array skipping any empty records
		if (!$any_field_records) {
			for($n=0, $c=count($values); $n<$c; $n++) {
				if (!$values[$n]) { unset($values[$n]); continue; }
				$values[$n] = unserialize($values[$n]);
				$values[$n] = $values[$n]['originalname'];
			}
		}
		
		// Eliminate duplicate records in the array
		$values = array_unique($values);
		sort($values);
		
		// Create attributes of the drop down field for selecting existing images
		$class = ' class="existingname"';
		
		$onchange = ' onchange="';
		$onchange .= ($required) ? ' fx_img_toggle_required(this,$(\''.$field->name.'_newfile\')); ' : '';
		$onchange .= " qmAssignFile".$field->id."(this.id, '', '".$adminprefix.$field->parameters->get('dir')."/s_'+this.value);";
		$onchange .= ' "';
		
		$attribs = $onchange." ".$class;
		
		// Populate the select field options
		$options = array(); 
		$options[] = JHTML::_('select.option', '', JText::_('FLEXI_FIELD_PLEASE_SELECT'));
		foreach ($values as $value) {
			$options[] = JHTML::_('select.option', $value, $value); 
		}
		
		// Finally create the select field and return it
		$formfldname = '__FORMFLDNAME__';
		$formfldid = '__FORMFLDID__';
		$list	= JHTML::_('select.genericlist', $options, $formfldname, $attribs, 'value', 'text', '', $formfldid);

		return $list;
	}

	function canDeleteImage( $field, $record )
	{
		$db =& JFactory::getDBO();

		$query = 'SELECT value'
				. ' FROM #__flexicontent_fields_item_relations'
				. ' WHERE field_id = '. (int) $field->id
				;
		$db->setQuery($query);
		$values = $db->loadResultArray();
		
		$i = 0;
		for($n=0, $c=count($values); $n<$c; $n++)
		{
			$values[$n] = unserialize($values[$n]);
			$values[$n] = $values[$n]['originalname'];
			if ($values[$n] == $record) {
				if (++$i > 1) return false;
			}
		}
		
		return true;
	}

	function listImageUses( $field, $record )
	{
		// Function is not called anywhere, used only for debugging
		
		$db =& JFactory::getDBO();

		$query = 'SELECT value, item_id'
				. ' FROM #__flexicontent_fields_item_relations'
				. ' WHERE field_id = '. (int) $field->id
				;
		$db->setQuery($query);
		$values = $db->loadObjectList();
		
		$itemid_list = ''; $sep = '';
		for($n=0, $c=count($values); $n<$c; $n++)
		{
			$val = unserialize($values[$n]->value);
			$val = $val['originalname'];
			if ($val == $record) {
				$itemid_list .= $sep . $values[$n]->item_id.",";
				$sep = ',';
			}
		}
		
		return $itemid_list;
	}
	
}
