# CLAUDE.md

Sylius 2 plugin: B2B tools (orders, reorder, quick order, quotes, trade credit, checkout link)
registered on the official Sylius MCP server (sylius/mcp-server-plugin 0.2, symfony/mcp-bundle
0.3), with an OAuth 2.1 authorization server for shop customers (league/oauth2-server).
Design: `SPEC.md`. Public docs: `README.md`.

## Commands

PHP needs more than 128 MB to compile the test application container: prefix console and
PHPStan calls with `php -d memory_limit=2G` when running locally.

```bash
docker compose up -d mysql mailhog        # ports come from compose.override.yml (gitignored)
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite non-unit   # integration + functional, test DB + fixtures
vendor/bin/phpstan analyse                # level max, src + tests
vendor/bin/ecs check
vendor/bin/console lint:container
vendor/bin/console tmonier:b2b-mcp:purge
vendor/bin/console tmonier:b2b-mcp:create-client "Claude" --redirect-uri=https://claude.ai/api/mcp/auth_callback
```

Local env overrides (gitignored): `compose.override.yml` (MySQL published on 3332, MailHog on
8051/1051), `tests/TestApplication/.env.local` (DATABASE_URL, MAILER_DSN,
SYLIUS_MCP_SERVER_API_SHOP_BASE_URI) and `.env.test.local` (DATABASE_URL). Databases:
`tmonier_sylius_b2b_mcp_dev` / `_test`, default fixture suite + company accounts, trade credit
and quote request fixtures (`b2b@example.com`, `*@acme-industries.example.com` / `sylius`). Dev
server: `symfony server:start -d --port=8099 --no-tls`, MCP endpoint
`http://localhost:8099/_mcp` (use `localhost`: the fixture channel host name).

## Architecture

- `OAuth/`: league repositories (`Repository/`) and entities (`Entity/`, opaque access tokens,
  `NullCryptKey`), `TokenStore` (hashed tokens, revocation, purge), `ConnectionManager`
  (consent, revocation), `ClientRegistrar` (RFC 7591), `RedirectUriPolicy`,
  `AuthorizationRequestValidator` (PKCE S256, redirect URI allow-list, RFC 8707),
  `AuthorizationServerFactory`, `McpEndpoint` (URLs), `PsrBridge`, `RefreshTokenDecoder`.
- `Controller/OAuth/`: metadata, register, authorize (redirects to the consent page), token,
  revoke. `Controller/Shop/`: consent page and "AI assistants" account page.
  `Controller/Admin/`: revoke a connection. Routes in `config/routes/*.yaml`.
- `Security/McpAuthenticationListener`: `kernel.request` priority 9 (bearer token, 401 with
  `resource_metadata`, rate limit) and 7 (shop user as security token, no session).
  `CurrentConnection` reads the `AuthenticatedConnection` from the request.
- `Mcp/`: `B2bToolLoader` (tag `mcp.loader`), `HiddenToolsRegistry` (decorates `mcp.registry`),
  `ToolRunner` (auth, scope, idempotency, audit, refusals), `Tool/*` (one class per tool, service
  id = class, tags `mcp.tool` + `tmonier_b2b_mcp.tool`).
- Optional tools: `config/services/{quick_order,quote_request,trade_credit,company_accounts}.php`
  loaded when the bundle is in `kernel.bundles` (`TmonierSyliusB2bMcpExtension::OPTIONAL_TOOLS`).
- `Cart/`: `CustomerCartProvider`, `CartLinesAdderInterface` (core or quick order), `CartLockChecker`.
- `Order/`: `OrderFinder`, `OrderPresenter`, `CompanyOrdersInterface` (null or company accounts),
  `ShopUrlGenerator`, `Text`.
- `Idempotency/IdempotencyStore` (DBAL), `Audit/ToolCallLogger`, `RateLimit/RateLimiter`
  (framework rate limiters prepended by the extension), `Command/`, `Menu/`.
- Tests: `tests/Functional/B2bMcpTestTrait` creates its own data in a rolled-back transaction,
  mints tokens directly, speaks JSON-RPC to `/_mcp`; the browser requests reset the entity
  manager, so helpers re-fetch entities (`managed()`). The test application enables every
  companion plugin and aliases the core adder publicly (`tests/TestApplication/config/services.php`).

## Rules

- Never store a token, code or secret in clear: hash (SHA-256 for tokens, password_hash for
  client secrets).
- Every tool goes through `ToolRunner::run()` with a scope; cart-changing tools pass their
  `idempotency_key`. No tool completes an order, pays, or changes account data.
- Tool results are plain data: pass free text through `Text::clean()`; amounts through
  `Text::money()`.
- Tool schemas and `__invoke()` parameters must agree (`ToolDefinitionsTest`).
- Labels in `messages`, flashes in `flashes`; every key in en, fr, de, es, it, nl, pl
  (`TranslationsTest`).
- Tests never depend on the random Sylius fixtures (`NoSkippedTestsTest`); amounts that
  promotions may change are computed from the orders.
- When sylius/mcp-server-plugin tags a version on mcp-bundle 0.13+: register the tools per
  server (`mcp.servers.<name>.registry.tools` or the loader), and read the HTTP path of that
  server for `endpoint_path`.
