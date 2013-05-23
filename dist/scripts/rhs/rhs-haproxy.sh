# DL and install haproxy binary

wget http://mirror.centos.org/centos/6/os/x86_64/Packages/haproxy-1.4.22-3.el6.x86_64.rpm
rpm -Uvh haproxy-1.4.22-3.el6.x86_64.rpm

# Correct Config File

mv /etc/haproxy/haproxy.cfg /etc/haproxy/haproxy.cfg.orig

cat > /etc/haproxy/haproxy.cfg << HAPROXYCFG
global
   log         127.0.0.1 local0
   log         127.0.0.1 local1 notice
   #log        loghost local0 info
   #debug
   #quiet
   maxconn     1024 # Total Max Connections. This is dependent on ulimit
   daemon
   nbproc      1 # Number of processing cores/cpus.
   user       haproxy
   group      haproxy

defaults
   log         global
   mode        http
   option      httplog
   option      dontlognull
   retries     3
   clitimeout  50000
   srvtimeout  30000
   contimeout  4000
   option      redispatch
   option      httpclose # Disable Keepalive


listen http_proxy 192.168.1.21:80
   mode http
   # This will enable the statistic interfaces
   # Accessible at proxy_url/haproxy?stats
   stats enable
   stats auth root:haproxy
   # Round-Robin & Session Stick on AjaXplorer
   balance roundrobin # Load Balancing algorithm
   appsession AjaXplorer len 64 timeout 3h request-learn
   option forwardfor # This sets X-Forwarded-For
   # Check the health of each server
   option httpchk HEAD /ajaxplorer/check.txt HTTP/1.0

   ## Now add there your nodes to balance
   server ajxpnode1 192.168.1.10:80 weight 1 maxconn 512 check
   server ajxpnode2 192.168.1.11:80 weight 1 maxconn 512 check
HAPROXYCFG