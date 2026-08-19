<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\Voter\UserVoter;
use Jul6Art\AuthBundle\Entity\User;
use Jul6Art\AuthBundle\Factory\UserFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

use function in_array;

/**
 * Le voter est la seule autorité sur les accès aux comptes : ces cas figent ses
 * règles, sans conteneur ni base de données.
 */
final class UserVoterTest extends TestCase
{
    public function testOnlyAnAdministratorListsAccounts(): void
    {
        self::assertFalse($this->granted($this->member(), UserVoter::LIST));
        self::assertTrue($this->granted($this->administrator(), UserVoter::LIST));
    }

    public function testAnAccountIsReadableByItsOwnerAndByAnAdministrator(): void
    {
        $member = $this->member();

        self::assertTrue($this->granted($member, UserVoter::VIEW, $member));
        self::assertFalse($this->granted($member, UserVoter::VIEW, $this->other()));
        self::assertTrue($this->granted($this->administrator(), UserVoter::VIEW, $this->other()));
    }

    public function testNobodyDeletesTheAccountTheyAreSignedInWith(): void
    {
        $administrator = $this->administrator();

        self::assertFalse($this->granted($administrator, UserVoter::DELETE, $administrator));
        self::assertTrue($this->granted($administrator, UserVoter::DELETE, $this->other()));
    }

    /**
     * Un attribut étranger au voter le laisse s'abstenir : c'est ce verdict que
     * Symfony met en cache, et il ne doit jamais devenir un refus.
     */
    public function testAnAttributeTheVoterDoesNotCarryLeavesItAbstaining(): void
    {
        $voter = $this->voter(true);

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($this->token($this->administrator()), null, [User::ROLE_ADMIN]),
        );
    }

    private function granted(User $user, string $attribute, mixed $subject = null): bool
    {
        // Le rôle stocké tient lieu de hiérarchie : c'est ce que rendrait
        // `Security::isGranted()` avec le `role_hierarchy` du projet.
        $isAdmin = in_array(User::ROLE_ADMIN, $user->getRoles(), true);

        return VoterInterface::ACCESS_GRANTED === $this->voter($isAdmin)
            ->vote($this->token($user), $subject, [$attribute]);
    }

    private function voter(bool $isAdmin): UserVoter
    {
        // createStub et non createMock : ce double n'a rien à vérifier, il
        // ne fait que répondre à la place de la hiérarchie des rôles.
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($isAdmin);

        $voter = new UserVoter();
        $voter->setSecurity($security);

        return $voter;
    }

    private function token(User $user): TokenInterface
    {
        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }

    private function member(): User
    {
        return UserFactory::create()->setEmail('membre@exemple.com');
    }

    private function other(): User
    {
        return UserFactory::create()->setEmail('autre@exemple.com');
    }

    private function administrator(): User
    {
        return UserFactory::create()
            ->setEmail('admin@exemple.com')
            ->setRoles([User::ROLE_ADMIN]);
    }
}
