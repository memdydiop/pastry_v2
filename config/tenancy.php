<?php

use Stancl\Tenancy\Database\Models\Domain;

return [
    'tenant_model' => App\Models\Tenant::class,
    'id_generator' => Stancl\Tenancy\UUIDGenerator::class,

    'bootstrappers' => [
        Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
    ],

    'features' => [
        Stancl\Tenancy\Features\TenantConfig::class,
        Stancl\Tenancy\Features\CrossDomainRedirect::class,
    ],

    'database' => [
        'central_connection' => 'pgsql',
        'template_tenant_connection' => 'tenant',
        'managers' => [
            'pgsql' => Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLSchemaManager::class,
        ],
        'prefix' => '',
        'suffix' => '',
    ],

    'filesystem' => [
        'disks' => ['local', 'public'],
    ],

    'cache' => [
        'tag_based' => true,
    ],

    'redis' => [
        'prefixed' => true,
    ],

    'routes' => [
        'central_except' => [
            'onboarding.*',
            'webhooks.*',
            'super-admin.*',
        ],
    ],
];
