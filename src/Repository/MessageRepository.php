<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\ConversationParticipant;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * @return Message[]
     */
    public function findByConversationPaginated(Conversation $conversation, int $limit = 30, int $offset = 0): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :conversation')
            ->setParameter('conversation', $conversation)
            ->orderBy('m.sentAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult()
        ;
    }

    public function findLastMessage(Conversation $conversation): ?Message
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :conversation')
            ->setParameter('conversation', $conversation)
            ->orderBy('m.sentAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function countUnreadInConversation(Conversation $conversation, User $user): int
    {
        $query = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.conversation = :conversation')
            ->andWhere('m.sender != :user')
            ->setParameter('conversation', $conversation)
            ->setParameter('user', $user)
        ;

        $participant = $this->getEntityManager()
            ->getRepository(ConversationParticipant::class)
            ->findOneByConversationAndUser($conversation, $user)
        ;

        if ($participant && $participant->getLastReadAt()) {
            $query->andWhere('m.sentAt > :lastReadAt')
            ->setParameter('lastReadAt', $participant->getLastReadAt());
        }

        return (int) $query->getQuery()->getSingleScalarResult();
    }

    public function countTotalUnreadForUser(User $user): int
    {
        $conversations = $this->getEntityManager()
            ->getRepository(Conversation::class)
            ->findForUser($user)
        ;

        $total = 0;
        foreach ($conversations as $conversation) {
            $total += $this->countUnreadInConversation($conversation, $user);
        }

        return $total;
    }

    //    /**
    //     * @return Message[] Returns an array of Message objects
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

    //    public function findOneBySomeField($value): ?Message
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
