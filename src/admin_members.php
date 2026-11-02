<?php
// 관리자 — 회원·권한 관리 (데모): 개국 준비 신청 승인/반려, 등급 변경
$ADMIN_PAGE = 'members';
require __DIR__ . '/admin_common.php';
require_once __DIR__ . '/auth.php';

$flash = '';
$flash_err = '';
$rows = array();
$pending = array();

try {
    $pdo = getConnection();
    authEnsureMembers($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['m_action'])) {
        $mid = (int) (isset($_POST['id']) ? $_POST['id'] : 0);
        if ($_POST['m_action'] === 'approve' && $mid > 0) {
            $st = $pdo->prepare("UPDATE site_members SET grade = 'prep', upgrade_status = '' WHERE id = ? AND upgrade_status = 'pending'");
            $st->execute(array($mid));
            $flash = $st->rowCount() ? '승인했습니다 — 해당 회원은 즉시 개국 준비 약사 메뉴를 이용할 수 있습니다. (개국 완료 시 등급 변경으로 개국약사 전환)' : '이미 처리된 신청입니다.';
        } elseif ($_POST['m_action'] === 'reject' && $mid > 0) {
            $st = $pdo->prepare("UPDATE site_members SET upgrade_status = 'rejected' WHERE id = ? AND upgrade_status = 'pending'");
            $st->execute(array($mid));
            $flash = $st->rowCount() ? '반려했습니다. 회원은 다시 신청할 수 있습니다.' : '이미 처리된 신청입니다.';
        } elseif ($_POST['m_action'] === 'grade' && $mid > 0) {
            $g = isset($_POST['grade']) ? $_POST['grade'] : '';
            if (array_key_exists($g, authGrades())) {
                $st = $pdo->prepare("UPDATE site_members SET grade = ?, upgrade_status = '' WHERE id = ?");
                $st->execute(array($g, $mid));
                $flash = '등급을 변경했습니다 — 다음 페이지 이동부터 즉시 적용됩니다.';
            }
        }
    }

    $pending = $pdo->query(
        "SELECT id, login_id, name, grade, requested_at FROM site_members WHERE upgrade_status = 'pending' ORDER BY requested_at"
    )->fetchAll(PDO::FETCH_ASSOC);
    $rows = $pdo->query(
        "SELECT id, login_id, name, grade, upgrade_status, is_demo, created_at FROM site_members ORDER BY FIELD(grade, 'admin', 'owner', 'prep', 'pharmacist', 'member'), id"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $flash_err = 'DB 오류: ' . htmlspecialchars($e->getMessage());
}

$GN = authGradeNames();

adminPageOpen(
    '회원·권한 관리',
    '입장 조건(회원→약사→개국 준비 약사→개국약사) 데모 회원을 관리합니다. <b>개국 준비 신청</b>을 승인하면 약사 회원이
     <b>개국 준비 약사</b>로 올라가 권리금·매물 비교·채용 분석을 이용하고, 개국 완료 시 등급 변경으로 <b>개국약사</b>(판매가 진단·재고 회전율 포함 전체)로 전환합니다.
     등급은 다음 페이지 이동부터 즉시 반영됩니다. (추후 데일리팜 본사이트 회원 이관 시 이 화면이 실회원 관리로 확장됩니다)',
    '회원·권한'
);
?>

