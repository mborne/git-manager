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
     * Optional project description (unbounded length).
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

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
     * Metadata about git repository :
     * - size : the size of the repository in octets
     * - tags : the list of the tags
     * - branches : the list of the branchs
     * - activity : the histogram of the activity per day
     *
     * Example :
     *
     * {
     *   "size": 74752,
     *   "tags": [
     *     "v0.1.0",
     *     "v0.1.1",
     *     "v0.1.2",
     *     "v0.2.0",
     *     "v0.3.0",
     *     "v1.0.0",
     *     "v1.0.1",
     *     "v1.0.2",
     *     "v1.1.0",
     *     "v1.2.0"
     *   ],
     *   "branches": [
     *     "master",
     *     "origin/1.0",
     *     "origin/master"
     *   ],
     *   "activity": {
     *     "20190127": 1,
     *     "20190615": 1,
     *     "20190723": 1,
     *     "20191006": 1,
     *     "20191015": 1,
     *     "20201023": 1,
     *     "20211125": 1,
     *     "20211203": 2,
     *     "20240201": 1,
     *     "20240715": 3
     *   }
     * }
     *
     * @var array<string,mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $metadata = [];

    /**
     * Checker results (readme, license, trivy, gitleaks).
     *
     * @var array<string,mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $checks = [];

    /**
     * @psalm-api
     */
    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): static
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @psalm-api
     */
    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @psalm-api
     */
    public function getHttpUrl(): string
    {
        return $this->httpUrl;
    }

    public function setHttpUrl(string $httpUrl): static
    {
        $this->httpUrl = $httpUrl;

        return $this;
    }

    /**
     * @psalm-api
     */
    public function getDefaultBranch(): ?string
    {
        return $this->defaultBranch;
    }

    public function setDefaultBranch(?string $defaultBranch): static
    {
        $this->defaultBranch = $defaultBranch;

        return $this;
    }

    /**
     * @psalm-api
     */
    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function setArchived(bool $archived): static
    {
        $this->archived = $archived;

        return $this;
    }

    /**
     * @psalm-api
     */
    public function getVisibility(): ?string
    {
        return $this->visibility;
    }

    public function setVisibility(?string $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
    }

    /**
     * @psalm-api
     */
    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = $fullName;

        return $this;
    }

    /**
     * @psalm-api
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @psalm-api
     */
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
     * @psalm-api
     *
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
     * @psalm-api
     *
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
