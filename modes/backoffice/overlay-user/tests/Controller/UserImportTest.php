<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Acl\DoctrinePermissionStore;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\DefaultRolePermissions;
use App\Security\UserRoles;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function fclose;
use function fopen;
use function fwrite;
use function sprintf;
use function uniqid;

/**
 * L'import de comptes par fichier, de bout en bout — l'exemple livré avec ce mode
 * (`jul6art/dataflow-bundle`).
 *
 * ⚠️ Ce que le bundle couvre déjà, et qui n'est pas refait ici : le `leftJoin`... non, il n'y en a
 * pas — mais la lecture ligne à ligne, la correspondance par POSITION de colonne, la résolution de
 * doublons PAR LOT, le flux en `Generator` : tout cela a sa propre suite dans le bundle, sur ses
 * entités de fixture. Ce qui reste ici est ce que ce projet ne peut prouver que lui-même :
 *
 * - le mot de passe importé n'est JAMAIS celui du fichier (il n'y en a pas dans le fichier) ;
 * - un rôle inconnu est refusé plutôt qu'accepté en silence ;
 * - la même adresse deux fois dans le fichier ne crée qu'UN compte ;
 * - la garde de permission (`USER_CREATE`) ferme les deux routes à qui ne l'a pas.
 */
#[CoversNothing]
final class UserImportTest extends WebTestCase
{
    public function testOnlyAnAccountThatMayCreateUsersCanReachEitherRoute(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        $client->loginUser($this->createUser(UserRoles::ROLE_USER));

        $client->request('GET', '/admin/users/import/new');
        self::assertResponseStatusCodeSame(403);

        $client->request('POST', '/admin/users/import/execute');
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * ⚠️ **Le mot de passe n'est écrit NULLE PART dans le fichier**, et c'est le point que ce test
     * porte : le compte importé doit malgré tout recevoir un hachage — un compte sans mot de passe
     * du tout casserait la connexion d'une façon bien plus confuse qu'un mot de passe simplement
     * inconnu de son titulaire.
     */
    public function testAccountsAreCreatedWithARandomPasswordAndTheRightRole(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();
        $client->loginUser($this->createUser(UserRoles::ROLE_ADMIN));

        $csv = "firstName,lastName,email,role\nGrace,Hopper,grace@example.test,\nAda,Lovelace,ada@example.test,ROLE_ADMIN\n";
        $crawler = $this->upload($client, $csv);

        // ⚠️ Les en-têtes du fichier correspondent EXACTEMENT aux champs du mapper
        // (`UserRowMapper::FIELDS`) : le bundle les a donc déjà associés tout seul, et le
        // formulaire de correspondance porte les `<option>` déjà sélectionnées. On soumet ce que
        // l'écran propose, pas une correspondance devinée à côté.
        $client->submit($crawler->filter('form')->form());

        self::assertResponseIsSuccessful();

        $users = static::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);

        $plain = $users->findOneByEmail('grace@example.test');
        self::assertInstanceOf(User::class, $plain);
        // ⚠️ `getRoles()` ajoute TOUJOURS `ROLE_USER` (implicite, jamais stocké) : n'obtenir QUE
        // lui prouve que la colonne vide n'a rien ajouté de plus — un rôle stocké par erreur
        // apparaîtrait à côté.
        self::assertSame([UserRoles::ROLE_USER], $plain->getRoles(), 'Une colonne role vide donne le rôle standard.');
        self::assertNotSame('', $plain->getPassword(), 'Un compte sans hachage du tout casserait la connexion.');

        $admin = $users->findOneByEmail('ada@example.test');
        self::assertInstanceOf(User::class, $admin);
        self::assertSame([UserRoles::ROLE_ADMIN, UserRoles::ROLE_USER], $admin->getRoles(), 'ROLE_USER est implicite : toute liste de rôles le porte en plus du rôle stocké.');

        // ⚠️ Deux comptes DIFFÉRENTS ne doivent pas partager le même secret : chaque ligne tire son
        // propre alea.
        self::assertNotSame($plain->getPassword(), $admin->getPassword());
    }

    public function testTheSameAddressTwiceInOneFileCreatesOnlyOneAccount(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();
        $client->loginUser($this->createUser(UserRoles::ROLE_ADMIN));

        // ⚠️ La casse diffère à dessein : une adresse est insensible à la casse dans son domaine,
        // et `UserDuplicateResolver` doit les reconnaître comme LE MÊME compte malgré ça.
        $csv = "firstName,lastName,email,role\nGrace,Hopper,dup@example.test,\nGrace,Hopper,Dup@example.test,\n";
        $crawler = $this->upload($client, $csv);
        $client->submit($crawler->filter('form')->form());

        self::assertResponseIsSuccessful();

        $users = static::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);
        self::assertInstanceOf(User::class, $users->findOneByEmail('dup@example.test'));

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $count = (int) $entityManager->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->where('u.email = :email')
            ->setParameter('email', 'dup@example.test')
            ->getQuery()
            ->getSingleScalarResult();
        self::assertSame(1, $count, 'La seconde ligne, même adresse, doit être ignorée — jamais un second compte.');
    }

    /**
     * ⚠️ Un rôle qui n'est pas un de ceux que `UserRoles::assignable()` propose est REFUSÉ, plutôt
     * qu'accepté en silence : un compte avec un rôle inconnu de tout voter ne répondrait à aucune
     * permission, ce qui se découvrirait au premier clic de son titulaire — pas à l'import.
     */
    public function testAnUnknownRoleIsRejectedRatherThanStoredAsIs(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();
        $client->loginUser($this->createUser(UserRoles::ROLE_ADMIN));

        $csv = "firstName,lastName,email,role\nGrace,Hopper,grace@example.test,ROLE_GHOST\n";
        $crawler = $this->upload($client, $csv);
        $client->submit($crawler->filter('form')->form());

        self::assertResponseIsSuccessful();

        $users = static::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $users);
        self::assertNull($users->findOneByEmail('grace@example.test'), 'La ligne au rôle inconnu ne doit créer aucun compte.');

        $html = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('user.import.error.unknown_role', $html, 'La clé de traduction ne doit jamais s\'afficher brute.');
    }

    /**
     * @return Crawler la page de correspondance, colonnes déjà pré-associées
     */
    private function upload(KernelBrowser $client, string $csv): Crawler
    {
        $crawler = $client->request('GET', '/admin/users/import/new');

        $path = tempnam(sys_get_temp_dir(), 'user_import_test_');
        $handle = fopen($path, 'w');
        self::assertNotFalse($handle);
        fwrite($handle, $csv);
        fclose($handle);

        $file = new UploadedFile($path, 'accounts.csv', 'text/csv', null, true);

        $client->submit($crawler->filter('form')->form(), ['file' => $file]);

        self::assertResponseIsSuccessful('L\'écran de correspondance doit s\'afficher après le téléversement.');

        return $client->getCrawler();
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
            ->setEmail(sprintf('%s@example.test', uniqid('importer', false)))
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
