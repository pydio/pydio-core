<?php
namespace GuzzleHttp\Command\Guzzle\RequestLocation;

use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Command\Guzzle\Operation;
use GuzzleHttp\Command\CommandInterface;

/**
 * Request header location
 */
class HeaderLocation extends AbstractLocation
{
    public function visit(
        CommandInterface $command,
        RequestInterface $request,
        Parameter $param,
        array $context
    ) {
        $value = $command[$param->getName()];
        $request->setHeader($param->getWireName(), $param->filter($value));
    }

    public function after(
        CommandInterface $command,
        RequestInterface $request,
        Operation $operation,
        array $context
    ) {
        $additional = $operation->getAdditionalParameters();
        if ($additional && $additional->getLocation() == $this->locationName) {
            foreach ($command->toArray() as $key => $value) {
                if (!$operation->hasParam($key)) {
                    $request->setHeader($key, $additional->filter($value));
                }
            }
        }
    }
}
