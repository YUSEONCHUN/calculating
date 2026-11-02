const pptxgen = require('pptxgenjs');
const { applyTheme } = require('/Users/kwondev/Library/Application Support/Claude/local-agent-mode-sessions/skills-plugin/2923eea7-42c1-4c35-b1b9-05aa2899c8c6/c35cfeeb-1e4e-4006-90dc-c3831c93ee2f/skills/pptx/scripts/apply_theme.js');

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
    accent6: 'C2410C',  // 경고/문제
    hlink: '0F766E', folHlink: '94A3B8',
  },
};

const pres = new pptxgen();
pres.layout = 'LAYOUT_WIDE';            // 13.3 x 7.5
pres.theme = { headFontFace: THEME.headFontFace, bodyFontFace: THEME.bodyFontFace };
pres.author = 'Yudev';
pres.title = '약국 권리금 계산기 프로젝트';
const C = pres.SchemeColor;

const FOOT = '약국 권리금 계산기 · Yudev · 2026-11';

// ── 레이아웃 ──
pres.defineSlideMaster({
  title: 'TITLE_DARK', background: { color: THEME.colors.dk1 },
  objects: [
    { placeholder: { options: { name: 'title', type: 'title', x: 0.9, y: 2.5, w: 11.5, h: 1.5,
        fontSize: 44, bold: true, color: C.background1 }, text: '' } },
    { placeholder: { options: { name: 'body', type: 'body', x: 0.9, y: 4.0, w: 11.5, h: 1.6,
        fontSize: 17, color: THEME.colors.accent5 }, text: '' } },
  ],
});

pres.defineSlideMaster({
  title: 'SECTION', background: { color: THEME.colors.accent1 },
  objects: [
    { placeholder: { options: { name: 'title', type: 'title', x: 0.9, y: 2.9, w: 11.5, h: 1.1,
        fontSize: 38, bold: true, color: C.background1 }, text: '' } },
    { placeholder: { options: { name: 'body', type: 'body', x: 0.9, y: 4.0, w: 11.5, h: 1.0,
        fontSize: 16, color: 'CCFBF1' }, text: '' } },
  ],
});

pres.defineSlideMaster({
  title: 'CONTENT', background: { color: C.background1 },
  objects: [
    { placeholder: { options: { name: 'title', type: 'title', x: 0.7, y: 0.45, w: 11.9, h: 0.8,
        fontSize: 32, bold: true, color: C.text1 }, text: '' } },
    { text: { text: FOOT, options: { x: 0.7, y: 6.95, w: 8, h: 0.3, fontSize: 9,
        color: THEME.colors.accent5, isTextBox: true, margin: 0 } } },
    { placeholder: { options: { name: 'slideNum', type: 'sldNum', x: 12.3, y: 6.95, w: 0.6, h: 0.3,
        fontSize: 9, color: THEME.colors.accent5, align: 'right' }, text: '' } },
  ],
});

pres.defineSlideMaster({
  title: 'CHART', background: { color: C.background1 },
  objects: [
    { placeholder: { options: { name: 'title', type: 'title', x: 0.7, y: 0.45, w: 11.9, h: 0.8,
        fontSize: 32, bold: true, color: C.text1 }, text: '' } },
    { placeholder: { options: { name: 'chart', type: 'chart', x: 0.7, y: 1.5, w: 7.6, h: 4.9,
        color: C.text1 }, text: '' } },
    { placeholder: { options: { name: 'body', type: 'body', x: 8.6, y: 1.6, w: 4.0, h: 4.7,
        fontSize: 14, color: C.text2 }, text: '' } },
    { text: { text: FOOT, options: { x: 0.7, y: 6.95, w: 8, h: 0.3, fontSize: 9,
        color: THEME.colors.accent5, isTextBox: true, margin: 0 } } },
    { placeholder: { options: { name: 'slideNum', type: 'sldNum', x: 12.3, y: 6.95, w: 0.6, h: 0.3,
        fontSize: 9, color: THEME.colors.accent5, align: 'right' }, text: '' } },
  ],
});

// ── 공통 차트 스타일 ──
const chartBase = {
  showTitle: false, showLegend: false,
  catAxisLabelColor: THEME.colors.dk2, valAxisLabelColor: THEME.colors.dk2,
  catAxisLabelFontSize: 11, valAxisLabelFontSize: 11,
  catAxisLabelFontFace: '+mn-lt', valAxisLabelFontFace: '+mn-lt',
  dataLabelFontFace: '+mn-lt', dataLabelFontSize: 12, dataLabelColor: THEME.colors.dk2,
  valGridLine: { color: 'E2E8F0', size: 1 }, catGridLine: { style: 'none' },
};

// 카드 그리기 헬퍼
function card(slide, x, y, w, h, opts) {
  slide.addShape(pres.shapes.ROUNDED_RECTANGLE, {
    x, y, w, h, rectRadius: 0.08,
    fill: { color: opts.fill || C.background2 },
    line: { color: opts.line || 'E2E8F0', width: 1 },
    objectName: opts.name || 'card',
  });
}
function kicker(slide, x, y, text, color) {
  slide.addText(text, { x, y, w: 4, h: 0.3, fontSize: 11, bold: true,
    color: color || THEME.colors.accent1, charSpacing: 1, isTextBox: true, margin: 0 });
}

// ═══ 1. 표지 ═══
pres.addSection({ title: '개발 목적과 배경' });
{
  const s = pres.addSlide({ masterName: 'TITLE_DARK', sectionTitle: '개발 목적과 배경' });
  s.addText('약국 권리금 계산기', { placeholder: 'title' });
  s.addText([
    { text: '"그 동네는 원래 그 정도"를 계산식으로', options: { breakLine: true, color: THEME.colors.accent3, bold: true } },
    { text: '개발 목적 · 히스토리 v1~v3 · 조사 검증 · 로드맵', options: { breakLine: true } },
    { text: 'Yudev · 2026년 11월', options: {} },
  ], { placeholder: 'body' });
  s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: 0.9, y: 1.75, w: 2.0, h: 0.45, rectRadius: 0.2,
    fill: { color: THEME.colors.accent1 }, line: { color: THEME.colors.accent1 }, objectName: 'badge' });
  s.addText('PROJECT REVIEW', { x: 0.9, y: 1.75, w: 2.0, h: 0.45, fontSize: 10, bold: true,
    color: 'FFFFFF', align: 'center', charSpacing: 1, isTextBox: true, margin: 0 });
  s.addNotes('약국 권리금 계산기 프로젝트 리뷰입니다. 왜 만들었는지, v1에서 v3까지 어떻게 발전시켰는지, 조사로 어떻게 검증했는지, 앞으로 어디로 갈지 순서로 말씀드리겠습니다.');
}

