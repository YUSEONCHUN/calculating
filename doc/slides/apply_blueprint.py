# 블루프린트 스킨 — 만들어진 덱에 색·서체·격자를 덮어씌워 별도 파일로 만든다.
#
#   python3 doc/slides/apply_blueprint.py doc/slides/권리금계산기_프로젝트.pptx
#   → 권리금계산기_프로젝트_블루프린트.pptx
#
# 원본 deck.js 는 건드리지 않는다. 스킨만 바꾸므로 내용·배치·숨김 설정은 그대로 유지된다.
#
# 방향 B(블루프린트): 옅은 블루그레이 바탕에 격자를 깔고, 수치는 고정폭 글꼴로 세운다.
#   "감이 아니라 계산"이라는 메시지를 디자인이 먼저 전달하도록.
import re
import shutil
import sys
import zipfile
from pathlib import Path

from pptx import Presentation
from pptx.util import Inches, Pt
from lxml import etree

A = 'http://schemas.openxmlformats.org/drawingml/2006/main'
P = 'http://schemas.openxmlformats.org/presentationml/2006/main'

# ── 색 매핑 (현재 사내 템플릿 색 → 블루프린트) ──
HEX = {
    # 테마 축
    '151517': '0F1E33',   # 본문 잉크 → 딥 네이비 잉크
    '444444': '3A4A5E',   # 보조 텍스트
    'DCE0E9': 'DCE6F2',   # 밝은 면
    '42469F': '163A6B',   # 주색 인디고 → 딥 블루
    'FF6600': 'C8553D',   # 주의·2차 → 테라코타
    '7E86D6': '5B8FD9',   # 보조 → 라이트 블루
    '273164': '102A4C',   # 짙은 면 (표지·전환 배경)
    '8A90A8': '7E8DA3',   # 연회색
    'E0453F': 'A33327',   # 문제 지점 레드
    # 페이지·카드
    'E9ECF4': 'F3F6FA',   # 페이지 배경
    '1B1F43': '102A4C',   # 어두운 카드
    'E3E6F5': 'DCE8F7',   # 파란 틴트 카드
    'B9BEE6': 'B9CEE8',
    '2A2E73': '163A6B',
    'FFF1E0': 'FBEDE8',   # 주의 틴트
    'FFD4A8': 'EFC7BA',
    'A64B00': 'A33327',
    'FDE7E6': 'FBE8E3',   # 경고 틴트
    'F3B3B1': 'E9B3A5',
    'A32C27': 'A33327',
    'E6E9F4': 'E7F1EC',   # 검증 배지 → 그린 틴트
    'B4BBDD': 'B5D6C8',
    '3A4580': '2E7D6B',
    'D6DAE5': 'D7E1EE',   # 테두리·구분선
    # 어두운 카드 안의 글자
    '9FA8E8': '7FB2E8',
    '9AA0BC': '93A6BD',
    'FFB266': 'EFA882',
    'FF9A9D': 'EFA093',
    'FF9A4D': 'E08A6B',   # 전환 슬라이드의 큰 단어
}

MONO = 'Consolas'        # 맥·윈도우 오피스에 모두 들어 있는 고정폭
NUM_RE = re.compile(r'^[\s\d.,/:%~+\-–—×()]+$')

SECTION_TAG = [
    (1, 5, 'WHY / 01'), (6, 20, 'HOW / 02'),
    (21, 25, 'SO WHAT / 03'), (26, 28, 'NEXT / 04'), (29, 30, 'APPENDIX'),
]


def remap_hex(src: Path, dst: Path):
    """모든 XML 파트의 srgbClr 값을 바꾼다 (테마·마스터·레이아웃·슬라이드·차트)"""
    pat = re.compile(r'(val=")([0-9A-Fa-f]{6})(")')

    def sub(m):
        return m.group(1) + HEX.get(m.group(2).upper(), m.group(2)) + m.group(3)

    zin = zipfile.ZipFile(src)
    with zipfile.ZipFile(dst, 'w', zipfile.ZIP_DEFLATED) as zout:
        for item in zin.infolist():
            data = zin.read(item.filename)
            if item.filename.endswith('.xml') and (
                item.filename.startswith('ppt/slides/')
                or item.filename.startswith('ppt/slideLayouts/')
                or item.filename.startswith('ppt/slideMasters/')
                or item.filename.startswith('ppt/charts/')
                or item.filename.startswith('ppt/theme/')
            ):
                text = data.decode('utf-8')
                text = pat.sub(sub, text)
                if item.filename.startswith('ppt/charts/'):
                    # 차트 수치 라벨만 고정폭으로
                    text = re.sub(
                        r'(<c:dLbls>.*?</c:dLbls>)',
                        lambda m: m.group(1).replace('typeface="+mn-lt"', f'typeface="{MONO}"'),
                        text, flags=re.S)
                data = text.encode('utf-8')
            zout.writestr(item, data)
    zin.close()


def el(xml: str):
    return etree.fromstring(xml)


