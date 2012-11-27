<?php defined('_JEXEC') or die('Restricted access'); ?>

<table class="contentpaneopen<?php echo $this->escape($this->params->get('pageclass_sfx')); ?>">
	<tr>
		<td>
		<?php
		$count = -1;
		foreach( $this->results as $result ) :
		$count++;
		?>
			<fieldset class="fc_search_result <?php echo $count%2 ? 'odd' : 'even'; ?>">
				<div class="fc_search_result_title">
					<span class="small<?php echo $this->escape($this->params->get('pageclass_sfx')); ?>">
						<?php echo $this->pageNav->limitstart + $result->count.'. ';?>
					</span>
					
					<?php
						if ( $result->href ) {
							echo '<a href="'.JRoute::_($result->href).'" '.(($result->browsernav == 1) ? 'target="_blank"' : '').' >';
						}
						echo $this->escape($result->title);
						if ( $result->href ) {
							echo '</a>';
						}
					?>
					
					<?php if ($this->params->get( 'show_section', 0 ) && $result->section ) : ?>
						<br />
						<span class="small<?php echo $this->escape($this->params->get('pageclass_sfx')); ?>">
							(<?php echo $this->escape($result->section); ?>)
						</span>
					<?php endif; ?>
					
				</div>
				<?php if ( $this->params->get( 'show_text', 1 )) : ?>
				<div class="fc_search_result_text">
					<?php echo $result->text; ?>
				</div>
				<?php endif;?>
				<?php
					if ( $this->params->get( 'show_date', 1 )) : ?>
				<div class="fc_search_result_fields">
					<span class="small<?php echo $this->escape($this->params->get('pageclass_sfx')); ?>">
						<?php echo $result->created; ?>
					</span>
				</div>
				<?php endif; ?>
			</fieldset>
		<?php endforeach; ?>
		</td>
	</tr>
</table>

<!-- BOF pagination -->
<?php
	// If customizing via CSS rules or JS scripts is not enough, then please copy the following file here to customize the HTML too
	include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'tmpl_common'.DS.'pagination.php');
?>
<!-- EOF pagination -->

