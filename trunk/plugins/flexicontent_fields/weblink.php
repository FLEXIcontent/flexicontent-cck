<?php
/**
 * @version 1.0 $Id$
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
	
	function onAdvSearchDisplayField(&$field, &$item)
	{
		if($field->field_type != 'weblink') return;
		plgFlexicontent_fieldsWeblink::onDisplayField($field, $item);
	}
	
	// This function is called just before the item is deleted to remove custom item data related to the field
	function onBeforeDeleteField(&$field, &$item)
	{
	}
	
	// This function is called to display the field in item edit/submit form
	function onDisplayField(&$field, &$item)
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'weblink') return;
		
		$field->label = JText::_($field->label);
		
		// some parameter shortcuts
		$size      = $field->parameters->get( 'size', 30 ) ;
		$multiple  = $field->parameters->get( 'allow_multiple', 1 ) ;
		$maxval    = $field->parameters->get( 'max_values', 0 ) ;
		
		$default_link     = ($item->version == 0) ? $field->parameters->get( 'default_value_link', '' ) : '';
		
		$usetitle      = $field->parameters->get( 'use_title', 0 ) ;
		$title_usage   = $field->parameters->get( 'title_usage', 0 ) ;
		$default_title = ($item->version == 0 || $title_usage > 0)  ?  JText::_($field->parameters->get( 'default_value_title', '' )) : '';
		
		$required		= $field->parameters->get( 'required', 0 ) ;
		$required		= $required ? ' required' : '';
		
		// initialise property
		if (!$field->value) {
			$field->value = array();
			$field->value[0]['link']  = JText::_($default_link);
			$field->value[0]['title'] = JText::_($default_title);
			$field->value[0]['hits']  = 0;
			$field->value[0] = serialize($field->value[0]);
		}
		
		$document	= & JFactory::getDocument();
		
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

			$js = "
			var uniqueRowNum".$field->id."	= ".count($field->value).";  // Unique row number incremented only
			var rowCount".$field->id."	= ".count($field->value).";      // Counts existing rows to be able to limit a max number of values
			var maxVal".$field->id."		= ".$maxval.";

			function addField".$field->id."(el) {
				if((rowCount".$field->id." < maxVal".$field->id.") || (maxVal".$field->id." == 0)) {

					var thisField 	 = $(el).getPrevious().getLast();
					var thisNewField = thisField.clone();
					var fx = thisNewField.effects({duration: 0, transition: Fx.Transitions.linear});
					
					thisNewField.getElements('input.urllink').setProperty('value','');
					thisNewField.getElements('input.urllink').setProperty('name','".$field->name."['+uniqueRowNum".$field->id."+'][link]');
					";
					
			if ($usetitle) $js .= "
					thisNewField.getElements('input.urltitle').setProperty('value','');
					thisNewField.getElements('input.urltitle').setProperty('name','".$field->name."['+uniqueRowNum".$field->id."+'][title]');
					";
					
			$js .= "
					thisNewField.getElements('input.urlhits').setProperty('value','0');
					thisNewField.getElements('input.urlhits').setProperty('name','".$field->name."['+uniqueRowNum".$field->id."+'][hits]');
					
					thisNewField.getElements('span span').setHTML('0');  // Set hits to zero for new row value

					thisNewField.injectAfter(thisField);
		
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
					var fx		= row.effects({duration: 300, transition: Fx.Transitions.linear});
					
					fx.start({
						'height': 0,
						'opacity': 0
						}).chain(function(){
							row.remove();
						});
					rowCount".$field->id."--;
				}
			}
			";
			$document->addScriptDeclaration($js);
			
			$css = '
			#sortables_'.$field->id.' { float:left; margin: 0px; padding: 0px; list-style: none; white-space: nowrap; }
			#sortables_'.$field->id.' li {
				clear: both;
				display: block;
				list-style: none;
				height: 20px;
				position: relative;
			}
			#sortables_'.$field->id.' li.sortabledisabled {
				background : transparent url(components/com_flexicontent/assets/images/move3.png) no-repeat 0px 1px;
			}
			#sortables_'.$field->id.' li input { cursor: text;}
			';
			
			$move2 	= JHTML::image ( JURI::root().'administrator/components/com_flexicontent/assets/images/move3.png', JText::_( 'FLEXI_CLICK_TO_DRAG' ) );
			$remove_button = '<input class="fcfield-button" type="button" value="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);" /><span class="fcfield-drag">'.$move2.'</span>';
		} else {
			$remove_button = '';
			$css = '';
		}
		
		$css .='
			#sortables_'.$field->id.' label.legende, #sortables_'.$field->id.' input.urllink, #sortables_'.$field->id.' input.urltitle, #sortables_'.$field->id.' input.fcfield-button {
				float: none!important;
				display: inline-block!important;
			}
		';
		$document->addStyleDeclaration($css);
		
		$field->html = array();
		$n = 0;
		foreach ($field->value as $value) {
			if ( @unserialize($value)!== false || $value === 'b:0;' ) {
				$value = unserialize($value);
			} else {
				$value = array('link' => $value, 'title' => '', 'hits'=>0);
			}
			$fieldname = FLEXI_J16GE ? 'custom['.$field->name.']['.$n.']' : $field->name.'['.$n.']';
			
			$link = '
				<label class="legende" for="'.$fieldname.'[link]">'.JText::_( 'FLEXI_FIELD_URL' ).':</label>
				<input class="urllink'.$required.'" name="'.$fieldname.'[link]" type="text" size="'.$size.'" value="'.$value['link'].'" />
			';
			
			if ($usetitle) $title = '
				<label class="legende" for="'.$fieldname.'[title]">'.JText::_( 'FLEXI_FIELD_URLTITLE' ).':</label>
				<input class="urltitle" name="'.$fieldname.'[title]" type="text" size="'.$size.'" value="'.@$value['title'].'" />
			';
			
			$hits= '
				<input class="urlhits" name="'.$fieldname.'[hits]" type="hidden" value="'.$value['hits'].'" />
				<span class="hits"><span class="hitcount">'.$value['hits'].'</span> '.JText::_( 'FLEXI_FIELD_HITS' ).'</span>
			';
			
			$field->html[] = '
				'.$link.'
				'.@$title.'
				'.$hits.'
				'.$remove_button.'
			';
			$n++;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}
		
		if ($multiple) { // handle multiple records
			$field->html = '<li>'. implode('</li><li>', $field->html) .'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
			$field->html .= '<input type="button" class="fcfield-addvalue" onclick="addField'.$field->id.'(this);" value="'.JText::_( 'FLEXI_ADD_VALUE' ).'" />';
		} else {  // handle single values
			$field->html = '<div>'.$field->html[0].'</div>';
		}
	}


	function onBeforeSaveField( $field, &$post, &$file )
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'weblink') return;
		if(!$post) return;
		
		// reformat the post
		$newpost = array();
		$new = 0;
		
		// make sure posted data is an array 
		$post = !is_array($post) ? array($post) : $post;
		
		foreach ($post as $n=>$v)
		{
			if ($post[$n]['link'] != '')
			{
				$newpost[$new] = $post[$n];
				$http_prefix = (!preg_match("#^http|^https|^ftp#i", $post[$n]['link'])) ? 'http://' : '';
				$newpost[$new]['link']  = $http_prefix.$post[$n]['link'];
				$newpost[$new]['title'] = strip_tags(@$post[$n]['title']);
				$newpost[$new]['hits']  = (int) $post[$n]['hits'];
				$new++;
			}
		}
		$post = $newpost;
		
		// create the fulltext search index
		if ($field->issearch) {
			$searchindex = '';
			
			foreach($post as $i => $v)
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
		
		if($field->isadvsearch && JRequest::getVar('vstate', 0)==2) {
			plgFlexicontent_fieldsWeblink::onIndexAdvSearch($field, $post);
		}
		
		// Serialize multiproperty data before storing into the DB
		foreach($post as $i => $v) {
			$post[$i] = serialize($v);
		}
	}
	
	function onIndexAdvSearch(&$field, $post) {
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'weblink') return;
		$db = &JFactory::getDBO();
		$post = is_array($post)?$post:array($post);
		$query = "DELETE FROM #__flexicontent_advsearch_index WHERE field_id='{$field->id}' AND item_id='{$field->item_id}' AND extratable='weblink';";
		$db->setQuery($query);
		$db->query();
		$i = 0;
		foreach($post as $v) {
			$query = "INSERT INTO #__flexicontent_advsearch_index VALUES('{$field->id}','{$field->item_id}','weblink','{$i}', ".$db->Quote($v['link'].":".$v['title']).");";
			$db->setQuery($query);
			$db->query();
			$i++;
		}
		return true;
	}

	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'weblink') return;
		
		$field->label = JText::_($field->label);
		
		$values = $values ? $values : $field->value;
		if ( !$values ) {	$field->{$prop} = '';	return;	}
		
		// some parameter shortcuts
		$pretext			= $field->parameters->get( 'pretext', '' ) ;
		$posttext			= $field->parameters->get( 'posttext', '' ) ;
		$separatorf		= $field->parameters->get( 'separatorf', 1 ) ;
		$opentag			= $field->parameters->get( 'opentag', '' ) ;
		$closetag			= $field->parameters->get( 'closetag', '' ) ;
		$target = $field->parameters->get( 'targetblank', 0 ) ? ' target="_blank"' : '';
		
		
		$usetitle      = $field->parameters->get( 'use_title', 0 ) ;
		$title_usage   = $field->parameters->get( 'title_usage', 0 ) ;
		$default_title = ($title_usage == 2)  ?  JText::_($field->parameters->get( 'default_value_title', '' )) : '';
		
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
		
		// initialise property
		$field->{$prop} = array();
		$n = 0;
		foreach ($values as $value)
		{
			if ( empty($value) ) continue;
			
			// Compatibility for old unserialized values
			$value = (@unserialize($value)!== false || $value === 'b:0;') ? unserialize($value) : $value;
			if ( is_array($value) ) {
				$link = $value['link'];
				$title = $value['title'];
				$hits = $value['hits'];
			} else {
				$link = $value;
				$title = '';
				$hits = 0;
			}
			
			// If not using property or property is empty, then use default property value
			// NOTE: default property values have been cleared, if (propertyname_usage != 2)
			$title = ($usetitle && strlen($title))  ?  $title  :  $default_title;
			
			// Indirect access to the web-link, via calling FLEXIcontent component
			$href = JRoute::_( 'index.php?option=com_flexicontent&fid='. $field->id .'&cid='.$field->item_id.'&ord='.($n+1).'&task=weblink' );
			
			// Create indirect link to web-link address with custom displayed text
			if ( strlen($title) && $usetitle ) {
				$field->{$prop}[] = $pretext. '<a href="' .$href. '" title="' . $title . '"' . $target . '>' .$title. '</a>' .$posttext;
			} else {
				$field->{$prop}[] = $pretext. '<a href="' .$href. '" title="' . $title . '"' . $target . '>'. $this->cleanurl($link) .'</a>' .$posttext;
			}
			
			$n++;
		}
		
		// Apply seperator and open/close tags
		if(count($field->{$prop})) {
			$field->{$prop}  = implode($separatorf, $field->{$prop});
			$field->{$prop}  = $opentag . $field->{$prop} . $closetag;
		} else {
			$field->{$prop} = '';
		}
	}
	
	function onFLEXIAdvSearch(&$field, $fieldsearch)
	{
		if($field->field_type!='weblink') return;
		$db = &JFactory::getDBO();
		$resultfields = array();
		foreach($fieldsearch as $fsearch) {
			$query = "SELECT ai.search_index, ai.item_id FROM #__flexicontent_advsearch_index as ai"
				." WHERE ai.field_id='{$field->id}' AND ai.extratable='weblink' AND ai.search_index like '%{$fsearch}%';";
			$db->setQuery($query);
			$objs = $db->loadObjectList();
			if ($objs===false) continue;
			$objs = is_array($objs)?$objs:array($objs);
			foreach($objs as $o) {
				$obj = new stdClass;
				$obj->item_id = $o->item_id;
				$obj->label = $field->label;
				$obj->value = $fsearch;
				$resultfields[] = $obj;
			}
		}
		$field->results = $resultfields;
	}
	
	function cleanurl($url)
	{
		$prefix = array("http://", "https://", "ftp://");
		$cleanurl = str_replace($prefix, "", $url);
		return $cleanurl;
	}
}
