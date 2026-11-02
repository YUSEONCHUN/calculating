/**
 * 약국 권리금 계산기 — 발표 슬라이드 생성기 (22장)
 *
 *   node doc/slides/deck.js
 *
 * 구성안: doc/04-슬라이드-구성.md  (슬라이드 번호가 1:1 대응)
 * 출처:   README.md · doc/01~03 · doc/data/2026-09-권리금-적정성-조사.csv
 * 이미지: doc/images/*.jpg|png (실제 화면 캡처와 계수 곡선)
 *
 * 테마 색은 제품 화면에서 그대로 가져왔다 — 틸(메인) · 앰버(v1) · 밝은 틸(v2) · 그린(v3).
 */
const path = require('path');
const pptxgen = require('pptxgenjs');
const { applyTheme } = require('/Users/kwondev/Library/Application Support/Claude/local-agent-mode-sessions/skills-plugin/2923eea7-42c1-4c35-b1b9-05aa2899c8c6/c35cfeeb-1e4e-4006-90dc-c3831c93ee2f/skills/pptx/scripts/apply_theme.js');

const IMG = path.join(__dirname, '..', 'images');
const img = (f) => path.join(IMG, f);
const OUT = path.join(__dirname, '권리금계산기_프로젝트.pptx');

const THEME = {
  name: '권리금 계산기',
  headFontFace: 'Cambria',
  bodyFontFace: 'Calibri',
  colors: {
    dk1: '0F172A', lt1: 'FFFFFF', dk2: '334155', lt2: 'F1F5F9',
    accent1: '0F766E',  // 메인 틸
    accent2: 'B45309',  // v1 앰버
    accent3: '0D9488',  // v2 밝은 틸
    accent4: '166534',  // v3 그린
    accent5: '94A3B8',  // 보조 회색
    accent6: 'C2410C',  // 경고·문제
    hlink: '0F766E', folHlink: '94A3B8',
  },
};

const pres = new pptxgen();
pres.layout = 'LAYOUT_WIDE';            // 13.3 x 7.5
pres.theme = { headFontFace: THEME.headFontFace, bodyFontFace: THEME.bodyFontFace };
pres.author = 'Yudev';
pres.title = '약국 권리금 계산기 프로젝트';
const C = pres.SchemeColor;
const T = THEME.colors;

const FOOT = '약국 권리금 계산기 · Yudev · 2026-11';
const SEC = { intro: '개발 목적과 배경', hist: '개발 히스토리', data: '데이터와 API', next: '앞으로' };

// ── 레이아웃 ──
pres.defineSlideMaster({
  title: 'TITLE_DARK', background: { color: T.dk1 },
  objects: [
    { placeholder: { options: { name: 'title', type: 'title', x: 0.9, y: 2.5, w: 11.5, h: 1.5,
        fontSize: 44, bold: true, color: C.background1 }, text: '' } },
    { placeholder: { options: { name: 'body', type: 'body', x: 0.9, y: 4.0, w: 11.5, h: 1.6,
        fontSize: 17, color: T.accent5 }, text: '' } },
  ],
});

pres.defineSlideMaster({
  title: 'CONTENT', background: { color: C.background1 },
  objects: [
    { placeholder: { options: { name: 'title', type: 'title', x: 0.7, y: 0.42, w: 11.9, h: 0.78,
        fontSize: 30, bold: true, color: C.text1 }, text: '' } },
    { text: { text: FOOT, options: { x: 0.7, y: 6.98, w: 8, h: 0.3, fontSize: 9,
        color: T.accent5, isTextBox: true, margin: 0 } } },
    { placeholder: { options: { name: 'slideNum', type: 'sldNum', x: 12.3, y: 6.98, w: 0.6, h: 0.3,
        fontSize: 9, color: T.accent5, align: 'right' }, text: '' } },
  ],
});

pres.defineSlideMaster({
  title: 'CHART', background: { color: C.background1 },
  objects: [
    { placeholder: { options: { name: 'title', type: 'title', x: 0.7, y: 0.42, w: 11.9, h: 0.78,
        fontSize: 30, bold: true, color: C.text1 }, text: '' } },
    { placeholder: { options: { name: 'chart', type: 'chart', x: 0.6, y: 1.75, w: 7.4, h: 4.7,
        color: C.text1 }, text: '' } },
    { placeholder: { options: { name: 'body', type: 'body', x: 8.4, y: 1.8, w: 4.3, h: 4.6,
        fontSize: 14, color: C.text2 }, text: '' } },
    { text: { text: FOOT, options: { x: 0.7, y: 6.98, w: 8, h: 0.3, fontSize: 9,
        color: T.accent5, isTextBox: true, margin: 0 } } },
    { placeholder: { options: { name: 'slideNum', type: 'sldNum', x: 12.3, y: 6.98, w: 0.6, h: 0.3,
        fontSize: 9, color: T.accent5, align: 'right' }, text: '' } },
  ],
});

// ── 공통 스타일·헬퍼 ──
const chartBase = () => ({
  showTitle: false, showLegend: false, barDir: 'col',
  catAxisLabelColor: T.dk2, valAxisLabelColor: T.dk2,
  catAxisLabelFontSize: 12, valAxisLabelFontSize: 11,
  catAxisLabelFontFace: '+mn-lt', valAxisLabelFontFace: '+mn-lt',
  dataLabelFontFace: '+mn-lt', dataLabelFontSize: 13, dataLabelColor: T.dk2,
  valGridLine: { color: 'E2E8F0', size: 1 }, catGridLine: { style: 'none' },
});

function card(slide, x, y, w, h, opts) {
  const o = opts || {};
  slide.addShape(pres.shapes.ROUNDED_RECTANGLE, {
    x, y, w, h, rectRadius: 0.08,
    fill: { color: o.fill || C.background2 },
    line: { color: o.line || 'E2E8F0', width: 1 },
    objectName: o.name || 'card',
  });
}
function lead(slide, text) {
  slide.addText(text, { x: 0.7, y: 1.22, w: 11.9, h: 0.4, fontSize: 15, color: C.text2,
    isTextBox: true, margin: 0 });
}
function kicker(slide, x, y, text, color, w) {
  slide.addText(text, { x, y, w: w || 5, h: 0.28, fontSize: 11, bold: true,
    color: color || T.accent1, charSpacing: 1, isTextBox: true, margin: 0 });
}
function tableOpts(extra) {
  return Object.assign({
    fontSize: 13, color: C.text1, align: 'left', valign: 'middle',
    border: { type: 'solid', color: 'E2E8F0', pt: 1 }, fill: { color: 'FFFFFF' },
  }, extra || {});
}
function head(cells) { return cells.map((t) => ({ text: t, options: { bold: true, color: C.text1 } })); }

// ═══════════════════════════════════════════ 섹션 1 ═══
pres.addSection({ title: SEC.intro });

// ── 1. 표지 ──
{
  const s = pres.addSlide({ masterName: 'TITLE_DARK', sectionTitle: SEC.intro });
  s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: 0.9, y: 1.75, w: 2.1, h: 0.45, rectRadius: 0.2,
    fill: { color: T.accent1 }, line: { color: T.accent1 }, objectName: 'badge' });
  s.addText('PROJECT REVIEW', { x: 0.9, y: 1.75, w: 2.1, h: 0.45, fontSize: 10, bold: true,
    color: 'FFFFFF', align: 'center', valign: 'middle', charSpacing: 1, isTextBox: true, margin: 0 });
  s.addText('약국 권리금 계산기', { placeholder: 'title' });
  s.addText([
    { text: '"그 동네는 원래 그 정도"를 계산식으로', options: { breakLine: true, color: T.accent3, bold: true, fontSize: 20 } },
    { text: '개발 목적 · 히스토리 v1~v3 · 조사 검증 · 로드맵', options: { breakLine: true } },
    { text: 'Yudev · 2026년 11월', options: {} },
  ], { placeholder: 'body' });
  s.addNotes('약국 권리금 계산기 프로젝트 리뷰입니다. 왜 만들었는지, v1에서 v3까지 어떻게 발전시켰는지, 조사로 어떻게 검증했는지, 앞으로 어디로 갈지 순서로 말씀드리겠습니다.');
}

