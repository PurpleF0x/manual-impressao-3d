<?php
/**
 * Daily Missions System — Manual de Impressão 3D
 */

require_once __DIR__ . '/functions.php';

/**
 * Define as missões disponíveis e as suas recompensas.
 */
function getMissionsDefinitions(): array {
    return [
        'daily_login' => [
            'title' => 'Primeiro Acesso',
            'desc'  => 'Entra no manual hoje para marcar a tua presença.',
            'goal'  => 1,
            'xp'    => 10,
            'gp'    => 5,
            'icon'  => '🌅'
        ],
        'post_comment' => [
            'title' => 'Participante Ativo',
            'desc'  => 'Deixa um comentário ou dúvida numa secção.',
            'goal'  => 1,
            'xp'    => 20,
            'gp'    => 10,
            'icon'  => '💬'
        ],
        'like_comment' => [
            'title' => 'Espalhar Apoio',
            'desc'  => 'Gosta de 2 comentários de outros utilizadores.',
            'goal'  => 2,
            'xp'    => 15,
            'gp'    => 5,
            'icon'  => '❤️'
        ],
        'read_sections' => [
            'title' => 'Explorador Curioso',
            'desc'  => 'Visita 3 capítulos diferentes do manual.',
            'goal'  => 3,
            'xp'    => 25,
            'gp'    => 15,
            'icon'  => '📖'
        ],
        'weekly_marathon' => [
            'title' => 'Maratona Semanal',
            'desc'  => 'Completa 10 missões diárias numa semana.',
            'goal'  => 10,
            'xp'    => 100,
            'gp'    => 50,
            'icon'  => '🏆',
            'type'  => 'weekly'
        ],
        'forum_replies' => [
            'title' => 'Guru do Fórum',
            'desc'  => 'Responde a 5 tópicos no fórum esta semana.',
            'goal'  => 5,
            'xp'    => 50,
            'gp'    => 30,
            'icon'  => '🧠',
            'type'  => 'weekly'
        ],
        'forum_upvotes' => [
            'title' => 'Apoiante da Comunidade',
            'desc'  => 'Dá 10 "likes" em posts ou respostas esta semana.',
            'goal'  => 10,
            'xp'    => 40,
            'gp'    => 20,
            'icon'  => '🙌',
            'type'  => 'weekly'
        ],
        'shop_enthusiast' => [
            'title' => 'Entusiasta da Loja',
            'desc'  => 'Compra 3 itens na loja para personalizar o teu perfil.',
            'goal'  => 3,
            'xp'    => 50,
            'gp'    => 25,
            'icon'  => '🛍️'
        ]
    ];
}

/**
 * Obtém ou inicializa os dados das missões diárias e semanais do utilizador.
 */
function getUserMissions(int $userId): array {
    $db = getDB();

    // Garantir que a coluna existe
    try {
        $db->query("SELECT daily_missions_data FROM user_profile_config LIMIT 1");
    } catch (Exception $e) {
        $db->exec("ALTER TABLE user_profile_config ADD COLUMN daily_missions_data JSON NULL");
    }

    $stmt = $db->prepare("SELECT daily_missions_data FROM user_profile_config WHERE user_id = ?");
    $stmt->execute([$userId]);
    $data = $stmt->fetchColumn();

    $missions = $data ? json_decode($data, true) : [];
    $today    = date('Y-m-d');
    $thisWeek = date('Y-W'); // Ano e número da semana

    // Inicialização se vazio
    if (empty($missions)) {
        $missions = [
            'date' => $today,
            'week' => $thisWeek,
            'list' => [],
            'weekly_list' => []
        ];
    }

    $definitions = getMissionsDefinitions();

    // Reset Diário
    if (!isset($missions['date']) || $missions['date'] !== $today) {
        $missions['date'] = $today;
        foreach ($definitions as $key => $def) {
            if (($def['type'] ?? 'daily') === 'daily') {
                $missions['list'][$key] = [
                    'current'   => 0,
                    'completed' => false,
                    'claimed'   => false
                ];
            }
        }
        // Missão de login automática
        $missions['list']['daily_login']['current'] = 1;
        $missions['list']['daily_login']['completed'] = true;
    }

    // Reset Semanal
    if (!isset($missions['week']) || $missions['week'] !== $thisWeek) {
        $missions['week'] = $thisWeek;
        foreach ($definitions as $key => $def) {
            if (($def['type'] ?? 'daily') === 'weekly') {
                $missions['weekly_list'][$key] = [
                    'current'   => 0,
                    'completed' => false,
                    'claimed'   => false
                ];
            }
        }
    }

    // Garantir que novas missões de definições existem no array do user
    foreach ($definitions as $key => $def) {
        $listType = (($def['type'] ?? 'daily') === 'weekly') ? 'weekly_list' : 'list';
        if (!isset($missions[$listType][$key])) {
            $missions[$listType][$key] = [
                'current'   => 0,
                'completed' => false,
                'claimed'   => false
            ];
        }
    }

    return $missions;
}

