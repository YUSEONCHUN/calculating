/**
 * 약국 권리금 계산기 — 발표 슬라이드 생성기 (30장: 보이는 20장 + 숨김 10장)
 *
 *   node doc/slides/deck.js
 *
 * 구성안: doc/04-슬라이드-구성.md   대본: doc/05-발표-대본.md
 * 출처:   README.md · doc/01~03 · doc/data/2026-09-권리금-적정성-조사.csv
 * 이미지: doc/images/*.jpg|png (실제 화면 캡처와 배수 곡선)
 *
 * 발표 설계 —
 *   · 10~20분 기준. 보이는 20장(전환 4장 · 라이브 시연 1장 포함)으로 흐름을 유지하고,
 *     상세 자료 10장은 PowerPoint '숨김'으로 두어 질문이 나올 때만 번호로 띄운다.
 *   · 주제가 바뀌는 자리마다 WHY / HOW / SO WHAT / NEXT 전환 슬라이드를 둔다.
 *     각 전환 장에 "앞에서 본 것 → 지금부터 볼 것"을 적어 이야기가 끊기지 않게 한다.
 *   · 슬라이드 끝의 bridge() 한 줄이 다음 장으로 말을 넘긴다.
 *   · 발표자가 개발자가 아니라는 전제 — 본문은 일상어, 남는 용어는 하단 각주, 마지막은 용어표.
 *
 * 테마 색은 제품 화면에서 그대로 가져왔다 — 틸(메인) · 앰버(1차) · 밝은 틸(2차) · 그린(3차).
 */
const path = require('path');
const pptxgen = require('pptxgenjs');
const { applyTheme } = require('/Users/kwondev/Library/Application Support/Claude/local-agent-mode-sessions/skills-plugin/2923eea7-42c1-4c35-b1b9-05aa2899c8c6/c35cfeeb-1e4e-4006-90dc-c3831c93ee2f/skills/pptx/scripts/apply_theme.js');

const IMG = path.join(__dirname, '..', 'images');
const img = (f) => path.join(IMG, f);
const OUT = path.join(__dirname, '권리금계산기_프로젝트.pptx');

// 색·글꼴은 제공받은 사내 PPT 템플릿에서 가져왔다 (인디고 #42469F · 연보라 배경 · 오렌지 포인트 · 맑은 고딕)
const THEME = {
  name: '권리금 계산기',
  headFontFace: '맑은 고딕',
  bodyFontFace: '맑은 고딕',
  colors: {
    dk1: '151517', lt1: 'FFFFFF', dk2: '444444', lt2: 'DCE0E9',
    accent1: '42469F',  // 인디고 — 기본 / 1차
    accent2: 'FF6600',  // 오렌지 — 주의 · 상시화
    accent3: '7E86D6',  // 연인디고 — 2차
    accent4: '273164',  // 네이비 — 3차
    accent5: '8A90A8',  // 연회색 — 보조 텍스트
    accent6: 'E0453F',  // 레드 — 문제 지점
    hlink: '42469F', folHlink: '8A90A8',
  },
};
const PAGE_BG = 'E9ECF4';   // 템플릿의 연보라 배경 (본문 카드는 흰색이라 대비가 산다)
const INK_BG = '1B1F43';    // 어두운 카드 배경 (인디고 잉크)

const pres = new pptxgen();
pres.layout = 'LAYOUT_WIDE';            // 13.3 x 7.5
pres.theme = { headFontFace: THEME.headFontFace, bodyFontFace: THEME.bodyFontFace };
pres.author = 'Yudev';
pres.title = '약국 권리금 계산기 프로젝트';
const C = pres.SchemeColor;
const T = THEME.colors;

const FOOT = '약국 권리금 계산기 · Yudev · 2026-11';
const SEC = { intro: '1. WHY — 왜 만들었나', hist: '2. HOW — 어떻게 만들었나',
              res: '3. SO WHAT — 무엇이 달라졌나', next: '4. NEXT — 앞으로', ref: '부록 (숨김)' };

// ── 레이아웃 ──
pres.defineSlideMaster({
  title: 'TITLE_DARK', background: { color: T.accent4 },
  objects: [
    { placeholder: { options: { name: 'title', type: 'title', x: 0.9, y: 2.5, w: 11.5, h: 1.5,
        fontSize: 44, bold: true, color: C.background1 }, text: '' } },
    { placeholder: { options: { name: 'body', type: 'body', x: 0.9, y: 4.0, w: 11.5, h: 1.6,
        fontSize: 17, color: T.accent5 }, text: '' } },
  ],
});

pres.defineSlideMaster({
  title: 'DIVIDER', background: { color: T.accent4 },
  objects: [
    { placeholder: { options: { name: 'title', type: 'title', x: 0.9, y: 3.15, w: 11.5, h: 1.0,
        fontSize: 36, bold: true, color: C.background1 }, text: '' } },
    { placeholder: { options: { name: 'body', type: 'body', x: 0.9, y: 4.35, w: 11.5, h: 1.3,
        fontSize: 15, color: T.accent5 }, text: '' } },
  ],
});

pres.defineSlideMaster({
  title: 'CONTENT', background: { color: PAGE_BG },
  objects: [
    { rect: { x: 0.36, y: 0.5, w: 0.1, h: 0.62, fill: { color: T.accent1 } } },
    { placeholder: { options: { name: 'title', type: 'title', x: 0.7, y: 0.42, w: 11.9, h: 0.78,
        fontSize: 30, bold: true, color: C.text1 }, text: '' } },
    { text: { text: FOOT, options: { x: 0.7, y: 6.98, w: 8, h: 0.3, fontSize: 9,
        color: T.accent5, isTextBox: true, margin: 0 } } },
    { placeholder: { options: { name: 'slideNum', type: 'sldNum', x: 12.3, y: 6.98, w: 0.6, h: 0.3,
        fontSize: 9, color: T.accent5, align: 'right' }, text: '' } },
  ],
});

pres.defineSlideMaster({
  title: 'CHART', background: { color: PAGE_BG },
  objects: [
    { rect: { x: 0.36, y: 0.5, w: 0.1, h: 0.62, fill: { color: T.accent1 } } },
    { placeholder: { options: { name: 'title', type: 'title', x: 0.7, y: 0.42, w: 11.9, h: 0.78,
        fontSize: 30, bold: true, color: C.text1 }, text: '' } },
    { placeholder: { options: { name: 'chart', type: 'chart', x: 0.6, y: 1.68, w: 7.4, h: 3.95,
        color: C.text1 }, text: '' } },
    { placeholder: { options: { name: 'body', type: 'body', x: 8.4, y: 1.72, w: 4.3, h: 3.9,
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
  valGridLine: { color: 'DCE0E9', size: 1 }, catGridLine: { style: 'none' },
});

function card(slide, x, y, w, h, opts) {
  const o = opts || {};
  slide.addShape(pres.shapes.ROUNDED_RECTANGLE, {
    x, y, w, h, rectRadius: 0.08,
    fill: { color: o.fill || C.background2 },
    line: { color: o.line || 'D6DAE5', width: 1 },
    objectName: o.name || 'card',
  });
}
/** 템플릿의 이중 테두리 모티프 — 카드 뒤에 살짝 비켜 놓은 외곽선 */
function frame(slide, x, y, w, h, color, name) {
  slide.addShape(pres.shapes.ROUNDED_RECTANGLE, {
    x: x + 0.12, y: y + 0.12, w, h, rectRadius: 0.08,
    fill: { color: PAGE_BG }, line: { color: color || T.accent3, width: 1 },
    objectName: name || 'frame',
  });
}
/** 화면 캡처 액자 — 연보라 배경 위에서 캡처가 떠 보이지 않도록 흰 카드로 감싼다 */
function shot(slide, file, x, y, w, h, altText) {
  slide.addShape(pres.shapes.ROUNDED_RECTANGLE, {
    x: x - 0.08, y: y - 0.08, w: w + 0.16, h: h + 0.16, rectRadius: 0.06,
    fill: { color: 'FFFFFF' }, line: { color: 'D6DAE5', width: 1 }, objectName: 'shotcard',
  });
  slide.addImage({ path: img(file), x, y, w, h, altText });
}
function lead(slide, text) {
  slide.addText(text, { x: 0.7, y: 1.22, w: 11.9, h: 0.4, fontSize: 15, color: C.text2,
    isTextBox: true, margin: 0 });
}
function kicker(slide, x, y, text, color, w) {
  slide.addText(text, { x, y, w: w || 5, h: 0.28, fontSize: 11, bold: true,
    color: color || T.accent1, charSpacing: 1, isTextBox: true, margin: 0 });
}
/** 슬라이드 하단 각주 — 본문에 남은 용어를 한 줄씩 풀어 준다 */
function footnote(slide, y, lines) {
  slide.addShape(pres.shapes.LINE, { x: 0.7, y, w: 11.9, h: 0,
    line: { color: 'DCE0E9', width: 1 }, objectName: 'fndiv' });
  slide.addText(lines.map((t, i) => ({ text: t, options: { breakLine: i < lines.length - 1 } })),
    { x: 0.7, y: y + 0.07, w: 11.9, h: lines.length * 0.24 + 0.04, fontSize: 10.5,
      color: T.accent5, isTextBox: true, margin: 0 });
}
/** 다음 주제로 말을 넘기는 한 줄 — 이야기가 끊기지 않도록 슬라이드 끝에 둔다 */
function bridge(slide, y, text) {
  slide.addText('→  ' + text, { x: 0.7, y, w: 11.9, h: 0.38, fontSize: 13.5, bold: true,
    color: T.accent1, isTextBox: true, margin: 0 });
}
/** 숨김 상세 슬라이드 표시 — 발표 중에는 건너뛰고, 질문이 나오면 번호로 띄운다 */
function backupBadge(slide) {
  slide.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: 10.9, y: 0.45, w: 1.7, h: 0.38, rectRadius: 0.19,
    fill: { color: C.background2 }, line: { color: 'DCE0E9' }, objectName: 'bk' });
  slide.addText('BACKUP · 숨김', { x: 10.9, y: 0.45, w: 1.7, h: 0.38, fontSize: 9.5, bold: true,
    color: T.accent5, align: 'center', valign: 'middle', isTextBox: true, margin: 0 });
}
function tableOpts(extra) {
  return Object.assign({
    fontSize: 13, color: C.text1, align: 'left', valign: 'middle',
    border: { type: 'solid', color: 'DCE0E9', pt: 1 }, fill: { color: 'FFFFFF' },
  }, extra || {});
}
function head(cells) { return cells.map((t) => ({ text: t, options: { bold: true, color: C.text1 } })); }

/** 주제 전환 슬라이드 — 큰 질문 하나와 "앞에서 → 지금부터" 한 줄 */
function divider(section, step, word, question, fromText, toText, notes) {
  const s = pres.addSlide({ masterName: 'DIVIDER', sectionTitle: section });
  s.addText(word, { x: 0.9, y: 1.95, w: 11.5, h: 1.1, fontSize: 60, bold: true,
    color: 'FF9A4D', charSpacing: 2, isTextBox: true, margin: 0 });
  s.addShape(pres.shapes.LINE, { x: 0.95, y: 3.08, w: 1.6, h: 0,
    line: { color: 'FF9A4D', width: 3 }, objectName: 'rule' });
  s.addText(question, { placeholder: 'title' });
  s.addText([
    { text: '앞에서   ' + fromText, options: { breakLine: true } },
    { text: '지금부터   ' + toText, options: { color: 'DCE0E9', bold: true } },
  ], { placeholder: 'body' });
  s.addText(step + ' / 4', { x: 11.2, y: 0.6, w: 1.2, h: 0.4, fontSize: 13, bold: true,
    color: T.accent5, align: 'right', isTextBox: true, margin: 0 });
  s.addNotes(notes);
  return s;
}

// ═══════════════════════════════════════════════════ 1. WHY ═══
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
    { text: '"그 동네는 원래 그 정도"를 계산식으로', options: { breakLine: true, color: 'FF9A4D', bold: true, fontSize: 20 } },
    { text: '왜 만들었나 · 어떻게 만들었나 · 무엇이 달라졌나 · 앞으로', options: { breakLine: true } },
    { text: 'Yudev · 2026년 11월 · 약 15분', options: {} },
  ], { placeholder: 'body' });
  s.addNotes('약국 권리금 계산기 프로젝트 리뷰입니다. 왜 만들었는지, 어떻게 만들었는지, 무엇이 달라졌는지, 앞으로 어디로 갈지 네 부분으로 말씀드리겠습니다. 15분 정도 걸립니다.');
}

