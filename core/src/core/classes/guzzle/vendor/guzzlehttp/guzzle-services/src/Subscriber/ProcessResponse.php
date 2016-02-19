<?php
namespace GuzzleHttp\Command\Guzzle\Subscriber;

use GuzzleHttp\Command\Guzzle\DescriptionInterface;
use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Guzzle\ResponseLocation\JsonLocation;
use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Command\Guzzle\ResponseLocation\ResponseLocationInterface;
use GuzzleHttp\Command\Guzzle\ResponseLocation\BodyLocation;
use GuzzleHttp\Command\Guzzle\ResponseLocation\StatusCodeLocation;
use GuzzleHttp\Command\Guzzle\ResponseLocation\ReasonPhraseLocation;
use GuzzleHttp\Command\Guzzle\ResponseLocation\HeaderLocation;
use GuzzleHttp\Command\Guzzle\ResponseLocation\XmlLocation;

/**
 * Subscriber used to create response models based on an HTTP response and
 * a service description.
 *
 * Response location visitors are registered with this subscriber to handle
 * locations (e.g., 'xml', 'json', 'header'). All of the locations of a response
 * model that will be visited first have their ``before`` method triggered.
 * After the before method is called on every visitor that will be walked, each
 * visitor is triggered using the ``visit()`` method. After all of the visitors
 * are visited, the ``after()`` method is called on each visitor. This is the
 * place in which you should handle things like additionalProperties with
 * custom locations (i.e., this is how it is handled in the JSON visitor).
 */
class ProcessResponse implements SubscriberInterface
{
    /** @var ResponseLocationInterface[] */
    private $responseLocations;

    /** @var DescriptionInterface */
    private $description;

    /**
     * @param DescriptionInterface        $description
     * @param ResponseLocationInterface[] $responseLocations Extra response locations
     */
    public function __construct(
        DescriptionInterface $description,
        array $responseLocations = []
    ) {
        static $defaultResponseLocations;
        if (!$defaultResponseLocations) {
            $defaultResponseLocations = [
                'body'         => new BodyLocation('body'),
                'header'       => new HeaderLocation('header'),
                'reasonPhrase' => new ReasonPhraseLocation('reasonPhrase'),
                'statusCode'   => new StatusCodeLocation('statusCode'),
                'xml'          => new XmlLocation('xml'),
                'json'         => new JsonLocation('json')
            ];
        }

        $this->responseLocations = $responseLocations + $defaultResponseLocations;
        $this->description = $description;
    }

    public function getEvents()
    {
        return ['process' => ['onProcess']];
    }

    public function onProcess(ProcessEvent $event)
    {
        // Only add a result object if no exception was encountered.
        if ($event->getException()) {
            return;
        }

        $command = $event->getCommand();

        // Do not overwrite a previous result
        if ($event->getResult()) {
            return;
        }

        $operation = $this->description->getOperation($command->getName());

        // Add a default Model as the result if no matching schema was found.
        if (!($modelName = $operation->getResponseModel())) {
            $event->setResult([]);
            return;
        }

        $model = $operation->getServiceDescription()->getModel($modelName);
        if (!$model) {
            throw new \RuntimeException("Unknown model: {$modelName}");
        }

        $event->setResult($this->visit($model, $event));
    }

    protected function visit(Parameter $model, ProcessEvent $event)
    {
        $result = [];
        $context = ['client' => $event->getClient(), 'visitors' => []];
        $command = $event->getCommand();
        $response = $event->getResponse();

        if ($model->getType() == 'object') {
            $this->visitOuterObject($model, $result, $command, $response, $context);
        } elseif ($model->getType() == 'array') {
            $this->visitOuterArray($model, $result, $command, $response, $context);
        } else {
            throw new \InvalidArgumentException('Invalid response model: ' . $model->getType());
        }

        // Call the after() method of each found visitor
        foreach ($context['visitors'] as $visitor) {
            $visitor->after($command, $response, $model, $result, $context);
        }

        return $result;
    }

    private function triggerBeforeVisitor(
        $location,
        Parameter $model,
        array &$result,
        CommandInterface $command,
        ResponseInterface $response,
        array &$context
    ) {
        if (!isset($this->responseLocations[$location])) {
            throw new \RuntimeException("Unknown location: $location");
        }

        $context['visitors'][$location] = $this->responseLocations[$location];

        $this->responseLocations[$location]->before(
            $command,
            $response,
            $model,
            $result,
            $context
        );
    }

    private function visitOuterObject(
        Parameter $model,
        array &$result,
        CommandInterface $command,
        ResponseInterface $response,
        array &$context
    ) {
        $parentLocation = $model->getLocation();

        // If top-level additionalProperties is a schema, then visit it
        $additional = $model->getAdditionalProperties();
        if ($additional instanceof Parameter) {
            // Use the model location if none set on additionalProperties.
            $location = $additional->getLocation() ?: $parentLocation;
            $this->triggerBeforeVisitor(
                $location, $model, $result, $command, $response, $context
            );
        }

        // Use 'location' from all individual defined properties, but fall back
        // to the model location if no per-property location is set. Collect
        // the properties that need to be visited into an array.
        $visitProperties = [];
        foreach ($model->getProperties() as $schema) {
            $location = $schema->getLocation() ?: $parentLocation;
            if ($location) {
                $visitProperties[] = [$location, $schema];
                // Trigger the before method on each unique visitor location
                if (!isset($context['visitors'][$location])) {
                    $this->triggerBeforeVisitor(
                        $location, $model, $result, $command, $response, $context
                    );
                }
            }
        }

        // Actually visit each response element
        foreach ($visitProperties as $prop) {
            $this->responseLocations[$prop[0]]->visit(
                $command, $response, $prop[1], $result, $context
            );
        }
    }

    private function visitOuterArray(
        Parameter $model,
        array &$result,
        CommandInterface $command,
        ResponseInterface $response,
        array &$context
    ) {
        // Use 'location' defined on the top of the model
        if (!($location = $model->getLocation())) {
            return;
        }

        if (!isset($foundVisitors[$location])) {
            $this->triggerBeforeVisitor(
                $location, $model, $result, $command, $response, $context
            );
        }

        // Visit each item in the response
        $this->responseLocations[$location]->visit(
            $command, $response, $model, $result, $context
        );
    }
}
