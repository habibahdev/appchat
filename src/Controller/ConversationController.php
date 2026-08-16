<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\ConversationParticipant;
use App\Entity\User;
use App\Repository\ConversationParticipantRepository;
use App\Repository\ConversationRepository;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/conversations', name: 'app_conversation')]
final class ConversationController extends AbstractController
{
    public function __construct(
        private readonly ConversationRepository $conversationRepo,
        private readonly ConversationParticipantRepository $participantRepo,
        private readonly MessageRepository $messageRepo,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: '_index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        assert($user instanceof User);

        $conversations = $this->conversationRepo->findForUser($user);

        $data = array_map(function (Conversation $conversation) use ($user) {
            $lastMessage = $this->messageRepo->findLastMessage($conversation);

            return [
                'id' => $conversation->getId(),
                'type' => $conversation->getType(),
                'name' => $conversation->getName(),
                'lastMessage' => $lastMessage?->getContent(),
                'lastMessageAt' => $lastMessage?->getSentAt()?->format(\DateTimeInterface::ATOM),
                'unreadCount' => $this->messageRepo->countUnreadInConversation($conversation, $user)
            ];
        }, $conversations);

        return $this->render('conversation/index.html.twig', [
            'conversations' => $data,
        ]);
    }

    #[Route('/new/{userId}', name: '_new_private', methods: ['POST'])]
    public function newPrivate(int $userId): Response
    {
        $currentUser = $this->getUser();
        assert($currentUser instanceof User);

        $otherUser = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$otherUser) {
            throw $this->createNotFoundException('Utilisateur introuvable');
        }

        $existing = $this->conversationRepo->findPrivateConversationBetween($currentUser, $otherUser);
        if ($existing) {
            return $this->redirectToRoute('app_conversation_show', [
                'id' => $existing->getId()
            ]);
        }

        $conversation = new Conversation();
        $conversation->setType(Conversation::TYPE_PRIVATE);
        $p1 = new ConversationParticipant();
        $p1->setUser($currentUser);
        $conversation->addParticipant($p1);

        $p2 = new ConversationParticipant();
        $p2->setUser($otherUser);
        $conversation->addParticipant($p2);

        $this->entityManager->persist($conversation);
        $this->entityManager->persist($p1);
        $this->entityManager->persist($p2);
        $this->entityManager->flush();

        return $this->redirectToRoute('app_conversation_show', [
            'id' => $conversation->getId()
        ]);
    }

    #[Route('/group', name: '_new_group', methods: ['POST'])]
    public function newGroup(Request $request): Response
    {
        $name = $request->request->get('name');
        $userIds = $request->request->all('userIds');

        $conversation = new Conversation();
        $conversation->setType(Conversation::TYPE_GROUP);
        $conversation->setName($name);

        $currentParticipant = new ConversationParticipant();
        $currentParticipant->setUser($this->getUser());
        $conversation->addParticipant($currentParticipant);
        $this->entityManager->persist($currentParticipant);

        foreach ($userIds as $userId) {
            $user = $this->entityManager->getRepository(User::class)->find($userId);
            if ($user) {
                $participant = new ConversationParticipant();
                $participant->setUser($user);
                $conversation->addParticipant($participant);
                $this->entityManager->persist($participant);
            }
        }

        $this->entityManager->persist($conversation);
        $this->entityManager->flush();

        return $this->redirectToRoute('app_conversation_show', [
            'id' => $conversation->getId()
        ]);
    }

    #[Route('/{id}', name: '_show', methods: ['GET'])]
    public function show(Conversation $conversation): Response
    {
        $user = $this->getUser();
        if (!$this->conversationRepo->isUserParticipant($conversation, $user)) {
            throw $this->createAccessDeniedException();
        }

        $messages = $this->messageRepo->findByConversationPaginated($conversation);
        $this->participantRepo->markAsRead($conversation, $user);

        return $this->render('conversation/show.html.twig', [
            'conversation' => $conversation,
            'messages' => array_reverse($messages)
        ]);
    }

    #[Route('/{id}/read', name: '_mark_read', methods: ['POST'])]
    public function markRead(Conversation $conversation): JsonResponse
    {
        $user = $this->getUser();
        if (!$this->conversationRepo->isUserParticipant($conversation, $user)) {
            throw $this->createAccessDeniedException();
        }

        $this->participantRepo->markAsRead($conversation, $user);

        return $this->json(['success' => true]);
    }
}
