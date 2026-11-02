<?php
// 가상 로그인 (데모) — 입장 조건 시연용: 비회원/회원/약사/개국약사/최고 관리자
// 추후 데일리팜 본사이트 회원 연동 시 이 페이지가 실제 로그인(또는 SSO)으로 교체될 자리
require_once __DIR__ . '/auth.php';

$next = isset($_GET['next']) ? basename($_GET['next']) : 'index.php';
if (!preg_match('/^[a-z0-9_]+\.php$/', $next)) {
    $next = 'index.php';
}
$need = isset($_GET['need']) ? (int) $_GET['need'] : 0;
$err = '';

// 로그아웃
if (isset($_GET['logout'])) {
    unset($_SESSION['site_user'], $_SESSION['dash_auth']);
    header('Location: index.php');
    exit;
}

// 로그인 처리 — 테스트 버전: 관리자 포함 전 계정 비밀번호 없이 로그인
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_id'])) {
    $lid = preg_replace('/[^a-z0-9_]/', '', $_POST['login_id']);
    try {
        $pdo = getConnection();
        authEnsureMembers($pdo);
        $st = $pdo->prepare('SELECT id, login_id, name, grade, upgrade_status FROM site_members WHERE login_id = ?');
        $st->execute(array($lid));
        $m = $st->fetch(PDO::FETCH_ASSOC);
        if (!$m) {
            $err = '계정을 찾을 수 없습니다.';
        } else {
            $_SESSION['site_user'] = $m;
            if ($m['grade'] === 'admin') {
                $_SESSION['dash_auth'] = true; // 관리자 페이지도 함께 열림
            } else {
                unset($_SESSION['dash_auth']);
            }
            header('Location: ' . ($next !== 'login.php' ? $next : 'index.php'));
            exit;
        }
    } catch (Exception $e) {
        $err = 'DB 연결에 실패했습니다.';
    }
}

// 개국 준비 신청 (약사 회원 → 관리자 승인 시 개국약사 등급)
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_upgrade') {
    $u = authUser();
    if ($u !== null && $u['grade'] === 'pharmacist') {
        try {
            $pdo = getConnection();
            $st = $pdo->prepare("UPDATE site_members SET upgrade_status = 'pending', requested_at = NOW() WHERE id = ? AND grade = 'pharmacist'");
            $st->execute(array((int) $u['id']));
            $_SESSION['site_user']['upgrade_status'] = 'pending';
            $flash = '개국 준비 신청이 접수되었습니다. 관리자 승인 후 개국 준비 약사 메뉴(권리금·매물 비교 등)가 열립니다.';
        } catch (Exception $e) {
            $err = '신청 처리에 실패했습니다.';
        }
    }
}

// 데모 계정 목록
$accounts = array();
try {
    $pdo = getConnection();
    authEnsureMembers($pdo);
    $accounts = $pdo->query("SELECT login_id, name, grade FROM site_members WHERE is_demo = 1 ORDER BY FIELD(grade, 'member', 'pharmacist', 'owner', 'admin')")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // DB 미연결 — 안내만
}

