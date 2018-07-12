<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

class flexicontent_images
{
	/**
	 * Get file size and icons
	 *
	 * @since 1.5
	 */
	static function BuildIcons($rows, $default_text = null)
	{
		jimport('joomla.filesystem.path' );
		jimport('joomla.filesystem.file');
		$NA = '-';

		for ($i=0, $n=count($rows); $i < $n; $i++)
		{
			$basePath = $rows[$i]->secure ? COM_FLEXICONTENT_FILEPATH : COM_FLEXICONTENT_MEDIAPATH;

			if ($rows[$i]->url)
			{
				$size = (int)$rows[$i]->size ? (int)$rows[$i]->size : $NA;
			}
			else if (is_file($basePath.DS.$rows[$i]->filename))
			{
				if (empty($rows[$i]->size))
				{
					$size = $default_text ?: filesize(str_replace(DS, '/', JPath::clean($basePath.DS.$rows[$i]->filename)));
				}
				else
				{
					$size = $rows[$i]->size;
				}
			}
			else
			{
				$size = $NA;
			}

			if (is_numeric($size))
			{
				if ($size < 1024)
				{
					$rows[$i]->size = $size . ' bytes';
				}
				else
				{
					$rows[$i]->size = $size < 1024 * 1024
						? sprintf('%01.2f', $size / 1024.0) . ' KBs'
						: sprintf('%01.2f', $size / (1024.0 * 1024)) . ' MBs';
				}
			}
			else
			{
				$rows[$i]->size = $size;
			}

			if ($rows[$i]->url == 1)
			{
				$ext = $rows[$i]->ext;
			} else {
				$ext = strtolower(flexicontent_upload::getExt($rows[$i]->filename));
			}
			switch ($ext)
			{
				// Image
				case 'jpg':
				case 'png':
				case 'gif':
				case 'xcf':
				case 'odg':
				case 'bmp':
				case 'jpeg':
					$rows[$i]->icon = 'components/com_flexicontent/assets/images/mime-icon-16/image.png';
					break;

				// Non-image document
				default:
					$icon = JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'assets'.DS.'images'.DS.'mime-icon-16'.DS.$ext.'.png';
					$rows[$i]->icon = file_exists($icon)
						? 'components/com_flexicontent/assets/images/mime-icon-16/'.$ext.'.png'
						: 'components/com_flexicontent/assets/images/mime-icon-16/unknown.png';
					break;
			}
		}

		return $rows;
	}
}