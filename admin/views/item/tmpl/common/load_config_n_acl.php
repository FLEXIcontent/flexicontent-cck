<?php
defined('_JEXEC') or die('Restricted access');

$app     = JFactory::getApplication();
$user    = JFactory::getUser();
$session = JFactory::getSession();
$isSite  = $app->isClient('site');
$CFGsfx  = $isSite ? '_fe' : '_be';


/**
 * Create some variables
 */
$this->row = $this->item;

$isnew  = !$this->row->id;
$typeid = (int) $this->row->type_id;

$this->menuCats = $isnew ? $this->menuCats : false;  // just make sure ...


/**
 * Create reusable html code
 * 
 */

$close_btn = '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="'.JText::_('CLOSE').'"></button>';  // '<a class="fc-close" onclick="this.parentNode.parentNode.removeChild(this.parentNode);">&#215;</a>';
$alert_box = '<div %s class="alert alert-%s %s alert-dismissible fade show" role="alert">'.$close_btn.'%s</div>';  // '<div %s class="fc-mssg fc-%s %s">'.$close_btn.'%s</div>';
$btn_class = 'btn';  // 'fc_button';
$tip_class = ' hasTooltip';
$lbl_class = ' ' . $this->params->get('form_lbl_class' . $CFGsfx, '');
$noplugin  = '<div class="fc-mssg-inline fc-warning" style="margin:0 2px 6px 2px; max-width: unset;">'.JText::_( 'FLEXI_PLEASE_PUBLISH_THIS_PLUGIN' ).'</div>';


/**
 * These are used in FRONTEND, to create
 * (A) a thanks message and
 * (B) redirect to a specific url when item is closed
 */
$newly_submitted      = $session->get('newly_submitted', array(), 'flexicontent');
$newly_submitted_item = isset($newly_submitted[$this->row->id])
	? $newly_submitted[$this->row->id]
	: null;
$submit_message            = $this->params->get('submit_message' . (!$isSite ? $CFGsfx  : ''));
$submit_redirect_url       = $isSite ? $this->params->get('submit_redirect_url') . $CFGsfx  : '';
$isredirected_after_submit = $newly_submitted_item && $submit_redirect_url;

$buttons_placement  = (int) $this->params->get('buttons_placement' . $CFGsfx, ($isSite ? 0 : -1));
$form_container_css   = $this->params->get('form_container_css' . $CFGsfx, '');
$form_container_class = $this->params->get('form_container_class' . $CFGsfx, '');

$usetitle    = (int) $this->params->get('usetitle' . $CFGsfx, 1);
$usealias    = (int) $this->params->get('usealias' . $CFGsfx, 1);
$uselang     = (int) $this->params->get('uselang' . $CFGsfx, 1);
$usetype     = (int) $this->params->get('usetype' . $CFGsfx, ($isSite ? 0 : 1));
$usestate    = (int) $this->params->get('usestate' . $CFGsfx, 1);
$useaccess   = (int) $this->params->get('useaccess' . $CFGsfx, 1);
$usemaincat  = (int) $this->params->get('usemaincat' . $CFGsfx, 1);

$use_versioning          = (int) $this->params->get('use_versioning', 1);
$allow_versioncomparing  = (int) $this->params->get('allow_versioncomparing', 1);
$auto_approve            = (int) $this->params->get('auto_approve', 1);
$approval_warning_inform = $this->params->get('approval_warning_inform'. $CFGsfx, 1);
$is_autopublished        = $isSite && $isnew && $this->params->get('autopublished', 0);

$allowdisablingcomments   = (int) $this->params->get('allowdisablingcomments' . $CFGsfx, ($isSite ? 0 : 1));
$allow_owner_notify       = (int) $this->params->get('allow_owner_notify' . $CFGsfx, ($isSite ? 0 : 1));
$allow_subscribers_notify = (int) $this->params->get('allow_subscribers_notify' . $CFGsfx, ($isSite ? 0 : 1));
$disable_langs            = $this->params->get('disable_languages' . $CFGsfx, array());

