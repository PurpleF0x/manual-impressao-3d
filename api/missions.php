<?php
/**
 * api/missions.php — API para Missões Diárias
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/missions.php';

header('Content-Type: application/json; charset=utf-8');

$input  = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;
$action = $input['action'] ?? $_GET['action'] ?? '';

// Verificar se o utilizador está logado
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Precisas de estar logado para aceder às missões.']);
    exit;
}

$user = getCurrentUser();
$userId = (int)$user['id'];

switch ($action) {
    case 'list':
        $missionsData = getUserMissions($userId);
        $definitions = getMissionsDefinitions();

        echo json_encode([
            'success'     => true,
            'missions'    => $missionsData,
            'definitions' => $definitions
        ]);
        break;

    case 'claim':
        $missionKey = $input['mission_key'] ?? $input['mission_id'] ?? '';
        if (empty($missionKey)) {
            echo json_encode(['success' => false, 'error' => 'ID da missão não fornecido.']);
            exit;
        }

        $result = claimMissionReward($userId, $missionKey);
        echo json_encode($result);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ação desconhecida.']);
        break;
}