/**
 * Guarda os dados das missões na base de dados.
 */
function saveUserMissions(int $userId, array $missions): void {
    $db = getDB();
    $json = json_encode($missions);
    $db->prepare("UPDATE user_profile_config SET daily_missions_data = ? WHERE user_id = ?")
       ->execute([$json, $userId]);
}

/**
 * Incrementa o progresso de uma missão.
 */
function updateMissionProgress(int $userId, string $missionKey, int $increment = 1): void {
    $missions = getUserMissions($userId);
    $definitions = getMissionsDefinitions();

    if (!isset($definitions[$missionKey])) return;
    $def = $definitions[$missionKey];
    $listType = (($def['type'] ?? 'daily') === 'weekly') ? 'weekly_list' : 'list';

    if (!isset($missions[$listType][$missionKey])) {
        $missions[$listType][$missionKey] = [
            'current'   => 0,
            'completed' => false,
            'claimed'   => false
        ];
    }

    $m = &$missions[$listType][$missionKey];
    if ($m['completed']) return;

    $m['current'] += $increment;
    if ($m['current'] >= $def['goal']) {
        $m['current']   = $def['goal'];
        $m['completed'] = true;
    }

    saveUserMissions($userId, $missions);
}

/**
 * Reclama a recompensa de uma missão completada.
 */
function claimMissionReward(int $userId, string $missionKey): array {
    $missions = getUserMissions($userId);
    $definitions = getMissionsDefinitions();

    $def = $definitions[$missionKey] ?? null;
    if (!$def) return ['success' => false, 'error' => 'Missão inválida.'];

    $listType = (($def['type'] ?? 'daily') === 'weekly') ? 'weekly_list' : 'list';
    $m = &$missions[$listType][$missionKey];

    if (!$m['completed']) return ['success' => false, 'error' => 'Missão ainda não completada.'];
    if ($m['claimed']) return ['success' => false, 'error' => 'Recompensa já reclamada.'];

    $m['claimed'] = true;

    // Se for uma missão diária, contribui para a missão semanal "weekly_marathon"
    if (($def['type'] ?? 'daily') === 'daily' && $missionKey !== 'daily_login') {
        if (isset($missions['weekly_list']['weekly_marathon'])) {
            $w = &$missions['weekly_list']['weekly_marathon'];
            $wDef = $definitions['weekly_marathon'];
            if (!$w['completed']) {
                $w['current']++;
                if ($w['current'] >= $wDef['goal']) {
                    $w['current'] = $wDef['goal'];
                    $w['completed'] = true;
                }
            }
        }
    }

    saveUserMissions($userId, $missions);

    $reward = $definitions[$missionKey];
    addXP($userId, $reward['xp'], "Missão " . (isset($reward['type']) ? 'Semanal' : 'Diária') . ": " . $reward['title'], $reward['gp']);

    // Conquistas exclusivas (Badges)
    if ($missionKey === 'shop_enthusiast') {
        awardItem($userId, 'badge_shop_enthusiast');
    } elseif ($missionKey === 'weekly_marathon') {
        awardItem($userId, 'badge_weekly_master');
    }

    return [
        'success' => true,
        'xp'      => $reward['xp'],
        'gp'      => $reward['gp'],
        'title'   => $reward['title']
    ];
}