$usepublicationdetails = (int) $this->params->get('usepublicationdetails' . $CFGsfx, ($isSite ? 1 : 2));
$usemetadata           = (int) $this->params->get('usemetadata' . $CFGsfx, ($isSite ? 1 : 2));
$useseoconf            = (int) $this->params->get('useseoconf' . $CFGsfx, ($isSite ? 0 : 1));
$usedisplaydetails     = (int) $this->params->get('usedisplaydetails' . $CFGsfx, ($isSite ? 1 : 2));
$use3rdpartyparams     = (int) $this->params->get('use3rdpartyparams' . $CFGsfx, 1);
$selecttheme           = (int) $this->params->get('selecttheme' . $CFGsfx, ($isSite ? 1 : 2));

$permsplacement    = (int) $this->params->get('permsplacement' . $CFGsfx, 2);
$versionsplacement = (int) $this->params->get('versionsplacement' . $CFGsfx, 2);

$task_items = 'task=items.';
$ctrl_items = 'items.';
$tags_task  = $isSite ? 'task=' : 'task=tags.';

// For tabsets/tabs ids (focusing, etc)
$tabSetCnt = -1;
$tabSetMax = -1;
$tabCnt = array();
$tabSetStack = array();


/**
 * Decide which field-sets with display options (parameters) will be shown
 */

$displayed_fieldSets = array();
$fieldSets = $this->form->getFieldsets('attribs');
foreach ($fieldSets as $name => $fieldSet)
{
	if ($name === 'themes' || $name === 'params-seoconf')
	{
		// These are displayed with seperate elements: 'layout_selection', 'layout_params' and  'metadata'
		continue;
	}
	elseif ($name === 'params-basic')
	{
		if ($typeid && $usedisplaydetails < 1) continue;
	}
	elseif ($name === 'params-advanced')
	{
		if ($typeid && $usedisplaydetails < 2) continue;
	}

	else
	{
		// 3rd-party display attributes
		if (!$use3rdpartyparams) continue;
	}

	$displayed_fieldSets[$name] = $fieldSet;
}


/**
 * Decide if we are showing:  secondary cats, featured cats, featured flag, tags
 */
$secondary_displayed =
  ($this->menuCats  && $this->menuCats->cid) ||   // New Content  -with-  Menu Override, check if secondary categories were enabled in menu
  (!$this->menuCats && $this->lists['cid']);      // New Content but  -without-  Menu override ... OR Existing Content, check if secondary are permitted  OR already set
$cats_canselect_sec =
	($this->menuCats && $this->menuCats->cancid) ||
	(!$this->menuCats && $this->perms['multicat'] && $this->perms['canchange_seccat']) ;

$usetags        = (int) $this->params->get('usetags' . $CFGsfx, 1);
$tags_editable  = $this->perms['cantags'] && $usetags === 1;
$tags_displayed = $typeid &&
	( ($this->perms['cantags'] && $usetags) || (count($this->usedtagsdata) && $usetags === 2) ) ;


/**
 * Check if it is possible to hide the main category selector
 */
if ($usemaincat === 0 && empty($this->menuCats->cancatid) && !$this->row->id && !$this->params->get('catid_default'))
{
	$usemaincat = 1;

	$this->lists['catid'] = sprintf( $alert_box,
		' style="margin: 2px 0px 6px 0px; display: inline-block;" ',
		'error', '', JText::_('FLEXI_CANNOT_HIDE_MAINCAT_MISCONFIG_INFO')
	) . '<br>' . $this->lists['catid'];
}


/**
 * Create info images
 */
$info_image = $this->params->get('use_font_icons', 1)
	? '<i class="icon-info text-info"></i>'
	: JHtml::image ( 'administrator/components/com_flexicontent/assets/images/information.png', JText::_( 'FLEXI_NOTES' ) );
$revert_image = $this->params->get('use_font_icons', 1)
	? '<i class="icon-undo" style="color:darkgray"></i>'
	: JHtml::image ( 'administrator/components/com_flexicontent/assets/images/arrow_rotate_anticlockwise.png', JText::_( 'FLEXI_REVERT' ) );
$compare_image = $this->params->get('use_font_icons', 1)
	? '<i class="icon-contract-2" style="color:darkgray"></i>'
	: JHtml::image ( 'administrator/components/com_flexicontent/assets/images/arrow-in-out.png', JText::_( 'FLEXI_VIEW' ) );
