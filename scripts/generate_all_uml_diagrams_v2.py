# -*- coding: utf-8 -*-
"""
Generator 4 Diagram UML Versi 2.0 (Ultra-Polish & Textbook Standard)
Mata Kuliah: Rekayasa Perangkat Lunak (TIKB2503021) - STMIK IKMI Cirebon
Penyusun: Salsa Nabila (NIM: 41257158) | Kelompok: Tiba Tiba Talun | Dosen: Yudhistira Arie Wijaya, M.Kom., CDS.
"""

import os
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
import matplotlib.patches as patches
from matplotlib.patches import FancyBboxPatch, Ellipse, Circle, Rectangle, Polygon

os.environ['MPLCONFIGDIR'] = os.path.join(os.getcwd(), 'scratch')
DOCS_DIR = os.path.join('docs', 'diagrams')
PUBLIC_DIR = os.path.join('public', 'assets', 'diagrams')
ARTIFACT_DIR = os.path.join(r'C:\Users\Lenovo\.gemini\antigravity\brain\4616d195-c8c1-46c2-9b3d-1b48ad97bea3', 'diagrams')

os.makedirs(DOCS_DIR, exist_ok=True)
os.makedirs(PUBLIC_DIR, exist_ok=True)
os.makedirs(ARTIFACT_DIR, exist_ok=True)

plt.rcParams['font.sans-serif'] = ['Segoe UI', 'DejaVu Sans', 'Arial', 'sans-serif']
plt.rcParams['font.family'] = 'sans-serif'

def save_diagram(fig, filename):
    p_docs = os.path.join(DOCS_DIR, filename)
    p_public = os.path.join(PUBLIC_DIR, filename)
    p_artifact = os.path.join(ARTIFACT_DIR, filename)
    fig.savefig(p_docs, dpi=250, bbox_inches='tight')
    fig.savefig(p_public, dpi=250, bbox_inches='tight')
    fig.savefig(p_artifact, dpi=250, bbox_inches='tight')
    plt.close(fig)
    print(f"[OK] Diagram disimpan: {filename}")

def draw_header(ax, title, subtitle, max_x=20.0, y_top=13.2):
    header = FancyBboxPatch((0.5, y_top - 1.1), max_x - 1.0, 1.1,
                            boxstyle="round,pad=0.08,rounding_size=0.1",
                            facecolor='#0f172a', edgecolor='none', zorder=3)
    ax.add_patch(header)
    ax.text(max_x / 2.0, y_top - 0.38, title, fontsize=15, fontweight='bold', color='white', ha='center', va='center', zorder=4)
    ax.text(max_x / 2.0, y_top - 0.78, subtitle, fontsize=9.5, color='#94a3b8', ha='center', va='center', zorder=4)

