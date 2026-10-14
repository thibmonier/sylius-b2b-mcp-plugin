<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Controller\OAuth;

use League\OAuth2\Server\Exception\OAuthServerException;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tmonier\SyliusB2bMcpPlugin\OAuth\AuthorizationRequestValidator;
use Tmonier\SyliusB2bMcpPlugin\OAuth\PsrBridge;
use Tmonier\SyliusB2bMcpPlugin\RateLimit\RateLimiter;

/**
 * Authorization endpoint advertised in the metadata: checks the request, then sends the browser
 * to the consent page of the shop account (localized, behind the shop login).
 */
final readonly class AuthorizeController
{
    public function __construct(
        private AuthorizationRequestValidator $validator,
        private PsrBridge $psrBridge,
        private RateLimiter $rateLimiter,
        private LocaleContextInterface $localeContext,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $limited = $this->rateLimiter->consumeOAuthRequest($request->getClientIp());
        if ($limited instanceof \Symfony\Component\HttpFoundation\Response) {
            return $limited;
        }

        try {
            $this->validator->validate($request);
        } catch (OAuthServerException $exception) {
            return $this->psrBridge->toSymfony($exception->generateHttpResponse($this->psrBridge->createPsrResponse()));
        }

        $consentUrl = $this->urlGenerator->generate('tmonier_b2b_mcp_shop_authorize', ['_locale' => $this->localeContext->getLocaleCode()]);

        return new RedirectResponse($consentUrl . '?' . $request->getQueryString());
    }
}