$comment_image = $this->params->get('use_font_icons', 1)
	? '<i class="icon-comment" style="color:darkgray"></i>'
	: JHtml::image ( 'administrator/components/com_flexicontent/assets/images/comments.png', JText::_( 'FLEXI_COMMENT' ) );
$hint_image = $this->params->get('use_font_icons', 1)
	? '<i class="icon-lamp"></i>'
	: JHtml::image ( 'administrator/components/com_flexicontent/assets/images/lightbulb.png', JText::_( 'FLEXI_NOTES' ), 'style="vertical-align:top;"' );
$warn_image = $this->params->get('use_font_icons', 1)
	? '<i class="icon-warning text-warning"></i>'
	: JHtml::image ( 'administrator/components/com_flexicontent/assets/images/warning.png', JText::_( 'FLEXI_NOTES' ), 'style="vertical-align:top;"' );
$conf_image = '<i class="icon-cog"></i>';

$lbl_extra_class = $isSite ? '' : ' pull-left label-fcinner label-toplevel';


// Add extra css/js for the edit form
if ($this->params->get('form_extra_css'))    $this->document->addStyleDeclaration($this->params->get('form_extra_css'));
if ($this->params->get('form_extra_css' . $CFGsfx)) $this->document->addStyleDeclaration($this->params->get('form_extra_css' . $CFGsfx));
if ($this->params->get('form_extra_js'))     $this->document->addScriptDeclaration($this->params->get('form_extra_js'));
if ($this->params->get('form_extra_js' . $CFGsfx))  $this->document->addScriptDeclaration($this->params->get('form_extra_js' . $CFGsfx));

// Load JS tabber lib
$this->document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/tabber-minimized.js', array('version' => FLEXI_VHASH));
$this->document->addStyleSheet(JUri::root(true).'/components/com_flexicontent/assets/css/tabber.css', array('version' => FLEXI_VHASH));
$this->document->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs


/**
 * Add javascript for tags and versioning (if these features are enabled)
 */
