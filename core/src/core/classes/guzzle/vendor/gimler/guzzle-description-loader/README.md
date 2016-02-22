[![Build Status](https://secure.travis-ci.org/gimler/guzzle-description-loader.png?branch=master)](http://travis-ci.org/gimler/guzzle-description-loader)
[![Dependency Status](https://www.versioneye.com/user/projects/55f17b93d4d204001e000053/badge.png)](https://www.versioneye.com/user/projects/55f17b93d4d204001e000053)

# Guzzle Service Description Loader

A stand-alone Service Description loader for Guzzle 5.x.

## Installation

If you are using Composer, and you should, just run the following command:

``` sh
composer require "gimler/guzzle-description-loader"
```

## Supported File Formats

* Yaml
* Php
* Json

## Usage

``` php
use Guzzle\Service\Loader\JsonLoader;
use GuzzleHttp\Command\Guzzle\Description;
use Symfony\Component\Config\FileLocator;

$configDirectories = array(DESCRIPTION_PATH);
$this->locator = new FileLocator($configDirectories);

$this->jsonLoader = new JsonLoader($this->locator);

$description = $this->jsonLoader->load($this->locator->locate('description.json'));
$description = new Description($description);
```

## Sample

``` json
{
  "operations": {
    "certificates.list": {
      "httpMethod": "GET",
      "uri": "certificates",
      "description": "Lists and returns basic information about all of the management certificates associated with the specified subscription.",
      "responseModel": "CertificateList"
    }
  },
  "models": {
    "CertificateList": {
      "type": "array",
      "name": "certificates",
      "sentAs": "SubscriptionCertificate",
      "location": "xml",
      "items": {
        "type": "object"
      }
    }
  },
  "imports": [
    "description_import.json"
  ]
}
```
