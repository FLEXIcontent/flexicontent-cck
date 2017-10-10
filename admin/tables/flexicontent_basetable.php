<?php
use Joomla\String\StringHelper;

class flexicontent_basetable extends JTable
{
	/**
	 * Original code form: Phoca International Alias Plugin for Joomla 1.5
	 *
	 * This method processes a string and replaces all accented UTF-8 characters by unaccented
	 * ASCII-7 "equivalents", whitespaces are replaced by hyphens and the string is lowercased.
	 */
	function transliterate($string, $language)
	{
		$langFrom    = array();
		$langTo      = array();
		
		// BULGARIAN
		if ($language == 'bg-BG') {
			$bgLangFrom = array('А', 'а', 'Б', 'б', 'В', 'в', 'Г', 'г', 'Д', 'д', 'Е', 'е', 'Ж', 'ж', 'З', 'з', 'И', 'и', 'Й', 'й', 'К', 'к', 'Л', 'л', 'М', 'м', 'Н', 'н', 'О', 'о', 'П', 'п', 'Р', 'р', 'С', 'с', 'Т', 'т', 'У', 'у', 'Ф', 'ф', 'Х', 'х', 'Ц', 'ц', 'Ч', 'ч', 'Ш', 'ш', 'Щ', 'щ', 'Ъ', 'ъ', 'Ьо', 'ьо', 'Ю', 'ю', 'Я', 'я');
			$bgLangTo   = array('A', 'a', 'B', 'b', 'V', 'v', 'G', 'g', 'D', 'd', 'E', 'e', 'Zh', 'zh', 'Z', 'z', 'I', 'i', 'J', 'j', 'K', 'k', 'L', 'l', 'M', 'm', 'N', 'n', 'O', 'o', 'P', 'p', 'R', 'r', 'S', 's', 'T', 't', 'U', 'u', 'F', 'f', 'H', 'h', 'C', 'c', 'Ch', 'ch', 'Sh', 'sh', 'Sht', 'sht', 'Y', 'y', 'Io', 'io', 'Ju', 'ju', 'Ja', 'ja');
			$langFrom   = array_merge ($langFrom, $bgLangFrom);
			$langTo     = array_merge ($langTo, $bgLangTo);
		}
		
		// CZECH
		if ($language == 'cz-CZ') {
			$czLangFrom = array('á','č','ď','é','ě','í','ň','ó','ř','š','ť','ú','ů','ý','ž','Á','Č','Ď','É','Ě','Í','Ň','Ó','Ř','Š','Ť','Ú','Ů','Ý','Ž');
			$czLangTo   = array('a','c','d','e','e','i','n','o','r','s','t','u','u','y','z','a','c','d','e','e','i','ň','o','r','s','t','u','u','y','z');
			$langFrom   = array_merge ($langFrom, $czLangFrom);
			$langTo     = array_merge ($langTo, $czLangTo);
		}
		
		// CROATIAN
		if ($language == 'hr-HR' || $language == 'hr-BA') {
			$hrLangFrom = array('č','ć','đ','š','ž','Č','Ć','Đ','Š','Ž');
			$hrLangTo   = array('c','c','d','s','z','c','c','d','s','z');
			$langFrom   = array_merge ($langFrom, $hrLangFrom);
			$langTo     = array_merge ($langTo, $hrLangTo);
		}
		
		// GREEK
		if ($language == 'el-GR') {
			$grLangFrom = array('α', 'β', 'γ', 'δ', 'ε', 'ζ', 'η', 'θ',  'η', 'ι', 'κ', 'λ', 'μ', 'ν', 'ξ',  'ο', 'π', 'ρ', 'σ', 'τ', 'υ', 'φ', 'χ', 'ψ',  'ω', 'Α', 'Β', 'Γ', 'Δ', 'Ε', 'Ζ', 'Η', 'Θ',  'Ι', 'Κ', 'Λ', 'Μ', 'Ν', 'Ξ',  'Ο', 'Π', 'Ρ', 'Σ', 'Τ', 'Υ', 'Φ', 'Χ', 'Ψ',  'Ω', 'Ά', 'Έ', 'Ή', 'Ί', 'Ύ', 'Ό', 'Ώ', 'ά', 'έ', 'ή', 'ί', 'ύ', 'ό', 'ώ', 'ΰ', 'ΐ', 'ϋ', 'ϊ', 'ς', '«', '»' );
			$grLangTo   = array('a', 'b', 'g', 'd', 'e', 'z', 'h', 'th', 'i', 'i', 'k', 'l', 'm', 'n', 'ks', 'o', 'p', 'r', 's', 't', 'u', 'f', 'x', 'ps', 'o', 'A', 'B', 'G', 'D', 'E', 'Z', 'I', 'Th', 'I', 'K', 'L', 'M', 'N', 'Ks', 'O', 'P', 'R', 'S', 'T', 'Y', 'F', 'X', 'Ps', 'O', 'A', 'E', 'I', 'I', 'U', 'O', 'O', 'a', 'e', 'i', 'i', 'u', 'o', 'o', 'u', 'i', 'u', 'i', 's', '_', '_' );
			$langFrom   = array_merge ($langFrom, $grLangFrom);
			$langTo     = array_merge ($langTo, $grLangTo);
		}
		
		// HUNGARIAN
		if ($language == 'hu-HU') {
			$huLangFrom = array('á','é','ë','í','ó','ö','ő','ú','ü','ű','Á','É','Ë','Í','Ó','Ö','Ő','Ú','Ü','Ű');
			$huLangTo   = array('a','e','e','i','o','o','o','u','u','u','a','e','e','i','o','o','o','u','u','u');
			$langFrom   = array_merge ($langFrom, $huLangFrom);
			$langTo     = array_merge ($langTo, $huLangTo);
		}
		
		// POLISH
		if ($language == 'pl-PL') {
			$plLangFrom = array('ą','ć','ę','ł','ń','ó','ś','ź','ż','Ą','Ć','Ę','Ł','Ń','Ó','Ś','Ź','Ż');
			$plLangTo   = array('a','c','e','l','n','o','s','z','z','a','c','e','l','n','o','s','z','z');
			$langFrom   = array_merge ($langFrom, $plLangFrom);
			$langTo     = array_merge ($langTo, $plLangTo);
		}
		
		// RUSSIAN
		if ($language == 'ru-RU') {
			$ruLangFrom = array('А', 'а', 'Б', 'б', 'В', 'в', 'Г', 'г', 'Д', 'д', 'Е', 'е', 'Ё', 'ё', 'Ж', 'ж', 'З', 'з', 'И', 'и', 'Й', 'й', 'К', 'к', 'Л', 'л', 'М', 'м', 'Н', 'н', 'О', 'о', 'П', 'п', 'Р', 'р', 'С', 'с', 'Т', 'т', 'У', 'у', 'Ф', 'ф', 'Х', 'х', 'Ц', 'ц', 'Ч', 'ч', 'Ш', 'ш', 'Щ', 'щ', 'Ъ', 'ъ', 'Ы', 'ы', 'Ь', 'ь', 'Э', 'э', 'Ю', 'ю', 'Я', 'я');
			$ruLangTo   = array('A', 'a', 'B', 'b', 'V', 'v', 'G', 'g', 'D', 'd', 'E', 'e', 'Jo', 'jo', 'Zh', 'zh', 'Z', 'z', 'I', 'i', 'J', 'j', 'K', 'k', 'L', 'l', 'M', 'm', 'N', 'n', 'O', 'o', 'P', 'p', 'R', 'r', 'S', 's', 'T', 't', 'U', 'u', 'F', 'f', 'H', 'h', 'C', 'c', 'Ch', 'ch', 'Sh', 'sh', 'Shh', 'shh', '', '', 'Y', 'y', '', '', 'Je', 'je', 'Ju', 'ju', 'Ja', 'ja');
			$langFrom   = array_merge ($langFrom, $ruLangFrom);
			$langTo     = array_merge ($langTo, $ruLangTo);
		}
		
		// SLOVAK
		if ($language == 'sk-SK') {
			$skLangFrom = array('á','ä','č','ď','é','í','ľ','ĺ','ň','ó','ô','ŕ','š','ť','ú','ý','ž','Á','Ä','Č','Ď','É','Í','Ľ','Ĺ','Ň','Ó','Ô','Ŕ','Š','Ť','Ú','Ý','Ž');
			$skLangTo   = array('a','a','c','d','e','i','l','l','n','o','o','r','s','t','u','y','z','a','a','c','d','e','i','l','l','n','o','o','r','s','t','u','y','z');
			$langFrom   = array_merge ($langFrom, $skLangFrom);
			$langTo     = array_merge ($langTo, $skLangTo);
		}
		
		// SLOVENIAN
		if ($language == 'sl-SI') {
			$slLangFrom = array('č','š','ž','Č','Š','Ž');
			$slLangTo   = array('c','s','z','c','s','z');
			$langFrom   = array_merge ($langFrom, $slLangFrom);
			$langTo     = array_merge ($langTo, $slLangTo);
		}
		
		// LITHUANIAN
		if ($language == 'lt-LT') {
			$ltLangFrom = array('ą','č','ę','ė','į','š','ų','ū','ž','Ą','Č','Ę','Ė','Į','Š','Ų','Ū','Ž');
			$ltLangTo   = array('a','c','e','e','i','s','u','u','z','A','C','E','E','I','S','U','U','Z');
			$langFrom   = array_merge ($langFrom, $ltLangFrom);
			$langTo     = array_merge ($langTo, $ltLangTo);
		}
		
		// ICELANDIC
		if ($language == 'is-IS') {
			$isLangFrom = array('þ', 'æ', 'ð', 'ö', 'í', 'ó', 'é', 'á', 'ý', 'ú', 'Þ', 'Æ', 'Ð', 'Ö', 'Í', 'Ó', 'É', 'Á', 'Ý', 'Ú');
			$isLangTo   = array('th','ae','d', 'o', 'i', 'o', 'e', 'a', 'y', 'u', 'Th','Ae','D', 'O', 'I', 'O', 'E', 'A', 'Y', 'U');
			$langFrom   = array_merge ($langFrom, $isLangFrom);
			$langTo     = array_merge ($langTo, $isLangTo);
		}
		
		// TURKISH
		if ($language == 'tr-TR') {
			$tuLangFrom = array('ş','ı','ö','ü','ğ','ç','Ş','İ','Ö','Ü','Ğ','Ç');
			$tuLangTo   = array('s','i','o','u','g','c','S','I','O','U','G','C');
			$langFrom   = array_merge ($langFrom, $tuLangFrom);
			$langTo     = array_merge ($langTo, $tuLangTo);
		}
		
		
		// GERMAN - because of german names used in Czech, Hungarian, Polish or Slovak (because of possible
		// match - e.g. German a => ae, but Slovak a => a ... we can use only one, so we use:
		// a not ae, u not ue, o not oe, ? will be ss
		
		$deLangFrom  = array('ä','ö','ü','ß','Ä','Ö','Ü');
		$deLangTo    = array('a','o','u','ss','a','o','u');
		//$deLangTo  = array('ae','oe','ue','ss','ae','oe','ue');
		
		$langFrom    = array_merge ($langFrom, $deLangFrom);
		$langTo      = array_merge ($langTo, $deLangTo);
		
		$string = StringHelper::str_ireplace($langFrom, $langTo, $string);
		$string = StringHelper::strtolower($string);
		
		return $string;
	}


	/**
	 * Make given string safe, also transliterating it - EITHER - if unicode aliases are not enabled - OR - if force ascii alias for current record type is true 
	 *
	 * @param   string   $string       The string to make safe
	 * @param   string   $language     The language of the string
	 * @param   boolean  $force_ascii  Whether to force transliteration
	 *
	 * @return  string   A safe string, possibly transliterated
	 *
	 * @since   3.2
	 */
	public function stringURLSafe($string, $language = '', $force_ascii)
	{
		if (JFactory::getConfig()->get('unicodeslugs') == 1 && !$force_ascii)
		{
			$output = JFilterOutput::stringURLUnicodeSlug($string);
		}
		else
		{
			if ($language === '*' || $language === '')
			{
				$languageParams = JComponentHelper::getParams('com_languages');
				$language = $languageParams->get('site');
			}

			if ($language !== '*' && !JLanguage::getInstance($language)->getTransliterator())
			{
				// Remove any '-' from the string since they will be used as concatenaters
				$this->alias = str_replace('-', ' ', $this->alias);

				$this->alias = $this->transliterate($this->alias, $language);
			}

			$output = JFilterOutput::stringURLSafe($string, $language);
		}

		return $output;
	}
}