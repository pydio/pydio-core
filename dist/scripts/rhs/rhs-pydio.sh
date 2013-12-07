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

echo "--- Describe RHS volumes ---"
data_volume_default="/mnt/samba/pydio-data"
read -p "Data volume [$data_volume_default]: " data_volume
data_volume="${data_volume:-$data_volume_default}"

config_volume_default="/mnt/samba/pydio-config"
read -p "Config volume [$config_volume_default]: " config_volume
config_volume="${config_volume:-$config_volume_default}"

echo "Pydio will be installed on the following volumes: $data_volume , $config_volume"
read -p "Are you sure? " -n 1 -r
echo    # (optional) move to a new line
if [[ ! $REPLY =~ ^[Yy]$ ]]
then
    exit 1
fi

# Install additional RPM Repositories
rpm -Uvh http://dl.ajaxplorer.info/repos/el6/ajaxplorer-stable/ajaxplorer-release-4-1.noarch.rpm
rpm -ivh http://download.fedoraproject.org/pub/epel/6/x86_64/epel-release-6-8.noarch.rpm
sleep 1

# Now install all RPMs
# Warning, this requires the "RHS EUS Server Optional" channel to be activated through RHN
# See for example http://itsystemsadmin.wordpress.com/2011/04/27/red-hat-enterprise-linux-6-rhel-6-install-php-mbstring/
yum -y install httpd php php-pdo php-ldap php-pecl-apc php-mbstring php-devel libattr-devel ImageMagick pydio
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
SetEnvIf Request_URI "^/pydio/check\.txt$" dontlog
CustomLog logs/access_log combined env=!dontlog
HTTPDCONF

# Update PHP Configuration
mv /etc/php.ini /etc/php.ini.orig
sed 's/output_buffering = 4096/output_buffering = Off/g' /etc/php.ini.orig > /etc/php.ini
sed -i 's/post_max_size = 8M/post_max_size = 200M/g' /etc/php.ini
sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 200M/g' /etc/php.ini

# Create target folders inside the gluster volumes
echo 'Configuring RHS nodes'
mkdir -p ${config_volume}/appdata
mkdir -p ${config_volume}/cache
mkdir -p ${config_volume}/log
mkdir -p ${data_volume}/public
mkdir -p ${data_volume}/common
mkdir -p ${data_volume}/users
cp -Rf /var/lib/pydio/plugins ${config_volume}/appdata

chown -R apache:apache ${config_volume}
chown -R apache:apache ${data_volume}

# Update Pydio configuration files
echo 'Adapting Pydio configuration files'

cp /etc/pydio/bootstrap_repositories.php /etc/pydio/bootstrap_repositories.php.orig
cp /etc/pydio/bootstrap_context.php /etc/pydio/bootstrap_context.php.orig

sed -i "s#\"/var/lib/pydio\"#\"${config_volume}/appdata\"#g" /etc/pydio/bootstrap_context.php
sed -i "s#\"AJXP_SHARED_CACHE_DIR\", \"/var/cache/pydio\"#\"AJXP_SHARED_CACHE_DIR\", \"${config_volume}/cache\"#g" /etc/pydio/bootstrap_context.php
sed -i "s#\"/var/log/pydio/\"#\"${config_volume}/log/\"#g" /etc/pydio/bootstrap_context.php

sed -i "s#\"AJXP_DATA_PATH/files\"#\"${data_volume}/common\"#g" /etc/pydio/bootstrap_repositories.php
sed -i "s#\"AJXP_DATA_PATH/personal/AJXP_USER\"#\"${data_volume}/users/AJXP_USER\"#g" /etc/pydio/bootstrap_repositories.php

# Update Share URL
sed -i "s#\"AJXP_INSTALL_PATH/data/public\"#\"${data_volume}/public\"#g" /usr/share/pydio/plugins/core.ajaxplorer/manifest.xml
sed -i "s#/var/lib/pydio/public#${data_volume}/public#g" /etc/httpd/conf.d/pydio.conf

# Start Apache
apachectl start

# Deploy patches if necessary
if [ -d ${config_volume}/install_patches ]
then
    cp -Rf ${config_volume}/install_patches/* /usr/share/pydio/
    cp ${config_volume}/install_patches/.*?? /usr/share/pydio/
fi

echo 'Finalizing Installation status'
if [ -e ${config_volume}/skip_install ]
then

    touch /var/cache/pydio/admin_counted
    touch /var/cache/pydio/first_run_passed
    touch /var/cache/pydio/diag_result.php
    touch /usr/share/pydio/check.txt

    echo '-----------------------'
    echo 'Pydio is ready to go. Configurations were launched from RHS node.'
    echo 'You can verify this by opening http://yourhost/pydio/ through a web browser'
    echo '-----------------------'
else

    touch /usr/share/pydio/check.txt
    touch ${config_volume}/skip_install

    echo '-----------------------'
    echo 'Your first Pydio node is now running.'
    echo 'Please open http://yourhost/pydio/ in a web browser and follow the setup wizard.'
    echo 'Then you should update the necessary settings, particularly the outside world IP of the installation, in the Pydio Core Options.'
    echo '-----------------------'

fi
