<?php

namespace App\Pomodoro\Entity;

use App\Authentication\Entity\User;
use App\Pomodoro\Repository\SettingsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SettingsRepository::class)]
#[ORM\Table(name: 'pomodoro_settings')]
class Settings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'userId', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column]
    private ?int $workTime = null;

    #[ORM\Column]
    private ?int $shortBreakTime = null;

    #[ORM\Column]
    private ?int $longBreakTime = null;

    #[ORM\Column]
    private ?int $cyclesBeforeLongBreak = null;

    #[ORM\Column]
    private ?int $maxConfirmationTime = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getWorkTime(): ?int
    {
        return $this->workTime;
    }

    public function setWorkTime(int $workTime): static
    {
        $this->workTime = $workTime;

        return $this;
    }

    public function getShortBreakTime(): ?int
    {
        return $this->shortBreakTime;
    }

    public function setShortBreakTime(int $shortBreakTime): static
    {
        $this->shortBreakTime = $shortBreakTime;

        return $this;
    }

    public function getLongBreakTime(): ?int
    {
        return $this->longBreakTime;
    }

    public function setLongBreakTime(int $longBreakTime): static
    {
        $this->longBreakTime = $longBreakTime;

        return $this;
    }

    public function getCyclesBeforeLongBreak(): ?int
    {
        return $this->cyclesBeforeLongBreak;
    }

    public function setCyclesBeforeLongBreak(int $cyclesBeforeLongBreak): static
    {
        $this->cyclesBeforeLongBreak = $cyclesBeforeLongBreak;

        return $this;
    }

    public function getMaxConfirmationTime(): ?int
    {
        return $this->maxConfirmationTime;
    }

    public function setMaxConfirmationTime(int $maxConfirmationTime): static
    {
        $this->maxConfirmationTime = $maxConfirmationTime;

        return $this;
    }
}
