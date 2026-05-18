<?php

declare(strict_types=1);

namespace App\Authentication\EventListener;

use App\Authentication\Entity\User;
use App\Authentication\Service\RefreshTokenService;
use App\Authentication\Utils\Factory\RefreshTokenCookieFactory;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class AuthenticationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RefreshTokenService $refreshTokenService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => 'onLogout',
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLogout(LogoutEvent $event): void
    {
        $request = $event->getRequest();
        $presentedRefreshToken = $request->cookies->get(RefreshTokenCookieFactory::COOKIE_NAME);

        if (is_string($presentedRefreshToken) && $presentedRefreshToken !== '') {
            $this->refreshTokenService->revokeRefreshToken($presentedRefreshToken);
        }

        $response = new JsonResponse(['message' => 'ok']);
        $response->headers->clearCookie(RefreshTokenCookieFactory::COOKIE_NAME, RefreshTokenCookieFactory::PATH);
        $event->setResponse($response);
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $user->setLastLoggedIn(new DateTimeImmutable());

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
