<?php defined('_JEXEC') or die('Restricted access'); ?>

<table class="contentpaneopen<?php echo $this->escape($this->params->get('pageclass_sfx')); ?>">
	<tr>
		<td>
		<?php
		foreach( $this->results as $result ) : ?>
			<fieldset>
				<div>
					<span class="small<?php echo $this->escape($this->params->get('pageclass_sfx')); ?>">
						<?php echo $this->pagination->limitstart + $result->count.'. ';?>
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
				<div>
					<?php echo $result->text; ?>
				</div>
				<?php endif;?>
				<?php
					if ( $this->params->get( 'show_date', 1 )) : ?>
				<div class="small<?php echo $this->escape($this->params->get('pageclass_sfx')); ?>">
					<?php echo $result->created; ?>
				</div>
				<?php endif; ?>
			</fieldset>
		<?php endforeach; ?>
		</td>
	</tr>
	<tr>
		<td colspan="3">
			<div class="pageslinks" align="center">
				<?php echo $this->pagination->getPagesCounter(); ?>
				<?php echo $this->pagination->getPagesLinks(); ?>
			</div>
		</td>
	</tr>
</table>