# ==============================================================================
# 1. USE CASE DIAGRAM V2.0 (6 AKTOR: 4 KIRI, 2 KANAN - BEBAS SALING SILANG)
# ==============================================================================
def generate_use_case_v2():
    fig, ax = plt.subplots(figsize=(21, 14.5), dpi=250)
    ax.set_xlim(-0.5, 20.5)
    ax.set_ylim(-0.5, 14.5)
    ax.axis('off')
    fig.patch.set_facecolor('#f8fafc')
    ax.set_facecolor('#f8fafc')

    draw_header(ax, "USE CASE DIAGRAM — SISTEM SMART EXAM (6 AKTOR)",
                "Penyusun: Salsa Nabila (NIM: 41257158) | Kelompok: Tiba Tiba Talun | Dosen: Yudhistira Arie Wijaya, M.Kom., CDS.",
                max_x=20.5, y_top=14.3)

    # System Boundary Box
    boundary = FancyBboxPatch((4.5, 0.6), 11.0, 12.0,
                              boxstyle="round,pad=0.1,rounding_size=0.15",
                              facecolor='#ffffff', edgecolor='#64748b', linewidth=2.0, linestyle='--', zorder=2)
    ax.add_patch(boundary)
    ax.text(10.0, 12.2, "Sistem Batasan: Smart Exam (Dual Camera AI & Live DB Chatbot)", 
            fontsize=12, fontweight='bold', color='#1e3a8a', ha='center', va='center', zorder=3)

    # Fungsi gambar Aktor Standar UML
    def draw_actor(x, y, name, desc, is_sys=False, color='#1e40af'):
        if not is_sys:
            # Stickman UML Presisi
            ax.add_patch(Circle((x, y + 0.34), 0.14, facecolor='white', edgecolor=color, linewidth=2.4, zorder=5))
            ax.plot([x, x], [y + 0.20, y - 0.16], color=color, linewidth=2.4, zorder=5)
            ax.plot([x - 0.25, x + 0.25], [y + 0.08, y + 0.08], color=color, linewidth=2.4, zorder=5)
            ax.plot([x, x - 0.20], [y - 0.16, y - 0.46], color=color, linewidth=2.4, zorder=5)
            ax.plot([x, x + 0.20], [y - 0.16, y - 0.46], color=color, linewidth=2.4, zorder=5)
        else:
            # System Actor UML Box
            box = FancyBboxPatch((x - 0.50, y - 0.25), 1.0, 0.65,
                                 boxstyle="round,pad=0.05,rounding_size=0.08",
                                 facecolor='#f8fafc', edgecolor=color, linewidth=2.2, zorder=5)
            ax.add_patch(box)
            ax.text(x, y + 0.09, "<<System>>", fontsize=8, color='#475569', ha='center', weight='bold', zorder=6)
            ax.text(x, y - 0.10, "Service API", fontsize=7.5, color='#64748b', ha='center', zorder=6)

        ax.text(x, y - 0.66, name, fontsize=10.5, fontweight='bold', ha='center', va='top', color='#0f172a', zorder=6)
        if desc:
            ax.text(x, y - 0.84, desc, fontsize=8, color='#475569', ha='center', va='top', zorder=6)

    # 4 Aktor KIRI (Tersusun Proporsional)
    draw_actor(1.8, 10.8, "Super Admin", "(Akses Penuh & Audit)", color="#7c3aed")
    draw_actor(1.8, 8.0, "Administrator", "(Manajemen Akademik)", color="#0284c7")
    draw_actor(1.8, 5.2, "Guru / Pengawas", "(Bank Soal & Penilaian)", color="#059669")
    draw_actor(1.8, 2.0, "Peserta (Siswa)", "(Pelaksana Ujian CBT)", color="#ea580c")

    # 2 Aktor KANAN (Sistem Layanan)
    draw_actor(18.2, 7.5, "AI Proctoring Engine", "(MediaPipe Face Mesh)", is_sys=True, color="#2563eb")
    draw_actor(18.2, 3.2, "Sistem Layanan OTP", "(Gmail SMTP & WA Gateway)", is_sys=True, color="#16a34a")

    # 12 Use Cases disusun dalam 2 Kolom Bersih
    # Kolom Kiri Dalam Boundary: x=7.3
    # Kolom Kanan Dalam Boundary: x=12.7
    use_cases = [
        # Kolom Kiri (Fungsi Administratif & Ujian)
        {"id": "UC01", "x": 7.3, "y": 11.2, "w": 3.4, "h": 0.80, "text": "UC-01: Autentikasi &\nManajemen Sesi", "fill": "#eff6ff", "stroke": "#3b82f6"},
        {"id": "UC03", "x": 7.3, "y": 9.6, "w": 3.4, "h": 0.80, "text": "UC-03: Kelola Pengguna\n& Hak Akses (RBAC)", "fill": "#eff6ff", "stroke": "#3b82f6"},
        {"id": "UC05", "x": 7.3, "y": 8.0, "w": 3.4, "h": 0.80, "text": "UC-05: Kelola Bank Soal &\nPaket Jadwal Ujian", "fill": "#eff6ff", "stroke": "#3b82f6"},
        {"id": "UC07", "x": 7.3, "y": 6.4, "w": 3.4, "h": 0.80, "text": "UC-07: Monitoring Ujian\nPeserta Live", "fill": "#eff6ff", "stroke": "#3b82f6"},
        {"id": "UC09", "x": 7.3, "y": 4.8, "w": 3.4, "h": 0.80, "text": "UC-09: Evaluasi Nilai &\nKoreksi Esai AI", "fill": "#f5f3ff", "stroke": "#8b5cf6"},
        {"id": "UC11", "x": 7.3, "y": 3.2, "w": 3.4, "h": 0.80, "text": "UC-11: Ekspor Rekap Nilai &\nBerita Acara", "fill": "#f8fafc", "stroke": "#475569"},

        # Kolom Kanan (Layanan Terintegrasi & CBT Engine)
        {"id": "UC02", "x": 12.7, "y": 11.2, "w": 3.4, "h": 0.80, "text": "UC-02: Verifikasi 2FA OTP\n(Email / WhatsApp)", "fill": "#ecfdf5", "stroke": "#10b981"},
        {"id": "UC04", "x": 12.7, "y": 9.6, "w": 3.4, "h": 0.80, "text": "UC-04: Audit Log Forensik &\nKonfigurasi AI", "fill": "#f1f5f9", "stroke": "#64748b"},
        {"id": "UC06", "x": 12.7, "y": 8.0, "w": 3.4, "h": 0.80, "text": "UC-06: Chatbot Operasional AI\n(Grounding Live DB)", "fill": "#ecfeff", "stroke": "#06b6d4"},
        {"id": "UC08", "x": 12.7, "y": 6.2, "w": 3.4, "h": 0.80, "text": "UC-08: Kalibrasi Dual Camera\n(Webcam + HP Meja)", "fill": "#fef3c7", "stroke": "#f59e0b"},
        {"id": "UC10", "x": 12.7, "y": 4.6, "w": 3.4, "h": 0.80, "text": "UC-10: Pengerjaan Ujian CBT\n& Auto-Save Jawaban", "fill": "#fff7ed", "stroke": "#f97316"},
        {"id": "UC12", "x": 12.7, "y": 3.0, "w": 3.4, "h": 0.80, "text": "UC-12: Deteksi Wajah AI\n(MediaPipe Face Mesh)", "fill": "#fef2f2", "stroke": "#ef4444"}
    ]

    uc_map = {}
    for uc in use_cases:
        el = Ellipse((uc["x"], uc["y"]), uc["w"], uc["h"], facecolor=uc["fill"], edgecolor=uc["stroke"], linewidth=1.8, zorder=4)
        ax.add_patch(el)
        ax.text(uc["x"], uc["y"], uc["text"], fontsize=8.5, fontweight='bold', ha='center', va='center', color='#0f172a', zorder=5)
        uc_map[uc["id"]] = (uc["x"], uc["y"], uc["w"], uc["h"])

    # Relasi Bersih dengan Titik Kontak yang Rapi
    def connect_actor(x1, y1, uc_id, color='#94a3b8', style='-', width=1.4):
        x2, y2, w, h = uc_map[uc_id]
        dx = x2 - x1
        tx = x2 - (w/2) * 0.95 if dx > 0 else x2 + (w/2) * 0.95
        ax.plot([x1, tx], [y1, y2], color=color, linestyle=style, linewidth=width, zorder=3)

    # 1. Super Admin (Ungu) -> UC01, UC03, UC04, UC06
    connect_actor(2.3, 10.8, "UC01", "#7c3aed")
    connect_actor(2.3, 10.8, "UC03", "#7c3aed")
    # Ke Kolom kanan atas tanpa motong
    ax.plot([2.3, 4.0, 4.0, 11.0], [10.8, 10.8, 10.3, 9.6], color="#7c3aed", lw=1.2, linestyle=':', zorder=3)

    # 2. Administrator (Biru) -> UC01, UC03, UC05, UC07, UC11
    connect_actor(2.3, 8.0, "UC01", "#0284c7")
    connect_actor(2.3, 8.0, "UC03", "#0284c7")
    connect_actor(2.3, 8.0, "UC05", "#0284c7")
    connect_actor(2.3, 8.0, "UC07", "#0284c7")
    connect_actor(2.3, 8.0, "UC11", "#0284c7")

    # 3. Guru / Pengawas (Hijau) -> UC01, UC05, UC06, UC07, UC09, UC11
    connect_actor(2.3, 5.2, "UC01", "#059669")
    connect_actor(2.3, 5.2, "UC05", "#059669")
    connect_actor(2.3, 5.2, "UC07", "#059669")
    connect_actor(2.3, 5.2, "UC09", "#059669")
    connect_actor(2.3, 5.2, "UC11", "#059669")

    # 4. Peserta Siswa (Oranye) -> UC01, UC02, UC08, UC10
    connect_actor(2.3, 2.0, "UC01", "#ea580c", width=1.6)
    connect_actor(2.3, 2.0, "UC10", "#ea580c", width=1.6)
    connect_actor(2.3, 2.0, "UC08", "#ea580c", width=1.6)

    # 5. AI Proctoring Engine (Kanan Atas) -> UC08, UC10, UC12
    connect_actor(17.6, 7.5, "UC08", "#2563eb", "--", 1.6)
    connect_actor(17.6, 7.5, "UC10", "#2563eb", "--", 1.6)
    connect_actor(17.6, 7.5, "UC12", "#2563eb", "--", 1.8)

    # 6. Sistem Layanan OTP (Kanan Bawah) -> UC02
    connect_actor(17.6, 3.2, "UC02", "#16a34a", "--", 1.8)

    # Relasi <<include>> Standar UML (Garis Putus-Putus dengan Panah Terbuka)
    # UC01 includes UC02 (Login includes OTP)
    ax.annotate("", xy=(11.0, 11.2), xytext=(9.0, 11.2),
                arrowprops=dict(arrowstyle="->", color="#059669", linestyle="--", lw=1.6), zorder=6)
    ax.text(10.0, 11.45, "<<include>>", fontsize=8, color="#059669", ha="center", weight="bold", zorder=7)

    # UC10 includes UC12 (Pengerjaan Ujian includes Deteksi Wajah AI)
    ax.annotate("", xy=(12.7, 3.4), xytext=(12.7, 4.2),
                arrowprops=dict(arrowstyle="->", color="#ef4444", linestyle="--", lw=1.6), zorder=6)
    ax.text(13.6, 3.8, "<<include>>", fontsize=8, color="#ef4444", ha="center", weight="bold", zorder=7)

    # Legend / Catatan
    leg = FancyBboxPatch((4.8, 0.75), 10.4, 0.8, boxstyle="round,pad=0.05,rounding_size=0.08", 
                         facecolor='#f1f5f9', edgecolor='#94a3b8', linewidth=1.0, zorder=4)
    ax.add_patch(leg)
    ax.text(10.0, 1.28, "Standar Notasi UML 2.5 Use Case Diagram:", fontsize=8.5, fontweight='bold', ha='center', color='#1e293b', zorder=5)
    ax.text(10.0, 1.05, "• Kiri: 4 Aktor Manusia (Garis Solid) | • Kanan: 2 Aktor Layanan Sistem Eksternal (Garis Putus-Putus)", fontsize=8, ha='center', color='#334155', zorder=5)
    ax.text(10.0, 0.85, "• Panah Putus-Putus (-->): Relasi <<include>> (Fungsi pendukung yang wajib dieksekusi bersama use case utama)", fontsize=7.5, ha='center', color='#64748b', zorder=5)

    save_diagram(fig, '01_use_case_diagram.png')

