<?php
/**
 * @version 1.5 stable $Id$
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
?>

<?php if ((($this->params->get('use_filters', 0)) && $this->filters) || ($this->params->get('use_search')) || ($this->params->get('show_alpha', 1))) : ?>
<form action="<?php echo $this->action; ?>" method="post" id="adminForm">
<?php if ((($this->params->get('use_filters', 0)) && $this->filters) || ($this->params->get('use_search'))) : ?>
<div id="fc_filter" class="floattext">
	<?php if ($this->params->get('use_search')) : ?>
	<div class="fc_fleft">
		<input type="text" name="filter" id="filter" value="<?php echo $this->lists['filter'];?>" class="text_area" onchange="document.getElementById('adminForm').submit();" />
		<button onclick="document.getElementById('adminForm').submit();"><?php echo JText::_( 'FLEXI_GO' ); ?></button>
		<button onclick="document.getElementById('filter').value='';document.getElementById('adminForm').submit();"><?php echo JText::_( 'FLEXI_RESET' ); ?></button>
	</div>
	<?php endif; ?>
	<?php if (($this->params->get('use_filters', 0)) && $this->filters) : ?>
	<div class="fc_fright">
	<?php
/*
	echo '<span class="filter">';
	echo 'Saison: ' . $this->filters['field24']->html;
	echo '</span>';
*/	
	foreach ($this->filters as $filt) :
		echo '<span class="filter">';
		echo @$filt->html;
		echo '</span>';
	endforeach;
	?>
	</div>
	<?php endif; ?>
</div>
<?php endif; ?>
<?php
if ($this->params->get('show_alpha', 1)) :
	echo $this->loadTemplate('alpha');
endif;
?>
<input type="hidden" name="option" value="com_flexicontent" />
<input type="hidden" name="filter_order" value="<?php echo $this->lists['filter_order']; ?>" />
<input type="hidden" name="filter_order_Dir" value="" />
<input type="hidden" name="view" value="category" />
<input type="hidden" name="letter" value="" id="alpha_index" />
<input type="hidden" name="task" value="" />
<input type="hidden" name="id" value="<?php echo $this->category->id; ?>" />
</form>
<?php endif; ?>
<?php
$items	= $this->items;
$count 	= count($items);
if ($count) :
?>
<div class="content">

		<!-- BOF items total-->
		<?php if ($this->params->get('show_item_total', 1)) : ?>
		<div id="item_total" class="item_total">
			<?php echo JText::sprintf( 'FLEXI_ITEMS_TOTAL', count($this->items));?>
		</div>
		<?php endif; ?>
		<!-- BOF items total-->

