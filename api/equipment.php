<?php
/**
 * /api/equipment.php  — RESTful CRUD for the `equipment_status` table.
 *
 * GET    /api/equipment.php          → list all equipment
 * POST   /api/equipment.php          → create an equipment record
 * PUT    /api/equipment.php?id={id}  → update an equipment record
 * DELETE /api/equipment.php?id={id}  → delete an equipment record
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
                "SELECT id, equipment_id, plate, battery_status, communication,
                        battery_percentage, last_communication
                 FROM equipment_status
                 ORDER BY equipment_id ASC"
            );
            respond('success', $stmt->fetchAll());
            break;

        case 'POST':
            $stmt = $pdo->prepare(
                "INSERT INTO equipment_status
                    (equipment_id, plate, battery_status, communication, battery_percentage, last_communication)
                 VALUES
                    (:equipment_id, :plate, :battery_status, :communication, :battery_percentage, :last_communication)
                 RETURNING id, equipment_id, plate, battery_status, communication, battery_percentage, last_communication"
            );
            $stmt->execute([
                ':equipment_id'        => strtoupper(trim($body['equipment_id']          ?? '')),
                ':plate'               => !empty($body['plate']) ? strtoupper(trim($body['plate'])) : null,
                ':battery_status'      => strtoupper($body['battery_status']             ?? 'DESCARREGADO'),
                ':communication'       => strtoupper($body['communication']              ?? 'OFF'),
                ':battery_percentage'  => (int) ($body['battery_percentage']             ?? 0),
                ':last_communication'  => !empty($body['last_communication']) ? $body['last_communication'] : null,
            ]);
            respond('success', $stmt->fetch(), 'Equipamento cadastrado com sucesso.', 201);
            break;

        case 'PUT':
            if (!$id) respond('error', null, 'ID é obrigatório para atualização.', 400);
            $stmt = $pdo->prepare(
                "UPDATE equipment_status
                 SET equipment_id       = :equipment_id,
                     plate              = :plate,
                     battery_status     = :battery_status,
                     communication      = :communication,
                     battery_percentage = :battery_percentage,
                     last_communication = :last_communication
                 WHERE id = :id"
            );
            $stmt->execute([
                ':equipment_id'        => strtoupper(trim($body['equipment_id']          ?? '')),
                ':plate'               => !empty($body['plate']) ? strtoupper(trim($body['plate'])) : null,
                ':battery_status'      => strtoupper($body['battery_status']             ?? 'DESCARREGADO'),
                ':communication'       => strtoupper($body['communication']              ?? 'OFF'),
                ':battery_percentage'  => (int) ($body['battery_percentage']             ?? 0),
                ':last_communication'  => !empty($body['last_communication']) ? $body['last_communication'] : null,
                ':id'                  => $id,
            ]);
            respond('success', null, 'Equipamento atualizado com sucesso.');
            break;

        case 'DELETE':
            if (!$id) respond('error', null, 'ID é obrigatório para remoção.', 400);
            $stmt = $pdo->prepare("DELETE FROM equipment_status WHERE id = :id");
            $stmt->execute([':id' => $id]);
            respond('success', null, 'Equipamento removido com sucesso.');
            break;

        default:
            respond('error', null, 'Método não permitido.', 405);
    }

} catch (PDOException $e) {
    respond('error', null, 'Erro de banco de dados: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    respond('error', null, 'Erro interno: ' . $e->getMessage(), 500);
}
