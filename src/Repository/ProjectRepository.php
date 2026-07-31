<?php

namespace MBO\GitManager\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use MBO\GitManager\Entity\Project;

/**
 * @extends ServiceEntityRepository<Project>
 */
final class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * @return Project[]
     */
    public function findByHost(string $host): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.fullName LIKE :host')
            ->setParameter('host', $host.'/%')
            ->getQuery()
            ->getResult();
    }
}
