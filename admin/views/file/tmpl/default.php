<?php
/**
 * @version 1.5 stable $Id: default.php 1604 2012-12-16 11:55:43Z ggppdk $
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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

defined('_JEXEC') or die('Restricted access');

require_once JPATH_SITE . '/components/com_flexicontent/classes/helpers/fileeditor.php';
$fc_native_atum = \Joomla\CMS\Factory::getApplication()->getTemplate() === 'atum';
$fc_atum_control = $fc_native_atum ? ' form-control' : '';
$fc_editor_params = ComponentHelper::getParams('com_flexicontent');
$fc_editor_attrs = static function($field) use ($fc_editor_params) {
	return ' data-fc-file-editor="' . $field . '"' . (flexicontent_fileeditor::visible($fc_editor_params, $field) && ($field !== 'path' || \Joomla\CMS\Factory::getApplication()->getIdentity()->authorise('flexicontent.editfileproperties', 'com_flexicontent')) ? '' : ' hidden style="display:none!important"');
};
$fc_download_full = flexicontent_fileeditor::downloadName($this->row->filename_original ?: $this->row->filename, $this->row->filename);
$fc_download_ext = flexicontent_fileeditor::extension($this->row->filename);
$fc_download_stem = $fc_download_ext !== '' ? substr($fc_download_full, 0, -strlen($fc_download_ext) - 1) : $fc_download_full;
$fc_editor_escape = static function($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); };

$jmedia_filetypes = array('folders', 'images', 'docs', 'videos', 'audios');

$mediaTypes = [];
$mediaTypeNames = [];
if (version_compare(\Joomla\CMS\Version::MAJOR_VERSION, '4', 'ge'))
{
	// 0: images, 1: audios, 2: videos, 3: documents * 'folders' is always included in J4
	$mediaTypes = [];
	if (in_array('images', $jmedia_filetypes)) { $mediaTypes[] = '0'; $mediaTypeNames[] = 'Images'; }
	if (in_array('audios', $jmedia_filetypes)) { $mediaTypes[] = '1'; $mediaTypeNames[] = 'Audios'; }
	if (in_array('videos', $jmedia_filetypes)) { $mediaTypes[] = '2'; $mediaTypeNames[] = 'Videos'; }
	if (in_array('docs',   $jmedia_filetypes)) { $mediaTypes[] = '3'; $mediaTypeNames[] = 'Documents'; }
}
else {
	$fileTypes = implode(',', $jmedia_filetypes); // Supported values: 'folders,images,docs,videos' * audios will be ignored in J3
}
// Only implode the mediaTypes array , but not the mediaTypeNames array, since it is not used in the layout file as an array
$mediaTypes = implode(',', $mediaTypes);  // * 'folders' is always included in J4

$tip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
$btn_class = FLEXI_J30GE ? 'btn' : 'fc_button fcsimple';
$disabled = $this->row->url ? '' : ' disabled="disabled"';
$fc_can_edit_properties = \Joomla\CMS\Factory::getApplication()->getIdentity()->authorise('flexicontent.editfileproperties', 'com_flexicontent');
?>

<script>
	function handleFormSumbit(form)
	{
		if (form.file_replacement && form.file_replacement.value != '')
		{
			file_replacement_box_info.style.display = 'block';
			file_replacement_box_input.style.display = 'none';
			return false;
		}
		return true;
	}
</script>
<?php
// Opened from the file field of the item form (inside a modal dialog): the toolbar is not available, so we show our own Save / Close buttons
$fc_in_modal = \Joomla\CMS\Factory::getApplication()->input->getCmd('tmpl') === 'component'
	&& \Joomla\CMS\Factory::getApplication()->input->getInt('fcmodal', 0)
	&& (int) $this->row->id;
?>
<div id="flexicontent" class="flexicontent">
<?php if ($fc_in_modal) : ?>
	<div class="fc-file-modal-bar<?php echo $fc_native_atum ? ' p-3 border-bottom bg-body' : ''; ?>" style="position: sticky; top: 0; z-index: 10; padding: 8px 0; margin-bottom: 8px; background: inherit; display: flex; gap: 8px; align-items: center;">
		<button type="button" class="btn btn-success<?php echo $fc_native_atum ? ' d-inline-flex align-items-center gap-2' : ''; ?>" onclick="return fcFileModalSaveAndClose(this);">
			<span class="icon-save" aria-hidden="true"></span> <?php echo Text::_('JSAVE'); ?>
		</button>
		<button type="button" class="btn <?php echo $fc_native_atum ? 'btn-outline-secondary d-inline-flex align-items-center gap-2' : 'btn-secondary'; ?>" onclick="fcFileModalClose(); return false;">
			<span class="icon-cancel" aria-hidden="true"></span> <?php echo Text::_('JTOOLBAR_CLOSE'); ?>
		</button>
	</div>
<?php endif; ?>
<form action="index.php" method="post" name="adminForm" id="adminForm" class="form-validate form-horizontal<?php echo $fc_native_atum ? ' fc-file-editor-atum p-3' : ''; ?>" onsubmit="handleFormSumbit(this)" enctype="multipart/form-data">

	<table class="fc-form-tbl">

		<tr<?php echo $fc_editor_attrs('storage'); ?>>
			<td class="key hasTooltip" title="<?php echo ((int) $this->row->url === 0)
				? flexicontent_html::getToolTip('FLEXI_FILE_STORAGE_FILENAME', 'FLEXI_FILE_STORAGE_FILENAME_DESC', 1, 1)
				: flexicontent_html::getToolTip('FLEXI_URL_LINK', 'FLEXI_URL_LINK_DESC', 1, 1)
				; ?>">
				<label class="fc-prop-lbl" for="filename">
					<?php
					switch ((int) $this->row->url)
					{
						case 0:
							echo \Joomla\CMS\Language\Text::_('FLEXI_FILE_STORAGE_FILENAME');
							break;
						case 1:
							echo \Joomla\CMS\Language\Text::_('FLEXI_URL_LINK');
							break;
						case 2:
							echo \Joomla\CMS\Language\Text::_('FLEXI_JMEDIA_LINK');
							break;
					}
					?>
				</label>
			</td>
			<td>
				<?php if ((int) $this->row->url === 2) :

										$use_quantum = 0;//ComponentHelper::isEnabled('com_quantummanager');
										if ($use_quantum)
										{
											$modal_url   = "index.php?option=com_ajax&view=default&tmpl=component&asset=com_content&author=&fieldid=filename&plugin=quantummanagermedia&format=html";
											$modal_url  .= '&folder=';
											$modal_title = 'Select file'; $width = 0; $height = 0;
											$onclick_js  = "var url = jQuery(this).attr('data-href'); "
												. " var the_dialog = fc_showDialog(url, 'fc_modal_popup_container', 0, {$width}, {$height}, null, "
												. " {title:'{$modal_title}', loadFunc: null}); return false;";
											$select_file_btn =
												'<a class="form-control btn btn-info customform-btn fit-contents"
													onclick="'.$onclick_js.'" href="javascript:" data-href="'.$modal_url.'"
												><i class="icon-search"></i></a>';  // &nbsp; Select
											$juri_root = \Joomla\CMS\Uri\Uri::root(true);
											$file_placeholder_text = 'No file selected';
											$file_placeholder_src  = $juri_root . '/' .'administrator/components/com_events/assets/images/person_placeholder.jpg';
											$file_clear_value_js   = "jQuery(this).parent().find('input[type=text]').val(''); jQuery(this).parent().parent().find('.inline-preview-img').attr('src', '".$file_placeholder_src."'); ";
											$value_src = $this->row->filename ? $juri_root . '/' . $this->row->filename : $file_placeholder_src;

											$file_is_img  = in_array(strtolower(pathinfo($this->row->filename, PATHINFO_EXTENSION)), array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'));
											$filename_ext = pathinfo($this->row->filename, PATHINFO_EXTENSION);
											$preview_alt  = 'File selected'; //strtoupper($filename_ext);

									echo <<<HTML
										<div class="control-group">
											<div class="controls">
												<div style="display:flex; align-items:center; width:100%; flex-direction: column;">
													<img alt="{$preview_alt}" src="{$value_src}" data-juri-root="{$juri_root}" class="inline-preview-img" style="max-width:300px"/>
												</div>
												<div class="input-group">
													<input class="form-control input-group-prepend" type="text" readonly="" style="background:#eee; border:1px solid #aaa; min-width: min(500px, 80vw); max-width:1020px" value="{$this->row->filename}" id="filename" name="filename" placeholder="{$file_placeholder_text}" />
													{$select_file_btn}
													<button type="button" href="#" title="Clear" class="form-control btn input-group-append fit-contents clear-btn" onclick="{$file_clear_value_js}"><i class="icon-cancel"></i></button>
												</div>
											</div>
										</div>
HTML;
										} else {

										  /*
											// Creation of modal URL in J3 LAYOUT
											$url    = ($readonly ? ''
												: ($link ?: 'index.php?option=com_media&amp;view=images&amp;tmpl=component&amp;asset='
													. $asset . '&amp;author=' . $authorId)
												. '&amp;fieldid={field-media-id}&amp;ismoo=0&amp;folder=' . $folder);

											// Creation of modal URL in J4+ LAYOUT
											$url = ($readonly ? ''
												: ($link ?: 'index.php?option=com_media&view=media&tmpl=component&mediatypes=' . $mediaTypes
													. '&asset=' . $asset . '&author=' . $authorId)
												. '&fieldid={field-media-id}&path=' . $folder);
											*/

											$modal_url = version_compare(\Joomla\CMS\Version::MAJOR_VERSION, '4', 'lt')
											  ? 'index.php?option=com_media&amp;view=media&amp;tmpl=component&amp;asset=com_flexicontent&amp;filetypes=' . $fileTypes . '&amp;author='
											  : 'index.php?option=com_media&amp;view=media&amp;tmpl=component&amp;asset=com_flexicontent&amp;mediatypes=' . $mediaTypes . '&amp;author=';

											$media_params = ComponentHelper::getParams('com_media');
										  $imagesExt    = in_array('images', $jmedia_filetypes)
											  ? array_map('trim', explode(',', $media_params->get('image_extensions', 'bmp,gif,jpg,jpeg,png,webp')))
											  : [];
										  $videosExt    = in_array('videos', $jmedia_filetypes)
											  ? array_map('trim', explode(',', $media_params->get('video_extensions', 'mp4,mp4v,mpeg,mov,webm')))
											  : [];
										  $audiosExt    = in_array('audios', $jmedia_filetypes)
											  ? array_map('trim', explode(',', $media_params->get('audio_extensions', 'mp3,m4a,mp4a,ogg')))
											  : [];
										  $documentsExt = in_array('docs', $jmedia_filetypes)
											  ? array_map('trim', explode(',', $media_params->get('doc_extensions', 'doc,odg,odp,ods,odt,pdf,ppt,txt,xcf,xls,csv')))
											  : [];

										  // SEE top of file: layouts/joomla/form/field/media.php
											$jMedia_file_displayData = [
												'disabled' => false,
												'preview' => 'tooltip',   // 'false', 'none', 'true', 'show', 'tooltip'
												'readonly' => false,
												'class' => 'required',
												'link' => $modal_url,
												'asset' => 'com_flexicontent',
												'authorId' => '',
												'previewWidth' => 320,
												'previewHeight' => 240,
												'name' => 'filename',
												'id' => 'filename',
												'value' => $this->row->filename,

												// J3 sub-path inside JPATH_ROOT/images
												// J4 sub-path inside JPATH_ROOT/top-level-directory, default is JPATH_ROOT/media
												'folder' => '',
										  ];

										  if (version_compare(\Joomla\CMS\Version::MAJOR_VERSION, '4', 'ge')) {
											  $jMedia_file_displayData += [
												  // J4 only, Miscellaneous data attributes preprocessed for HTML output, e.g. ' data-somename1="somevalue1" data-somename2="somevalue2" '
												  'dataAttribute' => '',

												  // J4 only, supported media types for the Media Manager
												  'mediaTypes'   => $mediaTypes,  // e.g. '0,3' Supported values '0,1,2,3', 0: images, 1: audios, 2: videos, 3: documents * 'folders' is always included in J4
												  'mediaTypeNames' => $mediaTypeNames,  // Layout expects the types names as an array and not as an comma separated string
												  'imagesExt'    => $imagesExt,
												  'audiosExt'    => $audiosExt,
												  'videosExt'    => $videosExt,
												  'documentsExt' => $documentsExt,
											  ];
										  }
										  else {
											  $jMedia_file_displayData += [
												  // J3 supported media types for the Media Manager
												  'filetypes'   => $fileTypes,    // e.g. 'folders,images,docs' Supported values: 'folders,images,docs,videos' * audios will be ignored in J3
											  ];
										  }

											$media_field = \Joomla\CMS\Layout\LayoutHelper::render($media_field_layout = 'joomla.form.field.media', $jMedia_file_displayData, $layouts_path = null);
											$media_field_html = str_replace('{field-media-id}', 'field-media-data' , $media_field);
											echo $media_field_html;
										}

				else :

					echo '
					<input type="text" id="filename" name="filename" value="' . $this->row->filename . '" class="input-xxlarge input-xxl required' . $fc_atum_control . '" maxlength="4000"
					' . ((int)$this->row->url === 0 ? ' readonly="readonly" style="background:#eee; border:1px solid #aaa" ' : '') . '
					/>
					';

				endif; ?>

			</td>
		</tr>

		<?php if ($this->row->url != 2): ?>
		<tr<?php echo $fc_editor_attrs('download_filename'); ?>>
			<td class="key hasTooltip" title="<?php echo flexicontent_html::getToolTip('FLEXI_DOWNLOAD_FILENAME', 'FLEXI_FILE_EDITOR_DOWNLOAD_DESC', 1, 1); ?>">
				<label class="fc-prop-lbl" for="filename_original_name">
					<?php
						echo \Joomla\CMS\Language\Text::_('FLEXI_DOWNLOAD_FILENAME');
					?>
				</label>
			</td>
			<td>
				<div class="input-group fc-download-filename">
					<input type="hidden" id="filename_original" name="filename_original" value="<?php echo $fc_editor_escape($fc_download_full); ?>" />
					<input type="text" id="filename_original_name" value="<?php echo $fc_editor_escape($fc_download_stem); ?>" class="input-xxlarge required<?php echo $fc_atum_control; ?>" maxlength="3900" aria-describedby="filename_original_extension" />
					<span class="input-group-text<?php echo $fc_native_atum ? ' bg-body-tertiary text-body border' : ''; ?>" id="filename_original_extension" data-extension="<?php echo $fc_editor_escape($fc_download_ext); ?>"><?php echo $fc_editor_escape($fc_download_ext !== '' ? '.' . $fc_download_ext : ''); ?></span>
				</div>
			</td>
		</tr>
		<?php endif; ?>

		<tr<?php echo $fc_editor_attrs('title'); ?>>
			<td class="key hasTooltip" title="<?php echo flexicontent_html::getToolTip('FLEXI_FILE_DISPLAY_TITLE', 'FLEXI_FILE_DISPLAY_TITLE_DESC', 1, 1); ?>">
				<label class="fc-prop-lbl" for="altname">
					<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_FILE_DISPLAY_TITLE' ); ?>
				</label>
			</td>
			<td>
				<input type="text" id="altname" name="altname" value="<?php echo htmlspecialchars((string) $this->row->altname, ENT_QUOTES, 'UTF-8'); ?>" maxlength="4000" class="input-xxlarge required<?php echo $fc_atum_control; ?>" />
			</td>
		</tr>

		<tr<?php echo $fc_editor_attrs('description'); ?>>
			<td class="key hasTooltip" title="<?php echo flexicontent_html::getToolTip('FLEXI_DESCRIPTION', 'FLEXI_FILE_DESCRIPTION_DESC', 1, 1); ?>">
				<label class="fc-prop-lbl" for="file-desc">
				<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_DESCRIPTION' ); ?>
				</label>
			</td>
			<td>
				<textarea name="description" rows="5" class="input-xxlarge<?php echo $fc_atum_control; ?>" id="file-desc"><?php echo htmlspecialchars((string) $this->row->description, ENT_QUOTES, 'UTF-8'); ?></textarea>
			</td>
		</tr>


		<?php if ($this->row->url != 1) : ?>

			<tr><td colspan="2"></td></tr>

			<tr<?php echo $fc_editor_attrs('size'); ?>>
				<td class="key">
					<span class="label text-white bg-info label-info"><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_SIZE' ); ?></span> &nbsp;
				</td>
				<td>
					<?php echo file_exists($this->rowdata->path) ? $this->rowdata->size_display : \Joomla\CMS\Language\Text::_('FLEXI_FILE_NOT_FOUND'); ?>
				</td>
			</tr>

			<tr<?php echo $fc_editor_attrs('path'); ?>>
				<td class="key">
					<span class="label text-white bg-info label-info"><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_REAL_PATH' ); ?></span> &nbsp;
				</td>
				<td>
					<?php echo $this->rowdata->path;?>
				</td>
			</tr>

		<?php else: ?>

			<tr<?php echo $fc_editor_attrs('size'); ?>>
				<td class="key hasTooltip" title="<?php echo flexicontent_html::getToolTip('FLEXI_SIZE', 'FLEXI_SIZE_IN_FORM', 1, 1); ?>">
					<label class="fc-prop-lbl" for="size">
						<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_SIZE' ); ?>
					</label>
				</td>
				<td>
					<div class="input-group">
						<input type="text" id="size" name="size" value="<?php echo ceil(((int)$this->rowdata->calculated_size)/1024.0); ?>" size="10" style="max-width:200px;" maxlength="100"/>
						<select id="size_unit" name="size_unit" class="use_select2_lib">
							<option value="KBs" selected="selected">KBs</option>
							<option value="MBs">MBs</option>
							<option value="GBs">GBs</option>
						</select>
						<span class="hasTooltip" title="<?php echo flexicontent_html::getToolTip('FLEXI_SIZE', 'FLEXI_SIZE_IN_FORM', 1, 1); ?>">&nbsp;  <i class="icon-info"></i></span>
					</div>

					<?php echo $this->rowdata->size_warning; ?>
				</td>
			</tr>

		<?php endif; ?>


		<?php if (!$this->row->url) : ?>

		<tr><td colspan="2"></td></tr>

		<tr<?php echo $fc_editor_attrs('replace'); ?>>
			<td class="key">
				<span class="<?php echo $fc_native_atum ? 'form-label' : 'label text-white bg-error label-warning'; ?>"><?php echo \Joomla\CMS\Language\Text::_( 'Upload & Replace' ); ?></span> &nbsp;
			</td>
			<td>
				<div id="file_replacement_box">
					<div id="file_replacement_box_input">
						<div<?php echo $fc_native_atum ? ' class="d-flex flex-column gap-2"' : ' style="display: flex; gap: 24px;"'; ?>>
							<div class="<?php echo $fc_native_atum ? 'form-text alert-info' : 'alert alert-info'; ?>" style="margin:0">Maximum file size: <?php echo min(ini_get('upload_max_filesize'), ini_get('post_max_size')); ?></div>
							<input type="file" id="file_replacement" name="file_replacement" class="input-xxlarge<?php echo $fc_atum_control; ?>" />
						</div>
						<span<?php echo $fc_editor_attrs('keep_uploaded_date'); ?>><input type="checkbox" class="<?php echo $fc_native_atum ? 'form-check-input me-1' : ''; ?>" id="keep_uploaded_date" name="keep_uploaded_date" /> <label for="keep_uploaded_date">Keep old upload date</label><br></span>
						<span<?php echo $fc_editor_attrs('keep_uploaded_by'); ?>><input type="checkbox" class="<?php echo $fc_native_atum ? 'form-check-input me-1' : ''; ?>" id="keep_uploaded_by" name="keep_uploaded_by" /> <label for="keep_uploaded_by">Keep old uploader (user)</label><br></span>
					</div>
					<div id="file_replacement_box_info" style="display:none;">
						<div class="fc_loading_msg alert alert-info"> &nbsp; &nbsp; <?php echo Text::_('FLEXI_UPLOADING') .' ... '. Text::_('FLEXI_PLEASE_WAIT'); ?></div>
					</div>
				</div>
			</td>
		</tr>
	<?php endif; ?>


		<?php if ($fc_can_edit_properties && (flexicontent_fileeditor::visible($fc_editor_params, 'language') || flexicontent_fileeditor::visible($fc_editor_params, 'access') || flexicontent_fileeditor::visible($fc_editor_params, 'stamp') || flexicontent_fileeditor::visible($fc_editor_params, 'hits'))) : ?>
		<tr>
			<td></td><td><input style="margin-top: 64px;" type="button" class="btn btn-primary" value="Advanced properties" onclick="jQuery('.advanced-file-props').toggle({ direction: 'left' }, 300);"/></td>
		</tr>

		<tr<?php echo $fc_editor_attrs('language'); ?> class="advanced-file-props">
			<td class="key hasTooltip" title="<?php echo flexicontent_html::getToolTip('FLEXI_LANGUAGE', 'FLEXI_FILE_LANGUAGE_DESC', 1, 1); ?>">
				<label class="fc-prop-lbl" for="language">
					<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_LANGUAGE' ); ?>
				</label>
			</td>
			<td>
				<span style="width:94%; max-width:800px; display:inline-block;">
					<?php echo $this->lists['language']; ?>
				</span>
			</td>
		</tr>

		<tr<?php echo $fc_editor_attrs('access'); ?> class="advanced-file-props">
			<td class="key hasTooltip" title="<?php echo flexicontent_html::getToolTip('FLEXI_ACCESS', 'FLEXI_FILE_ACCESS_DESC', 1, 1); ?>">
				<label class="fc-prop-lbl" for="access">
					<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_ACCESS' ); ?>
				</label>
			</td>
			<td>
				<span class="fc-file-narrow" style="display:inline-block; width:100%; max-width:320px;"><?php echo $this->lists['access']; ?></span>
			</td>
		</tr>

		<tr<?php echo $fc_editor_attrs('stamp'); ?> class="advanced-file-props">
			<td class="key hasTooltip" title="<?php echo flexicontent_html::getToolTip('FLEXI_DOWNLOAD_STAMPING', 'FLEXI_FILE_DOWNLOAD_STAMPING_CONF_FILE_FIELD_DESC', 1, 1); ?>">
				<label class="fc-prop-lbl" data-for="stamp">
					<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_DOWNLOAD_STAMPING' ); ?>
				</label>
			</td>
			<td>
				<?php echo $this->lists['stamp']; ?>
			</td>
		</tr>

		<tr<?php echo $fc_editor_attrs('hits'); ?> class="advanced-file-props">
			<td class="key hasTooltip" title="<?php echo flexicontent_html::getToolTip('FLEXI_HITS', 'FLEXI_DOWNLOAD_HITS', 1, 1); ?>">
				<label class="fc-prop-lbl" for="access">
					<?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_HITS' ); ?>
				</label>
			</td>
			<td>
				<input type="text" id="hits" name="hits" value="<?php echo $this->row->hits; ?>" maxlength="10" class="input-<?php echo $fc_atum_control; ?>" />
			</td>
		</tr>


		<?php endif; ?>
		<tr<?php echo $fc_editor_attrs('type'); ?>>
			<td class="key hasTooltip" title="<?php echo flexicontent_html::getToolTip('FLEXI_FILE_EDITOR_TYPE', 'FLEXI_FILE_EDITOR_TYPE_DESC', 1, 1); ?>">
				<label class="fc-prop-lbl" for="mime_ext"><?php echo Text::_('FLEXI_FILE_EDITOR_TYPE'); ?></label>
			</td>
			<td><input type="text" id="mime_ext" value="<?php echo $fc_editor_escape(strtoupper($fc_download_ext)); ?>" readonly aria-readonly="true" class="<?php echo $fc_native_atum ? 'form-control-plaintext' : 'input-'; ?>" /></td>
		</tr>
	</table>


