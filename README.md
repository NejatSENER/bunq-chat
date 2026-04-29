# bunq Chat App

A RESTful chat application backend built with PHP + Slim 4 + SQLite.

## Setup

```bash
brew install php composer   # macOS (if not already installed)
composer install
```

The repository includes an empty SQLite database file (`chat.db`) with the required tables already created, so the application can be run directly after `composer install`.

## Run

```bash
composer start
# Server runs at http://localhost:8080
```

## Test

```bash
composer test
```

## API

All endpoints except `POST /users` require the `X-User-Token` header.

### Users

| Method | Path     | Description            |
|--------|----------|------------------------|
| POST   | /users   | Create a user (public) |

**Request:** `{ "username": "alice" }`  
**Response:** `{ "id": 1, "username": "alice", "token": "...", "created_at": "..." }`

---

### Groups

| Method | Path                      | Description         |
|--------|---------------------------|---------------------|
| GET    | /groups                   | List all groups     |
| POST   | /groups                   | Create a group      |
| POST   | /groups/{id}/join         | Join a group        |

**Create group body:** `{ "name": "general", "description": "optional" }`

---

### Messages

| Method | Path                       | Description              |
|--------|----------------------------|--------------------------|
| GET    | /groups/{id}/messages      | List messages in a group |
| POST   | /groups/{id}/messages      | Send a message           |

**Query params for listing:** `?limit=50&before={message_id}` (cursor-based pagination)  
**Send body:** `{ "content": "Hello!" }`

---

## Example Flow

```bash
# 1. Create users
curl -X POST http://localhost:8080/users -H "Content-Type: application/json" \
     -d '{"username":"alice"}'
# → { "token": "abc123..." }

# 2. Create a group (use token from step 1)
curl -X POST http://localhost:8080/groups \
     -H "X-User-Token: abc123..." \
     -H "Content-Type: application/json" \
     -d '{"name":"general"}'

# 3. Send a message
curl -X POST http://localhost:8080/groups/1/messages \
     -H "X-User-Token: abc123..." \
     -H "Content-Type: application/json" \
     -d '{"content":"Hello!"}'

# 4. Read messages
curl http://localhost:8080/groups/1/messages \
     -H "X-User-Token: abc123..."
```
