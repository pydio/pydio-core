#!/bin/bash

echo "Upgrade pydio 5.2.5 to 6.0.0"
echo "Requirement (if your system does not satisfy below criteria, please do not execute!!!)"
echo "1. Pydio 5.2.5"
echo "2. Database mysql"
echo "3. Install from apt (.deb package)"
echo ""
while true; do
    read -p "Do you wish to run this script [Y/n]? " yn
    case $yn in
        [Yy]* ) echo "Start script"; break;;
        [Nn]* ) exit;;
        * ) echo "Please answer yes or no.";;
    esac
done


create_public_htaccess (){
file_name=$1
if [ -f $file_name ]
then
echo > $file_name
else
touch $file_name
fi

echo "Order Deny,Allow" >> $file_name
echo "Allow from all" >> $file_name

echo "<Files \".ajxp_*\">" >> $file_name
echo "deny from all" >> $file_name
echo "</Files>" >> $file_name

echo "<IfModule mod_rewrite.c>" >> $file_name
echo "RewriteEngine on" >> $file_name
echo "RewriteBase "$2 >> $file_name
echo "RewriteCond %{REQUEST_FILENAME} !-f" >> $file_name
echo "RewriteCond %{REQUEST_FILENAME} !-d" >> $file_name
echo "RewriteRule ^([a-zA-Z0-9_-]+)\.php$ share.php?hash=\$1 [QSA]" >> $file_name
echo "RewriteRule ^([a-zA-Z0-9_-]+)--([a-z]+)$ share.php?hash=\$1&lang=\$2 [QSA]" >> $file_name
echo "RewriteRule ^([a-zA-Z0-9_-]+)$ share.php?hash=\$1 [QSA]" >> $file_name
echo "</IfModule>" >> $file_name
}
################################################################
################################################################
################################################################
create_root_htaccess () {
file_name=$1


echo "<IfModule mod_rewrite.c>" >> $file_name
echo "# You must set the correct values here if you want" >> $file_name
echo "# to enable webDAV sharing. The values assume that your " >> $file_name
echo "# Pydio installation is at http://yourdomain/" >> $file_name
echo "# and that you want the webDAV shares to be accessible via " >> $file_name
echo "# http://yourdomain/shares/repository_id/" >> $file_name
echo "RewriteEngine on" >> $file_name
echo "RewriteBase "$2"" >> $file_name
echo "RewriteCond %{REQUEST_FILENAME} !-f" >> $file_name
echo "RewriteCond %{REQUEST_FILENAME} !-d" >> $file_name
echo "RewriteRule ^shares ./dav.php [L]" >> $file_name
echo "RewriteRule ^api ./rest.php [L]" >> $file_name
echo "RewriteRule ^user ./index.php?get_action=user_access_point [L]" >> $file_name
echo "" >> $file_name
echo "RewriteCond %{REQUEST_URI} !^"$2"/index" >> $file_name
echo "RewriteCond %{REQUEST_URI} !^"$2"/plugins" >> $file_name
echo "RewriteCond %{REQUEST_URI} ^"$2"/dashboard|^"$2"/welcome|^"$2"/settings|^"$2"/ws-" >> $file_name
echo "RewriteRule (.*) index.php [L]" >> $file_name
echo "" >> $file_name
echo "#Following lines seem to be necessary if PHP is working" >> $file_name
echo "#with apache as CGI or FCGI. Just remove the #" >> $file_name
echo "#See http://doc.tiki.org/WebDAV#Note_about_Apache_with_PHP_as_fcgi_or_cgi" >> $file_name
echo "" >> $file_name
echo "#RewriteCond %{HTTP:Authorization} ^(.*)" >> $file_name
echo "#RewriteRule ^(.*) - [E=HTTP_AUTHORIZATION:%1]" >> $file_name
echo "</IfModule>" >> $file_name
}

###########################################################
###########################################################
###########################################################

# test wget
echo "Install wget if not exist"
dpkg -s sudo 2>/dev/null >/dev/null || SUDO=1
if [ $SUDO == 1 ]
then
echo "Install wget"
dpkg -s wget 2>/dev/null >/dev/null || sudo apt-get -y install wget
else
echo "Install wget"
dpkg -s wget 2>/dev/null >/dev/null || apt-get -y install wget
fi

# download pydio.6.0.0
cd /tmp
echo ""
echo "Download Pydio version 6.0.0"
wget https://pyd.io/build/pydio-core-6.0.0.tar.gz 1>/dev/null
tar xzf pydio-core-6.0.0.tar.gz 
cd pydio-core-6.0.0



cd /etc/pydio
echo ""
echo "Backup /etc/pydio/bootstrap* files"
mv bootstrap_context.php bootstrap_context.php.pre-update
mv bootstrap_repositories.php bootstrap_repositories.php.pre-update
echo ""
echo "Copy new version of bootstrap* files"
cp /tmp/pydio-core-6.0.0/conf/bootstrap_context.php ./
cp /tmp/pydio-core-6.0.0/conf/bootstrap_repositories.php ./


################################################################
################################################################
################################################################
# if VM appliance fix URI and Public for URI, this value can 
# hard-code and no need to ask user to input
#
echo ""
echo "Your URI. for example your URL is http://your.domain/pydio => URI is: /pydio"
read -p "Enter your URI: [/pydio] " ROOT_URI
if [ "$ROOT_URI" = "" ]
then
ROOT_URI="/pydio"
fi

echo ""
echo "Your URI for public. for example your URL is http://your.domain/pydio/data/public => URI is: /pydio/data/public"
read -p "Enter your URI for public: [/pydio/data/public] " PUBLIC_URI
if [ "$PUBLIC_URI" = "" ]
then
PUBLIC_URI="/pydio/data/public"
fi
#########################################################



