<?php
/**
 * @version      $Revision: 1.0$
 * @package      Joomla
 * @license      GNU/GPL, see LICENSE.php
 * Joomla! is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 */
// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

// - RETURNED HTML is used as 'toc' 
// - Optionally set 'visible pages HTML' into $this->_text 

$custom_allpages = JText::_($this->params->get('custom_allpages', 'FLEXIBREAK_ALL_PAGES'));
$display_method  = (int) $this->params->get('display_method', 1);
$multipage_toc   = (int) $this->params->get('multipage_toc', 1);

// Needed to disable anchor following
$onclick = $display_method === 1 
	? 'javascript:return false;'
	: '';
$link_class = $display_method === 1
	? ' tocPaginated'
	: ($display_method === 0 ? ' tocScrolled' : ' tocReloaded');

$sef_link   = JRoute::_($this->nonsef_link);  // Get current SEF link of current item

if ($multipage_toc) : /* TOC Start */ ?>

	<div class="contenttoc" id="articleTOC">
	<a id="articleToc"></a>
		<?php if ( $this->params->get('toc_title', 1) ) : ?>
		<p class="tocHeader"><?php echo JText::_('FLEXIBREAK_TABLE_OF_CONTENT') ?></p>
		<?php endif; ?>
		
		<ul class="tocList">
		
			<?php
			$n = !empty($this->texts[0]) ? -1 : 0;
			for ($i = 0; $i < $this->pagescount; $i++) :
				$page = $this->_generateToc($this->row, $i);  // Create page data of current page, needed to create TOC navigation entries (in our case JS navigation links)
				
				switch($display_method)
				{
					case 2:
					case 1:
						$link = $page->link;
						break;

					case 0:
					default:
						$link = $sef_link.'#'.$page->id;
						break;
				}
				$active = !$this->showall && $this->limitstart == $i  ?  ' active'  : '';
				$n++;
			?>
				<li class="<?php echo $active ?>">
					<a class="tocLink<?php echo $link_class; ?>" id="<?php echo $page->id ?>_toc_link" href="<?php echo $link; ?>" onclick="<?php echo $onclick ?>" >
						<?php echo ($n+1) .". ". $page->title ?>
					</a>
				</li>
			<?php endfor; ?>
			
			<?php if ( $this->params->get('allpages_link', 1) && ($display_method == 1 || $display_method == 2) ) : ?>
				<li class="<?php echo $this->showall ? 'active' : ''; ?>">
					<a class="tocAll" id="showall" onclick="<?php echo $onclick ?>" href="<?php echo JRoute::_($this->nonsef_link.($display_method == 0 ? '#showall' : '&showall=1' )); ?>"> - <?php echo $custom_allpages; ?> - </a>
				</li>
			<?php endif; ?>
		
		</ul>

		<?php
		if ($this->params->get('pagination', 1) == 1)
		{
			echo $this->loadTemplate('pagination_js');
		}
		?>
	</div>

<?php endif; /* TOC End */ ?>

<?php

// Create 'visible pages text' (visible without page reload)
// Below is default code, that can handle display_method: 0, 1, 2
$this->_text = '';

for ($i = 0; $i < $this->pagescount; $i++)
{
	$this->_text .= $this->_getPageText($this->row, $i, $this->showall);
}
