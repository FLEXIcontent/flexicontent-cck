<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

class flexicontent_zip extends ZipArchive
{
	/**
	 * Add a directory with files and subdirectories to the archive
	 *
	 * @param string $pathname       Full (real) pathname of directory to add
	 * @param string $dirname        Directory name to add (in archive) for first call this is empty
	 *
	 * @since 1.0
	 **/
	public function addDir($pathname, $dirname)
	{
		if (!empty($dirname)) $this->addEmptyDir($dirname);
		$this->addDirDo($pathname, $dirname);
	}

	/**
	 * Add files & directories to archive for the given $dirname directory
	 *
	 * @param string     $pathname    Full (real) pathname
	 * @param string     $dirname     Directory name to examine and recursively add to archive
	 *
	 * @since 1.0
	 **/
	private function addDirDo($pathname, $dirname)
	{
		if ($dirname) $dirname .= '/';
		$pathname .= '/';

		// Read all Files in Dir
		$dir = opendir ($pathname);
		while ($file = readdir($dir))
		{
			if ($file == '.' || $file == '..') continue;

			// Recursive add contents. CASE dir: THIS::addDir(), else FlxZipArchive::addFile();
			$do = (filetype( $pathname . $file) == 'dir') ? 'addDir' : 'addFile';
			$this->$do($pathname . $file, $dirname . $file);
		}
	}
}
