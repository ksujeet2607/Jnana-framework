<?php

namespace Jnana\Command;

interface CommandHandlerInterface
{
    public function handle(CommandInterface $command): mixed;
}