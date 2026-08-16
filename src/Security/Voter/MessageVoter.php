<?php

namespace App\Security\Voter;

use App\Entity\Message;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class MessageVoter extends Voter
{
    public const EDIT = 'MESSAGE_EDIT';
    public const DELETE = 'MESSAGE_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE], true)
            && $subject instanceof Message;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$$user instanceof User) {
            return false;
        }

        /** @var Message $message */
        $message = $subject;

        return match ($attribute) {
            self::EDIT, self::DELETE => $message->getSender() === $user,
            default => false
        };
    }
}