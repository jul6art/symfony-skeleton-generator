<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\Voter\AdminVoter;
use Jul6Art\AuthBundle\Entity\User;
use Jul6Art\AuthBundle\Factory\UserFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * La porte du back-office : seul un administrateur l'ouvre.
 */
final class AdminVoterTest extends TestCase
{
    public function testOnlyAnAdministratorEntersTheBackOffice(): void
    {
        self::assertFalse($this->granted(false));
        self::assertTrue($this->granted(true));
    }

    private function granted(bool $isAdmin): bool
    {
        // createStub et non createMock : ce double n'a rien à vérifier, il
        // ne fait que répondre à la place de la hiérarchie des rôles.
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($isAdmin);

        $voter = new AdminVoter();
        $voter->setSecurity($security);

        $user = UserFactory::create()->setEmail('compte@exemple.com');
        if ($isAdmin) {
            $user->setRoles([User::ROLE_ADMIN]);
        }

        return VoterInterface::ACCESS_GRANTED === $voter->vote(
            new UsernamePasswordToken($user, 'main', $user->getRoles()),
            null,
            [AdminVoter::ACCESS],
        );
    }
}
