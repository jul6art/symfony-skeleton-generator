<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use ApiPlatform\Metadata\ApiResource;
use Jul6Art\CoreBundle\Entity\Traits\SoftDeletableTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

use function dirname;
use function in_array;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Une entité supprimable en douceur descend les DEUX champs que l'écran attend.
 *
 * ⚠️ **`deletedAt` et `isDeleted` ne se remplacent pas** — ils servent deux mécanismes distincts,
 * et il manquait toujours l'un des deux le 2026-08-26 :
 *
 * - **`deletedAt`** est lu par `createdRow` du contrôleur Stimulus du `datatable-bundle` pour poser
 *   la classe `datatable-row-deleted`. Sans lui, une ligne supprimée s'affiche exactement comme une
 *   vivante — mesuré sur `/admin/customers` : 25 lignes supprimées, aucune teintée, l'une d'elles
 *   badgée « Actif » et proposant « Restaurer ».
 * - **`isDeleted`** est lu par les `condition` des actions. Une condition qui lit un champ ABSENT
 *   de la réponse vaut `undefined` : `row.isDeleted` est faux pour tout le monde (« Restaurer »
 *   n'apparaît JAMAIS) et `!row.isDeleted` vrai pour tout le monde (« Supprimer » s'offre sur une
 *   ligne déjà supprimée). Mesuré sur `/admin/sites` : la ligne est bien teintée, et son menu
 *   propose « Supprimer » sans « Restaurer ».
 *
 * Les deux pannes sont SILENCIEUSES et se ressemblent si peu qu'on ne les cherche pas ensemble.
 *
 * ⚠️ La liste des entités n'est pas tenue à la main : elle est DÉCOUVERTE dans `src/Entity/`. C'est
 * ce qui fera tomber le test le jour où un lot ajoutera une entité supprimable sans y penser —
 * une liste explicite, elle, aurait simplement oublié la suivante.
 */
#[CoversNothing]
final class SoftDeletableExposureTest extends KernelTestCase
{
    /**
     * Une entité qui n'utilise pas le trait n'a rien à exposer : la découverte la laisse de côté
     * toute seule, sans qu'aucune liste d'exceptions ait à être tenue.
     */
    public function testEverySoftDeletableResourceExposesBothDeletionFields(): void
    {
        self::bootKernel();

        $factory = static::getContainer()->get('serializer.mapping.class_metadata_factory');
        self::assertInstanceOf(ClassMetadataFactoryInterface::class, $factory);

        $entities = self::softDeletableResources();
        self::assertNotSame([], $entities, 'Aucune entité supprimable trouvée : la découverte est cassée.');

        foreach ($entities as $entityClass => $readGroup) {
            $exposed = [];

            foreach ($factory->getMetadataFor($entityClass)->getAttributesMetadata() as $attribute) {
                if (in_array($readGroup, $attribute->getGroups(), true)) {
                    $exposed[] = $attribute->getSerializedName() ?? $attribute->getName();
                }
            }

            self::assertContains('deletedAt', $exposed, sprintf(
                '%s ne sérialise pas `deletedAt` dans le groupe « %s » : une ligne supprimée '
                .'s\'affichera comme une ligne vivante. Le champ vient d\'un TRAIT, donc il se '
                .'déclare dans config/serializer/<entité>.yaml.',
                $entityClass,
                $readGroup,
            ));

            self::assertContains('isDeleted', $exposed, sprintf(
                '%s ne sérialise pas `isDeleted` dans le groupe « %s » : « Restaurer » n\'apparaîtra '
                .'jamais et « Supprimer » s\'offrira sur une ligne déjà supprimée. Ajouter un getter '
                .'`#[Groups]` + `#[SerializedName(\'isDeleted\')]` (modèle : Customer::isDeletedForApi()).',
                $entityClass,
                $readGroup,
            ));
        }
    }

    /**
     * Les entités de `src/Entity/` qui portent `SoftDeletableTrait` ET sont exposées en lecture.
     *
     * @return array<class-string, string> classe → groupe de normalisation
     */
    private static function softDeletableResources(): array
    {
        $found = [];

        foreach (glob(dirname(__DIR__, 2).'/src/Entity/*.php') ?: [] as $file) {
            /** @var class-string $class */
            $class = 'App\\Entity\\'.basename($file, '.php');

            if (!class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if (!in_array(SoftDeletableTrait::class, $reflection->getTraitNames(), true)) {
                continue;
            }

            $group = self::readGroup($reflection);

            if (null !== $group) {
                $found[$class] = $group;
            }
        }

        return $found;
    }

    /**
     * Le groupe de `normalizationContext` de la ressource, ou `null` si l'entité n'est pas exposée.
     *
     * @param ReflectionClass<object> $reflection
     */
    private static function readGroup(ReflectionClass $reflection): ?string
    {
        foreach ($reflection->getAttributes(ApiResource::class) as $attribute) {
            $context = $attribute->getArguments()['normalizationContext'] ?? [];
            $groups = is_array($context) ? ($context['groups'] ?? []) : [];

            if (is_array($groups) && is_string($groups[0] ?? null)) {
                return $groups[0];
            }
        }

        return null;
    }
}