# ==============================================================================
# 2. ACTIVITY DIAGRAM V2.0 (ORTHOGONAL ELBOW ROUTING & CLEAR GUARDS)
# ==============================================================================
def generate_activity_v2():
    fig, ax = plt.subplots(figsize=(21, 15.5), dpi=250)
    ax.set_xlim(-0.5, 20.5)
    ax.set_ylim(-0.5, 15.5)
    ax.axis('off')
    fig.patch.set_facecolor('#f8fafc')
    ax.set_facecolor('#f8fafc')

    draw_header(ax, "ACTIVITY DIAGRAM — ALUR PELAKSANAAN UJIAN & AI PROCTORING",
                "Penyusun: Salsa Nabila (NIM: 41257158) | Kelompok: Tiba Tiba Talun | Dosen: Yudhistira Arie Wijaya, M.Kom., CDS.",
                max_x=20.5, y_top=15.3)

    # 3 Swimlanes
    lanes = [
        {"x": 0.5, "w": 6.2, "title": "1. Peserta Ujian (Siswa)", "color": "#eff6ff", "border": "#3b82f6"},
        {"x": 7.0, "w": 6.5, "title": "2. Sistem Smart Exam (Clean MVC Backend)", "color": "#f8fafc", "border": "#64748b"},
        {"x": 13.8, "w": 6.2, "title": "3. AI Engine & Layanan Gateway", "color": "#f0fdf4", "border": "#22c55e"}
    ]
    for lane in lanes:
        box = FancyBboxPatch((lane["x"], 0.6), lane["w"], 12.8,
                             boxstyle="round,pad=0.05,rounding_size=0.1",
                             facecolor=lane["color"], edgecolor=lane["border"], linewidth=1.6, zorder=2)
        ax.add_patch(box)
        ax.text(lane["x"] + lane["w"]/2, 13.1, lane["title"], fontsize=11, fontweight='bold', ha='center', va='center', color='#0f172a', zorder=3)

    def draw_action(x, y, w, h, text, fill="#ffffff", border="#2563eb"):
        box = FancyBboxPatch((x - w/2, y - h/2), w, h, boxstyle="round,pad=0.05,rounding_size=0.08",
                             facecolor=fill, edgecolor=border, linewidth=1.6, zorder=5)
        ax.add_patch(box)
        ax.text(x, y, text, fontsize=8.5, ha='center', va='center', color='#0f172a', weight='bold', zorder=6)

    def draw_decision(x, y, text):
        d = 0.52
        diamond = Polygon([[x, y + d], [x + d*1.35, y], [x, y - d], [x - d*1.35, y]],
                          facecolor='#fef3c7', edgecolor='#f59e0b', linewidth=1.6, zorder=5)
        ax.add_patch(diamond)
        ax.text(x, y, text, fontsize=7.5, ha='center', va='center', color='#78350f', weight='bold', zorder=6)

    def draw_arrow(x1, y1, x2, y2, label=""):
        ax.annotate("", xy=(x2, y2), xytext=(x1, y1),
                    arrowprops=dict(arrowstyle="-|>", color="#1e293b", lw=1.6, mutation_scale=12), zorder=4)
        if label:
            mx, my = (x1 + x2)/2, (y1 + y2)/2
            ax.text(mx, my + 0.15, label, fontsize=7.5, color='#b91c1c', weight='bold', ha='center', zorder=7)

    # Orthogonal Connector (Siku-Siku Rapi)
    def draw_elbow(points, arrow=True, label=""):
        for i in range(len(points)-1):
            p1 = points[i]
            p2 = points[i+1]
            if i == len(points)-2 and arrow:
                ax.annotate("", xy=p2, xytext=p1,
                            arrowprops=dict(arrowstyle="-|>", color="#1e293b", lw=1.6, mutation_scale=12), zorder=4)
            else:
                ax.plot([p1[0], p2[0]], [p1[1], p2[1]], color="#1e293b", lw=1.6, zorder=4)
        if label:
            mid = points[1]
            ax.text(mid[0] + 0.15, mid[1] + 0.15, label, fontsize=7.5, color='#b91c1c', weight='bold', zorder=7)

    # 1. Start Node
    ax.add_patch(Circle((3.6, 12.4), 0.22, facecolor='#0f172a', edgecolor='none', zorder=6))
    ax.text(3.6, 12.75, "Mulai", fontsize=8.5, ha='center', weight='bold', color='#0f172a')

    # Aksi 1: Input Kredensial
    draw_arrow(3.6, 12.18, 3.6, 11.5)
    draw_action(3.6, 11.1, 4.6, 0.7, "Input Email & Password")

    # Pindah ke Lane 2: Verifikasi Kredensial
    draw_elbow([(3.6, 10.75), (3.6, 10.2), (10.25, 10.2)])
    draw_action(10.25, 10.2, 5.0, 0.7, "Verifikasi Hash Sandi (bcrypt)")

    # Pindah ke Lane 3: Kirim OTP
    draw_elbow([(12.75, 10.2), (16.9, 10.2), (16.9, 9.4)])
    draw_action(16.9, 9.0, 4.8, 0.7, "Generate & Kirim OTP 6 Digit\n(Gmail SMTP / WhatsApp)", fill="#ecfdf5", border="#10b981")

    # Kembali ke Lane 1: Input Manual OTP
    draw_elbow([(14.5, 9.0), (3.6, 9.0), (3.6, 8.4)])
    draw_action(3.6, 8.0, 4.6, 0.7, "Ketik Manual Kode OTP 6 Digit")

    # Pindah ke Lane 2: Decision OTP Valid?
    draw_elbow([(5.9, 8.0), (10.25, 8.0), (10.25, 7.6)])
    draw_decision(10.25, 7.0, "OTP Valid?")

    # Cabang Tidak Valid -> Balik ke Input OTP
    draw_elbow([(10.25 + 0.7, 7.0), (12.2, 7.0), (12.2, 8.7), (5.9, 8.7)], label="[Tidak / Expired]")

    # Cabang Valid -> Otorisasi
    draw_arrow(10.25, 6.48, 10.25, 5.8, label="[Ya]")
    draw_action(10.25, 5.8, 5.0, 0.7, "Otorisasi Sesi & Muat Jadwal Ujian")

    # Ke Lane 1: Kalibrasi Kamera
    draw_elbow([(7.75, 5.8), (3.6, 5.8), (3.6, 5.2)])
    draw_action(3.6, 4.8, 4.6, 0.7, "Kalibrasi Dual Camera WebRTC\n(Kamera Laptop + HP Meja)", fill="#fef3c7", border="#f59e0b")

    # Ke Lane 2: Jalankan Server Timer
    draw_elbow([(5.9, 4.8), (10.25, 4.8), (10.25, 4.3)])
    draw_action(10.25, 3.9, 5.0, 0.7, "Mulai Sesi Ujian di Server\n(Server-Authoritative Timer)")

    # Paralel Lane 3 (AI Proctoring Loop) & Lane 1 (Pengerjaan Ujian)
    draw_elbow([(12.75, 3.9), (16.9, 3.9), (16.9, 3.4)])
    draw_action(16.9, 3.0, 4.8, 0.7, "MediaPipe AI Face Mesh:\nDeteksi Gaze & Wajah Hilang", fill="#fef2f2", border="#ef4444")

    # Pelanggaran AI -> Log ke DB
    draw_elbow([(14.5, 3.0), (10.25, 3.0), (10.25, 2.5)])
    draw_action(10.25, 2.2, 5.0, 0.65, "Simpan Telemetri `ai_violations`\n& Sinkronisasi Auto-Save AJAX")

    # Pengerjaan Soal Siswa
    draw_elbow([(7.75, 2.2), (3.6, 2.2), (3.6, 1.8)])
    draw_action(3.6, 1.5, 4.6, 0.65, "Jawab Soal & Selesaikan Ujian")

    # Submit & Auto-Grading
    draw_elbow([(5.9, 1.5), (10.25, 1.5), (10.25, 1.15)])
    draw_action(10.25, 0.95, 5.0, 0.60, "Kunci Sesi, Nilai Otomatis PG\n& Evaluasi Esai AI")

    # Final Node (Bullseye)
    ax.add_patch(Circle((10.25, 0.45), 0.22, facecolor='none', edgecolor='#0f172a', linewidth=2.0, zorder=6))
    ax.add_patch(Circle((10.25, 0.45), 0.14, facecolor='#0f172a', edgecolor='none', zorder=7))
    ax.text(10.75, 0.45, "Selesai", fontsize=8.5, va='center', weight='bold', color='#0f172a')

    save_diagram(fig, '02_activity_diagram.png')

