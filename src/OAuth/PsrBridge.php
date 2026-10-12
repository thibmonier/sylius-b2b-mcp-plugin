<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\OAuth;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** HttpFoundation <-> PSR-7 for league/oauth2-server. */
final class PsrBridge
{
    private readonly PsrHttpFactory $psrFactory;

    private readonly HttpFoundationFactory $foundationFactory;

    private readonly Psr17Factory $psr17Factory;

    public function __construct()
    {
        $this->psr17Factory = new Psr17Factory();
        $this->psrFactory = new PsrHttpFactory($this->psr17Factory, $this->psr17Factory, $this->psr17Factory, $this->psr17Factory);
        $this->foundationFactory = new HttpFoundationFactory();
    }

    public function toPsr(Request $request): ServerRequestInterface
    {
        return $this->psrFactory->createRequest($request);
    }

    public function createPsrResponse(): ResponseInterface
    {
        return $this->psr17Factory->createResponse();
    }

    public function toSymfony(ResponseInterface $response): Response
    {
        $symfonyResponse = $this->foundationFactory->createResponse($response);
        $symfonyResponse->headers->set('Cache-Control', 'no-store');
        $symfonyResponse->headers->set('Pragma', 'no-cache');

        return $symfonyResponse;
    }
}
