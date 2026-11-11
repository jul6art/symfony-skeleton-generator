<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\User;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use function count;
use function sprintf;

/**
 * Un nom ne s'écrit pas comme une balise.
 *
 * ⚠️ Les tables du back-office affichaient les noms sans les échapper : un nom
 * `<img src=x onerror=…>` s'exécutait chez l'administrateur qui ouvrait la liste.
 * datatable-bundle 3.1.1 échappe désormais ce qu'il affiche ; cette contrainte est la seconde
 * barrière, à la saisie.
 */
#[CoversNothing]
final class NameWithoutMarkupTest extends KernelTestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function names(): iterable
    {
        yield 'un prénom' => ['Jean-Luc', true];
        yield 'des accents et une apostrophe' => ["Zoé d'Aubigné", true];
        yield 'un pseudonyme' => ['Jul6art', true];
        yield 'une balise image' => ['<img src=x onerror=alert(1)>', false];
        yield 'un chevron seul' => ['a > b', false];
    }

    #[DataProvider('names')]
    public function testANameCannotCarryMarkup(string $name, bool $valid): void
    {
        self::bootKernel();
        $validator = self::getContainer()->get(ValidatorInterface::class);
        self::assertInstanceOf(ValidatorInterface::class, $validator);

        foreach (['firstName', 'lastName'] as $property) {
            $violations = $validator->validatePropertyValue(User::class, $property, $name);

            self::assertSame($valid, 0 === count($violations), sprintf('%s « %s »', $property, $name));
        }
    }
}
