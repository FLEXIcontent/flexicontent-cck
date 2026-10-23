# FLEXIcontent data source for JSitemap (com_jmap)

This is **not** a Joomla extension package: it is a "file plugin" data source of JSitemap, so it is not installed by the FLEXIcontent installer.

Install (only if JSitemap / `com_jmap` is installed):

1. Copy this folder to `administrator/components/com_jmap/plugins/flexicontent/`
2. In JSitemap: *Sitemap data sources* -> *New* -> *Plugin* -> select **FLEXIcontent**
3. Optionally set which categories to include or exclude (applies to the item's **main** category)

Notes:

- Each item is listed once, with the route of its main category. Items are not repeated for their secondary categories.
- Original author: Lyquix (2016), GPLv2.
