<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Tests\Tmonier\SyliusB2bMcpPlugin\Account\SwitchableChecker;
use Tmonier\SyliusB2bMcpPlugin\Entity\ConnectionInterface;
use Tmonier\SyliusB2bMcpPlugin\Entity\OAuthToken;
use Tmonier\SyliusB2bMcpPlugin\OAuth\TokenStore;

/** A customer whose account is not usable (a checker refuses) gets neither a consent nor the /_mcp endpoint. */
final class AccountUsabilityTest extends WebTestCase
{
    use B2bMcpTestTrait;

    private const REDIRECT_URI = 'https://claude.ai/api/mcp/auth_callback';

    protected function setUp(): void
    {
        $this->setUpClient();
        foreach ([$this->checker('first'), $this->checker('second')] as $checker) {
            $checker->refusal = null;
            $checker->failure = null;
            $checker->calls = 0;
        }
    }

    protected function tearDown(): void
    {
        $this->tearDownClient();
        parent::tearDown();
    }

    public function testWithoutRefusalNothingChanges(): void
    {
        $token = $this->accessToken($this->customer());

        self::assertArrayHasKey('result', $this->rpc($token, 'tools/list'));
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertSame(200, $this->consentStatus());
    }

    public function testAnExistingTokenOfARefusedAccountGets403WithoutPublicCacheAndIsAudited(): void
    {
        $token = $this->accessToken($this->customer());
        self::assertArrayHasKey('result', $this->rpc($token, 'tools/list'));
        $this->mcpSessionId = null;
        $this->checker('first')->refusal = 'registration_pending';

        $this->post($token, ['jsonrpc' => '2.0', 'id' => 7, 'method' => 'tools/list']);

        self::assertResponseStatusCodeSame(403);
        $body = $this->json();
        self::assertSame('access_denied', $body['error']);
        self::assertStringNotContainsString('registration_pending', (string) $this->client->getResponse()->getContent(), 'the reason stays server side');
        $cache = (string) $this->client->getResponse()->headers->get('Cache-Control');
        self::assertStringContainsString('no-store', $cache);
        self::assertStringNotContainsString('public', $cache);
        self::assertSame(1, $this->sqlInt("SELECT COUNT(*) FROM tmonier_b2b_mcp_tool_call WHERE outcome = 'refused' AND message LIKE '%registration_pending%'"));
    }

    public function testACheckerThatThrowsRefusesByCautionWithoutLeakingTheError(): void
    {
        $token = $this->accessToken($this->customer());
        $this->checker('first')->failure = new \RuntimeException('SQLSTATE[HY000] secret detail');

        $this->post($token, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);

        self::assertResponseStatusCodeSame(403);
        self::assertStringNotContainsString('secret detail', (string) $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('SQLSTATE', (string) $this->client->getResponse()->getContent());
    }

    public function testAForgedOrRevokedTokenIsA401AndNoCheckerSeesAResolvedCustomer(): void
    {
        $token = $this->accessToken($this->customer());
        $store = $this->service('tmonier_b2b_mcp.oauth.token_store');
        self::assertInstanceOf(TokenStore::class, $store);
        $store->revoke(OAuthToken::TYPE_ACCESS, $token);

        foreach ([str_repeat('a', 80), $token] as $unknown) {
            $this->post($unknown, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);

            self::assertResponseStatusCodeSame(401);
        }
        self::assertSame(0, $this->checker('first')->calls);
        self::assertSame(0, $this->checker('second')->calls);
    }

    public function testTwoCheckersOneRefusingRefusesAndTheFirstRefusalIsAudited(): void
    {
        $token = $this->accessToken($this->customer());
        $this->checker('second')->refusal = 'rejected';

        $this->post($token, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(1, $this->sqlInt("SELECT COUNT(*) FROM tmonier_b2b_mcp_tool_call WHERE outcome = 'refused' AND message LIKE '%rejected%'"));
    }

    public function testARefusedAccountIsRateLimitedBeforeAnythingIsWrittenToTheAuditLog(): void
    {
        $connection = null;
        $token = $this->accessToken($this->customer(), connection: $connection);
        self::assertInstanceOf(ConnectionInterface::class, $connection);
        $this->checker('first')->refusal = 'registration_pending';
        $limiter = $this->client->getContainer()->get('limiter.tmonier_b2b_mcp_requests');
        self::assertInstanceOf(RateLimiterFactory::class, $limiter);
        $limiter->create('connection-' . $connection->getId())->consume(120);
        $audited = $this->sqlInt('SELECT COUNT(*) FROM tmonier_b2b_mcp_tool_call');

        $this->post($token, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);

        self::assertResponseStatusCodeSame(429);
        self::assertSame($audited, $this->sqlInt('SELECT COUNT(*) FROM tmonier_b2b_mcp_tool_call'), 'a flood of refused calls cannot fill the audit log');
        self::assertSame(0, $this->checker('first')->calls, 'the checkers are not asked either');
    }

    public function testTheConsentIsRefusedAndNoCodeIsIssued(): void
    {
        $customer = $this->customer();
        $clientId = self::stringAt($this->registerClient(), 'client_id');
        [, $challenge] = $this->pkce();
        $this->loginShop($customer);
        $url = '/en_US/account/ai-assistants/authorize?' . http_build_query($this->authorizeQuery($clientId, $challenge));

        // The page was opened while the account was usable; the account is refused before the decision.
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('[data-test-tmonier-b2b-mcp-approve]')->form();
        $connections = $this->sqlInt('SELECT COUNT(*) FROM tmonier_b2b_mcp_connection');
        $this->checker('first')->refusal = 'registration_pending';

        $this->client->submit($form);
        self::assertResponseStatusCodeSame(403);
        self::assertFalse($this->client->getResponse()->isRedirect(), 'no redirection to the assistant, so no authorization code');
        self::assertSame($connections, $this->sqlInt('SELECT COUNT(*) FROM tmonier_b2b_mcp_connection'));

        $this->client->request('GET', $url);
        self::assertResponseStatusCodeSame(403);
    }

    /** @return array<mixed> */
    private function json(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($data);

        return $data;
    }

    private function consentStatus(): int
    {
        $client = self::stringAt($this->registerClient(), 'client_id');
        [, $challenge] = $this->pkce();
        $this->loginShop($this->customer());
        $this->client->request('GET', '/en_US/account/ai-assistants/authorize?' . http_build_query($this->authorizeQuery($client, $challenge)));

        return $this->client->getResponse()->getStatusCode();
    }

    private function checker(string $which): SwitchableChecker
    {
        $checker = $this->service('tests.account_usability.' . $which);
        self::assertInstanceOf(SwitchableChecker::class, $checker);

        return $checker;
    }

    /** @return array<mixed> */
    private function registerClient(): array
    {
        $this->client->request('POST', '/mcp-oauth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['client_name' => 'Claude', 'redirect_uris' => [self::REDIRECT_URI], 'token_endpoint_auth_method' => 'none'], \JSON_THROW_ON_ERROR));

        return $this->json();
    }

    /** @return array{string, string} */
    private function pkce(): array
    {
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');

        return [$verifier, rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=')];
    }

    /** @return array<string, string> */
    private function authorizeQuery(string $clientId, string $challenge): array
    {
        return ['response_type' => 'code', 'client_id' => $clientId, 'redirect_uri' => self::REDIRECT_URI, 'scope' => 'b2b:read b2b:cart', 'state' => 'xyz-state', 'code_challenge' => $challenge, 'code_challenge_method' => 'S256'];
    }
}