// ── 2. 전환 WHY ──
divider(SEC.intro, 1, 'WHY', '왜 이 계산기를 만들었나',
  '— (시작)',
  '권리금 앞에서 신규 약사가 겪는 문제와, 일반 계산기로는 안 되는 이유',
  '첫 번째는 "왜"입니다. 이 계산기가 왜 필요했는지부터 말씀드리겠습니다. 이 장은 한 호흡 쉬고 넘어가시면 됩니다.');

// ── 3. 문제 정의 ──
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

  card(s, 0.7, 4.8, 11.9, 1.3, { fill: 'FFF1E0', line: 'FFD4A8', name: 'quote' });
  s.addText('감은 틀린 게 아닙니다 — 수치화되지 않아 검증도 전수도 안 될 뿐입니다',
    { x: 1.1, y: 5.0, w: 11.1, h: 0.45, fontSize: 19, bold: true, color: 'A64B00', isTextBox: true, margin: 0 });
  s.addText('신규 개국 약사에게는 접근 자체가 불가능한 지식입니다.',
    { x: 1.1, y: 5.48, w: 11.1, h: 0.4, fontSize: 14, color: 'A64B00', isTextBox: true, margin: 0 });
  bridge(s, 6.3, '그럼 시중의 상가 권리금 계산기를 쓰면 되지 않나? — 다음 장');
  s.addNotes('신규 약사가 실제로 겪는 흐름입니다. 핵심은 감이 틀렸다는 게 아니라, 검증도 전수도 안 된다는 점입니다. 여기서 자연스럽게 "그럼 기존 계산기를 쓰면 되지 않나"라는 질문으로 넘어갑니다.');
}

// ── 4. 약국 ≠ 일반 상가 ──
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
    ['시설·재고', '범용 인테리어·집기', '조제 자동화기기 · 유효기간 리스크'],
  ];
  s.addTable(rows, tableOpts({ x: 0.7, y: 1.9, w: 11.9, colW: [2.6, 4.2, 5.1], rowH: 0.56 }));

  card(s, 0.7, 5.35, 11.9, 0.85, { fill: 'E3E6F5', line: 'B9BEE6', name: 'conc' });
  s.addText('구성 요소가 다르면 가중치도 달라야 합니다 — 일반 계산기는 소형을 과대평가, 문전 대형을 과소평가합니다',
    { x: 1.1, y: 5.35, w: 11.1, h: 0.85, fontSize: 15, bold: true, color: '2A2E73',
      isTextBox: true, margin: 0, valign: 'middle' });
  bridge(s, 6.4, '그래서 약국 전용 계산식을 따로 세웠습니다 — 다음 장');
  s.addNotes('이 표가 출발점입니다. 구성 요소가 다르면 계산식도 달라야 합니다. 문전 약국은 병원 앞이라는 자리값이 수익의 대부분이라, 일반 상가 기준으로는 늘 낮게 나옵니다.');
}

// ── 5. 해결 접근 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.intro });
  s.addText('감이 담은 판단을 수식과 숫자로 옮긴다', { placeholder: 'title' });
  lead(s, '감을 부정하는 것이 목표가 아닙니다. 누구나 쓰고 검증할 수 있는 형태로 바꾸는 것이 목표입니다.');

  const blocks = [
    ['한 달에 남는 돈', '조제 수익 + 일반약 마진\n− 월세 − 보증금 기회비용', T.accent1],
    ['몇 달치로 보느냐', '지역별로 다름\n빠른 매도 / 적정 / 높은 호가', T.accent3],
    ['규모에 따른 배수', '조제료가 클수록 더 쳐주고\n작으면 깎는다', T.accent4],
  ];
  blocks.forEach((b, i) => {
    const x = 0.7 + i * 4.1;
    frame(s, x, 1.9, 3.6, 1.7, b[2], 'ff' + i);
    card(s, x, 1.9, 3.6, 1.7, { fill: 'FFFFFF', line: b[2], name: 'f' + i });
    s.addText(b[0], { x: x + 0.25, y: 2.12, w: 3.1, h: 0.4, fontSize: 18, bold: true,
      color: b[2], isTextBox: true, margin: 0 });
    s.addText(b[1], { x: x + 0.25, y: 2.56, w: 3.1, h: 0.85, fontSize: 12.5, color: C.text2,
      isTextBox: true, margin: 0 });
    if (i < 2) {
      s.addText('×', { x: x + 3.6, y: 2.48, w: 0.5, h: 0.5, fontSize: 24, bold: true,
        color: T.accent5, align: 'center', isTextBox: true, margin: 0 });
    }
  });

  kicker(s, 0.7, 3.85, '결과는 금액 하나가 아니라 범위로');
  card(s, 0.7, 4.18, 11.9, 0.95, { fill: C.background2, name: 'rangebar' });
  s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: 1.0, y: 4.5, w: 11.3, h: 0.28, rectRadius: 0.14,
    fill: { color: T.accent1 }, line: { color: T.accent1 }, objectName: 'bar' });
  s.addText('최소 — 빨리 팔 때', { x: 1.0, y: 4.8, w: 3.0, h: 0.3, fontSize: 12, bold: true,
    color: C.text2, isTextBox: true, margin: 0 });
  s.addText('중간', { x: 5.9, y: 4.8, w: 1.5, h: 0.3, fontSize: 12, color: C.text2,
    align: 'center', isTextBox: true, margin: 0 });
  s.addText('최대 — 기다릴 수 있을 때', { x: 9.0, y: 4.8, w: 3.3, h: 0.3, fontSize: 12, bold: true,
    color: C.text2, align: 'right', isTextBox: true, margin: 0 });

  s.addText([
    { text: '범위 안에서 위치는 체크리스트로 좁힙니다', options: { bold: true, breakLine: true, color: C.text1, fontSize: 15 } },
    { text: '남은 임대 기간 · 처방 추이 · 병원 의존 구조 · 매도 시급성 · 시설 노후도', options: { fontSize: 13 } },
  ], { x: 0.7, y: 5.3, w: 11.9, h: 0.8, color: C.text2, isTextBox: true, margin: 0 });

  footnote(s, 6.3, [
    '· 보증금 기회비용 — 보증금으로 묶인 돈을 은행에 뒀다면 받았을 이자. 월세에 더해 "실제 부담"으로 계산합니다.',
    '· 몇 달치로 보느냐 = 회수개월수 — 권리금을 월 수익의 몇 배로 보느냐는 뜻. 예: 21.5개월이면 월 수익의 21.5배.',
  ]);
  s.addNotes('계산 구조는 이 한 줄입니다. 셋을 곱하면 권리금이 나옵니다. 결과는 금액 하나가 아니라 범위로 냅니다. 권리금에 정답 금액은 없고, 빨리 팔아야 하면 낮아지고 기다릴 수 있으면 높아지기 때문입니다.');
}

// ═══════════════════════════════════════════════════ 2. HOW ═══
pres.addSection({ title: SEC.hist });

// ── 6. 전환 HOW ──
divider(SEC.hist, 2, 'HOW', '이 숫자들을 어떻게 믿게 만들었나',
  '약국 전용 계산식이 필요한 이유',
  '계산식보다 정답지를 먼저 만든 과정과, 1차에서 3차까지의 수정 기록',
  '두 번째는 "어떻게"입니다. 계산식을 만든 것보다, 그 계산식이 맞는지 확인한 방법이 이 프로젝트의 핵심입니다.');

