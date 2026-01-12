<?php

declare(strict_types=1);

namespace App\Pomodoro\DTO;

use App\Pomodoro\Entity\Settings;
use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Attribute\Ignore;

#[OA\Schema(
    title: 'Pomodoro Settings Schema',
    description: 'Settings for pomodoro',
)]
class SettingsDTO {
    public function __construct(
        #[OA\Property(type: 'number', example: 25.0)]
        public float $workTime,
        #[OA\Property(type: 'number', example: 5.0)]
        public float $shortBreakTime,
        #[OA\Property(type: 'number', example: 15.0)]
        public float $longBreakTime,
        #[OA\Property(type: 'integer', example: 4)]
        public int $cyclesBeforeLongBreak,
        #[OA\Property(type: 'number', example: 1.0)]
        public float $maxConfirmationTime,
    ) {}

    public static function fromSettings(Settings $settings): self
    {
        return new SettingsDTO(
            workTime: $settings->getWorkTime() / 60,
            shortBreakTime: $settings->getShortBreakTime() / 60,
            longBreakTime: $settings->getLongBreakTime() / 60,
            cyclesBeforeLongBreak: $settings->getCyclesBeforeLongBreak(),
            maxConfirmationTime: $settings->getMaxConfirmationTime() / 60,
        );
    }

    public function toSettings(?Settings $settings = null): Settings
    {
        if (null === $settings) {
            $settings = new Settings();
        }

        $settings->setWorkTime(intval($this->workTime * 60));
        $settings->setShortBreakTime(intval($this->shortBreakTime * 60));
        $settings->setLongBreakTime(intval($this->longBreakTime * 60));
        $settings->setCyclesBeforeLongBreak($this->cyclesBeforeLongBreak);
        $settings->setMaxConfirmationTime(intval($this->maxConfirmationTime * 60));

        return $settings;
    }

    #[Ignore]
    public function isValid(): bool
    {
        return $this->workTime > 0
            && $this->shortBreakTime > 0
            && $this->longBreakTime > 0
            && $this->cyclesBeforeLongBreak > 0
            && $this->maxConfirmationTime > 0
        ;
    }
}