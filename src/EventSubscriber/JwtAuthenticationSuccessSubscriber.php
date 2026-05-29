<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\AppMode;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class JwtAuthenticationSuccessSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AppMode $appMode,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'lexik_jwt_authentication.on_authentication_success' => 'onAuthenticationSuccess',
        ];
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        if (!$this->appMode->isBroken()) {
            return;
        }

        $data = $event->getData();
        $token = (string) ($data['token'] ?? '');

        $variants = [
            ['token' => $token],
            ['jwt' => $token, 'message' => 'Signed in.'],
            ['access_token' => $token, 'status' => 'ok'],
        ];

        $event->setData($variants[array_rand($variants)]);
    }
}
