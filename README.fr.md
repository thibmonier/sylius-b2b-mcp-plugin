# Sylius B2B MCP Plugin

> English: [README.md](README.md) <!-- vitrine:lang -->

[![Build](https://github.com/thibmonier/sylius-b2b-mcp-plugin/actions/workflows/build.yaml/badge.svg)](https://github.com/thibmonier/sylius-b2b-mcp-plugin/actions/workflows/build.yaml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

**Des outils B2B pour le serveur MCP officiel de Sylius : vos clients professionnels connectent
Claude ou ChatGPT à leur propre compte sur la boutique pour retrouver leurs commandes passées,
recommander, passer une commande rapide par référence (SKU), demander des devis et consulter leur
crédit client. Ils passent et paient toujours leurs commandes sur votre site.**

[sylius/mcp-server-plugin](https://github.com/Sylius/McpServerPlugin) expose une boutique Sylius
aux assistants IA via le Model Context Protocol, en mode invité : recherche dans le catalogue et
tunnel de commande invité. Un responsable des achats qui recommande chaque mois a besoin de son
compte à la place : ses commandes, ses prix, son entreprise, ses devis, son crédit.

Ce plugin ajoute cela au même serveur MCP (ce n'est pas un second serveur) : une connexion OAuth 2.1
avec le compte boutique du client, et des outils limités à ce client et au canal. L'assistant
prépare le panier ; le client le vérifie et commande sur la boutique, où le bouton de commande, les
informations légales et le paiement restent sous votre contrôle.

C'est un outil B2B pour les clients qui ajoutent eux-mêmes le connecteur. Il ne référence pas votre
boutique dans les annuaires de ChatGPT ou de Claude (cela demande leur validation), et il ne vend
pas « dans » ChatGPT, Gemini ou Claude : aucune commande et aucun paiement n'ont lieu dans
l'assistant.

## Fonctionnalités

| Besoin | Ce que fait le plugin |
|---|---|
| Se connecter avec le compte boutique | **Serveur d'autorisation OAuth 2.1** pour le point d'accès MCP : code d'autorisation avec **PKCE (S256 uniquement)**, jetons de rafraîchissement à rotation, enregistrement dynamique des clients (RFC 7591), métadonnées de découverte (RFC 8414, RFC 9728), révocation (RFC 7009). Le client se connecte sur la boutique et approuve l'assistant sur une page de consentement de son compte. Construit sur [league/oauth2-server](https://oauth2.thephpleague.com/). |
| Retrouver les commandes passées | `list_my_orders`, `get_order` : commandes passées du client sur le canal, avec lignes, SKU, prix, totaux et états. |
| Recommander | `reorder` : les articles d'une commande passée sont ajoutés au panier aux prix du jour ; chaque ligne indique *ajouté*, *ajusté*, ou la raison du refus (n'est plus vendu, en rupture de stock, règles de quantité), et si le prix a changé. |
| Commande rapide par SKU | `quick_order_add` (avec [tmonier/sylius-quick-order-plugin](https://github.com/thibmonier/sylius-quick-order-plugin)) : aperçu d'abord, puis ajout avec la même validation que la page de commande rapide (conditionnements, stock, partiel ou tout-ou-rien). |
| Demander un devis | `request_quote`, `list_my_quotes` (avec [tmonier/sylius-quote-request-plugin](https://github.com/thibmonier/sylius-quote-request-plugin)) : depuis le panier ou depuis des lignes de SKU, avec un message ; le marchand répond dans la boutique comme d'habitude. |
| Connaître le crédit restant | `get_trade_credit_statement` (avec [tmonier/sylius-trade-credit-plugin](https://github.com/thibmonier/sylius-trade-credit-plugin)) : plafond, engagé, disponible, échéances ouvertes, montant en retard et balance âgée. |
| Acheteurs d'une entreprise | Avec [tmonier/sylius-company-accounts-plugin](https://github.com/thibmonier/sylius-company-accounts-plugin) : `list_my_orders` avec `scope: company`, `get_order` et `reorder` sur les commandes de l'entreprise, selon ce que permettent les rôles de l'entreprise (ses voters décident). |
| Valider des bons de commande *(1.1)* | Avec [tmonier/sylius-purchase-approval-plugin](https://github.com/thibmonier/sylius-purchase-approval-plugin) : `list_pending_approvals` pour les validateurs (acheteur, total par rapport au plafond, ancienneté, droit ou non de valider) ; `get_order` affiche la validation d'une commande ; `decide_approval` **en option** (valider ou refuser avec un commentaire, mêmes règles que la page de la boutique) derrière sa propre autorisation `b2b:approve`. |
| Passer le relais au tunnel de commande | `get_checkout_link` : le panier et le lien pour le vérifier et passer commande sur le site web, connecté avec le même compte (Sylius charge le dernier panier du client). |
| Tenir l'assistant à l'écart du tunnel de commande | Les outils de panier et de tunnel de commande invité du serveur officiel (`create_order`, `complete_checkout`, ...) sont masqués par défaut ; ses outils de catalogue restent disponibles. |
| Une seule piste d'audit *(1.1)* | Avec [tmonier/sylius-audit-log-plugin](https://github.com/thibmonier/sylius-audit-log-plugin) : les appels d'outils (sans arguments), les consentements et les révocations apparaissent aussi dans son journal et dans l'historique du client. |
| Garder le contrôle | Une **connexion** par client, assistant et canal : listée dans *Mon compte > Assistants IA* (déconnexion) et dans l'administration (*Clients > Connexions des assistants IA*, révocation). **Journal d'audit** de chaque appel d'outil (client, assistant, arguments, résultat, durée) dans l'administration. Limitation de débit par connexion et sur les points d'accès OAuth. |

Les outils dont le plugin n'est pas installé ne sont tout simplement pas enregistrés. Traductions en
anglais, français, allemand, espagnol, italien, néerlandais et polonais ; MySQL, MariaDB et
PostgreSQL.

### Outils

| Outil | Portée | Modifie | Nécessite |
|---|---|---|---|
| `list_my_orders` | `b2b:read` | rien | - |
| `get_order` | `b2b:read` | rien | - |
| `reorder` | `b2b:cart` | le panier (clé d'idempotence) | - |
| `get_checkout_link` | `b2b:read` | rien | - |
| `quick_order_add` | `b2b:read` pour l'aperçu, `b2b:cart` pour l'ajout | le panier (clé d'idempotence) | plugin de commande rapide |
| `request_quote` | `b2b:cart` | envoie une demande de devis (clé d'idempotence) | plugin de demande de devis |
| `list_my_quotes` | `b2b:read` | rien | plugin de demande de devis |
| `get_trade_credit_statement` | `b2b:read` | rien | plugin de crédit client |
| `list_pending_approvals` | `b2b:read` | rien | plugin de validation des achats |
| `decide_approval` | `b2b:approve` | valide ou refuse (une commande refusée est annulée ; clé d'idempotence) | plugin de validation des achats + `approvals.allow_decisions: true` |

Aucun outil ne passe de commande, ne paie, ni ne modifie le compte, les adresses ou les moyens de
paiement.

### Décisions de validation depuis un assistant (en option)

`decide_approval` est **désactivé par défaut**. Avec `approvals.allow_decisions: true`, la boutique
propose l'autorisation `b2b:approve` ; sur la page de consentement, c'est une case à cocher
distincte, **jamais pré-cochée**, accompagnée d'un avertissement, et un client qui ne demande
aucune portée ne l'obtient jamais. L'outil applique les règles de la page de la boutique (validateur
de l'entreprise de la commande, pas d'auto-validation, plafond de validation, commande encore
nouvelle, une seule décision sous verrou) et enregistre le validateur comme auteur de la décision.

Le risque est l'**injection de prompt** : un texte lu par l'assistant (un nom de produit, une
commande, le message d'un devis, une page web) pourrait tenter de lui faire valider une commande.
Mesures d'atténuation : la description de l'outil demande au modèle de ne l'appeler que sur
demande explicite de l'utilisateur, après avoir affiché la commande ; la décision est limitée par
le plafond de validation du validateur ; chaque appel est audité ; et **gardez la confirmation des
outils activée dans votre assistant** (Claude et ChatGPT demandent confirmation avant d'exécuter un
outil qui n'est pas en lecture seule : `decide_approval` est annoté comme destructeur). Ne
l'activez que pour des validateurs qui comprennent ce risque.

Désactiver `allow_decisions` retire définitivement `b2b:approve` à toutes les connexions (au
préchauffage du cache du déploiement, sinon à la première requête MCP ou au premier
rafraîchissement de jeton, et dans `tmonier:b2b-mcp:purge`) ; les connexions conservent `b2b:read`
et `b2b:cart` et leur rafraîchissement continue de fonctionner. La réactiver ne rétablit jamais
l'autorisation : chaque validateur doit la cocher de nouveau lors d'un nouveau consentement.

## Captures d'écran

![Page de consentement affichée à l'acheteur lorsqu'un assistant se connecte](docs/images/shop-ai-consent.jpg)

![Assistants IA connectés au compte du client](docs/images/shop-ai-assistants.jpg)

![Connexions des assistants IA dans le back-office](docs/images/admin-ai-connections.jpg)

![Journal d'audit des appels d'outils effectués par les assistants](docs/images/admin-ai-tool-calls.jpg)

## Prérequis

| | Version |
|---|---|
| PHP | 8.2+ (8.3+ avec Sylius 2.3) |
| Sylius | 2.2.9+, 2.3 |
| Symfony | 7.4 (Sylius 2.2) ; 7.4, 8.x (Sylius 2.3) |
| sylius/mcp-server-plugin | 0.2 (installé comme dépendance, avec symfony/mcp-bundle 0.3) |
| league/oauth2-server | 9 (installé comme dépendance) |

**Symfony 6.4 n'est pas pris en charge** : symfony/mcp-bundle, sur lequel repose le plugin MCP
officiel, exige Symfony 7.3 ou supérieur. Le plugin officiel est en bêta (0.x) ; voir les
limitations connues.

La boutique doit être accessible en **HTTPS sur un nom d'hôte public** : Claude et ChatGPT s'y
connectent depuis leurs serveurs. Derrière un reverse proxy, configurez les
[proxys de confiance](https://symfony.com/doc/current/deployment/proxies.html) pour que les URL des
métadonnées OAuth utilisent `https` et l'hôte public.

## Installation

1. Installez le paquet :

    ```bash
    composer require tmonier/sylius-b2b-mcp-plugin
    ```

2. Enregistrez les bundles dans `config/bundles.php` (les deux premiers y sont peut-être déjà) :

    ```php
    Symfony\AI\McpBundle\McpBundle::class => ['all' => true],
    Sylius\McpServerPlugin\SyliusMcpServerPlugin::class => ['all' => true],
    Tmonier\SyliusB2bMcpPlugin\TmonierSyliusB2bMcpPlugin::class => ['all' => true],
    ```

3. Importez la configuration et les routes des deux plugins :

    ```yaml
    # config/packages/tmonier_sylius_b2b_mcp.yaml
    imports:
        - { resource: "@SyliusMcpServerPlugin/config/config.yaml" }
        - { resource: "@TmonierSyliusB2bMcpPlugin/config/config.yaml" }
    ```

    ```yaml
    # config/routes/tmonier_sylius_b2b_mcp.yaml
    sylius_mcp_server:
        resource: "@SyliusMcpServerPlugin/config/routes.yaml"

    tmonier_sylius_b2b_mcp:
        resource: "@TmonierSyliusB2bMcpPlugin/config/routes.yaml"
    ```

    Aucune modification de `security.yaml` n'est nécessaire (voir Fonctionnement).

4. Exécutez la migration (tables du plugin uniquement, les tables du cœur ne sont pas modifiées) :

    ```bash
    bin/console doctrine:migrations:migrate
    ```

5. Définissez une clé de chiffrement dédiée (elle protège les codes d'autorisation et les jetons de
   rafraîchissement ; par défaut, c'est le secret du noyau) :

    ```yaml
    # config/packages/tmonier_sylius_b2b_mcp.yaml
    tmonier_sylius_b2b_mcp:
        oauth:
            encryption_key: '%env(TMONIER_B2B_MCP_ENCRYPTION_KEY)%'
    ```

6. Planifiez le nettoyage une fois par jour : `bin/console tmonier:b2b-mcp:purge` (jetons
   expirés, clients enregistrés mais jamais connectés, anciennes clés d'idempotence, entrées du
   journal d'audit plus anciennes que la durée de conservation).

Le point d'accès MCP est le chemin HTTP de symfony/mcp-bundle : `https://your-shop.example.com/_mcp`
avec sylius/mcp-server-plugin 0.2.

## Connecter un assistant

Le client doit avoir un compte sur la boutique, sur le canal (nom d'hôte) de l'URL.

### Claude (connecteur personnalisé)

1. Dans Claude (web ou bureau) : *Settings > Connectors > Add custom connector*.
2. Donnez-lui le nom de la boutique, l'URL `https://your-shop.example.com/_mcp`, sans identifiant
   de client (Claude s'enregistre lui-même).
3. *Connect* : Claude ouvre la boutique ; le client se connecte et clique sur *Allow* sur la page
   de consentement. Avec les offres Team et Enterprise, un propriétaire doit parfois ajouter
   d'abord le connecteur.

Claude utilise le callback `https://claude.ai/api/mcp/auth_callback` (et `claude.com`), autorisé
par défaut. Claude Code se connecte depuis un callback local (`claude mcp add --transport http shop
https://your-shop.example.com/_mcp`) : activez `allow_loopback_redirect_uris` pour lui.

### ChatGPT (mode développeur, Business et Enterprise)

1. Activez le mode développeur (*Settings > Apps & Connectors > Advanced settings*), ou demandez à
   l'administrateur de l'espace de travail avec les offres Business et Enterprise.
2. *Create* un connecteur (application) avec l'URL `https://your-shop.example.com/_mcp` et
   l'authentification OAuth ; ChatGPT s'enregistre lui-même.
3. Connectez : ChatGPT ouvre la boutique pour la connexion et le consentement.

ChatGPT utilise `https://chatgpt.com/connector_platform_oauth_redirect` ou un callback propre à
chaque application `https://chatgpt.com/connector/oauth/{id}`, tous deux autorisés par défaut.

La disponibilité des connecteurs personnalisés dépend de l'offre et du pays de l'utilisateur, et
change souvent : consultez la documentation actuelle d'[Anthropic](https://support.claude.com/en/articles/11503834)
et d'[OpenAI](https://developers.openai.com/apps-sdk/build/auth). Référencer la boutique dans un
annuaire public (applications ChatGPT, annuaire de connecteurs de Claude) demande leur validation
et sort du périmètre de ce plugin.

### Identifiant de client fixe plutôt qu'enregistrement dynamique

Certaines installations préfèrent enregistrer l'assistant une seule fois :

```bash
bin/console tmonier:b2b-mcp:create-client "Claude" --redirect-uri=https://claude.ai/api/mcp/auth_callback [--confidential]
```

puis saisir l'identifiant de client (et le secret) dans les paramètres avancés du connecteur.
L'enregistrement dynamique peut alors être désactivé (`oauth.dynamic_client_registration: false`).

**Recommandé en production lorsque vous pouvez fournir un identifiant de client à vos clients :**
désactivez l'enregistrement dynamique et créez un client par assistant avec la commande. Avec
l'enregistrement dynamique activé (nécessaire pour le parcours « Add connector » en un clic),
n'importe qui peut enregistrer un client et choisir son nom ; la liste d'autorisation des URI de
redirection garde les codes sur les hôtes propres aux assistants, et la page de consentement
nomme l'assistant d'après cet hôte vérifié (Claude, ChatGPT), affiche l'hôte de façon visible et
signale les clients qui se sont enregistrés eux-mêmes (« registered itself on <date> under the name
... not verified by the shop »).

## Restreindre les comptes autorisés à connecter un assistant (point d'extension)

Par défaut, tout compte boutique actif peut connecter un assistant et appeler le point d'accès. Pour ajouter une règle de votre
application (inscription non validée, compte suspendu, contrat terminé...), implémentez
`Tmonier\SyliusB2bMcpPlugin\Account\AccountUsabilityCheckerInterface` ; elle est taguée
`tmonier_b2b_mcp.account_usability_checker` par autoconfiguration (une priorité de tag ordonne
plusieurs vérificateurs) :

```php
final class ApprovedRegistrationChecker implements AccountUsabilityCheckerInterface
{
    public function check(CustomerInterface $customer): AccountUsability
    {
        return $this->isApproved($customer) ? AccountUsability::usable() : AccountUsability::refused('registration_pending');
    }
}
```

- Les vérificateurs sont interrogés lors du consentement OAuth (page et décision : un compte refusé reçoit un 403, aucune connexion et
  aucun code d'autorisation) et à **chaque appel de `/_mcp`**, après la résolution du jeton : un jeton d'accès
  émis avant le refus reçoit un 403 `access_denied` avec `Cache-Control: private, no-store`, et le refus est
  écrit dans le journal d'audit (outil `account_usability`, résultat `refused`, avec votre raison).
- Le premier refus l'emporte ; les vérificateurs suivants ne sont pas interrogés. Un vérificateur qui lève une exception refuse (échec fermé) : l'erreur est
  journalisée (canal `mcp`), le client ne voit que `access_denied`.
- La raison (une courte chaîne lisible par une machine) n'est jamais envoyée au client. Un jeton inconnu, expiré ou révoqué
  reste un 401 et aucun vérificateur n'est appelé.
- La limitation de débit de la connexion s'applique avant les vérificateurs : un flot d'appels refusés est limité (429) et ne
  remplit pas le journal d'audit.
- Les vérificateurs s'exécutent à chaque appel : gardez-les peu coûteux et indépendants de la requête (le client est la seule
  entrée). Le point d'accès de jetons n'est pas vérifié : un rafraîchissement donne un jeton que le point d'accès refuse ensuite.

## Configuration

Facultative, avec les valeurs par défaut :

```yaml
tmonier_sylius_b2b_mcp:
    endpoint_path: ~                 # defaults to mcp.http.path (symfony/mcp-bundle), then /_mcp
    hidden_official_tools:           # tools of sylius/mcp-server-plugin not registered
        - create_order
        - add_item_to_order
        - fetch_order
        - update_order_address
        - list_shipping_methods
        - select_shipping_method
        - list_payment_methods
        - select_payment_method
        - complete_checkout
    oauth:
        encryption_key: '%kernel.secret%'
        access_token_ttl: PT1H
        refresh_token_ttl: P30D
        auth_code_ttl: PT10M
        dynamic_client_registration: true
        redirect_uris:               # exact, or "*" for one path segment
            - https://claude.ai/api/mcp/auth_callback
            - https://claude.com/api/mcp/auth_callback
            - https://chatgpt.com/connector_platform_oauth_redirect
            - https://chatgpt.com/connector/oauth/*
        allow_loopback_redirect_uris: false   # http://localhost:<port> (Claude Code, MCP Inspector)
    rate_limit:
        mcp_requests_per_minute: 120          # per connection
        oauth_requests_per_minute: 60         # token/revocation: per client; authorize: per IP
        client_registrations_per_hour: 60     # per IP
        cache_pool: cache.rate_limiter
    approvals:
        allow_decisions: false                # decide_approval and the b2b:approve permission (opt-in)
    audit_log_bridge: true                    # with tmonier/sylius-audit-log-plugin
    max_page_size: 50
    idempotency_ttl_hours: 24
    audit_retention_days: 90
```

`hidden_official_tools: []` conserve tout le tunnel de commande invité du serveur officiel (les
outils invités fonctionnent alors à côté des outils B2B, avec un panier invité distinct).

## Fonctionnement

- **Une extension, pas un second serveur.** Les outils sont enregistrés sur le serveur de
  symfony/mcp-bundle via son point d'extension de chargeur (`mcp.loader`), le mécanisme utilisé par
  sylius/mcp-server-plugin lui-même. Un décorateur du registre MCP écarte les outils officiels
  masqués.
- **Authentification.** Un écouteur de requête s'exécute avant le pare-feu sur la route MCP (décidé
  sur la route trouvée par le routeur, ce qui couvre les écritures encodées comme `/%5Fmcp`) : sans
  jeton bearer valide, la réponse est un 401 avec
  `WWW-Authenticate: Bearer resource_metadata=".../.well-known/oauth-protected-resource/_mcp"`,
  ce qui lance le parcours OAuth dans Claude et ChatGPT. Un cookie de session de la boutique ne
  suffit jamais. Après le pare-feu, l'utilisateur de la boutique devient le jeton de sécurité de la
  requête (les voters de Sylius et du plugin s'appliquent donc) et rien n'est stocké en session.
- **Jetons.** Les jetons d'accès sont des chaînes aléatoires opaques (80 caractères
  hexadécimaux) ; seul leur hachage SHA-256 est stocké, de sorte qu'une fuite de la base ne donne
  aucun jeton utilisable et qu'aucune paire de clés n'est à gérer. Un jeton appartient à une
  connexion (client, assistant, canal) : il ne fonctionne que sur le nom d'hôte de ce canal, pour
  un utilisateur de la boutique actif, tant que la connexion est active.
  Les jetons de rafraîchissement tournent ; présenter de nouveau un jeton de rafraîchissement ou un
  code d'autorisation déjà utilisé révoque tous les jetons de la connexion.
- **Consentement.** `/mcp-oauth/authorize` vérifie la requête (client, URI de redirection
  enregistrée toujours autorisée, PKCE S256, `resource` désignant ce point d'accès) et envoie le
  navigateur vers `/{locale}/account/ai-assistants/authorize`, derrière la connexion à la
  boutique. La page nomme l'assistant d'après l'hôte vers lequel il revient lorsqu'il est connu
  (sinon le nom déclaré, débarrassé des caractères de contrôle et bidi), affiche cet hôte de
  façon visible, signale les clients qui se sont enregistrés eux-mêmes, et liste les autorisations ;
  elle ne peut pas être intégrée dans un cadre.
- **Mêmes règles que la boutique.** Les paniers sont le dernier panier du client sur le canal (celui
  que la boutique affiche après la connexion). Les lignes passent par l'ajouteur de commande
  rapide lorsque ce plugin est installé (comme le fait son bouton de recommande), sinon par les
  règles du cœur (activé, vendu dans le canal, tarifé, en stock, limite de quantité), puis par le
  processeur de commande de Sylius.
  Les demandes de devis passent par la fabrique et le gestionnaire de demandes de devis (limitation de débit par
  e-mail comprise). L'accès aux entreprises passe par les voters du plugin de comptes d'entreprise.
- **Hygiène contre l'injection de prompt.** Les résultats des outils sont des données JSON avec du texte
  brut (balises et caractères de contrôle retirés, longueurs plafonnées) ; chaque description d'outil indique que son résultat est
  une donnée de la boutique, jamais une instruction. Aucun outil ne peut modifier les paramètres du compte, les adresses ou les moyens de paiement, et
  rien n'est commandé sans que le client agisse sur le site web.
- **Idempotence.** `reorder`, `quick_order_add` (confirmation) et `request_quote` exigent une
  `idempotency_key` : une nouvelle tentative avec la même clé et les mêmes arguments renvoie le résultat enregistré
  (`"replayed": true`), la même clé avec d'autres arguments est refusée.

## Données personnelles

- **Journal d'audit** (`tmonier_b2b_mcp_tool_call`) : client, e-mail, nom de l'assistant, adresse IP
  du serveur de l'assistant, outil, arguments (le texte libre écrit par le client, comme le
  message d'une demande de devis ou le commentaire d'une décision de validation, est remplacé par sa longueur), résultat. Conservé `audit_retention_days`
  (90 par défaut, `tmonier:b2b-mcp:purge`). Finalité : sécurité et support du service propre
  du marchand ; documentez-le dans votre politique de confidentialité.
- **Clés d'idempotence** : le résultat des appels qui modifient le panier, conservé `idempotency_ttl_hours` (24).
- **Connexions et jetons** : client, assistant, canal, portées, dates ; les jetons sont stockés
  hachés et purgés un jour après leur expiration.
- Supprimer un client supprime ses connexions, jetons, entrées d'audit et clés d'idempotence
  (clés étrangères `ON DELETE CASCADE`, donc aussi lorsque la ligne est supprimée hors de Doctrine).
- L'assistant (Anthropic, OpenAI) reçoit les données renvoyées par les outils en tant que responsable de
  traitement indépendant, selon ses propres conditions : informez-en vos clients dans votre politique de confidentialité.

## Données de démonstration

L'application de test charge les fixtures des plugins compagnons : `b2b@example.com` / `sylius`
(compte de crédit client avec des échéances ouvertes), l'entreprise Acme Industries
(`admin@acme-industries.example.com`, `buyer@...`, `approver@...` / `sylius`, avec des commandes) et
des demandes de devis.

## Limitations connues

- **sylius/mcp-server-plugin est en bêta.** Sa version 0.2 taguée exige symfony/mcp-bundle 0.3 (janvier
  2026) ; sa branche principale est passée à mcp-bundle 0.13 avec plusieurs serveurs (`/mcp/shop`), pas encore
  taguée. Ce plugin cible la version 0.2 taguée ; l'enregistrement des outils devra être mis à jour lorsque
  Sylius taguera la version suivante.
- Les outils invités officiels qui restent (catalogue) appellent l'API de la boutique en HTTP en tant qu'invité, comme
  auparavant ; ils ne sont ni limités en débit ni audités par ce plugin.
- Pas de paiement ni de finalisation de commande dans l'assistant (volontairement) : la commande dans l'assistant n'est
  pas disponible aujourd'hui pour les marchands de l'UE, et les acheteurs B2B commandent depuis le site web.
- Pas de MCP Apps (interface interactive dans l'assistant) : les résultats sont du texte et des données structurées.
- Le transport stdio (`bin/console mcp:server`) n'a pas de client : les outils B2B refusent.
- Un seul émetteur par hôte de boutique ; les portées sont grossières (`b2b:read`, `b2b:cart`). L'enregistrement dynamique est
  ouvert, protégé par la liste d'autorisation des URI de redirection et la page de consentement (qui affiche l'hôte vers lequel
  l'assistant revient).
- Les prix dans les résultats sont des chaînes décimales dans la devise de la commande, avec deux décimales comme la boutique.
- Le pont vers le journal d'audit enregistre les événements quel que soit le réglage `sources` du plugin de journal d'audit
  (les actions des clients n'y sont pas enregistrées par défaut) ; sa commande d'anonymisation les couvre,
  mais pas la suppression d'un client (pas de clé étrangère dans ce plugin).
- Testé localement avec MySQL 8.4 ; les jobs MariaDB et PostgreSQL de la matrice CI n'ont pas été exécutés localement.

## Développement

```bash
docker compose up -d mysql mailhog   # host ports in compose.override.yml (gitignored)
composer database-reset              # dev database with fixtures (APP_ENV=test for the test one)
vendor/bin/phpunit                   # every suite once (unit, integration, functional)
vendor/bin/phpstan analyse
vendor/bin/ecs check
```

Voir [SPEC.md](SPEC.md) pour la recherche et les décisions de conception.

<!-- vitrine:demo-b2b:start -->
## Démo B2B

Ce plugin est utilisé dans la [démo B2B Sylius](https://github.com/thibmonier/sylius-b2b-demo) : une boutique Sylius 2.3 avec des données d'exemple fictives, où les plugins B2B fonctionnent ensemble. Le code source, l'installation et les fixtures de la démo sont dans ce dépôt.

- Démo en ligne : [sylius-b2b.thibaut-monier.fr](https://sylius-b2b.thibaut-monier.fr)
- Auteur : [Thibaut Monier](https://www.linkedin.com/in/thibautmonier/)

La démo est un exemple, pas une configuration de production.
<!-- vitrine:demo-b2b:end -->

## Licence

MIT, voir [LICENSE](LICENSE).
