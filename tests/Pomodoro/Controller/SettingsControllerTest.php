<?php

declare(strict_types=1);

namespace App\Tests\Pomodoro\Controller;

use App\Authentication\Entity\User;
use App\Pomodoro\Entity\Settings;
use App\Pomodoro\Repository\SettingsRepository;
use App\Tests\Utils\Controller\CleanWebTestCase;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;

class SettingsControllerTest extends CleanWebTestCase
{
    public function testGetSettingsNotLoggedIn(): void
    {
        $this->client->request('GET', '/api/pomodoro/settings');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testGetSettingsNotFound(): void
    {
        $this->createUser();
        $token = $this->testAndGetLoginToken('username', 'password');

        $this->client->request(
            'GET',
            '/api/pomodoro/settings',
            server: ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testGetSettingsSuccess(): void
    {
        $user = $this->createUser();
        $this->createSettings($user);
        $token = $this->testAndGetLoginToken('username', 'password');

        $this->client->request(
            'GET',
            '/api/pomodoro/settings',
            server: ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );

        $this->assertResponseIsSuccessful();
        $this->assertSame(1500, $this->testAndGetJsonResponse('workTimeSeconds'));
        $this->assertSame(300, $this->testAndGetJsonResponse('shortBreakTimeSeconds'));
        $this->assertSame(900, $this->testAndGetJsonResponse('longBreakTimeSeconds'));
        $this->assertSame(4, $this->testAndGetJsonResponse('cyclesBeforeLongBreak'));
        $this->assertSame(60, $this->testAndGetJsonResponse('maxConfirmationTimeSeconds'));
        $this->assertTrue($this->testAndGetJsonResponse('enableWaiting'));
    }

    public function testCreateSettingsNotLoggedIn(): void
    {
        $this->client->request(
            'PUT',
            '/api/pomodoro/settings',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($this->validPayload(), JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testCreateSettingsBadRequest(): void
    {
        $this->createUser();
        $token = $this->testAndGetLoginToken('username', 'password');

        $payload = $this->validPayload();
        $payload['workTimeSeconds'] = 0;

        $this->client->request(
            'PUT',
            '/api/pomodoro/settings',
            server: [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testCreateSettingsSuccess(): void
    {
        $user = $this->createUser();
        $token = $this->testAndGetLoginToken('username', 'password');

        $this->client->request(
            'PUT',
            '/api/pomodoro/settings',
            server: [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode($this->validPayload(), JSON_THROW_ON_ERROR),
        );

        $this->assertResponseIsSuccessful();

        $stored = $this->loadStoredSettings();
        $this->assertNotNull($stored);
        $this->assertSame(1500, $stored->getWorkTime());
        $this->assertSame(300, $stored->getShortBreakTime());
        $this->assertSame(900, $stored->getLongBreakTime());
        $this->assertSame(4, $stored->getCyclesBeforeLongBreak());
        $this->assertSame(60, $stored->getMaxConfirmationTime());
        $this->assertTrue($stored->getEnableWaiting());
        $this->assertSame($user->getId(), $stored->getUser()->getId());
    }

    public function testUpdateSettingsNotLoggedIn(): void
    {
        $this->client->request(
            'POST',
            '/api/pomodoro/settings',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($this->validPayload(), JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testUpdateSettingsBadRequest(): void
    {
        $user = $this->createUser();
        $this->createSettings($user);
        $token = $this->testAndGetLoginToken('username', 'password');

        $payload = $this->validPayload();
        $payload['maxConfirmationTimeSeconds'] = 0;
        $payload['enableWaiting'] = true;

        $this->client->request(
            'POST',
            '/api/pomodoro/settings',
            server: [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testUpdateSettingsNotFound(): void
    {
        $this->createUser();
        $token = $this->testAndGetLoginToken('username', 'password');

        $this->client->request(
            'POST',
            '/api/pomodoro/settings',
            server: [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode($this->validPayload(), JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testUpdateSettingsSuccess(): void
    {
        $user = $this->createUser();
        $this->createSettings($user);
        $token = $this->testAndGetLoginToken('username', 'password');

        $updatedPayload = [
            'workTimeSeconds' => 1800,
            'shortBreakTimeSeconds' => 240,
            'longBreakTimeSeconds' => 1200,
            'cyclesBeforeLongBreak' => 5,
            'maxConfirmationTimeSeconds' => 0,
            'enableWaiting' => false,
        ];

        $this->client->request(
            'POST',
            '/api/pomodoro/settings',
            server: [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode($updatedPayload, JSON_THROW_ON_ERROR),
        );

        $this->assertResponseIsSuccessful();

        $stored = $this->loadStoredSettings();
        $this->assertNotNull($stored);
        $this->assertSame(1800, $stored->getWorkTime());
        $this->assertSame(240, $stored->getShortBreakTime());
        $this->assertSame(1200, $stored->getLongBreakTime());
        $this->assertSame(5, $stored->getCyclesBeforeLongBreak());
        $this->assertSame(0, $stored->getMaxConfirmationTime());
        $this->assertFalse($stored->getEnableWaiting());
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setEmail('user@email.com');
        $user->setUsername('username');
        $user->setRoles(['ROLE_USER']);
        $user->setActivatedAt(new DateTimeImmutable());
        $user->setDisplayName('Username');
        $user->setIsActive(true);
        $user->setPassword('password');

        return $this->saveUser($user);
    }

    private function createSettings(User $user): Settings
    {
        $settings = new Settings();
        $settings->setUser($user);
        $settings->setWorkTime(1500);
        $settings->setShortBreakTime(300);
        $settings->setLongBreakTime(900);
        $settings->setCyclesBeforeLongBreak(4);
        $settings->setMaxConfirmationTime(60);
        $settings->setEnableWaiting(true);

        /** @var Settings */
        return $this->saveObjectToDatabase($settings);
    }

    /**
     * @return array{
     *     workTimeSeconds: int,
     *     shortBreakTimeSeconds: int,
     *     longBreakTimeSeconds: int,
     *     cyclesBeforeLongBreak: int,
     *     maxConfirmationTimeSeconds: int,
     *     enableWaiting: bool,
     * }
     */
    private function validPayload(): array
    {
        return [
            'workTimeSeconds' => 1500,
            'shortBreakTimeSeconds' => 300,
            'longBreakTimeSeconds' => 900,
            'cyclesBeforeLongBreak' => 4,
            'maxConfirmationTimeSeconds' => 60,
            'enableWaiting' => true,
        ];
    }

    private function loadStoredSettings(): ?Settings
    {
        /** @var SettingsRepository $repo */
        $repo = $this->client->getContainer()->get(SettingsRepository::class);

        return $repo->findOneByUser($this->getUser('username'));
    }
}
