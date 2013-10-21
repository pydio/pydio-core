<?php

use Symfony\CS\FixerInterface;

$finder = Symfony\CS\Finder\DefaultFinder::create()
    ->exclude('vendor')
    ->exclude('core/src/plugins/index.lucene/Zend')
    ->exclude('core/src/plugins/access.s3/aS3StreamWrapper/lib')
    ->exclude('core/src/plugins/access.sftp_psl/phpseclib')
    ->exclude('core/src/plugins/auth.cas/CAS')
    ->exclude('core/src/plugins/access.dropbox/dropbox-php')
    ->exclude('core/src/plugins/editor.ckeditor/ckeditor')
    ->exclude('core/src/data')
    ->exclude('ext/cmsms/cmsms_ajaxplorer/FEUajaxplorer')
    ->exclude('jdrivers')
    ->notName('http_class.php')
    ->notName('PThumb.lib.php')
    ->notName('pclzip.lib.php')
    ->notName('svn_lib.inc.php')
    ->notName('class.AjaXplorerPublisher.php')
    ->notName('class.fsAccessDriver.php')
    ->notName('glueCode.php')
    ->notName('compat.php')
    ->in(__DIR__)
;

return Symfony\CS\Config\Config::create()
    ->finder($finder)
;
