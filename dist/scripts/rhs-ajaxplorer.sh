#!/bin/bash

# CONFIG_VOLUME /mnt/samba/ajxp-config
# DATA_VOLUME /mnt/samba/ajxp-data

# Install additional RPM Repositories
rpm -Uvh http://dl.ajaxplorer.info/repos/el6/ajaxplorer-stable/ajaxplorer-release-4-1.noarch.rpm
rpm -ivh http://download.fedoraproject.org/pub/epel/6/x86_64/epel-release-6-8.noarch.rpm
sleep 1

# Now install all RPMs
yum -y install httpd php php-pdo php-ldap ajaxplorer
sleep 1

# Update PHP Configuration
mv /etc/php.ini /etc/php.ini.orig
sed 's/output_buffering = 4096/output_buffering = Off/g' /etc/php.ini.orig > /etc/php.ini

# Start Apache
apachectl start

# Create target folders inside the gluster volumes
echo 'Configuring RHS nodes'
mkdir /mnt/samba/ajxp-config/appdata
mkdir /mnt/samba/ajxp-config/cache
mkdir /mnt/samba/ajxp-config/log
mkdir /mnt/samba/ajxp-data/common
mkdir /mnt/samba/ajxp-data/users
cp -R /var/lib/ajaxplorer/plugins /mnt/samba/ajxp-config/appdata

chown -R apache:apache /mnt/samba/ajxp-config
chown -R apache:apache /mnt/samba/ajxp-data

# Update AjaXplorer configuration files
echo 'Adapting AjaXplorer configuration files'

cp /etc/ajaxplorer/bootstrap_repositories.php /etc/ajaxplorer/bootstrap_repositories.php.orig
cp /etc/ajaxplorer/bootstrap_context.php /etc/ajaxplorer/bootstrap_context.php.orig

sed -i 's/"\/var\/lib\/ajaxplorer"/"\/mnt\/samba\/ajxp-config\/appdata"/g' /etc/ajaxplorer/bootstrap_context.php
sed -i 's/"AJXP_SHARED_CACHE_DIR", "\/var\/cache\/ajaxplorer"/"AJXP_SHARED_CACHE_DIR", "\/mnt\/samba\/ajxp-config\/cache"/g' /etc/ajaxplorer/bootstrap_context.php
sed -i 's/"\/var\/log\/ajaxplorer\/"/"\/mnt\/samba\/ajxp-config\/log\/"/g' /etc/ajaxplorer/bootstrap_context.php

sed -i 's/"AJXP_DATA_PATH\/files"/"\/mnt\/samba\/ajxp-data\/common"/g' /etc/ajaxplorer/bootstrap_repositories.php
sed -i 's/"AJXP_DATA_PATH\/personal\/AJXP_USER"/"\/mnt\/samba\/ajxp-data\/users\/AJXP_USER"/g' /etc/ajaxplorer/bootstrap_repositories.php

echo 'Finalizing Installation status'
if [ -e /mnt/samba/ajxp-config/skip_install ]
then
    echo '-----------------------'
    echo 'AjaXplorer is ready to go. Configurations were launched from RHS node.'
    echo 'You can verify this by opening http://yourhost/ajaxplorer/ through a web browser'
    echo '-----------------------'
    touch /var/cache/ajaxplorer/admin_counted
    touch /var/cache/ajaxplorer/first_run_passed
    touch /var/cache/ajaxplorer/diag_result.php
else
    echo '-----------------------'
    echo 'Your first AjaXplorer node is now running.'
    echo 'Please open http://yourhost/ajaxplorer/ in a web browser and follow the setup wizard.'
    echo '-----------------------'
    touch /mnt/samba/ajxp-config/skip_install
fi