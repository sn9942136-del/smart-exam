# -*- coding: utf-8 -*-
"""
Generator Dokumen Microsoft Word (.docx) Resmi Tugas Akhir
Mahasiswa: Salsa Nabila (NIM: 41257158) - STMIK IKMI Cirebon
"""

import os
import re
from docx import Document
from docx.shared import Inches, Pt, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT, WD_ALIGN_VERTICAL
from docx.oxml import parse_xml, OxmlElement
from docx.oxml.ns import nsdecls, qn

MD_FILE = os.path.join('docs', 'Laporan_Lengkap_Tugas_Akhir_Salsa_Nabila.md')
DOCX_OUT = os.path.join('docs', 'Laporan_Lengkap_Tugas_Akhir_Salsa_Nabila.docx')
DIAGRAMS_DIR = os.path.join('docs', 'diagrams')

def set_cell_background(cell, fill_hex):
    tcPr = cell._element.get_or_add_tcPr()
    shd = parse_xml(f'<w:shd {nsdecls("w")} w:fill="{fill_hex}"/>')
    tcPr.append(shd)

def set_cell_margins(cell, top=100, bottom=100, left=150, right=150):
    tcPr = cell._element.get_or_add_tcPr()
    tcMar = parse_xml(f'''
        <w:tcMar {nsdecls("w")}>
            <w:top w:w="{top}" w:type="dxa"/>
            <w:bottom w:w="{bottom}" w:type="dxa"/>
            <w:left w:w="{left}" w:type="dxa"/>
            <w:right w:w="{right}" w:type="dxa"/>
        </w:tcMar>
    ''')
    tcPr.append(tcMar)