// ── 2. 문제 정의 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.intro });
  s.addText('가장 큰 금액이 걸린 결정을, 가장 경험 없는 상태에서', { placeholder: 'title' });
  lead(s, '약국 인수·개국은 대부분 생애 한두 번. 그런데 오가는 권리금은 수천만 원에서 수억 원입니다.');

  const items = [
    ['①', '제시', '중개사가 권리금 3억을 제시한다', T.accent1],
    ['②', '판단 불가', '비싼지 싼지 가늠할 기준이 없다', T.accent1],
    ['③', '감으로 회신', '"그 동네는 원래 그 정도"', T.accent1],
    ['④', '근거 부재', '왜 그 숫자인지 아무도 설명 못 한다', T.accent6],
  ];
  items.forEach((it, i) => {
    const x = 0.7 + i * 3.05;
    card(s, x, 2.0, 2.8, 2.35, { name: 'prob' + i });
    s.addShape(pres.shapes.OVAL, { x: x + 0.3, y: 2.3, w: 0.55, h: 0.55,
      fill: { color: it[3] }, line: { color: it[3] }, objectName: 'num' + i });
    s.addText(it[0], { x: x + 0.3, y: 2.3, w: 0.55, h: 0.55, fontSize: 15, bold: true,
      color: 'FFFFFF', align: 'center', valign: 'middle', isTextBox: true, margin: 0 });
    s.addText(it[1], { x: x + 0.3, y: 3.02, w: 2.2, h: 0.35, fontSize: 16, bold: true,
      color: C.text1, isTextBox: true, margin: 0 });
    s.addText(it[2], { x: x + 0.3, y: 3.42, w: 2.25, h: 0.8, fontSize: 12.5, color: C.text2,
      isTextBox: true, margin: 0 });
  });

  card(s, 0.7, 4.85, 11.9, 1.45, { fill: 'FEF3C7', line: 'FDE68A', name: 'quote' });
  s.addText('감은 틀린 게 아닙니다 — 수치화되지 않아 검증도 전수도 안 될 뿐입니다',
    { x: 1.1, y: 5.12, w: 11.1, h: 0.45, fontSize: 19, bold: true, color: '92400E', isTextBox: true, margin: 0 });
  s.addText('신규 개국 약사에게는 접근 자체가 불가능한 지식입니다.',
    { x: 1.1, y: 5.62, w: 11.1, h: 0.4, fontSize: 14, color: '92400E', isTextBox: true, margin: 0 });
  s.addNotes('신규 약사가 실제로 겪는 흐름입니다. 핵심은 감이 틀렸다는 게 아니라, 검증도 전수도 안 된다는 점입니다.');
}

// ── 3. 약국 ≠ 일반 상가 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.intro });
  s.addText('약국은 권리금을 구성하는 요소 자체가 다르다', { placeholder: 'title' });
  lead(s, '일반 상가는 「월 매출 × 개월수」로 업종을 가리지 않습니다. 약국은 전제가 다릅니다.');

  const rows = [
    head(['구분', '일반 상가', '약국']),
    ['수익의 원천', '매출 전체', '조제료(처방 건수)가 핵심'],
    ['수요의 성격', '유동인구·상권', '인근 병·의원의 처방에 종속'],
    ['입지 대체성', '비슷한 자리로 이전 가능', '병원 앞 자리는 대체 불가'],
    ['핵심 리스크', '상권 쇠퇴', '병원 이전·폐업 → 수익 소멸'],
    ['시설', '범용 인테리어·집기', '조제 전용 설비 (ATC·약장)'],
    ['재고', '비중 낮음', '금액 크고 유효기간 리스크'],
  ];
  s.addTable(rows, tableOpts({ x: 0.7, y: 1.9, w: 11.9, colW: [2.6, 4.2, 5.1], rowH: 0.52 }));

  card(s, 0.7, 5.85, 11.9, 0.95, { fill: 'CCFBF1', line: '5EEAD4', name: 'conc' });
  s.addText('구성 요소가 다르면 가중치도 달라야 합니다 — 일반 계산기는 소형을 과대평가, 문전 대형을 과소평가합니다',
    { x: 1.1, y: 5.85, w: 11.1, h: 0.95, fontSize: 15, bold: true, color: '115E59',
      isTextBox: true, margin: 0, valign: 'middle' });
  s.addNotes('이 표가 출발점입니다. 구성 요소가 다르면 계산식도 달라야 합니다.');
}

// ── 4. 해결 접근 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.intro });
  s.addText('감이 담은 판단을 수식과 계수로 옮긴다', { placeholder: 'title' });
  lead(s, '감을 부정하는 것이 목표가 아닙니다. 누구나 쓰고 검증할 수 있는 형태로 바꾸는 것이 목표입니다.');

  const blocks = [
    ['월 환산수익', '조제 수익 + OTC 마진\n− 월세 − 보증금 기회비용', T.accent1],
    ['지역 회수개월수', '5개 권역 × 3단계\n(p30 / p50 / p75)', T.accent3],
    ['조제료 규모 계수', '규모가 클수록 가산\n작을수록 할인', T.accent4],
  ];
  blocks.forEach((b, i) => {
    const x = 0.7 + i * 4.1;
    card(s, x, 1.95, 3.6, 1.75, { fill: 'FFFFFF', line: b[2], name: 'f' + i });
    s.addText(b[0], { x: x + 0.25, y: 2.2, w: 3.1, h: 0.4, fontSize: 18, bold: true,
      color: b[2], isTextBox: true, margin: 0 });
    s.addText(b[1], { x: x + 0.25, y: 2.65, w: 3.1, h: 0.85, fontSize: 12.5, color: C.text2,
      isTextBox: true, margin: 0 });
    if (i < 2) {
      s.addText('×', { x: x + 3.6, y: 2.55, w: 0.5, h: 0.5, fontSize: 24, bold: true,
        color: T.accent5, align: 'center', isTextBox: true, margin: 0 });
    }
  });

  kicker(s, 0.7, 4.05, '결과는 금액 하나가 아니라 범위로');
  card(s, 0.7, 4.4, 11.9, 1.0, { fill: C.background2, name: 'rangebar' });
  s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: 1.0, y: 4.75, w: 11.3, h: 0.3, rectRadius: 0.15,
    fill: { color: T.accent1 }, line: { color: T.accent1 }, objectName: 'bar' });
  s.addText('최소 — 빠른 매도', { x: 1.0, y: 5.05, w: 3.0, h: 0.3, fontSize: 12, bold: true,
    color: C.text2, isTextBox: true, margin: 0 });
  s.addText('중간', { x: 5.9, y: 5.05, w: 1.5, h: 0.3, fontSize: 12, color: C.text2,
    align: 'center', isTextBox: true, margin: 0 });
  s.addText('최대 — 높은 호가', { x: 9.3, y: 5.05, w: 3.0, h: 0.3, fontSize: 12, bold: true,
    color: C.text2, align: 'right', isTextBox: true, margin: 0 });

  s.addText([
    { text: '범위 안에서 위치는 체크리스트로 좁힙니다', options: { bold: true, breakLine: true, color: C.text1, fontSize: 15 } },
    { text: '잔여 임대차 · 처방 추이 · 병원 의존 구조 · 매도 시급성 · 시설 노후도 — 해당 조건을 체크하면 예상 구간이 좁아집니다', options: { fontSize: 13 } },
  ], { x: 0.7, y: 5.65, w: 11.9, h: 1.0, color: C.text2, isTextBox: true, margin: 0 });
  s.addNotes('계산 구조는 이 한 줄입니다. 그리고 결과를 단일 금액이 아니라 범위로 내놓습니다. 권리금에 정답 금액은 없고, 빨리 팔아야 하면 낮아지고 기다릴 수 있으면 높아지기 때문입니다.');
}

// ═══════════════════════════════════════════ 섹션 2 ═══
pres.addSection({ title: SEC.hist });

