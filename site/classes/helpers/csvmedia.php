<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

/**
 * Collects the media files (images, local files) of a CSV export, to be added into a ZIP file
 * together with the CSV file. Fields call add() while creating their 'csv_export' display
 * and use the returned name as the value written to the CSV file.
 */
class flexicontent_csvmedia
{
	// Set by the CSV export view when media files are to be added to a ZIP file
	public static $enabled = false;

	// Collected files: [zip folder][name inside zip folder] => full path of source file
	public static $files = array();

	// Files that were not found on the server: array of 'zip folder/name'
	public static $missing = array();

	/**
	 * Register a source file to be added into a ZIP folder, and return the name to use in the CSV file
	 * A different file having the same name is renamed, e.g. icon.webp -> icon_2.webp
	 *
	 * @param   string  $folder    Folder inside the ZIP file, e.g. fcimport_media
	 * @param   string  $src_path  Full path of the source file
	 * @param   string  $name      Filename to use inside the ZIP folder
	 *
	 * @return  string  The (possibly renamed) filename
	 */
	public static function add($folder, $src_path, $name)
	{
		if (!static::$enabled || !strlen($name))
		{
			return $name;
		}

		if (!is_file($src_path))
		{
			static::$missing[$folder . '/' . $name] = 1;
			return $name;
		}

		$ext  = pathinfo($name, PATHINFO_EXTENSION);
		$base = $ext !== '' ? substr($name, 0, -(strlen($ext) + 1)) : $name;

		for ($n = 1, $candidate = $name; isset(static::$files[$folder][$candidate]); $candidate = $base . '_' . (++$n) . ($ext !== '' ? '.' . $ext : ''))
		{
			// Same file already added (e.g. same image used by many items)
			$existing = static::$files[$folder][$candidate];
			if ($existing === $src_path || (filesize($existing) === filesize($src_path) && md5_file($existing) === md5_file($src_path)))
			{
				return $candidate;
			}
		}

		static::$files[$folder][$candidate] = $src_path;
		return $candidate;
	}
}
