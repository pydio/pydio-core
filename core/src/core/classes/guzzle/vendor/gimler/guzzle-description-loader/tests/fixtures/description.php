<?php

return array(
    'operations' =>
        array(
            'certificates.add' =>
                array(
                    'httpMethod' => 'POST',
                    'uri' => 'services/storageservices',
                    'description' => 'The Create Storage Account asynchronous operation creates a new storage account in Microsoft Azure.',
                    'data' =>
                        array(
                            'xmlEncoding' => 'utf-8',
                            'xmlRoot' =>
                                array(
                                    'name' => 'CreateStorageServiceInput',
                                    'namespaces' =>
                                        array(
                                            0 => 'http://schemas.microsoft.com/windowsazure',
                                        ),
                                ),
                        ),
                    'parameters' =>
                        array(
                            'name' =>
                                array(
                                    'type' => 'string',
                                    'location' => 'xml',
                                    'sentAs' => 'ServiceName',
                                    'required' => true,
                                ),
                        ),
                    'thumbprint' =>
                        array(
                            'type' => 'string',
                            'location' => 'xml',
                            'sentAs' => 'SubscriptionCertificateThumbprint',
                            'required' => true,
                        ),
                ),
        ),
    'models' =>
        array(
            'Storage' =>
                array(
                    'type' => 'object',
                    'additionalProperties' =>
                        array(
                            'location' => 'xml',
                        ),
                ),
        ),
    'imports' =>
        array (
            0 => 'description_import.php',
        ),
);