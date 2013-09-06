<?php
/**
 * @version 1.0 $Id: toolbar.php 1681 2013-05-04 23:51:21Z ggppdk $
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
	static $field_types = array('toolbar');
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function plgFlexicontent_fieldsToolbar( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_toolbar', JPATH_ADMINISTRATOR);
	}
	
	
	
	// *******************************************
	// DISPLAY methods, item form & frontend views
	// *******************************************
	
	// Method to create field's HTML display for item form
	function onDisplayField(&$field, $item)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
	}
	
	
	// Method to create field's HTML display for frontend views
	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
		if(JRequest::getCmd('print')) return;

		global $mainframe, $addthis;
		$view		= JRequest::getString('view', FLEXI_ITEMVIEW);
		if ($view != FLEXI_ITEMVIEW) return;
		$document	= & JFactory::getDocument();
		$lang       = $document->getLanguage();
		if(FLEXI_FISH) {
			$lang = @$item->lang?$item->lang:$lang;
		}else{
			$lang = $item->params->get('language', $lang);
		}
		$lang = $lang?$lang:'en-GB';
		$lang   	= substr($lang, 0, 2);
		$lang		= in_array($lang, array('en','es','it','th')) ? $lang : 'en';
		
		// parameters shortcuts
		$display_comments	= $field->parameters->get(FLEXI_J16GE ? 'display_comments' : 'display-comments', 1) && $item->parameters->get('comments',0);
		$display_resizer	= $field->parameters->get(FLEXI_J16GE ? 'display_resizer' : 'display-resizer', 1);
		$display_print 		= $field->parameters->get(FLEXI_J16GE ? 'display_print' : 'display-print', 1);
		$display_email 		= $field->parameters->get(FLEXI_J16GE ? 'display_email' : 'display-email', 1);
		$display_voice 		= $field->parameters->get(FLEXI_J16GE ? 'display_voice' : 'display-voice', 1);
		//$display_pdf 		= $field->parameters->get(FLEXI_J16GE ? 'display_pdf' : 'display-pdf', 1);
		$display_pdf 		= FLEXI_J16GE ? 0 : $field->parameters->get('display-pdf', 1);
		$load_css 			= $field->parameters->get(FLEXI_J16GE ? 'load_css' : 'load-css', 1);
		
		$display_social 	= $field->parameters->get(FLEXI_J16GE ? 'display_social' : 'display-social', 1);
		$addthis_user		= $field->parameters->get(FLEXI_J16GE ? 'addthis_user' : 'addthis-user', '');
		$addthis_pubid	= $field->parameters->get('addthis_pubid', $addthis_user);
		
		$spacer_size		= $field->parameters->get(FLEXI_J16GE ? 'spacer_size' : 'spacer-size', 21);
		$module_position	= $field->parameters->get('module_position', '');
		$default_size 		= $field->parameters->get(FLEXI_J16GE ? 'default_size' : 'default-size', 12);
		$default_line 		= $field->parameters->get(FLEXI_J16GE ? 'default_line' : 'default-line', 16);
		$target 			= $field->parameters->get('target', 'flexicontent');
		$voicetarget 		= $field->parameters->get('voicetarget', 'flexicontent');

		$spacer				= ' style="width:'.$spacer_size.'px;"';
		// define a global variable to be sure the script is loaded only once
		$addthis		= isset($addthis) ? $addthis : 0;
		
		if ($load_css) {
			$document->addStyleSheet(JURI::root().'plugins/flexicontent_fields/toolbar'.(FLEXI_J16GE ? '/toolbar' : '').'/toolbar.css');
		}
		
		$display	 = '<div class="flexitoolbar">'; // begin of the toolbar container

		// comments button
		if ($display_comments)
		{
			$link = FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug) . '#addcomments';
			$comment_link  = JRoute::_($link);

			$display	.= '
			<div class="flexi-react toolbar-element">
				<span class="comments-bubble">'.($module_position ? '<!-- jot '.$module_position.' s -->' : '').$this->_getCommentsCount($item->id).($module_position ? '<!-- jot '.$module_position.' e -->' : '').'</span>
				<span class="comments-legend flexi-legend"><a href="'.$comment_link.'" title="'.JText::_('FLEXI_FIELD_TOOLBAR_COMMENT').'">'.JText::_('FLEXI_FIELD_TOOLBAR_COMMENT').'</a></span>
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
			$link = JURI::root().JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug));
			//$link = JURI::root().JRoute::_( 'index.php?view='.FLEXI_ITEMVIEW.'&cid='.$item->categoryslug.'&id='.$item->slug, false );
			require_once(JPATH_SITE.DS.'components'.DS.'com_mailto'.DS.'helpers'.DS.'mailto.php');
			$url		 = 'index.php?option=com_mailto&tmpl=component&link='.MailToHelper::addLink( $link );
			$estatus	 = 'width=400,height=400,menubar=yes,resizable=yes';
			$display	.= '
			<div class="flexi-email toolbar-element">
				<span class="email-legend flexi-legend"><a rel="nofollow" href="'. JRoute::_($url) .'" class="editlinktip" onclick="window.open(this.href,\'win2\',\''.$estatus.'\'); return false;" title="'.JText::_('FLEXI_FIELD_TOOLBAR_SEND').'">'.JText::_('FLEXI_FIELD_TOOLBAR_SEND').'</a></span>
			</div>
			<div class="toolbar-spacer"'.$spacer.'></div>
			';
		}
		
		// print button
		if ($display_print)
		{
			$pop		 = JRequest::getInt('pop');
			$pstatus 	 = 'status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,width=640,height=480,directories=no,location=no';
			$link = FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug) . '&pop=1&print=1&tmpl=component';
			$print_link  = $pop ? '#' : JRoute::_($link);
			$js_link  	 = $pop ? 'onclick="window.print();return false;"' : 'onclick="window.open(this.href,\'win2\',\''.$pstatus.'\'); return false;"';
			$display	.= '
			<div class="flexi-print toolbar-element">
				<span class="print-legend flexi-legend"><a rel="nofollow" href="'. $print_link .'" '.$js_link.' class="editlinktip"  title="'.JText::_('FLEXI_FIELD_TOOLBAR_PRINT').'">'.JText::_('FLEXI_FIELD_TOOLBAR_PRINT').'</a></span>
			</div>
			<div class="toolbar-spacer"'.$spacer.'></div>
			';
		}

		// pdf button
		if ($display_voice)
		{
			$display .= "
			<div class=\"flexi-voice toolbar-element\">";
			if ($lang=='th') {
				// Special case language case, maybe la=laos, and Bhutan languages in the future (NECTEC support these languages)
				$document->addScript(JURI::root().'plugins/flexicontent_fields/toolbar'.(FLEXI_J16GE ? '/toolbar' : '').'/th.js');
				$display .="
					<span class=\"voice-legend flexi-legend\"><a href=\"javascript:void(0);\" onclick=\"openwindow('".$voicetarget."','".$lang."');\" class=\"mainlevel-toolbar-article-horizontal\" rel=\"nofollow\">" . JTEXT::_('FLEXI_FIELD_TOOLBAR_VOICE') . "</a></span>
				";
			} else {
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
			$addthis_outside_toolbar  = $field->parameters->get('addthis_outside_toolbar', 0);
			$addthis_custom_code       = $field->parameters->get('addthis_custom_code', false);
			$addthis_custom_predefined = $field->parameters->get('addthis_custom_predefined', false);
			
			$addthis_code = '';
			if ($addthis_custom_code) {
				$addthis_code .= $addthis_custom_code;
			}
			else {
				switch ($addthis_custom_predefined) {
					case 1:
						$addthis_code .= '
						<!-- AddThis Button BEGIN -->
						<div class="addthis_toolbox addthis_default_style addthis_counter_style">
						<a class="addthis_button_facebook_like" fb:like:layout="button_count"></a>
						<a class="addthis_button_tweet"></a>
						<a class="addthis_button_pinterest_pinit"></a>
						<a class="addthis_counter addthis_pill_style"></a>
						</div>
						<!-- AddThis Button END -->
						';
						break;
					case 2:
						$addthis_code .= '
						<!-- AddThis Button BEGIN -->
						<div class="addthis_toolbox addthis_default_style addthis_32x32_style">
						<a class="addthis_button_preferred_1"></a>
						<a class="addthis_button_preferred_2"></a>
						<a class="addthis_button_preferred_3"></a>
						<a class="addthis_button_preferred_4"></a>
						<a class="addthis_button_compact"></a>
						<a class="addthis_counter addthis_bubble_style"></a>
						</div>
						<!-- AddThis Button END -->
						';
						break;
					default:
					case 3:
						$addthis_code .= '
						<!-- AddThis Button BEGIN -->
						<div class="addthis_toolbox addthis_default_style addthis_16x16_style">
						<a class="addthis_button_preferred_1"></a>
						<a class="addthis_button_preferred_2"></a>
						<a class="addthis_button_preferred_3"></a>
						<a class="addthis_button_preferred_4"></a>
						<a class="addthis_button_compact"></a>
						<a class="addthis_counter addthis_bubble_style"></a>
						</div>
						<!-- AddThis Button END -->
						';
						break;
					case 4:
						$addthis_code .= '
						<!-- AddThis Button BEGIN -->
						<a class="addthis_button" href="http://www.addthis.com/bookmark.php?v=300&pubid='.$addthis_pubid.'"><img src="http://s7.addthis.com/static/btn/v2/lg-share-en.gif" width="125" height="16" alt="'.JText::_('FLEXI_FIELD_TOOLBAR_SHARE').'" style="border:0"/></a>
						<!-- AddThis Button END -->
						';
						break;
					case 5:
						$addthis_code .= '
						<!-- AddThis Button BEGIN -->
						<div class="addthis_toolbox addthis_floating_style addthis_counter_style" style="left:50px;top:50px;">
						<a class="addthis_button_facebook_like" fb:like:layout="box_count"></a>
						<a class="addthis_button_tweet" tw:count="vertical"></a>
						<a class="addthis_button_google_plusone" g:plusone:size="tall"></a>
						<a class="addthis_counter"></a>
						</div>
						<!-- AddThis Button END -->
						';
						break;
					case 6:
						$addthis_code .= '
						<!-- AddThis Button BEGIN -->
						<div class="addthis_toolbox addthis_floating_style addthis_32x32_style" style="left:50px;top:50px;">
						<a class="addthis_button_preferred_1"></a>
						<a class="addthis_button_preferred_2"></a>
						<a class="addthis_button_preferred_3"></a>
						<a class="addthis_button_preferred_4"></a>
						<a class="addthis_button_compact"></a>
						</div>
						<!-- AddThis Button END -->
						';
						break;
					case 7:
						$addthis_code .= '
						<!-- AddThis Button BEGIN -->
						<div class="addthis_toolbox addthis_floating_style addthis_16x16_style" style="left:50px;top:50px;">
						<a class="addthis_button_preferred_1"></a>
						<a class="addthis_button_preferred_2"></a>
						<a class="addthis_button_preferred_3"></a>
						<a class="addthis_button_preferred_4"></a>
						<a class="addthis_button_compact"></a>
						</div>
						<!-- AddThis Button END -->
						';
						break;
				}
			}
			if ($addthis_outside_toolbar)
				$display .= '<div class="flexi-socials-outside">'.$addthis_code.'</div>';
			else 
				$display .= '<div class="flexi-socials toolbar-element">' .$addthis_code. '</div>';
			
			
			if (!$addthis) {
				$document->addCustomTag('	
					<script type="text/javascript" src="http://s7.addthis.com/js/300/addthis_widget.js#pubid='.$addthis_pubid.'"></script>
					<script type="text/javascript">
					var addthis_config = {
					     services_exclude: "print,email"
					}
					</script>
				');
				$addthis = 1;
			}
		}
		
		$display	.= '</div>'; // end of the toolbar container

		$field->{$prop} = $display;
	}
	
	
	
	// **************************************************************
	// METHODS HANDLING before & after saving / deleting field events
	// **************************************************************
	
	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField($field, &$post, $file)
	{
		if ( !in_array($field->field_type, self::$field_types) ) return;
	}
	
	
	// Method to take any actions/cleanups needed after field's values are saved into the DB
	function onAfterSaveField( &$field, &$post, &$file, &$item ) {
	}
	
	
	// Method called just before the item is deleted to remove custom item data related to the field
	function onBeforeDeleteField(&$field, &$item) {
	}
	
	
	
	// **********************
	// VARIOUS HELPER METHODS
	// **********************
	
	function _getCommentsCount($id)
	{
		$db = JFactory::getDBO();
		static $jcomment_installed = null;
		
		if ($jcomment_installed===null) {
			$app = JFactory::getApplication();
			$dbprefix = $app->getCfg('dbprefix');
			$db->setQuery('SHOW TABLES LIKE "'.$dbprefix.'jcomments"');
			$jcomment_installed = (boolean) count($db->loadObjectList());
		}
		if (!$jcomment_installed) return 0;
		
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
