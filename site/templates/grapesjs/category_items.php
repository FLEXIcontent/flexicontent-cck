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

$fcl_min_card = trim($this->params->get('cat_builder_min_card', '280px'));
$fcl_gap_x    = trim($this->params->get('cat_builder_gap_x', '32px'));
$fcl_gap_y    = trim($this->params->get('cat_builder_gap_y', '32px'));
$fcl_rows_natural = ((int) $this->params->get('cat_builder_rows', 1) === 0);
// auto-fit: empty tracks collapse and cards stretch to fill the whole row;
// auto-fill: empty tracks are kept so cards keep their size (the last row may leave space)
$fcl_fit = trim($this->params->get('cat_builder_fit', 'auto-fill'));
$fcl_fit = in_array($fcl_fit, array('auto-fit', 'auto-fill'), true) ? $fcl_fit : 'auto-fit';
?>
<style>
.fc-builder-cat.fc-items-block{display:grid;grid-template-columns:repeat(<?php echo $fcl_fit; ?>,minmax(min(var(--fcl-min-card,280px),100%),1fr));column-gap:var(--fcl-gap-x,32px);row-gap:var(--fcl-gap-y,32px)}
.fc-builder-cat.fc-items-block.fc-builder-rows-natural{align-items:start}
.fc-builder-cat .fc-item-builder{min-width:0}
</style>
<div class="fc-builder-cat fc-items-block<?php echo $fcl_rows_natural ? ' fc-builder-rows-natural' : ''; ?>" style="--fcl-min-card:<?php echo htmlspecialchars($fcl_min_card, ENT_QUOTES, 'UTF-8'); ?>;--fcl-gap-x:<?php echo htmlspecialchars($fcl_gap_x, ENT_QUOTES, 'UTF-8'); ?>;--fcl-gap-y:<?php echo htmlspecialchars($fcl_gap_y, ENT_QUOTES, 'UTF-8'); ?>">

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