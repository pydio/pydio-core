#!/bin/sh
pear channel-update pear.php.net
peck channel-update pecl.php.net
pecl install imagick
pear config-set auto_discover 1
pear channel-discover pear.amazonwebservices.com
pear install PEAR
pear install aws/sdk
pear install HTTP_OAuth
pear install HTTP_WebDAV_Client
pear install Mail_mimeDecode

# customize php.ini
#echo "extension=imap.so" >> /etc/php.ini
echo "extension=imagick.so" >> /etc/php.ini
sed -i "s/output_buffering = 4096/output_buffering = Off/g" /etc/php.ini
sed -i "s/upload_max_filesize = 2M/upload_max_filesize = 1024M/g" /etc/php.ini
sed -i "s/post_max_size = 8M/post_max_size = 1024M/g" /etc/php.ini