// ═══ 2. 문제 ═══
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: '개발 목적과 배경' });
  s.addText('가장 큰 결정을, 가장 경험 없는 상태에서', { placeholder: 'title' });
  s.addText('약국 인수·개국은 대부분 생애 한두 번. 그런데 권리금은 수천만 원에서 수억 원입니다.',
    { x: 0.7, y: 1.3, w: 11.9, h: 0.4, fontSize: 15, color: C.text2, isTextBox: true, margin: 0 });

  const items = [
    ['①', '제시받는다', '중개사가 권리금 3억을 제시한다'],
    ['②', '판단이 안 된다', '비싼지 싼지 가늠할 기준이 없다'],
    ['③', '감으로 답이 온다', '"그 동네는 원래 그 정도"'],
    ['④', '근거가 없다', '왜 그 숫자인지 아무도 설명 못 한다'],
  ];
  items.forEach((it, i) => {
    const x = 0.7 + i * 3.05;
    card(s, x, 2.0, 2.8, 2.3, { fill: C.background2, name: 'prob' + i });
    s.addShape(pres.shapes.OVAL, { x: x + 0.3, y: 2.3, w: 0.55, h: 0.55,
      fill: { color: i === 3 ? THEME.colors.accent6 : THEME.colors.accent1 },
      line: { color: i === 3 ? THEME.colors.accent6 : THEME.colors.accent1 }, objectName: 'n' + i });
    s.addText(it[0], { x: x + 0.3, y: 2.3, w: 0.55, h: 0.55, fontSize: 15, bold: true,
      color: 'FFFFFF', align: 'center', valign: 'middle', isTextBox: true, margin: 0 });
    s.addText(it[1], { x: x + 0.3, y: 3.0, w: 2.2, h: 0.35, fontSize: 15, bold: true,
      color: C.text1, isTextBox: true, margin: 0 });
    s.addText(it[2], { x: x + 0.3, y: 3.4, w: 2.2, h: 0.8, fontSize: 12, color: C.text2,
      isTextBox: true, margin: 0 });
  });

  card(s, 0.7, 4.7, 11.9, 1.5, { fill: 'FEF3C7', line: 'FDE68A', name: 'quote' });
  s.addText('업계의 감은 틀린 게 아닙니다. 다만 수치화되지 않아 검증할 수도, 물려받을 수도 없습니다.',
    { x: 1.1, y: 5.0, w: 11.1, h: 0.4, fontSize: 18, bold: true, color: '92400E', isTextBox: true, margin: 0 });
  s.addText('신규 개국 약사에게는 접근 자체가 불가능한 지식입니다.',
    { x: 1.1, y: 5.5, w: 11.1, h: 0.4, fontSize: 14, color: '92400E', isTextBox: true, margin: 0 });
  s.addNotes('신규 약사가 실제로 겪는 흐름입니다. 핵심은 감이 틀렸다는 게 아니라, 검증도 전수도 안 된다는 점입니다.');
}

// ═══ 3. 일반 계산기와의 차이 ═══
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: '개발 목적과 배경' });
  s.addText('약국은 권리금을 구성하는 요소가 다르다', { placeholder: 'title' });
  s.addText('일반 상가는 「월 매출 × 개월수」로 업종을 가리지 않습니다. 약국은 전제가 다릅니다.',
    { x: 0.7, y: 1.3, w: 11.9, h: 0.4, fontSize: 15, color: C.text2, isTextBox: true, margin: 0 });

  const rows = [
    [{ text: '구분', options: { bold: true } }, { text: '일반 상가', options: { bold: true } }, { text: '약국', options: { bold: true } }],
    ['수익의 원천', '매출 전체', '조제료(처방 건수)가 핵심'],
    ['수요의 성격', '유동인구·상권', '인근 병·의원의 처방에 종속'],
    ['입지 대체성', '비슷한 자리로 이전 가능', '병원 앞 자리는 대체 불가'],
    ['핵심 리스크', '상권 쇠퇴', '병원 이전·폐업 → 수익 소멸'],
    ['시설', '범용 인테리어·집기', '조제 전용 설비 (ATC·약장)'],
    ['재고', '비중 낮음', '금액 크고 유효기간 리스크'],
  ];
  s.addTable(rows, {
    x: 0.7, y: 1.95, w: 11.9, colW: [2.6, 4.2, 5.1], fontSize: 13, color: C.text1,
    border: { type: 'solid', color: 'E2E8F0', pt: 1 }, align: 'left', valign: 'middle',
    rowH: 0.52, fill: { color: 'FFFFFF' },
  });
  card(s, 0.7, 5.85, 11.9, 0.95, { fill: 'CCFBF1', line: '5EEAD4', name: 'conc' });
  s.addText('구성 요소가 다르면 가중치도 달라야 합니다 — 일반 계산기를 쓰면 소형은 과대평가, 문전 대형은 과소평가됩니다.',
    { x: 1.1, y: 5.85, w: 11.1, h: 0.95, fontSize: 15, bold: true, color: '115E59', isTextBox: true, margin: 0, valign: 'middle' });
  s.addNotes('이 표가 이 프로젝트의 출발점입니다. 구성 요소가 다르니 계산식도 달라야 한다는 것.');
}

