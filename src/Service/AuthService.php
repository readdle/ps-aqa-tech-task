<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SignupCode;
use App\Entity\User;
use App\Repository\SignupCodeRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AuthService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly SignupCodeRepository $signupCodeRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
        private readonly AppMode $appMode,
    ) {
    }

    public function signUp(string $email, string $password): string
    {
        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Invalid email format.');
        }

        if (strlen($password) < 8) {
            throw new \RuntimeException('Password must be at least 8 characters.');
        }

        $existingUser = $this->userRepository->findOneByEmail($email);
        if (null !== $existingUser && $existingUser->isVerified()) {
            throw new \RuntimeException('User already exists.');
        }

        $user = $existingUser ?? (new User())->setEmail($email);
        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $password));
        $this->entityManager->persist($user);

        $code = $this->generateOneTimeCode();
        $signupCode = (new SignupCode())
            ->setUser($user)
            ->setCodeHash(hash('sha256', $code))
            ->setExpiresAt((new \DateTimeImmutable())->modify('+10 minutes'));
        $this->entityManager->persist($signupCode);
        $this->entityManager->flush();

        return $code;
    }

    public function confirmSignUp(string $email, string $code): string
    {
        $email = strtolower(trim($email));
        $user = $this->userRepository->findOneByEmail($email);
        if (null === $user) {
            throw new \RuntimeException('User not found.');
        }

        $signupCode = $this->signupCodeRepository->findActiveCode($user, $code);
        if (null === $signupCode) {
            throw new \RuntimeException('Invalid or expired confirmation code.');
        }

        $signupCode->setUsedAt(new \DateTimeImmutable());
        $user->setIsVerified(true);

        $this->entityManager->flush();

        return $this->jwtTokenManager->create($user);
    }

    private function generateOneTimeCode(): string
    {
        return (string) random_int(100000, 999999);
    }
}
