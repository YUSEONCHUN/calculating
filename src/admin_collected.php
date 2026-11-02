<?php
// 관리자 — 수집 데이터 (판매가 진단에서 수집된 익명 판매가 · AI 이름 매칭)
// user_price_reports: 양식 모드에서 동의한 익명 판매가 / name_match_map: 사용자가 확인한 상품명 매칭 쌍
$ADMIN_PAGE = 'collected';
require __DIR__ . '/admin_common.php';

$flash = '';
$flash_err = '';

$nm_rows = array();
$pr_agg = array();
$pr_recent = array();
$pr_total = 0;
$pr_src = array();
$pr_products = 0;

try {
    $pdo = getConnection();

    // 행 삭제 (잘못 수집된 데이터 정리)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['c_action'])) {
        $cid = (int) (isset($_POST['id']) ? $_POST['id'] : 0);
        if ($_POST['c_action'] === 'nm_delete' && $cid > 0) {
            $st = $pdo->prepare('DELETE FROM name_match_map WHERE id = ?');
            $st->execute(array($cid));
            $flash = '매칭을 삭제했습니다. 다음 분석부터 자동 적용에서 제외됩니다.';
        } elseif ($_POST['c_action'] === 'pr_delete' && $cid > 0) {
            $st = $pdo->prepare('DELETE FROM user_price_reports WHERE id = ?');
            $st->execute(array($cid));
            $flash = '판매가 제공 건을 삭제했습니다.';
        }
    }

    // 이름 매칭 목록 (확인 횟수 많은 순)
    try {
        $nm_rows = $pdo->query(
            "SELECT id, alias_name, match_name, source, hits, updated_at
               FROM name_match_map ORDER BY hits DESC, updated_at DESC LIMIT 500"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $nm_rows = array(); // 테이블 미생성 (아직 수집 없음)
    }

    // 익명 판매가 — 품목별 요약 + 최근 원본
    try {
        $pr_total = (int) $pdo->query("SELECT COUNT(*) FROM user_price_reports")->fetchColumn();
        if ($pr_total > 0) {
            $pr_agg = $pdo->query(
                "SELECT product_name, COUNT(*) n, MIN(price) mn, MAX(price) mx,
                        ROUND(AVG(price)) avgp, MAX(created_at) last_at
                   FROM user_price_reports GROUP BY product_name ORDER BY n DESC, last_at DESC LIMIT 300"
            )->fetchAll(PDO::FETCH_ASSOC);
            $pr_products = count($pr_agg);
            try { // 출처 구분 (file=진단 파일 유래 / tpl=양식·간편 입력) — 구버전 테이블은 열 없음
                $pr_src = $pdo->query("SELECT source, COUNT(*) c FROM user_price_reports GROUP BY source")->fetchAll(PDO::FETCH_KEY_PAIR);
            } catch (Exception $e) {
                $pr_src = array();
            }
            $pr_recent = $pdo->query(
                "SELECT id, jibu_name, product_name, price, monthly_qty, created_at" . (count($pr_src) > 0 ? ", source" : "") . "
                   FROM user_price_reports ORDER BY id DESC LIMIT 50"
            )->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $pr_total = 0; // 테이블 미생성
    }
    // 매물 상담기록 (listings.php 매물 비교 동기화분)
    $lr_total = 0;
    $lr_agg = array();
    $lr_recent = array();
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['c_action']) && $_POST['c_action'] === 'lr_delete') {
            $cid = (int) (isset($_POST['id']) ? $_POST['id'] : 0);
            if ($cid > 0) {
                $st = $pdo->prepare('DELETE FROM listing_reports WHERE id = ?');
                $st->execute(array($cid));
                $flash = '매물 상담기록을 삭제했습니다.';
            }
        }
        $lr_total = (int) $pdo->query('SELECT COUNT(*) FROM listing_reports')->fetchColumn();
        if ($lr_total > 0) {
            $lr_agg = $pdo->query(
                "SELECT region, kind, COUNT(*) n,
                        SUM(CASE WHEN is_test = 1 THEN 1 ELSE 0 END) test_n,
                        ROUND(AVG(CASE WHEN ask > 0 AND model_fair > 0 AND is_test = 0
                                        AND ask BETWEEN model_fair * 0.2 AND model_fair * 3
                                       THEN ask / model_fair END), 3) ask_ratio
                   FROM listing_reports GROUP BY region, kind ORDER BY region, kind"
            )->fetchAll(PDO::FETCH_ASSOC);
            $lr_recent = $pdo->query(
                "SELECT id, kind, name, source, cdate, region, presc, rent, ask, model_fair, is_test, updated_at
                   FROM listing_reports ORDER BY updated_at DESC LIMIT 50"
            )->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        $lr_total = 0; // 테이블 미생성
    }

    // OTC 판매가 실측 조사 (survey_price.php) — 품목별 설문 중앙값 vs 기존 조사 시세 비교
    $ops_total = 0;
    $ops_resp = 0;
    $ops_agg = array();
    try {
        $ops_total = (int) $pdo->query('SELECT COUNT(*) FROM otc_price_survey')->fetchColumn();
        if ($ops_total > 0) {
            $ops_resp = (int) $pdo->query('SELECT COUNT(DISTINCT submission) FROM otc_price_survey')->fetchColumn();
            $rows = $pdo->query(
                "SELECT product_name, pack,
                        COUNT(*) n, SUM(carry) carry_n,
                        GROUP_CONCAT(CASE WHEN carry = 1 AND price IS NOT NULL THEN price END ORDER BY price) prices
                   FROM otc_price_survey GROUP BY product_name, pack ORDER BY n DESC"
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $ps = $r['prices'] !== null ? array_map('intval', explode(',', $r['prices'])) : array();
                $med = count($ps) ? $ps[(int) floor(count($ps) / 2)] : null;
                // 기존 조사 시세 중앙값 (otc_prices)
                $st = $pdo->prepare('SELECT price FROM otc_prices WHERE product_name = ? AND price > 100 ORDER BY price');
                $st->execute(array($r['product_name']));
                $base = $st->fetchAll(PDO::FETCH_COLUMN);
                $base_med = count($base) ? (int) $base[(int) floor(count($base) / 2)] : null;
                $ops_agg[] = array(
                    'name' => $r['product_name'], 'pack' => $r['pack'], 'n' => (int) $r['n'],
                    'carry_pct' => round($r['carry_n'] / max(1, $r['n']) * 100),
                    'med' => $med, 'base_med' => $base_med,
                );
            }
        }
    } catch (Exception $e) {
        $ops_total = 0; // 테이블 미생성
    }
} catch (Exception $e) {
    $flash_err = 'DB 연결에 실패했습니다: ' . htmlspecialchars($e->getMessage());
}

