INSERT INTO `#__flexicontent_fields`
	(`id`,`field_type`,`name`,`label`,`description`,`isfilter`,`iscore`,`issearch`,`isadvsearch`,`untranslatable`,`formhidden`,`valueseditable`,`edithelp`,`positions`,`published`,`attribs`,`checked_out`,`checked_out_time`,`access`,`ordering`)
VALUES
	(1,'maintext','text','Description','Main description text (introtext/fulltext)',0,1,1,0,0,0,0,2,'description.items.default',1,'display_label=0\ntrigger_onprepare_content=1',0,'0000-00-00 00:00:00',1,2),
	(2,'created','created','Created','Date this item was created',0,1,1,0,0,0,0,2,'top.items.default\nabove-description-line1-nolabel.category.blog',1,'display_label=1\ndate_format=DATE_FORMAT_LC1\ncustom_date=\npretext=\nposttext=',0,'0000-00-00 00:00:00',1,3),
	(3,'createdby','created_by','Created by','User who created this item',0,1,1,0,0,0,0,2,'top.items.default\nabove-description-line1-nolabel.category.blog',1,'display_label=1\npretext=\nposttext=',0,'0000-00-00 00:00:00',1,4),
	(4,'modified','modified','Last modified','Date this item was last modified',0,1,1,0,0,0,0,2,'top.items.default',1,'display_label=1\ndate_format=DATE_FORMAT_LC1\ncustom_date=\npretext=\nposttext=',0,'0000-00-00 00:00:00',1,5),
	(5,'modifiedby','modified_by','Revised by','User who last edited this item',0,1,1,0,0,0,0,2,'top.items.default',1,'display_label=1\npretext=\nposttext=',0,'0000-00-00 00:00:00',1,6),
	(6,'title','title','Title','Item Title',0,1,1,0,0,0,0,2,'',1,'display_label=1',0,'0000-00-00 00:00:00',1,1),
	(7,'hits','hits','Hits','Number of hits for this item',0,1,1,0,0,0,0,2,'',1,'display_label=1\npretext=\nposttext=views',0,'0000-00-00 00:00:00',1,7),
	(8,'type','document_type','Document Type','Document type',0,1,1,0,0,0,0,2,'',1,'display_label=1\npretext=\nposttext=',0,'0000-00-00 00:00:00',1,8),
	(9,'version','version','Version','Version Number',0,1,1,0,0,0,0,2,'',1,'display_label=1\npretext=\nposttext=',0,'0000-00-00 00:00:00',1,9),
	(10,'state','state','State','Publication status',0,1,1,0,0,0,0,2,'',1,'display_label=1',0,'0000-00-00 00:00:00',1,10),
	(11,'voting','voting','Voting','Up and down voting buttons',0,1,1,0,0,0,0,2,'top.items.default\nabove-description-line2-nolabel.category.blog',1,'display_label=1\ndimension=16\nimage=components/com_flexicontent/assets/images/star-small.png',0,'0000-00-00 00:00:00',1,11),
	(12,'favourites','favourites','Favourites','Add-to-Favourites button',0,1,1,0,0,0,0,2,'top.items.default\nabove-description-line2-nolabel.category.blog',1,'display_label=1',0,'0000-00-00 00:00:00',1,12),
	(13,'categories','categories','Categories','Categories this item is assigned to',0,1,1,0,0,0,0,2,'top.items.default\nunder-description-line1.category.blog',1,'display_label=1\nseparatorf=2',0,'0000-00-00 00:00:00',1,13),
	(14,'tags','tags','Tags','Tags assigned to this item',0,1,1,0,0,0,0,2,'top.items.default\nunder-description-line2.category.blog',1,'display_label=1\nseparatorf=2',0,'0000-00-00 00:00:00',1,14);

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
