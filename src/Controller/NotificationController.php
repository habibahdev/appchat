<?php

namespace App\Controller;

use App\Repository\NotificationRepository;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/notifications', name: 'app_notification')]
#[IsGranted('ROLE_USER')]
final class NotificationController extends AbstractController
{
    public function __construct(private readonly NotificationRepository $notificationRepository)
    {
    }
    
    #[Route('', name: '', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $notifications = $this->notificationRepository->findRecentForUser($this->getUser());

        return $this->json(array_map(fn ($n) => [
            'id' => $n->getId(),
            'type' => $n->getType(),
            'conversationId' => $n->getConversation()?->getId(),
            'isRead' => $n->isRead(),
            'createdAt' => $n->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], $notifications));
    }

    #[Route('/mark-all-read', name: '_mark_all_read', methods: ['POST'])]
    public function markAllRead(): JsonResponse
    {
        $this->notificationRepository->markAllAsRead($this->getUser());

        return $this->json(['success' => true]);
    }
}
