<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class HealthController extends AbstractController
{
    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function __invoke(Connection $db): JsonResponse
    {
        try {
            $db->executeQuery('SELECT 1');
            $database = 'ok';
        } catch (Throwable) {
            $database = 'down';
        }

        return new JsonResponse(['status' => 'ok' === $database ? 'ok' : 'degraded', 'database' => $database], 'ok' === $database ? 200 : 503);
    }
}
