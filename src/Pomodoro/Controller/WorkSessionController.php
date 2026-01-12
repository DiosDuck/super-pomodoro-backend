<?php

declare(strict_types=1);

namespace App\Pomodoro\Controller;

use App\Authentication\Entity\User;
use App\Pomodoro\DTO\SessionHistoryDailyDTO;
use App\Pomodoro\Entity\SessionSaved;
use App\Pomodoro\Service\WorkSessionService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;

#[Route(path: '/api/pomodoro/session', name: 'session_')]
class WorkSessionController extends AbstractController {
    #[Route(path: '', name: '_put', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/pomodoro/session',
        operationId: 'putPomodoroSession',
        description: 'Save work session time used',
        summary: 'Put Pomodoro Session',
        security: [['Bearer' => []]],
        tags: ['Pomodoro Session'],
    )]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'workTime',
                    description: 'in minutes',
                    type: 'number',
                    example: 25,
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Session is saved',
    )]
    #[OA\Response(
        response: 403,
        description: 'User is not logged in',
    )]
    public function saveWorkSession(
        #[CurrentUser] ?User $user,
        WorkSessionService $workSessionService,
        EntityManagerInterface $entityManager,
        Request $request,
    ): JsonResponse
    {
        if (null === $user) {
            return $this->json(
                ['message' => 'Forbidden'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        $workTimeFloat = json_decode($request->getContent(), true)['workTime'] ?? 0;
        $workTime = intval($workTimeFloat * 60);

        if (!$workSessionService->isNewWorkSessionValid($user, $workTime)) {
            return $this->json(
                ['message' => 'Forbidden'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        $sessionSaved = new SessionSaved();
        $sessionSaved->setUser($user)
            ->setWorkTime($workTime)
            ->setCreatedAt(new DateTimeImmutable())
        ;

        $entityManager->persist($sessionSaved);
        $entityManager->flush();

        return $this->json(['message' => 'ok']);
    }

    #[Route(path: '/history', name: '_history', methods: ['GET'])]
    #[OA\Get(
        path: '/api/pomodoro/session/history',
        operationId: 'getPomodoroSessionHistory',
        description: 'Get work session time history for a user all over the last week',
        summary: 'Get Pomodoro Session History',
        security: [['Bearer' => []]],
        tags: ['Pomodoro Session'],
    )]
    #[OA\Parameter(
        name: 'timestamp',
        in: 'query',
        required: true,
        description: 'Timestamp in miliseconds',
        example: 1767484800000
    )]
    #[OA\Response(
        response: 200,
        description: 'Session history for a user all over the last week',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                ref: new Model(type: SessionHistoryDailyDTO::class)
            )
        )
    )]
    #[OA\Response(
        response: 403,
        description: 'User is not logged in',
    )]
    public function getWorkSessionHistory(
        #[CurrentUser] ?User $user,
        #[MapQueryParameter] int $timestamp,
        WorkSessionService $workSessionService,
    ): JsonResponse
    {
        if (null === $user) {
            return $this->json(
                ['message' => 'Forbidden'],
                JsonResponse::HTTP_FORBIDDEN
            );
        }

        return $this->json(
            $workSessionService->getHistoryForAWeek($user, $timestamp)
        );
    }
}