$GRADE_NAMES = authGradeNames();
$LEVEL_NAMES = authLevelNames();
$GRADE_DESC = array(
    'member' => '조건 ①만 충족 — 정보성 도구(입지·설비·손익·세금 등)까지 이용',
    'pharmacist' => '조건 ①② 충족 — OTC 판매가·구직 급여·운영 도구까지 이용',
    'prep' => '개국 준비 승인 — 권리금·매물 비교·채용 분석 등 개국 준비 도구까지 이용',
    'owner' => '조건 ①②③ 충족 — 판매가 진단·재고 회전율 포함 전체 이용 (운영 중)',
    'admin' => '모든 메뉴 + 관리자 콘솔 (테스트 버전 — 비밀번호 없이 로그인)',
);
$user = authUser();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>로그인 — 데일리팜 약국 툴</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Malgun Gothic', -apple-system, sans-serif; margin: 0; background: #f3f5f9; color: #1e293b; line-height: 1.6; }
        .wrap { max-width: 480px; margin: 0 auto; padding: 40px 16px 60px; }
        .brand { text-align: center; font-size: 0.85em; color: #94a3b8; font-weight: bold; margin-bottom: 4px; }
        h1 { text-align: center; font-size: 1.35em; margin: 0 0 6px; }
        .lead { text-align: center; color: #64748b; font-size: 0.88em; margin: 0 0 20px; }
        .notice { background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; padding: 10px 14px; font-size: 0.85em; color: #92400e; margin-bottom: 14px; }
        .err { background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 10px 14px; font-size: 0.85em; color: #b91c1c; margin-bottom: 14px; }
        .acct { display: block; width: 100%; text-align: left; background: #fff; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; margin-bottom: 10px; cursor: pointer; font-family: inherit; font-size: 1em; }
        .acct:hover { border-color: #1967d2; box-shadow: 0 2px 10px rgba(25,103,210,0.1); }
        .a-name { font-weight: bold; }
        .a-grade { font-size: 0.78em; font-weight: bold; color: #1967d2; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 999px; padding: 1px 9px; margin-left: 6px; }
        .a-grade.g-admin { color: #9a3412; background: #fff7ed; border-color: #fed7aa; }
        .a-desc { font-size: 0.79em; color: #94a3b8; margin-top: 3px; line-height: 1.5; }
        .upg { background: #fff; border: 1.5px solid #bae6fd; border-radius: 12px; padding: 14px 16px; margin-bottom: 14px; font-size: 0.88em; }
        .upg p { margin: 6px 0 10px; color: #64748b; line-height: 1.6; }
        .upg-btn { width: 100%; padding: 11px; border: none; border-radius: 8px; background: #0369a1; color: #fff; font-weight: bold; font-size: 0.95em; cursor: pointer; }
        .upg-btn:hover { background: #075985; }
        .guest { display: block; text-align: center; margin-top: 16px; font-size: 0.85em; color: #64748b; }
        .cur { text-align: center; font-size: 0.85em; color: #047857; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 10px; padding: 9px 12px; margin-bottom: 14px; }
        .demo-note { font-size: 0.76em; color: #9ca3af; text-align: center; margin-top: 18px; line-height: 1.6; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand">💊 데일리팜 약국 툴</div>
    <h1>로그인</h1>
    <p class="lead">가상 로그인(데모) — 계정을 선택하면 해당 등급으로 로그인됩니다.</p>

    <?php if ($user !== null) : ?>
    <div class="cur">현재 <b><?= htmlspecialchars($user['name']) ?></b>님 (<?= htmlspecialchars($GRADE_NAMES[$user['grade']]) ?>) 로그인 중 —
        <a href="login.php?logout=1" style="color:#047857;">로그아웃</a></div>
    <?php endif; ?>

    <?php if ($need > 0) : ?>
    <div class="notice">🔒 <b><?= htmlspecialchars(isset($LEVEL_NAMES[$need]) ? $LEVEL_NAMES[$need] : '회원') ?> 전용 메뉴</b>입니다.
        아래에서 해당 등급 이상의 계정으로 로그인하세요.</div>
    <?php endif; ?>
    <?php if ($flash !== '') : ?><div class="cur"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <?php if ($err !== '') : ?><div class="err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <?php if ($user !== null && $user['grade'] === 'pharmacist') :
        $ustat = isset($user['upgrade_status']) ? $user['upgrade_status'] : ''; ?>
    <div class="upg">
        <b>🏪 개국 준비 약사 신청</b>
        <?php if ($ustat === 'pending') : ?>
            <p>승인 대기 중입니다 — 관리자 승인이 완료되면 <b>개국 준비 약사</b> 메뉴(권리금·매물 비교·채용 분석 등)가 자동으로 열립니다.</p>
        <?php elseif ($ustat === 'rejected') : ?>
            <p>이전 신청이 반려되었습니다. 다시 신청할 수 있습니다.</p>
            <form method="post" action="login.php?next=<?= htmlspecialchars($next) ?>&need=<?= $need ?>">
                <input type="hidden" name="action" value="request_upgrade">
                <button type="submit" class="upg-btn">다시 신청하기</button>
            </form>
        <?php else : ?>
            <p>개국을 준비 중이신가요? 신청 후 관리자 승인을 받으면 권리금·매물 비교·채용 분석 등 <b>개국 준비 약사 메뉴</b>를 이용할 수 있습니다.</p>
            <form method="post" action="login.php?next=<?= htmlspecialchars($next) ?>&need=<?= $need ?>">
                <input type="hidden" name="action" value="request_upgrade">
                <button type="submit" class="upg-btn">개국 준비 신청하기</button>
            </form>
        <?php endif; ?>
    </div>
    <?php elseif ($user !== null && $user['grade'] === 'prep' && $need >= 4) : ?>
    <div class="notice">판매가 진단·재고 회전율은 <b>운영 중인 개국약사</b> 전용입니다. 개국을 완료하셨다면 관리자에게 개국약사 등급 전환을 요청하세요.</div>
    <?php elseif ($user !== null && $user['grade'] === 'member' && $need >= 3) : ?>
    <div class="notice">개국 준비·개국약사 메뉴는 <b>약사 회원</b>이 개국 준비 신청 후 승인받아 이용할 수 있습니다. (일반 회원은 약사 인증이 먼저 필요합니다)</div>
    <?php endif; ?>

    <?php foreach ($accounts as $a) : ?>
        <form method="post" action="login.php?next=<?= htmlspecialchars($next) ?>&need=<?= $need ?>">
            <input type="hidden" name="login_id" value="<?= htmlspecialchars($a['login_id']) ?>">
            <button type="submit" class="acct">
                <span class="a-name"><?= $a['grade'] === 'admin' ? '🔧' : '👤' ?> <?= htmlspecialchars($a['name']) ?></span><span class="a-grade<?= $a['grade'] === 'admin' ? ' g-admin' : '' ?>"><?= htmlspecialchars($GRADE_NAMES[$a['grade']]) ?></span>
                <span class="a-desc" style="display:block;"><?= htmlspecialchars($GRADE_DESC[$a['grade']]) ?></span>
            </button>
        </form>
    <?php endforeach; ?>

    <a class="guest" href="index.php">로그인 없이 둘러보기 (비회원 — 공개 도구만) →</a>

    <p class="demo-note">입장 조건: ① 회원인가 → ② 약사인가 → ③ 개국약사인가.<br>
        데모 계정은 site_members 테이블의 가상 데이터이며, 추후 데일리팜 본사이트 회원 정보 이관 시 실제 로그인으로 교체됩니다.</p>
</div>
</body>
</html>
