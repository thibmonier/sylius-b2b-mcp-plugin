<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Controller\Shop;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Tmonier\SyliusB2bMcpPlugin\OAuth\AuthorizationRequestValidator;
use Tmonier\SyliusB2bMcpPlugin\OAuth\ConnectionManager;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Entity\ClientEntity;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Entity\UserEntity;
use Tmonier\SyliusB2bMcpPlugin\OAuth\PsrBridge;
use Tmonier\SyliusB2bMcpPlugin\OAuth\Scopes;
use Twig\Environment;

/**
 * Consent page of the signed-in customer (shop account, behind the shop login): approving
 * creates or updates the connection of the assistant on this channel and redirects back to the
 * assistant with an authorization code.
 */
final class ConsentController
{
    public const CSRF_TOKEN_ID = 'tmonier_b2b_mcp_consent';

    public function __construct(
        private readonly AuthorizationRequestValidator $validator,
        private readonly AuthorizationServer $authorizationServer,
        private readonly ConnectionManager $connectionManager,
        private readonly PsrBridge $psrBridge,
        private readonly Security $security,
        private readonly ChannelContextInterface $channelContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly Environment $twig,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();
        $customer = $user instanceof ShopUserInterface ? $user->getCustomer() : null;
        if (!$customer instanceof CustomerInterface) {
            throw new AccessDeniedHttpException();
        }

        try {
            $authorizationRequest = $this->validator->validate($request);
        } catch (OAuthServerException $exception) {
            return $this->psrBridge->toSymfony($exception->generateHttpResponse($this->psrBridge->createPsrResponse()));
        }

        if ($request->isMethod('POST')) {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, (string) $request->request->get('_csrf_token')))) {
                throw new AccessDeniedHttpException('Invalid CSRF token.');
            }

            return $this->complete($authorizationRequest, $customer, 'approve' === $request->request->get('decision'));
        }

        $client = $authorizationRequest->getClient();

        return new Response($this->twig->render('@TmonierSyliusB2bMcpPlugin/shop/account/authorize.html.twig', [
            'client_name' => $client->getName(),
            'redirect_host' => $this->validator->getRedirectHost($authorizationRequest),
            'scopes' => $this->scopeIdentifiers($authorizationRequest),
            'channel' => $this->channelContext->getChannel(),
        ]), Response::HTTP_OK, ['X-Frame-Options' => 'DENY', 'Cache-Control' => 'no-store']);
    }

    private function complete(AuthorizationRequestInterface $authorizationRequest, CustomerInterface $customer, bool $approved): Response
    {
        if ($approved) {
            $client = $authorizationRequest->getClient();
            \assert($client instanceof ClientEntity);
            $connection = $this->connectionManager->approve(
                $customer,
                $client->getClient(),
                (string) $this->channelContext->getChannel()->getCode(),
                $this->scopeIdentifiers($authorizationRequest),
            );
            $authorizationRequest->setUser(new UserEntity((string) $connection->getId()));
        }
        $authorizationRequest->setAuthorizationApproved($approved);

        try {
            $response = $this->authorizationServer->completeAuthorizationRequest($authorizationRequest, $this->psrBridge->createPsrResponse());
        } catch (OAuthServerException $exception) {
            $response = $exception->generateHttpResponse($this->psrBridge->createPsrResponse());
        }

        return $this->psrBridge->toSymfony($response);
    }

    /** @return list<string> */
    private function scopeIdentifiers(AuthorizationRequestInterface $authorizationRequest): array
    {
        $scopes = [];
        foreach ($authorizationRequest->getScopes() as $scope) {
            $scopes[] = $scope->getIdentifier();
        }

        return Scopes::normalize($scopes);
    }
}