<?php echo \Joomla\CMS\HTML\HTMLHelper::_( 'form.token' ); ?>
<input type="hidden" name="option" value="com_flexicontent" />
<?php if (!$this->row->url) : ?>
<input type="hidden" name="ext" value="<?php echo htmlspecialchars((string) $this->row->ext, ENT_QUOTES, 'UTF-8'); ?>" />
<?php endif; ?>
<input type="hidden" name="url" value="<?php echo htmlspecialchars((string) $this->row->url, ENT_QUOTES, 'UTF-8'); ?>" />
<input type="hidden" name="secure" value="<?php echo $this->row->secure; ?>" />
<input type="hidden" name="uploaded" value="<?php echo htmlspecialchars((string) $this->row->uploaded, ENT_QUOTES, 'UTF-8'); ?>" />
<input type="hidden" name="uploaded_by" value="<?php echo $this->row->uploaded_by; ?>" />
<input type="hidden" name="published" value="<?php echo $this->row->published; ?>" />
<input type="hidden" name="id" value="<?php echo $this->row->id; ?>" />
<?php if ($fc_in_modal) : ?>
<input type="hidden" name="tmpl" value="component" />
<input type="hidden" name="fcmodal" value="1" />
<input type="hidden" name="fieldid" value="<?php echo \Joomla\CMS\Factory::getApplication()->input->getInt('fieldid', 0); ?>" />
<?php endif; ?>
<input type="hidden" name="controller" value="filemanager" />
<input type="hidden" name="view" value="file" />
<input type="hidden" name="task" value="" />
</form>
</div>

