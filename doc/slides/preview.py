# PPTX 레이아웃 프리뷰 — 슬라이드 도형 좌표를 그대로 HTML로 옮겨 브라우저에서 확인한다.
#
#   python3 doc/slides/preview.py doc/slides/권리금계산기_프로젝트.pptx /tmp/preview.html
#
# LibreOffice 없이도 레이아웃(겹침·넘침·여백)을 눈으로 점검하기 위한 근사 렌더러.
# 텍스트·도형·이미지·표·막대차트를 위치 그대로 그린다. 폰트 메트릭과 차트 축은
# 실제 PowerPoint와 완전히 같지는 않으므로 "배치 확인용"으로만 쓴다.
import sys, html, base64
from pptx import Presentation
from pptx.util import Emu

deck, out = sys.argv[1], sys.argv[2]
p = Presentation(deck)
EMU = 914400
SW, SH = p.slide_width/EMU, p.slide_height/EMU
PX = 96

def rgb(obj, default=None):
    try:
        if obj and obj.rgb is not None:
            return str(obj.rgb)
    except Exception:
        pass
    return default

parts = ['<!doctype html><meta charset="utf-8"><style>',
    'body{background:#52525b;font-family:"Apple SD Gothic Neo","Noto Sans KR",sans-serif;margin:0;padding:24px}',
    f'.slide{{position:relative;width:{SW*PX}px;height:{SH*PX}px;background:#fff;margin:0 auto 26px;overflow:hidden;box-shadow:0 6px 20px rgba(0,0,0,.35)}}',
    '.num{position:absolute;left:-54px;top:0;color:#fff;font-size:20px;font-weight:700}',
    '.tb{position:absolute;box-sizing:border-box;line-height:1.3}',
    '.sh{position:absolute;box-sizing:border-box}',
    'img{position:absolute}',
    'table.pt{position:absolute;border-collapse:collapse;table-layout:fixed}',
    'table.pt td{border:1px solid #e2e8f0;padding:3px 6px;vertical-align:middle;overflow:hidden}',
    '.chart{position:absolute;box-sizing:border-box;display:flex;flex-direction:column;justify-content:flex-end}',
    '.bars{display:flex;align-items:flex-end;justify-content:space-around;height:100%;gap:10px}',
    '.grp{display:flex;align-items:flex-end;gap:5px;height:100%;flex:1;justify-content:center}',
    '.bar{width:26px;display:flex;flex-direction:column;justify-content:flex-end;align-items:center}',
    '.bar i{display:block;width:100%}', '.bar b{font-size:11px;font-weight:600;margin-bottom:2px}',
    '.cat{font-size:11px;color:#334155;text-align:center;margin-top:4px;white-space:pre-line}',
    '.lg{font-size:11px;color:#334155;text-align:center;margin-top:6px}',
    '</style>']

def cell_text(cell):
    return html.escape(cell.text)

def render_table(gf, l, t, w, h):
    tbl = gf.table
    colw = [c.width/EMU*PX for c in tbl.columns]
    rows = []
    for ri, row in enumerate(tbl.rows):
        rh = row.height/EMU*PX
        tds = []
        for ci, cell in enumerate(row.cells):
            runs = [r for para in cell.text_frame.paragraphs for r in para.runs]
            fs = runs[0].font.size.pt if runs and runs[0].font.size else 12
            bold = any(r.font.bold for r in runs)
            col = rgb(runs[0].font.color, '0F172A') if runs else '0F172A'
            bg = rgb(cell.fill.fore_color, None) if cell.fill.type == 1 else None
            st = f'font-size:{fs}px;color:#{col};'
            if bold: st += 'font-weight:700;'
            if bg: st += f'background:#{bg};'
            tds.append(f'<td style="{st}">{cell_text(cell)}</td>')
        rows.append(f'<tr style="height:{rh}px">' + ''.join(tds) + '</tr>')
    cols = ''.join(f'<col style="width:{cw}px">' for cw in colw)
    return (f'<table class="pt" style="left:{l}px;top:{t}px;width:{w}px">' + cols + ''.join(rows) + '</table>')

PAL = ['0F766E', 'B45309', '0D9488', '166534', '94A3B8', 'C2410C']

