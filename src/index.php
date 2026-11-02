<?php
// 권리금 도구 — 홈 (카드 목록은 nav.php 의 sideNavItems() 한 곳에서 관리)
require_once __DIR__ . '/nav.php';

$idx_user = authUser();
$idx_lv = authLevel();
$idx_lvnames = authLevelNames();
$idx_gnames = authGradeNames();

$categories = array();
foreach (sideNavItems() as $nav_title => $nav_group) {
    if (count($nav_group['items']) === 0) {
        continue;
    }
    if (!empty($nav_group['admin']) && $idx_lv < 9) {
        continue; // 관리자 카테고리는 관리자 로그인 시에만
    }
    $tools = array();
    foreach ($nav_group['items'] as $it) {
        $tools[] = array(
            $it[0],
            isset($it[4]) && $it[4] !== null ? $it[4] : $it[1],
            isset($it[2]) ? $it[2] : '',
            isset($it[3]) ? $it[3] : '#94a3b8',
            authPageLevel($it[0]),
        );
    }
    $categories[] = array('title' => $nav_title, 'desc' => $nav_group['desc'], 'tools' => $tools);
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>권리금 도구</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Malgun Gothic', sans-serif; padding: 24px 20px 40px; max-width: 860px; margin: 0 auto; background-color: #f8fafc; }
        .hero { background: linear-gradient(135deg, #7c2d12, #b45309); color: #fff; border-radius: 12px;
                padding: 34px 30px; margin-bottom: 26px; box-shadow: 0 4px 16px rgba(180,83,9,0.25); }
        .hero h1 { margin: 0 0 8px; font-size: 1.6em; }
        .hero p { margin: 0; opacity: 0.9; font-size: 0.95em; line-height: 1.6; }
        .cat { margin-bottom: 26px; }
        .cat-head { display: flex; align-items: baseline; gap: 10px; margin-bottom: 12px; }
        .cat-head h2 { margin: 0; font-size: 1.1em; color: #1e293b; }
        .cat-head span { font-size: 0.82em; color: #94a3b8; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 12px; }
        .tool-card { display: block; background: #fff; border: 1px solid #e5e7eb; border-left: 4px solid var(--accent, #94a3b8);
                     border-radius: 10px; padding: 16px 16px 14px; text-decoration: none; transition: transform 0.12s, box-shadow 0.12s; }
        .tool-card:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,0.08); }
        .tool-card.locked { opacity: 0.6; }
        .tool-name { font-weight: bold; color: #1e293b; font-size: 0.98em; }
        .tool-name::after { content: ' →'; color: var(--accent, #94a3b8); font-weight: normal; }
        .tool-desc { margin-top: 6px; font-size: 0.82em; color: #64748b; line-height: 1.5; }
        .auth-bar { display: flex; align-items: center; gap: 10px; justify-content: flex-end; font-size: 0.85em; color: #334155; margin-bottom: 10px; }
        .ab-btn { color: #64748b; text-decoration: none; border: 1px solid #d1d5db; border-radius: 8px; padding: 5px 14px; font-weight: bold; background: #fff; }
        .ab-btn:hover { border-color: #94a3b8; color: #1e293b; }
        .ab-login { color: #b45309; border-color: #fde68a; }
        .foot { margin-top: 10px; font-size: 0.78em; color: #94a3b8; line-height: 1.6; }
    </style>
    <link rel="stylesheet" href="theme.css?v=2">
</head>
<body>

<div class="auth-bar">
    <?php if ($idx_user !== null) : ?>
        <span>👤 <b><?= htmlspecialchars($idx_user['name']) ?></b>님 · <?= htmlspecialchars(isset($idx_gnames[$idx_user['grade']]) ? $idx_gnames[$idx_user['grade']] : '') ?></span>
        <a class="ab-btn" href="login.php?logout=1">로그아웃</a>
    <?php else : ?>
        <span style="color:#94a3b8;">비회원으로 둘러보는 중</span>
        <a class="ab-btn ab-login" href="login.php">로그인</a>
    <?php endif; ?>
</div>

<div class="hero">
    <h1>💰 권리금 도구</h1>
    <p>실거래 회수배수 모델로 권리금 범위를 산정하고, 구성(바닥·영업·시설)을 분해하며, 받은 매물을 같은 기준으로 비교합니다.</p>
</div>

<?php foreach ($categories as $cat): ?>
<div class="cat">
    <div class="cat-head">
        <h2><?= htmlspecialchars($cat['title']) ?></h2>
        <?php if ($cat['desc'] !== ''): ?><span><?= htmlspecialchars($cat['desc']) ?></span><?php endif; ?>
    </div>
    <div class="grid">
        <?php foreach ($cat['tools'] as $t):
            $locked = $t[4] > $idx_lv;
            $href = $locked ? 'login.php?next=' . htmlspecialchars($t[0]) . '&need=' . $t[4] : htmlspecialchars($t[0]);
        ?>
        <a class="tool-card<?= $locked ? ' locked' : '' ?>" href="<?= $href ?>" style="--accent: <?= htmlspecialchars($t[3]) ?>;">
            <span class="tool-name"><?= htmlspecialchars($t[1]) ?><?= $locked ? ' 🔒' : '' ?></span>
            <span class="tool-desc" style="display:block;"><?= htmlspecialchars($t[2]) ?><?= $locked ? ' — ' . htmlspecialchars(isset($idx_lvnames[$t[4]]) ? $idx_lvnames[$t[4]] : '회원') . ' 전용' : '' ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<p class="foot">데일리팜 약국 툴에서 권리금 관련 기능만 분리한 프로젝트입니다. 권리금 계산은 AI 분석에 Gemini API 키가 필요합니다(.env).</p>

</body>
</html>
