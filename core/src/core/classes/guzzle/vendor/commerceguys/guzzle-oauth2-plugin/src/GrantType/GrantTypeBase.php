<?php

namespace CommerceGuys\Guzzle\Oauth2\GrantType;

use CommerceGuys\Guzzle\Oauth2\AccessToken;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Collection;

abstract class GrantTypeBase implements GrantTypeInterface
{
    /** @var ClientInterface The token endpoint client */
    protected $client;

    /** @var Collection Configuration settings */
    protected $config;

    /** @var string */
    protected $grantType = '';

    /**
     * @param ClientInterface $client
     * @param array           $config
     */
    public function __construct(ClientInterface $client, array $config = [])
    {
        $this->client = $client;
        $this->config = Collection::fromConfig($config, $this->getDefaults(), $this->getRequired());
    }

    /**
     * Get default configuration items.
     *
     * @return array
     */
    protected function getDefaults()
    {
        return [
            'client_secret' => '',
            'scope' => '',
            'token_url' => 'oauth2/token',
            'auth_location' => 'headers',
        ];
    }

    /**
     * Get required configuration items.
     *
     * @return string[]
     */
    protected function getRequired()
    {
        return ['client_id'];
    }

    /**
     * Get additional options, if any.
     *
     * @return array|null
     */
    protected function getAdditionalOptions()
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getToken()
    {
        $config = $this->config->toArray();

        $body = $config;
        $body['grant_type'] = $this->grantType;
        unset($body['token_url'], $body['auth_location']);

        $requestOptions = [];

        if ($config['auth_location'] !== 'body') {
            $requestOptions['auth'] = [$config['client_id'], $config['client_secret']];
            unset($body['client_id'], $body['client_secret']);
        }

        $requestOptions['body'] = $body;

        if ($additionalOptions = $this->getAdditionalOptions()) {
            $requestOptions = array_merge_recursive($requestOptions, $additionalOptions);
        }

        $response = $this->client->post($config['token_url'], $requestOptions);
        $data = $response->json();

        return new AccessToken($data['access_token'], $data['token_type'], $data);
    }
}
