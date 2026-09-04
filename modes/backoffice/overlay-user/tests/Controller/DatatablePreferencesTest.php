<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Acl\DoctrinePermissionStore;
use App\Entity\DatatablePreference;
use App\Entity\User;
use App\Repository\DatatablePreferenceRepository;
use App\Security\DefaultRolePermissions;
use App\Security\UserRoles;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Translation\TranslatorBagInterface;

use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * La couture des préférences de tableau par compte.
 *
 * `jul6art/datatable-bundle` a sa propre suite sur l'interprétation du contenu et sur les trois
 * opérations, avec un magasin en mémoire. Ce qu'il ne peut pas savoir, et que ce test dit :
 *
 * - la route est importée sous `/admin` et gardée par le pare-feu qui garde l'espace ;
 * - `DatatablePreferenceStoreInterface` est branchée sur l'implémentation Doctrine du projet. Sans
 *   l'alias, `PreferenceControllerPass` RETIRE le contrôleur et l'endpoint répond 404 — même sens
 *   de défaillance silencieuse que les deux alias ACL ;
 * - le magasin fait un upsert : une ligne par (compte, tableau), quoi qu'il arrive à la deuxième
 *   sauvegarde. Un `INSERT` aveugle sortirait en 500 au second clic ;
 * - la table des comptes embarque l'URL et le jeton, donc le panneau a de quoi écrire.
 */
#[CoversNothing]
final class DatatablePreferencesTest extends WebTestCase
{
    private const string TABLE_KEY = 'user';

    private const string ENDPOINT = '/admin/datatable/preferences/'.self::TABLE_KEY;

    private User $user;

    /**
     * Le pare-feu de `/admin` garde l'endpoint. Sans l'import sous ce préfixe, la route tomberait
     * hors de l'`access_control` qui vise `^/admin`.
     */
    public function testAnAnonymousRequestIsSentToTheLoginPage(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        $client->request('GET', self::ENDPOINT);

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    /**
     * Rien d'enregistré est l'état de chaque compte sur chaque tableau jusqu'à sa première
     * sauvegarde : ça répond les préférences vides, pas un 404 que le JavaScript devrait traiter à
     * part.
     */
    public function testReadingWithNothingStoredAnswersEmptyPreferences(): void
    {
        $client = $this->authenticated();

        $client->request('GET', self::ENDPOINT);

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['v' => 1, 'columns' => [], 'sort' => null, 'views' => []],
            $this->json($client),
        );
    }