// ═══ 4. 섹션 구분 — 개발 히스토리 ═══
pres.addSection({ title: '개발 히스토리' });
{
  const s = pres.addSlide({ masterName: 'SECTION', sectionTitle: '개발 히스토리' });
  s.addText('개발 히스토리 — 0단계에서 v3까지', { placeholder: 'title' });
  s.addText('계산식보다 정답지를 먼저 만들었습니다', { placeholder: 'body' });
  s.addNotes('이제 어떻게 만들었는지 순서대로 말씀드리겠습니다.');
}

// ═══ 5. 전체 흐름 ═══
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: '개발 히스토리' });
  s.addText('네 단계로 발전시켰다', { placeholder: 'title' });

  const steps = [
    ['0단계', '현장의 감을 숫자로', '주변 약사 20케이스 조사 → 오차 측정', THEME.colors.dk2],
    ['v1', '단순 계산식 + 구간 배율', '결과는 나오지만 경계에서 금액이 절벽처럼 튐', THEME.colors.accent2],
    ['v2', '절벽을 완만하게', '구간 사이를 선형보간하는 식 도입', THEME.colors.accent3],
    ['v3', '외부 데이터 결합', 'Gemini + 공공데이터·카카오 API 연동', THEME.colors.accent4],
  ];
  steps.forEach((st, i) => {
    const y = 1.45 + i * 1.32;
    card(s, 0.7, y, 11.9, 1.12, { fill: i === 0 ? C.background2 : 'FFFFFF', name: 'step' + i });
    s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: 1.0, y: y + 0.26, w: 1.35, h: 0.6, rectRadius: 0.1,
      fill: { color: st[3] }, line: { color: st[3] }, objectName: 'tag' + i });
    s.addText(st[0], { x: 1.0, y: y + 0.26, w: 1.35, h: 0.6, fontSize: 16, bold: true,
      color: 'FFFFFF', align: 'center', valign: 'middle', isTextBox: true, margin: 0 });
    s.addText(st[1], { x: 2.65, y: y + 0.2, w: 4.2, h: 0.4, fontSize: 18, bold: true,
      color: C.text1, isTextBox: true, margin: 0 });
    s.addText(st[2], { x: 2.65, y: y + 0.62, w: 9.5, h: 0.35, fontSize: 13, color: C.text2,
      isTextBox: true, margin: 0 });
  });
  s.addNotes('0단계가 가장 중요합니다. 계산식을 먼저 만들지 않고 정답지부터 모았습니다.');
}

// ═══ 6. 0단계 방법 ═══
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: '개발 히스토리' });
  s.addText('0단계 — 계산식보다 정답지를 먼저', { placeholder: 'title' });

  const how = [
    ['20개 케이스 구성', '지역 5개 권역 × 규모별. 조제료·일매출·월세·보증금을 랜덤·반올림으로 흩뿌려 패턴이 보이지 않게'],
    ['계산 결과를 함께 적어 전달', '빠른매도 / 적정호가 / 높은호가 3단계를 표에 같이 기재'],
    ['약사에게 직접 기입받음', '"당신이 보는 적정 권리금"을 케이스마다 손으로 적게 함'],
  ];
  how.forEach((h, i) => {
    const y = 1.45 + i * 1.08;
    s.addShape(pres.shapes.OVAL, { x: 0.75, y: y + 0.08, w: 0.5, h: 0.5,
      fill: { color: THEME.colors.accent1 }, line: { color: THEME.colors.accent1 }, objectName: 'st' + i });
    s.addText(String(i + 1), { x: 0.75, y: y + 0.08, w: 0.5, h: 0.5, fontSize: 14, bold: true,
      color: 'FFFFFF', align: 'center', valign: 'middle', isTextBox: true, margin: 0 });
    s.addText(h[0], { x: 1.45, y: y, w: 11, h: 0.38, fontSize: 17, bold: true, color: C.text1, isTextBox: true, margin: 0 });
    s.addText(h[1], { x: 1.45, y: y + 0.4, w: 11, h: 0.55, fontSize: 13, color: C.text2, isTextBox: true, margin: 0 });
  });

  card(s, 0.7, 4.85, 11.9, 1.55, { fill: THEME.colors.dk1, line: THEME.colors.dk1, name: 'why' });
  kicker(s, 1.1, 5.1, '왜 결과를 같이 보여줬나', THEME.colors.accent3);
  s.addText('백지에 적게 하면 답이 흩어지지만, 모델가를 옆에 두면 "높다 / 낮다" 판단이 함께 나옵니다.',
    { x: 1.1, y: 5.42, w: 11.1, h: 0.35, fontSize: 16, bold: true, color: 'FFFFFF', isTextBox: true, margin: 0 });
  s.addText('처음부터 정답을 맞히는 게 아니라 오차를 재는 것이 목적이었기 때문입니다.',
    { x: 1.1, y: 5.82, w: 11.1, h: 0.35, fontSize: 13, color: THEME.colors.accent5, isTextBox: true, margin: 0 });
  s.addNotes('계산 결과를 숨기지 않고 같이 보여준 것이 설계 포인트입니다.');
}

