<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\Database;
use PDOException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UserController
{
    public function __construct(private readonly Database $db) {}

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();
        $username = trim((string) ($body['username'] ?? ''));

        if ($username === '') {
            return $this->json($response, ['error' => 'username is required'], 422);
        }

        if (strlen($username) > 50) {
            return $this->json($response, ['error' => 'username must be 50 characters or fewer'], 422);
        }

        $token = bin2hex(random_bytes(32));

        try {
            $pdo  = $this->db->getPdo();
            $pdo->prepare('INSERT INTO users (username, token) VALUES (?, ?)')->execute([$username, $token]);
            $stmt = $pdo->prepare('SELECT id, username, token, created_at FROM users WHERE id = ?');
            $stmt->execute([$pdo->lastInsertId()]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                return $this->json($response, ['error' => 'username already taken'], 409);
            }
            throw $e;
        }

        return $this->json($response, $user, 201);
    }

    private function json(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
