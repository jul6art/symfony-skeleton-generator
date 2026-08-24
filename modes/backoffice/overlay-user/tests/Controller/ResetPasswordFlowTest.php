<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function sprintf;

/**
 * Le parcours « mot de passe oublié », de la demande au mot de passe changé.
 *
 * ⚠️ Ce test existe à cause d'un défaut que la gate ne pouvait pas voir : le mail était routé en
 * ASYNCHRONE (`SendEmailMessage: async`) vers une file Doctrine que RIEN ne consomme — pas de
 * worker dans la pile Docker. Résultat : aucun mail n'arrivait jamais, et le jour où quelqu'un a
 * drainé la file, elle a livré un message fabriqué des heures plus tôt, avec l'objet en clé brute
 * et un jeton dont la demande avait expiré depuis longtemps. Symptôme final à l'écran :
 * « This reset link is invalid or has expired » (constaté le 2026-08-23, prouvé en base).
 *
 * Les deux assertions qui comptent :
 * 1. l'objet du mail n'est PAS une clé de traduction ;
 * 2. le lien contenu dans le mail mène réellement à un formulaire qui change le mot de passe.
 */
#[CoversNothing]
final class ResetPasswordFlowTest extends WebTestCase
{
    private const string OLD_PASSWORD = 'ancien-mot-de-passe';
    private const string NEW_PASSWORD = 'un-nouveau-mot-de-passe';

    public function testTheResetMailCarriesATranslatedSubjectAndAWorkingLink(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();
        $user = $this->createUser();

        $crawler = $client->request('GET', '/reset-password');
        $client->submit($crawler->filter('form')->form([
            'reset_password_request_form[email]' => $user->getEmail(),
        ]));

        self::assertResponseRedirects('/reset-password/check-email');
        self::assertEmailCount(1, message: 'Le mail part dans la requête : une file que rien ne consomme n\'envoie jamais.');

        // `getMailerMessage()` rend un `RawMessage` : on le RESSERRE sur `Email`, sinon ni le
        // sujet ni le corps HTML ne sont accessibles (et PHPStan 8 le refuse).
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);

        // ⚠️ L'assertion utile n'est pas « l'objet vaut telle phrase » — elle figerait la
        // traduction — mais « l'objet n'est pas une CLÉ ». `->subject('clé')` enverrait la clé
        // telle quelle, et c'est exactement ce qu'un mail livré le 2026-08-23 portait.
        self::assertStringNotContainsString('security.reset_password', $email->getSubject() ?? '');

        $link = $this->extractResetLink($email);
        $client->request('GET', $link);
        $client->followRedirect();

        self::assertResponseIsSuccessful('Le lien du mail doit ouvrir le formulaire, pas le rejeter.');
    }

    public function testTheLinkFromTheMailActuallyChangesThePassword(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();
        $user = $this->createUser();

        $crawler = $client->request('GET', '/reset-password');
        $client->submit($crawler->filter('form')->form([
            'reset_password_request_form[email]' => $user->getEmail(),
        ]));

        $link = $this->extractResetLink(self::getMailerMessage());
        $client->request('GET', $link);
        $crawler = $client->followRedirect();

        $client->submit($crawler->filter('form')->form([
            'new_password_form[plainPassword][first]' => self::NEW_PASSWORD,
            'new_password_form[plainPassword][second]' => self::NEW_PASSWORD,
        ]));

        self::assertResponseRedirects('/login');

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $reloaded = $entityManager->getRepository(User::class)->find($user->getId());

        self::assertInstanceOf(User::class, $reloaded);
        self::assertTrue($hasher->isPasswordValid($reloaded, self::NEW_PASSWORD), 'Le nouveau mot de passe doit authentifier.');
        self::assertFalse($hasher->isPasswordValid($reloaded, self::OLD_PASSWORD), 'L\'ancien ne doit plus.');
    }

    /** Un jeton est à usage unique : le rejouer doit échouer, et le dire dans la langue de l'écran. */
    public function testAConsumedLinkIsRefused(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();
        $user = $this->createUser();

        $crawler = $client->request('GET', '/reset-password');
        $client->submit($crawler->filter('form')->form([
            'reset_password_request_form[email]' => $user->getEmail(),
        ]));

        $link = $this->extractResetLink(self::getMailerMessage());
        $client->request('GET', $link);
        $crawler = $client->followRedirect();
        $client->submit($crawler->filter('form')->form([
            'new_password_form[plainPassword][first]' => self::NEW_PASSWORD,
            'new_password_form[plainPassword][second]' => self::NEW_PASSWORD,
        ]));

        // Le premier passage ne fait que remettre le jeton en session et rediriger vers la route
        // sans jeton (il sort de l'URL pour ne pas fuir dans le `Referer`). C'est le SECOND
        // passage qui le valide — donc qui le refuse.
        $client->request('GET', $link);
        $client->followRedirect();

        self::assertResponseRedirects('/reset-password');
    }

    /**
     * L'adresse inconnue ne doit RIEN révéler : même redirection, et aucun mail.
     * C'est la protection contre l'énumération de comptes, et elle se vérifie.
     */
    public function testAnUnknownAddressIsIndistinguishable(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();

        $crawler = $client->request('GET', '/reset-password');
        $client->submit($crawler->filter('form')->form([
            'reset_password_request_form[email]' => 'personne@example.test',
        ]));

        self::assertResponseRedirects('/reset-password/check-email');
        self::assertEmailCount(0);
    }

    /**
     * L'étranglement du bundle : une seule demande par fenêtre. Le SILENCE fait partie de la
     * fonctionnalité — la seconde demande répond exactement comme la première, sans mail. Ce test
     * fige les deux moitiés : pas de second mail, et pas non plus d'indice à l'écran.
     */
    public function testASecondRequestWithinTheThrottleWindowSendsNothingAndSaysNothing(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->createSchema();
        $user = $this->createUser();

        // ⚠️ Le collecteur de mails est remis à zéro à CHAQUE requête du client : on compte donc
        // après chacune, pas une fois à la fin — sinon on mesure la dernière et on croit à zéro.
        $expected = [1 => 1, 2 => 0];

        foreach ($expected as $attempt => $mails) {
            $crawler = $client->request('GET', '/reset-password');
            $client->submit($crawler->filter('form')->form([
                'reset_password_request_form[email]' => $user->getEmail(),
            ]));

            self::assertResponseRedirects('/reset-password/check-email', message: sprintf('Demande %d : même réponse, toujours.', $attempt));
            self::assertEmailCount($mails, message: sprintf('Demande %d : %d mail attendu.', $attempt, $mails));
        }
    }

    private function extractResetLink(?object $email): string
    {
        self::assertInstanceOf(Email::class, $email);

        // `self::fail()` plutôt qu'une assertion suivie d'un accès : elle rend `never`, donc le
        // chemin d'échec est clos pour l'analyse statique comme pour le lecteur.
        if (1 !== preg_match('#/reset-password/reset/[A-Za-z0-9]+#', (string) $email->getHtmlBody(), $matches)) {
            self::fail('Le mail doit porter un lien de réinitialisation.');
        }

        return $matches[0];
    }

    private function createSchema(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    private function createUser(): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User()
            ->setEmail(sprintf('%s@example.test', uniqid('reset', false)))
            ->setFirstName('Ada')
            ->setLastName('Lovelace')
            ->setIsActive(true);
        $user->setPassword($hasher->hashPassword($user, self::OLD_PASSWORD));

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
