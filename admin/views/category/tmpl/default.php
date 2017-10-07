<?php
/**
 * @version 1.5 stable $Id: default.php 1079 2012-01-02 00:18:34Z ggppdk $
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

$useAssocs = flexicontent_db::useAssociations();

//keep session alive while editing
JHtml::_('behavior.keepalive');

// Load JS tabber lib
$this->document->addScriptVersion(JUri::root(true).'/components/com_flexicontent/assets/js/tabber-minimized.js', FLEXI_VHASH);
$this->document->addStyleSheetVersion(JUri::root(true).'/components/com_flexicontent/assets/css/tabber.css', FLEXI_VHASH);
$this->document->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs
$js = "
	jQuery(document).ready(function(){
		fc_bindFormDependencies('#flexicontent', 2, '.control-group');
	});
";
$this->document->addScriptDeclaration($js);
?>

<div id="flexicontent" class="flexicontent">
<form action="index.php" method="post" name="adminForm" id="adminForm" class="form-validate form-horizontal">

<div class="row-fluid">
	<div class="span12">
		<h1 class="contentx">
			<?php
			if ( $this->form->getValue( 'title' ) == '' ) {
				echo 'New Category';
			} else {
				echo $this->form->getValue( 'title' );
			};
			?>
		</h1>
	</div>
</div>
<?php
// *****************
// MAIN TABSET START
// *****************
global $tabSetCnt;
array_push( $tabSetStack, $tabSetCnt );
$tabSetCnt = ++$tabSetMax;
$tabCnt[ $tabSetCnt ] = 0;
?>
<script>
	/* tab memory */
	jQuery( function ( $ ) {
		var json, tabsState;
		$( 'a[data-toggle="pill"], a[data-toggle="tab"]' ).on( 'shown', function ( e ) {
			var href, json, parentId, tabsState;

			tabsState = localStorage.getItem( "tabs-state" );
			json = JSON.parse( tabsState || "{}" );
			parentId = $( e.target ).parents( "ul.nav.nav-pills, ul.nav.nav-tabs" ).attr( "id" );
			href = $( e.target ).attr( 'href' );
			json[ parentId ] = href;

			return localStorage.setItem( "tabs-state", JSON.stringify( json ) );
		} );

		tabsState = localStorage.getItem( "tabs-state" );
		json = JSON.parse( tabsState || "{}" );

		$.each( json, function ( containerId, href ) {
			if ( !$( "body" ).hasClass( "task-add" ) ) {
				return $( "#" + containerId + " a[href=" + href + "]" ).tab( 'show' );
			}
		} );

		$( "ul.nav.nav-pills, ul.nav.nav-tabs" ).each( function () {
			var $this = $( this );
			if ( !json[ $this.attr( "id" ) ] ) {
				return $this.find( "a[data-toggle=tab]:first, a[data-toggle=pill]:first" ).tab( "show" );
			}
		} );


		////VALIDATOR SWITCH TO TAB 

		$( "#toolbar .buttons.btn-group a" ).click( function () {


			$( '.invalid' ).each( function () {
				var id = $( '.tab-pane' ).find( ':required:invalid' ).closest( '.tab-pane' ).attr( 'id' );

				$( '.nav a[href="#' + id + '"]' ).tab( 'show' );
			} );

		} );

	} );
</script>
	
<?php
$options = array(
    'active' => 'tab' . $tabCnt[$tabSetCnt] . ''
);
echo JHtml::_('bootstrap.startTabSet', 'ID-Tabs-' . $tabSetCnt . '', $options, array(
    'useCookie' => 1
));
?> 
           <!--FLEXI_Description TAB1 --> 
<?php
echo JHtml::_( 'bootstrap.addTab', 'ID-Tabs-' . $tabSetCnt . '', 'tab' . $tabCnt[ $tabSetCnt ]++ . '', '<i class="icon-star"></i><br class="hidden-phone">BASIC' );
?>
           <!--LETS SPLIT INTO 2 COLUMNS-->
