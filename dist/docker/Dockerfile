# DOCKER-VERSION 0.xx
# Pydio Version 6.0.2
FROM centos:centos6
MAINTAINER team@pyd.io

ADD ./my.cnf /etc/my.cnf
ADD ./supervisord.conf /etc/
ADD ./create.mysql /etc/create.mysql
ADD ./bootstrap.json /etc/bootstrap.json
ADD ./gencert.sh /etc/gencert.sh
ADD ./gencert /etc/gencert
ADD ./pydio.conf /etc/pydio.conf
ADD ./pre_conf_pydio.sh /etc/pre_conf_pydio.sh
ADD ./configure_php_modules.sh /etc/configure_php_modules.sh
ADD ./public.htaccess /etc/public.htaccess
ADD ./root.htaccess /etc/root.htaccess

RUN yum install -y wget
RUN rpm -Uvh http://dl.ajaxplorer.info/repos/pydio-release-1-1.noarch.rpm
RUN wget http://dl.fedoraproject.org/pub/epel/6/x86_64/epel-release-6-8.noarch.rpm
RUN wget http://rpms.famillecollet.com/enterprise/remi-release-6.rpm
RUN wget -q -O – http://www.atomicorp.com/installers/atomic | sh
RUN rpm -Uvh remi-release-6*.rpm epel-release-6*.rpm
RUN yum -y update
RUN yum -y install httpd php-mcrypt* ImageMagick ImageMagick-devel ImageMagick-perl gcc cc php-pecl-apc php php-mysql php-cli php-devel php-gd php-pecl-memcache php-pspell php-snmp php-xmlrpc php-xml mod_ssl openssl mysql-server mysql php-ioncube-loader
RUN chmod 0777 /etc/create.mysql
RUN chmod +x /etc/gencert.sh
RUN chmod +x /etc/pre_conf_pydio.sh
RUN chmod +x /etc/configure_php_modules.sh

# generate certificate for server
#RUN /etc/gencert.sh

# install some php modules
RUN /etc/configure_php_modules.sh

# fix lack of network file for mysql
RUN echo -e "NETWORKING=yes" > /etc/sysconfig/network

# install pydio
RUN yum install -y --disablerepo=pydio-testing pydio

# pre-configure pydio
RUN /etc/pre_conf_pydio.sh

# install supervisord
RUN yum install -y python-pip && pip install "pip>=1.4,<1.5" --upgrade
RUN pip install supervisor
VOLUME /var/lib/pydio
VOLUME /var/lib/mysql
VOLUME /etc/pki/tls
VOLUME /var/cache/pydio

EXPOSE 443
CMD ["supervisord", "-n"]
