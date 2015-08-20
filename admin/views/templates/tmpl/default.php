<?php
/**
 * @version 1.5 stable $Id: default.php 1793 2013-10-20 02:22:05Z ggppdk $
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

defined('_JEXEC') or die('Restricted access');

$tip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
$commentimage = JHTML::image ( 'administrator/components/com_flexicontent/assets/images/comment.png', JText::_( 'FLEXI_COMMENT' ), ' class="fc-man-icon-s" style="vertical-align:top;" ');

$basetemplates = array('default', 'blog', 'faq', 'items-tabbed', 'presentation');
$ctrl_task = FLEXI_J16GE ? 'task=templates.' : 'controller=templates&task=';
$form_token = FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken();
$js = "
jQuery(document).ready(function() {
	jQuery('a.deletable-template').click(function( event ) {
		var answer = confirm('".JText::_( 'FLEXI_TEMPLATE_DELETE_CONFIRM',true )."')
		if (!answer) return;
		var el = jQuery(this);
		var tmpl_name = el.attr('id').replace('del-','');
		
		el.html('<img src=\"components/com_flexicontent/assets/images/ajax-loader.gif\" align=\"center\">');
		jQuery.ajax({
			type: \"GET\",
			url:  \"index.php?option=com_flexicontent&".$ctrl_task."remove&format=raw&dir=\" + tmpl_name + \"&".$form_token."=1\",
			success: function(str) {
				el.parent().css('width','200px');
				el.parent().html(str);
			}
		});
	});
});
";
JFactory::getDocument()->addScriptDeclaration($js);

$editSingle   = JHTML::image ( 'components/com_flexicontent/assets/images/page_single_edit.png', JText::_( 'FLEXI_EDIT_LAYOUT' ), ' style="min-width:22px;" ' );
$editMultiple = JHTML::image ( 'components/com_flexicontent/assets/images/page_multiple_edit.png', JText::_( 'FLEXI_EDIT_LAYOUT' ), ' style="min-width:22px;" '  );
$editlayout = JHTML::image ( 'administrator/components/com_flexicontent/assets/images/layout_edit.png', JText::_( 'FLEXI_EDIT_LAYOUT' ), ' style="min-width:16px;" '  );
$noeditlayout = JHTML::image ( 'administrator/components/com_flexicontent/assets/images/layout_link.png', JText::_( 'FLEXI_NOEDIT_LAYOUT' ), ' style="min-width:16px;" '  );
$copytmpl = JHTML::image ( 'administrator/components/com_flexicontent/assets/images/layout_add.png', JText::_( 'FLEXI_DUPLICATE' ), ' style="min-width:16px;" '  );
$deltmpl = JHTML::image ( 'administrator/components/com_flexicontent/assets/images/layout_delete.png', JText::_( 'FLEXI_REMOVE' ), ' style="min-width:16px;" '  );

$list_total_cols = 7;
?>

<div id="flexicontent" class="flexicontent">
<form action="index.php" method="post" name="adminForm" id="adminForm">

<?php if (!empty( $this->sidebar)) : ?>
	<div id="j-sidebar-container" class="span2">
		<?php echo $this->sidebar; ?>
	</div>
	<div id="j-main-container" class="span10">
<?php else : ?>
	<div id="j-main-container">
<?php endif;?>


<div id="howto_box" class="alert alert-info">
				<h4 class="alert-heading">Configure display of your fields <span class="badge">item</span> view and <span class="badge">multi-item</span> views</h4>
															<p class="alert-message"><span class="badge badge-warning">ITEM Layout</span> Select this in configuration of <span class="badge">types</span> and (optionally) in  <span class="badge">items</span></p>
                                                            <p><span class="badge badge-warning">CATEGORY Layout</span> Select this in configuration of <span class="badge">categories / content lists</span> except for <strong>search view</strong></p>
												</div>
                                 

	
<div class="block-flat">
	
	<table class="adminlist no-border no-border-x hover">
	
	<thead class="no-border">
		<tr class="header">
			<th class="center hidden-tablet hidden-phone"><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
			<th></th>
			<th class="title"><?php echo JText::_( 'FLEXI_TEMPLATE_NAME' ); ?></th>
			<th colspan="2">
				<?php echo JText::_( 'FLEXI_SINGLE_CONTENT' ); ?><br/>
				<span class="badge badge-warning">ITEM Layout</span>
			</th>
			<th colspan="2">
				<?php echo JText::_( 'FLEXI_CONTENT_LISTS' ); ?><br/>
				<span class="badge badge-warning">CATEGORY Layout</span>
			</th>
		</tr>
	</thead>



	<tfoot>
		<tr>
			<td colspan="<?php echo $list_total_cols; ?>">
				
                <div class="row-fluid">
              <div class="span6">
              <p><?php echo $copytmpl; ?> <?php echo JText::_( 'FLEXI_DUPLICATE_TEMPLATE' ); ?></p>
              <p><?php echo $editlayout; ?> <?php echo JText::_( 'FLEXI_EDIT_LAYOUT' ); ?></p>
           </div>   
           <div class="span6">
           <p><?php echo $deltmpl; ?> <?php echo JText::_( 'FLEXI_REMOVE_TEMPLATE' ); ?></p>
           <p><?php echo $noeditlayout; ?>	<?php echo JText::_( 'FLEXI_NOEDIT_LAYOUT' ); ?></p>
           </div>   
           </div>
	</tfoot>
	
	<tbody>
		<?php
		$k = 0;
		$i = 1;
		foreach ($this->rows as $row) :
			$copylink 	= 'index.php?option=com_flexicontent&amp;view=templates&amp;layout=duplicate&amp;tmpl=component&amp;source='. $row->name;
			$itemlink	= 'index.php?option=com_flexicontent&amp;view=template&amp;type=items&amp;folder='.$row->name;
			$catlink	= 'index.php?option=com_flexicontent&amp;view=template&amp;type=category&amp;folder='.$row->name;
			
			$defaulttitle_item = !empty($row->items)    ? @ $row->items->defaulttitle    : '';
			$defaulttitle_cat  = !empty($row->category) ? @ $row->category->defaulttitle : '';
			
			$description_item = !empty($row->items)    ? @ $row->items->description    : '';
			$description_cat  = !empty($row->category) ? @ $row->category->description : '';
			?>
		<tr class="<?php echo "row$k"; ?>" id="<?php echo 'up-'.$row->name ?>">
			<td class="center hidden-tablet hidden-phone"><?php echo $i; ?></td>
			<td class="center">
				<?php if (!in_array($row->name, $basetemplates)) :?>
					<a style="margin-right: 5px" id="<?php echo 'del-' . $row->name ?>" class="deletable-template" href="javascript:;">
						<?php echo $deltmpl; ?>
					</a>
			 	<?php endif; ?>
				<a class="modal" rel="{handler: 'iframe', size: {x: 390, y: 210}}" href="<?php echo $copylink; ?>">  <?php echo $copytmpl; ?> </a>
			</td>
			<td>
				<?php echo htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8'); ?>
				<?php if (in_array($row->name, $basetemplates)) :?>
					<!--<span class="icon-lock"></span>-->
				<?php else: ?>
					<span class="icon-user"></span><span class="badge"><?php echo JText::_('FLEXI_USER').' - '.JText::_('FLEXI_CREATED'); ?></span>
				<?php endif; ?>
			</td>
			<td>
				<?php echo @$row->items ? (isset($row->items->positions) ? '<a href="'.$itemlink.'">'.$editSingle.'</a>' : $noeditlayout) : ''; ?>
			</td>
			<td>
				<?php if ($defaulttitle_item): ?>
					<span data-placement="top" class="<?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('', $description_item, 1, 1); ?>" >
						<?php echo JText::_($defaulttitle_item); ?>
					</span>
				<?php endif; ?>
			</td>
			<td>
				<?php echo @$row->category ? (isset($row->category->positions) ? '<a href="'.$catlink.'">'.$editMultiple.'</a>' : $noeditlayout) : ''; ?>
			</td>
			<td>
				<?php if ($defaulttitle_cat): ?>
					<span data-placement="top" class="<?php echo $tip_class; ?>" title="<?php echo flexicontent_html::getToolTip('', $description_cat, 1, 1); ?>" >
						<?php echo JText::_($defaulttitle_cat); ?>
					</span>
				<?php endif; ?>
			</td>
		</tr>
		<?php
		$k = 1 - $k;
		$i++;
		endforeach;
		?>
	</tbody>

	</table>

</div>	
	<div class="clear"></div>
	
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="templates" />
	<input type="hidden" name="view" value="templates" />
	<input type="hidden" name="task" value="" />
	<?php echo JHTML::_( 'form.token' ); ?>
	
	</div>
</form>
</div>
