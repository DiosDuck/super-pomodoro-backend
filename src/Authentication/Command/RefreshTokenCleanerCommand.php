<?php

declare(strict_types=1);

namespace App\Authentication\Command;

use App\Authentication\Repository\RefreshTokenRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:refresh-token:cleaner',
    description: 'Deletes expired refresh tokens and revoked refresh tokens older than the configured retention.'
)]
class RefreshTokenCleanerCommand extends Command
{
    public function __construct(
        private RefreshTokenRepository $refreshTokenRepository,
    ) {
        parent::__construct();
    }

    public function __invoke(
        OutputInterface $output,
        #[Option(description: 'Minimum age in days for a revoked token to be purged.')]
        int $daysOld = 7,
    ): int {
        $output->writeln(sprintf('Starting to delete refresh tokens older than %d day(s)', $daysOld));

        $now = new \DateTimeImmutable();
        $revokedCutoff = $now->modify("-{$daysOld} days");
        $deletedCount = $this->refreshTokenRepository->purgeExpiredAndRevoked($now, $revokedCutoff);

        $output->writeln(sprintf('Deleted %d expired or old-revoked refresh tokens', $deletedCount));
        $output->writeln('Finish deleting command');

        return Command::SUCCESS;
    }
}
