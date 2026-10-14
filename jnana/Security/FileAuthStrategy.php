<?php

namespace Jnana\Security;

class FileAuthStrategy implements AuthStrategyInterface
{
    private string $filePath;
    private ?array $currentUser = null;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    public function authenticate(string $username, string $password): bool
    {
        if (!file_exists($this->filePath)) {
            throw new \Exception("User file not found.");
        }

        $users = json_decode(file_get_contents($this->filePath), true);

        foreach ($users as $user) {
            if ($user['username'] === $username && password_verify($password, $user['password'])) {
                $this->currentUser = $user;
                return true;
            }
        }

        return false;
    }

    public function getUser(): ?array
    {
        return $this->currentUser;
    }

    public function setCurrentUser(?array $user): void
    {
        $this->currentUser = $user;
    }

    public function logout(): void
    {
        $this->currentUser = null;
    }
}