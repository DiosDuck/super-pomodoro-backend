<?php

declare(strict_types = 1);

namespace App\Health\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use App\Health\Model\Ping;
use App\Health\Model\Status;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\DBAL\Connection;

#[Route(path: '/api/health', name: 'health_')]
class HealthController extends AbstractController
{
    #[Route(path: '/ping', name: 'ping', methods: ['GET'])]
    #[OA\Get(
        path: '/api/health/ping',
        summary: 'Server check',
        tags: ['Utilities'],
    )]
    #[OA\Parameter(
        parameter: 'fail',
        name: 'fail',
        in: 'query',
        description: 'If set, it returns 500 error',
    )]
    #[OA\Response(
        response: 200,
        description: 'OK',
        content: new OA\JsonContent(
            ref: new Model(type: Ping::class),
        )
    )]
    #[OA\Response(
        response: 500,
        description: 'Internal server error',
        content: new OA\JsonContent(
            ref: new Model(type: Ping::class),
        )
    )]
    public function index(Request $request): JsonResponse
    {
        $fail = $request->query->get('fail');
        if ($fail !== null) {
            return $this->json(
                new Ping('Test error message', Status::CRIT),
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->json(new Ping('OK'));
    }

    #[Route(path: '/database', name: 'database', methods: ['GET'])]
    #[OA\Get(
        path: '/api/health/database',
        summary: 'Database check',
        tags: ['Utilities'],
    )]
    #[OA\Response(
        response: 200,
        description: 'OK',
        content: new OA\JsonContent(
            ref: new Model(type: Ping::class),
        )
    )]
    #[OA\Response(
        response: 500,
        description: 'Internal server error',
        content: new OA\JsonContent(
            ref: new Model(type: Ping::class),
        )
    )]
    public function database(Connection $connection): JsonResponse
    {
        try {
            $connection->executeQuery('SELECT 1');

            return $this->json(new Ping('OK'));
        } catch (\Throwable $e) {
            return $this->json(
                new Ping($e->getMessage(), Status::CRIT),
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
