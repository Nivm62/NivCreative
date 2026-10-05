# Migrations

`schema.sql` creates a fresh install (idempotent `CREATE TABLE IF NOT EXISTS`).
For changes after the first release add numbered SQL files here, e.g. `002_add_something.sql`.
`php bin/migrate.php` applies every file that has not been applied yet (tracked in the `settings` table, key `migrations_applied`).
Run it after uploading a new version.