<div class="row-fluid">
	<div class="span8 full_width_980">



		<!--CONTENT-->
		<div class="control-group">
			<div class="control-label">
				<label class="required" for="jform_title" id="jform_title-lbl">
					<?php
					echo jtext::_( "FLEXI_TITLE" );
					?>
				</label>
			</div>
			<div class="controls">
				<?php
				echo $this->form->getInput( 'title' );
				?>
			</div>
		</div>
		<!--CONTENT-->
		<!--ALIAS-->
		<div class="control-group">
			<div class="control-label">
				<label for="jform_alias" id="jform_alias-lbl">
					<?php
					echo jtext::_( "FLEXI_ALIAS" );
					?>
				</label>
			</div>
			<div class="controls">
				<?php
				echo $this->form->getInput( 'alias' );
				?>
			</div>
		</div>
		<!--/ALIAS-->
		<!--PUBLISHED-->
		<div class="control-group">
			<div class="control-label">
				<?php
				echo $this->form->getLabel( 'published' );
				?>
			</div>
			<div class="controls">
				<?php
				echo $this->form->getInput( 'published' );
				?><i class="icon-calendar ml-5"></i>
			</div>
		</div>
		<!--/PUBLISHED-->
		<!--CAT-->
		<div class="control-group">
			<div class="control-label">
				<?php
				echo $this->form->getLabel( 'parent_id' );
				?>
			</div>
			<div class="controls">
				<?php
				echo $this->Lists[ 'parent_id' ];
				?><i class="icon-folder ml-5"></i>
			</div>
		</div>
		<!--/CAT-->
		<!--LANGUAGE-->
		<div class="h_lang">
			<div class="control-group">
				<div class="control-label">
					<?php
					echo $this->form->getLabel( 'language' );
					?>
				</div>
				<div class="controls">
					<?php
					echo $this->form->getInput( 'language' );
					?><i class="icon-comments-2 ml-5 movel"></i>
				</div>
			</div>
		</div>
		<!--END-->
		<?php
		echo $this->form->getLabel( 'description' );
		?>
		<div class="pr-1">
		<?php
		echo $this->form->getInput( 'description' );
		?>
		</div>
	</div>
	<!--/SPAN8-->
<div class="span4 full_width_980 off-white">
	<!--RIGHT COLUMN-->
<!--START RIGHT ACCORDION-->
<?php echo JHtml::_('bootstrap.startAccordion', 'right-accordion-1', array('active' => 'slide1_id', 'parent' => 'right-accordion-1')); ?>


 <?php
if ($useAssocs):
?> 
<!--START: Slide #1-->
<?php echo JHtml::_('bootstrap.addSlide', 'right-accordion-1', JText::_('FLEXI_ASSOCIATIONS'), 'slide1_id', 'accordion-toggle'); ?>

<?php
    echo $this->loadTemplate('associations');
?> 

<?php echo JHtml::_('bootstrap.endSlide'); ?>
<!--END: Slide #1-->
<?php
endif;
?> 

<!--START: Slide #2-->
	<?php echo JHtml::_('bootstrap.addSlide', 'right-accordion-1', JText::_('FLEXI_PUBLISHING'), 'slide2_id', 'accordion-toggle'); ?>

	<?php
		echo JLayoutHelper::render( 'joomla.edit.publishingdata', $this );
	?>

	<?php echo JHtml::_('bootstrap.endSlide'); ?>
<!--END: Slide #2-->






<?php echo JHtml::_('bootstrap.endAccordion'); ?>
<!--END RIGHT ACCORDION-->
	<!--/RIGHT COLUMN-->
</div>

</div><!--.row-fluid-->
<?php
echo JHtml::_('bootstrap.endTab');
?> 
           <!--/FLEXI_Description TAB1 --> 
           
           
           
<!--TAB2 IMAGE--> 
<?php
echo JHtml::_( 'bootstrap.addTab', 'ID-Tabs-' . $tabSetCnt . '', 'tab' . $tabCnt[ $tabSetCnt ]++ . '', JText::_( '<i class="icon-image"></i><br class="hidden-phone">' . JText::_( 'FLEXI_IMAGE' ) ) );
?>
<?php
$fieldSet = $this->form->getFieldset( 'cat_basic' );

if ( isset( $fieldSet->description ) && trim( $fieldSet->description ) ):
	echo '<div class="fc-mssg fc-info">' . JText::_( $fieldSet->description ) . '</div>';
