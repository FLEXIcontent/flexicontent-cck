<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_media
 *
 * @copyright   Copyright (C) 2005 - 2018 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

$app  = JFactory::getApplication();
$lang = JFactory::getLanguage();
$this->viewLayout = basename(__FILE__, '.php');

JHtml::_('stylesheet', 'media/popup-imagelist.css', array('version' => 'auto', 'relative' => true));

if ($lang->isRtl())
{
	JHtml::_('stylesheet', 'media/popup-imagelist_rtl.css', array('version' => 'auto', 'relative' => true));
}

JFactory::getDocument()->addScriptDeclaration('var ImageManager = window.parent.ImageManager;');

if ($lang->isRtl())
{
	JFactory::getDocument()->addStyleDeclaration(
		'
			@media (max-width: 767px) {
				li.imgOutline.thumbnail.height-80.width-80.center {
					float: right;
					margin-right: 15px;
				}
			}
		'
	);
}
else
{
	JFactory::getDocument()->addStyleDeclaration(
		'
			@media (max-width: 767px) {
				li.imgOutline.thumbnail.height-80.width-80.center {
					float: left;
					margin-left: 15px;
				}
			}
		'
	);
}

// Force container to body of container to full height
JFactory::getDocument()->addStyleDeclaration(
	'
		body.contentpane.component {
			height: 100%;
			box-sizing: border-box;
		}
	'
);

// Only get required documents
$filetypes = JFactory::getApplication()->input->getString('filetypes', false);
$filetypes = $filetypes ?: 'folders,images';
$filetypes = explode(',', $filetypes);

$this->docs    = in_array('docs', $filetypes) ? $this->get('documents') : array();
$this->videos  = in_array('videos', $filetypes) ? $this->get('videos') : array();
$this->images  = in_array('images', $filetypes) ? $this->images : array();
$this->folders = in_array('folders', $filetypes) ? $this->folders : array();

