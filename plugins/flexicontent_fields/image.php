<?php
/**
 * @version 1.0 $Id$
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

	function onDisplayField(&$field, $item)
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'image') return;
		
		$n = 0;
		$field->html = '';
		$select = $this->buildSelectList( $field );
		$app =& JFactory::getApplication();
		
		// if an image exists it display the existing image
		if ($field->value  && $field->value[0] != '')
		{				
			foreach ($field->value as $value) {
				$value = unserialize($value);
				$image = $value['originalname'];
				$delete = $this->canDeleteImage( $field, $image ) ? '' : ' disabled="disabled"';				
				$remove = $this->canDeleteImage( $field, $image ) ? ' disabled="disabled"' : '';
				$adminprefix = $app->isAdmin() ? '../' : '';
				$field->html	.= '
				<div style="float:left; margin-right: 5px;">
					<img src="'.$adminprefix.$field->parameters->get('dir').'/s_'.$value['originalname'].'" style="border: 1px solid silver;" />
					<br />
					<input type="checkbox" name="'.$field->name.'[remove]" value="1"'.$remove.'>'.JText::_( 'FLEXI_FIELD_REMOVE' ).'
					<input type="checkbox" name="'.$field->name.'[delete]" value="1"'.$delete.'>'.JText::_( 'FLEXI_FIELD_DELETE' ).'
					<input name="'.$field->name.'[originalname]" type="hidden" value="'.$value['originalname'].'" />
				</div>
				<div style="float:left;">
					<table class="admintable">
						<tr>
							<td class="key">'.JText::_( 'FLEXI_FIELD_ALT' ).':</td>
							<td><input name="'.$field->name.'[alt]" value="'.$value['alt'].'" type="text" /></td>
						</tr>
						<tr>
							<td class="key">'.JText::_( 'FLEXI_FIELD_TITLE' ).':</td>
							<td><input name="'.$field->name.'[title]" value="'.$value['title'].'" type="text" /></td>
						</tr>
						<tr>
							<td class="key">'.JText::_( 'FLEXI_FIELD_LONGDESC' ).':</td>
							<td><textarea name="'.$field->name.'[desc]" rows="6" cols="18" />'.(isset($value['desc']) ? $value['desc'] : '').'</textarea></td>
						</tr>
					</table>
				</div>';
				$n++;
				}
		}
		else
		{
			// else display the form for adding a new image
			$onchange = ($field->parameters->get('autoupload', 1) && $app->isAdmin()) ? ' onchange="javascript: submitbutton(\'apply\')"' : '';
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
						<td><input name="'.$field->name.'"'.$onchange.' type="file" /></td>
					</tr>
					<tr>
						<td class="key">'.JText::_( 'FLEXI_FIELD_EXISTINGFILE' ).':</td>
						<td>'.$select.'</td>
					</tr>
					<tr>
						<td class="key">'.JText::_( 'FLEXI_FIELD_ALT' ).':</td>
						<td><input name="'.$field->name.'[alt]" type="text" /></td>
					</tr>
					<tr>
						<td class="key">'.JText::_( 'FLEXI_FIELD_TITLE' ).':</td>
						<td><input name="'.$field->name.'[title]" type="text" /></td>
					</tr>
				</table>
			</div>
			';
		}
	}

	function onDisplayFieldValue(&$field, $item, $values=null, $prop='display')
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'image') return;

		global $mainframe, $multiboxadded;
		jimport('joomla.filesystem');

		$values = $values ? $values : $field->value;

		// some parameter shortcuts
		$uselegend	= $field->parameters->get( 'uselegend', 1 ) ;
		$usepopup	= $field->parameters->get( 'usepopup', 1 ) ;
		$popuptype	= $field->parameters->get( 'popuptype', 1 ) ;

		if ($values && $values[0] != '')
		{				
			$document	= & JFactory::getDocument();
			
			// load the tooltip library if redquired
			if ($uselegend) JHTML::_('behavior.tooltip');
			
			if ($usepopup && $mainframe->isSite() && ($popuptype == 1))
			{
				if (!$multiboxadded) {
			// Multibox integration 
			$document->addStyleSheet('components/com_flexicontent/librairies/multibox/multibox.css');

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

			JHTML::_('behavior.mootools');
			$document->addScript('components/com_flexicontent/librairies/multibox/js/overlay.js');
			$document->addScript('components/com_flexicontent/librairies/multibox/js/multibox.js');

			$box = "
			var box = {};
			window.addEvent('domready', function(){
				box = new MultiBox('mb', {descClassName: 'multiBoxDesc', useOverlay: true});
			});
			";
			$document->addScriptDeclaration($box);
			
			$multiboxadded = 1;
				}
			}
			
			$i = 0;
			foreach ($values as $value)
			{
				$value	= unserialize($value);
				$path	= JPath::clean(JPATH_SITE . DS . $field->parameters->get('dir') . DS . 'l_' . $value['originalname']);
				$size	= getimagesize($path);
				$hl 	= $size[1];
				$wl 	= $size[0];
				$title	= isset($value['title']) ? $value['title'] : '';
				$alt	= isset($value['alt']) ? $value['alt'] : '';
				$desc	= isset($value['desc']) ? $value['desc'] : '';
				$srcs	= $field->parameters->get('dir') . '/s_' . $value['originalname'];
				$srcm	= $field->parameters->get('dir') . '/m_' . $value['originalname'];
				$srcb	= $field->parameters->get('dir') . '/l_' . $value['originalname'];
				$tip	= JText::_( 'FLEXI_FIELD_LEGEND' ) . '::' . $title;
				$id		= $field->item_id . '_' . $field->id . '_' . $i;
				$legend = $uselegend ? ' class="hasTip" title="'.$tip.'"' : '' ;
				$i++;
			
				$view 	= JRequest::setVar('view', JRequest::getVar('view', 'items'));
				$src 	= ($view == 'category') ? $srcs : $srcm;
				// first condition is for the display for the preview feature
				if ($mainframe->isAdmin()) {
					$field->{$prop} = '<img class="hasTip" src="../'.$srcs.'" alt ="'.$alt.'" title="'.$tip.'" />';
				}
				else if ($usepopup && $popuptype == 1)
				{
					$field->{$prop} = '
					<a href="'.$srcb.'" id="mb'.$id.'" class="mb">
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
				} else {
					$field->{$prop} = '<img src="'. $src .'" alt ="'.$alt.'"'.$legend.' />';
				}
			}

		} else {
			$field->{$prop} = '';
		}
		// some parameter shortcuts
	}
	
	function onBeforeSaveField($field, &$post, &$file)
	{
		// execute the code only if the field type match the plugin type
		if($field->field_type != 'image') return;

		global $mainframe;
		
		// create the fulltext search index
		$searchindex = '';
		
		$searchindex .= $post['alt'];
		$searchindex .= ' ';
		$searchindex .= $post['title'];
		$searchindex .= ' ';
		$searchindex .= $post['desc'];
		$searchindex .= ' | ';

		$field->search = $searchindex;

		// Upload the original file
		$this->uploadOriginalFile($field, $post, $file);
		
		if ($post['originalname'])
		{
			if ($post['delete'] == 1)
			{
				$filename = $post['originalname'];
				$this->removeOriginalFile( $field, $filename );
				$post = '';
				$mainframe->enqueueMessage($field->label . ' : ' . JText::_('Images succesfully removed'));
			}
			elseif ($post['remove'] == 1)
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
		global $mainframe;
		
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
						// some parameters for phpthumb
						$ext 		= strtolower(JFile::getExt($file['name']));
						$onlypath 	= JPath::clean(COM_FLEXICONTENT_FILEPATH.DS);
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
	
						// set the filename for posting
						$post['originalname'] = $filename;
					}
					return;
				}
			}
		}
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

		$output_filename = $destpath . $prefix . $filename ;

		if ($phpThumb->GenerateThumbnail())
		{
			//echo "generated!";
			//die();
			if ($phpThumb->RenderToFile($output_filename))
			{
				// echo "rendered!";
				// die();
			} else {
				echo 'Failed:<pre>' . implode("\n\n", $phpThumb->debugmessages) . '</pre>';
				die();
			}
		} else {
			echo 'Failed2:<pre>' . $phpThumb->fatalerror . "\n\n" . implode("\n\n", $phpThumb->debugmessages) . '</pre>';
			//echo 'Failed:<div class="error">Size is too big!</pre>';
			die();
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

	function rebuildThumbs( $field )
	{
		// @TODO implement
	}

	function buildSelectList( $field )
	{
		$db =& JFactory::getDBO();
		
		$query = 'SELECT value'
				. ' FROM #__flexicontent_fields_item_relations'
				. ' WHERE field_id = '. (int) $field->id
				;
		$db->setQuery($query);
		$values = $db->loadResultArray();
		

		for($n=0, $c=count($values); $n<$c; $n++)
		{
			$values[$n] = unserialize($values[$n]);
			$values[$n] = $values[$n]['originalname'];
		}
		
		// eliminate duplicate records in the array
		$values = array_unique($values);

		$options = array(); 
		$options[] = JHTML::_('select.option', '', JText::_('FLEXI_FIELD_PLEASE_SELECT'));
		$onchange = ($field->parameters->get('autoupload', 1) && $app->isAdmin()) ? ' onchange="javascript: submitbutton(\'apply\')"' : '';
		foreach ($values as $value) {
			$options[] = JHTML::_('select.option', $value, $value); 
		}
		$list	= JHTML::_('select.genericlist', $options, $field->name.'[originalname]', $onchange, 'value', 'text', '');

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
			if ($values[$n] == $record) { $i++; }
		}
		
		if ($i > 1) return false;
		
		return true;
	}

}