// ── 5. 전체 흐름 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.hist });
  s.addText('정답지 → 계산식 → 보정 → 확장 순서로 만들었다', { placeholder: 'title' });
  lead(s, '계산식을 먼저 세우지 않았습니다. 검증할 데이터를 먼저 만들고 거기에 맞춰 고쳐 나갔습니다.');

  const steps = [
    ['0단계', '현장의 감을 숫자로', '가상 20케이스 조사 → 두 계산식을 같은 데이터에 돌려 선택', T.dk2],
    ['v1', '회수배수 계산식 + 구간 배율', '60응답으로 계수 보정 → 적중률 68% → 85%', T.accent2],
    ['v2', '절벽을 완만하게', '기준점은 보존하고 사이만 선형보간', T.accent3],
    ['v3', '외부 데이터 결합', '공공데이터·카카오 실측 + Gemini 서술', T.accent4],
    ['상시화', '검증을 반복 가능하게', '설문 → DB 적재 → 집계 → 계수 보정', T.accent1],
  ];
  steps.forEach((st, i) => {
    const y = 1.85 + i * 1.0;
    s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: 0.7, y, w: 1.55, h: 0.8, rectRadius: 0.08,
      fill: { color: st[3] }, line: { color: st[3] }, objectName: 'stage' + i });
    s.addText(st[0], { x: 0.7, y, w: 1.55, h: 0.8, fontSize: 15, bold: true, color: 'FFFFFF',
      align: 'center', valign: 'middle', isTextBox: true, margin: 0 });
    s.addText(st[1], { x: 2.5, y: y + 0.04, w: 4.2, h: 0.4, fontSize: 16, bold: true,
      color: C.text1, isTextBox: true, margin: 0 });
    s.addText(st[2], { x: 2.5, y: y + 0.42, w: 9.9, h: 0.38, fontSize: 13, color: C.text2,
      isTextBox: true, margin: 0 });
    if (i < 4) {
      s.addShape(pres.shapes.LINE, { x: 1.47, y: y + 0.8, w: 0, h: 0.2,
        line: { color: 'CBD5E1', width: 1.5 }, objectName: 'link' + i });
    }
  });
  s.addNotes('전체 흐름입니다. 각 단계에서 무엇을 발견했고 어떻게 고쳤는지를 이어서 보시겠습니다.');
}

// ── 6. 0단계 — 조사 설계 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.hist });
  s.addText('계산식보다 정답지를 먼저 만들었다', { placeholder: 'title' });
  lead(s, '식을 먼저 만들면 그 식이 맞는지 확인할 방법이 영영 생기지 않습니다.');

  const rows = [
    head(['케이스', '지역', '월 조제료', '일반약 일매출', '월세', '보증금', '모델 적정호가', '내가 보는 적정 권리금']),
    ['2', '강남 등 서울 핵심', '2,900', '29', '460', '8,500', '70,300', ''],
    ['7', '서울 일반', '1,200', '18', '330', '3,500', '19,600', ''],
    ['9', '수도권', '800', '13', '160', '4,000', '7,739', ''],
    ['14', '광역시', '2,700', '34', '200', '4,000', '57,500', ''],
    ['17', '지방 중소도시', '1,500', '19', '140', '2,500', '24,400', ''],
  ];
  s.addTable(rows, tableOpts({ x: 0.7, y: 1.85, w: 11.9, fontSize: 12,
    colW: [0.9, 2.3, 1.3, 1.5, 1.0, 1.2, 1.6, 2.1], rowH: 0.45 }));

  const notes = [
    ['설계', '가상 20케이스 = 지역 5권역 × 규모별 · 조건은 랜덤·반올림'],
    ['의도적 공개', '모델 산출가를 숨기지 않고 함께 제시'],
    ['표본', '1차 2명 40응답 (모델 선택) → 2차 3명 60응답 (계수 보정)'],
  ];
  notes.forEach((n, i) => {
    const x = 0.7 + i * 4.1;
    card(s, x, 4.75, 3.7, 1.0, { name: 'n' + i });
    s.addText(n[0], { x: x + 0.22, y: 4.85, w: 3.3, h: 0.3, fontSize: 13, bold: true,
      color: T.accent1, isTextBox: true, margin: 0 });
    s.addText(n[1], { x: x + 0.22, y: 5.15, w: 3.3, h: 0.55, fontSize: 11.5, color: C.text2,
      isTextBox: true, margin: 0 });
  });

  s.addText('목적은 정답 수집이 아니라 오차 측정 — 모델가를 옆에 두면 "높다 / 낮다"는 판단이 함께 나옵니다',
    { x: 0.7, y: 5.95, w: 11.9, h: 0.5, fontSize: 15, bold: true, color: T.accent1,
      isTextBox: true, margin: 0 });
  s.addNotes('실제 조사지의 일부입니다. 마지막 열이 비어 있고, 그 왼쪽에 모델이 계산한 금액이 미리 적혀 있습니다. 백지에서 적게 하면 답이 흩어지는데, 숫자를 옆에 두면 판단이 함께 나옵니다.');
}

// ── 7. 0단계 결과 — 모델 선택 ──
{
  const s = pres.addSlide({ masterName: 'CHART', sectionTitle: SEC.hist });
  s.addText('일반 상가식은 평균이 아니라 구조가 틀렸다', { placeholder: 'title' });
  s.addChart(pres.charts.BAR, [{
    name: '범위 적중 응답 수 (40응답 중)',
    labels: ['모델 A\n매출총이익 × 12개월', '모델 B\n회수배수 × 규모 계수'],
    values: [7, 32],
  }], Object.assign(chartBase(), {
    placeholder: 'chart',
    chartColors: [T.accent6, T.accent1],
    showValue: true, dataLabelPosition: 'outEnd', dataLabelFontSize: 18,
    valAxisMaxVal: 40, barGapWidthPct: 120,
  }));
  s.addText([
    { text: '틀린 방향이 서로 반대였다', options: { bold: true, fontSize: 17, color: C.text1, breakLine: true } },
    { text: '모델 A ÷ 조사원 응답 (중앙값)', options: { fontSize: 12, color: T.accent5, breakLine: true } },
    { text: '소형 (조제료 <1,000만원)', options: { bold: true, breakLine: true, color: T.accent6 } },
    { text: '3.03배 과대  (1.59 ~ 4.16)', options: { fontSize: 18, bold: true, color: T.accent6, breakLine: true } },
    { text: '일반 (조제료 ≥1,000만원)', options: { bold: true, breakLine: true, color: T.accent1 } },
    { text: '0.67배 과소  (0.46 ~ 1.15)', options: { fontSize: 18, bold: true, color: T.accent1, breakLine: true } },
    { text: '한쪽은 3배 높고 한쪽은 1/3 낮다 — 어떤 배수로도 동시에 맞출 수 없다', options: { fontSize: 13, color: C.text2 } },
  ], { placeholder: 'body' });
  s.addNotes('적중률 자체보다 중요한 건 틀린 방향입니다. 한 방향으로 치우쳤다면 배수만 고치면 되는데, 소형은 3배 높고 대형은 3분의 1 낮습니다. 지역·규모·저수익을 전부 상수로 둔 식의 구조적 한계입니다.');
}

