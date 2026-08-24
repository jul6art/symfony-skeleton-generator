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
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function sprintf;

/**
 * Le thème de formulaire du projet, vérifié en RENDANT les écrans.
 *
 * ⚠️ Ce test existe à cause d'un défaut qu'aucune inspection de configuration n'attrape : les
 * `Custom*Type` d'`ui-bundle` rendent par `input_group_addon_widget`, qui écrit son PROPRE
 * `<input>` et ne délègue jamais à `form_widget_simple`. Une classe posée dans ce bloc-là
 * n'atteint donc pas le champ : l'`<input>` sort sans aucun attribut `class`, donc sans bordure
 * ni fond — blanc sur blanc, invisible, et le HTML reste parfaitement valide.
 *
 * La classe doit passer par les OPTIONS de `form_widget`, jamais par `form_widget_simple`.
 * Constaté à l'écran le 2026-08-23 sur quatre formulaires (rapport `docs/corrections/`).
 */
#[CoversNothing]
final class FormThemeTest extends WebTestCase
{
    /**
     * Le champ e-mail de « mot de passe oublié » : un `CustomEmailType` nu, sans rien autour pour
     * masquer le défaut. C'est le cas le plus dépouillé, donc le plus probant.
     */
    public function testTheForgottenPasswordEmailFieldCarriesTheControlClass(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/reset-password');

        self::assertResponseIsSuccessful();
        self::assertSame(
            1,
            $crawler->filter('input[type="email"].form-control')->count(),
            'Le champ e-mail doit porter `form-control` : sans elle il est blanc sur blanc.',
        );
    }

    public function testTheRegistrationFormCarriesTheControlClassOnEveryField(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/register');

        self::assertResponseIsSuccessful();
        self::assertSame(1, $crawler->filter('input[type="email"].form-control')->count());
        self::assertSame(
            2,
            $crawler->filter('input[type="password"].form-control')->count(),
            'Les deux bornes du RepeatedType sont des champs comme les autres.',
        );

        // Le conteneur compound du formulaire RACINE porte l'espacement : `form_widget(form)` rend
        // un `<div id="…">` intermédiaire, et un `space-y-*` posé sur le `<form>` ne descend pas
        // jusqu'aux lignes — il ne voit que ce div et le bouton.
        self::assertSame(
            1,
            $crawler->filter('form div#registration_form.form-grid')->count(),
            'Sans `form-grid` sur le conteneur, les lignes du formulaire sont collées.',
        );

        // Les deux bornes d'un RepeatedType sont dans le conteneur prévu pour elles, et non à plat.
        self::assertSame(1, $crawler->filter('.password-repeat-grid')->count());
    }

    /**
     * Un champ de mot de passe prend `CustomPasswordType` d'`ui-bundle`, jamais le `PasswordType`
     * natif : c'est le FAIL A1 de `form-checklist.md`, « l'erreur la plus fréquente ». Le type
     * dédié apporte l'œil qui révèle la saisie — et le contrôleur `form--password` qui l'anime.
     */
    public function testEveryPasswordFieldCarriesTheRevealControl(): void
    {
        $client = static::createClient();

        foreach (['/register', '/login'] as $uri) {
            $crawler = $client->request('GET', $uri);
            self::assertGreaterThan(
                0,
                $crawler->filter('[data-controller="form--password"]')->count(),
                sprintf('« %s » : le champ mot de passe doit porter son contrôleur de révélation.', $uri),
            );
        }
    }

    /**
     * ⚠️ Le jeton CSRF ne doit PAS recevoir `form-control` : c'est la preuve que le thème trie ses
     * lignes au lieu de décorer tout ce qui passe.
     */
    public function testTheHiddenCsrfFieldIsNotDecorated(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/register');

        self::assertSame(0, $crawler->filter('input[type="hidden"].form-control')->count());
    }

    public function testTheAdminUserFormRendersRolesAsChoiceCardsAndNotSystemCheckboxes(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        $client->loginUser($this->createUser(UserRoles::ROLE_SUPER_ADMIN));
        $crawler = $client->request('GET', '/admin/users/new');

        self::assertResponseIsSuccessful();

        self::assertSame(1, $crawler->filter('input[type="email"].form-control')->count(), 'CustomEmailType.');
        self::assertSame(1, $crawler->filter('input[type="password"].form-control')->count(), 'CustomPasswordType.');

        self::assertSame(
            1,
            $crawler->filter('.role-choice-grid')->count(),
            'Un ChoiceType `expanded` se rend en grille de cartes — les jetons existent dans admin-bundle.',
        );
        self::assertSame(
            2,
            $crawler->filter('input.role-choice-input')->count(),
            'Une entrée par rôle assignable.',
        );

        // L'interrupteur du compte actif reste ce qu'il est : le thème ne le casse pas au passage.
        self::assertSame(1, $crawler->filter('input.toggle-switch-input')->count());
    }

    /** Un champ en erreur porte `form-control-error` — la classe existe et n'était jamais posée. */
    public function testAnInvalidFieldCarriesTheErrorClass(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        $crawler = $client->request('GET', '/reset-password');
        $form = $crawler->filter('form')->form(['reset_password_request_form[email]' => 'pas-une-adresse']);
        $crawler = $client->submit($form);

        self::assertSame(
            1,
            $crawler->filter('input[type="email"].form-control-error')->count(),
            'Le champ refusé doit se voir.',
        );
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
            ->setEmail(sprintf('%s@example.test', uniqid('theme', false)))
            ->setFirstName('Ada')
            ->setLastName('Lovelace')
            ->setRoles([$role])
            ->setIsActive(true);
        $user->setPassword($hasher->hashPassword($user, 'correct-horse-battery'));

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
