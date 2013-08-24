#!/bin/bash

# CONFIG_VOLUME /mnt/samba/ajxp-config
# DATA_VOLUME /mnt/samba/ajxp-data

function channels_error {
   declare -a reg_channels=(`rhn-channel --list`)
   echo "ERROR: All required channels are not registered!"
   echo -e "Required Channel for Ajaxplorer:\n\trhel-x86_64-server-optional-6.2.z"
   echo -e "Registered Channels:"
   for chan in "${reg_channels[@]}"
   do
         echo -e "\t$chan"
   done
   return 1
}


function check_channels {

   declare -a reg_channels=(`rhn-channel --list`)
   correct=0
   for chan in "${reg_channels[@]}"
   do
      if [ "$chan" == "rhel-x86_64-server-optional-6.2.z" ]
      then
         (( correct++ ))
      fi
   done

   if [ $correct -ne 1 ]
   then
      channels_error
      return 1
   fi

   echo -e "Registered Channels:"
   for chan in "${reg_channels[@]}"
   do
         echo -e "\t$chan"
   done
   return 0
}


function rhn_register_rhel_optional {

    profile_name=`hostname -s`
    profile_name=RHS_$profile_name

    echo "---- Register Channels ----"
    read -p "RHN Login: " rhn_login
    read -s -p "RHN Password: " rhn_password
    echo ""
    rhn-channel --verbose --user $rhn_login --password $rhn_password \
        --add --channel=rhel-x86_64-server-optional-6.2.z

    check_channels || return 1
    echo "System registered to the correct Red Hat Channels!"
    return 0
}

rhn_register_rhel_optional || \
        (echo "RHN optional channels not registered, exit!" ; exit 1)

# Install additional RPM Repositories
rpm -Uvh http://dl.ajaxplorer.info/repos/el6/ajaxplorer-stable/ajaxplorer-release-4-1.noarch.rpm
rpm -ivh http://download.fedoraproject.org/pub/epel/6/x86_64/epel-release-6-8.noarch.rpm
sleep 1

# Now install all RPMs
# Warning, this requires the "RHS EUS Server Optional" channel to be activated through RHN
# See for example http://itsystemsadmin.wordpress.com/2011/04/27/red-hat-enterprise-linux-6-rhel-6-install-php-mbstring/
yum -y install httpd php php-pdo php-ldap php-pecl-apc php-mbstring php-devel libattr-devel ImageMagick ajaxplorer
sleep 1
echo "Compiling Extended Attribute PECL Extension"
pecl install xattr
echo "extension=xattr.so" >> /etc/php.d/xattr.ini
sleep 1


# Update HTTPD Configuration
# Filter out the regular health checks of HAProxy
cp /etc/httpd/conf/httpd.conf /etc/httpd/conf/httpd.conf.orig
sed -i 's/CustomLog logs\/access_log combined/#CustomLog logs\/access_log combined/' /etc/httpd/conf/httpd.conf
cat >> /etc/httpd/conf/httpd.conf << HTTPDCONF
SetEnvIf Request_URI "^/ajaxplorer/check\.txt$" dontlog
CustomLog logs/access_log combined env=!dontlog
HTTPDCONF

# Update PHP Configuration
mv /etc/php.ini /etc/php.ini.orig
sed 's/output_buffering = 4096/output_buffering = Off/g' /etc/php.ini.orig > /etc/php.ini

# Create target folders inside the gluster volumes
echo 'Configuring RHS nodes'
mkdir -p /mnt/samba/ajxp-config/appdata
mkdir -p /mnt/samba/ajxp-config/cache
mkdir -p /mnt/samba/ajxp-config/log
mkdir -p /mnt/samba/ajxp-data/public
mkdir -p /mnt/samba/ajxp-data/common
mkdir -p /mnt/samba/ajxp-data/users
cp -Rf /var/lib/ajaxplorer/plugins /mnt/samba/ajxp-config/appdata

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

# Update Share URL
sed -i 's/"AJXP_INSTALL_PATH\/data\/public"/"\/mnt\/samba\/ajxp-data\/public"/g' /usr/share/ajaxplorer/plugins/core.ajaxplorer/manifest.xml
sed -i 's/\/var\/lib\/ajaxplorer\/public/\/mnt\/samba\/ajxp-data\/public/g' /etc/httpd/conf.d/ajaxplorer.conf

# Start Apache
apachectl start

# Deploy patches if necessary
if [ -d /mnt/samba/ajxp-config/install_patches ]
then
    cp -Rf /mnt/samba/ajxp-config/install_patches/* /usr/share/ajaxplorer/
    cp /mnt/samba/ajxp-config/install_patches/.*?? /usr/share/ajaxplorer/
fi

echo 'Finalizing Installation status'
if [ -e /mnt/samba/ajxp-config/skip_install ]
then

    touch /var/cache/ajaxplorer/admin_counted
    touch /var/cache/ajaxplorer/first_run_passed
    touch /var/cache/ajaxplorer/diag_result.php
    touch /usr/share/ajaxplorer/check.txt

    echo '-----------------------'
    echo 'AjaXplorer is ready to go. Configurations were launched from RHS node.'
    echo 'You can verify this by opening http://yourhost/ajaxplorer/ through a web browser'
    echo '-----------------------'
else

    touch /usr/share/ajaxplorer/check.txt
    touch /mnt/samba/ajxp-config/skip_install

    echo '-----------------------'
    echo 'Your first AjaXplorer node is now running.'
    echo 'Please open http://yourhost/ajaxplorer/ in a web browser and follow the setup wizard.'
    echo 'Then you should update the necessary settings, particularly the outside world IP of the installation, in the AjaXplorer Core Options.'
    echo '-----------------------'

fi
