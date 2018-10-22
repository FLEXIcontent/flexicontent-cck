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

defined('_JEXEC') or die('Restricted access');
?>
<script>
function submitbutton(pressbutton)
{
	var form = document.adminForm;
	if (pressbutton == 'cancel') {
		submitform( pressbutton );
		return;
	}

	// do field validation
	if (form.altname.value == ""){
		alert( "<?php echo JText::_( 'FLEXI_ADD_NAME_TAG',true ); ?>" );
	} else {
		submitform( pressbutton );
	}
}
</script>

<?php

$tip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
$btn_class = FLEXI_J30GE ? 'btn' : 'fc_button fcsimple';
$disabled = $this->row->url ? '' : ' disabled="disabled"';
?>


<div id="flexicontent" class="flexicontent">
<form action="index.php" method="post" name="adminForm" id="adminForm" class="form-validate form-horizontal">

	<table class="fc-form-tbl">

		<tr>
			<td class="key hasTooltip" title="<?php echo flexicontent_html::getToolTip('FLEXI_FILE_FILENAME', 'FLEXI_FILE_FILENAME_DESC', 1, 1); ?>">
				<label class="fc-prop-lbl" for="filename_original">
					<?php
					switch ((int) $this->row->url)
					{
						case 0:
							echo JText::_('FLEXI_FILENAME');
							break;
						case 1:
							echo JText::_('FLEXI_URL_LINK');
							break;
						case 2:
							echo JText::_('FLEXI_JMEDIA_LINK');
							break;
					}
					?>
				</label>
			</td>
			<td>
				<?php if ((int) $this->row->url !== 2) :

					echo '
					<input type="text" id="filename_original" name="filename_original" value="' . (strlen($this->row->filename_original) ? $this->row->filename_original : $this->row->filename) . '" class="input-xxlarge required" maxlength="4000" />
					';

				else :

					$jMedia_file_displayData = array(
						'disabled' => false,
						'preview' => 'tooltip',
						'readonly' => false,
						'class' => 'required',
						'link' => 'index.php?option=com_media&amp;view=images&amp;layout=default_fc&amp;tmpl=component&amp;filetypes=folders,images,docs,videos&amp;asset=',  //com_flexicontent&amp;author=&amp;fieldid=\'+mm_id+\'&amp;folder='
						'asset' => 'com_flexicontent',
						'authorId' => '',
						'previewWidth' => 480,
						'previewHeight' => 360,
						'name' => 'filename_original',
						'id' => 'filename_original',
						'value' => strlen($this->row->filename_original) ? $this->row->filename_original : $this->row->filename,
						'folder' => '',
					);
					echo JLayoutHelper::render($media_field_layout = 'joomla.form.field.media', $jMedia_file_displayData, $layouts_path = null);

				endif; ?>

			</td>
		</tr>

		<tr>
			<td class="key hasTooltip" title="<?php echo flexicontent_html::getToolTip('FLEXI_FILE_DISPLAY_TITLE', 'FLEXI_FILE_DISPLAY_TITLE_DESC', 1, 1); ?>">
				<label class="fc-prop-lbl" for="altname">
					<?php echo JText::_( 'FLEXI_FILE_DISPLAY_TITLE' ); ?>
				</label>
			</td>
			<td>
				<input type="text" id="altname" name="altname" value="<?php echo $this->row->altname; ?>" maxlength="4000" class="input-xxlarge" />
			</td>
		</tr>

		<tr>
			<td class="key hasTooltip" title="<?php echo flexicontent_html::getToolTip('FLEXI_DESCRIPTION', 'FLEXI_FILE_DESCRIPTION_DESC', 1, 1); ?>">
				<label class="fc-prop-lbl" for="file-desc">
				<?php echo JText::_( 'FLEXI_DESCRIPTION' ); ?>
				</label>
			</td>
			<td>
				<textarea name="description" rows="5" class="input-xxlarge" id="file-desc"><?php echo $this->row->description; ?></textarea>
			</td>
		</tr>

		<tr>
			<td class="key hasTooltip" title="<?php echo flexicontent_html::getToolTip('FLEXI_LANGUAGE', 'FLEXI_FILE_LANGUAGE_DESC', 1, 1); ?>">
				<label class="fc-prop-lbl" for="language">
					<?php echo JText::_( 'FLEXI_LANGUAGE' ); ?>
				</label>
			</td>
			<td>
				<span style="width:94%; max-width:800px; display:inline-block;">
					<?php echo $this->lists['language']; ?>
				</span>
			</td>
		</tr>

		<tr>
			<td class="key hasTooltip" title="<?php echo flexicontent_html::getToolTip('FLEXI_ACCESS', 'FLEXI_FILE_ACCESS_DESC', 1, 1); ?>">
				<label class="fc-prop-lbl" for="access">
					<?php echo JText::_( 'FLEXI_ACCESS' ); ?>
				</label>
			</td>
			<td>
				<?php echo $this->lists['access']; ?>
			</td>
		</tr>

		<tr>
			<td class="key hasTooltip" title="<?php echo flexicontent_html::getToolTip('FLEXI_DOWNLOAD_STAMPING', 'FLEXI_FILE_DOWNLOAD_STAMPING_CONF_FILE_FIELD_DESC', 1, 1); ?>">
				<label class="fc-prop-lbl" data-for="stamp">
					<?php echo JText::_( 'FLEXI_DOWNLOAD_STAMPING' ); ?>
				</label>
			</td>
			<td>
				<?php echo $this->lists['stamp']; ?>
			</td>
		</tr>

		<tr>
			<td class="key hasTooltip" title="<?php echo flexicontent_html::getToolTip('FLEXI_HITS', 'FLEXI_DOWNLOAD_HITS', 1, 1); ?>">
				<label class="fc-prop-lbl" for="access">
					<?php echo JText::_( 'FLEXI_HITS' ); ?>
				</label>
			</td>
			<td>
				<input type="text" id="hits" name="hits" value="<?php echo $this->row->hits; ?>" maxlength="10" class="input-small" />
			</td>
		</tr>

		<tr>
			<td class="key hasTooltip" title="<?php echo flexicontent_html::getToolTip('FLEXI_FILEEXT_MIME', 'FLEXI_FILEEXT_MIME_DESC' ); ?>">
				<label class="fc-prop-lbl" for="mime_ext">
					<?php echo JText::_( 'FLEXI_FILEEXT_MIME' ); ?>
				</label>
			</td>
			<td>
				<input type="text" id="mime_ext" name="ext" value="<?php echo $this->row->ext; ?>" size="5" style="max-width:100px;" maxlength="100"/>
