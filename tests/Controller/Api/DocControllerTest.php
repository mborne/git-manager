<?php

namespace App\Tests\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Yaml\Yaml;

class DocControllerTest extends WebTestCase
{
    public function testIndex(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#swagger-ui');
    }

    public function testOpenApi(): void
    {
        $client = self::createClient();
        $client->request('GET', 'http://git-manager.example.org/api/openapi.yaml');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/yaml; charset=UTF-8');

        $openApi = Yaml::parse((string) $client->getResponse()->getContent());
        $this->assertIsArray($openApi);
        $this->assertSame('3.1.0', $openApi['openapi']);
        // the servers must match the URL exposing the specification
        $this->assertSame([
            [
                'url' => 'http://git-manager.example.org',
                'description' => 'The current instance',
            ],
        ], $openApi['servers']);
    }

    /**
     * Ensures that the servers match the public URL when the instance is exposed
     * behind a reverse proxy (see TRUSTED_PROXIES).
     */
    public function testOpenApiBehindProxy(): void
    {
        $client = self::createClient();
        $client->request('GET', 'http://127.0.0.1/api/openapi.yaml', [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'git-manager.example.org',
            'HTTP_X_FORWARDED_PREFIX' => '/git-manager',
        ]);

        $this->assertResponseIsSuccessful();

        $openApi = Yaml::parse((string) $client->getResponse()->getContent());
        $this->assertIsArray($openApi);
        $this->assertSame(
            'https://git-manager.example.org/git-manager',
            $openApi['servers'][0]['url']
        );
    }
}
