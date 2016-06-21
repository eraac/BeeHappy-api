<?php

namespace CoreBundle\Repository;

use CoreBundle\Entity\Hive;
use CoreBundle\Entity\Type;

/**
 * MeasureRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class MeasureRepository extends \Doctrine\ORM\EntityRepository
{
    public function countPerHiveAndType(Hive $hive, Type $type)
    {
        $qb = $this->createQueryBuilder('m')
                    ->select('COUNT(m.id)')
                    ->where('m.hive = :hive')
                    ->andWhere('m.type = :type')
                    ->setParameters([
                        'hive' => $hive,
                        'type' => $type,
                    ]);

        return $qb->getQuery()->getSingleScalarResult();
    }

    public function getMeasureByHiveAndType(Hive $hive, Type $type, $page, $itemPerPage)
    {
        return $this->queryBuilderMeasureByHiveAndType($hive, $type, $page, $itemPerPage)->getQuery()->getArrayResult();
    }

    /**
     * @param Hive $hive
     * @param Type $type
     * @param $page
     * @param $itemPerPage
     *
     * @return \Doctrine\ORM\QueryBuilder
     */
    public function queryBuilderMeasureByHiveAndType(Hive $hive, Type $type, $page, $itemPerPage)
    {
        $qb = $this->createQueryBuilder('m')
            ->select('m.value')
            ->addSelect('m.createdAt AS created_at')
            ->where('m.hive = :hive')
            ->andWhere('m.type = :type')
            ->orderBy('m.createdAt', 'DESC')
            ->setParameters([
                'hive' => $hive,
                'type' => $type,
            ])
            ->setMaxResults($itemPerPage)
            ->setFirstResult(($page - 1) * $itemPerPage);

        return $qb;
    }
}