// ═══ 7. 0단계 결과 — 모델 선택 ═══
{
  const s = pres.addSlide({ masterName: 'CHART', sectionTitle: '개발 히스토리' });
  s.addText('두 계산 방식을 같은 케이스에 돌렸다', { placeholder: 'title' });
  s.addChart([{
    type: pres.charts.BAR,
    data: [{ name: '범위 적중률(%)', labels: ['월 매출총이익 × 12개월\n(일반 상가식)', '지역 회수개월수 ×\n조제료 규모 계수'], values: [18, 80] }],
    options: Object.assign({}, chartBase, {
      barDir: 'col', chartColors: [THEME.colors.accent6, THEME.colors.accent1],
      showValue: true, dataLabelPosition: 'outEnd', dataLabelFormatCode: '0"%"',
      valAxisMaxVal: 100, valAxisMinVal: 0, barGapWidthPct: 90,
      catAxisLabelFontSize: 12,
    }),
  }], { placeholder: 'chart' });
  s.addText([
    { text: '40응답 기준 (조사원 2명)', options: { bold: true, fontSize: 15, color: THEME.colors.accent1, breakLine: true, paraSpaceAfter: 10 } },
    { text: '일반 상가식 7 / 40', options: { bold: true, breakLine: true } },
    { text: '맞는 구간이 사실상 없었습니다.', options: { fontSize: 13, breakLine: true, paraSpaceAfter: 12 } },
    { text: '· 대형 → 응답의 60~70%로 과소평가', options: { fontSize: 13, breakLine: true } },
    { text: '· 소형 → 2.5~4배로 과대평가', options: { fontSize: 13, breakLine: true, paraSpaceAfter: 14 } },
    { text: '약국 특화 모델 32 / 40', options: { bold: true, color: THEME.colors.accent1, breakLine: true } },
    { text: '지역·규모·저수익을 변수로 다루자 현장 응답과 맞아떨어졌습니다.', options: { fontSize: 13 } },
  ], { placeholder: 'body' });
  s.addNotes('이 결과가 방향을 갈랐습니다. 일반 상가식은 지역·규모·저수익을 전부 상수로 취급합니다.');
}

// ═══ 8. v1 — 구간 배율과 절벽 ═══
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: '개발 히스토리' });
  s.addText('v1 — 결과는 나왔지만 경계가 절벽이었다', { placeholder: 'title' });
  s.addText('권리금 = 월 환산수익 × 지역 회수개월수 × 조제료 규모 계수',
    { x: 0.7, y: 1.28, w: 11.9, h: 0.4, fontSize: 16, bold: true, color: THEME.colors.accent1, isTextBox: true, margin: 0 });

  // 좌: 계수 표
  kicker(s, 0.7, 1.85, '조제료 구간별 고정 계수');
  const t = [
    [{ text: '월 조제료', options: { bold: true } }, { text: '계수', options: { bold: true } }],
    ['1,000만원 미만', '0.50'], ['1,000 ~ 2,500', '1.00'], ['2,500 ~ 3,500', '1.15'],
    ['3,500 ~ 5,000', '1.25'], ['5,000 이상', '1.35'],
  ];
  s.addTable(t, { x: 0.7, y: 2.2, w: 5.4, colW: [3.6, 1.8], fontSize: 13, color: C.text1,
    border: { type: 'solid', color: 'E2E8F0', pt: 1 }, rowH: 0.44, valign: 'middle', fill: { color: 'FFFFFF' } });

  // 우: 절벽 사례
  kicker(s, 6.6, 1.85, '같은 조건, 조제료만 1만원 차이', THEME.colors.accent6);
  card(s, 6.6, 2.2, 2.85, 1.5, { fill: C.background2, name: 'c999' });
  s.addText('조제료 999만원', { x: 6.8, y: 2.35, w: 2.5, h: 0.3, fontSize: 12, color: C.text2, isTextBox: true, margin: 0 });
  s.addText('1.24억', { x: 6.8, y: 2.65, w: 2.5, h: 0.65, fontSize: 32, bold: true, color: C.text1, isTextBox: true, margin: 0 });
  s.addText('계수 0.50', { x: 6.8, y: 3.32, w: 2.5, h: 0.3, fontSize: 11, color: THEME.colors.accent5, isTextBox: true, margin: 0 });

  card(s, 9.75, 2.2, 2.85, 1.5, { fill: 'FEE2E2', line: 'FCA5A5', name: 'c1000' });
  s.addText('조제료 1,000만원', { x: 9.95, y: 2.35, w: 2.5, h: 0.3, fontSize: 12, color: '991B1B', isTextBox: true, margin: 0 });
  s.addText('2.47억', { x: 9.95, y: 2.65, w: 2.5, h: 0.65, fontSize: 32, bold: true, color: THEME.colors.accent6, isTextBox: true, margin: 0 });
  s.addText('계수 1.00', { x: 9.95, y: 3.32, w: 2.5, h: 0.3, fontSize: 11, color: '991B1B', isTextBox: true, margin: 0 });

  s.addText('1만원 차이로 권리금 2배', { x: 6.6, y: 3.85, w: 6.0, h: 0.4, fontSize: 17, bold: true,
    color: THEME.colors.accent6, align: 'center', isTextBox: true, margin: 0 });

  // 경계별 점프
  kicker(s, 6.6, 4.45, '경계별 금액 점프');
  const jt = [
    [{ text: '경계', options: { bold: true } }, { text: '계수', options: { bold: true } }, { text: '점프', options: { bold: true } }],
    ['1,000만원', '0.50 → 1.00', '+100%'], ['2,500만원', '1.00 → 1.15', '+15%'],
    ['3,500만원', '1.15 → 1.25', '+9%'], ['5,000만원', '1.25 → 1.35', '+8%'],
  ];
  s.addTable(jt, { x: 6.6, y: 4.8, w: 6.0, colW: [2.0, 2.2, 1.8], fontSize: 12, color: C.text1,
    border: { type: 'solid', color: 'E2E8F0', pt: 1 }, rowH: 0.36, valign: 'middle', fill: { color: 'FFFFFF' } });

  card(s, 0.7, 4.45, 5.4, 1.95, { fill: 'FEF3C7', line: 'FDE68A', name: 'v1note' });
  s.addText('구간을 나눈 편의가\n결과의 왜곡이 됐다', { x: 1.0, y: 4.7, w: 4.9, h: 0.75, fontSize: 17, bold: true,
    color: '92400E', isTextBox: true, margin: 0 });
  s.addText('쓰는 사람에게는 "숫자를 조금 바꿨는데 결과가 확 변한다"는 신뢰 문제로 직결됩니다.',
    { x: 1.0, y: 5.5, w: 4.9, h: 0.7, fontSize: 12, color: '92400E', isTextBox: true, margin: 0 });
  s.addNotes('실무에서 조제료 999만원과 1000만원 약국이 두 배 차이 날 리가 없습니다.');
}

