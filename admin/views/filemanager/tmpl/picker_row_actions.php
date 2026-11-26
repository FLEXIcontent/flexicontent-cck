<?php
/**
 * 'View' / 'Download' and 'Edit' buttons of a file row of the file library opened by the field pickers.
 * Variables of the list template: $row, $file_abspath, $file_src, $file_inroot
 */
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

$view_url   = '';
$view_title = 'FLEXI_VIEW';
$view_icon  = 'icon-eye';

// A link: only web addresses (or addresses relative to the site) are opened
if (!$this->folder_mode && (int) $row->url === 1)
{
	$address = trim($row->filename);

	if (preg_match('#^(https?:|ftps?:)?//#i', $address))
	{
		$view_url = $address;
	}
	elseif ($address !== '' && !preg_match('#^[a-z][a-z0-9+.\-]*:#i', $address))
	{
		$view_url = Uri::root() . ltrim($address, '/');
	}
}

// A file of the secure folder (or outside of the site) has no public address, it is downloaded via the component
elseif (!$this->folder_mode && ((!$row->url && !empty($row->secure)) || !$file_inroot))
{
	if (is_file($file_abspath))
	{
		// This is a site download: it checks the file access of the (site) user and counts a hit
		$view_url   = Uri::root() . 'index.php?option=com_flexicontent&task=download_file&id=' . (int) $row->id;
		$view_title = 'FLEXI_DOWNLOAD';
		$view_icon  = 'icon-download';
	}
}

// A public file: opened directly, this does not count a download
elseif ($file_inroot && is_file($file_abspath))
{
	$view_url = Uri::root() . implode('/', array_map('rawurlencode', explode('/', $file_src)));
}

// The same editor (and permissions) as the 'Edit' button of the file values in the item form
$can_edit  = !$this->folder_mode && $row->canEdit;
$is_locked = $can_edit && $row->checked_out && $row->checked_out != $user->id;
$task_url  = Uri::base(true) . '/index.php?option=com_flexicontent&' . Session::getFormToken() . '=1&task=filemanager.';
?>
<?php if ($view_url !== '') : ?>
	<a class="btn btn-mini ntxt" href="<?php echo $this->escape($view_url); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo $this->escape(Text::_($view_title)); ?>" aria-label="<?php echo $this->escape(Text::_($view_title)); ?>">
		<span class="<?php echo $view_icon; ?>" aria-hidden="true"></span>
	</a>
<?php endif; ?>
<?php if ($can_edit) :
	$edit_title = Text::_($is_locked ? 'FLEXI_EDITED_BY_ANOTHER_ADMIN' : 'FLEXI_EDIT_FILE'); ?>
	<button type="button" class="btn btn-mini ntxt" title="<?php echo $this->escape($edit_title); ?>" aria-label="<?php echo $this->escape($edit_title); ?>"
		data-picker-edit="<?php echo $this->escape($task_url . 'edit&view=file&id=' . (int) $row->id . '&tmpl=component&fcmodal=1&fieldid=' . (int) $this->fieldid . '&u_item_id=' . (int) $this->u_item_id); ?>"
		data-picker-checkin="<?php echo $this->escape($task_url . 'checkin&cid[]=' . (int) $row->id); ?>" <?php echo $is_locked ? 'disabled' : ''; ?>>
		<span class="<?php echo $is_locked ? 'icon-lock' : 'icon-edit'; ?>" aria-hidden="true"></span>
	</button>
<?php endif; ?>
