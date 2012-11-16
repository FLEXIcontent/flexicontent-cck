<?php
/**
 * @version 1.5 stable $Id: default.php 1544 2012-11-12 02:50:17Z ggppdk $
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

$params = & $this->params;
$db     =& JFactory::getDBO();
$flexiparams =& JComponentHelper::getParams('com_flexicontent');

// Date configuration
$use_date   = $params->get( 'show_modify_date', 1 ) ;
$dateformat = $params->get( 'date_format', 'DATE_FORMAT_LC2' ) ;
$customdate = $params->get( 'custom_date', '' ) ;
$dateformat = ($dateformat != "DATE_FORMAT_CUSTOM") ? $dateformat : $customdate;

// Image configuration
$use_image    = (int)$params->get('use_image', 1);
$image_source = $params->get('image_source');
$img_height   = (int)$params->get('img_height', 40);
$img_width    = (int)$params->get('img_width', 40);
$img_method   = (int)$params->get('img_method', 1);

// Retrieve default image for the image field
if ($image_source) {
	$query = 'SELECT attribs FROM #__flexicontent_fields WHERE id = '.(int) $image_source;
	$db->setQuery($query);
	$midata = new stdClass();
	$midata->params = $db->loadResult();
	$midata->params = new JParameter($midata->params);
	
	$midata->default_image = $midata->params->get( 'default_image', '');
	if ( $midata->default_image !== '' ) {
		$midata->default_image_filepath = JPATH_BASE.DS.$midata->default_image;
		$midata->default_image_filename = basename($midata->default_image);
	}
}

// Extra fields configuration
$use_fields = (int)$params->get('use_fields', 1);
$fields = $params->get('fields');
$fields = array_map( 'trim', explode(',', $params->get('fields')) );
if ($fields[0]=='') $fields = array();

$page_classes  = '';
$page_classes .= $this->pageclass_sfx ? ' page'.$this->pageclass_sfx : '';
$page_classes .= ' tags tag'.$this->tag->id;

JFactory::getDocument()->addScript( JURI::base().'components/com_flexicontent/assets/js/tmpl-common.js');
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

<form action="<?php echo $this->action; ?>" method="POST" id="adminForm" onsubmit="">

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
	$img_field_size = $params->get('image_size');
	$img_field_size = $img_field_size ? $img_field_size : 'l';
	foreach ($this->items as $item) :
		$src = '';
		$thumb = '';
		if ($image_source) {
			if (!empty($item->image)) {
				$image	= unserialize($item->image);
				if ( $midata->params->get('image_source') && empty($midata->value[0]['is_default_value'] ) ) {
					$dir	 = $midata->params->get('dir') .'/'. 'item_'.$item->id.'_field_'.$image_source;
				} else {
					$dir	 = $midata->params->get('dir');
				}
				//$src	= JURI::base(true) . '/' . $flexiparams->get('file_path') . '/' . $image['originalname'];
				$src	= JURI::base(true) . '/' . $dir . '/'.$img_field_size.'_' . $image['originalname'];
			} else if (!empty($midata->default_image_filepath)) {
				$src	= $midata->default_image_filepath;
			}
		} else {
			$src = flexicontent_html::extractimagesrc($item);
			if ( !empty($src) ) {
				$base_url = (!preg_match("#^http|^https|^ftp#i", $src)) ?  JURI::base(true).'/' : '';
				$src = $base_url . $src;
			}
		}
		
		$RESIZE_FLAG = !$image_source || !$img_field_size;
		if ( $src && $RESIZE_FLAG ) {
			// Resize image when src path is set and RESIZE_FLAG: (a) using image extracted from item main text OR (b) not using image field's already created thumbnails
			$h		= '&amp;h=' . $img_height;
			$w		= '&amp;w=' . $img_width;
			$aoe	= '&amp;aoe=1';
			$q		= '&amp;q=95';
			$zc		= $img_method ? '&amp;zc=' . $img_method : '';
			$ext = pathinfo($src, PATHINFO_EXTENSION);
			$f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
			$conf	= $w . $h . $aoe . $q . $zc . $f;
			
			$thumb = JURI::base().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$src.$conf;
		} else {
			// Do not resize image when (a) image src path not set or (b) using image field's already created thumbnails
			$thumb = $src;
		}
		
		$item_link = JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug));
	?>
		<tr class="sectiontableentry" >
		<?php if ($this->params->get('use_image', 1)) : ?>
			<td headers="fc_image" align="center">
				<?php if (!empty($src)) : ?>
				
					<?php if ($this->params->get('link_image', 1)) { ?>
						<a href="<?php echo $item_link; ?>" class="hasTip" title="<?php echo JText::_( 'FLEXI_READ_MORE_ABOUT' ) . '::' . $this->escape($item->title); ?>">
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