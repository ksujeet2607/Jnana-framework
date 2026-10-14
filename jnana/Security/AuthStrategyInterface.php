<?php

namespace Jnana\Security;

interface AuthStrategyInterface
{
    public function authenticate(string $username, string $password): bool;
    public function getUser(): ?array;
    public function setCurrentUser(?array $user): void;
    public function logout(): void;
}