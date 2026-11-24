<?php
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

$file = $displayData['file'];
$stored = (string) $file->filename;
$original = $file->filename_original ?: $stored;
$name = $displayData['header'] === 1 ? $stored : $original;
$title = $displayData['showTitle'] && $file->altname !== $stored && $file->altname !== $original ? $file->altname : '';
$ext = strtoupper($file->ext ?: pathinfo($original, PATHINFO_EXTENSION));
$size = !empty($file->size) ? HTMLHelper::_('number.bytes', $file->size, 'auto', 1) : '';
$secure = !empty($file->secure) && empty($file->url);
$escape = static fn ($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
?>
<div class="fc-asset-summary" data-header="<?php echo $displayData['header']; ?>" data-show-title="<?php echo (int) $displayData['showTitle']; ?>"<?php echo $name === '' ? ' hidden' : ''; ?>>
	<span class="fc-asset-tile" aria-hidden="true"><?php echo $escape($ext); ?></span>
	<div class="fc-asset-details">
		<span class="fc-asset-name"<?php if ($displayData['header'] === 3 && $stored !== $original) : ?> title="<?php echo $escape($stored); ?>"<?php endif; ?>><?php echo $escape($name); ?></span>
		<span class="fc-asset-title"><?php echo $escape($title); ?></span>
		<div class="fc-asset-meta">
			<span class="fc-asset-size"><?php echo $escape($size); ?></span>
			<span class="fc-asset-type"><?php echo $escape($ext); ?></span>
			<span class="fc-asset-secure"<?php echo $secure ? '' : ' hidden'; ?>><?php echo Text::_('FLEXI_SECURE'); ?></span>
		</div>
	</div>
</div>
