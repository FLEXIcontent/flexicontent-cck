# Native workflow patch: code index

This is the cumulative file index for draft PR #1421 against upstream master `bda8b4c7172c6efc0274ee9f15107128201338be`, including the 20 November follow-up. It contains **45 authored runtime JavaScript files**, 3 changed vendor JavaScript files and **11 browser test files**. Existing native files may have targeted fixes rather than a complete rewrite.

The PHP templates also generate JavaScript; the relevant integrations are linked below. The complete binary Git patch includes all PHP, XML, language, CSS/LESS, tests and screenshots as well.

Start with [form-utils](../site/assets/js/form-utils.js), [shared helpers](../site/assets/js/flexi-lib.js), [field repeaters](../site/assets/js/field-repeater.js), [native controls](../site/assets/js/native-field-controls.js), [input masks](../site/assets/js/input-masks.js), [editor validation](../site/assets/js/validate.js), [administrator actions](../site/assets/js/admin-actions.js), [history](../site/assets/js/item-history.js) and [dynamic layout settings](../site/assets/js/layout-params.js).

Choices remains the select enhancer. It is independent of optional gallery selection: FancyBox, elastislide, Galleriffic, multibox and fcxSlide may load jQuery on pages that use them. See the [audit report](JOOMLA6-NATIVE-AUDIT.md) for measured coverage and limitations.

## Authored runtime JavaScript

- [admin/assets/js/batchprocess.js](../admin/assets/js/batchprocess.js)
- [admin/assets/js/filter_presets.js](../admin/assets/js/filter_presets.js)
- [admin/assets/js/flexi-lib.js](../admin/assets/js/flexi-lib.js)
- [admin/assets/js/flexi_versions.js](../admin/assets/js/flexi_versions.js)
- [admin/assets/js/layout_editor.js](../admin/assets/js/layout_editor.js)
- [admin/assets/js/native-admin-layout.js](../admin/assets/js/native-admin-layout.js)
- [modules/mod_flexicontent/tmpl_common/js/favlist.js](../modules/mod_flexicontent/tmpl_common/js/favlist.js)
- [modules/mod_flexicontent/tmpl_common/js/tabs.js](../modules/mod_flexicontent/tmpl_common/js/tabs.js)
- [plugins/content/flexibreak/flexibreak/flexibreak.js](../plugins/content/flexibreak/flexibreak/flexibreak.js)
- [plugins/flexicontent_fields/addressint/js/form.js](../plugins/flexicontent_fields/addressint/js/form.js)
- [plugins/flexicontent_fields/addressint/js/form_osm.js](../plugins/flexicontent_fields/addressint/js/form_osm.js)
- [plugins/flexicontent_fields/addressint/js/repeater.js](../plugins/flexicontent_fields/addressint/js/repeater.js)
- [plugins/flexicontent_fields/date/js/form.js](../plugins/flexicontent_fields/date/js/form.js)
- [plugins/flexicontent_fields/fieldgroup/js/form.js](../plugins/flexicontent_fields/fieldgroup/js/form.js)
- [plugins/flexicontent_fields/file/js/form.js](../plugins/flexicontent_fields/file/js/form.js)
- [plugins/flexicontent_fields/image/js/form.js](../plugins/flexicontent_fields/image/js/form.js)
- [plugins/flexicontent_fields/mediafile/js/form.js](../plugins/flexicontent_fields/mediafile/js/form.js)
- [plugins/flexicontent_fields/mediafile/js/view.js](../plugins/flexicontent_fields/mediafile/js/view.js)
- [plugins/flexicontent_fields/relation/js/form.js](../plugins/flexicontent_fields/relation/js/form.js)
- [plugins/flexicontent_fields/sharedmedia/js/form.js](../plugins/flexicontent_fields/sharedmedia/js/form.js)
- [plugins/flexicontent_fields/text/js/form.js](../plugins/flexicontent_fields/text/js/form.js)
- [plugins/flexicontent_fields/weblink/js/form.js](../plugins/flexicontent_fields/weblink/js/form.js)
- [site/assets/js/admin-actions.js](../site/assets/js/admin-actions.js)
- [site/assets/js/admin.js](../site/assets/js/admin.js)
- [site/assets/js/color-input.js](../site/assets/js/color-input.js)
- [site/assets/js/fc-upload-transport.js](../site/assets/js/fc-upload-transport.js)
- [site/assets/js/fc-uploader.js](../site/assets/js/fc-uploader.js)
- [site/assets/js/fcfav.js](../site/assets/js/fcfav.js)
- [site/assets/js/field-repeater.js](../site/assets/js/field-repeater.js)
- [site/assets/js/flexi-lib-form.js](../site/assets/js/flexi-lib-form.js)
- [site/assets/js/flexi-lib.js](../site/assets/js/flexi-lib.js)
- [site/assets/js/form-utils.js](../site/assets/js/form-utils.js)
- [site/assets/js/indexed-field.js](../site/assets/js/indexed-field.js)
- [site/assets/js/input-masks.js](../site/assets/js/input-masks.js)
- [site/assets/js/item-history.js](../site/assets/js/item-history.js)
- [site/assets/js/itemscreen.js](../site/assets/js/itemscreen.js)
- [site/assets/js/juxtapose.js](../site/assets/js/juxtapose.js)
- [site/assets/js/layout-params.js](../site/assets/js/layout-params.js)
- [site/assets/js/media-field-controls.js](../site/assets/js/media-field-controls.js)
- [site/assets/js/media-metadata.js](../site/assets/js/media-metadata.js)
- [site/assets/js/media-repeater.js](../site/assets/js/media-repeater.js)
- [site/assets/js/native-field-controls.js](../site/assets/js/native-field-controls.js)
- [site/assets/js/tabber-minimized.js](../site/assets/js/tabber-minimized.js)
- [site/assets/js/tag-picker.js](../site/assets/js/tag-picker.js)
- [site/assets/js/validate.js](../site/assets/js/validate.js)

