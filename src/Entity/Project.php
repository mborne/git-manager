<?php

namespace MBO\GitManager\Entity;

use Doctrine\ORM\Mapping as ORM;
use MBO\GitManager\Repository\ProjectRepository;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(length: 255)]
    private string $fullName;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $defaultBranch = null;

    #[ORM\Column(length: 512)]
    private string $httpUrl;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $fetchedAt;

    #[ORM\Column(length: 512)]
    private int $size;

    /**
     * @var array<string>
     */
    #[ORM\Column(type: 'simple_array', nullable: true)]
    private array $tags = [];

    #[ORM\Column(type: 'simple_array', nullable: true)]
    private array $branchNames = [];

    /**
     * @var array<string,int>
     */
    #[ORM\Column(type: 'json')]
    private array $activity = [];

    /**
     * @var array<string,mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $checks = [];

    public function getId(): int
    {
        return $this->id;
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

    public function getDefaultBranch(): ?string
    {
        return $this->defaultBranch;
    }

    public function setDefaultBranch(?string $defaultBranch): static
    {
        $this->defaultBranch = $defaultBranch;

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

    public function getFetchedAt(): \DateTime
    {
        return $this->fetchedAt;
    }

    public function setFetchedAt(\DateTime $fetchedAt): static
    {
        $this->fetchedAt = $fetchedAt;

        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @param array<string> $tags
     */
    public function setTags(array $tags): static
    {
        $this->tags = $tags;

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getBranchNames(): array
    {
        return $this->branchNames;
    }

    public function setBranchNames(array $branchNames): static
    {
        $this->branchNames = $branchNames;

        return $this;
    }

    /**
     * @return array<string,int>
     */
    public function getActivity(): array
    {
        return $this->activity;
    }

    /**
     * @param array<string,int> $activity
     */
    public function setActivity(array $activity): static
    {
        $this->activity = $activity;

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
