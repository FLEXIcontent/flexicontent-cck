<?php

	// Unserialize value's properties and check for empty original name property
	$array = $this->unserialize_array($value, $force_array=false, $force_value=false);
	$value = $array ?: array(
		'originalname' => $value
	);

	$image_subpath = $value['originalname'] = isset($value['originalname']) ? trim($value['originalname']) : '';

	// Skip empty value, adding an empty placeholder if field inside in field group
	if ( !strlen($image_subpath) )
	{
		if ( $is_ingroup )
		{
			$field->{$prop}[]	= '';
		}
		return _FC_CONTINUE_;
	}
	$i++;
	
	// Some types contain sub-path together with the image name (relative to joomla folder)
	if ( is_array($orig_urlpath) )
	{
		$orig_urlpath[$i] = str_replace('\\', '/', dirname($image_subpath));
	}
	
	// In other cases check for sub-path relative to the calculated 'original path'
	else
	{
		if ($dirname = dirname($image_subpath))
		{
			$orig_urlpath .=  '/'. str_replace('\\', '/', $dirname);
		}
	}

	$image_name = basename($image_subpath);


	// ***
	// Create thumbnails urls, note thumbnails have already been verified above
	// ***

	// Optional properties
	$title	= ($usetitle && isset($value['title'])) ? $value['title'] : '';
	$alt	= ($usealt && isset($value['alt'])) ? $value['alt'] : $alt_image_prefix . ($n + 1);
	$desc	= ($usedesc && isset($value['desc'])) ? $value['desc'] : '';
	
	// Optional custom properties
	$cust1	= ($usecust1 && isset($value['cust1'])) ? $value['cust1'] : '';
	$desc .= $cust1 ? $cust1_label.': '.$cust1 : '';  // ... Append custom properties to description
	$cust2	= ($usecust2 && isset($value['cust2'])) ? $value['cust2'] : '';
	$desc .= $cust2 ? $cust2_label.': '.$cust2 : '';  // ... Append custom properties to description
	
	// HTML encode output
	$title= htmlspecialchars($title, ENT_COMPAT, 'UTF-8');
	$alt	= htmlspecialchars($alt, ENT_COMPAT, 'UTF-8');
	$desc	= htmlspecialchars($desc, ENT_COMPAT, 'UTF-8');
	
	$srcb = $thumb_urlpath . '/b_' .$extra_prefix. $image_name;  // backend
	$srcs = $thumb_urlpath . '/s_' .$extra_prefix. $image_name;  // small
	$srcm = $thumb_urlpath . '/m_' .$extra_prefix. $image_name;  // medium
	$srcl = $thumb_urlpath . '/l_' .$extra_prefix. $image_name;  // large
	$srco = (is_array($orig_urlpath) ? $orig_urlpath[$i] : $orig_urlpath)  . '/'   .$image_name;  // original image
	
	// Create a popup url link
	$urllink = isset($value['urllink']) ? $value['urllink'] : '';
	//if ($urllink && false === strpos($urllink, '://')) $urllink = 'http://' . $urllink;
	
	// Create a popup tooltip (legend)
	$class = 'fc_field_image';
	if ($uselegend && (!empty($title) || !empty($desc)))
	{
		$class .= ' '.$tooltip_class;
		$legend = ' title="'.flexicontent_html::getToolTip($title, $desc, 0, 1).'"';
	}
	else
	{
		$legend = '';
	}
	
	// Handle single image display, with/without total, TODO: verify all JS handle & ignore display none on the img TAG
	$style = ($i!=0 && $isSingle) ? 'display:none;' : '';
	
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
	
	
	// ADD some extra (display) properties that point to all sizes, currently SINGLE IMAGE only (for consistency use 'use_ingroup' of 'ingroup')
	if ($use_ingroup)
	{
		// In case of field displayed via in fieldgroup, this is an array
		$field->{"display_backend_src"}[$n] = JUri::root(true).'/'.$srcb;
		$field->{"display_small_src"}[$n] = JUri::root(true).'/'.$srcs;
		$field->{"display_medium_src"}[$n] = JUri::root(true).'/'.$srcm;
		$field->{"display_large_src"}[$n] = JUri::root(true).'/'.$srcl;
		$field->{"display_original_src"}[$n] = JUri::root(true).'/'.$srco;
	}

	// Field displayed not via fieldgroup return only the 1st value
	else if ($i==0)
	{
		$field->{"display_backend_src"} = JUri::root(true).'/'.$srcb;
		$field->{"display_small_src"} = JUri::root(true).'/'.$srcs;
		$field->{"display_medium_src"} = JUri::root(true).'/'.$srcm;
		$field->{"display_large_src"} = JUri::root(true).'/'.$srcl;
		$field->{"display_original_src"} = JUri::root(true).'/'.$srco;
	}

	$field->thumbs_src['backend'][$use_ingroup ? $n : $i] = JUri::root(true).'/'.$srcb;
	$field->thumbs_src['small'][$use_ingroup ? $n : $i] = JUri::root(true).'/'.$srcs;
	$field->thumbs_src['medium'][$use_ingroup ? $n : $i] = JUri::root(true).'/'.$srcm;
	$field->thumbs_src['large'][$use_ingroup ? $n : $i] = JUri::root(true).'/'.$srcl;
	$field->thumbs_src['original'][$use_ingroup ? $n : $i] = JUri::root(true).'/'.$srco;
	
	$field->thumbs_path['backend'][$use_ingroup ? $n : $i] = JPATH_SITE.DS.$srcb;
	$field->thumbs_path['small'][$use_ingroup ? $n : $i] = JPATH_SITE.DS.$srcs;
	$field->thumbs_path['medium'][$use_ingroup ? $n : $i] = JPATH_SITE.DS.$srcm;
	$field->thumbs_path['large'][$use_ingroup ? $n : $i] = JPATH_SITE.DS.$srcl;
	$field->thumbs_path['original'][$use_ingroup ? $n : $i] = JPATH_SITE.DS.$srco;
	
	// Suggest image for external use, e.g. for Facebook etc, (making sure that URL is ABSOLUTE URL)
	if ( $isHtmlViewFE && $useogp )
	{
		if ( in_array($view, $ogpinview) ) {
			switch ($ogpthumbsize)
			{
				case 1: $ogp_src = JUri::root().$srcs; break;   // this maybe problematic, since it maybe too small or not accepted by social website
				case 2: $ogp_src = JUri::root().$srcm; break;
				case 3: $ogp_src = JUri::root().$srcl; break;
				case 4: $ogp_src =  JUri::root().$srco; break;
				default: $ogp_src = JUri::root().$srcm; break;
			}
			$document->addCustomTag('<link rel="image_src" href="'.$ogp_src.'" />');
			$document->addCustomTag('<meta property="og:image" content="'.$ogp_src.'" />');
		}
	}


	// ***
	// *** CHECK if we were asked for value only display (e.g. image source)
	// *** if so we will not be creating the HTML code for Image / Gallery 
	// ***

	if ( isset(self::$value_only_displays[$prop]) )
	{
		return _FC_CONTINUE_;
	}


	// ***
	// *** Create image tags (according to configuration parameters)
	// *** that will be used for the requested 'display' variable
	// ***

	switch ($prop)
	{
		case 'display_backend':
			$img_legend   = '<img src="'.JUri::root(true).'/'.$srcb.'" alt="'.$alt.'"'.$legend.' class="'.$class.'" itemprop="image"/>';
			$img_nolegend = '<img src="'.JUri::root(true).'/'.$srcb.'" alt="'.$alt.'" class="'.$class.'" itemprop="image"/>';
			break;

		case 'display_small':
			$img_legend   = '<img src="'.JUri::root(true).'/'.$srcs.'" alt="'.$alt.'"'.$legend.' class="'.$class.'" itemprop="image"/>';
			$img_nolegend = '<img src="'.JUri::root(true).'/'.$srcs.'" alt="'.$alt.'" class="'.$class.'" itemprop="image"/>';
			break;

		case 'display_medium':
			$img_legend   = '<img src="'.JUri::root(true).'/'.$srcm.'" alt="'.$alt.'"'.$legend.' class="'.$class.'" itemprop="image"/>';
			$img_nolegend = '<img src="'.JUri::root(true).'/'.$srcm.'" alt="'.$alt.'" class="'.$class.'" itemprop="image"/>';
			break;

		case 'display_large':
			$img_legend   = '<img src="'.JUri::root(true).'/'.$srcl.'" alt="'.$alt.'"'.$legend.' class="'.$class.'" itemprop="image"/>';
			$img_nolegend = '<img src="'.JUri::root(true).'/'.$srcl.'" alt="'.$alt.'" class="'.$class.'" itemprop="image"/>';
			break;

		case 'display_original':
			$img_legend   = '<img src="'.JUri::root(true).'/'.$srco.'" alt="'.$alt.'"'.$legend.' class="'.$class.'" itemprop="image"/>';
			$img_nolegend = '<img src="'.JUri::root(true).'/'.$srco.'" alt="'.$alt.'" class="'.$class.'" itemprop="image"/>';
			break;

		case 'display': default:
			$img_legend   = '<img src="'.JUri::root(true).'/'.$src.'" alt="'.$alt.'"'.$legend.' class="'.$class.'" itemprop="image"/>';
			$img_nolegend = '<img src="'.JUri::root(true).'/'.$src.'" alt="'.$alt.'" class="'.$class.'" itemprop="image"/>';
			break;
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
		// Add inline display of title/desc
		if ( ($showtitle && $title ) || ($showdesc && $desc) )
		{
			$inline_info = '
			<div class="fc_img_tooltip_data alert alert-info" style="'.$style.'" >

			' . ( $showtitle && $title ? '<div class="fc_img_tooltip_title" style="line-height:1em; font-weight:bold;">'.$title.'</div>' : '') . '
			' . ( $showdesc && $desc ? '<div class="fc_img_tooltip_desc" style="line-height:1em;">'.$desc.'</div>' : '') . '

			</div>';
		}
	}
	
	return 0;