// ── 7. 전체 흐름 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.hist });
  s.addText('정답지 → 계산식 → 보정 → 확장 순서로 만들었다', { placeholder: 'title' });
  lead(s, '계산식을 먼저 세우지 않았습니다. 검증할 데이터를 먼저 만들고 거기에 맞춰 고쳐 나갔습니다.');

  const steps = [
    ['0단계', '현장의 감을 숫자로', '가상 20케이스 조사 → 두 계산식을 같은 데이터에 돌려 선택', T.accent5],
    ['1차', '기본 계산식을 만들다', '조사 결과로 숫자를 조정 → 적중률 68% → 85%', T.accent1],
    ['2차', '튀는 구간을 다듬다', '구간 경계에서 금액이 두 배로 뛰는 문제를 손봄', T.accent3],
    ['3차', '바깥 데이터를 붙이다', '공공데이터·지도에서 실제 수치를 받아오고 AI가 해설', T.accent4],
    ['상시화', '검증을 반복 가능하게', '설문 → 저장 → 집계 → 계산식에 반영', T.accent2],
  ];
  steps.forEach((st, i) => {
    const y = 1.85 + i * 0.92;
    s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: 0.7, y, w: 1.7, h: 0.74, rectRadius: 0.08,
      fill: { color: st[3] }, line: { color: st[3] }, objectName: 'stage' + i });
    s.addText(st[0], { x: 0.7, y, w: 1.7, h: 0.74, fontSize: 14, bold: true, color: 'FFFFFF',
      align: 'center', valign: 'middle', isTextBox: true, margin: 0 });
    s.addText(st[1], { x: 2.65, y: y + 0.02, w: 4.2, h: 0.38, fontSize: 16, bold: true,
      color: C.text1, isTextBox: true, margin: 0 });
    s.addText(st[2], { x: 2.65, y: y + 0.39, w: 9.7, h: 0.36, fontSize: 13, color: C.text2,
      isTextBox: true, margin: 0 });
    if (i < 4) {
      s.addShape(pres.shapes.LINE, { x: 1.55, y: y + 0.74, w: 0, h: 0.18,
        line: { color: 'B9BEE6', width: 1.5 }, objectName: 'link' + i });
    }
  });
  bridge(s, 6.45, '첫 단계부터 보겠습니다 — 계산식보다 정답지가 먼저였습니다');
  s.addNotes('전체 흐름입니다. 1차·2차·3차는 화면에 v1·v2·v3로 표시됩니다. 지금부터 각 단계에서 무엇을 발견했고 어떻게 고쳤는지 보시겠습니다.');
}

// ── 8. 0단계 (조사 + 계산식 선택) ──
{
  const s = pres.addSlide({ masterName: 'CHART', sectionTitle: SEC.hist });
  s.addText('계산식보다 정답지를 먼저 만들었다', { placeholder: 'title' });
  s.addChart(pres.charts.BAR, [{
    name: '조사원 응답이 계산 범위 안에 든 건수 (40건 중)',
    labels: ['A안\n매출총이익 × 12개월', 'B안\n지역·규모를 반영한 배수'],
    values: [7, 32],
  }], Object.assign(chartBase(), {
    placeholder: 'chart',
    chartColors: [T.accent6, T.accent1],
    showValue: true, dataLabelPosition: 'outEnd', dataLabelFontSize: 18,
    valAxisMaxVal: 40, barGapWidthPct: 120,
  }));
  s.addText([
    { text: '어떻게 만든 정답지인가', options: { bold: true, fontSize: 16, color: C.text1, breakLine: true } },
    { text: '가상 20케이스 = 지역 5권역 × 규모별', options: { bullet: true, breakLine: true, fontSize: 13 } },
    { text: '계산기가 낸 금액을 숨기지 않고 함께 제시', options: { bullet: true, breakLine: true, fontSize: 13 } },
    { text: '목적은 정답 수집이 아니라 오차 측정', options: { bullet: true, breakLine: true, fontSize: 13 } },
    { text: '틀린 방향이 서로 반대였다', options: { bold: true, fontSize: 16, color: C.text1, breakLine: true } },
    { text: '작은 약국 — A안이 3.03배 높게', options: { bold: true, fontSize: 15, color: T.accent6, breakLine: true } },
    { text: '보통~큰 약국 — A안이 3분의 2 수준', options: { bold: true, fontSize: 15, color: T.accent1, breakLine: true } },
    { text: '어떤 배수를 넣어도 동시에 맞출 수 없다 → B안 채택', options: { fontSize: 13, color: C.text2 } },
  ], { placeholder: 'body' });
  bridge(s, 5.8, 'B안으로 계산기를 만들었습니다 — 그게 1차입니다');
  footnote(s, 6.25, [
    '· 매출총이익 — 매출에서 약값·물건값을 뺀, 가게에 남는 이익.  ·  "범위 안에 들었다" = 응답 금액이 계산기의 최소~최대 사이.',
    '· 조사지 원본과 설계는 9번, 계산식을 세운 과정은 11번 슬라이드(숨김)에 있습니다.',
  ]);
  s.addNotes('계산식을 먼저 만들면 맞는지 확인할 방법이 없습니다. 그래서 현장 감각부터 숫자로 받았습니다. 두 계산식을 같은 20케이스에 돌려 비교했더니, A안은 작은 약국에서 3배 높고 큰 약국에서 3분의 2로 낮았습니다. 방향이 반대라 어떤 배수로도 동시에 못 맞춥니다.');
}

// ── 9. (숨김) 조사지 원본 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.hist });
  s.hidden = true;
  s.addText('조사지는 이렇게 생겼다', { placeholder: 'title' });
  backupBadge(s);
  lead(s, '마지막 칸을 비워 두고, 그 왼쪽에 계산기가 낸 금액을 미리 적어 두었습니다.');

  const rows = [
    head(['케이스', '지역', '월 조제료', '일반약 일매출', '월세', '보증금', '계산기 적정 금액', '내가 보는 적정 권리금']),
    ['2', '강남 등 서울 핵심', '2,900', '29', '460', '8,500', '70,300', ''],
    ['7', '서울 일반', '1,200', '18', '330', '3,500', '19,600', ''],
    ['9', '수도권', '800', '13', '160', '4,000', '7,739', ''],
    ['14', '광역시', '2,700', '34', '200', '4,000', '57,500', ''],
    ['17', '지방 중소도시', '1,500', '19', '140', '2,500', '24,400', ''],
  ];
  s.addTable(rows, tableOpts({ x: 0.7, y: 1.85, w: 11.9, fontSize: 12,
    colW: [0.9, 2.3, 1.3, 1.5, 1.0, 1.2, 1.6, 2.1], rowH: 0.45 }));

  const notes = [
    ['설계', '가상 20케이스 = 지역 5권역 × 규모별 · 조건은 무작위로 흩뿌림'],
    ['의도적 공개', '계산기가 낸 금액을 숨기지 않고 함께 제시'],
    ['표본', '1차 2명 40응답 (계산식 선택) → 2차 3명 60응답 (숫자 보정)'],
  ];
  notes.forEach((n, i) => {
    const x = 0.7 + i * 4.1;
    card(s, x, 4.75, 3.7, 1.0, { name: 'n' + i });
    s.addText(n[0], { x: x + 0.22, y: 4.85, w: 3.3, h: 0.3, fontSize: 13, bold: true,
      color: T.accent1, isTextBox: true, margin: 0 });
    s.addText(n[1], { x: x + 0.22, y: 5.15, w: 3.3, h: 0.55, fontSize: 11.5, color: C.text2,
      isTextBox: true, margin: 0 });
  });
  s.addText('백지에서 적게 하면 답이 흩어지지만, 금액을 옆에 두면 "높다 / 낮다"는 판단이 함께 나옵니다',
    { x: 0.7, y: 5.95, w: 11.9, h: 0.45, fontSize: 15, bold: true, color: T.accent1,
      isTextBox: true, margin: 0 });
  footnote(s, 6.45, ['· 금액 단위는 만원. 전체 20케이스는 저장소의 조사 원자료(CSV)에 그대로 들어 있습니다.']);
  s.addNotes('숨김 슬라이드입니다. "조사를 어떻게 했냐"는 질문이 나오면 띄우세요.');
}

// ── 10. 1차 검증 ──
{
  const s = pres.addSlide({ masterName: 'CHART', sectionTitle: SEC.hist });
  s.addText('1차 — 두 군데만 고쳐 적중률 68% → 85%', { placeholder: 'title' });
  s.addChart(pres.charts.BAR, [
    { name: '고치기 전', labels: ['전체', '작은 약국', '보통~큰 약국'], values: [1.129, 0.647, 1.156] },
    { name: '고친 후', labels: ['전체', '작은 약국', '보통~큰 약국'], values: [0.999, 0.992, 1.006] },
  ], Object.assign(chartBase(), {
    placeholder: 'chart',
    chartColors: [T.accent5, T.accent1],
    showValue: true, dataLabelPosition: 'outEnd', dataLabelFormatCode: '0.00',
    showLegend: true, legendPos: 'b', legendColor: T.dk2, legendFontSize: 12,
    valAxisMaxVal: 1.4, valAxisMinVal: 0,
  }));
  s.addText([
    { text: '조사원 응답 ÷ 계산기 금액 (1.00이면 완전 일치)', options: { fontSize: 12, color: T.accent5, breakLine: true } },
    { text: '발견', options: { bold: true, fontSize: 16, color: C.text1, breakLine: true } },
    { text: '보통~큰 약국 17건 — 계산기가 15% 낮음', options: { bullet: true, breakLine: true, fontSize: 13 } },
    { text: '작은 약국 3건 — 계산기가 35% 높음', options: { bullet: true, breakLine: true, fontSize: 13 } },
    { text: '조치', options: { bold: true, fontSize: 16, color: C.text1, breakLine: true } },
    { text: '전 지역 개월수를 1.15배로', options: { bullet: true, breakLine: true, fontSize: 13 } },
    { text: '작은 약국 배수를 0.88 → 0.50으로', options: { bullet: true, breakLine: true, fontSize: 13 } },
    { text: '적중 41/60 → 51/60', options: { bold: true, fontSize: 17, color: T.accent1, breakLine: true } },
    { text: '조사원 3은 9/20 → 18/20 — 높게 보는 성향이 보정으로 흡수', options: { fontSize: 12, color: C.text2 } },
  ], { placeholder: 'body' });
  bridge(s, 5.8, '그런데 쓰다 보니 이상한 게 나왔습니다');
  footnote(s, 6.25, [
    '· 막대는 "조사원이 적은 금액 ÷ 계산기 금액"의 가운데 값. 1.00보다 크면 계산기가 낮게, 작으면 높게 본 것입니다.',
    '· 계산식을 어떻게 세웠는지(11번)와 5단계 식·지역별 개월수 표(12번)는 숨김 슬라이드에 있습니다.',
  ]);
  s.addNotes('1차 계산식을 만들고 60응답과 대조했더니 두 가지가 보였습니다. 큰 약국은 계산기가 낮고, 작은 약국은 높았습니다. 전 지역을 일괄로 올리고 작은 약국만 따로 내렸더니 적중이 41건에서 51건으로 올랐습니다.');
}

