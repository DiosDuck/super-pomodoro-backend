<?php

declare(strict_types=1);

namespace App\Tests\Pomodoro\DTO;

use App\Pomodoro\DTO\SettingsDTO;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SettingsDTOTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->validator = static::getContainer()->get(ValidatorInterface::class);
    }

    public function testValidDtoHasNoViolations(): void
    {
        $violations = $this->validator->validate($this->makeDto());

        $this->assertCount(0, $violations);
    }

    /**
     * @return array<string, array{property: string, overrides: array<string, int>}>
     */
    public static function invalidPositiveFieldsProvider(): array
    {
        return [
            'workTimeSeconds zero' => [
                'property' => 'workTimeSeconds',
                'overrides' => ['workTimeSeconds' => 0],
            ],
            'shortBreakTimeSeconds negative' => [
                'property' => 'shortBreakTimeSeconds',
                'overrides' => ['shortBreakTimeSeconds' => -1],
            ],
            'longBreakTimeSeconds zero' => [
                'property' => 'longBreakTimeSeconds',
                'overrides' => ['longBreakTimeSeconds' => 0],
            ],
            'cyclesBeforeLongBreak zero' => [
                'property' => 'cyclesBeforeLongBreak',
                'overrides' => ['cyclesBeforeLongBreak' => 0],
            ],
        ];
    }

    /**
     * @param array<string, int> $overrides
     */
    #[DataProvider('invalidPositiveFieldsProvider')]
    public function testPositiveConstraintViolations(string $property, array $overrides): void
    {
        $violations = $this->validator->validate($this->makeDto(...$overrides));

        $this->assertGreaterThanOrEqual(1, $violations->count());

        $matched = false;
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === $property) {
                $matched = true;
                break;
            }
        }

        $this->assertTrue($matched, sprintf('Expected a violation on property "%s".', $property));
    }

    public function testMaxConfirmationZeroWithEnableWaitingTrueFails(): void
    {
        $dto = $this->makeDto(maxConfirmationTimeSeconds: 0, enableWaiting: true);

        $violations = $this->validator->validate($dto);

        $matched = false;
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === 'maxConfirmationTimeSeconds') {
                $matched = true;
                break;
            }
        }

        $this->assertTrue(
            $matched,
            'Expected an Expression violation on maxConfirmationTimeSeconds when enableWaiting is true.',
        );
    }

    public function testMaxConfirmationZeroWithEnableWaitingFalsePasses(): void
    {
        $dto = $this->makeDto(maxConfirmationTimeSeconds: 0, enableWaiting: false);

        $violations = $this->validator->validate($dto);

        $this->assertCount(0, $violations);
    }

    private function makeDto(
        int $workTimeSeconds = 1500,
        int $shortBreakTimeSeconds = 300,
        int $longBreakTimeSeconds = 900,
        int $cyclesBeforeLongBreak = 4,
        int $maxConfirmationTimeSeconds = 60,
        bool $enableWaiting = true,
    ): SettingsDTO {
        return new SettingsDTO(
            workTimeSeconds: $workTimeSeconds,
            shortBreakTimeSeconds: $shortBreakTimeSeconds,
            longBreakTimeSeconds: $longBreakTimeSeconds,
            cyclesBeforeLongBreak: $cyclesBeforeLongBreak,
            maxConfirmationTimeSeconds: $maxConfirmationTimeSeconds,
            enableWaiting: $enableWaiting,
        );
    }
}
