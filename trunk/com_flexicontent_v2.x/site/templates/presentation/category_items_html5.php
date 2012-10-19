<?php
/**
 * HTML5 Template
 * @version 1.5 stable $Id: category_items_html5.php 0001 2012-09-23 14:00:28Z Rehne $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * FLEXIcontent is a derivative work of the excellent QuickFAQ component
 * @copyright (C) 2008 Christoph Lukes
 * see www.schlu.net for more information
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

defined( '_JEXEC' ) or die( 'Restricted access' );
// first define the template name
$tmpl = $this->tmpl;

JFactory::getDocument()->addScript( JURI::base().'components/com_flexicontent/assets/js/tmpl-common.js');
JFactory::getDocument()->addScript( JURI::base().'components/com_flexicontent/assets/js/tabber-minimized.js');
JFactory::getDocument()->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/tabber.css');
?>

<!--script type="text/javascript">
</script-->

<?php if ((($this->params->get('use_filters', 0)) && $this->filters) || ($this->params->get('use_search')) || ($this->params->get('show_alpha', 1))) : ?>
<aside class="group">
	<form action="<?php echo htmlentities($this->action); ?>" method="POST" id="adminForm" onsubmit="" class="group well">

	<?php if ( JRequest::getVar('clayout') == $this->params->get('clayout', 'blog') ) :?>
	<input type="hidden" name="clayout" value="<?php echo JRequest::getVar('clayout'); ?>" />
	<?php endif; ?>

	<?php if ((($this->params->get('use_filters', 0)) && $this->filters) || ($this->params->get('use_search'))) : /* BOF filter ans search block */ ?>
	<div id="fc_filter" class="floattext control-group group">
		<?php if ($this->params->get('use_search')) : /* BOF search */ ?>
		<div class="fc_fleft">
			<input type="text" name="filter" id="filter" value="<?php echo $this->lists['filter'];?>" class="text_area input-medium search-query" />
			<?php if ( $this->params->get('show_filter_labels', 0) && $this->params->get('use_filters', 0) && $this->filters ) : ?>
				<br />
			<?php endif; ?>
			<button class="fc_button btn" onclick="var form=document.getElementById('adminForm');                               adminFormPrepare(form);"><i class="icon-search"></i><?php echo JText::_( 'FLEXI_GO' ); ?></button>
			<button class="fc_button btn" onclick="var form=document.getElementById('adminForm'); adminFormClearFilters(form);  adminFormPrepare(form);"><i class="icon-refresh"></i><?php echo JText::_( 'FLEXI_RESET' ); ?></button>
		</div>
		<?php endif; /* EOF search */ ?>
		<?php if ($this->params->get('use_filters', 0) && $this->filters) : /* BOF filter */ ?>
	
		<!--div class="fc_fright"-->
		<?php
		foreach ($this->filters as $filt) :
			if (empty($filt->html)) continue;
			// Add form preparation
			if ( preg_match('/onchange[ ]*=[ ]*([\'"])/i', $filt->html, $matches) ) {
				$filt->html = preg_replace('/onchange[ ]*=[ ]*([\'"])/i', 'onchange=${1}adminFormPrepare(document.getElementById(\'adminForm\'));', $filt->html);
			} else {
				$filt->html = preg_replace('/<(select|input)/i', '<${1} onchange="adminFormPrepare(document.getElementById(\'adminForm\'));"', $filt->html);
			}
		?>
			<span class="filter" style="white-space: nowrap;">
			
				<?php if ( $this->params->get('show_filter_labels', 0) ) : ?>
					<span class="filter_label">
					<?php echo $filt->label; ?>
					</span>
				<?php endif; ?>
			
				<span class="filter_field">
				<?php echo $filt->html; ?>
				</span>
			
			</span>
		<?php endforeach; ?>
	
		<?php if (!$this->params->get('use_search')) : ?>
			<button class="btn" onclick="var form=document.getElementById('adminForm'); adminFormClearFilters(form);  adminFormPrepare(form);"><i class="icon-refresh"></i><?php echo JText::_( 'FLEXI_RESET' ); ?></button>
		<?php endif; ?>
		<!--/div-->

		<?php endif; /* EOF filter */ ?>
	</div>
	<?php endif; /* EOF filter ans serch block */ ?>
	<?php
	if ($this->params->get('show_alpha', 1)) :
		echo $this->loadTemplate('alpha_html5');
	endif;
	?>
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="filter_order" value="<?php echo $this->lists['filter_order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="" />
	<input type="hidden" name="view" value="category" />
	<input type="hidden" name="letter" value="<?php echo JRequest::getVar('letter');?>" id="alpha_index" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="id" value="<?php echo $this->category->id; ?>" />
	<input type="hidden" name="cid" value="<?php echo $this->category->id; ?>" />