// ── 8. v1 계산식 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.hist });
  s.addText('v1 — 살아남은 구조를 5단계 식으로', { placeholder: 'title' });
  lead(s, '권리금 = 월 환산수익 × 지역 회수개월수 × 조제료 규모 계수');

  const steps = [
    ['①', '월 환산수익', '조제 수익 + OTC 마진 − (월세 + 보증금 기회비용)'],
    ['②', '규모 계수', '조제료 5구간 고정값 — 0.50 / 1.00 / 1.15 / 1.25 / 1.35'],
    ['③', '3단계 가격', '지역 p30 / p50 / p75 × 저수익 임계 할인'],
    ['④', '의사 고령 할인', '65세 이상 ×0.7 · 75세 이상 ×0.5'],
    ['⑤', '보정', '지역 피드백 ±10% · 설문 ±15%'],
  ];
  steps.forEach((st, i) => {
    const y = 1.95 + i * 0.92;
    s.addText(st[0], { x: 0.7, y, w: 0.45, h: 0.4, fontSize: 17, bold: true, color: T.accent2,
      isTextBox: true, margin: 0 });
    s.addText(st[1], { x: 1.2, y, w: 2.3, h: 0.4, fontSize: 15, bold: true, color: C.text1,
      isTextBox: true, margin: 0 });
    s.addText(st[2], { x: 1.2, y: y + 0.38, w: 5.9, h: 0.4, fontSize: 12.5, color: C.text2,
      isTextBox: true, margin: 0 });
  });

  kicker(s, 7.5, 1.72, '지역 회수개월수 (2026-09 조사 반영)', null, 5.1);
  const rows = [
    head(['지역', '빠른매도', '적정', '높은호가']),
    ['강남 등 서울 핵심', '20.2', '25.8', '33.2'],
    ['서울 일반', '16.9', '21.5', '27.7'],
    ['수도권 (경기·인천)', '17.7', '21.0', '27.1'],
    ['광역시', '13.6', '20.2', '26.6'],
    ['지방 중소도시', '12.2', '18.2', '23.9'],
  ];
  s.addTable(rows, tableOpts({ x: 7.5, y: 2.05, w: 5.1, fontSize: 12,
    colW: [2.1, 1.0, 1.0, 1.0], rowH: 0.46, align: 'center' }));
  s.addText('핵심 구현은 config.php 의 gwBaseQuote() 한 곳 — 모든 계산기가 이 함수를 공유합니다',
    { x: 7.5, y: 5.05, w: 5.1, h: 0.8, fontSize: 12, color: C.text2, isTextBox: true, margin: 0 });
  s.addNotes('v1은 가장 단순한 형태입니다. 규모 계수를 구간별 고정값으로 둔 것이 설명하기 쉬웠고, 그게 나중에 문제가 됩니다.');
}

// ── 9. v1 검증 ──
{
  const s = pres.addSlide({ masterName: 'CHART', sectionTitle: SEC.hist });
  s.addText('두 군데만 고쳐 적중률 68% → 85%', { placeholder: 'title' });
  s.addChart(pres.charts.BAR, [
    { name: '보정 전', labels: ['전체', '소형 (<1,000)', '일반 (≥1,000)'], values: [1.129, 0.647, 1.156] },
    { name: '보정 후', labels: ['전체', '소형 (<1,000)', '일반 (≥1,000)'], values: [0.999, 0.992, 1.006] },
  ], Object.assign(chartBase(), {
    placeholder: 'chart',
    chartColors: [T.accent5, T.accent1],
    showValue: true, dataLabelPosition: 'outEnd', dataLabelFormatCode: '0.00',
    showLegend: true, legendPos: 'b', legendColor: T.dk2, legendFontSize: 12,
    valAxisMaxVal: 1.4, valAxisMinVal: 0,
  }));
  s.addText([
    { text: '응답 ÷ 모델 (1.00이 완전 일치)', options: { fontSize: 12, color: T.accent5, breakLine: true } },
    { text: '발견한 두 패턴', options: { bold: true, fontSize: 16, color: C.text1, breakLine: true } },
    { text: '1,000만원 이상 17케이스 — 모델이 15% 낮음', options: { bullet: true, breakLine: true, fontSize: 13 } },
    { text: '1,000만원 미만 3케이스 — 모델이 35% 높음', options: { bullet: true, breakLine: true, fontSize: 13 } },
    { text: '조치', options: { bold: true, fontSize: 16, color: C.text1, breakLine: true } },
    { text: '전 지역 회수개월수 ×1.15', options: { bullet: true, breakLine: true, fontSize: 13 } },
    { text: '소형 구간 계수 0.88 → 0.50', options: { bullet: true, breakLine: true, fontSize: 13 } },
    { text: '적중 41/60 → 51/60', options: { bold: true, fontSize: 17, color: T.accent1, breakLine: true } },
    { text: '조사원 3은 9/20 → 18/20 — 높게 보는 성향이 보정으로 흡수', options: { fontSize: 12, color: C.text2 } },
  ], { placeholder: 'body' });
  s.addNotes('지역별 편차가 1.08에서 1.17로 좁아서, 특정 지역 문제가 아니라 전체 수준 문제로 봤습니다. 그래서 전 지역을 일괄 상향하고 소형 계수만 따로 내렸습니다.');
}

// ── 10. v1 절벽 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.hist });
  s.addText('조제료 1만원 차이로 권리금이 2배가 된다', { placeholder: 'title' });
  lead(s, '같은 조건에서 조제료만 1만원 바꾼 결과 — 서울 일반 · 일매출 18만원 · 월세 100만원 · 보증금 3,500만원');

  s.addImage({ path: img('v1-cliff-compare.jpg'), x: 0.95, y: 1.8, w: 11.4, h: 3.93,
    altText: 'v1 계산 결과 비교 — 조제료 999만원(규모 계수 0.50, 9,118만원~1.49억원)과 1,000만원(1.00, 1.83~2.99억원)' });

  const jumps = [['1,000만원', '0.50 → 1.00', '+100%', T.accent6],
                 ['2,500만원', '1.00 → 1.15', '+15%', T.dk2],
                 ['3,500만원', '1.15 → 1.25', '+9%', T.dk2],
                 ['5,000만원', '1.25 → 1.35', '+8%', T.dk2]];
  jumps.forEach((j, i) => {
    const x = 0.7 + i * 3.05;
    card(s, x, 5.9, 2.8, 0.85, { fill: i === 0 ? 'FEE2E2' : C.background2,
      line: i === 0 ? 'FCA5A5' : 'E2E8F0', name: 'jump' + i });
    s.addText(j[0] + ' 경계', { x: x + 0.2, y: 5.98, w: 1.7, h: 0.3, fontSize: 11.5,
      color: C.text2, isTextBox: true, margin: 0 });
    s.addText(j[1], { x: x + 0.2, y: 6.28, w: 1.7, h: 0.3, fontSize: 11.5, color: C.text2,
      isTextBox: true, margin: 0 });
    s.addText(j[2], { x: x + 1.75, y: 6.05, w: 0.9, h: 0.5, fontSize: 17, bold: true,
      color: j[3], align: 'right', valign: 'middle', isTextBox: true, margin: 0 });
  });
  s.addNotes('구간을 나눈 편의가 그대로 결과의 왜곡이 됐습니다. 실무에서 999만원과 1,000만원 약국이 두 배 차이 날 리가 없습니다. 쓰는 사람에게는 신뢰 문제로 직결됩니다.');
}

// ── 11. v2 접근 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.hist });
  s.addText('바꾼 것은 계수가 아니라 가정이었다', { placeholder: 'title' });
  lead(s, '5개 계수는 조사로 검증된 자산입니다. 그대로 두고 절벽만 없애는 방법을 찾았습니다.');

  const rows = [
    head(['안', '방법', '장점', '버린 이유']),
    ['1', '구간을 더 잘게 쪼갠다', '구현이 가장 쉬움', '절벽이 작아질 뿐 사라지지 않음 · 계수표 4배'],
    ['2', '회귀로 곡선 적합 (로그·시그모이드)', '수학적으로 매끄러움', '검증값 소멸 · 20케이스로 곡선 추정은 과적합'],
    [{ text: '3', options: { bold: true } },
     { text: '기준점은 두고 사이만 직선으로', options: { bold: true } },
     { text: '검증 결과 100% 보존', options: { bold: true } },
     { text: '채택', options: { bold: true, color: T.accent1 } }],
  ];
  s.addTable(rows, tableOpts({ x: 0.7, y: 1.9, w: 11.9, fontSize: 12.5,
    colW: [0.7, 3.6, 3.0, 4.6], rowH: 0.62 }));

  card(s, 0.7, 4.5, 11.9, 1.85, { fill: '0F172A', line: '0F172A', name: 'code' });
  s.addText([
    { text: '// 두 기준점 사이를 직선으로 잇는다', options: { color: '94A3B8', breakLine: true } },
    { text: 'lerp(x, x0, y0, x1, y1) = y0 + (x − x0) / (x1 − x0) × (y1 − y0)', options: { color: '5EEAD4', breakLine: true } },
    { text: '', options: { breakLine: true } },
    { text: '5개 값을 구간 대표값이 아니라 1,000 / 2,500 / 3,500 / 5,000만원 지점의 기준점으로 재해석', options: { color: 'E2E8F0' } },
  ], { x: 1.05, y: 4.7, w: 11.2, h: 1.5, fontSize: 14, fontFace: 'Courier New',
       isTextBox: true, margin: 0 });
  s.addNotes('핵심 판단은 "검증으로 얻은 5개 값은 바꿀 대상이 아니다"였습니다. 그래서 값을 그대로 두고 해석만 바꿨습니다.');
}

