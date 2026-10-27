<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\SlaChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Entry point for a scheduler (e.g. Vercel Cron) on hosts with no long-running worker.
 * Vercel sends "Authorization: Bearer $CRON_SECRET". With no secret configured the endpoint is disabled.
 */
final class CronController extends AbstractController
{
    public function __construct(#[Autowire('%env(default::CRON_SECRET)%')] private readonly ?string $secret)
    {
    }

    #[Route('/cron/sla', name: 'app_cron_sla', methods: ['GET'])]
    public function sla(Request $request, SlaChecker $checker): JsonResponse
    {
        $given = (string) $request->headers->get('Authorization');
        if (null === $this->secret || '' === $this->secret || !hash_equals('Bearer '.$this->secret, $given)) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        return new JsonResponse(['breached' => $checker->run()]);
    }
}
