<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function random_int;
use function sprintf;
use function uniqid;

/**
 * Un compte désactivé ne se connecte pas (`App\Security\UserChecker`) : sans lui, un compte inscrit en
 * attente d'activation se connectait avec son mot de passe — vu dans Chrome sur un projet généré.
 */
#[CoversNothing]
final class InactiveAccountTest extends WebTestCase
{
    private const string PASSWORD = 'correct-horse-battery';

    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        // Une adresse par test : la limite d'essais de connexion compte par adresse.
        $this->client->setServerParameter('REMOTE_ADDR', sprintf('2001:db8::%x:%x', random_int(0, 0xFFFF), random_int(0, 0xFFFF)));
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testAnInactiveAccountIsRefusedEvenWithItsPassword(): void
    {
        $this->signIn($this->user(active: false));

        self::assertResponseRedirects('/login');
        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('cannot sign in', $crawler->filter('body')->text());
    }

    public function testAnActiveAccountSignsIn(): void
    {
        $this->signIn($this->user(active: true));

        self::assertResponseRedirects();
        self::assertStringNotContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    private function signIn(User $user): void
    {
        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->filter('form')->form(['_username' => $user->getEmail(), '_password' => self::PASSWORD]));
    }

    private function user(bool $active): User
    {
        $user = new User()
            ->setEmail(sprintf('%s@example.test', uniqid('inactive', false)))
            ->setFirstName('Ada')
            ->setLastName('Lovelace')
            ->setIsActive($active);
        $user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, self::PASSWORD));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
