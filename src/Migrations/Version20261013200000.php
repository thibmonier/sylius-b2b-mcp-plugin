<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Written with the Schema API (not raw SQL) so that it runs on MySQL, MariaDB and PostgreSQL.
 */
final class Version20261013200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remember whether an OAuth client registered itself (tmonier/sylius-b2b-mcp-plugin).';
    }

    public function up(Schema $schema): void
    {
        // Existing clients are treated as self-registered: their name is not verified.
        $schema->getTable('tmonier_b2b_mcp_client')->addColumn('dynamically_registered', Types::BOOLEAN, ['default' => true]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('tmonier_b2b_mcp_client')->dropColumn('dynamically_registered');
    }
}
