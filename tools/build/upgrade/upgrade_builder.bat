set src=ajaxplorer-src
set log_file=..\logs\upgrade.txt
set export=ajaxplorer-core-upgrade-%3-%4

rem --- SVN Checkout sources and grab revision number %2
chdir tmp
svn co https://ajaxplorer.svn.sourceforge.net/svnroot/ajaxplorer/trunk/core/src %src% -r %2 > %log_file%

chdir %src%
svn diff --summarize -r%1:%2 > ..\svn_summarize

chdir ..\
php ..\bin\parse_summarize.php svn_summarize %src%  %export%

rem --- Create ZIP
..\bin\7za.exe a %export%.zip %export% >> %log_file%

rem --- Upload FTP
echo off
(
echo open %FTP_SERVER%
echo %FTP_LOGIN%
echo %FTP_PASSWORD%
echo cd www/update/stable
echo type binary
echo put %export%.zip
echo close
echo quit
) > ftp_commands.txt
ftp -s:ftp_commands.txt >> %log_file%

rem --- Clear Variables and remove tmp folders
del ftp_commands.txt
del svn_summarize
move %export%.zip ..\
rmdir /S /Q %src%
rmdir /S /Q %export%
set src=
set export=
chdir ..

