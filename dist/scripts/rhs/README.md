This is a set of scripts allowing the deployment of AjaXplorer in a scalable and HA fashion on top of Red Hat Storage.

# Basics

AjaXplorer is an open-source alternative to Dropbox and Box.net, providing accesss to any filesystem through a neat webapp, dedicated iOS and Android native applications, as well as WebDAV. This document will describe how to deploy it efficiently in an Red Hat Storage environment, assuming you have the following set up :

+ One RHServer node for LoadBalancer proxy (using HAProxy software).
+ N RHServer node accessing the distributed replicated gluster volume.
+ Optionaly, and AD or LDAP directory to provide users (AjaXplorer has its own user system otherwise).

The idea of this setup is to actually use the gluster nodes to act as HTTP servers, in an High Availability fashion : clients will access through the proxy to either node (based on a round-robin balance), and if one node dies it will be automatically ignored. Adding a node should be a matter of minutes, mounting the gluster volume, running the AjaXplorer install script, and telling the proxy of this new node.

*NOTE* : During the AjaXplorer installation scripts, if except it is already checked, you will be asked to enable the "RHServer EUS Optional Server" channel subscription, by providing your RHN login and password. Please make sure you have them available before running the script.

# How-To

## Gluster nodes

This installation will rely on two different gluster volumes

+ */mnt/samba/ajxp-data/* Will contain all the actual data of the users. Metadata will be stored in the file system extended attributes.
+ */mnt/samba/ajxp-config/* Will contain all ajaxplorer specific configurations and internal data.

### Running AjaXplorer script on a first gluster node

The provided rhs-ajaxplorer.sh script will deploy AjaXplorer and all necessary dependancies automatically on a gluster node.

It will detect if this is the very first node to be installed, and in that case will ask you to run the AjaXplorer graphical installation by accessing this first node through a web-browser, at url http://first_node_IP/ajaxplorer/

*NOTE* : Until the new RPM package is released (final version of v5.0), please the script will directly deploy the testing channel package, ie. AjaXplorer 4.3.4. Some patches are to be applied on this version, that are contained in the rhs-patches-4.3.4.tgz file. Please extract this package content inside the /mnt/samba/ajxp-config/ folder (you should have a /mnt/samba/ajxp-config/install_patches folder after that). This must be done ON THE VERY FIRST NODE, BEFORE running the install script. For the other nodes, you won't need to re-extract the package, as the content of /ajxp-config/ will be replicated by gluster by construction.


### First-node AjaXplorer configuration

When deploying the very first AjaXplorer, whatever the node, you will have to go through the standard AjaXplorer installation wizard to setup the basic configs.

+ Choose an administrator user name and password
+ Skip Emails configuration for the moment
+ Setup the "Configurations Storage" : this defines how AjaXplorer handles its own internal configurations. As a start, we suggest using an Sqlite3-based backend, although a MySQL DB would be recommanded for scalability purpose. The SQlite file will be stored on the gluster config volume, and will be shared amongs all AjaXplorer nodes.

Execute installation and login, you're in! If you go to the Settings panel (in the admin user menu), you will here be able to switch the Authentication mechanism to LDAP or AD if necessary. Please refer to the admin guide before touching this, you could end up being logged out of the system!

### Next nodes deployment

Once you're setup with your configuration on the first AjaXplorer instance, you will be able to run the script on the other nodes, and configurations will be automatically imported from the "master". At the end of the installation, you should be notified that AjaXplorer configurations were correctly detected on the gluster volume, and check that everything is indded running as expected by accessing the new node web server : http://new_node_IP/ajaxplorer/

## Installing the Load Balancer

On the Proxy node, simply download and execute the rhs-haproxy.sh script, and the open the /etc/haproxy/haproxy.cfg
At the very end of the file, you will see the backend definition : to what IP am I listening (this will be your outside world IP), and what are the available nodes. Please modify this accordingly. Also, make sure to change the root:haproxy credential to access the haproxy statistics through the browser.

<pre><code>
listen http_proxy 192.168.1.21:80
   mode http
   # This will enable the statistic interfaces
   # Accessible at proxy_url/haproxy?stats
   stats enable
   #
   # Modify here the statistics user credential to use a secret key!
   #
   stats auth root:haproxy
   # Round-Robin & Session Stick on AjaXplorer
   balance roundrobin # Load Balancing algorithm
   appsession AjaXplorer len 64 timeout 3h request-learn
   option forwardfor # This sets X-Forwarded-For
   # Check the health of each server
   option httpchk HEAD /ajaxplorer/check.txt HTTP/1.0

   #
   # And modify here your nodes IPs
   #
   server ajxpnode1 192.168.1.10:80 weight 1 maxconn 512 check
   server ajxpnode2 192.168.1.11:80 weight 1 maxconn 512 check
   server ajxpnode3 192.168.1.12:80 weight 1 maxconn 512 check
   server ajxpnode4 192.168.1.13:80 weight 1 maxconn 512 check
</code></pre>

Once you are done, restart HAProxy using /etc/init.d/haproxy restart
Now you should be able to access the external IP htpp://external_IP/ajaxplorer/ and this will round robin the load on the various nodes.
You will also be able to access the HAProxy statistics by opening the following URL : http://external_IP/haproxy?stats and providing the credentials you have modified in the configuration file.

*NOTE* : This is a fairly simple setup for demonstrating LoadBalancing and node failing automatic detection. To provide the best High Availability configuration, you should actually double the LoadBalancer nodes, using HAProxy and Heartbeat to make sure that if one of them fall down, a slave can take the relay.
