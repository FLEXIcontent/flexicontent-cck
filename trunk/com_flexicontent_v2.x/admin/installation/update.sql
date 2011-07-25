ALTER TABLE `#__flexicontent_items_ext` ADD `language` VARCHAR( 11 ) NOT NULL DEFAULT '' AFTER `type_id` ;

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
) ENGINE=MyISAM CHARACTER SET `utf8` COLLATE `utf8_general_ci`;