// ── 12. v2 곡선 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.hist });
  s.addText('기준점에서 만나고, 사이에서만 달라진다', { placeholder: 'title' });
  s.addImage({ path: img('presc-mult-curve.png'), x: 1.85, y: 1.35, w: 9.6, h: 4.48,
    altText: '조제료 규모 계수 곡선 — v1 계단형과 v2 선형보간이 1,000/2,500/3,500/5,000만원 기준점에서 만난다' });

  const facts = [
    ['1,000만원 경계 점프', '+100% → +0.05%'],
    ['나머지 경계', '모두 +0.01% 이하'],
    ['동일 조건 999만원', 'v1 9,118만원 → v2 1.82억원'],
  ];
  facts.forEach((f, i) => {
    const x = 0.7 + i * 4.1;
    card(s, x, 5.95, 3.7, 0.85, { fill: 'CCFBF1', line: '5EEAD4', name: 'fact' + i });
    s.addText(f[0], { x: x + 0.22, y: 6.03, w: 3.3, h: 0.3, fontSize: 11.5, color: '115E59',
      isTextBox: true, margin: 0 });
    s.addText(f[1], { x: x + 0.22, y: 6.33, w: 3.3, h: 0.35, fontSize: 14, bold: true,
      color: '115E59', isTextBox: true, margin: 0 });
  });
  s.addNotes('지역 회수개월수, 저수익 할인, 고령 할인, 보정은 v1과 완전히 같습니다. 바뀐 것은 계수를 구하는 함수 하나뿐입니다.');
}

// ── 13. v2 검증 ──
{
  const s = pres.addSlide({ masterName: 'CHART', sectionTitle: SEC.hist });
  s.addText('매끄러운 식이 정확한 식은 아니었다', { placeholder: 'title' });
  s.addChart(pres.charts.BAR, [
    { name: 'v1 계단형', labels: ['전체', '소형 (<1,000)', '일반 (≥1,000)'], values: [0.999, 0.992, 1.006] },
    { name: 'v2 곡선형', labels: ['전체', '소형 (<1,000)', '일반 (≥1,000)'], values: [0.926, 0.525, 0.943] },
  ], Object.assign(chartBase(), {
    placeholder: 'chart',
    chartColors: [T.accent1, T.accent6],
    showValue: true, dataLabelPosition: 'outEnd', dataLabelFormatCode: '0.00',
    showLegend: true, legendPos: 'b', legendColor: T.dk2, legendFontSize: 12,
    valAxisMaxVal: 1.2, valAxisMinVal: 0,
  }));
  s.addText([
    { text: '응답 ÷ 모델 (1.00이 완전 일치)', options: { fontSize: 12, color: T.accent5, breakLine: true } },
    { text: '범위 적중  v1 51/60  ·  v2 42/60', options: { bold: true, fontSize: 16, color: C.text1, breakLine: true } },
    { text: '차이는 전부 소형 구간', options: { bold: true, fontSize: 15, color: T.accent6, breakLine: true } },
    { text: 'v2는 0→1,000만원을 직선으로 이어 조제료 800만원에 계수 0.90', options: { bullet: true, breakLine: true, fontSize: 13 } },
    { text: 'v1의 0.50은 소형 응답으로 직접 보정한 값 — 서로 충돌', options: { bullet: true, breakLine: true, fontSize: 13 } },
    { text: '소형 표본 3케이스 9응답 → 지금 데이터로는 결론 불가', options: { bullet: true, breakLine: true, fontSize: 13 } },
    { text: '→ v1을 기본값 유지, v2는 비교용으로 존치', options: { bold: true, fontSize: 15, color: T.accent1 } },
  ], { placeholder: 'body' });
  s.addNotes('v2는 목표였던 경계 점프 제거에는 성공했지만 적중률은 떨어졌습니다. 검증 데이터를 먼저 만들어 뒀기 때문에 배포 전에 알 수 있었습니다. 소형 표본이 30건 이상 쌓이면 둘 중 하나를 데이터로 결정할 수 있습니다.');
}

// ── 14. v3 외부 데이터 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.hist });
  s.addText('핵심 변수는 사용자가 모르는 정보였다', { placeholder: 'title' });
  lead(s, '"주변에 병원이 몇 곳인가"는 인수자가 알 수 없습니다 — 주소만으로 가져올 수 있는 데이터를 찾았습니다.');

  const rows = [
    head(['데이터', '제공', '쓰임', '상태']),
    ['병·의원 목록', '국립중앙의료원', '행정구역 내 병·의원 수', '적용'],
    ['약국 목록', '국립중앙의료원', '행정구역 내 약국 수', '적용'],
    ['상가(상권)정보', '소상공인시장진흥공단', '반경 500m 업소·업종 분포', '적용'],
    ['주소 → 좌표', '카카오', '상권 조회용 좌표 확보', '적용'],
    ['처방·청구 통계', '심평원·건보공단', '병원 의존도 연속값화', '약국 단위 공개 없음'],
    ['권리금 실거래', '—', '모델 검증 축 추가', '공개 DB 부재'],
  ];
  s.addTable(rows, tableOpts({ x: 0.7, y: 1.9, w: 7.6, fontSize: 12,
    colW: [2.0, 2.0, 2.3, 1.3], rowH: 0.5 }));

  kicker(s, 8.6, 1.72, '판정 기준 — 약국 1곳당 병·의원 수', null, 4.0);
  const verdicts = [
    ['전국 평균 1.7곳 기준', '', T.dk2],
    ['평균의 1.2배 이상', '처방 유입 여력 넉넉', T.accent4],
    ['평균 ±20% 이내', '전국 평균 수준', T.dk2],
    ['평균의 0.8배 이하', '경쟁이 센 편', T.accent6],
  ];
  verdicts.forEach((v, i) => {
    const y = 2.05 + i * 0.78;
    card(s, 8.6, y, 4.0, 0.68, { fill: i === 0 ? C.background2 : 'FFFFFF', name: 'v' + i });
    s.addText(v[0], { x: 8.8, y: y + (v[1] ? 0.04 : 0.16), w: 3.6, h: 0.32, fontSize: 12.5,
      bold: i === 0, color: v[2], isTextBox: true, margin: 0 });
    if (v[1]) {
      s.addText(v[1], { x: 8.8, y: y + 0.34, w: 3.6, h: 0.3, fontSize: 12, bold: true,
        color: v[2], isTextBox: true, margin: 0 });
    }
  });
  card(s, 0.7, 5.95, 11.9, 0.85, { fill: 'CCFBF1', line: '5EEAD4', name: 'policy' });
  s.addText('키가 없으면 해당 블록만 "미조회"로 바뀌고 권리금 계산은 그대로 동작합니다 — 실측은 부가 정보이지 계산의 전제가 아닙니다',
    { x: 1.1, y: 5.95, w: 11.1, h: 0.85, fontSize: 14, bold: true, color: '115E59',
      isTextBox: true, margin: 0, valign: 'middle' });
  s.addNotes('가장 갖고 싶은 데이터일수록 공개돼 있지 않습니다. 처방 통계는 약국 단위로 없고, 권리금 실거래 DB는 아예 존재하지 않습니다.');
}

