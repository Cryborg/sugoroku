<?php

require_once __DIR__ . '/../autoload.php';

use Trapped\Controllers\GameController;
use Trapped\Controllers\PlayerController;
use Trapped\Controllers\TurnController;

// Headers CORS et JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Récupérer la méthode et l'URI
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace('/api.php', '', $uri);

// Récupérer les données POST/PUT
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Router simple
try {
    $response = route($method, $uri, $input);
    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Router simple
 */
function route(string $method, string $uri, array $input): array
{
    // Parse l'URI
    $parts = array_filter(explode('/', $uri));
    $parts = array_values($parts);

    // Routes API

    // POST /game/create
    if ($method === 'POST' && $parts[0] === 'game' && $parts[1] === 'create') {
        $controller = new GameController();
        return $controller->create($input['players'] ?? []);
    }

    // POST /game/{id}/start
    if ($method === 'POST' && $parts[0] === 'game' && $parts[2] === 'start') {
        $controller = new GameController();
        return $controller->start((int) $parts[1]);
    }

    // GET /game/{id}/state
    if ($method === 'GET' && $parts[0] === 'game' && $parts[2] === 'state') {
        $controller = new GameController();
        return $controller->getState((int) $parts[1]);
    }

    // GET /game/{id}/check-end
    if ($method === 'GET' && $parts[0] === 'game' && $parts[2] === 'check-end') {
        $controller = new GameController();
        return $controller->checkEndConditions((int) $parts[1]);
    }

    // POST /door/{id}/open
    if ($method === 'POST' && $parts[0] === 'door' && $parts[2] === 'open') {
        $controller = new PlayerController();
        return $controller->openDoor($input['playerId'], (int) $parts[1]);
    }

    // POST /player/{id}/choose-door
    if ($method === 'POST' && $parts[0] === 'player' && $parts[2] === 'choose-door') {
        $controller = new PlayerController();
        return $controller->chooseDoor((int) $parts[1], $input['doorId']);
    }

    // POST /player/{id}/stay
    if ($method === 'POST' && $parts[0] === 'player' && $parts[2] === 'stay') {
        $controller = new PlayerController();
        return $controller->stayInRoom((int) $parts[1]);
    }

    // POST /player/{id}/free
    if ($method === 'POST' && $parts[0] === 'player' && $parts[2] === 'free') {
        $controller = new PlayerController();
        return $controller->freePlayer($input['liberatorId'], (int) $parts[1]);
    }

    // POST /player/{id}/give-up
    if ($method === 'POST' && $parts[0] === 'player' && $parts[2] === 'give-up') {
        $controller = new PlayerController();
        return $controller->giveUp((int) $parts[1]);
    }

    // GET /game/{id}/choices
    if ($method === 'GET' && $parts[0] === 'game' && $parts[2] === 'choices') {
        $controller = new PlayerController();
        return $controller->getAllChoices((int) $parts[1]);
    }

    // POST /turn/{gameId}/resolve
    if ($method === 'POST' && $parts[0] === 'turn' && $parts[2] === 'resolve') {
        $controller = new TurnController();
        return $controller->resolveTurn((int) $parts[1]);
    }

    // POST /turn/{gameId}/force-resolve
    if ($method === 'POST' && $parts[0] === 'turn' && $parts[2] === 'force-resolve') {
        $controller = new TurnController();
        return $controller->forceResolve((int) $parts[1]);
    }

    // GET /turn/{gameId}/check
    if ($method === 'GET' && $parts[0] === 'turn' && $parts[2] === 'check') {
        $controller = new TurnController();
        return $controller->checkAndResolve((int) $parts[1]);
    }

    // POST /turn/{gameId}/next
    if ($method === 'POST' && $parts[0] === 'turn' && $parts[2] === 'next') {
        $controller = new TurnController();
        return $controller->nextTurn((int) $parts[1]);
    }

    // Route non trouvée
    http_response_code(404);
    return [
        'success' => false,
        'error' => 'Route not found: ' . $method . ' ' . $uri
    ];
}