</form>
<?php endif; ?>

<?php
if ($this->items) :
	// routine to determine all used columns for this table
	$layout = $this->params->get('clayout', 'default');
	$fbypos		= flexicontent_tmpl::getFieldsByPositions($layout, 'category');
	$columns = array();
	foreach ($this->items as $item) :
		if (isset($item->positions['table'])) :
			foreach ($fbypos['table']->fields as $f) :
				if (!in_array($f, $columns)) :
					$columns[$f] = @$item->fields[$f]->label;
				endif;
			endforeach;
		endif;
	endforeach;
?>

	<?php $items = & $this->items; ?>

	<?php
	if (!$this->params->get('show_title', 1) && $this->params->get('limit', 0) && !count($columns)) :
		echo '<span style="font-weight:bold; color:red;">'.JText::_('FLEXI_TPL_NO_COLUMNS_SELECT_FORCING_DISPLAY_ITEM_TITLE').'</span>';
		$this->params->set('show_title', 1);
	endif;
	?>
	
	<?php if ( (!$this->params->get('show_title', 1) || !count($columns)) && !$this->params->get('show_item_total', 1)) : ?>
        </aside>
    <?php endif; ?>

	<?php if ($this->params->get('show_title', 1) || count($columns)) : ?>
	
        <!-- BOF items total-->
        <?php if ($this->params->get('show_item_total', 1)) : ?>
        <div id="item_total" class="item_total group">
            <?php	//echo $this->pageNav->getResultsCounter(); // Alternative way of displaying total (via joomla pagination class) ?>
            <?php echo $this->resultsCounter; // custom Results Counter ?>
        </div>
        </aside>
        <?php endif; ?>
        <!-- BOF items total-->

		<?php foreach ($items as $item) : ?>
        
        <?php if ($this->params->get('show_title', 1) || count($columns)) : ?>
        <article class="group"> 
        <?php endif; ?>
        
            <!-- BOF beforeDisplayContent -->
            <?php if ($item->event->beforeDisplayContent) : ?>
                <div class="fc_beforeDisplayContent group">
                    <?php echo $item->event->beforeDisplayContent; ?>
                </div>
            <?php endif; ?>
            <!-- EOF beforeDisplayContent -->
    
            <!-- BOF buttons -->
            <?php
            $pdfbutton = flexicontent_html::pdfbutton( $item, $this->params );
            $mailbutton = flexicontent_html::mailbutton( 'items', $this->params, null , $item->slug );
            $printbutton = flexicontent_html::printbutton( $this->print_link, $this->params );
            $editbutton = flexicontent_html::editbutton( $item, $this->params );
            if ($pdfbutton || $mailbutton || $printbutton || $editbutton) {
            ?>
            <p class="buttons">
            <?php echo $pdfbutton; ?>
            <?php echo $mailbutton; ?>
            <?php echo $printbutton; ?>
            <?php echo $editbutton; ?>
            </p>
            <?php } ?>
            <!-- EOF buttons -->
            
            <!-- BOF item title -->
            <?php if ($this->params->get('show_title', 1)) : ?>
            <header class="group"> 	
            <h1 class="contentheading"><span class="fc_item_title">
                <?php
                if ( mb_strlen($item->title, 'utf-8') > $this->params->get('title_cut_text',200) ) :
                    echo mb_substr ($item->title, 0, $this->params->get('title_cut_text',200), 'utf-8') . ' ...';
                else :
                    echo $item->title;
                endif;
                ?>
            </span></h1>
            <?php endif; ?>
            <!-- EOF item title -->
            
            <!-- BOF afterDisplayTitle -->
            <?php if ($item->event->afterDisplayTitle) : ?>
            <div class="fc_afterDisplayTitle group">
                <?php echo $item->event->afterDisplayTitle; ?>
            </div>
            <?php endif; ?>
            <!-- EOF afterDisplayTitle -->
            
            <!-- BOF subtitle1 block -->
            <?php if (isset($item->positions['subtitle1'])) : ?>
            <div class="flexi lineinfo subtitle1 group">
                <?php foreach ($item->positions['subtitle1'] as $field) : ?>
                <div class="flexi element">
                    <?php if ($field->label) : ?>
                    <span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
                    <?php endif; ?>
                    <div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <!-- EOF subtitle1 block -->
            
            <!-- BOF subtitle2 block -->
            <?php if (isset($item->positions['subtitle2'])) : ?>
            <div class="flexi lineinfo subtitle2 group">
                <?php foreach ($item->positions['subtitle2'] as $field) : ?>
                <div class="flexi element">
                    <?php if ($field->label) : ?>
                    <span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
                    <?php endif; ?>
                    <div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <!-- EOF subtitle2 block -->
            
            <!-- BOF subtitle3 block -->
            <?php if (isset($item->positions['subtitle3'])) : ?>
            <div class="flexi lineinfo subtitle3 group">
                <?php foreach ($item->positions['subtitle3'] as $field) : ?>
                <div class="flexi element">
                    <?php if ($field->label) : ?>
                    <span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
                    <?php endif; ?>
                    <div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <!-- EOF subtitle3 block -->
        
            <?php if ($this->params->get('show_title', 1)) : ?>
            </header>
            <?php endif; ?>
            
            <?php
            $tabcount = 6;
            
            // Find if at least one tabbed position is used
            for ($tc=1; $tc<=$tabcount; $tc++) $createtabs = @$createtabs ||  isset($this->item->positions['subtitle_tab'.$tc]);
            
            if (@$createtabs) /* BOF fctabber */  :
                echo '<div class="fctabber group">'."\n";
                
                for ($tc=1; $tc<=$tabcount; $tc++) :
                    $tabpos_name  = 'subtitle_tab'.$tc;
                    $tabpos_label = JText::_($this->params->get('subtitle_tab'.$tc.'_label', $tabpos_name));
                    if (isset($this->item->positions[$tabpos_name])):
                    /*BOF subtitle_tabN block*/ ?>
                
                    <section class="tabbertab"><!-- tab start -->
                        <header>
                            <h3><?php echo $tabpos_name; ?></h3><!-- tab title -->
                        </header>
                        <div class="flexi lineinfo <?php echo $tabpos_name; ?>">
                            <?php foreach ($this->item->positions[$tabpos_name] as $field) : ?>
                            <div class="flexi element">
                                <?php if ($field->label) : ?>
                                <span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
                                <?php endif; ?>
                                <div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                    </section><!-- tab end -->
                     
                    <?php endif; /*EOF subtitle_tabN block*/ ?>
                
                <?php endfor; ?>
                    
            <?php
                echo '</div>'."\n";
            endif; /* BOF fctabber */
            ?>
        
            <?php if ((isset($item->positions['image'])) || (isset($item->positions['top']))) : ?>
            <aside class="flexi topblock group row">  <!-- NOTE: image block is inside top block ... -->
            
            <!-- BOF image block -->
                <?php if (isset($item->positions['image'])) : ?>
                    <?php foreach ($item->positions['image'] as $field) : ?>
                	<div class="flexi image field_<?php echo $field->name; ?> span4">
                   		<?php echo $field->display; ?>
                    	<div class="clear"></div>
                	</div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <!-- EOF image block -->
            
            <!-- BOF top block -->
                <?php if (isset($item->positions['top'])) : ?>
				<?php 
                $classTopColsspan = ''; // bootstrap span
                if ($this->params->get('top_cols', 'two') == 'one') :
                    $classTopColsspan = 'span12';
                else :
                    $classTopColsspan = 'span6';
                endif;
                ?>
                <div class="flexi infoblock <?php echo $this->params->get('top_cols', 'two'); ?>cols span8">
                    <ul class="flexi row">
                        <?php foreach ($item->positions['top'] as $field) : ?>
                        <li class="flexi <?php echo $classTopColsspan; ?>">
                            <div>
                                <?php if ($field->label) : ?>
                                <div class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></div>
                                <?php endif; ?>
                                <div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            <!-- EOF top block -->
            
            </aside>
            <?php endif; ?>
            
            <!-- BOF TOC -->
            <?php if (isset($item->toc)) : ?>
                <?php echo $item->toc; ?>
            <?php endif; ?>
            <!-- EOF TOC -->
            
            <!-- BOF description -->
            <?php if (isset($item->positions['description'])) : ?>
            <div class="description group">
                <?php foreach ($item->positions['description'] as $field) : ?>
                    <?php if ($field->label) : ?>
                	<div class="desc-title"><?php echo $field->label; ?></div>
                    <?php endif; ?>
                <div class="desc-content"><?php echo $field->display; ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <!-- EOF description -->
            
            <?php
            $tabcount = 6;
            
            // Find if at least one tabbed position is used
            for ($tc=1; $tc<=$tabcount; $tc++) $createtabs = @$createtabs ||  isset($this->item->positions['bottom_tab'.$tc]);
            
            if (@$createtabs) : /* BOF fctabber */
                echo '	<div class="fctabber group">'."\n";
                
                for ($tc=1; $tc<=$tabcount; $tc++) :
                    $tabpos_name  = 'bottom_tab'.$tc;
                    $tabpos_label = JText::_($this->params->get('bottom_tab'.$tc.'_label', $tabpos_name));
                    if (isset($this->item->positions[$tabpos_name])):
                    /*BOF bottom_tabNblock*/ ?>
                
                    <section class="tabbertab"><!-- tab start -->
                        
                        <header>
                        <h3><?php echo $tabpos_name; ?></h3><!-- tab title -->
                        </header>
                        
                        <div class="flexi lineinfo <?php echo $tabpos_name; ?>">
                            <?php foreach ($this->item->positions[$tabpos_name] as $field) : ?>
                            <div class="flexi element">
                                <?php if ($field->label) : ?>
                                <span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
                                <?php endif; ?>
                                <div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                    </section><!-- tab end -->
                     
                    <?php endif; /*BOF bottom_tabN block*/ ?>
                
                <?php endfor; ?>
                    
            <?php
                echo '</div>'."\n";
            endif; /* BOF fctabber */
            ?>
        
            <?php if ( ($this->params->get('show_title', 1) || count($columns)) && (isset($item->positions['bottom'])) || ( $this->params->get('show_readmore', 1) && strlen(trim($item->fulltext)) >= 1 ) || ($item->event->afterDisplayContent) ) : ?>
            <footer class="group">
            <?php endif; ?>
            
            <!-- BOF bottom block -->
            <?php if (isset($item->positions['bottom'])) : ?>
			<?php 
            $classBottomColsspan = ''; // bootstrap span
            if ($this->params->get('top_cols', 'two') == 'one') :
                $classBottomColsspan = 'span12';
            else :
                $classBottomColsspan = 'span6';
            endif;
            ?>
            <div class="flexi infoblock <?php echo $this->params->get('bottom_cols', 'two'); ?>cols group">
                <ul class="flexi row">
                    <?php foreach ($item->positions['bottom'] as $field) : ?>
                    <li class="flexi <?php echo $classBottomColsspan; ?>">
                        <div>
                            <?php if ($field->label) : ?>
                            <div class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></div>
                            <?php endif; ?>
                            <div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            <!-- EOF bottom block -->
    
            <?php if (
                ( $this->params->get('show_readmore', 1) && strlen(trim($item->fulltext)) >= 1 )
                ||  $this->params->get('lead_strip_html', 1) == 1 /* option 2, strip-cuts and option 1 also forces read more  */
            ) : ?>
            <span class="readmore group">
                <a href="<?php echo JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug)); ?>" class="readon">
                <?php
                if ($item->params->get('readmore')) :
                    echo ' ' . $item->params->get('readmore');
                else :
                    echo ' ' . JText::sprintf('FLEXI_READ_MORE', $item->title);
                endif;
                ?>
                </a>
            </span>
            <?php endif; ?>
                    
            <!-- BOF afterDisplayContent -->
            <?php if ($item->event->afterDisplayContent) : ?>
                <div class="afterDisplayContent group">
                    <?php echo $item->event->afterDisplayContent; ?>
                </div>
            <?php endif; ?>
            <!-- EOF afterDisplayContent -->
            
            <?php if ( ($this->params->get('show_title', 1) || count($columns)) && (isset($item->positions['bottom'])) || (($this->params->get('show_readmore', 1) && strlen(trim($item->fulltext)) >= 1) ||  $this->params->get('lead_strip_html', 1) == 1
            ) || ($item->event->afterDisplayContent) ) : ?>
            </footer>
            <?php endif; ?>
            
			<?php if ($this->params->get('show_title', 1) || count($columns)) : ?>
            <article class="group"> 
            <?php endif; ?>
                
            <div class="fc_item_seperator"></div>
    
		<?php endforeach; ?>
	<?php endif; ?>            
	
<?php elseif ($this->getModel()->getState('limit')) : // Check case of creating a category view without items ?>
	<div class="noitems group"><?php echo JText::_( 'FLEXI_NO_ITEMS_CAT' ); ?></div>
<?php endif; ?>

