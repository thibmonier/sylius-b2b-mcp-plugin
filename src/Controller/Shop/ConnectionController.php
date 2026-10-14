<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Controller\Shop;

use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Symfony\Bundle\SecurityBundle\Security;
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
use Twig\Environment;

/** "AI assistants" page of the shop account: the connected assistants, and their disconnection. */
final readonly class ConnectionController
{
    public function __construct(
        private ConnectionRepository $connectionRepository,
        private ConnectionManager $connectionManager,
        private Security $security,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private UrlGeneratorInterface $urlGenerator,
        private Environment $twig,
    ) {
    }

    public function indexAction(): Response
    {
        return new Response($this->twig->render('@TmonierSyliusB2bMcpPlugin/shop/account/connections.html.twig', [
            'connections' => $this->connectionRepository->findActiveByCustomer($this->customer()),
        ]));
    }

    public function revokeAction(Request $request, int $id): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $customer = $this->customer();
        $connection = $this->connectionRepository->findOneById($id);
        if (!$connection instanceof \Tmonier\SyliusB2bMcpPlugin\Entity\ConnectionInterface || null === $customer->getId() || $connection->getCustomer()?->getId() !== $customer->getId()) {
            throw new NotFoundHttpException();
        }
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('tmonier_b2b_mcp_revoke_' . $id, (string) $request->request->get('_csrf_token')))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }
        $this->connectionManager->revoke($connection);

        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('success', 'tmonier_b2b_mcp.flash.connection_revoked');
        }

        return new RedirectResponse($this->urlGenerator->generate('tmonier_b2b_mcp_shop_connections'));
    }

    private function customer(): CustomerInterface
    {
        $user = $this->security->getUser();
        $customer = $user instanceof ShopUserInterface ? $user->getCustomer() : null;
        if (!$customer instanceof CustomerInterface) {
            throw new AccessDeniedHttpException();
        }

        return $customer;
    }
}
