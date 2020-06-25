		INSERT INTO `#__flexicontent_fields`
			(`id`,`field_type`,`name`,`label`,`description`,`isfilter`,`iscore`,`issearch`,`isadvsearch`,`untranslatable`,`formhidden`,`valueseditable`,`edithelp`,`positions`,`published`,`attribs`,`checked_out`,`checked_out_time`,`access`,`ordering`)
		VALUES
			(1,"maintext","text","Description","Main description text (introtext/fulltext)",0,1,1,0,0,0,0,1,"description.items.default",1,'{"display_label":"0","trigger_onprepare_content":"1"}',0,"0000-00-00 00:00:00",1,2),
			(2,"created","created","Created","Date this item was created",0,1,1,0,0,0,0,1,"top.items.default\nabove-description-line1-nolabel.category.blog",1,'{"display_label":"1","date_format":"DATE_FORMAT_LC1","custom_date":"","pretext":"","posttext":""}',0,"0000-00-00 00:00:00",1,3),
			(3,"createdby","created_by","Created by","User (owner) who created this item",0,1,1,0,0,0,0,1,"top.items.default\nabove-description-line1-nolabel.category.blog",1,'{"display_label":"1","pretext":"","posttext":""}',0,"0000-00-00 00:00:00",1,4),
			(4,"modified","modified","Last modified","Date this item was last modified",0,1,1,0,0,0,0,1,"top.items.default",1,'{"display_label":"1","date_format":"DATE_FORMAT_LC1","custom_date":"","pretext":"","posttext":""}',0,"0000-00-00 00:00:00",1,5),
			(5,"modifiedby","modified_by","Revised by","User who last modified this item",0,1,1,0,0,0,0,1,"top.items.default",1,'{"display_label":"1","pretext":"","posttext":""}',0,"0000-00-00 00:00:00",1,6),
			(6,"title","title","Title","Item title",0,1,1,0,0,0,0,1,"",1,'{"display_label":"1"}',0,"0000-00-00 00:00:00",1,1),
			(7,"hits","hits","Hits","Number of hits",0,1,1,0,0,0,0,1,"",1,'{"display_label":"1","pretext":"","posttext":"views"}',0,"0000-00-00 00:00:00",1,7),
			(8,"type","document_type","Document type","Document type",0,1,1,0,0,0,0,1,"",1,'{"display_label":"1","pretext":"","posttext":""}',0,"0000-00-00 00:00:00",1,8),
			(9,"version","version","Version","Latest version number",0,1,1,0,0,0,0,1,"",1,'{"display_label":"1","pretext":"","posttext":""}',0,"0000-00-00 00:00:00",1,9),
			(10,"state","state","State","Publication status",0,1,1,0,0,0,0,1,"",1,'{"display_label":"1"}',0,"0000-00-00 00:00:00",1,10),
			(11,"voting","voting","Voting","Voting buttons",0,1,1,0,0,0,0,1,"top.items.default\nabove-description-line2-nolabel.category.blog",1,'{"display_label":"1","dimension":"16","image":"components/com_flexicontent/assets/images/star-small.png"}',0,"0000-00-00 00:00:00",1,11),
			(12,"favourites","favourites","Favourites","Add to favourites button",0,1,1,0,0,0,0,1,"top.items.default\nabove-description-line2-nolabel.category.blog",1,'{"display_label":"1"}',0,"0000-00-00 00:00:00",1,12),
			(13,"categories","categories","Categories","Categories this item is assigned to",0,1,1,0,0,0,0,1,"top.items.default\nunder-description-line1.category.blog",1,'{"display_label":"1","separatorf":"2"}',0,"0000-00-00 00:00:00",1,13),
			(14,"tags","tags","Tags","Tags assigned to this item",0,1,1,0,0,0,0,1,"top.items.default\nunder-description-line2.category.blog",1,'{"display_label":"1","separatorf":"2"}',0,"0000-00-00 00:00:00",1,14)
		;
		
		INSERT INTO `#__flexicontent_types`
			(id, asset_id, name, alias, published, checked_out, checked_out_time, access, attribs)
		VALUES
		(
			1, 0, "Article", "article", 1, 0, "0000-00-00 00:00:00", 1,
			'{"ilayout":"default","hide_maintext":"0","hide_html":"0","maintext_label":"","maintext_desc":"","comments":"","top_cols":"two","bottom_cols":"two","allow_jview":"1"}'
		)
		;
		
		INSERT INTO `#__flexicontent_fields_type_relations`
			(`field_id`,`type_id`,`ordering`)
		VALUES
			(1,1,1), (2,1,2), (3,1,3), (4,1,4), (5,1,5), (6,1,6), (7,1,7), (8,1,8), (9,1,9), (10,1,10), (11,1,11), (12,1,12), (13,1,13), (14,1,14)
		;