# ==============================================================================
# 3. CLASS DIAGRAM V2.0 (LENGKAP MULTIPLICITY, STEREOTYPES & 4 TIERS)
# ==============================================================================
def generate_class_diagram_v2():
    fig, ax = plt.subplots(figsize=(22, 16.0), dpi=250)
    ax.set_xlim(-0.5, 21.5)
    ax.set_ylim(-0.5, 16.0)
    ax.axis('off')
    fig.patch.set_facecolor('#f8fafc')
    ax.set_facecolor('#f8fafc')

    draw_header(ax, "CLASS DIAGRAM — ARSITEKTUR CLEAN MVC & SERVICE LAYER",
                "Penyusun: Salsa Nabila (NIM: 41257158) | Kelompok: Tiba Tiba Talun | Dosen: Yudhistira Arie Wijaya, M.Kom., CDS.",
                max_x=21.5, y_top=15.8)

    def draw_uml_class(x, y, w, h_head, h_attr, h_meth, title, stereotype, attrs, meths, color="#1e3a8a", fill="#ffffff"):
        total_h = h_head + h_attr + h_meth
        box = FancyBboxPatch((x, y - total_h), w, total_h, boxstyle="round,pad=0.03,rounding_size=0.06",
                             facecolor=fill, edgecolor=color, linewidth=1.8, zorder=4)
        ax.add_patch(box)
        header = Rectangle((x, y - h_head), w, h_head, facecolor=color, edgecolor=color, zorder=5)
        ax.add_patch(header)
        ax.text(x + w/2, y - 0.22, stereotype, fontsize=7.5, color='#cbd5e1', ha='center', weight='bold', zorder=6)
        ax.text(x + w/2, y - 0.50, title, fontsize=9.5, color='white', ha='center', weight='bold', zorder=6)

        ax.plot([x, x + w], [y - h_head - h_attr, y - h_head - h_attr], color='#cbd5e1', lw=1.2, zorder=5)

        ay = y - h_head - 0.24
        for a in attrs:
            ax.text(x + 0.15, ay, a, fontsize=7.5, color='#334155', fontfamily='monospace', zorder=6)
            ay -= 0.22

        my = y - h_head - h_attr - 0.24
        for m in meths:
            ax.text(x + 0.15, my, m, fontsize=7.5, color='#0f172a', fontfamily='monospace', weight='bold', zorder=6)
            my -= 0.22

    # 1. Superclass: BaseModel (Kiri Bawah)
    draw_uml_class(0.8, 14.0, 5.6, 0.7, 0.9, 1.3, "BaseModel", "<<Abstract Model>>",
                   ["# db : PDO", "# table : string", "# primaryKey : string"],
                   ["+ find(id) : array", "+ where(conditions) : array", "+ create(data) : int", "+ update(id, data) : bool", "+ delete(id) : bool"],
                   color="#334155", fill="#f8fafc")

    # 2. Domain Models (Turunan BaseModel)
    draw_uml_class(0.8, 10.4, 5.6, 0.7, 1.1, 0.9, "UserModel", "<<Model>>",
                   ["+ id : int", "+ name : string", "+ email : string", "+ password : string", "+ role_id : int"],
                   ["+ verifyPassword(plain) : bool", "+ findByEmail(email) : array", "+ getRoleName() : string"],
                   color="#0284c7")

    draw_uml_class(0.8, 7.0, 5.6, 0.7, 1.3, 0.9, "ExamModel", "<<Model>>",
                   ["+ id : int", "+ title : string", "+ duration_minutes : int", "+ token : string", "+ status : string"],
                   ["+ getActiveExams() : array", "+ validateToken(token) : bool", "+ getQuestions() : array"],
                   color="#0284c7")

    draw_uml_class(0.8, 3.5, 5.6, 0.7, 1.3, 0.9, "ExamSessionModel", "<<Model>>",
                   ["+ id : int", "+ exam_id : int", "+ user_id : int", "+ started_at : datetime", "+ total_score : float"],
                   ["+ getRemainingSeconds() : int", "+ markSubmitted() : bool", "+ isExpired() : bool"],
                   color="#0284c7")

    # 3. Service Layer (Tengah)
    draw_uml_class(7.6, 14.0, 6.2, 0.7, 0.9, 1.6, "ExamSessionService", "<<Service>>",
                   ["- examModel : ExamModel", "- sessionModel : ExamSessionModel", "- answerModel : ExamAnswerModel"],
                   ["+ startSession(userId, examId) : array", "+ saveAnswer(sessionId, qId, ans) : bool",
                    "+ validateSessionTimer(sessionId) : bool", "+ submitSession(sessionId) : array", "+ autoGradeMultipleChoice() : float"],
                   color="#059669")

    draw_uml_class(7.6, 9.8, 6.2, 0.7, 0.9, 1.4, "AiProctoringService", "<<Service>>",
                   ["- violationModel : AiViolationModel", "- sessionModel : ExamSessionModel"],
                   ["+ recordTelemetry(sessionId, data) : bool", "+ analyzeFaceViolation(type) : void",
                    "+ issueWarning(sessionId, msg) : void", "+ getIncidentSummary(sessionId) : array"],
                   color="#059669")

    draw_uml_class(7.6, 5.8, 6.2, 0.7, 0.7, 1.2, "AiChatService", "<<Service>>",
                   ["- apiKey : string", "- model : string"],
                   ["+ processUserQuery(query, role) : string", "+ getOperationalContext() : array", "+ executeGeminiApi(prompt) : string"],
                   color="#059669")

    draw_uml_class(7.6, 2.5, 6.2, 0.7, 0.7, 0.9, "NotificationQueueService", "<<Service>>",
                   ["- smtpClient : SmtpClient", "- waClient : WhatsAppClient"],
                   ["+ enqueueOtp(userId, code) : bool", "+ processPendingJobs() : int"],
                   color="#059669")

    # 4. Presentation & Middleware (Kanan)
    draw_uml_class(15.0, 14.0, 5.6, 0.7, 0.7, 1.4, "ExamController", "<<Controller>>",
                   ["- examService : ExamSessionService", "- proctorService : AiProctoringService"],
                   ["+ start(examId) : Response", "+ showRoom(sessionId) : View", "+ saveAnswer(request) : Json", "+ finish(sessionId) : Response"],
                   color="#7c3aed")

    draw_uml_class(15.0, 10.2, 5.6, 0.7, 0.7, 1.2, "AuthController", "<<Controller>>",
                   ["- userModel : UserModel", "- notifService : NotificationQueueService"],
                   ["+ login(request) : Response", "+ verifyOtp(request) : Response", "+ resendOtp() : Json", "+ logout() : Response"],
                   color="#7c3aed")

    draw_uml_class(15.0, 6.6, 5.6, 0.7, 0.5, 1.2, "AiChatController", "<<Controller>>",
                   ["- chatService : AiChatService"],
                   ["+ sendMessage(request) : Json", "+ getHistory(sessionId) : Json", "+ updateConfiguration(req) : Json"],
                   color="#7c3aed")

    draw_uml_class(15.0, 3.2, 5.6, 0.7, 0.5, 0.9, "SecurityFilters", "<<Middleware>>",
                   ["- authFilter : AuthFilter", "- roleFilter : RoleFilter"],
                   ["+ validateCsrfToken() : bool", "+ enforceRbac(requiredRole) : void"],
                   color="#b91c1c")

    # Generalization: UserModel inherits BaseModel
    ax.plot([3.6, 3.6], [10.4, 11.0], color="#334155", lw=1.8, zorder=3)
    ax.add_patch(Polygon([[3.6, 11.1], [3.45, 10.85], [3.75, 10.85]], facecolor='white', edgecolor='#334155', lw=1.8, zorder=5))

    # Multiplicity & Association Antar Model:
    # UserModel (1) --- (0..*) ExamSessionModel
    ax.plot([6.4, 7.0, 7.0, 6.4], [9.3, 9.3, 2.5, 2.5], color="#0284c7", lw=1.5, zorder=3)
    ax.text(6.5, 9.5, "1", fontsize=8, weight='bold', color="#0284c7")
    ax.text(6.5, 2.7, "0..*", fontsize=8, weight='bold', color="#0284c7")

    # ExamModel (1) --- (0..*) ExamSessionModel
    ax.plot([3.6, 3.6], [4.1, 4.8], color="#0284c7", lw=1.5, zorder=3)
    ax.text(3.8, 4.7, "1", fontsize=8, weight='bold', color="#0284c7")
    ax.text(3.8, 4.2, "0..*", fontsize=8, weight='bold', color="#0284c7")

    # Association Controller -> Service
    def draw_assoc(x1, y1, x2, y2, label=""):
        ax.annotate("", xy=(x2, y2), xytext=(x1, y1),
                    arrowprops=dict(arrowstyle="->", color="#475569", lw=1.6), zorder=3)
        if label:
            ax.text((x1+x2)/2, (y1+y2)/2 + 0.15, label, fontsize=7.5, color='#475569', ha='center', weight='bold', zorder=6)

    draw_assoc(15.0, 12.5, 13.8, 12.5, "memanggil")
    draw_assoc(15.0, 8.8, 13.8, 8.8, "memanggil")
    draw_assoc(15.0, 5.5, 13.8, 5.5, "memanggil")

    save_diagram(fig, '03_class_diagram.png')

