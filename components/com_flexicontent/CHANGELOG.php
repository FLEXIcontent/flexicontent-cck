/**
 * @version 1.5 stable $Id$
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



FLEXIcontent 1.5.3a stable - build 337 - Changelog

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

+ Add the sh404sef plugin to the trunk


FLEXIcontent 1.5.3 stable - build 333 - Changelog

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


################################################################################################

FLEXIcontent beta 5 changelog 

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


################################################################################################

FLEXIcontent beta 4 changelog 

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