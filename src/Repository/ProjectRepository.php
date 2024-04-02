<?php

namespace MBO\GitManager\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use MBO\GitManager\Entity\Project;

/**
 * @extends ServiceEntityRepository<ProjectRepository>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $managerRegistry)
    {
        parent::__construct($managerRegistry, Project::class);
    }
}
