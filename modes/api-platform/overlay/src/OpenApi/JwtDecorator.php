<?php

declare(strict_types=1);

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;
use ArrayObject;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * POST /api/login est servi par le pare-feu, pas par une opération API
 * Platform : sans ce décorateur il n'apparaîtrait pas dans la documentation.
 */
#[AsDecorator('api_platform.openapi.factory')]
final class JwtDecorator implements OpenApiFactoryInterface
{
    public function __construct(
        #[AutowireDecorated]
        private readonly OpenApiFactoryInterface $decorated,
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        $credentials = new ArrayObject([
            'application/json' => new ArrayObject([
                'schema' => [
                    'type' => 'object',
                    'required' => ['email', 'password'],
                    'properties' => [
                        'email' => ['type' => 'string', 'example' => 'moi@exemple.com'],
                        'password' => ['type' => 'string', 'format' => 'password'],
                    ],
                ],
            ]),
        ]);

        $token = new ArrayObject([
            'application/json' => new ArrayObject([
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'token' => ['type' => 'string', 'readOnly' => true],
                    ],
                ],
            ]),
        ]);

        $openApi->getPaths()->addPath('/api/login', new PathItem(
            post: new Operation(
                operationId: 'postApiLogin',
                tags: ['Authentification'],
                responses: [
                    '200' => new Response(description: 'Jeton JWT', content: $token),
                    '401' => new Response(description: 'Identifiants invalides'),
                ],
                summary: 'Échange des identifiants contre un jeton JWT.',
                requestBody: new RequestBody(description: 'Identifiants du compte', content: $credentials, required: true),
                security: [],
            ),
        ));

        return $openApi;
    }
}