def grid_shapes(start_id: int, w_in=13.333, h_in=7.5, step=0.4167):
    """레이아웃 뒤쪽에 깔 옅은 격자 — 도면 느낌의 바탕"""
    out = []
    i = start_id
    xs = [round(x * step, 4) for x in range(1, int(w_in / step) + 1)]
    ys = [round(y * step, 4) for y in range(1, int(h_in / step) + 1)]
    for x in xs:
        cx = int(x * 914400)
        out.append(f'''<p:cxnSp xmlns:p="{P}" xmlns:a="{A}"><p:nvCxnSpPr>
          <p:cNvPr id="{i}" name="grid_v{i}"/><p:cNvCxnSpPr/><p:nvPr/></p:nvCxnSpPr>
          <p:spPr><a:xfrm><a:off x="{cx}" y="0"/><a:ext cx="0" cy="{int(h_in*914400)}"/></a:xfrm>
          <a:prstGeom prst="line"><a:avLst/></a:prstGeom>
          <a:ln w="6350"><a:solidFill><a:srgbClr val="E2E9F2"/></a:solidFill></a:ln></p:spPr></p:cxnSp>''')
        i += 1
    for y in ys:
        cy = int(y * 914400)
        out.append(f'''<p:cxnSp xmlns:p="{P}" xmlns:a="{A}"><p:nvCxnSpPr>
          <p:cNvPr id="{i}" name="grid_h{i}"/><p:cNvCxnSpPr/><p:nvPr/></p:nvCxnSpPr>
          <p:spPr><a:xfrm><a:off x="0" y="{cy}"/><a:ext cx="{int(w_in*914400)}" cy="0"/></a:xfrm>
          <a:prstGeom prst="line"><a:avLst/></a:prstGeom>
          <a:ln w="6350"><a:solidFill><a:srgbClr val="E2E9F2"/></a:solidFill></a:ln></p:spPr></p:cxnSp>''')
        i += 1
    return out


def add_grid_to_layouts(prs):
    """본문·차트 레이아웃 바탕에 격자를 넣는다 (슬라이드마다 넣지 않아 파일이 가볍다)"""
    done = 0
    seen = set()
    for slide in prs.slides:
        lay = slide.slide_layout
        name = lay.element.find('.//' + f'{{{P}}}cSld').get('name') or ''
        if name not in ('CONTENT', 'CHART') or name in seen:
            continue
        seen.add(name)
        spTree = lay.element.find(f'.//{{{P}}}cSld/{{{P}}}spTree')
        anchor = spTree[1]              # nvGrpSpPr, grpSpPr 다음부터가 도형
        for x in reversed(grid_shapes(900 + done * 200)):
            anchor.addnext(el(x))
        done += 1
    return done


def tag_for(num):
    for a, b, t in SECTION_TAG:
        if a <= num <= b:
            return t
    return ''


def add_section_tags(prs):
    """좌상단에 섹션 태그 — 도면의 도면번호처럼"""
    n = 0
    for i, slide in enumerate(prs.slides, 1):
        lay = slide.slide_layout.element.find('.//' + f'{{{P}}}cSld').get('name') or ''
        if lay not in ('CONTENT', 'CHART'):
            continue
        tb = slide.shapes.add_textbox(Inches(0.7), Inches(0.14), Inches(4), Inches(0.26))
        tf = tb.text_frame
        tf.margin_left = tf.margin_right = tf.margin_top = tf.margin_bottom = 0
        r = tf.paragraphs[0].add_run()
        r.text = tag_for(i)
        r.font.size = Pt(10)
        r.font.name = MONO
        r.font.color.rgb = __import__('pptx.dml.color', fromlist=['RGBColor']).RGBColor(0x7E, 0x8D, 0xA3)
        n += 1
    return n


def mono_numbers(prs):
    """숫자만 있는 글자는 고정폭으로 — 자릿수가 맞아떨어져 '계산된 값'으로 읽힌다"""
    n = 0

    def walk(tf):
        nonlocal n
        for para in tf.paragraphs:
            for run in para.runs:
                t = run.text.strip()
                if t and any(c.isdigit() for c in t) and NUM_RE.match(t):
                    run.font.name = MONO
                    n += 1

    for slide in prs.slides:
        for sh in slide.shapes:
            if sh.has_text_frame:
                walk(sh.text_frame)
            if sh.has_table:
                for row in sh.table.rows:
                    for cell in row.cells:
                        walk(cell.text_frame)
    return n


def main():
    src = Path(sys.argv[1] if len(sys.argv) > 1 else 'doc/slides/권리금계산기_프로젝트.pptx')
    out = src.with_name(src.stem + '_블루프린트.pptx')
    tmp = src.with_name('.tmp_blueprint.pptx')
    remap_hex(src, tmp)
    prs = Presentation(str(tmp))
    g = add_grid_to_layouts(prs)
    t = add_section_tags(prs)
    m = mono_numbers(prs)
    prs.save(str(out))
    tmp.unlink()
    print(f'{out.name} — 격자 레이아웃 {g}종 · 섹션 태그 {t}장 · 고정폭 숫자 {m}곳')


if __name__ == '__main__':
    main()
