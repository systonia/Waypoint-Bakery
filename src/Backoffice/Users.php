<?php

namespace Bakery\Backoffice;

use Tenma\Db\Postgres;
use Waypoint\Attributes\Inject;

/** The people who work in the bakery. */
final class Users
{
    // A hash to compare against when the email is unknown, so that both cases take the same time
    private const NOBODY = '$2y$12$cqbSo.j9MwrHRHzj7IVvkOcHkFG8rOnDJDWb5IXUW.1ab7KBV0q6a';

    #[Inject]
    private Postgres $db;

    /**
     * The JWT claims of the user with this email and password, null for a wrong one.
     *
     * Note: password_verify() is CPU work. Unlike a query it does not wait but compute, and holds up the other
     * requests of this worker for its few dozen milliseconds. Fine for a login form; keep loops of it out of requests.
     *
     * @return array{sub: int, name: string, role: string}|null
     */
    public function verify(string $email, string $password): ?array
    {
        $user = $this->db->first(
            "SELECT json_build_object('sub', id, 'name', name, 'role', role) AS claims, password_hash FROM users WHERE lower(email) = lower($1)",
            [trim($email)],
        );
        return password_verify($password, $user['password_hash'] ?? self::NOBODY) ? $user['claims'] ?? null : null;
    }
}
