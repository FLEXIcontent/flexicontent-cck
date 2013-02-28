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
$user =& JFactory::getUser();

JFactory::getDocument()->addScript( JURI::base().'components/com_flexicontent/assets/js/tmpl-common.js');
?>

<aside class="group">
<?php
	// Form for (a) Text search, Field Filters, Alpha-Index, Items Total Statistics, Selectors(e.g. per page, orderby)
	// If customizing via CSS rules or JS scripts is not enough, then please copy the following file here to customize the HTML too
	include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'tmpl_common'.DS.'listings_filter_form_html5.php');
?>
</aside>

<div class="clear"></div>

<?php
$items	= & $this->items;
$count 	= count($items);
if ($count) :
?>
<div class="content group">

<?php
$leadnum		= $this->params->get('lead_num', 2);
$leadnum		= ($leadnum >= $count) ? $count : $leadnum;

// ONLY FIRST PAGE has leading content items
if ($this->limitstart != 0) $leadnum = 0;

if ($leadnum) :
?>
	<ul class="leadingblock group row">
		<?php
		if ($this->params->get('lead_use_image', 1) && $this->params->get('lead_image')) {
			$img_size_map   = array('l'=>'large', 'm'=>'medium', 's'=>'small');
			$img_field_size = $img_size_map[ $this->params->get('lead_image_size' , 'l') ];
			$img_field_name = $this->params->get('lead_image');
		}
		$li_item_classes = 'bloglist_item';
		$li_item_classes .= ' span12';
		for ($i=0; $i<$leadnum; $i++) : 
		?>
		<li id="bloglist_item_<?php echo $i; ?>" class="<?php echo $li_item_classes; ?>" style="overflow: hidden;">
			<?php if ($this->params->get('show_title', 1)) : ?>
				<article class="group">
			<?php endif; ?>	
			
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
					
			<?php if ($this->params->get('show_comments_count')) : ?>
				<?php if ( isset($this->comments[ $items[$i]->id ]->total) ) : ?>
					<div style="float:left;" class="fc_comments_count hasTip" alt=="<?php echo JText::_('FLEXI_NUM_OF_COMMENTS');?>" title="<?php echo JText::_('FLEXI_NUM_OF_COMMENTS');?>::<?php echo JText::_('FLEXI_NUM_OF_COMMENTS_TIP');?>">
						<?php echo $this->comments[ $items[$i]->id ]->total; ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
			
			<?php if ($this->params->get('show_title', 1)) : ?>
			<header class="group"> 	
				<h1 class="contentheading"><span class="fc_item_title">
					<?php if ($this->params->get('link_titles', 0)) : ?>
					<a href="<?php echo JRoute::_(FlexicontentHelperRoute::getItemRoute($items[$i]->slug, $items[$i]->categoryslug)); ?>"><?php echo $items[$i]->title; ?></a>
					<?php
					else :
					echo $items[$i]->title;
					endif;
					?>
				</span></h1>
			<?php endif; ?>
							
				<!-- BOF afterDisplayTitle -->
				<?php if ($items[$i]->event->afterDisplayTitle) : ?>
					<div class="fc_afterDisplayTitle group">
						<?php echo $items[$i]->event->afterDisplayTitle; ?>
					</div>
				<?php endif; ?>
				<!-- EOF afterDisplayTitle -->

			<?php if ($this->params->get('show_title', 1)) : ?>
			</header>
			<?php endif; ?>	

				<?php 
					if ($this->params->get('lead_use_image', 1)) :
						if (!empty($img_field_name)) :
							FlexicontentFields::getFieldDisplay($items[$i], $img_field_name, $values=null, $method='display');
							$img_field = & $items[$i]->fields[$img_field_name];
							$src = str_replace(JURI::root(), '', @ $img_field->thumbs_src[$img_field_size][0] );
						else :
							$src = flexicontent_html::extractimagesrc($items[$i]);
						endif;
						
						$RESIZE_FLAG = !$this->params->get('lead_image') || !$this->params->get('lead_image_size');
						if ( $src && $RESIZE_FLAG ) {
							// Resize image when src path is set and RESIZE_FLAG: (a) using image extracted from item main text OR (b) not using image field's already created thumbnails
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
						} else {
							// Do not resize image when (a) image src path not set or (b) using image field's already created thumbnails
							$thumb = $src;
						}
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
				<div class="lineinfo line2">
					<?php foreach ($items[$i]->positions['above-description-line2-nolabel'] as $field) : ?>
					<span class="element">
						<span class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></span>
					</span>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<!-- EOF above-description-nolabel-line2 block -->
				
				<div class="lineinfo image_descr">
				<?php if ($this->params->get('lead_use_image', 1) && $src) : ?>
				<figure class="image<?php echo $this->params->get('lead_position') ? ' right' : ' left'; ?>">
					<?php if ($this->params->get('lead_link_image', 1)) : ?>
					<a href="<?php echo JRoute::_(FlexicontentHelperRoute::getItemRoute($items[$i]->slug, $items[$i]->categoryslug)); ?>" class="hasTip" title="<?php echo JText::_( 'FLEXI_READ_MORE_ABOUT' ) . '::' . htmlspecialchars($items[$i]->title, ENT_COMPAT, 'UTF-8'); ?>">
						<img src="<?php echo $thumb; ?>" alt="<?php echo htmlspecialchars($items[$i]->title, ENT_COMPAT, 'UTF-8'); ?>" />
					</a>
					<?php else : ?>
					<img src="<?php echo $thumb; ?>" alt="<?php echo htmlspecialchars($items[$i]->title, ENT_COMPAT, 'UTF-8'); ?>" />
					<?php endif; ?>
				</figure>
				<?php endif; ?>
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
				</div>

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

				<?php if ( ($this->params->get('show_title', 1)) && ( ($this->params->get('show_readmore', 1) && strlen(trim($items[$i]->fulltext)) >= 1) ||  $this->params->get('intro_strip_html', 1) == 1 ) || ($items[$i]->event->afterDisplayContent) ) : ?>
					<footer>
				<?php endif; ?>

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
						<div class="fc_afterDisplayContent group">
							<?php echo $items[$i]->event->afterDisplayContent; ?>
						</div>
					<?php endif; ?>
					<!-- EOF afterDisplayContent -->
					
				<?php if ( ($this->params->get('show_title', 1)) && ( ($this->params->get('show_readmore', 1) && strlen(trim($items[$i]->fulltext)) >= 1) ||  $this->params->get('intro_strip_html', 1) == 1 ) || ($items[$i]->event->afterDisplayContent) ) : ?>
					</footer>
				<?php endif; ?>
					
			<?php if ($this->params->get('show_title', 1)) : ?>
				</article>
			<?php endif; ?>	
		</li>
		<?php endfor; ?>
	</ul>
