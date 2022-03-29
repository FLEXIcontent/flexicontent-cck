<?php

	// Unserialize value's properties and check for empty original name property
	$array = $this->unserialize_array($value, $force_array=false, $force_value=false);
	$value = $array ?: array(
		'originalname' => $value
	);

	$image_subpath = $value['originalname'] = isset($value['originalname']) ? trim($value['originalname']) : '';

	// Check if image file is a URL (e.g. we are using Media URLs to videos)
	$isURL = preg_match("#^(?:[a-z]+:)?//#", $image_subpath);

	// Skip empty value, adding an empty placeholder if field inside in field group
	if (!strlen($image_subpath))
	{
		if ($is_ingroup)
		{
			$field->{$prop}[]	= '';
		}

		return _FC_CONTINUE_;
	}

	$i++;

	// Some types contain sub-path together with the image name (relative to joomla folder)
	if (is_array($orig_urlpath))
	{
		$orig_urlpath[$i] = str_replace('\\', '/', dirname($image_subpath));
	}

	// In other cases check for sub-path relative to the calculated 'original path'
	else
	{
		if ($dirname = dirname($image_subpath))
		{
			if ($dirname !== '.')
			{
				$orig_urlpath .=  '/' . str_replace('\\', '/', $dirname);
			}
		}
	}

	// Get image's filename out of image path
	$image_name = basename($image_subpath);


	/**
	 * Create thumbnails urls, note thumbnails have already been verified above
	 */

	// Optional properties
	$title = $usetitle && isset($value['title']) ? $value['title'] : '';
	$alt   = $usealt && isset($value['alt']) ? $value['alt'] : $alt_image_prefix . ($n + 1);
	$desc  = $usedesc && isset($value['desc']) ? $value['desc'] : '';

	// Optional custom properties
	$cust1  = $usecust1 && isset($value['cust1']) ? $value['cust1'] : '';
	$desc  .= $cust1 ? $cust1_label.': '.$cust1."\n" : '';  // ... Append custom properties to description
	$cust2  = $usecust2 && isset($value['cust2']) ? $value['cust2'] : '';
	$desc  .= $cust2 ? $cust2_label.': '.$cust2."\n" : '';  // ... Append custom properties to description
	
	// HTML encode output
	$title_encoded = htmlspecialchars($title, ENT_COMPAT, 'UTF-8');
	$alt_encoded   = htmlspecialchars($alt, ENT_COMPAT, 'UTF-8');
	$desc_encoded  = htmlspecialchars($desc, ENT_COMPAT, 'UTF-8');
	$desc_encoded  = nl2br(preg_replace("/(\r\n|\r|\n){3,}/", "\n\n", $desc_encoded));

	if (!$isURL)
	{
		$srcb = $thumb_urlpath . '/b_' .$extra_prefix. $image_name;  // backend
		$srcs = $thumb_urlpath . '/s_' .$extra_prefix. $image_name;  // small
		$srcm = $thumb_urlpath . '/m_' .$extra_prefix. $image_name;  // medium
		$srcl = $thumb_urlpath . '/l_' .$extra_prefix. $image_name;  // large
		$srco = (is_array($orig_urlpath) ? $orig_urlpath[$i] : $orig_urlpath)  . '/'   .$image_name;  // original image
	}
	else
	{
		$srcb = $image_subpath;
		$srcs = $image_subpath;
		$srcm = $image_subpath;
		$srcl = $image_subpath;
		$srco = $image_subpath;
	}

	// Create a popup url link
	$urllink = isset($value['urllink']) ? $value['urllink'] : '';
	//if ($urllink && false === strpos($urllink, '://')) $urllink = 'http://' . $urllink;

	$class = 'fc_field_image';

	// Create a popup tooltip (legend)
	if ($uselegend && (!empty($title_encoded) || !empty($desc_encoded)))
	{
		$class .= ' ' . $tooltip_class;
		$legend = ' title="'.flexicontent_html::getToolTip($title_encoded, $desc_encoded, 0, 0).'"';
	}
	else
	{
		$legend = '';
	}

	// Handle single image display, with/without total, TODO: verify all JS handle & ignore display none on the img TAG container
	$style = $i > 0 && $isSingle
		? 'display:none;'
		: '';

	// Create a unique id for the link tags, and a class name for image tags
	$uniqueid = $item->id . '_' . $field->id . '_' . $i;

	switch ($thumb_size)
	{
		case -1: $src = $srcb; break;
		case 1: $src = $srcs; break;
		case 2: $src = $srcm; break;
		case 3: $src = $srcl; break;   // this makes little sense, since both thumbnail and popup image are size 'large'
		case 4: $src = $srco; break;
		default: $src = $srcs; break;
	}


	// Create a grouping name
	switch ($grouptype)
	{
		// This field only
		case 0: $group_name = 'fcview_'.$view.'_fcitem_'.$item->id.'_fcfield_'.$field->id; break;

		// All fields of the item
		case 1: $group_name = 'fcview_'.$view.'_fcitem_'.$item->id; break;

		// Per view:  all items of category page, or search page
		case 2: $group_name = 'fcview_'.$view; break;

		// No group
		default: $group_name = ''; break;
	}


	$abs_srcb = $isURL ? $srcb : JUri::root(true).'/'.$srcb;
	$abs_srcs = $isURL ? $srcs : JUri::root(true).'/'.$srcs;
	$abs_srcm = $isURL ? $srcm : JUri::root(true).'/'.$srcm;
	$abs_srcl = $isURL ? $srcl : JUri::root(true).'/'.$srcl;
	$abs_srco = $isURL ? $srco : JUri::root(true).'/'.$srco;
	$abs_src  = $isURL ? $src  : JUri::root(true).'/'.$src;


	// ADD some extra (display) properties that point to all sizes, currently SINGLE IMAGE only (for consistency use 'use_ingroup' of 'ingroup')
	if ($use_ingroup)
	{
		// In case of field displayed via in fieldgroup, this is an array
		$field->{"display_backend_src"}[$n] = $abs_srcb;
		$field->{"display_small_src"}[$n] = $abs_srcs;
		$field->{"display_medium_src"}[$n] = $abs_srcm;
		$field->{"display_large_src"}[$n] = $abs_srcl;
		$field->{"display_original_src"}[$n] = $abs_srco;
	}

	// Field displayed not via fieldgroup return only the 1st value
	elseif ($i === 0)
	{
		$field->{"display_backend_src"} = $abs_srcb;
		$field->{"display_small_src"} = $abs_srcs;
		$field->{"display_medium_src"} = $abs_srcm;
		$field->{"display_large_src"} = $abs_srcl;
		$field->{"display_original_src"} = $abs_srco;
	}

	$field->thumbs_src['backend'][$use_ingroup ? $n : $i] = $abs_srcb;
	$field->thumbs_src['small'][$use_ingroup ? $n : $i] = $abs_srcs;
	$field->thumbs_src['medium'][$use_ingroup ? $n : $i] = $abs_srcm;
	$field->thumbs_src['large'][$use_ingroup ? $n : $i] = $abs_srcl;
	$field->thumbs_src['original'][$use_ingroup ? $n : $i] = $abs_srco;

	$field->thumbs_path['backend'][$use_ingroup ? $n : $i] = $isURL ? $srcb : JPATH_SITE.DS.$srcb;
	$field->thumbs_path['small'][$use_ingroup ? $n : $i] = $isURL ? $srcs : JPATH_SITE.DS.$srcs;
	$field->thumbs_path['medium'][$use_ingroup ? $n : $i] = $isURL ? $srcm : JPATH_SITE.DS.$srcm;
	$field->thumbs_path['large'][$use_ingroup ? $n : $i] = $isURL ? $srcl : JPATH_SITE.DS.$srcl;
	$field->thumbs_path['original'][$use_ingroup ? $n : $i] = $isURL ? $srco : JPATH_SITE.DS.$srco;

	/*
	 * Suggest 1 or more (all?) images to social website listing, e.g. Facebook, twitter etc, (making sure that URL is ABSOLUTE URL)
	 * Also check that 
	 * - we are in HTML format at Frontend
	 * - we are viewing the item in full item view 
	 */
	if ($useogp && ($ogplimit === 0 || $i < $ogplimit))
	{
		if (static::$isHtmlViewFE && $isMatchedItemView)
		{
			switch ($ogpthumbsize)
			{
				case 1: $ogp_src = $isURL ? $srcs : JUri::root().$srcs; break;   // this maybe problematic, since it maybe too small or not accepted by social website
				case 2: $ogp_src = $isURL ? $srcm : JUri::root().$srcm; break;
				case 3: $ogp_src = $isURL ? $srcl : JUri::root().$srcl; break;
				case 4: $ogp_src =  $isURL ? $srco : JUri::root().$srco; break;
				default: $ogp_src = $isURL ? $srcm : JUri::root().$srcm; break;
			}
			$document->addCustomTag('<link rel="image_src" href="'.$ogp_src.'" />');
			$document->addCustomTag('<meta property="og:image" content="'.$ogp_src.'" />');
		}
	}


	/**
	 * CHECK if we were asked for value only display (e.g. image source)
	 * if so we will not be creating the HTML code for Image / Gallery
	 */

	if (isset(self::$value_only_displays[$prop]))
	{
		return _FC_CONTINUE_;
	}


	/**
	 * Create image tags (according to configuration parameters)
	 * that will be used for the requested 'display' variable
	 */

	$size = isset(self::$display_to_thumb_size[$prop])
		? self::$display_to_thumb_size[$prop]
		: (isset(self::$index_to_thumb_size[$thumb_size]) ? self::$index_to_thumb_size[$thumb_size] : 's');

	$crop = $field->parameters->get('method_'.$size);
	$img_size_attrs = '';

	if ($size !== 'o')
	{
		$w = isset($value['size_w_' . $size]) ? $value['size_w_' . $size] : $field->parameters->get('w_' . $size, self::$default_widths[$size]);
		$h = isset($value['size_h_' . $size]) ? $value['size_h_' . $size] : $field->parameters->get('h_' . $size, self::$default_heights[$size]);

		$size_w_s = isset($value['size_w_s']) ? $value['size_w_s'] : 0;
		$size_h_s = isset($value['size_h_s']) ? $value['size_h_s'] : 0;
		$size_w_m = isset($value['size_w_m']) ? $value['size_w_m'] : 0;
		$size_h_m = isset($value['size_h_m']) ? $value['size_h_m'] : 0;
		$size_w_l = isset($value['size_w_l']) ? $value['size_w_l'] : 0;
		$size_h_l = isset($value['size_h_l']) ? $value['size_h_l'] : 0;

		// Inform about smaller image sizes than the current selected
		$srcset = array();
		$_sizes = array();

		if ($size === 'l')
		{
			if ($srcl)
			{
				$minmax_prefix  = ! (int) $field->parameters->get( 'l_thumb_width_as_min_or_max', 0) ? 'min' : 'max';
				$w_l = $size_w_l ?: $field->parameters->get('w_l', self::$default_widths['l']);
				$srcset[] = (!$isURL ? JUri::root() : '') . $srcl . ' ' . $w_l . 'w';
				$_sizes[] = '(' . $minmax_prefix . '-width: ' . $w_l . 'px) ' . $w_l . 'px';
			}
		}

		if ($size === 'l' || $size === 'm')
		{
			if ($srcm)
			{
				$minmax_prefix  = ! (int) $field->parameters->get( 'm_thumb_width_as_min_or_max', 0) ? 'min' : 'max';
				$w_m = $size_w_m ?: $field->parameters->get('w_m', self::$default_widths['m']);
				$srcset[] = (!$isURL ? JUri::root() : '') . $srcm . ' ' . $w_m . 'w';
				$_sizes[] = '(' . $minmax_prefix . '-width: ' . $w_m . 'px) ' . $w_m . 'px';
			}
			if ($srcs)
			{
				$minmax_prefix  = ! (int) $field->parameters->get( 's_thumb_width_as_min_or_max', 0) ? 'min' : 'max';
				$w_s = $size_w_s ?: $field->parameters->get('w_s', self::$default_widths['s']);
				$srcset[] = (!$isURL ? JUri::root() : '') . $srcs . ' ' . $w_s . 'w';
				$_sizes[] = '(' . $minmax_prefix . '-width: ' . $w_s . 'px) ' . $w_s . 'px';
			}
		}

		if (count($srcset))
		{
			$img_size_attrs .= ' srcset="' . implode(', ', $srcset) . '"';
			$img_size_attrs .= ' sizes="' . implode(', ', $_sizes) . '"';
		}

		// Inform browser of real images sizes and of desired image size
		$img_size_attrs .= ' width="' . $w . '" height="' . $h . '" style="height: auto; max-width: 100%;" ';
		// This following does not combine well with SRCSET / SIZES ...
		/*$img_size_attrs .= $crop ? ' style="width: ' . $w . 'px; height: ' . $h . 'px;' : ' style="max-width: ' . $w . 'px; max-height: ' . $h . 'px;" ';*/
	}

	$use_lazy_loading = (int) $field->parameters->get('use_lazy_loading', 1);
	$lazy_loading = $use_lazy_loading ? ' loading="lazy" decoding="async" ' : '';

	switch ($prop)
	{
		case 'display_backend':
		case 'display_backend_thumb':
			$img_legend   = '<img src="'.$abs_srcb.'" alt="'.$alt_encoded.'"'.$legend.' class="'.$class.'" itemprop="image" ' . $img_size_attrs . $lazy_loading . '/>';
			$img_nolegend = '<img src="'.$abs_srcb.'" alt="'.$alt_encoded.'" class="'.$class.'" itemprop="image" ' . $img_size_attrs . $lazy_loading . '/>';
			break;

		case 'display_small':
		case 'display_small_thumb':
			$img_legend   = '<img src="'.$abs_srcs.'" alt="'.$alt_encoded.'"'.$legend.' class="'.$class.'" itemprop="image" ' . $img_size_attrs . $lazy_loading . '/>';
			$img_nolegend = '<img src="'.$abs_srcs.'" alt="'.$alt_encoded.'" class="'.$class.'" itemprop="image" ' . $img_size_attrs . $lazy_loading . '/>';
			break;

		case 'display_medium':
		case 'display_medium_thumb':
			$img_legend   = '<img src="'.$abs_srcm.'" alt="'.$alt_encoded.'"'.$legend.' class="'.$class.'" itemprop="image" ' . $img_size_attrs . $lazy_loading . '/>';
			$img_nolegend = '<img src="'.$abs_srcm.'" alt="'.$alt_encoded.'" class="'.$class.'" itemprop="image" ' . $img_size_attrs . $lazy_loading . '/>';
			break;

		case 'display_large':
		case 'display_large_thumb':
			$img_legend   = '<img src="'.$abs_srcl.'" alt="'.$alt_encoded.'"'.$legend.' class="'.$class.'" itemprop="image" ' . $img_size_attrs . $lazy_loading . '/>';
			$img_nolegend = '<img src="'.$abs_srcl.'" alt="'.$alt_encoded.'" class="'.$class.'" itemprop="image" ' . $img_size_attrs . $lazy_loading . '/>';
			break;

		case 'display_original':
		case 'display_original_thumb':
			$img_legend   = '<img src="'.$abs_srco.'" alt="'.$alt_encoded.'"'.$legend.' class="'.$class.'" itemprop="image" ' . $img_size_attrs . $lazy_loading . '/>';
			$img_nolegend = '<img src="'.$abs_srco.'" alt="'.$alt_encoded.'" class="'.$class.'" itemprop="image" ' . $img_size_attrs . $lazy_loading . '/>';
			break;

		case 'display':
		default:
			$img_legend   = '<img src="'.$abs_src.'" alt="'.$alt_encoded.'"'.$legend.' class="'.$class.'" itemprop="image" ' . $img_size_attrs . $lazy_loading . '/>';
			$img_nolegend = '<img src="'.$abs_src.'" alt="'.$alt_encoded.'" class="'.$class.'" itemprop="image" ' . $img_size_attrs . $lazy_loading . '/>';
			break;
	}


	/**
	 * CHECK if we were asked for thumbnail only display
	 * if so we will not be creating the HTML code for Image / Gallery
	 */

	if (isset(self::$thumb_only_displays[$prop]))
	{
		if ($use_ingroup)
		{
			// In case of field displayed via in fieldgroup, this is an array
			$field->{$prop}[$n] = $img_legend;
		}

		// Field displayed not via fieldgroup return only the 1st value
		elseif ($i === 0)
		{
			$field->{$prop} = $img_legend;
		}

		return _FC_CONTINUE_;
	}


	// ***
	// *** Create thumbnail appending text (not linked text)
	// ***

	$inline_info = '';

	// Link to item is typically for category view, not appropriate for adding inline info, pretext, posttext
	if ($linkto_item)
	{
	}

	// Note this is ignore by some galleries that have special containment that does not allow inline info, pretext, posttext
	else
	{
		// Add inline display of title/desc, note that if we are hide non-first images (display_single_*), then we will hide this information box too
		if (($showtitle && $title) || ($showdesc && $desc))
		{
			$inline_info = '
			<div class="fc_img_tooltip_data alert alert-info" style="' . $style . '">

			' . ($showtitle && $title ? '<div class="fc_img_tooltip_title" style="line-height:1em; font-weight:bold;">'.$title.'</div>' : '') . '
			' . ($showdesc && $desc ? '<div class="fc_img_tooltip_desc" style="line-height:1em;">'.$desc.'</div>' : '') . '

			</div>';
		}
	}

	return 0;