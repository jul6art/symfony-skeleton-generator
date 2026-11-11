<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function sprintf;
use function uniqid;

/**
 * La sortie est un POST qui porte le jeton `logout` (`enable_csrf` du pare-feu) : un lien GET se
 * déclenche depuis n'importe quelle page qui l'embarque — une `<img src="/logout">` ailleurs
 * déconnecterait quiconque l'affiche.
 *
 * ⚠️ Le jeton est sans état (`stateless_token_ids` de `csrf.yaml`) : Symfony vérifie l'origine de
 * la requête. Le navigateur de test pose un `Referer` à chaque requête qui en suit une autre.
 */
#[CoversNothing]
final class LogoutTest extends WebTestCase
{
    private const string ACCOUNT_PAGE = '/admin/account/two-factor';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $user = new User()
            ->setEmail(sprintf('%s@example.test', uniqid('logout', false)))
            ->setFirstName('Ada')
            ->setLastName('Lovelace')
            ->setIsActive(true)
            ->setPassword('not-used');
        $entityManager->persist($user);
        $entityManager->flush();
        $this->client->loginUser($user);
    }

    public function testTheAccountMenuSignsOutByAPost(): void
    {
        $page = $this->client->request('GET', self::ACCOUNT_PAGE);
        $this->client->submit($page->filter('form[action="/logout"]')->form());

        self::assertResponseRedirects('/login');
        $this->client->request('GET', self::ACCOUNT_PAGE);
        self::assertResponseRedirects('http://localhost/login', null, 'La session est fermée.');
    }

    public function testALinkNoLongerSignsOut(): void
    {
        $this->client->request('GET', '/logout');

        self::assertResponseStatusCodeSame(405);
        $this->assertStillSignedIn();
    }

    public function testAPostWithoutItsTokenOrFromElsewhereIsRefused(): void
    {
        $this->client->request('GET', self::ACCOUNT_PAGE);
        $this->client->request('POST', '/logout');
        self::assertResponseStatusCodeSame(403, 'Sans jeton.');

        $this->client->request('POST', '/logout', ['_csrf_token' => 'csrf-token'], server: ['HTTP_SEC_FETCH_SITE' => 'cross-site']);
        self::assertResponseStatusCodeSame(403, 'Depuis un autre site.');

        $this->assertStillSignedIn();
    }

    private function assertStillSignedIn(): void
    {
        $this->client->request('GET', self::ACCOUNT_PAGE);
        self::assertResponseIsSuccessful('La session est toujours ouverte.');
    }
}
