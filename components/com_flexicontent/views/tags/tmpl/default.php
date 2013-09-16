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
jimport( 'joomla.html.parameter' );

$params =  $this->params;
$db     =  JFactory::getDBO();

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
if ($use_image && $image_source) {
	$query = 'SELECT attribs, name FROM #__flexicontent_fields WHERE id = '.(int) $image_source;
	$db->setQuery($query);
	$image_dbdata = $db->loadObject();
	//$image_dbdata->params = FLEXI_J16GE ? new JRegistry($image_dbdata->params) : new JParameter($image_dbdata->params);
	
	$img_size_map   = array('l'=>'large', 'm'=>'medium', 's'=>'small', '' => '');
	$img_field_size = $img_size_map[ $image_size ];
	$img_field_name = $image_dbdata->name;
}

// Extra fields configuration
$use_fields = (int)$params->get('use_fields', 1);
$fields = $params->get('fields');
$fields = preg_replace("/[\"'\\\]/u", "", $fields);
$fields = array_unique(preg_split("/\s*,\s*/u", $fields));
if ( !strlen($fields[0]) ) unset($fields[0]);

$page_classes  = '';
$page_classes .= $this->pageclass_sfx ? ' page'.$this->pageclass_sfx : '';
$page_classes .= ' fctags fctag'.$this->tag->id;
$menu = JSite::getMenu()->getActive();
if ($menu) $page_classes .= ' menuitem'.$menu->id; 
?>

<!--script type="text/javascript">
</script-->

<div id="flexicontent" class="flexicontent <?php echo $page_classes; ?>" >

<p class="buttons">
	<?php echo flexicontent_html::mailbutton( 'tags', $this->params, $this->tag->slug ); ?>
</p>

<?php if ( $this->params->get( 'show_page_heading', 1 ) ) : ?>
	<h1 class="componentheading">
		<?php echo $this->params->get('page_heading'); ?>
	</h1>
<?php else : ?>
	<h2 class="contentheading">
		<?php echo JText::_( 'FLEXI_ITEMS_WITH_TAG' ).' : '.$this->tag->name; ?>
	</h2>
<?php endif; ?>

<?php if (!count($this->items)) : ?>

	<div class="note">
		<?php echo JText::_( 'FLEXI_NO_ITEMS_TAGGED' ); ?>
	</div>

<?php else : ?>

<?php
if ($use_fields && count($fields)) {
	foreach ($this->items as $i => $item) {
		foreach ($fields as $fieldname) {
			// IMPORTANT: below we must use $this->items[$i], and not $item, otherwise joomla will not cache value !!!
			FlexicontentFields::getFieldDisplay($this->items[$i], $fieldname, $values=null, $method='display');
			if ( !empty($this->items[$i]->fields[$fieldname]->display) )  $found_fields[$fieldname] = 1;
		}
	}
}
?>

<form action="<?php echo $this->action; ?>" method="POST" id="adminForm" name="adminForm" onsubmit="">

<?php
	$this->params->set('use_filters',0);  // Currently not supported by the view, disable it
	$this->params->set('show_alpha',0);   // Currently not supported by the view, disable it
	
	// Body of form for (a) Text search, Field Filters, Alpha-Index, Items Total Statistics, Selectors(e.g. per page, orderby)
	// If customizing via CSS rules or JS scripts is not enough, then please copy the following file here to customize the HTML too
	include(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'tmpl_common'.DS.'listings_filter_form_body.php');
?>

<input type="hidden" name="option" value="com_flexicontent" />
<input type="hidden" name="filter_order" value="<?php echo $this->lists['filter_order']; ?>" />
<input type="hidden" name="filter_order_Dir" value="" />
<input type="hidden" name="view" value="tags" />
<input type="hidden" name="task" value="" />
<input type="hidden" name="id" value="<?php echo $this->tag->id; ?>" />
</form>

