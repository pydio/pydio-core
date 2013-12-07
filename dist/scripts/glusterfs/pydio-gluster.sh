#!/bin/bash

function install_from_repos {

	echo 'Installing Pydio repositories'
	echo '-----------------------------'
	echo 'deb http://dl.ajaxplorer.info/repos/apt stable main' >> /etc/apt/sources.list
	echo 'deb-src http://dl.ajaxplorer.info/repos/apt stable main' >> /etc/apt/sources.list
	wget http://dl.ajaxplorer.info/repos/charles@ajaxplorer.info.gpg.key
	apt-key add charles@ajaxplorer.info.gpg.key

	echo 'Updating repositories list and installing Pydio'
	echo '-----------------------------'
	apt-get update
	apt-get install php5 php5-mysql php5-ldap php-apc php-pear libattr1-dev php5-dev make imagemagick pydio
	sleep 1
	echo "Compiling Extended Attribute PECL Extension"
	pecl install xattr
	echo "extension=xattr.so" > /etc/php5/conf.d/xattr.ini

}

function update_apache_conf {

        echo 'Updating Apache Configuration'
        echo '-----------------------------'
	# Update HTTPD Configuration
	cp /usr/share/doc/pydio/apache2.sample.conf /etc/apache2/sites-enabled/pydio.conf
	# Filter out the regular health checks of HAProxy
	sed -i 's/CustomLog ${APACHE_LOG_DIR}\/access.log combined/SetEnvIf Request_URI « ^\/pydio\/check\.txt$" dontlog\n\tCustomLog ${APACHE_LOG_DIR}\/access_log combined env=!dontlog/' /etc/apache2/sites-enabled/000-default

}

function update_php_conf {

        echo 'Updating PHP Configuration'
        echo '-----------------------------'
	cp /etc/php5/apache2/php.ini /etc/php5/apache2/php.ini.orig
	sed -i 's/output_buffering = 4096/output_buffering = Off/g' /etc/php5/apache2/php.ini
	sed -i 's/post_max_size = 8M/post_max_size = 200M/g' /etc/php5/apache2/php.ini
	sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 200M/g' /etc/php5/apache2/php.ini

}

function update_pydio_conf {

        echo 'Updating Pydio Configuration'
        echo '-----------------------------'
	config_volume=$1
	data_volume=$2

	# Create target folders inside the gluster volumes
	echo 'Configuring RHS nodes'
	mkdir -p ${config_volume}/appdata
	mkdir -p ${config_volume}/cache
	mkdir -p ${config_volume}/log
	mkdir -p ${data_volume}/public
	mkdir -p ${data_volume}/common
	mkdir -p ${data_volume}/users
	mkdir -p /var/cache/pydio

	cp -Rf /var/lib/pydio/data/plugins ${config_volume}/appdata

	chown -R www-data:www-data ${config_volume}
	chown -R www-data:www-data ${data_volume}
	chown -R www-data:www-data /var/cache/pydio

	cp /etc/pydio/bootstrap_repositories.php /etc/pydio/bootstrap_repositories.php.orig
	cp /etc/pydio/bootstrap_context.php /etc/pydio/bootstrap_context.php.orig

	sed -i "s#AJXP_INSTALL_PATH.\"/data\"#\"${config_volume}\/appdata\"#g" /etc/pydio/bootstrap_context.php
	sed -i "s#AJXP_INSTALL_PATH.\"/data/cache\"#\"${config_volume}/cache\"#g" /etc/pydio/bootstrap_context.php
        sed -i "s#AJXP_DATA_PATH.\"/cache\"#\"/var/cache/pydio\"#g" /etc/pydio/bootstrap_context.php
	sed -i "s#\"AJXP_DATA_PATH/files\"#\"${data_volume}/common\"#g" /etc/pydio/bootstrap_repositories.php
	sed -i "s#\"AJXP_DATA_PATH/personal/AJXP_USER\"#\"${data_volume}/users/AJXP_USER\"#g" /etc/pydio/bootstrap_repositories.php

}

# CONFIG_VOLUME /mnt/pydio-config
# DATA_VOLUME /mnt/pydio-data
echo "--- Describe Gluster volumes ---"
data_volume_default="/mnt/pydio-data"
read -p "Data volume [$data_volume_default]: " data_volume
data_volume="${data_volume:-$data_volume_default}"

config_volume_default="/mnt/pydio-config"
read -p "Config volume [$config_volume_default]: " config_volume
config_volume="${config_volume:-$config_volume_default}"

echo "Pydio will be installed on the following volumes: $data_volume , $config_volume"
read -p "Are you sure? " -n 1 -r
echo    # (optional) move to a new line
if [[ ! $REPLY =~ ^[Yy]$ ]]
then
    exit 1
fi

install_from_repos
sleep 1

update_apache_conf
sleep 1

update_php_conf
sleep 1

update_pydio_conf ${config_volume} ${data_volume}
sleep 1

apachectl restart


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
