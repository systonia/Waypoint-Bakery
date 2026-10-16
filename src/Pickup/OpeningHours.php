<?php

namespace Bakery\Pickup;

use Tenma\Db\Postgres;
use Waypoint\Attributes\Inject;

final class OpeningHours
{
    #[Inject]
    private Postgres $db;

    /** @return array<int, array{opens: string, closes: string}> ISO weekday (1 = Monday) => hours; closed days are missing */
    public function week(): array
    {
        $week = [];
        foreach ($this->db->query("SELECT weekday, to_char(opens, 'HH24:MI') AS opens, to_char(closes, 'HH24:MI') AS closes FROM opening_hours") as $row) {
            $week[(int) $row['weekday']] = ['opens' => (string) $row['opens'], 'closes' => (string) $row['closes']];
        }
        return $week;
    }
}
