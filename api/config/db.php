<?php
/**
 * Database connection — Neon PostgreSQL via PDO.
 *
 * Reads the DATABASE_URL environment variable, which Neon provides
 * in the format:
 *   postgresql://user:password@host/dbname?sslmode=require
 *
 * Returns a configured PDO instance. Throws a PDOException on
 * connection failure (caught by each endpoint's error handler).
 */

function getDbConnection(): PDO
{
    $databaseUrl = getenv('DATABASE_URL');

    if (!$databaseUrl) {
        throw new RuntimeException('DATABASE_URL environment variable is not set.');
    }

    // Parse the Neon connection string into PDO DSN components
    $parsed = parse_url($databaseUrl);

    $host   = $parsed['host']              ?? '';
    $port   = $parsed['port']              ?? 5432;
    $dbname = ltrim($parsed['path'] ?? '', '/');
    $user   = $parsed['user']              ?? '';
    $pass   = $parsed['pass']              ?? '';

    // Extract sslmode from the query string (Neon requires sslmode=require)
    $sslmode = 'require';
    if (!empty($parsed['query'])) {
        parse_str($parsed['query'], $queryParams);
        $sslmode = $queryParams['sslmode'] ?? 'require';
    }

    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode={$sslmode}";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
