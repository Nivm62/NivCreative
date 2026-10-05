<?php
declare(strict_types=1);

namespace Nivc\Core;

/** The authenticated principal. Tenant isolation hangs off this object. */
final class Actor
{
    public function __construct(
        public readonly int $userId,
        public readonly string $role,       // admin | client
        public readonly ?int $clientId,     // null for admins
        public readonly string $name,
        public readonly string $email,
        public readonly string $locale,
    ) {
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Resolves which client's data a request may touch.
     * Clients are ALWAYS pinned to their own client_id, whatever the request says.
     * Admins may pass a client_id (or none = all clients, returned as null).
     */
    public function scopeClientId(mixed $requested): ?int
    {
        if (!$this->isAdmin()) {
            return $this->clientId;
        }
        if ($requested === null || $requested === '' || $requested === '0' || $requested === 0) {
            return null;
        }
        return filter_var($requested, FILTER_VALIDATE_INT) !== false ? (int) $requested : null;
    }

    /** Throws 404 (never reveals existence) unless this actor may access a row owned by $ownerClientId. */
    public function assertOwns(?int $ownerClientId): void
    {
        if ($this->isAdmin()) {
            return;
        }
        if ($ownerClientId === null || $this->clientId === null || $ownerClientId !== $this->clientId) {
            throw HttpException::notFound(t('error.not_found'));
        }
    }
}
