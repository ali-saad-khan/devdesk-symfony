<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Security\ApiTokenHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class AccountController extends AbstractController
{
    #[Route('/account', name: 'app_account', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): Response
    {
        return $this->render('account/index.html.twig', ['hasToken' => null !== $user->getApiTokenHash(), 'token' => null]);
    }

    /** Issues a new API token. The plain value is displayed once; only its hash is stored. */
    #[Route('/account/api-token', name: 'app_account_token', methods: ['POST'])]
    public function token(Request $request, #[CurrentUser] User $user, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('api-token', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $plain = 'dd_'.bin2hex(random_bytes(24));
        $user->setApiTokenHash(ApiTokenHandler::hash($plain));
        $em->flush();

        return $this->render('account/index.html.twig', ['hasToken' => true, 'token' => $plain]);
    }
}
