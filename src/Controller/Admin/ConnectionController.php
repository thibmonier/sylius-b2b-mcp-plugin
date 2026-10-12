<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Controller\Admin;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Tmonier\SyliusB2bMcpPlugin\OAuth\ConnectionManager;
use Tmonier\SyliusB2bMcpPlugin\Repository\ConnectionRepository;

/** Disconnects an assistant from a customer account (every token of the connection is revoked). */
final class ConnectionController
{
    public function __construct(
        private readonly ConnectionRepository $connectionRepository,
        private readonly ConnectionManager $connectionManager,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function revokeAction(Request $request, int $id): Response
    {
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('tmonier_b2b_mcp_admin_revoke_' . $id, (string) $request->request->get('_csrf_token')))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }
        $connection = $this->connectionRepository->find($id);
        if (null === $connection) {
            throw new NotFoundHttpException();
        }
        $this->connectionManager->revoke($connection);

        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('success', 'tmonier_b2b_mcp.flash.connection_revoked');
        }

        return new RedirectResponse($this->urlGenerator->generate('tmonier_b2b_mcp_admin_connection_index'));
    }
}
