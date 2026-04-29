<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Database;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class MessageController
{
    public function __construct(private readonly Database $db) {}

    public function list(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $groupId = (int) $args['id'];

        if (!$this->groupExists($groupId)) {
            return $this->json($response, ['error' => 'group not found'], 404);
        }

        $params = $request->getQueryParams();
        $limit  = min((int) ($params['limit'] ?? 50), 200);
        $before = $params['before'] ?? null;

        $sql = 'SELECT m.id, m.content, m.created_at, u.id AS user_id, u.username
                FROM messages m
                JOIN users u ON u.id = m.user_id
                WHERE m.group_id = ?';

        $bindings = [$groupId];

        if ($before !== null) {
            $sql .= ' AND m.id < ?';
            $bindings[] = (int) $before;
        }

        $sql .= ' ORDER BY m.created_at DESC LIMIT ?';
        $bindings[] = $limit;

        $stmt = $this->db->getPdo()->prepare($sql);
        $stmt->execute($bindings);
        $messages = array_reverse($stmt->fetchAll());

        return $this->json($response, $messages);
    }

    public function send(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user    = $request->getAttribute('user');
        $groupId = (int) $args['id'];

        if (!$this->groupExists($groupId)) {
            return $this->json($response, ['error' => 'group not found'], 404);
        }

        if (!$this->isMember($groupId, $user['id'])) {
            return $this->json($response, ['error' => 'you must join the group before sending messages'], 403);
        }

        $body    = $request->getParsedBody();
        $content = trim((string) ($body['content'] ?? ''));

        if ($content === '') {
            return $this->json($response, ['error' => 'content is required'], 422);
        }

        $pdo = $this->db->getPdo();
        $pdo->prepare('INSERT INTO messages (group_id, user_id, content) VALUES (?, ?, ?)')->execute([$groupId, $user['id'], $content]);
        $stmt = $pdo->prepare('SELECT id, content, created_at FROM messages WHERE id = ?');
        $stmt->execute([$pdo->lastInsertId()]);
        $message = $stmt->fetch();
        $message['user_id']  = $user['id'];
        $message['username'] = $user['username'];

        return $this->json($response, $message, 201);
    }

    private function groupExists(int $id): bool
    {
        $stmt = $this->db->getPdo()->prepare('SELECT 1 FROM groups WHERE id = ?');
        $stmt->execute([$id]);
        return (bool) $stmt->fetch();
    }

    private function isMember(int $groupId, int $userId): bool
    {
        $stmt = $this->db->getPdo()->prepare('SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?');
        $stmt->execute([$groupId, $userId]);
        return (bool) $stmt->fetch();
    }

    private function json(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
