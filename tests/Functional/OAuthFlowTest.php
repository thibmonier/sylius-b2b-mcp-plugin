<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Functional;

use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusB2bMcpPlugin\Entity\ConnectionInterface;

/** The OAuth 2.1 flow as Claude and ChatGPT run it: discovery, registration, consent, PKCE, refresh. */
final class OAuthFlowTest extends WebTestCase
{
    use B2bMcpTestTrait;

    private const REDIRECT_URI = 'https://claude.ai/api/mcp/auth_callback';

    protected function setUp(): void
    {
        $this->setUpClient();
    }

    protected function tearDown(): void
    {
        $this->tearDownClient();
        parent::tearDown();
    }

    public function testTheEndpointAsksForATokenAndPointsToItsMetadata(): void
    {
        $this->client->request('POST', '/_mcp', [], [], ['CONTENT_TYPE' => 'application/json'], '{"jsonrpc":"2.0","id":1,"method":"tools/list"}');

        self::assertResponseStatusCodeSame(401);
        self::assertSame('Bearer resource_metadata="http://localhost/.well-known/oauth-protected-resource/_mcp", scope="b2b:read b2b:cart"', $this->client->getResponse()->headers->get('WWW-Authenticate'));

        $this->client->request('POST', '/_mcp', [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . str_repeat('a', 80)], '{}');

        self::assertResponseStatusCodeSame(401);
        self::assertStringContainsString('error="invalid_token"', (string) $this->client->getResponse()->headers->get('WWW-Authenticate'));
    }

    /** The router decodes the path: an encoded spelling of the endpoint is the endpoint. */
    public function testEncodedSpellingsOfTheEndpointAlsoNeedAToken(): void
    {
        foreach (['/%5Fmcp', '/_mc%70', '/%5F%6D%63%70'] as $path) {
            $this->client->request('POST', $path, [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream'], '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"x","version":"1"}}}');

            self::assertResponseStatusCodeSame(401, $path);
            self::assertNull($this->client->getResponse()->headers->get('Mcp-Session-Id'), $path);
        }

