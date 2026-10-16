<?php
// Waypoint's CLI tasks, under tenma so that they can use Postgres:
//   tenma bin/task.php db:reset         drop, migrate and seed the database (db:migrate, db:seed on their own)
//   tenma bin/task.php i18n:extract     add new texts from the code to lang/*.json (also i18n:check, i18n:status)
// The database is DATABASE_URL from the environment, .env or config.json, as for the app.

$app = require __DIR__ . '/../app.php';

// Waiting (a query) only works in a task: the whole command runs in one
Tenma::async(fn () => exit($app->runCli()));
