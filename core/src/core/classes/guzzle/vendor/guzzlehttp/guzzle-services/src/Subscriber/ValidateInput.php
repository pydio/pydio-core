<?php
namespace GuzzleHttp\Command\Guzzle\Subscriber;

use GuzzleHttp\Command\Guzzle\DescriptionInterface;
use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Command\Guzzle\SchemaValidator;
use GuzzleHttp\Command\Event\InitEvent;

/**
 * Subscriber used to validate command input against a service description.
 */
class ValidateInput implements SubscriberInterface
{
    /** @var SchemaValidator */
    private $validator;

    /** @var DescriptionInterface */
    private $description;

    public function __construct(
        DescriptionInterface $description,
        SchemaValidator $schemaValidator = null
    ) {
        $this->description = $description;
        $this->validator = $schemaValidator ?: new SchemaValidator();
    }

    public function getEvents()
    {
        return ['init' => ['onInit']];
    }

    public function onInit(InitEvent $event)
    {
        $command = $event->getCommand();
        $errors = [];
        $operation = $this->description->getOperation($command->getName());

        foreach ($operation->getParams() as $name => $schema) {
            $value = $command[$name];
            if (!$this->validator->validate($schema, $value)) {
                $errors = array_merge($errors, $this->validator->getErrors());
            } elseif ($value !== $command[$name]) {
                // Update the config value if it changed and no validation
                // errors were encountered
                $command[$name] = $value;
            }
        }

        if ($params = $operation->getAdditionalParameters()) {
            foreach ($command->toArray() as $name => $value) {
                // It's only additional if it isn't defined in the schema
                if (!$operation->hasParam($name)) {
                    // Always set the name so that error messages are useful
                    $params->setName($name);
                    if (!$this->validator->validate($params, $value)) {
                        $errors = array_merge(
                            $errors,
                            $this->validator->getErrors()
                        );
                    } elseif ($value !== $command[$name]) {
                        $command[$name] = $value;
                    }
                }
            }
        }

        if ($errors) {
            throw new CommandException(
                'Validation errors: ' . implode("\n", $errors),
                $event->getTransaction()
            );
        }
    }
}
