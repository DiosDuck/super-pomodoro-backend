<?php

declare(strict_types=1);

namespace App\Pomodoro\Controller;

use App\Authentication\Entity\User;
use App\Pomodoro\DTO\SaveWorkSessionRequest;
use App\Pomodoro\DTO\SessionHistoryDailyDTO;
use App\Pomodoro\Entity\SessionSaved;
use App\Pomodoro\Service\WorkSessionService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

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
        required: true,
        content: new OA\JsonContent(
            ref: new Model(type: SaveWorkSessionRequest::class)
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Session is saved',
    )]
    #[OA\Response(
        response: 400,
        description: 'Bad request',
    )]
    #[OA\Response(
        response: 401,
        description: 'User is not logged in',
    )]
    public function saveWorkSession(
        #[CurrentUser] ?User $user,
        #[MapRequestPayload(validationFailedStatusCode: JsonResponse::HTTP_BAD_REQUEST)]
        SaveWorkSessionRequest $payload,
        WorkSessionService $workSessionService,
        EntityManagerInterface $entityManager,
    ): JsonResponse
    {
        if (null === $user) {
            return $this->json(
                ['message' => 'Unauthorized'],
                JsonResponse::HTTP_UNAUTHORIZED
            );
        }

        if (!$workSessionService->isNewWorkSessionValid($user, $payload->workTime)) {
            return $this->json(
                ['message' => 'Bad Request'],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $sessionSaved = new SessionSaved();
        $sessionSaved->setUser($user)
            ->setWorkTime($payload->workTime)
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
        response: 401,
        description: 'User is not logged in',
    )]
    public function getWorkSessionHistory(
        #[CurrentUser] ?User $user,
        #[MapQueryParameter(validationFailedStatusCode: JsonResponse::HTTP_BAD_REQUEST)]
        int $timestamp,
        WorkSessionService $workSessionService,
    ): JsonResponse
    {
        if (null === $user) {
            return $this->json(
                ['message' => 'Unauthorized'],
                JsonResponse::HTTP_UNAUTHORIZED
            );
        }

        return $this->json(
            $workSessionService->getHistoryForAWeek($user, $timestamp)
        );
    }
}