public_file_htaccess="/var/lib/pydio/data/public/.htaccess"
if [ -f $public_file_htaccess ]
then
echo "Back up /var/lib/pydio/data/public/.htaccess"
mv $public_file_htaccess /var/lib/pydio/data/public/htaccess.bk
fi

root_file_htaccess="/usr/share/pydio/.htaccess"
if [ -f  $root_file_htaccess ]
then
echo "Back up /usr/share/pydio/.htaccess"
mv $root_file_htaccess /usr/share/pydio/htaccess.bk
fi

echo ""
echo "Create new .htaccess in public folder" 
create_public_htaccess $public_file_htaccess $PUBLIC_URI

echo ""
echo "Create new .htaccess in pydio root folder" 
create_root_htaccess $root_file_htaccess $ROOT_URI

################################################################
################################################################
################################################################
echo ""
echo "Database"
echo ""
echo "Backup database..."

if [ -f /var/lib/pydio/data/plugins/boot.conf/bootstrap.json ]
then
echo "extract info from bootstrap.json"
MYSQL_USERNAME=$(cat /var/lib/pydio/data/plugins/boot.conf/bootstrap.json | grep mysql_username | tr -d ' '| tr -d ',' | sed s/mysql_username//g | tr -d '"' | tr -d ':')
MYSQL_PASSWORD=$(cat /var/lib/pydio/data/plugins/boot.conf/bootstrap.json | grep mysql_password | tr -d ' '| tr -d ',' | sed s/mysql_password//g | tr -d '"' | tr -d ':')
MYSQL_DATABASE=$(cat /var/lib/pydio/data/plugins/boot.conf/bootstrap.json | grep mysql_database | tr -d ' '| tr -d ',' | sed s/mysql_database//g | tr -d '"' | tr -d ':')

else
# input manually
read -p "Enter mysql account username: " MYSQL_USERNAME
read -p "Enter mysql account password: " MYSQL_PASSWORD
read -p "Enter mysql database: " MYSQL_DATABASE
echo ""
fi

#echo "mysql_username:"$MYSQL_USERNAME
#echo "mysql_password:"$MYSQL_PASSWORD
#echo "mysql_database:"$MYSQL_DATABASE

cd /usr/share/pydio
/usr/bin/mysqldump --user=$MYSQL_USERNAME --password=$MYSQL_PASSWORD $MYSQL_DATABASE > ./backup-pydio-5.2.5.sql
echo "Database bakup locates on /usr/share/pydio/backup-pydio-5.2.5.sql"

echo ""
echo "update database"

cd /tmp
wget https://raw.githubusercontent.com/pydio/pydio-core/develop/dist/scripts/misc/5.2.5-6.0.0.mysql
/usr/bin/mysql --user=$MYSQL_USERNAME --password=$MYSQL_PASSWORD $MYSQL_DATABASE < 5.2.5-6.0.0.mysql
#/usr/bin/mysql --user=$MYSQL_USERNAME --password=$MYSQL_PASSWORD -e "USE "$MYSQL_DATABASE"; UPDATE ajxp_roles SET serial_role=replace(serial_role,'ajxp_user', 'ajxp_home');"

#rm -f $SQL_FILE

sleep 2;

echo ""
echo "Copy code"
cd /usr/share/pydio

file_backup="backup_upgrade_525_to_600"
[ -d $file_backup ] && rm -rf $file_backup
mkdir $file_backup
[ -d /usr/share/pydio/core ] && mv -f /usr/share/pydio/core $file_backup
[ -d /usr/share/pydio/plugins ] && mv -f /usr/share/pydio/plugins $file_backup
[ -f /usr/share/pydio/index.php ] && mv -f /usr/share/pydio/index.php $file_backup
[ -f /usr/share/pydio/content.php ] && mv -f /usr/share/pydio/content.php $file_backup
[ -f /usr/share/pydio/cmd.php ] && mv -f /usr/share/pydio/cmd.php $file_backup
[ -f /usr/share/pydio/dav.php ] && mv -f /usr/share/pydio/dav.php $file_backup
[ -f /usr/share/pydio/index_shared.php ] && mv -f /usr/share/pydio/index_shared.php $file_backup
[ -f /usr/share/pydio/rest.php ] && mv -f /usr/share/pydio/rest.php $file_backup
[ -f /usr/share/pydio/base.conf.php ] && mv -f /usr/share/pydio/base.conf.php $file_backup

sleep 3

cp -rf /tmp/pydio-core-6.0.0/core ./core
cp -rf /tmp/pydio-core-6.0.0/plugins ./plugins
cp -f /tmp/pydio-core-6.0.0/*.php ./
cp -f /tmp/pydio-core-6.0.0/conf/VERSION /etc/pydio/VERSION
cp -f /tmp/pydio-core-6.0.0/conf/VERSION.php /etc/pydio/VERSION.php

echo ""
echo "Clear pydio cache"
[ -d /var/lib/pydio/data/cache/i18n/ ] && rm -rf /var/lib/pydio/data/cache/i18n/*
[ -d /var/lib/pydio/data/cache ] && rm -rf /var/lib/pydio/data/cache/*.ser

[ -d /var/cache/pydio ] && rm -rf /var/cache/pydio/*.ser
[ -d /var/cache/pydio/i18n ] && rm -rf /var/cache/pydio/i18n/*

rm -rf /tmp/pydio-core-6.*
rm -rf /tmp/pydio-sql-upgrade*
rm -r /tmp/5.2.5-6.0.0.mysql

echo ""
echo "Finish!"
echo ""