<select class="use_select2_lib" onchange="jQuery(this).parent().find('input').val(jQuery(this).val()); jQuery(this).val('').select2('destroy').show().select2(); ">
<option value=""><?php echo JText::_( 'FLEXI_PLEASE_SELECT' ); ?></option>
<option value="3dm">3dm :: x-world/x-3dmf</option>
<option value="3dmf">3dmf :: x-world/x-3dmf</option>
<option value="a">a :: application/octet-stream</option>
<option value="aab">aab :: application/x-authorware-bin</option>
<option value="aam">aam :: application/x-authorware-map</option>
<option value="aas">aas :: application/x-authorware-seg</option>
<option value="abc">abc :: text/vnd.abc</option>
<option value="acgi">acgi :: text/html</option>
<option value="afl">afl :: video/animaflex</option>
<option value="ai">ai :: application/postscript</option>
<option value="aif">aif :: audio/aiff</option>
<option value="aif">aif :: audio/x-aiff</option>
<option value="aifc">aifc :: audio/aiff</option>
<option value="aifc">aifc :: audio/x-aiff</option>
<option value="aiff">aiff :: audio/aiff</option>
<option value="aiff">aiff :: audio/x-aiff</option>
<option value="aim">aim :: application/x-aim</option>
<option value="aip">aip :: text/x-audiosoft-intra</option>
<option value="ani">ani :: application/x-navi-animation</option>
<option value="aos">aos :: application/x-nokia-9000-communicator-add-on-software</option>
<option value="aps">aps :: application/mime</option>
<option value="arc">arc :: application/octet-stream</option>
<option value="arj">arj :: application/arj</option>
<option value="arj">arj :: application/octet-stream</option>
<option value="art">art :: image/x-jg</option>
<option value="asf">asf :: video/x-ms-asf</option>
<option value="asm">asm :: text/x-asm</option>
<option value="asp">asp :: text/asp</option>
<option value="asx">asx :: application/x-mplayer2</option>
<option value="asx">asx :: video/x-ms-asf</option>
<option value="asx">asx :: video/x-ms-asf-plugin</option>
<option value="au">au :: audio/basic</option>
<option value="au">au :: audio/x-au</option>
<option value="avi">avi :: application/x-troff-msvideo</option>
<option value="avi">avi :: video/avi</option>
<option value="avi">avi :: video/msvideo</option>
<option value="avi">avi :: video/x-msvideo</option>
<option value="avs">avs :: video/avs-video</option>
<option value="bcpio">bcpio :: application/x-bcpio</option>
<option value="bin">bin :: application/mac-binary</option>
<option value="bin">bin :: application/macbinary</option>
<option value="bin">bin :: application/octet-stream</option>
<option value="bin">bin :: application/x-binary</option>
<option value="bin">bin :: application/x-macbinary</option>
<option value="bm">bm :: image/bmp</option>
<option value="bmp">bmp :: image/bmp</option>
<option value="bmp">bmp :: image/x-windows-bmp</option>
<option value="boo">boo :: application/book</option>
<option value="book">book :: application/book</option>
<option value="boz">boz :: application/x-bzip2</option>
<option value="bsh">bsh :: application/x-bsh</option>
<option value="bz">bz :: application/x-bzip</option>
<option value="bz2">bz2 :: application/x-bzip2</option>
<option value="c">c :: text/plain</option>
<option value="c">c :: text/x-c</option>
<option value="c++">text/plain</option>
<option value="cat">cat :: application/vnd.ms-pki.seccat</option>
<option value="cc">cc :: text/plain</option>
<option value="cc">cc :: text/x-c</option>
<option value="ccad">ccad :: application/clariscad</option>
<option value="cco">cco :: application/x-cocoa</option>
<option value="cdf">cdf :: application/cdf</option>
<option value="cdf">cdf :: application/x-cdf</option>
<option value="cdf">cdf :: application/x-netcdf</option>
<option value="cer">cer :: application/pkix-cert</option>
<option value="cer">cer :: application/x-x509-ca-cert</option>
<option value="cha">cha :: application/x-chat</option>
<option value="chat">chat :: application/x-chat</option>
<option value="class">class :: application/java</option>
<option value="class">class :: application/java-byte-code</option>
<option value="class">class :: application/x-java-class</option>
<option value="com">com :: application/octet-stream</option>
<option value="com">com :: text/plain</option>
<option value="conf">conf :: text/plain</option>
<option value="cpio">cpio :: application/x-cpio</option>
<option value="cpp">cpp :: text/x-c</option>
<option value="cpt">cpt :: application/mac-compactpro</option>
<option value="cpt">cpt :: application/x-compactpro</option>
<option value="cpt">cpt :: application/x-cpt</option>
<option value="crl">crl :: application/pkcs-crl</option>
<option value="crl">crl :: application/pkix-crl</option>
<option value="crt">crt :: application/pkix-cert</option>
<option value="crt">crt :: application/x-x509-ca-cert</option>
<option value="crt">crt :: application/x-x509-user-cert</option>
<option value="csh">csh :: application/x-csh</option>
<option value="csh">csh :: text/x-script.csh</option>
<option value="css">css :: application/x-pointplus</option>
<option value="css">css :: text/css</option>
<option value="cxx">cxx :: text/plain</option>
<option value="dcr">dcr :: application/x-director</option>
<option value="deepv">deepv :: application/x-deepv</option>
<option value="def">def :: text/plain</option>
<option value="der">der :: application/x-x509-ca-cert</option>
<option value="dif">dif :: video/x-dv</option>
<option value="dir">dir :: application/x-director</option>
<option value="dl">dl :: video/dl</option>
<option value="dl">dl :: video/x-dl</option>
<option value="doc">doc :: application/msword</option>
<option value="dot">dot :: application/msword</option>
<option value="dp">dp :: application/commonground</option>
<option value="drw">drw :: application/drafting</option>
<option value="dump">dump :: application/octet-stream</option>
<option value="dv">dv :: video/x-dv</option>
<option value="dvi">dvi :: application/x-dvi</option>
<option value="dwf">dwf :: drawing/x-dwf (old)</option>
<option value="dwf">dwf :: model/vnd.dwf</option>
<option value="dwg">dwg :: application/acad</option>
<option value="dwg">dwg :: image/vnd.dwg</option>
<option value="dwg">dwg :: image/x-dwg</option>
<option value="dxf">dxf :: application/dxf</option>
<option value="dxf">dxf :: image/vnd.dwg</option>
<option value="dxf">dxf :: image/x-dwg</option>
<option value="dxr">dxr :: application/x-director</option>
<option value="el">el :: text/x-script.elisp</option>
<option value="elc">elc :: application/x-bytecode.elisp (compiled elisp)</option>
<option value="elc">elc :: application/x-elc</option>
<option value="env">env :: application/x-envoy</option>
<option value="eps">eps :: application/postscript</option>
<option value="es">es :: application/x-esrehber</option>
<option value="etx">etx :: text/x-setext</option>
<option value="evy">evy :: application/envoy</option>
<option value="evy">evy :: application/x-envoy</option>
<option value="exe">exe :: application/octet-stream</option>
<option value="f">f :: text/plain</option>
<option value="f">f :: text/x-fortran</option>
<option value="f77">f77 :: text/x-fortran</option>
<option value="f90">f90 :: text/plain</option>
<option value="f90">f90 :: text/x-fortran</option>
<option value="fdf">fdf :: application/vnd.fdf</option>
<option value="fif">fif :: application/fractals</option>
<option value="fif">fif :: image/fif</option>
<option value="fli">fli :: video/fli</option>
<option value="fli">fli :: video/x-fli</option>
<option value="flo">flo :: image/florian</option>
<option value="flx">flx :: text/vnd.fmi.flexstor</option>
<option value="fmf">fmf :: video/x-atomic3d-feature</option>
<option value="for">for :: text/plain</option>
<option value="for">for :: text/x-fortran</option>
<option value="fpx">fpx :: image/vnd.fpx</option>
<option value="fpx">fpx :: image/vnd.net-fpx</option>
<option value="frl">frl :: application/freeloader</option>
<option value="funk">funk :: audio/make</option>
<option value="g">g :: text/plain</option>
<option value="g3">g3 :: image/g3fax</option>
<option value="gif">gif :: image/gif</option>
<option value="gl">gl :: video/gl</option>
<option value="gl">gl :: video/x-gl</option>
<option value="gsd">gsd :: audio/x-gsm</option>
<option value="gsm">gsm :: audio/x-gsm</option>
<option value="gsp">gsp :: application/x-gsp</option>
<option value="gss">gss :: application/x-gss</option>
<option value="gtar">gtar :: application/x-gtar</option>
<option value="gz">gz :: application/x-compressed</option>
<option value="gz">gz :: application/x-gzip</option>
<option value="gzip">gzip :: application/x-gzip</option>
<option value="gzip">gzip :: multipart/x-gzip</option>
<option value="h">h :: text/plain</option>
<option value="h">h :: text/x-h</option>
<option value="hdf">hdf :: application/x-hdf</option>
<option value="help">help :: application/x-helpfile</option>
<option value="hgl">hgl :: application/vnd.hp-hpgl</option>
<option value="hh">hh :: text/plain</option>
<option value="hh">hh :: text/x-h</option>
<option value="hlb">hlb :: text/x-script</option>
<option value="hlp">hlp :: application/hlp</option>
<option value="hlp">hlp :: application/x-helpfile</option>
<option value="hlp">hlp :: application/x-winhelp</option>
<option value="hpg">hpg :: application/vnd.hp-hpgl</option>
<option value="hpgl">hpgl :: application/vnd.hp-hpgl</option>
<option value="hqx">hqx :: application/binhex</option>
<option value="hqx">hqx :: application/binhex4</option>
<option value="hqx">hqx :: application/mac-binhex</option>
<option value="hqx">hqx :: application/mac-binhex40</option>
<option value="hqx">hqx :: application/x-binhex40</option>
<option value="hqx">hqx :: application/x-mac-binhex40</option>
<option value="hta">hta :: application/hta</option>
<option value="htc">htc :: text/x-component</option>
<option value="htm">htm :: text/html</option>
<option value="html">html :: text/html</option>
<option value="htmls">htmls :: text/html</option>
<option value="htt">htt :: text/webviewhtml</option>
<option value="htx">htx :: text/html</option>
<option value="ice">ice :: x-conference/x-cooltalk</option>
<option value="ico">ico :: image/x-icon</option>
<option value="idc">idc :: text/plain</option>
<option value="ief">ief :: image/ief</option>
<option value="iefs">iefs :: image/ief</option>
<option value="iges">iges :: application/iges</option>
<option value="iges">iges :: model/iges</option>
<option value="igs">igs :: application/iges</option>
<option value="igs">igs :: model/iges</option>
<option value="ima">ima :: application/x-ima</option>
<option value="imap">imap :: application/x-httpd-imap</option>
<option value="inf">inf :: application/inf</option>
<option value="ins">ins :: application/x-internett-signup</option>
<option value="ip">ip :: application/x-ip2</option>
<option value="isu">isu :: video/x-isvideo</option>
<option value="it">it :: audio/it</option>
<option value="iv">iv :: application/x-inventor</option>
<option value="ivr">ivr :: i-world/i-vrml</option>
<option value="ivy">ivy :: application/x-livescreen</option>
<option value="jam">jam :: audio/x-jam</option>
<option value="jav">jav :: text/plain</option>
<option value="jav">jav :: text/x-java-source</option>
<option value="java">java :: text/plain</option>
<option value="java">java :: text/x-java-source</option>
<option value="jcm">jcm :: application/x-java-commerce</option>
<option value="jfif">jfif :: image/jpeg</option>
<option value="jfif">jfif :: image/pjpeg</option>
<option value="jfif-tbnl">image/jpeg</option>
<option value="jpe">jpe :: image/jpeg</option>
<option value="jpe">jpe :: image/pjpeg</option>
<option value="jpeg">jpeg :: image/jpeg</option>
<option value="jpeg">jpeg :: image/pjpeg</option>
<option value="jpg">jpg :: image/jpeg</option>
<option value="jpg">jpg :: image/pjpeg</option>
<option value="jps">jps :: image/x-jps</option>
<option value="js">js :: application/x-javascript</option>
<option value="js">js :: application/javascript</option>
<option value="js">js :: application/ecmascript</option>
<option value="js">js :: text/javascript</option>
<option value="js">js :: text/ecmascript</option>
<option value="jut">jut :: image/jutvision</option>
<option value="kar">kar :: audio/midi</option>
<option value="kar">kar :: music/x-karaoke</option>
<option value="ksh">ksh :: application/x-ksh</option>
<option value="ksh">ksh :: text/x-script.ksh</option>
<option value="la">la :: audio/nspaudio</option>
<option value="la">la :: audio/x-nspaudio</option>
<option value="lam">lam :: audio/x-liveaudio</option>
<option value="latex">latex :: application/x-latex</option>
<option value="lha">lha :: application/lha</option>
<option value="lha">lha :: application/octet-stream</option>
<option value="lha">lha :: application/x-lha</option>
<option value="lhx">lhx :: application/octet-stream</option>
<option value="list">list :: text/plain</option>
<option value="lma">lma :: audio/nspaudio</option>
<option value="lma">lma :: audio/x-nspaudio</option>
<option value="log">log :: text/plain</option>
<option value="lsp">lsp :: application/x-lisp</option>
<option value="lsp">lsp :: text/x-script.lisp</option>
<option value="lst">lst :: text/plain</option>
<option value="lsx">lsx :: text/x-la-asf</option>
<option value="ltx">ltx :: application/x-latex</option>
<option value="lzh">lzh :: application/octet-stream</option>
<option value="lzh">lzh :: application/x-lzh</option>
<option value="lzx">lzx :: application/lzx</option>
<option value="lzx">lzx :: application/octet-stream</option>
<option value="lzx">lzx :: application/x-lzx</option>
<option value="m">m :: text/plain</option>
<option value="m">m :: text/x-m</option>
<option value="m1v">m1v :: video/mpeg</option>
<option value="m2a">m2a :: audio/mpeg</option>
<option value="m2v">m2v :: video/mpeg</option>
<option value="m3u">m3u :: audio/x-mpequrl</option>
<option value="man">man :: application/x-troff-man</option>
<option value="map">map :: application/x-navimap</option>
<option value="mar">mar :: text/plain</option>
<option value="mbd">mbd :: application/mbedlet</option>
<option value="mc$">application/x-magic-cap-package-1.0</option>
<option value="mcd">mcd :: application/mcad</option>
<option value="mcd">mcd :: application/x-mathcad</option>
<option value="mcf">mcf :: image/vasa</option>
<option value="mcf">mcf :: text/mcf</option>
<option value="mcp">mcp :: application/netmc</option>
<option value="me">me :: application/x-troff-me</option>
<option value="mht">mht :: message/rfc822</option>
<option value="mhtml">mhtml :: message/rfc822</option>
<option value="mid">mid :: application/x-midi</option>
<option value="mid">mid :: audio/midi</option>
<option value="mid">mid :: audio/x-mid</option>
<option value="mid">mid :: audio/x-midi</option>
<option value="mid">mid :: music/crescendo</option>
<option value="mid">mid :: x-music/x-midi</option>
<option value="midi">midi :: application/x-midi</option>
<option value="midi">midi :: audio/midi</option>
<option value="midi">midi :: audio/x-mid</option>
<option value="midi">midi :: audio/x-midi</option>
<option value="midi">midi :: music/crescendo</option>
<option value="midi">midi :: x-music/x-midi</option>
<option value="mif">mif :: application/x-frame</option>
<option value="mif">mif :: application/x-mif</option>
<option value="mime">mime :: message/rfc822</option>
<option value="mime">mime :: www/mime</option>
<option value="mjf">mjf :: audio/x-vnd.audioexplosion.mjuicemediafile</option>
<option value="mjpg">mjpg :: video/x-motion-jpeg</option>
<option value="mm">mm :: application/base64</option>
<option value="mm">mm :: application/x-meme</option>
<option value="mme">mme :: application/base64</option>
<option value="mod">mod :: audio/mod</option>
<option value="mod">mod :: audio/x-mod</option>
<option value="moov">moov :: video/quicktime</option>
<option value="mov">mov :: video/quicktime</option>
<option value="movie">movie :: video/x-sgi-movie</option>
<option value="mp2">mp2 :: audio/mpeg</option>
<option value="mp2">mp2 :: audio/x-mpeg</option>
<option value="mp2">mp2 :: video/mpeg</option>
<option value="mp2">mp2 :: video/x-mpeg</option>
<option value="mp2">mp2 :: video/x-mpeq2a</option>
<option value="mp3">mp3 :: audio/mpeg3</option>
<option value="mp3">mp3 :: audio/x-mpeg-3</option>
<option value="mp3">mp3 :: video/mpeg</option>
<option value="mp3">mp3 :: video/x-mpeg</option>
<option value="mpa">mpa :: audio/mpeg</option>
<option value="mpa">mpa :: video/mpeg</option>
<option value="mpc">mpc :: application/x-project</option>
<option value="mpe">mpe :: video/mpeg</option>
<option value="mpeg">mpeg :: video/mpeg</option>
<option value="mpg">mpg :: audio/mpeg</option>
<option value="mpg">mpg :: video/mpeg</option>
<option value="mpga">mpga :: audio/mpeg</option>
<option value="mpp">mpp :: application/vnd.ms-project</option>
<option value="mpt">mpt :: application/x-project</option>
<option value="mpv">mpv :: application/x-project</option>
<option value="mpx">mpx :: application/x-project</option>
<option value="mrc">mrc :: application/marc</option>
<option value="ms">ms :: application/x-troff-ms</option>
<option value="mv">mv :: video/x-sgi-movie</option>
<option value="my">my :: audio/make</option>
<option value="mzz">mzz :: application/x-vnd.audioexplosion.mzz</option>
<option value="nap">nap :: image/naplps</option>
<option value="naplps">naplps :: image/naplps</option>
<option value="nc">nc :: application/x-netcdf</option>
<option value="ncm">ncm :: application/vnd.nokia.configuration-message</option>
<option value="nif">nif :: image/x-niff</option>
<option value="niff">niff :: image/x-niff</option>
<option value="nix">nix :: application/x-mix-transfer</option>
<option value="nsc">nsc :: application/x-conference</option>
<option value="nvd">nvd :: application/x-navidoc</option>
<option value="o">o :: application/octet-stream</option>
<option value="oda">oda :: application/oda</option>
<option value="omc">omc :: application/x-omc</option>
<option value="omcd">omcd :: application/x-omcdatamaker</option>
<option value="omcr">omcr :: application/x-omcregerator</option>
<option value="p">p :: text/x-pascal</option>
<option value="p10">p10 :: application/pkcs10</option>
<option value="p10">p10 :: application/x-pkcs10</option>
<option value="p12">p12 :: application/pkcs-12</option>
<option value="p12">p12 :: application/x-pkcs12</option>
<option value="p7a">p7a :: application/x-pkcs7-signature</option>
<option value="p7c">p7c :: application/pkcs7-mime</option>
<option value="p7c">p7c :: application/x-pkcs7-mime</option>
<option value="p7m">p7m :: application/pkcs7-mime</option>
<option value="p7m">p7m :: application/x-pkcs7-mime</option>
<option value="p7r">p7r :: application/x-pkcs7-certreqresp</option>
<option value="p7s">p7s :: application/pkcs7-signature</option>
<option value="part">part :: application/pro_eng</option>
<option value="pas">pas :: text/pascal</option>
<option value="pbm">pbm :: image/x-portable-bitmap</option>
<option value="pcl">pcl :: application/vnd.hp-pcl</option>
<option value="pcl">pcl :: application/x-pcl</option>
<option value="pct">pct :: image/x-pict</option>
<option value="pcx">pcx :: image/x-pcx</option>
<option value="pdb">pdb :: chemical/x-pdb</option>
<option value="pdf">pdf :: application/pdf</option>
<option value="pfunk">pfunk :: audio/make</option>
<option value="pfunk">pfunk :: audio/make.my.funk</option>
<option value="pgm">pgm :: image/x-portable-graymap</option>
<option value="pgm">pgm :: image/x-portable-greymap</option>
<option value="pic">pic :: image/pict</option>
<option value="pict">pict :: image/pict</option>
<option value="pkg">pkg :: application/x-newton-compatible-pkg</option>
<option value="pko">pko :: application/vnd.ms-pki.pko</option>
<option value="pl">pl :: text/plain</option>
<option value="pl">pl :: text/x-script.perl</option>
<option value="plx">plx :: application/x-pixclscript</option>
<option value="pm">pm :: image/x-xpixmap</option>
<option value="pm">pm :: text/x-script.perl-module</option>
<option value="pm4">pm4 :: application/x-pagemaker</option>
<option value="pm5">pm5 :: application/x-pagemaker</option>
<option value="png">png :: image/png</option>
<option value="pnm">pnm :: application/x-portable-anymap</option>
<option value="pnm">pnm :: image/x-portable-anymap</option>
<option value="pot">pot :: application/mspowerpoint</option>
<option value="pot">pot :: application/vnd.ms-powerpoint</option>
<option value="pov">pov :: model/x-pov</option>
<option value="ppa">ppa :: application/vnd.ms-powerpoint</option>
<option value="ppm">ppm :: image/x-portable-pixmap</option>
<option value="pps">pps :: application/mspowerpoint</option>
<option value="pps">pps :: application/vnd.ms-powerpoint</option>
<option value="ppt">ppt :: application/mspowerpoint</option>
<option value="ppt">ppt :: application/powerpoint</option>
<option value="ppt">ppt :: application/vnd.ms-powerpoint</option>
<option value="ppt">ppt :: application/x-mspowerpoint</option>
<option value="ppz">ppz :: application/mspowerpoint</option>
<option value="pre">pre :: application/x-freelance</option>
<option value="prt">prt :: application/pro_eng</option>
<option value="ps">ps :: application/postscript</option>
<option value="psd">psd :: application/octet-stream</option>
<option value="pvu">pvu :: paleovu/x-pv</option>
<option value="pwz">pwz :: application/vnd.ms-powerpoint</option>
<option value="py">py :: text/x-script.phyton</option>
<option value="pyc">pyc :: application/x-bytecode.python</option>
<option value="qcp">qcp :: audio/vnd.qcelp</option>
<option value="qd3">qd3 :: x-world/x-3dmf</option>
<option value="qd3d">qd3d :: x-world/x-3dmf</option>
<option value="qif">qif :: image/x-quicktime</option>
<option value="qt">qt :: video/quicktime</option>
<option value="qtc">qtc :: video/x-qtc</option>
<option value="qti">qti :: image/x-quicktime</option>
<option value="qtif">qtif :: image/x-quicktime</option>
<option value="ra">ra :: audio/x-pn-realaudio</option>
<option value="ra">ra :: audio/x-pn-realaudio-plugin</option>
<option value="ra">ra :: audio/x-realaudio</option>
<option value="ram">ram :: audio/x-pn-realaudio</option>
<option value="ras">ras :: application/x-cmu-raster</option>
<option value="ras">ras :: image/cmu-raster</option>
<option value="ras">ras :: image/x-cmu-raster</option>
<option value="rast">rast :: image/cmu-raster</option>
<option value="rexx">rexx :: text/x-script.rexx</option>
<option value="rf">rf :: image/vnd.rn-realflash</option>
<option value="rgb">rgb :: image/x-rgb</option>
<option value="rm">rm :: application/vnd.rn-realmedia</option>
<option value="rm">rm :: audio/x-pn-realaudio</option>
<option value="rmi">rmi :: audio/mid</option>
<option value="rmm">rmm :: audio/x-pn-realaudio</option>
<option value="rmp">rmp :: audio/x-pn-realaudio</option>
<option value="rmp">rmp :: audio/x-pn-realaudio-plugin</option>
<option value="rng">rng :: application/ringing-tones</option>
<option value="rng">rng :: application/vnd.nokia.ringing-tone</option>
<option value="rnx">rnx :: application/vnd.rn-realplayer</option>
<option value="roff">roff :: application/x-troff</option>
<option value="rp">rp :: image/vnd.rn-realpix</option>
<option value="rpm">rpm :: audio/x-pn-realaudio-plugin</option>
<option value="rt">rt :: text/richtext</option>
<option value="rt">rt :: text/vnd.rn-realtext</option>
<option value="rtf">rtf :: application/rtf</option>
<option value="rtf">rtf :: application/x-rtf</option>
<option value="rtf">rtf :: text/richtext</option>
<option value="rtx">rtx :: application/rtf</option>
<option value="rtx">rtx :: text/richtext</option>
<option value="rv">rv :: video/vnd.rn-realvideo</option>
<option value="s">s :: text/x-asm</option>
<option value="s3m">s3m :: audio/s3m</option>
<option value="saveme">saveme :: application/octet-stream</option>
<option value="sbk">sbk :: application/x-tbook</option>
<option value="scm">scm :: application/x-lotusscreencam</option>
<option value="scm">scm :: text/x-script.guile</option>
<option value="scm">scm :: text/x-script.scheme</option>
<option value="scm">scm :: video/x-scm</option>
<option value="sdml">sdml :: text/plain</option>
<option value="sdp">sdp :: application/sdp</option>
<option value="sdp">sdp :: application/x-sdp</option>
<option value="sdr">sdr :: application/sounder</option>
<option value="sea">sea :: application/sea</option>
<option value="sea">sea :: application/x-sea</option>
<option value="set">set :: application/set</option>
<option value="sgm">sgm :: text/sgml</option>
<option value="sgm">sgm :: text/x-sgml</option>
<option value="sgml">sgml :: text/sgml</option>
<option value="sgml">sgml :: text/x-sgml</option>
<option value="sh">sh :: application/x-bsh</option>
<option value="sh">sh :: application/x-sh</option>
<option value="sh">sh :: application/x-shar</option>
<option value="sh">sh :: text/x-script.sh</option>
<option value="shar">shar :: application/x-bsh</option>
<option value="shar">shar :: application/x-shar</option>
<option value="shtml">shtml :: text/html</option>
<option value="shtml">shtml :: text/x-server-parsed-html</option>
<option value="sid">sid :: audio/x-psid</option>
<option value="sit">sit :: application/x-sit</option>
<option value="sit">sit :: application/x-stuffit</option>
<option value="skd">skd :: application/x-koan</option>
<option value="skm">skm :: application/x-koan</option>
<option value="skp">skp :: application/x-koan</option>
<option value="skt">skt :: application/x-koan</option>
<option value="sl">sl :: application/x-seelogo</option>
<option value="smi">smi :: application/smil</option>
<option value="smil">smil :: application/smil</option>
<option value="snd">snd :: audio/basic</option>
<option value="snd">snd :: audio/x-adpcm</option>
<option value="sol">sol :: application/solids</option>
<option value="spc">spc :: application/x-pkcs7-certificates</option>
<option value="spc">spc :: text/x-speech</option>
<option value="spl">spl :: application/futuresplash</option>
<option value="spr">spr :: application/x-sprite</option>
<option value="sprite">sprite :: application/x-sprite</option>
<option value="src">src :: application/x-wais-source</option>
<option value="ssi">ssi :: text/x-server-parsed-html</option>
<option value="ssm">ssm :: application/streamingmedia</option>
<option value="sst">sst :: application/vnd.ms-pki.certstore</option>
<option value="step">step :: application/step</option>
<option value="stl">stl :: application/sla</option>
<option value="stl">stl :: application/vnd.ms-pki.stl</option>
<option value="stl">stl :: application/x-navistyle</option>
<option value="stp">stp :: application/step</option>
<option value="sv4cpio">sv4cpio :: application/x-sv4cpio</option>
<option value="sv4crc">sv4crc :: application/x-sv4crc</option>
<option value="svf">svf :: image/vnd.dwg</option>
<option value="svf">svf :: image/x-dwg</option>
<option value="svr">svr :: application/x-world</option>
<option value="svr">svr :: x-world/x-svr</option>
<option value="swf">swf :: application/x-shockwave-flash</option>
<option value="t">t :: application/x-troff</option>
<option value="talk">talk :: text/x-speech</option>
<option value="tar">tar :: application/x-tar</option>
<option value="tbk">tbk :: application/toolbook</option>
<option value="tbk">tbk :: application/x-tbook</option>
<option value="tcl">tcl :: application/x-tcl</option>
<option value="tcl">tcl :: text/x-script.tcl</option>
<option value="tcsh">tcsh :: text/x-script.tcsh</option>
<option value="tex">tex :: application/x-tex</option>
<option value="texi">texi :: application/x-texinfo</option>
<option value="texinfo">texinfo :: application/x-texinfo</option>
<option value="text">text :: application/plain</option>
<option value="text">text :: text/plain</option>
<option value="tgz">tgz :: application/gnutar</option>
<option value="tgz">tgz :: application/x-compressed</option>
<option value="tif">tif :: image/tiff</option>
<option value="tif">tif :: image/x-tiff</option>
<option value="tiff">tiff :: image/tiff</option>
<option value="tiff">tiff :: image/x-tiff</option>
<option value="tr">tr :: application/x-troff</option>
<option value="tsi">tsi :: audio/tsp-audio</option>
<option value="tsp">tsp :: application/dsptype</option>
<option value="tsp">tsp :: audio/tsplayer</option>
<option value="tsv">tsv :: text/tab-separated-values</option>
<option value="turbot">turbot :: image/florian</option>
<option value="txt">txt :: text/plain</option>
<option value="uil">uil :: text/x-uil</option>
<option value="uni">uni :: text/uri-list</option>
<option value="unis">unis :: text/uri-list</option>
<option value="unv">unv :: application/i-deas</option>
<option value="uri">uri :: text/uri-list</option>
<option value="uris">uris :: text/uri-list</option>
<option value="ustar">ustar :: application/x-ustar</option>
<option value="ustar">ustar :: multipart/x-ustar</option>
<option value="uu">uu :: application/octet-stream</option>
<option value="uu">uu :: text/x-uuencode</option>
<option value="uue">uue :: text/x-uuencode</option>
<option value="vcd">vcd :: application/x-cdlink</option>
<option value="vcs">vcs :: text/x-vcalendar</option>
<option value="vda">vda :: application/vda</option>
<option value="vdo">vdo :: video/vdo</option>
<option value="vew">vew :: application/groupwise</option>
<option value="viv">viv :: video/vivo</option>
<option value="viv">viv :: video/vnd.vivo</option>
<option value="vivo">vivo :: video/vivo</option>
<option value="vivo">vivo :: video/vnd.vivo</option>
<option value="vmd">vmd :: application/vocaltec-media-desc</option>
<option value="vmf">vmf :: application/vocaltec-media-file</option>
<option value="voc">voc :: audio/voc</option>
<option value="voc">voc :: audio/x-voc</option>
<option value="vos">vos :: video/vosaic</option>
<option value="vox">vox :: audio/voxware</option>
<option value="vqe">vqe :: audio/x-twinvq-plugin</option>
<option value="vqf">vqf :: audio/x-twinvq</option>
<option value="vql">vql :: audio/x-twinvq-plugin</option>
<option value="vrml">vrml :: application/x-vrml</option>
<option value="vrml">vrml :: model/vrml</option>
<option value="vrml">vrml :: x-world/x-vrml</option>
<option value="vrt">vrt :: x-world/x-vrt</option>
<option value="vsd">vsd :: application/x-visio</option>
<option value="vst">vst :: application/x-visio</option>
<option value="vsw">vsw :: application/x-visio</option>
<option value="w60">w60 :: application/wordperfect6.0</option>
<option value="w61">w61 :: application/wordperfect6.1</option>
<option value="w6w">w6w :: application/msword</option>
<option value="wav">wav :: audio/wav</option>
<option value="wav">wav :: audio/x-wav</option>
<option value="wb1">wb1 :: application/x-qpro</option>
<option value="wbmp">wbmp :: image/vnd.wap.wbmp</option>
<option value="web">web :: application/vnd.xara</option>
<option value="wiz">wiz :: application/msword</option>
<option value="wk1">wk1 :: application/x-123</option>
<option value="wmf">wmf :: windows/metafile</option>
<option value="wml">wml :: text/vnd.wap.wml</option>
<option value="wmlc">wmlc :: application/vnd.wap.wmlc</option>
<option value="wmls">wmls :: text/vnd.wap.wmlscript</option>
<option value="wmlsc">wmlsc :: application/vnd.wap.wmlscriptc</option>
<option value="word">word :: application/msword</option>
<option value="wp">wp :: application/wordperfect</option>
<option value="wp5">wp5 :: application/wordperfect</option>
<option value="wp5">wp5 :: application/wordperfect6.0</option>
<option value="wp6">wp6 :: application/wordperfect</option>
<option value="wpd">wpd :: application/wordperfect</option>
<option value="wpd">wpd :: application/x-wpwin</option>
<option value="wq1">wq1 :: application/x-lotus</option>
<option value="wri">wri :: application/mswrite</option>
<option value="wri">wri :: application/x-wri</option>
<option value="wrl">wrl :: application/x-world</option>
<option value="wrl">wrl :: model/vrml</option>
<option value="wrl">wrl :: x-world/x-vrml</option>
<option value="wrz">wrz :: model/vrml</option>
<option value="wrz">wrz :: x-world/x-vrml</option>
<option value="wsc">wsc :: text/scriplet</option>
<option value="wsrc">wsrc :: application/x-wais-source</option>
<option value="wtk">wtk :: application/x-wintalk</option>
<option value="xbm">xbm :: image/x-xbitmap</option>
<option value="xbm">xbm :: image/x-xbm</option>
<option value="xbm">xbm :: image/xbm</option>
<option value="xdr">xdr :: video/x-amt-demorun</option>
<option value="xgz">xgz :: xgl/drawing</option>
<option value="xif">xif :: image/vnd.xiff</option>
<option value="xl">xl :: application/excel</option>
<option value="xla">xla :: application/excel</option>
<option value="xla">xla :: application/x-excel</option>
<option value="xla">xla :: application/x-msexcel</option>
<option value="xlb">xlb :: application/excel</option>
<option value="xlb">xlb :: application/vnd.ms-excel</option>
<option value="xlb">xlb :: application/x-excel</option>
<option value="xlc">xlc :: application/excel</option>
<option value="xlc">xlc :: application/vnd.ms-excel</option>
<option value="xlc">xlc :: application/x-excel</option>
<option value="xld">xld :: application/excel</option>
<option value="xld">xld :: application/x-excel</option>
<option value="xlk">xlk :: application/excel</option>
<option value="xlk">xlk :: application/x-excel</option>
<option value="xll">xll :: application/excel</option>
<option value="xll">xll :: application/vnd.ms-excel</option>
<option value="xll">xll :: application/x-excel</option>
<option value="xlm">xlm :: application/excel</option>
<option value="xlm">xlm :: application/vnd.ms-excel</option>
<option value="xlm">xlm :: application/x-excel</option>
<option value="xls">xls :: application/excel</option>
<option value="xls">xls :: application/vnd.ms-excel</option>
<option value="xls">xls :: application/x-excel</option>
<option value="xls">xls :: application/x-msexcel</option>
<option value="xlt">xlt :: application/excel</option>
<option value="xlt">xlt :: application/x-excel</option>
<option value="xlv">xlv :: application/excel</option>
<option value="xlv">xlv :: application/x-excel</option>
<option value="xlw">xlw :: application/excel</option>
<option value="xlw">xlw :: application/vnd.ms-excel</option>
<option value="xlw">xlw :: application/x-excel</option>
<option value="xlw">xlw :: application/x-msexcel</option>
<option value="xm">xm :: audio/xm</option>
<option value="xml">xml :: application/xml</option>
<option value="xml">xml :: text/xml</option>
<option value="xmz">xmz :: xgl/movie</option>
<option value="xpix">xpix :: application/x-vnd.ls-xpix</option>
<option value="xpm">xpm :: image/x-xpixmap</option>
<option value="xpm">xpm :: image/xpm</option>
<option value="x-png">image/png</option>
<option value="xsr">xsr :: video/x-amt-showrun</option>
<option value="xwd">xwd :: image/x-xwd</option>
<option value="xwd">xwd :: image/x-xwindowdump</option>
<option value="xyz">xyz :: chemical/x-pdb</option>
<option value="z">z :: application/x-compress</option>
<option value="z">z :: application/x-compressed</option>
<option value="zip">zip :: application/x-compressed</option>
<option value="zip">zip :: application/x-zip-compressed</option>
<option value="zip">zip :: application/zip</option>
<option value="zip">zip :: multipart/x-zip</option>
<option value="zoo">zoo :: application/octet-stream</option>
<option value="zsh">zsh :: text/x-script.zsh</option>
</select>
			</td>
		</tr>

		<?php if (!$this->row->url) : ?>

		<tr><td colspan="2"></td></tr>

		<tr>
			<td class="key">
				<span class="label label-info"><?php echo JText::_( 'FLEXI_SIZE' ); ?></span> &nbsp;
			</td>
			<td>
				<?php echo file_exists($this->rowdata->path) ? $this->rowdata->size_display : JText::_('FLEXI_FILE_NOT_FOUND'); ?>
			</td>
		</tr>

		<tr>
			<td class="key">
				<span class="label label-info"><?php echo JText::_( 'FLEXI_REAL_PATH' ); ?></span> &nbsp;
			</td>
			<td>
				<?php echo $this->rowdata->path;?>
			</td>
		</tr>

		<?php else: ?>

		<tr>
			<td class="key hasTooltip" title="<?php echo flexicontent_html::getToolTip('FLEXI_SIZE', 'FLEXI_SIZE_IN_FORM', 1, 1); ?>">
				<label class="fc-prop-lbl" for="size">
					<?php echo JText::_( 'FLEXI_SIZE' ); ?>
				</label>
			</td>
			<td>
				<input type="text" id="size" name="size" value="<?php echo ceil(((int)$this->rowdata->calculated_size)/1024.0); ?>" size="10" style="max-width:200px;" maxlength="100"/>
				<select id="size_unit" name="size_unit" class="use_select2_lib">
					<option value="KBs" selected="selected">KBs</option>
					<option value="MBs">MBs</option>
					<option value="GBs">GBs</option>
				</select>
				<span class="hasTooltip" title="<?php echo flexicontent_html::getToolTip('FLEXI_SIZE', 'FLEXI_SIZE_IN_FORM', 1, 1); ?>"><i class="icon-info"></i></span>

				<?php echo $this->rowdata->size_warning; ?>
			</td>
		</tr>
		
		<?php endif; ?>
		
	</table>


<?php echo JHtml::_( 'form.token' ); ?>
<input type="hidden" name="option" value="com_flexicontent" />
<?php if (!$this->row->url) : ?>
<input type="hidden" name="filename" value="<?php echo $this->row->filename; ?>" />
<input type="hidden" name="ext" value="<?php echo $this->row->ext; ?>" />
<?php endif; ?>
<input type="hidden" name="url" value="<?php echo $this->row->url; ?>" />
<input type="hidden" name="secure" value="<?php echo $this->row->secure; ?>" />
<input type="hidden" name="uploaded" value="<?php echo $this->row->uploaded; ?>" />
<input type="hidden" name="uploaded_by" value="<?php echo $this->row->uploaded_by; ?>" />
<input type="hidden" name="published" value="<?php echo $this->row->published; ?>" />
<input type="hidden" name="id" value="<?php echo $this->row->id; ?>" />
<input type="hidden" name="controller" value="filemanager" />
<input type="hidden" name="view" value="file" />
<input type="hidden" name="task" value="" />
</form>
</div>

<?php
//keep session alive while editing
JHtml::_('behavior.keepalive');
?>
