<?php

namespace App\Repository;

use App\Entity\Message;
use App\Entity\MessageRead;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MessageRead>
 */
class MessageReadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageRead::class);
    }

    public function hasUserReadMessage(Message $message, User $user): bool
    {
        return $this->createQueryBuilder('mr')
            ->select('COUNT(mr.id)')
            ->andWhere('mr.message = :message')
            ->andWhere('mr.user = :user')
            ->setParameter('message', $message)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult() > 0
        ;
    }

    public function markAsRead(Message $message, User $user): void
    {
        if ($this->hasUserReadMessage($message, $user)) {
            return;
        }

        $read = new MessageRead();
        $read->setMessage($message);
        $read->setUser($user);

        $entityManager = $this->getEntityManager();
        $entityManager->persist($read);
        $entityManager->flush();
    }

    /**
     * @return User[]
     */
    public function findReadersOf(Message $message): array
    {
        $reads = $this->createQueryBuilder('mr')
            ->andWhere('mr.message = :message')
            ->setParameter('message', $message)
            ->getQuery()
            ->getResult()
        ;

        return array_map(fn (MessageRead $r) => $r->getUser(), $reads);
    }

    //    /**
    //     * @return MessageRead[] Returns an array of MessageRead objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('m.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?MessageRead
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
