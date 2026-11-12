<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use Jul6Art\AuthBundle\Test\FirewallRulesTestCase;

/**
 * Les pare-feu de production tiennent leurs règles : connexion limitée partout où l'on tape un mot
 * de passe, un même vérificateur de compte derrière chaque entrée, déconnexion en POST seulement.
 * Les règles vivent dans auth-bundle.
 */
final class FirewallRulesTest extends FirewallRulesTestCase
{
}
