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
$ico_class = 'btn'; //'fc-man-icon-s';
$commentimage = JHtml::image ( 'administrator/components/com_flexicontent/assets/images/comments.png', JText::_( 'FLEXI_COMMENT' ), ' class="fc-man-icon-s" style="vertical-align:top;" ');
$loading_msg = flexicontent_html::encodeHTML(JText::_('FLEXI_LOADING') .' ... '. JText::_('FLEXI_PLEASE_WAIT'), 2);

JText::script("FLEXI_UPDATING_CONTENTS", true);
JFactory::getDocument()->addScriptDeclaration('
	function fc_template_modal_close()
	{
		window.location.reload(false);
		document.body.innerHTML = Joomla.JText._("FLEXI_UPDATING_CONTENTS") + \' <img id="page_loading_img" src="components/com_flexicontent/assets/images/ajax-loader.gif">\';
	}
');

$basetemplates = array('default', 'blog', 'faq', 'items-tabbed', 'presentation');
$ctrl_task = FLEXI_J16GE ? 'task=templates.' : 'controller=templates&task=';
$form_token = JSession::getFormToken();
$js = "
var fc_tmpls_modal;

jQuery(document).ready(function() {
	jQuery('span.deletable-template').click(function( event ) {
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

$edit_layout = htmlspecialchars(JText::_('FLEXI_EDIT_LAYOUT_N_GLOBAL_PARAMETERS', true), ENT_QUOTES, 'UTF-8');
$edit_icon         = '<span class="icon-edit"></span>';
$editSingle_icon   = $edit_icon . ' <span class="icon-file hidden-phone"></span>';
	//JHtml::image ( 'components/com_flexicontent/assets/images/page_single_edit.png', $edit_layout, ' style="min-width:22px;" class="'.$ico_class.' '.$tip_class.'" title="'.$edit_layout.'" ' );
$editMultiple_icon = $edit_icon . ' <span class="icon-stack hidden-phone"></span>';
	//JHtml::image ( 'components/com_flexicontent/assets/images/page_multiple_edit.png', $edit_layout, ' style="min-width:22px;" class="'.$ico_class.' '.$tip_class.'" title="'.$edit_layout.'" '  );
$editLayout_icon   = $editSingle_icon;
$noEditLayout_icon = '<span class="icon-edit" title="' . JText::_( 'FLEXI_NOEDIT_LAYOUT', true ) .'"></span>';
$copyTmpl_icon     = '<span class="icon-copy"></span>';
	//JHtml::image ( 'administrator/components/com_flexicontent/assets/images/layout_add.png', JText::_( 'FLEXI_DUPLICATE', true ), ' style="min-width:16px;" class="'.$ico_class.' '.$tip_class.'" title="'.JText::_( 'FLEXI_DUPLICATE', true ).'" '  );
$delTmpl_icon      = '<span class="icon-delete"></span>';
	//JHtml::image ( 'administrator/components/com_flexicontent/assets/images/layout_delete.png', JText::_( 'FLEXI_REMOVE', true ), ' style="min-width:16px;" class="'.$ico_class.' '.$tip_class.'" title="'.JText::_( 'FLEXI_REMOVE', true ).'" '  );

$list_total_cols = 8;
?>

<div id="flexicontent" class="flexicontent">
<?php if (!empty( $this->sidebar)) : ?>
	<div id="j-sidebar-container" class="span2">
		<?php echo $this->sidebar; ?>
	</div>
	<div id="j-main-container" class="span10">
<?php else : ?>
	<div id="j-main-container">
<?php endif;?>

<form action="index.php" method="post" name="adminForm" id="adminForm">

<div id="outer_templates">

<div class="row-fluid">
<div class="span12 text-right">
<button data-toggle="collapse" data-target="#howto_box" class="btn clearfix"><span class="icon-help"></span></button>
</div></div>
<div id="howto_box" class="collapse">
	<div class="alert alert-info mt-20">
		<h4 class="alert-heading">Configure display of your fields <span class="badge">item</span> view and <span class="badge">multi-item</span> views</h4>
															<p class="alert-message"><span class="badge badge-warning">ITEM Layout</span> Select this in configuration of <span class="badge">types</span> and (optionally) in  <span class="badge">items</span></p>
                                                            <p><span class="badge badge-warning">CATEGORY Layout</span> Select this in configuration of <span class="badge">categories / content lists</span> except for <strong>search view</strong></p>
	</div>
</div>
	
	
	
	

<div class="block-flat no-padding-bottom">
	<table id="adminListTableFCtemplates" class="adminlist fcmanlist">
	
	<thead>
		<tr class="header">
			<th class="text-center hidden-tablet hidden-phone"><small><?php echo JText::_( 'FLEXI_NUM' ); ?></small></th>
			<th class="text-center checker hidden-phone">
				<input type="checkbox" name="checkall-toggle" id="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
				<label for="checkall-toggle" class="green single"></label>
			</th>
			<th class="col_tmpl_edit"></th>
			<th class="text-left"><?php echo JText::_( 'FLEXI_TEMPLATE_NAME' ); ?></th>
			<th colspan="2"  class="text-left">
				<?php echo JText::_( 'FLEXI_SINGLE_CONTENT' ); ?><br class="hidden-phone">
				<span class="label label-info hidden-phone">ITEM Layout</span>
			</th>
			<th colspan="2"  class="text-left">
				<?php echo JText::_( 'FLEXI_CONTENT_LISTS' ); ?><br class="hidden-phone">
				<span class="label label-info hidden-phone">CATEGORY Layout</span>
			</th>
		</tr>
	</thead>

	<tbody>
		<?php
		$k = 0;
		$i = 0;
		// Get cached texts to avoid reloading all language files
		$item_texts = flexicontent_tmpl::getLayoutTexts('items');
		$cats_texts = flexicontent_tmpl::getLayoutTexts('category');
		foreach ($this->rows as $row) :
			$copylink 	= 'index.php?option=com_flexicontent&amp;view=templates&amp;layout=duplicate&amp;tmpl=component&amp;source='. $row->name;
			$itemlink	= 'index.php?option=com_flexicontent&amp;view=template&amp;type=items&amp;folder='.$row->name;
			$catlink	= 'index.php?option=com_flexicontent&amp;view=template&amp;type=category&amp;folder='.$row->name;
			
			$defaulttitle_item = !empty($row->items)    ? @ $item_texts->{$row->name}->title    : '';
			$defaulttitle_cat  = !empty($row->category) ? @ $cats_texts->{$row->name}->title : '';
			
			$description_item = !empty($row->items)    ? @ $item_texts->{$row->name}->description    : '';
			$description_cat  = !empty($row->category) ? @ $cats_texts->{$row->name}->description : '';
			
			$row->id = $row->name;
			$checked	= JHtml::_('grid.checkedout', $row, $i );
			?>
		<tr class="<?php echo "row$k"; ?>" id="<?php echo 'up-'.$row->name ?>">
			<td class="text-center hidden-tablet hidden-phone">
				<div class="adminlist-table-row"></div>
				<small><?php echo $i+1; ?></small>
			</td>
			<td class="text-center checker hidden-phone">
				<?php echo JHtml::_('grid.id', $i, $row->id); ?>
				<label for="cb<?php echo $i; ?>" class="green single"></label>
			</td>
			<td class="col_tmpl_edit text-right">
				<?php if (!in_array($row->name, $basetemplates)) :?>
					<span class="btn btn-small hasTooltip deletable-template" title="<?php echo JText::_('FLEXI_REMOVE_TEMPLATE', true); ?>" id="<?php echo 'del-' . $row->name ?>">
						<?php echo $delTmpl_icon; ?>
					</span>
			 	<?php endif; ?>
				<?php /*<a class="modal" onclick="javascript:;" rel="{handler: 'iframe', size: {x: 390, y: 210}}" href="<?php echo $copylink; ?>"><?php echo $copyTmpl_icon; ?></a>*/ ?>
				<span class="btn btn-small hasTooltip green" title="<?php echo JText::_('FLEXI_DUPLICATE_TEMPLATE', true); ?>" onclick="var url = jQuery(this).attr('data-href'); fc_tmpls_modal = fc_showDialog(url, 'fc_modal_popup_container', 0, 440, 300, fc_template_modal_close); return false;" data-href="<?php echo $copylink; ?>">
					<?php echo $copyTmpl_icon; ?>
				</span>
			</td>
			<td>
				<?php echo htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8'); ?>
				<?php if (in_array($row->name, $basetemplates)) :?>
					<!--<span class="icon-lock"></span>-->
				<?php else: ?>
					<span class="icon-user"></span><span class="badge"><?php echo JText::_('FLEXI_USER').' - '.JText::_('FLEXI_CREATED'); ?></span>
				<?php endif; ?>
			</td>
			<td style="padding-right: 0;"  class="tmpl-icon-box">
				<?php echo !empty($row->items)
					? '<a class="btn btn-small hasTooltip" href="'.$itemlink.'" title="'.$edit_layout.'">'.$editSingle_icon.'</a>'
					: '<span class="btn btn-small disabled">'.$noEditLayout_icon.'</span>'; ?>
			</td>
			<td style="text-align: left; padding-left: 0;">
				<?php if ($defaulttitle_item): ?>
					<span data-placement="top" class="<?php echo $tip_class; ?> hidden-phone" title="<?php echo flexicontent_html::getToolTip('', $description_item, 0, 1); ?>" >
						<i class="small icon-info"></i>
					</span>
				<span class="small hidden-phone"><?php echo $defaulttitle_item; ?></span>
				<?php endif; ?>
			</td>
			<td style="padding-right: 0;" class="tmpl-icon-box">
				<?php echo !empty($row->category)
					? '<a class="btn btn-small hasTooltip" href="'.$catlink.'" title="'.$edit_layout.'">'.$editMultiple_icon.'</a>'
					: '<span class="btn btn-small disabled">'.$noEditLayout_icon.'</span>'; ?>
			</td>
			<td style="text-align: left; padding-left: 0;" class="tmpl-icon-box">
				<?php if ($defaulttitle_cat): ?>
					<span data-placement="top" class="<?php echo $tip_class; ?> hidden-phone" title="<?php echo flexicontent_html::getToolTip('', $description_cat, 0, 1); ?>" >
						<i class="small icon-info"></i>
					</span>
					<span class="small hidden-phone"><?php echo $defaulttitle_cat; ?></span>
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
	<div class="block-flat m20 hidden-phone">
   <div class="row-fluid">
              <div class="span6">
              <p><?php echo $copyTmpl_icon; ?> <?php echo JText::_( 'FLEXI_DUPLICATE_TEMPLATE' ); ?></p>
              <p><?php echo $edit_icon; ?> <?php echo JText::_( 'FLEXI_EDIT_LAYOUT' ); ?></p>
           </div>   
           <div class="span6">
           <p><?php echo $delTmpl_icon; ?> <?php echo JText::_( 'FLEXI_REMOVE_TEMPLATE' ); ?></p>
           <p><?php echo $noEditLayout_icon; ?>	<?php echo JText::_( 'FLEXI_NOEDIT_LAYOUT' ); ?></p>
           </div>   
           </div>
 </div>
	<div class="clear"></div>
	
</div><!-- #outer_templates -->

	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="templates" />
	<input type="hidden" name="view" value="templates" />
	<input type="hidden" name="task" value="" />
	<?php echo JHtml::_( 'form.token' ); ?>
</form></div>
</div><!-- #flexicontent end -->