adminPageOpen(
    '수집 데이터',
    '판매가 진단(<a href="pricecheck.php">pricecheck.php</a>)에서 사용자 동의로 수집된 <strong>익명 판매가</strong>와,
     사용자가 확인·적용한 <strong>AI 이름 매칭</strong>을 확인합니다. 잘못 수집된 행은 삭제하면 다음 분석부터 제외됩니다.
     <a href="admin_drugsync.php">의약품 허가정보 동기화 →</a>',
    '수집 데이터'
);
?>

<style>
    .col-table { width: 100%; border-collapse: collapse; font-size: 0.86em; }
    .col-table th { text-align: left; padding: 8px 10px; color: #6b7280; font-size: 0.88em; border-bottom: 2px solid #f3f4f6; white-space: nowrap; }
    .col-table td { padding: 7px 10px; border-bottom: 1px solid #f9fafb; color: #374151; }
    .col-table td.r, .col-table th.r { text-align: right; white-space: nowrap; }
    .col-table .dim { color: #9ca3af; font-size: 0.9em; white-space: nowrap; }
    .src-badge { display: inline-block; font-size: 0.75em; font-weight: bold; padding: 2px 9px; border-radius: 999px; }
    .src-badge.ai { background: #eff6ff; color: #1d4ed8; }
    .src-badge.manual { background: #fef3c7; color: #92400e; }
    .btn-del { padding: 4px 10px; background: #fff; color: #b91c1c; border: 1px solid #fca5a5; border-radius: 6px; font-size: 0.78em; cursor: pointer; }
    .btn-del:hover { background: #fef2f2; }
    .empty-note { color: #9ca3af; font-size: 0.88em; padding: 8px 0; }
    .cnt-chip { font-size: 0.72em; color: #6b7280; background: #f3f4f6; border-radius: 999px; padding: 3px 10px; font-weight: bold; }
    .tbl-scroll { overflow-x: auto; }
    .arrow { color: #9ca3af; padding: 0 4px; }
</style>

<div class="result-card">
    <div class="grp-head">
        <h3>🔤 수집된 이름 매칭</h3>
        <span class="cnt-chip"><?= count($nm_rows) ?>건</span>
    </div>
    <p class="grp-desc">
        사용자가 AI 제안을 검토·적용한 상품명 매칭 쌍입니다. 같은 별칭에 다른 매칭이 있으면 확인 횟수가 많은 쪽이 자동 적용됩니다.
        잘못된 매칭은 삭제하세요 — 즉시 자동 적용에서 빠집니다.
    </p>
    <?php if (count($nm_rows) === 0): ?>
        <div class="empty-note">아직 수집된 매칭이 없습니다. 판매가 진단에서 사용자가 AI 매칭을 적용하면 여기에 쌓입니다.</div>
    <?php else: ?>
    <div class="tbl-scroll">
    <table class="col-table">
        <tr><th>표기 변형 (별칭)</th><th></th><th>대표 상품명</th><th>출처</th><th class="r">확인 횟수</th><th>최근 확인</th><th></th></tr>
        <?php foreach ($nm_rows as $r): ?>
        <tr>
            <td><strong><?= htmlspecialchars($r['alias_name']) ?></strong></td>
            <td class="arrow">→</td>
            <td><?= htmlspecialchars($r['match_name']) ?></td>
            <td><span class="src-badge <?= $r['source'] === 'manual' ? 'manual' : 'ai' ?>"><?= $r['source'] === 'manual' ? '수동 수정' : 'AI 제안' ?></span></td>
            <td class="r"><?= (int) $r['hits'] ?></td>
            <td class="dim"><?= htmlspecialchars(substr($r['updated_at'], 0, 16)) ?></td>
            <td class="r">
                <form method="post" style="display:inline;" onsubmit="return confirm('이 매칭을 삭제할까요? 다음 분석부터 자동 적용되지 않습니다.');">
                    <input type="hidden" name="c_action" value="nm_delete">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button type="submit" class="btn-del">삭제</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
    <?php endif; ?>
</div>

<div class="result-card">
    <div class="grp-head">
        <h3>💰 익명 판매가 제공 — 품목별 요약</h3>
        <span class="cnt-chip">총 <?= number_format($pr_total) ?>건 · <?= number_format($pr_products) ?>개 품목</span>
        <?php if (count($pr_src) > 0): ?>
        <span class="cnt-chip" title="file=진단 파일 유래(실데이터) / tpl=양식·간편 입력">파일 유래 <?= number_format(isset($pr_src['file']) ? $pr_src['file'] : 0) ?> · 수기 입력 <?= number_format(isset($pr_src['tpl']) ? $pr_src['tpl'] : 0) ?></span>
        <?php endif; ?>
    </div>
    <p class="grp-desc">
        양식·간편 입력 또는 파일 진단에서 "익명 시세 데이터로 제공"에 동의한 판매가입니다.
        파일 유래(file)는 약국 프로그램 실데이터라 신뢰도가 높습니다. 표본이 쌓이면 실측 조사(otc_prices)에 합쳐 진단 품목을 늘리는 데 쓸 수 있습니다.
    </p>
    <?php if ($pr_total === 0): ?>
        <div class="empty-note">아직 제공된 판매가가 없습니다. 양식 모드에서 사용자가 동의하면 여기에 쌓입니다.</div>
    <?php else: ?>
    <div class="tbl-scroll">
    <table class="col-table">
        <tr><th>상품명</th><th class="r">제공 건수</th><th class="r">평균가</th><th class="r">최소~최대</th><th>최근 제공</th></tr>
        <?php foreach ($pr_agg as $r): ?>
        <tr>
            <td><strong><?= htmlspecialchars($r['product_name']) ?></strong></td>
            <td class="r"><?= number_format($r['n']) ?></td>
            <td class="r"><?= number_format($r['avgp']) ?>원</td>
            <td class="r dim"><?= number_format($r['mn']) ?> ~ <?= number_format($r['mx']) ?>원</td>
            <td class="dim"><?= htmlspecialchars(substr($r['last_at'], 0, 16)) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php if (count($pr_recent) > 0): ?>
<div class="result-card">
    <div class="grp-head">
        <h3>🧾 익명 판매가 — 최근 제공 50건</h3>
    </div>
    <div class="tbl-scroll">
    <table class="col-table">
        <tr><th>제공 일시</th><th>지역</th><th>상품명</th><th class="r">판매가</th><th class="r">월 판매량</th><th></th></tr>
        <?php foreach ($pr_recent as $r): ?>
        <tr>
            <td class="dim"><?= htmlspecialchars(substr($r['created_at'], 0, 16)) ?></td>
            <td><?= $r['jibu_name'] !== null && $r['jibu_name'] !== '' ? htmlspecialchars($r['jibu_name']) : '<span class="dim">전국</span>' ?></td>
            <td><?= htmlspecialchars($r['product_name']) ?>
                <?php if (isset($r['source'])): ?><span style="font-size:0.72em; font-weight:bold; border-radius:999px; padding:1px 7px; margin-left:4px; <?= $r['source'] === 'file' ? 'background:#ecfdf5; color:#047857; border:1px solid #a7f3d0;' : 'background:#f1f5f9; color:#64748b; border:1px solid #e2e8f0;' ?>"><?= $r['source'] === 'file' ? '파일' : '수기' ?></span><?php endif; ?></td>
            <td class="r"><strong><?= number_format($r['price']) ?>원</strong></td>
            <td class="r"><?= $r['monthly_qty'] !== null ? number_format($r['monthly_qty']) : '<span class="dim">-</span>' ?></td>
            <td class="r">
                <form method="post" style="display:inline;" onsubmit="return confirm('이 제공 건을 삭제할까요?');">
                    <input type="hidden" name="c_action" value="pr_delete">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button type="submit" class="btn-del">삭제</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
</div>
<?php endif; ?>

<?php if ($lr_total > 0): ?>
<div class="result-card">
    <div class="grp-head">
        <h3>🏷 매물 상담기록 (매물 비교 동기화)</h3>
        <span class="cnt-chip"><?= number_format($lr_total) ?>건</span>
    </div>
    <p class="grp-desc" style="font-size:0.85em; color:#6b7280;">
        <a href="listings.php" target="_blank">매물 비교</a>에서 저장된 매물이 익명으로 축적됩니다.
        지역별 <b>호가 ÷ 모델 적정가</b> 평균이 권리금 시세·계산의 지역 보정에 합산됩니다
        (기존 인수·테스트 제외분만, 실사용 피드백과 합산 표본 30건부터 ±10% 한도). 테스트 가데이터는 보정에서 제외됩니다.
    </p>
    <div class="tbl-scroll">
    <table class="col-table">
        <tr><th>지역</th><th>유형</th><th class="r">건수</th><th class="r">(테스트)</th><th class="r">호가/적정가 평균</th></tr>
        <?php $rn = array('seoul_core' => '서울 핵심', 'seoul' => '서울 일반', 'metro' => '수도권', 'bigcity' => '광역시', 'local' => '지방'); ?>
        <?php foreach ($lr_agg as $a): ?>
        <tr>
            <td><?= htmlspecialchars(isset($rn[$a['region']]) ? $rn[$a['region']] : $a['region']) ?></td>
            <td><?= $a['kind'] === 'new' ? '신규 자리' : '기존 인수' ?></td>
            <td class="r"><strong><?= number_format($a['n']) ?></strong></td>
            <td class="r dim"><?= (int) $a['test_n'] ?></td>
            <td class="r"><?= $a['ask_ratio'] !== null ? '×' . number_format($a['ask_ratio'], 3) : '<span class="dim">-</span>' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
    <div class="tbl-scroll" style="margin-top:14px;">
    <table class="col-table">
        <tr><th>수정 일시</th><th>매물</th><th>지역</th><th class="r">조제료</th><th class="r">월세</th><th class="r">호가</th><th class="r">모델 적정가</th><th></th></tr>
        <?php foreach ($lr_recent as $r): ?>
        <tr>
            <td class="dim"><?= htmlspecialchars(substr($r['updated_at'], 0, 16)) ?></td>
            <td><?= htmlspecialchars($r['name']) ?>
                <?php if ($r['is_test']): ?><span style="font-size:0.72em; font-weight:bold; border-radius:999px; padding:1px 7px; margin-left:4px; background:#fef3c7; color:#92400e;">테스트</span><?php endif; ?>
                <span class="dim"><?= $r['source'] !== '' && $r['source'] !== null ? '· ' . htmlspecialchars($r['source']) : '' ?><?= $r['cdate'] !== null ? ' · ' . htmlspecialchars($r['cdate']) : '' ?></span></td>
            <td><?= htmlspecialchars(isset($rn[$r['region']]) ? $rn[$r['region']] : $r['region']) ?> <span class="dim"><?= $r['kind'] === 'new' ? '신규' : '기존' ?></span></td>
            <td class="r"><?= number_format($r['presc']) ?>만</td>
            <td class="r"><?= number_format($r['rent']) ?>만</td>
            <td class="r"><strong><?= $r['ask'] > 0 ? number_format($r['ask']) . '만' : '-' ?></strong></td>
            <td class="r"><?= $r['model_fair'] > 0 ? number_format($r['model_fair']) . '만' : '-' ?></td>
            <td class="r">
                <form method="post" style="display:inline;" onsubmit="return confirm('이 상담기록을 삭제할까요?');">
                    <input type="hidden" name="c_action" value="lr_delete">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button type="submit" class="btn-del">삭제</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
</div>
<?php endif; ?>

<div class="result-card">
    <div class="grp-head">
        <h3>🧪 OTC 판매가 실측 조사 (설문)</h3>
        <span class="badge <?= $ops_total > 0 ? 'on' : '' ?>">응답자 <?= number_format($ops_resp) ?>명 · <?= number_format($ops_total) ?>건</span>
    </div>
    <p class="grp-desc">조사원·약사에게 <a href="survey_price.php" target="_blank">판매가 실측 조사</a> 링크를 전달하세요 —
        시세를 보여주지 않고 실판매가를 직접 수집합니다. <b>otc_prices와 분리 보관</b>되며 아래 비교로 검증 후 병합을 결정합니다.</p>
    <?php if (count($ops_agg) > 0) : ?>
    <div class="tbl-scroll">
    <table class="col-table">
        <tr><th>품목</th><th class="r">응답</th><th class="r">취급률</th><th class="r">설문 중앙값</th><th class="r">조사 시세 중앙값</th><th class="r">격차</th></tr>
        <?php foreach ($ops_agg as $a) :
            $gap = ($a['med'] !== null && $a['base_med']) ? round(($a['med'] / $a['base_med'] - 1) * 100) : null;
        ?>
        <tr>
            <td><?= htmlspecialchars($a['name']) ?> <span class="dim"><?= htmlspecialchars($a['pack']) ?></span></td>
            <td class="r"><?= $a['n'] ?>건</td>
            <td class="r"><?= $a['carry_pct'] ?>%</td>
            <td class="r"><strong><?= $a['med'] !== null ? number_format($a['med']) . '원' : '-' ?></strong></td>
            <td class="r"><?= $a['base_med'] !== null ? number_format($a['base_med']) . '원' : '-' ?></td>
            <td class="r" style="<?= $gap !== null && abs($gap) >= 10 ? 'color:#b91c1c; font-weight:bold;' : 'color:#047857;' ?>">
                <?= $gap !== null ? ($gap > 0 ? '+' : '') . $gap . '%' : '-' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
    <?php endif; ?>
</div>

<p class="foot-note">
    ※ 두 데이터 모두 상품명·가격·지역만 저장하며 약국을 특정할 수 있는 정보는 없습니다.<br>
    ※ 이름 매칭은 삭제 즉시 판매가 진단의 자동 적용에서 제외됩니다. 익명 판매가·실측 조사는 아직 시세 계산에 직접 쓰이지 않으며, 검토 후 병합 기능은 표본이 쌓이면 추가 예정입니다.
</p>
</body>
</html>