<?php
$leadnum		= $this->params->get('lead_num', 2);
$leadnum		= ($leadnum >= $count) ? $count : $leadnum;
if ($this->limitstart == 0) :
?>
	<ul class="leadingblock">
		<?php for ($i=0; $i<$leadnum; $i++) : 
		?>
		<li>
			<div style="overflow: hidden;">
    			<?php if ($this->params->get('show_title', 1)) : ?>
    			<h2 class="contentheading">
   				<?php if ($this->params->get('link_titles', 0)) : ?>
    			<a href="<?php echo JRoute::_(FlexicontentHelperRoute::getItemRoute($items[$i]->slug, $items[$i]->categoryslug)); ?>"><?php echo $items[$i]->title; ?></a>
    			<?php
    			else :
   				echo $items[$i]->title;
    			endif;
    			?>
    			</h2>
    			<?php endif; ?>
    			<?php 
    			if ($this->params->get('lead_use_image', 1)) :
    				if ($this->params->get('lead_image')) :
    					FlexicontentFields::getFieldDisplay($items[$i], $this->params->get('lead_image'), $values=null, $method='display');
							if (isset($items[$i]->fields[$this->params->get('lead_image')]->value[0])) :
								$dir{$i}	= $items[$i]->fields[$this->params->get('lead_image')]->parameters->get('dir');
								$value{$i} 	= unserialize($items[$i]->fields[$this->params->get('lead_image')]->value[0]);
								$image{$i}	= $value{$i}['originalname'];
								$scr{$i}	= $dir{$i}.($this->params->get('lead_image_size') ? '/'.$this->params->get('lead_image_size').'_' : '/l_').$image{$i};
							else :
								$scr{$i}	= '';
							endif;
							$src = $scr{$i};
    				else :
    					$src = flexicontent_html::extractimagesrc($items[$i]);
    				endif;
    				$w		= '&w=' . $this->params->get('lead_width', 200);
   					$h		= '&h=' . $this->params->get('lead_height', 200);
    				$aoe	= '&aoe=1';
    				$q		= '&q=95';
    				$zc		= $this->params->get('lead_method') ? '&zc=' . $this->params->get('lead_method') : '';
    				$conf	= $w . $h . $aoe . $q . $zc;
    				
    				if (!$this->params->get('lead_image_size')) :
    					$thumb = JURI::base().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.JURI::base(true).'/'.$src.$conf;
    				else :
    					$thumb = $src;
    				endif;
    				
    				if ($src) : // case source
    			?>
				<div class="image<?php echo $this->params->get('lead_position') ? ' right' : ' left'; ?>">
    				<?php if ($this->params->get('lead_link_image', 1)) : ?>
    				<a href="<?php echo JRoute::_(FlexicontentHelperRoute::getItemRoute($items[$i]->slug, $items[$i]->categoryslug)); ?>" class="hasTip" title="<?php echo JText::_( 'FLEXI_READ_MORE_ABOUT' ) . '::' . addslashes($items[$i]->title); ?>">
						<img src="<?php echo $thumb; ?>" alt="<?php echo addslashes($items[$i]->title); ?>" />
					</a>
					<?php else : ?>
					<img src="<?php echo $thumb; ?>" alt="<?php echo addslashes($items[$i]->title); ?>" />
					<?php endif; ?>
					<div class="clear"></div>
				</div>
    			<?php
	    			endif; // case source
    			endif; 
    			?>			
				
				<!-- BOF above-description-line1 block -->
				<?php if (isset($items[$i]->positions['above-description-line1'])) : ?>
				<div class="lineinfo line1">
					<?php foreach ($items[$i]->positions['above-description-line1'] as $field) : ?>
					<span class="element">
						<?php if ($field->label) : ?>
						<span class="label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
						<?php endif; ?>
						<span class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></span>
					</span>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<!-- EOF above-description-line1 block -->

				<!-- BOF above-description-nolabel-line1 block -->
				<?php if (isset($items[$i]->positions['above-description-line1-nolabel'])) : ?>
				<div class="lineinfo line1">
					<?php foreach ($items[$i]->positions['above-description-line1-nolabel'] as $field) : ?>
					<span class="element">
						<span class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></span>
					</span>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<!-- EOF above-description-nolabel-line1 block -->
				
				<!-- BOF above-description-line2 block -->
				<?php if (isset($items[$i]->positions['above-description-line2'])) : ?>
				<div class="lineinfo line2">
					<?php foreach ($items[$i]->positions['above-description-line2'] as $field) : ?>
					<span class="element">
						<?php if ($field->label) : ?>
						<span class="label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
						<?php endif; ?>
						<span class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></span>
					</span>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<!-- EOF above-description-line2 block -->
				
				<!-- BOF above-description-nolabel-line2 block -->
				<?php if (isset($items[$i]->positions['above-description-line2-nolabel'])) : ?>
				<div class="lineinfo line1">
					<?php foreach ($items[$i]->positions['above-description-line2-nolabel'] as $field) : ?>
					<span class="element">
						<span class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></span>
					</span>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<!-- EOF above-description-nolabel-line1 block -->
				
				<p>
				<?php
				if ($this->params->get('lead_strip_html', 1)) :
				echo flexicontent_html::striptagsandcut( $items[$i]->fields['text']->display, $this->params->get('lead_cut_text', 400) );
				else :
				echo $items[$i]->fields['text']->display;
				endif;
				?>
				</p>

				<!-- BOF under-description-line1 block -->
				<?php if (isset($items[$i]->positions['under-description-line1'])) : ?>
				<div class="lineinfo line3">
					<?php foreach ($items[$i]->positions['under-description-line1'] as $field) : ?>
					<span class="element">
						<?php if ($field->label) : ?>
						<span class="label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
						<?php endif; ?>
						<span class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></span>
					</span>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<!-- EOF under-description-line1 block -->
				
				<!-- BOF under-description-line1-nolabel block -->
				<?php if (isset($items[$i]->positions['under-description-line1-nolabel'])) : ?>
				<div class="lineinfo line3">
					<?php foreach ($items[$i]->positions['under-description-line1-nolabel'] as $field) : ?>
					<span class="element">
						<span class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></span>
					</span>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<!-- EOF under-description-line1-nolabel block -->

				<!-- BOF under-description-line2 block -->
				<?php if (isset($items[$i]->positions['under-description-line2'])) : ?>
				<div class="lineinfo line4">
					<?php foreach ($items[$i]->positions['under-description-line2'] as $field) : ?>
					<span class="element">
						<?php if ($field->label) : ?>
						<span class="label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
						<?php endif; ?>
						<span class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></span>
					</span>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<!-- EOF under-description-line2 block -->

				<!-- BOF under-description-line2-nolabel block -->
				<?php if (isset($items[$i]->positions['under-description-line2-nolabel'])) : ?>
				<div class="lineinfo line4">
					<?php foreach ($items[$i]->positions['under-description-line2-nolabel'] as $field) : ?>
					<span class="element">
						<span class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></span>
					</span>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<!-- EOF under-description-line2-nolabel block -->

    			<?php if (
    				( $this->params->get('show_readmore', 1) && strlen(trim($items[$i]->fulltext)) >= 1 )
    				||  $this->params->get('lead_strip_html', 1) == 1 /* option 2, strip-cuts and option 1 also forces read more  */
    			) : ?>
    			<span class="readmore">
    				<a href="<?php echo JRoute::_(FlexicontentHelperRoute::getItemRoute($items[$i]->slug, $items[$i]->categoryslug)); ?>" class="readon">
    				<?php
    				if ($items[$i]->params->get('readmore')) :
    					echo ' ' . $items[$i]->params->get('readmore');
    				else :
    					echo ' ' . JText::sprintf('FLEXI_READ_MORE', $items[$i]->title);
    				endif;
    				?>
    				</a>
    			</span>
    			<?php endif; ?>

			</div>
		</li>
		<?php endfor; ?>
	</ul>
