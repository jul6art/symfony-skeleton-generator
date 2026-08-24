<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Acl\DoctrinePermissionStore;
use App\Entity\User;
use App\Security\DefaultRolePermissions;
use App\Security\UserRoles;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function sprintf;

/**
 * La fiche d'un compte, et ce que le profil en fait après enregistrement.
 *
 * ⚠️ La redirection du profil est gardée, et ce garde n'est pas facultatif : `admin_user_show`
 * porte `#[IsGranted(PermissionCodes::USER_READ)]`, que `DefaultRolePermissions` n'accorde PAS à
 * un compte ordinaire. Rediriger sans condition enverrait la moitié des comptes sur un 403 juste
 * après un enregistrement réussi.
 */
#[CoversNothing]
final class UserShowTest extends WebTestCase
{
    public function testTheProfileFicheShowsTheAvatarAndTheAccountFacts(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        $admin = $this->createUser(UserRoles::ROLE_ADMIN);
        $client->loginUser($admin);
        $crawler = $client->request('GET', sprintf('/admin/users/%d', $admin->getId()));

        self::assertResponseIsSuccessful();

        // L'avatar : c'est la première chose qu'on vient vérifier après avoir changé sa photo, et
        // la fiche ne le montrait pas du tout.
        // Scopé à `main` : la coquille pose déjà deux avatars (barre du haut, pied de barre
        // latérale), et un sélecteur global passerait sans que la FICHE en montre un.
        self::assertGreaterThan(0, $crawler->filter('main .rounded-full.overflow-hidden')->count(), 'La fiche montre l\'avatar.');

        self::assertGreaterThan(0, $crawler->filter('main nav a')->count(), 'Un fil d\'Ariane ramène à la liste.');
        self::assertGreaterThan(0, $crawler->filter('main .badge-active, main .badge-inactive')->count(), 'Le statut se lit d\'un coup d\'œil.');

        $html = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('ROLE_USER', $html, 'ROLE_USER vient de la hiérarchie : l\'afficher laisse croire qu\'on peut le retirer.');
        self::assertStringNotContainsString('user.field.', $html, 'Aucune clé brute.');
        self::assertStringNotContainsString('user.show.', $html);
    }

    /** Sur SA PROPRE fiche, « Éditer » mène au profil — pas à l'écran d'administration des comptes. */
    public function testTheEditLinkOnOwnFichePointsAtTheProfile(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        $admin = $this->createUser(UserRoles::ROLE_ADMIN);
        $client->loginUser($admin);
        $crawler = $client->request('GET', sprintf('/admin/users/%d', $admin->getId()));

        $hrefs = $crawler->filter('a')->extract(['href']);
        self::assertContains('/admin/account/profile', $hrefs);
        self::assertNotContains(sprintf('/admin/users/%d/edit', $admin->getId()), $hrefs);
    }

    public function testSavingTheProfileLandsOnTheFicheWhenTheAccountMayReadIt(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        $admin = $this->createUser(UserRoles::ROLE_ADMIN);
        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin/account/profile');

        $client->submit($crawler->filter('form')->form([
            'profile_form[firstName]' => 'Grace',
            'profile_form[lastName]' => 'Hopper',
        ]));

        self::assertResponseRedirects(sprintf('/admin/users/%d', $admin->getId()));
    }

    /**
     * ⚠️ Le cas qui attrape le 403 : un compte ordinaire n'a pas `user:read`, donc la fiche lui
     * est fermée. Il doit revenir sur son profil.
     */
    public function testSavingTheProfileStaysOnTheProfileWhenTheFicheIsClosed(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        $client->loginUser($this->createUser(UserRoles::ROLE_USER));
        $crawler = $client->request('GET', '/admin/account/profile');

        $client->submit($crawler->filter('form')->form([
            'profile_form[firstName]' => 'Grace',
            'profile_form[lastName]' => 'Hopper',
        ]));

        self::assertResponseRedirects('/admin/account/profile');
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
            ->setEmail(sprintf('%s@example.test', uniqid('fiche', false)))
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
