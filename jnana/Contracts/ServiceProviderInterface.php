<?php

namespace Jnana\Contracts;

use DI\ContainerBuilder;

interface ServiceProviderInterface
{
    public function register(ContainerBuilder $container): void;
}