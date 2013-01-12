CREATE TABLE IF NOT EXISTS `#__flexicontent_cats_item_relations` (
  `catid` int(11) NOT NULL default '0',
  `itemid` int(11) NOT NULL default '0',
  `ordering` int(11) NOT NULL default '0',
  PRIMARY KEY  (`catid`,`itemid`),
  KEY `catid` (`catid`),
  KEY `itemid` (`itemid`)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS `#__flexicontent_favourites` (
  `id` int(11) NOT NULL auto_increment,
  `itemid` int(11) NOT NULL default '0',
  `userid` int(11) NOT NULL default '0',
  `notify` int(11) NOT NULL default '0',
  PRIMARY KEY  (`id`,`itemid`,`userid`),
  KEY `id` (`id`),
  KEY `itemid` (`itemid`),
  KEY `userid` (`userid`)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS `#__flexicontent_fields` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `field_type` varchar(50) NOT NULL default '',
  `name` varchar(255) NOT NULL default '',
  `label` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL default '',
  `isfilter` tinyint(1) NOT NULL default '0',
  `iscore` tinyint(1) NOT NULL default '0',
  `issearch` tinyint(1) NOT NULL default '1',
  `isadvsearch` tinyint(1) NOT NULL default '0',
  `untranslatable` tinyint(1) NOT NULL default '0',
  `formhidden` tinyint(1) NOT NULL default '0',
  `valueseditable` tinyint(1) NOT NULL default '0',
  `edithelp` tinyint(1) NOT NULL default '2',
  `positions` text NOT NULL,
  `published` tinyint(1) NOT NULL default '0',
  `attribs` text NOT NULL,
  `checked_out` int(11) unsigned NOT NULL default '0',
  `checked_out_time` datetime NOT NULL default '0000-00-00 00:00:00',
  `access` int(11) unsigned NOT NULL default '0',
  `ordering` int(11) NOT NULL default '0',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS `#__flexicontent_fields_item_relations` (
  `field_id` int(11) NOT NULL default '0',
  `item_id` int(11) NOT NULL default '0',
  `valueorder` int(11) NOT NULL default '1',
  `value` mediumtext NOT NULL,
  PRIMARY KEY  (`field_id`,`item_id`,`valueorder`),
  KEY `field_id` (`field_id`),
  KEY `item_id` (`item_id`),
  KEY `valueorder` (`valueorder`)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS `#__flexicontent_fields_type_relations` (
  `field_id` int(11) NOT NULL default '0',
  `type_id` int(11) NOT NULL default '0',
  `ordering` int(11) NOT NULL default '0',
  PRIMARY KEY  (`field_id`,`type_id`),
  KEY `field_id` (`field_id`),
  KEY `type_id` (`type_id`)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS `#__flexicontent_files` (
  `id` int(11) NOT NULL auto_increment,
  `filename` varchar(255) NOT NULL,
  `altname` varchar(255) NOT NULL,
  `description` text NOT NULL default '',
  `url` tinyint(3) unsigned NOT NULL default '0',
  `secure` tinyint(3) unsigned NOT NULL default '1',
  `ext` varchar(10) NOT NULL,
  `published` tinyint(1) NOT NULL default '1',
  `hits` int(11) unsigned NOT NULL default '0',
  `uploaded` datetime NOT NULL default '0000-00-00 00:00:00',
  `uploaded_by` int(11) unsigned NOT NULL default '0',
  `checked_out` int(11) unsigned NOT NULL default '0',
  `checked_out_time` datetime NOT NULL default '0000-00-00 00:00:00',
  `access` int(11) unsigned NOT NULL,
  `attribs` text NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS `#__flexicontent_items_ext` (
  `item_id` int(11) unsigned NOT NULL,
  `type_id` int(11) unsigned NOT NULL,
  `language` varchar(11) NOT NULL default '',
  `lang_parent_id` int(11) unsigned NOT NULL default 0,
  `sub_items` text NOT NULL,
  `sub_categories` text NOT NULL,
  `related_items` text NOT NULL,
  `search_index` mediumtext NOT NULL,
  PRIMARY KEY  (`item_id`),
  FULLTEXT KEY `search_index` (`search_index`)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS `#__flexicontent_items_extravote` (
  `content_id` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `lastip` varchar(50) NOT NULL,
  `rating_sum` int(11) NOT NULL,
  `rating_count` int(11) NOT NULL,
  KEY `extravote_idx` (`content_id`)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS `#__flexicontent_items_versions` (
  `version` int(11) NOT NULL default '0',
  `field_id` int(11) NOT NULL default '0',
  `item_id` int(11) NOT NULL default '0',
  `valueorder` int(11) NOT NULL default '1',
  `value` mediumtext NOT NULL,
  PRIMARY KEY  (`version`,`field_id`,`item_id`,`valueorder`),
  KEY `version` (`version`),
  KEY `field_id` (`field_id`),
  KEY `item_id` (`item_id`),
  FULLTEXT KEY `value` (`value`),
  KEY `valueorder` (`valueorder`)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS `#__flexicontent_tags` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) NOT NULL,
  `alias` varchar(255) NOT NULL,
  `published` tinyint(1) NOT NULL,
  `checked_out` int(11) unsigned NOT NULL default '0',
  `checked_out_time` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS `#__flexicontent_tags_item_relations` (
  `tid` int(11) NOT NULL default '0',
  `itemid` int(11) NOT NULL default '0',
  PRIMARY KEY  (`tid`,`itemid`),
  KEY `tid` (`tid`),
  KEY `itemid` (`itemid`)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS `#__flexicontent_types` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) NOT NULL,
  `alias` varchar(255) NOT NULL,
  `published` tinyint(1) NOT NULL,
  `checked_out` int(11) unsigned NOT NULL default '0',
  `checked_out_time` datetime NOT NULL default '0000-00-00 00:00:00',
  `access` int(11) unsigned NOT NULL default '0',
  `attribs` text NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS `#__flexicontent_versions` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `item_id` int(11) unsigned NOT NULL default '0',
  `version_id` int(11) unsigned NOT NULL default '0',
  `comment` mediumtext NOT NULL,
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `created_by` int(11) unsigned NOT NULL default '0',
  `state` int(3) NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `version2item` (`item_id`,`version_id`)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS `#__flexicontent_templates` (
  `template` varchar(50) NOT NULL default '',
  `layout` varchar(20) NOT NULL default '',
  `position` varchar(100) NOT NULL default '',
  `fields` text NOT NULL,
  PRIMARY KEY  (`template`,`layout`,`position`)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS `#__flexicontent_advsearch_index` (
  `field_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `extratable` varchar(255) NOT NULL,
  `extraid` int(11) NOT NULL,
  `search_index` longtext NOT NULL,
  `value_id` varchar(255) NULL,
  PRIMARY KEY (`field_id`,`item_id`,`extratable`,`extraid`)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS `#__flexicontent_authors_ext` (
  `user_id` int(11) unsigned NOT NULL,
  `author_basicparams` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `author_catparams` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  PRIMARY KEY  (`user_id`)
) ENGINE=MyISAM;