# ==============================================================================
# 4. SEQUENCE DIAGRAM V2.0 (LENGKAP BALOK AKTIVASI & RUANG LEGA 23 LANGKAH)
# ==============================================================================
def generate_sequence_diagram_v2():
    fig, ax = plt.subplots(figsize=(22, 17.5), dpi=250)
    ax.set_xlim(-0.5, 21.5)
    ax.set_ylim(-0.5, 17.5)
    ax.axis('off')
    fig.patch.set_facecolor('#f8fafc')
    ax.set_facecolor('#f8fafc')

    draw_header(ax, "SEQUENCE DIAGRAM — INTERAKSI END-TO-END SISTEM SMART EXAM",
                "Penyusun: Salsa Nabila (NIM: 41257158) | Kelompok: Tiba Tiba Talun | Dosen: Yudhistira Arie Wijaya, M.Kom., CDS.",
                max_x=21.5, y_top=17.3)

    # 6 Lifeline Komponen
    lifelines = [
        {"x": 1.6, "name": "Peserta\n(Siswa)", "color": "#ea580c"},
        {"x": 5.2, "name": "Browser Client\n(JS + MediaPipe)", "color": "#0284c7"},
        {"x": 9.2, "name": "Controller &\nSecurity Filter", "color": "#7c3aed"},
        {"x": 13.0, "name": "Service Layer\n(Exam/Proctoring)", "color": "#059669"},
        {"x": 16.8, "name": "Basis Data\nMySQL 8.0", "color": "#0f172a"},
        {"x": 20.2, "name": "AI Engine &\nLayanan OTP", "color": "#2563eb"}
    ]

    for line in lifelines:
        x = line["x"]
        # Header Box
        box = FancyBboxPatch((x - 1.25, 14.8), 2.5, 0.9,
                             boxstyle="round,pad=0.05,rounding_size=0.08",
                             facecolor=line["color"], edgecolor='none', zorder=4)
        ax.add_patch(box)
        ax.text(x, 15.25, line["name"], fontsize=8.5, color='white', weight='bold', ha='center', va='center', zorder=5)
        # Garis Vertikal
        ax.plot([x, x], [14.8, 0.5], color='#94a3b8', linestyle='--', linewidth=1.5, zorder=2)

    # Balok Aktivasi (Focus of Control)
    def draw_activation(x, y_start, y_end, color="#cbd5e1"):
        w = 0.22
        h = y_start - y_end
        rect = Rectangle((x - w/2, y_end), w, h, facecolor=color, edgecolor="#64748b", lw=1.0, zorder=3)
        ax.add_patch(rect)

    draw_activation(1.6, 14.5, 0.8, color="#fed7aa")   # Peserta
    draw_activation(5.2, 14.3, 0.9, color="#bae6fd")   # Browser
    draw_activation(9.2, 14.0, 1.2, color="#ddd6fe")   # Controller
    draw_activation(13.0, 9.8, 1.5, color="#a7f3d0")  # Service
    draw_activation(16.8, 13.8, 1.6, color="#e2e8f0") # DB
    draw_activation(20.2, 13.0, 5.0, color="#bfdbfe") # AI / OTP

    # Helper Message
    def seq_msg(y, x1, x2, num, text, dashed=False, color="#1e293b"):
        linestyle = "--" if dashed else "-"
        ax.annotate("", xy=(x2, y), xytext=(x1, y),
                    arrowprops=dict(arrowstyle="-|>", linestyle=linestyle, color=color, lw=1.5, mutation_scale=10), zorder=5)
        mx = (x1 + x2) / 2
        ax.text(mx, y + 0.12, f"{num}. {text}", fontsize=7.5, color=color, weight='bold', ha='center', zorder=6)

    # 1. Login & Auth Flow
    seq_msg(14.2, 1.6, 5.2, 1, "Input Email & Password")
    seq_msg(13.7, 5.2, 9.2, 2, "POST /auth/login")
    seq_msg(13.2, 9.2, 16.8, 3, "Verifikasi hash password (bcrypt)")
    seq_msg(12.7, 9.2, 20.2, 4, "Generate & Kirim OTP via Email/WA")
    seq_msg(12.2, 9.2, 1.6, 5, "Tampilkan Layar Input OTP 6-Digit", dashed=True, color="#059669")

    # 2. Input Manual OTP & Otorisasi
    seq_msg(11.6, 1.6, 5.2, 6, "Ketik Manual Kode OTP 6 Digit")
    seq_msg(11.1, 5.2, 9.2, 7, "POST /auth/verify-otp")
    seq_msg(10.6, 9.2, 16.8, 8, "Validasi token di `otp_tokens` (is_used=1)")
    seq_msg(10.1, 9.2, 5.2, 9, "Otorisasi Sukses -> Buka Dashboard", dashed=True, color="#059669")

    # 3. Kalibrasi Dual Camera & Mulai Ujian
    seq_msg(9.5, 1.6, 5.2, 10, "Inisialisasi Dual Camera (Webcam + HP)")
    seq_msg(9.0, 5.2, 20.2, 11, "MediaPipe aktifkan landmark mesh wajah")
    seq_msg(8.5, 5.2, 9.2, 12, "POST /exam/start (exam_id, token)")
    seq_msg(8.0, 9.2, 13.0, 13, "startSession(userId, examId)")
    seq_msg(7.5, 13.0, 16.8, 14, "Insert `exam_sessions` (started_at)")
    seq_msg(7.0, 13.0, 5.2, 15, "Return Sesi Aktif + Sisa Waktu Detik", dashed=True)

    # Frame Box: Pengawasan AI & Auto-Save Loop
    loop_box = FancyBboxPatch((0.8, 2.7), 20.0, 3.8, boxstyle="round,pad=0.05,rounding_size=0.08",
                              facecolor='#fff7ed', edgecolor='#f97316', linestyle=':', linewidth=1.6, zorder=2)
    ax.add_patch(loop_box)
    ax.text(1.2, 6.2, "[LOOP: Setiap 3 Detik Selama Ujian Berlangsung]", fontsize=8, color='#c2410c', weight='bold', zorder=5)

    seq_msg(5.8, 5.2, 20.2, 16, "MediaPipe scan orientasi wajah & mata")
    seq_msg(5.2, 20.2, 5.2, 17, "Deteksi Wajah Menoleh / Hilang / Multi-Wajah", dashed=True, color="#b91c1c")
    seq_msg(4.6, 5.2, 13.0, 18, "POST Telemetri Pelanggaran")
    seq_msg(4.0, 13.0, 16.8, 19, "Insert into `ai_violations`")
    seq_msg(3.3, 1.6, 16.8, 20, "Auto-Save Jawaban Siswa ke `exam_answers`", color="#2563eb")

    # 4. Selesai Ujian & Rekap Nilai
    seq_msg(2.3, 1.6, 9.2, 21, "Klik Selesai Ujian / Waktu Habis")
    seq_msg(1.7, 9.2, 13.0, 22, "submitSession(sessionId)")
    seq_msg(1.1, 13.0, 16.8, 23, "Kunci sesi status = 'submitted' & hitung nilai")

    save_diagram(fig, '04_sequence_diagram.png')

if __name__ == '__main__':
    print("Mengeksekusi Generator UML Versi 2.0 (Ultra-Polish)...")
    generate_use_case_v2()
    generate_activity_v2()
    generate_class_diagram_v2()
    generate_sequence_diagram_v2()
    print("Semua 4 Diagram UML Versi 2.0 berhasil dibuat dan disimpan!")