def render_chart(gf, l, t, w, h):
    ch = gf.chart
    try:
        cats = [str(c) for c in ch.plots[0].categories]
    except Exception:
        cats = []
    series = []
    for si, s in enumerate(ch.series):
        vals = [v if v is not None else 0 for v in s.values]
        series.append((s.name or f'계열{si+1}', vals))
    if not series or not cats:
        return f'<div class="sh" style="left:{l}px;top:{t}px;width:{w}px;height:{h}px;border:1px dashed #94a3b8"></div>'
    mx = max(max(v for v in vals) for _, vals in series) or 1
    body_h = h - 46
    grp_html = []
    for ci, cat in enumerate(cats):
        bars = []
        for si, (nm, vals) in enumerate(series):
            v = vals[ci] if ci < len(vals) else 0
            bh = max(2, (v/mx) * (body_h - 18))
            lbl = f'{v:.2f}' if mx <= 3 else f'{v:.0f}'
            bars.append(f'<div class="bar"><b>{lbl}</b><i style="height:{bh}px;background:#{PAL[si%len(PAL)]}"></i></div>')
        grp_html.append('<div class="grp" style="flex-direction:column;justify-content:flex-end">'
                        f'<div style="display:flex;align-items:flex-end;gap:5px;height:{body_h}px">' + ''.join(bars) + '</div>'
                        f'<div class="cat">{html.escape(cat)}</div></div>')
    legend = ''
    if len(series) > 1:
        legend = '<div class="lg">' + ' &nbsp; '.join(
            f'<span style="color:#{PAL[i%len(PAL)]}">■</span> {html.escape(nm)}' for i, (nm, _) in enumerate(series)) + '</div>'
    return (f'<div class="chart" style="left:{l}px;top:{t}px;width:{w}px;height:{h}px">'
            f'<div class="bars" style="height:{body_h+18}px">' + ''.join(grp_html) + '</div>' + legend + '</div>')

for i, s in enumerate(p.slides, 1):
    parts.append(f'<div class="slide"><div class="num">{i}</div>')
    for sh in s.shapes:
        try:
            l, t, w, h = sh.left/EMU*PX, sh.top/EMU*PX, sh.width/EMU*PX, sh.height/EMU*PX
        except Exception:
            continue
        if sh.shape_type == 13:
            ip = sh.image
            b64 = base64.b64encode(ip.blob).decode()
            parts.append(f'<img src="data:{ip.content_type};base64,{b64}" style="left:{l}px;top:{t}px;width:{w}px;height:{h}px">')
            continue
        if sh.has_table:
            parts.append(render_table(sh, l, t, w, h)); continue
        if sh.has_chart:
            parts.append(render_chart(sh, l, t, w, h)); continue
        fill = None
        try:
            if sh.fill.type == 1: fill = rgb(sh.fill.fore_color)
        except Exception: pass
        line = None
        try: line = rgb(sh.line.color)
        except Exception: pass
        if fill or line:
            st = f'left:{l}px;top:{t}px;width:{w}px;height:{h}px;'
            if fill: st += f'background:#{fill};'
            if line: st += f'border:1px solid #{line};'
            st += 'border-radius:6px;'
            parts.append(f'<div class="sh" style="{st}"></div>')
        if sh.has_text_frame and sh.text_frame.text.strip():
            lines = []
            for para in sh.text_frame.paragraphs:
                lh = ''
                for r in para.runs:
                    fs = r.font.size.pt if r.font.size else 14
                    col = rgb(r.font.color, '111111')
                    b = 'font-weight:700;' if r.font.bold else ''
                    lh += f'<span style="font-size:{fs}px;color:#{col};{b}">{html.escape(r.text)}</span>'
                lines.append(lh or '&nbsp;')
            align = 'left'
            try:
                a = sh.text_frame.paragraphs[0].alignment
                align = {2: 'center', 3: 'right'}.get(int(a) if a is not None else 1, 'left')
            except Exception: pass
            parts.append(f'<div class="tb" style="left:{l}px;top:{t}px;width:{w}px;height:{h}px;text-align:{align}">'
                         + '<br>'.join(lines) + '</div>')
    parts.append('</div>')

open(out, 'w').write(''.join(parts))
print('wrote', out, f'({len(p.slides)} slides)')