// ── 11. (숨김) 1차 계산식을 어떻게 세웠나 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.hist });
  s.hidden = true;
  s.addText('자료가 있는 건 자료로, 없는 건 장치로', { placeholder: 'title' });
  backupBadge(s);
  lead(s, '1차 계산식은 한 번에 나온 게 아니라, 항목마다 근거를 따로 두고 쌓아 올렸습니다.');

  const rows = [
    head(['무엇을', '무엇을 근거로', '어떻게 정했나']),
    ['① 전체 구조', '0단계 40응답', '매출 × 12개월(7/40) vs 회수배수(32/40) → 회수배수 채택'],
    ['② 지역별 개월수', '거래 사례 362건 가중회귀 (참고 자료)', '분포의 30 / 50 / 75 백분위를 빠른 매도 · 적정 · 높은 호가로'],
    ['③ 규모 배수', '업계 통념 + 조사 응답', '조제료 5구간에 0.50 ~ 1.35'],
    ['④ 안 되는 구간', '실무 관행', '월 700만원 미만은 권리금이 성립하지 않음 → 시설비 수준 고정'],
    ['⑤ 리스크 할인', '실무 관행', '메인 병원 의사 65세 ×0.7 · 75세 ×0.5'],
    [{ text: '⑥ 교정', options: { bold: true } },
     { text: '2026-09 조사 60응답', options: { bold: true } },
     { text: '전 지역 개월수 ×1.15 · 작은 약국 배수 0.88 → 0.50', options: { bold: true, color: T.accent1 } }],
  ];
  s.addTable(rows, tableOpts({ x: 0.7, y: 1.66, w: 11.9, fontSize: 12,
    colW: [2.2, 3.6, 6.1], rowH: 0.4 }));

  s.addText('자료가 있는 항목은 자료로, 없는 항목은 "틀렸을 때 덜 다치는 쪽"으로 — 그리고 ⑥에서 숫자로 교정했습니다',
    { x: 0.7, y: 4.5, w: 11.9, h: 0.3, fontSize: 14, bold: true, color: '2A2E73',
      isTextBox: true, margin: 0 });

  kicker(s, 0.7, 4.88, '② 지역 회수배수는 이 분포에서 30 / 50 / 75 백분위를 뽑은 값입니다 — 362건은 공개 실거래가 아닌 참고용 사례 모음, 큰 그림은 30번(숨김)', null, 11.9);
  shot(s, 'recovery-dist-band.png', 2.92, 5.2, 7.5, 1.5,
    '지역별 회수배수 분포 — 조사 60응답(점)과 모델 표의 p30~p75 밴드, p50 선');
  s.addNotes('숨김 슬라이드입니다. "그 숫자들은 어디서 나왔냐"는 질문에 띄우세요. 위 표는 항목마다 근거가 다르다는 점을, 아래 그림은 ②를 어떻게 뽑았는지를 보여줍니다. 주황 점이 조사 응답이고 연보라 띠가 모델 표의 범위입니다. 가운데 선(p50)이 점들의 한가운데에 놓인다는 점만 짚으시면 됩니다. 큰 그림은 30번에 있습니다.');
}

// ── 12. (숨김) 1차 계산식 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.hist });
  s.hidden = true;
  s.addText('1차 계산식 — 5단계', { placeholder: 'title' });
  backupBadge(s);
  lead(s, '권리금 = 한 달에 남는 돈 × 지역별 개월수 × 조제료 규모 배수');

  const steps = [
    ['①', '한 달에 남는 돈', '조제 수익 + 일반약 마진 − (월세 + 보증금 기회비용)'],
    ['②', '규모 배수', '조제료 5구간마다 고정값 — 0.50 / 1.00 / 1.15 / 1.25 / 1.35'],
    ['③', '3단계 금액', '지역별 개월수(빠른 매도 / 적정 / 높은 호가) × 저수익 할인'],
    ['④', '의사 고령 할인', '65세 이상이면 ×0.7 · 75세 이상이면 ×0.5'],
    ['⑤', '보정', '사용자 의견 ±10% · 설문 결과 ±15% 범위 안에서만'],
  ];
  steps.forEach((st, i) => {
    const y = 1.9 + i * 0.86;
    s.addText(st[0], { x: 0.7, y, w: 0.45, h: 0.4, fontSize: 17, bold: true, color: T.accent1,
      isTextBox: true, margin: 0 });
    s.addText(st[1], { x: 1.2, y, w: 2.5, h: 0.4, fontSize: 15, bold: true, color: C.text1,
      isTextBox: true, margin: 0 });
    s.addText(st[2], { x: 1.2, y: y + 0.36, w: 5.9, h: 0.4, fontSize: 12.5, color: C.text2,
      isTextBox: true, margin: 0 });
  });

  kicker(s, 7.5, 1.7, '지역별 개월수 (2026-09 조사 반영)', null, 5.1);
  const rows = [
    head(['지역', '빠른 매도', '적정', '높은 호가']),
    ['강남 등 서울 핵심', '20.2', '25.8', '33.2'],
    ['서울 일반', '16.9', '21.5', '27.7'],
    ['수도권 (경기·인천)', '17.7', '21.0', '27.1'],
    ['광역시', '13.6', '20.2', '26.6'],
    ['지방 중소도시', '12.2', '18.2', '23.9'],
  ];
  s.addTable(rows, tableOpts({ x: 7.5, y: 2.0, w: 5.1, fontSize: 12,
    colW: [2.1, 1.0, 1.0, 1.0], rowH: 0.46 }));
  s.addText('계산식은 프로그램 안 한 군데에만 있습니다 — 세 버전이 모두 같은 식을 불러 씁니다',
    { x: 7.5, y: 4.95, w: 5.1, h: 0.7, fontSize: 12, color: C.text2, isTextBox: true, margin: 0 });
  footnote(s, 6.25, [
    '· 저수익 할인 — 한 달에 남는 돈이 700만원에 못 미치면 권리금 거래가 사실상 어려워 시설비 수준으로 낮춥니다.',
    '· 표의 숫자는 "월 수익의 몇 배로 보느냐"입니다. 서울 일반 21.5 = 월 수익의 21.5배가 적정선.',
  ]);
  s.addNotes('숨김 슬라이드입니다. "계산식이 정확히 뭐냐"는 질문에 띄우세요.');
}

// ── 13. 1차 절벽 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.hist });
  s.addText('그런데 — 조제료 1만원 차이로 권리금이 2배가 됐다', { placeholder: 'title' });
  lead(s, '같은 조건에서 조제료만 1만원 바꾼 결과 — 서울 일반 · 일매출 18만원 · 월세 100만원 · 보증금 3,500만원');

  shot(s, 'v1-cliff-compare.jpg', 1.05, 1.72, 11.2, 3.86,
    '1차 계산 결과 비교 — 조제료 999만원(규모 배수 0.50, 9,118만원~1.49억원)과 1,000만원(1.00, 1.83~2.99억원)');

  const jumps = [['1,000만원', '0.50 → 1.00', '+100%', T.accent6],
                 ['2,500만원', '1.00 → 1.15', '+15%', T.dk2],
                 ['3,500만원', '1.15 → 1.25', '+9%', T.dk2],
                 ['5,000만원', '1.25 → 1.35', '+8%', T.dk2]];
  jumps.forEach((j, i) => {
    const x = 0.7 + i * 3.05;
    card(s, x, 5.68, 2.8, 0.78, { fill: i === 0 ? 'FDE7E6' : C.background2,
      line: i === 0 ? 'F3B3B1' : 'D6DAE5', name: 'jump' + i });
    s.addText(j[0] + ' 경계', { x: x + 0.2, y: 5.73, w: 1.7, h: 0.28, fontSize: 11.5,
      color: C.text2, isTextBox: true, margin: 0 });
    s.addText(j[1], { x: x + 0.2, y: 6.0, w: 1.7, h: 0.28, fontSize: 11.5, color: C.text2,
      isTextBox: true, margin: 0 });
    s.addText(j[2], { x: x + 1.75, y: 5.78, w: 0.9, h: 0.5, fontSize: 17, bold: true,
      color: j[3], align: 'right', valign: 'middle', isTextBox: true, margin: 0 });
  });
  bridge(s, 6.52, '"이 절벽을 완만하게 만들 수 없을까?" — 2차의 출발점');
  s.addNotes('구간을 나눈 편의가 그대로 결과의 왜곡이 됐습니다. 999만원과 1,000만원 약국이 두 배 차이 날 리가 없습니다. 쓰는 사람에게는 신뢰 문제입니다. 여기서 2차 작업이 시작됩니다.');
}

// ── 14. 2차 곡선 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.hist });
  s.addText('2차 — 네 지점은 두고, 사이만 직선으로 이었다', { placeholder: 'title' });
  s.addText('다섯 숫자는 조사로 검증된 자산이라 그대로 두고, "구간마다 고정"이라는 가정만 바꿨습니다.',
    { x: 0.7, y: 1.2, w: 11.9, h: 0.3, fontSize: 15, color: C.text2, isTextBox: true, margin: 0 });
  shot(s, 'presc-mult-curve.png', 2.49, 1.64, 8.36, 3.9,
    '조제료 규모 배수 그래프 — 1차 계단형과 2차 직선 연결이 1,000/2,500/3,500/5,000만원 지점에서 만난다');

  const facts = [
    ['1,000만원 경계에서', '+100% → +0.05%'],
    ['나머지 경계', '모두 +0.01% 이하'],
    ['같은 조건 999만원', '1차 9,118만원 → 2차 1.82억원'],
  ];
  facts.forEach((f, i) => {
    const x = 0.7 + i * 4.1;
    card(s, x, 5.72, 3.7, 0.74, { fill: 'E3E6F5', line: 'B9BEE6', name: 'fact' + i });
    s.addText(f[0], { x: x + 0.22, y: 5.76, w: 3.3, h: 0.28, fontSize: 11.5, color: '2A2E73',
      isTextBox: true, margin: 0 });
    s.addText(f[1], { x: x + 0.22, y: 6.04, w: 3.3, h: 0.34, fontSize: 14, bold: true,
      color: '2A2E73', isTextBox: true, margin: 0 });
  });
  bridge(s, 6.5, '그러면 2차가 더 정확해야 할 텐데 — 같은 60건으로 확인해 봤습니다');
  s.addNotes('구간 사이를 직선으로 이었습니다. 네 지점의 값은 1차와 똑같으니 조사로 얻은 기준은 그대로 보존됩니다. 절벽은 사라졌습니다. 다른 방법들을 왜 버렸는지는 15번 숨김 슬라이드에 있습니다.');
}

