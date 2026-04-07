<?php
/**
 * /api/drivers.php  — RESTful CRUD for the `drivers` table.
 *
 * GET    /api/drivers.php          → list all drivers
 * POST   /api/drivers.php          → create a driver
 * PUT    /api/drivers.php?id={id}  → update a driver
 * DELETE /api/drivers.php?id={id}  → delete a driver
 *
 * The `name` column is aliased to `nome` in all SELECT queries so
 * the React frontend can use the same field name it had during the
 * mock-data phase, without any client-side mapping.
 */

require_once __DIR__ . '/config/db.php';

// ── CORS + content-type headers ───────────────────────────
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Pre-flight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/**
 * Emit a standardised JSON response and terminate execution.
 */
function respond(string $status, $data, string $message = '', int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode(['status' => $status, 'data' => $data, 'message' => $message]);
    exit;
}

// ── Route ─────────────────────────────────────────────────
try {
    $pdo    = getDbConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    $id     = isset($_GET['id']) ? (int) $_GET['id'] : null;
    // Incoming JSON body (POST / PUT)
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];

    switch ($method) {

        // ── LIST ──────────────────────────────────────────
        case 'GET':
            $stmt = $pdo->query(
                "SELECT id, matricula, name AS nome, cnh, categoria, turno, setor, status
                 FROM drivers
                 ORDER BY name ASC"
            );
            respond('success', $stmt->fetchAll());
            break;

        // ── CREATE ────────────────────────────────────────
        case 'POST':
            $stmt = $pdo->prepare(
                "INSERT INTO drivers (matricula, name, cnh, categoria, turno, setor, status)
                 VALUES (:matricula, :name, :cnh, :categoria, :turno, :setor, :status)
                 RETURNING id, matricula, name AS nome, cnh, categoria, turno, setor, status"
            );
            $stmt->execute([
                ':matricula' => strtoupper(trim($body['matricula'] ?? '')),
                ':name'      => strtoupper(trim($body['nome']      ?? '')),
                ':cnh'       => trim($body['cnh']                   ?? ''),
                ':categoria' => $body['categoria']                  ?? 'B',
                ':turno'     => $body['turno']                      ?? '1º TURNO',
                ':setor'     => $body['setor']                      ?? 'OPERAÇÃO',
                ':status'    => $body['status']                     ?? 'ATIVO',
            ]);
            respond('success', $stmt->fetch(), 'Motorista cadastrado com sucesso.', 201);
            break;

        // ── UPDATE ────────────────────────────────────────
        case 'PUT':
            if (!$id) respond('error', null, 'ID é obrigatório para atualização.', 400);
            $stmt = $pdo->prepare(
                "UPDATE drivers
                 SET matricula = :matricula,
                     name      = :name,
                     cnh       = :cnh,
                     categoria = :categoria,
                     turno     = :turno,
                     setor     = :setor,
                     status    = :status
                 WHERE id = :id"
            );
            $stmt->execute([
                ':matricula' => strtoupper(trim($body['matricula'] ?? '')),
                ':name'      => strtoupper(trim($body['nome']      ?? '')),
                ':cnh'       => trim($body['cnh']                   ?? ''),
                ':categoria' => $body['categoria']                  ?? 'B',
                ':turno'     => $body['turno']                      ?? '1º TURNO',
                ':setor'     => $body['setor']                      ?? 'OPERAÇÃO',
                ':status'    => $body['status']                     ?? 'ATIVO',
                ':id'        => $id,
            ]);
            respond('success', null, 'Motorista atualizado com sucesso.');
            break;

        // ── DELETE ────────────────────────────────────────
        case 'DELETE':
            if (!$id) respond('error', null, 'ID é obrigatório para remoção.', 400);
            $stmt = $pdo->prepare("DELETE FROM drivers WHERE id = :id");
            $stmt->execute([':id' => $id]);
            respond('success', null, 'Motorista removido com sucesso.');
            break;

        default:
            respond('error', null, 'Método não permitido.', 405);
    }

} catch (PDOException $e) {
    // Duplicate unique constraint (matricula / cnh)
    if (str_contains($e->getMessage(), '23505')) {
        respond('error', null, 'Matrícula ou CNH já cadastrada.', 409);
    }
    respond('error', null, 'Erro de banco de dados: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    respond('error', null, 'Erro interno: ' . $e->getMessage(), 500);
}
