<?php

declare(strict_types=1);

namespace Tests;

class GroupTest extends AppTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $user        = $this->createUser('alice');
        $this->token = $user['token'];
    }

    public function test_list_groups_returns_empty_array_initially(): void
    {
        $response = $this->request('GET', '/groups', token: $this->token);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], $this->json($response));
    }

    public function test_create_group_returns_201(): void
    {
        $response = $this->request('POST', '/groups', ['name' => 'general'], $this->token);

        $this->assertSame(201, $response->getStatusCode());
        $body = $this->json($response);
        $this->assertSame('general', $body['name']);
    }

    public function test_creator_is_auto_member(): void
    {
        $this->request('POST', '/groups', ['name' => 'general'], $this->token);
        $listResponse = $this->request('GET', '/groups', token: $this->token);
        $groups = $this->json($listResponse);

        $this->assertSame(1, (int) $groups[0]['member_count']);
    }

    public function test_duplicate_group_name_returns_409(): void
    {
        $this->request('POST', '/groups', ['name' => 'general'], $this->token);
        $response = $this->request('POST', '/groups', ['name' => 'general'], $this->token);

        $this->assertSame(409, $response->getStatusCode());
    }

    public function test_missing_name_returns_422(): void
    {
        $response = $this->request('POST', '/groups', [], $this->token);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_join_group_increments_member_count(): void
    {
        $this->request('POST', '/groups', ['name' => 'general'], $this->token);

        $bob      = $this->createUser('bob');
        $groupId  = $this->json($this->request('GET', '/groups', token: $this->token))[0]['id'];

        $joinResponse = $this->request('POST', "/groups/{$groupId}/join", token: $bob['token']);
        $this->assertSame(200, $joinResponse->getStatusCode());

        $groups = $this->json($this->request('GET', '/groups', token: $this->token));
        $this->assertSame(2, (int) $groups[0]['member_count']);
    }

    public function test_joining_twice_returns_409(): void
    {
        $this->request('POST', '/groups', ['name' => 'general'], $this->token);
        $groups = $this->json($this->request('GET', '/groups', token: $this->token));

        $response = $this->request('POST', "/groups/{$groups[0]['id']}/join", token: $this->token);

        $this->assertSame(409, $response->getStatusCode());
    }

    public function test_join_nonexistent_group_returns_404(): void
    {
        $response = $this->request('POST', '/groups/999/join', token: $this->token);

        $this->assertSame(404, $response->getStatusCode());
    }
}