// ── 15. (숨김) 2차 방법 선택 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.hist });
  s.hidden = true;
  s.addText('2차 — 세 가지 안을 두고 고른 과정', { placeholder: 'title' });
  backupBadge(s);
  lead(s, '바꿀 것은 숫자가 아니라 "구간마다 고정"이라는 가정이었습니다.');

  const rows = [
    head(['안', '방법', '장점', '버린 이유']),
    ['1', '구간을 더 잘게 쪼갠다', '가장 손쉬움', '튐이 작아질 뿐 사라지지 않음 · 숫자표만 4배로 늘어남'],
    ['2', '곡선을 새로 그린다 (통계로 다시 계산)', '수학적으로 매끄러움', '검증한 다섯 숫자가 사라짐 · 20건으로 곡선까지 추정하면 억지로 맞추는 셈'],
    [{ text: '3', options: { bold: true } },
     { text: '네 지점은 두고 사이만 직선으로', options: { bold: true } },
     { text: '검증 결과 100% 보존', options: { bold: true } },
     { text: '채택', options: { bold: true, color: T.accent1 } }],
  ];
  s.addTable(rows, tableOpts({ x: 0.7, y: 1.9, w: 11.9, fontSize: 12.5,
    colW: [0.7, 3.5, 2.6, 5.1], rowH: 0.66 }));

  card(s, 0.7, 4.45, 11.9, 1.6, { fill: INK_BG, line: INK_BG, name: 'code' });
  s.addText([
    { text: '두 지점 사이의 중간값을 구하는 식', options: { color: '9AA0BC', breakLine: true } },
    { text: '중간값 = 앞 지점 값 + (지금 위치 − 앞 지점) ÷ (뒤 지점 − 앞 지점) × (뒤 지점 값 − 앞 지점 값)', options: { color: '9FA8E8', breakLine: true } },
    { text: '', options: { breakLine: true } },
    { text: '다섯 숫자를 "구간의 대표값"이 아니라 1,000 / 2,500 / 3,500 / 5,000만원 "지점의 값"으로 다시 해석', options: { color: 'DCE0E9' } },
  ], { x: 1.05, y: 4.62, w: 11.2, h: 1.3, fontSize: 13, isTextBox: true, margin: 0 });

  footnote(s, 6.2, [
    '· 개발에서는 이 방식을 선형보간이라고 부릅니다 — 두 점 사이를 직선으로 이어 중간값을 구하는 방법.',
    '· "억지로 맞추는 셈" = 과적합 — 표본이 적은데 복잡한 곡선을 맞추면 그 20건에만 맞고 실제에는 안 맞게 됩니다.',
  ]);
  s.addNotes('숨김 슬라이드입니다. "왜 그 방법을 골랐냐"는 질문에 띄우세요.');
}

// ── 16. 2차 검증 (핵심) ──
{
  const s = pres.addSlide({ masterName: 'CHART', sectionTitle: SEC.hist });
  s.addText('매끄럽게 만들었더니, 오히려 덜 맞았다', { placeholder: 'title' });
  s.addChart(pres.charts.BAR, [
    { name: '1차', labels: ['전체', '작은 약국', '보통~큰 약국'], values: [0.999, 0.992, 1.006] },
    { name: '2차', labels: ['전체', '작은 약국', '보통~큰 약국'], values: [0.926, 0.525, 0.943] },
  ], Object.assign(chartBase(), {
    placeholder: 'chart',
    chartColors: [T.accent1, T.accent2],
    showValue: true, dataLabelPosition: 'outEnd', dataLabelFormatCode: '0.00',
    showLegend: true, legendPos: 'b', legendColor: T.dk2, legendFontSize: 12,
    valAxisMaxVal: 1.2, valAxisMinVal: 0,
  }));
  s.addText([
    { text: '조사원 응답 ÷ 계산기 금액 (1.00이면 완전 일치)', options: { fontSize: 12, color: T.accent5, breakLine: true } },
    { text: '범위 적중  1차 51/60  ·  2차 42/60', options: { bold: true, fontSize: 16, color: C.text1, breakLine: true } },
    { text: '차이는 전부 작은 약국에서', options: { bold: true, fontSize: 15, color: T.accent2, breakLine: true } },
    { text: '2차는 직선으로 이어 조제료 800만원에 배수 0.90', options: { bullet: true, breakLine: true, fontSize: 13 } },
    { text: '1차의 0.50은 작은 약국 응답으로 직접 맞춘 값', options: { bullet: true, breakLine: true, fontSize: 13 } },
    { text: '작은 약국 표본이 3건뿐 → 지금은 결론 불가', options: { bullet: true, breakLine: true, fontSize: 13 } },
    { text: '→ 1차를 기본값 유지, 2차는 비교용으로 존치', options: { bold: true, fontSize: 15, color: T.accent1 } },
  ], { placeholder: 'body' });
  bridge(s, 5.8, '여기까지는 입력한 숫자만 썼습니다 — 3차는 입력하지 않은 정보를 가져옵니다');
  footnote(s, 6.25, [
    '· 2차가 더 매끄럽지만 작은 약국에서 금액을 높게 잡습니다 — 1차의 0.50이 실제 응답으로 맞춘 값이라 서로 충돌합니다.',
    '· 검증 데이터를 미리 만들어 두지 않았다면, 더 매끄럽다는 이유로 2차를 기본값으로 바꿨을 것입니다.',
  ]);
  s.addNotes('이 장이 발표에서 가장 중요한 장면입니다. 2차는 목표였던 절벽 제거에는 성공했지만 적중률은 오히려 떨어졌습니다. 더 매끄러운 식이 더 정확한 식은 아니었고, 검증 데이터가 있었기 때문에 쓰기 전에 알 수 있었습니다. 여기서 잠깐 멈추셔도 좋습니다.');
}

// ── 17. 3차 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.hist });
  s.addText('3차 — 사용자가 모르는 정보를 대신 가져왔다', { placeholder: 'title' });
  lead(s, '"주변에 병원이 몇 곳인가"는 인수자가 알 수 없습니다. 주소만 넣으면 공개 데이터에서 받아옵니다.');

  shot(s, 'v3-realdata.jpg', 0.7, 1.62, 6.1, 2.36,
    '주변 의료기관 실측 — 강남구 병·의원 3,119곳 / 약국 515곳, 약국 1곳당 6.1곳');
  shot(s, 'v3-validate.jpg', 0.7, 4.24, 6.1, 1.9,
    'AI 입력 검증 — 선택한 상권 유형이 실제 위치와 다르다고 지적하고 수정 시 금액까지 제시');

  const picks = [
    ['받아오는 것', '병·의원 수 · 약국 수 · 반경 500m 상가 · 주소 좌표', T.accent1],
    ['판정', '약국 1곳당 병·의원 수를 전국 평균 1.7곳과 비교\n강남구 6.1 : 1 = 평균의 3.6배 → 처방 여력 넉넉', T.accent4],
    ['AI의 역할', '금액은 손대지 않고 해설만\n고른 상권 유형이 실제와 다르면 지적하고 금액까지 다시 계산', T.accent3],
    ['없으면', '그 칸만 "미조회" — 권리금 계산은 그대로', T.dk2],
  ];
  picks.forEach((p, i) => {
    const y = 1.62 + i * 1.12;
    card(s, 7.2, y, 5.4, 1.02, { fill: 'FFFFFF', line: i === 3 ? 'D6DAE5' : p[2], name: 'p' + i });
    s.addText(p[0], { x: 7.42, y: y + 0.05, w: 4.9, h: 0.28, fontSize: 13, bold: true,
      color: p[2], isTextBox: true, margin: 0 });
    s.addText(p[1], { x: 7.42, y: y + 0.32, w: 5.0, h: 0.64, fontSize: 11.5, color: C.text2,
      isTextBox: true, margin: 0 });
  });
  footnote(s, 6.3, [
    '· 3차의 오차 — 1차 기초값에 화면 기본 조정(복수 병원 +10%)을 얹어 같은 60건에 돌리면 적중 46건(77%) · 응답 대비 0.91. 조정을 끄면 1차와 같은 51건입니다.',
    '· 바꿔 말하면 매물 조정 계수(+10% 등)는 아직 조사로 검증되지 않았습니다 — 로드맵 2단계 과제입니다.',
  ]);
  s.addNotes('3차는 입력하지 않은 정보를 주소만으로 가져옵니다. 왼쪽 위가 실제로 받아온 숫자, 왼쪽 아래가 AI의 지적입니다. 금액 자체는 1차 계산식이 그대로 내고, 3차는 그 위에 매물 조건을 곱합니다. 각주의 46건은 그 조정을 얹었을 때의 적중이고, 조정을 끄면 1차와 같은 51건입니다.');
}

// ── 18. 라이브 시연 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.hist });
  s.addText('실제 화면으로 보겠습니다', { placeholder: 'title' });
  lead(s, '지금 서버에 올라가 있는 화면입니다. 숫자를 바꿔 가며 바로 보여 드리겠습니다.');

  card(s, 0.7, 1.85, 11.9, 0.9, { fill: INK_BG, line: INK_BG, name: 'url' });
  s.addText('recruit.dailypharm.com / addon / calu', { x: 0.7, y: 1.85, w: 11.9, h: 0.9,
    fontSize: 24, bold: true, color: 'FFFFFF', align: 'center', valign: 'middle',
    charSpacing: 1, isTextBox: true, margin: 0 });

  const steps = [
    ['①', '같은 조건을 넣어 본다', '서울 일반 · 조제료 1,200 · 일반약 18 · 월세 330 · 보증금 3,500\n→ 범위와 "계산 근거" 표가 함께 나온다', T.accent1],
    ['②', '조제료만 1만원 바꾼다', '월세를 100으로 두고 999 → 1,000\n→ 9,118만원이 1.83억으로 뛰는 절벽을 그 자리에서 확인', T.accent6],
    ['③', '주소를 넣어 본다', '권리금 계산 v3 · "서울 강남구 역삼동" 입력 (비우면 실측이 안 붙는다)\n→ 병·의원 3,100곳대 실측과 AI 입력 검증이 함께 나온다', T.accent4],
  ];
  steps.forEach((st, i) => {
    const x = 0.7 + i * 4.1;
    card(s, x, 3.0, 3.7, 2.35, { fill: 'FFFFFF', line: st[3], name: 'dm' + i });
    s.addShape(pres.shapes.OVAL, { x: x + 0.25, y: 3.22, w: 0.5, h: 0.5,
      fill: { color: st[3] }, line: { color: st[3] }, objectName: 'dn' + i });
    s.addText(st[0], { x: x + 0.25, y: 3.22, w: 0.5, h: 0.5, fontSize: 14, bold: true,
      color: 'FFFFFF', align: 'center', valign: 'middle', isTextBox: true, margin: 0 });
    s.addText(st[1], { x: x + 0.25, y: 3.84, w: 3.2, h: 0.34, fontSize: 15, bold: true,
      color: C.text1, isTextBox: true, margin: 0 });
    s.addText(st[2], { x: x + 0.25, y: 4.22, w: 3.25, h: 1.0, fontSize: 12, color: C.text2,
      isTextBox: true, margin: 0 });
  });

  card(s, 0.7, 5.6, 11.9, 0.78, { fill: 'FFF1E0', line: 'FFD4A8', name: 'fallback' });
  s.addText('시연이 막히면 — 13번(절벽)과 17번(실측·AI) 화면 캡처로 그대로 이어서 설명하시면 됩니다 · 실측 수치는 조회 시점마다 조금씩 달라집니다',
    { x: 1.1, y: 5.6, w: 11.1, h: 0.78, fontSize: 13.5, bold: true, color: 'A64B00',
      isTextBox: true, margin: 0, valign: 'middle' });
  bridge(s, 6.5, '여기까지가 만든 과정입니다 — 그래서 무엇이 달라졌는지로 넘어가겠습니다');
  s.addNotes('발표 전에 브라우저 탭 세 개를 미리 열어 두세요 — 권리금 계산 v1, v3, 그리고 status.php. status.php에서 DB 연결과 AI 상태가 초록인지 먼저 확인하고 시작합니다. ③에서 주소를 반드시 입력하세요. 비워 두고 산정하면 주변 의료기관이 "미조회"로 나옵니다. 실측 수치는 공공데이터를 그때그때 조회하는 값이라 캡처와 몇 곳 차이가 날 수 있으니, 화면에 뜬 숫자를 그대로 읽으시면 됩니다. 시연은 2분 안쪽으로 끊고, AI 분석이 10초 넘게 돌면 기다리지 말고 넘어가세요.');
}

