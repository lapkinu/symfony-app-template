<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function __invoke(): Response
    {
        return new Response(
            'OK',
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain'],
        );
    }
}