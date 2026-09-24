<?php
/**
 * @package FLEXIcontent
 * @copyright (C) 2009-2026 Emmanuel Danan, Georgios Papadakis, Yannick Berges
 * @license GNU/GPL v2
 */

defined( '_JEXEC' ) or die( 'Restricted access' );

// GrapesJS Layout Builder category layout: the whole builder canvas is ONE item "card"
// (see the backend template editor), replicated in a uniform grid (see the replication
// grid parameters of category.xml) for every item of the category loop. Each card is
// rendered through flexicontent_html::renderBuilderLayout, which resolves per item the
// {flexi_field:..} / {flexi_link:..} / {flexi_item:..} placeholders and makes element IDs
// unique ({{fc-item-id}}). The builder assets (CSS/JS) are loaded once by that renderer
// (guarded per layout/css id).

if (!$this->items)
{
	if ($this->getModel()->getState('limit'))
	{
		echo '<div class="noitems">' . \Joomla\CMS\Language\Text::_('FLEXI_NO_ITEMS_FOUND') . '</div>';
	}
	return;
}

$ilayout          = $this->params->get('clayout', 'grapesjs');
$builder_enabled  = (int) $this->params->get('builder_enabled', 1);
$builder_layout   = trim($this->params->get('builder_layout1_html', ''));

$fcl_cols_desktop = max(1, (int) $this->params->get('cat_builder_cols_desktop', 3));
$fcl_cols_tablet  = max(1, (int) $this->params->get('cat_builder_cols_tablet', 2));
$fcl_cols_mobile  = max(1, (int) $this->params->get('cat_builder_cols_mobile', 1));
$fcl_gap          = trim($this->params->get('cat_builder_gap', '32px'));
?>
<style>
.fc-builder-cat.fc-items-block{display:grid;grid-template-columns:repeat(var(--fcl-cols-d),1fr);gap:var(--fcl-gap);align-items:start}
.fc-builder-cat .fc-item-builder{min-width:0}
@media (max-width:991px){.fc-builder-cat.fc-items-block{grid-template-columns:repeat(var(--fcl-cols-t),1fr)}}
@media (max-width:575px){.fc-builder-cat.fc-items-block{grid-template-columns:repeat(var(--fcl-cols-m),1fr)}}
</style>
<div class="fc-builder-cat fc-items-block" style="--fcl-cols-d:<?php echo (int)$fcl_cols_desktop; ?>;--fcl-cols-t:<?php echo (int)$fcl_cols_tablet; ?>;--fcl-cols-m:<?php echo (int)$fcl_cols_mobile; ?>;--fcl-gap:<?php echo htmlspecialchars($fcl_gap, ENT_QUOTES, 'UTF-8'); ?>">

<?php if ($builder_enabled && $builder_layout !== '') : foreach ($this->items as $item) : ?>
	<div class="fc-item-builder">
		<?php echo flexicontent_html::renderBuilderLayout(
			$builder_layout,
			$this->params,
			$item,
			array(
				'layout_name'   => 'builder_layout1',
				'css_prefix'    => '#flexicontent',
				'css_id'        => $ilayout . 'cat',
				'template_name' => $ilayout . 'cat',
				'view'          => 'category',
			)
		); ?>
	</div>
<?php endforeach; else : foreach ($this->items as $item) : ?>
	<div class="fc-item-builder">
		<p class="fc_builder_fallback_title"><a href="<?php echo \Joomla\CMS\Router\Route::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug, 0, $item)); ?>"><?php echo $item->title; ?></a></p>
	</div>
<?php endforeach; endif; ?>

</div>