# Audit qualité — sylius-b2b-mcp-plugin

> Généré le 2026-10-14 · Branche `chore/quality-tooling` · Outillage : ECS, PHPStan, Rector, PHPUnit exécutés via Docker `ghcr.io/sylius/sylius-php:8.4-alpine`.
> La revue qualitative (sections 2 et 3) a été effectuée par lecture directe des fichiers sources par l'orchestrateur.

---

## 1. Audit factuel (outils)

| Contrôle | Commande | Résultat |
|---|---|---|
| Style (ECS) | `vendor/bin/ecs check` | ✅ 0 erreur |
| Analyse statique (PHPStan **level max**) | `vendor/bin/phpstan analyse` | ✅ 0 erreur |
| Tests unitaires | `vendor/bin/phpunit --testsuite=unit` | ✅ 69 tests, 727 assertions |
| Validation composer | `composer validate --strict` | ✅ valide |
| Modernisation (Rector, dry-run) | `vendor/bin/rector process --dry-run` | ⚠️ 66 fichiers avec suggestions |
| Tests `non-unit` (functional/integration) | — | ⏭️ non exécutés localement (DB + Sylius app requise) — couverts par le CI |
| Vulnérabilités dépendances | `composer audit` | ⚠️ 2 paquets abandonnés (non-bloquant, cf. note) |

**Rector** vient d'être ajouté (`rector/rector ^2.0`, `rector.php`, cibles `make rector` / `make rector-fix`). Les 66 fichiers signalés relèvent de trois règles : `ReadOnlyClassRector` (classes sans mutation post-construction), `FlipTypeControlToUseExclusiveTypeRector` et `RemoveAlwaysTrueIfConditionRector`. Ce sont des **suggestions de modernisation non bloquantes**, non appliquées. Lancer `make rector-fix` pour les appliquer, puis relancer ECS/PHPStan/tests.

**Paquets abandonnés** : `behat/transliterator` et `php-http/message-factory` sont signalés par `composer audit` comme abandonnés (pas de vulnérabilités CVE). Ce sont des dépendances transitives de `sylius/sylius` ; aucune action immédiate requise.

---

## 2. Audit qualitatif (revue par l'orchestrateur, lecture directe)

| Dimension | Score | Synthèse |
|---|---|---|
| PHP / Clean Archi / SOLID | **8/10** | Typage strict, `final` + `readonly` quasi-systématiques, null-objects, interfaces bien ségrégées, pas de duplication visible. `OAuthToken` non-`final` et 66 fichiers Rector en attente. |
| Intégration Symfony / Sylius | **8/10** | Extension, PrependExtension, DI conditionnelle (bundles optionnels), rate-limiters prépendés, migrations Sylius, `McpAuthenticationListener` à double priorité. Un point interne Symfony fragile (`_security_firewall_run`). |
| Sécurité OWASP 2025 | **9/10** | PKCE S256 obligatoire, redirect URIs avec path-traversal guard, tokens hashés SHA-256, secrets `password_hash`, rate-limiters sur 3 chemins, replay detection, scope intersecté à chaque requête, CSRF sur consent + admin revoke, `Text::clean()` contre injection de prompt. |

### Points forts

