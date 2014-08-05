<?php

require_once($this->getBaseDir()."/openstack-sdk-php/vendor/autoload.php");
use \OpenStack\Bootstrap;

Bootstrap::useStreamWrappers();

Bootstrap::setConfiguration(array(
    'username' => $this->repository->getOption("USERNAME"),
    'password' => $this->repository->getOption("PASSWORD"),
    'tenantid' => $this->repository->getOption("TENANT_ID"),
    'endpoint' => $this->repository->getOption("ENDPOINT"),
    'openstack.swift.region'   => $this->repository->getOption("REGION"),
    'transport.ssl.verify' => false
));
