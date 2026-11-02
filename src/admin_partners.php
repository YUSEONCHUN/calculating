<?php
// 관리자 — 파트너 관리 (약국 컨설팅 업체 · 공인중개사 소개 리스트)
// 권리금 계산 결과 하단의 "이 지역 전문 파트너" 노출 목록을 관리합니다.
$ADMIN_PAGE = 'partners';
require __DIR__ . '/admin_common.php';
require_once __DIR__ . '/partners.php';

$p_sido_list = array('서울특별시', '경기도', '인천광역시', '부산광역시', '대구광역시', '광주광역시', '대전광역시',
    '울산광역시', '세종특별자치시', '강원특별자치도', '충청북도', '충청남도', '전북특별자치도', '전라남도',
    '경상북도', '경상남도', '제주특별자치도');

$p_flash = '';
$p_err = '';
$edit_row = null;

try {
    $pdo = getConnection();
    partnersEnsureTable($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['p_action'])) {
        $act = $_POST['p_action'];
        $id = (int) (isset($_POST['id']) ? $_POST['id'] : 0);

        if ($act === 'delete' && $id > 0) {
            $st = $pdo->prepare('DELETE FROM partner_listings WHERE id = ?');
            $st->execute(array($id));
            $p_flash = '삭제했습니다.';
        } elseif ($act === 'toggle' && $id > 0) {
            $st = $pdo->prepare('UPDATE partner_listings SET is_active = 1 - is_active WHERE id = ?');
            $st->execute(array($id));
            $p_flash = '노출 상태를 변경했습니다.';
        } elseif ($act === 'promo_add') {
            // 프로모션 기간 등록 (강조효과/상단고정) — 같은 종류의 기존 기간과 겹치면 거부 (기간 종료 후 새 기간 추가 방식)
            $pr_partner = (int) $_POST['partner_id'];
            $pr_type = $_POST['promo_type'] === 'pin' ? 'pin' : 'highlight';
            $pr_start = $_POST['start_date'];
            $pr_end = $_POST['end_date'];
            $pr_amount = max(0, (int) str_replace(',', '', $_POST['amount']));
            $pr_memo = mb_substr(trim($_POST['memo']), 0, 120);

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $pr_start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $pr_end)) {
                $p_err = '시작일·종료일을 입력하세요.';
            } elseif ($pr_end < $pr_start) {
                $p_err = '종료일이 시작일보다 빠릅니다.';
            } else {
                $chk = $pdo->prepare(
                    'SELECT start_date, end_date FROM partner_promotions
                      WHERE partner_id = ? AND promo_type = ? AND NOT (end_date < ? OR start_date > ?) LIMIT 1'
                );
                $chk->execute(array($pr_partner, $pr_type, $pr_start, $pr_end));
                $overlap = $chk->fetch();
                if ($overlap) {
                    $p_err = '같은 종류의 기존 기간(' . $overlap['start_date'] . ' ~ ' . $overlap['end_date'] . ')과 겹칩니다 — 그 기간이 끝난 뒤 날짜로 등록하세요.';
                } else {
                    $st = $pdo->prepare(
                        'INSERT INTO partner_promotions (partner_id, promo_type, start_date, end_date, amount, memo) VALUES (?,?,?,?,?,?)'
                    );
                    $st->execute(array($pr_partner, $pr_type, $pr_start, $pr_end, $pr_amount, $pr_memo));
                    $p_flash = ($pr_type === 'pin' ? '상단고정' : '강조효과') . ' 기간을 등록했습니다 (' . $pr_start . ' ~ ' . $pr_end . ').';
                }
            }
        } elseif ($act === 'promo_delete' && $id > 0) {
            $st = $pdo->prepare('DELETE FROM partner_promotions WHERE id = ?');
            $st->execute(array($id));
            $p_flash = '프로모션 기간을 삭제했습니다.';
        } elseif ($act === 'save') {
            $category = array_key_exists($_POST['category'], partnerCategories()) ? $_POST['category'] : 'consulting';
            $name = mb_substr(trim($_POST['name']), 0, 60);
            $phone = mb_substr(trim($_POST['phone']), 0, 30);
            $address = mb_substr(trim($_POST['address']), 0, 120);
            $website = mb_substr(trim($_POST['website']), 0, 200);
            if ($website !== '' && !preg_match('~^https?://~i', $website)) {
                $website = 'https://' . $website; // 프로토콜 생략 시 보정
            }
            $sido = in_array($_POST['sido'], $p_sido_list, true) ? $_POST['sido'] : '';
            $sigungu = $sido !== '' ? mb_substr(trim($_POST['sigungu']), 0, 30) : '';
            $note = mb_substr(trim($_POST['note']), 0, 120);
            $sort = (int) $_POST['sort_order'];

            if ($name === '') {
                $p_err = '업체명을 입력하세요.';
            } elseif ($id > 0) {
                $st = $pdo->prepare(
                    'UPDATE partner_listings SET category=?, name=?, phone=?, address=?, website=?, sido=?, sigungu=?, note=?, sort_order=? WHERE id=?'
                );
                $st->execute(array($category, $name, $phone, $address, $website, $sido, $sigungu, $note, $sort, $id));
                $p_flash = '「' . $name . '」 수정 완료.';
            } else {
                $st = $pdo->prepare(
                    'INSERT INTO partner_listings (category, name, phone, address, website, sido, sigungu, note, sort_order) VALUES (?,?,?,?,?,?,?,?,?)'
                );
                $st->execute(array($category, $name, $phone, $address, $website, $sido, $sigungu, $note, $sort));
                $p_flash = '「' . $name . '」 등록 완료 — 결과 화면에 즉시 노출됩니다.';
            }
        }
    }

    if (isset($_GET['edit'])) {
        $st = $pdo->prepare('SELECT * FROM partner_listings WHERE id = ?');
        $st->execute(array((int) $_GET['edit']));
        $edit_row = $st->fetch() ?: null;
    }

    // 프로모션 관리 대상 파트너 (?promo=ID)
    $promo_row = null;
    $promo_history = array();
    if (isset($_GET['promo'])) {
        $st = $pdo->prepare('SELECT * FROM partner_listings WHERE id = ?');
        $st->execute(array((int) $_GET['promo']));
        $promo_row = $st->fetch() ?: null;
        if ($promo_row !== null) {
            $st = $pdo->prepare('SELECT * FROM partner_promotions WHERE partner_id = ? ORDER BY promo_type, start_date DESC');
            $st->execute(array((int) $promo_row['id']));
            $promo_history = $st->fetchAll();
        }
    }

    // 목록 카테고리 필터 (?cat=키)
    $cat_filter = isset($_GET['cat']) && array_key_exists($_GET['cat'], partnerCategories()) ? $_GET['cat'] : '';
    if ($cat_filter !== '') {
        $st = $pdo->prepare(
            "SELECT * FROM partner_listings WHERE category = ? ORDER BY CASE WHEN sido = '' THEN 1 ELSE 0 END, sido, sigungu, sort_order, id"
        );
        $st->execute(array($cat_filter));
        $listings = $st->fetchAll();
    } else {
        $listings = $pdo->query(
            "SELECT * FROM partner_listings ORDER BY category, CASE WHEN sido = '' THEN 1 ELSE 0 END, sido, sigungu, sort_order, id"
        )->fetchAll();
    }

    // 목록 배지용: 진행 중·예정 프로모션 맵
    $promo_map = array();
    foreach ($pdo->query(
        "SELECT partner_id, promo_type, start_date, end_date, amount FROM partner_promotions WHERE end_date >= CURDATE() ORDER BY start_date"
    ) as $pm) {
        $promo_map[(int) $pm['partner_id']][] = $pm;
    }
} catch (Exception $e) {
    $p_err = 'DB 연결 실패: ' . htmlspecialchars($e->getMessage());
    $listings = array();
    $promo_row = null;
    $promo_history = array();
    $promo_map = array();
    $cat_filter = '';
}

