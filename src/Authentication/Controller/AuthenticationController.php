<?php

declare(strict_types=1);

namespace App\Authentication\Controller;

use App\Authentication\DTO\RegisterUserDTO;
use App\Authentication\DTO\VerificationTokenRequestDTO;
use App\Authentication\Enum\TokenTypeEnum;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Authentication\Exception\InvalidRegisterDataException;
use App\Authentication\Exception\InvalidTokenException;
use App\Authentication\Service\AuthenticationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\BodyRendererInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

#[Route(path: '/api/auth/register', name: 'api_auth_register_')]
class AuthenticationController extends AbstractController {
    public function __construct(
        private readonly AuthenticationService $authenticationService,
        private EntityManagerInterface $entityManager,
        private string $frontendBaseUrl,
    ) { }

    #[Route(path: '', name: 'index', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/auth/register',
        summary: 'Register user',
        tags: ['Authentication'],
    )]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            ref: new Model(type: RegisterUserDTO::class),
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'User has been registered',
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid user data',
    )]
    #[OA\Response(
        response: 429,
        description: 'Too many requests for respective IP',
    )]
    public function register(
        #[MapRequestPayload] RegisterUserDTO $registerUser,
        RateLimiterFactoryInterface $registerAccountLimiter,
        MailerInterface $mailer,
        BodyRendererInterface $bodyRenderer,
        Request $request,
    ): JsonResponse {
        try {
            $user = $this->authenticationService->getUserFromRegisterData($registerUser);
        } catch (InvalidRegisterDataException) {
            return $this->json(
                ['message' => 'Invalid user data'],
                JsonResponse::HTTP_BAD_REQUEST
            );
        }

        $limiter = $registerAccountLimiter->create($request->getClientIp());
        if (false === $limiter->consume(1)->isAccepted()) {
            return $this->json(
                ['message' => 'Too many requests'],
                JsonResponse::HTTP_TOO_MANY_REQUESTS,
            );
        }

        $createdToken = $this->authenticationService->createToken(
            TokenTypeEnum::TOKEN_EMAIL_VERIFICATION, $user
        );

        $this->entityManager->persist($user);
        $this->entityManager->persist($createdToken->tokenVerification);
        $this->entityManager->flush();

        $queryParam = http_build_query(
            [
                'token' => $createdToken->unhashedToken, 
                'id' => $user->getId(),
            ]
        );

        $email = new TemplatedEmail();
        $email->to($user->getEmail())
            ->subject('Registering Account')
            ->htmlTemplate('@authentication/email/register.html.twig')
            ->context([
                'displayName' => $user->getDisplayName(),
                'url' => sprintf(
                    '%s/verify-email/register?%s',
                    $this->frontendBaseUrl,
                    $queryParam,
                ),
                'username' => $user->getUsername(),
            ])
        ;

        $bodyRenderer->render($email);
        $mailer->send($email);

        return $this->json(['message' => 'ok']);
    }

    #[Route(path: '/verify-email', name: 'verify-email', methods: ['POST'])]
    #[OA\Post(
        path: '/api/auth/register/verify-email',
        summary: 'Verify user',
        tags: ['Authentication'],
    )]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            ref: new Model(type: VerificationTokenRequestDTO::class),
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'User has been activated',
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid token data',
    )]
    public function verifyEmail(
        #[MapRequestPayload] VerificationTokenRequestDTO $verificationTokenRequest,
    ): JsonResponse
    {
        try {
            $verificationToken = $this->authenticationService->getValidTokenForUser(
                $verificationTokenRequest->id,
                TokenTypeEnum::TOKEN_EMAIL_VERIFICATION,
                $verificationTokenRequest->token,
            );
        } catch (InvalidTokenException $e) {
            return $this->json(['message' => 'Bad Request'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $user = $verificationToken->getUser();
        $user->setIsActive(true)
            ->setActivatedAt(new DateTimeImmutable())
        ;
        $verificationToken->setIsUsed(true);

        $this->entityManager->persist($user);
        $this->entityManager->persist($verificationToken);
        $this->entityManager->flush();

        return $this->json(['message' => 'ok']);
    }
}
