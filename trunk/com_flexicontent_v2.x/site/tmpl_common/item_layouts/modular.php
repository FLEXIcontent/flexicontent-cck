<?php
/**
 * @version 1.5 stable $Id: item.php 1538 2012-11-05 02:44:34Z ggppdk $
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
$tmpl = $this->tmpl; // for backwards compatiblity

// USE HTML5 or XHTML
$html5			= $this->params->get('htmlmode', 0); // 0 = XHTML , 1 = HTML5
if ($html5) {  /* BOF html5  */
	echo $this->loadTemplate('html5');
} else {

$page_classes  = '';
$page_classes .= $this->pageclass_sfx ? ' page'.$this->pageclass_sfx : '';
$page_classes .= ' items item'.$this->item->id;
$page_classes .= ' type'.$this->item->type_id;

JFactory::getDocument()->addScript( JURI::base().'components/com_flexicontent/assets/js/tabber-minimized.js');
JFactory::getDocument()->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/tabber.css');
?>

<div id="flexicontent" class="flexicontent <?php echo $page_classes; ?>" >

  <!-- BOF beforeDisplayContent -->
  	
	<?php if ($this->item->event->beforeDisplayContent) : /* BOF beforeDisplayContent */ ?>
		<div class="fc_beforeDisplayContent group">
			<?php echo $this->item->event->beforeDisplayContent; ?>
		</div>
	<?php endif; /* EOF beforeDisplayContent */ ?>
	
	<?php /* BOF buttons */
	$pdfbutton = flexicontent_html::pdfbutton( $this->item, $this->params );
	$mailbutton = flexicontent_html::mailbutton( FLEXI_ITEMVIEW, $this->params, null , $this->item->slug );
	$printbutton = flexicontent_html::printbutton( $this->print_link, $this->params );
	$editbutton = flexicontent_html::editbutton( $this->item, $this->params );
	$statebutton = flexicontent_html::statebutton( $this->item, $this->params );
	$approvalbutton = flexicontent_html::approvalbutton( $this->item, $this->params );
	if ($pdfbutton || $mailbutton || $printbutton || $editbutton || $statebutton || $approvalbutton) {
	?>
  
	<p class="buttons">
		<?php echo $pdfbutton; ?>
		<?php echo $mailbutton; ?>
		<?php echo $printbutton; ?>
		<?php echo $editbutton; ?>
		<?php echo $statebutton; ?>
		<?php echo $approvalbutton; ?>
	</p>
	<?php } /* EOF buttons */ ?>

	<?php if ( $this->params->get( 'show_page_heading', 1 ) && $this->params->get('page_heading') != $this->item->title ) : /* BOF page title */ ?>
	<h1 class="componentheading">
		<?php echo $this->params->get('page_heading'); ?>
	</h1>
	<?php endif; /* EOF page title */ ?>
    
    
	<?php if ($this->params->get('show_title', 1)) : /* BOF item title */ ?>
	<h2 class="contentheading"><span class="fc_item_title">
		<?php
		if ( mb_strlen($this->item->title, 'utf-8') > $this->params->get('title_cut_text',200) ) :
			echo mb_substr ($this->item->title, 0, $this->params->get('title_cut_text',200), 'utf-8') . ' ...';
		else :
			echo $this->item->title;
		endif;
		?>
	</span></h2>
	<?php endif; /* EOF item title */ ?>

    <?php if ($this->item->event->afterDisplayTitle) : /* BOF afterDisplayTitle */ ?>
		<div class="fc_afterDisplayTitle group">
			<?php echo $this->item->event->afterDisplayTitle; ?>
		</div>
	<?php endif; /* EOF afterDisplayTitle */ ?>
	
	<?php if (isset($this->item->positions['subtitle1'])) : /* BOF subtitle1 block */ ?>
	<div class="flexi lineinfo subtitle1 group">
		<?php foreach ($this->item->positions['subtitle1'] as $field) : ?>
		<div class="flexi element">
			<?php if ($field->label) : ?>
			<span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
			<?php endif; ?>
			<div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
		</div>
		<?php endforeach; ?>
	</div>
	<?php endif; /* EOF subtitle1 block */ ?>
	
	<?php if (isset($this->item->positions['subtitle2'])) : /* BOF subtitle2 block */ ?>
	<div class="flexi lineinfo subtitle2 group">
		<?php foreach ($this->item->positions['subtitle2'] as $field) : ?>
		<div class="flexi element">
			<?php if ($field->label) : ?>
			<span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
			<?php endif; ?>
			<div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
		</div>
		<?php endforeach; ?>
	</div>
	<?php endif; /* EOF subtitle2 block */ ?>
	
	<?php if (isset($this->item->positions['subtitle3'])) : /* BOF subtitle3 block */ ?>
	<div class="flexi lineinfo subtitle3 group">
		<?php foreach ($this->item->positions['subtitle3'] as $field) : ?>
		<div class="flexi element">
			<?php if ($field->label) : ?>
			<span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
			<?php endif; ?>
			<div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
		</div>
		<?php endforeach; ?>
	</div>
	<?php endif; /* EOF subtitle3 block */ ?>
    

	<div class="clear"></div>

<?php
$tabcount = 6;

// Find if at least one tabbed position is used
for ($tc=1; $tc<=$tabcount; $tc++) $createtabs = @$createtabs ||  isset($this->item->positions['subtitle_tab'.$tc]);

/* BOF fctabber */
if (@$createtabs) :
	echo '<div class="fctabber group">'."\n";
	
	for ($tc=1; $tc<=$tabcount; $tc++) :
		$tabpos_name  = 'subtitle_tab'.$tc;
		$tabpos_label = JText::_($this->params->get('subtitle_tab'.$tc.'_label', $tabpos_name));
		if (isset($this->item->positions[$tabpos_name])):
    /*BOF subtitle_tabN block*/
?>
        
		<div class="tabbertab"><!-- tab start -->

			<h3><?php echo $tabpos_label; ?></h3><!-- tab title -->

			<div class="flexi lineinfo <?php echo $tabpos_label; ?>">
				<?php foreach ($this->item->positions[$tabpos_name] as $field) : ?>
				<div class="flexi element">
					<?php if ($field->label) : ?>
					<span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
					<?php endif; ?>
					<div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
				</div>
				<?php endforeach; ?>
			</div>
			
		</div><!-- tab end -->
		 
		<?php endif; /*EOF subtitle_tabN block*/ ?>
	
	<?php endfor; ?>
		
<?php
	echo '</div>'."\n";
endif;
/* EOF fctabber */
?>


	<div class="clear"></div>
	
	<?php if ((isset($this->item->positions['image'])) || (isset($this->item->positions['top']))) : /* BOF top block */ ?>
	<div class="flexi topblock group row">  <!-- NOTE: image block is inside top block ... -->
	
		<?php if (isset($this->item->positions['image'])) : /* BOF image */ ?>
			<?php foreach ($this->item->positions['image'] as $field) : ?>
		<div class="flexi image field_<?php echo $field->name; ?>">
			<?php echo $field->display; ?>
			<div class="clear"></div>
		</div>
			<?php endforeach; ?>
		<?php endif; /* EOF image */ ?>

		<?php if (isset($this->item->positions['top'])) : /* BOF top */ ?>
		<div class="flexi infoblock <?php echo $this->params->get('top_cols', 'two'); ?>cols">
			<ul class="flexi row">
				<?php foreach ($this->item->positions['top'] as $field) : ?>
				<li class="flexi">
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
		<?php endif; /* EOF top block */ ?>
	
	</div>
	<?php endif; ?>
	
	<div class="clear"></div>
	
	<?php if (isset($this->item->toc)) : /* BOF TOC */ ?>
		<?php echo $this->item->toc; ?>
	<?php endif; /* BOF TOC */ ?>
	
	<?php if (isset($this->item->positions['description'])) : /* BOF description */ ?>
	<div class="description group">
		<?php foreach ($this->item->positions['description'] as $field) : ?>
			<?php if ($field->label) : ?>
		<div class="desc-title"><?php echo $field->label; ?></div>
			<?php endif; ?>
		<div class="desc-content"><?php echo $field->display; ?></div>
		<?php endforeach; ?>
    </div>
	<?php endif; /* EOF description */ ?>
	
	<div class="clear"></div>

<?php
$tabcount = 6;

// Find if at least one tabbed position is used
for ($tc=1; $tc<=$tabcount; $tc++) $createtabs = @$createtabs ||  isset($this->item->positions['bottom_tab'.$tc]);

/* BOF fctabber */
if (@$createtabs) :
	echo '<div class="fctabber group">'."\n";
	
	for ($tc=1; $tc<=$tabcount; $tc++) :
		$tabpos_name  = 'bottom_tab'.$tc;
		$tabpos_label = JText::_($this->params->get('bottom_tab'.$tc.'_label', $tabpos_name));
		if (isset($this->item->positions[$tabpos_name])):
    /*BOF subtitle_tabN block*/
?>
        
		<div class="tabbertab"><!-- tab start -->
			
			<h3><?php echo $tabpos_label; ?></h3><!-- tab title -->
			
			<div class="flexi lineinfo <?php echo $tabpos_label; ?>">
				<?php foreach ($this->item->positions[$tabpos_name] as $field) : ?>
				<div class="flexi element">
					<?php if ($field->label) : ?>
					<span class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></span>
					<?php endif; ?>
					<div class="flexi value field_<?php echo $field->name; ?>"><?php echo $field->display; ?></div>
				</div>
				<?php endforeach; ?>
			</div>
			
		</div><!-- tab end -->
		 
		<?php endif; /*EOF subtitle_tabN block*/ ?>
	
	<?php endfor; ?>
		
<?php
	echo '</div>'."\n";
endif;
/* EOF fctabber */
?>


	<div class="clear"></div>

	<?php if (isset($this->item->positions['bottom'])) : /* BOF bottom block */ ?>
	<div class="flexi infoblock <?php echo $this->params->get('bottom_cols', 'two'); ?>cols group row">
		<ul class="flexi row">
			<?php foreach ($this->item->positions['bottom'] as $field) : ?>
			<li class="flexi">
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
	<?php endif;  /* EOF bottom block */ ?>

	<?php if ($this->params->get('comments') && !JRequest::getVar('print')) : /* BOF comments */ ?>
	<div class="comments group">
	<?php
		if ($this->params->get('comments') == 1) :
			if (file_exists(JPATH_SITE.DS.'components'.DS.'com_jcomments'.DS.'jcomments.php')) :
				require_once(JPATH_SITE.DS.'components'.DS.'com_jcomments'.DS.'jcomments.php');
				echo JComments::showComments($this->item->id, 'com_flexicontent', $this->escape($this->item->title));
			endif;
		endif;
	
		if ($this->params->get('comments') == 2) :
			if (file_exists(JPATH_SITE.DS.'plugins'.DS.'content'.DS.'jom_comment_bot.php')) :
    			require_once(JPATH_SITE.DS.'plugins'.DS.'content'.DS.'jom_comment_bot.php');
    			echo jomcomment($this->item->id, 'com_flexicontent');
  			endif;
  		endif;
	?>
	</div>
	<?php endif; /* EOF comments */ ?>
    

  	<?php if ($this->item->event->afterDisplayContent) : /* BOF afterDisplayContent */ ?>
		<div class="fc_afterDisplayContent group">
			<?php echo $this->item->event->afterDisplayContent; ?>
		</div>
  	<?php endif; /* EOF afterDisplayContent */ ?>

</div>

<?php } /* EOF if html5  */ ?>
