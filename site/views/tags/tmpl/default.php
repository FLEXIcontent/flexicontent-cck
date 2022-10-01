<?php
/**
 * @version 1.5 stable $Id: default.php 1764 2013-09-16 08:00:21Z ggppdk $
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

$params =  $this->params;
$db     =  JFactory::getDbo();

// Date configuration
$use_date   = $params->get( 'show_modify_date', 1 ) ;
$dateformat = $params->get( 'date_format', 'DATE_FORMAT_LC2' ) ;
$customdate = $params->get( 'custom_date', '' ) ;
$dateformat = ($dateformat != "DATE_FORMAT_CUSTOM") ? $dateformat : $customdate;

// Image configuration
$use_image    = (int)$params->get('use_image', 1);
$image_source = $params->get('image_source');
$image_height = (int)$params->get('image_height', 40);
$image_width  = (int)$params->get('image_width', 40);
$image_method = (int)$params->get('image_method', 1);
$image_size		= $params->get('image_size', '');

// Retrieve default image for the image field
if ($use_image && $image_source)
{
	$query = 'SELECT attribs, name FROM #__flexicontent_fields WHERE id = ' . (int) $image_source;
	$image_dbdata = $db->setQuery($query)->loadObject();
	//$image_dbdata->params = new JRegistry($image_dbdata->params);
	
	$img_size_map   = array('l'=>'large', 'm'=>'medium', 's'=>'small', '' => '');
	$img_field_size = $img_size_map[ $image_size ];
	$img_field_name = $image_dbdata->name;
}

// Extra fields configuration
$use_fields = (int)$params->get('use_fields', 1);
$fields = $params->get('fields', '');
$fields = preg_replace("/[\"'\\\]/u", "", $fields);
$fields = array_unique(preg_split("/\s*,\s*/u", $fields));
if ( !strlen($fields[0]) ) unset($fields[0]);

$page_classes  = '';
$page_classes .= $this->pageclass_sfx ? ' page'.$this->pageclass_sfx : '';
$page_classes .= ' fctags fctag'.$this->tag->id;
$menu = JFactory::getApplication()->getMenu()->getActive();
if ($menu) $page_classes .= ' menuitem'.$menu->id; 
?>

<!--script>
</script-->

<div id="flexicontent" class="flexicontent <?php echo $page_classes; ?>" >


<!-- BOF buttons -->
<?php
if (JFactory::getApplication()->input->getInt('print', 0)) {
	if ($this->params->get('print_behaviour', 'auto') == 'auto') : ?>
		<script>jQuery(document).ready(function(){ window.print(); });</script>
	<?php	elseif ($this->params->get('print_behaviour') == 'button') : ?>
		<input type='button' id='printBtn' name='printBtn' value='<?php echo JText::_('Print');?>' class='btn btn-info' onclick='this.style.display="none"; window.print(); return false;'>
	<?php endif;
} else {
	$pdfbutton = '';
	$mailbutton = flexicontent_html::mailbutton( 'tags', $this->params, $this->tag->slug );
	$printbutton = flexicontent_html::printbutton( $this->print_link, $this->params );
	if ($pdfbutton || $mailbutton || $printbutton) {
	?>
	<div class="buttons">
		<?php echo $pdfbutton; ?>
		<?php echo $mailbutton; ?>
		<?php echo $printbutton; ?>
	</div>
	<?php }
}
?>
<!-- EOF buttons -->

<?php if ( $this->params->get( 'show_page_heading', 1 ) ) : ?>
	<h1 class="componentheading">
		<?php echo $this->params->get('page_heading'); ?>
	</h1>
<?php else : ?>
	<h2 class="contentheading">
		<?php echo JText::_( 'FLEXI_TAG' ).' : '.$this->tag->name; ?>
	</h2>
<?php endif; ?>


<?php
$items	= & $this->items;
?>

<?php if (!count($items)) : ?>

	<div class="note">
		<?php echo JText::_( 'FLEXI_NO_ITEMS_TAGGED' ); ?>
	</div>

<?php else : ?>