// ═══ 9. v2 — 선형보간 ═══
{
  const s = pres.addSlide({ masterName: 'CHART', sectionTitle: '개발 히스토리' });
  s.addText('v2 — 기준점은 두고 사이만 이었다', { placeholder: 'title' });
  const labels = ['0', '500', '999', '1,000', '1,500', '2,000', '2,499', '2,500', '3,000', '3,499', '3,500', '4,200', '4,999', '5,000', '5,800'];
  const v1v = [0.5, 0.5, 0.5, 1.0, 1.0, 1.0, 1.0, 1.15, 1.15, 1.15, 1.25, 1.25, 1.25, 1.35, 1.35];
  const v2v = [0.5, 0.75, 0.9995, 1.0, 1.05, 1.1, 1.1499, 1.15, 1.2, 1.2499, 1.25, 1.264, 1.2998, 1.35, 1.35];
  s.addChart([{
    type: pres.charts.LINE,
    data: [
      { name: 'v1 계단형', labels: labels, values: v1v },
      { name: 'v2 곡선형', labels: labels, values: v2v },
    ],
    options: Object.assign({}, chartBase, {
      showLegend: true, legendPos: 'b', legendColor: THEME.colors.dk2, legendFontSize: 11,
      legendFontFace: '+mn-lt',
      chartColors: [THEME.colors.accent2, THEME.colors.accent3],
      lineSize: 3, lineSmooth: false, showValue: false,
      valAxisMinVal: 0.4, valAxisMaxVal: 1.45, valAxisMajorUnit: 0.2,
      catAxisLabelRotate: 0, catAxisLabelFontSize: 9,
    }),
  }], { placeholder: 'chart' });
  s.addText([
    { text: '구간 경계만 없앴다', options: { bold: true, fontSize: 15, color: THEME.colors.accent1, breakLine: true, paraSpaceAfter: 10 } },
    { text: '기준점(1,000 / 2,500 / 3,500 / 5,000)의 값은 v1과 동일합니다. 검증으로 얻은 기준은 그대로 보존됩니다.', options: { fontSize: 13, breakLine: true, paraSpaceAfter: 14 } },
    { text: '경계 점프', options: { bold: true, breakLine: true } },
    { text: '1,000만원   +100% → +0.05%', options: { fontSize: 13, breakLine: true } },
    { text: '2,500만원     +15% → +0.01%', options: { fontSize: 13, breakLine: true } },
    { text: '3,500만원       +9% → +0.01%', options: { fontSize: 13, breakLine: true, paraSpaceAfter: 14 } },
    { text: 'v1을 지우지 않은 이유', options: { bold: true, breakLine: true } },
    { text: 'v1은 기준을 말로 설명하기 쉽고, v2는 결과가 안정적입니다. 어느 쪽이 실무에 맞는지는 데이터로 판단할 문제라 나란히 남겼습니다.', options: { fontSize: 12 } },
  ], { placeholder: 'body' });
  s.addNotes('주황이 v1 계단, 청록이 v2 곡선입니다. 기준점에서 두 선이 만납니다.');
}

// ═══ 10. v3 — 외부 데이터 ═══
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: '개발 히스토리' });
  s.addText('v3 — 입력할 수 없는 정보를 끌어왔다', { placeholder: 'title' });
  s.addText('v1·v2는 입력한 숫자만으로 계산합니다. 그러나 실제 판단에는 사용자가 알 수 없는 정보가 필요합니다.',
    { x: 0.7, y: 1.3, w: 11.9, h: 0.4, fontSize: 15, color: C.text2, isTextBox: true, margin: 0 });

  const blocks = [
    ['공공데이터포털', '지역 병·의원 수 · 약국 수', '약국 1곳당 병·의원 수를\n전국 평균과 비교해 3단계 판정', THEME.colors.accent1],
    ['카카오', '주소 → 좌표 · 반경 500m 상권', '상권 업소 수와 업종 분포로\n입지 성격 파악', THEME.colors.accent3],
    ['Gemini', '상권·리스크·협상 전략 서술', '확정된 산출치를 넘겨\n서술만 맡김 (재계산 금지)', THEME.colors.accent4],
    ['개국 자금 계획', '권리금 + 보증금 + 인테리어', '초기 재고·예비 운전자금까지\n총 필요자금을 범위로', THEME.colors.dk2],
  ];
  blocks.forEach((b, i) => {
    const x = 0.7 + (i % 2) * 6.1, y = 2.0 + Math.floor(i / 2) * 2.3;
    card(s, x, y, 5.8, 2.0, { fill: 'FFFFFF', name: 'api' + i });
    s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: x + 0.35, y: y + 0.28, w: 0.16, h: 0.75, rectRadius: 0.08,
      fill: { color: b[3] }, line: { color: b[3] }, objectName: 'bar' + i });
    s.addText(b[0], { x: x + 0.7, y: y + 0.26, w: 4.9, h: 0.38, fontSize: 18, bold: true, color: b[3], isTextBox: true, margin: 0 });
    s.addText(b[1], { x: x + 0.7, y: y + 0.66, w: 4.9, h: 0.35, fontSize: 13, bold: true, color: C.text1, isTextBox: true, margin: 0 });
    s.addText(b[2], { x: x + 0.7, y: y + 1.05, w: 4.9, h: 0.75, fontSize: 12, color: C.text2, isTextBox: true, margin: 0 });
  });
  s.addNotes('v3는 외부 데이터를 결합합니다. AI는 계산이 아니라 서술만 맡습니다.');
}

// ═══ 11. 섹션 — 조사 데이터 ═══
pres.addSection({ title: '조사 데이터와 검증' });
{
  const s = pres.addSlide({ masterName: 'SECTION', sectionTitle: '조사 데이터와 검증' });
  s.addText('조사 데이터와 검증 결과', { placeholder: 'title' });
  s.addText('20케이스 × 조사원 3명 = 60응답', { placeholder: 'body' });
  s.addNotes('실제로 받은 조사 데이터와 그 결과를 보여드리겠습니다.');
}

