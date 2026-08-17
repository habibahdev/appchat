<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class MercureAuthController extends AbstractController
{
    #[Route('/mercure/auth', name: 'app_mercure_auth')]
    #[IsGranted('ROLE_USER')]
    public function index(Authorization $authorization): Cookie
    {
        $user = $this->getUser();
        assert($user instanceof User);

        $topics = [
            sprintf('/users/%d/notifications', $user->getId()),
            '/presence'
        ];

        foreach ($user->getConversationParticipants() ?? [] as $conversation) {
            $topics[] = sprintf('/conversations/%d', $conversation->getId());
            $topics[] = sprintf('/conversations/%d/read', $conversation->getId());
            $topics[] = sprintf('/conversations/%d/typing', $conversation->getId());
        }

        return $authorization->createCookie($this->container->get('request_stack')->getCurrentRequest(), $topics);
    }
}
