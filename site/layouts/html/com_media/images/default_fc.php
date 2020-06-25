<?php
//include(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_media'.DS.'views'.DS.'images'.DS.'tmpl'.DS.'default.php');

defined('_JEXEC') or die;

$user       = JFactory::getUser();
$input      = JFactory::getApplication()->input;
$params     = JComponentHelper::getParams('com_media');
$lang       = JFactory::getLanguage();
$onClick    = '';
$fieldInput = $this->state->get('field.id');
$isMoo      = $input->getInt('ismoo', 1);
$author     = $input->getCmd('author');
$asset      = $input->getCmd('asset');
$filetypes  = $input->getString('filetypes', '');

// This needed if you are creating a custom named layout for 'imagesList' view
$this->imagesListLayout = 'default_fc';

JHtml::_('formbehavior.chosen', 'select');

// Load tooltip instance without HTML support because we have a HTML tag in the tip
JHtml::_('bootstrap.tooltip', '.noHtmlTip', array('html' => false));

// Include jQuery
JHtml::_('behavior.core');
JHtml::_('jquery.framework');
JHtml::_('script', 'media/popup-imagemanager.min.js', array('version' => 'auto', 'relative' => true));
JHtml::_('stylesheet', 'media/popup-imagemanager.css', array('version' => 'auto', 'relative' => true));

if ($lang->isRtl())
{
	JHtml::_('stylesheet', 'media/popup-imagemanager_rtl.css', array('version' => 'auto', 'relative' => true));
}

JFactory::getDocument()->addScriptOptions(
	'mediamanager', array(
		'base'   => $params->get('image_path', 'images') . '/',
		'asset'  => $asset,
		'author' => $author,
		'layout' => $this->imagesListLayout,   // This is currently ignored by Media manager JS and we will need to override window.ImageManager.setFrameUrl below
	)
);

JFactory::getDocument()->addStyleDeclaration('
	@media (min-width: 480px) {
		#folderlist_chzn {
			min-width: 320px;
		}
	}

	@media (min-width: 801px) {
		#f_url {
			min-width: 380px;
		}
	}

	body.contentpane.component,
	.container-popup {
		min-height: 240px;
		height: 100%;
		box-sizing: border-box;
	}

	@media (max-width: 768px) {
		#f_url_box {
			display: none;
		}
		.well {
			padding: 2px;
		}
	}
	@media (max-width: 1024px) {
		#folder-lbl {
			display: none;
		}
	}

	@media (max-height: 340px) {
		#folder-lbl, #upload-file-lbl {
			display: none;
		}
		#imageframe {
			height: 57% !important;
		}
	}
	@media (min-height: 341px) and (max-height: 500px) {
		#imageframe {
			height: 67% !important;
		}
	}
	@media (min-height: 501px) and (max-height: 620px) {
		#imageframe {
			height: 74% !important;
		}
	}
	@media (min-height: 621px) and (max-height: 820px) {
		#imageframe {
			height: 84% !important;
		}
	}
	@media (min-height: 821px) {
		#imageframe {
			height: 88% !important;
		}
	}

	@media (max-width: 768px), (max-height: 480px) {
		#f_url_box {
			display: none;
		}
	}

	.well {
		padding: 6px ! important;
	}

	@media (max-width: 480px) {
		#folder-lbl,
		#upload-file-lbl {
			display: none;
		}
	}
');

