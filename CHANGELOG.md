# FLEXIcontent Plus Changelog

## v6.1.0 — 15 April 2026

### 🎉 Major: Joomla 5.x + 6.1 "Nyota" Compatibility

**Joomla compatibility:**
- Joomla 4.4.x (PHP 7.4–8.1) — backward compatible
- Joomla 5.0–5.4 (PHP 8.1–8.2) — fully supported  
- Joomla 6.0 (PHP 8.3) — fully supported
- Joomla 6.1 "Nyota" (PHP 8.3–8.4) — fully supported

**PHP 8.1+ fixes (156 files):**
- `each()` → `foreach` (PHP 8.0 removed)
- `FILTER_SANITIZE_STRING` → `FILTER_SANITIZE_SPECIAL_CHARS`
- ArrayAccess/Iterator return types added
- `JRequest` → `Factory::getApplication()->input`
- `jimport()` 355 calls → PSR-4 `use` statements

**Joomla 5/6 API migration (93 files):**
- `JText/JHtml/JFactory/JUri/JRoute` → namespaced classes
- `JError` → `enqueueMessage()` / `RuntimeException`
- `addScript()/addStyleSheet()` → WebAsset Manager

**PHP 8.2+ / Dynamic properties (96 files):**
- `#[AllowDynamicProperties]` added to all affected classes
- 605 class properties declared explicitly

**Manifest:**
- php_minimum: 8.1
- MySQL 8.0.13+ / MariaDB 10.4+ declared

---

## v4.2.1 — 15 July 2023
*(Previous release — see GitHub releases)*