endif;
?>
<?php
foreach ( $fieldSet as $field ):
	echo( $field->getAttribute( 'type' ) == 'separator' || $field->hidden ) ? $field->input : ' 

                                            <div class="control-group"> 
                                                <div class="control-label">' . $field->label . '</div> 
                                                <div class="controls"> 
                                                    ' . $field->input /* non-inherited */ . ' 
                                                </div> 
                                            </div> 
                                            ';
endforeach;
?>
<?php
echo JHtml::_( 'bootstrap.endTab' );
?>
           <!--/TAB2 IMAGE --> 
           
           <!--TAB3 - META_SEO--> 
<?php
echo JHtml::_( 'bootstrap.addTab', 'ID-Tabs-' . $tabSetCnt . '', 'tab' . $tabCnt[ $tabSetCnt ]++ . '', JText::_( '<i class="icon-bookmark"></i><br class="hidden-phone">' . JText::_( 'FLEXI_META_SEO' ) ) );
?>
<?php
/*echo JLayoutHelper::render('joomla.edit.metadata', $this);*/
?>
<?php
$fieldnames_arr = array(
	'metadesc' => null,
	'metakey' => null
);
foreach ( $this->form->getGroup( 'metadata' ) as $field )
	$fieldnames_arr[ $field->fieldname ] = 'metadata';

foreach ( $fieldnames_arr as $fieldnames => $groupname ) {
	foreach ( ( array )$fieldnames as $f ) {
		$field = $this->form->getField( $f, $groupname );
		if ( !$field )
			continue;

		echo( $field->getAttribute( 'type' ) == 'separator' || $field->hidden ) ? $field->input : ' 
                                                <div class="control-group"> 
                                                    <div class="control-label">' . $field->label . '</div> 
                                                    <div class="controls"> 
                                                        ' . $field->input /* non-inherited */ . ' 
                                                    </div> 
                                                </div> 
                                                ';
	}
}
?>
<?php
echo JHtml::_( 'bootstrap.endTab' );
?>
<!--/TAB3 - META_SEO-->
 
                     <!--TAB3.5 - CONFIGURATION--> 
<?php
echo JHtml::_( 'bootstrap.addTab', 'ID-Tabs-' . $tabSetCnt . '', 'tab' . $tabCnt[ $tabSetCnt ]++ . '', JText::_( '<i class="icon-options"></i><br class="hidden-phone">' . JText::_( 'SETTINGS' ) ) );
?>

<div class="sub-tab">
<!--
/*
 *	=======================================================================
	+ LAYOUT
 *	=======================================================================
 */
-->
<?php
$options = array(
    'active' => 'tab' . $tabCnt[$tabSetCnt] . ''
);
echo JHtml::_('bootstrap.startTabSet', 'tabset_cat_params-' . $tabSetCnt . '', $options, array(
    'useCookie' => 1
));
?> 
<!-- 1. TAB SETTINGS - DISPLAY --> 
<?php
echo JHtml::_('bootstrap.addTab', 'tabset_cat_params-' . $tabSetCnt . '', 'tab' . $tabCnt[$tabSetCnt]++ . '', '<i class="icon-info-2 mr-5"></i>' . JText::_('FLEXI_CAT_DISPLAY_HEADER'));
?> 
<?php
$fieldSet = $this->form->getFieldset('cats_display');

if (isset($fieldSet->description) && trim($fieldSet->description)):
    echo '<div class="fc-mssg fc-info">' . JText::_($fieldSet->description) . '</div>';
endif;
?> 

<?php
foreach ( $fieldSet as $field ):
	echo( $field->getAttribute( 'type' ) == 'separator' || $field->hidden ) ? $field->input : ' 
            <div class="control-group"> 
                 <div class="control-label">' . $field->label . '</div> 
                    <div class="controls"> 
                        ' . $this->getInheritedFieldDisplay( $field, $this->iparams ) . ' 
                    </div> 
           	</div> 
             ';
endforeach;
?>

<?php
echo JHtml::_('bootstrap.endTab');
?> 
<!-- /END - 1. TAB SETTINGS - DISPLAY --> 

