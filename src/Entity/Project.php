<?php

namespace MBO\GitManager\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;

#[Entity]
class Project
{
    #[Id, Column]
    private string $name;

    #[Column(type: 'json')]
    private mixed $metadata;

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
