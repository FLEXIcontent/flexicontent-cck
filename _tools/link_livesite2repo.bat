
:: *********
:: VARIABLES
:: *********

:: Disable echoing of MS-DOS commands
@ ECHO OFF

:: Store original folder path
SET original_folder=%CD%

SET dobackup=1



:: *****************************
:: Parse command line parameters
:: *****************************

:parse_param
IF "%1"=="" GOTO parse_done
IF /I "%1"=="/J" (
	SET joomlasite_path=%2
	SHIFT /1
	SHIFT /1
	GOTO parse_param
)
IF /I "%1"=="/R" (
	SET repository_path=%2
	SHIFT /1
	SHIFT /1
	GOTO parse_param
)
IF /I "%1"=="/NoBackup" (
	SET dobackup=0
	SHIFT /1
	GOTO parse_param
)

:usage_error
echo USAGE:
echo.
echo  %0  /J JOOMLA_SITE_PATH  /R FLEXI_REPOSITORY_PATH /NoBackup
exit /b

:parse_done
IF "%joomlasite_path%"==""  GOTO :usage_error
IF "%repository_path%"==""  GOTO :usage_error




:: ****************************************************************************
:: Check that Joomla live site and repository folder have the expected contents 
:: ****************************************************************************

IF NOT EXIST %joomlasite_path%  (ECHO JOOMLA_SITE_PATH %joomlasite_path% not found  &  GOTO :usage_error)
IF NOT EXIST %joomlasite_path%\administrator  (ECHO Joomal path:  %joomlasite_path% is not a joomla root folder  &  GOTO :usage_error)
IF NOT EXIST %joomlasite_path%\components     (ECHO Joomal path:  %joomlasite_path% is not a joomla root folder  &  GOTO :usage_error)
IF NOT EXIST %joomlasite_path%\administrator\components\com_flexicontent  (ECHO Joomal path:  %joomlasite_path% does not have FLEXIcontent installed  &  GOTO :usage_error)
IF NOT EXIST %joomlasite_path%\components\com_flexicontent                (ECHO Joomal path:  %joomlasite_path% does not have FLEXIcontent installed  &  GOTO :usage_error)

IF NOT EXIST %repository_path%  (ECHO "FLEXI_REPOSITORY_PATH %repository_path% not found"  &  GOTO :usage_error)
IF NOT EXIST %repository_path%\admin    (ECHO Repository:  %repository_path%  &  ECHO -- has no admin folder   &  GOTO :usage_error)
IF NOT EXIST %repository_path%\site     (ECHO Repository:  %repository_path%  &  ECHO -- has no site folder    &  GOTO :usage_error)
IF NOT EXIST %repository_path%\modules  (ECHO Repository:  %repository_path%  &  ECHO -- has no modules folder &  GOTO :usage_error)
IF NOT EXIST %repository_path%\plugins  (ECHO Repository:  %repository_path%  &  ECHO -- has no plugins folder &  GOTO :usage_error)




: **********************************************************************
: RENAME EXISTING folders in live site and make LINKS towards repository
: **********************************************************************


: Component SITE
IF EXIST  %joomlasite_path%\components\com_flexicontent_  (
	IF EXIST  %joomlasite_path%\components\com_flexicontent  (rd /q /s %joomlasite_path%\components\com_flexicontent)
) ELSE (
	IF "%dobackup%"=="1" (
		ren  %joomlasite_path%\components\com_flexicontent  com_flexicontent_
	) ELSE (
		rd /q /s %joomlasite_path%\components\com_flexicontent
	)
)
mklink  /J %joomlasite_path%\components\com_flexicontent  %repository_path%\site



: Component ADMIN

IF EXIST  %joomlasite_path%\administrator\components\com_flexicontent_  (
	IF EXIST  %joomlasite_path%\administrator\components\com_flexicontent  (rd /q /s %joomlasite_path%\administrator\components\com_flexicontent)
) ELSE (
	IF "%dobackup%"=="1" (
		ren  %joomlasite_path%\administrator\components\com_flexicontent  com_flexicontent_
	) ELSE (
		rd /q /s %joomlasite_path%\administrator\components\com_flexicontent
	)
)
mklink  /J %joomlasite_path%\administrator\components\com_flexicontent  %repository_path%\admin



: Component manifest.xml file

IF EXIST  %joomlasite_path%\administrator\components\com_flexicontent\flexicontent.xml  (del %joomlasite_path%\administrator\components\com_flexicontent\flexicontent.xml)
mklink %joomlasite_path%\administrator\components\com_flexicontent\flexicontent.xml  %repository_path%\flexicontent.xml



: Component SITE language

IF EXIST  %joomlasite_path%\language\en-GB\en-GB.com_flexicontent.ini_  (
	IF EXIST  %joomlasite_path%\language\en-GB\en-GB.com_flexicontent.ini  (del %joomlasite_path%\language\en-GB\en-GB.com_flexicontent.ini)
) ELSE (
	IF "%dobackup%"=="1" (
		ren  %joomlasite_path%\language\en-GB\en-GB.com_flexicontent.ini  en-GB.com_flexicontent.ini_
	) ELSE (
		del  %joomlasite_path%\language\en-GB\en-GB.com_flexicontent.ini
	)
)
mklink %joomlasite_path%\language\en-GB\en-GB.com_flexicontent.ini  %repository_path%\site\language\en-GB\en-GB.com_flexicontent.ini



