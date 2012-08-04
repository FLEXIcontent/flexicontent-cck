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
	
	function onAdvSearchDisplayField(&$field, &$item)
	{
		if($field->field_type != 'extendedweblink') return;
		plgFlexicontent_fieldsExtendedWeblink::onDisplayField($field, $item);
	}
	
	// This function is called just before the item is deleted to remove custom item data related to the field
	function onBeforeDeleteField(&$field, &$item)
	{
	}
	
	// This function is called to display the field in item edit/submit form
	function onDisplayField(&$field, &$item)
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'extendedweblink') return;
		
		$field->label = JText::_($field->label);
		
		// some parameter shortcuts
		$size					= $field->parameters->get( 'size', 30 ) ;
		$multiple			= $field->parameters->get( 'allow_multiple', 1 ) ;
		$maxval				= $field->parameters->get( 'max_values', 0 ) ;
		
		$default_link_usage = $field->parameters->get( 'default_link_usage', 0 ) ;
		$default_link       = ($item->version == 0 || $default_link_usage > 0) ? $field->parameters->get( 'default_link', '' ) : '';
		
		$title_usage = $field->parameters->get( 'title_usage', 0 ) ;
		$text_usage  = $field->parameters->get( 'text_usage', 0 ) ;
		$class_usage = $field->parameters->get( 'class_usage', 0 ) ;
		$id_usage    = $field->parameters->get( 'id_usage', 0 ) ;
		
		$default_title  = ($item->version == 0 || $title_usage > 0) ? JText::_($field->parameters->get( 'default_title', '' )) : '';
		$default_text   = ($item->version == 0 || $text_usage > 0) ? $field->parameters->get( 'default_text', '' ) : '';
		$default_class  = ($item->version == 0 || $class_usage > 0) ? $field->parameters->get( 'default_class', '' ) : '';
		$default_id     = ($item->version == 0 || $id_usage > 0) ? $field->parameters->get( 'default_id', '' ) : '';
		
		$usetitle  = $field->parameters->get( 'use_title', 0 ) ;
		$usetext   = $field->parameters->get( 'use_text', 0 ) ;
		$useclass  = $field->parameters->get( 'use_class', 0 ) ;
		$useid     = $field->parameters->get( 'use_id', 0 ) ;
		
		$required		= $field->parameters->get( 'required', 0 ) ;
		$required		= $required ? ' required' : '';
		
		// Initialise property with default value
		if ( !$field->value ) {
			$field->value = array();
			$field->value[0]['link'] = JText::_($default_link);
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
					
					thisNewField.getElements('input.urllink').setProperty('value','".$default_link."');
					thisNewField.getElements('input.urllink').setProperty('name','".$field->name."['+uniqueRowNum".$field->id."+'][link]');
					";
					
			if ($usetitle) $js .= "
					thisNewField.getElements('input.urltitle').setProperty('value','".$default_title."');
					thisNewField.getElements('input.urltitle').setProperty('name','".$field->name."['+uniqueRowNum".$field->id."+'][title]');
					";
					
			if ($usetext) $js .= "
					thisNewField.getElements('input.urllinktext').setProperty('value','".$default_text."');
					thisNewField.getElements('input.urllinktext').setProperty('name','".$field->name."['+uniqueRowNum".$field->id."+'][linktext]');
					";
					
			if ($useclass) $js .= "
					thisNewField.getElements('input.urlclass').setProperty('value','".$default_class."');
					thisNewField.getElements('input.urlclass').setProperty('name','".$field->name."['+uniqueRowNum".$field->id."+'][class]');
					";
					
			if ($useid) $js .= "
					thisNewField.getElements('input.urlid').setProperty('value','".$default_id."');
					thisNewField.getElements('input.urlid').setProperty('name','".$field->name."['+uniqueRowNum".$field->id."+'][id]');
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
				position: relative;
			}
			#sortables_'.$field->id.' li input { cursor: text;}
			#add'.$field->name.' { margin-top: 5px; clear: both; display:block; }
			#sortables_'.$field->id.' li .admintable { text-align: right; }
			#sortables_'.$field->id.' li:only-child span.drag, #sortables_'.$field->id.' li:only-child input.fcfield-button { display:none; }
			';
			
			$move2 	= JHTML::image ( JURI::root().'administrator/components/com_flexicontent/assets/images/move3.png', JText::_( 'FLEXI_CLICK_TO_DRAG' ) );
			$remove_button = '<input class="fcfield-button" type="button" value="'.JText::_( 'FLEXI_REMOVE_VALUE' ).'" onclick="deleteField'.$field->id.'(this);" /><span class="fcfield-drag">'.$move2.'</span>';
		} else {
			$remove_button = '';
			$css = '';
		}
		
		$document->addStyleDeclaration($css);
		
		$field->html = array();
		$n = 0;
		foreach ($field->value as $value) {
			if ( empty($value) ) continue;
			$value  = unserialize($value);
			
			$fieldname = FLEXI_J16GE ? 'custom['.$field->name.']['.$n.']' : $field->name.'['.$n.']';
			
			$link = '<tr><td class="key">'.JText::_( 'FLEXI_FIELD_URL' ).':</td><td><input class="urllink '.$required.'" name="'.$fieldname.'[link]" type="text" size="'.$size.'" value="'.$value['link'].'" /></td></tr>';
			
			if ($usetitle) $title =
				'<tr><td class="key">'.JText::_( 'FLEXI_EXTWL_URLTITLE' ).    ':</td><td><input class="urltitle" name="'.$fieldname.'[title]" type="text" size="'.$size.'" value="'.(@$value['title'] ? $value['title'] : $default_title).'" /></td></tr>';

			if ($usetext) $linktext =
				'<tr><td class="key">'.JText::_( 'FLEXI_EXTWL_URLLINK_TEXT' ).':</td><td><input class="urllinktext" name="'.$fieldname.'[linktext]" type="text" size="'.$size.'" value="'.(@$value['linktext'] ? $value['linktext'] : $default_text).'" /></td></tr>';
			
			if ($useclass) $class =
				'<tr><td class="key">'.JText::_( 'FLEXI_EXTWL_URLCLASS' ).    ':</td><td><input class="urlclass" name="'.$fieldname.'[class]" type="text" size="'.$size.'" value="'.(@$value['class'] ? $value['class'] : $default_class).'" /></td></tr>';
			
			if ($useid) $id =
				'<tr><td class="key">'.JText::_( 'FLEXI_EXTWL_URLID' ).       ':</td><td><input class="urlid" name="'.$fieldname.'[id]" type="text" size="'.$size.'" value="'.(@$value['id'] ? $value['id'] : $default_id).'" /></td></tr>';
			
			$hits = @$value['hits'] ? $value['hits'] : 0;
			
			$field->html[] = '
				<table class="admintable"><tbody>
					'.$link.'
					'.@$title.'
					'.@$linktext.'
					'.@$class.'
					'.@$id.'
				</tbody></table>
				'.$remove_button.'
				<input class="urlhits" name="'.$fieldname.'[hits]" type="hidden" value="'.$hits.'" />
				<span class="hits"><span class="hitcount">'.$hits.'</span> '.JText::_( 'FLEXI_FIELD_HITS' ).'</span>
				';
			$n++;
			if (!$multiple) break;  // multiple values disabled, break out of the loop, not adding further values even if the exist
		}
		
		if ($multiple) { // handle multiple records
			$field->html = '<li>'. implode('</li><li>', $field->html) .'</li>';
			$field->html = '<ul class="fcfield-sortables" id="sortables_'.$field->id.'">' .$field->html. '</ul>';
			$field->html .= '<input type="button" class="fcfield-addvalue" onclick="addField'.$field->id.'(this);" value="'.JText::_( 'FLEXI_ADD_WEBLINK' ).'" />';
		} else {  // handle single values
			$field->html = $field->html[0];
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
		
		// make sure posted data is an array 
		$post = !is_array($post) ? array($post) : $post;
		
		foreach ($post as $n=>$v)
		{
			if ($post[$n]['link'] != '')
			{
				$http_prefix = (!preg_match("#^http|^https|^ftp#i", $post[$n]['link'])) ? 'http://' : '';
				$newpost[$new]['link']		= $http_prefix.$post[$n]['link'];
				$newpost[$new]['title']		= strip_tags(@$post[$n]['title']);
				$newpost[$new]['id']			= strip_tags(@$post[$n]['id']);
				$newpost[$new]['class']		= strip_tags(@$post[$n]['class']);
				$newpost[$new]['linktext']= strip_tags(@$post[$n]['linktext']);
				$newpost[$new]['hits']		= (int) $post[$n]['hits'];
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
			plgFlexicontent_fieldsExtendedweblink::onIndexAdvSearch($field, $post);
		}
		
		// Serialize multiproperty data before storing into the DB
		foreach($post as $i => $v) {
			$post[$i] = serialize($v);
		}
	}
	
	function onIndexAdvSearch(&$field, $post) {
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'extendedweblink') return;
		$db = &JFactory::getDBO();
		$post = is_array($post)?$post:array($post);
		$query = "DELETE FROM #__flexicontent_advsearch_index WHERE field_id='{$field->id}' AND item_id='{$field->item_id}' AND extratable='extendedweblink';";
		$db->setQuery($query);
		$db->query();
		$i = 0;
		foreach($post as $v) {
			$query = "INSERT INTO #__flexicontent_advsearch_index VALUES('{$field->id}','{$field->item_id}','extendedweblink','{$i}', ".$db->Quote($v['link'].":".$v['title']).");";
			$db->setQuery($query);
			$db->query();
			$i++;
		}
		return true;
	}

	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'extendedweblink') return;
		
		$field->label = JText::_($field->label);
		
		$values = $values ? $values : $field->value;
		if ( !$values ) {	$field->{$prop} = '';	return;	}
		
		// some parameter shortcuts
		$pretext			= $field->parameters->get( 'pretext', '' ) ;
		$posttext			= $field->parameters->get( 'posttext', '' ) ;
		$separatorf		= $field->parameters->get( 'separatorf', 1 ) ;
		$opentag			= $field->parameters->get( 'opentag', '' ) ;
		$closetag			= $field->parameters->get( 'closetag', '' ) ;
		
		$usetitle      = $field->parameters->get( 'use_title', 0 ) ;
		$title_usage   = $field->parameters->get( 'title_usage', 0 ) ;
		$default_title = ($title_usage == 2)  ?  JText::_($field->parameters->get( 'default_title', '' )) : '';
		
		$usetext      = $field->parameters->get( 'use_text', 0 ) ;
		$text_usage   = $field->parameters->get( 'text_usage', 0 ) ;
		$default_text = ($text_usage == 2)  ?  $field->parameters->get( 'default_text', '' ) : '';
		
		$useclass      = $field->parameters->get( 'use_class', 0 ) ;
		$class_usage   = $field->parameters->get( 'class_usage', 0 ) ;
		$default_class = ($class_usage == 2)  ?  $field->parameters->get( 'default_class', '' ) : '';
		
		$useid      = $field->parameters->get( 'use_id', 0 ) ;
		$id_usage	  = $field->parameters->get( 'id_usage', 0 ) ;
		$default_id = ($id_usage == 2)  ?  $field->parameters->get( 'default_id', '' ) : '';
		
		$target         = $field->parameters->get( 'target', '' );
		$target_param   = $target ? ' target="'.$target.'"' : '';
		
		
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
		
		$field->{$prop} = array();
		$n = 0;
		foreach ($values as $value)
		{
			if ( empty($value) ) continue;
			$value  = unserialize($value);
			
			// If not using property or property is empty, then use default property value
			// NOTE: default property values have been cleared, if (propertyname_usage != 2)
			$title    = ($usetitle && @$value['title']   )  ?  $value['title']    : $default_title;
			$linktext = ($usetext  && @$value['linktext'])  ?  $value['linktext'] : $default_text;
			$class    = ($useclass && @$value['class']   )  ?  $value['class']    : $default_class;
			$id       = ($useid    && @$value['id']      )  ?  $value['id']       : $default_id;
			
			$link_params  = $title ? ' title="'.$title.'"' : '';
			$link_params .= $class ? ' class="'.$class.'"' : '';
			$link_params .= $id    ? ' id="'   .$id.'"'    : '';
			$link_params .= $target_param;
			
			// Set a displayed text for the link if one was not given and default value has not been set
			if( !$linktext )
				$linktext = $title ? $title: $this->cleanurl($value['link']);
			
			// Indirect access to the web-link, via calling FLEXIcontent component
			$href = JRoute::_( 'index.php?option=com_flexicontent&fid='. $field->id .'&cid='.$field->item_id.'&ord='.($n+1).'&task=weblink' );
			
			// Create indirect link to web-link address with custom displayed text
			$field->{$prop}[] = $pretext. '<a href="'.$href.'" '.$link_params.'>'. $linktext .'</a>' .$posttext;
			
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

	function cleanurl($url)
	{
		$prefix = array("http://", "https://", "ftp://");
		$cleanurl = str_replace($prefix, "", $url);
		return $cleanurl;
	}
}
