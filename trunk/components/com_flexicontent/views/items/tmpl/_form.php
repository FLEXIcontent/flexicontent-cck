<?php
/**
 * @version 1.5 beta 5 $Id: form.php 85 2009-10-10 13:48:04Z vistamedia $
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
		
		<?php if ($this->user->authorize('com_flexicontent', 'newtags')) : ?>
		var tags = new tagajax('tags', {id:<?php echo $this->item->id ? $this->item->id : 0; ?>, task:'gettags'});
    	tags.fetchscreen();
    	<?php endif; ?>
    	
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
		
	<?php if ($this->user->authorize('com_flexicontent', 'newtags')) : ?>
		
		function addtag()
		{
			var tagname = document.adminForm.tagname.value;
	
			if(tagname == ''){
    			alert('<?php echo JText::_( 'FLEXI_ENTER_TAG', true); ?>' );
				return;
			}
	
			var tag = new tagajax();
    		tag.addtag( tagname );  		
    		
    		var tags = new tagajax('tags', {id:<?php echo $this->item->id ? $this->item->id : 0; ?>, task:'gettags'});
    		
    		tags.fetchscreen();
		}

	<?php endif; ?>
		
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
   				alert("<?php echo JText::_( 'FLEXI_ADD_TITLE', true ); ?>");
   				validator.handleResponse(false,form.title);
   				//form.title.focus();
   				return false;
			} else if ( form.cid.selectedIndex == -1 ) {
    			alert("<?php echo JText::_( 'FLEXI_SELECT_CATEGORY', true ); ?>");
    			//form.cid.focus();
    			return false;
  			} else {
  			<?php
			// JavaScript for extracting editor text
				echo $this->editor->save( 'text' );
			?>
				submitform(pressbutton);

				return true;
			}
		}
</script>

<div id="flexicontent" class="adminForm">

    <?php if ($this->params->def( 'show_page_title', 1 )) : ?>
    <h1 class="componentheading">
        <?php echo $this->params->get('page_title'); ?>
    </h1>
    <?php endif; ?>

	<form action="<?php echo $this->action ?>" method="post" name="adminForm">
	
		<div>
            <button type="submit" class="button" onclick="return submitbutton('save')">
        	    <?php echo JText::_( 'FLEXI_SAVE' ) ?>
        	</button>
        	<button type="reset" class="button" onclick="submitbutton('cancel')">
        	    <?php echo JText::_( 'FLEXI_CANCEL' ) ?>
        	</button>
        </div>
         
        <br class="clear" />
	
        <fieldset>
			<legend><?php echo JText::_( 'FLEXI_GENERAL' ); ?></legend>
			<div>
				<label for="title">
				<?php echo JText::_( 'FLEXI_TITLE' ).':'; ?>
				</label>
				<input class="inputbox required" type="text" id="title" name="title" value="<?php echo $this->escape($this->item->title); ?>" size="65" maxlength="254" />
			</div>

			<div>
				<label for="cid" class="cid">
					<?php echo JText::_( 'FLEXI_CATEGORIES' ).':';?>
					<span class="editlinktip hasTip" title="<?php echo JText::_ ( 'FLEXI_NOTES' ); ?>::<?php echo JText::_ ( 'FLEXI_CATEGORIES_NOTES' );?>">
					<?php echo JHTML::image ( 'components/com_flexicontent/assets/images/icon-16-hint.png', JText::_ ( 'FLEXI_NOTES' ) ); ?>
					</span>
				</label>
          		<?php echo $this->lists['cid']; ?>
			</div>
          
          	<?php if ($this->user->authorize('com_flexicontent', 'state')) : ?>
			<div class="qf_state floattext">
          		<label for="state">
				<?php echo JText::_( 'FLEXI_STATE' ).':';?>
				</label>
          		<?php echo $this->lists['state']; ?>
			</div>
			<?php endif; ?>
		</fieldset>
		
		<fieldset>
			<legend><?php echo JText::_( 'FLEXI_TAGS' ); ?></legend>
			
			<div id="tags">
        	<?php
        	if (!$this->user->authorize('com_flexicontent', 'newtags')) :
        		$n = count($this->tags);
        		for( $i = 0, $n; $i < $n; $i++ ){
					$tag = $this->tags[$i];
			
					if( ( $i % 4 ) == 0 ){
						if( $i != 0 ){
							echo '</div>';
						}
						echo '<div class="qf_tagline">';
					}
					echo '<span class="qf_tag"><span class="qf_tagidbox"><input type="checkbox" name="tag[]" value="'.$tag->id.'"' . (in_array($tag->id, $this->used) ? 'checked="checked"' : '') . ' /></span>'.$this->escape($tag->name).'</span>';	
				}
				echo '</div>';
			endif; 
			?>
			</div>
		</fieldset>

		<fieldset>
			<legend>
			<?php echo $this->item->typename ? JText::_( 'FLEXI_ITEM_TYPE' ) . ' : ' . $this->item->typename : JText::_( 'FLEXI_TYPE_NOT_DEFINED' ); ?>
			</legend>
					
			<table class="admintable">
				<?php
						foreach ($this->fields as $field) {
							// used to hide the core fields from this listing
							if ( $field->iscore == 0 || ($field->field_type == 'maintext' && (!$this->tparams->get('hide_maintext'))) ) {
							// set the specific label for the maintext field
								if ($field->field_type == 'maintext')
								{
									$field->label = $this->tparams->get('maintext_label', $field->label);
									$field->description = $this->tparams->get('maintext_desc', $field->description);
									if ($this->tparams->get('hide_html', 0))
									{
										$field->html = '
										<textarea name="text" rows="20" cols="75">'.$this->item->text.'</textarea>
										';
									} else {
										$field->html = $this->editor->display( 'text', $this->item->text, '100%;', '350', '75', '20', array('pagebreak','image') ) ;
									}
								}
						?>
						<tr>
							<td class="key">
								<label for="<?php echo $field->name; ?>" class="hasTip" title="<?php echo $field->label; ?>::<?php echo $field->description; ?>">
									<?php echo $field->label; ?>
								</label>
							</td>
							<td>
								<?php
								$noplugin = '<div id="fc-change-error" class="fc-error">'. JText::_( 'FLEXI_PLEASE_PUBLISH_PLUGIN' ) .'</div>';
								if(isset($field->html)){
									echo $field->html;
								} else {
									echo $noplugin;
								}
								?>
							</td>
						</tr>
						<?php
							}
						}
						?>
					</table>
		</fieldset>

    	<fieldset class="qf_fldst_meta">
       	<legend><?php echo JText::_( 'FLEXI_METADATA_INFORMATION' ); ?></legend>

            <div class="qf_box_left">
              	<label for="metadesc"><?php echo JText::_( 'FLEXI_META_DESCRIPTION' ); ?></label>
          		<textarea class="inputbox" cols="20" rows="5" name="metadesc" id="metadesc" style="width:250px;"><?php echo $this->item->metadesc; ?></textarea>
            </div>

            <div class="qf_box_right">
        		<label for="metakey"><?php echo JText::_( 'FLEXI_META_KEYWORDS' ); ?></label>
        		<textarea class="inputbox" cols="20" rows="5" name="metakey" id="metakey" style="width:250px;"><?php echo $this->item->metakey; ?></textarea>
            </div>
      	</fieldset>

		<br class="clear" />
        
		<input type="hidden" name="id" value="<?php echo $this->item->id; ?>" />
    	<input type="hidden" name="referer" value="<?php echo @$_SERVER['HTTP_REFERER']; ?>" />
    	<?php echo JHTML::_( 'form.token' ); ?>
    	<input type="hidden" name="task" value="" />
        
	</form>
</div>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>