<?php
//keep session alive while editing
\Joomla\CMS\HTML\HTMLHelper::_('behavior.keepalive');
?>

<style>
  /* Native Atum presentation; template overrides keep their existing layout. */
  #flexicontent #adminForm.fc-file-editor-atum table.fc-form-tbl td.key { text-align: start; padding-block: .75rem; }
  #flexicontent #adminForm.fc-file-editor-atum table.fc-form-tbl td { padding-block: .5rem; }
  #flexicontent #adminForm.fc-file-editor-atum .label { color: var(--body-color) !important; background: none !important; padding: 0; font-size: inherit; }
  #flexicontent #adminForm.fc-file-editor-atum .form-control { min-height: 2.75rem; }
  #flexicontent #adminForm.fc-file-editor-atum #file_replacement_box_input .form-text { order: 1; margin-top: 0; }
  #flexicontent #adminForm.fc-file-editor-atum #file_replacement_box_input > span { display: block; margin-top: .5rem; }
  #flexicontent #adminForm.fc-file-editor-atum input[value="Advanced properties"] { margin-top: 1rem !important; }
  @media (max-width: 575.98px) {
    #flexicontent #adminForm.fc-file-editor-atum table.fc-form-tbl, #flexicontent #adminForm.fc-file-editor-atum table.fc-form-tbl tbody, #flexicontent #adminForm.fc-file-editor-atum table.fc-form-tbl tr, #flexicontent #adminForm.fc-file-editor-atum table.fc-form-tbl td { display: block; width: 100%; min-width: 0; }
    #flexicontent #adminForm.fc-file-editor-atum table.fc-form-tbl td.key { padding: .5rem 0 0; }
  }
  .flexicontent .field-media-wrapper .field-media-input {
    width: unset;
    min-width: 60% !important;
    max-width: unset;
  }

  /* File edit form layout: label column + fluid fields */
  #flexicontent #adminForm table.fc-form-tbl {
    width: 100%;
    max-width: 1100px;
    table-layout: auto;
    border-collapse: separate;
    border-spacing: 0 6px;
  }
  #flexicontent #adminForm table.fc-form-tbl td.key {
    width: 190px;
    min-width: 150px;
    text-align: right;
    vertical-align: top;
    padding: 8px 14px 0 0;
    white-space: normal;
  }
  #flexicontent #adminForm table.fc-form-tbl td:not(.key) {
    width: auto;
    vertical-align: top;
  }
  /* Text fields (URL, filenames, title, description): use the whole available width */
  #flexicontent #adminForm input.input-xxlarge,
  #flexicontent #adminForm input.input-xxl,
  #flexicontent #adminForm textarea.input-xxlarge,
  #flexicontent #adminForm input#filename,
  #flexicontent #adminForm input#filename_original_name,
  #flexicontent #adminForm input#altname {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
  }
  #flexicontent #adminForm textarea#file-desc {
    min-height: 90px;
    resize: vertical;
  }
  #flexicontent #adminForm .fc-download-filename { width: 100%; }
  #flexicontent #adminForm .fc-download-filename > input { flex: 1 1 0; min-width: 0; width: 0; }
  #flexicontent #adminForm #filename_original_extension { flex: 0 0 auto; white-space: nowrap; }
  /* Parameter fields: compact */
  #flexicontent #adminForm .advanced-file-props select,
  #flexicontent #adminForm .advanced-file-props .select2-container,
  #flexicontent #adminForm .advanced-file-props .chzn-container,
  #flexicontent #adminForm .fc-file-narrow * {
    width: 100% !important;
    max-width: 320px !important;
  }
  #flexicontent #adminForm .advanced-file-props span.fc-file-narrow,
  #flexicontent #adminForm .advanced-file-props span[style*="width:94%"] {
    width: 100% !important;
    max-width: 320px !important;
  }
  #flexicontent #adminForm input#hits {
    width: 140px;
    max-width: 140px;
  }
  #flexicontent #adminForm input#size {
    width: 140px;
    max-width: 140px;
  }
  #flexicontent #adminForm .input-group {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 6px;
    width: auto;
  }
  #flexicontent #adminForm .input-group > select,
  #flexicontent #adminForm .input-group > .select2-container {
    max-width: 220px !important;
    width: auto !important;
    flex: 0 1 220px;
  }
  #flexicontent #adminForm input#mime_ext {
    width: 100px;
    max-width: 100px;
  }
  #flexicontent #adminForm .advanced-file-props fieldset.radio,
  #flexicontent #adminForm .advanced-file-props .btn-group {
    margin: 0;
  }
  /* "Advanced properties" button: smaller gap */
  #flexicontent #adminForm input[value="Advanced properties"] {
    margin-top: 16px !important;
  }
  #flexicontent #adminForm #file_replacement_box .alert {
    padding: 6px 12px;
  }
