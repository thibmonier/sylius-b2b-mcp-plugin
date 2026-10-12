<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tmonier\SyliusB2bMcpPlugin\Audit\AuditLogMirror;

/** Loaded when tmonier/sylius-audit-log-plugin is enabled and audit_log_bridge is on. */
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('tmonier_b2b_mcp.audit.mirror', AuditLogMirror::class)
        ->args([
            service('tmonier_audit_log.factory.audit_log_entry'),
            service('tmonier_audit_log.writer'),
            service('tmonier_audit_log.context_provider'),
            service('doctrine.orm.entity_manager'),
            service('clock'),
            service('logger'),
        ]);
};
