# Changelog

## Unreleased (1.2.0)

### Added

- **Account usability extension point**: `AccountUsabilityCheckerInterface` (tag
  `tmonier_b2b_mcp.account_usability_checker`, autoconfigured), `AccountUsability` and `AccountUsabilityChain`. A
  refused account cannot open the OAuth consent (403, no code, no connection) and gets a 403 `access_denied`
  (`Cache-Control: private, no-store`, audit log entry) on `/_mcp`, tokens issued before the refusal included. First
  refusal wins, a checker that throws refuses (fail closed, error logged, nothing leaked). No checker, no change.

## 1.1.0 (2026-10-12)

### Added

- **Purchase approvals** (with tmonier/sylius-purchase-approval-plugin ^1.0):
  `list_pending_approvals` (approvers: pending orders of the company, buyer, total against the
  limit, age, whether they may approve), the approval of an order in `get_order`, and
  `decide_approval` (approve / reject with a comment, idempotency key, shop rules), **opt-in**
  with `approvals.allow_decisions` and behind the new scope `b2b:approve`, offered on the consent
  page as a separate checkbox never pre-ticked, with a warning.
- **Audit log bridge** (with tmonier/sylius-audit-log-plugin ^1.0, `audit_log_bridge`): tool
  calls (without arguments), consents and revocations mirrored as entries owned by the customer.

### Changed

- Default scopes (client asking for none): `b2b:read b2b:cart`; `scopes_supported` and the
  `WWW-Authenticate` scope hint list `b2b:approve` only when decisions are enabled.

### Security

- The comment of an approval decision is audited as its length only, like the quote message.
- Disabling `approvals.allow_decisions` keeps the existing grants working for `b2b:read` and
  `b2b:cart` (their refresh does not fail) and removes `b2b:approve` from the connections for
  good: enabling it again needs a new consent.
- Tool outputs drop format characters (bidi overrides, zero-width) from customer-written text.

## 1.0.0 (2026-10-12)

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
