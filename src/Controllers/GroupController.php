<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Database;
use PDOException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GroupController
{
    public function __construct(private readonly Database $db) {}

    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $stmt = $this->db->getPdo()->query(
            'SELECT g.id, g.name, g.description, g.created_at,
                    u.username AS created_by,
                    COUNT(DISTINCT gm.user_id) AS member_count
             FROM groups g
             JOIN users u ON u.id = g.created_by
             LEFT JOIN group_members gm ON gm.group_id = g.id
             GROUP BY g.id
             ORDER BY g.created_at DESC'
        );

        return $this->json($response, $stmt->fetchAll());
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $body = $request->getParsedBody();
        $name = trim((string) ($body['name'] ?? ''));
        $description = trim((string) ($body['description'] ?? ''));

        if ($name === '') {
            return $this->json($response, ['error' => 'name is required'], 422);
        }

        try {
            $pdo = $this->db->getPdo();
            $pdo->beginTransaction();

            $pdo->prepare('INSERT INTO groups (name, description, created_by) VALUES (?, ?, ?)')->execute([$name, $description ?: null, $user['id']]);
            $groupId = (int) $pdo->lastInsertId();
            $stmt    = $pdo->prepare('SELECT id, name, description, created_by, created_at FROM groups WHERE id = ?');
            $stmt->execute([$groupId]);
            $group = $stmt->fetch();

            // Creator automatically joins the group
            $pdo->prepare('INSERT INTO group_members (group_id, user_id) VALUES (?, ?)')->execute([$group['id'], $user['id']]);
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                return $this->json($response, ['error' => 'group name already taken'], 409);
            }
            throw $e;
        }

        return $this->json($response, $group, 201);
    }

    public function join(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user    = $request->getAttribute('user');
        $groupId = (int) $args['id'];

        if (!$this->groupExists($groupId)) {
            return $this->json($response, ['error' => 'group not found'], 404);
        }

        try {
            $this->db->getPdo()
                ->prepare('INSERT INTO group_members (group_id, user_id) VALUES (?, ?)')
                ->execute([$groupId, $user['id']]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                return $this->json($response, ['error' => 'already a member of this group'], 409);
            }
            throw $e;
        }

        return $this->json($response, ['message' => 'joined group successfully']);
    }

    private function groupExists(int $id): bool
    {
        $stmt = $this->db->getPdo()->prepare('SELECT 1 FROM groups WHERE id = ?');
        $stmt->execute([$id]);
        return (bool) $stmt->fetch();
    }

    private function json(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