// ── 15. v3 화면 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.hist });
  s.addText('실측으로 사용자 입력까지 검증한다', { placeholder: 'title' });

  kicker(s, 0.7, 1.3, '산출 — 범위와 조정 내역');
  s.addImage({ path: img('v3-quote.jpg'), x: 0.7, y: 1.62, w: 5.2, h: 3.76,
    altText: 'v3 산출 화면 — 권리금 범위와 상권·병원·임대차 조정 내역' });
  kicker(s, 6.4, 1.3, '실측 — 공공데이터포털 조회');
  s.addImage({ path: img('v3-realdata.jpg'), x: 6.4, y: 1.62, w: 6.2, h: 2.41,
    altText: '주변 의료기관 실측 — 강남구 병·의원 3,119곳 / 약국 515곳, 약국 1곳당 6.1곳' });
  kicker(s, 6.4, 4.2, 'AI 입력 검증 — 금액까지 다시 계산');
  s.addImage({ path: img('v3-validate.jpg'), x: 6.4, y: 4.52, w: 6.2, h: 1.93,
    altText: 'AI 입력 검증 — 선택한 상권 특성이 실제 입지와 다르다고 지적하고 수정 시 금액까지 제시' });

  const nums = [['병·의원 3,119곳 / 약국 515곳', '강남구 = 6.1 : 1'],
                ['전국 평균 1.7 : 1 대비', '3.6배 — 유입 여력 넉넉'],
                ['입력 수정 반영 시', '+10% → +15% · 4.07~6.68억 → 4.25~6.99억']];
  nums.forEach((n, i) => {
    const y = 5.55 + i * 0.44;
    s.addText(n[0], { x: 0.7, y, w: 2.6, h: 0.38, fontSize: 11, color: C.text2,
      isTextBox: true, margin: 0 });
    s.addText(n[1], { x: 3.3, y, w: 2.7, h: 0.38, fontSize: 11.5, bold: true,
      color: i === 2 ? T.accent4 : T.accent1, isTextBox: true, margin: 0 });
  });
  s.addNotes('사용자가 고른 상권 특성이 실제 입지와 다르면 AI가 지적하고, 수정했을 때 금액이 어떻게 바뀌는지까지 보여줍니다. 실측치가 그 판단의 근거입니다.');
}

// ── 16. AI 설계 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.hist });
  s.addText('말로 부탁한 건 깨지고, 구조로 막은 것만 남았다', { placeholder: 'title' });
  lead(s, '수치 계산은 전부 서버에서 끝내고, AI에는 확정된 산출치를 넘겨 서술만 맡깁니다.');

  card(s, 0.7, 1.75, 5.3, 4.5, { fill: '0F172A', line: '0F172A', name: 'prompt' });
  s.addText([
    { text: '시스템 프롬프트 (발췌)', options: { color: '5EEAD4', bold: true, breakLine: true, fontSize: 12 } },
    { text: '', options: { breakLine: true } },
    { text: '너는 대한민국 약국 양수도(권리금)', options: { color: 'E2E8F0', breakLine: true } },
    { text: '전문 컨설팅 시스템이다.', options: { color: 'E2E8F0', breakLine: true } },
    { text: '[Calculated Data]는 시스템이 산출한', options: { color: 'FBBF24', breakLine: true } },
    { text: '확정 수치이므로 재계산하거나', options: { color: 'FBBF24', breakLine: true } },
    { text: '다른 금액을 제시하지 마라.', options: { color: 'FBBF24', breakLine: true } },
    { text: '너의 역할은 상권 분석, 리스크 평가,', options: { color: 'E2E8F0', breakLine: true } },
    { text: '협상 전략 서술이다.', options: { color: 'E2E8F0', breakLine: true } },
    { text: '', options: { breakLine: true } },
    { text: '※ 단일 금액이 아니라 범위로 제시된', options: { color: '94A3B8', breakLine: true } },
    { text: '   값이다. 협상 서술도 이 범위를', options: { color: '94A3B8', breakLine: true } },
    { text: '   기준으로 하라.', options: { color: '94A3B8', breakLine: true } },
    { text: '', options: { breakLine: true } },
    { text: '[작성 지침]', options: { color: 'E2E8F0', breakLine: true } },
    { text: '1 상권 분석  2 리스크 평가  3 협상 전략', options: { color: 'E2E8F0', breakLine: true } },
    { text: '4 실사 체크리스트  5 자금 조달 조언', options: { color: 'E2E8F0', breakLine: true } },
    { text: '6 입력 검증  7·8 대안 선택지 반환', options: { color: 'E2E8F0', breakLine: true } },
    { text: '출력은 요구된 JSON 스키마만 반환하라.', options: { color: 'FBBF24', breakLine: true } },
    { text: 'temperature: 0 · responseSchema 8필드', options: { color: '5EEAD4' } },
  ], { x: 1.0, y: 1.92, w: 4.8, h: 4.3, fontSize: 10.5, fontFace: 'Courier New',
       isTextBox: true, margin: 0 });

  const rows = [
    head(['깨진 것', '막은 방법']),
    ['같은 입력인데 금액이 매번 달랐다', '계산을 PHP로 이관 · 재계산 금지 명시 · temperature 0'],
    ['자유 서술로 와서 화면에 못 꽂았다', 'responseSchema + required 8필드로 구조 강제'],
    ['범위를 "적정 5억"으로 단정했다', '수치 바로 아래에 범위 기준 지시 삽입'],
    ['검증이 "타당합니다"로만 끝났다', '목록에서 그대로 반환 + 수정 시 금액 재계산 표시'],
    ['긴 답변이 중간에 잘렸다', '토큰 상한을 .env 로 분리 · 지침을 번호 항목으로 분할'],
    ['의사 고령 시 금액이 과하게 떨어졌다', '엔진·조정 양쪽 할인 → 엔진 단일화 (이중 할인 제거)'],
  ];
  s.addTable(rows, tableOpts({ x: 6.3, y: 1.75, w: 6.3, fontSize: 11.5,
    colW: [2.75, 3.55], rowH: 0.63 }));
  s.addNotes('프롬프트는 한 번에 나오지 않았습니다. 여섯 번 깨졌고, 공통점은 하나였습니다. 말로 부탁한 부분은 전부 깨졌고 구조로 못 박은 부분만 남았습니다.');
}

// ── 17. v1~v3 종합 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.hist });
  s.addText('기초 산출은 하나의 엔진, 쓰임만 다르다', { placeholder: 'title' });

  const rows = [
    head(['항목', 'v1 계단형', 'v2 곡선형', 'v3 정밀']),
    ['기초 산출', '회수배수 (구간 고정)', '계수 함수만 교체', 'v1과 동일 엔진'],
    ['60응답 범위 적중', '51/60 (85%)', '42/60 (70%)', 'v1과 동일'],
    ['입력 변수', '5개', '5개', '15개+'],
    ['외부 데이터', '없음', '없음', '병·의원 · 약국 · 상권 · AI'],
    ['같은 입력 → 같은 결과', '○', '○', '○ (AI는 서술만)'],
    ['용도', '기본 시세', '경계 비교', '매물 실사 · 협상 준비'],
  ];
  s.addTable(rows, tableOpts({ x: 0.7, y: 1.75, w: 11.9, fontSize: 13,
    colW: [2.9, 2.9, 2.6, 3.5], rowH: 0.52 }));

  kicker(s, 0.7, 5.5, 'v3 검증 — 조사 응답과 1:1 대조가 불가능해 네 축으로 확인');
  const axes = [['기초 산출 일치', 'v1과 동일값'], ['재현성', '같은 입력 → 같은 금액'],
                ['실측 정확도', '강남 6.1:1 = 평균의 3.6배'], ['장애 내성', '미조회 안내 · 예비 모델 전환']];
  axes.forEach((a, i) => {
    const x = 0.7 + i * 3.05;
    card(s, x, 5.85, 2.8, 0.9, { fill: 'DCFCE7', line: '86EFAC', name: 'axis' + i });
    s.addText(a[0], { x: x + 0.2, y: 5.95, w: 2.45, h: 0.3, fontSize: 12.5, bold: true,
      color: '14532D', isTextBox: true, margin: 0 });
    s.addText(a[1], { x: x + 0.2, y: 6.25, w: 2.45, h: 0.4, fontSize: 11, color: '166534',
      isTextBox: true, margin: 0 });
  });
  s.addNotes('세 버전은 경쟁 관계가 아니라 역할 분담입니다. v3의 기초 시세는 v1과 완전히 같은 값이고, 화면에도 그 환산 행이 그대로 노출됩니다.');
}

