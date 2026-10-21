<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Functional;

use Sylius\Component\Core\Model\CustomerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tmonier\SyliusB2bMcpPlugin\Entity\Connection;
use Tmonier\SyliusB2bMcpPlugin\Entity\ConnectionInterface;
use Tmonier\SyliusB2bMcpPlugin\OAuth\ApproveScopeGuard;
use Tmonier\SyliusB2bMcpPlugin\OAuth\AuthorizationServerFactory;
use Tmonier\SyliusB2bMcpPlugin\OAuth\PsrBridge;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Repository\AccessTokenRepository;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Repository\AuthCodeRepository;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Repository\ClientRepository;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Repository\RefreshTokenRepository;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Repository\ScopeRepository;
use Tmonier\SyliusB2bMcpPlugin\OAuth\ScopeCatalog;
use Tmonier\SyliusB2bMcpPlugin\OAuth\TokenStore;

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
        self::assertSame('Bearer resource_metadata="http://localhost/.well-known/oauth-protected-resource/_mcp", scope="b2b:read b2b:cart b2b:approve"', $this->client->getResponse()->headers->get('WWW-Authenticate'));

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
        // b2b:approve is listed because the test application enables approval decisions.
        self::assertSame(['b2b:read', 'b2b:cart', 'b2b:approve'], $resource['scopes_supported']);

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
        [$verifier, $challenge] = $this->pkce();

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
        [$verifier, $challenge] = $this->pkce();
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
        [$verifier, $challenge] = $this->pkce();
        $tokens = $this->token(['grant_type' => 'authorization_code', 'client_id' => $clientId, 'code' => $this->approve($customer, $clientId, $challenge), 'redirect_uri' => self::REDIRECT_URI, 'code_verifier' => $verifier]);

        // The customer approves the assistant again, for reading only.
        [$verifier, $challenge] = $this->pkce();
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
        [, $challenge] = $this->pkce();
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
        [, $challenge] = $this->pkce();

        $this->client->request('GET', '/mcp-oauth/authorize?' . http_build_query($this->authorizeQuery($clientId, $challenge) + ['resource' => 'https://other.example.com/mcp']));
        self::assertResponseStatusCodeSame(400);
        self::assertStringContainsString('invalid_target', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/mcp-oauth/authorize?' . http_build_query($this->authorizeQuery($clientId, $challenge) + ['resource' => 'http://localhost/_mcp']));
        self::assertResponseRedirects();
    }

    public function testTheConsentPageNamesTheAssistantFromItsVerifiedCallbackHost(): void
    {
        $customer = $this->customer();
        $client = $this->register(['client_name' => "Totally Legit Bank \u{202E}\u{200B}", 'redirect_uris' => [self::REDIRECT_URI], 'token_endpoint_auth_method' => 'none']);
        self::assertSame('Totally Legit Bank', $client['client_name'], 'Control and bidi characters are removed.');
        [, $challenge] = $this->pkce();
        $this->loginShop($customer);

        $crawler = $this->client->request('GET', '/en_US/account/ai-assistants/authorize?' . http_build_query($this->authorizeQuery(self::stringAt($client, 'client_id'), $challenge)));

        self::assertResponseIsSuccessful();
        self::assertSame('Connect Claude to your account', trim($crawler->filter('[data-test-tmonier-b2b-mcp-consent] h1')->text()));
        $notice = $crawler->filter('[data-test-tmonier-b2b-mcp-unverified]');
        self::assertCount(1, $notice);
        self::assertStringContainsString('“Totally Legit Bank”', $notice->text());
        self::assertStringContainsString((new \DateTimeImmutable())->format('Y-m-d'), $notice->text());
    }

    public function testAClientCreatedByTheMerchantIsNotFlaggedAsSelfRegistered(): void
    {
        $customer = $this->customer();
        $registrar = $this->service('tmonier_b2b_mcp.oauth.client_registrar');
        self::assertInstanceOf(\Tmonier\SyliusB2bMcpPlugin\OAuth\ClientRegistrar::class, $registrar);
        [$client] = $registrar->create('Claude for Acme', [self::REDIRECT_URI], false);
        [, $challenge] = $this->pkce();
        $this->loginShop($customer);

        $crawler = $this->client->request('GET', '/en_US/account/ai-assistants/authorize?' . http_build_query($this->authorizeQuery($client->getIdentifier(), $challenge)));

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-test-tmonier-b2b-mcp-unverified]'));
    }

    public function testTheApprovalPermissionIsGrantedOnlyWhenTheCustomerTicksIt(): void
    {
        $customer = $this->customer();
        $clientId = self::stringAt($this->register(['redirect_uris' => [self::REDIRECT_URI], 'token_endpoint_auth_method' => 'none']), 'client_id');
        $this->loginShop($customer);

        foreach ([false => ['b2b:read', 'b2b:cart'], true => ['b2b:read', 'b2b:cart', 'b2b:approve']] as $tick => $expected) {
            [, $challenge] = $this->pkce();
            $query = ['scope' => 'b2b:read b2b:cart b2b:approve'] + $this->authorizeQuery($clientId, $challenge);
            $crawler = $this->client->request('GET', '/en_US/account/ai-assistants/authorize?' . http_build_query($query));
            self::assertCount(1, $crawler->filter('[data-test-tmonier-b2b-mcp-approve-warning]'));
            $checkbox = $crawler->filter('[data-test-tmonier-b2b-mcp-grant-approve]');
            self::assertNull($checkbox->attr('checked'), 'Never pre-ticked.');
            $form = $crawler->filter('[data-test-tmonier-b2b-mcp-approve]')->form();
            if ((bool) $tick) {
                $field = $form['grant_approve'];
                self::assertInstanceOf(\Symfony\Component\DomCrawler\Field\ChoiceFormField::class, $field);
                $field->tick();
            }
            $this->client->submit($form);
            self::assertStringStartsWith(self::REDIRECT_URI . '?', (string) $this->client->getResponse()->headers->get('Location'));

            $scopes = $this->sqlValue('SELECT scopes FROM tmonier_b2b_mcp_connection WHERE customer_id = ? AND revoked_at IS NULL', [$customer->getId()]);
            self::assertIsString($scopes);
            self::assertSame($expected, json_decode($scopes, true));
        }

        // A client asking for no scope gets the default ones: never b2b:approve.
        [, $challenge] = $this->pkce();
        $query = $this->authorizeQuery($clientId, $challenge);
        unset($query['scope']);
        $crawler = $this->client->request('GET', '/en_US/account/ai-assistants/authorize?' . http_build_query($query));
        self::assertCount(0, $crawler->filter('[data-test-tmonier-b2b-mcp-approve-warning]'));
        self::assertCount(2, $crawler->filter('[data-test-tmonier-b2b-mcp-scopes] li'));
    }

    /**
     * MCP11-02: approval decisions turned off by the merchant. Grants keep working for b2b:read and
     * b2b:cart (the refresh does not fail), lose b2b:approve for good, and turning decisions back
     * on (the test application has them on) needs a new consent.
     */
    public function testTurningDecisionsOffAndOnNeverRestoresTheApprovalPermission(): void
    {
        $customer = $this->customer();
        $clientId = self::stringAt($this->register(['redirect_uris' => [self::REDIRECT_URI], 'token_endpoint_auth_method' => 'none']), 'client_id');
        [$verifier, $challenge] = $this->pkce();
        $this->loginShop($customer);
        $crawler = $this->client->request('GET', '/en_US/account/ai-assistants/authorize?' . http_build_query(['scope' => 'b2b:read b2b:cart b2b:approve'] + $this->authorizeQuery($clientId, $challenge)));
        $form = $crawler->filter('[data-test-tmonier-b2b-mcp-approve]')->form();
        $field = $form['grant_approve'];
        self::assertInstanceOf(\Symfony\Component\DomCrawler\Field\ChoiceFormField::class, $field);
        $field->tick();
        $this->client->submit($form);
        $code = $this->codeFromRedirect('xyz-state');
        $this->client->getCookieJar()->clear();
        $tokens = $this->token(['grant_type' => 'authorization_code', 'client_id' => $clientId, 'code' => $code, 'redirect_uri' => self::REDIRECT_URI, 'code_verifier' => $verifier]);
        $connectionId = $this->sqlInt('SELECT id FROM tmonier_b2b_mcp_connection WHERE customer_id = ?', [$customer->getId()]);
        self::assertSame(['b2b:read', 'b2b:cart', 'b2b:approve'], $this->sqlJson('SELECT scopes FROM tmonier_b2b_mcp_connection WHERE id = ?', [$connectionId]));

        // Decisions off: the refresh succeeds with b2b:read and b2b:cart only, and the connection loses b2b:approve.
        $disabled = new ScopeCatalog(false);
        $store = $this->service('tmonier_b2b_mcp.oauth.token_store');
        self::assertInstanceOf(TokenStore::class, $store);
        $guard = new ApproveScopeGuard($this->entityManager(), $disabled, new ArrayAdapter(), Connection::class);
        $encryptionKey = self::getContainer()->getParameter('tmonier_b2b_mcp.oauth.encryption_key');
        self::assertIsString($encryptionKey);
        $server = AuthorizationServerFactory::create(new ClientRepository($store), new AccessTokenRepository($store), new ScopeRepository($store, $disabled, $guard), new AuthCodeRepository($store), new RefreshTokenRepository($store), $encryptionKey, 'PT1H', 'P30D', 'PT10M');
        $bridge = new PsrBridge();
        $response = $server->respondToAccessTokenRequest(
            $bridge->toPsr(\Symfony\Component\HttpFoundation\Request::create('/mcp-oauth/token', 'POST', ['grant_type' => 'refresh_token', 'client_id' => $clientId, 'refresh_token' => $tokens['refresh_token']])),
            $bridge->createPsrResponse(),
        );
        self::assertSame(200, $response->getStatusCode());
        $refreshed = json_decode((string) $response->getBody(), true);
        $accessToken = self::stringAt($refreshed, 'access_token');
        self::assertSame(['b2b:read', 'b2b:cart'], $this->sqlJson('SELECT scopes FROM tmonier_b2b_mcp_token WHERE identifier_hash = ?', [hash('sha256', $accessToken)]));
        self::assertSame(['b2b:read', 'b2b:cart'], $this->sqlJson('SELECT scopes FROM tmonier_b2b_mcp_connection WHERE id = ?', [$connectionId]));

        // Decisions on again (the test application): reading works, approving does not without a new consent.
        self::assertSame(0, self::intAt($this->callTool($accessToken, 'list_my_orders'), 'total'));
        $result = $this->callTool($accessToken, 'decide_approval', ['order_number' => 'any', 'decision' => 'approve', 'idempotency_key' => 'after-reenable']);
        self::assertStringContainsString('was not granted the "b2b:approve" permission', self::stringAt($result, 'error'));
    }

    public function testConnectionsNotUsedWhileDecisionsAreOffLoseTheApprovalPermissionToo(): void
    {
        $connection = null;
        $token = $this->accessToken($this->customer(), ['b2b:read', 'b2b:cart', 'b2b:approve'], connection: $connection);
        $cache = new ArrayAdapter();

        // Sweep of a disabled period (first request, cache warmup or purge command), once.
        (new ApproveScopeGuard($this->entityManager(), new ScopeCatalog(false), $cache, Connection::class))->enforce();
        self::assertSame(['b2b:read', 'b2b:cart'], $this->sqlJson('SELECT scopes FROM tmonier_b2b_mcp_connection WHERE id = ?', [$connection->getId()]));
        self::assertTrue($cache->hasItem('tmonier_b2b_mcp.approve_scope_swept'));
        // Back on: the flag is reset for the next disabled period, the permission stays removed.
        (new ApproveScopeGuard($this->entityManager(), new ScopeCatalog(true), $cache, Connection::class))->enforce();
        self::assertFalse($cache->hasItem('tmonier_b2b_mcp.approve_scope_swept'));

        $result = $this->callTool($token, 'decide_approval', ['order_number' => 'any', 'decision' => 'approve', 'idempotency_key' => 'unused-grant-1']);
        self::assertStringContainsString('was not granted the "b2b:approve" permission', self::stringAt($result, 'error'));
    }

    public function testDenyingSendsAccessDeniedBack(): void
    {
        $customer = $this->customer();
        $clientId = self::stringAt($this->register(['redirect_uris' => [self::REDIRECT_URI], 'token_endpoint_auth_method' => 'none']), 'client_id');
        [, $challenge] = $this->pkce();
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
        [, $challenge] = $this->pkce();
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
        [$verifier, $challenge] = $this->pkce();
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
        self::assertInstanceOf(\Symfony\Component\RateLimiter\RateLimiterFactoryInterface::class, $limiter);
        $limiter->create('connection-' . $connection->getId())->consume(120);

        $this->post($token, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);

        self::assertResponseStatusCodeSame(429);
        self::assertNotNull($this->client->getResponse()->headers->get('Retry-After'));
    }

    public function testRegistrationIsRateLimitedPerAddress(): void
    {
        $limiter = $this->client->getContainer()->get('limiter.tmonier_b2b_mcp_registration');
        self::assertInstanceOf(\Symfony\Component\RateLimiter\RateLimiterFactoryInterface::class, $limiter);
        $limiter->create('127.0.0.1')->consume(60);

        $this->register(['redirect_uris' => [self::REDIRECT_URI]]);

        self::assertResponseStatusCodeSame(429);
    }

    /** @return array{string, string} verifier and S256 challenge */
    private function pkce(): array
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
