<?php

declare(strict_types=1);

namespace App\Status\Controller;

use App\Status\Model\Ping;
use App\Status\Model\StatusCode;
use App\Status\Service\PHPVersionService;
use Nelmio\ApiDocBundle\Attribute\Model;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

#[Route(path: '/api/version', name: 'status_version_')]
class VersionController extends AbstractController
{
    #[Route(path: '/app', name: 'app', methods: ['GET'])]
    #[OA\Get(
        path: '/api/version/app',
        summary: 'Version of the app',
        tags: ['Utilities'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Version of the app',
        content: new OA\JsonContent(
            ref: new Model(type: Ping::class),
        ),
    )]
    public function appVersion(
        string $versionApp,
    ): JsonResponse
    {
        return $this->json(new Ping(StatusCode::OK, $versionApp));
    }

    #[Route(path: '/php', name: 'php', methods: ['GET'])]
    #[OA\Get(
        path: '/api/version/php',
        summary: 'Version of the php',
        tags: ['Utilities'],
    )]
    #[OA\Response(
        response: 200,
        description: 'Version of the PHP',
        content: new OA\JsonContent(
            ref: new Model(type: Ping::class),
        ),
    )]
    public function phpversion(
        PHPVersionService $phpVersionService,
    ): JsonResponse
    {
        return $this->json(new Ping(StatusCode::OK, $phpVersionService->getPHPVersion()));
    }
}
