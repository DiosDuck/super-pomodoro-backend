<?php

declare(strict_types=1);

namespace App\Authentication\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;
use App\Authentication\Entity\User;
use App\Authentication\DTO\UserDTO;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route(path: '/api', name: 'profile_')]
class ProfileController extends AbstractController {

    #[Route(path: '/profile', name: 'fetch', methods: ['GET'])]
    #[OA\Get(
        path: '/api/profile',
        summary: 'Profile user',
        tags: ['Profile'],
        security: [['Bearer' => []]],
    )]
    #[OA\Response(
        response: 200,
        description: 'User\'s profile data',
        content: new OA\JsonContent(
            ref: new Model(type: UserDTO::class),
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'User not logged in ',
    )]
    public function profile(#[CurrentUser] ?User $user): JsonResponse
    {
        if (null === $user) {
            return $this->json(
                ['message' => 'User not logged in'],
                JsonResponse::HTTP_UNAUTHORIZED,
            );
        }

        return $this->json(UserDTO::fromUser($user));
    }

    #[Route(path: '/profile', name: 'delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/profile',
        summary: 'Delete user',
        tags: ['Profile'],
        security: [['Bearer' => []]],
    )]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'password',
                    type: 'string',
                    example: 'password',
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'User is deleted',
    )]
    #[OA\Response(
        response: 403,
        description: 'User not logged in or wrong password',
    )]
    public function deleteAccount(
        #[CurrentUser] ?User $user,
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ) : JsonResponse
    {
        if (null === $user) {
            return $this->json(
                ['message' => 'Forbidden'],
                JsonResponse::HTTP_FORBIDDEN,
            );
        }

        $password = json_decode($request->getContent(), true)['password'] ?? '';
        if (!$passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(
                ['message' => 'Forbidden'],
                JsonResponse::HTTP_FORBIDDEN,
            );
        }

        $entityManager->remove($user);
        $entityManager->flush();

        return $this->json(['message' => 'ok']);
    }
}
