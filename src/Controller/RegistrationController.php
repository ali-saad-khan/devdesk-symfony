<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly VerifyEmailHelperInterface $verifyEmailHelper,
        private readonly MailerInterface $mailer,
        #[Autowire('%app.mail_from%')] private readonly string $from,
        #[Autowire('%app.require_email_verification%')] private readonly bool $requireVerification,
    ) {
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
        #[Autowire(service: 'limiter.registration')] RateLimiterFactory $registrationLimiter,
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$registrationLimiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
                $this->addFlash('error', 'Too many sign-ups from your address. Try again later.');

                return $this->redirectToRoute('app_register');
            }

            $user->setPassword($hasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
            // Demo deployments without a mail provider can turn verification off (REQUIRE_EMAIL_VERIFICATION=0).
            $user->setIsVerified(!$this->requireVerification);
            $em->persist($user);
            $em->flush();

            if (!$this->requireVerification) {
                $this->addFlash('success', 'Account created. You can sign in now.');

                return $this->redirectToRoute('app_login');
            }

            $signature = $this->verifyEmailHelper->generateSignature('app_verify_email', (string) $user->getId(), $user->getEmail(), ['id' => $user->getId()]);
            $this->mailer->send(
                (new TemplatedEmail())
                    ->from(new Address($this->from, 'DevDesk'))
                    ->to(new Address($user->getEmail(), $user->getName()))
                    ->subject('Confirm your DevDesk email')
                    ->htmlTemplate('email/verify.html.twig')
                    ->context(['signedUrl' => $signature->getSignedUrl(), 'user' => $user]),
            );

            $this->addFlash('success', 'Account created. Check your inbox for the confirmation link.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', ['form' => $form]);
    }

    #[Route('/verify/email', name: 'app_verify_email', methods: ['GET'])]
    public function verifyEmail(Request $request, UserRepository $users, EntityManagerInterface $em): Response
    {
        $user = null !== $request->query->get('id') ? $users->find($request->query->getInt('id')) : null;
        if (!$user instanceof User) {
            $this->addFlash('error', 'This confirmation link is not valid.');

            return $this->redirectToRoute('app_register');
        }

        try {
            $this->verifyEmailHelper->validateEmailConfirmationFromRequest($request, (string) $user->getId(), $user->getEmail());
        } catch (VerifyEmailExceptionInterface) {
            $this->addFlash('error', 'This confirmation link is invalid or has expired.');

            return $this->redirectToRoute('app_register');
        }

        $user->setIsVerified(true);
        $em->flush();
        $this->addFlash('success', 'Email confirmed. You can sign in now.');

        return $this->redirectToRoute('app_login');
    }
}