// ── 19. (숨김) 3차 데이터 목록 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.hist });
  s.hidden = true;
  s.addText('3차 — 가져올 수 있는 데이터를 찾은 결과', { placeholder: 'title' });
  backupBadge(s);
  lead(s, '가장 갖고 싶은 데이터일수록 공개돼 있지 않았습니다.');

  const rows = [
    head(['데이터', '어디서', '쓰임', '상태']),
    ['병·의원 목록', '국립중앙의료원', '그 지역 병·의원 수', '적용'],
    ['약국 목록', '국립중앙의료원', '그 지역 약국 수', '적용'],
    ['상가 정보', '소상공인시장진흥공단', '반경 500m 업소·업종 분포', '적용'],
    ['주소 → 위치 좌표', '카카오 지도', '상가 조회에 쓸 좌표 확보', '적용'],
    ['처방·진료비 통계', '심평원·건보공단', '병원 의존도를 숫자로', '약국 단위 공개 없음'],
    ['권리금 실거래', '—', '검증 축 하나 더 확보', '공개 자료 자체가 없음'],
  ];
  s.addTable(rows, tableOpts({ x: 0.7, y: 1.9, w: 7.6, fontSize: 12,
    colW: [2.0, 2.0, 2.3, 1.3], rowH: 0.5 }));

  kicker(s, 8.6, 1.72, '판정 기준 — 약국 1곳당 병·의원 수', null, 4.0);
  const verdicts = [
    ['전국 평균 1.7곳 기준', '', T.dk2],
    ['평균의 1.2배 이상', '처방 들어올 여력 넉넉', T.accent4],
    ['평균 ±20% 이내', '전국 평균 수준', T.dk2],
    ['평균의 0.8배 이하', '경쟁이 센 편', T.accent6],
  ];
  verdicts.forEach((v, i) => {
    const y = 2.02 + i * 0.75;
    card(s, 8.6, y, 4.0, 0.66, { fill: i === 0 ? C.background2 : 'FFFFFF', name: 'v' + i });
    s.addText(v[0], { x: 8.8, y: y + (v[1] ? 0.03 : 0.16), w: 3.6, h: 0.32, fontSize: 12.5,
      bold: i === 0, color: v[2], isTextBox: true, margin: 0 });
    if (v[1]) {
      s.addText(v[1], { x: 8.8, y: y + 0.33, w: 3.6, h: 0.3, fontSize: 12, bold: true,
        color: v[2], isTextBox: true, margin: 0 });
    }
  });
  card(s, 0.7, 5.5, 11.9, 0.8, { fill: 'E3E6F5', line: 'B9BEE6', name: 'policy' });
  s.addText('데이터를 못 받아와도 해당 칸만 "미조회"로 바뀌고 권리금 계산은 그대로 됩니다 — 실측은 참고 정보이지 계산의 전제가 아닙니다',
    { x: 1.1, y: 5.5, w: 11.1, h: 0.8, fontSize: 13.5, bold: true, color: '2A2E73',
      isTextBox: true, margin: 0, valign: 'middle' });
  footnote(s, 6.45, ['· 처방 통계는 약국 단위로 공개되지 않고, 권리금 실거래 자료는 아예 존재하지 않습니다 — 이 계산기가 "추정"인 이유입니다.']);
  s.addNotes('숨김 슬라이드입니다. "어떤 데이터를 쓰냐"는 질문에 띄우세요.');
}

// ── 20. (숨김) AI 지시문과 실패 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.hist });
  s.hidden = true;
  s.addText('3차 — AI에게 금액은 맡기지 않는다', { placeholder: 'title' });
  backupBadge(s);
  lead(s, '계산은 프로그램이 끝내고, AI에게는 해설만 맡깁니다 — 같은 입력에 늘 같은 금액이 나와야 하니까요.');

  card(s, 0.7, 1.8, 5.3, 4.3, { fill: INK_BG, line: INK_BG, name: 'prompt' });
  s.addText([
    { text: 'AI에게 준 지시문 (발췌)', options: { color: '9FA8E8', bold: true, breakLine: true, fontSize: 12 } },
    { text: '', options: { breakLine: true } },
    { text: '너는 대한민국 약국 양수도(권리금)', options: { color: 'DCE0E9', breakLine: true } },
    { text: '전문 컨설팅 시스템이다.', options: { color: 'DCE0E9', breakLine: true } },
    { text: '[계산된 수치]는 시스템이 산출한', options: { color: 'FFB266', breakLine: true } },
    { text: '확정 금액이므로 다시 계산하거나', options: { color: 'FFB266', breakLine: true } },
    { text: '다른 금액을 제시하지 마라.', options: { color: 'FFB266', breakLine: true } },
    { text: '너의 역할은 상권 분석, 위험 평가,', options: { color: 'DCE0E9', breakLine: true } },
    { text: '협상 전략 서술이다.', options: { color: 'DCE0E9', breakLine: true } },
    { text: '', options: { breakLine: true } },
    { text: '※ 금액 하나가 아니라 범위로 제시된', options: { color: '9AA0BC', breakLine: true } },
    { text: '   값이다. 협상 설명도 이 범위를', options: { color: '9AA0BC', breakLine: true } },
    { text: '   기준으로 하라.', options: { color: '9AA0BC', breakLine: true } },
    { text: '', options: { breakLine: true } },
    { text: '[써야 할 항목]', options: { color: 'DCE0E9', breakLine: true } },
    { text: '1 상권  2 위험  3 협상 전략', options: { color: 'DCE0E9', breakLine: true } },
    { text: '4 계약 전 확인 목록  5 자금 조언', options: { color: 'DCE0E9', breakLine: true } },
    { text: '6 입력 검증  7·8 대안 제시', options: { color: 'DCE0E9', breakLine: true } },
  ], { x: 1.0, y: 1.97, w: 4.8, h: 4.0, fontSize: 11, isTextBox: true, margin: 0 });

  const rows = [
    head(['처음에 깨진 것', '어떻게 막았나']),
    ['같은 입력인데 금액이 매번 달랐다', '계산을 프로그램이 전담 · 다시 계산하지 말라고 못 박음'],
    ['답이 긴 줄글로 와서 화면에 못 넣었다', '답변을 8개 칸으로 나눠 받도록 형식을 고정'],
    ['범위를 "적정 5억"으로 단정했다', '금액 바로 아래에 "범위 기준으로 쓰라"를 넣음'],
    ['검증이 "타당합니다"로만 끝났다', '정해진 보기 중에서 고르게 하고, 고치면 금액까지 재계산'],
    ['긴 답변이 중간에 잘렸다', '답변 길이 상한을 설정값으로 분리하고 항목을 쪼갬'],
    ['의사 고령일 때 금액이 과하게 낮았다', '할인이 두 군데 걸려 있던 것을 한 곳으로 통일'],
  ];
  s.addTable(rows, tableOpts({ x: 6.3, y: 1.8, w: 6.3, fontSize: 11.5,
    colW: [2.75, 3.55], rowH: 0.6 }));

  footnote(s, 6.3, [
    '· AI에는 "창의성" 설정이 있는데 이 계산기는 0으로 고정했습니다 — 같은 질문에 늘 같은 답을 하게 하기 위해서입니다.',
    '· 공통점 한 줄: 말로 부탁한 것은 전부 깨졌고, 형식으로 못 박은 것만 지켜졌습니다.',
  ]);
  s.addNotes('숨김 슬라이드입니다. "AI를 어디까지 믿냐"는 질문에 띄우세요. 실패 사례 표가 설득력이 큽니다.');
}

// ═══════════════════════════════════════════════════ 3. SO WHAT ═══
pres.addSection({ title: SEC.res });

// ── 21. 전환 SO WHAT ──
divider(SEC.res, 3, 'SO WHAT', '그래서 무엇이 달라졌나',
  '0단계부터 3차까지의 수정 기록',
  '세 버전의 역할과, 이 검증을 한 번으로 끝내지 않기 위한 장치',
  '세 번째입니다. 여기까지가 만든 과정이었고, 이제 그래서 무엇이 달라졌는지 정리하겠습니다.');

