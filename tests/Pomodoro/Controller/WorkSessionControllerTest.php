<?php

declare(strict_types=1);

namespace App\Tests\Pomodoro\Controller;

use App\Authentication\Entity\User;
use App\Pomodoro\Entity\SessionSaved;
use App\Tests\Utils\Controller\CleanWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

class WorkSessionControllerTest extends CleanWebTestCase
{
    public function testSaveWorkSessionNotLoggedIn(): void
    {
        $this->client->request(
            'PUT',
            '/api/pomodoro/session',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['workTime' => 1500], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testSaveWorkSessionEmptyBody(): void
    {
        $this->createUser();
        $token = $this->testAndGetLoginToken('username', 'password');

        $this->client->request(
            'PUT',
            '/api/pomodoro/session',
            server: [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSame(0, $this->countSessions());
    }

    public function testSaveWorkSessionNonNumericWorkTime(): void
    {
        $this->createUser();
        $token = $this->testAndGetLoginToken('username', 'password');

        $this->client->request(
            'PUT',
            '/api/pomodoro/session',
            server: [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(['workTime' => 'abc'], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSame(0, $this->countSessions());
    }

    public function testSaveWorkSessionZeroWorkTime(): void
    {
        $this->createUser();
        $token = $this->testAndGetLoginToken('username', 'password');

        $this->client->request(
            'PUT',
            '/api/pomodoro/session',
            server: [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(['workTime' => 0], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSame(0, $this->countSessions());
    }

    public function testSaveWorkSessionRecentPriorSessionRejected(): void
    {
        $user = $this->createUser();
        $this->seedSession($user, 1500, new DateTimeImmutable());
        $token = $this->testAndGetLoginToken('username', 'password');

        $this->client->request(
            'PUT',
            '/api/pomodoro/session',
            server: [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(['workTime' => 1500], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSame(1, $this->countSessions());
    }

    public function testSaveWorkSessionSuccessNoPriorSession(): void
    {
        $user = $this->createUser();
        $token = $this->testAndGetLoginToken('username', 'password');

        $this->client->request(
            'PUT',
            '/api/pomodoro/session',
            server: [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(['workTime' => 1500], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $this->countSessions());

        $stored = $this->loadAllSessions()[0];
        $this->assertSame(1500, $stored->getWorkTime());
        $this->assertSame($user->getId(), $stored->getUser()->getId());
    }

    public function testSaveWorkSessionSuccessOldPriorSession(): void
    {
        $user = $this->createUser();
        $this->seedSession($user, 1500, new DateTimeImmutable('-1 day'));
        $token = $this->testAndGetLoginToken('username', 'password');

        $this->client->request(
            'PUT',
            '/api/pomodoro/session',
            server: [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode(['workTime' => 1500], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseIsSuccessful();
        $this->assertSame(2, $this->countSessions());
    }

    public function testGetHistoryNotLoggedIn(): void
    {
        $this->client->request('GET', '/api/pomodoro/session/history?timestamp=1767484800000');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testGetHistoryMissingTimestamp(): void
    {
        $this->createUser();
        $token = $this->testAndGetLoginToken('username', 'password');

        $this->client->request(
            'GET',
            '/api/pomodoro/session/history',
            server: ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testGetHistoryInvalidTimestamp(): void
    {
        $this->createUser();
        $token = $this->testAndGetLoginToken('username', 'password');

        $this->client->request(
            'GET',
            '/api/pomodoro/session/history?timestamp=abc',
            server: ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testGetHistoryEmpty(): void
    {
        $this->createUser();
        $token = $this->testAndGetLoginToken('username', 'password');

        $this->client->request(
            'GET',
            sprintf('/api/pomodoro/session/history?timestamp=%d', $this->fixedLastDayTimestampMs()),
            server: ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );

        $this->assertResponseIsSuccessful();

        $payload = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($payload);
        $this->assertCount(7, $payload);

        foreach ($payload as $day) {
            $this->assertArrayHasKey('workTimeTotal', $day);
            $this->assertArrayHasKey('sessionAmount', $day);
            $this->assertArrayHasKey('timestamp', $day);
            $this->assertSame(0, $day['sessionAmount']);
            $this->assertSame(0, $day['workTimeTotal']);
        }
    }

    public function testGetHistoryWithSessions(): void
    {
        $user = $this->createUser();
        $lastDayMs = $this->fixedLastDayTimestampMs();
        $lastDay = new DateTimeImmutable(sprintf('@%d', intval($lastDayMs / 1000)));

        $this->seedSession($user, 1500, $lastDay->modify('-2 hours'));
        $this->seedSession($user, 1500, $lastDay->modify('-3 hours'));
        $this->seedSession($user, 600, $lastDay->modify('-3 days -2 hours'));

        $token = $this->testAndGetLoginToken('username', 'password');

        $this->client->request(
            'GET',
            sprintf('/api/pomodoro/session/history?timestamp=%d', $lastDayMs),
            server: ['HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token)],
        );

        $this->assertResponseIsSuccessful();

        $payload = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($payload);
        $this->assertCount(7, $payload);

        $recentBucket = $this->findBucketContaining($payload, $lastDay->modify('-2 hours'));
        $this->assertNotNull($recentBucket, 'Expected a bucket containing lastDay - 2 hours');
        $this->assertSame(2, $recentBucket['sessionAmount']);
        $this->assertSame(3000, $recentBucket['workTimeTotal']);

        $oldBucket = $this->findBucketContaining($payload, $lastDay->modify('-3 days -2 hours'));
        $this->assertNotNull($oldBucket, 'Expected a bucket containing lastDay - 3 days - 2 hours');
        $this->assertSame(1, $oldBucket['sessionAmount']);
        $this->assertSame(600, $oldBucket['workTimeTotal']);

        $emptyDays = array_filter(
            $payload,
            static fn (array $day): bool => $day['sessionAmount'] === 0,
        );
        $this->assertCount(5, $emptyDays);
    }

    private function seedSession(User $user, int $workTime, DateTimeImmutable $createdAt): SessionSaved
    {
        $session = new SessionSaved();
        $session->setUser($user);
        $session->setWorkTime($workTime);
        $session->setCreatedAt($createdAt);

        /** @var SessionSaved */
        return $this->saveObjectToDatabase($session);
    }

    private function countSessions(): int
    {
        /** @var EntityManagerInterface $em */
        $em = $this->client->getContainer()->get('doctrine')->getManager();

        return $em->getRepository(SessionSaved::class)->count([]);
    }

    /**
     * @return array<SessionSaved>
     */
    private function loadAllSessions(): array
    {
        /** @var EntityManagerInterface $em */
        $em = $this->client->getContainer()->get('doctrine')->getManager();

        return $em->getRepository(SessionSaved::class)->findAll();
    }

    private function fixedLastDayTimestampMs(): int
    {
        return (new DateTimeImmutable('2026-05-01 12:00:00 UTC'))->getTimestamp() * 1000;
    }

    /**
     * Find the daily bucket whose 24-hour window contains the given moment.
     * `getSessionHistoryForADay($user, $day)` groups by `[$day, $day + 24h)`,
     * so each returned bucket's `timestamp` is the inclusive start of its window.
     *
     * @param array<int, array{workTimeTotal: int, sessionAmount: int, timestamp: int}> $payload
     * @return array{workTimeTotal: int, sessionAmount: int, timestamp: int}|null
     */
    private function findBucketContaining(array $payload, DateTimeImmutable $moment): ?array
    {
        $momentMs = $moment->getTimestamp() * 1000;
        $oneDayMs = 24 * 60 * 60 * 1000;

        foreach ($payload as $bucket) {
            if ($momentMs >= $bucket['timestamp'] && $momentMs < $bucket['timestamp'] + $oneDayMs) {
                return $bucket;
            }
        }

        return null;
    }
}
