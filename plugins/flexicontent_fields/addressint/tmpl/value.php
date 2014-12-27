<?php
//No direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

// get parameters
$show_map 		= $this->getParam('show_map','none');
$map_width 		= $this->getParam('map_width',200);
$map_height 	= $this->getParam('map_height',150);
$map_type 		= $this->getParam('map_type','roadmap');
$map_zoom 		= $this->getParam('map_zoom',16);
$link_map 		= $this->getParam('link_map',1);
$map_position 	= $this->getParam('map_position',0);
$marker_color 	= $this->getParam('marker_color','red');
$marker_size 	= $this->getParam('marker_size','mide');
$field_prefix 	= $this->getParam('field_prefix','');
$field_suffix 	= $this->getParam('field_suffix','');
// get view
$view = JRequest::getVar('view');
// get value
$values = $values[0] ;
// generate map
$map = '';
if(($view=='category' && ($show_map=='category' || $show_map=='both')) || ($view!='category' && ($show_map=='item' || $show_map=='both'))) {
	$map_link = "http://maps.google.com/maps?q=".$address['lat'].",".$address['lon'];
	$map_url = "http://maps.google.com/maps/api/staticmap?center=".$address['lat'].",".$address['lon']."&zoom=".$map_zoom."&size=".$map_width."x".$map_height."&maptype=".$map_type."&markers=size:".$marker_size."%7Ccolor:".$marker_color."%7C|".$address['lat'].",".$address['lon']."&sensor=false";
	$map .= '<div class="map">';
	if($link_map==1) $map .= '<a href="'.$map_link.'" target="_blank">';
	$map .= '<img src="'.$map_url.'" width="'.$map_width.'" height="'.$map_height.'" />';
	if($link_map==1) $map .= '<br />Click Map for Directions</a>';
	$map .= '</div>';
}
echo $field_prefix;
if($map_position==0) echo $map;
if($address['addr1']) echo '<div class="addr1">'.$address['addr1'].'</div>';
if($address['addr2']) echo '<div class="addr2">'.$address['addr2'].'</div>';
if($address['city']||$address['state']||$address['province']) {
	echo '<div class="city-state-zip">';
	if($address['city']) echo '<span class="city">'.$address['city'].'</span>, ';
	if($address['state']) echo '<span class="state">'.$address['state'].'</span> ';
	if($address['province']) echo '<span class="province">'.$address['province'].'</span> ';
	if($address['zip']) echo '<span class="zip">'.$address['zip'].'</span>';			
	echo '</div>';
}
if($address['country']) echo '<div class="country">'.JText::_('PLG_FLEXICONTENT_FIELDS_ADDRESSINT_CC_'.$address['country']).'</div>';
if($map_position==1) echo $map;
echo $field_suffix;
?>