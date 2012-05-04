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
		$required = $field->parameters->get( 'required', 0 ) ;
		$required 	= $required ? ' required' : '';
		$autoupload = $field->parameters->get('autoupload', 1);
		$always_allow_removal = $field->parameters->get('always_allow_removal', 0);
		
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
			";
		$document	= & JFactory::getDocument();
		$document->addScriptDeclaration($js);

		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'image') return;
		
		$n = 0;
		$field->html = '';
		$select = $this->buildSelectList( $field );
		$app =& JFactory::getApplication();
		$linkto_url = $field->parameters->get('linkto_url',0);
		
		// if an image exists it display the existing image
		if ($field->value  && @$field->value[0]!=='')
		{
			if(isset($field->value['originalname']))
				$field->value = array($field->value);
			foreach ($field->value as $value) {
				$value = unserialize($value);
				
				// Check and rebuild thumbnails if needed
				$this->rebuildThumbs($field,$value);
				
				$image = $value['originalname'];
				$delete = $this->canDeleteImage( $field, $image ) ? '' : ' disabled="disabled"';				
				if ($always_allow_removal)
					$remove = '';
				else
					$remove = $this->canDeleteImage( $field, $image ) ? ' disabled="disabled"' : '';
				$adminprefix = $app->isAdmin() ? '../' : '';
				$field->html	.= '
				<div style="float:left; margin-right: 5px;">
					<img src="'.$adminprefix.$field->parameters->get('dir').'/s_'.$value['originalname'].'" style="border: 1px solid silver;" />
					<br />
					<input type="checkbox" name="custom['.$field->name.'][remove]" value="1"'.$remove.' style="float:none;">'.JText::_( 'FLEXI_FIELD_REMOVE' ).' &nbsp;
					<input type="checkbox" name="custom['.$field->name.'][delete]" value="1"'.$delete.' style="float:none;">'.JText::_( 'FLEXI_FIELD_DELETE' ).'
					<input name="custom['.$field->name.'][originalname]" type="hidden" value="'.$value['originalname'].'" />
				</div>
				<div style="float:left;">
					<table class="admintable">'.
					($linkto_url ? '
						<tr>
							<td class="key">'.JText::_( 'FLEXI_FIELD_LINKTO_URL' ).':</td>
							<td><input size="40" name="custom['.$field->name.'][urllink]" value="'.(isset($value['urllink']) ? $value['urllink'] : '').'" type="text" /></td>
						</tr>'
						:
						'').'
						<tr>
							<td class="key">'.JText::_( 'FLEXI_FIELD_ALT' ).': ('.JText::_('FLEXI_FIELD_IMAGE').')</td>
							<td><input size="40" name="custom['.$field->name.'][alt]" value="'.$value['alt'].'" type="text" /></td>
						</tr>
						<tr>
							<td class="key">'.JText::_( 'FLEXI_FIELD_TITLE' ).': ('.JText::_('FLEXI_FIELD_TOOLTIP').')</td>
							<td><input size="40" name="custom['.$field->name.'][title]" value="'.$value['title'].'" type="text" /></td>
						</tr>
						<tr>
							<td class="key">'.JText::_( 'FLEXI_FIELD_LONGDESC' ).': ('.JText::_('FLEXI_FIELD_TOOLTIP').')</td>
							<td><textarea name="custom['.$field->name.'][desc]" rows="5" cols="30" />'.(isset($value['desc']) ? $value['desc'] : '').'</textarea></td>
						</tr>
					</table>
				</div>';
				$n++;
				}
		}
		else
		{
			// else display the form for adding a new image
			$class = ' class="'.$required.' "';
			$onchange= ' onchange="';
			$onchange .= ($required) ? ' fx_img_toggle_required(this,$(\''.$field->name.'originalname\')); ' : '';
			$onchange .= ($autoupload && $app->isAdmin()) ? ' submitbutton(\'apply\')"' : '';
			$onchange .= ' "';
			$field->html	.= '
			<div style="float:left; margin-right: 5px;">
				<div class="empty_image" style="height:'.$field->parameters->get('h_s').'px; width:'.$field->parameters->get('w_s').'px;"></div>
			</div>
			<div style="float:left;">
				<table class="admintable">
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
						<td><input name="'.$field->name.'" id="'.$field->name.'_newfile"  class="'.$required.'" '.$onchange.' type="file" /></td>
					</tr>
					<tr>
						<td class="key">'.JText::_( 'FLEXI_FIELD_EXISTINGFILE' ).':</td>
						<td>'.$select.'</td>
					</tr>'.
					($linkto_url ? '
					<tr>
						<td class="key">'.JText::_( 'FLEXI_FIELD_LINKTO_URL' ).':</td>
						<td><input size="40" name="custom['.$field->name.'][urllink]" value="'.(isset($value['urllink']) ? $value['urllink'] : '').'" type="text" /></td>
					</tr>'
					:
					'').'
					<tr>
						<td class="key">'.JText::_( 'FLEXI_FIELD_ALT' ).': ('.JText::_('FLEXI_FIELD_IMAGE').')</td>
						<td><input size="40" name="custom['.$field->name.'][alt]" type="text" /></td>
					</tr>
					<tr>
						<td class="key">'.JText::_( 'FLEXI_FIELD_TITLE' ).': ('.JText::_('FLEXI_FIELD_TOOLTIP').')</td>
						<td><input size="40" name="custom['.$field->name.'][title]" type="text" /></td>
					</tr>
					<tr>
						<td class="key">'.JText::_( 'FLEXI_FIELD_LONGDESC' ).': ('.JText::_('FLEXI_FIELD_TOOLTIP').')</td>
						<td><textarea name="custom['.$field->name.'][desc]" rows="2" cols="30" />'.(isset($value['desc']) ? $value['desc'] : '').'</textarea></td>
					</tr>
				</table>
			</div>
			';
		}
	}

	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		$field->label = JText::_($field->label);
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'image') return;

		static $multiboxadded = false;
		$mainframe = &JFactory::getApplication();
		$view = JRequest::getVar('view');
		jimport('joomla.filesystem');

		$values = $values ? $values : $field->value;

		// some parameter shortcuts
		$uselegend	= $field->parameters->get( 'uselegend', 1 ) ;
		$usepopup	= $field->parameters->get( 'usepopup', 1 ) ;
		$popuptype	= $field->parameters->get( 'popuptype', 1 ) ;
		
		// Check and disable 'uselegend'
		$legendinview = $field->parameters->get('legendinview', array(FLEXI_ITEMVIEW,'category'));
		if (FLEXI_J16GE && !is_array($legendinview)) {
			$legendinview = explode("|", $legendinview);
			$legendinview = ($legendinview[0]=='') ? array() : $legendinview;
		} else {
			$legendinview = !is_array($legendinview) ? array($legendinview) : $legendinview;
		}
		if ($view==FLEXI_ITEMVIEW && !in_array(FLEXI_ITEMVIEW,$legendinview)) $uselegend = 0;
		if ($view=='category' && !in_array('category',$legendinview)) $uselegend = 0;
		
		// Check and disable 'usepopup'
		$popupinview = $field->parameters->get('popupinview', array(FLEXI_ITEMVIEW,'category'));
		if (FLEXI_J16GE && !is_array($popupinview)) {
			$popupinview = explode("|", $popupinview);
			$popupinview = ($popupinview[0]=='') ? array() : $popupinview;
		} else {
			$popupinview = !is_array($popupinview) ? array($popupinview) : $popupinview;
		}
		if ($view==FLEXI_ITEMVIEW && !in_array(FLEXI_ITEMVIEW,$popupinview)) $usepopup = 0;
		if ($view=='category' && !in_array('category',$popupinview)) $usepopup = 0;
		
		$showtitle    = $field->parameters->get( 'showtitle', 0 ) ;
		$showdesc	= $field->parameters->get( 'showdesc', 0 ) ;
		
		$linkto_url	= $field->parameters->get('linkto_url',0);
		$url_target = $field->parameters->get('url_target','_self');
		
		// Allow for thumbnailing of the default image
		if (!$values || $values[0] == '') {
			$default_image = $field->parameters->get( 'default_image', '');
			if ( $default_image !== '' ) {
				$values[0] = array();
				$values[0]['is_default_image'] = true;
				$values[0]['default_image'] = $default_image;
				$values[0]['originalname'] = basename($default_image);
				$values[0]['title'] = $values[0]['alt'] = $values[0]['desc'] = $values[0]['urllink'] = '';
				$values[0] = serialize($values[0]);
				$field->value[0] = $values[0];
			}
		}
		
		if ($values && $values[0] != '')
		{
			$document	= & JFactory::getDocument();
			
			// load the tooltip library if redquired
			if ($uselegend) JHTML::_('behavior.tooltip');
			
			if ( $mainframe->isSite()
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
					$box = "
						var box = {};
						window.addEvent('domready', function(){
							box = new MultiBox('mb', {descClassName: 'multiBoxDesc', useOverlay: true});
						});
					";
					$document->addScriptDeclaration($box);
				}

				$multiboxadded = 1;
			}
			
			$i = 0;
			foreach ($values as $value)
			{
				$value	= unserialize($value);
				
				// Check and rebuild thumbnails if needed
				$this->rebuildThumbs($field,$value);
				
				$path	= JPath::clean(JPATH_SITE . DS . $field->parameters->get('dir') . DS . 'l_' . $value['originalname']);
				$size	= getimagesize($path);
				$hl 	= $size[1];
				$wl 	= $size[0];
				$title	= @$value['title'] ? $value['title'] : '';
				$alt	= @$value['alt'] ? $value['alt'] : flexicontent_html::striptagsandcut($item->title, 60);
				$desc	= @$value['desc'] ? $value['desc'] : '';

				$srcs	= $field->parameters->get('dir') . '/s_' . $value['originalname'];
				$srcm	= $field->parameters->get('dir') . '/m_' . $value['originalname'];
				$srcb	= $field->parameters->get('dir') . '/l_' . $value['originalname'];
				
				$urllink = @$value['urllink'] ? $value['urllink'] : '';
				if ($urllink && false === strpos($urllink, '://')) $urllink = 'http://' . $urllink;
				
				$tip	= $title . '::' . $desc;
				$id		= $field->item_id . '_' . $field->id . '_' . $i;
				$legend = ($uselegend && (!empty($title) || !empty($desc) ) )? ' class="hasTip" title="'.$tip.'"' : '' ;
				$i++;
				
				$view 	= JRequest::setVar('view', JRequest::getVar('view', FLEXI_ITEMVIEW));
				
				$thumb_size = 0;
				if ($view == 'category')
					$thumb_size =  $field->parameters->get('thumbincatview',2);
				if($view == FLEXI_ITEMVIEW)
					$thumb_size =  $field->parameters->get('thumbinitemview',1);
				switch ($thumb_size)
				{
					case 1: $src = $srcs; break;
					case 2: $src = $srcm; break;
					case 3: $src = $srcb; $popuptype = 0; break;
					default: $src = $srcs; break;
				}
				
				// ADD some extra (display) properties that point to all sizes
				$field->{"display_small_src"} = JURI::root().$srcs;
				$field->{"display_medium_src"} = JURI::root().$srcm;
				$field->{"display_large_src"} = JURI::root().$srcb;
				$field->{"display_small"} = '<img src="'.JURI::root().$srcs.'" alt ="'.$alt.'"'.$legend.' />';
				$field->{"display_medium"} = '<img src="'.JURI::root().$srcm.'" alt ="'.$alt.'"'.$legend.' />';
				$field->{"display_large"} = '<img src="'.JURI::root().$srcb.'" alt ="'.$alt.'"'.$legend.' />';
				
				// Check if a custom variable (display) was requested and do not render default variable
				if ( in_array($prop,
						array("display_small","display_medium", "display_large",
						"display_small_src","display_medium_src", "display_large_src") )
				) {
					return $field->{$prop};
				}
								
				// first condition is for the display for the preview feature
				if ($mainframe->isAdmin()) {
					$field->{$prop} = '<img class="hasTip" src="../'.$srcs.'" alt ="'.$alt.'" title="'.$tip.'" />';
				} else if ($linkto_url && $url_target=='multibox' && $urllink) {
					$field->{$prop} = '
					<script>document.write(\'<a href="'.$urllink.'" id="mb'.$id.'" class="mb" rel="width:\'+(window.getSize().size.x-150)+\',height:\'+(window.getSize().size.y-150)+\'">\')</script>
						<img src="'. $src .'" alt ="'.$alt.'"'.$legend.' />
					<script>document.write(\'</a>\')</script>
					<div class="multiBoxDesc mbox_img_url mb'.$id.'">'.($desc ? $desc : $title).'</div>
					';
				} else if ($linkto_url && $urllink) {
					$field->{$prop} = '
					<a href="'.$urllink.'" target="'.$url_target.'">
						<img src="'. $src .'" alt ="'.$alt.'"'.$legend.' />
					</a>
					';
				} else if ($usepopup && $popuptype == 1) {
					$field->{$prop} = '
					<a href="'.$srcb.'" id="mb'.$id.'" class="mb" rel="[images]" >
						<img src="'. $src .'" alt ="'.$alt.'"'.$legend.' />
					</a>
					<div class="multiBoxDesc mb'.$id.'">'.($desc ? $desc : $title).'</div>
					';
				} else if ($usepopup && $popuptype == 2) {
				$field->{$prop} = '
					<a href="'.$srcb.'" rel="rokbox['.$wl.' '.$hl.']" title="'.($desc ? $desc : $title).'">
						<img src="'. $src .'" alt ="'.$alt.'" />
					</a>
					';
				} else if ($usepopup && $popuptype == 3) {
				$field->{$prop} = '
					<a href="'.$srcb.'" class="jcepopup" rel="'.$field->item_id.'" title="'.($desc ? $desc : $title).'">
						<img src="'. $src .'" alt ="'.$alt.'" />
					</a>
					';
				} else {
					$field->{$prop} = '<img src="'. $src .'" alt ="'.$alt.'"'.$legend.' />';
				}
			}
			if ($showtitle || $showdesc) $field->{$prop} = '<div class="fcimg_tooltip_data">'.$field->{$prop};
			if ($showtitle) $field->{$prop} .= '<div class="fc_img_tooltip_title" style="line-height:1em; font-weight:bold;">'.$title.'</div>';
			if ($showdesc) $field->{$prop} .= '<div class="fc_img_tooltip_desc" style="line-height:1em;">'.$desc.'</div>';
			if ($showtitle || $showdesc) $field->{$prop} .= '</div>';
		} else {
			$field->{$prop} = '';
		}
		// some parameter shortcuts
	}
	
	function onBeforeSaveField($field, &$post, &$file)
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'image') return;
		if(!$post) return;

		$mainframe = &JFactory::getApplication();
		
		// create the fulltext search index
		if ($field->issearch) {
			$searchindex = '';
			
			$searchindex .= $post['alt'];
			$searchindex .= ' ';
			$searchindex .= $post['title'];
			$searchindex .= ' ';
			$searchindex .= $post['desc'];
			$searchindex .= ' | ';
	
			$field->search = $searchindex;
		} else {
			$field->search = '';
		}

		// Upload the original file
		$this->uploadOriginalFile($field, $post, $file);
		if ($post['originalname'])
		{
			if (@$post['delete'] == 1)
			{
				$filename = $post['originalname'];
				$this->removeOriginalFile( $field, $filename );
				$post = '';
				$mainframe->enqueueMessage($field->label . ' : ' . JText::_('Images succesfully removed'));
			}
			elseif (@$post['remove'] == 1)
			{
				$post = '';
			} else {
				// we serialize this array to keep it's properties.
				$post = serialize($post);
			}
		} else {
			// unset $post because no file was posted
			$post = '';
		}
	}
	
	function uploadOriginalFile($field, &$post, $file)
	{
		$mainframe = &JFactory::getApplication();
		
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

					$mainframe->enqueueMessage($field->label . ' : ' . JText::_('Upload complete'));
					
					$sizes 		= array('l','m','s');
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
		$destpath	= JPath::clean(JPATH_SITE . DS . $field->parameters->get('dir', 'images/stories/flexicontent') . DS);
		$prefix		= $size . '_';
		$w			= $field->parameters->get('w_'.$size);
		$h			= $field->parameters->get('h_'.$size);
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

		// delete the thumbnails
		$errors		= array();
		$sizes 		= array('l','m','s');
		foreach ($sizes as $size)
		{
			$path		= JPATH_SITE . DS . $field->parameters->get('dir');
			$image	 	= $path . DS . $size . '_' . $filename;
								
			if (!JFile::delete($image)) 
			{ 
				// handle failed delete
				$errors[] = JText::_('Unable to delete file');
			}
		}
		
		$origpath = JPath::clean(COM_FLEXICONTENT_FILEPATH.DS.$filename);
		// delete the original image from file manager
		if (!JFile::delete($origpath)) {
				JError::raiseNotice(100, JText::_('Unable to delete:').$origpath);
			}

		$query  = 'DELETE FROM #__flexicontent_files'
				. ' WHERE ' . $db->nameQuote('filename') . ' = ' . $db->Quote($filename);

		$db->setQuery( $query );

		if(!$db->query()) {
			$this->setError($db->getErrorMsg());
			return false;
		}

	}


	function rebuildThumbs( &$field, $value )
	{
		$filename = $value['originalname'];
		if (empty($value['is_default_image'])) {
			$onlypath 	= JPath::clean(COM_FLEXICONTENT_FILEPATH.DS);
			$filepath = $onlypath . $filename;
		} else {
			$onlypath = JPATH_BASE.DS.dirname($value['default_image']).DS;
			$filepath = JPATH_BASE.DS.$value['default_image'];
		}
		
		if (!file_exists($filepath)) {
			echo "Original file seems to have been deleted, cannot find image file: ".$filepath ."<br />\n";
			return;
		}
		$filesize	= getimagesize($filepath);
		$origsize_h = $filesize[1];
		$origsize_w = $filesize[0];
		
		$sizes 		= array('l','m','s');
		foreach ($sizes as $size)
		{
			$path	= JPath::clean(JPATH_SITE . DS . $field->parameters->get('dir') . DS . $size . '_' . $filename);
			$thumbnail_exists = false;
			if (file_exists($path)) {
				$filesize = getimagesize($path);
				$filesize_h = $filesize[1];
				$filesize_w = $filesize[0];
				$param_h = $field->parameters->get('h_'.$size);
				$param_w = $field->parameters->get('w_'.$size);
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
		$required		= $field->parameters->get( 'required', 0 ) ;
		$required		= $required ? ' required' : '';
		$autoupload = $field->parameters->get('autoupload', 1);
		$list_all_media_files = $field->parameters->get('list_all_media_files', 0);
		$limit_by_uploader = $field->parameters->get('limit_by_uploader', 0);
		
		$db =& JFactory::getDBO();
		$app =& JFactory::getApplication();
		$user =& JFactory::getUser();
		
		if ($list_all_media_files) {
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

		if (!$list_all_media_files) {
			for($n=0, $c=count($values); $n<$c; $n++) {
				if (!$values[$n]) { unset($values[$n]); continue; }
				$values[$n] = unserialize($values[$n]);
				$values[$n] = $values[$n]['originalname'];
			}
		}
		
		// eliminate duplicate records in the array
		$values = array_unique($values);
		sort($values);

		$options = array(); 
		$options[] = JHTML::_('select.option', '', JText::_('FLEXI_FIELD_PLEASE_SELECT'));
		$class = ' class="'.$required.' "';
		$onchange= ' onchange="';
		$onchange .= ($required) ? ' fx_img_toggle_required(this,$(\''.$field->name.'_newfile\')); ' : '';
		$onchange .= ($autoupload && $app->isAdmin()) ? ' submitbutton(\'apply\')"' : '';
		$onchange .= ' "';
		foreach ($values as $value) {
			$options[] = JHTML::_('select.option', $value, $value); 
		}
		$list	= JHTML::_('select.genericlist', $options, 'custom['.$field->name.'][originalname]', $onchange, 'value', 'text', '');

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
