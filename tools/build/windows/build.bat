rem --- Compute date and folder
for /f "tokens=1,2,3 delims=/ " %%a in ('DATE /T') do set date=%%c%%b%%a
chdir tmp
set src=ajaxplorer-sources
set log_file=..\logs\%date%-build.txt

rem --- SVN Checkout sources and grab current revision
svn co https://ajaxplorer.svn.sourceforge.net/svnroot/ajaxplorer/trunk/core/src %src% > %log_file%
chdir %src%
for /f "tokens=*" %%a in ('svnversion') do set revision=%%a

rem --- Export sources
set export=ajaxplorer-%date%-%revision%
svn export . ..\%export%


rem --- Create ZIP
chdir ..\
..\bin\7za.exe a %export%.zip %export% >> %log_file%

rem --- Upload FTP
echo off
(
echo open %FTP_SERVER%
echo %FTP_LOGIN%
echo %FTP_PASSWORD%
echo cd www/build
echo type binary
echo put %export%.zip
echo close
echo quit
) > ftp_commands.txt
ftp -s:ftp_commands.txt >> %log_file%

rem --- Clear Variables and remove tmp folders
del ftp_commands.txt
del %export%.zip
rmdir /S /Q %src%
rmdir /S /Q %export%
set src=
set new=
set revision=
set export=
chdir ..

