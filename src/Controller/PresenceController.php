<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Component\Mercure\Update;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/presence')]
#[IsGranted('ROLE_USER')]
final class PresenceController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly HubInterface $hub
    ) {
    }

    #[Route('/heartbeat', name: '_heartbeat', methods: ['POST'])]
    public function heartbeat(): JsonResponse
    {
        $user = $this->getUser();
        assert($user instanceof User);

        $wasOffline = !$user->isOnline();

        $user->setIsOnline(true);
        $user->setLastSeenAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        if ($wasOffline) {
            $this->broadcastStatus($user, true);
        }

        return $this->json(['success' => true]);
    }

    #[Route('/offline', name: '_offline', methods: ['POST'])]
    public function offline(): JsonResponse
    {
        $user = $this->getUser();
        assert($user instanceof User);

        $user->setIsOnline(false);
        $user->setLastSeenAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $this->broadcastStatus($user, false);

        return $this->json(['success' => true]);
    }

    private function broadcastStatus(\App\Entity\User $user, bool $isOnline): void
    {
        $update = new Update(
            '/presence',
            json_encode([
                'userId' => $user->getId(),
                'isOnline' => $isOnline,
                'lastSeenAt' => $user->getLastSeenAt()->format(\DateTimeInterface::ATOM),
            ])
        );

        $this->hub->publish($update);
    }
}
