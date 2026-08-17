<?php

namespace App\DataFixtures;

use App\Entity\Conversation;
use App\Entity\ConversationParticipant;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $users = [];
        $names = ['Alice', 'Bob', 'Chloé', 'David', 'Emma'];

        foreach ($names as $name) {
            $user = new User();
            $user->setEmail(strtolower($name).'@example.com');
            $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));
            $user->setIsOnline(false);
            $user->setLastSeenAt(new \DateTimeImmutable('-'.rand(1, 120).' minutes'));

            $manager->persist($user);
            $users[$name] = $user;
        }

        // conversation privée Alice <-> Bob
        $conv1 = new Conversation();
        $conv1->setType(Conversation::TYPE_PRIVATE);
        $manager->persist($conv1);

        $this->addParticipant($manager, $conv1, $users['Alice']);
        $this->addParticipant($manager, $conv1, $users['Bob']);

        $this->addMessages($manager, $conv1, [
            [$users['Alice'], 'Salut Bob, ça va ?'],
            [$users['Bob'], 'Oui nickel, et toi ?'],
            [$users['Alice'], 'Ça va bien, merci !'],
        ]);

        // groupe avec 3 personnes
        $conv2 = new Conversation();
        $conv2->setType(Conversation::TYPE_GROUP);
        $conv2->setName('Projet Symfony');
        $manager->persist($conv2);

        $this->addParticipant($manager, $conv2, $users['Alice']);
        $this->addParticipant($manager, $conv2, $users['Chloé']);
        $this->addParticipant($manager, $conv2, $users['David']);

        $this->addMessages($manager, $conv2, [
            [$users['Chloé'], 'On avance bien sur le projet !'],
            [$users['David'], 'Oui, les entités sont prêtes.'],
            [$users['Alice'], 'Il reste les tests à écrire.'],
        ]);

        $manager->flush();
    }

    private function addParticipant(ObjectManager $manager, Conversation $conversation, User $user): void
    {
        $participant = new ConversationParticipant();
        $participant->setUser($user);
        $participant->setJoinedAt(new \DateTimeImmutable());
        $conversation->addParticipant($participant);
        $manager->persist($participant);
    }

    private function addMessages(ObjectManager $manager, Conversation $conversation, array $entries): void
    {
        $currentTime = new \DateTimeImmutable('-1 hour');

        foreach ($entries as [$sender, $content]) {
            $message = new Message();
            $message->setConversation($conversation);
            $message->setSender($sender);
            $message->setContent($content);
            $message->setSentAt($currentTime);

            $conversation->addMessage($message);
            $manager->persist($message);

            $currentTime = $currentTime->modify('+'.rand(2, 8).' minutes');
        }
    }
}