// ═══ 12. 조사 개요 ═══
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: '조사 데이터와 검증' });
  s.addText('조사 개요와 조사원별 성향', { placeholder: 'title' });

  const stats = [['20', '케이스'], ['3', '조사원'], ['60', '응답'], ['5', '권역']];
  stats.forEach((st, i) => {
    const x = 0.7 + i * 3.05;
    card(s, x, 1.4, 2.8, 1.25, { fill: C.background2, name: 'stat' + i });
    s.addText(st[0], { x: x, y: 1.52, w: 2.8, h: 0.75, fontSize: 40, bold: true,
      color: THEME.colors.accent1, align: 'center', isTextBox: true, margin: 0 });
    s.addText(st[1], { x: x, y: 2.26, w: 2.8, h: 0.3, fontSize: 12, color: C.text2,
      align: 'center', isTextBox: true, margin: 0 });
  });

  kicker(s, 0.7, 2.95, '조사 설계');
  s.addText([
    { text: '지역 5개 권역(서울 핵심·서울 일반·수도권·광역시·지방) × 규모별 20케이스', options: { bullet: true, breakLine: true, paraSpaceAfter: 6 } },
    { text: '조건 변수 — 월 조제료, OTC 비율, 일반약 일매출, 월세, 보증금 (랜덤·반올림)', options: { bullet: true, breakLine: true, paraSpaceAfter: 6 } },
    { text: '각 케이스에 모델 산출 3단계(빠른매도·적정호가·높은호가)를 함께 기재해 전달', options: { bullet: true } },
  ], { x: 0.75, y: 3.3, w: 5.6, h: 1.6, fontSize: 13, color: C.text1, isTextBox: true });

  kicker(s, 6.9, 2.95, '조사원별 성향 — 한 명의 치우침은 중앙값으로 걸러짐', THEME.colors.accent6);
  const st2 = [
    [{ text: '조사원', options: { bold: true } }, { text: '범위 안', options: { bold: true } }, { text: '성향', options: { bold: true } }],
    ['조사자 1', '18 / 20', '모델과 가장 근접'],
    ['조사자 2', '14 / 20', '전반적으로 보수적'],
    ['조사자 3', '9 / 20', '일관되게 높게 평가 (중앙 1.26)'],
  ];
  s.addTable(st2, { x: 6.9, y: 3.3, w: 5.7, colW: [1.5, 1.3, 2.9], fontSize: 12, color: C.text1,
    border: { type: 'solid', color: 'E2E8F0', pt: 1 }, rowH: 0.42, valign: 'middle', fill: { color: 'FFFFFF' } });

  card(s, 0.7, 5.15, 11.9, 1.25, { fill: THEME.colors.dk1, line: THEME.colors.dk1, name: 'pat' });
  s.addText('드러난 두 가지 패턴 (응답 중앙값 ÷ 모델 적정호가)', { x: 1.1, y: 5.35, w: 11.1, h: 0.3,
    fontSize: 12, bold: true, color: THEME.colors.accent3, isTextBox: true, margin: 0 });
  s.addText('조제료 1,000만원 이상 (17케이스) → 1.154   모델이 약 15% 낮음        |        1,000만원 미만 (3케이스) → 0.646   모델이 약 35% 높음',
    { x: 1.1, y: 5.7, w: 11.1, h: 0.45, fontSize: 14, bold: true, color: 'FFFFFF', isTextBox: true, margin: 0 });
  s.addNotes('조사자 3이 높게 보는 성향이지만 케이스별 3인 중앙값을 쓰면 걸러집니다.');
}

// ═══ 13. 보정 결과 ═══
{
  const s = pres.addSlide({ masterName: 'CHART', sectionTitle: '조사 데이터와 검증' });
  s.addText('보정 후 적중률 68% → 85%', { placeholder: 'title' });
  s.addChart([{
    type: pres.charts.BAR,
    data: [
      { name: '보정 전', labels: ['전체 적중률(%)', '소형 구간(×100)', '일반 구간(×100)'], values: [68, 64.6, 115.4] },
      { name: '보정 후', labels: ['전체 적중률(%)', '소형 구간(×100)', '일반 구간(×100)'], values: [85, 99.2, 100.6] },
    ],
    options: Object.assign({}, chartBase, {
      barDir: 'col', chartColors: [THEME.colors.accent5, THEME.colors.accent1],
      showValue: true, dataLabelPosition: 'outEnd', dataLabelFormatCode: '0.0',
      showLegend: true, legendPos: 'b', legendColor: THEME.colors.dk2, legendFontSize: 11,
      legendFontFace: '+mn-lt', valAxisMaxVal: 130, valAxisMinVal: 0, barGapWidthPct: 60,
      catAxisLabelFontSize: 11,
    }),
  }], { placeholder: 'chart' });
  s.addText([
    { text: '두 군데만 손봤다', options: { bold: true, fontSize: 15, color: THEME.colors.accent1, breakLine: true, paraSpaceAfter: 10 } },
    { text: '전 지역 회수개월수 일괄 상향 (×1.15)', options: { bold: true, fontSize: 13, breakLine: true } },
    { text: '지역별 배율이 1.08~1.17로 좁아 특정 지역이 아닌 전체 수준 문제로 판단', options: { fontSize: 12, breakLine: true, paraSpaceAfter: 12 } },
    { text: '소형 구간 계수 하향 (0.88 → 0.50)', options: { bold: true, fontSize: 13, breakLine: true } },
    { text: '조사원 3명 모두 모델의 절반 수준으로 평가', options: { fontSize: 12, breakLine: true, paraSpaceAfter: 14 } },
    { text: '응답 ÷ 모델 중앙값', options: { bold: true, breakLine: true } },
    { text: '1.129  →  0.999', options: { fontSize: 20, bold: true, color: THEME.colors.accent1 } },
  ], { placeholder: 'body' });
  s.addNotes('소형·일반 구간이 모두 1.0 근처로 수렴했습니다. 100이 모델과 응답이 일치하는 지점입니다.');
}

