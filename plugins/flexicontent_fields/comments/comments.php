<?php
/**
 * @package         FLEXIcontent
 * @version         3.4
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2020, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined( '_JEXEC' ) or die( 'Restricted access' );
JLoader::register('FCField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/parentfield.php');
require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');

class plgFlexicontent_fieldsComments extends FCField
{
	static $field_types = null; // Automatic, do not remove since needed for proper late static binding, define explicitely when a field can render other field types
	var $task_callable = null;  // Field's methods allowed to be called via AJAX
	static $css_added = array();

	// ***
	// *** CONSTRUCTOR
	// ***

	public function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
	}

	// ***
	// *** DISPLAY methods, item form & frontend views
	// ***

	// Method to create field's HTML display for item form
	public function onDisplayField(&$field, &$item)
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
	}


	// Method to create field's HTML display for frontend views
	public function onDisplayFieldValue(&$field, $item, $values = null, $prop = 'display')
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;

		$document	= JFactory::getDocument();

		//API comments
		$comment_api 	= $field->parameters->get('comment_api');

		//Current url
		$page_url = JUri::getInstance();

		//language
		$mlang 					= $field->parameters->get('comment_language');
		$autolanguage			= $field->parameters->get('autolanguage','1');
		$jlang					= str_replace('-', '_', JFactory::getLanguage()->getTag());
		$comments_langs 	= array("af_ZA","gn_PY","ay_BO","az_AZ","id_ID","ms_MY","jv_ID","bs_BA","ca_ES","cs_CZ","ck_US","cy_GB","da_DK","se_NO",
		"de_DE","et_EE","ثn_IN","en_PI",	"en_GB","en_UD","en_US","es_LA","es_CL","es_CO","es_ES","es_MX","es_VE","eo_EO","eu_ES","tl_PH",
		"fo_FO","fr_FR","fr_CA","fy_NL","ga_IE","gl_ES","ko_KR","hr_HR","xh_ZA","zu_ZA","is_IS","it_IT","ka_GE","sw_KE","tl_ST","ku_TR",
		"lv_LV","fb_LT","lt_LT","li_NL","la_VA","hu_HU","mg_MG","mt_MT","nl_NL","nl_BE","ja_JP","nb_NO","nn_NO","uz_UZ","pl_PL","pt_BR",
		"pt_PT","qu_PE","ro_RO","rm_CH","ru_RU","sq_AL","sk_SK","sl_SI","so_SO","fi_FI","sv_SE","th_TH","vi_VN","tr_TR","zh_CN","zh_TW",
		"zh_HK","el_GR","gx_GR","be_BY","bg_BG","kk_KZ","mk_MK","mn_MN","sr_RS","tt_RU","tg_TJ","uk_UA","hy_AM","yi_DE","he_IL","ur_PK",
		"ar_AR","ps_AF","fa_IR","sy_SY","ne_NP","mr_IN","sa_IN","hi_IN","bn_IN","pa_IN","gu_IN","ta_IN","te_IN","kn_IN","ml_IN","km_KH");
		$language				= (($autolanguage && in_array($jlang, $comments_langs))) ? $jlang : $mlang;

		// Params for disqus
		$forum_shortname 	= $field->parameters->get('pforum_shortname');

		// Params for Facebook
		$fappId = $field->parameters->get('fappId');
		$fdatanumposts  	 = $field->parameters->get('fdata-num-posts');
		$fdatawidth  	 = $field->parameters->get('fdata-width');
		$fdatacolorscheme = $field->parameters->get('fdata-colorscheme');

		if ($comment_api == 'disqus')
		{
		if (!$forum_shortname) {
					$display=  '<div class="fc-mssg fc-info fc-iblock fc-nobgimage fcfavs-isnot-subscriber">
				'.JText::_("PLG_FLEXICONTENT_FIELDS_DISQUS_PLEASE_ENTER_YOUR_DISQUS_SUBDOMAIN").'
			</div>';
		} else {
				$page_identifier 	= $item->id;
				$document->addCustomTag('
				<script>
				    /**
				     *  RECOMMENDED CONFIGURATION VARIABLES: EDIT AND UNCOMMENT
				     *  THE SECTION BELOW TO INSERT DYNAMIC VALUES FROM YOUR
				     *  PLATFORM OR CMS.
				     *
				     *  LEARN WHY DEFINING THESE VARIABLES IS IMPORTANT:
				     *  https://disqus.com/admin/universalcode/#configuration-variables
				     */
				    /*
				    var disqus_config = function () {

								//Add $language
								this.language = "'.$language.'";

				        // Replace PAGE_URL with your canonical URL variable
				        this.page.url = '.$page_url.';

				        // Replace PAGE_IDENTIFIER with your page unique identifier variable
				        this.page.identifier = '.$page_identifier.';
				    };
				    */

				    (function() {  // REQUIRED CONFIGURATION VARIABLE: EDIT THE SHORTNAME BELOW
				        var d = document, s = d.createElement(\'script\');

				        // IMPORTANT: Replace EXAMPLE with your forum shortname!
				        s.src = \'https://'.$forum_shortname.'.disqus.com/embed.js\';

				        s.setAttribute(\'data-timestamp\', +new Date());
				        (d.head || d.body).appendChild(s);
				    })();
				</script>
				<noscript>
				    Please enable JavaScript to view the
				    <a href="https://disqus.com/?ref_noscript" rel="nofollow">
				        comments powered by Disqus.
				    </a>
				</noscript>
				');

				$display = "<div id='disqus_thread' style='float:none;'></div>";

				// Add custom styles for proper width
				if (!isset(static::$css_added[$field->id]))
				{
					static::$css_added[$field->id] = true;

					$style = '.flexi.value.field_'.$field->name.' {float: none;};';
					$document->addStyleDeclaration($style);
				}
			}
		}
		elseif ($comment_api == 'facebook')	{
			if (!$fappId) {
				$display=  '<div class="fc-mssg fc-info fc-iblock fc-nobgimage fcfavs-isnot-subscriber">
			'.JText::_("PLG_FLEXICONTENT_FIELDS_FACEBOOK_PLEASE_ENTER_YOUR_APPID").'
		</div>';
}
		//check params for FB
		if($fappId){$fappId = "&appId=".$fappId; }
		if($fdatanumposts){$fdatanumposts = "data-num-posts='".$fdatanumposts."'"; }
		if($fdatawidth){$fdatawidth = "data-width='".$fdatawidth."'"; }
		if($fdatacolorscheme)  { $fdatacolorscheme = "data-colorscheme='".$fdatacolorscheme."'"; }

		$document->addCustomTag('
			<script>(function(d, s, id) {
				  var js, fjs = d.getElementsByTagName(s)[0];
				  if (d.getElementById(id)) return;
				  js = d.createElement(s); js.id = id;
				  js.src = \'//connect.facebook.net/"'.$language.'"/all.js#xfbml=1$appId\';
				  fjs.parentNode.insertBefore(js, fjs);
				}(document, \'script\', \'facebook-jssdk\'));</script>
			');

		$display = "<div id='fb-root'></div><div class='fb-comments' data-href='http://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']."' ".
					$fdatanumposts." ".$fdatawidth." ".$fdatacolorscheme."></div>";
	}
	$catid= $item->catid;
	$catids = $field->parameters->get('catid');
	// Include : 1 or Exclude : -1 categories : 0 all
	$method_category = $field->parameters->get('method_category', '0');
	if ($method_category == 1 && in_array($catid, $catids)){//render comment in include mode
			$field->{$prop} = $display;
	}elseif ($method_category == 1 && !in_array($catid, $catids)) {//dont render comment in include mode
				$field->{$prop} = '';
	}elseif ($method_category == -1 && in_array($catid, $catids)) {//dont render comment in exclude mode
			$field->{$prop} = '';
	}elseif ($method_category == -1 && !in_array($catid, $catids)) {//render comment in exclude mode
		$field->{$prop} = $display;
	}else { //no filtering render comments
		$field->{$prop} = $display;
	}

}

	// ***
	// *** METHODS HANDLING before & after saving / deleting field events
	// ***

	// Method to handle field's values before they are saved into the DB
	function onBeforeSaveField( &$field, &$post, &$file, &$item )
	{
		if ( !in_array($field->field_type, static::$field_types) ) return;
	}


	// Method to take any actions/cleanups needed after field's values are saved into the DB
	function onAfterSaveField( &$field, &$post, &$file, &$item ) {
	}


	// Method called just before the item is deleted to remove custom item data related to the field
	function onBeforeDeleteField(&$field, &$item) {
	}

}