<?php
	$_read_more_about = JText::_( 'FLEXI_READ_MORE_ABOUT' );
	$tooltip_class = FLEXI_J30GE ? 'hasTooltip' : 'hasTip';
	
	unset($item);  // just in case there is reference
	if ($use_fields && count($fields)) {
		foreach ($items as $i => $item) {
			foreach ($fields as $fieldname) {
				// IMPORTANT: below we must use $items[$i], and not $item, otherwise joomla will not cache value !!!
				FlexicontentFields::getFieldDisplay($items[$i], $fieldname, $values=null, $method='display');
				if ( !empty($items[$i]->fields[$fieldname]->display) )  $found_fields[$fieldname] = 1;
			}
		}
	}
?>

<form action="<?php echo $this->action; ?>" method="post" name="adminForm" id="adminForm">

<?php
	// Filtering form features not supported, will have been disabled in the view.htmnl.php
	// this is needed since some of these, when parameter is not set, are defaulting to 'yes'
	
	// Body of form for (a) Text search, Field Filters, Alpha-Index, Items Total Statistics, Selectors(e.g. per page, orderby)
	// If customizing via CSS rules or JS scripts is not enough, then please copy the following file here to customize the HTML too
	include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'tmpl_common'.DS.'listings_filter_form_body.php');
?>

<input type="hidden" id="filter_order" name="filter_order" value="<?php echo $this->lists['filter_order']; ?>" />
<input type="hidden" id="filter_order_Dir" name="filter_order_Dir" value="" />

<input type="hidden" name="option" value="com_flexicontent" />
<input type="hidden" name="view" value="tags" />
<input type="hidden" name="task" value="" />

<input type="hidden" name="id" value="<?php echo $this->tag->id; ?>" />
</form>

