# pre-configure pydio 
[[ ! -d /var/lib/pydio/plugins/boot.conf ]] && mkdir /var/lib/pydio/plugins/boot.conf
cp -f /etc/bootstrap.json /var/lib/pydio/plugins/boot.conf/bootstrap.json

# create some files which indicate that pydio has been installed.
touch /var/cache/pydio/admin_counted
touch /var/cache/pydio/diag_result.php
touch /var/cache/pydio/first_run_passed

# fix LANG
if [ "$LANG" = "" ]; then
mylang=$LANG
else
mylang="en_US.UTF-8"
fi
echo "define(\"AJXP_LOCALE\", \"$mylang\");" >> /etc/pydio/bootstrap_conf.php

# mysql
service mysqld start
mysql -uroot -e "create database pydio"
mysql -uroot -e "create user pydio@localhost identified by 'pydiopsss'"
mysql -uroot -e "grant all privileges on pydio.* to pydio@localhost identified by 'pydiopsss' with grant option"
mysql -u pydio -p'pydiopsss' pydio  < /etc/create.mysql
