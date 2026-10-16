<?php

declare(strict_types=1);

namespace Jnana\Security;

use Jnana\Http\Request;
use Psr\Log\LoggerInterface;

/** Structured security audit events with sensitive-field redaction. */
final class SecurityAuditLogger
{
    public function __construct(private readonly LoggerInterface $logger) {}

    public function record(string $event, array $context = [], ?Request $request = null): void
    {
        $event = preg_match('/^[a-z0-9_.-]{1,100}$/i', $event) === 1
            ? $event
            : 'security.unknown';

        $this->logger->notice('Security audit event', [
            'audit_event' => $event,
            'correlation_id' => $request?->getAttribute('correlation_id'),
            'method' => $request?->getMethod(),
            'path' => $request?->getUri(),
            'client_ip' => $request?->getServer()['REMOTE_ADDR'] ?? null,
            'context' => $this->redact($context),
        ]);
    }

    private function redact(array $context): array
    {
        $safe = [];
        foreach ($context as $key => $value) {
            $name = (string) $key;
            if (preg_match('/password|passwd|secret|token|authorization|cookie|session|credential|api[_-]?key/i', $name)) {
                $safe[$name] = '[REDACTED]';
                continue;
            }
            if (is_array($value)) {
                $safe[$name] = $this->redact($value);
            } elseif (is_scalar($value) || $value === null) {
                $safe[$name] = is_string($value) ? (function_exists('mb_substr') ? mb_substr(str_replace(["\r", "\n"], ' ', $value), 0, 500) : substr(str_replace(["\r", "\n"], ' ', $value), 0, 500)) : $value;
            } else {
                $safe[$name] = '[' . get_debug_type($value) . ']';
            }
        }
        return $safe;
    }
}
