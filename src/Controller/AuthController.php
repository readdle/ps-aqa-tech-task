<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AuthService;
use App\Service\AppMode;
use App\Entity\User;
use OpenApi\Attributes as OA;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class AuthController extends ApiController
{
    private const MIN_PASSWORD_LENGTH = 8;
    private const MAX_PASSWORD_LENGTH = 255;

    public function __construct(
        private readonly AuthService $authService,
        private readonly MailerInterface $mailer,
        private readonly AppMode $appMode,
    ) {
    }

    #[Route('/api/auth/signup', name: 'api_auth_signup', methods: ['POST'])]
    #[OA\Tag(name: 'Login Check')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                new OA\Property(property: 'password', type: 'string', example: 'secret123', maxLength: 255, minLength: 8),
            ],
        ),
    )]
    #[OA\Response(
        response: Response::HTTP_CREATED,
        description: 'Sign-up request accepted, confirmation email sent.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Confirmation code sent to email.'),
            ],
        ),
    )]
    #[OA\Response(
        response: Response::HTTP_BAD_REQUEST,
        description: 'Validation/business error.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'User already exists.'),
            ],
        ),
    )]
    #[OA\Response(
        response: Response::HTTP_INTERNAL_SERVER_ERROR,
        description: 'Internal server error.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: '500'),
            ],
        ),
    )]
    public function signUp(Request $request): JsonResponse
    {
        try {
            $payload = $this->payload($request);
            $email = $this->validatedEmail($payload);
            $password = $this->validatedPassword($payload);
            $code = $this->authService->signUp($email, $password);
            $this->sendConfirmationCode($email, $code);

            return $this->json($this->signupSuccessPayload(), $this->appMode->isBroken() ? random_int(200, 299) : Response::HTTP_CREATED);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        } catch (Throwable $e) {
            return $this->error($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/auth/confirm', name: 'api_auth_confirm', methods: ['POST'])]
    #[OA\Tag(name: 'Login Check')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'code'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                new OA\Property(property: 'code', type: 'string', pattern: '^\d{6}$', example: '123456'),
            ],
        ),
    )]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Account confirmed.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...'),
                new OA\Property(property: 'message', type: 'string', example: 'Account confirmed.'),
            ],
        ),
    )]
    #[OA\Response(
        response: Response::HTTP_BAD_REQUEST,
        description: 'Validation/business error.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Invalid or expired confirmation code.'),
            ],
        ),
    )]
    #[OA\Response(
        response: Response::HTTP_INTERNAL_SERVER_ERROR,
        description: 'Internal server error.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: '500'),
            ],
        ),
    )]
    public function confirm(Request $request): JsonResponse
    {
        try {
            $payload = $this->payload($request);
            $email = $this->validatedEmail($payload);
            $code = $this->validatedConfirmationCode($payload);
            $token = $this->authService->confirmSignUp($email, $code);

            return $this->json($this->confirmSuccessPayload($token), $this->appMode->isBroken() ? random_int(200, 299) : Response::HTTP_CREATED);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        } catch (Throwable $e) {
            return $this->error($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/auth/me', name: 'api_auth_me', methods: ['GET'])]
    #[OA\Tag(name: 'Login Check')]
    #[OA\Parameter(
        name: 'Authorization',
        description: 'Bearer JWT token.',
        in: 'header',
        required: true,
        schema: new OA\Schema(type: 'string', example: 'Bearer <token>'),
    )]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Current authenticated user.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'string', format: 'uuid', example: 'f0f04231-8b66-4e5f-9d55-2eead364ea02'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
            ],
        ),
    )]
    #[OA\Response(
        response: Response::HTTP_UNAUTHORIZED,
        description: 'Unauthorized.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Unauthorized.'),
            ],
        ),
    )]
    #[OA\Response(
        response: Response::HTTP_INTERNAL_SERVER_ERROR,
        description: 'Internal server error.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: '500'),
            ],
        ),
    )]
    public function me(): JsonResponse
    {
        try {
            $user = $this->getUser();
            if (!$user instanceof User) {
                throw new RuntimeException('Unauthorized.');
            }

            return $this->json($this->meSuccessPayload($user));
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), Response::HTTP_UNAUTHORIZED);
        } catch (Throwable $e) {
            return $this->error($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function sendConfirmationCode(string $email, string $code): void
    {
        if ($this->appMode->isBroken()) {
            $confirmLink = 'http://oh-no-it-is-broken-mode.example.com/app/confirm_email='.$email.'&confirm_kode='.$code;
        } else {
            $confirmLink = $this->generateUrl('app_main', [
                'confirm_email' => $email,
                'confirm_code' => $code,
            ], UrlGeneratorInterface::ABSOLUTE_URL);
        }
        $message = (new Email())
            ->from('no-reply@qa-task.local')
            ->to($email)
            ->subject('Your one-time sign-up confirmation code')
            ->text(sprintf(
                "Confirm your account by opening this link:\n%s\n\nThis link expires in 10 minutes.",
                $confirmLink,
            ))
            ->html(sprintf(
                '<p>Confirm your account by clicking this link:</p><p><a href="%s">%s</a></p><p>This link expires in 10 minutes.</p>',
                htmlspecialchars($confirmLink, ENT_QUOTES),
                htmlspecialchars($confirmLink, ENT_QUOTES),
            ));

        $this->mailer->send($message);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validatedEmail(array $payload): string
    {
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        if ('' === $email) {
            throw new RuntimeException('Email is required.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid email format.');
        }

        if (strlen($email) > 190) {
            throw new RuntimeException('Email is too long.');
        }

        return $email;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validatedPassword(array $payload): string
    {
        $password = (string) ($payload['password'] ?? '');
        if ('' === $password) {
            throw new RuntimeException('Password is required.');
        }

        $length = strlen($password);
        if ($length < self::MIN_PASSWORD_LENGTH) {
            throw new RuntimeException('Password must be at least 8 characters.');
        }

        if ($length > self::MAX_PASSWORD_LENGTH) {
            throw new RuntimeException('Password is too long.');
        }

        return $password;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validatedConfirmationCode(array $payload): string
    {
        $code = trim((string) ($payload['code'] ?? ''));
        if (!preg_match('/^\d{6}$/', $code)) {
            throw new RuntimeException('Confirmation code must be exactly 6 digits.');
        }

        return $code;
    }

    /**
     * @return array<string, string>
     */
    private function signupSuccessPayload(): array
    {
        if (!$this->appMode->isBroken()) {
            return ['message' => 'Confirmation code sent to email.'];
        }

        $variants = [
            ['message' => 'Confirmation code sent to email.'],
            ['status' => 'ok', 'message' => 'Mail queued.'],
            ['result' => 'success', 'message' => 'Code dispatched.'],
        ];

        return $variants[array_rand($variants)];
    }

    /**
     * @return array<string, string>
     */
    private function confirmSuccessPayload(string $token): array
    {
        if (!$this->appMode->isBroken()) {
            return [
                'token' => $token,
                'message' => 'Account confirmed.',
            ];
        }

        $variants = [
            ['token' => $token, 'message' => 'Account confirmed.'],
            ['jwt' => $token, 'message' => 'Confirmed.'],
            ['access_token' => $token, 'status' => 'ok'],
        ];

        return $variants[array_rand($variants)];
    }

    /**
     * @return array<string, string>
     */
    private function meSuccessPayload(User $user): array
    {
        if (!$this->appMode->isBroken()) {
            return [
                'id' => (string) $user->getId(),
                'email' => $user->getEmail(),
            ];
        }

        $variants = [
            ['id' => (string) $user->getId(), 'email' => $user->getEmail()],
            ['user_id' => (string) $user->getId(), 'mail' => $user->getEmail()],
            ['profile' => 'ok', 'id' => (string) $user->getId(), 'email' => $user->getEmail()],
        ];

        return $variants[array_rand($variants)];
    }
}