<!-- 2. TAB SETTINGS - SEARCH --> 
<?php echo JHtml::_('bootstrap.addTab', 'tabset_cat_params-'.$tabSetCnt.'', 'tab'.$tabCnt[$tabSetCnt]++.'', 
	 '<i class="icon-search mr-5"></i>'.JText::_('FLEXI_CAT_DISPLAY_SEARCH_FILTER_FORM')); ?>
	
	<?php
					$fieldSet = $this->form->getFieldset('cat_search_filter_form');

					if (isset($fieldSet->description) && trim($fieldSet->description)) :
						echo '<div class="fc-mssg fc-info">'.JText::_($fieldSet->description).'</div>';
					endif;
					?>

					<?php foreach ($fieldSet as $field) :
						echo ($field->getAttribute('type')=='separator' || $field->hidden) ? $field->input : '
						<div class="control-group">
							<div class="control-label">'.$field->label.'</div>
							<div class="controls">
								'.$this->getInheritedFieldDisplay($field, $this->iparams).'
							</div>
						</div>
						';
					endforeach; ?>
					
<?php
echo JHtml::_('bootstrap.endTab');
?> 
<!-- /END - 2. TAB SETTINGS - SEARCH --> 	

<!-- 3. TAB SETTINGS - LAYOUT --> 
<?php echo JHtml::_('bootstrap.addTab', 'tabset_cat_params-'.$tabSetCnt.'', 'tab'.$tabCnt[$tabSetCnt]++.'', 
	 '<i class="icon-palette mr-5"></i>'.JText::_('FLEXI_LAYOUT')); ?>
	
	 					<span class="btn-group input-append" style="margin: 2px 0px 6px;">
						<span id="fc-layouts-help_btn" class="btn" onclick="fc_toggle_box_via_btn('fc-layouts-help', this, 'btn-primary');" ><span class="icon-help"></span><?php echo JText::_('JHELP'); ?></span>
					</span>
					<div class="fcclear"></div>

					<div class="fc-info fc-nobgimage fc-mssg-inline" id="fc-layouts-help" style="margin: 2px 0px!important; font-size: 12px; display: none;">
						<h3 class="themes-title">
							<?php echo JText::_( 'FLEXI_PARAMETERS_LAYOUT_EXPLANATION' ); ?>
						</h3>
						<b>NOTE:</b> Common method for -displaying- fields is by <b>editing the template layout</b> in template manager and placing the fields into <b>template positions</b>
					</div>
					<div class="fcclear"></div><br/>

					<?php
					$_p = & $this->row->params;
					foreach($this->form->getGroup('templates') as $field):
						$_name  = $field->fieldname;
						if ($_name!='clayout' && $_name!='clayout_mobile') continue;

						$_value = isset($_p[$_name])  ?  $_p[$_name]  :  null;

						// setValue(), is ok if input property, has not been already created, otherwise we need to re-initialize (which clears input)
						//$field->setup($field->element, $_value, $field->group);

						$field->setValue($_value);

						echo ($field->getAttribute('type')=='separator' || $field->hidden) ? $field->input : '
						<div class="control-group">
							<div class="control-label">'.$field->label.'</div>
							<div class="controls">
								'.$this->getInheritedFieldDisplay($field, $this->iparams).'
							</div>
						</div>
						';
					endforeach; ?>
					
					<div class="fctabber tabset_cat_props" id="tabset_layout">

						<div class="tabbertab" id="tabset_layout_params_tab" data-icon-class="icon-palette" >
							<h3 class="tabberheading"><?php echo JText::_( 'FLEXI_LAYOUT_PARAMETERS' ); ?></h3>
							
							<div class="fc-success fc-mssg-inline" style="font-size: 12px; margin: 8px 0 !important;" id="__category_inherited_layout__">
								<?php echo JText::_( 'FLEXI_TMPL_USING_INHERITED_CATEGORY_LAYOUT' ). ': <b>'. $this->iparams->get('clayout') .'</b>'; ?>
							</div>
							<div class="fcclear"></div>
							
							<div class="fc-sliders-plain-outer">
								<?php
								echo JHtml::_('sliders.start','theme-sliders-'.$this->form->getValue("id"), array('useCookie'=>1,'show'=>1));
								$groupname = 'attribs';  // Field Group name this is for name of <fields name="..." >
								$cat_layout = @ $this->row->params['clayout'];

								foreach ($this->tmpls as $tmpl) :

									$form_layout = $tmpl->params;
									$label = JText::_( 'FLEXI_PARAMETERS_THEMES_SPECIFIC' ) . ' : ' . $tmpl->name;
									echo JHtml::_('sliders.panel', $label, $tmpl->name.'-'.$groupname.'-options');

									if (!$cat_layout || $tmpl->name != $cat_layout) continue;

									$fieldSets = $form_layout->getFieldsets($groupname);
									foreach ($fieldSets as $fsname => $fieldSet) : ?>
										<fieldset class="panelform params_set">

										<?php
										if (isset($fieldSet->label) && trim($fieldSet->label)) :
											echo '<div style="margin:0 0 12px 0; font-size: 16px; background-color: #333; float:none;" class="fcsep_level0">'.JText::_($fieldSet->label).'</div>';
										endif;
										if (isset($fieldSet->description) && trim($fieldSet->description)) :
											echo '<div class="fc-mssg fc-info">'.JText::_($fieldSet->description).'</div>';
										endif;

										foreach ($form_layout->getFieldset($fsname) as $field) :

											if ($field->getAttribute('not_inherited')) continue;
											if ($field->getAttribute('cssprep')) continue;

											$fieldname = $field->fieldname;
											//$value = $form_layout->getValue($fieldname, $groupname, @ $this->row->params[$fieldname]);

											$input_only = !$field->label || $field->hidden;
											echo
												($input_only ? '' :
												str_replace('jform_attribs_', 'jform_layouts_'.$tmpl->name.'_',
													$form_layout->getLabel($fieldname, $groupname)).'
												<div class="container_fcfield">
												').

												str_replace('jform_attribs_', 'jform_layouts_'.$tmpl->name.'_', 
													str_replace('[attribs]', '[layouts]['.$tmpl->name.']',
														$this->getInheritedFieldDisplay($field, $this->iparams)
														//$form_layout->getInput($fieldname, $groupname/*, $value*/)   // Value already set, no need to pass it
													)
												).

												($input_only ? '' : '
												</div>
												');
										endforeach; ?>

										</fieldset>

									<?php endforeach; //fieldSets ?>
								<?php endforeach; //tmpls ?>

								<?php echo JHtml::_('sliders.end'); ?>

							</div><!-- class="fc-sliders-plain-outer" -->
							
						</div><!--.tabbertab FLEXI_LAYOUT_PARAMETERS-->
						
						<!--tabbertab FLEXI_CATEGORY_LAYOUT_SWITCHER-->	
						<div class="tabbertab" id="tabset_layout_switcher_tab" data-icon-class="icon-grid" >
							<h3 class="tabberheading"> <?php echo JText::_('FLEXI_CATEGORY_LAYOUT_SWITCHER'); ?> </h3>
							
							<?php
							$_p = & $this->row->params;
							foreach($this->form->getGroup('templates') as $field):
								$_name  = $field->fieldname;
								if ($_name=='clayout' || $_name=='clayout_mobile') continue;

								$_value = isset($_p[$_name])  ?  $_p[$_name]  :  null;

								// setValue(), is ok if input property, has not been already created, otherwise we need to re-initialize (which clears input)
								//$field->setup($field->element, $_value, $field->group);

								$field->setValue($_value);

								echo ($field->getAttribute('type')=='separator' || $field->hidden) ? $field->input : '
								<div class="control-group">
									<div class="control-label">'.$field->label.'</div>
									<div class="controls">
										'.$this->getInheritedFieldDisplay($field, $this->iparams).'
									</div>
								</div>
								';
							endforeach; ?>
							
						</div><!--/.tabbertab FLEXI_CATEGORY_LAYOUT_SWITCHER-->	
							
					</div>
						
	 <?php echo JHtml::_('bootstrap.endTab');?> 
<!-- /END - 3. TAB SETTINGS - SEARCH --> 	
 
<!-- 4. TAB SETTINGS - ITEMS_LIST --> 
<?php echo JHtml::_('bootstrap.addTab', 'tabset_cat_params-'.$tabSetCnt.'', 'tab'.$tabCnt[$tabSetCnt]++.'', 
	 '<i class="icon-list-2 mr-5"></i>'.JText::_('FLEXI_CAT_DISPLAY_ITEMS_LIST')); ?>
	 
	 <?php
					$fieldSet = $this->form->getFieldset('cat_items_list');

					if (isset($fieldSet->description) && trim($fieldSet->description)) :
						echo '<div class="fc-mssg fc-info">'.JText::_($fieldSet->description).'</div>';
					endif;
					?>

					<?php foreach ($fieldSet as $field) :
						echo ($field->getAttribute('type')=='separator' || $field->hidden) ? $field->input : '
						<div class="control-group">
							<div class="control-label">'.$field->label.'</div>
							<div class="controls">
								'.$this->getInheritedFieldDisplay($field, $this->iparams).'
							</div>
						</div>
						';
					endforeach; ?>
					
<?php echo JHtml::_('bootstrap.endTab');?> 	 
<!-- /END - 4. TAB SETTINGS - ITEMS_LIST --> 

<!-- 5. TAB SETTINGS - RSS FORM --> 
<?php echo JHtml::_('bootstrap.addTab', 'tabset_cat_params-'.$tabSetCnt.'', 'tab'.$tabCnt[$tabSetCnt]++.'', 
	 '<i class="icon-feed mr-5"></i>'.JText::_('FLEXI_PARAMS_CAT_RSS_FEEDS')); ?>

	 <?php
					$fieldSet = $this->form->getFieldset('cat_rss_feeds');

					if (isset($fieldSet->description) && trim($fieldSet->description)) :
						echo '<div class="fc-mssg fc-info">'.JText::_($fieldSet->description).'</div>';
					endif;
					?>

					<?php foreach ($fieldSet as $field) :
						echo ($field->getAttribute('type')=='separator' || $field->hidden) ? $field->input : '
						<div class="control-group">
							<div class="control-label">'.$field->label.'</div>
							<div class="controls">
								'.$this->getInheritedFieldDisplay($field, $this->iparams).'
							</div>
						</div>
						';
					endforeach; ?>
						 
<?php echo JHtml::_('bootstrap.endTab');?> 	 
<!-- /END - 5. TAB SETTINGS - RSS FORM --> 

<!-- 6. TAB SETTINGS - PARAMETERS HANDLING --> 
<?php echo JHtml::_('bootstrap.addTab', 'tabset_cat_params-'.$tabSetCnt.'', 'tab'.$tabCnt[$tabSetCnt]++.'', 
	 '<i class="icon-wrench mr-5"></i>'.JText::_('FLEXI_PARAMETERS_HANDLING')); ?>
			<div class="fcsep_level0">
						<?php echo JText::_('FLEXI_PARAMETERS_HANDLING'); ?>
			</div>

<span class="btn-group input-append" style="margin: 2px 0px 6px;">
						<span id="fc-heritage-help_btn" class="btn" onclick="fc_toggle_box_via_btn('fc-heritage-help', this, 'btn-primary');" ><span class="icon-help"></span><?php echo JText::_('FLEXI_HERITAGE_OVERRIDE_ORDER'); ?></span>
					</span>
					<div class="fcclear"></div>

					<div class="fc-mssg fc-info fc-nobgimage" id="fc-heritage-help" style="margin: 2px 0px!important; font-size:12px; display: none;">
						<?php echo JText::_('FLEXI_CAT_PARAM_OVERRIDE_ORDER_DETAILS_INHERIT'); ?>
					</div>
					<div class="fcclear"></div>

					<?php foreach($this->form->getGroup('special') as $field): ?>
						<div class="control-group">
							<div class="control-label"><?php echo $field->label; ?></div>
							<div class="controls">
								<?php echo $this->Lists[$field->fieldname]; ?>
							</div>
						</div>
					<?php endforeach; ?>


					<div class="control-group">
							<div class="control-label">
						<?php echo $this->form->getLabel('copycid'); ?></div>
						<div class="controls">
							<?php echo $this->Lists['copycid']; ?>
						</div>
					</div>
<?php echo JHtml::_('bootstrap.endTab');?> 
<!-- END/ 6. TAB SETTINGS - PARAMETERS HANDLING --> 
<?php echo JHtml::_('bootstrap.endTabSet');?> 
<!--
/*
 *	=======================================================================
	+ END LAYOUT
 *	=======================================================================
 */
-->
</div><!-- .sub-tab -->
 <?php
echo JHtml::_( 'bootstrap.endTab' );
?>
 <!--/TAB3.5 - CONFIGURATION-->          
                   
                                   <!--TAB4 - NOTIFICATIONS--> 
            <?php
echo JHtml::_('bootstrap.addTab', 'ID-Tabs-' . $tabSetCnt . '', 'tab' . $tabCnt[$tabSetCnt]++ . '', JText::_('<i class="icon-mail"></i><br class="hidden-phone">' . JText::_('FLEXI_NOTIFICATIONS_CONF')));
?> 
           <?php
$fieldSet = $this->form->getFieldset('notifications_conf');

if (isset($fieldSet->description) && trim($fieldSet->description)):
    echo '<div class="fc-mssg fc-info">' . JText::_($fieldSet->description) . '</div>';
endif;
?> 
           <?php
foreach ($fieldSet as $field):
    echo ($field->getAttribute('type') == 'separator' || $field->hidden) ? $field->input : ' 
                                            <div class="control-group"> 
                                                <div class="control-label">' . $field->label . '</div> 
                                                <div class="controls"> 
                                                    ' . $this->getInheritedFieldDisplay($field, $this->iparams) . ' 
                                                </div> 
                                            </div> 
                                            ';
endforeach;
?> 
           <?php
if ($this->cparams->get('nf_allow_cat_specific', 0)):
?> 
           <?php
    $fieldSet = $this->form->getFieldset('cat_notifications_conf');
    
    if (isset($fieldSet->description) && trim($fieldSet->description)):
        echo '<div class="fc-mssg fc-info">' . JText::_($fieldSet->description) . '</div>';
    endif;
?> 
           <?php
    foreach ($fieldSet as $field):
        echo ($field->getAttribute('type') == 'separator' || $field->hidden) ? $field->input : ' 
                                                <div class="control-group"> 
                                                    <div class="control-label">' . $field->label . '</div> 
                                                    <div class="controls"> 
                                                        ' . $this->getInheritedFieldDisplay($field, $this->iparams) . ' 
                                                    </div> 
                                                </div> 
                                                ';
    endforeach;
?> 
           <?php
else:
?> 
           <div class="fcsep_level0"> 
                <?php
    echo JText::_('FLEXI_NOTIFY_EMAIL_RECEPIENTS');
?> 
           </div> 
            <div class="fcclear"></div> 
            <div class="alert alert-info"> 
                <?php
    echo JText::_('FLEXI_INACTIVE_PER_CONTENT_CAT_NOTIFICATIONS_INFO');
?> 
           </div> 
            <?php
endif;
?> 
           <?php
echo JHtml::_('bootstrap.endTab');
?> 
           <!--/TAB4 - NOTIFICATIONS--> 
 
 
  <?php
if ($this->perms->CanRights):
?> 
           <!--TAB6 - PERMISSIONS--> 
            <?php
    echo JHtml::_('bootstrap.addTab', 'ID-Tabs-' . $tabSetCnt . '', 'tab' . $tabCnt[$tabSetCnt]++ . '', JText::_('<i class="icon-power-cord"></i><br class="hidden-phone">' . JText::_('FLEXI_PERMISSIONS')));
?> 
           <div id="access"> 
                <?php
    echo $this->form->getInput('rules');
?> 
           </div> 
            <?php
    echo JHtml::_('bootstrap.endTab');
?> 
           <!--/TAB6 - PERMISSIONS=--> 
            <?php
endif;
?> 
 
  <?php echo JHtml::_('bootstrap.endTabSet');?>          


	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="id" value="<?php echo $this->form->getValue('id'); ?>" />
	<input type="hidden" name="controller" value="<?php echo $this->controller; ?>" />
	<input type="hidden" name="view" value="<?php echo $this->view; ?>" />
	<input type="hidden" name="task" value="" />
	<?php echo $this->form->getInput('extension'); ?>
	<?php echo JHtml::_( 'form.token' ); ?>

</form>
</div><!-- id:flexicontent -->
