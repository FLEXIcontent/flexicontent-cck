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
?>

<script language="javascript" type="text/javascript">
Window.onDomReady(function(){
	document.formvalidator.setHandler('cid',
		function (value) {
			if(value == -1) {
				return true;
			} else {
				timer = new Date();
				time = timer.getTime();
				regexp = new Array();
				regexp[time] = new RegExp('^[1-9]{1}[0-9]{0,}$');
				return regexp[time].test(value);
			}
		}
	);
});
	
function submitbutton( pressbutton ) {

	if (pressbutton == 'cancel') {
		submitform( pressbutton );
		return;
	}

	var form = document.adminForm;
	var validator = document.formvalidator;
	var title = $(form.title).getValue();
	title.replace(/\s/g,'');

	if ( title.length==0 ) {
		//alert("<?php echo JText::_( 'FLEXI_ADD_TITLE', true ); ?>");
		validator.handleResponse(false,form.title);
		var invalid = $$('.invalid');
		new Fx.Scroll(window).toElement(invalid[0]);
		invalid[0].focus();
			//form.title.focus();
			return false;
	} else if ( form.cid.selectedIndex == -1 ) {
		//alert("<?php echo JText::_( 'FLEXI_SELECT_CATEGORY', true ); ?>");
		validator.handleResponse(false,form.cid);
		var invalid = $$('.invalid');
		new Fx.Scroll(window).toElement(invalid[0]);
		invalid[0].focus();
		return false;
	} else {
	<?php echo $this->editor->save( 'text' ); ?>
	submitform(pressbutton);
	return true;
	}
}
</script>

<div id="flexicontent" class="adminForm flexi_edit">

    <?php if ($this->params->def( 'show_page_title', 1 )) : ?>
    <h1 class="componentheading">
        <?php echo $this->params->get('page_title'); ?>
    </h1>
    <?php endif; ?>

	<form action="<?php echo $this->action ?>" method="post" name="adminForm">
	
		<div class="flexi_buttons">
            <button type="submit" class="button" onclick="return submitbutton('save')">
        	    <?php echo JText::_( 'FLEXI_SAVE' ) ?>
        	</button>
        	<button type="reset" class="button" onclick="submitbutton('cancel')">
        	    <?php echo JText::_( 'FLEXI_CANCEL' ) ?>
        	</button>
        </div>
         
        <br class="clear" />
	
        <fieldset class="flexi_general">
			<legend><?php echo JText::_( 'FLEXI_GENERAL' ); ?></legend>
			<div class="flexi_formblock">
				<label for="title" class="flexi_label">
				<?php echo JText::_( 'FLEXI_TITLE' ).':'; ?>
				</label>
				<input class="inputbox required" type="text" id="title" name="title" value="<?php echo $this->escape($this->item->title); ?>" size="65" maxlength="254" />
			</div>

			<div class="flexi_formblock">
				<label for="cid" class="flexi_label">
					<?php echo JText::_( 'FLEXI_CATEGORIES' ).':';?>
					<?php if ($this->perms['multicat']) : ?>
					<span class="editlinktip hasTip" title="<?php echo JText::_ ( 'FLEXI_NOTES' ); ?>::<?php echo JText::_ ( 'FLEXI_CATEGORIES_NOTES' );?>">
						<?php echo JHTML::image ( 'components/com_flexicontent/assets/images/icon-16-hint.png', JText::_ ( 'FLEXI_NOTES' ) ); ?>
					</span>
					<?php endif; ?>
				</label>
          		<?php echo $this->lists['cid']; ?>
			</div>
          
			<?php if ($this->perms['canpublish']) : ?>
			<div class="flexi_formblock">
          		<label for="state" class="flexi_label">
				<?php echo JText::_( 'FLEXI_STATE' ).':';?>
				</label>
          		<?php echo $this->lists['state']; ?>
			</div>
			<?php endif; ?>
		</fieldset>
		
		<?php if ($this->perms['cantags']) : ?>
		<fieldset class="flexi_tags">
			<legend><?php echo JText::_( 'FLEXI_TAGS' ); ?></legend>
			
			<div id="tags">
        	<?php
    		$n = count($this->tags);
    		if ($n) {
        		echo '<div class="flexi_tagbox"><ul>';
        		for( $i = 0, $n; $i < $n; $i++ )
        		{
					$tag = $this->tags[$i];
					echo '<li><div><input type="checkbox" name="tag[]" value="'.$tag->id.'"' . (in_array($tag->id, $this->used) ? 'checked="checked"' : '') . ' /></span>'.$this->escape($tag->name).'</div></li>';	
				}
				echo '</ul></div>';
			}
			?>
			</div>
		</fieldset>
		<?php endif; ?>

		<fieldset class="flexi_fields">
			<legend>
			<?php echo JText::_( 'FLEXI_ITEM_TYPE_ARTICLE' ); ?>
			<?php // echo $this->item->typename ? JText::_( 'FLEXI_ITEM_TYPE' ) . ' : ' . $this->item->typename : JText::_( 'FLEXI_TYPE_NOT_DEFINED' ); ?>
			</legend>
			<?php echo $this->editor->display( 'text', $this->item->text, '100%;', '350', '75', '20', array('pagebreak','image') ); ?>
		</fieldset>

		<?php if ($this->perms['canparams']) : ?>
    	<fieldset class="flexi_meta">
       	<legend><?php echo JText::_( 'FLEXI_METADATA_INFORMATION' ); ?></legend>

            <div class="flexi_box_left">
              	<label for="metadesc"><?php echo JText::_( 'FLEXI_META_DESCRIPTION' ); ?></label>
          		<textarea class="inputbox" cols="20" rows="5" name="metadesc" id="metadesc" style="width:250px;"><?php echo $this->item->metadesc; ?></textarea>
            </div>

            <div class="flexi_box_right">
        		<label for="metakey"><?php echo JText::_( 'FLEXI_META_KEYWORDS' ); ?></label>
        		<textarea class="inputbox" cols="20" rows="5" name="metakey" id="metakey" style="width:250px;"><?php echo $this->item->metakey; ?></textarea>
            </div>
      	</fieldset>
		<?php endif; ?>

		<br class="clear" />
        
		<input type="hidden" name="id" value="<?php echo $this->item->id; ?>" />
    	<input type="hidden" name="referer" value="<?php echo str_replace(array('"', '<', '>', "'"), '', @$_SERVER['HTTP_REFERER']); ?>" />
    	<?php echo JHTML::_( 'form.token' ); ?>
    	<input type="hidden" name="task" value="" />

	</form>
</div>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>