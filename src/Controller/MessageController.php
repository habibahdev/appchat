<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Message;
use App\Entity\Attachment;
use App\Entity\Conversation;
use App\Entity\Notification;
use App\Security\Voter\MessageVoter;
use Symfony\Component\Mercure\Update;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\MessageReadRepository;
use App\Security\Voter\ConversationVoter;
use App\Repository\ConversationRepository;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Repository\ConversationParticipantRepository;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/conversations/{conversationId}/messages', name: 'app_message')]
#[IsGranted('ROLE_USER')]
final class MessageController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ConversationRepository $conversationRepo,
        private readonly ConversationParticipantRepository $participantRepo,
        private readonly HubInterface $hub,
        private readonly SluggerInterface $slugger,
        private readonly string $uploadsDirectory
    ) {
    }

    #[Route('', name: '_send', methods: ['POST'])]
    public function send(int $conversationId, Request $request): JsonResponse
    {
        $conversation = $this->entityManager->getRepository(Conversation::class)->find($conversationId);
        if (!$conversation) {
            throw $this->createNotFoundException();
        }

        $user = $this->getUser();
        assert($user instanceof User);

        if (!$this->conversationRepo->isUserParticipant($conversation, $user)) {
            throw $this->createAccessDeniedException();
        }

        $content = $request->request->get('content');
        $uploadedFiles = $request->files->all('attachments');
        if (!$content && empty($uploadedFiles)) {
            return $this->json(['error' => 'Message vide'], 400);
        }

        $message = new Message();
        $message->setConversation($conversation);
        $message->setSender($user);
        $message->setContent($content);

        $this->entityManager->persist($message);

        foreach ($uploadedFiles as $file) {
            /** @var UploadedFile $file */
            $attachment = $this->handleUpload($file, $message);
            $this->entityManager->persist($attachment);
        }

        $others = $this->participantRepo->findOtherParticipants($conversation, $user);
        foreach ($others as $recipient) {
            $notification = new Notification();
            $notification->setRecipient($recipient);
            $notification->setType(Notification::TYPE_NEW_MESSAGE);
            $notification->setConversation($conversation);
            $notification->setMessage($message);

            $this->entityManager->persist($notification);

            $this->publishNotification($notification);
        }

        $this->entityManager->flush();

        $this->publishMessage($message);

        return $this->json([
            'id' => $message->getId(),
            'content' => $message->getContent(),
            'sentAt' => $message->getSentAt()->format(\DateTimeInterface::ATOM)
        ], 201);
    }

    #[Route('/{messageId}/read', name: '_mark_read', methods: ['POST'])]
    public function markRead(int $conversationId, int $messageId, MessageReadRepository $readRepository): JsonResponse
    {
        $conversation = $this->entityManager->getRepository(Conversation::class)->find($conversationId);
        if (!$conversation) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(ConversationVoter::PARTICIPATE, $conversation);

        $message = $this->entityManager->getRepository(Message::class)->find($messageId);
        if (!$message) {
            throw $this->createNotFoundException();
        }

        $user = $this->getUser();
        assert($user instanceof User);

        $readRepository->markAsRead($message, $user);

        $update = new Update(
            sprintf('/conversations/%d/reads', $message->getConversation()->getId()),
            json_encode([
                'messageId' => $message->getId(),
                'readBy' => $user->getId(),
                'readAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)
            ])
        );

        $this->hub->publish($update);

        return $this->json(['success' => true]);
    }

    #[Route('/{messageId}/edit', name: '_edit', methods: ['POST'])]
    public function edit(int $conversationId, int $messageId, Request $request): JsonResponse
    {
        $conversation = $this->entityManager->getRepository(Conversation::class)->find($conversationId);
        if (!$conversation) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(ConversationVoter::PARTICIPATE, $conversation);

        $message = $this->entityManager->getRepository(Message::class)->find($messageId);
        if (!$message || $message->getConversation()->getId() !== $conversation->getId()) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(MessageVoter::EDIT, $message);

        $content = trim($request->request->get('content', ''));
        if ($content === '') {
            return $this->json(['error' => 'Le message ne peut pas être vide.'], 400);
        }

        $message->setContent($content);
        $message->setEditedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $update = new Update(
            sprintf('/conversations/%d', $conversation->getId()),
            json_encode([
                'action' => 'edit',
                'id' => $message->getId(),
                'content' => $message->getContent(),
                'editedAt' => $message->getEditedAt()->format(\DateTimeInterface::ATOM),
            ])
        );
        $this->hub->publish($update);

        return $this->json([
            'id' => $message->getId(),
            'content' => $message->getContent(),
            'editedAt' => $message->getEditedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route('/{messageId}', name: '_delete', methods: ['DELETE'])]
    public function delete(int $conversationId, int $messageId): JsonResponse
    {
        $conversation = $this->entityManager->getRepository(Conversation::class)->find($conversationId);
        if (!$conversation) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(ConversationVoter::PARTICIPATE, $conversation);

        $message = $this->entityManager->getRepository(Message::class)->find($messageId);
        if (!$message || $message->getConversation()->getId() !== $conversation->getId()) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(MessageVoter::DELETE, $message);

        $message->setIsDeleted(true);
        $message->setContent(null);
        $this->entityManager->flush();

        $update = new Update(
            sprintf('/conversations/%d', $conversation->getId()),
            json_encode([
                'action' => 'delete',
                'id' => $message->getId(),
            ])
        );
        $this->hub->publish($update);

        return $this->json(['success' => true]);
    }

    private function handleUpload(UploadedFile $file, Message $message): Attachment
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        $file->move($this->uploadsDirectory, $newFilename);

        $attachment = new Attachment();
        $attachment->setMessage($message);
        $attachment->setOriginalName($file->getClientOriginalName());
        $attachment->setFileName($newFilename);
        $attachment->setMimeType($file->getMimeType() ?? 'application/octet-stream');
        $attachment->setSize($file->getSize());

        $message->addAttachment($attachment);

        return $attachment;
    }

    private function publishMessage(Message $message): void
    {
        $update = new Update(
            sprintf('/conversations/%d', $message->getConversation()->getId()),
            json_encode([
                'id' => $message->getId(),
                'content' => $message->getContent(),
                'senderId' => $message->getSender()->getId(),
                'senderName' => $message->getSender()->getUserIdentifier(),
                'sentAt' => $message->getSentAt()->format(\DateTimeInterface::ATOM),
                'attachments' => array_map(fn ($a) => [
                    'id' => $a->getId(),
                    'originalName' => $a->getOriginalName(),
                    'fileName' => $a->getFileName(),
                ], $message->getAttachments()->toArray())
            ])
        );

        $this->hub->publish($update);
    }

    private function publishNotification(Notification $notification): void
    {
        $update = new Update(
            sprintf('/users/%d/notifications', $notification->getRecipient()->getId()),
            json_encode([
                'id' => $notification->getId(),
                'type' => $notification->getType(),
                'conversationId' => $notification->getConversation()?->getId(),
                'createdAt' => $notification->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ])
        );

        $this->hub->publish($update);
    }
}
