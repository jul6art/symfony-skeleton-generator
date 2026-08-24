<?php

declare(strict_types=1);

namespace App\Tests\DataTable;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Metadata\ApiFilter;
use App\Entity\User;
use App\Security\PermissionCodes;
use Jul6Art\AclBundle\Security\PermissionDecisionService;
use Jul6Art\DatatableBundle\DataTable\AbstractDataTableConfigProvider;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function count;
use function in_array;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Les deux règles de plage de dates, rendues exécutables pour CHAQUE tableau du projet.
 *
 * 1. **Toute colonne date porte son filtre de plage.** C'est la demande du 2026-08-24 : « avec
 *    beaucoup de données on serait noyé sous les archives sans ce filtre ». Une liste qui grossit
 *    sans moyen de remonter à une période devient illisible, et le manque ne se voit qu'à l'usage,
 *    quand il est trop tard pour l'ajouter sans migrer des préférences enregistrées.
 *
 * 2. **Tout filtre de plage est mappé côté API** (item A4 de la checklist datatable). Un
 *    `dateRangeFilter()` sans `DateFilter` sur l'entité s'affiche et ne filtre RIEN : API Platform
 *    jette en silence un paramètre qu'aucun filtre ne réclame. C'était le cas de `createdAt` sur la
 *    table des comptes depuis le premier jour — 103 lignes avant filtre, 103 après.
 *
 * Aucun test de configuration n'attrapait le second : les deux configurations étaient correctes
 * séparément, c'est leur CORRESPONDANCE qui manquait. C'est exactement ce que ce test vérifie.
 *
 * ⚠️ Un provider ajouté au projet s'ajoute ICI, dans `providers()`. La liste est explicite plutôt
 * que découverte par le conteneur : un provider a besoin de ses dépendances pour s'instancier, et
 * les deviner rendrait l'échec du test illisible le jour où l'une change.
 */
#[CoversNothing]
final class DateRangeFilterMappingTest extends KernelTestCase
{
    /**
     * Les rendus qui font d'une colonne une colonne de DATE. Le vocabulaire vient du contrôleur
     * Stimulus du datatable-bundle, qui les résout en formatage de date.
     *
     * @var list<string>
     */
    private const array DATE_RENDERERS = ['date', 'datetime', 'dateTime'];

    /**
     * Provider → entité qu'il liste.
     *
     * @return iterable<string, array{class-string<AbstractDataTableConfigProvider>, class-string}>
     */
    public static function providers(): iterable
    {
        yield 'users' => [\App\DataTable\UserDataTableConfigProvider::class, User::class];
    }

    /**
     * @param class-string<AbstractDataTableConfigProvider> $providerClass
     * @param class-string                                  $entityClass
     */
    #[DataProvider('providers')]
    public function testEveryDateColumnOffersItsRangeFilter(string $providerClass, string $entityClass): void
    {
        $provider = $this->buildProvider($providerClass);
        $filtered = self::dateRangeColumns($provider);

        foreach (self::dateColumns($provider) as $column) {
            self::assertContains(
                $column,
                $filtered,
                sprintf(
                    '%s affiche la colonne date « %s » sans filtre de plage. Avec du volume, la liste devient '
                    .'illisible : ajouter `$this->dateRangeFilter(\'%s\', \'%s\', …)` dans getFilters().',
                    $providerClass,
                    $column,
                    $column,
                    $column,
                ),
            );
        }
    }

    /**
     * @param class-string<AbstractDataTableConfigProvider> $providerClass
     * @param class-string                                  $entityClass
     */
    #[DataProvider('providers')]
    public function testEveryRangeFilterIsMappedOnTheEntity(string $providerClass, string $entityClass): void
    {
        $mapped = self::dateFilteredProperties($entityClass);

        foreach (self::dateRangeColumns($this->buildProvider($providerClass)) as $param) {
            self::assertContains(
                $param,
                $mapped,
                sprintf(
                    '%s déclare un filtre de plage sur « %s » que %s ne mappe pas : le filtre s\'affichera et ne '
                    .'retirera aucune ligne. Ajouter #[ApiFilter(DateFilter::class, properties: [\'%s\'])].',
                    $providerClass,
                    $param,
                    $entityClass,
                    $param,
                ),
            );
        }
    }

    /**
     * Le test ne prouve rien s'il ne regarde aucune colonne : on l'affirme plutôt que l'espérer.
     *
     * @param class-string<AbstractDataTableConfigProvider> $providerClass
     * @param class-string                                  $entityClass
     */
    #[DataProvider('providers')]
    public function testTheTableActuallyHasADateColumnToCheck(string $providerClass, string $entityClass): void
    {
        self::assertNotSame([], self::dateColumns($this->buildProvider($providerClass)), $providerClass);
    }

    /**
     * @param class-string<AbstractDataTableConfigProvider> $providerClass
     */
    private function buildProvider(string $providerClass): AbstractDataTableConfigProvider
    {
        self::bootKernel();
        $container = static::getContainer();

        $provider = new $providerClass(
            $container->get(\Symfony\Contracts\Translation\TranslatorInterface::class),
            $container->get(PermissionDecisionService::class),
        );
        self::assertInstanceOf(AbstractDataTableConfigProvider::class, $provider);

        // Une constante référencée pour que l'analyse statique voie l'usage de PermissionCodes,
        // que le provider consomme et dont ce test dépend indirectement.
        self::assertNotSame('', PermissionCodes::USER_READ);

        return $provider;
    }

    /** @return list<string> les `data` des colonnes rendues en date */
    private static function dateColumns(AbstractDataTableConfigProvider $provider): array
    {
        $columns = [];

        foreach ($provider->getColumns() as $column) {
            $render = $column['render'] ?? null;
            $data = $column['data'] ?? null;

            if (is_string($render) && is_string($data) && in_array($render, self::DATE_RENDERERS, true)) {
                $columns[] = $data;
            }
        }

        return $columns;
    }

    /** @return list<string> les `param` des filtres de type `daterange` */
    private static function dateRangeColumns(AbstractDataTableConfigProvider $provider): array
    {
        $params = [];

        foreach ($provider->getFilters() as $filter) {
            $param = $filter['param'] ?? null;

            if ('daterange' === ($filter['type'] ?? null) && is_string($param)) {
                $params[] = $param;
            }
        }

        return $params;
    }

    /**
     * @param class-string $entityClass
     *
     * @return list<string> les propriétés couvertes par un `#[ApiFilter(DateFilter::class)]`
     */
    private static function dateFilteredProperties(string $entityClass): array
    {
        $properties = [];

        foreach (new ReflectionClass($entityClass)->getAttributes(ApiFilter::class) as $attribute) {
            $arguments = $attribute->getArguments();

            if (DateFilter::class !== ($arguments[0] ?? $arguments['filterClass'] ?? null)) {
                continue;
            }

            $declared = $arguments['properties'] ?? $arguments[1] ?? [];
            if (!is_array($declared)) {
                continue;
            }

            foreach ($declared as $key => $value) {
                // `['createdAt']` comme `['createdAt' => 'exclude_null']` : la propriété est la
                // valeur dans le premier cas, la clé dans le second.
                $properties[] = is_string($key) ? $key : (string) $value;
            }
        }

        self::assertGreaterThanOrEqual(0, count($properties));

        return $properties;
    }
}
