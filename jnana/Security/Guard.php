<?php

namespace Jnana\Security;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Guard
{
    public function __construct(
        public string $guardClass,
        public array $arguments = []
    ) {}
}