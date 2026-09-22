<?php
declare(strict_types=1);

$DbUser = 'MYsqlAocount';
$DbPassword = 'MysqlPassword';
$DbName = 'Databasename';
$DbHost = 'DatabaseServer';

function CreateDatabaseConnection(): PDO
{
    global $DbHost, $DbName, $DbUser, $DbPassword;

    $Dsn = "mysql:host={$DbHost};dbname={$DbName};charset=utf8mb4";

    try {
        return new PDO($Dsn, $DbUser, $DbPassword, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $Exception) {
        throw new RuntimeException('Database connection failed: ' . $Exception->getMessage(), 0, $Exception);
    }
}
