<?php

namespace Bakery\Pickup;

use Tenma\Db\Postgres;
use Waypoint\Attributes\Inject;

final class OpeningHours
{
    #[Inject]
    private Postgres $db;

    /** @return array<array<string, mixed>> ISO weekday (1 = Monday) => weekday, opens and closes; closed days are missing */
    public function week(): array
    {
        $rows = $this->db->query("SELECT weekday, to_char(opens, 'HH24:MI') AS opens, to_char(closes, 'HH24:MI') AS closes FROM opening_hours");
        return array_column($rows, null, 'weekday');
    }
}
