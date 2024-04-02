<?php

namespace MBO\GitManager\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use MBO\GitManager\Repository\ProjectRepository;
use Symfony\Component\Uid\Uuid;

#[Entity(repositoryClass: ProjectRepository::class)]
class Project
{
    /**
     * Generated UID.
     */
    #[Id, Column]
    private string $id;

    /**
     * Full name (ex : github.com/mborne/ansible-docker-ce).
     */
    #[Column(unique: true)]
    private string $name;

    /**
     * Metadata update date.
     */
    #[Column(type: 'datetime')]
    private \DateTime $updatedAt;

    /**
     * Size of repository (ko).
     */
    #[Column]
    private int $size;

    /**
     * Tag names.
     *
     * @var string[]
     */
    #[Column(type: 'json')]
    private array $tags;

    /**
     * Branch names.
     *
     * @var string[]
     */
    #[Column(type: 'json')]
    private array $branches;

    /**
     * Number of commit per days.
     *
     * @var array<string,int>
     */
    #[Column(type: 'json')]
    private mixed $activity;

    /**
     * @var array<string,mixed>
     */
    #[Column(type: 'json')]
    private array $checks;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->updatedAt = new \DateTime('now');
        $this->checks = [];
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @param string[] $tags
     */
    public function setTags(array $tags): self
    {
        $this->tags = $tags;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getBranches(): array
    {
        return $this->branches;
    }

    /**
     * @param string[] $branches
     */
    public function setBranches(array $branches): self
    {
        $this->branches = $branches;

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
    public function setActivity(array $activity): self
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
    public function setChecks(array $checks): self
    {
        $this->checks = $checks;

        return $this;
    }
}
