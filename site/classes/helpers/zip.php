<?php
defined( '_JEXEC' ) or die( 'Restricted access' );

class flexicontent_zip extends ZipArchive
{
	/**
	 * Add a directory with files and subdirectories to the archive
	 *
	 * @param string $location Full (real) pathname
	 * @param string $name Name in Archive
	 **/
	public function addDir($pathname, $name)
	{
		$this->addEmptyDir($name);
		$this->addDirDo($pathname, $name);
	}

	/**
	 * Add files & directories to archive
	 *
	 * @param string $location Full (real) pathname
	 * @param string $name Name in Archive
	 **/
	private function addDirDo($pathname, $name)
	{
		if ($name) $name .= '/';
		$pathname .= '/';

		// Read all Files in Dir
		$dir = opendir ($pathname);
		while ($file = readdir($dir))
		{
			if ($file == '.' || $file == '..') continue;

			// Rekursiv, If dir: FlxZipArchive::addDir(), else ::File();
			$do = (filetype( $pathname . $file) == 'dir') ? 'addDir' : 'addFile';
			$this->$do($pathname . $file, $name . $file);
		}
	}
}
