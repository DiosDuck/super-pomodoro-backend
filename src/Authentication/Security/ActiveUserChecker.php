<?php

declare(strict_types=1);

namespace App\Authentication\Security;

use App\Authentication\Entity\User;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class ActiveUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }
        
        if (!$user->isActive()) {
            throw new BadCredentialsException();
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // do nothing
    }
}
