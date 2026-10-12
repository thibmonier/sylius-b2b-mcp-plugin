# Changelog

## 1.0.0 (unreleased)

### Added

- B2B tools registered on the server of `sylius/mcp-server-plugin` 0.2: `list_my_orders`,
  `get_order`, `reorder`, `get_checkout_link`; `quick_order_add` (quick order plugin),
  `request_quote` and `list_my_quotes` (quote request plugin), `get_trade_credit_statement`
  (trade credit plugin); company orders with the company accounts plugin.
- OAuth 2.1 authorization server for shop customers (league/oauth2-server): authorization code
  with PKCE S256, rotating refresh tokens with reuse detection, dynamic client registration,
  RFC 8414 / RFC 9728 metadata, RFC 7009 revocation, redirect URI allow-list (Claude, ChatGPT),
  consent page in the shop account.
- Connections (customer, assistant, channel) with disconnection in the shop account and
  revocation in the admin; audit log of the tool calls; idempotency keys; rate limits.
- The guest cart and checkout tools of the official server are hidden by default.
- Commands `tmonier:b2b-mcp:purge` and `tmonier:b2b-mcp:create-client`.
- Translations in English, French, German, Spanish, Italian, Dutch and Polish.
