<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthControllerTest extends WebTestCase
{
    public function testHealthEndpointIsHealthy(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame(
            'content-type',
            'text/plain; charset=UTF-8',
        );
        self::assertResponseFormatSame('txt');
        self::assertSame('OK', $client->getResponse()->getContent());
    }
}