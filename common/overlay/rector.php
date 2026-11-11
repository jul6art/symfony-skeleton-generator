<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Cast\RecastingRemovalRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php80\Rector\Ternary\TernaryToNullsafeCoalesceRector;
use Rector\Renaming\Rector\MethodCall\RenameMethodRector;
use Rector\Symfony\Symfony63\Rector\Class_\ParamAndEnvAttributeRector;
use Rector\Symfony\Symfony80\Rector\Class_\RemoveEraseCredentialsRector;
use Rector\TypeDeclaration\Rector\FuncCall\AddArrayFunctionClosureParamTypeRector;
use Rector\TypeDeclaration\Rector\FunctionLike\AddClosureParamTypeFromVariableCallRector;

// Les jeux de règles préparés de Rector, comme les bundles maison et devinthecode (audit Fable 5.2,
// T19). Le générateur l'applique une fois à la naissance du projet ; `make rector` vérifie ensuite
// qu'il n'a plus rien à changer (dans `make qa`), `make rector-fix` applique.
return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    // Sans argument : la version de PHP visée se lit dans la contrainte « php » de composer.json.
    ->withPhpSets()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
        doctrineCodeQuality: true,
        symfonyCodeQuality: true,
    )
    ->withAttributesSets(symfony: true, doctrine: true, phpunit: true)
    ->withComposerBased(doctrine: true, symfony: true, phpunit: true)
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withSkip([
        // Les deux exceptions des bundles. Un utilitaire statique appelé depuis une méthode
        // d'instance reste statique ; `eraseCredentials()` ne quitte `UserInterface` qu'avec Symfony 8.
        LocallyCalledStaticMethodToNonStaticRector::class,
        RemoveEraseCredentialsRector::class,
        // ⚠️ Les huit règles suivantes changent le COMPORTEMENT : constatées sur les neuf applications
        // nées de ce squelette, le 2026-11-11, sans que PHPStan ni les tests ne voient toujours rien.
        //
        // Sur une entité, le paramètre promu prend le type du CONSTRUCTEUR : une relation `?User`
        // remise à NULL par `onDelete: 'SET NULL'` devenait une propriété `User`, une TypeError au
        // chargement.
        ClassPropertyAssignToConstructorPromotionRector::class => ['*/Entity/*'],
        // `null === $x ? a() : $x->b()` n'est pas `$x?->b() ?? a()` quand `b()` rend null ou void :
        // la seconde forme appelle `a()` à chaque fois.
        TernaryToNullsafeCoalesceRector::class,
        // Paramètres de closure typés d'après UN seul appel, ou d'après un docblock : une closure qui
        // reçoit aussi un `FormInterface` devenait `FormBuilderInterface`, un filtre qui écarte ce qui
        // n'est pas un `UploadedFile` devenait typé `UploadedFile`.
        AddClosureParamTypeFromVariableCallRector::class,
        AddArrayFunctionClosureParamTypeRector::class,
        // Elle retire le cast ET ses parenthèses : `$a !== (string) ($b ?? '')` devenait
        // `$a !== $b ?? ''`, que PHP lit `($a !== $b) ?? ''`.
        RecastingRemovalRector::class,
        // Elle retire aussi l'ARGUMENT à chaque appel, effet de bord compris : un `get()` du
        // conteneur passé en argument ne construisait plus le service qu'un test attendait.
        RemoveUnusedPublicMethodParameterRector::class,
        // `#[Autowire('%kernel.project_dir%/var/x/%kernel.environment%')]` devenait
        // `#[Autowire(param: 'kernel.environment')]` : juste sur un `%param%` seul, faux sur une
        // chaîne composée.
        ParamAndEnvAttributeRector::class,
        // Le jeu Symfony renomme `UserInterface::getUsername()` en `getUserIdentifier()` (5.3). Sur
        // Symfony 7.4 il ne reste rien de déprécié à renommer, et un `getUsername()` métier (le
        // pseudonyme) y perdait sa valeur au profit de l'e-mail.
        RenameMethodRector::class,
    ]);
