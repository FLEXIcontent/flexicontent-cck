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
$builder_layout   = trim($this->params->get('builder_layout1_html', ''));

$fcl_min_card = trim($this->params->get('cat_builder_min_card', '280px'));
$fcl_gap_x    = trim($this->params->get('cat_builder_gap_x', '32px'));
$fcl_gap_y    = trim($this->params->get('cat_builder_gap_y', '32px'));
$fcl_rows_natural = ((int) $this->params->get('cat_builder_rows', 1) === 0);
// auto-fit: empty tracks collapse and cards stretch to fill the whole row;
// auto-fill: empty tracks are kept so cards keep their size (the last row may leave space)
$fcl_fit = trim($this->params->get('cat_builder_fit', 'auto-fill'));
$fcl_fit = in_array($fcl_fit, array('auto-fit', 'auto-fill'), true) ? $fcl_fit : 'auto-fit';

// "Equal card heights" (JYES, default): the whole content of each cell must stretch to the
// row height. Bootstrap has no single utility that propagates height:100% through arbitrary
// wrappers, so we mirror the BS5 flex pattern: h-100 + d-flex flex-column on the cell and an
// inner flex-fill wrapper (scoped to the card shell); the remaining builder wrappers
// (.container / .fc-empty-box) get height:100% via the scoped CSS below. JNO keeps natural
// heights (align-items:start).
$fcl_cell_cls   = $fcl_rows_natural ? '' : ' h-100 d-flex flex-column';
$fcl_inner_cls  = $fcl_rows_natural ? 'fc-item-inner' : 'fc-item-inner h-100 flex-fill d-flex flex-column';
?>
<style>
.fc-builder-cat.fc-items-block{display:grid;grid-template-columns:repeat(<?php echo $fcl_fit; ?>,minmax(min(var(--fcl-min-card,280px),100%),1fr));column-gap:var(--fcl-gap-x,32px);row-gap:var(--fcl-gap-y,32px)}
.fc-builder-cat.fc-items-block.fc-builder-rows-natural{align-items:start}
.fc-builder-cat .fc-item-builder{min-width:0}
/* Equal-height: stretch the layout root(s) then their direct builder wrappers. Child
 * combinators keep the rule scoped to the card shell and out of nested .container inside
 * the card body. */
.fc-builder-cat:not(.fc-builder-rows-natural) .fc-item-inner > *{flex:1 1 auto;height:100%}
.fc-builder-cat:not(.fc-builder-rows-natural) .fc-item-inner > * > .fc-empty-box,
.fc-builder-cat:not(.fc-builder-rows-natural) .fc-item-inner > * > .container > .fc-empty-box{height:100%}
</style>
<div class="fc-builder-cat fc-items-block<?php echo $fcl_rows_natural ? ' fc-builder-rows-natural' : ''; ?>" style="--fcl-min-card:<?php echo htmlspecialchars($fcl_min_card, ENT_QUOTES, 'UTF-8'); ?>;--fcl-gap-x:<?php echo htmlspecialchars($fcl_gap_x, ENT_QUOTES, 'UTF-8'); ?>;--fcl-gap-y:<?php echo htmlspecialchars($fcl_gap_y, ENT_QUOTES, 'UTF-8'); ?>">

<?php if ($builder_layout !== '') : foreach ($this->items as $item) : ?>
	<div class="fc-item-builder<?php echo $fcl_cell_cls; ?>">
		<div class="<?php echo $fcl_inner_cls; ?>">
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
	</div>
<?php endforeach; else : foreach ($this->items as $item) : ?>
	<div class="fc-item-builder<?php echo $fcl_cell_cls; ?>">
		<div class="<?php echo $fcl_inner_cls; ?>">
		<p class="fc_builder_fallback_title"><a href="<?php echo \Joomla\CMS\Router\Route::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug, 0, $item)); ?>"><?php echo $item->title; ?></a></p>
		</div>
	</div>
<?php endforeach; endif; ?>

</div>