// ── 22. 종합 비교 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.res });
  s.addText('세 버전은 경쟁이 아니라 역할 분담', { placeholder: 'title' });

  const rows = [
    head(['항목', '1차', '2차', '3차']),
    ['기본 계산', '구간마다 고정 배수', '배수 구하는 법만 교체', '1차 계산식을 그대로 호출'],
    ['조사 60건 중 적중', '51건 (85%)', '42건 (70%)', '기초 동일 · 기본 조정 시 46건'],
    ['입력 항목', '5개', '5개', '15개 이상'],
    ['바깥 데이터', '없음', '없음', '병·의원 · 약국 · 상가 · AI'],
    ['같은 입력 → 같은 결과', '○', '○', '○ (AI는 해설만)'],
    ['쓰임', '기본 시세 확인', '튀는 구간 비교', '매물 실사 · 협상 준비'],
  ];
  s.addTable(rows, tableOpts({ x: 0.7, y: 1.7, w: 11.9, fontSize: 13,
    colW: [2.9, 2.9, 2.6, 3.5], rowH: 0.5 }));

  kicker(s, 0.7, 5.24, '3차는 조사 응답과 직접 비교가 안 돼 네 가지로 확인했습니다');
  const axes = [['기본 금액 일치', '1차 엔진을 그대로 호출'], ['재현성', '같은 입력 → 같은 금액'],
                ['실측 정확도', '강남 6.1:1 = 평균의 3.6배'], ['고장 대비', '미조회 안내 · 예비 AI 전환']];
  axes.forEach((a, i) => {
    const x = 0.7 + i * 3.05;
    card(s, x, 5.54, 2.8, 0.84, { fill: 'E6E9F4', line: 'B4BBDD', name: 'axis' + i });
    s.addText(a[0], { x: x + 0.2, y: 5.6, w: 2.45, h: 0.3, fontSize: 12.5, bold: true,
      color: '273164', isTextBox: true, margin: 0 });
    s.addText(a[1], { x: x + 0.2, y: 5.89, w: 2.45, h: 0.38, fontSize: 11, color: '3A4580',
      isTextBox: true, margin: 0 });
  });
  bridge(s, 6.48, '문제는, 이 85%가 2026년 9월에 멈춰 있다는 점입니다');
  s.addNotes('세 버전은 경쟁 관계가 아니라 역할 분담입니다. 3차는 1차 계산식을 그대로 불러 기초 금액을 내고, 그 위에 매물 조건을 곱합니다. 그래서 조정을 끄면 1차와 같은 51건이고, 화면 기본 조정을 얹으면 46건입니다. 조정 계수 자체는 아직 조사로 검증하지 못했다는 점을 솔직하게 말씀하시면 됩니다.');
}

// ── 23. 상시 검증 구조 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.res });
  s.addText('그래서 조사를 앱 안으로 넣었다', { placeholder: 'title' });
  s.addText('계산기는 60응답으로 맞춰졌고, 그 60응답은 2026년 9월에 멈춰 있습니다.',
    { x: 0.7, y: 1.22, w: 8.2, h: 0.4, fontSize: 15, color: C.text2, isTextBox: true, margin: 0 });

  const flow = [
    ['설문', '응답자 1명당 6케이스 · 2~3분 · 익명'],
    ['저장', '유형 · 지역 · 조사원 · 공개 전 추정액 · 판정 · 조정액을 기록'],
    ['집계', '관리자 화면에서 유형별 가운데 값과 판정 분포 확인'],
    ['반영', '버튼 한 번으로 계산기 숫자에 적용'],
  ];
  flow.forEach((f, i) => {
    const y = 1.9 + i * 0.98;
    s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x: 0.7, y, w: 1.45, h: 0.74, rectRadius: 0.08,
      fill: { color: T.accent1 }, line: { color: T.accent1 }, objectName: 'fl' + i });
    s.addText(f[0], { x: 0.7, y, w: 1.45, h: 0.74, fontSize: 14, bold: true, color: 'FFFFFF',
      align: 'center', valign: 'middle', isTextBox: true, margin: 0 });
    s.addText(f[1], { x: 2.35, y: y + 0.17, w: 6.3, h: 0.5, fontSize: 13, color: C.text2,
      isTextBox: true, margin: 0 });
    if (i < 3) {
      s.addShape(pres.shapes.LINE, { x: 1.42, y: y + 0.74, w: 0, h: 0.24,
        line: { color: 'B9BEE6', width: 1.5 }, objectName: 'fla' + i });
    }
  });

  shot(s, 'survey-card.jpg', 9.1, 1.55, 3.3, 4.55,
    '적정성 설문 케이스 화면 — 조건표, 계산기 금액, 판정과 조정 입력');

  card(s, 0.7, 5.7, 7.9, 0.74, { fill: C.background2, name: 'guard' });
  s.addText('안전장치 — 응답자 유형 4종 분리 · 30건 이상 쌓인 지역만 · 조정 폭 ±10~15% · 관리자 확인 후 반영',
    { x: 1.0, y: 5.7, w: 7.4, h: 0.74, fontSize: 12.5, bold: true, color: C.text1,
      isTextBox: true, margin: 0, valign: 'middle' });
  bridge(s, 6.5, '쓸수록 정확해지는 구조 — 다만 표본이 모여야 작동합니다');
  s.addNotes('0단계에서 사람이 하던 일을 사용자가 대신하고, 그 결과가 다시 계산식으로 들어가는 순환입니다. 계산식이 응답 몇 건에 흔들리지 않도록 안전장치를 함께 뒀습니다.');
}

// ── 24. (숨김) 원자료와 재현성 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.res });
  s.hidden = true;
  s.addText('발표의 모든 수치는 원자료에서 다시 계산된다', { placeholder: 'title' });
  backupBadge(s);
  lead(s, '조사 결과를 문장으로만 남기지 않고, 원자료와 계산 과정을 함께 보관했습니다.');

  kicker(s, 0.7, 1.72, '원자료 — 2026-09 적정성 조사');
  const rows = [
    head(['기록 항목', '내용']),
    ['케이스 조건', '지역 · 월 조제료 · 일반약 비율·일매출 · 월세 · 보증금'],
    ['계산기 제시 금액', '빠른 매도 · 적정 · 높은 호가 (조사 당시 값)'],
    ['응답', '조사원 1 · 2 · 3 이 적은 적정 권리금'],
    ['규모', '20케이스 × 3명 = 60응답'],
  ];
  s.addTable(rows, tableOpts({ x: 0.7, y: 2.05, w: 6.0, fontSize: 12,
    colW: [1.8, 4.2], rowH: 0.62 }));

  kicker(s, 7.1, 1.72, '다시 돌려 보기 — 검증용 계산 파일', null, 5.5);
  card(s, 7.1, 2.05, 5.5, 2.48, { fill: INK_BG, line: INK_BG, name: 'term' });
  s.addText([
    { text: '1차 고치기 전   적중 41/60 (68%)', options: { color: '9AA0BC', breakLine: true } },
    { text: '1차 고친 후     적중 51/60 (85%)', options: { color: '9FA8E8', breakLine: true } },
    { text: '2차 고치기 전   적중 42/60 (70%)', options: { color: '9AA0BC', breakLine: true } },
    { text: '2차 고친 후     적중 42/60 (70%)', options: { color: 'FF9A9D', breakLine: true } },
    { text: '', options: { breakLine: true } },
    { text: '+ 케이스별 · 조사원별 · 작은 약국 비교', options: { color: 'DCE0E9' } },
  ], { x: 7.35, y: 2.25, w: 5.0, h: 2.1, fontSize: 11.5, isTextBox: true, margin: 0 });

  card(s, 0.7, 4.85, 11.9, 1.25, { fill: C.background2, name: 'why' });
  s.addText('검증 지표는 넷 — 범위 적중 · 응답 ÷ 계산기 배율 · 케이스별 적중 · 조사원별 적중',
    { x: 1.05, y: 5.03, w: 11.2, h: 0.4, fontSize: 14, bold: true, color: C.text1, isTextBox: true, margin: 0 });
  s.addText('적중 건수만 보면 "어느 쪽으로 틀렸는지"를 알 수 없기 때문입니다. 방향을 알아야 어떤 숫자를 고칠지 결정할 수 있습니다.',
    { x: 1.05, y: 5.45, w: 11.2, h: 0.5, fontSize: 13, color: C.text2, isTextBox: true, margin: 0 });

  footnote(s, 6.25, [
    '· 검증용 계산 파일 — 조사 원자료를 읽어 네 조합(1차·2차 × 고치기 전·후)을 한 번에 다시 계산해 주는 작은 프로그램입니다.',
    '· 발표 중 "그 수치 근거는요?" 질문이 나오면 이 장을 띄우고, 저장소에 원자료가 그대로 있다고 답하시면 됩니다.',
  ]);
  s.addNotes('숨김 슬라이드입니다. 수치 근거를 묻는 질문에 띄우세요.');
}

// ── 25. (숨김) 데이터와 키 발급 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.res });
  s.hidden = true;
  s.addText('무료 공개 데이터 3종, 없어도 계산은 된다', { placeholder: 'title' });
  backupBadge(s);

  const apis = [
    ['공공데이터포털', '정부 공개 데이터', ['병의원 찾기', '약국 찾기', '상가(상권)정보'],
     '회원가입 후 세 가지를\n각각 신청 → 인증키 복사', T.accent1],
    ['카카오 지도', '주소 → 위치 좌표', ['주소를 좌표로 변환', '주소 검색창 (신청 불필요)'],
     '개발자 사이트에서\n앱 등록 → 키 복사', T.accent3],
    ['Google AI (Gemini)', '분석 문장 작성', ['상권·위험 해설', '사용자 입력 검증'],
     'AI 스튜디오 접속 →\n키 생성 버튼 한 번', T.accent4],
  ];
  apis.forEach((a, i) => {
    const x = 0.7 + i * 4.1;
    card(s, x, 1.5, 3.7, 3.8, { fill: 'FFFFFF', line: a[4], name: 'api' + i });
    s.addShape(pres.shapes.ROUNDED_RECTANGLE, { x, y: 1.5, w: 3.7, h: 0.5, rectRadius: 0.08,
      fill: { color: a[4] }, line: { color: a[4] }, objectName: 'apihead' + i });
    s.addText(a[0], { x: x + 0.22, y: 1.5, w: 3.3, h: 0.5, fontSize: 15, bold: true,
      color: 'FFFFFF', valign: 'middle', isTextBox: true, margin: 0 });
    s.addText(a[1], { x: x + 0.22, y: 2.12, w: 3.3, h: 0.3, fontSize: 11.5, color: C.text2,
      isTextBox: true, margin: 0 });
    s.addText(a[2].map((t, j) => ({ text: t, options: { bullet: true, breakLine: j < a[2].length - 1 } })),
      { x: x + 0.22, y: 2.5, w: 3.3, h: 1.1, fontSize: 12.5, color: C.text1, isTextBox: true, margin: 0 });
    s.addText('받는 방법', { x: x + 0.22, y: 3.72, w: 3.3, h: 0.3, fontSize: 10.5, bold: true,
      color: T.accent5, charSpacing: 1, isTextBox: true, margin: 0 });
    s.addText(a[3], { x: x + 0.22, y: 4.0, w: 3.3, h: 1.0, fontSize: 11.5, color: C.text2,
      isTextBox: true, margin: 0 });
  });

  card(s, 0.7, 5.5, 11.9, 0.62, { fill: 'FDE7E6', line: 'F3B3B1', name: 'warn' });
  s.addText('⚠  공공데이터포털은 두 가지 키를 주는데 "Decoding" 쪽을 써야 합니다 — 다른 쪽을 넣으면 조회가 실패합니다',
    { x: 1.05, y: 5.5, w: 11.2, h: 0.62, fontSize: 13, bold: true, color: 'A32C27',
      isTextBox: true, margin: 0, valign: 'middle' });
  footnote(s, 6.3, [
    '· 인증키 — 데이터를 받아올 때 쓰는 신분증 같은 문자열입니다. 공개하면 안 되고, 서버 설정 파일에만 넣습니다.',
    '· 키가 없을 때: 해당 칸만 "미조회" · 조회 실패: 안내 후 계산 계속 · AI가 느릴 때: 예비 AI로 자동 전환.',
  ]);
  s.addNotes('숨김 슬라이드입니다. "이 데이터 어떻게 받냐 / 돈 드냐"는 질문에 띄우세요.');
}

