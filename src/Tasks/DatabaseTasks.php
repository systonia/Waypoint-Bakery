<?php

namespace Bakery\Tasks;

use Tenma\Db\Postgres;
use Waypoint\Attributes\{Inject, Manager, Task};

/**
 * The schema and the demo data, as Waypoint CLI tasks: `tenma index.php db:migrate`, `db:seed`, `db:reset`.
 * They run under tenma because Tenma's Postgres client needs its event loop.
 */
#[Manager('db')]
final class DatabaseTasks
{
    private const LOCK = 2_610_001;

    private const DIRECTORY = __DIR__ . '/../../database';

    #[Inject]
    private Postgres $db;

    /**
     * Applies database/migrations/*.sql in name order, each file once. All pending files run in one transaction,
     * so a failing file leaves the database as it was; the advisory lock lets two starts wait for each other.
     */
    #[Task('migrate')]
    public function migrate(): string
    {
        $ran = $this->db->transaction(function (Postgres $tx): array {
            $tx->execute('SELECT pg_advisory_xact_lock($1)', [self::LOCK]);
            $tx->script('CREATE TABLE IF NOT EXISTS schema_migrations (name text PRIMARY KEY, applied_at timestamptz NOT NULL DEFAULT now())');
            $applied = array_column($tx->query('SELECT name FROM schema_migrations'), 'name');
            $files = glob(self::DIRECTORY . '/migrations/*.sql') ?: [];
            sort($files);
            $ran = [];
            foreach ($files as $file) {
                if (!in_array(basename($file), $applied, true)) {
                    $tx->script(\Tenma\Fs::readFile($file));
                    $tx->execute('INSERT INTO schema_migrations (name) VALUES ($1)', [basename($file)]);
                    $ran[] = basename($file);
                }
            }
            return $ran;
        });
        return 'Applied: ' . ($ran === [] ? 'nothing new' : implode(', ', $ran)) . "\n";
    }

    /** Migrates, then loads database/seed.sql: for an empty database. */
    #[Task('seed')]
    public function seed(): string
    {
        $this->migrate();
        $this->db->script(\Tenma\Fs::readFile(self::DIRECTORY . '/seed.sql'));
        return "Seeded the demo data.\n";
    }

    /** Drops every table, then migrates and seeds. */
    #[Task('reset')]
    public function reset(): string
    {
        $this->db->script('DROP SCHEMA public CASCADE; CREATE SCHEMA public;');
        return $this->seed();
    }
}
