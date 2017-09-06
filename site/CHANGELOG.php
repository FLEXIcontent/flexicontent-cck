/**
 * @version 1.5 stable $Id: CHANGELOG.php 1079 2012-01-02 00:18:34Z ggppdk $
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

-----------------------------------------------------
- FLEXIcontent 1.5.6 beta2 - build 922 - Changelog -
-----------------------------------------------------
Legend:
* -> Security Fix
# -> Bug Fix
$ -> Language fix or change
+ -> Addition
^ -> Change
- -> Removed
! -> Note


# Bug fixes
--------------------------------------------------------------------------------------------------
# Fixed the recently added (new) replacement tags for select and selectmultiple, these are like {item->created_by}
# Fixed item Layout for blog template to display fields and also added field positions to it
# Minor template fixes/changes/additions for default and blog templates and for universal module templates.
# Fixed Url creation to select (activate) menu items pointing to the category of items

+ New features
--------------------------------------------------------------------------------------------------
+ Add : Enhanced BLOG template to supports up to 4 columns, also a cosmetix fix the alphaindex seperator
+ Add : FC Universal module updates:
 (a) Added to news/select templates a new MODULE readmore link (and appropriate module parameters)
 (b) Added a new (item) date field parameter for standard/featured items and also a date label on-off paramater
 (c) Added to news template the new (item) date field with appropriate css classes
 (d) Seperated the Global Display parameters (current category & (new) module read more), from the Item List Display parameters
+ Add : to Relateditems field:
 (a) Added 2 Scopes (category and type), ability for subcategories for category scope
 (b) Added more editing options including: Ordering and a Filter mechanism based on filterlist.js
 (c) Cleaned up and reordered the layout of the parameters
+ Add : to Image Field:
 (a) Characterized Title and Description options as used for Tooltips
 (b) Added 2 new options "Show Title" and "Show Description" to display them after image thubmnail
+ Add : to Author Management:
 (a) several filters to backend Author Manager to shape the Author List in all obvious ways
 (b) Added a new menu item "My Items" that displays the items of the currently logged user
+ Add : Templates:  
 (a) Added a new template called 'faq'
 (b) Allowed the use of readonly positions for templates (e.g. blog and faq)


^ Changed features
--------------------------------------------------------------------------------------------------
^ Change : Moved all the css loading code with/without caching in the entry point of the modules (mod_flexicontent.php and mod_flexitagcloud.php) so that it is no longer needed to handle css loading at the template
^ Changed installation script to clear postinstall session variables and all flexicontent caching groups, so that no logout-login and cache clearing is needed after installation

$ Language fix or change
--------------------------------------------------------------------------------------------------
$ Added some missed language files


-----------------------------------------------------
- FLEXIcontent 1.5.6 beta1 - build 905 - Changelog -
-----------------------------------------------------
Legend:
* -> Security Fix
# -> Bug Fix
$ -> Language fix or change
+ -> Addition
^ -> Change
- -> Removed
! -> Note


# Bug fixes
--------------------------------------------------------------------------------------------------
# Fix (issue 134 ): broken javascript validation in IE8 browser when (applying/saving) an item
# Fix : improvements of postinstall process
# Fix : support for php 5.3.5+ during installation (changed from TYPE=MyISAM to be ENGINE=MyISAM)
# Fix : passing bad parameter object to content plugins when triggering them on the category description
# Fix : for image field (issue ...) :
 (a) a bug of the signature of function onDisplayField not to have reference for the second parameter
 (b) field not working properly when configuration unsaved, this can only occur after an upgrade to new Flexicontent version ...
 (c) some other minor bugs of the field
# Fix (issue 148) : the Pagination of version control is not displayed properly in all cases
# Fix (issue 135) : "invalid token" in fields using the filemanager in a modal window
# Fix : bugs in minigallery field :
 (a) wrong path on windows servers causing a message "error messages disabled" when choosing an image to add to the gallery
 (b) adding of javascript and css to the header multiple times
 (c) error image when selecting an image (occured when medias folder was changed in the Global Configuration to anything other than the default value)
# Fix : some css errors and some cosmetic spacing
# Fix (issue 141) : article choosing for some non-flexicontent aware modules, we redirect the com_content article choosing element to the corresponding com_flexicontent item choosing element
# Fix : corrected the JRequest 'view' variable, not restored properly after content plugin triggering.
# Fix (issue 155) about Frontend editing, the reported bug plus one more, were because of 2 non-initialized variables (frontend items view files).
# Fix : Article Page Navigation Field (fcpagenav plugin) to
 (a) display iterate through all items of current category
 (b) it will always stay inside current category
 (c) respect the Global configuration parameter of filtering language (display only current language items)
 (d) an other issue related to SEF links, was fixed by new SEF code
# Fix : some bug about checking query command in sql_mode with select and selectmultiple fields
# Fix : removed warning if a field doesnot exist for an item but it exists in the template, this is normal behaviour as different TYPEs may have different fields but use the same Template
# Fix (issue ...) : category slug for items that have 'unrouted' (hidden) category, (unrouted categories are defined in the flexiadvroute.php SYSTEM PLUGIN)
# Fix (issue ...) : for filters not appearing always in category view ...
# Fix : SEF handling for AJAX voting / favourites, advanced search view, flexicontent (directory) view for with and without SH404_SEF
# Fix : prevent unnecessary inserting of records with empty value in table __flexicontent_fields_item_relations
# Fix : bugs of file field :
 (a) not linking to flexicontent component properly in all cases
 (b) compatibility with joomfish translation of the file label
# Fix : bug about parent category in 'items' view
# Fix : item state strings, a lot of user confusion and problems, because of one language string wrong in Global configuration.
# Fix (issue ...) : custom date FORMAT ignored, in date field
# Fix (issue 200) : tracked down long lasting publish_up bug, timezone offset was applied twice to the publish_up date, resulting in publish date going to the future for negative GMT zones
# Fix (issue 138) : search box is not working unless "Use Filters" is enabled
# Fix (issue 173) : Any item in unpublished state is always showed in frontend, if it had never been modified
# Fix (issue ...) : bug in archive manager, redirect to items manager on any user action
# Fix (issue ...) : JSite class not found bug , occuring in the backend
# Fix (issue 179) : an error occurs in frontend when editing item not assigned to a menu item
# Fix (issue 180) : bug that the current category is set as item's category-slug but current category is not in the item's categories
# Fix : custom date format to work for My-Favourites
# Fix : mod_flexicontent module to respect "read more" parameter for non-featured items
# Fix : Filemanager : Allow upload to server of files with invalid filenames (having local language characters) by changing their name to be date("Y-m-d-H-i-s")
# Fix : a bug when install current versions of items


+ New features
--------------------------------------------------------------------------------------------------
+ Add : NEW!!! Advanced Search Feature: (a) a module, (b) a plugin, (c) a view
 Features include:
 -- Very fast results because of precalculated SEARCH INDEX
 -- Allow to search various flexicontent fields, NOT just the title and description
 -- Allow to select which flexicontent fields are displayed in the results, NOT just the title and description
 -- Allow to form more complex AND-OR searches that standard joomla search
 -- A lot of other customizations options to shape the search to fit your needs

+ Add : NEW!!! a new ultra powerful Alpha Index
 1. support for default AlphaIndex per Language in language file
 2. supports character 'entities' (ranges, aggregations, aliases), a hardcoded alias is # for  0-9 numbers
 3. support utf8 characters in the MySQL query for all in (2) (above)
 4. allow custom per category AlphaIndex ( (a)Hide, (b)Show & use language default, (c)Show & use custom characters )
 5. option to skip (no display) unused characters
 6. new category option to allow a custom separator to be used, instead of boxes around characters
 7. option to skip disabled characters / ranges / aliases
 8. wrote clear description For ALPHA-INDEX usage
 9. enhanced the alphaindex to work even with joomla cache enabled
 10. Some css fixes and classes added, like highlighting for current alpha index character (entity)

+ Add : NEW!!! an Author Manager (backend) and an Author Category Layout.
	It is now possible to create author category LAYOUTs by creating a new menu item link a Category THAT uses the author layout
	When a category is display in author layout the category params are taken from the author configuration.
	Additionally for the presentation of the author, a description item can defined per author, that is displayed at the top of the author category layout.

+ Add : NEW!!! to Flexicontent Universal (items) module:
	1. 2 scopes : (a) current item scope, (b) current language scope
  2. An mechanism for skipping items that have specific fields empty
  3. Made module configuration easy by (a) adding TAB sliders, (b) reorder parameters (c) rewroting some parameter descriptions
  4. Added option for all item's categories in category scope
  5. Added option to add a Title to each ordering group of items (module usually display on group e.g. recently added, e.g. recently modified etc)
  6. Wrote clear descriptions for ordering group (e.g. if you display 2 groups "most popular" and "Recently Added" at once)
  7. Added current category information: title, description, and (scaled/cropped) image
  8. a language file for the module

+ Add : NEW!!! a --Related Items-- Field:
 used to display a list of items related to the item in some way, note: it allows multiple instances

+ Add : NEW!!! field 'textselect', this field uses input text field when input data but displays a drop down( select) field at search view

+ Add : a new task 'doPlgAct' to controller.php both frontend and backend to calling plugin(field) function by URL.
  This is important for developers of flexicontent fields (plugins) as it allow them to call plugins function by URL !!!
 
+ Add : (BIG PERFORMANCE IMPOVEMENT (particularly for category view):
  new option for description field (maintext) of items, not to trigger (for it) the content plugins in category view

+ Add : support for joomfish v2.2.x
+ Add : to select & selectmultiple fields ability for replacements of item data, e.g. replacements like: {item->created_by} {item->catid} (first is item's owner and second is item's main category)
+ Add : altering the JRequest 'view' variable to 'article' during content plugin triggering for all (core and custom) flexicontent fields for better compatibility with some content plugins
+ Add : altering the JRequest 'option' variable to 'com_content' during content plugin triggering for all (core and custom) flexicontent fields for better compatibility with some content plugins
+ Add : options & code not to display empty categories or subcategories in the flexicontent ('directory') view
+ Add : option in the BACKEND items view to --bind-- joomla articles to --any-- Flexicontent item Type
+ Add : implemented the "required" configuration attribute for Flexicontent "File" Field
+ Add : allowed multiple minigallery fields to work properly
+ Add : For Templates:
 (a) added a 'renderonly' position, this will allow fields placed there to be rendered (created) but not displayed
 (b) added Method getFieldDisplay() to render a field on demand and return the display
+ Add : implemented option for category view: To show subcategories items from any sublevel not just the 1st sublevel. Category option was change to "No, 1st sublevel, All sublevel"
+ Add : "Display Resizing Controls" option that was missing in the XML file of the article toolbar field
+ Add : image field enhancements: 
 (a) allow linking to a URL that opens in (a) Same Win/Tab (b) New Win/Tab (c) Modal pop-up window
 (b) implemented the 'Required' behaviour (except when deleting existing image)
 (c) can now choose which size is displayed in item and in category
 (d) new display variables: $field->{'display_small'}, $field->{'display_medium'}, $field->{'display_big'}
 (e) new option to allow to only drop image from image field without deleting it, also added 3 options to change image field behaviour
+ Add : a new postinstall task to create menu 2 menu links automatically in a hidden Menu (not displayed in a module), so that it is not needed to be done by user. This is useful for SEF URLs
+ Add : a new Global configuration option for a default menu item id to be added to SEF urls in the cases a more appropriate cannot be found
+ Add : a new 'Template' column to categories and items manager (LISTINGS)
+ Add : a new 'Field Type' column to fields manager (LISTINGS)
+ Add : allow to create urls to 'flexicontent' view (directory-like view) that have the variables rootcat,columns_count,etc IN their URL
+ Add : Global (performance) Option to create fields html display (a) only when really used, (b) always in items view, (c) always in any case
+ Add : WORKFLOW improvements: 
 (a) added a fake state in items view to allow the user to LIST items that have VERSIONS that require REVIEWAL-APPROVAL !!!
 (b) a new slider for items with Versions-to-be-Reviews and added links to the items view ... to view all items in each state
 (c) wrote clear names for states and more clear name for quick access sliders in the Flexicontent dashboard (home)
+ Add : new option to favourites field to list usernames or fullnames favouring the item
+ Add : new option (in Global Config) to prevent the default menu item from showing in the pathway


- Removed features
--------------------------------------------------------------------------------------------------
- Remove : (BIG PERFORMANCE IMPOVEMENT (particularly for category view):
  delete code of unnecessary triggering of all content plugins on the fulltext of all displayed category items (same of item views),
  code was forgotten there when content plugin triggering was moved to the helper fileflexicontent.fields.php


^ Changed features
--------------------------------------------------------------------------------------------------
^ Change : from jquery v1.4.2 to jquery v1.6.3
^ Change : (NOTICABLE PERFORMANCE IMPOVEMENT (particularly for category view):
  new efficient (and fast) way to call Flexicontent plugin functions, avoiding the inapproproate joomla way that calls ALL plugins (it is appropriate for other use)
^ Change : (BIG PERFORMANCE IMPOVEMENT (particularly for category view):
  implementation of creating Flexicontent Fields displayed html on DEMAND (only if they are actually used in a template position or in a module)
^ Change : REWROTE!!! route.php (produces and recognizes SEF URLs) so that:
  (a) to fix some bugs, where not all flexicontent urls were always properly routed.
  (b) Make code easy to read, and easy to extend in the future !!!
  (c) Maintain compatibility with existing bookmarked SEF URLs and google indexed content (please test !!!)
^ Change : improved backend models not to call listing query twice (second was for pagination), now we use SQL_CALC_FOUND_ROWS to avoid second query.
  Performance improved about 20% in backend listings (noticable only on long items listings)
^ Change : altered (and cleaned) POSTINSTALL code not to check postinstall tasks, if they are not to be displayed, (this depends on saved SESSION variables)
^ Change : Field manager listing, changed name of ORDERING column to indicate clearly the contents of the column (Global ordering OR Item Type ordering)


! Notes
--------------------------------------------------------------------------------------------------
! Notes : made flexicontent submenu code to be generated by a function, thus removed repeated code, that is difficult to update / prone to errors


$ Language
--------------------------------------------------------------------------------------------------
$ Language (issue 133) : added language missing strings 



-----------------------------------------------------
- FLEXIcontent 1.5.5 stable - build 608 - Changelog -
-----------------------------------------------------
Legend:
* -> Security Fix
# -> Bug Fix
$ -> Language fix or change
+ -> Addition
^ -> Change
- -> Removed
! -> Note

# Bug fixes
--------------------------------------------------------------------------------------------------
# Fix issue : Bug plugin minigallery with fileselement.
# Fix issue : Bug query error about i.version , change to be iv.version no c.version
# Fix issue : Bug in components/com_flexicontent/views/category/view.html.php on line 205 around foreach ($item->cats as $cat)
# Fix issue : Bug when displaying a view on frontend and no lang var is set in the query string (issue 121) Thanks to Adrien (Acymailing) for the fix ;)))
# Fix issue : Bad alt text in toolbar plugin for social bookmarking (issue115)
# Fix issue : Editor buttons were not active anymore
# Fix issue : Bug about including the template.(change $params to be $tparams)
# Fix issue : bug when adding a file in frontend with sh404 (issue 125)
# Fix issue : Add handlers to validate.js for radio and checkbox elements (issue 124)

+ New features
--------------------------------------------------------------------------------------------------
+ Allow forms to use template.
+ Add the flexicontent module
+ Add : force a specific Itemid when routing a tag
+ Add the flexicontent tagcloud module
+ Add new filter types : created_by, modified_by, document type, state, tags
+ Add : can now order a category view on a custom field value
+ Add : Preview feature with auto-login option
+ Add : Notification plugin
+ Add : View switch before content plugin are triggered to enhance compatibility

- Removed features
--------------------------------------------------------------------------------------------------
- Cleanup : Remove the deprecated method setCacheThumbPerms() - thanks to Brian Teeman for reporting ;-)))



-----------------------------------------------------
- FLEXIcontent 1.5.4 stable - build 558 - Changelog -
-----------------------------------------------------
Legend:
* -> Security Fix
# -> Bug Fix
$ -> Language fix or change
+ -> Addition
^ -> Change
- -> Removed
! -> Note


* Security fix
--------------------------------------------------------------------------------------------------
* Disable the phpThumb cache directory check and avoid forcing chmod 777 on phpThumb cache directory
* Security Fix for the phpThumb library

+ New features
--------------------------------------------------------------------------------------------------
+ Add the pagebreak feature in the default and blog templates
+ Add a default value to the field elements radio, select and select multiple
+ Add the page navigation field plugin
+ Add the load module field plugin
+ Add a javascript validation for required fields
+ Add a canonical tag to avoid duplicate contents with multi-mapping
+ Add the option to allow the user to choose some of the pre-selected categories when submiting items from frontend (issue99)
+ Add the new article toolbar plugin to the trunk
+ Add the ability to sort category by the last modified items (for Mihàly)
+ Add display label with filter option in category views
+ Add the feature to route categories to a specific item type (requires the advanced route system plugin)
+ Add the advanced routing features to the category view. You can now exclude some categories from routing ;)
+ Add the pageid creation feature to sh404 plugin
+ Add the feature to handle the page break (with overpagebreak by Joomlaworks)
+ Add an argument($Itemid) to function FlexicontentHelperRoute::getItemRoute()
+ Add the advanced routing plugin to the 1.5 trunk
+ Add some parameters (id+cid) to the return string (very usefull for websites selling content)
+ Add the ability to display label or value in frontend for select or select multiple fields
+ Add some missing string in the fr-FR frontend language file (thanks to Annick)

- Removed features
--------------------------------------------------------------------------------------------------
- Advanced search column in the fields view because feature is not implemented yet (issue67)
- Remove the category FLEXIaccess check for the item view (more logical) 
- Remove the empty tooltip from the item edit form if no description exists for the field

- Bug fixes
--------------------------------------------------------------------------------------------------
# Joomfish bug when no lang variable was found in query string, the category view was not filtering the items. (eg Homepage)
# Joomfish bug when editing an item in frontend. The language saved was always the site default language (see http://www.flexicontent.org/phpbb/viewtopic.php?f=7&t=2945)
# Fix a the category field to avoid displaying categories that are excluded from routing + some cleanup
# Comment duplicate select category items query.
# Fix a small parameter bug in menu items
# Clean the template cache when duplicating or deleting template
# Fix upgrade bug in the installer
# Fix bug on default template related to advanced routing feature
# Fix some bugs on the filters for the items view (backend)
# Fix bug about voting css loaded multiple times on category views
# Fix bug about fields values appended to search_index even if they shouldn't be searchable (issue67)
# Fix bug about content plugins not triggered in blog template (issue76) # Fix bug about the readmore button that was loaded even there was no content in fulltext
# Fix bug about deleting tags in IE (issue21 & issue90)
# Fix bug about AlphaIndex with publish_up date (issue80)
# Fix bug about comments form displayed in the print item view (issue89)
# Fix bug about the start publishing date when editing an item in frontend (issue74)
# Fix bug in voting plugin CSS (issue84)
# Fix empty legends in image fields (issue91)
# Fix joomfish bug (issue68)
# Fix html tags issue (issue86)
# Fix the IE double posting (issue77)
# Fix default value as int for the getUserStateFromRequest method in the filemanager views (issue66)
# Fix Alpha index was filtering all categories (issue64)
# Fix a small notice that appears when disabling joomfish
# Fix bug in the frontend edit: quotes were replace by htmlentities when editing
# Fix bug in the frontend edit: a readmore tag was inserted even there was nothing in the fulltext DB field
# Fix the bugs about extra parameber setting about template in type,category,and item edit layout. + add language field to item edit layout.
# Fix javascript error, cannot click to do postinstall
# Fix the drag and drop feature (for mootools 1.2)
# Fix bug in parameter inheritance
# Fixed hard-coded database prefix. Thanks to tembargo for bug report here: http://www.flexicontent.org/forum/index.php?f=29&t=2467&rb_v=viewtopic
# Fix bug in sh404 plugin (issue 51)
# Fix bug param show_intro doesn't function globaly
# Fix bug in minigallery for multiple item used this field.
# Fix bug about minigallery and fileselement
# Fix bug about path to file of minigallery plugin.
# Fix bug about phpThumb(cannot display images)

^ Modified features
--------------------------------------------------------------------------------------------------
^ Improvement on the title fulltext filter for the frontend category view (issue 75)
^ Move the add to pathway after plugin events.
^ Put the getTemplates method in cache for performance
^ Small modifications to the buttons to build a better printing view
^ Modification of the Include Subcategories filter from checkbox to radio options. Avoid loosing the choice when changing from view (issue72)
^ The update version check is visible by default
^ Add a default value to the alt attribute of the image image field plugin (60 first characters of the item title)
^ Modify the file field and the fieldelement view to disable the filter on current item by default and allow to strike the already added files.
^ Sort the item filter by title ASC on the fileselement view

------------------------------------------------------
- FLEXIcontent 1.5.3c stable - build 354 - Changelog -
------------------------------------------------------
Legend:
* -> Security Fix
# -> Bug Fix
$ -> Language fix or change
+ -> Addition
^ -> Change
- -> Removed
! -> Note

 	
# Bug fix: the created_by was reset to the current editor (http://www.flexicontent.org/developers-blog/flexicontent-153-stable-release-frontend-editing-inside.html#comment-438)
# Bug fix: the created date was modified by the time offset each time the item was edited and the versioning was disabled [issue 47]

+ Add compatibility with the PrintMe component from dioscouri http://bit.ly/dcDacR


------------------------------------------------------
- FLEXIcontent 1.5.3b stable - build 350 - Changelog -
------------------------------------------------------
Legend:
* -> Security Fix
# -> Bug Fix
$ -> Language fix or change
+ -> Addition
^ -> Change
- -> Removed
! -> Note

 	
# Bug fix: JS error when submitting an item which didn't use the maintext field [issue 50]
# Bug fix: There was a problem with submitting new contents with sh404sef activated
# Bug fix: The tagelement view was not working properly http://www.flexicontent.org/forum/index.php?f=29&t=1733&start=0&rb_v=viewtopic

+ Add an update check in control panel / see => http://code.google.com/p/flexicontent/issues/detail?id=45


------------------------------------------------------
- FLEXIcontent 1.5.3a stable - build 345 - Changelog -
------------------------------------------------------
Legend:
* -> Security Fix
# -> Bug Fix
$ -> Language fix or change
+ -> Addition
^ -> Change
- -> Removed
! -> Note

# Bug fix: The created date was set to the time the content was edited [issue 42]
# Bug fix: The fulltext bloc disapeared when editing the current version of an article in frontend [issue 46]
# Bug fix: Handle the default values properly for every field type [issue 48]
# Bug fix: Type parameters were not working properly when submitting a new item [issue 49]

+ Add the sh404sef plugin to the trunk

$ Add missing string for the weblink field


-----------------------------------------------------
- FLEXIcontent 1.5.3 stable - build 333 - Changelog -
-----------------------------------------------------
Legend:
* -> Security Fix
# -> Bug Fix
$ -> Language fix or change
+ -> Addition
^ -> Change
- -> Removed
! -> Note

+ New features
--------------------------------------------------------------------------------------------------
+ Full frontend submission with custom fields
+ Full frontend edition with custom fields & FLEXIaccess permissions
+ Display subcategory items in a parent category page
+ New minigallery plugin
+ Add show_intro parameter overriding: global -> type -> item
+ Add autopublishing feature for frontend submission form.
+ Add events to the content plugins
+ Add parameter to the state field to allow displaying icon instead of text (issue 12) http://code.google.com/p/flexicontent/issues/detail?id=12
+ Add metadata.xml file to allow hidding filemanager and fileselement views from menu manager
+ Append values to the core fields to allow more flexibility in display
+ Add compatibility of the Alphabetical Index with UTF8
+ Add JCE MediaBox for popup type in field image
+ Add a type filter list for the itemelement view and auto adjust the modal window (menu manager)
+ Add a configuration parameter to define extra css properties on the submit/edit form
+ Add a refresh confirmation message when all items are bound to their extended data


^ Modifications
--------------------------------------------------------------------------------------------------
^ Manage the FLEXI constants globaly through the system plugin to be sure they are defined everywhere.
^ Improvements on the file plugin (when a file is unpublished)
^ Improve fileselement and filemanager to add an items filters
^ Improve the router
^ Improve post installation process in terms of ergonomy and performances
^ Modification on the image field to allow frontend submit/edit and to disable the tooltip
^ Improved items view for FLEXIaccess users to allow users to display all items they can edit only and the category filter accordingly
^ Improve code from the getAlphaindex() method (issue 33) http://code.google.com/p/flexicontent/issues/detail?id=33
^ Use the autocomplete for the tags on frontend instead of the checkboxes
^ Move the inculde subcategories items parameter from the menus to the categories
^ Set a specific limit parameter scope for the items view in backend


# Bug fixes
--------------------------------------------------------------------------------------------------
# fix bug on category menu filter and add the state filter (http://www.flexicontent.org/forum/index.php?f=21&amp;t=1196&amp;rb_v=viewtopic)
# fix bug extended data not saved (issue 13)
# fix bug with double quotes striped from the text fields (http://www.flexicontent.org/forum/index.php?f=29&amp;t=1113&amp;start=0&amp;rb_v=viewtopic)
# fix bug about create link if the article are not in flexisection, use joomla article url format.
# fix bug Hits default field suffix text
# fix bug about category layout(field ordering was not correct).
# fix bug on the search plugin and improve compatibility with joomfish allowing users to display only the results in the active language
# fix bug missing coma in the $query of the search plugin
# Fix a bug when deleting and trying to edit an item (issue 26) http://code.google.com/p/flexicontent/issues/detail?id=26
# Bug on category filters when cache is activated issue29 (http://code.google.com/p/flexicontent/issues/detail?id=29)
# Bug on the search plugin with publish up and down (issue 31) http://code.google.com/p/flexicontent/issues/detail?id=31
# Bug on the radio button field when no value is set in the configuration
# Bug the fields where not loaded properly when the versioning was disabled
# Bug administrator group edit permissions where not properly filtered by FLEXIaccess
# Bug avoid versioning the hits field, fix issue 18
# Bug with opentag and closetag displayed even if there's no value
# The types list was not alphabebitically ordered in the item view in backend
# Fix a bug on the copy/move feature (issue 39) http://code.google.com/p/flexicontent/issues/detail?id=39
# Fix a bug missing controllers folder in the manifest.xml


$ Language
--------------------------------------------------------------------------------------------------
$ Add underline to language string See: http://www.flexicontent.org/forum/index.php?f=39&amp;t=1321&amp;rb_v=viewtopic#p6212
$ EN spelling and grammar fixes. See: http://www.flexicontent.org/forum/index.php?f=39&amp;t=427&amp;rb_v=viewtopic
$ EN grammar fix. See: http://www.flexicontent.org/forum/index.php?f=39&amp;t=509&amp;rb_v=viewtopic
$ Added 3 missing language strings to en-GB. 
$ Removed some duplicate strings from FR and EN admin language files.
$ added 2 missing language strings to fr-FR. See r216.


---------------------------------
- FLEXIcontent beta 5 changelog -
---------------------------------

Legend:
* -> Security Fix
# -> Bug Fix
$ -> Language fix or change
+ -> Addition
^ -> Change
- -> Removed
! -> Note


# Fix the uninstaller that uninstalls the editor image button instead of the image field plugin
# Fix the ordering column that looses focus when you filter by type
+ Gives an ordering by default to all new created fields
# Fix the bugs in versioning and make the process more efficient allowing to switch back to current version
+ Add the confirmation before restoring
# Fix recalculate pagination when changing the limit in every backend view
# Fix missing pagination in tags and favourites views
# Replace TINYINT format of the ordering field by INT to avoid being blocked when there more than 127 articles in a category
# Fixing publish_up / publish_down missing check in category and items views
+ Add the category tree in the system plugin with its specific cache layer
+ Add templates and template backend views
+ Add the duplicate template feature
+ Add the drag and drop field positioning feature for easier templating and multiordering
+ Add the advanced filtering UI for items view in backend
# Fix the email field not storing the value when it doesn't allow multiple values
+ Add external fields class to be able to call fields from everywhere and its specific cache layer
$ Add languages missing strings
+ Add css override in directory, tags and favourites views
# Fix bug in filter search box
+ Name the filter objects to allow to call them and label them individually
+ Print option for the category page (usefull for product catalogs)
+ Add confirmation alert before deleting items and templates directories
# Fix the one col/two cols bug in default item template


---------------------------------
- FLEXIcontent beta 4 changelog -
---------------------------------
Legend:
* -> Security Fix
# -> Bug Fix
$ -> Language fix or change
+ -> Addition
^ -> Change
- -> Removed
! -> Note

# Fixes on the installer post script
# Fix the description field that isn't displayed in category view
# Image field inverted slashes on windows servers with JPath::clean
# Weblink field Add title attribute to links
# Image field Chmod 777 on the created directories to allow phpTumb to write in object mode when FTP layer is activated 
# Fix the not triggered content plugins
# Fix the active Itemid detection route.php
# Correct JRoute in Jcomments plugin
# Correct compatibility with Jcomments 2.1.1
# Error on category view when it contains sub-items 
# Added FlexicontentHelperRoute to all views
# Fix the medium size popups in category view
# Fix the remaining <hr> system tag from the item view when using the readmore function
# Fix the saving date issue in the backend item view. It was addind two hours at each save.
+ Multinlingual Joomfish support
+ Copy/Move function for the items
+ Select/Select multiple list generated by SQL queries
+ Content plugins triggered by each field
+ Versions table class to store the version list
^ Installer for beta3 -> beta4 update
^ Beginnig refactor version list storing procedure
+ Category views filtered by active language
+ Display the description field by default on a new install
# correct the search index and language file in the post install procedure
# correct the fields not added during the copy $db->Quote()
+ Automatic check to verify if at least one menu item is created
+ Choose the star and its dimension in the vote field
+ Joomfish content elements auto-install procedure
^ Reuce spacers in category tree to reduce the space waste
+ Params filter category for multilingual
+ Params filter tag view for multilingual
+ Parameters to the system plugin
$ Missing french translations strings (still some missing)
# Solve the bug of the filter order in every backend view!!! :-)
+ Hide Joomfish parameters from menus
+ Add the missing parameters from standard Joomla articles Key reference (seems to be used by some plugins) and Alternative readmore
^ The items/categories count in the category view and per language
# The categories count in the directory view (counts the unpublished items too and is not filtered by active language)
# Categories above the directory root are not added to the pathway anymore
+ The batch upload for the filemanager with powerfull options
+ The Joomla! com_content ajax import procedure with logs
+ Refactoring of the extended data binding procedure
^ Desactivate the state toggler in the backend items view when $limit is > 30 items (for performance issue, waiting for a better solution)
^ Move category menu parameters to categories
- The image template which is not complete
+ Add an heritance option which allows to copy parameters from another category
+ Multiple category parameters copy
+ Types copy
+ Tag list import
$ Missing JText for the item restore function
^ The version table methods to reduce the queries number
# Ability to remove the root category when creating a new directory menu item
- Remove incomplete frontend submission form to avoid errors
# correct pagelimit bug in category view
+ New simple method to load field positions
- Remove the scope in category views (not usefull anymore)
# router.php that doesn't take care of the existing menu objects and produces stupid SEF urls
# download and goto task from the frontend controller are not supported by the router.php
# Blog and table category views. The category title could not be displayed if the description was hidden
# Category table broken if not every items had the the same fields
+ Experimental parameter to enhance content plugins compatibility and reduce the load
# modification of the route.php to meet multi-mapping requirements
+ target _blank on weblinks field
# filter scope in category views
# odd and even blocks in blog templates
+ backend_hidden field parameter (for later use as connector)
# modification of the route.php to meet multi-mapping requirements
+ target _blank on weblinks field
# filter scope in category views
# odd and even blocks in blog templates
+ backend_hidden field parameter (for later use as connector)
^ Changelog
+ Add the raw.view for ajax fields
^ fix css in the post-installer
# Notice in the positions loop when field->display isn't set
# Save ordering bug when ordering column is disabled
+ Allow content plugins to be triggered on category views as well
# Bug for complex fields in feed views
# Set sql mode on 0 by default for select fields to avoid problems on updating beta 3
# en-GB parameter
# function goto() was not compatible with php 5.3
# allow authorised users to see unpublished contents
# frontend category filter fix the too large scope bug
# directory creation in batch add mode
# left a dump in the code
# add height parameter to textarea field, add image button too
# fix image not displaying on windows servers
# add width 100% for full size editor display
# correct post-install icons not displayed on IE
# manage hide introtext parameter
# add editor height parameter in the type view
# fix pass field by reference bug for php 5.3
# fix version 0 bug in versioning
# disable version table when use_versioning is disabled
# PHP5.3 fix in blog template with new params
$ French language file (admin)