def create_word_document():
    doc = Document()

    # Page Setup (A4, Standar Skripsi/Tugas Akhir: Kiri 4cm, Atas 3cm, Kanan 3cm, Bawah 3cm)
    for section in doc.sections:
        section.page_width = Inches(8.27)
        section.page_height = Inches(11.69)
        section.top_margin = Inches(1.18)      # 3 cm
        section.bottom_margin = Inches(1.18)   # 3 cm
        section.left_margin = Inches(1.57)     # 4 cm
        section.right_margin = Inches(1.18)    # 3 cm

    # Base Normal Style
    normal_style = doc.styles['Normal']
    normal_style.font.name = 'Times New Roman'
    normal_style.font.size = Pt(12)
    normal_style.font.color.rgb = RGBColor(15, 23, 42) # #0f172a
    normal_style.paragraph_format.line_spacing = 1.25
    normal_style.paragraph_format.space_after = Pt(4)

    with open(MD_FILE, 'r', encoding='utf-8') as f:
        lines = f.readlines()

    in_cover = True
    in_table = False
    table_rows = []
    in_code_block = False
    skip_next_hr = False

    i = 0
    while i < len(lines):
        line = lines[i].rstrip('\r\n')
        stripped = line.strip()

        # Handle Code blocks (Mermaid / code)
        if stripped.startswith('```'):
            if not in_code_block:
                in_code_block = True
                # Skip mermaid lines by skipping until next ```
                if 'mermaid' in stripped:
                    i += 1
                    while i < len(lines) and not lines[i].strip().startswith('```'):
                        i += 1
                    in_code_block = False
            else:
                in_code_block = False
            i += 1
            continue

        if in_code_block:
            i += 1
            continue

        # Page Break on specific dividers
        if stripped == '---':
            doc.add_page_break()
            i += 1
            continue

        # Image Embeds
        img_match = re.search(r'!\[(.*?)\]\((.*?)\)', stripped)
        if img_match:
            caption = img_match.group(1)
            img_rel = img_match.group(2)
            img_fname = os.path.basename(img_rel.split('?')[0])
            img_path = os.path.join(DIAGRAMS_DIR, img_fname)
            if os.path.exists(img_path):
                p_img = doc.add_paragraph()
                p_img.alignment = WD_ALIGN_PARAGRAPH.CENTER
                p_img.paragraph_format.space_before = Pt(8)
                p_img.paragraph_format.space_after = Pt(4)
                run_img = p_img.add_run()
                run_img.add_picture(img_path, width=Inches(5.4))
                
                p_cap = doc.add_paragraph()
                p_cap.alignment = WD_ALIGN_PARAGRAPH.CENTER
                p_cap.paragraph_format.space_after = Pt(12)
                r_cap = p_cap.add_run(f"Gambar: {caption}")
                r_cap.font.size = Pt(10)
                r_cap.font.italic = True
                r_cap.font.color.rgb = RGBColor(71, 85, 105)
            i += 1
            continue

        # Tables Parsing
        if '|' in stripped and ('---' in stripped or i + 1 < len(lines) and '|' in lines[i+1]):
            # Start collecting table
            table_lines = []
            while i < len(lines) and '|' in lines[i].strip():
                tline = lines[i].strip()
                if not re.match(r'^[\|\s\:\-]+$', tline): # not separator
                    cols = [c.strip() for c in tline.split('|')[1:-1]]
                    table_lines.append(cols)
                i += 1

            if table_lines:
                num_cols = max(len(r) for r in table_lines)
                table = doc.add_table(rows=len(table_lines), cols=num_cols)
                table.alignment = WD_TABLE_ALIGNMENT.CENTER
                table.autofit = True

                for r_idx, row_data in enumerate(table_lines):
                    is_header = (r_idx == 0)
                    for c_idx, cell_value in enumerate(row_data):
                        if c_idx < num_cols:
                            cell = table.cell(r_idx, c_idx)
                            set_cell_margins(cell, top=120, bottom=120, left=150, right=150)
                            if is_header:
                                set_cell_background(cell, "1E3A8A") # Navy Blue
                            elif r_idx % 2 == 1:
                                set_cell_background(cell, "F8FAFC") # Soft Slate
                            else:
                                set_cell_background(cell, "FFFFFF")

                            cell.text = ""
                            p = cell.paragraphs[0]
                            p.paragraph_format.space_before = Pt(0)
                            p.paragraph_format.space_after = Pt(0)
                            p.paragraph_format.line_spacing = 1.15
                            
                            # Clean markdown bold/italic
                            clean_text = cell_value.replace('**', '').replace('*', '').replace('`', '')
                            run = p.add_run(clean_text)
                            run.font.name = 'Times New Roman'
                            run.font.size = Pt(9.5 if num_cols > 3 else 10.5)
                            if is_header:
                                run.font.bold = True
                                run.font.color.rgb = RGBColor(255, 255, 255)
                            else:
                                run.font.color.rgb = RGBColor(15, 23, 42)

                p_sp = doc.add_paragraph()
                p_sp.paragraph_format.space_after = Pt(6)
            continue

        # Headings
        if stripped.startswith('# '):
            h_text = stripped[2:].strip()
            # If BAB heading, add page break before if not first page
            p = doc.add_paragraph()
            p.alignment = WD_ALIGN_PARAGRAPH.CENTER if ('LAPORAN' in h_text or 'SMART EXAM' in h_text or 'BAB' in h_text or 'DAFTAR' in h_text or 'LEMBAR' in h_text or 'PERNYATAAN' in h_text or 'KATA' in h_text) else WD_ALIGN_PARAGRAPH.LEFT
            p.paragraph_format.space_before = Pt(14)
            p.paragraph_format.space_after = Pt(8)
            r = p.add_run(h_text)
            r.font.name = 'Times New Roman'
            r.font.size = Pt(14)
            r.font.bold = True
            r.font.color.rgb = RGBColor(15, 23, 42)
        elif stripped.startswith('## '):
            h_text = stripped[3:].strip()
            p = doc.add_paragraph()
            p.alignment = WD_ALIGN_PARAGRAPH.CENTER if ('SMART EXAM' in h_text or 'REKAYASA' in h_text) else WD_ALIGN_PARAGRAPH.LEFT
            p.paragraph_format.space_before = Pt(12)
            p.paragraph_format.space_after = Pt(6)
            r = p.add_run(h_text)
            r.font.name = 'Times New Roman'
            r.font.size = Pt(13)
            r.font.bold = True
            r.font.color.rgb = RGBColor(30, 58, 138) # Dark Blue
        elif stripped.startswith('### '):
            h_text = stripped[4:].strip()
            p = doc.add_paragraph()
            p.paragraph_format.space_before = Pt(10)
            p.paragraph_format.space_after = Pt(4)
            r = p.add_run(h_text)
            r.font.name = 'Times New Roman'
            r.font.size = Pt(12)
            r.font.bold = True
            r.font.color.rgb = RGBColor(15, 23, 42)
        elif stripped.startswith('#### '):
            h_text = stripped[5:].strip()
            p = doc.add_paragraph()
            p.paragraph_format.space_before = Pt(8)
            p.paragraph_format.space_after = Pt(3)
            r = p.add_run(h_text)
            r.font.name = 'Times New Roman'
            r.font.size = Pt(11.5)
            r.font.bold = True
            r.font.italic = True
            r.font.color.rgb = RGBColor(51, 65, 85)
        elif stripped.startswith('> '):
            # Blockquote
            bq_text = stripped[2:].strip().replace('**', '')
            p = doc.add_paragraph()
            p.paragraph_format.left_indent = Inches(0.4)
            p.paragraph_format.space_before = Pt(4)
            p.paragraph_format.space_after = Pt(4)
            r = p.add_run(bq_text)
            r.font.name = 'Times New Roman'
            r.font.size = Pt(11)
            r.font.italic = True
            r.font.color.rgb = RGBColor(71, 85, 105)
        elif stripped.startswith('* ') or stripped.startswith('- '):
            # Bullet point
            item_text = stripped[2:].strip()
            p = doc.add_paragraph(style='List Bullet')
            p.paragraph_format.space_before = Pt(1)
            p.paragraph_format.space_after = Pt(3)
            p.paragraph_format.line_spacing = 1.15
            format_inline_text(p, item_text)
        elif re.match(r'^\d+\.\s', stripped):
            # Numbered list
            m = re.match(r'^(\d+\.)\s(.*)', stripped)
            prefix = m.group(1)
            item_text = m.group(2)
            p = doc.add_paragraph()
            p.paragraph_format.left_indent = Inches(0.25)
            p.paragraph_format.space_before = Pt(2)
            p.paragraph_format.space_after = Pt(3)
            p.paragraph_format.line_spacing = 1.15
            r_num = p.add_run(prefix + " ")
            r_num.font.bold = True
            format_inline_text(p, item_text)
        elif stripped:
            # Regular paragraph
            p = doc.add_paragraph()
            p.paragraph_format.space_before = Pt(2)
            p.paragraph_format.space_after = Pt(5)
            p.paragraph_format.line_spacing = 1.25
            if any(k in stripped for k in ['Disusun Oleh:', 'Dosen Pengampu:', 'PROGRAM STUDI', 'SEKOLAH TINGGI', 'TAHUN AKADEMIK']):
                p.alignment = WD_ALIGN_PARAGRAPH.CENTER
            else:
                p.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
            format_inline_text(p, stripped)

        i += 1

    doc.save(DOCX_OUT)
    print(f"[OK] File Word (.docx) berhasil dibuat: {DOCX_OUT}")

def format_inline_text(paragraph, text):
    """Parse bold **text** and italic *text* and code `text`"""
    tokens = re.split(r'(\*\*.*?\*\*|\*.*?\*|`.*?`)', text)
    for tok in tokens:
        if not tok:
            continue
        if tok.startswith('**') and tok.endswith('**'):
            r = paragraph.add_run(tok[2:-2])
            r.font.bold = True
        elif tok.startswith('*') and tok.endswith('*'):
            r = paragraph.add_run(tok[1:-1])
            r.font.italic = True
        elif tok.startswith('`') and tok.endswith('`'):
            r = paragraph.add_run(tok[1:-1])
            r.font.name = 'Consolas'
            r.font.size = Pt(10.5)
            r.font.color.rgb = RGBColor(30, 58, 138)
        else:
            r = paragraph.add_run(tok)

if __name__ == '__main__':
    create_word_document()

