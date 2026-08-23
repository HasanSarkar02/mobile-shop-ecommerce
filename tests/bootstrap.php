<?php

declare(strict_types=1);

/*
 * Test-suite bootstrap: autoload, then make sure the test database exists.
 *
 * The suite runs on MySQL rather than sqlite deliberately. DatabaseLockRetry
 * decides what to retry by inspecting MySQL error codes (1213 deadlock, 1205
 * lock wait timeout, 40001, 1412), and InventoryLockOrderTest exercises real
 * lockForUpdate row ordering. On sqlite both of those degrade to no-ops, so the
 * concurrency tests would keep passing while testing nothing.
 *
 * The cost of that choice is that the schema needs a database to live in, and a
 * missing one does not fail with "create the database" — it fails with dozens of
 * "Table 'testing.migrations' doesn't exist" errors that read like a broken
 * codebase. Creating it here removes the manual setup step entirely.
 *
 * PHPUnit applies the <env> block from phpunit.xml before loading this file
 * (TextUI\Application runs PhpHandler ahead of BootstrapLoader), and PhpHandler
 * skips any variable that is already set in the real environment unless it is
 * marked force="true". So the values read below are exactly the ones the suite
 * is about to connect with, including whatever CI exported.
 */

require __DIR__.'/../vendor/autoload.php';

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$database = getenv('DB_DATABASE') ?: 'testing';
$username = getenv('DB_USERNAME') ?: 'root';
$password = getenv('DB_PASSWORD');
$password = $password === false ? '' : $password;

try {
    $connection = new PDO(
        "mysql:host={$host};port={$port}",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );

    // Quoting the identifier rather than interpolating it raw: DB_DATABASE can
    // come from CI, and a backtick in it would otherwise break out of the name.
    $connection->exec(sprintf(
        'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
        str_replace('`', '``', $database),
    ));
} catch (PDOException $e) {
    fwrite(STDERR, <<<TXT

    Could not reach MySQL at {$host}:{$port} as '{$username}', so the '{$database}'
    test database could not be created.

    The suite requires MySQL — see the note at the top of tests/bootstrap.php for
    why sqlite is not an option here. Start MySQL, or point the suite elsewhere by
    exporting DB_HOST / DB_PORT / DB_DATABASE / DB_USERNAME / DB_PASSWORD, which
    override the defaults in phpunit.xml.

    Driver said: {$e->getMessage()}

    TXT);

    exit(1);
}
