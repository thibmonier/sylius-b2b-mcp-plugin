<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusB2bMcpPlugin\Unit\OAuth;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Request;
use Tmonier\SyliusB2bMcpPlugin\OAuth\PsrBridge;

final class PsrBridgeTest extends TestCase
{
    public function testToPsr(): void
    {
        $bridge = new PsrBridge();
        $request = Request::create('/test', 'GET', ['q' => 'hello']);

        $psr = $bridge->toPsr($request);

        self::assertInstanceOf(ServerRequestInterface::class, $psr);
        self::assertSame('GET', $psr->getMethod());
    }

    public function testCreatePsrResponse(): void
    {
        $bridge = new PsrBridge();

        $response = $bridge->createPsrResponse();

        self::assertInstanceOf(ResponseInterface::class, $response);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testToSymfonySetsNoCacheHeaders(): void
    {
        $bridge = new PsrBridge();
        $psr = $bridge->createPsrResponse();

        $symfony = $bridge->toSymfony($psr);

        self::assertStringContainsString('no-store', (string) $symfony->headers->get('Cache-Control'));
        self::assertSame('no-cache', $symfony->headers->get('Pragma'));
    }
}
