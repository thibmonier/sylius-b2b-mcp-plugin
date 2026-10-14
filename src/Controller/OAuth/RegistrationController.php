<?php

declare(strict_types=1);

namespace Tmonier\SyliusB2bMcpPlugin\Controller\OAuth;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tmonier\SyliusB2bMcpPlugin\OAuth\ClientRegistrar;
use Tmonier\SyliusB2bMcpPlugin\OAuth\ClientRegistrationException;
use Tmonier\SyliusB2bMcpPlugin\RateLimit\RateLimiter;

/** RFC 7591 dynamic client registration (used by Claude and ChatGPT when they add a connector). */
final readonly class RegistrationController
{
    public function __construct(
        private ClientRegistrar $registrar,
        private RateLimiter $rateLimiter,
        private bool $enabled,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->enabled) {
            throw new NotFoundHttpException();
        }
        $limited = $this->rateLimiter->consumeClientRegistration($request->getClientIp());
        if ($limited instanceof \Symfony\Component\HttpFoundation\Response) {
            return $limited;
        }

        try {
            $metadata = json_decode($request->getContent(), true, 8, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $metadata = null;
        }
        if (!\is_array($metadata)) {
            return $this->error('invalid_client_metadata', 'The body must be a JSON object.');
        }

        try {
            $client = $this->registrar->register($metadata);
        } catch (ClientRegistrationException $exception) {
            return $this->error($exception->error, $exception->getMessage());
        }

        return new JsonResponse($client, Response::HTTP_CREATED, ['Cache-Control' => 'no-store']);
    }

    private function error(string $error, string $description): JsonResponse
    {
        return new JsonResponse(['error' => $error, 'error_description' => $description], Response::HTTP_BAD_REQUEST, ['Cache-Control' => 'no-store']);
    }
}