    /** L'aller-retour complet, à travers la vraie route, le vrai jeton et la vraie table. */
    public function testWritingStoresOneRowAndAnswersTheSanitisedState(): void
    {
        $client = $this->authenticated();
        $token = $this->preferencesToken($client);

        $this->put($client, $token, [
            'columns' => [['key' => 'email'], ['key' => 'lastName', 'visible' => false]],
            'sort' => ['key' => 'email', 'dir' => 'desc'],
            'views' => [['name' => 'Comptes actifs', 'filters' => ['isActive' => 'true'], 'default' => true]],
        ]);

        self::assertResponseIsSuccessful();

        /** @var array{columns: list<array{key: string, visible: bool}>, views: list<array{id: string}>} $payload */
        $payload = $this->json($client);
        self::assertSame(['email', 'lastName'], array_column($payload['columns'], 'key'));
        self::assertSame([true, false], array_column($payload['columns'], 'visible'));
        // L'id vient du nom, côté serveur : le client ne le choisit pas.
        self::assertSame('comptes-actifs', $payload['views'][0]['id']);

        $stored = $this->repository()->findOneForUser($this->user, self::TABLE_KEY);
        self::assertInstanceOf(DatatablePreference::class, $stored);
        // Le magasin garde les octets qu'on lui a donnés : c'est le contrat « la valeur est opaque ».
        self::assertSame($payload, json_decode($stored->getPayload(), true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * `PUT` REMPLACE, et le magasin doit faire un upsert : l'endpoint n'est pas un patch, le
     * navigateur renvoie tout l'état à chaque sauvegarde. Un `INSERT` aveugle heurterait
     * `uniq_datatable_preference_owner_key` et sortirait en 500 — au SECOND clic.
     */
    public function testASecondWriteReplacesTheFirstWithoutAddingARow(): void
    {
        $client = $this->authenticated();
        $token = $this->preferencesToken($client);

        $this->put($client, $token, ['views' => [['name' => 'Première']]]);
        self::assertResponseIsSuccessful();

        $this->put($client, $token, ['views' => [['name' => 'Seconde']]]);
        self::assertResponseIsSuccessful();

        /** @var array{views: list<array{name: string}>} $payload */
        $payload = $this->json($client);
        self::assertSame(['Seconde'], array_column($payload['views'], 'name'));
        self::assertCount(1, $this->repository()->findBy(['owner' => $this->user]));
    }

    /**
     * L'identité vient du jeton de sécurité, et le client n'envoie jamais d'identifiant : écrire
     * les préférences d'un autre n'est pas « interdit », c'est irreprésentable. D'où l'absence de
     * voter d'appartenance — et ce test, qui est ce qui le dit.
     */
    public function testTwoAccountsDoNotShareTheirPreferences(): void
    {
        $client = $this->authenticated();
        $this->put($client, $this->preferencesToken($client), ['views' => [['name' => 'Alice']]]);
        self::assertResponseIsSuccessful();
        $alice = $this->user;

        $client->loginUser($this->createUser(UserRoles::ROLE_ADMIN));
        $client->request('GET', self::ENDPOINT);

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->json($client)['views'], 'Le second compte ne voit pas les vues du premier.');
        self::assertNotNull($this->repository()->findOneForUser($alice, self::TABLE_KEY));
    }

    public function testDeletingRemovesTheRow(): void
    {
        $client = $this->authenticated();
        $token = $this->preferencesToken($client);
        $this->put($client, $token, ['columns' => [['key' => 'email']]]);
        self::assertResponseIsSuccessful();

        $client->request('DELETE', self::ENDPOINT, server: ['HTTP_X_CSRF_TOKEN' => $token]);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertNull($this->repository()->findOneForUser($this->user, self::TABLE_KEY));
    }

    /**
     * `SameSite=Lax` bloque déjà une écriture cross-origin ; le jeton est le second verrou, et il
     * vaut pour les DEUX écritures — un `DELETE` réinitialise une mise en page aussi sûrement
     * qu'un `PUT`.
     */
    public function testAWriteWithoutAValidTokenIsRefused(): void
    {
        $client = $this->authenticated();

        $this->put($client, 'forged', ['columns' => [['key' => 'email']]]);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $client->request('DELETE', self::ENDPOINT, server: ['HTTP_X_CSRF_TOKEN' => 'forged']);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        self::assertNull($this->repository()->findOneForUser($this->user, self::TABLE_KEY));
    }

    /**
     * La table embarque de quoi écrire, et ses libellés de panneau ne sont pas des clés brutes.
     *
     * Sans l'inclusion de `_preferences.html.twig`, la table se rend exactement comme avant — les
     * deux menus ne s'affichent pas et rien ne le signale. Et sans le domaine `datatable`, les
     * libellés sortent en clair : c'est le défaut qu'un écran rendu attrape et qu'une
     * configuration inspectée laisse passer.
     */
    public function testTheUsersTableShipsThePreferencesAttributesAndItsPanelLabels(): void
    {
        $client = $this->authenticated();

        $crawler = $client->request('GET', '/admin/users');

        self::assertResponseIsSuccessful();
        $table = $crawler->filter('table[data-core--datatable-preferences-url-value]')->first();
        self::assertGreaterThan(0, $table->count(), 'Le partial `_preferences` doit être inclus.');
        self::assertSame(self::ENDPOINT, $table->attr('data-core--datatable-preferences-url-value'));
        self::assertNotEmpty($table->attr('data-core--datatable-preferences-csrf-value'));

        // ⚠️ Les libellés du panneau ne voyagent plus dans un attribut : le navigateur reçoit le
        // catalogue `javascript` entier. Et ces quatre-là sont invisibles à un scanner — le
        // contrôleur les passe par variable (`_buildPanel('columns', …, 'datatable.columns.button')`),
        // ce qui est exactement pourquoi `DeclaredTranslationKeys` les déclare.
        $translator = static::getContainer()->get('translator');
        self::assertInstanceOf(TranslatorBagInterface::class, $translator);

        // L'invariant est indépendant de la LOCALE : le libellé n'est pas la clé. Figer « Colonnes »
        // ferait échouer le test dans l'environnement de test, qui tourne en anglais — pour une
        // raison qui ne dirait rien du défaut visé.
        foreach ([['columns', 'button'], ['views', 'button'], ['columns', 'hint'], ['views', 'empty']] as [$panel, $key]) {
            $translationKey = sprintf('datatable.%s.%s', $panel, $key);
            $value = $translator->getCatalogue()->get($translationKey, 'javascript');

            self::assertNotEmpty($value);
            self::assertNotSame($translationKey, $value, 'Le libellé du panneau ne doit pas sortir en clé brute.');
        }
    }

    /**
     * Les deux colonnes offertes sans être montrées. `hidden` est ce qui permet de déclarer large
     * sans élargir l'écran de personne : elles sont dans le sélecteur, absentes du premier rendu.
     */
    public function testTheNameColumnsAreOfferedHidden(): void
    {
        $client = $this->authenticated();

        $crawler = $client->request('GET', '/admin/users');

        self::assertResponseIsSuccessful();
        $columns = json_decode(
            (string) $crawler->filter('table[data-core--datatable-columns-value]')->first()
                ->attr('data-core--datatable-columns-value'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($columns);

        /** @var array<string, array<string, mixed>> $byKey */
        $byKey = array_column($columns, null, 'data');
        foreach (['lastName', 'firstName'] as $key) {
            self::assertArrayHasKey($key, $byKey);
            self::assertTrue($byKey[$key]['hidden'], sprintf('La colonne %s est masquée par défaut.', $key));
        }

        // Et leur entête n'est pas celui de `fullName` : deux colonnes nommées « Nom » seraient
        // illisibles dans le sélecteur.
        self::assertNotSame($byKey['fullName']['title'], $byKey['lastName']['title']);
    }

    private function authenticated(): KernelBrowser
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        $this->user = $this->createUser(UserRoles::ROLE_ADMIN);
        $client->loginUser($this->user);

        return $client;
    }

    /**
     * Le jeton tel que la page le rend : c'est la couture entre le partial du bundle et le contrôle
     * côté serveur, et la lire ici la met à l'épreuve en même temps que l'endpoint.
     */
    private function preferencesToken(KernelBrowser $client): string
    {
        $crawler = $client->request('GET', '/admin/users');
        self::assertResponseIsSuccessful();

        $token = $crawler->filter('table[data-core--datatable-preferences-csrf-value]')->first()
            ->attr('data-core--datatable-preferences-csrf-value');
        self::assertNotEmpty($token);

        return (string) $token;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function put(KernelBrowser $client, string $token, array $payload): void
    {
        $client->request(
            'PUT',
            self::ENDPOINT,
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $token],
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function json(KernelBrowser $client): array
    {
        $decoded = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function repository(): DatatablePreferenceRepository
    {
        $repository = static::getContainer()->get(DatatablePreferenceRepository::class);
        self::assertInstanceOf(DatatablePreferenceRepository::class, $repository);

        return $repository;
    }

    private function createSchema(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $store = static::getContainer()->get(DoctrinePermissionStore::class);
        foreach (DefaultRolePermissions::map() as $role => $permissions) {
            foreach ($permissions as $permission) {
                $store->grantToRole($role, $permission, null);
            }
        }
        $entityManager->flush();
    }

    private function createUser(string $role): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User()
            ->setEmail(sprintf('%s@example.test', uniqid('prefs', false)))
            ->setFirstName('Ada')
            ->setLastName('Lovelace')
            ->setRoles(UserRoles::ROLE_USER === $role ? [] : [$role])
            ->setIsActive(true);
        $user->setPassword($hasher->hashPassword($user, 'correct-horse-battery'));

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
