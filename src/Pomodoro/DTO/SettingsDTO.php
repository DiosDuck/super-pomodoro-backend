<?php

declare(strict_types=1);

namespace App\Pomodoro\DTO;

use App\Pomodoro\Entity\Settings;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[OA\Schema(
    title: 'Pomodoro Settings Schema',
    description: 'Settings for pomodoro',
)]
class SettingsDTO {
    public function __construct(
        #[Assert\Positive]
        #[OA\Property(type: 'integer', example: 1500)]
        public int $workTimeSeconds,
        #[Assert\Positive]
        #[OA\Property(type: 'integer', example: 300)]
        public int $shortBreakTimeSeconds,
        #[Assert\Positive]
        #[OA\Property(type: 'integer', example: 900)]
        public int $longBreakTimeSeconds,
        #[Assert\Positive]
        #[OA\Property(type: 'integer', example: 4)]
        public int $cyclesBeforeLongBreak,
        #[OA\Property(type: 'integer', example: 60)]
        public int $maxConfirmationTimeSeconds,
        #[OA\Property(type: 'boolean', example: true)]
        public bool $enableWaiting,
    ) {}

    #[Assert\Callback]
    public function validateMaxConfirmation(ExecutionContextInterface $context): void
    {
        if ($this->enableWaiting && $this->maxConfirmationTimeSeconds <= 0) {
            $context->buildViolation('maxConfirmationTimeSeconds must be positive when enableWaiting is true.')
                ->atPath('maxConfirmationTimeSeconds')
                ->addViolation();
        }
    }

    public static function fromSettings(Settings $settings): self
    {
        return new SettingsDTO(
            workTimeSeconds: $settings->getWorkTime(),
            shortBreakTimeSeconds: $settings->getShortBreakTime(),
            longBreakTimeSeconds: $settings->getLongBreakTime(),
            cyclesBeforeLongBreak: $settings->getCyclesBeforeLongBreak(),
            maxConfirmationTimeSeconds: $settings->getMaxConfirmationTime(),
            enableWaiting: $settings->getEnableWaiting(),
        );
    }

    public function toSettings(?Settings $settings = null): Settings
    {
        if (null === $settings) {
            $settings = new Settings();
        }

        $settings->setWorkTime($this->workTimeSeconds);
        $settings->setShortBreakTime($this->shortBreakTimeSeconds);
        $settings->setLongBreakTime($this->longBreakTimeSeconds);
        $settings->setCyclesBeforeLongBreak($this->cyclesBeforeLongBreak);
        $settings->setMaxConfirmationTime($this->maxConfirmationTimeSeconds);
        $settings->setEnableWaiting($this->enableWaiting);

        return $settings;
    }
}
