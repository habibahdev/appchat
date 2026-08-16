<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\ConversationParticipant;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConversationParticipant>
 */
class ConversationParticipantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConversationParticipant::class);
    }

    public function findOneByConversationAndUser(Conversation $conversation, User $user): ?ConversationParticipant
    {
        return $this->createQueryBuilder('cp')
            ->andWhere('cp.conversation = :conversation')
            ->andWhere('cp.user = :user')
            ->setParameter('conversation', $conversation)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function markAsRead(Conversation $conversation, User $user): void
    {
        $participant = $this->findOneByConversationAndUser($conversation, $user);
        if ($participant) {
            $participant->setLastReadAt(new \DateTimeImmutable());
            $this->getEntityManager()->flush();
        }
    }

    public function findOtherParticipants(Conversation $conversation, User $excludeUser): array
    {
        $participants = $this->createQueryBuilder('cp')
            ->andWhere('cp.conversation = :conversation')
            ->andWhere('cp.user != :user')
            ->setParameter('conversation', $conversation)
            ->setParameter('user', $excludeUser)
            ->getQuery()
            ->getResult()
        ;

        return array_map(fn (ConversationParticipant $p) => $p->getUser(), $participants);
    }

    //    /**
    //     * @return ConversationParticipant[] Returns an array of ConversationParticipant objects
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

    //    public function findOneBySomeField($value): ?ConversationParticipant
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
