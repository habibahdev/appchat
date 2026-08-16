<?php

namespace App\Security\Voter;

use App\Entity\Conversation;
use App\Entity\User;
use App\Repository\ConversationRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ConversationVoter extends Voter
{
    public const VIEW = 'CONVERSATION_VIEW';
    public const PARTICIPATE = 'CONVERSATION_PARTICIPATE';

    public function __construct(private readonly ConversationRepository $conversationRepo)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::PARTICIPATE], true)
            && $subject instanceof Conversation;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Conversation $conversation */
        $conversation = $subject;

        return match ($attribute) {
            self::VIEW, self::PARTICIPATE => $this->conversationRepo->isUserParticipant($conversation, $user),
            default => false
        };
    }
}