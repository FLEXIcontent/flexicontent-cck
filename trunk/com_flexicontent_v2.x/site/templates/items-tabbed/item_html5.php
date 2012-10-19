<?php
/**
 * HTML5 Template
 * @version 1.5 stable $Id: item_html5.php 0001 2012-09-23 14:00:28Z Rehne $
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

$page_classes  = '';
$page_classes .= $this->pageclass_sfx ? ' page'.$this->pageclass_sfx : '';
$page_classes .= ' items item'.$this->item->id;
$page_classes .= ' type'.$this->item->type_id;

JFactory::getDocument()->addScript( JURI::base().'components/com_flexicontent/assets/js/tabber-minimized.js');
JFactory::getDocument()->addStyleSheet(JURI::base().'components/com_flexicontent/assets/css/tabber.css');

$mainAreaTag = ( $this->params->get( 'show_page_heading', 1 ) && $this->params->get('page_heading') != $this->item->title && $this->params->get('show_title', 1) ) ? 'section' : 'article';
// SEO
$itemTitleHeaderLevel = ( $this->params->get( 'show_page_heading', 1 ) && $this->params->get('page_heading') != $this->item->title && $this->params->get('show_title', 1) ) ? '2' : '1'; 
$tabsHeaderLevel =	( $itemTitleHeaderLevel == 2 ) ? '3' : '2';  	
// Note:in Some editors like Dreamweaver will automatically set a closing tag > after </h when opening the document. So look for h>  and replaced it with h
?>

<?php echo '<'.$mainAreaTag; ?> id="flexicontent" class="flexicontent <?php echo $page_classes; ?> group" >

    <?php echo ( ($mainAreaTag == 'section') ? '<header>' : ''); ?>
  	
	<?php if ($this->item->event->beforeDisplayContent) : /* BOF beforeDisplayContent */ ?>
		<?php echo ( ($mainAreaTag == 'section') ? '<aside' : '<div'); ?> class="fc_beforeDisplayContent group">
			<?php echo $this->item->event->beforeDisplayContent; ?>
		<?php echo ( ($mainAreaTag == 'section') ? '</aside>' : '</div>'); ?>
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
	<header>
    <h1 class="componentheading">
		<?php echo $this->params->get('page_heading'); ?>
	</h1>
    </header>
	<?php endif; /* EOF page title */ ?>
    
    <?php echo ( ($mainAreaTag == 'section') ? '</header>' : ''); ?>
	
    <?php echo ( ($mainAreaTag == 'section') ? '<article>' : ''); ?>
    
	<?php if ($this->params->get('show_title', 1)) : /* BOF item title */ ?>
    <header class="group">
	<h<?php echo $itemTitleHeaderLevel; ?> class="contentheading"><span class="fc_item_title">
		<?php
		if ( mb_strlen($this->item->title, 'utf-8') > $this->params->get('title_cut_text',200) ) :
			echo mb_substr ($this->item->title, 0, $this->params->get('title_cut_text',200), 'utf-8') . ' ...';
		else :
			echo $this->item->title;
		endif;
		?>
	</span></h<?php echo $itemTitleHeaderLevel; ?>>
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
                <h<?php echo $tabsHeaderLevel; ?>><?php echo $tabpos_name; ?></h<?php echo $tabsHeaderLevel; ?>><!-- tab title -->
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

	<?php if ((isset($this->item->positions['image'])) || (isset($this->item->positions['top']))) : /* BOF top block */ ?>
	<aside class="flexi topblock group row">  <!-- NOTE: image block is inside top block ... -->
	
		<?php if (isset($this->item->positions['image'])) : /* BOF image */ ?>
			<?php foreach ($this->item->positions['image'] as $field) : ?>
            <figure class="flexi image field_<?php echo $field->name; ?> span4">
                <?php echo $field->display; ?>
                <div class="clear"></div>
            </figure>
			<?php endforeach; ?>
		<?php endif; /* EOF image */ ?>

		<?php if (isset($this->item->positions['top'])) : /* BOF top */ ?>
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
				<?php foreach ($this->item->positions['top'] as $field) : ?>
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
		<?php endif; /* EOF top */ ?>
	
	</aside>
	<?php endif; ?>
	
	
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
        
            <section class='tabbertab'><!-- tab start -->
                <header>
                <h<?php echo ($tabsHeaderLevel+1); ?>><?php echo $tabpos_name; ?></h<?php echo ($tabsHeaderLevel+1); ?>><!-- tab title -->
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
    
	<?php if (isset($this->item->positions['bottom'])) : /* BOF bottom block */ ?>
	<?php 
    $classBottomColsspan = ''; // bootstrap span
    if ($this->params->get('top_cols', 'two') == 'one') :
        $classBottomColsspan = 'span12';
    else :
        $classBottomColsspan = 'span6';
    endif;
	?>
	<footer class="flexi infoblock <?php echo $this->params->get('bottom_cols', 'two'); ?>cols group row">
		<ul class="flexi">
			<?php foreach ($this->item->positions['bottom'] as $field) : ?>
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
	</footer>
	<?php endif;  /* EOF bottom block */ ?>

	<?php if ($this->params->get('comments') && !JRequest::getVar('print')) : /* BOF comments */ ?>
	<section class="comments group"> <?php /*?> the 'comment component may be have to be adjusted also to outputs HTML5 code (each comment as article ...)<?php */?>
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
	</section>
	<?php endif; /* EOF comments */ ?>
    
    <?php echo ( ($mainAreaTag == 'section') ? '</article>' : ''); ?>

  	<?php if ($this->item->event->afterDisplayContent) : /* BOF afterDisplayContent */ ?>
	<?php echo ( ($mainAreaTag == 'section') ? '<footer' : '<div'); ?> class="fc_afterDisplayContent group">
        <?php echo $this->item->event->afterDisplayContent; ?>
    <?php echo ( ($mainAreaTag == 'section') ? '</footer>' : '</div>'); ?>
  	<?php endif; /* EOF afterDisplayContent */ ?>
    
<?php echo '</'.$mainAreaTag.'>'; ?>