// ═══════════════════════════════════════════ 섹션 3 ═══
pres.addSection({ title: SEC.data });

// ── 18. 원자료와 재현성 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.data });
  s.addText('발표의 모든 수치는 원자료에서 다시 계산된다', { placeholder: 'title' });
  lead(s, '조사 결과를 문장으로만 남기지 않고, 원자료와 계산 스크립트를 저장소에 함께 뒀습니다.');

  kicker(s, 0.7, 1.75, '원자료 — 2026-09 적정성 조사');
  const rows = [
    head(['기록 항목', '내용']),
    ['케이스 조건', '지역 · 월 조제료 · OTC 비율 · 일반약 일매출 · 월세 · 보증금'],
    ['모델 제시가', '빠른매도 · 적정호가 · 높은호가 (조사 시점 값)'],
    ['응답', '조사원 1 · 2 · 3 의 적정 권리금'],
    ['규모', '20케이스 × 3명 = 60응답'],
  ];
  s.addTable(rows, tableOpts({ x: 0.7, y: 2.08, w: 6.0, fontSize: 12,
    colW: [1.8, 4.2], rowH: 0.66 }));

  kicker(s, 7.1, 1.75, '재현 — php doc/data/verify-model.php', null, 5.5);
  card(s, 7.1, 2.08, 5.5, 2.64, { fill: '0F172A', line: '0F172A', name: 'term' });
  s.addText([
    { text: 'v1 보정전  적중 41/60 (68%)  중앙값 1.129', options: { color: '94A3B8', breakLine: true } },
    { text: 'v1 보정후  적중 51/60 (85%)  중앙값 0.999', options: { color: '5EEAD4', breakLine: true } },
    { text: 'v2 보정전  적중 42/60 (70%)  중앙값 1.066', options: { color: '94A3B8', breakLine: true } },
    { text: 'v2 보정후  적중 42/60 (70%)  중앙값 0.926', options: { color: 'FCA5A5', breakLine: true } },
    { text: '', options: { breakLine: true } },
    { text: '+ 케이스별 · 조사원별 · 소형 구간 비교', options: { color: 'E2E8F0' } },
  ], { x: 7.35, y: 2.3, w: 5.0, h: 2.2, fontSize: 11, fontFace: 'Courier New',
       isTextBox: true, margin: 0 });

  card(s, 0.7, 5.1, 11.9, 1.3, { fill: C.background2, name: 'why' });
  s.addText('검증 지표는 넷 — 범위 적중 · 응답÷모델 중앙값 · 케이스별 · 조사원별',
    { x: 1.05, y: 5.28, w: 11.2, h: 0.4, fontSize: 14, bold: true, color: C.text1, isTextBox: true, margin: 0 });
  s.addText('적중률만 보면 "어느 쪽으로 틀렸는지"를 알 수 없기 때문입니다. 방향을 알아야 어떤 계수를 고칠지 결정할 수 있습니다.',
    { x: 1.05, y: 5.72, w: 11.2, h: 0.5, fontSize: 13, color: C.text2, isTextBox: true, margin: 0 });
  s.addNotes('수치를 문서에 적는 대신 스크립트로 남겼습니다. 계수를 바꾸면 적중률이 어떻게 변하는지 그 자리에서 다시 돌려볼 수 있습니다.');
}

// ── 19. API와 키 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.data });
  s.addText('무료 공개 API 3종, 키가 없어도 계산은 돈다', { placeholder: 'title' });

  const apis = [
    ['공공데이터포털', 'DATA_GO_KR_KEY', ['병의원 찾기', '약국 찾기', '상가(상권)정보'],
     '세 서비스 각각 활용신청 →\n마이페이지에서 일반 인증키 복사', T.accent1],
    ['카카오', 'KAKAO_REST_KEY', ['로컬 — 주소 → 좌표', '우편번호 (키 불필요)'],
     '애플리케이션 추가 →\n앱 키에서 REST API 키 복사', T.accent3],
    ['Google AI Studio', 'GEMINI_API_KEY', ['상권·리스크 서술', '입력값 검증'],
     'Get API key →\n프로젝트 선택 후 키 생성', T.accent4],
  ];
  apis.forEach((a, i) => {
    const x = 0.7 + i * 4.1;
    card(s, x, 1.55, 3.7, 3.9, { fill: 'FFFFFF', line: a[4], name: 'api' + i });
    s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x, y: 1.55, w: 3.7, h: 0.5, rectRadius: 0.08,
      fill: { color: a[4] }, line: { color: a[4] }, objectName: 'apihead' + i });
    s.addText(a[0], { x: x + 0.22, y: 1.55, w: 3.3, h: 0.5, fontSize: 15, bold: true,
      color: 'FFFFFF', valign: 'middle', isTextBox: true, margin: 0 });
    s.addText(a[1], { x: x + 0.22, y: 2.2, w: 3.3, h: 0.3, fontSize: 11.5, color: C.text2,
      fontFace: 'Courier New', isTextBox: true, margin: 0 });
    s.addText(a[2].map((t, j) => ({ text: t, options: { bullet: true, breakLine: j < a[2].length - 1 } })),
      { x: x + 0.22, y: 2.6, w: 3.3, h: 1.1, fontSize: 12.5, color: C.text1, isTextBox: true, margin: 0 });
    s.addText('발급 경로', { x: x + 0.22, y: 3.85, w: 3.3, h: 0.3, fontSize: 10.5, bold: true,
      color: T.accent5, charSpacing: 1, isTextBox: true, margin: 0 });
    s.addText(a[3], { x: x + 0.22, y: 4.15, w: 3.3, h: 1.0, fontSize: 11.5, color: C.text2,
      isTextBox: true, margin: 0 });
  });

  card(s, 0.7, 5.65, 11.9, 0.65, { fill: 'FEE2E2', line: 'FCA5A5', name: 'warn' });
  s.addText('⚠  공공데이터포털은 Encoding 키가 아니라 Decoding 키 — 이미 인코딩된 키를 넣으면 이중 인코딩되어 인증 오류가 납니다',
    { x: 1.05, y: 5.65, w: 11.2, h: 0.65, fontSize: 13, bold: true, color: '991B1B',
      isTextBox: true, margin: 0, valign: 'middle' });
  s.addText('폴백 — 키 없음: 해당 블록만 "미조회" · API 오류: 안내 후 계산 계속 · 기본 모델 지연: 예비 모델 자동 전환',
    { x: 0.7, y: 6.42, w: 11.9, h: 0.4, fontSize: 12.5, color: C.text2, isTextBox: true, margin: 0 });
  s.addNotes('세 키 모두 무료 등급으로 충분합니다. 발급 단계는 README의 키 발급 방법에 정리돼 있습니다.');
}

// ═══════════════════════════════════════════ 섹션 4 ═══
pres.addSection({ title: SEC.next });

