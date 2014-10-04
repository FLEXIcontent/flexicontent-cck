<?php // no direct access
defined('_JEXEC') or die('Restricted access'); 

?>
<ul id="flexicloud" class="mod_flexitagcloud<?php echo $params->get('moduleclass_sfx'); ?>">
<?php foreach ($list as $item) : ?>
	<li>
		<?php if (!$params->get('seo_mode', 1)) : ?>
		<span><?php echo $item->screenreader.' '; ?></span>
		<?php endif; ?>
		<a href="<?php echo $item->link; ?>" class="tag<?php echo $item->size; ?>"><?php echo $item->name; ?></a>
	</li>
<?php endforeach; ?>
</ul>