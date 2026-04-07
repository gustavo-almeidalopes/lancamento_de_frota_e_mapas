<?php
/**
 * /api/operators.php  — RESTful CRUD for the `operators` table.
 *
 * GET    /api/operators.php          → list all operators
 * POST   /api/operators.php          → create an operator
 * PUT    /api/operators.php?id={id}  → update an operator
 * DELETE /api/operators.php?id={id}  → delete an operator
 *
 * `name` is aliased to `nome` in SELECT queries (same convention as
 * drivers.php) so the React frontend receives the expected field name.
 */

require_once __DIR__ . '/config/db.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function respond(string $status, $data, string $message = '', int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode(['status' => $status, 'data' => $data, 'message' => $message]);
    exit;
}

try {
    $pdo    = getDbConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    $id     = isset($_GET['id']) ? (int) $_GET['id'] : null;
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];

    switch ($method) {

        case 'GET':
            $stmt = $pdo->query(
                "SELECT id, matricula, name AS nome, turno, status
                 FROM operators
                 ORDER BY name ASC"
            );
            respond('success', $stmt->fetchAll());
            break;

        case 'POST':
            $stmt = $pdo->prepare(
                "INSERT INTO operators (matricula, name, turno, status)
                 VALUES (:matricula, :name, :turno, :status)
                 RETURNING id, matricula, name AS nome, turno, status"
            );
            $stmt->execute([
                ':matricula' => strtoupper(trim($body['matricula'] ?? '')),
                ':name'      => strtoupper(trim($body['nome']      ?? '')),
                ':turno'     => $body['turno']                     ?? '1º TURNO',
                ':status'    => $body['status']                    ?? 'ATIVO',
            ]);
            respond('success', $stmt->fetch(), 'Operador cadastrado com sucesso.', 201);
            break;

        case 'PUT':
            if (!$id) respond('error', null, 'ID é obrigatório para atualização.', 400);
            $stmt = $pdo->prepare(
                "UPDATE operators
                 SET matricula = :matricula,
                     name      = :name,
                     turno     = :turno,
                     status    = :status
                 WHERE id = :id"
            );
            $stmt->execute([
                ':matricula' => strtoupper(trim($body['matricula'] ?? '')),
                ':name'      => strtoupper(trim($body['nome']      ?? '')),
                ':turno'     => $body['turno']                     ?? '1º TURNO',
                ':status'    => $body['status']                    ?? 'ATIVO',
                ':id'        => $id,
            ]);
            respond('success', null, 'Operador atualizado com sucesso.');
            break;

        case 'DELETE':
            if (!$id) respond('error', null, 'ID é obrigatório para remoção.', 400);
            $stmt = $pdo->prepare("DELETE FROM operators WHERE id = :id");
            $stmt->execute([':id' => $id]);
            respond('success', null, 'Operador removido com sucesso.');
            break;

        default:
            respond('error', null, 'Método não permitido.', 405);
    }

} catch (PDOException $e) {
    if (str_contains($e->getMessage(), '23505')) {
        respond('error', null, 'Matrícula já cadastrada.', 409);
    }
    respond('error', null, 'Erro de banco de dados: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    respond('error', null, 'Erro interno: ' . $e->getMessage(), 500);
}
