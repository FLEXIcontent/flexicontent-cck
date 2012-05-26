INSERT INTO `#__flexicontent_fields` (`id`,`field_type`,`name`,`label`,`description`,`isfilter`,`iscore`,`issearch`,`isadvsearch`,`untranslatable`,`positions`,`published`,`attribs`,`checked_out`,`checked_out_time`,`access`,`ordering`)
VALUES
	(1,'maintext','text','Description','The main description text (introtext/fulltext)',0,1,1,0,0,'description.items.default',1,'display_label=0\ntrigger_onprepare_content=0',0,'0000-00-00 00:00:00',0,2),
	(2,'created','created','Created','Creation date',0,1,1,0,0,'top.items.default\nabove-description-line1-nolabel.category.blog',1,'display_label=1\ndate_format=DATE_FORMAT_LC1\ncustom_date=\npretext=\nposttext=',0,'0000-00-00 00:00:00',0,3),
	(3,'createdby','created_by','Created by','Item author',0,1,1,0,0,'top.items.default\nabove-description-line1-nolabel.category.blog',1,'display_label=1\npretext=\nposttext=',0,'0000-00-00 00:00:00',0,4),
	(4,'modified','modified','Last modified','Date of the last modification',0,1,1,0,0,'top.items.default',1,'display_label=1\ndate_format=DATE_FORMAT_LC1\ncustom_date=\npretext=\nposttext=',0,'0000-00-00 00:00:00',0,5),
	(5,'modifiedby','modified_by','Revised by','Name of the user which last edited the item',0,1,1,0,0,'top.items.default',1,'display_label=1\npretext=\nposttext=',0,'0000-00-00 00:00:00',0,6),
	(6,'title','title','Title','The item title',0,1,1,0,0,'',1,'display_label=1',0,'0000-00-00 00:00:00',0,1),
	(7,'hits','hits','Hits','Number of hits',0,1,1,0,0,'',1,'display_label=1\npretext=\nposttext=views',0,'0000-00-00 00:00:00',0,7),
	(8,'type','document_type','Document type','Document type',0,1,1,0,0,'',1,'display_label=1\npretext=\nposttext=',0,'0000-00-00 00:00:00',0,8),
	(9,'version','version','Version','Number of version',0,1,1,0,0,'',1,'display_label=1\npretext=\nposttext=',0,'0000-00-00 00:00:00',0,9),
	(10,'state','state','State','State',0,1,1,0,0,'',1,'display_label=1',0,'0000-00-00 00:00:00',0,10),
	(11,'voting','voting','Voting','The up and down voting buttons',0,1,1,0,0,'top.items.default\nabove-description-line2-nolabel.category.blog',1,'display_label=1\ndimension=16\nimage=components/com_flexicontent/assets/images/star-small.png',0,'0000-00-00 00:00:00',0,11),
	(12,'favourites','favourites','Favourites','The add to favourites button',0,1,1,0,0,'top.items.default\nabove-description-line2-nolabel.category.blog',1,'display_label=1',0,'0000-00-00 00:00:00',0,12),
	(13,'categories','categories','Categories','The categories assigned to this item',0,1,1,0,0,'top.items.default\nunder-description-line1.category.blog',1,'display_label=1\nseparatorf=2',0,'0000-00-00 00:00:00',0,13),
	(14,'tags','tags','Tags','The tags assigned to this item',0,1,1,0,0,'top.items.default\nunder-description-line2.category.blog',1,'display_label=1\nseparatorf=2',0,'0000-00-00 00:00:00',0,14);

INSERT INTO `#__flexicontent_types` VALUES(1, 'Article', 'article', 1, 0, '0000-00-00 00:00:00', 0, 'ilayout=default\nhide_maintext=0\nhide_html=0\nmaintext_label=\nmaintext_desc=\ncomments=\ntop_cols=two\nbottom_cols=two');

INSERT INTO `#__flexicontent_fields_type_relations` (`field_id`,`type_id`,`ordering`)
VALUES
	(1,1,1),
	(2,1,2),
	(3,1,3),
	(4,1,4),
	(5,1,5),
	(6,1,6),
	(7,1,7),
	(8,1,8),
	(9,1,9),
	(10,1,10),
	(11,1,11),
	(12,1,12),
	(13,1,13),
	(14,1,14);