// ── 20. 상시 검증 구조 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.next });
  s.addText('일회성 조사를 쓸수록 정확해지는 구조로', { placeholder: 'title' });
  lead(s, '모델은 60응답으로 맞춰졌고, 그 60응답은 2026년 9월에 멈춰 있습니다.');

  const flow = [
    ['설문', '응답자 1명당 6케이스 · 2~3분 · 익명'],
    ['DB 적재', '유형 · 지역 · 조사원 · 공개 전 추정액 · 판정 · 조정액'],
    ['집계', '관리자 화면에서 유형별 ratio 중앙값 · 판정 분포'],
    ['보정', '원클릭 반영 → 전 계산기에 즉시 적용'],
  ];
  flow.forEach((f, i) => {
    const y = 1.95 + i * 1.02;
    s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: 0.7, y, w: 1.45, h: 0.75, rectRadius: 0.08,
      fill: { color: T.accent1 }, line: { color: T.accent1 }, objectName: 'fl' + i });
    s.addText(f[0], { x: 0.7, y, w: 1.45, h: 0.75, fontSize: 14, bold: true, color: 'FFFFFF',
      align: 'center', valign: 'middle', isTextBox: true, margin: 0 });
    s.addText(f[1], { x: 2.35, y: y + 0.18, w: 6.3, h: 0.5, fontSize: 13, color: C.text2,
      isTextBox: true, margin: 0 });
    if (i < 3) {
      s.addShape(pres.shapes.LINE, { x: 1.42, y: y + 0.75, w: 0, h: 0.27,
        line: { color: 'CBD5E1', width: 1.5 }, objectName: 'fla' + i });
    }
  });

  s.addImage({ path: img('survey-card.jpg'), x: 9.1, y: 1.62, w: 3.3, h: 4.55,
    altText: '적정성 설문 케이스 화면 — 조건표, 모델 계산 금액, 판정과 조정 입력' });

  card(s, 0.7, 6.0, 7.9, 0.85, { fill: C.background2, name: 'guard' });
  s.addText('안전장치 — 응답자 유형 4종 분리 · 표본 30건 이상 지역만 · 보정 폭 ±10~15% · 관리자 확인 후 반영',
    { x: 1.0, y: 6.0, w: 7.4, h: 0.85, fontSize: 12.5, bold: true, color: C.text1,
      isTextBox: true, margin: 0, valign: 'middle' });
  s.addNotes('0단계에서 사람이 하던 일을 사용자가 대신하고, 그 결과가 다시 계산식으로 들어가는 순환입니다. 계산식이 응답 몇 건에 흔들리지 않도록 안전장치를 함께 뒀습니다.');
}

// ── 21. 로드맵 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.next });
  s.addText('표본을 채우면 남은 판단이 데이터로 결정된다', { placeholder: 'title' });

  const stages = [
    ['1단계 · 단기', '표본 확보', [
      '지역별 30건+ 확보 → 지역 보정 가동',
      '지방 중소도시 집중 (조사원 간 편차 큼)',
      '대형 구간(3,500만원+) 표본 0건 해소',
    ], '목표 — 지역 보정 자동 가동', T.accent1],
    ['2단계 · 중기', '모델 정교화', [
      'v1 / v2 중 하나를 데이터로 확정',
      '병원 의존도를 연속값으로 (처방 비중 입력)',
      '저수익 임계점 지역별 분리',
      '실거래 확보 경로 (중개사·컨설팅 협력)',
    ], '목표 — 남은 판단을 데이터로 종결', T.accent3],
    ['3단계 · 장기', '활용 확장', [
      '권리금 구성 분해 (바닥·영업·시설)',
      '매물 비교 — 여러 건 동일 모델 평가',
      'PDF 리포트 — 협상 자리용',
      '지역·규모별 시세 추이 대시보드',
    ], '목표 — 협상 현장에서 쓰는 산출물', T.accent4],
  ];
  stages.forEach((st, i) => {
    const x = 0.7 + i * 4.1;
    const col = st[4];
    card(s, x, 1.5, 3.7, 3.75, { fill: 'FFFFFF', line: col, name: 'rm' + i });
    s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x, y: 1.5, w: 3.7, h: 0.52, rectRadius: 0.08,
      fill: { color: col }, line: { color: col }, objectName: 'rmh' + i });
    s.addText(st[0], { x: x + 0.22, y: 1.5, w: 3.3, h: 0.52, fontSize: 13, bold: true,
      color: 'FFFFFF', valign: 'middle', isTextBox: true, margin: 0 });
    s.addText(st[1], { x: x + 0.22, y: 2.12, w: 3.3, h: 0.4, fontSize: 17, bold: true,
      color: C.text1, isTextBox: true, margin: 0 });
    s.addText(st[2].map((t, j) => ({ text: t, options: { bullet: true, breakLine: j < st[2].length - 1, paraSpaceAfter: 8 } })),
      { x: x + 0.22, y: 2.6, w: 3.3, h: 1.95, fontSize: 12, color: C.text2, isTextBox: true, margin: 0 });
    s.addShape(pres.shapes.LINE, { x: x + 0.22, y: 4.68, w: 3.26, h: 0,
      line: { color: 'E2E8F0', width: 1 }, objectName: 'rmd' + i });
    s.addText(st[3], { x: x + 0.22, y: 4.8, w: 3.3, h: 0.35, fontSize: 12, bold: true,
      color: col, isTextBox: true, margin: 0 });
  });

  card(s, 0.7, 5.5, 11.9, 0.85, { fill: C.background2, name: 'ops' });
  s.addText('운영 과제 — 공공데이터·카카오 키 운영 계정 전환 · 응답 지연 대응(예비 모델 기본화 검토) · 계수 변경 이력 관리',
    { x: 1.05, y: 5.5, w: 11.2, h: 0.85, fontSize: 13, color: C.text1,
      isTextBox: true, margin: 0, valign: 'middle' });
  s.addText('1단계가 가장 급합니다 — 표본이 모이면 지역 보정이 자동으로 돌고, v1 / v2 판단도 결론이 납니다.',
    { x: 0.7, y: 6.5, w: 11.9, h: 0.35, fontSize: 12.5, color: T.accent5,
      isTextBox: true, margin: 0 });
  s.addNotes('1단계가 가장 급합니다. 지역별 30건이 모이면 지역 보정이 자동으로 돌고, 소형 표본이 쌓이면 v1과 v2 중 어느 쪽이 맞는지도 결론이 납니다.');
}

// ── 22. 한계와 마무리 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.next });
  s.addText('실거래 조회가 아니라, 설명 가능한 추정 모델', { placeholder: 'title' });
  lead(s, '오해를 막기 위해 한계를 분명히 둡니다.');

  const limits = [
    ['공개 실거래 DB 부재', '약국 권리금 실거래 데이터베이스는 존재하지 않습니다. 수익 구조에 기반한 추정입니다.'],
    ['실거래는 ±30% 변동', '1인약국 여부 · 병원 안정성 · 건물 형태 · 임대차 조건에 따라 달라집니다.'],
    ['검증 표본의 한계', '조사원 3명 · 20케이스 기준이며, 조제료 3,500만원 이상 대형 구간은 미검증입니다.'],
  ];
  limits.forEach((l, i) => {
    const y = 1.9 + i * 1.08;
    card(s, 0.7, y, 11.9, 0.92, { name: 'lim' + i });
    s.addText(l[0], { x: 1.05, y: y + 0.1, w: 3.4, h: 0.35, fontSize: 14, bold: true,
      color: T.accent6, isTextBox: true, margin: 0 });
    s.addText(l[1], { x: 4.5, y: y + 0.12, w: 7.8, h: 0.7, fontSize: 13, color: C.text2,
      isTextBox: true, margin: 0 });
  });

  card(s, 0.7, 5.35, 11.9, 1.45, { fill: '0F172A', line: '0F172A', name: 'closing' });
  s.addText('"그냥 그 정도" 대신, 그 금액이 어떻게 나왔는지 설명할 수 있게',
    { x: 1.1, y: 5.6, w: 11.1, h: 0.5, fontSize: 19, bold: true, color: 'FFFFFF',
      isTextBox: true, margin: 0 });
  s.addText('검증 데이터를 먼저 만들고 그에 맞춰 계산기를 고쳐 나간 순서 — 그 덕분에 v2를 기본값으로 삼지 않는 판단까지 숫자로 내릴 수 있었습니다.',
    { x: 1.1, y: 6.12, w: 11.1, h: 0.5, fontSize: 13, color: '94A3B8', isTextBox: true, margin: 0 });
  s.addNotes('이 도구가 하지 않는 것을 분명히 하는 것도 중요합니다. 가치는 금액을 정해 주는 데 있지 않고, 그 금액이 어떻게 나왔는지 설명할 수 있게 하는 데 있습니다.');
}

// ── 저장 ──
pres.writeFile({ fileName: OUT })
  .then(() => applyTheme(OUT, THEME))
  .then(() => console.log('생성 완료:', OUT))
  .catch((e) => { console.error(e); process.exit(1); });
