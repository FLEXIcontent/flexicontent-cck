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
					
					<?php if ( $result->href ) :
						if ($result->browsernav == 1 ) : ?>
							<a href="<?php echo JRoute::_($result->href); ?>" target="_blank">
						<?php else : ?>
							<a href="<?php echo JRoute::_($result->href); ?>">
						<?php endif; ?>
					<?php endif; ?>

						echo $this->escape($result->title);

					<?php if ( $result->href ) : ?>
							</a>
					<?php endif; ?>
					
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
			<div align="center">
				<?php echo $this->pagination->getPagesLinks(); ?>
			</div>
		</td>
	</tr>
</table>