: Component ADMIN language

IF EXIST  %joomlasite_path%\administrator\language\en-GB\en-GB.com_flexicontent.ini_  (
	IF EXIST  %joomlasite_path%\administrator\language\en-GB\en-GB.com_flexicontent.ini  (del %joomlasite_path%\administrator\language\en-GB\en-GB.com_flexicontent.ini)
) ELSE (
	IF "%dobackup%"=="1" (
		ren  %joomlasite_path%\administrator\language\en-GB\en-GB.com_flexicontent.ini  en-GB.com_flexicontent.ini_
	) ELSE (
		del  %joomlasite_path%\administrator\language\en-GB\en-GB.com_flexicontent.ini
	)
)
mklink %joomlasite_path%\administrator\language\en-GB\en-GB.com_flexicontent.ini  %repository_path%\admin\language\en-GB\en-GB.com_flexicontent.ini



:: Component ADMIN language SYS

IF EXIST  %joomlasite_path%\administrator\language\en-GB\en-GB.com_flexicontent.sys.ini_  (
	IF EXIST  %joomlasite_path%\administrator\language\en-GB\en-GB.com_flexicontent.sys.ini  (del %joomlasite_path%\administrator\language\en-GB\en-GB.com_flexicontent.sys.ini)
) ELSE (
	IF "%dobackup%"=="1" (
		ren  %joomlasite_path%\administrator\language\en-GB\en-GB.com_flexicontent.sys.ini  en-GB.com_flexicontent.sys.ini_
	) ELSE (
		del  %joomlasite_path%\administrator\language\en-GB\en-GB.com_flexicontent.sys.ini
	)
)
mklink %joomlasite_path%\administrator\language\en-GB\en-GB.com_flexicontent.sys.ini  %repository_path%\admin\language\en-GB\en-GB.com_flexicontent.sys.ini



:: MODULES

CD /d %repository_path%\modules
FOR /D %%G in ("*") DO (
	IF EXIST  %joomlasite_path%\modules\%%G_  (
		IF EXIST  %joomlasite_path%\modules\%%G  (rd /q /s %joomlasite_path%\modules\%%G)
	) ELSE (
		IF "%dobackup%"=="1" (
			ren  %joomlasite_path%\modules\%%G  %%G_
		) ELSE (
			rd /q /s  %joomlasite_path%\modules\%%G
		)
	)
	mklink  /J %joomlasite_path%\modules\%%G  %repository_path%\modules\%%G
	
	REM :: module language file
	IF EXIST  %joomlasite_path%\language\en-GB\en-GB.%%G.ini_  (
		IF EXIST  %joomlasite_path%\language\en-GB\en-GB.%%G.ini  (del %joomlasite_path%\language\en-GB\en-GB.%%G.ini)
	) ELSE (
		IF "%dobackup%"=="1" (
			ren  %joomlasite_path%\language\en-GB\en-GB.%%G.ini  en-GB.%%G.ini_
		) ELSE (
			del %joomlasite_path%\language\en-GB\en-GB.%%G.ini
		)
	)
	mklink %joomlasite_path%\language\en-GB\en-GB.%%G.ini  %repository_path%\modules\%%G\language\en-GB.%%G.ini
)



:: PLUGINS  -- ALL TYPES

SET PLG_TYPES=(flexicontent_fields, flexicontent, search, system, content, finder, editors-xtd, osmap)

FOR  %%i  IN  %PLG_TYPES%  DO (
	CD /d %repository_path%\plugins\%%i
	FOR /D %%G in ("*") DO (
		IF EXIST  %joomlasite_path%\plugins\%%i\%%G_  (
			IF EXIST  %joomlasite_path%\plugins\%%i\%%G  (rd /q /s %joomlasite_path%\plugins\%%i\%%G)
		) ELSE (
			IF "%dobackup%"=="1" (
				ren  %joomlasite_path%\plugins\%%i\%%G  %%G_
			) ELSE (
				rd /q /s  %joomlasite_path%\plugins\%%i\%%G
			)
		)
		mklink  /J %joomlasite_path%\plugins\%%i\%%G  %repository_path%\plugins\%%i\%%G
		
		REM :: plugin language file
		IF EXIST  %joomlasite_path%\administrator\language\en-GB\en-GB.plg_%%i_%%G.ini_  (
			IF EXIST  %joomlasite_path%\administrator\language\en-GB\en-GB.plg_%%i_%%G.ini  (del %joomlasite_path%\administrator\language\en-GB\en-GB.plg_%%i_%%G.ini)
		) ELSE (
			IF "%dobackup%"=="1" (
				ren  %joomlasite_path%\administrator\language\en-GB\en-GB.plg_%%i_%%G.ini  en-GB.plg_%%i_%%G.ini_
			) ELSE (
				del  %joomlasite_path%\administrator\language\en-GB\en-GB.plg_%%i_%%G.ini
			)
		)
		mklink %joomlasite_path%\administrator\language\en-GB\en-GB.plg_%%i_%%G.ini  %repository_path%\plugins\%%i\%%G\en-GB.plg_%%i_%%G.ini
	)
)



:: Return to original folder
CD /d %original_folder%