$f = function ($key, $default = '') use ($edit_row) {
    return htmlspecialchars($edit_row !== null && isset($edit_row[$key]) ? (string) $edit_row[$key] : $default);
};

adminPageOpen(
    '🤝 파트너 관리',
    '권리금 계산 결과 하단에 노출되는 <strong>파트너 업체</strong> 소개 리스트를 관리합니다 (컨설팅·중개사·설비·세무사·노무사·도매·재고정리·대출·변호사·전산·보험 11종). 각 계산기 결과 화면에 종류별로 자동 노출됩니다.'
    . ' 지역(시/도·시/군/구)을 지정하면 해당 지역 사용자에게만, 비우면 전국에 노출됩니다.',
    '파트너 관리'
);
?>

<style>
    .pt-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px 14px; }
    .pt-form .full { grid-column: 1 / -1; }
    .pt-form label { display: block; font-weight: bold; font-size: 0.8em; color: #374151; margin-bottom: 4px; }
    .pt-form input, .pt-form select { width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box; font-size: 0.9em; }
    .btn-pt { margin-top: 12px; padding: 10px 26px; background: #0e7490; color: #fff; border: none; border-radius: 7px; font-weight: bold; font-size: 0.92em; cursor: pointer; }
    .btn-pt:hover { background: #155e75; }
    .btn-cancel { margin-top: 12px; margin-left: 8px; padding: 10px 18px; background: #fff; color: #4b5563; border: 1px solid #d1d5db; border-radius: 7px; font-size: 0.88em; cursor: pointer; text-decoration: none; display: inline-block; }

    .pt-table { width: 100%; border-collapse: collapse; font-size: 0.85em; }
    .pt-table th { text-align: left; padding: 8px 6px; border-bottom: 2px solid #e5e7eb; color: #6b7280; font-size: 0.85em; white-space: nowrap; }
    .pt-table td { padding: 9px 6px; border-bottom: 1px solid #f1f5f9; color: #374151; vertical-align: top; }
    .pt-table .cat-chip { font-size: 0.78em; font-weight: bold; border-radius: 4px; padding: 2px 7px; white-space: nowrap; }
    .pt-table .cat-chip { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
    .pt-table .cat-consulting { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
    .pt-table .cat-realtor { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
    .pt-table .cat-equipment { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
    .pt-table .off { opacity: 0.45; }
    .pt-btns { display: flex; gap: 4px; }
    .pt-btns button, .pt-btns a { padding: 4px 9px; border: 1px solid #d1d5db; background: #fff; color: #4b5563; border-radius: 5px; font-size: 0.8em; cursor: pointer; text-decoration: none; }
    .pt-btns .del:hover { background: #fee2e2; color: #b91c1c; border-color: #fca5a5; }
    .pt-note { font-size: 0.78em; color: #9ca3af; margin-top: 10px; line-height: 1.6; }
    @media (max-width: 800px) { .pt-hide-m { display: none; } }
</style>

<?php if ($p_flash !== ''): ?><div class="flash ok">✓ <?= $p_flash ?></div><?php endif; ?>
<?php if ($p_err !== ''): ?><div class="flash err">⚠ <?= $p_err ?></div><?php endif; ?>

<div class="result-card">
    <div class="grp-head"><h3><?= $edit_row !== null ? '✏️ 파트너 수정' : '➕ 파트너 등록' ?></h3></div>
    <form method="post" class="pt-form">
        <input type="hidden" name="p_action" value="save">
        <input type="hidden" name="id" value="<?= $edit_row !== null ? (int) $edit_row['id'] : 0 ?>">
        <div>
            <label>구분</label>
            <select name="category">
                <?php foreach (partnerCategories() as $ck => $cn): ?>
                    <option value="<?= $ck ?>" <?= $f('category', 'consulting') === $ck ? 'selected' : '' ?>><?= htmlspecialchars($cn) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>업체명 *</label>
            <input type="text" name="name" value="<?= $f('name') ?>" placeholder="예: OO약국컨설팅" required>
        </div>
        <div>
            <label>전화번호</label>
            <input type="text" name="phone" value="<?= $f('phone') ?>" placeholder="예: 02-1234-5678">
        </div>
        <div>
            <label>노출 지역 (시/도)</label>
            <select name="sido">
                <option value="">전국</option>
                <?php foreach ($p_sido_list as $s): ?>
                    <option value="<?= $s ?>" <?= $f('sido') === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>노출 지역 (시/군/구, 선택)</label>
            <input type="text" name="sigungu" value="<?= $f('sigungu') ?>" placeholder="비우면 시/도 전체 (예: 강남구)">
        </div>
        <div>
            <label>정렬 순서 (낮을수록 위)</label>
            <input type="number" name="sort_order" value="<?= $f('sort_order', '0') ?>">
        </div>
        <div class="full">
            <label>주소</label>
            <input type="text" name="address" value="<?= $f('address') ?>" placeholder="예: 서울 강남구 테헤란로 123, 4층">
        </div>
        <div class="full">
            <label>홈페이지 URL <small style="font-weight:normal;color:#9ca3af;">(선택 — 입력 시 결과 리스트에 🔗 홈페이지 링크 노출)</small></label>
            <input type="text" name="website" value="<?= $f('website') ?>" placeholder="예: https://www.example.co.kr (https:// 생략 가능)">
        </div>
        <div class="full">
            <label>한 줄 소개 (선택)</label>
            <input type="text" name="note" value="<?= $f('note') ?>" placeholder="예: 약국 전문 15년, 권리 계약·지위승계 동행">
        </div>
        <div class="full">
            <button type="submit" class="btn-pt"><?= $edit_row !== null ? '수정 저장' : '등록' ?></button>
            <?php if ($edit_row !== null): ?><a class="btn-cancel" href="admin_partners.php">취소</a><?php endif; ?>
        </div>
    </form>
</div>

<?php if ($promo_row !== null): ?>
<div class="result-card" style="border: 2px solid #1d4ed8;">
    <div class="grp-head"><h3>⭐ 프로모션 관리 — <?= htmlspecialchars($promo_row['name']) ?></h3></div>
    <p style="font-size:0.85em; color:#6b7280; margin:0 0 14px;">
        <strong>강조효과</strong>는 리스트에서 ★추천 배지와 강조 배경으로, <strong>상단고정</strong>은 📌 배지와 함께 그룹 최상단에 표시됩니다
        (상단고정이 여럿이면 <strong>지불 비용이 높은 순</strong>으로 정렬). 같은 종류의 기간은 겹칠 수 없으며, 기간 종료 후 새 기간을 추가 등록하세요.
    </p>
    <form method="post" class="pt-form">
        <input type="hidden" name="p_action" value="promo_add">
        <input type="hidden" name="partner_id" value="<?= (int) $promo_row['id'] ?>">
        <div>
            <label>종류</label>
            <select name="promo_type">
                <option value="highlight">★ 강조효과</option>
                <option value="pin">📌 상단고정</option>
            </select>
        </div>
        <div>
            <label>시작일</label>
            <input type="date" name="start_date" required style="width:100%; padding:8px 10px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box; font-size:0.9em;">
        </div>
        <div>
            <label>종료일</label>
            <input type="date" name="end_date" required style="width:100%; padding:8px 10px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box; font-size:0.9em;">
        </div>
        <div>
            <label>지불 비용 (원) <small style="font-weight:normal;color:#9ca3af;">상단고정 정렬 기준</small></label>
            <input type="text" name="amount" value="0" placeholder="예: 300000">
        </div>
        <div class="full">
            <label>메모 (선택)</label>
            <input type="text" name="memo" placeholder="예: 3개월 계약, 담당자 홍길동">
        </div>
        <div class="full">
            <button type="submit" class="btn-pt">기간 등록</button>
            <a class="btn-cancel" href="admin_partners.php<?= $cat_filter !== '' ? '?cat=' . $cat_filter : '' ?>">닫기</a>
        </div>
    </form>
    <?php if (count($promo_history) > 0): ?>
    <div style="overflow-x:auto; margin-top:16px;">
    <table class="pt-table">
        <tr><th>종류</th><th>기간</th><th>지불 비용</th><th>상태</th><th class="pt-hide-m">메모</th><th>관리</th></tr>
        <?php $today = date('Y-m-d'); foreach ($promo_history as $ph):
            $state = $ph['end_date'] < $today ? '종료' : ($ph['start_date'] > $today ? '예정' : '진행중');
            $stateColor = $state === '진행중' ? '#15803d' : ($state === '예정' ? '#1d4ed8' : '#9ca3af');
        ?>
        <tr style="<?= $state === '종료' ? 'opacity:0.55;' : '' ?>">
            <td style="white-space:nowrap;"><?= $ph['promo_type'] === 'pin' ? '📌 상단고정' : '★ 강조효과' ?></td>
            <td style="white-space:nowrap;"><?= $ph['start_date'] ?> ~ <?= $ph['end_date'] ?></td>
            <td style="white-space:nowrap;"><?= number_format($ph['amount']) ?>원</td>
            <td style="white-space:nowrap; font-weight:bold; color:<?= $stateColor ?>;"><?= $state ?></td>
            <td class="pt-hide-m"><?= htmlspecialchars($ph['memo']) ?></td>
            <td>
                <form method="post" style="margin:0;" onsubmit="return confirm('이 기간을 삭제할까요?');">
                    <input type="hidden" name="p_action" value="promo_delete">
                    <input type="hidden" name="id" value="<?= (int) $ph['id'] ?>">
                    <button type="submit" class="del" style="padding:4px 9px; border:1px solid #d1d5db; background:#fff; color:#4b5563; border-radius:5px; font-size:0.8em; cursor:pointer;">삭제</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="result-card">
    <div class="grp-head"><h3>📋 등록된 파트너 (<?= count($listings) ?>)</h3></div>
    <form method="get" style="margin: 0 0 12px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
        <label style="font-size:0.85em; font-weight:bold; color:#374151;">카테고리 필터</label>
        <select name="cat" onchange="this.form.submit()" style="padding:8px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:0.9em; background:#fff; cursor:pointer;">
            <option value="">전체 카테고리</option>
            <?php foreach (partnerCategories() as $ck => $cn): ?>
                <option value="<?= $ck ?>" <?= $cat_filter === $ck ? 'selected' : '' ?>><?= htmlspecialchars($cn) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php if (count($listings) === 0): ?>
        <p style="color:#9ca3af; font-size:0.9em;">등록된 파트너가 없습니다 — 위에서 첫 업체를 등록하세요.</p>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="pt-table">
        <tr><th>구분</th><th>업체명</th><th>전화</th><th class="pt-hide-m">주소</th><th>노출 지역</th><th class="pt-hide-m">소개</th><th style="text-align:center;">노출</th><th style="text-align:center;">클릭</th><th>관리</th></tr>
        <?php foreach ($listings as $L): ?>
        <tr class="<?= $L['is_active'] ? '' : 'off' ?>">
            <td><span class="cat-chip cat-<?= $L['category'] ?>"><?php
                $cn_all = partnerCategories();
                echo htmlspecialchars(isset($cn_all[$L['category']]) ? $cn_all[$L['category']] : $L['category']);
            ?></span></td>
            <td><strong><?= htmlspecialchars($L['name']) ?></strong><?= $L['is_active'] ? '' : ' <small>(숨김)</small>' ?>
                <?php if (isset($promo_map[(int) $L['id']])): $today = date('Y-m-d'); foreach ($promo_map[(int) $L['id']] as $pm):
                    $ing = ($pm['start_date'] <= $today && $pm['end_date'] >= $today);
                ?>
                    <br><small style="color:<?= $ing ? ($pm['promo_type'] === 'pin' ? '#1d4ed8' : '#b45309') : '#9ca3af' ?>; font-weight:bold;">
                        <?= $pm['promo_type'] === 'pin' ? '📌 고정' : '★ 강조' ?> ~<?= $pm['end_date'] ?><?= $pm['promo_type'] === 'pin' && $pm['amount'] > 0 ? ' (' . number_format($pm['amount']) . '원)' : '' ?><?= $ing ? '' : ' [예정]' ?>
                    </small>
                <?php endforeach; endif; ?>
            </td>
            <td style="white-space:nowrap;"><?= htmlspecialchars($L['phone']) ?></td>
            <td class="pt-hide-m"><?= htmlspecialchars($L['address']) ?></td>
            <td style="white-space:nowrap;"><?= $L['sido'] === '' ? '전국' : htmlspecialchars(trim($L['sido'] . ' ' . $L['sigungu'])) ?></td>
            <td class="pt-hide-m"><?= htmlspecialchars($L['note']) ?></td>
            <td style="text-align:center; color:#6b7280;" title="파트너 목록이 결과 화면에 표시된 횟수"><?= number_format((int) (isset($L['views']) ? $L['views'] : 0)) ?></td>
            <td style="text-align:center; font-weight:bold; color:#374151;" title="전화·홈페이지 클릭 합계"><?= number_format((int) (isset($L['clicks']) ? $L['clicks'] : 0)) ?></td>
            <td>
                <div class="pt-btns">
                    <a href="admin_partners.php?edit=<?= (int) $L['id'] ?>">수정</a>
                    <a href="admin_partners.php?promo=<?= (int) $L['id'] ?><?= $cat_filter !== '' ? '&cat=' . $cat_filter : '' ?>" style="color:#1d4ed8; border-color:#bfdbfe;">프로모션</a>
                    <form method="post" style="margin:0;"><input type="hidden" name="p_action" value="toggle"><input type="hidden" name="id" value="<?= (int) $L['id'] ?>"><button type="submit"><?= $L['is_active'] ? '숨김' : '노출' ?></button></form>
                    <form method="post" style="margin:0;" onsubmit="return confirm('삭제할까요?');"><input type="hidden" name="p_action" value="delete"><input type="hidden" name="id" value="<?= (int) $L['id'] ?>"><button type="submit" class="del">삭제</button></form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
    <?php endif; ?>
    <p class="pt-note">노출 우선순위: 시/군/구 일치 → 시/도 일치 → 전국 순으로 정렬되어 권리금 계산 결과 하단에 표시됩니다.
        시/군/구는 주소 파싱 결과와 같은 표기(예: "강남구", "고양시 덕양구")로 입력하세요.</p>
</div>

<?php adminPageClose(); ?>
