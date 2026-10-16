<?php

namespace Jnana\Security;

use Core\ConfigManager;
use Jnana\Database\DatabaseInterface;
use Jnana\Session\SessionManager;

class DatabaseAuthStrategy implements AuthStrategyInterface
{
    private DatabaseInterface $db;
    private SessionManager $sessionManager;
    private ConfigManager $configManager;
    private ?array $currentUser = null;
    private ?string $lastError = null;

    public function __construct(
        DatabaseInterface $db,
        SessionManager $sessionManager,
        ConfigManager $configManager
    ) {
        $this->db = $db;
        $this->sessionManager = $sessionManager;
        $this->configManager = $configManager;

        // Restore from session if available
        $user = $this->sessionManager->get('user');
        if (is_array($user)) {
            $this->currentUser = $user;
        }
    }

    public function authenticate(string $username, string $password): bool
    {
        // Load config with sensible defaults
        $table = $this->configManager->get('Auth.table', 'users');
        $usernameField = $this->configManager->get('Auth.usernameField', 'username');
        $emailField = $this->configManager->get('Auth.emailField', 'email');
        $passwordField = $this->configManager->get('Auth.passwordField', 'password_hash');
        $statusField = $this->configManager->get('Auth.statusField', 'is_active');
        $activeStatus = $this->configManager->get('Auth.activeStatusValue', true);

        foreach ([$table, $usernameField, $emailField, $passwordField, $statusField] as $identifier) {
            if ($identifier === null) {
                continue;
            }
            if (!is_string($identifier) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
                throw new \InvalidArgumentException('Invalid configured authentication database identifier.');
            }
        }

        $qb = $this->db->getConnection()->createQueryBuilder();
        $qb->select('*')
            ->from($table, 'u')
            ->where($qb->expr()->orX(
                $qb->expr()->eq("u.{$usernameField}", ':input'),
                $qb->expr()->eq("u.{$emailField}", ':input')
            ))
            ->setParameter('input', $username);

        if ($statusField !== null) {
            $qb->andWhere("u.{$statusField} = :status")
                ->setParameter('status', $activeStatus);
        }

        $user = $qb->executeQuery()->fetchAssociative();

        if (!$user) {
            $this->lastError = 'user_not_found';
            return false;
        }

        // Verify before any password mutation. Rehashing before verification would
        // let an attacker replace a stored hash by submitting an incorrect password.
        if (!PasswordHasher::verify($password, (string) ($user[$passwordField] ?? ''))) {
            $this->lastError = 'invalid_credentials';
            return false;
        }

        if (PasswordHasher::needsRehash((string) $user[$passwordField])) {
            $idField = (string) $this->configManager->get('Auth.idField', 'id');
            foreach ([$table, $passwordField, $idField] as $identifier) {
                if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
                    throw new \InvalidArgumentException('Invalid configured authentication database identifier.');
                }
            }

            $this->db->getConnection()->createQueryBuilder()
                ->update($table, 'u')
                ->set("u.{$passwordField}", ':new_hash')
                ->where("u.{$idField} = :user_id")
                ->setParameter('new_hash', PasswordHasher::hash($password))
                ->setParameter('user_id', $user[$idField])
                ->executeStatement();
        }



        // Fetch roles
        $user['roles'] = $this->getUserRoles((int)$user['id']);

        // Set current user
        $this->setCurrentUser($user);

        return true;
    }

    /**
     * Fetch user roles from pivot + roles table
     */
    private function getUserRoles(int $userId): array
    {
        $qb = $this->db->getConnection()->createQueryBuilder();
        $qb->select('r.code')
            ->from('roles', 'r')
            ->innerJoin('r', 'user_roles', 'ur', 'ur.role_id = r.id')
            ->where('ur.user_id = :uid')
            ->setParameter('uid', $userId);

        $rows = $qb->executeQuery()->fetchAllAssociative();

        return array_column($rows, 'code');
    }

    public function getUser(): ?array
    {
        return $this->currentUser;
    }

    public function setCurrentUser(?array $user): void
    {
        $this->currentUser = $user;

        if ($user === null) {
            $this->sessionManager->remove('user');
        } else {
            $this->sessionManager->set('user', $user);
        }
    }

    public function logout(): void
    {
        $this->currentUser = null;
        $this->sessionManager->remove('user');
    }

    /**
     * Get the last authentication error
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }
}
