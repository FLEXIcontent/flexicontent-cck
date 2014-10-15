<?php
/**
 * HTML5 Template
 * @version 1.5 stable $Id: category_alpha_html5.php 0001 2012-09-23 14:00:28Z Rehne $
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

function utf8ord($char) {
	$i = 0;
	$number = '';
	while (isset($char{$i})) {
		$number.= ord($char{$i});
		++$i;
	}
	return $number;
}

$app = JFactory::getApplication();
$caching = $app->getCfg('caching');

$show_alpha = $this->params->get('show_alpha',1);
if ($show_alpha == 1) {
	// Language Default
	$alphacharacters = JTEXT::_("FLEXI_ALPHA_INDEX_CHARACTERS");
	$groups = explode("!!", $alphacharacters);
	$groupcssclasses = explode("!!", JTEXT::_("FLEXI_ALPHA_INDEX_CSSCLASSES"));
	$alphaaliases = explode("!!", JTEXT::_("FLEXI_ALPHA_INDEX_ALIASES"));
} else {  // $show_alpha == 2
	// Custom setting
	$alphacharacters = $this->params->get('alphacharacters', "[default]=a,b,c,d,e,f,g,h,i,j,k,l,m,n,o,p,q,r,s,t,u,v,w,y,z!!0,1,2,3,4,5,6,7,8,9");
	
	// Get a 2 character language tag
	$lang = flexicontent_html::getUserCurrentLang();
	
	// a. Try to get for current language
	$result = preg_match("/(\[$lang\])=([^[]+)/i", $alphacharacters, $matches);
	if ($result) {
		$custom_lang_alpha_index = $matches[2];
	} else {
		// b. Try to get default for all languages
		$result = preg_match("/(\[default\])=([^[]+)/i", $alphacharacters, $matches);
		if ($result) {
			$custom_lang_alpha_index = $matches[2];
		} else {
			// c. Use default language string from language file
			$custom_lang_alpha_index = JTEXT::_("FLEXI_ALPHA_INDEX_CHARACTERS");
		}
	}
	
	$groups = explode("!!", $custom_lang_alpha_index);
	$groupcssclasses = explode("!!", $this->params->get('alphagrpcssclasses'));
	$alphaaliases = explode("!!", $this->params->get('alphaaliases'));
}
foreach ($alphaaliases as $alphaalias) {
	$alias_data = explode("~", $alphaalias);
	if (count($alias_data)!=2) continue;
	$alphaalias_arr[$alias_data[0]] = $alias_data[1];
}

$alphacharsep = $this->params->get('alphacharseparator',false);
$alphaskipempty = $this->params->get('alphaskipempty',0);

// a. Trim classes names
foreach ($groupcssclasses as $i => $grpcssclass) {
	$groupcssclasses[$i] = trim($grpcssclass);
}
// b. Check for empty first value, means initial string was empty ... and set empty array
if ($groupcssclasses[0]=='') $groupcssclasses = array();
// c. Set missing classes to class 'letters'
for($i=count($groupcssclasses); $i<count($groups); $i++) {
	$groupcssclasses[$i] = 'letters';
}

if ($caching) $selected_letter = JRequest::getVar('letter', '', 'get');
else $selected_letter = JRequest::getVar('letter', '', 'post');
?>

<div id="fc_alpha" class="control-group group" role="application">
	<?php
	$flag = true;
	$grp_no=-1;
	foreach($groups as $group) {
		$grp_no++;
		$group_start = true;
		$letters = explode(",", $group);
	?>
	<div class="aichargrp <?php echo $groupcssclasses[$grp_no]; ?>">
	<?php if($flag) {?>
	<a class="btn btn-medium fc_alpha_index" href="javascript:;" onclick="document.getElementById('alpha_index').value='';document.getElementById('adminForm').submit();"><?php echo JText::_('FLEXI_ALL'); ?></a>
	<?php $flag = false;}?>
	<?php
		foreach ($letters as $letter) :
			// a. Skip on empty $letter (2 commas ,,)
			$letter = trim($letter);
			if ($letter==='') continue;
			
			// b. Check for ALIASes
			$letter_label = $letter;
			if ($letter==='#' ) {
				$letter = "0-9";
			}
			if (isset($alphaalias_arr[$letter])) {
				$letter = $alphaalias_arr[$letter];
			}
			
			// c. Try to get range of characters
			$range = explode("-", $letter);
			
			// d. Check if character exists 
			$has_item = false;
			if(count($range)==1) {
				
				// Check if any character out of the all subgroup characters exists
				// Meaning (There is at least on item title starting with one of the group letters)
				$c = 0;
				while ($c < JString::strlen($letter)) {
					$uchar = JString::substr($letter,$c++,1);
					if (in_array($uchar, $this->alpha)) {
						$has_item = true;
						break;
					}
				}
			} else {
				// ERROR CHECK: Character range has only one minus(-)
				if (count($range) != 2) {
					echo "Error in Alpha Index<br>incorrect letter range: ".$letter."<br>";
					continue;
				}
				
				// Get range characters
				$startletter = $range[0];  $endletter = $range[1];
				
				// ERROR CHECK: Range START and END are single character strings
				if (JString::strlen($startletter) != 1 || JString::strlen($endletter) != 1) {
					echo "Error in Alpha Index<br>letter range: ".$letter." start and end must be one character<br>";
					continue;
				}
				
				// Get ord of characters and their rangle length
				$startord=FLEXIUtilities::uniord($startletter);
				$endord=FLEXIUtilities::uniord($endletter);
				$range_length = $endord - $startord;
				
				// ERROR CHECK: Character range has at least one character
				if ($range_length > 200 || $range_length < 1) {
					// A sanity check that the range is something logical and that 
					echo "Error in Alpha Index<br>letter range: ".$letter.", is incorrect or contains more that 200 characters<br>";
					continue;
				}
				
				// Check if any character out of the range characters exists
				// Meaning (There is at least on item title starting with one of the range characters)
				for($uord=$startord; $uord<=$endord; $uord++) :
					$uchar = FLEXIUtilities::unichr($uord);
					if (in_array($uchar, $this->alpha)) {
						$has_item = true;
						break;
					}
				endfor;
			}
			
			if ($alphacharsep) $aiclass = "btn btn-medium fc_alpha_index_sep";
			else $aiclass = "btn btn-medium fc_alpha_index";
			$currentclass = '';
			if($letter_label == $selected_letter){
				$currentclass = 'current active';
			}
			if ($has_item) :
				if ($alphacharsep && !$group_start) echo "<span class=\"fc_alpha_index_sep\">$alphacharsep</span>";
				echo "<a class=\"$aiclass $currentclass\" href=\"javascript:;\" onclick=\"document.getElementById('alpha_index').value='".$letter."'; ";
				echo " var form=document.getElementById('adminForm'); ";
				echo " adminFormPrepare(form); form.submit(); \">".mb_strtoupper($letter_label)."</a>";
			elseif (!$alphaskipempty) :
				if ($alphacharsep && !$group_start) echo "<span class=\"fc_alpha_index_sep\">$alphacharsep</span>";
				echo '<span class="'.$aiclass.' disabled">'.mb_strtoupper($letter_label).'</span>';
			endif;
			$group_start = false;
		endforeach;
	?>
	</div>
	
	<div class='fcclear'></div><?php /* needed by ie6-ie7 */ ?>
	
	<?php
	}?>
</div>