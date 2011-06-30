<?php
/**
 * @version 1.0 $Id$
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.extendedweblink
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

class plgFlexicontent_fieldsExtendedWeblink extends JPlugin
{
	function plgFlexicontent_fieldsExtendedWeblink( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_extendedweblink', JPATH_ADMINISTRATOR);
	}
	function onAdvSearchDisplayField(&$field, &$item) {
		plgFlexicontent_fieldsExtendedWeblink::onDisplayField($field, $item);
	}
	function onDisplayField(&$field, &$item)
	{
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'extendedweblink') return;

		// some parameter shortcuts
		$multiple			= $field->parameters->get( 'allow_multiple', 1 ) ;
		$maxval				= $field->parameters->get( 'max_values', 0 ) ;
		$default_value		= $field->parameters->get( 'default_value', '' ) ;
		$size				= $field->parameters->get( 'size', 30 ) ;
								
		$required 			= $field->parameters->get( 'required', 0 ) ;
		$required 	= $required ? ' required' : '';
		
		// initialise property
		if($item->version < 2 && $default_value) {
			$field->value = array();
			$field->value[0] = JText::_($default_value);
		} elseif (!$field->value) {
			$field->value = array();
			$field->value[0] = '';
		}
			
		if ($multiple) {
			$document	= & JFactory::getDocument();

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

			$js = "
			var uniqueRowNum".$field->id."	= ".count($field->value).";
			var curRowNum".$field->id."	= ".count($field->value).";
			var maxVal".$field->id."		= ".$maxval.";

			function addField".$field->id."(el) {
				if((curRowNum".$field->id." < maxVal".$field->id.") || (maxVal".$field->id." == 0)) {

					var thisField 	 = $(el).getPrevious().getLast();
					var thisNewField = thisField.clone();
					var fx			 = thisNewField.effects({duration: 0, transition: Fx.Transitions.linear});

					thisNewField.getElements('input.urllink').setProperty('value','');
					thisNewField.getElements('input.urllink').setProperty('name','".$field->name."['+uniqueRowNum".$field->id."+'][link]');

					thisNewField.getElements('input.urltitle').setProperty('value','');
					thisNewField.getElements('input.urltitle').setProperty('name','".$field->name."['+uniqueRowNum".$field->id."+'][title]');

					thisNewField.getElements('input.urlhits').setProperty('value','0');
					thisNewField.getElements('input.urlhits').setProperty('name','".$field->name."['+uniqueRowNum".$field->id."+'][hits]');
					
					thisNewField.getElements('span span').setHTML('0');

					thisNewField.injectAfter(thisField);
		
					new Sortables($('sortables_".$field->id."'), {
						'constrain': true,
						'clone': true,
						'handle': '.drag".$field->id."'
					});			

					fx.start({ 'opacity': 1 }).chain(function(){
						this.setOptions({duration: 600});
						this.start({ 'opacity': 0 });
						})
						.chain(function(){
							this.setOptions({duration: 300});
							this.start({ 'opacity': 1 });
						});

					curRowNum".$field->id."++;
					uniqueRowNum".$field->id."++;
					}
				}

			function deleteField".$field->id."(el) {
				if(curRowNum".$field->id." > 1) {

				var field	= $(el);
				var row		= field.getParent();
				var fx		= row.effects({duration: 300, transition: Fx.Transitions.linear});
				
				fx.start({
					'height': 0,
					'opacity': 0			
					}).chain(function(){
						row.remove();
					});
				curRowNum".$field->id."--;
				}
			}
			";
			$document->addScriptDeclaration($js);
			
			$css = '
			#sortables_'.$field->id.' { margin: 0px; padding: 0px; list-style: none; white-space: nowrap; }
			#sortables_'.$field->id.' li {
				list-style: none;
				display: block;
				}
			#sortables_'.$field->id.' li input { cursor: text;}
			#sortables_'.$field->id.' li input.fcbutton, .fcbutton { cursor: pointer; margin-left: 3px; }
			#add'.$field->name.' { margin-top: 5px; clear: both; display:block; }
			span.drag'.$field->id.' img {
				margin: -4px 8px;
				cursor: move;
			}
			#sortables_'.$field->id.' li .admintable {
				
				text-align: right;
			}
			#sortables_'.$field->id.' li:only-child span.drag, #sortables_'.$field->id.' li:only-child input.fcbutton {
				display:none;
			}
			';
			$document->addStyleDeclaration($css);

			$move2 	= JHTML::image ( 'administrator/components/com_flexicontent/assets/images/move3.png', JText::_( 'FLEXI_CLICK_TO_DRAG' ) );
			$n = 0;
			$field->html = '<ul id="sortables_'.$field->id.'">';

			foreach ($field->value as $value) {
				$value  = unserialize($value);
				
				$linktext = $field->parameters->get( 'use_linktext', 0 ) ? true : '';
				if($linktext) $linktext = '<tr><td class="key">'.JText::_( 'FLEXI_FIELD_URLLINK_TEXT' ).':</td><td><input class="urllinktext" name="'.$field->name.'['.$n.'][linktext]" type="text" size="'.$size.'" value="'.($value['linktext'] ? $value['linktext'] : $field->parameters->get( 'linktext_default' )).'" /></td></tr>';
				
				$class = $field->parameters->get( 'use_class', 0 ) ? true : '';
				if($class) $class = '<tr><td class="key">'.JText::_( 'FLEXI_FIELD_URLCLASS' ).':</td><td><input class="urlclass" name="'.$field->name.'['.$n.'][class]" type="text" size="'.$size.'" value="'.($value['class'] ? $value['class'] : $field->parameters->get( 'class_default', null )).'" /></td></tr>';
				
				$id = $field->parameters->get( 'use_id', 0 ) ? true : '';
				if($id) $id = '<tr><td class="key">'.JText::_( 'FLEXI_FIELD_URLID' ).':</td><td><input class="urlid" name="'.$field->name.'['.$n.'][id]" type="text" size="'.$size.'" value="'.($value['id'] ? $value['id'] : $field->parameters->get( 'id_default', null )).'" /></td></tr>';
				
				$field->html	.= '
				<li>
					<table class="admintable"><tbody>
						<tr>
						<td class="key">'.JText::_( 'FLEXI_FIELD_URL' ).':</td>
						<td><input class="urllink'.$required.'" name="'.$field->name.'['.$n.'][link]" type="text" size="'.$size.'" value="'.($value['link'] ? $value['link'] : $default_value).'" /></td>
						</tr>
						<tr>
						<td class="key">'.JText::_( 'FLEXI_FIELD_URLTITLE' ).':</td>
						<td><input class="urltitle'.$required.'" name="'.$field->name.'['.$n.'][title]" type="text" size="'.$size.'" value="'.($value['title'] ? $value['title'] : $default_value).'" /></td>
						</tr>
						'.$linktext.'
						'.$class.'
						'.$id.'
					</tbody></table>
					<input class="fcbutton" type="button" value="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);" /><span class="drag'.$field->id.'">'.$move2.'</span>
					<input class="urlhits" name="'.$field->name.'['.$n.'][hits]" type="hidden" value="'.($value['hits'] ? $value['hits'] : 0).'" />
					<span class="hits"><span class="hitcount">'.($value['hits'] ? $value['hits'] : 0).'</span> '.JText::_( 'FLEXI_FIELD_HITS' ).'</span>
					
				</li>';
				$n++;
				}
			$field->html .=	'</ul>';
			$field->html .= '<input type="button" id="add'.$field->name.'" onclick="addField'.$field->id.'(this);" value="'.JText::_( 'FLEXI_ADD_WEBLINK' ).'" />';

		} else {
			$value = unserialize($field->value[0]);
			$n = 0;
			
			$linktext = $field->parameters->get( 'use_linktext', 0 ) ? true : '';
			if($linktext) $linktext = '<tr><td class="key">'.JText::_( 'FLEXI_FIELD_URLLINK_TEXT' ).':</td><td><input class="urllinktext" name="'.$field->name.'['.$n.'][linktext]" type="text" size="'.$size.'" value="'.($value['linktext'] ? $value['linktext'] : $field->parameters->get( 'linktext_default' )).'" /></td></tr>';
			
			$class = $field->parameters->get( 'use_class', 0 ) ? true : '';
			if($class) $class = '<tr><td class="key">'.JText::_( 'FLEXI_FIELD_URLCLASS' ).':</td><td><input class="urlclass" name="'.$field->name.'['.$n.'][class]" type="text" size="'.$size.'" value="'.($value['class'] ? $value['class'] : $field->parameters->get( 'class_default', null )).'" /></td></tr>';
			
			$id = $field->parameters->get( 'use_id', 0 ) ? true : '';
			if($id) $id = '<tr><td class="key">'.JText::_( 'FLEXI_FIELD_URLID' ).':</td><td><input class="urlid" name="'.$field->name.'['.$n.'][id]" type="text" size="'.$size.'" value="'.($value['id'] ? $value['id'] : $field->parameters->get( 'id_default', null )).'" /></td></tr>';
			
			$field->html	.= '
				<table class="admintable"><tbody>
					<tr>
					<td class="key">'.JText::_( 'FLEXI_FIELD_URL' ).':</td>
					<td><input class="urllink'.$required.'" name="'.$field->name.'['.$n.'][link]" type="text" size="'.$size.'" value="'.($value['link'] ? $value['link'] : $default_value).'" /></td>
					</tr>
					<tr>
					<td class="key">'.JText::_( 'FLEXI_FIELD_URLTITLE' ).':</td>
					<td><input class="urltitle'.$required.'" name="'.$field->name.'['.$n.'][title]" type="text" size="'.$size.'" value="'.($value['title'] ? $value['title'] : $default_value).'" /></td>
					</tr>
					'.$linktext.'
					'.$class.'
					'.$id.'
				</tbody></table>
				<input class="urlhits" name="'.$field->name.'['.$n.'][hits]" type="hidden" value="'.($value['hits'] ? $value['hits'] : 0).'" />
				<span class="hits"><span class="hitcount">'.($value['hits'] ? $value['hits'] : 0).'</span> '.JText::_( 'FLEXI_FIELD_HITS' ).'</span>';
		}
	}


	function onBeforeSaveField( $field, &$post, &$file )
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'extendedweblink') return;
		if(!$post) return;
		
		// reformat the post
		$newpost = array();
		$new = 0;

		foreach ($post as $n=>$v)
		{
			if ($post[$n]['link'] != '')
			{
				if (!preg_match("#^http|^https|^ftp#i", $post[$n]['link'])) 
				{
					$newpost[$new]['link']		= 'http://'.$post[$n]['link'];
					$newpost[$new]['title']		= $post[$n]['title'];
					$newpost[$new]['id']		= $post[$n]['id'];
					$newpost[$new]['class']		= $post[$n]['class'];
					$newpost[$new]['linktext']	= $post[$n]['linktext'];
					$newpost[$new]['hits']		= $post[$n]['hits'];
				} else {
					$newpost[$new]['link']		= $post[$n]['link'];
					$newpost[$new]['title']		= $post[$n]['title'];
					$newpost[$new]['id']		= $post[$n]['id'];
					$newpost[$new]['class']		= $post[$n]['class'];
					$newpost[$new]['linktext']	= $post[$n]['linktext'];
					$newpost[$new]['hits']		= $post[$n]['hits'];
				}
				$new++;
			}
		}
		$post = $newpost;
		
		// create the fulltext search index
		if ($field->issearch) {
			$searchindex = '';
			
			foreach ($post as $v)
			{
				$searchindex .= $v['link'];
				$searchindex .= ' ';
				$searchindex .= $v['title'];
				$searchindex .= ' ';
			}
			$searchindex .= ' | ';
			$field->search = $searchindex;
		} else {
			$field->search = '';
		}
	}


	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'extendedweblink') return;
		
		$values = $values ? $values : $field->value ;

		// some parameter shortcuts
		$separatorf			= $field->parameters->get( 'separatorf', 1 ) ;
		$usetitle			= $field->parameters->get( 'use_title', 0 ) ;
		$uselinktext		= $field->parameters->get( 'use_linktext', 0 ) ;
		$useclass			= $field->parameters->get( 'use_class', 0 ) ;
		$useid				= $field->parameters->get( 'use_id', 0 ) ;
								
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

			default:
			$separatorf = '&nbsp;';
			break;
		}
		
		// initialise property
		$field->{$prop} = array();

		$n = 0;
		foreach ($values as $value) {
			$value  = unserialize($value);
			$class  = $useclass && isset($value['class']) ? ' class="'.$value['class'].'"' : null;
			$id     = $useid && isset($value['id']) ? ' id="'.$value['id'].'"' : null;
			$target = $field->parameters->get( 'target', false ) ? ' target="'.$field->parameters->get( 'target').'"' : null;
			$text   = $uselinktext && isset($value['linktext']) ? $value['linktext'] : null;
			if(!$text) $text = $usetitle ? $value['title'] : $this->cleanurl($value['link']);
			$field->{$prop}[]	= $value ? '<a href="' . JRoute::_( 'index.php?fid='. $field->id .'&cid='.$field->item_id.'&ord='.($n+1).'&task=weblink' ) . '"'.$id.$class.$target.' title="' . $value['title'] . '">'.( $text ).'</a>' : '';
			$n++;
			}
		$field->{$prop} = implode($separatorf, $field->{$prop});
	}

	function cleanurl($url)
	{
		$prefix = array("http://", "https://", "ftp://");
		$cleanurl = str_replace($prefix, "", $url);
		return $cleanurl;
	}
}
