<?php
/**
 * AI 결과 체감 피드백 위젯 — 분석 성공 리포트 하단에 표시
 * 사용: echo renderFeedbackWidget('analyzer', $sido, $sigungu, '적정 월급 (세전)', '원');
 * $valueLabel이 비어 있으면 적정값 제시 입력 없이 3버튼만 표시
 */
function renderFeedbackWidget($tool, $sido = '', $sigungu = '', $valueLabel = '', $valueUnit = '')
{
    $t = htmlspecialchars($tool);
    $s = htmlspecialchars($sido);
    $g = htmlspecialchars($sigungu);
    $hasValue = $valueLabel !== '';
    ob_start();
?>
<div class="fb-widget">
    <div class="fb-q">💬 이 결과, 실제 시장 체감과 비교하면 어떤가요? <small>(익명 수집 — 계산 기준 보정에 사용됩니다)</small></div>
    <div class="fb-btns">
        <button type="button" onclick="fbSend(0, this)">😊 적정해요</button>
        <button type="button" onclick="fbSend(-1, this)">📉 너무 낮아요</button>
        <button type="button" onclick="fbSend(1, this)">📈 너무 높아요</button>
        <span class="fb-done" id="fb-done">의견 감사합니다!</span>
    </div>
    <?php if ($hasValue): ?>
    <div class="fb-value" id="fb-value">
        <span class="fb-value-label">그렇다면 실제로는 얼마가 적정할까요? (선택)</span>
        <input type="number" id="fb-value-input" min="1" placeholder="<?= htmlspecialchars($valueLabel) ?>">
        <span class="fb-unit"><?= htmlspecialchars($valueUnit) ?></span>
        <button type="button" class="fb-value-btn" onclick="fbSendValue()">보내기</button>
    </div>
    <?php endif; ?>
</div>
<style>
    .fb-widget { margin-top: 14px; background: #fff; border: 1px dashed #cbd5e1; border-radius: 10px; padding: 16px 18px; }
    .fb-q { font-size: 0.88em; font-weight: bold; color: #475569; margin-bottom: 10px; }
    .fb-q small { font-weight: normal; color: #94a3b8; }
    .fb-btns { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .fb-btns button { padding: 8px 14px; border: 1px solid #d1d5db; background: #f8fafc; color: #374151;
                      border-radius: 20px; font-size: 0.85em; cursor: pointer; font-weight: bold; width: auto; margin: 0; }
    .fb-btns button:hover { background: #eef2f7; }
    .fb-btns button.sel { background: #1e293b; border-color: #1e293b; color: #fff; }
    .fb-done { display: none; font-size: 0.8em; color: #059669; font-weight: bold; }
    .fb-value { display: none; margin-top: 10px; gap: 8px; align-items: center; flex-wrap: wrap; }
    .fb-value-label { font-size: 0.8em; color: #64748b; flex-basis: 100%; }
    .fb-value input { width: 160px; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9em; }
    .fb-unit { font-size: 0.82em; color: #64748b; }
    .fb-value-btn { padding: 7px 14px; border: 1px solid #1e293b; background: #1e293b; color: #fff;
                    border-radius: 6px; font-size: 0.82em; cursor: pointer; width: auto; margin: 0; }
</style>
<script>
    var FB_TOOL = '<?= $t ?>', FB_SIDO = '<?= $s ?>', FB_SIGUNGU = '<?= $g ?>', FB_VERDICT = null;

    function fbPost(params, cb) {
        var body = new URLSearchParams();
        Object.keys(params).forEach(function(k) { body.set(k, params[k]); });
        fetch('feedback_api.php', { method: 'POST', body: body })
            .then(function(r) { return r.json(); })
            .then(cb)
            .catch(function() {});
    }

    function fbSend(v, btn) {
        FB_VERDICT = v;
        document.querySelectorAll('.fb-btns button').forEach(function(b) { b.classList.remove('sel'); });
        btn.classList.add('sel');
        fbPost({ tool: FB_TOOL, verdict: v, region_sido: FB_SIDO, region_sigungu: FB_SIGUNGU }, function(j) {
            if (!j.ok) return;
            document.getElementById('fb-done').style.display = 'inline';
            var fv = document.getElementById('fb-value');
            if (fv) fv.style.display = (v !== 0) ? 'flex' : 'none'; // 낮음/높음일 때만 적정값 질문
        });
    }

    function fbSendValue() {
        var inp = document.getElementById('fb-value-input');
        var val = parseInt(inp.value || '0', 10);
        if (!val || val <= 0) { inp.focus(); return; }
        fbPost({ tool: FB_TOOL, verdict: FB_VERDICT === null ? 0 : FB_VERDICT,
                 region_sido: FB_SIDO, region_sigungu: FB_SIGUNGU, suggested_value: val }, function(j) {
            if (!j.ok) return;
            document.getElementById('fb-value').innerHTML =
                '<span style="font-size:0.82em; color:#059669; font-weight:bold;">제시해주신 값이 반영됐습니다. 감사합니다!</span>';
            document.getElementById('fb-value').style.display = 'flex';
        });
    }
</script>
<?php
    return ob_get_clean();
}
