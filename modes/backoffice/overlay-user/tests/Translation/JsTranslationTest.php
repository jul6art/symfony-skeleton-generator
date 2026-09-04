<?php

declare(strict_types=1);

namespace App\Tests\Translation;

use App\Security\BackofficeLocales;
use Jul6Art\CoreBundle\Test\AbstractJsTranslationTestCase;
use Jul6Art\DatatableBundle\Translation\DeclaredTranslationKeys;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Le garde du domaine `javascript` : les deux moitiés d'un libellé lu par le navigateur.
 *
 * ⚠️ Il remplace un montage qui ne pouvait pas voir ce qu'il prétendait garder. Jusqu'à
 * `datatable-bundle` v2, un libellé partait vers le JavaScript par un arbre JSON construit en Twig
 * et parcouru en JS : rien ne comparait la clé envoyée à la clé lue. Les cases de sélection de
 * masse ont annoncé « bulk.select_all » à un lecteur d'écran pendant des semaines, sur trois
 * produits, avec une suite verte partout. La clé lue EST maintenant la clé du catalogue, et ce
 * fichier tient les deux bouts.
 *
 * ⚠️ Les répertoires des BUNDLES sont scannés, pas seulement `assets/`. Les clés que le contrôleur
 * du tableau lit sont traduites par CE catalogue : ne regarder que le code du projet laisserait
 * sans garde la moitié de ce qui s'affiche.
 *
 * ⚠️ Et le garde va dans les DEUX sens. Une clé lue et absente s'affiche brute ; une clé présente
 * que plus rien ne lit part dans le paquet de chaque visiteur, et rien d'autre dans la chaîne ne
 * la mentionnerait jamais.
 */
#[CoversNothing]
final class JsTranslationTest extends AbstractJsTranslationTestCase
{
    #[\Override]
    protected static function javaScriptDirectories(): array
    {
        return [
            self::projectDir().'/assets',
            self::projectDir().'/vendor/jul6art/datatable-bundle/assets',
            self::projectDir().'/vendor/jul6art/admin-bundle/assets',
        ];
    }

    /**
     * ⚠️ `BackofficeLocales::SUPPORTED` et non `kernel.enabled_locales` : la première dit ce que
     * l'interface est RÉELLEMENT traduite, la seconde ce que le routeur accepte. Tant que les deux
     * listes sont égales — ce que `LocaleCatalogueTest` exige dans ce mode — le choix ne change
     * rien ; le jour où un produit déclare une langue avant de la traduire, il évite d'exiger un
     * catalogue que personne n'a encore écrit (c'est ce qui est arrivé à `cegeta`, qui accepte
     * cinq langues et en traduit deux).
     *
     * @return list<string>
     */
    #[\Override]
    protected static function locales(): array
    {
        return BackofficeLocales::SUPPORTED;
    }

    /**
     * Les clés qu'aucun scanner ne peut voir : celles que le contrôleur reçoit par variable, et les
     * cas des énumérations que `badge()` construit par interpolation.
     *
     * ⚠️ Elles viennent du bundle, pas d'une liste recopiée ici. `datatable.status_maps` les
     * énumère déjà dans `config/packages/datatable.yaml`, et deux listes d'un même vocabulaire
     * divergent le jour où l'une change.
     */
    #[\Override]
    protected static function declaredKeys(): array
    {
        return self::declared()->keys();
    }

    #[\Override]
    protected static function declaredPrefixes(): array
    {
        return self::declared()->prefixes();
    }

    /**
     * Le garde anti-retour : plus aucun gabarit ne passe de libellés au JavaScript par un attribut.
     */
    #[\Override]
    protected static function templateDirectories(): array
    {
        return [self::projectDir().'/templates'];
    }

    /**
     * Le garde de l'autre moitié d'un déplacement de domaine : les appelants SERVEUR.
     *
     * ⚠️ Un vocabulaire déplacé vers `javascript` est souvent rendu par Twig ou par PHP aussi —
     * une pastille sur la fiche, une option dans un filtre, le libellé d'une action de ligne. Un
     * `|trans` resté sur l'ancien domaine NE LÈVE PAS : il rend la CLÉ, en toutes lettres, dans
     * la page. Le défaut a traversé trois projets de cet écosystème avant que ce garde existe.
     *
     * @return list<string>
     */
    #[\Override]
    protected static function serverDirectories(): array
    {
        return [self::projectDir().'/templates', self::projectDir().'/src'];
    }

    private static function declared(): DeclaredTranslationKeys
    {
        $service = static::getContainer()->get(DeclaredTranslationKeys::class);
        self::assertInstanceOf(DeclaredTranslationKeys::class, $service);

        return $service;
    }
}