// ═══ 14. 상시 구조 ═══
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: '조사 데이터와 검증' });
  s.addText('일회성 조사를 상시 구조로', { placeholder: 'title' });
  s.addText('엑셀로 한 번 돌린 조사를 앱 안에서 반복할 수 있게 만들었습니다.',
    { x: 0.7, y: 1.3, w: 11.9, h: 0.4, fontSize: 15, color: C.text2, isTextBox: true, margin: 0 });

  const flow = ['설문 링크 배포\n1명당 6케이스 · 2~3분', '응답 누적\n유형·지역·조사원 기록', '관리자 집계\n유형별 ratio 확인', '원클릭 보정\n계산기에 반영'];
  flow.forEach((f, i) => {
    const x = 0.7 + i * 3.1;
    card(s, x, 2.0, 2.75, 1.5, { fill: i === 3 ? 'CCFBF1' : 'FFFFFF', line: i === 3 ? '5EEAD4' : 'E2E8F0', name: 'f' + i });
    s.addText(f, { x: x + 0.2, y: 2.25, w: 2.35, h: 1.0, fontSize: 13, bold: true,
      color: i === 3 ? '115E59' : C.text1, align: 'center', valign: 'middle', isTextBox: true, margin: 0 });
    if (i < 3) s.addText('→', { x: x + 2.78, y: 2.5, w: 0.3, h: 0.5, fontSize: 20,
      color: THEME.colors.accent5, align: 'center', isTextBox: true, margin: 0 });
  });

  kicker(s, 0.7, 3.85, '안전장치 — 소수 응답으로 계산식이 흔들리지 않게');
  const guards = [
    ['응답자 유형 4종 분리', '매수·매도·일반·중개사 — 한쪽 입장에 치우치지 않게'],
    ['지역 표본 30건 이상만 보정', '표본이 모자라면 기본 모델 그대로'],
    ['보정 폭 상한 ±10%', '한 번에 크게 움직이지 못하게'],
    ['관리자 확인 후 반영', '자동 적용이 아니라 사람이 승인'],
  ];
  guards.forEach((g, i) => {
    const x = 0.7 + (i % 2) * 6.1, y = 4.25 + Math.floor(i / 2) * 1.05;
    s.addShape(pres.shapes.OVAL, { x: x, y: y + 0.08, w: 0.32, h: 0.32,
      fill: { color: THEME.colors.accent1 }, line: { color: THEME.colors.accent1 }, objectName: 'g' + i });
    s.addText('✓', { x: x, y: y + 0.08, w: 0.32, h: 0.32, fontSize: 11, bold: true, color: 'FFFFFF',
      align: 'center', valign: 'middle', isTextBox: true, margin: 0 });
    s.addText(g[0], { x: x + 0.45, y: y, w: 5.4, h: 0.33, fontSize: 14, bold: true, color: C.text1, isTextBox: true, margin: 0 });
    s.addText(g[1], { x: x + 0.45, y: y + 0.35, w: 5.4, h: 0.4, fontSize: 12, color: C.text2, isTextBox: true, margin: 0 });
  });
  s.addNotes('한 번의 검증을 자산으로 만들려면 반복 가능해야 합니다.');
}

// ═══ 15. 섹션 — API와 로드맵 ═══
pres.addSection({ title: 'API와 로드맵' });
{
  const s = pres.addSlide({ masterName: 'SECTION', sectionTitle: 'API와 로드맵' });
  s.addText('사용한 API와 앞으로의 계획', { placeholder: 'title' });
  s.addText('무엇을 가져와 쓰는가 · 어디로 발전시킬 것인가', { placeholder: 'body' });
}

// ═══ 16. API 목록 ═══
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: 'API와 로드맵' });
  s.addText('사용한 API와 수집 데이터', { placeholder: 'title' });
  const rows = [
    [{ text: 'API', options: { bold: true } }, { text: '제공', options: { bold: true } },
     { text: '가져오는 데이터', options: { bold: true } }, { text: '쓰임', options: { bold: true } }],
    ['병·의원 찾기', '국립중앙의료원', '시군구·동 병·의원 수, 주요 의료기관명', '처방 유입 여력 판정'],
    ['약국 찾기', '국립중앙의료원', '시군구·동 약국 수', '경쟁 밀도 판정'],
    ['상권정보', '소상공인시장진흥공단', '반경 500m 업소 수·업종 분포', '상권 성격 파악'],
    ['로컬 (주소→좌표)', '카카오', '위경도 좌표', '상권 조회 연결'],
    ['우편번호 서비스', '카카오(다음)', '주소·행정구역', '주소 입력 UI'],
    ['Gemini', 'Google', '— (생성)', '상권·리스크 서술, 입력 검증'],
  ];
  s.addTable(rows, { x: 0.7, y: 1.4, w: 11.9, colW: [2.5, 2.6, 4.0, 2.8], fontSize: 12, color: C.text1,
    border: { type: 'solid', color: 'E2E8F0', pt: 1 }, rowH: 0.45, valign: 'middle', fill: { color: 'FFFFFF' } });

  s.addText('실측 데이터를 어떻게 쓰는가 — 약국 1곳당 병·의원 수를 전국 평균 1.7곳과 비교',
    { x: 0.7, y: 4.6, w: 11.9, h: 0.38, fontSize: 11, bold: true, color: THEME.colors.accent1,
      charSpacing: 1, isTextBox: true, margin: 0, valign: 'middle' });
  const judge = [
    ['평균의 1.2배 이상', '처방 유입 여력이 넉넉', THEME.colors.accent4, 'DCFCE7'],
    ['평균 ±20% 이내', '전국 평균과 비슷', THEME.colors.dk2, 'F1F5F9'],
    ['평균의 0.8배 이하', '약국이 많아 경쟁이 센 편', THEME.colors.accent6, 'FEE2E2'],
  ];
  judge.forEach((j, i) => {
    const x = 0.7 + i * 4.07;
    card(s, x, 5.0, 3.77, 0.95, { fill: j[3], line: j[3], name: 'j' + i });
    s.addText(j[0], { x: x + 0.25, y: 5.12, w: 3.3, h: 0.33, fontSize: 13, bold: true, color: j[2], isTextBox: true, margin: 0 });
    s.addText(j[1], { x: x + 0.25, y: 5.47, w: 3.3, h: 0.35, fontSize: 12, color: C.text2, isTextBox: true, margin: 0 });
  });
  s.addText('설계 원칙 — 수치 계산은 전부 서버에서 끝내고 AI에는 확정된 산출치를 넘겨 서술만 맡깁니다. 같은 입력에 같은 결과가 나와야 협상 근거로 쓸 수 있기 때문입니다.',
    { x: 0.7, y: 6.2, w: 11.9, h: 0.5, fontSize: 12, italic: true, color: C.text2, isTextBox: true, margin: 0 });
  s.addNotes('키가 없으면 해당 기능만 건너뛰고 권리금 계산 자체는 정상 동작합니다.');
}

