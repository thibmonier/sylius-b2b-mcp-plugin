<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Written with the Schema API (not raw SQL) so that it runs on MySQL, MariaDB and PostgreSQL.
 */
final class Version20261012500000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the OAuth, connection, idempotency and audit tables (tmonier/sylius-b2b-mcp-plugin).';
    }

    public function up(Schema $schema): void
    {
        $client = $schema->createTable('tmonier_b2b_mcp_client');
        $client->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $client->addColumn('identifier', Types::STRING, ['length' => 64]);
        $client->addColumn('name', Types::STRING, ['length' => 255]);
        $client->addColumn('redirect_uris', Types::JSON);
        $client->addColumn('secret_hash', Types::STRING, ['length' => 255, 'notnull' => false]);
        $client->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $client->setPrimaryKey(['id']);
        $client->addUniqueIndex(['identifier'], 'UNIQ_96DBEAF772E836A');

        $connection = $schema->createTable('tmonier_b2b_mcp_connection');
        $connection->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $connection->addColumn('client_id', Types::INTEGER);
        $connection->addColumn('customer_id', Types::INTEGER);
        $connection->addColumn('channel_code', Types::STRING, ['length' => 255]);
        $connection->addColumn('scopes', Types::JSON);
        $connection->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $connection->addColumn('last_used_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $connection->addColumn('revoked_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $connection->setPrimaryKey(['id']);
        $connection->addIndex(['client_id'], 'IDX_307F8E3F19EB6921');
        $connection->addIndex(['customer_id'], 'IDX_307F8E3F9395C3F3');
        $connection->addIndex(['customer_id', 'client_id', 'channel_code'], 'tmonier_b2b_mcp_connection_lookup_idx');
        $connection->addForeignKeyConstraint('tmonier_b2b_mcp_client', ['client_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_307F8E3F19EB6921');
        $connection->addForeignKeyConstraint('sylius_customer', ['customer_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_307F8E3F9395C3F3');

        $token = $schema->createTable('tmonier_b2b_mcp_token');
        $token->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $token->addColumn('connection_id', Types::INTEGER);
        $token->addColumn('type', Types::STRING, ['length' => 16]);
        $token->addColumn('identifier_hash', Types::STRING, ['length' => 64]);
        $token->addColumn('parent_hash', Types::STRING, ['length' => 64, 'notnull' => false]);
        $token->addColumn('scopes', Types::JSON);
        $token->addColumn('expires_at', Types::DATETIME_IMMUTABLE);
        $token->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $token->addColumn('revoked_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $token->setPrimaryKey(['id']);
        $token->addUniqueIndex(['identifier_hash'], 'UNIQ_1769C44CF25EE041');
        $token->addIndex(['connection_id'], 'IDX_1769C44CDD03F01');
        $token->addIndex(['expires_at'], 'tmonier_b2b_mcp_token_expires_idx');
        $token->addForeignKeyConstraint('tmonier_b2b_mcp_connection', ['connection_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_1769C44CDD03F01');

        $idempotency = $schema->createTable('tmonier_b2b_mcp_idempotency');
        $idempotency->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $idempotency->addColumn('customer_id', Types::INTEGER);
        $idempotency->addColumn('tool', Types::STRING, ['length' => 64]);
        $idempotency->addColumn('idempotency_key', Types::STRING, ['length' => 128]);
        $idempotency->addColumn('request_hash', Types::STRING, ['length' => 64]);
        $idempotency->addColumn('response', Types::TEXT, ['notnull' => false]);
        $idempotency->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $idempotency->setPrimaryKey(['id']);
        $idempotency->addUniqueIndex(['customer_id', 'tool', 'idempotency_key'], 'tmonier_b2b_mcp_idempotency_key_uniq');
        $idempotency->addIndex(['created_at'], 'tmonier_b2b_mcp_idempotency_created_idx');

        $call = $schema->createTable('tmonier_b2b_mcp_tool_call');
        $call->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $call->addColumn('connection_id', Types::INTEGER, ['notnull' => false]);
        $call->addColumn('customer_email', Types::STRING, ['length' => 255, 'notnull' => false]);
        $call->addColumn('client_name', Types::STRING, ['length' => 255, 'notnull' => false]);
        $call->addColumn('channel_code', Types::STRING, ['length' => 255, 'notnull' => false]);
        $call->addColumn('tool', Types::STRING, ['length' => 64]);
        $call->addColumn('arguments', Types::TEXT);
        $call->addColumn('outcome', Types::STRING, ['length' => 16]);
        $call->addColumn('message', Types::STRING, ['length' => 255, 'notnull' => false]);
        $call->addColumn('duration_ms', Types::INTEGER);
        $call->addColumn('ip_address', Types::STRING, ['length' => 45, 'notnull' => false]);
        $call->addColumn('created_at', Types::DATETIME_IMMUTABLE);
        $call->setPrimaryKey(['id']);
        $call->addIndex(['connection_id'], 'IDX_F30747E3DD03F01');
        $call->addIndex(['created_at'], 'tmonier_b2b_mcp_tool_call_created_idx');
        $call->addForeignKeyConstraint('tmonier_b2b_mcp_connection', ['connection_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_F30747E3DD03F01');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('tmonier_b2b_mcp_tool_call');
        $schema->dropTable('tmonier_b2b_mcp_idempotency');
        $schema->dropTable('tmonier_b2b_mcp_token');
        $schema->dropTable('tmonier_b2b_mcp_connection');
        $schema->dropTable('tmonier_b2b_mcp_client');
    }
}
