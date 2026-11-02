<?php
require_once __DIR__ . '/config.php';

$status  = '';
$error   = '';
$version = '';

try {
    $pdo     = getConnection();
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    $status  = 'ok';
} catch (PDOException $e) {
    $status = 'fail';
    $error  = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Calculator - DB 연결 상태</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #1e1e2e;
    font-family: -apple-system, BlinkMacSystemFont, sans-serif;
    color: #fff;
  }
  .card {
    background: #2a2a3c;
    border-radius: 16px;
    padding: 32px 40px;
    box-shadow: 0 10px 40px rgba(0,0,0,.4);
    min-width: 340px;
  }
  h1 { font-size: 1.3rem; margin-bottom: 20px; }
  .row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #3b3b52; font-size: .95rem; }
  .row:last-child { border-bottom: none; }
  .label { color: #9a9ab0; }
  .ok   { color: #4ade80; font-weight: 600; }
  .fail { color: #f87171; font-weight: 600; }
  .error-box {
    margin-top: 16px;
    padding: 12px;
    background: #3b2a2a;
    border-radius: 8px;
    color: #fca5a5;
    font-size: .85rem;
    word-break: break-all;
  }
</style>
    <link rel="stylesheet" href="theme.css?v=2">
    <style>
      /* 다크 카드 페이지 — theme.css 공통 글자색(어두운 잉크)이 배경에 묻히는 것 방지 */
      body { background: #1e1e2e; color: #fff; }
      .card h1, .card .row span:not(.label):not(.ok):not(.fail) { color: #e2e8f0; }
    </style>
</head>
<body>
  <div class="card">
    <h1>🖥 서버 · DB 연결 상태</h1>
    <div class="row">
      <span class="label">PHP 버전</span>
      <span><?= htmlspecialchars(PHP_VERSION) ?></span>
    </div>
    <div class="row">
      <span class="label">DB 연결</span>
      <?php if ($status === 'ok'): ?>
        <span class="ok">연결됨 ✓</span>
      <?php else: ?>
        <span class="fail">실패 ✗</span>
      <?php endif; ?>
    </div>
    <?php if ($status === 'ok'): ?>
    <div class="row">
      <span class="label">MariaDB 버전</span>
      <span><?= htmlspecialchars($version) ?></span>
    </div>
    <div class="row">
      <span class="label">데이터베이스</span>
      <span><?= htmlspecialchars(DB_NAME) ?></span>
    </div>
    <?php else: ?>
    <div class="error-box"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <h1 style="margin-top:26px;">🤖 AI(Gemini) 상태</h1>
    <div class="row"><span class="label">종합</span><span id="ai-overall">확인 중…</span></div>
    <div class="row"><span class="label">기본 모델 (flash)</span><span id="ai-flash">-</span></div>
    <div class="row"><span class="label">예비 모델 (flash-lite)</span><span id="ai-lite">-</span></div>
    <div class="row"><span class="label">마지막 점검</span><span id="ai-age">-</span></div>
    <?php
    // 최근 24시간 폴백 비율 — api_usage 로그 기준 (기본 모델 장애 시 lite 비중이 올라감)
    $usage24 = array();
    try {
        if ($status === 'ok') {
            $usage24 = $pdo->query(
                "SELECT model, COUNT(*) c FROM api_usage
                  WHERE created_at >= NOW() - INTERVAL 24 HOUR GROUP BY model ORDER BY c DESC"
            )->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $usage24 = array();
    }
    if (count($usage24) > 0) :
        $tot24 = 0;
        foreach ($usage24 as $u) { $tot24 += (int) $u['c']; }
    ?>
    <div class="row"><span class="label">최근 24h 호출</span>
      <span><?php
        $parts = array();
        foreach ($usage24 as $u) {
            $short = str_replace(array('gemini-', '-latest'), '', $u['model']);
            $parts[] = htmlspecialchars($short) . ' ' . (int) $u['c'] . '회 (' . round((int) $u['c'] / $tot24 * 100) . '%)';
        }
        echo implode(' · ', $parts);
      ?></span></div>
    <?php endif; ?>
    <button id="ai-recheck" onclick="aiCheck(true)" style="margin-top:16px; width:100%; padding:10px; border:none; border-radius:8px;
        background:#4338ca; color:#fff; font-weight:bold; cursor:pointer; font-size:0.9rem;">지금 다시 점검</button>
  </div>

<script>
function aiRender(s) {
    var map = { ok: ['정상 ✓', 'ok'], degraded: ['예비 모델로 동작 중 ⚠', 'fail'], down: ['전부 응답 없음 ✗', 'fail'], unknown: ['미확인', 'label'] };
    var m = map[s.overall] || map.unknown;
    var ov = document.getElementById('ai-overall');
    ov.textContent = m[0];
    ov.className = s.overall === 'ok' ? 'ok' : (s.overall === 'unknown' ? 'label' : 'fail');
    function one(id, d) {
        var el = document.getElementById(id);
        if (!d) { el.textContent = '-'; return; }
        el.textContent = (d.state === 'ok' ? '정상' : d.state === 'slow' ? '지연' : '응답 없음') + ' · ' + d.ms + 'ms';
        el.className = d.state === 'ok' ? 'ok' : 'fail';
    }
    one('ai-flash', s.models && s.models.flash);
    one('ai-lite', s.models && s.models.lite);
    document.getElementById('ai-age').textContent = Math.round(s.age / 60) + '분 전';
}
function aiCheck(force) {
    var btn = document.getElementById('ai-recheck');
    if (force) { btn.disabled = true; btn.textContent = '점검 중… (최대 8초)'; }
    fetch('ai_status.php' + (force ? '?force=1' : '')).then(function (r) { return r.json(); }).then(function (j) {
        if (j && j.status) { aiRender(j.status); }
        btn.disabled = false;
        btn.textContent = '지금 다시 점검';
    }).catch(function () {
        document.getElementById('ai-overall').textContent = '상태 API 호출 실패';
        btn.disabled = false;
        btn.textContent = '지금 다시 점검';
    });
}
aiCheck(false);
</script>
</body>
</html>
