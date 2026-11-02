<?php
// AI(Gemini) 상태 점검 API — 네비 상태 램프·관리자 시스템 상태에서 사용
// 5분 캐시: 실제 프로브(모델별 초경량 호출)는 최대 5분에 1회만 나가고,
// 그 사이 요청은 캐시를 돌려준다. ?force=1 은 30초 제한으로 즉시 재점검.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/public_api.php'; // fetchParallel

define('AI_STATUS_TTL', 300);       // 캐시 유효 (초)
define('AI_STATUS_FORCE_MIN', 30);  // 강제 재점검 최소 간격 (초)
define('AI_STATUS_SLOW_MS', 4000);  // 이보다 느리면 '지연'

header('Content-Type: application/json; charset=utf-8');

function aiStatusProbe()
{
    $models = array('flash' => 'gemini-flash-latest', 'lite' => 'gemini-flash-lite-latest');
    $body = json_encode(array(
        'contents' => array(array('role' => 'user', 'parts' => array(array('text' => 'ping')))),
        'generationConfig' => array('maxOutputTokens' => 5),
    ));
    // fetchParallel은 GET 전용이라 여기서는 POST 필요 → curl_multi 직접 구성
    $mh = curl_multi_init();
    $chs = array();
    $t0 = microtime(true);
    foreach ($models as $k => $m) {
        $ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/{$m}:generateContent");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'X-goog-api-key: ' . GEMINI_API_KEY));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8); // 프로브는 8초까지만 — hang이면 down 판정
        if (DEV_INSECURE_SSL) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }
        curl_multi_add_handle($mh, $ch);
        $chs[$k] = $ch;
    }
    $running = null;
    do {
        curl_multi_exec($mh, $running);
        if ($running) {
            curl_multi_select($mh, 1);
        }
    } while ($running > 0);

    $out = array();
    foreach ($chs as $k => $ch) {
        $res = curl_multi_getcontent($ch);
        $ms = (int) round(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000);
        $j = json_decode((string) $res, true);
        $hasText = isset($j['candidates'][0]);
        $state = !$hasText ? 'down' : ($ms > AI_STATUS_SLOW_MS ? 'slow' : 'ok');
        $out[$k] = array('state' => $state, 'ms' => $ms,
                         'http' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE));
        curl_multi_remove_handle($mh, $ch);
        if (PHP_VERSION_ID < 80000) { curl_close($ch); } // PHP 8.0+ 불필요(8.5부터 deprecated), 7.x 호환 유지
    }
    curl_multi_close($mh);
    return $out;
}

/** 종합 판정: ok(정상) / degraded(1차 장애 — 예비로 동작) / down(전부 장애) */
function aiStatusOverall($models)
{
    $flash = isset($models['flash']['state']) ? $models['flash']['state'] : 'down';
    $lite = isset($models['lite']['state']) ? $models['lite']['state'] : 'down';
    if ($flash === 'ok' && ($lite === 'ok' || $lite === 'slow')) {
        return 'ok';
    }
    if ($lite === 'ok' || $lite === 'slow' || $flash === 'slow') {
        return 'degraded';
    }
    return 'down';
}

$force = isset($_GET['force']) && $_GET['force'] === '1';
$now = time();
$payload = null;

try {
    $pdo = getConnection();
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_status_cache (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        payload TEXT NOT NULL,
        checked_at INT UNSIGNED NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $row = $pdo->query("SELECT payload, checked_at FROM ai_status_cache WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    $age = $row ? $now - (int) $row['checked_at'] : PHP_INT_MAX;

    $needProbe = $row === false || $age >= AI_STATUS_TTL || ($force && $age >= AI_STATUS_FORCE_MIN);
    if ($needProbe && GEMINI_API_KEY !== '') {
        $models = aiStatusProbe();
        $payload = array('overall' => aiStatusOverall($models), 'models' => $models, 'checked_at' => $now);
        $pdo->prepare("INSERT INTO ai_status_cache (id, payload, checked_at) VALUES (1, ?, ?)
                       ON DUPLICATE KEY UPDATE payload = VALUES(payload), checked_at = VALUES(checked_at)")
            ->execute(array(json_encode($payload, JSON_UNESCAPED_UNICODE), $now));
    } elseif ($row) {
        $payload = json_decode($row['payload'], true);
    }
} catch (Exception $e) {
    // DB 없이도 동작: 캐시 없이 직접 프로브 (빈도 제한이 없어지지만 fail-safe 우선)
    if ($payload === null && GEMINI_API_KEY !== '') {
        $models = aiStatusProbe();
        $payload = array('overall' => aiStatusOverall($models), 'models' => $models, 'checked_at' => $now);
    }
}

if ($payload === null) {
    $payload = array('overall' => 'unknown', 'models' => array(), 'checked_at' => $now);
}
$payload['age'] = $now - (int) $payload['checked_at'];
echo json_encode(array('ok' => true, 'status' => $payload), JSON_UNESCAPED_UNICODE);