// ═══ 17. 로드맵 ═══
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: 'API와 로드맵' });
  s.addText('로드맵', { placeholder: 'title' });

  const phases = [
    ['1단계', '표본 확보', '단기', THEME.colors.accent1, [
      '지역별 30건 이상 확보 → 지역 보정 자동 가동',
      '조사자 편차 컸던 지방 중소도시 집중 조사',
      '조제료 3,500만원 이상 대형 구간 (현재 표본 0건)',
    ]],
    ['2단계', '모델 정교화', '중기', THEME.colors.accent3, [
      'v1·v2 중 실무에 맞는 쪽을 데이터로 결정',
      '병원 의존 구조 수치화 (처방 상위 병원 비중)',
      '저수익 임계점 지역별 분리',
      '실거래 데이터 확보 경로 마련',
    ]],
    ['3단계', '활용 확장', '장기', THEME.colors.accent4, [
      '권리금 구성 분해 재도입 (바닥·영업·시설)',
      '매물 비교 재도입 — 여러 건 동시 평가',
      '산정 결과 PDF 리포트',
      '시세 추이 대시보드 · 대출 상품 연계',
    ]],
  ];
  phases.forEach((p, i) => {
    const x = 0.7 + i * 4.07;
    card(s, x, 1.4, 3.77, 4.1, { fill: 'FFFFFF', name: 'ph' + i });
    s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: x + 0.25, y: 1.65, w: 1.1, h: 0.42, rectRadius: 0.1,
      fill: { color: p[3] }, line: { color: p[3] }, objectName: 'pt' + i });
    s.addText(p[0], { x: x + 0.25, y: 1.65, w: 1.1, h: 0.42, fontSize: 12, bold: true, color: 'FFFFFF',
      align: 'center', valign: 'middle', isTextBox: true, margin: 0 });
    s.addText(p[2], { x: x + 1.5, y: 1.68, w: 2.0, h: 0.36, fontSize: 11, color: THEME.colors.accent5,
      valign: 'middle', isTextBox: true, margin: 0 });
    s.addText(p[1], { x: x + 0.25, y: 2.2, w: 3.3, h: 0.42, fontSize: 20, bold: true, color: C.text1, isTextBox: true, margin: 0 });
    s.addText(p[4].map((t, k) => ({ text: t, options: { bullet: true, breakLine: k < p[4].length - 1, paraSpaceAfter: 8 } })),
      { x: x + 0.3, y: 2.75, w: 3.2, h: 2.6, fontSize: 11.5, color: C.text2, isTextBox: true });
  });

  card(s, 0.7, 5.75, 11.9, 0.95, { fill: C.background2, name: 'ops' });
  s.addText('운영 과제', { x: 1.0, y: 5.9, w: 2.0, h: 0.3, fontSize: 12, bold: true, color: THEME.colors.accent1, isTextBox: true, margin: 0 });
  s.addText('공공데이터·카카오 키 운영 계정 전환   ·   Gemini 기본 모델 응답 지연(평균 10초) 대응   ·   계수 변경 이력 관리',
    { x: 1.0, y: 6.2, w: 11.3, h: 0.35, fontSize: 12, color: C.text2, isTextBox: true, margin: 0 });
  s.addNotes('단기는 표본, 중기는 모델, 장기는 활용 확장입니다.');
}

// ═══ 18. 한계와 마무리 ═══
{
  const s = pres.addSlide({ masterName: 'TITLE_DARK', sectionTitle: 'API와 로드맵' });
  s.addText('무엇을 하지 않는가', { placeholder: 'title' });
  s.addNotes('마무리입니다. 한계를 분명히 해두는 것이 신뢰의 조건입니다.');

  const lim = [
    '실거래가 조회가 아닙니다 — 공개된 약국 권리금 실거래 DB는 존재하지 않습니다',
    '실제 거래는 1인약국 여부·병원 안정성·건물 형태에 따라 ±30%까지 달라질 수 있습니다',
    '검증 표본은 조사원 3명·20케이스이며, 대형 구간은 아직 미검증입니다',
  ];
  lim.forEach((t, i) => {
    s.addText('—', { x: 0.95, y: 4.05 + i * 0.42, w: 0.3, h: 0.35, fontSize: 13,
      color: THEME.colors.accent3, isTextBox: true, margin: 0 });
    s.addText(t, { x: 1.3, y: 4.05 + i * 0.42, w: 11.2, h: 0.35, fontSize: 13,
      color: THEME.colors.accent5, isTextBox: true, margin: 0 });
  });

  card(s, 0.9, 5.6, 11.5, 1.1, { fill: THEME.colors.accent1, line: THEME.colors.accent1, name: 'end' });
  s.addText('"그냥 그 정도"라는 답 대신, 어떤 요소가 얼마나 반영되어 그 금액이 나왔는지 설명할 수 있게 하는 것',
    { x: 1.3, y: 5.85, w: 10.7, h: 0.6, fontSize: 16, bold: true, color: 'FFFFFF', valign: 'middle', isTextBox: true, margin: 0 });
}

(async () => {
  await pres.writeFile({ fileName: '권리금계산기_프로젝트.pptx' });
  await applyTheme('권리금계산기_프로젝트.pptx', THEME);
  console.log('생성 완료');
})();
