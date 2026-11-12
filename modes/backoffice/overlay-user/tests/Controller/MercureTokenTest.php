<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Security\UserRoles;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Jul6Art\PushBundle\Mercure\GlobalFeedTopicResolver;
use Jul6Art\PushBundle\Mercure\TopicPrefix;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function json_decode;
use function sprintf;
use function uniqid;

use const JSON_THROW_ON_ERROR;

/**
 * Le rafraîchissement des tableaux : le point d'entrée du jeton (push-bundle ≥ 3.3, routé par
 * `config/routes/push.yaml`) pose le cookie d'abonné et nomme le canal, dans le coin de l'application
 * sur le hub. Le jeton n'atteint jamais le JavaScript — un hub Mercure 1.x le refuse dans une URL.
 */
#[CoversNothing]
final class MercureTokenTest extends WebTestCase
{
    public function testTheTablesTokenTravelsInTheCookieOnly(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();
        $client->loginUser($this->createUser());

        $client->request('GET', '/admin/mercure-token');

        self::assertResponseIsSuccessful();
        $prefix = static::getContainer()->get(TopicPrefix::class);
        self::assertSame(
            ['subscribed' => [$prefix->apply(GlobalFeedTopicResolver::TOPIC)], 'patterns' => []],
            json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR),
        );
        $cookie = $client->getResponse()->headers->getCookies()[0] ?? null;
        self::assertNotNull($cookie);
        self::assertSame('__Secure-mercure_access_token', $cookie->getName());
        self::assertTrue($cookie->isHttpOnly());
    }

    /**
     * Un visiteur anonyme n'obtient ni jeton ni canal : la règle d'accès de `/admin` le renvoie à la
     * connexion avant le point d'entrée.
     */
    public function testAnAnonymousVisitorGetsNoSubscriberToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/mercure-token');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
        foreach ($client->getResponse()->headers->getCookies() as $cookie) {
            self::assertNotSame('__Secure-mercure_access_token', $cookie->getName());
        }
    }

    private function createSchema(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    private function createUser(): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = new User()
            ->setEmail(sprintf('%s@example.test', uniqid('mercure', false)))
            ->setFirstName('Ada')
            ->setLastName('Lovelace')
            ->setRoles([UserRoles::ROLE_ADMIN])
            ->setIsActive(true);
        $user->setPassword('noop');
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
