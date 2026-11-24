<?php
/**
 * Confirmation reached from the file-in-use warning in the manager or picker.
 * @license GNU/GPL
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

$app = Factory::getApplication();
$user = $app->getIdentity();
if (!$user->authorise('flexicontent.deletefile', 'com_flexicontent') || !$user->authorise('core.edit', 'com_content'))
{
	throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
}
$requested = $app->input->getString('fc_confirm_unlink');
if (!preg_match('/^[1-9][0-9]*(?:,[1-9][0-9]*)*$/D', $requested))
{
	return;
}
$db = Factory::getContainer()->get(DatabaseInterface::class);
$confirmationFiles = $db->setQuery('SELECT id, filename, filename_original FROM #__flexicontent_files WHERE id IN ('
	. implode(',', array_map('intval', explode(',', $requested))) . ')')->loadObjectList();
if (!$confirmationFiles)
{
	return;
}

$back = clone Uri::getInstance();
$back->delVar('fc_confirm_unlink');
$back->setFragment('');
?>
<form method="post" id="fc-unlink-confirm" class="alert alert-warning" action="<?php echo Uri::base(true); ?>/index.php" aria-labelledby="fc-unlink-title">
	<h2 id="fc-unlink-title"><?php echo Text::_('FLEXI_FILEMAN_REMOVE_ASSOC_DELETE'); ?></h2>
	<p><?php echo Text::_('FLEXI_FILEMAN_REMOVE_ASSOC_DELETE_CONFIRM'); ?></p>
	<ul>
		<?php foreach ($confirmationFiles as $file) : ?>
			<li><?php echo $this->escape($file->filename_original ?: $file->filename); ?>
				<input type="hidden" name="cid[]" value="<?php echo (int) $file->id; ?>">
			</li>
		<?php endforeach; ?>
	</ul>
	<input type="hidden" name="option" value="com_flexicontent">
	<input type="hidden" name="task" value="filemanager.removeunlink">
	<input type="hidden" name="return" value="<?php echo base64_encode((string) $back); ?>">
	<?php echo HTMLHelper::_('form.token'); ?>
	<a class="btn btn-secondary" href="<?php echo $this->escape((string) $back); ?>"><?php echo Text::_('JCANCEL'); ?></a>
	<button class="btn btn-danger" type="submit"><?php echo Text::_('FLEXI_FILEMAN_REMOVE_ASSOC_DELETE'); ?></button>
</form>
