<?php

return array (
    'operations' =>
        array (
            'certificates.delete' =>
                array (
                    'httpMethod' => 'DELETE',
                    'uri' => 'services/storageservices/{name}',
                    'description' => 'The Delete Storage Account asynchronous operation deletes the specified storage account.',
                    'parameters' =>
                        array (
                            'name' =>
                                array (
                                    'type' => 'string',
                                    'location' => 'query',
                                    'required' => true,
                                ),
                        ),
                ),
        ),
);