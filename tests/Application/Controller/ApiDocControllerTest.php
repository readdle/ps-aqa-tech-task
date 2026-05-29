<?php

declare(strict_types=1);

namespace Readdle\PlatformSolutions\Mailer\Tests\Application\Controller;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApiDocControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testApiDocIsSuccessful(): void
    {
        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/api/doc');
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/api/doc.json');
        $this->assertResponseIsSuccessful();
    }
}