<table class="flexitable" width="100%" border="0" cellspacing="0" cellpadding="0" summary="<?php echo JText::_( 'FLEXI_ITEMS_WITH_TAG' ).' : '.$this->escape($this->tag->name); ?>">
	<thead>
		<tr>
			<?php if ($use_image) : ?>
			<th id="fc_image"><?php echo JText::_( 'FLEXI_IMAGE' ); ?></th>
			<?php endif; ?>
			<th id="fc_title"><?php echo JText::_( 'FLEXI_ITEMS' ); ?></th>
			<th id="fc_desc"><?php echo JText::_( 'FLEXI_DESCRIPTION' ); ?></th>
			<?php if ($use_date) : ?>
			<th id="fc_modified"><?php echo JText::_( 'FLEXI_LAST_UPDATED' ); ?></th>
			<?php endif; ?>
			<?php if ($use_fields && count($fields)) : ?>
				<?php foreach ($fields as $fieldname) : ?>
					<?php	if ( empty($found_fields[$fieldname]) ) continue; ?>
					<th id="fc_<?php echo $fieldname; ?>" ><?php echo $this->items[0]->fields[$fieldname]->label; ?></th>
				<?php endforeach; ?>
			<?php endif; ?>
		</tr>
	</thead>
	<tbody>	
	<?php
	foreach ($this->items as $i => $item) :
		if ($use_image) {
			$src = '';
			$thumb = '';
			if ($image_source)
			{
				FlexicontentFields::getFieldDisplay($item, $img_field_name, null, 'display', 'module');
				$img_field = $item->fields[$img_field_name];
				if ( !$img_field_size ) {
					$src = str_replace(JURI::root(), '',  $img_field->thumbs_src['large'][0] );
				} else {
					$thumb = $img_field->thumbs_src[ $img_field_size ][0];
				}
			} else {
				$src = flexicontent_html::extractimagesrc($item);
			}
			
			$RESIZE_FLAG = !$image_source || !$img_field_size;
			if ( $src && $RESIZE_FLAG ) {
				// Resize image when src path is set and RESIZE_FLAG: (a) using image extracted from item main text OR (b) not using image field's already created thumbnails
				$h		= '&amp;h=' . $image_height;
				$w		= '&amp;w=' . $image_width;
				$aoe	= '&amp;aoe=1';
				$q		= '&amp;q=95';
				$zc		= $image_method ? '&amp;zc=' . $image_method : '';
				$ext = pathinfo($src, PATHINFO_EXTENSION);
				$f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
				$conf	= $w . $h . $aoe . $q . $zc . $f;
				
				$base_url = (!preg_match("#^http|^https|^ftp#i", $src)) ?  JURI::base(true).'/' : '';
				$thumb = JURI::base(true).'/components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$base_url.$src.$conf;
			} else {
				// Do not resize image when (a) image src path not set or (b) using image field's already created thumbnails
			}
		}
		$item_link = JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug));
		
		$fc_item_classes = 'sectiontableentry';
		foreach ($item->categories as $item_cat) {
			$fc_item_classes .= ' fc_itemcat_'.$item_cat->id;
		}
		$fc_item_classes .= $item->has_access ? ' fc_item_has_access' : ' fc_item_no_access';
	?>
		<tr id="tablelist_item_<?php echo $i; ?>" class="<?php echo $fc_item_classes; ?>">
		<?php if ($use_image) : ?>
			<td headers="fc_image" align="center">
				<?php if (!empty($thumb)) : ?>
				
					<?php if ($this->params->get('link_image', 1)) { ?>
						<a href="<?php echo $item_link; ?>" class="hasTip" title="<?php echo JText::_( 'FLEXI_READ_MORE_ABOUT' ) . '::' . htmlspecialchars($item->title, ENT_COMPAT, 'UTF-8'); ?>">
							<img src="<?php echo $thumb; ?>" />
						</a>
					<?php } else { ?>
						<img src="<?php echo $thumb; ?>" />
					<?php } ?>
					
				<?php else : ?>
					<small><i><?php echo JText::_('FLEXI_NO_IMAGE'); ?></i></small>
				<?php endif; ?>
			</td>
		<?php endif; ?>
			<td headers="fc_title">
				<a href="<?php echo $item_link; ?>"><?php echo $this->escape($item->title); ?></a>
			</td>
			<td headers="fc_intro">
				<?php echo flexicontent_html::striptagsandcut( $item->introtext, 150 ); ?>
			</td>
		<?php if ($use_date) : ?>
			<td headers="fc_modified">
				<?php echo JHTML::_( 'date', ($item->modified ? $item->modified : $item->created), JText::_($dateformat) ); ?>		
			</td>
		<?php endif; ?>
		
		<?php if ($use_fields && count($fields)) : ?>
			<?php foreach ($fields as $fieldname) : ?>
				<?php	if ( empty($found_fields[$fieldname]) ) continue; ?>
				<td headers="fc_<?php echo $item->fields[$fieldname]->name; ?>" ><?php echo $item->fields[$fieldname]->display; ?></th>
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