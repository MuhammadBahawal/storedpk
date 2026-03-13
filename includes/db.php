<?php

declare(strict_types=1);

function db(): ?mysqli
{
    static $connection = null;

    if ($connection instanceof mysqli) {
        return $connection;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $name = getenv('DB_NAME') ?: 'store_pk';
    $port = (int) (getenv('DB_PORT') ?: 3306);

    try {
        $mysqli = @new mysqli($host, $user, $pass, $name, $port);
        if ($mysqli->connect_errno !== 0) {
            return null;
        }
        $mysqli->set_charset('utf8mb4');
        $connection = $mysqli;

        return $connection;
    } catch (Throwable $exception) {
        return null;
    }
}
