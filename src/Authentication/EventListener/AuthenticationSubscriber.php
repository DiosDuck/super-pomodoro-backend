<?php

declare(strict_types=1);

namespace App\Authentication\EventListener;

use App\Authentication\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class AuthenticationSubscriber implements EventSubscriberInterface {
    public function __construct(
        private readonly EntityManagerInterface $entityManager
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
