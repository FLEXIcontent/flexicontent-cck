<?php // no direct access
defined('_JEXEC') or die('Restricted access');
if ($add_tooltips)
	JHTML::_('behavior.tooltip');
?>

<?php
foreach ($ordering as $ord) :
	if (isset($list[$ord]['featured'])) :
?>
<ul class="mod_flexicontent<?php echo $params->get('moduleclass_sfx'); ?> mod_flexicontent_featured">
<?php foreach ($list[$ord]['featured'] as $item) : ?>
	<li>
		<?php if ($add_tooltips) : ?>
		<a href="<?php echo $item->link; ?>" class="hasTip" title="<?php echo htmlspecialchars($item->fulltitle, ENT_COMPAT, "UTF-8").'::'.htmlspecialchars($item->text, ENT_COMPAT, "UTF-8"); ?>">
			<?php echo $item->title; ?>
		</a>
		<?php else : ?>
		<a href="<?php echo $item->link; ?>"><?php echo $item->title; ?></a>
		<?php endif; ?>
	</li>
<?php endforeach; ?>
</ul>
<?php
	endif;
	if (isset($list[$ord]['standard'])) :
?>
<ul class="mod_flexicontent<?php echo $params->get('moduleclass_sfx'); ?> mod_flexicontent_standard">
<?php foreach ($list[$ord]['standard'] as $item) : ?>
	<li>
		<?php if ($add_tooltips) : ?>
		<a href="<?php echo $item->link; ?>" class="hasTip" title="<?php echo htmlspecialchars($item->fulltitle, ENT_COMPAT, "UTF-8").'::'.htmlspecialchars($item->text, ENT_COMPAT, "UTF-8"); ?>">
			<?php echo $item->title; ?>
		</a>
		<?php else : ?>
		<a href="<?php echo $item->link; ?>"><?php echo $item->title; ?></a>
		<?php endif; ?>
	</li>
<?php endforeach; ?>
</ul>
<?php
	endif;
endforeach;
?>
