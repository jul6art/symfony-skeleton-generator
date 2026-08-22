<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\DefaultRolePermissions;
use App\Security\PermissionCodes;
use App\Security\UserRoles;
use Jul6Art\AclBundle\Security\PermissionCodeParser;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function sprintf;

/**
 * La couture entre le catalogue de ce projet et le moteur du bundle.
 *
 * Le bundle a sa propre suite ; ce qu'il ne peut pas savoir, c'est si LES CODES D'ICI lui sont
 * lisibles, et si le moteur est réellement branché sur le stockage de ce projet. Les deux
 * échouent en silence :
 *
 * - un code mal orthographié **ouvre** l'écran au lieu de le fermer — le voter s'abstient sur ce
 *   que son parseur ne sait pas lire, et une décision où tous les voters s'abstiennent vaut
 *   « accordé » sous la stratégie par défaut de Symfony ;
 * - sans fournisseur de permissions enregistré, le moteur ne refuse que… presque tout, ce qui
 *   ressemble à une application très verrouillée plutôt qu'à un câblage manquant.
 */
final class AclWiringTest extends KernelTestCase
{
    public function testEveryCatalogueCodeIsReadableByTheEngine(): void
    {
        $parser = new PermissionCodeParser();

        foreach (PermissionCodes::all() as $code) {
            $parsed = $parser->parse($code);

            self::assertNotSame('', $parsed['resource'], $code);
            self::assertNotSame('', $parsed['action'], $code);
        }
    }

    public function testTheCatalogueHasNoDuplicate(): void
    {
        $codes = PermissionCodes::all();

        self::assertSame($codes, array_values(array_unique($codes)));
    }

    /** Une valeur par défaut qui cite un code disparu du catalogue n'accorde rien, sans le dire. */
    public function testEveryDefaultReferencesAnExistingCode(): void
    {
        $catalogue = PermissionCodes::all();

        foreach (DefaultRolePermissions::map() as $role => $permissions) {
            foreach ($permissions as $permission) {
                self::assertContains($permission, $catalogue, sprintf('%s accorde un code inconnu : %s', $role, $permission));
            }
        }
    }

    /**
     * ⚠️ Le super-admin ne doit PAS avoir de permissions par défaut : le moteur le laisse passer
     * avant de consulter le stockage. Lui en donner les rendrait retirables depuis l'écran, ce qui
     * permettrait de s'enfermer dehors.
     */
    public function testTheSuperAdminHasNoStoredPermissions(): void
    {
        self::assertArrayNotHasKey('ROLE_SUPER_ADMIN', DefaultRolePermissions::map());
    }

    /**
     * Le fournisseur de permissions du projet est bien celui que le moteur interroge — prouvé par
     * le COMPORTEMENT, pas par l'existence d'un service : une permission accordée à un rôle en
     * base doit être lue par le moteur. Sans fournisseur branché, ce test répond faux et la
     * défaillance silencieuse (« seul un super-admin passe ») devient visible.
     */
    public function testThePermissionStorageIsWired(): void
    {
        self::bootKernel();

        $entityManager = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $store = static::getContainer()->get(\App\Acl\DoctrinePermissionStore::class);
        $store->grantToRole(UserRoles::ROLE_ADMIN, PermissionCodes::USER_READ, null);
        $entityManager->flush();

        $decision = static::getContainer()->get(\Jul6Art\AclBundle\Security\PermissionDecisionService::class);

        // ⚠️ PERSISTÉ, et ce n'est pas un détail de fixture : le cache de lecture du moteur
        // refuse tout utilisateur sans id — un compte non persisté n'a pas d'identité stable à
        // mettre en cache, et n'a par construction aucune permission stockée. Un `new User()`
        // jamais flushé serait refusé quel que soit le câblage, et ce test ne prouverait rien.
        $user = new \App\Entity\User()->setEmail('wired@test.test')->setRoles([UserRoles::ROLE_ADMIN])->setIsActive(true);
        $user->setPassword('irrelevant');
        $entityManager->persist($user);
        $entityManager->flush();

        self::assertTrue(
            $decision->isGranted($user, PermissionCodes::USER_READ),
            'Le moteur ne lit pas le stockage du projet : vérifier les alias des contrats dans services.yaml.',
        );
        self::assertFalse(
            $decision->isGranted($user, PermissionCodes::USER_DELETE),
            'Une permission jamais accordée doit rester refusée.',
        );
    }
}
