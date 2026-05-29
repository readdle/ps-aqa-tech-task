<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\Doctrine\Orm\Filter\FreeTextQueryFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrFilter;
use ApiPlatform\Doctrine\Orm\Filter\PartialSearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\SortFilter;
use App\Repository\NoteRepository;
use App\State\NotePersistProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/api/notes',
            paginationMaximumItemsPerPage: 50,
            paginationClientItemsPerPage: true,
            security: "is_granted('ROLE_USER')",
            parameters: [
                'q' => new QueryParameter(
                    filter: new FreeTextQueryFilter(new OrFilter(new PartialSearchFilter())),
                    properties: ['title', 'content'],
                ),
                'title' => new QueryParameter(filter: new PartialSearchFilter(), property: 'title'),
                'content' => new QueryParameter(filter: new PartialSearchFilter(), property: 'content'),
                'sort[updatedAt]' => new QueryParameter(filter: new SortFilter(), property: 'updatedAt'),
                'sort[title]' => new QueryParameter(filter: new SortFilter(), property: 'title'),
            ],
        ),
        new Post(uriTemplate: '/api/notes', security: "is_granted('ROLE_USER')", processor: NotePersistProcessor::class),
        new Get(uriTemplate: '/api/notes/{id}', security: "is_granted('ROLE_USER')"),
        new Put(uriTemplate: '/api/notes/{id}', security: "is_granted('ROLE_USER')", processor: NotePersistProcessor::class),
        new Delete(uriTemplate: '/api/notes/{id}', security: "is_granted('ROLE_USER')"),
    ],
    normalizationContext: ['groups' => ['note:read']],
    denormalizationContext: ['groups' => ['note:write']],
)]
#[ORM\Entity(repositoryClass: NoteRepository::class)]
#[ORM\Table(name: 'notes')]
#[ORM\HasLifecycleCallbacks]
class Note
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Groups(['note:read'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'notes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\Column(length: 255)]
    #[Groups(['note:read', 'note:write'])]
    #[Assert\NotBlank(message: 'Title is required.')]
    #[Assert\Length(
        min: 1,
        max: 255,
        minMessage: 'Title cannot be empty.',
        maxMessage: 'Title cannot be longer than {{ limit }} characters.',
    )]
    private string $title = '';

    #[ORM\Column(type: 'text')]
    #[Groups(['note:read', 'note:write'])]
    #[Assert\NotBlank(message: 'Content is required.')]
    #[Assert\Length(
        min: 1,
        max: 10000,
        minMessage: 'Content cannot be empty.',
        maxMessage: 'Content cannot be longer than {{ limit }} characters.',
    )]
    private string $content = '';

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    #[Groups(['note:read'])]
    #[SerializedName('created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    #[Groups(['note:read'])]
    #[SerializedName('updated_at')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        if (null === $this->id) {
            $this->id = self::generateUuidV4();
        }

        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(User $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = trim($title);

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private static function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }

}
