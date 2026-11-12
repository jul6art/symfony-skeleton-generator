<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use Jul6Art\ApiBundle\Test\ApiExposureTestCase;

/**
 * Chaque opération d'API dit qui peut l'appeler : une règle par défaut fermée en production, et une
 * règle sur chaque opération — la sienne ou ce défaut. Les règles vivent dans api-bundle.
 */
final class ApiOperationSecurityTest extends ApiExposureTestCase
{
}
