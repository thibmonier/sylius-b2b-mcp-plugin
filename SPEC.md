# Specification — B2B tools for the official Sylius MCP server

Source: decision memo of 2026-10-12 on AI-assisted selling (§2.1 MCP, §3 legal, §5.3 "B2B
extension of the official MCP server"). Research done on 2026-10-12.

## 1. Research

### 1.1 The official MCP server

- `sylius/mcp-server-plugin` (MIT in the repository, Packagist shows "proprietary"): tags
  v0.1.0, v0.1.1, **v0.2.0 (2026-02-23)**. v0.2.0 requires `symfony/mcp-bundle ^0.3.0`, i.e.
  0.3.x only, which requires `mcp/sdk ^0.2|0.3` and **Symfony ^7.3|^8.0**. The `main` branch
  (2026-09-02, not tagged) moved to `symfony/mcp-bundle ^0.13` with several servers per
  application (`mcp.servers.sylius_shop`, path `/mcp/shop`).
- v0.2.0: one server, HTTP route `/_mcp` (mcp-bundle default) and stdio (`mcp:server`). 15 tools,
  all guest, calling the Shop API over HTTP. Tools are tagged `mcp.tool` and registered by a
  loader (`PluginDiscoveryLoader`, tag `mcp.loader`) that runs the SDK discoverer on its
  `src/Tool`. The README documents extension by `#[McpTool]` classes.
- symfony/mcp-bundle 0.3: `mcp.server.builder` calls `addLoaders(tagged_iterator('mcp.loader'))`
  and `setContainer()` with a locator of the `mcp.tool` services; the registry is the service
  `mcp.registry`. The HTTP controller runs the SDK `StreamableHttpTransport` inside the Symfony
  request (so the request listeners and the firewall run as for any route).
- mcp-bundle 0.4 to 0.14 changed the configuration (several servers, per-server registries by
  namespace). The extension points used here (`mcp.loader`, `mcp.tool`, `mcp.registry`) exist in
  0.3; 0.13 keeps `mcp.loader` (applied to every server) and assigns tagged tools by namespace.

**Conclusion: the official server can be extended cleanly** with its own extension point (a
loader registering tools whose services are tagged `mcp.tool`), without forking it nor running
a second server. The extension is tied to mcp-bundle 0.3 through the tagged 0.2.

### 1.2 Customer authentication options

| Option | Finding | Verdict |
|---|---|---|
| `sylius/admin-mcp-server-plugin` 0.1.2 (2026-08-03) | OAuth 2.0 PKCE with `league/oauth2-server-bundle`, for **admin users** only; requires mcp-bundle ^0.11, so it cannot even be installed with mcp-server-plugin 0.2 (mcp-bundle 0.3). Its routes take the root well-known paths. | Not reusable for customers. |
| `ohartwig/sylius-mcp-oauth` (2026-09-28) | GitHub repository without any commit on 2026-10-12, not on Packagist. | Not usable. |
| `league/oauth2-server-bundle` | Mature, but one bundle configuration per application (would collide with the admin MCP plugin), JWT access tokens signed with an RSA key pair to provision. | Not chosen. |
| **`league/oauth2-server` 9 (library)** | Mature (thephpleague), RFC 6749 + PKCE (RFC 7636), refresh token rotation, PSR-7. Repositories and entities are ours: our tables, our hashing. | **Chosen.** |
| Own OAuth implementation | Full control, but protocol details (PKCE verification, code replay, redirect matching) are easy to get wrong. | Not chosen. |
| Personal access tokens pasted in the assistant | Claude and ChatGPT custom connectors authenticate with OAuth (no static header in their UI). | Not possible. |

**Choice: league/oauth2-server (library) with opaque access tokens.** Our access token entity
returns its random identifier instead of a JWT (`toString()`), so no key pair is needed
(`NullCryptKey`). Tokens are looked up by SHA-256 hash. The library keeps the protocol checks
(client, redirect URI, PKCE verifier, single-use codes, refresh rotation); the plugin adds what
the MCP authorization spec asks and the library leaves optional: PKCE S256 for every client,
dynamic registration (RFC 7591), metadata (RFC 8414, RFC 9728), revocation (RFC 7009),
resource indicator check (RFC 8707).

### 1.3 Assistants (2026-10-12)

- Claude custom connectors: remote MCP URL, OAuth with dynamic registration or a client ID and
  secret; callback `https://claude.ai/api/mcp/auth_callback` (and `claude.com` later); Claude
  Code uses a loopback callback on a changing port. Sources: support.claude.com 11503834,
  claude.com/docs/connectors/building/authentication.
- ChatGPT developer mode and Business/Enterprise apps: remote MCP with OAuth; callback
  `https://chatgpt.com/connector/oauth/{callback_id}` per app, legacy
  `https://chatgpt.com/connector_platform_oauth_redirect`. Source:
  developers.openai.com/apps-sdk/build/auth.
- In-assistant payment is not available to EU merchants (memo §0, §2): the plugin hands over to
  the shop checkout.

## 2. Model (plugin tables only)

| Table | Content |
|---|---|
| `tmonier_b2b_mcp_client` | OAuth client: random identifier, name, redirect URIs, secret hash (confidential clients). |
| `tmonier_b2b_mcp_connection` | Consent of a customer to a client on a channel (scopes, last use, revocation). FK customer `ON DELETE CASCADE`. |
| `tmonier_b2b_mcp_token` | Access token, refresh token or authorization code: type, SHA-256 hash of the identifier, connection, scopes, expiry, revocation, hash of the parent access token (refresh). |
| `tmonier_b2b_mcp_idempotency` | Customer, tool, key, hash of the arguments, stored result (written with DBAL). |
| `tmonier_b2b_mcp_tool_call` | Audit log: connection (`SET NULL`), customer e-mail, client name, channel, tool, arguments (truncated JSON), outcome, message, duration, IP. |

`Connection` and `ToolCall` are Sylius resources (admin grids); the others are plain entities.
Audit entries and idempotency keys reference the customer with `ON DELETE CASCADE` (erasure);
free-text arguments (`message`) are logged as their length only.

## 3. Rules

- The OAuth "user" of league is the connection: the consent creates or reuses the active
  connection (customer, client, channel) and replaces its scopes with the approved ones. Tokens
  carry the scopes approved at the time; `finalizeScopes` keeps a refresh within the current
  scopes of the connection.
- Access tokens 1 hour, refresh tokens 30 days (rotated, the spent one revoked), codes 10
  minutes. A spent code or refresh token presented again revokes every token of the connection
  (OAuth 2.1 §4.1.2, refresh token reuse detection). Tokens revoked with their connection also
  expire at once, so presenting them later is not taken for a replay.
- A new consent that withdraws a scope revokes the tokens of the connection; the authenticator
  also intersects the scopes of the token with those of the connection.
- The MCP request needs `Authorization: Bearer`; the token must be usable (not expired, not
  revoked, connection active), the shop user enabled, the channel of the request the channel of
  the connection. Otherwise 401 (`invalid_token` when a token was sent).
- Redirect URIs: allow-list (exact, or one trailing path segment with `*`), checked at
  registration and again at each authorization (a client registered under an older list stops
  working). HTTPS only, no fragment, no whitespace; loopback only when enabled.
- Tools: `ToolRunner` resolves the connection, checks the scope, claims the idempotency key,
  runs the handler, stores the result, writes the audit entry. Refusals (`ToolRefusal`) become
  MCP tool errors; other exceptions are logged and returned as an internal error.
- Orders: own orders (customer and channel, state other than `cart`); company orders through
  `CompanyOrdersInterface` (company accounts plugin: member's company and `ORDER_VIEW_COMPANY`
  on the company for the list, on the order for one order). Unknown and forbidden orders get
  the same message.
- Cart: latest non-empty cart of the customer on the channel
  (`findLatestNotEmptyCartByChannelAndCustomer`, as `CustomerAndChannelBasedCartContext`), else
  a new cart (channel base currency and default locale) persisted by the adder.
- Adder: `QuickOrderCartLinesAdder` (quick order `BulkCartAdder`: same statuses, pack sizes,
  limits, quote cart lock) when the quick order bundle is enabled, else `CoreCartLinesAdder`
  (enabled, sold in the channel, channel pricing, stock with the cart quantity, Sylius quantity
  limit, quote cart lock, one order processing, one flush).
- Reorder reports the previous unit price (first item of the variant in the past order) and the
  current one (cart item after processing).
- Quote request: customer first and last name required (as the shop form), lines quotable as
  in the shop (enabled variant and product, sold and priced in the channel), at most the
  plugin `max_lines`, `from_cart` respected, e-mail rate limiter of the plugin, source cart
  remembered, `QuoteRequestManager::submit()`.
- Trade credit: `StatementBuilder::forCustomer()` (1.0 API); ageing computed by the tool from the
  due dates (not due, 1-30, 31-60, 61-90, over 90 days past due).

## 4. Decisions (product questions not settled by the brief, most conservative option)

1. Hide the guest cart and checkout tools of the official server by default (configurable): an
   authenticated B2B endpoint should not complete guest orders in the assistant.
2. The whole MCP endpoint requires OAuth (the official catalog tools too): Claude and ChatGPT only
   start the OAuth flow on a 401.
3. Redirect URI allow-list by default (Claude, ChatGPT); loopback (Claude Code) off by default.
4. Two scopes, both granted when the client asks for none; the customer approves them together.
5. Idempotency key required (not optional) on every cart-changing tool.
6. Reorder through the quick order rules when installed (same as its reorder button).
7. A company member may reorder a company order they can see (the items go into their own cart).
8. No security.yaml change: request listeners around the firewall instead of a firewall that
   the application would have to place before the "shop" one (Symfony does not allow a bundle
   to add firewalls to the application's list).
9. Purchase approval and audit log plugin bridges left for a later version.
10. OAuth endpoints rate limited per client (the assistants call from a few server addresses)
    and the registration per IP with a generous default.

## 5. Interfaces

- `B2bToolInterface::getDefinition()` (static MCP tool definition: name, JSON schema,
  description, annotations) + `__invoke()` with the arguments of the schema
  (`ToolDefinitionsTest` checks that they agree).
- `CartLinesAdderInterface` (`preview()`, `add()`), service alias `tmonier_b2b_mcp.cart.adder`.
- `CompanyOrdersInterface`, service `tmonier_b2b_mcp.order.company_orders`.
- Console: `tmonier:b2b-mcp:purge`, `tmonier:b2b-mcp:create-client`.

## 6. Tests

Functional tests drive the real endpoints in the test application (all companion plugins
enabled), each in a rolled-back transaction, with their own products, customers, orders,
companies, accounts, clients and connections: the full OAuth flow (registration, consent,
PKCE, refresh rotation and reuse, code replay, revocation, channel and user checks, rate
limits, statelessness), every tool through JSON-RPC over HTTP, the admin and account pages.
Integration tests cover the core adder, the idempotency store and the commands; unit tests the
configuration, policies, tool schemas and translations. Reproduced on Sylius 2.2.10 +
Symfony 7.4 (PHP 8.3, platform 8.2) and Sylius 2.3 + Symfony 8.1 (PHP 8.5).
