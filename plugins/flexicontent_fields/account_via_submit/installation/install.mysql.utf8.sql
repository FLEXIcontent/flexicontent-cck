
CREATE TABLE IF NOT EXISTS `#__flexicontent_edit_coupons` (
	id INT(11) unsigned NOT NULL auto_increment,
	email VARCHAR(255) NOT NULL,
	timestamp INT NOT NULL,
	token VARCHAR(255) NOT NULL,
	PRIMARY KEY  (`id`)
) ENGINE=MyISAM CHARACTER SET `utf8` COLLATE `utf8_general_ci`;
