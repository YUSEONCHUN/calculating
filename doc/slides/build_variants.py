# 발표용 변형본 두 가지를 만든다 — 원본은 건드리지 않는다.
#
#   python3 doc/slides/build_variants.py doc/slides/권리금계산기_프로젝트.pptx
#
#   ① _애니메이션.pptx — 클릭할 때마다 단계가 하나씩 나타난다 (PowerPoint 애니메이션 XML 삽입)
#   ② _분할.pptx       — 같은 단계를 슬라이드 여러 장으로 쪼갠다 (애니메이션 없이 같은 효과)
#
# 단계는 STEPS 에 "영역"으로 적는다. 그 사각형 안에 중심이 들어오는 도형이 한 단계로 묶인다.
# 어느 단계에도 속하지 않는 도형(제목·리드·푸터)은 처음부터 보인다.
import copy
import sys
from pathlib import Path

from pptx import Presentation
from pptx.oxml.ns import qn
from lxml import etree

E = 914400

# 슬라이드 번호 → 단계별 영역 (x0, y0, x1, y1 / 인치)
STEPS = {
    3:  [(0.60, 1.90, 3.60, 4.45), (3.65, 1.90, 6.65, 4.45),
         (6.70, 1.90, 9.70, 4.45), (9.75, 1.90, 12.75, 4.45),
         (0.60, 4.70, 12.70, 6.20)],
    5:  [(0.60, 1.80, 4.45, 3.80), (4.70, 1.80, 8.55, 3.80), (8.80, 1.80, 12.65, 3.80),
         (0.60, 3.75, 12.70, 5.20), (0.60, 5.20, 12.70, 6.20)],
    7:  [(0.60, 1.80, 12.70, 2.70), (0.60, 2.70, 12.70, 3.62), (0.60, 3.62, 12.70, 4.54),
         (0.60, 4.54, 12.70, 5.46), (0.60, 5.46, 12.70, 6.40)],
    13: [(0.60, 1.60, 12.70, 5.70), (0.60, 5.60, 12.70, 6.60)],
    16: [(0.50, 1.60, 8.10, 5.70), (8.30, 1.60, 12.80, 5.70)],
    17: [(0.55, 1.50, 7.00, 6.30), (7.10, 1.55, 12.70, 6.10)],
    18: [(0.60, 1.80, 12.70, 2.80), (0.60, 3.10, 4.50, 6.10),
         (4.70, 3.10, 8.60, 6.10), (8.80, 3.10, 12.70, 6.10)],
    22: [(0.60, 1.60, 12.70, 5.20), (0.60, 5.20, 12.70, 6.50)],
}

NS_P = 'http://schemas.openxmlformats.org/presentationml/2006/main'


def center(shape):
    return ((shape.left + shape.width / 2) / E, (shape.top + shape.height / 2) / E)


def step_of(shape, boxes):
    """도형 중심이 어느 단계 영역에 들어가는지 — 없으면 None(처음부터 보임)"""
    if shape.left is None:
        return None
    cx, cy = center(shape)
    for i, (x0, y0, x1, y1) in enumerate(boxes):
        if x0 <= cx <= x1 and y0 <= cy <= y1:
            return i
    return None


def grouped_shapes(slide, boxes):
    """단계별 도형 목록 (단계 수만큼의 리스트)"""
    groups = [[] for _ in boxes]
    for sh in slide.shapes:
        i = step_of(sh, boxes)
        if i is not None:
            groups[i].append(sh)
    return groups


# ─────────────────────────────── ① 애니메이션 ───────────────────────────────

def timing_xml(step_ids):
    """단계별 도형 id 목록 → 클릭할 때마다 하나씩 나타나는 p:timing 트리"""
    uid = [3]  # cTn id 는 유일해야 한다 (1·2 는 루트·mainSeq 가 쓴다)

    def nid():
        uid[0] += 1
        return uid[0]

    pars = []
    for ids in step_ids:
        if not ids:
            continue
        sets = []
        for sid in ids:
            sets.append(
                f'<p:set><p:cBhvr><p:cTn id="{nid()}" dur="1" fill="hold">'
                f'<p:stCondLst><p:cond delay="0"/></p:stCondLst></p:cTn>'
                f'<p:tgtEl><p:spTgt spid="{sid}"/></p:tgtEl>'
                f'<p:attrNameLst><p:attrName>style.visibility</p:attrName></p:attrNameLst>'
                f'</p:cBhvr><p:to><p:strVal val="visible"/></p:to></p:set>'
            )
        inner = ''.join(
            f'<p:par><p:cTn id="{nid()}" presetID="1" presetClass="entr" presetSubtype="0" '
            f'fill="hold" grpId="0" nodeType="{"clickEffect" if k == 0 else "withEffect"}">'
            f'<p:stCondLst><p:cond delay="0"/></p:stCondLst>'
            f'<p:childTnLst>{st}</p:childTnLst></p:cTn></p:par>'
            for k, st in enumerate(sets)
        )
        pars.append(
            f'<p:par><p:cTn id="{nid()}" fill="hold">'
            f'<p:stCondLst><p:cond delay="indefinite"/></p:stCondLst>'
            f'<p:childTnLst><p:par><p:cTn id="{nid()}" fill="hold">'
            f'<p:stCondLst><p:cond delay="0"/></p:stCondLst>'
            f'<p:childTnLst>{inner}</p:childTnLst></p:cTn></p:par></p:childTnLst></p:cTn></p:par>'
        )

    if not pars:
        return None
    return (
        f'<p:timing xmlns:p="{NS_P}">'
        f'<p:tnLst><p:par><p:cTn id="1" dur="indefinite" restart="never" nodeType="tmRoot">'
        f'<p:childTnLst><p:seq concurrent="1" nextAc="seek">'
        f'<p:cTn id="2" dur="indefinite" nodeType="mainSeq"><p:childTnLst>{"".join(pars)}</p:childTnLst></p:cTn>'
        f'<p:prevCondLst><p:cond evt="onPrev" delay="0"><p:tgtEl><p:sldTgt/></p:tgtEl></p:cond></p:prevCondLst>'
        f'<p:nextCondLst><p:cond evt="onNext" delay="0"><p:tgtEl><p:sldTgt/></p:tgtEl></p:cond></p:nextCondLst>'
        f'</p:seq></p:childTnLst></p:cTn></p:par></p:tnLst></p:timing>'
    )


