<?php
/**
 * @version 1.0 $Id$
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.email
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

class plgFlexicontent_fieldsEmail extends JPlugin
{
	function plgFlexicontent_fieldsEmail( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_email', JPATH_ADMINISTRATOR);
	}
	
	function onAdvSearchDisplayField(&$field, &$item)
	{
		if($field->field_type != 'email') return;
		plgFlexicontent_fieldsEmail::onDisplayField($field, $item);
	}
	
	// This function is called just before the item is deleted to remove custom item data related to the field
	function onBeforeDeleteField(&$field, &$item)
	{
	}
	
	// This function is called to display the field in item edit/submit form
	function onDisplayField(&$field, &$item)
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'email') return;
		
		$field->label = JText::_($field->label);
		
		// some parameter shortcuts
		$size      = $field->parameters->get( 'size', 30 ) ;
		$multiple  = $field->parameters->get( 'allow_multiple', 1 ) ;
		$maxval    = $field->parameters->get( 'max_values', 0 ) ;
		
		$default_value    = ($item->version == 0) ? $field->parameters->get( 'default_value', '' ) : '';
		
		$usetitle      = $field->parameters->get( 'use_title', 0 ) ;
		$title_usage   = $field->parameters->get( 'title_usage', 0 ) ;
		$default_title = ($item->version == 0 || $title_usage > 0)  ?  JText::_($field->parameters->get( 'default_value_title', '' )) : '';
		
		$required		= $field->parameters->get( 'required', 0 ) ;
		$required		= $required ? ' required' : '';
		
		// initialise property
		if (!$field->value) {
			$field->value = array();
			$field->value[0]['addr'] = JText::_($default_value);
			$field->value[0]['text'] = JText::_($default_title);
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
					
					thisNewField.getElements('input.emailaddr').setProperty('value','');
					thisNewField.getElements('input.emailaddr').setProperty('name','".$field->name."['+uniqueRowNum".$field->id."+'][addr]');
					";
					
			if ($usetitle) $js .= "
					thisNewField.getElements('input.emailtext').setProperty('value','');
					thisNewField.getElements('input.emailtext').setProperty('name','".$field->name."['+uniqueRowNum".$field->id."+'][text]');
					";
					
			$js .= "
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
			#sortables_'.$field->id.' label.legende, #sortables_'.$field->id.' input.emailaddr, #sortables_'.$field->id.' input.emailtext, #sortables_'.$field->id.' input.fcfield-button {
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
				$value = array('addr' => $value, 'text' => '');
			}
			$fieldname = FLEXI_J16GE ? 'custom['.$field->name.']['.$n.']' : $field->name.'['.$n.']';
			
			$addr = '
				<label class="legende" for="'.$fieldname.'[addr]">'.JText::_( 'FLEXI_FIELD_EMAILADDRESS' ).':</label>
				<input class="emailaddr validate-email'.$required.'" name="'.$fieldname.'[addr]" type="text" size="'.$size.'" value="'.$value['addr'].'" />
			';
			
			if ($usetitle) $text = '
				<label class="legende" for="'.$fieldname.'[text]">'.JText::_( 'FLEXI_FIELD_EMAILTITLE' ).':</label>
				<input class="emailtext" name="'.$fieldname.'[text]" type="text" size="'.$size.'" value="'.@$value['text'].'" />
			';
			
			$field->html[] = '
				'.$addr.'
				'.@$text.'
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
		if($field->field_type != 'email') return;
		if(!$post) return;
		
		// reformat the post
		$newpost = array();
		$new = 0;
		
		// make sure posted data is an array 
		$post = !is_array($post) ? array($post) : $post;
		
		foreach ($post as $n=>$v)
		{
			if ($post[$n]['addr'] != '')
			{
				$newpost[$new] = $post[$n];
				$newpost[$new]['addr'] = $post[$n]['addr'];
				$newpost[$new]['text'] = strip_tags(@$post[$n]['text']);
				$new++;
			}
		}
		$post = $newpost;
		
		// create the fulltext search index
		if ($field->issearch) {
			$searchindex = '';
			
			foreach($post as $i => $v)
			{
				$searchindex .= $v['addr'];
				$searchindex .= ' ';
				$searchindex .= $v['text'];
				$searchindex .= ' ';
			}
			$searchindex .= ' | ';
			$field->search = $searchindex;
		} else {
			$field->search = '';
		}
		
		if($field->isadvsearch && JRequest::getVar('vstate', 0)==2) {
			plgFlexicontent_fieldsEmail::onIndexAdvSearch($field, $post);
		}
		
		// Serialize multiproperty data before storing into the DB
		foreach($post as $i => $v) {
			$post[$i] = serialize($v);
		}
	}
	
	function onIndexAdvSearch(&$field, $post) {
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'email') return;
		$db = &JFactory::getDBO();
		$post = is_array($post)?$post:array($post);
		$query = "DELETE FROM #__flexicontent_advsearch_index WHERE field_id='{$field->id}' AND item_id='{$field->item_id}' AND extratable='email';";
		$db->setQuery($query);
		$db->query();
		$i = 0;
		foreach($post as $v) {
			$query = "INSERT INTO #__flexicontent_advsearch_index VALUES('{$field->id}','{$field->item_id}','email','{$i}', ".$db->Quote($v).");";
			$db->setQuery($query);
			$db->query();
			$i++;
		}
		return true;
	}

	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'email') return;
		
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
				$addr = $value['addr'];
				$text = $value['text'];
			} else {
				$addr = $value;
				$text = '';
			}
			
			// If not using property or property is empty, then use default property value
			// NOTE: default property values have been cleared, if (propertyname_usage != 2)
			$text = ($usetitle && strlen($text))  ?  $text  :  $default_title;
			
			// Create cloacked email address with custom displayed text
			if ( strlen($text) && $usetitle ) {
				$field->{$prop}[]	= $pretext. JHTML::_('email.cloak', $addr, 1, $text, 0) .$posttext;
			} else {
				$field->{$prop}[]	= $pretext. JHTML::_('email.cloak', $addr) .$posttext;
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
		if($field->field_type!='email') return;
		$db = &JFactory::getDBO();
		$resultfields = array();
		foreach($fieldsearch as $fsearch) {
			$query = "SELECT ai.search_index, ai.item_id FROM #__flexicontent_advsearch_index as ai"
				." WHERE ai.field_id='{$field->id}' AND ai.extratable='email' AND ai.search_index like '%{$fsearch}%';";
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
	
}
