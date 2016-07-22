guzzle-oauth2-plugin
====================

Provides an OAuth2 plugin (subscriber) for [Guzzle](http://guzzlephp.org/).

[![Build Status](https://travis-ci.org/commerceguys/guzzle-oauth2-plugin.svg)](https://travis-ci.org/commerceguys/guzzle-oauth2-plugin)
[![Code Coverage](https://scrutinizer-ci.com/g/commerceguys/guzzle-oauth2-plugin/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/commerceguys/guzzle-oauth2-plugin/?branch=master)

Version 2.x (on the `master` branch) is intended for Guzzle 5:
```json
        "commerceguys/guzzle-oauth2-plugin": "~2.0"
```

Guzzle 3 compatibility continues in the [`1.0`](https://github.com/commerceguys/guzzle-oauth2-plugin/tree/1.0) branch:
```json
        "commerceguys/guzzle-oauth2-plugin": "~1.0"
```

## Features

- Acquires access tokens via one of the supported grant types (code, client credentials,
  user credentials, refresh token). Or you can set an access token yourself.
- Supports refresh tokens (stores them and uses them to get new access tokens).
- Handles token expiration (acquires new tokens and retries failed requests).

## Running the tests

First make sure you have all the dependencies in place by running `composer install --prefer-dist`, then simply run `./bin/phpunit`.

## Example
```php
use GuzzleHttp\Client;
use CommerceGuys\Guzzle\Oauth2\GrantType\RefreshToken;
use CommerceGuys\Guzzle\Oauth2\GrantType\PasswordCredentials;
use CommerceGuys\Guzzle\Oauth2\Oauth2Subscriber;

$base_url = 'https://example.com';

$oauth2Client = new Client(['base_url' => $base_url]);

$config = [
    'username' => 'test@example.com',
    'password' => 'test password',
    'client_id' => 'test-client',
    'scope' => 'administration',
];

$token = new PasswordCredentials($oauth2Client, $config);
$refreshToken = new RefreshToken($oauth2Client, $config);

$oauth2 = new Oauth2Subscriber($token, $refreshToken);

$client = new Client([
    'defaults' => [
        'auth' => 'oauth2',
        'subscribers' => [$oauth2],
    ],
]);

$response = $client->get('https://example.com/api/user/me');

print_r($response->json());

// Use $oauth2->getAccessToken(); and $oauth2->getRefreshToken() to get tokens
// that can be persisted for subsequent requests.

```
