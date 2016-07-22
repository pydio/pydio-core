<?php

return array (
    'operations' =>
        array (
            'certificates.list' =>
                array (
                    'httpMethod' => 'GET',
                    'uri' => 'services/storageservices',
                    'description' => 'The List Storage Accounts operation lists the storage accounts that are available in the specified subscription.',
                    'responseModel' => 'StorageList',
                ),
        ),
    'models' =>
        array (
            'StorageList' =>
                array (
                    'type' => 'array',
                    'name' => 'certificates',
                    'sentAs' => 'SubscriptionCertificate',
                    'location' => 'xml',
                    'items' =>
                        array (
                            'type' => 'object',
                        ),
                ),
            'Storage' =>
                array (
                    'type' => 'object',
                    'additionalProperties' =>
                        array (
                            'location' => 'xml',
                        ),
                ),
        ),
    'imports' =>
        array (
            0 => 'description_import_import.php',
        ),
);