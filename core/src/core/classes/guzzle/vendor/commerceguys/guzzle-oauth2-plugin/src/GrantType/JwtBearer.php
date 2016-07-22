<?php

namespace CommerceGuys\Guzzle\Oauth2\GrantType;

use GuzzleHttp\ClientInterface;
use JWT;
use SplFileObject;
use InvalidArgumentException;

/**
 * JSON Web Token (JWT) Bearer Token Profiles for OAuth 2.0
 *
 * @link http://tools.ietf.org/html/draft-jones-oauth-jwt-bearer-04
 */
class JwtBearer extends GrantTypeBase
{
    protected $grantType = 'urn:ietf:params:oauth:grant-type:jwt-bearer';

    /**
     * @param ClientInterface $client
     * @param array           $config
     */
    public function __construct(ClientInterface $client, array $config = [])
    {
        parent::__construct($client, $config);

        if (!($this->config->get('private_key') instanceof SplFileObject)) {
            throw new InvalidArgumentException('private_key needs to be instance of SplFileObject');
        }
    }

    /**
     * @inheritdoc
     */
    protected function getRequired()
    {
        return array_merge(parent::getRequired(), ['private_key']);
    }

    /**
     * @inheritdoc
     */
    protected function getAdditionalOptions()
    {
        return [
            'body' => [
                'assertion' => $this->computeJwt()
            ]
        ];
    }

    /**
     * Compute JWT, signing with provided private key
     */
    protected function computeJwt()
    {
        $payload = [
            'iss' => $this->config->get('client_id'),
            'aud' => sprintf('%s/%s', rtrim($this->client->getBaseUrl(), '/'), ltrim($this->config->get('token_url'), '/')),
            'exp' => time() + 60 * 60,
            'iat' => time()
        ];

        return JWT::encode($payload, $this->readPrivateKey($this->config->get('private_key')), 'RS256');
    }

    /**
     * Read private key
     *
     * @param SplFileObject $privateKey
     *
     * @return string
     */
    protected function readPrivateKey(SplFileObject $privateKey)
    {
        $key = '';
        while (!$privateKey->eof()) {
            $key .= $privateKey->fgets();
        }
        return $key;
    }
}
