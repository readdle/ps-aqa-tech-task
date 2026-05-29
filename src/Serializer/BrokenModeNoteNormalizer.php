<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Entity\Note;
use App\Service\AppMode;
use ArrayObject;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class BrokenModeNoteNormalizer implements NormalizerInterface
{
    private const ALREADY_CALLED = 'broken_mode_note_normalizer_already_called';

    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')]
        private readonly NormalizerInterface $normalizer,
        private readonly AppMode $appMode,
    ) {
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): ArrayObject|array|string|int|float|bool|null
    {
        $context[self::ALREADY_CALLED] = true;
        $normalized = $this->normalizer->normalize($data, $format, $context);

        if (is_array($normalized)) {
            $normalized['content'] = '';
        }

        return $normalized;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $this->appMode->isBroken()
            && $data instanceof Note
            && !isset($context[self::ALREADY_CALLED]);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Note::class => false,
        ];
    }
}
