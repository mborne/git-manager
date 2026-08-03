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
     * Find projects with a full name starting with a given prefix
     * (ex : "github.com" or "github.com/IGNF").
     *
     * @return Project[]
     */
    public function findByPrefix(string $prefix): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.fullName LIKE :prefix')
            ->setParameter('prefix', addcslashes($prefix, '%_\\').'%')
            ->getQuery()
            ->getResult();
    }
}
