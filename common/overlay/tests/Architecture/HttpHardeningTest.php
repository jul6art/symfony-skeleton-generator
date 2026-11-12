<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use Jul6Art\CoreBundle\Test\HttpHardeningTestCase;

/**
 * La configuration de production durcit HTTP : hôtes listés, proxys qui en sont vraiment, en-têtes
 * de sécurité et garde-fou des secrets activés. Les règles vivent dans core-bundle.
 */
final class HttpHardeningTest extends HttpHardeningTestCase
{
}