</style>
<script>
	document.addEventListener('DOMContentLoaded', function() {
		document.querySelectorAll('[data-fc-file-editor][hidden] .required, [data-fc-file-editor][hidden] [required]').forEach(function(control) { control.classList.remove('required'); control.removeAttribute('required'); });
		var input = document.getElementById('filename_original_name'), full = document.getElementById('filename_original'), suffix = document.getElementById('filename_original_extension');
		if (!input || !full || !suffix) return;
		var sync = function() { var name = input.value.trim(), ext = suffix.dataset.extension; if (ext && name.toLowerCase().endsWith('.' + ext)) name = name.slice(0, -ext.length - 1); full.value = name + (ext ? '.' + ext : ''); };
		input.addEventListener('input', sync);
		document.getElementById('adminForm').addEventListener('formdata', function(event) { sync(); event.formData.set('filename_original', full.value); });
	});
</script>
<?php if ($fc_in_modal) : ?>
<script>
	function fcFileModalSaveAndClose(button)
	{
		var form = document.getElementById('adminForm');
		if (form.fc_ajax_saving || (document.formvalidator && !document.formvalidator.isValid(form))) return false;
		var buttons = document.querySelectorAll('.fc-file-modal-bar button');
		var payload = new FormData(form);
		payload.set('task', 'filemanager.apply_ajax');
		payload.set('fc_doajax_submit', '1');
		form.fc_ajax_saving = true;
		form.setAttribute('aria-busy', 'true');
		buttons.forEach(function(el) { el.disabled = true; });
		var controller = new AbortController();
		var timeout = setTimeout(function() { controller.abort(); }, 120000);
		var failure = <?php echo json_encode(Text::_('FLEXI_AJAX_SAVE_UNCONFIRMED')); ?>;
		fetch(form.action, {method: 'POST', body: payload, credentials: 'same-origin', signal: controller.signal})
			.then(async function(response) {
				var parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
				var messages = document.getElementById('system-message-container');
				var alerts = parsed.querySelectorAll('.alert, joomla-alert');
				var success = parsed.querySelector('.alert-success, .alert-message, joomla-alert[type="success"], joomla-alert[type="message"]');
				var error = parsed.querySelector('.alert-danger, .alert-error, joomla-alert[type="danger"], joomla-alert[type="error"]');
				if (messages && alerts.length) messages.replaceChildren.apply(messages, Array.from(alerts));
				if (!response.ok || !success || error) throw new Error(failure);
				var saved = parsed.getElementById('fc-file-saved-data');
				if (saved) {
					var file = JSON.parse(saved.textContent);
					if (Number(file.id) !== Number(form.querySelector('input[name="id"]').value)) throw new Error(failure);
					form.fc_saved_file = file;
				}
				form.dispatchEvent(new CustomEvent('flexicontent:save-confirmed'));
			})
			.catch(function() {
				var messages = document.getElementById('system-message-container');
				if (messages && !messages.querySelector('.alert-danger, .alert-error, joomla-alert[type="danger"], joomla-alert[type="error"]')) {
					var message = document.createElement('div');
					message.className = 'alert alert-danger';
					message.setAttribute('role', 'alert');
					message.textContent = failure;
					messages.replaceChildren(message);
				}
			})
			.finally(function() {
				clearTimeout(timeout);
				form.fc_ajax_saving = false;
				form.removeAttribute('aria-busy');
				buttons.forEach(function(el) { el.disabled = false; });
			});
		return false;
	}

	function fcFileModalClose()
	{
		try { window.parent.jQuery('#fc_modal_popup_container').dialog('close'); } catch (e) { window.close(); }
	}

	// After a confirmed (AJAX) save, show the new data in the values of the parent (item) form that use this file
	document.getElementById('adminForm').addEventListener('flexicontent:save-confirmed', function()
	{
		var form = this;
		var val = function(sel) { var el = form.querySelector(sel); return el ? el.value : ''; };

		var file = form.fc_saved_file || {
			id: val('input[name="id"]'),
			filename: val('input[name="filename"]'),
			filename_original: val('input[name="filename_original"]'),
			altname: val('input[name="altname"]'),
			description: val('textarea[name="description"]'),
			ext: val('#mime_ext') || val('input[name="ext"]')
		};

		try { window.parent.fcfield_file.refreshFileRows(file); } catch (e) {}
		fcFileModalClose();
	});
</script>
<?php endif; ?>
<script>
	function jInsertFieldValue(value, id) {
		var elem = jQuery('#'+id).get(0);

		elem.value = value;
		let preview_img = jQuery(elem).parent().parent().find('img.inline-preview-img');
		if (preview_img.length > 0) {
			let juri_root = preview_img.attr('data-juri-root');
			preview_img.attr('src', juri_root + '/' + value);
		}
		if (typeof SqueezeBox != 'undefined') SqueezeBox.close();
		if (typeof jQuery != 'undefined') jQuery('#fc_modal_popup_container').dialog('close');
	}
</script>