<?php
	endif;
	if ($this->limitstart != 0) $leadnum = 0;
	if ($count > $leadnum) :
		//added to intercept more columns (see also css changes)
		$intro_cols = $this->params->get('intro_cols', 2);
		$intro_cols_classes = array(1=>'one',2=>'two',3=>'three',4=>'four');
		$classnum = $intro_cols_classes[$intro_cols];
		
		// bootstrap span
		$intro_cols_spanclasses = array(1=>'span12',2=>'span6',3=>'span4',4=>'span3');
		$classspan = $intro_cols_spanclasses[$intro_cols];
?>
	<ul class="introblock <?php echo $classnum; ?> group row">	
		<?php
		if ($this->params->get('intro_use_image', 1) && $this->params->get('intro_image')) {
			$img_size_map   = array('l'=>'large', 'm'=>'medium', 's'=>'small');
			$img_field_size = $img_size_map[ $this->params->get('intro_image_size' , 'l') ];
			$img_field_name = $this->params->get('intro_image');
		}
		for ($i=$leadnum; $i<$count; $i++) :
			$li_item_classes  = ($i-$leadnum)%2 ? 'even' : 'odd';
			$li_item_classes .= ' col'.($i%$intro_cols + 1);
			$li_item_classes .= ' '.$classspan;
		?>
		<li id="bloglist_item_<?php echo $i; ?>" class="<?php echo $li_item_classes; ?>" style="overflow: hidden;">
			<?php if ($this->params->get('show_title', 1)) : ?>
				<article class="group">
			<?php endif; ?>
				
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
				
				<?php if ($this->params->get('show_comments_count')) : ?>
					<?php if ( isset($this->comments[ $items[$i]->id ]->total )) : ?>
						<div style="float:left;" class="fc_comments_count hasTip" alt=="<?php echo JText::_('FLEXI_NUM_OF_COMMENTS');?>" title="<?php echo JText::_('FLEXI_NUM_OF_COMMENTS');?>::<?php echo JText::_('FLEXI_NUM_OF_COMMENTS_TIP');?>">
							<?php echo $this->comments[ $items[$i]->id ]->total; ?>
						</div>
					<?php endif; ?>
				<?php endif; ?>
				
				<?php if ($this->params->get('show_title', 1)) : ?>
				<header>
					<h1 class="contentheading">
						<?php if ($this->params->get('link_titles', 0)) : ?>
						<a href="<?php echo JRoute::_(FlexicontentHelperRoute::getItemRoute($items[$i]->slug, $items[$i]->categoryslug)); ?>"><?php echo $items[$i]->title; ?></a>
						<?php
						else :
						echo $items[$i]->title;
						endif;
						?>
					</h1>
				</header>
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
					if (!empty($img_field_name)) :
						FlexicontentFields::getFieldDisplay($items[$i], $img_field_name, $values=null, $method='display');
						$img_field = & $items[$i]->fields[$img_field_name];
						$src = str_replace(JURI::root(), '', @ $img_field->thumbs_src[$img_field_size][0] );
					else :
						$src = flexicontent_html::extractimagesrc($items[$i]);
					endif;
					
					$RESIZE_FLAG = !$this->params->get('intro_image') || !$this->params->get('intro_image_size');
					if ( $src && $RESIZE_FLAG ) {
						// Resize image when src path is set and RESIZE_FLAG: (a) using image extracted from item main text OR (b) not using image field's already created thumbnails
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
					} else {
						// Do not resize image when (a) image src path not set or (b) using image field's already created thumbnails
						$thumb = $src;
					}
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
			<div class="lineinfo line2">
				<?php foreach ($items[$i]->positions['above-description-line2-nolabel'] as $field) : ?>
				<span class="element">
					<span class="value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></span>
				</span>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
			<!-- EOF above-description-nolabel-line2 block -->

			<div class="lineinfo image_descr">
			<?php if ($this->params->get('intro_use_image', 1) && $src) : ?>
			<figure class="image<?php echo $this->params->get('intro_position') ? ' right' : ' left'; ?>">
				<?php if ($this->params->get('intro_link_image', 1)) : ?>
					<a href="<?php echo JRoute::_(FlexicontentHelperRoute::getItemRoute($items[$i]->slug, $items[$i]->categoryslug)); ?>" class="hasTip" title="<?php echo JText::_( 'FLEXI_READ_MORE_ABOUT' ) . '::' . htmlspecialchars($items[$i]->title, ENT_COMPAT, 'UTF-8'); ?>">
						<img src="<?php echo $thumb; ?>" alt="<?php echo htmlspecialchars($items[$i]->title, ENT_COMPAT, 'UTF-8'); ?>" />
					</a>
				<?php else : ?>
					<img src="<?php echo $thumb; ?>" alt="<?php echo htmlspecialchars($items[$i]->title, ENT_COMPAT, 'UTF-8'); ?>" />
				<?php endif; ?>
			</figure>
			<?php endif; ?>
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
			</div>

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

	<?php if ( ($this->params->get('show_title', 1)) && ( ($this->params->get('show_readmore', 1) && strlen(trim($items[$i]->fulltext)) >= 1) ||  $this->params->get('intro_strip_html', 1) == 1 ) || ($items[$i]->event->afterDisplayContent) ) : ?>
		<footer>
	<?php endif; ?>

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
					<div class="fc_afterDisplayContent group">
						<?php echo $items[$i]->event->afterDisplayContent; ?>
					</div>

				<?php endif; ?>
				<!-- EOF afterDisplayContent -->
			
			<?php if ( ($this->params->get('show_title', 1)) && ( ($this->params->get('show_readmore', 1) && strlen(trim($items[$i]->fulltext)) >= 1) ||  $this->params->get('intro_strip_html', 1) == 1 ) || ($items[$i]->event->afterDisplayContent) ) : ?>
				</footer>
			<?php endif; ?>
			
		<?php if ($this->params->get('show_title', 1)) : ?>
			</article>
		<?php endif; ?>	
		</li>
		<?php endfor; ?>
	</ul>
	<?php endif; ?>
</div>
<?php elseif ($this->getModel()->getState('limit')) : // Check case of creating a category view without items ?>
	<div class="noitems"><?php echo JText::_( 'FLEXI_NO_ITEMS_CAT' ); ?></div>
<?php endif; ?>