<?php
	endif;
	if ($count > $leadnum || $this->limitstart != 0) :
    //added to intercept more columns (see also css changes)
    $classnum = '';
    if ($this->params->get('intro_cols', 2) == 1) :
       $classnum = 'one';
    elseif ($this->params->get('intro_cols', 2) == 2) :
       $classnum = 'two';
    elseif ($this->params->get('intro_cols', 2) == 3) :
       $classnum = 'three';
    elseif ($this->params->get('intro_cols', 2) == 4) :
       $classnum = 'four';
    endif;
?>
	<ul class="introblock <?php echo $classnum; ?>">	
		<?php for ($i=($this->limitstart == 0 ? $leadnum : 0 ); $i<$count; $i++) : ?>
		<li class="<?php echo (($this->limitstart == 0) ? ($i+$leadnum)%2 : $i%2) ? 'even' : 'odd'; ?>">
			<div style="overflow: hidden;">
    			<?php if ($this->params->get('show_title', 1)) : ?>
    			<h2 class="contentheading">
    				<?php if ($this->params->get('link_titles', 0)) : ?>
    				<a href="<?php echo JRoute::_(FlexicontentHelperRoute::getItemRoute($items[$i]->slug, $items[$i]->categoryslug)); ?>"><?php echo $items[$i]->title; ?></a>
    				<?php
    				else :
    				echo $items[$i]->title;
    				endif;
    				?>
    			</h2>
    			<?php endif; ?>
    			<?php 
    			if ($this->params->get('intro_use_image', 1)) :
    				if ($this->params->get('intro_image')) :
							FlexicontentFields::getFieldDisplay($items[$i], $this->params->get('intro_image'), $values=null, $method='display');
							if (isset($items[$i]->fields[$this->params->get('intro_image')]->value[0])) :
								$dir{$i}	= $items[$i]->fields[$this->params->get('intro_image')]->parameters->get('dir');
								$value{$i} 	= unserialize($items[$i]->fields[$this->params->get('intro_image')]->value[0]);
								$image{$i}	= $value{$i}['originalname'];
								$scr{$i}	= $dir{$i}.($this->params->get('intro_image_size') ? '/'.$this->params->get('intro_image_size').'_' : '/l_').$image{$i};
							else :
								$scr{$i}	= '';
							endif;
							$src = $scr{$i};
    				else :
    					$src = flexicontent_html::extractimagesrc($items[$i]);
    				endif;
    				$w		= '&w=' . $this->params->get('intro_width', 200);
   					$h		= '&h=' . $this->params->get('intro_height', 200);
    				$aoe	= '&aoe=1';
    				$q		= '&q=95';
    				$zc		= $this->params->get('intro_method') ? '&zc=' . $this->params->get('intro_method') : '';
    				$conf	= $w . $h . $aoe . $q . $zc;
    				
    				if (!$this->params->get('intro_image_size')) :
    					$thumb = JURI::base().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.JURI::base(true).'/'.$src.$conf;
    				else :
    					$thumb = $src;
    				endif;
    				
    				if ($src) : // case source
    			?>
				<div class="image<?php echo $this->params->get('intro_position') ? ' right' : ' left'; ?>">
    				<?php if ($this->params->get('intro_link_image', 1)) : ?>
    				<a href="<?php echo JRoute::_(FlexicontentHelperRoute::getItemRoute($items[$i]->slug, $items[$i]->categoryslug)); ?>" class="hasTip" title="<?php echo JText::_( 'FLEXI_READ_MORE_ABOUT' ) . '::' . addslashes($items[$i]->title); ?>">
						<img src="<?php echo $thumb; ?>" alt="<?php echo addslashes($items[$i]->title); ?>" />
					</a>
					<?php else : ?>
					<img src="<?php echo $thumb; ?>" alt="<?php echo addslashes($items[$i]->title); ?>" />
					<?php endif; ?>
					<div class="clear"></div>
				</div>
    			<?php
	    			endif; // case source
    			endif; 
    			?>			
				
				<!-- BOF above-description-line1 block -->
				<?php if (isset($items[$i]->positions['above-description-line1'])) : ?>
				<div class="lineinfo line1">
					<?php foreach ($items[$i]->positions['above-description-line1'] as $field) : ?>
					<span class="element">
						<?php if ($field->label) : ?>
						<span class="label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
						<?php endif; ?>
						<span class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></span>
					</span>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<!-- EOF above-description-line1 block -->

				<!-- BOF above-description-nolabel-line1 block -->
				<?php if (isset($items[$i]->positions['above-description-line1-nolabel'])) : ?>
				<div class="lineinfo line1">
					<?php foreach ($items[$i]->positions['above-description-line1-nolabel'] as $field) : ?>
					<span class="element">
						<span class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></span>
					</span>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<!-- EOF above-description-nolabel-line1 block -->
				
				<!-- BOF above-description-line2 block -->
				<?php if (isset($items[$i]->positions['above-description-line2'])) : ?>
				<div class="lineinfo line2">
					<?php foreach ($items[$i]->positions['above-description-line2'] as $field) : ?>
					<span class="element">
						<?php if ($field->label) : ?>
						<span class="label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
						<?php endif; ?>
						<span class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></span>
					</span>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<!-- EOF above-description-line2 block -->
				
				<!-- BOF above-description-nolabel-line2 block -->
				<?php if (isset($items[$i]->positions['above-description-line2-nolabel'])) : ?>
				<div class="lineinfo line1">
					<?php foreach ($items[$i]->positions['above-description-line2-nolabel'] as $field) : ?>
					<span class="element">
						<span class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></span>
					</span>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<!-- EOF above-description-nolabel-line1 block -->

				<p>
				<?php
				if ($this->params->get('intro_strip_html', 1)) :
				echo flexicontent_html::striptagsandcut( $items[$i]->fields['text']->display, $this->params->get('intro_cut_text', 200) );
				else :
				echo $items[$i]->fields['text']->display;
				endif;
				?>
				</p>

				<!-- BOF under-description-line1 block -->
				<?php if (isset($items[$i]->positions['under-description-line1'])) : ?>
				<div class="lineinfo line3">
					<?php foreach ($items[$i]->positions['under-description-line1'] as $field) : ?>
					<span class="element">
						<?php if ($field->label) : ?>
						<span class="label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
						<?php endif; ?>
						<span class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></span>
					</span>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<!-- EOF under-description-line1 block -->
				
				<!-- BOF under-description-line1-nolabel block -->
				<?php if (isset($items[$i]->positions['under-description-line1-nolabel'])) : ?>
				<div class="lineinfo line3">
					<?php foreach ($items[$i]->positions['under-description-line1-nolabel'] as $field) : ?>
					<span class="element">
						<span class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></span>
					</span>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<!-- EOF under-description-line1-nolabel block -->

				<!-- BOF under-description-line2 block -->
				<?php if (isset($items[$i]->positions['under-description-line2'])) : ?>
				<div class="lineinfo line4">
					<?php foreach ($items[$i]->positions['under-description-line2'] as $field) : ?>
					<span class="element">
						<?php if ($field->label) : ?>
						<span class="label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
						<?php endif; ?>
						<span class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></span>
					</span>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<!-- EOF under-description-line2 block -->

				<!-- BOF under-description-line2-nolabel block -->
				<?php if (isset($items[$i]->positions['under-description-line2-nolabel'])) : ?>
				<div class="lineinfo line4">
					<?php foreach ($items[$i]->positions['under-description-line2-nolabel'] as $field) : ?>
					<span class="element">
						<span class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></span>
					</span>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<!-- EOF under-description-line2-nolabel block -->

    			<?php if (
    				( $this->params->get('show_readmore', 1) && strlen(trim($items[$i]->fulltext)) >= 1 )
    				||  $this->params->get('intro_strip_html', 1) == 1 /* option 2, strip-cuts and option 1 also forces read more  */
    			) : ?>
    			<span class="readmore">
    				<a href="<?php echo JRoute::_(FlexicontentHelperRoute::getItemRoute($items[$i]->slug, $items[$i]->categoryslug)); ?>" class="readon">
    				<?php
    				if ($items[$i]->params->get('readmore')) :
    					echo ' ' . $items[$i]->params->get('readmore');
    				else :
    					echo ' ' . JText::sprintf('FLEXI_READ_MORE', $items[$i]->title);
    				endif;
    				?>
    				</a>
    			</span>
    			<?php endif; ?>

			</div>
		</li>
		<?php endfor; ?>
	</ul>
	<?php endif; ?>
</div>
<?php else : ?>
<div class="noitems"><?php echo JText::_( 'FLEXI_NO_ITEMS_CAT' ); ?></div>
<?php endif; ?>
