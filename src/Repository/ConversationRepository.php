<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    /**
     * @return Conversation[]
     */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.participants', 'p')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->leftJoin('c.messages', 'm')
            ->addSelect('m')
            ->orderBy('m.sentAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return Conversation|null
     */
    public function findPrivateConversationBetween(User $a, User $b): ?Conversation
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.type = :type')
            ->setParameter('type', Conversation::TYPE_PRIVATE)
            ->innerJoin('c.participants', 'p1')
            ->andWhere('p1.user = :a')
            ->setParameter('a', $a)
            ->innerJoin('c.participants', 'p2')
            ->andWhere('p2.user = :b')
            ->setParameter('b', $b)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * @return bool
     */
    public function isUserParticipant(Conversation $conversation, User $user): bool
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(p.id)')
            ->innerJoin('c.participants', 'p')
            ->andWhere('c = :conversation')
            ->andWhere('p.user = :user')
            ->setParameter('conversation', $conversation)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult() > 0
        ;
    }

    //    /**
    //     * @return Conversation[] Returns an array of Conversation objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Conversation
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
