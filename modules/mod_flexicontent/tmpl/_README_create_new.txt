For CSS/HTML developers, it is easy to create a custom template for the FLEXIcontent module:

1. Create new template files/folders, by
	-- duplicating:
	  news.php
	  news.xml
	  /news/
	
	AS:
	  mynews.php
	  mynews.xml
	  /mynews/
		
	-- renaming 
	/mynews/news.css to /mynews/mynews.css


2. Edit /mynews/mynews.css and do a global replace of ".news." to ".mynews." (please notice the 2 fullstops)


3. Edit mynews.php and replace (near the top)

<div class="news mod_flexicontent_wrapper ..." ...

  with

<div class="mynews mod_flexicontent_wrapper ..." ...


4. Edit /mynews/mynews.css to customize the CSS rules that you need or to add more