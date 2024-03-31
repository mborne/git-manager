<?php

namespace MBO\GitManager\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Symfony\Component\Serializer\Annotation\Ignore;
use Symfony\Component\Uid\Uuid;

#[Entity]
class Project
{
    #[Id, Column]
    private string $id;

    #[Column(unique: true)]
    private string $name;

    #[Column(type: 'json')]
    // #[Ignore]
    private mixed $metadata;

    public function __construct()
    {
        $this->id = Uuid::v4();
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

    public function getMetadata(): mixed
    {
        return $this->metadata;
    }

    public function setMetadata(mixed $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }
}