        $token = $this->accessToken($this->customer());
        $this->client->request('POST', '/%5Fmcp', [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream', 'HTTP_AUTHORIZATION' => 'Bearer ' . $token], '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"x","version":"1"}}}');
        self::assertResponseIsSuccessful();
        self::assertSame([], $this->client->getCookieJar()->allRawValues('http://localhost/'), 'Stateless on the encoded path too.');
    }

    public function testAShopSessionIsNotEnough(): void
    {
        $this->loginShop($this->customer());

        $this->client->request('POST', '/_mcp', [], [], ['CONTENT_TYPE' => 'application/json'], '{"jsonrpc":"2.0","id":1,"method":"tools/list"}');

        self::assertResponseStatusCodeSame(401);
    }

    public function testMetadataDescribesTheResourceAndTheAuthorizationServer(): void
    {
        $this->client->request('GET', '/.well-known/oauth-protected-resource/_mcp');
        self::assertResponseIsSuccessful();
        $resource = $this->json();
        self::assertSame('http://localhost/_mcp', $resource['resource']);
        self::assertSame(['http://localhost'], $resource['authorization_servers']);
        self::assertSame(['b2b:read', 'b2b:cart'], $resource['scopes_supported']);

        $this->client->request('GET', '/.well-known/oauth-protected-resource/other');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/.well-known/oauth-authorization-server');
        self::assertResponseIsSuccessful();
        $server = $this->json();
        self::assertSame('http://localhost', $server['issuer']);
        self::assertSame('http://localhost/mcp-oauth/authorize', $server['authorization_endpoint']);
        self::assertSame('http://localhost/mcp-oauth/token', $server['token_endpoint']);
        self::assertSame('http://localhost/mcp-oauth/register', $server['registration_endpoint']);
        self::assertSame(['S256'], $server['code_challenge_methods_supported']);
    }

    public function testRegistrationOnlyAcceptsTheRedirectUrisOfTheAssistants(): void
    {
        $this->register(['redirect_uris' => ['https://evil.example.com/callback'], 'token_endpoint_auth_method' => 'none']);
        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_redirect_uri', $this->json()['error']);

        $this->register(['redirect_uris' => ['https://chatgpt.com/connector/oauth/abc123/../x']]);
        self::assertResponseStatusCodeSame(400);

        $this->register(['redirect_uris' => [self::REDIRECT_URI], 'grant_types' => ['client_credentials']]);
        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_client_metadata', $this->json()['error']);

        $this->client->request('POST', '/mcp-oauth/register', [], [], ['CONTENT_TYPE' => 'application/json'], 'not json');
        self::assertResponseStatusCodeSame(400);

        $client = $this->register(['client_name' => 'ChatGPT', 'redirect_uris' => ['https://chatgpt.com/connector/oauth/abc_123'], 'token_endpoint_auth_method' => 'client_secret_post']);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('ChatGPT', $client['client_name']);
        self::assertIsString($client['client_secret']);
        self::assertSame(0, $client['client_secret_expires_at']);
    }

    public function testTheCustomerApprovesAndTheAssistantGetsRotatingTokens(): void
    {
        $customer = $this->customer();
        $client = $this->register(['client_name' => 'Claude', 'redirect_uris' => [self::REDIRECT_URI], 'token_endpoint_auth_method' => 'none']);
        $clientId = self::stringAt($client, 'client_id');
        [$verifier, $challenge] = self::pkce();

        // Not signed in on the shop: the consent page sends the customer to the login page.
        $this->client->request('GET', '/mcp-oauth/authorize?' . http_build_query($this->authorizeQuery($clientId, $challenge)));
        self::assertResponseRedirects();
        $consentUrl = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/account/ai-assistants/authorize?', $consentUrl);
        $this->client->request('GET', $consentUrl);
        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));

        $this->loginShop($customer);
        $crawler = $this->client->request('GET', $consentUrl);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Connect Claude to your account', (string) $this->client->getResponse()->getContent());
        self::assertSame('After your decision you are sent back to claude.ai.', trim($crawler->filter('[data-test-tmonier-b2b-mcp-redirect-host]')->text()));
        self::assertCount(2, $crawler->filter('[data-test-tmonier-b2b-mcp-scopes] li'));

        $this->client->submit($crawler->filter('[data-test-tmonier-b2b-mcp-approve]')->form());
        $code = $this->codeFromRedirect('xyz-state');

        // Wrong verifier: refused.
        $this->token(['grant_type' => 'authorization_code', 'client_id' => $clientId, 'code' => $code, 'redirect_uri' => self::REDIRECT_URI, 'code_verifier' => str_repeat('x', 50)]);
        self::assertResponseStatusCodeSame(400);

        $tokens = $this->token(['grant_type' => 'authorization_code', 'client_id' => $clientId, 'code' => $code, 'redirect_uri' => self::REDIRECT_URI, 'code_verifier' => $verifier]);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertSame('Bearer', $tokens['token_type']);
        $accessToken = self::stringAt($tokens, 'access_token');
        self::assertSame(80, \strlen($accessToken));
        self::assertSame(1, $this->sqlInt('SELECT COUNT(*) FROM tmonier_b2b_mcp_token WHERE identifier_hash = ?', [hash('sha256', $accessToken)]));
        self::assertSame(0, $this->sqlInt('SELECT COUNT(*) FROM tmonier_b2b_mcp_token WHERE identifier_hash = ?', [$accessToken]));

        $this->client->getCookieJar()->clear();
        $tools = $this->rpc($accessToken, 'tools/list');
        self::assertArrayHasKey('result', $tools);
        self::assertSame([], $this->client->getCookieJar()->allRawValues('http://localhost/'), 'The MCP endpoint is stateless: no session.');

        // Refresh: a new pair, the old refresh token is spent.
        $refreshed = $this->token(['grant_type' => 'refresh_token', 'client_id' => $clientId, 'refresh_token' => $tokens['refresh_token']]);
        self::assertResponseIsSuccessful();
        $this->mcpSessionId = null;
        self::assertArrayHasKey('result', $this->rpc(self::stringAt($refreshed, 'access_token'), 'tools/list'));

        // Replaying the spent refresh token revokes the whole connection (stolen token).
        $this->token(['grant_type' => 'refresh_token', 'client_id' => $clientId, 'refresh_token' => $tokens['refresh_token']]);
        self::assertResponseStatusCodeSame(400);
        $this->post(self::stringAt($refreshed, 'access_token'), ['jsonrpc' => '2.0', 'id' => 99, 'method' => 'tools/list']);
        self::assertResponseStatusCodeSame(401);
    }

    public function testAReplayedAuthorizationCodeRevokesTheTokensItGave(): void
    {
        $customer = $this->customer();
        $clientId = self::stringAt($this->register(['redirect_uris' => [self::REDIRECT_URI], 'token_endpoint_auth_method' => 'none']), 'client_id');
        [$verifier, $challenge] = self::pkce();
        $code = $this->approve($customer, $clientId, $challenge);
        $request = ['grant_type' => 'authorization_code', 'client_id' => $clientId, 'code' => $code, 'redirect_uri' => self::REDIRECT_URI, 'code_verifier' => $verifier];

        $tokens = $this->token($request);
        self::assertResponseIsSuccessful();
        $this->token($request);
        self::assertResponseStatusCodeSame(400);

        $this->post(self::stringAt($tokens, 'access_token'), ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);
        self::assertResponseStatusCodeSame(401);
    }

    public function testARefreshNeverExceedsWhatTheCustomerApprovesNow(): void
    {
        $customer = $this->customer();
        $clientId = self::stringAt($this->register(['redirect_uris' => [self::REDIRECT_URI], 'token_endpoint_auth_method' => 'none']), 'client_id');
        [$verifier, $challenge] = self::pkce();
        $tokens = $this->token(['grant_type' => 'authorization_code', 'client_id' => $clientId, 'code' => $this->approve($customer, $clientId, $challenge), 'redirect_uri' => self::REDIRECT_URI, 'code_verifier' => $verifier]);

        // The customer approves the assistant again, for reading only.
        [$verifier, $challenge] = self::pkce();
        $this->loginShop($customer);
        $query = ['scope' => 'b2b:read'] + $this->authorizeQuery($clientId, $challenge);
        $crawler = $this->client->request('GET', '/en_US/account/ai-assistants/authorize?' . http_build_query($query));
        $this->client->submit($crawler->filter('[data-test-tmonier-b2b-mcp-approve]')->form());
        $code = $this->codeFromRedirect('xyz-state');
        $this->client->getCookieJar()->clear();

        // The tokens of the wider consent are revoked, refresh token included.
        $this->token(['grant_type' => 'refresh_token', 'client_id' => $clientId, 'refresh_token' => $tokens['refresh_token']]);
        self::assertResponseStatusCodeSame(400);

        $narrowed = $this->token(['grant_type' => 'authorization_code', 'client_id' => $clientId, 'code' => $code, 'redirect_uri' => self::REDIRECT_URI, 'code_verifier' => $verifier]);
        self::assertResponseIsSuccessful();
        $refreshed = $this->token(['grant_type' => 'refresh_token', 'client_id' => $clientId, 'refresh_token' => $narrowed['refresh_token']]);
        self::assertResponseIsSuccessful();
        $result = $this->callTool(self::stringAt($refreshed, 'access_token'), 'reorder', ['order_number' => 'any', 'idempotency_key' => 'scope-after-refresh']);
        self::assertStringContainsString('was not granted the "b2b:cart" permission', self::stringAt($result, 'error'));
    }

    public function testWithdrawingAPermissionStopsTheTokensAlreadyIssued(): void
    {
        $customer = $this->customer();
        $connection = null;
        $token = $this->accessToken($customer, connection: $connection);
        $manager = $this->service('tmonier_b2b_mcp.oauth.connection_manager');
        self::assertInstanceOf(\Tmonier\SyliusB2bMcpPlugin\OAuth\ConnectionManager::class, $manager);
        $client = $connection->getClient();
        self::assertNotNull($client);

        // Re-approving the same permissions keeps the tokens.
        $manager->approve($this->managed($customer), $client, (string) $this->channel()->getCode(), ['b2b:read', 'b2b:cart']);
        self::assertArrayHasKey('result', $this->rpc($token, 'tools/list'));

        $manager->approve($this->managed($customer), $client, (string) $this->channel()->getCode(), ['b2b:read']);
        $this->post($token, ['jsonrpc' => '2.0', 'id' => 9, 'method' => 'tools/list']);
        self::assertResponseStatusCodeSame(401);
    }

    public function testATokenNeverGetsMoreThanItsConnectionAllows(): void
    {
        $customer = $this->customer();
        $connection = null;
        $token = $this->accessToken($customer, connection: $connection);
        // Narrowed without revocation (e.g. by hand in the database): the scope check still applies.
        $this->connection()->executeStatement('UPDATE tmonier_b2b_mcp_connection SET scopes = ? WHERE id = ?', ['["b2b:read"]', $connection->getId()]);
        $this->entityManager()->refresh($connection);

        $result = $this->callTool($token, 'quick_order_add', ['lines' => [['sku' => (string) $this->variant()->getCode(), 'quantity' => 1]], 'confirm' => true, 'idempotency_key' => 'narrowed-key-1']);

        self::assertStringContainsString('was not granted the "b2b:cart" permission', self::stringAt($result, 'error'));
        self::assertNull($this->cartOf($customer));
    }

    public function testAClientWhoseRedirectUriIsNoLongerAllowedCannotAuthorize(): void
    {
        $this->connection()->executeStatement("INSERT INTO tmonier_b2b_mcp_client (identifier, name, redirect_uris, created_at) VALUES ('legacy-client', 'Legacy', '[\"https://legacy.example.com/callback\"]', ?)", [(new \DateTimeImmutable())->format('Y-m-d H:i:s')]);
        [, $challenge] = self::pkce();
        $query = ['client_id' => 'legacy-client', 'redirect_uri' => 'https://legacy.example.com/callback'] + $this->authorizeQuery('legacy-client', $challenge);

        $this->client->request('GET', '/mcp-oauth/authorize?' . http_build_query($query));

        self::assertResponseStatusCodeSame(400);
        self::assertStringNotContainsString('legacy.example.com', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testPkceWithS256IsRequired(): void
    {
        $clientId = self::stringAt($this->register(['redirect_uris' => [self::REDIRECT_URI], 'token_endpoint_auth_method' => 'none']), 'client_id');
        $query = $this->authorizeQuery($clientId, 'ignored');

        unset($query['code_challenge'], $query['code_challenge_method']);
        $this->client->request('GET', '/mcp-oauth/authorize?' . http_build_query($query));
        self::assertResponseStatusCodeSame(400);

        $query['code_challenge'] = str_repeat('a', 43);
        $query['code_challenge_method'] = 'plain';
        $this->client->request('GET', '/mcp-oauth/authorize?' . http_build_query($query));
        self::assertResponseStatusCodeSame(400);
        self::assertStringContainsString('S256', (string) $this->client->getResponse()->getContent());
    }

    public function testTheResourceIndicatorMustNameThisEndpoint(): void
    {
        $clientId = self::stringAt($this->register(['redirect_uris' => [self::REDIRECT_URI], 'token_endpoint_auth_method' => 'none']), 'client_id');
        [, $challenge] = self::pkce();

        $this->client->request('GET', '/mcp-oauth/authorize?' . http_build_query($this->authorizeQuery($clientId, $challenge) + ['resource' => 'https://other.example.com/mcp']));
        self::assertResponseStatusCodeSame(400);
        self::assertStringContainsString('invalid_target', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/mcp-oauth/authorize?' . http_build_query($this->authorizeQuery($clientId, $challenge) + ['resource' => 'http://localhost/_mcp']));
        self::assertResponseRedirects();
    }

    public function testDenyingSendsAccessDeniedBack(): void
    {
        $customer = $this->customer();
        $clientId = self::stringAt($this->register(['redirect_uris' => [self::REDIRECT_URI], 'token_endpoint_auth_method' => 'none']), 'client_id');
        [, $challenge] = self::pkce();
        $this->loginShop($customer);

        $crawler = $this->client->request('GET', '/en_US/account/ai-assistants/authorize?' . http_build_query($this->authorizeQuery($clientId, $challenge)));
        $this->client->submit($crawler->filter('[data-test-tmonier-b2b-mcp-deny]')->form());

        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringStartsWith(self::REDIRECT_URI . '?', $location);
        self::assertStringContainsString('error=access_denied', $location);
        self::assertSame(0, $this->sqlInt('SELECT COUNT(*) FROM tmonier_b2b_mcp_connection WHERE customer_id = ?', [$customer->getId()]));
    }

    public function testTheConsentNeedsAValidCsrfToken(): void
    {
        $customer = $this->customer();
        $clientId = self::stringAt($this->register(['redirect_uris' => [self::REDIRECT_URI], 'token_endpoint_auth_method' => 'none']), 'client_id');
        [, $challenge] = self::pkce();
        $this->loginShop($customer);

        $this->client->request('POST', '/en_US/account/ai-assistants/authorize?' . http_build_query($this->authorizeQuery($clientId, $challenge)), ['decision' => 'approve', '_csrf_token' => 'forged']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(0, $this->sqlInt('SELECT COUNT(*) FROM tmonier_b2b_mcp_connection WHERE customer_id = ?', [$customer->getId()]));
    }

    public function testTheCustomerDisconnectsTheAssistantFromTheAccount(): void
    {
        $customer = $this->customer();
        $connection = null;
        $token = $this->accessToken($customer, connection: $connection);
        self::assertInstanceOf(ConnectionInterface::class, $connection);
        self::assertArrayHasKey('result', $this->rpc($token, 'tools/list'));

        $this->loginShop($customer);
        $crawler = $this->client->request('GET', '/en_US/account/ai-assistants');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-test-tmonier-b2b-mcp-connection]'));
        $this->client->submit($crawler->filter(sprintf('[data-test-tmonier-b2b-mcp-revoke="%d"]', $connection->getId()))->form());
        self::assertResponseRedirects('/en_US/account/ai-assistants');
        $crawler = $this->client->followRedirect();
        self::assertCount(1, $crawler->filter('[data-test-tmonier-b2b-mcp-no-connection]'));

        $this->client->getCookieJar()->clear();
        $this->post($token, ['jsonrpc' => '2.0', 'id' => 5, 'method' => 'tools/list']);
        self::assertResponseStatusCodeSame(401);
    }

    public function testACustomerCannotDisconnectTheAssistantOfAnother(): void
    {
        $connection = null;
        $this->accessToken($this->customer(), connection: $connection);
        self::assertInstanceOf(ConnectionInterface::class, $connection);
        $this->loginShop($this->customer('Eve', 'Other'));
        $crawler = $this->client->request('GET', '/en_US/account/ai-assistants');
        self::assertCount(0, $crawler->filter('[data-test-tmonier-b2b-mcp-connection]'));

        $this->client->request('POST', sprintf('/en_US/account/ai-assistants/%d/revoke', $connection->getId()), ['_csrf_token' => 'any']);

        self::assertResponseStatusCodeSame(404);
        self::assertNull($this->connection()->fetchOne('SELECT revoked_at FROM tmonier_b2b_mcp_connection WHERE id = ?', [$connection->getId()]));
    }

    public function testDisconnectingNeedsAValidCsrfToken(): void
    {
        $customer = $this->customer();
        $connection = null;
        $this->accessToken($customer, connection: $connection);
        self::assertInstanceOf(ConnectionInterface::class, $connection);
        $this->loginShop($customer);

        $this->client->request('POST', sprintf('/en_US/account/ai-assistants/%d/revoke', $connection->getId()), ['_csrf_token' => 'forged']);

        self::assertResponseStatusCodeSame(403);
        self::assertNull($this->connection()->fetchOne('SELECT revoked_at FROM tmonier_b2b_mcp_connection WHERE id = ?', [$connection->getId()]));
    }

    public function testTheRevocationEndpointRevokesTheTokensOfItsClient(): void
    {
        $customer = $this->customer();
        $client = $this->register(['redirect_uris' => [self::REDIRECT_URI], 'token_endpoint_auth_method' => 'none']);
        $clientId = self::stringAt($client, 'client_id');
        [$verifier, $challenge] = self::pkce();
        $tokens = $this->token(['grant_type' => 'authorization_code', 'client_id' => $clientId, 'code' => $this->approve($customer, $clientId, $challenge), 'redirect_uri' => self::REDIRECT_URI, 'code_verifier' => $verifier]);

        // Another client cannot revoke it.
        $other = self::stringAt($this->register(['redirect_uris' => [self::REDIRECT_URI], 'token_endpoint_auth_method' => 'none']), 'client_id');
        $this->client->request('POST', '/mcp-oauth/revoke', ['client_id' => $other, 'token' => $tokens['access_token']]);
        self::assertResponseIsSuccessful();
        $this->client->getCookieJar()->clear();
        self::assertArrayHasKey('result', $this->rpc(self::stringAt($tokens, 'access_token'), 'tools/list'));

        $this->client->request('POST', '/mcp-oauth/revoke', ['client_id' => $clientId, 'token' => $tokens['refresh_token']]);
        self::assertResponseIsSuccessful();
        $this->token(['grant_type' => 'refresh_token', 'client_id' => $clientId, 'refresh_token' => $tokens['refresh_token']]);
        self::assertResponseStatusCodeSame(400);

        $this->client->request('POST', '/mcp-oauth/revoke', ['client_id' => $clientId, 'token' => $tokens['access_token']]);
        self::assertResponseIsSuccessful();
        $this->post(self::stringAt($tokens, 'access_token'), ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/list']);
        self::assertResponseStatusCodeSame(401);
    }

    public function testATokenIsBoundToItsChannelAndToAnEnabledAccount(): void
    {
        $customer = $this->customer();
        $otherChannelToken = $this->accessToken($customer, channelCode: 'ANOTHER_CHANNEL');
        $this->post($otherChannelToken, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);
        self::assertResponseStatusCodeSame(401);

        $token = $this->accessToken($customer);
        $customer->getUser()?->setEnabled(false);
        $this->entityManager()->flush();
        $this->post($token, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);
        self::assertResponseStatusCodeSame(401);
    }

    public function testRequestsAreRateLimitedPerConnection(): void
    {
        $customer = $this->customer();
        $connection = null;
        $token = $this->accessToken($customer, connection: $connection);
        self::assertInstanceOf(ConnectionInterface::class, $connection);
        $limiter = $this->client->getContainer()->get('limiter.tmonier_b2b_mcp_requests');
        self::assertInstanceOf(\Symfony\Component\RateLimiter\RateLimiterFactory::class, $limiter);
        $limiter->create('connection-' . $connection->getId())->consume(120);

        $this->post($token, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);

        self::assertResponseStatusCodeSame(429);
        self::assertNotNull($this->client->getResponse()->headers->get('Retry-After'));
    }

    public function testRegistrationIsRateLimitedPerAddress(): void
    {
        $limiter = $this->client->getContainer()->get('limiter.tmonier_b2b_mcp_registration');
        self::assertInstanceOf(\Symfony\Component\RateLimiter\RateLimiterFactory::class, $limiter);
        $limiter->create('127.0.0.1')->consume(60);

        $this->register(['redirect_uris' => [self::REDIRECT_URI]]);

        self::assertResponseStatusCodeSame(429);
    }

    /** @return array{string, string} verifier and S256 challenge */
    private static function pkce(): array
    {
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');

        return [$verifier, rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=')];
    }

    /** @return array<string, string> */
    private function authorizeQuery(string $clientId, string $challenge): array
    {
        return [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => self::REDIRECT_URI,
            'scope' => 'b2b:read b2b:cart',
            'state' => 'xyz-state',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ];
    }

    private function approve(CustomerInterface $customer, string $clientId, string $challenge): string
    {
        $this->loginShop($customer);
        $crawler = $this->client->request('GET', '/en_US/account/ai-assistants/authorize?' . http_build_query($this->authorizeQuery($clientId, $challenge)));
        $this->client->submit($crawler->filter('[data-test-tmonier-b2b-mcp-approve]')->form());
        $code = $this->codeFromRedirect('xyz-state');
        $this->client->getCookieJar()->clear();

        return $code;
    }

    private function codeFromRedirect(string $state): string
    {
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringStartsWith(self::REDIRECT_URI . '?', $location);
        parse_str((string) parse_url($location, \PHP_URL_QUERY), $query);
        self::assertSame($state, $query['state'] ?? null);
        self::assertIsString($query['code'] ?? null);

        return $query['code'];
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return array<mixed>
     */
    private function register(array $metadata): array
    {
        $this->client->request('POST', '/mcp-oauth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($metadata, \JSON_THROW_ON_ERROR));

        return $this->json();
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<mixed>
     */
    private function token(array $parameters): array
    {
        $this->client->request('POST', '/mcp-oauth/token', $parameters);

        return $this->json();
    }

    /** @return array<mixed> */
    private function json(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($data, (string) $this->client->getResponse()->getContent());

        return $data;
    }
}
