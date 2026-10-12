<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Written with the Schema API (not raw SQL) so that it runs on MySQL, MariaDB and PostgreSQL.
 */
final class Version20261013100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Audit entries and idempotency keys go with the customer (tmonier/sylius-b2b-mcp-plugin).';
    }

    public function up(Schema $schema): void
    {
        // Rows of customers already deleted (they would break the new foreign key).
        $this->addSql('DELETE FROM tmonier_b2b_mcp_idempotency WHERE customer_id NOT IN (SELECT id FROM sylius_customer)');

        $call = $schema->getTable('tmonier_b2b_mcp_tool_call');
        $call->addColumn('customer_id', Types::INTEGER, ['notnull' => false]);
        $call->addIndex(['customer_id'], 'IDX_F30747E39395C3F3');
        $call->addForeignKeyConstraint('sylius_customer', ['customer_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_F30747E39395C3F3');

        $idempotency = $schema->getTable('tmonier_b2b_mcp_idempotency');
        $idempotency->addIndex(['customer_id'], 'IDX_E786963B9395C3F3');
        $idempotency->addForeignKeyConstraint('sylius_customer', ['customer_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_E786963B9395C3F3');
    }

    public function postUp(Schema $schema): void
    {
        $this->connection->executeStatement('UPDATE tmonier_b2b_mcp_tool_call SET customer_id = (SELECT c.customer_id FROM tmonier_b2b_mcp_connection c WHERE c.id = tmonier_b2b_mcp_tool_call.connection_id) WHERE connection_id IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $call = $schema->getTable('tmonier_b2b_mcp_tool_call');
        $call->removeForeignKey('FK_F30747E39395C3F3');
        $call->dropIndex('IDX_F30747E39395C3F3');
        $call->dropColumn('customer_id');

        $idempotency = $schema->getTable('tmonier_b2b_mcp_idempotency');
        $idempotency->removeForeignKey('FK_E786963B9395C3F3');
        $idempotency->dropIndex('IDX_E786963B9395C3F3');
    }
}
