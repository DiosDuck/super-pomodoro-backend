<?php

declare(strict_types=1);

namespace App\Authentication\Command;

use App\Authentication\Entity\TokenVerification;
use App\Authentication\Repository\TokenVerificationRepository;
use App\Authentication\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:token:cleaner',
    description: 'Clears all tokens from DB which expired. If there is also unactive users caused by unactive token, they will get removed too'
)]
class TokenCleanerCommand extends Command {
    public function __construct(
        private TokenVerificationRepository $tokenVerificationRepository,
        private UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    public function __invoke(OutputInterface $output): int
    {
        $output->writeln('Starting to delete unecessary tokens');

        $userIds = array_map(
            fn (TokenVerification $token) => $token->getUser()->getId(),
            $this->tokenVerificationRepository->getAllUnusedExpiredValidationToken()
        );

        if ($userIds) {
            $output->writeln(sprintf('Found %d unconfirmed users', count($userIds)));
            $deletedCount = $this->userRepository->deleteAllUsersIn($userIds);
            $output->writeln(sprintf('Deleted %d unconfirmed users', $deletedCount));
        }

        $deletedCount = $this->tokenVerificationRepository->deleteAllExpiredOrUsedTokens();
        $output->writeln(sprintf('Deleted %d used or expired tokens', $deletedCount));
        $output->writeln('Finish deleting command');

        return Command::SUCCESS; 
    }
}
