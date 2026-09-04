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
use Symfony\Component\Translation\TranslatorBagInterface;

use function sprintf;

/**
 * Ce que la table des comptes affiche vraiment, et dans quelle langue.
 *
 * ⚠️ Il n'y a plus de TRANSPORT à éprouver. Jusqu'à `datatable-bundle` v2, un rendu de badge lisait
 * son libellé dans un arbre JSON posé par le gabarit dans `data-…-translations-value`, et trois
 * surfaces devaient s'accorder — la carte, le rendu, et l'include qui descendait l'arbre. La
 * troisième avait été oubliée : la cellule affichait `datatable.user_role.ROLE_SUPER_ADMIN` avec
 * la bonne couleur pendant que la clé existait dans le catalogue (2026-08-24). Le navigateur a
 * maintenant le catalogue lui-même, et {@see \App\Tests\Translation\JsTranslationTest} tient les
 * deux bouts.
 *
 * Ce qui reste à prouver ici, et que le garde ne dit pas : que les libellés atteignent le paquet
 * JavaScript **traduits**, dans chaque langue. Une clé présente dans le bon domaine mais vide, ou
 * recopiée du français à l'anglais, passerait le garde sans que personne ne le voie.
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

    /**
     * ⚠️ Le test lit le CATALOGUE de la locale, pas la page. C'est ce que `ux-translator` dépose
     * dans `var/translations/index.js`, donc exactement ce que le navigateur recevra — et la seule
     * surface où la traduction d'un badge est encore observable côté serveur.
     */
    #[DataProvider('roleLabels')]
    public function testTheRoleBadgeLabelsAreTranslatedInEveryLocale(string $locale, string $role, string $expected): void
    {
        static::createClient();

        $translator = static::getContainer()->get('translator');
        self::assertInstanceOf(TranslatorBagInterface::class, $translator);

        $key = sprintf('datatable.user_role.%s', $role);
        $catalogue = $translator->getCatalogue($locale);

        self::assertTrue(
            $catalogue->defines($key, 'javascript'),
            sprintf('« %s » doit vivre dans le domaine `javascript` : c\'est le seul que le navigateur reçoit.', $key),
        );
        self::assertSame(
            $expected,
            $catalogue->get($key, 'javascript'),
            sprintf('Le libellé de %s doit être traduit en « %s ».', $role, $locale),
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
