<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Acl\DoctrinePermissionStore;
use App\Entity\User;
use App\Security\DefaultRolePermissions;
use App\Security\UserRoles;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function sprintf;
use function uniqid;

/**
 * Un administrateur gère les comptes que ses permissions désignent — jamais celui d'un super-administrateur,
 * et jamais jusqu'à en devenir un (2026-10-25). Avant, `user:update` offrait la case `ROLE_SUPER_ADMIN` à tout
 * administrateur : cochée sur son propre compte, elle le plaçait au-dessus de toute permission.
 */
#[CoversNothing]
final class SuperAdminAccountTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $store = static::getContainer()->get(DoctrinePermissionStore::class);
        foreach (DefaultRolePermissions::map() as $role => $permissions) {
            foreach ($permissions as $permission) {
                $store->grantToRole($role, $permission, null);
            }
        }
        $this->entityManager->flush();
    }

    public function testAnAdministratorIsNeverOfferedTheSuperAdministratorRole(): void
    {
        $admin = $this->account([UserRoles::ROLE_ADMIN]);
        $other = $this->account([]);
        $this->client->loginUser($admin);

        $this->client->request('GET', '/admin/users/new');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('input[value="ROLE_SUPER_ADMIN"]');

        $crawler = $this->client->request('GET', sprintf('/admin/users/%d/edit', $other->getId()));
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('input[value="ROLE_SUPER_ADMIN"]');
        self::assertSelectorExists('input[value="ROLE_ADMIN"]', 'Ce que l\'administrateur détient, il peut toujours l\'accorder.');

        // Le POST forgé : la case n'est pas dans le formulaire, le rôle est envoyé quand même.
        $values = $crawler->filter('form[name="user"]')->form()->getPhpValues();
        $values['user']['roles'] = [UserRoles::ROLE_SUPER_ADMIN];
        $this->client->request('POST', sprintf('/admin/users/%d/edit', $other->getId()), $values);
        self::assertResponseStatusCodeSame(422);

        $this->entityManager->clear();
        self::assertNotContains(UserRoles::ROLE_SUPER_ADMIN, $this->entityManager->getRepository(User::class)->find($other->getId())?->getRoles() ?? []);
    }

    public function testASuperAdministratorsAccountIsOutOfAnAdministratorsReach(): void
    {
        $super = $this->account([UserRoles::ROLE_SUPER_ADMIN]);
        $player = $this->account([]);
        $this->client->loginUser($this->account([UserRoles::ROLE_ADMIN]));

        $this->client->request('GET', sprintf('/admin/users/%d/edit', $super->getId()));
        self::assertResponseStatusCodeSame(403);

        $crawler = $this->client->request('GET', sprintf('/admin/users/%d', $super->getId()));
        self::assertResponseIsSuccessful('La lecture reste ouverte : user:read.');
        self::assertCount(0, $crawler->filter(sprintf('a[href="/admin/users/%d/edit"], form[action="/admin/users/%d/deactivate"]', $super->getId(), $super->getId())), 'Aucun bouton qui répondrait 403.');

        // Un jeton de ligne valide, lu là où le bouton existe : le refus vient du voter, pas du CSRF.
        $crawler = $this->client->request('GET', sprintf('/admin/users/%d', $player->getId()));
        $token = (string) $crawler->filter(sprintf('form[action="/admin/users/%d/deactivate"] input[name="_token"]', $player->getId()))->attr('value');
        $this->client->request('POST', sprintf('/admin/users/%d/deactivate', $super->getId()), ['_token' => $token]);
        self::assertResponseStatusCodeSame(403);

        // Dans une sélection, la ligne du super-administrateur reste telle quelle ; les autres suivent.
        $crawler = $this->client->request('GET', '/admin/users');
        $bulkToken = (string) $crawler->filter('table[data-controller="core--datatable"]')->attr('data-core--datatable-bulk-csrf-value');
        $this->client->request('POST', '/admin/users/bulk-deactivate', ['ids' => [$super->getId(), $player->getId()], '_token' => $bulkToken]);
        self::assertResponseRedirects('/admin/users');

        $this->entityManager->clear();
        $repository = $this->entityManager->getRepository(User::class);
        self::assertTrue($repository->find($super->getId())?->isActive());
        self::assertFalse($repository->find($player->getId())?->isActive());
    }

    public function testAnAccountWithoutANameIsRefusedWithAMessage(): void
    {
        // Ni prénom ni nom : ici le prénom est obligatoire (`Assert\NotBlank`), le refus doit être un
        // formulaire en erreur, pas une exception.
        $player = $this->account([]);
        $this->client->loginUser($this->account([UserRoles::ROLE_ADMIN]));

        $crawler = $this->client->request('GET', sprintf('/admin/users/%d/edit', $player->getId()));
        $this->client->submit($crawler->filter('form[name="user"]')->form(['user[isActive]' => false]));
        self::assertResponseStatusCodeSame(422, 'C\'était un 500 : null écrit dans une chaîne.');
    }

    public function testTheSuperAdministratorStillGrantsTheRole(): void
    {
        $other = $this->account([UserRoles::ROLE_SUPER_ADMIN]);
        $this->client->loginUser($this->account([UserRoles::ROLE_SUPER_ADMIN]));

        $this->client->request('GET', '/admin/users/new');
        self::assertSelectorExists('input[value="ROLE_SUPER_ADMIN"]');
        $this->client->request('GET', sprintf('/admin/users/%d/edit', $other->getId()));
        self::assertResponseIsSuccessful();
    }

    /**
     * @param list<string> $roles
     */
    private function account(array $roles): User
    {
        $user = new User()
            ->setEmail(sprintf('%s@example.test', uniqid('acl', false)))
            ->setPassword('not-a-hash')
            ->setRoles($roles)
            ->setIsActive(true);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
