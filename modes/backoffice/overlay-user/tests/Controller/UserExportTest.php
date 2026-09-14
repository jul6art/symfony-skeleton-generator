<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Acl\DoctrinePermissionStore;
use App\Entity\User;
use App\Security\DefaultRolePermissions;
use App\Security\UserRoles;
use App\Service\UserCsvExporter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function explode;
use function sprintf;
use function str_getcsv;
use function trim;
use function uniqid;

/**
 * L'export CSV de la liste des comptes — l'exemple livré avec ce mode
 * (`jul6art/dataflow-bundle`).
 *
 * ⚠️ **En FLUX, et c'est ce que ce test doit prouver plutôt que supposer** : une
 * `StreamedResponse` que le contrôleur construirait mais que `UserCsvExporter::rows()`
 * matérialiserait quand même en tableau passerait un test qui ne regarde que le TYPE de la
 * réponse. Rien ici ne mesure la mémoire — {@see UserRowMapper} et le générateur qu'il alimente
 * sont couverts côté bundle — mais le contenu réel du fichier, lui, ne l'est que par ce test.
 */
#[CoversNothing]
final class UserExportTest extends WebTestCase
{
    public function testOnlyAnAccountThatMayReadUsersCanExportThem(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        $client->loginUser($this->createUser(UserRoles::ROLE_USER));
        $client->request('GET', '/admin/users/export.csv');

        self::assertResponseStatusCodeSame(403);
    }

    public function testTheExportStreamsEveryAccountWithoutAnySecret(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        $admin = $this->createUser(UserRoles::ROLE_ADMIN);
        $second = $this->createUser(UserRoles::ROLE_USER, 'Grace', 'Hopper');

        $client->loginUser($admin);
        $client->request('GET', '/admin/users/export.csv');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');
        self::assertInstanceOf(StreamedResponse::class, $client->getResponse(), 'L\'export doit couler : dix mille comptes ne se chargent pas en mémoire pour être écrits en CSV.');

        // ⚠️ Le corps se lit sur la réponse INTERNE, jamais par un second appel à
        // `getContent()` : `HttpKernelBrowser` a déjà fait couler le flux dans un tampon, et une
        // `StreamedResponse` ne coule qu'UNE fois. Un second appel rendrait une chaîne vide, et le
        // test passerait sur un export vide en croyant l'avoir lu.
        $csv = (string) $client->getInternalResponse()->getContent();
        $lines = array_values(array_filter(explode("\r\n", $csv), static fn (string $line): bool => '' !== trim($line)));

        // ⚠️ `escape: ''` explicite : PHP 8.5 déprécie le paramètre implicite, et c'est aussi le
        // bon réglage — le dialecte par défaut du bundle n'échappe pas à l'antislash, il double
        // l'enceinte, ce qui est ce que `str_getcsv()` doit lire pour s'accorder avec lui.
        self::assertSame(UserCsvExporter::HEADER, str_getcsv($lines[0], escape: ''), 'L\'en-tête doit être EXACTEMENT celui de UserCsvExporter::HEADER.');
        self::assertCount(3, $lines, 'Un en-tête et deux comptes.');

        $rows = [str_getcsv($lines[1], escape: ''), str_getcsv($lines[2], escape: '')];
        $byEmail = [];
        foreach ($rows as $row) {
            $email = $row[1];
            self::assertIsString($email, 'La colonne e-mail ne doit jamais être vide.');
            $byEmail[$email] = $row;
        }

        self::assertArrayHasKey($admin->getEmail(), $byEmail);
        self::assertArrayHasKey($second->getEmail(), $byEmail);

        // ⚠️ **La colonne `roles`, en clair — pas seulement présente.** `HYDRATE_SCALAR` rend cette
        // colonne encore en JSON texte (`'["ROLE_ADMIN"]'`), jamais en tableau PHP : un export qui
        // ne décoderait pas ce JSON avant de l'écrire produirait une colonne VIDE plutôt qu'une
        // erreur — `implode(',', $roles)` sur une chaîne rend `''` en silence. Sans cette
        // assertion, le mutant qui retire le décodage reste vert.
        //
        // ⚠️ **La colonne exportée est le rôle STOCKÉ, pas `getRoles()`.** `ROLE_USER` est ajouté
        // par l'accesseur à la lecture, jamais persisté — la requête DQL lit la colonne brute, donc
        // le compte « standard » ci-dessous exporte une colonne VIDE, pas `ROLE_USER`.
        self::assertSame('ROLE_ADMIN', $byEmail[$admin->getEmail()][4]);
        self::assertSame('', $byEmail[$second->getEmail()][4]);

        self::assertStringNotContainsString('$2y$', $csv, 'Aucun hachage de mot de passe ne doit apparaître dans le fichier.');
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

    private function createUser(string $role, string $firstName = 'Ada', string $lastName = 'Lovelace'): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User()
            ->setEmail(sprintf('%s@example.test', uniqid('export', false)))
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setRoles(UserRoles::ROLE_USER === $role ? [] : [$role])
            ->setIsActive(true);
        $user->setPassword($hasher->hashPassword($user, 'correct-horse-battery'));

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
