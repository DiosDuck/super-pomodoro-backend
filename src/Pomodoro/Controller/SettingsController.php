<?php

declare(strict_types=1);

namespace App\Pomodoro\Controller;

use App\Authentication\Entity\User;
use App\Pomodoro\DTO\SettingsDTO;
use App\Pomodoro\Repository\SettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

#[Route(path: '/api/pomodoro/settings', name: 'settings_')]
class SettingsController extends AbstractController {
    #[Route(path: '', name: '_get', methods: ['GET'])]
    #[OA\Get(
        path: '/api/pomodoro/settings',
        operationId: 'getPomodoroSettings',
        description: 'Get Pomodoro Settings for user',
        summary: 'Get Pomodoro Settings',
        security: [['Bearer' => []]],
        tags: ['Pomodoro'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Pomodoro Settings',
        content: new OA\JsonContent(
            ref: new Model(type: SettingsDTO::class),
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'User not signed in',
    )]
    #[OA\Response(
        response: 404,
        description: 'Settings not found',
    )]
    public function getSettings(
        SettingsRepository $settingRepository,
        #[CurrentUser] ?User $user,
    ): JsonResponse {
        if ($user === null) {
            return $this->json(
                ['message' => 'User not logged in'],
                JsonResponse::HTTP_UNAUTHORIZED,
            );
        }

        $settings = $settingRepository->findOneByUser($user);
        if (null === $settings) {
            return $this->json(
                ['message' => 'Settings not found'], 
                JsonResponse::HTTP_NOT_FOUND
            );
        }

        return $this->json(SettingsDTO::fromSettings($settings));
    }

    #[Route(path: '', name: '_put', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/pomodoro/settings',
        operationId: 'putPomodoroSettings',
        description: 'Create new Pomodoro Settings for user',
        summary: 'Create Pomodoro Settings',
        security: [['Bearer' => []]],
        tags: ['Pomodoro'],
    )]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            ref: new Model(type: SettingsDTO::class)
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Pomodoro Setting are updated',
    )]
    #[OA\Response(
        response: 401,
        description: 'User not signed in',
    )]
    #[OA\Response(
        response: 422,
        description: 'Bad format',
    )]
    public function createSettings(
        #[MapRequestPayload] SettingsDTO $settingsDTO,
        EntityManagerInterface $entityManager,
        #[CurrentUser] ?User $user,
    ): JsonResponse {
        if ($user === null) {
            return $this->json(
                ['message' => 'User not logged in'],
                JsonResponse::HTTP_UNAUTHORIZED,
            );
        }

        if (!$settingsDTO->isValid()) {
            return $this->json(
                ['message' => 'Bad format'],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $settings = $settingsDTO->toSettings();
        $settings->setUser($user);

        $entityManager->persist($settings);
        $entityManager->flush();

        return $this->json(['message' => 'ok']);
    }

    #[Route(path: '', name: '_post', methods: ['POST'])]
    #[OA\Post(
        path: '/api/pomodoro/settings',
        operationId: 'postPomodoroSettings',
        description: 'Update Pomodoro Settings for user',
        summary: 'Update Pomodoro Settings',
        security: [['Bearer' => []]],
        tags: ['Pomodoro'],
    )]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            ref: new Model(type: SettingsDTO::class)
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Pomodoro Setting are updated',
    )]
    #[OA\Response(
        response: 401,
        description: 'User not signed in',
    )]
    #[OA\Response(
        response: 404,
        description: 'Settings not found',
    )]
    #[OA\Response(
        response: 422,
        description: 'Bad format',
    )]
    public function updateSettings(
        #[MapRequestPayload] SettingsDTO $settingsDTO,
        SettingsRepository $settingRepository,
        EntityManagerInterface $entityManager,
        #[CurrentUser] ?User $user,
    ): JsonResponse {
        if ($user === null) {
            return $this->json(
                ['message' => 'User not logged in'],
                JsonResponse::HTTP_UNAUTHORIZED,
            );
        }

        if (!$settingsDTO->isValid()) {
            return $this->json(
                ['message' => 'Bad format'],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $settings = $settingRepository->findOneByUser($user);
        if (null === $settings) {
            return $this->json(
                ['message' => 'Settings not found'], 
                JsonResponse::HTTP_NOT_FOUND
            );
        }

        $settings = $settingsDTO->toSettings($settings);
        
        $entityManager->persist($settings);
        $entityManager->flush();

        return $this->json(['message' => 'ok']);
    }
}
