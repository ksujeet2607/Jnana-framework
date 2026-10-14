<?php

namespace Jnana\Template;

final class TemplateContext
{
    private array $blocks = [];
    private array $data = [];

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function setBlock(string $name, string $content): void
    {
        $this->blocks[$name] = $content;
    }

    public function appendToBlock(string $name, string $content): void
    {
        $this->blocks[$name] =
            ($this->blocks[$name] ?? '') . $content;
    }

    public function getBlock(string $name): string
    {
        return $this->blocks[$name] ?? '';
    }

    public function hasBlock(string $name): bool
    {
        return array_key_exists($name, $this->blocks);
    }

    public function data(): array
    {
        return $this->data;
    }
}
