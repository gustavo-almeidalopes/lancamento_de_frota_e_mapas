<?php
/**
 * /api/dispatch.php  — RESTful CRUD for the `dispatch_orders` table.
 *
 * GET    /api/dispatch.php          → list all dispatch orders
 * POST   /api/dispatch.php          → create a batch of orders (body: { orders: [...] })
 * DELETE /api/dispatch.php?id={id}  → delete a single order
 *
 * Arrays (maps / operators) handling:
 *   - The React frontend sends `maps_json` and `operators_json` as plain
 *     JavaScript arrays inside the JSON request body.
 *   - PHP encodes them to JSON strings before inserting into the JSONB
 *     columns (PostgreSQL accepts a JSON string cast to JSONB).
 *   - On GET, the JSONB columns are decoded back to PHP arrays via
 *     json_decode() before sending the response, so the frontend
 *     receives them as native arrays — no client-side parsing needed.
 */

require_once __DIR__ . '/config/db.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
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

        // ── LIST all orders ───────────────────────────────
        case 'GET':
            $stmt = $pdo->query(
                "SELECT id, tracker_id, tracker_status, truck_plate, driver_name,
                        maps_json::text      AS maps_json_raw,
                        operators_json::text AS operators_json_raw
                 FROM dispatch_orders
                 ORDER BY id DESC"
            );
            $rows = $stmt->fetchAll();

            // Decode JSONB text columns into PHP arrays before responding
            foreach ($rows as &$row) {
                $row['maps_json']      = json_decode($row['maps_json_raw']      ?? '[]', true) ?? [];
                $row['operators_json'] = json_decode($row['operators_json_raw'] ?? '[]', true) ?? [];
                unset($row['maps_json_raw'], $row['operators_json_raw']);
            }
            unset($row);

            respond('success', $rows);
            break;

        // ── BATCH CREATE ──────────────────────────────────
        case 'POST':
            $orders = $body['orders'] ?? [];
            if (empty($orders) || !is_array($orders)) {
                respond('error', null, 'O campo "orders" deve ser um array não-vazio.', 400);
            }

            $stmt = $pdo->prepare(
                "INSERT INTO dispatch_orders
                    (tracker_id, tracker_status, truck_plate, driver_name, maps_json, operators_json)
                 VALUES
                    (:tracker_id, :tracker_status, :truck_plate, :driver_name, :maps_json::jsonb, :operators_json::jsonb)"
            );

            $pdo->beginTransaction();
            foreach ($orders as $order) {
                // Encode the arrays sent by the frontend into JSON strings
                // so PostgreSQL can store them in the JSONB columns
                $mapsJson      = json_encode(array_values((array) ($order['maps_json']      ?? [])));
                $operatorsJson = json_encode(array_values((array) ($order['operators_json'] ?? [])));

                $stmt->execute([
                    ':tracker_id'     => strtoupper(trim($order['tracker_id']     ?? '')),
                    ':tracker_status' => strtoupper($order['tracker_status']      ?? 'ATIVO'),
                    ':truck_plate'    => strtoupper(trim($order['truck_plate']    ?? '')),
                    ':driver_name'    => strtoupper(trim($order['driver_name']    ?? '')),
                    ':maps_json'      => $mapsJson,
                    ':operators_json' => $operatorsJson,
                ]);
            }
            $pdo->commit();

            respond('success', null, count($orders) . ' ordem(ns) salva(s) com sucesso.', 201);
            break;

        // ── DELETE ────────────────────────────────────────
        case 'DELETE':
            if (!$id) respond('error', null, 'ID é obrigatório para remoção.', 400);
            $stmt = $pdo->prepare("DELETE FROM dispatch_orders WHERE id = :id");
            $stmt->execute([':id' => $id]);
            respond('success', null, 'Ordem removida com sucesso.');
            break;

        default:
            respond('error', null, 'Método não permitido.', 405);
    }

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    respond('error', null, 'Erro de banco de dados: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    respond('error', null, 'Erro interno: ' . $e->getMessage(), 500);
}
