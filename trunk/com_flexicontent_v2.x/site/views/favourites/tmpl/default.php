<?php
/**
 * @version 1.5 stable $Id: default.php 1299 2012-05-14 00:06:22Z ggppdk $
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

?>

<div id="flexicontent" class="flexicontent">

<?php if ($this->params->def( 'show_page_title', 1 )) : ?>

    <h1 class="componentheading">
		<?php echo $this->params->get('page_title'); ?>
	</h1>

<?php endif; ?>

<h2 class="flexicontent favourites">
	<?php echo JText::_( 'FLEXI_YOUR_FAVOURED_ITEMS' ).' '; ?>
</h2>

<?php if (!count($this->items)) : ?>

	<div class="note">
		<?php echo JText::_( 'FLEXI_NO_FAVOURED_ITEMS_INFO' ); ?>
	</div>

<?php else : ?>

<?php
if ($use_fields && count($fields)) {
	foreach ($this->items as $item) {
		foreach ($fields as $fieldname) {
			FlexicontentFields::getFieldDisplay($item, $fieldname, $values=null, $method='display');
		}
	}
}
?>

<?php if ($this->params->get('use_search')) : ?>
<form action="<?php echo $this->action; ?>" method="post" id="adminForm">
<div id="fc_filter" class="floattext">
	<div class="fc_fleft">
		<input type="text" name="filter" id="filter" value="<?php echo $this->lists['filter'];?>" class="text_area" onchange="document.getElementById('adminForm').submit();" />
		<button onclick="document.getElementById('adminForm').submit();"><?php echo JText::_( 'FLEXI_GO' ); ?></button>
		<button onclick="document.getElementById('filter').value='';document.getElementById('adminForm').submit();"><?php echo JText::_( 'FLEXI_RESET' ); ?></button>
	</div>
</div>
<input type="hidden" name="option" value="com_flexicontent" />
<input type="hidden" name="filter_order" value="<?php echo $this->lists['filter_order']; ?>" />
<input type="hidden" name="filter_order_Dir" value="" />
<input type="hidden" name="view" value="favourites" />
<input type="hidden" name="task" value="" />
</form>
<?php endif; ?>

<table class="flexitable" width="100%" border="0" cellspacing="0" cellpadding="0" summary="<?php echo JText::_( 'FLEXI_YOUR_FAVOURED_ITEMS' ).' '; ?>">
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
				<th id="fc_<?php echo $fieldname; ?>" ><?php echo $this->items[0]->fields[$fieldname]->label; ?></th>
				<?php endforeach; ?>
			<?php endif; ?>
		</tr>
	</thead>
	<tbody>	
	<?php
	foreach ($this->items as $item) :
		if ($image_source) {
			$src = '';
			if (!empty($item->image)) {
				$image	= unserialize($item->image);
				$src	= JURI::base(true) . '/' . $flexiparams->get('file_path') . '/' . $image['originalname'];
			} else if (!empty($midata->default_image_filepath)) {
				$src	= $midata->default_image_filepath;
			}
				
			if ($src) {
				$h		= '&amp;h=' . $img_height;
				$w		= '&amp;w=' . $img_height;
				$aoe	= '&amp;aoe=1';
				$q		= '&amp;q=95';
				$zc		= $img_method ? '&amp;zc=' . $img_method : '';
				$ext = pathinfo($src, PATHINFO_EXTENSION);
				$f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
				$conf	= $w . $h . $aoe . $q . $zc . $f;
			
				$thumb 	= JURI::base().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$src.$conf;
			}
		} else {
			$articleimage = flexicontent_html::extractimagesrc($item);
			if ($articleimage) {
			  $src	= $articleimage;
			
				$h		= '&amp;h=' . $img_height;
				$w		= '&amp;w=' . $img_width;
				$aoe	= '&amp;aoe=1';
				$q		= '&amp;q=95';
				$zc		= $img_method ? '&amp;zc=' . $img_method : '';
				$ext = pathinfo($src, PATHINFO_EXTENSION);
				$f = in_array( $ext, array('png', 'ico', 'gif') ) ? '&amp;f='.$ext : '';
				$conf	= $w . $h . $aoe . $q . $zc . $f;
			
				$base_url = (!preg_match("#^http|^https|^ftp#i", $src)) ?  JURI::base(true).'/' : '';
				$thumb = JURI::base().'components/com_flexicontent/librairies/phpthumb/phpThumb.php?src='.$base_url.$src.$conf;
			}
		}
		$item_link = JRoute::_(FlexicontentHelperRoute::getItemRoute($item->slug, $item->categoryslug));
	?>
		<tr class="sectiontableentry" >
		<?php if ($this->params->get('use_image', 1)) : ?>
			<td headers="fc_image" align="center">
				<?php if (!empty($src)) : ?>
				<a href="<?php echo $item_link; ?>" class="hasTip" title="<?php echo JText::_( 'FLEXI_READ_MORE_ABOUT' ) . '::' . $this->escape($item->title); ?>">
				<img src="<?php echo $thumb; ?>" />
				</a>
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
				<td headers="fc_<?php echo $item->fields[$fieldname]->name; ?>" ><?php echo $item->fields[$fieldname]->display; ?></th>
			<?php endforeach; ?>
		<?php endif; ?>
		
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
<?php endif; ?>

<!-- BOF pagination -->
<?php if ($this->params->get('show_pagination', 2) != 0) : ?>
<div class="pageslinks">
	<?php echo $this->pageNav->getPagesLinks(); ?>
</div>

<?php if ($this->params->get('show_pagination_results', 1)) : ?>
<p class="pagescounter">
	<?php echo $this->pageNav->getPagesCounter(); ?>
</p>
<?php
	endif;
endif; 
?>
<!-- EOF pagination -->

</div>