JFactory::getDocument()->addScriptDeclaration(
"
	jQuery(document).ready(function($){
		if (!!window.parent.fc_dialog_resize_now)
		{
			window.parent.fc_dialog_resize_now();
		}

		var JoomlaImageManager_setFrameUrl_fc = window.ImageManager.setFrameUrl;
		window.ImageManager.setFrameUrl = function (folder, asset, author)
		{
			var qs = {
				option: 'com_media',
				view: 'imagesList',
				layout: 'default_fc',
				tmpl: 'component',
				asset: asset,
				author: author
			};

			// Don't run folder through params because / will end up double encoded.
			this.frameurl = 'index.php?' + $.param(qs) + '&folder=' + folder;
			this.frame.location.href = this.frameurl;
		}
	});
");


/**
 * Mootools compatibility
 *
 * There is an extra option passed in the URL for the iframe &ismoo=0 for the bootstrap fields.
 * By default the value will be 1 or defaults to mootools behaviour
 *
 * This should be removed when mootools won't be shipped by Joomla.
 */
if (!empty($fieldInput)) // Media Form Field
{
	if ($isMoo)
	{
		$onClick = "window.parent.jInsertFieldValue(document.getElementById('f_url').value, '" . $fieldInput . "');window.parent.jModalClose();window.parent.jQuery('.modal.in').modal('hide');";
	}
}
else // XTD Image plugin
{
	$onClick = 'ImageManager.onok();window.parent.jModalClose();';
}
?>
<div class="container-popup">

	<div style="height: 80%;">
	<form style="display:block; height: 100%;" action="index.php?option=com_media&amp;asset=<?php echo $asset; ?>&amp;author=<?php echo $author; ?>" class="form-vertical" id="imageForm" method="post" enctype="multipart/form-data">

		<div id="messages" style="display: none;">
			<span id="message"></span><?php echo JHtml::_('image', 'media/dots.gif', '...', array('width' => 22, 'height' => 12), true); ?>
		</div>

		<div class="well" style="display:block; min-height: 10%; height:auto; box-sizing: border-box;">

			<div class="row" style="margin: 0;">
				<div class="span6 control-group" style="margin: 0; padding: 0 4px 2px 4px; box-sizing: border-box;">
					<!--div class="control-label">
						<label class="control-label" for="folder"><?php echo JText::_('COM_MEDIA_DIRECTORY'); ?></label>
					</div-->
					<div class="controls">
						<label id="folder-lbl" class="badge" for="folder">
							<?php echo JText::_('COM_MEDIA_DIRECTORY'); ?>
						</label>
						<?php echo $this->folderList; ?>
						<button class="btn" type="button" id="upbutton" title="<?php echo JText::_('COM_MEDIA_DIRECTORY_UP'); ?>"><?php echo JText::_('COM_MEDIA_UP'); ?></button>
					</div>
				</div>

				<div class="span6" style="margin: 0; padding: 0;">

					<?php if ($this->state->get('field.id')) : ?>
					<div class="control-group pull-right" id="f_url_box" style="margin: 0; padding: 0 4px 2px 4px; box-sizing: border-box;">
						<!--div class="control-label">
							<label for="f_url"><?php echo JText::_('COM_MEDIA_IMAGE_URL'); ?></label>
						</div-->
						<div class="controls">
							<input type="text" id="f_url" value="" class="" placeholder="<?php echo JText::_('COM_MEDIA_IMAGE_URL', true); ?>" />
						</div>
					</div>
					<?php endif; ?>

					<div class="clearfix"></div>
					<div class="pull-right">
						<button class="btn btn-success button-save-selected" type="button" <?php if (!empty($onClick)) :
						// This is for Mootools compatibility ?>onclick="<?php echo $onClick; ?>"<?php endif; ?> data-dismiss="modal"><?php echo JText::_('COM_MEDIA_INSERT'); ?></button>
						<button class="btn button-cancel" type="button" onclick="window.parent.jQuery('.modal.in').modal('hide');<?php if (!empty($onClick)) :
							// This is for Mootools compatibility ?>parent.jModalClose();<?php endif ?>" data-dismiss="modal"><?php echo JText::_('JCANCEL'); ?></button>
					</div>

				</div>

			</div>

			<div class="clearfix"></div>

		</div>

		<iframe style="display:block; box-sizing: border-box;" id="imageframe" name="imageframe" src="index.php?option=com_media&amp;view=imagesList&amp;filetypes=<?php echo $filetypes; ?>&amp;layout=default_fc&amp;tmpl=component&amp;folder=<?php echo $this->state->folder; ?>&amp;asset=<?php echo $asset; ?>&amp;author=<?php echo $author; ?>"></iframe>

		<?php if (!$this->state->get('field.id')) : ?>
		<div class="well">
			<div class="row-fluid">
				<div class="span6 control-group">
					<div class="control-label">
						<label for="f_url"><?php echo JText::_('COM_MEDIA_IMAGE_URL'); ?></label>
					</div>
					<div class="controls">
						<input type="text" id="f_url" value="" />
					</div>
				</div>

				<div class="span6 control-group">
					<div class="control-label">
						<label title="<?php echo JText::_('COM_MEDIA_ALIGN_DESC'); ?>" class="noHtmlTip" for="f_align"><?php echo JText::_('COM_MEDIA_ALIGN'); ?></label>
					</div>
					<div class="controls">
						<select size="1" id="f_align">
							<option value="" selected="selected"><?php echo JText::_('COM_MEDIA_NOT_SET'); ?></option>
							<option value="left"><?php echo JText::_('JGLOBAL_LEFT'); ?></option>
							<option value="center"><?php echo JText::_('JGLOBAL_CENTER'); ?></option>
							<option value="right"><?php echo JText::_('JGLOBAL_RIGHT'); ?></option>
						</select>
					</div>
				</div>
			</div>

			<div class="row-fluid">
				<div class="span6 control-group">
					<div class="control-label">
						<label for="f_alt"><?php echo JText::_('COM_MEDIA_IMAGE_DESCRIPTION'); ?></label>
					</div>
					<div class="controls">
						<input type="text" id="f_alt" value="" />
					</div>
				</div>
				<div class="span6 control-group">
					<div class="control-label">
						<label for="f_title"><?php echo JText::_('COM_MEDIA_TITLE'); ?></label>
					</div>
					<div class="controls">
						<input type="text" id="f_title" value="" />
					</div>
				</div>
			</div>
			<div class="row-fluid">
				<div class="span6 control-group">
					<div class="control-label">
						<label for="f_caption"><?php echo JText::_('COM_MEDIA_CAPTION'); ?></label>
					</div>
					<div class="controls">
						<input type="text" id="f_caption" value="" />
					</div>
				</div>
				<div class="span6 control-group">
					<div class="control-label">
						<label title="<?php echo JText::_('COM_MEDIA_CAPTION_CLASS_DESC'); ?>" class="noHtmlTip" for="f_caption_class"><?php echo JText::_('COM_MEDIA_CAPTION_CLASS_LABEL'); ?></label>
					</div>
					<div class="controls">
						<input type="text" list="d_caption_class" id="f_caption_class" value="" />
						<datalist id="d_caption_class">
							<option value="text-left">
							<option value="text-center">
							<option value="text-right">
						</datalist>
					</div>
				</div>
			</div>

		</div>
		<?php endif; ?>

		<input type="hidden" id="dirPath" name="dirPath" />
		<input type="hidden" id="f_file" name="f_file" />
		<input type="hidden" id="tmpl" name="component" />

	</form>
	</div>

	<?php if ($user->authorise('core.create', 'com_media')) : ?>
	<div style="height: 20%;">
		<form class="form-vertical" style="display:block; position: relative;" action="<?php echo JUri::base(); ?>index.php?option=com_media&amp;task=file.upload&amp;tmpl=component&amp;<?php echo $this->session->getName() . '=' . $this->session->getId(); ?>&amp;<?php echo JSession::getFormToken(); ?>=1&amp;asset=<?php echo $asset; ?>&amp;author=<?php echo $author; ?>&amp;view=images" id="uploadForm" class="form-horizontal" name="uploadForm" method="post" enctype="multipart/form-data">
			<div id="uploadform" class="well">
				<fieldset id="upload-noflash" class="fc-formbox">
					<div class="row" style="margin: 0;">
						<div class="span6 control-group">
							<!--div class="control-label">
								<label for="upload-file" class="control-label"><?php echo JText::_('COM_MEDIA_UPLOAD_FILE'); ?></label>
							</div-->
							<div class="controls">
								<label id="upload-file-lbl" for="upload-file" class="badge"><?php echo JText::_('COM_MEDIA_UPLOAD_FILE'); ?></label>
								<input required type="file" id="upload-file" name="Filedata[]" multiple />
								<button class="btn btn-primary" id="upload-submit">
									<span class="icon-upload icon-white"></span> <?php echo JText::_('COM_MEDIA_START_UPLOAD'); ?>
								</button>

								<p class="help-block">
									<?php $cMax    = (int) $this->config->get('upload_maxsize'); ?>
									<?php $maxSize = JUtility::getMaxUploadSize($cMax . 'MB'); ?>
									<?php echo JText::sprintf('JGLOBAL_MAXIMUM_UPLOAD_SIZE_LIMIT', JHtml::_('number.bytes', $maxSize)); ?>
								</p>
							</div>
						</div>
					</div>
				</fieldset>
				<?php JFactory::getSession()->set('com_media.return_url', 'index.php?option=com_media&view=images&tmpl=component&fieldid=' . $input->getCmd('fieldid', '') . '&e_name=' . $input->getCmd('e_name') . '&asset=' . $asset . '&author=' . $author); ?>
			</div>
		</form>
	</div>
	<?php endif; ?>
</div>

<?php

// Add video play JS
JHtml::_('script', 'media/mediaelement-and-player.js', array('version' => 'auto', 'relative' => true));
JHtml::_('stylesheet', 'media/mediaelementplayer.css', array('version' => 'auto', 'relative' => true));

// Add preview box for images
echo JHtml::_(
	'bootstrap.renderModal',
	'imagePreview',
	array(
		'title'  => JText::_('COM_MEDIA_PREVIEW'),
		'footer' => '<a type="button" class="btn" data-dismiss="modal" aria-hidden="true">'
			. JText::_('JLIB_HTML_BEHAVIOR_CLOSE') . '</a>',
	),
	'<div id="image" style="text-align:center;"><img id="imagePreviewSrc" src="../media/jui/img/alpha.png" alt="preview" style="max-width:100%; max-height:300px;"/></div>'
);

// Add preview box for videos
echo JHtml::_(
	'bootstrap.renderModal',
	'videoPreview',
	array(
		'title'  => JText::_('COM_MEDIA_PREVIEW'),
		'footer' => '<a type="button" class="btn" data-dismiss="modal" aria-hidden="true">'
			. JText::_('JLIB_HTML_BEHAVIOR_CLOSE') . '</a>',
	),
	'<div id="videoPlayer" style="z-index: -100;"><video id="mejsPlayer" style="height: 250px;"/></div>'
);