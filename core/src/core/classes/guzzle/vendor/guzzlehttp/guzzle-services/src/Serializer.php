<?php
namespace GuzzleHttp\Command\Guzzle;

use GuzzleHttp\Command\ServiceClientInterface;
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\CommandTransaction;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Command\Guzzle\RequestLocation\BodyLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\HeaderLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\JsonLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\PostFieldLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\PostFileLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\QueryLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\XmlLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\RequestLocationInterface;
use GuzzleHttp\Utils;

/**
 * Serializes requests for a given command.
 */
class Serializer
{
    /** @var RequestLocationInterface[] */
    private $requestLocations;

    /** @var DescriptionInterface */
    private $description;

    /**
     * @param DescriptionInterface       $description
     * @param RequestLocationInterface[] $requestLocations Extra request locations
     */
    public function __construct(
        DescriptionInterface $description,
        array $requestLocations = []
    ) {
        static $defaultRequestLocations;
        if (!$defaultRequestLocations) {
            $defaultRequestLocations = [
                'body'      => new BodyLocation('body'),
                'query'     => new QueryLocation('query'),
                'header'    => new HeaderLocation('header'),
                'json'      => new JsonLocation('json'),
                'xml'       => new XmlLocation('xml'),
                'postField' => new PostFieldLocation('postField'),
                'postFile'  => new PostFileLocation('postFile')
            ];
        }

        $this->requestLocations = $requestLocations + $defaultRequestLocations;
        $this->description = $description;
    }

    public function __invoke(CommandTransaction $trans)
    {
        $request = $this->createRequest($trans);
        $this->prepareRequest($trans, $request);

        return $request;
    }

    /**
     * Prepares a request for sending using location visitors
     *
     * @param CommandTransaction $trans
     * @param RequestInterface       $request Request being created
     * @throws \RuntimeException If a location cannot be handled
     */
    protected function prepareRequest(
        CommandTransaction $trans,
        RequestInterface $request
    ) {
        $visitedLocations = [];
        $context = ['client' => $trans->client, 'command' => $trans->command];
        $operation = $this->description->getOperation($trans->command->getName());

        // Visit each actual parameter
        foreach ($operation->getParams() as $name => $param) {
            /* @var Parameter $param */
            $location = $param->getLocation();
            // Skip parameters that have not been set or are URI location
            if ($location == 'uri' || !$trans->command->hasParam($name)) {
                continue;
            }
            if (!isset($this->requestLocations[$location])) {
                throw new \RuntimeException("No location registered for $location");
            }
            $visitedLocations[$location] = true;
            $this->requestLocations[$location]->visit(
                $trans->command,
                $request,
                $param,
                $context
            );
        }

        // Ensure that the after() method is invoked for additionalParameters
        if ($additional = $operation->getAdditionalParameters()) {
            $visitedLocations[$additional->getLocation()] = true;
        }

        // Call the after() method for each visited location
        foreach (array_keys($visitedLocations) as $location) {
            $this->requestLocations[$location]->after(
                $trans->command,
                $request,
                $operation,
                $context
            );
        }
    }

    /**
     * Create a request for the command and operation
     *
     * @param CommandTransaction $trans
     *
     * @return RequestInterface
     * @throws \RuntimeException
     */
    protected function createRequest(CommandTransaction $trans)
    {
        $operation = $this->description->getOperation($trans->command->getName());

        // If the command does not specify a template, then assume the base URL
        // of the client
        if (null === ($uri = $operation->getUri())) {
            return $trans->client->createRequest(
                $operation->getHttpMethod(),
                $this->description->getBaseUrl(),
                $trans->command['request_options'] ?: []
            );
        }

        return $this->createCommandWithUri(
            $operation, $trans->command, $trans->serviceClient
        );
    }

    /**
     * Create a request for an operation with a uri merged onto a base URI
     */
    private function createCommandWithUri(
        Operation $operation,
        CommandInterface $command,
        ServiceClientInterface $client
    ) {
        // Get the path values and use the client config settings
        $variables = [];
        foreach ($operation->getParams() as $name => $arg) {
            /* @var Parameter $arg */
            if ($arg->getLocation() == 'uri') {
                if (isset($command[$name])) {
                    $variables[$name] = $arg->filter($command[$name]);
                    if (!is_array($variables[$name])) {
                        $variables[$name] = (string) $variables[$name];
                    }
                }
            }
        }

        // Expand the URI template.
        $uri = Utils::uriTemplate($operation->getUri(), $variables);

        return $client->getHttpClient()->createRequest(
            $operation->getHttpMethod(),
            $this->description->getBaseUrl()->combine($uri),
            $command['request_options'] ?: []
        );
    }
}