def build_animated(src, out):
    prs = Presentation(src)
    touched = 0
    for num, boxes in STEPS.items():
        slide = prs.slides[num - 1]
        groups = grouped_shapes(slide, boxes)
        step_ids = [[sh.shape_id for sh in g] for g in groups]
        xml = timing_xml(step_ids)
        if xml is None:
            continue
        el = etree.fromstring(xml)
        sld = slide._element
        old = sld.find(qn('p:timing'))
        if old is not None:
            sld.remove(old)
        sld.append(el)          # p:timing 은 p:sld 의 마지막 자식이어야 한다
        touched += 1
    prs.save(out)
    return touched


# ─────────────────────────────── ② 슬라이드 분할 ───────────────────────────────

R_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships'


def clone_slide(prs, src_slide):
    """같은 레이아웃으로 새 슬라이드를 만들고 도형을 복사 — 그림·차트 참조(rId)는 새로 연결한다"""
    new = prs.slides.add_slide(src_slide.slide_layout)
    for shp in list(new.shapes):          # 레이아웃이 끼워 넣은 자리표시자 제거
        shp._element.getparent().remove(shp._element)

    rid_map = {}
    for rid, rel in src_slide.part.rels.items():
        if rel.is_external:
            rid_map[rid] = new.part.rels.get_or_add_ext_rel(rel.reltype, rel.target_ref)
        else:
            rid_map[rid] = new.part.relate_to(rel.target_part, rel.reltype)

    for shp in src_slide.shapes:
        el = copy.deepcopy(shp._element)
        for node in el.iter():
            for attr in list(node.attrib):
                if attr.startswith('{%s}' % R_NS) and node.get(attr) in rid_map:
                    node.set(attr, rid_map[node.get(attr)])
        new.shapes._spTree.append(el)

    if src_slide.has_notes_slide:
        new.notes_slide.notes_text_frame.text = src_slide.notes_slide.notes_text_frame.text
    return new


def move_slide(prs, slide, pos):
    """슬라이드를 원하는 위치로 옮긴다 (sldIdLst 순서가 발표 순서다)"""
    rid = None
    for r_id, rel in prs.part.rels.items():
        if rel.reltype.endswith('/slide') and not rel.is_external and rel.target_part is slide.part:
            rid = r_id
            break
    lst = prs.slides._sldIdLst
    target = [e for e in list(lst) if e.get(qn('r:id')) == rid][0]
    lst.remove(target)
    lst.insert(pos, target)


def build_split(src, out):
    prs = Presentation(src)
    added = 0
    for num in sorted(STEPS, reverse=True):     # 뒤에서부터 넣어야 앞쪽 번호가 안 밀린다
        boxes = STEPS[num]
        base = prs.slides[num - 1]
        groups = grouped_shapes(base, boxes)
        if len(groups) < 2:
            continue
        hidden_by_step = [[sh.shape_id for sh in g] for g in groups]

        # 원본은 1단계까지만 남기고, 2..N 단계는 복제본으로 만든다
        copies = []
        for k in range(1, len(groups)):
            c = clone_slide(prs, base)
            copies.append(c)
        # 복제본에서 아직 안 나올 단계의 도형을 지운다
        for k, c in enumerate(copies, start=1):
            keep = set()
            for g in hidden_by_step[:k + 1]:
                keep.update(g)
            for shp in list(c.shapes):
                sid = shp.shape_id
                owner = next((i for i, g in enumerate(hidden_by_step) if sid in g), None)
                if owner is not None and sid not in keep:
                    shp._element.getparent().remove(shp._element)
        # 원본은 1단계만
        keep0 = set(hidden_by_step[0])
        for shp in list(base.shapes):
            sid = shp.shape_id
            owner = next((i for i, g in enumerate(hidden_by_step) if sid in g), None)
            if owner is not None and sid not in keep0:
                shp._element.getparent().remove(shp._element)
        # 순서 정리 — 원본 바로 뒤에 1·2·… 순으로
        for k, c in enumerate(copies, start=1):
            move_slide(prs, c, num - 1 + k)
        added += len(copies)
    prs.save(out)
    return added


def main():
    src = Path(sys.argv[1] if len(sys.argv) > 1 else 'doc/slides/권리금계산기_프로젝트.pptx')
    anim = src.with_name(src.stem + '_애니메이션.pptx')
    split = src.with_name(src.stem + '_분할.pptx')
    n1 = build_animated(str(src), str(anim))
    n2 = build_split(str(src), str(split))
    print(f'애니메이션 — {anim.name} (슬라이드 {n1}장에 단계 적용)')
    print(f'슬라이드 분할 — {split.name} ({n2}장 추가)')


if __name__ == '__main__':
    main()