if ($tags_editable || (!$isSite && $this->perms['canversion']))
{
	//$this->document->addScript(JUri::root(true).'/components/com_flexicontent/librairies/jquery-autocomplete/jquery.bgiframe.min.js', array('version' => FLEXI_VHASH));
	//$this->document->addScript(JUri::root(true).'/components/com_flexicontent/librairies/jquery-autocomplete/jquery.ajaxQueue.js', array('version' => FLEXI_VHASH));
	//$this->document->addScript(JUri::root(true).'/components/com_flexicontent/librairies/jquery-autocomplete/jquery.autocomplete.min.js', array('version' => FLEXI_VHASH));
	$this->document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/jquery.pager.js', array('version' => FLEXI_VHASH));     // e.g. pagination for item versions
	$this->document->addScript(JUri::root(true).'/components/com_flexicontent/assets/js/jquery.autogrow.js', array('version' => FLEXI_VHASH));  // e.g. autogrow version comment textarea

	//$this->document->addStyleSheet(JUri::root(true).'/components/com_flexicontent/librairies/jquery-autocomplete/jquery.autocomplete.css', array('version' => FLEXI_VHASH));

	JText::script("FLEXI_DELETE_TAG", true);
	JText::script("FLEXI_ENTER_TAG", true);

	$this->document->addScriptDeclaration("
		jQuery(document).ready(function(){

			jQuery('.deletetag').click(function(e){
				jQuery(this).parent().remove();
				return false;
			});

			var tagInput = jQuery('#input-tags');

			tagInput.keydown(function(event)
			{
				if (event.keyCode == 13)
				{
					var el = jQuery(event.target);
					if (el.val()=='') return false; // No tag to assign / create

					var selection_isactive = jQuery('.ui-autocomplete .ui-state-focus').length != 0;
					if (selection_isactive) return false; // Enter pressed, while autocomplete item is focused, autocomplete \'select\' event handler will handle this

					var data_id   = el.data('tagid');
					var data_name = el.data('tagname');
					//window.console.log( 'User input: '+el.val() + ' data-tagid: ' + data_id + ' data-tagname: \"'+ data_name + '\"');

					if (el.val() == data_name && data_id != '' && data_id != '0')
					{
						//window.console.log( 'Assigning found tag: (' + data_id + ', \"' + data_name + '\")');
						addToList(data_id, data_name);
						el.autocomplete('close');
					}
					else {
						//window.console.log( 'Retrieving (create-if-missing) tag: \"' + el.val() + '\"');
						addtag(0, el.val());
						el.autocomplete('close');
					}

					el.val('');  //clear existing value
					return false;
				}
			});

			var fcTagsCache = {};

			jQuery.ui.autocomplete({
				source: function( request, response )
				{
					var el   = jQuery(this.element);
					var term = request.term;

					if (term in fcTagsCache)
					{
						response(fcTagsCache[term]);
						return;
					}

					//window.console.log( 'Getting tags for \"' + term + '\" ...');
					//window.console.log(jQuery('#jform_language').val());

					jQuery.ajax({
						url: '".JUri::base(true)."/components/com_flexicontent/tasks/core.php?". JSession::getFormToken() ."=1',
						dataType: 'json',
						data: {
							q: term,
							task: 'viewtags',
							item_lang: jQuery('#jform_language').val(),
							lang: '". JFactory::getLanguage()->getTag() . "',
							format: 'json'
						},
						success: function(data)
						{
							//window.console.log( '... received tags for \"' + term + '\"');
							var response_data = jQuery.map(data, function(item)
							{
								if (el.val() == item.name)
								{
									//window.console.log( 'Found exact TAG match, (' + item.id + ', \"' + item.name + '\")');
									el.data('tagid',   item.id);
									el.data('tagname', item.name);
								}
								let label_text = item.name + (item.translated_text ? ' (' + item.translated_text + ')' : '');
								return jQuery('#ultagbox').find('input[value=\"'+item.id+'\"]').length > 0 ? null : { label: label_text, value: item.id };
							});

							fcTagsCache[term] = response_data;
							response(response_data);
						}
					});
				},

				delay: 200,
				minLength: 0,

				focus: function ( event, ui )
				{
					//window.console.log( (ui.item  ?  'current ID: ' + ui.item.value + ' , current Label: ' + ui.item.label :  'Nothing selected') );

					var el = jQuery(event.target);
					if (ui.item.value!='' && ui.item.value!='0')
					{
						el.val(ui.item.label);
					}
					el.data('tagid',   ui.item.value);
					el.data('tagname', ui.item.label);

					event.preventDefault();  // Prevent default behaviour of setting 'ui.item.value' into the input
				},

				select: function( event, ui )
				{
					//window.console.log( 'Selected: ' + ui.item.label + ', input was \'' + this.value + '\'');

					var el = jQuery(event.target);
					if (ui.item.value != '' && ui.item.value != '0')
					{
						addToList(ui.item.value, ui.item.label);
						el.val('');  //clear existing value
					}

					event.preventDefault();  // Prevent default behaviour of setting 'ui.item.value' into the input and triggering change event
				},

				//change: function( event, ui ) { window.console.log( 'autocomplete change()' ); },
				//open: function() { window.console.log( 'autocomplete open()' ); },
				//close: function() { window.console.log( 'autocomplete close()' ); },
				//search: function() { window.console.log( 'autocomplete search()' ); }
			}, tagInput.get(0) );

			// Call search method on focus to allow immediate search
			tagInput.focus(function () {
				jQuery(this).autocomplete('search', this.value);
			});

			// Call autocomplete.search method to load and cache all tags up to a maximum ... e.g. 500
			tagInput.attr('readonly', 'readonly').autocomplete('search', '').autocomplete('close').removeAttr('readonly');
		});


		function addToList(id, name)
		{
			// Prefer quick tag selector if it exists
			var cmtag = jQuery('#quick-tag-'+id);

			if (cmtag.length)
			{
				cmtag.attr('checked', 'checked').trigger('change');
			}
			else
			{
				var obj = jQuery('#ultagbox');
				if (obj.find('input[value=\"'+id+'\"]').length > 0)
				{
					return;
				}
				obj.append('<li class=\"tagitem\"><span>'+name+'</span><input type=\"hidden\" name=\"jform[tag][]\" value=\"'+id+'\" /><a href=\"javascript:;\" class=\"deletetag\" onclick=\"javascript:deleteTag(this);\" title=\"' + Joomla.JText._('FLEXI_DELETE_TAG') + '\"></a></li>');
			}
		}


		function addtag(id, tagname)
		{
			id = id==null ? 0 : id;

			if (tagname == '')
			{
				alert(\" + Joomla.JText._('FLEXI_ENTER_TAG') + \");
				return;
			}

			var tag = new itemscreen();
			tag.addtag( id, tagname, '".JUri::base(true)."/index.php?option=com_flexicontent&".$tags_task."addtag&format=raw&". JSession::getFormToken() ."=1');
		}

		function deleteTag(obj)
		{
			var parent = obj.parentNode;
			parent.innerHTML = '';
			parent.parentNode.removeChild(parent);
		}

		" . ($isSite || !$this->perms['canversion'] ? "" : "

		PageClick = function(pageclickednumber) {
			jQuery.ajax({ url: '".JUri::base(true)."/index.php?option=com_flexicontent&".$task_items."getversionlist&id=".$this->row->id."&active=".$this->row->version."&". JSession::getFormToken() ."=1&format=raw&page='+pageclickednumber, context: jQuery('#version_tbl'), success: function(str){
				jQuery(this).html(\"\\
				<table class='fc-table-list fc-tbl-short' style='margin:10px;'>\\
				\"+str+\"\\
				</table>\\
				\");
				jQuery('#version_tbl').find('.hasTooltip').tooltip({html: true, container: jQuery('#version_tbl')});
				jQuery('#version_tbl').find('.hasPopover').popover({html: true, container: jQuery('#version_tbl'), trigger : 'hover focus'});

				// Attach click event to version compare links of the newly created page
				jQuery(this).find('a.modal-versions').each(function(index, value) {
					jQuery(this).on('click', function() {
						// Load given URL in an popup dialog
						var url = jQuery(this).attr('href');
						fc_showDialog(url, 'fc_modal_popup_container');
						return false;
					});
				});
			}});

			// Reattach pagination inside the newly created page
			jQuery('#fc_pager').pager({ pagenumber: pageclickednumber, pagecount: ".$this->pagecount.", buttonClickCallback: PageClick });
		}

		jQuery(document).ready(function()
		{
			// For the initially displayed versions page:  Add onclick event that opens compare in popup
			jQuery('a.modal-versions').each(function(index, value) {
				jQuery(this).on('click', function() {
					// Load given URL in an popup dialog
					var url = jQuery(this).attr('href');
					fc_showDialog(url, 'fc_modal_popup_container');
					return false;
				});
			});

			// Attach pagination for versions listing
			jQuery('#fc_pager').pager({
				pagenumber: " . $this->current_page . ",
				pagecount: " . $this->pagecount . ",
				buttonClickCallback: PageClick
			});

			// Attach textarea autogrow height (while typing)
			jQuery('#versioncomment').autogrow({
				minHeight: 32,
				maxHeight: 250,
				lineHeight: 16
			});
		});

		") . "

	");
}


// ITEM SCREEN
$this->document->addScriptDeclaration
("
	jQuery(document).ready(function(){
		var hits = new itemscreen('hits', {id:".($this->row->id ? $this->row->id : 0).", task:'".$ctrl_items."gethits', sess_token:'" . JSession::getFormToken() . "'});
		//hits.fetchscreen();
		var votes = new itemscreen('votes', {id:".($this->row->id ? $this->row->id : 0).", task:'".$ctrl_items."getvotes', sess_token:'" . JSession::getFormToken() . "'});
		//votes.fetchscreen();
	});

	function reseter(task, id, div)
	{
		var res = new itemscreen();
		task = '".$ctrl_items."' + task;
		res.reseter(task, id, div, '" . JUri::base(true) . "/index.php?option=com_flexicontent&controller=items&" . JSession::getFormToken() . "=1');
	}

	function clickRestore(link)
	{
		if (confirm('".JText::_( 'FLEXI_CONFIRM_VERSION_RESTORE',true )."'))
		{
			location.href=link;
		}

		return false;
	}
");


if ($permsplacement && $this->perms['canright'] && $permsplacement === 2)
{
	$this->document->addScriptDeclaration("
		jQuery(document).ready(function()
		{
			jQuery('fieldset.flexiaccess legend + div#tabacces').hide();
			jQuery('fieldset.flexiaccess legend').on('click', function(ev)
			{
				var panel = jQuery(this).next();
				panel.is(':visible') ? panel.slideUp(600) : panel.slideDown(600);
			});
		});
	");
}
