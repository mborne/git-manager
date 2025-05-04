<?php

namespace MBO\GitManager\Entity;

use Doctrine\ORM\Mapping as ORM;
use MBO\GitManager\Repository\ProjectRepository;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
class Project
{
    /**
     * UUID V3 computed using project URL.
     */
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    /**
     * The project name with namespaces (ex : mborne/git-manager).
     */
    #[ORM\Column(length: 255)]
    private string $name;

    /**
     * The URL of the project (ex : "https://github.com/mborne/git-manager").
     */
    #[ORM\Column(length: 512)]
    private string $httpUrl;

    /**
     * The full name of a project (ex : "github.com/mborne/git-manager").
     */
    #[ORM\Column(length: 255)]
    private string $fullName;

    /**
     * The default branch.
     */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $defaultBranch = null;

    /**
     * True if the repository is archived.
     */
    #[ORM\Column(nullable: false)]
    private bool $archived;

    /**
     * public, private or internal.
     */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $visibility;

    /**
     * Last clone or fetch date.
     */
    #[ORM\Column(type: 'datetime')]
    private \DateTime $fetchedAt;

    /**
     * Metadata about git repository (size, tags, branchNames, activity,...).
     *
     * @var array<string,mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $metadata = [];

    /**
     * Checker results (license, trivy, ).
     *
     * @var array<string,mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $checks = [];

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getHttpUrl(): string
    {
        return $this->httpUrl;
    }

    public function setHttpUrl(string $httpUrl): static
    {
        $this->httpUrl = $httpUrl;

        return $this;
    }

    public function getDefaultBranch(): ?string
    {
        return $this->defaultBranch;
    }

    public function setDefaultBranch(?string $defaultBranch): static
    {
        $this->defaultBranch = $defaultBranch;

        return $this;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function setArchived(bool $archived): static
    {
        $this->archived = $archived;

        return $this;
    }

    public function getVisibility(): ?string
    {
        return $this->visibility;
    }

    public function setVisibility(?string $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getFetchedAt(): \DateTime
    {
        return $this->fetchedAt;
    }

    public function setFetchedAt(\DateTime $fetchedAt): static
    {
        $this->fetchedAt = $fetchedAt;

        return $this;
    }

    /**
     * @return array<string,mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param array<string,mixed> $metadata
     */
    public function setMetadata(array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * @return array<string,mixed>
     */
    public function getChecks(): array
    {
        return $this->checks;
    }

    /**
     * @param array<string,mixed> $checks
     */
    public function setChecks(array $checks): static
    {
        $this->checks = $checks;

        return $this;
    }
}
