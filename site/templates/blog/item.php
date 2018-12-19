<?php
/**
 * @package FLEXIcontent
 * @copyright (C) 2009-2018 Emmanuel Danan, Georgios Papadakis, Yannick Berges
 * @author Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @license GNU/GPL v2
 */

defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\String\StringHelper;

// USE 1: HTML5 or 0: XHTML
$html5 = $this->params->get('htmlmode', 0);

if ($html5)
{
	// Load html5 layout
	echo $this->loadTemplate('html5');
}

// BOF XHTML
else
{

// first define the template name
$tmpl = $this->tmpl;
$item = $this->item;
$menu = JFactory::getApplication()->getMenu()->getActive();

// Create description field if not already created
FlexicontentFields::getFieldDisplay($item, 'text', $values=null, $method='display');

// Find if description is placed via template position
$_text_via_pos = false;
if (isset($item->positions) && is_array($item->positions)) {
	foreach ($item->positions as $posName => $posFields) {
		if ($posName == 'renderonly') continue;
		foreach($posFields as $field) if ($field->name=='text') { $_text_via_pos = true; break; }
	}
}

// Prepend toc (Table of contents) before item's description (toc will usually float right)
// By prepend toc to description we make sure that it get's displayed at an appropriate place
if (isset($item->toc))
{
	$item->fields['text']->display = $item->toc . $item->fields['text']->display;
}

// Set the class for controlling number of columns in custom field blocks
switch ($this->params->get( 'columnmode', 2 ))
{
	case 0: $columnmode = 'singlecol'; break;
	case 1: $columnmode = 'doublecol'; break;
	default: $columnmode = 'variablecol'; break;
}


/**
 * Custom Classes for containers
 */

$box_class_fields_top        = $this->params->get('box_class_fields_top', 'group iteminfo');
$box_class_beforedescription = $this->params->get('box_class_beforedescription', 'group customblock beforedescription');
$box_class_description       = $this->params->get('box_class_description', 'group description');
$box_class_afterdescription  = $this->params->get('box_class_afterdescription', 'group customblock afterdescription');
$box_class_fields_bottom     = $this->params->get('box_class_fields_bottom', 'group itemadditional');


/**
 * Decide Tags for containers
 */

$page_heading_shown =
	$this->params->get( 'show_page_heading', 1 ) &&
	$this->params->get('page_heading') != $item->title &&
	$this->params->get('show_title', 1);

// Main container
$mainAreaTag = 'div';

// SEO, header level of title tag
$itemTitleHeaderLevel = '2';

$page_classes  = 'flexicontent group';
$page_classes .= $this->pageclass_sfx ? ' page'.$this->pageclass_sfx : '';
$page_classes .= ' fcitems fcitem'.$item->id;
$page_classes .= ' fctype'.$item->type_id;
$page_classes .= ' fcmaincat'.$item->catid;
if ($menu) $page_classes .= ' menuitem'.$menu->id;

// SEO
$microdata_itemtype = $this->params->get( 'microdata_itemtype', 'Article');
$microdata_itemtype_code = 'itemscope itemtype="http://schema.org/'.$microdata_itemtype.'"';
?>

<?php echo '<'.$mainAreaTag; ?> id="flexicontent" class="<?php echo $page_classes; ?>" <?php echo $microdata_itemtype_code; ?>>


  <?php if ($item->event->beforeDisplayContent) : ?>
		<!-- BOF beforeDisplayContent -->
		<div class="fc_beforeDisplayContent group">
			<?php echo $item->event->beforeDisplayContent; ?>
		</div>
		<!-- EOF beforeDisplayContent -->
	<?php endif; ?>

	<?php if (JFactory::getApplication()->input->getInt('print', 0)) : ?>
		<!-- BOF Print handling -->
		<?php if ($this->params->get('print_behaviour', 'auto') == 'auto') : ?>
			<script>jQuery(document).ready(function(){ window.print(); });</script>
		<?php	elseif ($this->params->get('print_behaviour') == 'button') : ?>
			<input type='button' id='printBtn' name='printBtn' value='<?php echo JText::_('Print');?>' class='btn btn-info' onclick='this.style.display="none"; window.print(); return false;'>
		<?php endif; ?>
		<!-- EOF Print handling -->

	<?php else : ?>

		<?php
		$pdfbutton = flexicontent_html::pdfbutton( $item, $this->params );
		$mailbutton = flexicontent_html::mailbutton( FLEXI_ITEMVIEW, $this->params, $item->categoryslug, $item->slug, 0, $item );
		$printbutton = flexicontent_html::printbutton( $this->print_link, $this->params );
		$editbutton = flexicontent_html::editbutton( $item, $this->params );
		$statebutton = flexicontent_html::statebutton( $item, $this->params );
		$deletebutton = flexicontent_html::deletebutton( $item, $this->params );
		$approvalbutton = flexicontent_html::approvalbutton( $item, $this->params );
		?>

		<?php if ($pdfbutton || $mailbutton || $printbutton || $editbutton || $deletebutton || $statebutton || $approvalbutton) : ?>

			<!-- BOF buttons -->
			<?php if ($this->params->get('btn_grp_dropdown')) : ?>

			<div class="buttons btn-group">
			  <button type="button" class="btn dropdown-toggle" data-toggle="dropdown">
			    <span class="<?php echo $this->params->get('btn_grp_dropdown_class', 'icon-options'); ?>"></span>
			  </button>
			  <ul class="dropdown-menu" role="menu">
			    <?php echo $pdfbutton    ? '<li>'.$pdfbutton.'</li>' : ''; ?>
			    <?php echo $mailbutton   ? '<li>'.$mailbutton.'</li>' : ''; ?>
			    <?php echo $printbutton  ? '<li>'.$printbutton.'</li>' : ''; ?>
			    <?php echo $editbutton   ? '<li>'.$editbutton.'</li>' : ''; ?>
			    <?php echo $deletebutton   ? '<li>'.$deletebutton.'</li>' : ''; ?>
			    <?php echo $approvalbutton  ? '<li>'.$approvalbutton.'</li>' : ''; ?>
			  </ul>
		    <?php echo $statebutton; ?>
			</div>

			<?php else : ?>
			<div class="buttons">
				<?php echo $pdfbutton; ?>
				<?php echo $mailbutton; ?>
				<?php echo $printbutton; ?>
				<?php echo $editbutton; ?>
				<?php echo $deletebutton; ?>
				<?php echo $statebutton; ?>
				<?php echo $approvalbutton; ?>
			</div>
			<?php endif; ?>
			<!-- EOF buttons -->

		<?php endif; ?>
	<?php endif; ?>

	<?php if ( $page_heading_shown ) : ?>
		<!-- BOF page heading -->
		<h1 class="componentheading">
			<?php echo $this->params->get('page_heading'); ?>
		</h1>
		<!-- EOF page heading -->
	<?php endif; ?>


	<?php if ($this->params->get('show_title', 1)) : ?>
		<!-- BOF item title -->
		<?php echo '<h'.$itemTitleHeaderLevel; ?> class="contentheading">
			<span class="fc_item_title" itemprop="name">
			<?php
				echo ( StringHelper::strlen($item->title) > (int) $this->params->get('title_cut_text',200) ) ?
					StringHelper::substr($item->title, 0, (int) $this->params->get('title_cut_text',200)) . ' ...'  :  $item->title;
			?>
			</span>
		<?php echo '</h'.$itemTitleHeaderLevel; ?>>
		<!-- EOF item title -->
	<?php endif; ?>


  <?php if ($item->event->afterDisplayTitle) : ?>
		<!-- BOF afterDisplayTitle -->
		<div class="fc_afterDisplayTitle group">
			<?php echo $item->event->afterDisplayTitle; ?>
		</div>
		<!-- EOF afterDisplayTitle -->
	<?php endif; ?>

	<?php if ((intval($item->modified) !=0 && $this->params->get('show_modify_date')) || ($this->params->get('show_author') && ($item->creator != "")) || ($this->params->get('show_create_date')) || (($this->params->get('show_modifier')) && (intval($item->modified) !=0))) : ?>
	<!-- BOF item basic/core info -->
	<div class="<?php echo $box_class_fields_top; ?>">

		<div class="createdline">

			<?php if (($this->params->get('show_author')) && ($item->creator != "")) : ?>
			<div class="createdby">
				<?php FlexicontentFields::getFieldDisplay($item, 'created_by', $values=null, $method='display'); ?>
				<?php echo JText::sprintf('FLEXI_WRITTEN_BY', $this->fields['created_by']->display); ?>
			</div>
			<?php endif; ?>

			<?php if (($this->params->get('show_author')) && ($item->creator != "") && ($this->params->get('show_create_date'))) : ?>
			::
			<?php endif; ?>

			<?php if ($this->params->get('show_create_date')) : ?>
			<div class="created">
				<?php FlexicontentFields::getFieldDisplay($item, 'created', $values=null, $method='display'); ?>
				<?php echo '['.JHtml::_('date', $this->fields['created']->value[0], JText::_('DATE_FORMAT_LC2')).']'; ?>
			</div>
			<?php endif; ?>

		</div>

		<div class="modifiedline">

			<?php if (($this->params->get('show_modifier')) && ($item->modifier != "")) : ?>
			<div class="modifiedby">
				<?php FlexicontentFields::getFieldDisplay($item, 'modified_by', $values=null, $method='display'); ?>
				<?php echo JText::_('FLEXI_LAST_UPDATED').' '.JText::sprintf('FLEXI_BY', $this->fields['modified_by']->display); ?>
			</div>
			<?php endif; ?>

			<?php if (($this->params->get('show_modifier')) && ($item->modifier != "") && ($this->params->get('show_modify_date'))) : ?>
			::
			<?php endif; ?>

			<?php if (intval($item->modified) !=0 && $this->params->get('show_modify_date')) : ?>
				<div class="modified">
				<?php FlexicontentFields::getFieldDisplay($item, 'modified', $values=null, $method='display'); ?>
				<?php echo '['.JHtml::_('date', $this->fields['modified']->value[0], JText::_('DATE_FORMAT_LC2')).']'; ?>
				</div>
			<?php endif; ?>

		</div>

	</div>
	<!-- EOF item basic/core info -->
	<?php endif; ?>

	<?php
		$show_vote = $this->params->get('show_vote', 1) && FlexicontentFields::getFieldDisplay($item, 'voting', $values=null, $method='display');
		$show_favs = $this->params->get('show_favs', 1) && FlexicontentFields::getFieldDisplay($item, 'favourites', $values=null, $method='display');
	?>
	
	<?php if ($show_vote || $show_favs) : ?>
	<!-- BOF item rating, favourites -->

	<div class="itemactions  group">

		<?php if ($show_vote) : ?>
			<div class="voting">
				<?php echo $this->fields['voting']->display; ?>
			</div>
		<?php endif; ?>

		<?php if ($show_favs) : ?>
			<div class="favourites">
				<?php echo $this->fields['favourites']->display; ?>
			</div>
		<?php endif; ?>

	</div>

	<!-- EOF item rating, favourites -->
	<?php endif; ?>


	<?php if (isset($item->positions['beforedescription'])) : ?>
	<!-- BOF beforedescription block -->
	<div class="<?php echo $box_class_beforedescription; ?>">
		<?php foreach ($item->positions['beforedescription'] as $field) : ?>
		<div class="flexi element field_<?php echo $field->name; ?> <?php echo $columnmode; ?>">
			<?php if ($field->label) : ?>
			<div class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></div>
			<?php endif; ?>
			<div class="flexi value field_<?php echo $field->name.' '.(!$field->label ? ' nolabel ' : ''); ?>">
				<?php echo $field->display; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
	<!-- EOF beforedescription block -->
	<?php endif; ?>

	<?php if (!$_text_via_pos): ?>
	<!-- BOF description block -->
	<div class="<?php echo $box_class_description; ?>">
	<?php echo $this->fields['text']->display; ?>
	</div>
	<!-- EOF description block -->
	<?php endif; ?>

	<?php if (isset($item->positions['afterdescription'])) : ?>
	<!-- BOF afterdescription block -->
	<div class="<?php echo $box_class_afterdescription; ?>">
		<?php foreach ($item->positions['afterdescription'] as $field) : ?>
		<div class="flexi element field_<?php echo $field->name; ?> <?php echo $columnmode; ?>">
			<?php if ($field->label) : ?>
			<div class="flexi label field_<?php echo $field->name; ?>"><?php echo $field->label; ?></div>
			<?php endif; ?>
			<div class="flexi value field_<?php echo $field->name.' '.(!$field->label ? ' nolabel ' : ''); ?>">
				<?php echo $field->display; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
	<!-- EOF afterdescription block -->
	<?php endif; ?>

	<?php if (($this->params->get('show_tags', 1)) || ($this->params->get('show_category', 1)))  : ?>
	<!-- BOF item categories, tags -->
	<div class="<?php echo $box_class_fields_bottom; ?>">
		<?php if ($this->params->get('show_category', 1)) : ?>
		<div class="categories">
			<?php FlexicontentFields::getFieldDisplay($item, 'categories', $values=null, $method='display'); ?>
			<div class="flexi label"><?php echo $this->fields['categories']->label; ?></div>
			<div class="flexi value"><i class="icon-folder-open"></i> <?php echo $this->fields['categories']->display; ?></div>
		</div>
		<?php endif; ?>

		<?php FlexicontentFields::getFieldDisplay($item, 'tags', $values=null, $method='display'); ?>
		<?php if ($this->params->get('show_tags', 1) && $this->fields['tags']->display) : ?>
		<div class="tags">
			<div class="flexi label"><?php echo $this->fields['tags']->label; ?></div>
			<div class="flexi value"><i class="icon-tags"></i> <?php echo $this->fields['tags']->display; ?></div>
		</div>
		<?php endif; ?>
	</div>
	<!-- EOF item categories, tags  -->
	<?php endif; ?>

	<?php if ($this->params->get('comments') && !JFactory::getApplication()->input->getInt('print', 0)) : ?>
		<!-- BOF comments -->
		<div class="comments group">
		<?php
			if ($this->params->get('comments') == 1) :
				if (file_exists(JPATH_SITE.DS.'components'.DS.'com_jcomments'.DS.'jcomments.php')) :
					require_once(JPATH_SITE.DS.'components'.DS.'com_jcomments'.DS.'jcomments.php');
					echo JComments::showComments($item->id, 'com_flexicontent', $this->escape($item->title));
				endif;
			endif;

			if ($this->params->get('comments') == 2) :
				if (file_exists(JPATH_SITE.DS.'plugins'.DS.'content'.DS.'jom_comment_bot.php')) :
					require_once(JPATH_SITE.DS.'plugins'.DS.'content'.DS.'jom_comment_bot.php');
					echo jomcomment($item->id, 'com_flexicontent');
				endif;
			endif;
		?>
		</div>
		<!-- EOF comments -->
	<?php endif; ?>

	<?php if ($item->event->afterDisplayContent) : ?>
	<!-- BOF afterDisplayContent -->
	<div class="fc_afterDisplayContent group">
		<?php echo $item->event->afterDisplayContent; ?>
	</div>
	<!-- EOF afterDisplayContent -->
	<?php endif; ?>

<?php echo '</'.$mainAreaTag.'>'; ?>

<?php
} // EOF XHTML
