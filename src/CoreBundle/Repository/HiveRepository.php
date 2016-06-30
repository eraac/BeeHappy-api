<?php

namespace CoreBundle\Repository;

use UserBundle\Entity\User;

/**
 * HiveRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class HiveRepository extends \Doctrine\ORM\EntityRepository
{
    public function queryAdminHives()
    {
        return $this->queryBuilderHives()->getQuery();
    }

    public function queryBuilderHives()
    {
        return $this->createQueryBuilder('h');
    }

    public function queryBuilderHivesByUser(User $user)
    {
        $qb = $this->createQueryBuilder('h')
                    ->where('h.owner = :user')
                    ->setParameter('user', $user);

        return $qb;
    }

    public function queryMeHives(User $user)
    {
        $qb = $this->createQueryBuilder('h')
                    ->where('h.owner = :user')
                    ->setParameter('user', $user);

        return $qb->getQuery();
    }

    public function findByApiKey($apiKey)
    {
        $qb = $this->createQueryBuilder('h')
                    ->where('h.apiKey = :apiKey')
                    ->setParameter('apiKey', $apiKey);

        return $qb->getQuery()->getOneOrNullResult();
    }
}