## Changed vendor JavaScript

- [site/librairies/galleriffic/js/jquery.galleriffic.js](../site/librairies/galleriffic/js/jquery.galleriffic.js)
- [site/librairies/inputmask/inputmask.min.js](../site/librairies/inputmask/inputmask.min.js)
- [site/librairies/wavesurfer/wavesurfer.min.js](../site/librairies/wavesurfer/wavesurfer.min.js)

## Browser regression suites

- [tests/image-media-clone.cjs](../tests/image-media-clone.cjs)
- [tests/native-admin-workflows.cjs](../tests/native-admin-workflows.cjs)
- [tests/native-color-input.cjs](../tests/native-color-input.cjs)
- [tests/native-editor-workflows.cjs](../tests/native-editor-workflows.cjs)
- [tests/native-input-masks.cjs](../tests/native-input-masks.cjs)
- [tests/native-joomla-dialogs.cjs](../tests/native-joomla-dialogs.cjs)
- [tests/native-legacy-helpers.cjs](../tests/native-legacy-helpers.cjs)
- [tests/native-maintenance.cjs](../tests/native-maintenance.cjs)
- [tests/native-media-uploader.cjs](../tests/native-media-uploader.cjs)
- [tests/native-public-waveform.cjs](../tests/native-public-waveform.cjs)
- [tests/native-public-widgets.cjs](../tests/native-public-widgets.cjs)

## CSS and LESS

- [admin/assets/css/native-admin.css](../admin/assets/css/native-admin.css)
- [plugins/flexicontent_fields/fieldgroup/css/form.css](../plugins/flexicontent_fields/fieldgroup/css/form.css)
- [plugins/flexicontent_fields/subform/css/form.css](../plugins/flexicontent_fields/subform/css/form.css)
- [site/assets/css/fc-uploader.css](../site/assets/css/fc-uploader.css)
- [site/assets/css/flexi_form_fields.css](../site/assets/css/flexi_form_fields.css)
- [site/assets/css/flexicontent.css](../site/assets/css/flexicontent.css)
- [site/assets/css/flexicontent_rtl.css](../site/assets/css/flexicontent_rtl.css)
- [site/assets/css/tag-picker.css](../site/assets/css/tag-picker.css)
- [site/assets/less/flexi_form_fields.less](../site/assets/less/flexi_form_fields.less)
- [site/assets/less/flexi_frontend.less](../site/assets/less/flexi_frontend.less)
- [site/assets/less/flexi_frontend_rtl.less](../site/assets/less/flexi_frontend_rtl.less)
- [site/librairies/galleriffic/css/basic.css](../site/librairies/galleriffic/css/basic.css)

## Follow-up PHP integrations

- [site/classes/helpers/html.php](../site/classes/helpers/html.php)
- [site/controller.php](../site/controller.php)
- [modules/mod_flexicontent/tmpl/news.php](../modules/mod_flexicontent/tmpl/news.php)
- [modules/mod_flexicontent/tmpl/swiper.php](../modules/mod_flexicontent/tmpl/swiper.php)
- [modules/mod_flexicontent/tmpl/carousel.php](../modules/mod_flexicontent/tmpl/carousel.php)
- [modules/mod_flexicontent/tmpl_common/favlist.php](../modules/mod_flexicontent/tmpl_common/favlist.php)
- [plugins/content/flexibreak/flexibreak.php](../plugins/content/flexibreak/flexibreak.php)
- [plugins/content/flexibreak/flexibreak/tmpl/default_tabs_sliders.php](../plugins/content/flexibreak/flexibreak/tmpl/default_tabs_sliders.php)
- [plugins/flexicontent_fields/image/tmpl/value_gallery_fancybox.php](../plugins/flexicontent_fields/image/tmpl/value_gallery_fancybox.php)
- [plugins/flexicontent_fields/image/tmpl/value_gallery_galleriffic.php](../plugins/flexicontent_fields/image/tmpl/value_gallery_galleriffic.php)
- [plugins/flexicontent_fields/image/tmpl/value_gallery_juxtapose.php](../plugins/flexicontent_fields/image/tmpl/value_gallery_juxtapose.php)
- [site/layouts/flexicontent_fields/favourites/favicon.php](../site/layouts/flexicontent_fields/favourites/favicon.php)
