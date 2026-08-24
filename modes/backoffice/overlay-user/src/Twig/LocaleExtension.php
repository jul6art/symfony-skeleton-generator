<?php

declare(strict_types=1);

namespace App\Twig;

use App\Security\BackofficeLocales;
use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Ce que le sélecteur de langue affiche : la liste des langues, et le drapeau de chacune.
 *
 * Des fonctions plutôt que des paramètres Twig globaux : la liste appartient à
 * `BackofficeLocales`, et la recopier dans `twig.globals` en ferait une seconde source de vérité.
 */
final class LocaleExtension extends AbstractExtension
{
    /**
     * Le drapeau de chaque langue, en SVG inline.
     *
     * ⚠️ **SVG et non emoji.** 🇫🇷 est tentant — zéro octet de dépendance — mais Windows ne
     * possède pas les glyphes de drapeaux nationaux : la même page y rend « FR » en lettres. Le
     * sélecteur serait illustré chez les uns, textuel chez les autres, et rien côté serveur ne le
     * signalerait. Deux rectangles pèsent moins qu'une requête, et rendent partout à l'identique.
     *
     * ⚠️ **Le balisage vit ici et pas dans le gabarit**, pour la raison qu'`IconSet` d'`ui-bundle`
     * écrit en tête : un jeu d'icônes est une décision de PROJET. Un `{% if %}` par code dans le
     * partial remettrait cette décision dans la vue, et la dupliquerait au premier second
     * sélecteur (il y en a déjà deux : la barre du haut et la carte d'authentification).
     *
     * `aria-hidden` sur les deux : le nom de la langue est déjà écrit à côté (`user.locale.<code>`),
     * donc le drapeau est décoratif — l'annoncer le ferait lire deux fois.
     *
     * `preserveAspectRatio="none"` : le conteneur du gabarit impose le cadre (w-5 h-3.5), et les
     * deux drapeaux n'ont pas le même rapport (3:2 pour la France, 2:1 pour le Royaume-Uni). Sans
     * lui, l'un des deux flotterait dans sa boîte.
     *
     * @var array<string, string>
     */
    private const array FLAGS = [
        'fr' => '<svg viewBox="0 0 3 2" preserveAspectRatio="none" class="block w-full h-full" aria-hidden="true" focusable="false">'
            .'<rect width="3" height="2" fill="#ED2939"/>'
            .'<rect width="2" height="2" fill="#fff"/>'
            .'<rect width="1" height="2" fill="#002395"/>'
            .'</svg>',
        // L'anglais est illustré par l'Union Jack : c'est l'usage, et le seul drapeau qu'un lecteur
        // associe à « English » sans hésiter.
        'en' => '<svg viewBox="0 0 60 30" preserveAspectRatio="none" class="block w-full h-full" aria-hidden="true" focusable="false">'
            .'<rect width="60" height="30" fill="#012169"/>'
            .'<path d="M0,0 L60,30 M60,0 L0,30" stroke="#fff" stroke-width="6"/>'
            .'<path d="M0,0 L60,30 M60,0 L0,30" stroke="#C8102E" stroke-width="4"/>'
            .'<path d="M30,0 V30 M0,15 H60" stroke="#fff" stroke-width="10"/>'
            .'<path d="M30,0 V30 M0,15 H60" stroke="#C8102E" stroke-width="6"/>'
            .'</svg>',
    ];

    /**
     * @return list<TwigFunction>
     */
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('backoffice_locales', static fn (): array => BackofficeLocales::SUPPORTED),
            // `is_safe: html` parce que la valeur EST du balisage, et qu'il vient d'une constante
            // de ce fichier — même niveau de confiance qu'un gabarit. Aucune donnée utilisateur ne
            // traverse cette table.
            new TwigFunction('locale_flag', $this->flag(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * Le balisage du drapeau, ou une chaîne vide pour une langue sans drapeau.
     *
     * Vide plutôt qu'une exception : une langue ajoutée sans son drapeau doit rendre un sélecteur
     * un peu terne, pas une erreur 500 sur toutes les pages du back-office. `LocaleCatalogueTest`
     * garde l'égalité des deux listes, ce qui rend ce cas impossible en pratique.
     */
    public function flag(string $locale): string
    {
        return self::FLAGS[$locale] ?? '';
    }
}
