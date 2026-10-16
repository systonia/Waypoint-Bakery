<?php

namespace Bakery\Backoffice;

/** Someone who works in the bakery, as signed in: from the users table, or from the JWT of a request. */
final readonly class User
{
    public function __construct(
        public int $id,
        public string $name,
        public string $role,
    ) {
    }

    /** The user a request's JWT names, null without one. */
    public static function fromJwt(mixed $jwt): ?self
    {
        if (!is_array($jwt) || !is_string($jwt['role'] ?? null)) {
            return null;
        }
        return new self((int) ($jwt['sub'] ?? 0), (string) ($jwt['name'] ?? ''), $jwt['role']);
    }

    /** @return array{sub: int, name: string, role: string} the JWT's payload */
    public function claims(): array
    {
        return ['sub' => $this->id, 'name' => $this->name, 'role' => $this->role];
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['owner', 'staff'], true);
    }
}
