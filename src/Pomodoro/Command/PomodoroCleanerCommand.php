<?php

declare(strict_types=1);

namespace App\Pomodoro\Command;

use App\Pomodoro\Repository\SessionSavedRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:pomodoro:cleaner',
    description: 'Clears all pomodoro session worker which are older than 8 days',
)]
class PomodoroCleanerCommand extends Command
{
    public function __construct(
        private readonly SessionSavedRepository $sessionSavedRepository,
    )
    {
        return parent::__construct();
    }

    public function __invoke(OutputInterface $output): int
    {
        $output->writeln('Starting to delete older sessions');

        $count = $this->sessionSavedRepository->deleteAllOldSessions();
        $output->writeln(
            sprintf('%d rows were deleted', $count)
        );

        $output->writeln('Finished deleting older sessions');

        return Command::SUCCESS;
    }
}