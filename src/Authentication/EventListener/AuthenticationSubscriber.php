<?php

declare(strict_types=1);

namespace App\Authentication\EventListener;

use App\Authentication\Entity\User;
use App\Authentication\Repository\RefreshTokenRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class AuthenticationSubscriber implements EventSubscriberInterface {
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RefreshTokenRepository $refreshTokenRepository,
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
        $token = json_decode($request->getContent(), true)['refresh_token'] ?? '';
        $refreshToken = $this->refreshTokenRepository->findOneBy(['refreshToken' => $token]);
        if ($refreshToken !== null) {
            $this->entityManager->remove($refreshToken);
            $this->entityManager->flush();
        }

        $event->setResponse(new JsonResponse(['message' => 'ok']));
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
