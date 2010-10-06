<?php
/**
 * @version 1.0 $Id: weblink.php 343 2010-06-28 05:29:06Z emmanuel.danan $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.weblink
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

class plgFlexicontent_fieldsWeblink extends JPlugin
{
	function plgFlexicontent_fieldsWeblink( &$subject, $params )
	{
		parent::__construct( $subject, $params );
        JPlugin::loadLanguage('plg_flexicontent_fields_weblink', JPATH_ADMINISTRATOR);
	}

	function onDisplayField(&$field, $item)
	{
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'weblink') return;

		// some parameter shortcuts
		$required 			= $field->parameters->get( 'required', 0 ) ;
		$multiple			= $field->parameters->get( 'allow_multiple', 1 ) ;
		$maxval				= $field->parameters->get( 'max_values', 0 ) ;
		$default_link		= $field->parameters->get( 'default_value_link', '' ) ;
		$default_title		= $field->parameters->get( 'default_value_title', '' ) ;
		$size				= $field->parameters->get( 'size', 30 ) ;
								
		$required 	= $required ? ' class="required"' : '';
		
		// initialise property
		if($item->version < 2 && $default_link) {
			$field->value = array();
			$field->value[0]['link'] = $default_link;
			$field->value[0]['title'] = $default_title;
			$field->value[0]['hits'] = 0;
			$field->value[0] = serialize($field->value[0]);
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

			$move2 	= JHTML::image ( 'administrator/components/com_flexicontent/assets/images/move3.png', JText::_( 'FLEXI_CLICK_TO_DRAG' ) );
			$n = 0;
			$field->html = '<ul id="sortables_'.$field->id.'">';

			foreach ($field->value as $value) {
				$value = unserialize($value);
				$field->html	.= '
				<li>
					<span class="legende">'.JText::_( 'FLEXI_FIELD_URL' ).':</span>
					<input class="urllink" name="'.$field->name.'['.$n.'][link]" type="text" size="'.$size.'" value="'.$value['link'].'" />
					<span class="legende">'.JText::_( 'FLEXI_FIELD_URLTITLE' ).':</span>
					<input class="urltitle" name="'.$field->name.'['.$n.'][title]" type="text" size="'.$size.'" value="'.$value['title'].'" />
					<input class="urlhits" name="'.$field->name.'['.$n.'][hits]" type="hidden" value="'.$value['hits'].'" />
					<span class="hits"><span class="hitcount">'.($value['hits'] ? $value['hits'] : 0).'</span> '.JText::_( 'FLEXI_FIELD_HITS' ).'</span>
					<input class="fcbutton" type="button" value="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);" /><span class="drag">'.$move2.'</span>
				</li>';
				$n++;
				}
			$field->html .=	'</ul>';
			$field->html .= '<input type="button" id="add'.$field->name.'" onclick="addField'.$field->id.'(this);" value="'.JText::_( 'FLEXI_ADD_VALUE' ).'" />';

		} else {
			$field->value[0] = unserialize($field->value[0]);
			$field->html	= '<div>Url: <input name="'.$field->name.'[0][link]" type="text" size="'.$size.'" value="'.$field->value[0]['link'].'" /> Title: <input name="'.$field->name.'[0][title]" type="text" size="'.$size.'" value="'.$field->value[0]['title'].'" /><input name="'.$field->name.'[0][hits]" type="hidden" value="'.($field->value[0]['hits'] ? $field->value[0]['hits'] : 0).'" /> '.($field->value[0]['hits'] ? $field->value[0]['hits'] : 0).' '.JText::_( 'FLEXI_FIELD_HITS' ).' </div>';
		}
	}


	function onBeforeSaveField( $field, &$post, &$file )
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'weblink') return;
		
		// reformat the post
		$newpost = array();
		$new = 0;

		foreach ($post as $n=>$v)
		{
			if ($post[$n]['link'] != '')
			{
				if (!preg_match("#^http|^https|^ftp#i", $post[$n]['link'])) 
				{
					$newpost[$new]['link']	= 'http://'.$post[$n]['link'];
					$newpost[$new]['title']	= $post[$n]['title'];
					$newpost[$new]['hits']	= $post[$n]['hits'];
				} else {
					$newpost[$new]['link']	= $post[$n]['link'];
					$newpost[$new]['title']	= $post[$n]['title'];
					$newpost[$new]['hits']	= $post[$n]['hits'];
				}
				$new++;
			}
		}
		$post = $newpost;
		
		// create the fulltext search index
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
	}


	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'weblink') return;
		
		$values = $values ? $values : $field->value ;

		// some parameter shortcuts
		$separatorf			= $field->parameters->get( 'separatorf', 1 ) ;
		$usetitle			= $field->parameters->get( 'use_title', 0 ) ;
		$target				= $field->parameters->get( 'targetblank', 0 ) ? ' target="_blank"' : '';
								
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
			$value = unserialize($value);
			$field->{$prop}[]	= $value ? '<a href="' . JRoute::_( 'index.php?fid='. $field->id .'&cid='.$field->item_id.'&ord='.($n+1).'&task=weblink' ) . '" title="' . $value['title'] . '"' . $target . '>'.( $usetitle ? $value['title'] : $this->cleanurl($value['link']) ).'</a>' : '';
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