<style>
    .mb-tbl { width: 100%; border-collapse: collapse; font-size: 0.88em; }
    .mb-tbl th { text-align: left; padding: 9px 10px; color: #6b7280; font-size: 0.86em; border-bottom: 2px solid #f3f4f6; white-space: nowrap; }
    .mb-tbl td { padding: 9px 10px; border-bottom: 1px solid #f9fafb; vertical-align: middle; }
    .g-badge { font-size: 0.78em; font-weight: bold; border-radius: 999px; padding: 2px 10px; white-space: nowrap; }
    .g-member { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
    .g-pharmacist { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
    .g-prep { background: #fefce8; color: #a16207; border: 1px solid #fde68a; }
    .g-owner { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
    .g-admin { background: #fff7ed; color: #9a3412; border: 1px solid #fed7aa; }
    .st-pending { color: #b45309; font-weight: bold; font-size: 0.84em; }
    .st-rejected { color: #b91c1c; font-size: 0.84em; }
    .mb-btn { padding: 6px 14px; border-radius: 7px; border: 1px solid #d1d5db; background: #fff; font-size: 0.8em; font-weight: bold; cursor: pointer; }
    .mb-btn.ok { background: #047857; color: #fff; border-color: #047857; }
    .mb-btn.no { color: #b91c1c; }
    .mb-sel { padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 7px; font-size: 0.84em; }
    .dim { color: #9ca3af; font-size: 0.88em; }
    .tbl-scroll { overflow-x: auto; }
</style>

<div class="result-card">
    <div class="grp-head"><h3>🏪 개국 준비 신청 대기</h3>
        <span class="badge <?= count($pending) > 0 ? 'on' : '' ?>"><?= count($pending) ?>건</span>
    </div>
    <?php if (count($pending) === 0) : ?>
    <p class="grp-desc">대기 중인 신청이 없습니다. 약사 회원이 로그인 페이지에서 「개국 준비 신청하기」를 누르면 여기에 표시됩니다.</p>
    <?php else : ?>
    <div class="tbl-scroll"><table class="mb-tbl">
        <tr><th>회원</th><th>현재 등급</th><th>신청 일시</th><th>처리</th></tr>
        <?php foreach ($pending as $p) : ?>
        <tr>
            <td><b><?= htmlspecialchars($p['name']) ?></b> <span class="dim"><?= htmlspecialchars($p['login_id']) ?></span></td>
            <td><span class="g-badge g-<?= $p['grade'] ?>"><?= htmlspecialchars(isset($GN[$p['grade']]) ? $GN[$p['grade']] : $p['grade']) ?></span></td>
            <td class="dim"><?= htmlspecialchars(substr((string) $p['requested_at'], 0, 16)) ?></td>
            <td>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="m_action" value="approve"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <button type="submit" class="mb-btn ok">✓ 승인 (개국 준비 약사로)</button>
                </form>
                <form method="post" style="display:inline;" onsubmit="return confirm('이 신청을 반려할까요?');">
                    <input type="hidden" name="m_action" value="reject"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <button type="submit" class="mb-btn no">반려</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table></div>
    <?php endif; ?>
</div>

<div class="result-card">
    <div class="grp-head"><h3>👥 전체 회원</h3><span class="badge on"><?= count($rows) ?>명</span></div>
    <div class="tbl-scroll"><table class="mb-tbl">
        <tr><th>회원</th><th>등급</th><th>신청 상태</th><th>구분</th><th>가입일</th><th>등급 변경</th></tr>
        <?php foreach ($rows as $r) : ?>
        <tr>
            <td><b><?= htmlspecialchars($r['name']) ?></b> <span class="dim"><?= htmlspecialchars($r['login_id']) ?></span></td>
            <td><span class="g-badge g-<?= $r['grade'] ?>"><?= htmlspecialchars(isset($GN[$r['grade']]) ? $GN[$r['grade']] : $r['grade']) ?></span></td>
            <td><?php if ($r['upgrade_status'] === 'pending') : ?><span class="st-pending">승인 대기</span>
                <?php elseif ($r['upgrade_status'] === 'rejected') : ?><span class="st-rejected">반려됨</span>
                <?php else : ?><span class="dim">-</span><?php endif; ?></td>
            <td class="dim"><?= $r['is_demo'] ? '데모' : '이관' ?></td>
            <td class="dim"><?= htmlspecialchars(substr((string) $r['created_at'], 0, 10)) ?></td>
            <td>
                <form method="post" style="display:inline-flex; gap:6px; align-items:center;">
                    <input type="hidden" name="m_action" value="grade"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <select name="grade" class="mb-sel">
                        <?php foreach ($GN as $gk => $gn) : ?>
                        <option value="<?= $gk ?>"<?= $r['grade'] === $gk ? ' selected' : '' ?>><?= htmlspecialchars($gn) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="mb-btn">변경</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table></div>
</div>

<?php adminPageClose(); ?>
