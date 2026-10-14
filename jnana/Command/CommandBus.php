<?php

namespace Jnana\Command;

use Doctrine\Instantiator\Exception\InvalidArgumentException;
use Jnana\Events\EventInterface;

class CommandBus
{
    protected array $handlers = [];

    public function register(string $commandName, CommandHandlerInterface $handler) : void
    {
        $this->handlers[$commandName] = $handler;
    }

    public function dispatch(CommandInterface $command) : mixed
    {
        $commandClass = get_class($command);

        if(!isset($this->handlers[$commandClass])) {
            throw new InvalidArgumentException("No handler registered for {$commandClass}");
        }

        return $this->handlers[$commandClass]->handle($command);

    }

}