// Add image preview on click to preview icon
if (count($this->images))
{
	JFactory::getDocument()->addScriptDeclaration(
	"
		jQuery(document).ready(function($){

			$('.img-preview-icon').each(function(index, value) {
				$(this).on('click', function(e) {
					window.parent.jQuery('#imagePreviewSrc').attr('src', $(this).attr('href'));
					window.parent.jQuery('#imagePreview').modal('show');
					return false;
				});
			});
		});
 ");
}

// Add video preview on click to preview icon
if (count($this->videos))
{
	JFactory::getDocument()->addScriptDeclaration(
	"
		jQuery(document).ready(function($){

			$('.img-preview-icon').each(function(index, value) {
				$(this).on('click', function(e) {
					window.parent.jQuery('#imagePreviewSrc').attr('src', $(this).attr('href'));
					window.parent.jQuery('#imagePreview').modal('show');
					return false;
				});
			});
			$('.video-preview-icon').each(function(index, value) {
				$(this).unbind('click');
				$(this).on('click', function(e) {
					e.preventDefault();
					window.parent.jQuery('#videoPreview').modal('show');

					var elementInitialised = window.parent.jQuery('#mejsPlayer').attr('src');

					if (!elementInitialised)
					{
						window.parent.jQuery('#mejsPlayer').attr('src', $(this).attr('href'));
						window.parent.jQuery('#mejsPlayer').mediaelementplayer({width: 880, height: 640});
					}

					window.parent.jQuery('#mejsPlayer')[0].player.media.setSrc($(this).attr('href'));
					window.parent.jQuery('#mejsPlayer')[0].player.media.setVideoSize(480, 250);

					return false;
				});
			});
		});

		jQuery(document).ready(function($){
			window.parent.jQuery('#videoPreview').on('hidden', function () {
				window.parent.jQuery('#mejsPlayer')[0].player.pause();
			});
		});
 ");
}

$params     = new Joomla\Registry\Registry;
$dispatcher = JEventDispatcher::getInstance();
$asset  = JFactory::getApplication()->input->getCmd('asset');
$author = JFactory::getApplication()->input->getCmd('author');

if (count($this->images) > 0 || count($this->videos) > 0 || count($this->docs) > 0 || count($this->folders) > 0) : ?>
	<ul class="manager thumbnails thumbnails-media">
		<?php for ($i = 0, $n = count($this->folders); $i < $n; $i++) :
			$folder =  &$this->folders[$i];
			?>
			<li class="imgOutline thumbnail height-80 width-80 center">
				<a href="index.php?option=com_media&amp;view=imagesList&amp;layout=<?php echo $this->viewLayout;?>&amp;tmpl=component&amp;folder=<?php echo $folder->path_relative; ?>&amp;asset=<?php echo $asset;?>&amp;author=<?php echo $author;?>" target="imageframe">
					<div class="imgFolder">
						<span class="icon-folder-2"></span>
					</div>
					<div class="small">
						<?php echo JHtml::_('string.truncate', $folder->name, 10, false); ?>
					</div>
				</a>
			</li>
			<?php

		endfor;

		for ($i = 0, $n = count($this->docs); $i < $n; $i++) :
			$file = & $this->docs[$i];
			FLEXI_J40GE
				? $app->triggerEvent('onContentBeforeDisplay', array('com_media.file', &$this->_tmp_doc, &$params))
				: $dispatcher->trigger('onContentBeforeDisplay', array('com_media.file', &$this->_tmp_doc, &$params));
			?>

			<li class="imgOutline thumbnail height-80 width-80 center">
				<a class="img-preview" href="javascript:ImageManager.populateFields('<?php echo $file->path_relative; ?>')" title="<?php echo $file->name; ?>" >
					<div class="imgThumb">
						<div class="imgThumbInside">
							<?php echo JHtml::_('image', $file->icon_32, $file->name, null, true, true) ? JHtml::_('image', $file->icon_32, $file->title, null, true) : JHtml::_('image', 'media/con_info.png', $file->name, null, true); ?>
						</div>
					</div>
					<div class="imgDetails small">
						<?php echo JHtml::_('string.truncate', $file->name, 10, false); ?>
					</div>
				</a>
			</li>

			<?php
			FLEXI_J40GE
				? $app->triggerEvent('onContentAfterDisplay', array('com_media.file', &$file, &$params))
				: $dispatcher->trigger('onContentAfterDisplay', array('com_media.file', &$file, &$params));

		endfor;

		for ($i = 0, $n = count($this->videos); $i < $n; $i++) :
			$file = & $this->videos[$i];
			FLEXI_J40GE
				? $app->triggerEvent('onContentBeforeDisplay', array('com_media.file', &$file, &$params))
				: $dispatcher->trigger('onContentBeforeDisplay', array('com_media.file', &$file, &$params));
			?>

			<li class="imgOutline thumbnail height-80 width-80 center">
				<a class="img-preview" href="javascript:ImageManager.populateFields('<?php echo $file->path_relative; ?>')" title="<?php echo $file->name; ?>" >
					<div class="imgThumb">
						<div class="imgThumbInside">
							<?php echo JHtml::_('image', $file->icon_32, $file->name, null, true, true) ? JHtml::_('image', $file->icon_32, $file->title, null, true) : JHtml::_('image', 'media/con_info.png', $file->name, null, true); ?>
						</div>
					</div>
					<div class="imgDetails small">
						<?php echo JHtml::_('string.truncate', $file->name, 10, false); ?>
					</div>
				</a>
				<span class="video-preview-icon btn icon-search" style="z-index: 2; position: absolute; bottom: 0; right: 0; padding: 4px; box-sizing: content-box; margin: 0;" href="<?php echo COM_MEDIA_BASEURL, '/', $file->name; ?>" title="<?php echo $file->name; ?>"></span>
			</li>
			<?php
			FLEXI_J40GE
				? $app->triggerEvent('onContentAfterDisplay', array('com_media.file', &$file, &$params))
				: $dispatcher->trigger('onContentAfterDisplay', array('com_media.file', &$file, &$params));

		endfor;

		for ($i = 0, $n = count($this->images); $i < $n; $i++) :
			$file = & $this->images[$i];
			FLEXI_J40GE
				? $app->triggerEvent('onContentBeforeDisplay', array('com_media.file', &$file, &$params))
				: $dispatcher->trigger('onContentBeforeDisplay', array('com_media.file', &$file, &$params));
			?>

			<li class="imgOutline thumbnail height-80 width-80 center">
				<a class="img-preview" href="javascript:ImageManager.populateFields('<?php echo $file->path_relative; ?>')" title="<?php echo $file->name; ?>" >
					<div class="imgThumb">
						<div class="imgThumbInside">
						<?php echo JHtml::_('image', $this->baseURL . '/' . $file->path_relative, JText::sprintf('COM_MEDIA_IMAGE_TITLE', $file->title, JHtml::_('number.bytes', $file->size)), array('width' => $file->width_60, 'height' => $file->height_60)); ?>
						</div>
					</div>
					<div class="imgDetails small">
						<?php echo JHtml::_('string.truncate', $file->name, 10, false); ?>
					</div>
				</a>
				<span class="img-preview-icon btn icon-search" style="z-index: 2; position: absolute; bottom: 0; right: 0; padding: 4px; box-sizing: content-box; margin: 0;" href="<?php echo COM_MEDIA_BASEURL, '/', $file->name; ?>" title="<?php echo $file->name; ?>"></span>
			</li>
			<?php
			FLEXI_J40GE
				? $app->triggerEvent('onContentAfterDisplay', array('com_media.file', &$file, &$params))
				: $dispatcher->trigger('onContentAfterDisplay', array('com_media.file', &$file, &$params));

		endfor; ?>

	</ul>
<?php else : ?>
	<div id="media-noimages">
		<div class="alert alert-info"><?php echo JText::_('NO_FILES_FOUND'); ?></div>
	</div>
<?php endif; ?>