<table id="flexitable" class="flexitable">
	<thead>
		<tr>
			<?php if ($use_image) : ?>
			<th id="fc_image"><?php echo JText::_( 'FLEXI_IMAGE' ); ?></th>
			<?php endif; ?>
			<th id="fc_title"><?php echo JText::_( 'FLEXI_TITLE' ); ?></th>
			<th id="fc_desc"><?php echo JText::_( 'FLEXI_DESCRIPTION' ); ?></th>
			<?php if ($use_date) : ?>
			<th id="fc_modified"><?php echo JText::_( 'FLEXI_LAST_UPDATED' ); ?></th>
			<?php endif; ?>
			<?php if ($use_fields && count($fields)) : ?>
				<?php foreach ($fields as $fieldname) : ?>
					<?php	if ( empty($found_fields[$fieldname]) ) continue; ?>
					<th id="fc_<?php echo $fieldname; ?>" ><?php echo $items[0]->fields[$fieldname]->label; ?></th>
				<?php endforeach; ?>
			<?php endif; ?>
		</tr>
	</thead>
	<tbody>	
	<?php
	foreach ($items as $i => $item) :
		if ($use_image)
		{
			$src = '';
			$thumb = '';
			if ($image_source)
			{
				$imageurl = FlexicontentFields::getFieldDisplay($item, $img_field_name, null, 'display_' . ($img_field_size ?: 'large') . '_src', 'module');

				if ($imageurl)
				{
					$img_field = $item->fields[$img_field_name];
					!$img_field_size
						? $src = str_replace(JUri::root(), '',  $img_field->thumbs_src['large'][0])
						: $thumb = $img_field->thumbs_src[ $img_field_size ][0];
				}
			}
			else
			{
				$src = flexicontent_html::extractimagesrc($item);
			}
			
			$RESIZE_FLAG = !$image_source || !$img_field_size;
			if ( $src && $RESIZE_FLAG ) {
				// Resize image when src path is set and RESIZE_FLAG: (a) using image extracted from item main text OR (b) not using image field's already created thumbnails
				$h		= '&amp;h=' . $image_height;
				$w		= '&amp;w=' . $image_width;
				$aoe	= '&amp;aoe=1';
				$q		= '&amp;q=95';
				$ar 	= '&amp;ar=x';
				$zc		= $image_method ? '&amp;zc=' . $image_method : '';
				$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
				$f = in_array( $ext, array('png', 'gif', 'jpeg', 'jpg', 'webp', 'wbmp', 'bmp', 'ico') ) ? '&amp;f='.$ext : '';
				$conf	= $w . $h . $aoe . $q . $ar . $zc . $f;
				
				$base_url = (!preg_match("#^http|^https|^ftp|^/#i", $src)) ?  JUri::base(true).'/' : '';
				$thumb = JUri::base(true).'/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$base_url.$src.$conf;
			} else {
				// Do not resize image when (a) image src path not set or (b) using image field's already created thumbnails
			}
		}
		$item_link = JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug, 0, $item));
		
		$fc_item_classes = 'sectiontableentry';
		foreach ($item->categories as $item_cat) {
			$fc_item_classes .= ' fc_itemcat_'.$item_cat->id;
		}
		$fc_item_classes .= $item->has_access ? ' fc_item_has_access' : ' fc_item_no_access';
		
		$markup_tags = '<span class="fc_mublock">';
		foreach($item->css_markups as $grp => $css_markups) {
			if ( empty($css_markups) )  continue;
			$fc_item_classes .= ' fc'.implode(' fc', $css_markups);
			
			$ecss_markups  = $item->ecss_markups[$grp];
			$title_markups = $item->title_markups[$grp];
			foreach($css_markups as $mui => $css_markup) {
				$markup_tags .= '<span class="fc_markup mu' . $css_markups[$mui] . $ecss_markups[$mui] .'">' .$title_markups[$mui]. '</span>';
			}
		}
		$markup_tags .= '</span>';
	?>
		<tr id="tablelist_item_<?php echo $i; ?>" class="<?php echo $fc_item_classes; ?>">
		<?php if ($use_image) : ?>
			<td headers="fc_image">
				<?php if (!empty($thumb)) : ?>
				
					<?php $title_encoded = htmlspecialchars($item->title, ENT_COMPAT, 'UTF-8'); ?>
					<?php if ($this->params->get('link_image', 1)) { ?>
						<a href="<?php echo $item_link; ?>" >
							<img src="<?php echo $thumb; ?>" alt="<?php echo $title_encoded; ?>" class="<?php echo $tooltip_class;?>" title="<?php echo flexicontent_html::getToolTip($_read_more_about, $title_encoded, 0, 0); ?>"/>
						</a>
					<?php } else { ?>
						<img src="<?php echo $thumb; ?>" alt="<?php echo $title_encoded; ?>" title="<?php echo $title_encoded; ?>" />
					<?php } ?>
					
				<?php else : ?>
					<small><i><?php echo JText::_('FLEXI_NO_IMAGE'); ?></i></small>
				<?php endif; ?>
			</td>
		<?php endif; ?>
			<td headers="fc_title">
				<a href="<?php echo $item_link; ?>">
					<?php echo $this->escape($item->title); ?>
				</a>
				<?php echo $markup_tags; ?>
			</td>
			<td headers="fc_desc">
				<?php echo flexicontent_html::striptagsandcut( $item->introtext, 150 ); ?>
			</td>
		<?php if ($use_date) : ?>
			<td headers="fc_modified">
				<?php echo JHtml::_( 'date', ($item->modified ? $item->modified : $item->created), JText::_($dateformat) ); ?>		
			</td>
		<?php endif; ?>
		
		<?php if ($use_fields && count($fields)) : ?>
			<?php foreach ($fields as $fieldname) : ?>
				<?php	if ( empty($found_fields[$fieldname]) ) continue; ?>
				<td headers="fc_<?php echo $item->fields[$fieldname]->name; ?>" ><?php echo $item->fields[$fieldname]->display; ?></td>
			<?php endforeach; ?>
		<?php endif; ?>
		
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
<?php endif; ?>

<!-- BOF pagination -->
<?php
	// If customizing via CSS rules or JS scripts is not enough, then please copy the following file here to customize the HTML too
	include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'tmpl_common'.DS.'pagination.php');
?>
<!-- EOF pagination -->

</div>