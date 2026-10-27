<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use PDO;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

/** Sessions live in Postgres so they survive stateless, multi-instance hosting (e.g. serverless). */
final class DatabaseSessionHandler extends PdoSessionHandler
{
    public function __construct(Connection $connection)
    {
        $pdo = $connection->getNativeConnection();
        \assert($pdo instanceof PDO);

        parent::__construct($pdo, ['db_table' => 'sessions', 'lock_mode' => PdoSessionHandler::LOCK_ADVISORY]);
    }
}
