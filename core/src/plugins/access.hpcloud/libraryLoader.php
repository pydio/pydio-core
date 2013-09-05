<?php
require_once($this->getBaseDir()."/HPCloud/Bootstrap.php");
\HPCloud\Bootstrap::useAutoloader();
\HPCloud\Bootstrap::useStreamWrappers();

\HPCloud\Bootstrap::setConfiguration(array(
    'username' => $this->repository->getOption("USERNAME"),
    'password' => $this->repository->getOption("PASSWORD"),
    'tenantid' => $this->repository->getOption("TENANT_ID"),
    'endpoint' => $this->repository->getOption("ENDPOINT"),
    'transport.ssl.verify' => false
));
