<?php
namespace GuzzleHttp\Command\Event;

/**
 * Event fired when a command is initializing before a request is serialized.
 *
 * This event is useful for adding default parameters and command validation.
 */
class InitEvent extends CommandEvent {}
