<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Acl\DoctrinePermissionStore;
use App\Entity\User;
use App\Security\DefaultRolePermissions;
use App\Security\UserRoles;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function sprintf;

/**
 * « Couleurs et icônes » — l'item D8 de `docs/checklist/form-checklist.md`, rendu exécutable.
 *
 * Un bouton nu se lit comme un lien, et une action colorée autrement que son homologue ailleurs se
 * lit comme une action différente : « Désactiver » était gris sur la fiche d'un compte et orange
 * dans le menu de sa ligne, pour la même route. Les classes `.btn-*` portent déjà
 * `inline-flex items-center gap-2`, donc l'icône ne coûte rien de plus qu'une balise.
 *
 * ⚠️ Le test RESSORT les écrans plutôt que d'inspecter les gabarits : une classe n'a de sens que
 * rendue, et c'est le seul niveau où l'on voit qu'un `{% if %}` a laissé un bouton derrière.
 */
#[CoversNothing]
final class ActionButtonTest extends WebTestCase
{
    /**
     * Écran → sélecteur du bouton de soumission, icône attendue.
     *
     * @return iterable<string, array{string, string, string}>
     */
    public static function saveButtons(): iterable
    {
        yield 'création d\'un compte' => ['/admin/users/new', 'form button[type="submit"].btn-primary', 'fa-floppy-disk'];
        yield 'profil' => ['/admin/account/profile', 'form button[type="submit"].btn-primary', 'fa-floppy-disk'];
        yield 'mot de passe' => ['/admin/account/password', 'form button[type="submit"].btn-primary', 'fa-floppy-disk'];
        yield 'permissions' => ['/admin/role-permissions', 'form button[type="submit"].btn-primary', 'fa-floppy-disk'];
    }

    #[DataProvider('saveButtons')]
    public function testEverySaveButtonCarriesItsIcon(string $path, string $selector, string $icon): void
    {
        $crawler = $this->openAsAdmin($path);

        $button = $crawler->filter($selector);
        self::assertSame(1, $button->count(), sprintf('%s doit porter un bouton d\'enregistrement en btn-primary.', $path));
        self::assertSame(
            1,
            $button->filter(sprintf('i.%s', $icon))->count(),
            sprintf('Le bouton d\'enregistrement de %s doit porter l\'icône %s.', $path, $icon),
        );
    }

    /** Annuler est une sortie : secondaire, et sa croix. */
    public function testTheCancelLinkIsSecondaryAndCarriesItsIcon(): void
    {
        $crawler = $this->openAsAdmin('/admin/users/new');

        $cancel = $crawler->filter('.form-actions a.btn-secondary');
        self::assertSame(1, $cancel->count());
        self::assertSame(1, $cancel->filter('i.fa-xmark')->count());
    }

    /**
     * Le cas qui a motivé la règle : sur la fiche d'un compte ACTIF, le bouton qui le désactive
     * doit être orange et porter l'interdit — exactement comme l'action « Désactiver » du menu de
     * sa ligne dans la table, et comme la modale que ce bouton ouvre (`confirm-variant: warning`).
     */
    public function testTheDeactivateButtonMatchesItsRowActionAndItsModal(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        $admin = $this->createUser(UserRoles::ROLE_SUPER_ADMIN);
        $target = $this->createUser(UserRoles::ROLE_USER);
        $client->loginUser($admin);

        $crawler = $client->request('GET', sprintf('/admin/users/%d', $target->getId()));
        self::assertResponseIsSuccessful();

        $button = $crawler->filter('form[data-controller="ui--modal"] button[type="submit"]');
        self::assertSame(1, $button->count());
        self::assertStringContainsString('btn-warning', (string) $button->attr('class'), 'Un compte actif se désactive : orange.');
        self::assertSame(1, $button->filter('i.fa-ban')->count(), 'La même icône que l\'action « Désactiver » d\'une ligne.');
    }

    /** Et son symétrique : sur un compte inactif, « Activer » est vert et porte la coche. */
    public function testTheActivateButtonIsGreen(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        $admin = $this->createUser(UserRoles::ROLE_SUPER_ADMIN);
        $target = $this->createUser(UserRoles::ROLE_USER, active: false);
        $client->loginUser($admin);

        $crawler = $client->request('GET', sprintf('/admin/users/%d', $target->getId()));

        $button = $crawler->filter('form[data-controller="ui--modal"] button[type="submit"]');
        self::assertStringContainsString('btn-success', (string) $button->attr('class'), 'Un compte inactif s\'active : vert.');
        self::assertSame(1, $button->filter('i.fa-circle-check')->count());
    }

    /** « Voir » et « Modifier » restent sobres : ce sont des lectures, pas des transitions. */
    public function testTheEditLinkStaysSober(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        $admin = $this->createUser(UserRoles::ROLE_SUPER_ADMIN);
        $target = $this->createUser(UserRoles::ROLE_USER);
        $client->loginUser($admin);

        $crawler = $client->request('GET', sprintf('/admin/users/%d', $target->getId()));
        $edit = $crawler->filter(sprintf('a[href="/admin/users/%d/edit"]', $target->getId()));

        self::assertSame(1, $edit->count());
        self::assertStringContainsString('btn-secondary', (string) $edit->attr('class'));
        self::assertSame(1, $edit->filter('i.fa-pen-to-square')->count());
    }

    private function openAsAdmin(string $path): Crawler
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        $client->loginUser($this->createUser(UserRoles::ROLE_SUPER_ADMIN));
        $crawler = $client->request('GET', $path);

        self::assertResponseIsSuccessful();

        return $crawler;
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

    private function createUser(string $role, bool $active = true): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User()
            ->setEmail(sprintf('%s@example.test', uniqid('btn', false)))
            ->setFirstName('Ada')
            ->setLastName('Lovelace')
            ->setRoles(UserRoles::ROLE_USER === $role ? [] : [$role])
            ->setIsActive($active);
        $user->setPassword($hasher->hashPassword($user, 'correct-horse-battery'));

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
