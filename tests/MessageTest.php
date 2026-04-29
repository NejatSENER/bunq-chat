<?php

declare(strict_types=1);

namespace Tests;

class MessageTest extends AppTestCase
{
    private string $token;
    private int $groupId;

    protected function setUp(): void
    {
        parent::setUp();
        $user        = $this->createUser('alice');
        $this->token = $user['token'];

        $group         = $this->json($this->request('POST', '/groups', ['name' => 'general'], $this->token));
        $this->groupId = $group['id'];
    }

    public function test_list_messages_returns_empty_array_initially(): void
    {
        $response = $this->request('GET', "/groups/{$this->groupId}/messages", token: $this->token);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], $this->json($response));
    }

    public function test_send_message_returns_201(): void
    {
        $response = $this->request('POST', "/groups/{$this->groupId}/messages", ['content' => 'Hello!'], $this->token);

        $this->assertSame(201, $response->getStatusCode());
        $body = $this->json($response);
        $this->assertSame('Hello!', $body['content']);
        $this->assertSame('alice', $body['username']);
    }

    public function test_messages_appear_in_chronological_order(): void
    {
        $this->request('POST', "/groups/{$this->groupId}/messages", ['content' => 'First'], $this->token);
        $this->request('POST', "/groups/{$this->groupId}/messages", ['content' => 'Second'], $this->token);

        $messages = $this->json($this->request('GET', "/groups/{$this->groupId}/messages", token: $this->token));

        $this->assertSame('First', $messages[0]['content']);
        $this->assertSame('Second', $messages[1]['content']);
    }

    public function test_non_member_cannot_send_message(): void
    {
        $bob      = $this->createUser('bob');
        $response = $this->request('POST', "/groups/{$this->groupId}/messages", ['content' => 'Hi'], $bob['token']);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_member_can_send_after_joining(): void
    {
        $bob = $this->createUser('bob');
        $this->request('POST', "/groups/{$this->groupId}/join", token: $bob['token']);

        $response = $this->request('POST', "/groups/{$this->groupId}/messages", ['content' => 'Hi from Bob'], $bob['token']);

        $this->assertSame(201, $response->getStatusCode());
    }

    public function test_empty_content_returns_422(): void
    {
        $response = $this->request('POST', "/groups/{$this->groupId}/messages", ['content' => ''], $this->token);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function test_message_on_nonexistent_group_returns_404(): void
    {
        $response = $this->request('GET', '/groups/999/messages', token: $this->token);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_pagination_with_limit_parameter(): void
    {
        foreach (range(1, 5) as $i) {
            $this->request('POST', "/groups/{$this->groupId}/messages", ['content' => "msg $i"], $this->token);
        }

        $messages = $this->json(
            $this->request('GET', "/groups/{$this->groupId}/messages?limit=3", token: $this->token)
        );

        $this->assertCount(3, $messages);
    }
}
