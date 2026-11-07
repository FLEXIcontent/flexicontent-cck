<?php
defined('_JEXEC') or die('Restricted access');

/** Shared presentation and download-name rules for the FLEXIcontent file editor. */
class flexicontent_fileeditor
{
	const FIELDS = array('download_filename', 'title', 'description', 'replace', 'keep_uploaded_date', 'keep_uploaded_by', 'language', 'access', 'stamp', 'hits', 'type', 'size', 'storage', 'path');

	static function visible($params, $field)
	{
		return (int) $params->get('file_editor_show_' . $field, $field === 'path' ? 0 : 1) === 1;
	}

	static function extension($stored)
	{
		$source = preg_match('#^https?://#i', $stored) ? (string) parse_url($stored, PHP_URL_PATH) : $stored;
		return strtolower(pathinfo(str_replace('\\', '/', $source), PATHINFO_EXTENSION));
	}

	/** Keep the requested name, but derive its suffix from the stored file or validated URL. */
	static function downloadName($requested, $stored)
	{
		$source = preg_match('#^https?://#i', $stored) ? (string) parse_url($stored, PHP_URL_PATH) : $stored;
		$fallback = pathinfo(basename(str_replace('\\', '/', $source)), PATHINFO_FILENAME);
		$name = basename(str_replace('\\', '/', preg_replace('/[\x00-\x1f\x7f]/', '', strip_tags((string) $requested))));
		$stem = trim(pathinfo(trim($name), PATHINFO_FILENAME), '. ');
		if ($stem === '') $stem = trim($fallback, '. ');
		$extension = self::extension($stored);
		return $stem . ($extension !== '' ? '.' . $extension : '');
	}
}
