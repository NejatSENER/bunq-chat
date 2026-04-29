<?php

declare(strict_types=1);

namespace Tests;

class UserTest extends AppTestCase
{
    public function test_create_user_returns_token(): void
    {
        $response = $this->request('POST', '/users', ['username' => 'alice']);

        $this->assertSame(201, $response->getStatusCode());
        $body = $this->json($response);
        $this->assertSame('alice', $body['username']);
        $this->assertNotEmpty($body['token']);
    }

    public function test_duplicate_username_returns_409(): void
    {
        $this->createUser('alice');
        $response = $this->request('POST', '/users', ['username' => 'alice']);

        $this->assertSame(409, $response->getStatusCode());
    }

    public function test_missing_username_returns_422(): void
    {
        $response = $this->request('POST', '/users', []);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_request_without_token_returns_401(): void
    {
        $response = $this->request('GET', '/groups');

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_request_with_invalid_token_returns_401(): void
    {
        $response = $this->request('GET', '/groups', token: 'bad-token');

        $this->assertSame(401, $response->getStatusCode());
    }
}