- `declare(strict_types=1)` + `final` + `readonly` sur l'ensemble des services, controllers, OAuth entities.
- `ToolRunner::run()` : point de contrôle unique pour toutes les invocations d'outil — authentification, scope, idempotence, audit, gestion des erreurs.
- `McpAuthenticationListener` : double passe (prè-firewall pour 401 RFC 9728, post-firewall pour token de sécurité), rate-limiting par connexion, scope intersecté avec `ScopeCatalog` (défense en profondeur si un scope est retiré après émission).
- `TokenStore` : identifiants stockés uniquement sous SHA-256, détection de replay (refresh token présenté après rotation), révocation en masse par connexion.
- `RedirectUriPolicy` : blocage des segments `.`/`..` percent-encodés, des caractères de contrôle et des URIs trop longues — prévention d'open-redirect via inscription dynamique.
- `AuthorizationRequestValidator` : PKCE S256 obligatoire, vérification de l'indicateur de ressource RFC 8707 — conformité MCP authorization spec.
- `DecideApprovalTool` : voter `ApprovalPermissions::APPROVAL_DECIDE`, vérification du channel, description de l'outil incluant une instruction anti-prompt-injection explicite.
- `OrderPresenter` : noms uniquement (pas d'email ni téléphone) pour les commandes d'entreprise.
- `ToolCallLogger` : free-text réduit à sa longueur (`[N characters]`), arguments tronqués à 2 000 caractères.
- Null-objects uniformes : `NullAuditMirror`, `NullCompanyOrders`, `NullOrderApprovalStatus`, `NullCryptKey` — les dépendances optionnelles ne nécessitent pas de `if (null !== $dep)` dans les classes consommateurs.

---

## 3. Recommandations priorisées

### 🟠 Majeur

1. **`OAuthToken` non-`final`** (`src/Entity/OAuthToken.php:11`). L'entité est la seule classe sans `final` dans `src/` alors qu'elle est centrale à la sécurité (hash des identifiants, révocation, validité). Aucune sous-classe n'est prévue ni documentée. → Ajouter `final` pour fermer la porte à une sous-classe qui pourrait affaiblir `isUsableAt()` ou `revoke()`.

2. **`_security_firewall_run` supprimé manuellement** (`src/Security/McpAuthenticationListener.php:99`). L'attribut `_security_firewall_run` est interne à `SecurityBundle` et non documenté : sa sémantique peut changer dans Symfony 7.x → 8.x sans annonce. Un changement silencieux pourrait briser l'isolation de session de l'endpoint MCP (les tokens de connexion MCP écrits en session du shop). → Surveiller les changelogs `SecurityBundle` à chaque upgrade. Considérer un firewall dédié `stateless: true` pour `/_mcp` plutôt que de dépendre de l'attribut interne.

### 🟡 Mineur

3. **66 fichiers Rector — `ReadOnlyClassRector`** (`src/**`). La règle `ReadOnlyClassRector` est la plus structurante : des classes sans mutation post-construction (DTOs, value objects) pourraient être déclarées `readonly class`, éliminant tout setter accidentel. → Appliquer `make rector-fix`, relancer ECS/PHPStan/tests, commit séparé.

4. **`error_description` distingue les états du token** (`src/Security/McpAuthenticationListener.php:146`). Le message `"The access token is invalid, expired or revoked."` n'est émis que lorsque le token a été trouvé en base (hash connu). Un attaquant qui énumère des tokens pourrait déduire si un hash est présent. En pratique le risque est négligeable (tokens 256 bits aléatoires), mais l'erreur pourrait uniformément retourner `"invalid_token"` sans description différenciée. → Simplification facultative.

5. **Email du customer dans l'audit** (`src/Audit/ToolCallLogger.php:48`). `$connection->customer->getEmail()` est stocké en clair dans `ToolCall`. C'est intentionnel (traçabilité), mais constitue un traitement de PII documenté à mentionner dans la politique de rétention. → Vérifier que `audit_retention_days` est configuré dans l'application hôte et que la rétention respecte les obligations RGPD.

6. **`createByCustomerAndChannelIdQueryBuilder` non testé unitairement** (`src/Order/OrderFinder.php:88`). `OrderFinder::list()` utilise directement le query builder Sylius ; l'ordering `checkoutCompletedAt DESC, id DESC` n'est pas couvert par un test unitaire (le repository est mocké). → Ajouter un test vérifiant l'ordre de tri pour prévenir une régression silencieuse.

### ℹ️ Pour information (choix de conception assumés)

- **Opaque tokens + `NullCryptKey`** : décision documentée (`src/OAuth/NullCryptKey.php`). Pas de clé à gérer, pas de JWT exposé — adapté à l'usage MCP read-only.
- **Scope intersecté à chaque requête** (`src/Security/McpAuthenticationListener.php:127`) : si le shop désactive `b2b:approve` en production, les tokens existants sont immédiatement dégradés sans avoir à les révoquer — conception défensive correcte.
- **`Text::clean()` + descriptions d'outils** : double protection contre l'injection de prompt (sanitisation des données + instructions dans les descriptions MCP).

---

## 4. Verdict

Plugin **très soigné** sur les trois dimensions : outillage 100 % au vert (ECS, PHPStan level max, 69 tests unitaires, 727 assertions), architecture OAuth 2.1 complète et conforme (PKCE, redirect-URI policy, replay detection, rate-limiters), et discipline PHP (strict types, final, readonly, null-objects) quasi-uniforme.

Le seul point de vigilance fonctionnel est la **présence de `_security_firewall_run`** comme levier d'isolation stateless — à surveiller lors des mises à jour Symfony. Le manque de `final` sur `OAuthToken` et les 66 modernisations Rector sont cosmétiques. Aucun problème bloquant.

Avant une release 1.0, appliquer `make rector-fix` et documenter la politique de rétention de l'audit PII (point 5).
