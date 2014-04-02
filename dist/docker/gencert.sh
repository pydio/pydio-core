# generate certificate (selfsign) for apache
/bin/sh /etc/gencert
# override pydio.conf when we use SSL
sed -i "s/localhost.crt/pydio.csr/g" /etc/httpd/conf.d/ssl.conf
sed -i "s/localhost.key/pydio.pem/g" /etc/httpd/conf.d/ssl.conf
