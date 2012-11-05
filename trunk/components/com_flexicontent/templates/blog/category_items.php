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
$user =& JFactory::getUser();

JFactory::getDocument()->addScript( JURI::base().'components/com_flexicontent/assets/js/tmpl-common.js');
?>

<!--script type="text/javascript">
</script-->

<?php if ((($this->params->get('use_filters', 0)) && $this->filters) || ($this->params->get('use_search')) || ($this->params->get('show_alpha', 1))) : ?>
<form action="<?php echo htmlentities($this->action); ?>" method="POST" id="adminForm" onsubmit="">

	<?php if ( JRequest::getVar('clayout') == $this->params->get('clayout', 'blog') ) :?>
	<input type="hidden" name="clayout" value="<?php echo JRequest::getVar('clayout'); ?>" />
	<?php endif; ?>

	<?php if ((($this->params->get('use_filters', 0)) && $this->filters) || ($this->params->get('use_search'))) : /* BOF filter ans search block */ ?>
	<div id="fc_filter" class="floattext">
		<?php if ($this->params->get('use_search')) : /* BOF search */ ?>
		<div class="fc_fleft">
			<span class="fc_search_label"><?php echo JText::_('FLEXI_SEARCH'); ?>:</span>
			<input type="text" name="filter" id="filter" value="<?php echo $this->lists['filter'];?>" class="text_area" />
			<button class="fc_button" onclick="var form=document.getElementById('adminForm');                               adminFormPrepare(form);"><span class="fcbutton_go"><?php echo JText::_( 'FLEXI_GO' ); ?></span></button>
			<button class="fc_button" onclick="var form=document.getElementById('adminForm'); adminFormClearFilters(form);  adminFormPrepare(form);"><span class="fcbutton_reset"><?php echo JText::_( 'FLEXI_RESET' ); ?></span></button>
		</div>
		<?php endif; /* EOF search */ ?>

		<?php if ( $this->params->get('use_search') && ($this->params->get('use_filters', 0) && $this->filters) ) : ?>
		<div class="fc_splitter_line"></div>
		<?php endif; ?>

		<?php if ($this->params->get('use_filters', 0) && $this->filters) : /* BOF filter */ ?>
		<span class="fc_filters_label"><?php echo JText::_('FLEXI_FIELD_FILTERS'); ?>:</span>
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
			<button onclick="var form=document.getElementById('adminForm'); adminFormClearFilters(form);  adminFormPrepare(form);"><?php echo JText::_( 'FLEXI_RESET' ); ?></button>
		<?php endif; ?>
		<!--/div-->

		<?php endif; /* EOF filter */ ?>
	</div>
	<?php endif; /* EOF filter ans serch block */ ?>
	<?php
	if ($this->params->get('show_alpha', 1)) :
		echo $this->loadTemplate('alpha');
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
$items	= $this->items;
$count 	= count($items);
if ($count) :
?>
<div class="content">

		<!-- BOF items total-->
		<?php if ($this->params->get('show_item_total', 1)) : ?>
		<div id="item_total" class="item_total group">
			<?php	//echo $this->pageNav->getResultsCounter(); // Alternative way of displaying total (via joomla pagination class) ?>
			<?php echo $this->resultsCounter; // custom Results Counter ?>
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
					
			<!-- BOF beforeDisplayContent -->
			<?php if ($items[$i]->event->beforeDisplayContent) : ?>
				<div class="fc_beforeDisplayContent group">
					<?php echo $items[$i]->event->beforeDisplayContent; ?>
				</div>
			<?php endif; ?>
			<!-- EOF beforeDisplayContent -->

			<?php if ($this->params->get('show_editbutton', 0)) : ?>
				<?php $editbutton = flexicontent_html::editbutton( $items[$i], $this->params ); ?>
				<?php if ($editbutton) : ?>
					<div style="float:left;"><?php echo $editbutton;?></div>
				<?php endif; ?>
			<?php endif; ?>
					
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
							
				<!-- BOF afterDisplayTitle -->
				<?php if ($items[$i]->event->afterDisplayTitle) : ?>
					<div class="fc_afterDisplayTitle group">
						<?php echo $items[$i]->event->afterDisplayTitle; ?>
					</div>
				<?php endif; ?>
				<!-- EOF afterDisplayTitle -->


				<?php 
					if ($this->params->get('lead_use_image', 1)) :
						if ($this->params->get('lead_image')) :
							FlexicontentFields::getFieldDisplay($items[$i], $this->params->get('lead_image'), $values=null, $method='display');
							
							$img_field_name = $this->params->get('lead_image');
							$img_field = & $items[$i]->fields[$img_field_name];
							$img_field_size = $this->params->get('lead_image_size');
							$img_field_size = $img_field_size ? $img_field_size : 'l';
							if ( !empty($img_field->value[0]) ) :
							
								$value{$i} = unserialize($img_field->value[0]);
								
								if ( $img_field->parameters->get('image_source') && empty($img_field->value[0]['is_default_value'] ) ) {
									$dir{$i}	 = $img_field->parameters->get('dir') .'/'. 'item_'.$items[$i]->id.'_field_'.$img_field->id;
								} else {
									$dir{$i}	 = $img_field->parameters->get('dir');
								}
								
								$image{$i} = $value{$i}['originalname'];
								$scr{$i}	 = $dir{$i}.'/'.$img_field_size.'_'.$image{$i};
							else :
								$scr{$i}	= '';
							endif;
							$src = str_replace('\\','/', $scr{$i});
						else :
							$src = flexicontent_html::extractimagesrc($items[$i]);
						endif;
						
						if (!$this->params->get('lead_image_size') || !$this->params->get('lead_image')) :
							$w		= '&amp;w=' . $this->params->get('lead_width', 200);
							$h		= '&amp;h=' . $this->params->get('lead_height', 200);
							$aoe	= '&amp;aoe=1';
							$q		= '&amp;q=95';
							$zc		= $this->params->get('lead_method') ? '&amp;zc=' . $this->params->get('lead_method') : '';
							$ext = pathinfo($src, PATHINFO_EXTENSION);
							$f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
							$conf	= $w . $h . $aoe . $q . $zc . $f;
							
							$base_url = (!preg_match("#^http|^https|^ftp#i", $src)) ?  JURI::base(true).'/' : '';
							$thumb = JURI::base().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$base_url.$src.$conf;
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
				FlexicontentFields::getFieldDisplay($items[$i], 'text', $values=null, $method='display');
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
						<?php
						/*$uniqueid = "read_more_fc_item_".$items[$i]->id;
						$itemlnk = JRoute::_(FlexicontentHelperRoute::getItemRoute($items[$i]->slug, $items[$i]->categoryslug).'&tmpl=component');
						echo '<script>document.write(\'<a href="'.$itemlnk.'" id="mb'.$uniqueid.'" class="mb" rel="width:\'+((MooTools.version>='1.2.4' ? window.getSize().x : window.getSize().size.x)-150)+\',height:\'+((MooTools.version>='1.2.4' ? window.getSize().y : window.getSize().size.y)-150)+\'">\')</script>';
						*/
						?>
						<a href="<?php echo JRoute::_(FlexicontentHelperRoute::getItemRoute($items[$i]->slug, $items[$i]->categoryslug)); ?>" class="readon">
						<?php
						if ($items[$i]->params->get('readmore')) :
							echo ' ' . $items[$i]->params->get('readmore');
						else :
							echo ' ' . JText::sprintf('FLEXI_READ_MORE', $items[$i]->title);
						endif;
						?>
						</a>
						<?php //echo '<script>document.write(\'</a> <div class="multiBoxDesc mbox_img_url mb'.$uniqueid.'">'.$items[$i]->title.'</div>\')</script>'; ?>
					</span>
					<?php endif; ?>
					
					<!-- BOF afterDisplayContent -->
					<?php if ($items[$i]->event->afterDisplayContent) : ?>
						<div class="afterDisplayContent group">
							<?php echo $items[$i]->event->afterDisplayContent; ?>
						</div>
					<?php endif; ?>
					<!-- EOF afterDisplayContent -->
					
					
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
				
					<!-- BOF beforeDisplayContent -->
					<?php if ($items[$i]->event->beforeDisplayContent) : ?>
						<div class="fc_beforeDisplayContent group">
							<?php echo $items[$i]->event->beforeDisplayContent; ?>
						</div>
					<?php endif; ?>
					<!-- EOF beforeDisplayContent -->

					<?php if ($this->params->get('show_editbutton', 0)) : ?>
						<?php $editbutton = flexicontent_html::editbutton( $items[$i], $this->params ); ?>
						<?php if ($editbutton) : ?>
							<div style="float:left;"><?php echo $editbutton;?></div>
						<?php endif; ?>
					<?php endif; ?>
					
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
					
					<!-- BOF afterDisplayTitle -->
					<?php if ($items[$i]->event->afterDisplayTitle) : ?>
						<div class="fc_afterDisplayTitle group">
							<?php echo $items[$i]->event->afterDisplayTitle; ?>
						</div>
					<?php endif; ?>
					<!-- EOF afterDisplayTitle -->
					
					<?php 
					if ($this->params->get('intro_use_image', 1)) :
						if ($this->params->get('intro_image')) :
							FlexicontentFields::getFieldDisplay($items[$i], $this->params->get('intro_image'), $values=null, $method='display');
							
							$img_field_name = $this->params->get('intro_image');
							$img_field = & $items[$i]->fields[$img_field_name];
							$img_field_size = $this->params->get('intro_image_size');
							$img_field_size = $img_field_size ? $img_field_size : 'l';
							if ( !empty($img_field->value[0]) ) :
							
								$value{$i} = unserialize($img_field->value[0]);
								
								if ( $img_field->parameters->get('image_source') && empty($img_field->value[0]['is_default_value'] ) ) {
									$dir{$i}	 = $img_field->parameters->get('dir') .'/'. 'item_'.$items[$i]->id.'_field_'.$img_field->id;
								} else {
									$dir{$i}	 = $img_field->parameters->get('dir');
								}
								
								$image{$i} = $value{$i}['originalname'];
								$scr{$i}	 = $dir{$i}.'/'.$img_field_size.'_'.$image{$i};
							else :
								$scr{$i}	= '';
							endif;
							$src = str_replace('\\','/', $scr{$i});
						else :
							$src = flexicontent_html::extractimagesrc($items[$i]);
						endif;
						
						if (!$this->params->get('intro_image_size') || !$this->params->get('intro_image')) :
							$w		= '&amp;w=' . $this->params->get('intro_width', 200);
							$h		= '&amp;h=' . $this->params->get('intro_height', 200);
							$aoe	= '&amp;aoe=1';
							$q		= '&amp;q=95';
							$zc		= $this->params->get('intro_method') ? '&amp;zc=' . $this->params->get('intro_method') : '';
							$ext = pathinfo($src, PATHINFO_EXTENSION);
							$f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
							$conf	= $w . $h . $aoe . $q . $zc . $f;
							
							$base_url = (!preg_match("#^http|^https|^ftp#i", $src)) ?  JURI::base(true).'/' : '';
							$thumb = JURI::base().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$base_url.$src.$conf;
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
				FlexicontentFields::getFieldDisplay($items[$i], 'text', $values=null, $method='display');
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
						<?php
						/*$uniqueid = "read_more_fc_item_".$items[$i]->id;
						$itemlnk = JRoute::_(FlexicontentHelperRoute::getItemRoute($items[$i]->slug, $items[$i]->categoryslug).'&tmpl=component');
						echo '<script>document.write(\'<a href="'.$itemlnk.'" id="mb'.$uniqueid.'" class="mb" rel="width:\'+((MooTools.version>='1.2.4' ? window.getSize().x : window.getSize().size.x)-150)+\',height:\'+((MooTools.version>='1.2.4' ? window.getSize().y : window.getSize().size.y)-150)+\'">\')</script>';
						*/
						?>
						<a href="<?php echo JRoute::_(FlexicontentHelperRoute::getItemRoute($items[$i]->slug, $items[$i]->categoryslug)); ?>" class="readon">
						<?php
						if ($items[$i]->params->get('readmore')) :
							echo ' ' . $items[$i]->params->get('readmore');
						else :
							echo ' ' . JText::sprintf('FLEXI_READ_MORE', $items[$i]->title);
						endif;
						?>
						</a>
						<?php //echo '<script>document.write(\'</a> <div class="multiBoxDesc mbox_img_url mb'.$uniqueid.'">'.$items[$i]->title.'</div>\')</script>'; ?>
					</span>
					<?php endif; ?>
					
					<!-- BOF afterDisplayContent -->
					<?php if ($items[$i]->event->afterDisplayContent) : ?>
						<div class="afterDisplayContent group">
							<?php echo $items[$i]->event->afterDisplayContent; ?>
						</div>

					<?php endif; ?>
					<!-- EOF afterDisplayContent -->
					
			</div>
		</li>
		<?php endfor; ?>
	</ul>
	<?php endif; ?>
</div>
<?php elseif ($this->getModel()->getState('limit')) : // Check case of creating a category view without items ?>
	<div class="noitems"><?php echo JText::_( 'FLEXI_NO_ITEMS_CAT' ); ?></div>
<?php endif; ?>
