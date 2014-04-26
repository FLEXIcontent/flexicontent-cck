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
  `isadvfilter` tinyint(1) NOT NULL default '0',
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
  KEY `valueorder` (`valueorder`),
  KEY `value` (`value`(32))
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
  `language` char(7) NOT NULL DEFAULT '*',
  `hits` int(11) unsigned NOT NULL default '0',
  `uploaded` datetime NOT NULL default '0000-00-00 00:00:00',
  `uploaded_by` int(11) unsigned NOT NULL default '0',
  `checked_out` int(11) unsigned NOT NULL default '0',
  `checked_out_time` datetime NOT NULL default '0000-00-00 00:00:00',
  `access` int(11) unsigned NOT NULL default '0',
  `attribs` text NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS `#__flexicontent_items_ext` (
  `item_id` int(11) unsigned NOT NULL,
  `type_id` int(11) unsigned NOT NULL,
  `language` varchar(11) NOT NULL default '*',
  `cnt_state` int(11) NOT NULL,
  `cnt_access` int(11) NOT NULL,
  `cnt_publish_up` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `cnt_publish_down` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `cnt_created_by` INT NOT NULL DEFAULT '0',
  `lang_parent_id` int(11) unsigned NOT NULL default 0,
  `sub_items` text NOT NULL,
  `sub_categories` text NOT NULL,
  `related_items` text NOT NULL,
  `search_index` mediumtext NOT NULL,
  PRIMARY KEY  (`item_id`),
  FULLTEXT KEY `search_index` (`search_index`),
  KEY `lang_parent_id` (`lang_parent_id`),
  KEY `type_id` (`type_id`)
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
  `itemscreatable` SMALLINT(8) NOT NULL default '0',
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
  `sid` int(11) NOT NULL auto_increment,
  `field_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `extraid` int(11) NOT NULL,
  `search_index` longtext NOT NULL,
  `value_id` varchar(255) NULL,
  PRIMARY KEY (`field_id`,`item_id`,`extraid`),
  KEY `sid` (`sid`),
  KEY `field_id` (`field_id`),
  KEY `item_id` (`item_id`),
  FULLTEXT `search_index` (`search_index`),
  KEY `value_id` (`value_id`)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS `#__flexicontent_authors_ext` (
  `user_id` int(11) unsigned NOT NULL,
  `author_basicparams` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  `author_catparams` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL,
  PRIMARY KEY  (`user_id`)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS `#__flexicontent_download_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  `hits` int(11) NOT NULL,
  `last_hit_on` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `file_id` (`file_id`)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS `#__flexicontent_download_coupons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `file_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `hits` int(11) NOT NULL,
  `hits_limit` int(11) NOT NULL,
  `expire_on` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `file_id` (`file_id`),
  KEY `token` (`token`),
  KEY `expire_on` (`expire_on`)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS `#__flexicontent_items_tmp` (
 `id` int(10) unsigned NOT NULL,
 `title` varchar(255) NOT NULL,
 `state` tinyint(3) NOT NULL DEFAULT '0',
 `sectionid` int(10) unsigned NOT NULL DEFAULT '0',
 `catid` int(10) unsigned NOT NULL DEFAULT '0',
 `created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 `created_by` int(10) unsigned NOT NULL DEFAULT '0',
 `modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 `modified_by` int(10) unsigned NOT NULL DEFAULT '0',
 `publish_up` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 `publish_down` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 `version` int(10) unsigned NOT NULL DEFAULT '1',
 `ordering` int(11) NOT NULL DEFAULT '0',
 `access` int(10) unsigned NOT NULL DEFAULT '0',
 `hits` int(10) unsigned NOT NULL DEFAULT '0',
 `language` char(7) NOT NULL,
 `type_id` int(11) NOT NULL DEFAULT '0',
 PRIMARY KEY (`id`),
 KEY `state` (`state`),
 KEY `catid` (`catid`),
 KEY `created_by` (`created_by`),
 KEY `access` (`access`),
 KEY `language` (`language`),
 KEY `type_id` (`type_id`)
) ENGINE=MyISAM;
