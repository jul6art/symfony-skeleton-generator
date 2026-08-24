<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Acl\DoctrinePermissionStore;
use App\Entity\User;
use App\Security\BackofficeLocales;
use App\Security\DefaultRolePermissions;
use App\Security\UserRoles;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * Ce que la table des comptes DESCEND au JavaScript, et pas seulement ce qu'elle configure.
 *
 * Un rendu de badge résout son libellé par `this.t('datatable.<carte>.<clé>')`, qui descend l'arbre
 * posé dans `data-…-translations-value`. Trois surfaces doivent donc s'accorder — la carte
 * (`datatable.status_maps`), le rendu (`assets/datatable/renderers.js`) et le TRANSPORT
 * (`extra_translations` de l'include). Les deux premières étaient bonnes, la troisième absente :
 * la cellule affichait `datatable.user_role.ROLE_SUPER_ADMIN`, avec la bonne couleur, pendant que
 * la clé existait dans le catalogue. Aucune erreur, aucun test rouge (2026-08-24).
 *
 * Le test assert sur la VALEUR TRADUITE et non sur la présence de la clé : c'est ce qui attrape
 * aussi une carte descendue depuis le mauvais domaine, qui rendrait la clé brute à l'identique.
 */
#[CoversNothing]
final class UserDataTableTranslationsTest extends WebTestCase
{
    /** @return iterable<string, array{string, string, string}> */
    public static function roleLabels(): iterable
    {
        yield 'fr / super admin' => ['fr', 'ROLE_SUPER_ADMIN', 'Super administrateur'];
        yield 'fr / admin' => ['fr', 'ROLE_ADMIN', 'Administrateur'];
        yield 'en / super admin' => ['en', 'ROLE_SUPER_ADMIN', 'Super administrator'];
        yield 'en / admin' => ['en', 'ROLE_ADMIN', 'Administrator'];
    }

    #[DataProvider('roleLabels')]
    public function testTheRoleBadgeLabelsReachTheBrowser(string $locale, string $role, string $expected): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        $admin = $this->createUser(UserRoles::ROLE_ADMIN, $locale);
        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin/users');

        self::assertResponseIsSuccessful();

        $raw = $crawler->filter('table[data-controller]')->attr('data-core--datatable-translations-value');
        /** @var array{datatable?: array{user_role?: array<string, string>}} $translations */
        $translations = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(
            $expected,
            $translations['datatable']['user_role'][$role] ?? null,
            sprintf('Le libellé de %s doit atteindre le navigateur en « %s », traduit.', $role, $locale),
        );
    }

    /**
     * Le pendant du précédent, vu depuis l'écran : aucune clé du domaine ne doit se lire dans le
     * HTML. C'est l'assertion qui aurait échoué si le transport avait été oublié sur une AUTRE
     * carte que `user_role`.
     */
    public function testTheListShowsNoRawTranslationKey(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        $client->loginUser($this->createUser(UserRoles::ROLE_ADMIN, BackofficeLocales::DEFAULT));
        $client->request('GET', '/admin/users');

        $html = (string) $client->getResponse()->getContent();

        self::assertStringNotContainsString('datatable.user_role.', $html, 'Aucune clé brute de carte de statuts.');
        self::assertStringNotContainsString('user.field.', $html, 'Aucune clé brute d\'en-tête de colonne.');
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

    private function createUser(string $role, string $locale): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User()
            ->setEmail(sprintf('%s@example.test', uniqid('dt', false)))
            ->setFirstName('Ada')
            ->setLastName('Lovelace')
            ->setRoles(UserRoles::ROLE_USER === $role ? [] : [$role])
            ->setLocale($locale)
            ->setIsActive(true);
        $user->setPassword($hasher->hashPassword($user, 'correct-horse-battery'));

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
