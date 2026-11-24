<?php
/**
 * Local Uppy labels shared by the item editor and the My Files picker.
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\LanguageHelper;

final class FlexicontentUppyLanguage
{
	public static function register($document)
	{
		if ($document->getScriptOptions('flexicontent.uppyLocale')) return;

		$language = Factory::getApplication()->getLanguage();
		$catalog = 'lib_flexicontent_uppy';
		$tag = $language->getTag() ?: 'en-GB';
		$language->load($catalog, JPATH_SITE, 'en-GB');
		// A regional French or Traditional Chinese installation can use the bundled translation.
		$fallback = str_starts_with($tag, 'fr-') ? 'fr-FR' : (preg_match('/^zh-(TW|Hant)/i', $tag) ? 'zh-TW' : null);
		if ($fallback && $fallback !== $tag) $language->load($catalog, __DIR__, $fallback, false, false);
		$language->load($catalog, __DIR__, $tag, false, false);
		$language->load($catalog, JPATH_BASE, $tag, false, false);

		$reference = LanguageHelper::parseIniFile(JPATH_SITE . '/language/en-GB/en-GB.' . $catalog . '.ini');
		$strings = [];
		foreach (array_keys($reference) as $key)
		{
			$name = substr($key, strlen('FLEXI_UPPY_'));
			if (str_ends_with($name, '_ONE')) continue;
			$plural = str_ends_with($name, '_OTHER');
			if ($plural) $name = substr($name, 0, -6);
			$uppyKey = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($name)))));
			if (!$plural)
			{
				$strings[$uppyKey] = $language->_($key);
				continue;
			}
			foreach (['zero', 'one', 'two', 'few', 'many', 'other'] as $category)
			{
				$form = 'FLEXI_UPPY_' . $name . '_' . strtoupper($category);
				$strings[$uppyKey][$category] = $language->_($language->hasKey($form) ? $form : $key);
			}
		}
		$document->addScriptOptions('flexicontent.uppyLocale', ['strings' => $strings]);
	}
}
