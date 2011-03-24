<?php
/**
 * @version 1.0 $Id: toolbar.php 40 2010-02-09 00:08:23Z emmanuel.danan $
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

class plgFlexicontent_fieldsToolbar extends JPlugin
{
	function plgFlexicontent_fieldsToolbar( &$subject, $params )
	{
		parent::__construct( $subject, $params );
        JPlugin::loadLanguage('plg_flexicontent_fields_toolbar', JPATH_ADMINISTRATOR);
	}

	function onDisplayField(&$field, $item)
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'toolbar') return;
	}


	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'toolbar') return;
		if(JRequest::getCmd('print')) return;

		global $mainframe, $addthis;
		$view		= JRequest::getString('view', 'items');
		if ($view != 'items') return;
		$document	= & JFactory::getDocument();
		$lang       = $document->getLanguage();

		$lang   	= substr($lang, 0, 2);
		
		$lang		= in_array($lang, array('en','es','it','th')) ? $lang : 'en';
		
		// parameters shortcuts
		$display_comments	= $field->parameters->get('display-comments', 1);
		$display_resizer	= $field->parameters->get('display-resizer', 1);
		$display_print 		= $field->parameters->get('display-print', 1);
		$display_email 		= $field->parameters->get('display-email', 1);
		$display_voice 		= $field->parameters->get('display-voice', 1);
		$display_pdf 		= $field->parameters->get('display-pdf', 1);
		$display_social 	= $field->parameters->get('display-social', 1);
		$load_css 			= $field->parameters->get('load-css', 1);
		$addthis_user		= $field->parameters->get('addthis-user', '');
		$spacer_size		= $field->parameters->get('spacer-size', 21);
		$module_position	= $field->parameters->get('module_position', '');
		$default_size 		= $field->parameters->get('default-size', 12);
		$default_line 		= $field->parameters->get('default-line', 16);
		$target 			= $field->parameters->get('target', 'flexicontent');
		$voicetarget 		= $field->parameters->get('voicetarget', 'flexicontent');

		$spacer				= ' style="width:'.$spacer_size.'px;"';
		// define a global variable to be sure the script is loaded only once
		$addthis		= isset($addthis) ? $addthis : 0;
		
		if ($load_css) $document->addStyleSheet('plugins/flexicontent_fields/toolbar/toolbar.css');
		
		$display	 = '<div class="flexitoolbar">'; // begin of the toolbar container

		// comments button
		if ($display_comments)
		{
			$display	.= '
			<div class="flexi-react toolbar-element">
				<span class="comments-bubble">'.($module_position ? '<!-- jot '.$module_position.' s -->' : '').$this->_getCommentsCount($item->id).($module_position ? '<!-- jot '.$module_position.' e -->' : '').'</span>
				<span class="comments-legend flexi-legend"><a href="#addcomments" title="'.JText::_('FLEXI_FIELD_TOOLBAR_COMMENT').'">'.JText::_('FLEXI_FIELD_TOOLBAR_COMMENT').'</a></span>
			</div>
			<div class="toolbar-spacer"'.$spacer.'></div>
			';
		}

		// text resizer
		if ($display_resizer)
		{
			$document->addScriptDeclaration('var textsize = '.$default_size.';
			var lineheight = '.$default_line.';
			function fsize(size,line,unit,id){
				var vfontsize = document.getElementById(id);
				if(vfontsize){
					vfontsize.style.fontSize = size + unit;
					vfontsize.style.lineHeight = line + unit;
				}
			}
			function changetextsize(up){
				if(up){
					textsize 	= parseFloat(textsize)+2;
					lineheight 	= parseFloat(lineheight)+2;
				}else{
					textsize 	= parseFloat(textsize)-2;
					lineheight 	= parseFloat(lineheight)-2;
				}
			}');
			$display	 .= '
			<div class="flexi-resizer toolbar-element">
				<a class="decrease" href="javascript:fsize(textsize,lineheight,\'px\',\''.$target.'\');" onclick="changetextsize(0);">'.JText::_("FLEXI_FIELD_TOOLBAR_DECREASE").'</a>
				<a class="increase" href="javascript:fsize(textsize,lineheight,\'px\',\''.$target.'\');" onclick="changetextsize(1);">'.JText::_("FLEXI_FIELD_TOOLBAR_INCREASE").'</a>
				<span class="flexi-legend">'.JText::_("FLEXI_FIELD_TOOLBAR_SIZE").'</span>
			</div>
			<div class="toolbar-spacer"'.$spacer.'></div>
			';
		}
		
		// email button
		if ($display_email)
		{
			$link		 = JURI::base().JRoute::_( 'index.php?view=items&cid='.$item->categoryslug.'&id='.$item->slug, false );
			$url		 = 'index.php?option=com_mailto&tmpl=component&link='.base64_encode( $link );
			$estatus	 = 'width=400,height=400,menubar=yes,resizable=yes';
			$display	.= '
			<div class="flexi-email toolbar-element">
				<span class="email-legend flexi-legend"><a href="'. JRoute::_($url) .'" class="editlinktip" onclick="window.open(this.href,\'win2\',\''.$estatus.'\'); return false;" title="'.JText::_('FLEXI_FIELD_TOOLBAR_SEND').'">'.JText::_('FLEXI_FIELD_TOOLBAR_SEND').'</a></span>
			</div>
			<div class="toolbar-spacer"'.$spacer.'></div>
			';
		}
		
		// print button
		if ($display_print)
		{
			$pop		 = JRequest::getInt('pop');
			$pstatus 	 = 'status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,width=640,height=480,directories=no,location=no';
	        $print_link  = $pop ? '#' : JRoute::_('index.php?view=items&cid='.$item->categoryslug.'&id='.$item->slug.'&pop=1&print=1&tmpl=component');
	        $js_link  	 = $pop ? 'onclick="window.print();return false;"' : 'onclick="window.open(this.href,\'win2\',\''.$pstatus.'\'); return false;"';
			$display	.= '
			<div class="flexi-print toolbar-element">
				<span class="print-legend flexi-legend"><a href="'. JRoute::_($print_link) .'" '.$js_link.' class="editlinktip"  title="'.JText::_('FLEXI_FIELD_TOOLBAR_PRINT').'">'.JText::_('FLEXI_FIELD_TOOLBAR_PRINT').'</a></span>
			</div>
			<div class="toolbar-spacer"'.$spacer.'></div>
			';
		}

		// pdf button
		if ($display_voice)
		{
			$display .= "
			<div class=\"flexi-voice toolbar-element\">";
			if($lang=='th') {//may be la=laos,and Bhutan languages in the future(NECTEC support these languges).
			$document->addScript(JURI::root().'plugins/flexicontent_fields/toolbar/th.js');
			$display .="
				<span class=\"voice-legend flexi-legend\"><a href=\"javascript:void(0);\" onclick=\"openwindow('".$voicetarget."','".$lang."');\" class=\"mainlevel-toolbar-article-horizontal\" rel=\"nofollow\">" . JTEXT::_('FLEXI_FIELD_TOOLBAR_VOICE') . "</a></span>
			";
			}else{
			$document->addScript('http://vozme.com/get_text.js');
			$display .="
				<span class=\"voice-legend flexi-legend\"><a href=\"javascript:void(0);\" onclick=\"get_id('".$voicetarget."','".$lang."','fm');\" class=\"mainlevel-toolbar-article-horizontal\" rel=\"nofollow\">" . JTEXT::_('FLEXI_FIELD_TOOLBAR_VOICE') . "</a></span>
			";
			}
			$display .="
			</div>
			<div class=\"toolbar-spacer\"".$spacer."></div>
			";
		}

		// pdf button
		if ($display_pdf)
		{
			$pdflink 	= 'index.php?view=items&cid='.$item->categoryslug.'&id='.$item->slug.'&format=pdf';
			$display	.= '
			<div class="flexi-pdf toolbar-element">
				<span class="pdf-legend flexi-legend"><a href="'.JRoute::_($pdflink).'" class="editlinktip" title="'.JText::_('FLEXI_FIELD_TOOLBAR_PDF').'">'.JText::_('FLEXI_FIELD_TOOLBAR_PDF').'</a></span>
			</div>
			<div class="toolbar-spacer"'.$spacer.'></div>
			';
		}

		// AddThis button
		if ($display_social)
		{
			$display 	.= '
			<div class="flexi-socials toolbar-element">
				<div class="addthis_toolbox addthis_default_style">
				<a href="http://www.addthis.com/bookmark.php?v=250&username='.$addthis_user.'" class="addthis_button_compact">'.JText::_('FLEXI_FIELD_TOOLBAR_SHARE').'</a>
				<span class="addthis_separator">|</span>
				<a class="addthis_button_facebook" title="'.JText::_('FLEXI_FIELD_TOOLBAR_FACEBOOK').'"></a>
				<a class="addthis_button_myspace" title="'.JText::_('FLEXI_FIELD_TOOLBAR_GOOGLE').'"></a>
				<a class="addthis_button_google" title="'.JText::_('FLEXI_FIELD_TOOLBAR_MYSPACE').'"></a>
				<a class="addthis_button_twitter" title="'.JText::_('FLEXI_FIELD_TOOLBAR_TWITTER').'"></a>
			</div>
			';
			if (!$addthis) {
				$document->addCustomTag('	
					<script type="text/javascript" src="http://s7.addthis.com/js/250/addthis_widget.js#username='.$addthis_user.'"></script>
					<script type="text/javascript">
					var addthis_config = {
					     services_exclude: "print,email"
					}
					</script>
				');
				$addthis = 1;
			}
			$display	.= '</div>';
		}
		
		$display	.= '</div>'; // end of the toolbar container

		$field->{$prop} = $display;
	}

	function onBeforeSaveField(&$field, &$post, $file)
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'toolbar') return;
		
		return;
	}
	
	function _getCommentsCount($id)
	{
		$db =& JFactory::getDBO();
		
		$query 	= 'SELECT COUNT(com.object_id)'
				. ' FROM #__jcomments AS com'
				. ' WHERE com.object_id = ' . (int)$id
				. ' AND com.object_group = ' . $db->Quote('com_flexicontent')
				. ' AND com.published = 1'
				;
		$db->setQuery($query);
				
		return $db->loadResult() ? (int)$db->loadResult() : 0;
	}

}