// ═══════════════════════════════════════════════════ 4. NEXT ═══
pres.addSection({ title: SEC.next });

// ── 26. 전환 NEXT ──
divider(SEC.next, 4, 'NEXT', '앞으로 무엇을 하면 되나',
  '세 버전의 역할과 상시 검증 구조',
  '표본이 모이면 결론 나는 것들, 그리고 이 도구가 하지 않는 것',
  '마지막입니다. 지금 상태에서 무엇이 남았는지, 그리고 이 도구가 하지 않는 것이 무엇인지로 마무리하겠습니다.');

// ── 27. 로드맵 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.next });
  s.addText('표본을 채우면 남은 판단이 데이터로 결정된다', { placeholder: 'title' });

  const stages = [
    ['1단계 · 단기', '표본 확보', [
      '지역별 30건 이상 → 지역 보정 가동',
      '지방 중소도시 집중 (조사원 간 편차 큼)',
      '큰 약국(조제료 3,500만원+) 표본 0건 해소',
    ], '목표 — 지역 보정 자동 가동', T.accent1],
    ['2단계 · 중기', '계산 정교화', [
      '1차 / 2차 중 하나를 데이터로 확정',
      '병원 의존도를 단계가 아닌 숫자로',
      '저수익 기준선을 지역별로 분리',
      '실거래 확보 경로 (중개사·컨설팅 협력)',
    ], '목표 — 남은 판단을 데이터로 종결', T.accent3],
    ['3단계 · 장기', '활용 확장', [
      '권리금을 바닥·영업·시설로 나눠 제시',
      '매물 여러 건 한 번에 비교',
      '협상 자리에 들고 갈 PDF 리포트',
      '지역·규모별 시세 흐름 보기',
    ], '목표 — 협상 현장에서 쓰는 산출물', T.accent4],
  ];
  stages.forEach((st, i) => {
    const x = 0.7 + i * 4.1;
    const col = st[4];
    frame(s, x, 1.5, 3.7, 3.75, col, 'rmf' + i);
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
      line: { color: 'DCE0E9', width: 1 }, objectName: 'rmd' + i });
    s.addText(st[3], { x: x + 0.22, y: 4.8, w: 3.3, h: 0.35, fontSize: 12, bold: true,
      color: col, isTextBox: true, margin: 0 });
  });

  card(s, 0.7, 5.5, 11.9, 0.78, { fill: C.background2, name: 'ops' });
  s.addText('운영 과제 — 데이터 이용 계정을 운영용으로 전환 · AI 응답 지연 대응 · 숫자를 언제 왜 바꿨는지 기록 관리',
    { x: 1.05, y: 5.5, w: 11.2, h: 0.78, fontSize: 13, color: C.text1,
      isTextBox: true, margin: 0, valign: 'middle' });
  bridge(s, 6.45, '1단계가 가장 급합니다 — 표본만 모이면 나머지는 데이터가 답을 줍니다');
  s.addNotes('1단계가 가장 급합니다. 지역별 30건이 모이면 지역 보정이 자동으로 돌고, 작은 약국 표본이 쌓이면 1차와 2차 중 어느 쪽이 맞는지도 결론이 납니다.');
}

// ── 28. 한계와 마무리 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.next });
  s.addText('실거래 조회가 아니라, 설명 가능한 추정', { placeholder: 'title' });
  lead(s, '오해를 막기 위해 한계를 분명히 둡니다.');

  const limits = [
    ['공개 실거래 자료 없음', '약국 권리금의 실제 거래가를 모아 둔 공개 자료는 없습니다. 수익 구조에 기반한 추정입니다.'],
    ['실거래는 ±30% 변동', '1인약국 여부 · 병원 안정성 · 건물 형태 · 임대 조건에 따라 달라집니다.'],
    ['검증 표본의 한계', '조사원 3명 · 20케이스 기준이며, 조제료 3,500만원 이상 큰 약국은 아직 검증 못 했습니다.'],
  ];
  limits.forEach((l, i) => {
    const y = 1.9 + i * 1.08;
    card(s, 0.7, y, 11.9, 0.92, { name: 'lim' + i });
    s.addText(l[0], { x: 1.05, y: y + 0.1, w: 3.4, h: 0.35, fontSize: 14, bold: true,
      color: T.accent6, isTextBox: true, margin: 0 });
    s.addText(l[1], { x: 4.5, y: y + 0.12, w: 7.8, h: 0.7, fontSize: 13, color: C.text2,
      isTextBox: true, margin: 0 });
  });

  card(s, 0.7, 5.35, 11.9, 1.45, { fill: INK_BG, line: INK_BG, name: 'closing' });
  s.addText('"그냥 그 정도" 대신, 그 금액이 어떻게 나왔는지 설명할 수 있게',
    { x: 1.1, y: 5.6, w: 11.1, h: 0.5, fontSize: 19, bold: true, color: 'FFFFFF',
      isTextBox: true, margin: 0 });
  s.addText('검증 데이터를 먼저 만들고 그에 맞춰 계산기를 고쳐 나간 순서 — 그 덕분에 2차를 기본값으로 삼지 않는 판단까지 숫자로 내릴 수 있었습니다.',
    { x: 1.1, y: 6.12, w: 11.1, h: 0.5, fontSize: 13, color: '9AA0BC', isTextBox: true, margin: 0 });
  s.addNotes('마무리입니다. 이 도구가 하지 않는 것을 분명히 하는 것도 중요합니다. 가치는 금액을 정해 주는 데 있지 않고, 그 금액이 어떻게 나왔는지 설명할 수 있게 하는 데 있습니다. 질문 받겠습니다.');
}

// ═══════════════════════════════════════════════════ 부록 ═══
pres.addSection({ title: SEC.ref });

// ── 29. (숨김) 용어 정리 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.ref });
  s.hidden = true;
  s.addText('부록 — 발표 중 나올 수 있는 용어', { placeholder: 'title' });
  backupBadge(s);
  lead(s, '질문이 들어오면 이 한 줄로 답하시면 됩니다.');

  const rows = [
    head(['용어', '한 줄 설명']),
    ['v1 · v2 · v3', '계산기의 1차 · 2차 · 3차 버전. 화면에도 같은 이름으로 표시됩니다.'],
    ['회수개월수', '권리금을 월 수익의 몇 배로 보느냐. 21.5개월 = 월 수익의 21.5배.'],
    ['규모 배수', '조제료가 클수록 더 쳐주고 작으면 깎는 곱하기 값 (0.50 ~ 1.35).'],
    ['보증금 기회비용', '보증금으로 묶인 돈을 은행에 뒀다면 받았을 이자. 월세에 더해 계산.'],
    ['매출총이익', '매출에서 약값·물건값을 뺀, 가게에 남는 이익.'],
    ['선형보간', '두 지점 사이를 직선으로 이어 중간값을 구하는 방법 (2차에서 사용).'],
    ['과적합', '표본이 적은데 복잡한 식을 맞춰, 그 표본에만 맞게 되는 것.'],
    ['API · 인증키', '기관 데이터를 프로그램이 바로 받아오는 통로와, 그때 쓰는 신분증 문자열.'],
    ['창의성 0 설정', 'AI가 같은 질문에 늘 같은 답을 하도록 고정한 설정.'],
  ];
  s.addTable(rows, tableOpts({ x: 0.7, y: 1.75, w: 11.9, fontSize: 13,
    colW: [2.8, 9.1], rowH: 0.47 }));
  s.addNotes('숨김 슬라이드입니다. 용어 질문이 나오면 띄우고 읽으시면 됩니다.');
}

// ── 30. (숨김) 회수배수 분포 ──
{
  const s = pres.addSlide({ masterName: 'CONTENT', sectionTitle: SEC.ref });
  s.hidden = true;
  s.addText('지역 회수배수는 이 분포에서 뽑았다', { placeholder: 'title' });
  backupBadge(s);
  lead(s, '조사 60응답을 같은 방법으로 역산한 결과 — 모델 표의 p50(적정)이 분포 가운데에 놓입니다.');
  shot(s, 'recovery-dist.png', 1.3, 1.68, 10.72, 4.62,
    '조사 60응답에서 역산한 회수배수 분포 — 전체 히스토그램의 p30 19.0 / p50 21.0 / p75 23.9, 지역별 점 분포와 모델 표 p30~p75 밴드');
  footnote(s, 6.46, ['· 모델 표의 숫자는 거래 사례 362건에서 뽑은 값이고, 이 그림은 같은 방법을 조사 60응답에 다시 적용한 것입니다 — 두 분포가 겹치는지 보는 용도.']);
  s.addNotes('숨김 슬라이드입니다. "그 개월수는 어떻게 나온 숫자냐"는 질문에 띄우세요. 왼쪽은 전체 60응답을 늘어놓은 것이고, 세로선 셋이 30·50·75 백분위입니다. 오른쪽은 지역별로 흩어 놓고 모델 표의 범위를 겹쳐 둔 것입니다. 지역당 12건뿐이라 조사 쪽 폭이 좁습니다 — 표본이 쌓이면 이 그림으로 표를 직접 갱신할 수 있습니다.');
}

// ── 저장 ──
pres.writeFile({ fileName: OUT })
  .then(() => applyTheme(OUT, THEME))
  .then(() => console.log('생성 완료:', OUT))
  .catch((e) => { console.error(e); process.exit(1); });
