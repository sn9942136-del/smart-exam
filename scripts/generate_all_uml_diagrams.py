# -*- coding: utf-8 -*-
"""
Generator 4 Diagram UML Lengkap & Profesional untuk Proyek Smart Exam
Mata Kuliah: Rekayasa Perangkat Lunak (TIKB2503021) - STMIK IKMI Cirebon
Penyusun: Salsa Nabila (NIM: 41257158) | Kelompok: Tiba Tiba Talun
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
os.makedirs(DOCS_DIR, exist_ok=True)
os.makedirs(PUBLIC_DIR, exist_ok=True)

plt.rcParams['font.sans-serif'] = ['Segoe UI', 'DejaVu Sans', 'Arial', 'sans-serif']
plt.rcParams['font.family'] = 'sans-serif'

def save_diagram(fig, filename):
    p1 = os.path.join(DOCS_DIR, filename)
    p2 = os.path.join(PUBLIC_DIR, filename)
    fig.savefig(p1, dpi=200, bbox_inches='tight')
    fig.savefig(p2, dpi=200, bbox_inches='tight')
    plt.close(fig)
    print(f"Diagram tersimpan: {p1} & {p2}")

def draw_header(ax, title, subtitle):
    header = FancyBboxPatch((0.5, 11.6), 18.0, 1.1,
                            boxstyle="round,pad=0.08,rounding_size=0.1",
                            facecolor='#0f172a', edgecolor='none', zorder=3)
    ax.add_patch(header)
    ax.text(9.5, 12.35, title, fontsize=15, fontweight='bold', color='white', ha='center', va='center', zorder=4)
    ax.text(9.5, 11.90, subtitle, fontsize=9.5, color='#94a3b8', ha='center', va='center', zorder=4)

# ==============================================================================
# 1. USE CASE DIAGRAM (6 AKTOR: 4 KIRI, 2 KANAN)
# ==============================================================================
def generate_use_case():
    fig, ax = plt.subplots(figsize=(19, 13), dpi=200)
    ax.set_xlim(-1, 19)
    ax.set_ylim(-1, 13)
    ax.axis('off')
    fig.patch.set_facecolor('#f8fafc')
    ax.set_facecolor('#f8fafc')

    draw_header(ax, "USE CASE DIAGRAM — SISTEM SMART EXAM (6 AKTOR)",
                "Penyusun: Salsa Nabila (NIM: 41257158) | Kelompok: Tiba Tiba Talun | Dosen: Yudhistira Arie Wijaya, M.Kom., CDS.")

    # Boundary Box Sistem
    boundary = FancyBboxPatch((4.2, 0.5), 10.6, 10.7,
                              boxstyle="round,pad=0.1,rounding_size=0.15",
                              facecolor='#ffffff', edgecolor='#94a3b8', linewidth=2.0, linestyle='--', zorder=2)
    ax.add_patch(boundary)
    ax.text(9.5, 10.85, "Sistem Batasan: Smart Exam (Dual Camera AI & Live DB Chatbot)", 
            fontsize=12, fontweight='bold', color='#1e3a8a', ha='center', va='center', zorder=3)

    # Fungsi gambar Aktor
    def draw_actor(x, y, name, desc, is_sys=False, color='#1e40af'):
        if not is_sys:
            # Stickman
            ax.add_patch(Circle((x, y + 0.32), 0.12, facecolor='white', edgecolor=color, linewidth=2.5, zorder=5))
            ax.plot([x, x], [y + 0.20, y - 0.15], color=color, linewidth=2.5, zorder=5)
            ax.plot([x - 0.22, x + 0.22], [y + 0.08, y + 0.08], color=color, linewidth=2.5, zorder=5)
            ax.plot([x, x - 0.18], [y - 0.15, y - 0.42], color=color, linewidth=2.5, zorder=5)
            ax.plot([x, x + 0.18], [y - 0.15, y - 0.42], color=color, linewidth=2.5, zorder=5)
        else:
            # System Box
            box = FancyBboxPatch((x - 0.45, y - 0.25), 0.90, 0.60,
                                 boxstyle="round,pad=0.05,rounding_size=0.08",
                                 facecolor='#f1f5f9', edgecolor=color, linewidth=2.5, zorder=5)
            ax.add_patch(box)
            ax.text(x, y + 0.08, "[SYSTEM]", fontsize=8.5, color='#475569', ha='center', weight='bold', zorder=6)
            ax.text(x, y - 0.10, "Service API", fontsize=7.5, color='#64748b', ha='center', zorder=6)

        ax.text(x, y - 0.60, name, fontsize=10.5, fontweight='bold', ha='center', va='top', color='#0f172a', zorder=6)
        if desc:
            ax.text(x, y - 0.76, desc, fontsize=8, color='#475569', ha='center', va='top', zorder=6)

    # 4 Aktor KIRI
    draw_actor(1.8, 9.5, "Super Admin", "(Akses Penuh & Audit)", color="#7c3aed")
    draw_actor(1.8, 6.8, "Administrator", "(Manajemen Akademik)", color="#0284c7")
    draw_actor(1.8, 4.1, "Guru / Pengawas", "(Bank Soal & Penilaian)", color="#059669")
    draw_actor(1.8, 1.4, "Peserta (Siswa)", "(Pelaksana Ujian)", color="#ea580c")

    # 2 Aktor KANAN
    draw_actor(17.0, 7.5, "AI Proctoring Engine", "(MediaPipe Face Mesh)", is_sys=True, color="#2563eb")
    draw_actor(17.0, 3.0, "Sistem Layanan OTP", "(Gmail SMTP & WA Gateway)", is_sys=True, color="#16a34a")

    # 10 Use Cases di Tengah
    use_cases = [
        {"id": "UC1", "x": 6.5, "y": 9.8, "w": 3.4, "h": 0.82, "text": "UC-01: Autentikasi &\nManajemen Sesi Multi-Role", "fill": "#eff6ff", "stroke": "#3b82f6"},
        {"id": "UC2", "x": 12.5, "y": 9.8, "w": 3.4, "h": 0.82, "text": "UC-02: Verifikasi 2FA OTP\n(Email & WhatsApp)", "fill": "#ecfdf5", "stroke": "#10b981"},
        {"id": "UC3", "x": 6.5, "y": 8.4, "w": 3.4, "h": 0.82, "text": "UC-03: Kelola Bank Soal &\nPaket Jadwal Ujian", "fill": "#eff6ff", "stroke": "#3b82f6"},
        {"id": "UC4", "x": 12.5, "y": 8.4, "w": 3.4, "h": 0.82, "text": "UC-04: Kalibrasi Dual Camera\n(Laptop & HP Samping)", "fill": "#fef3c7", "stroke": "#f59e0b"},
        {"id": "UC5", "x": 6.5, "y": 7.0, "w": 3.4, "h": 0.82, "text": "UC-05: Pelaksanaan Ujian CBT\n& Auto-Save Jawaban", "fill": "#fff7ed", "stroke": "#f97316"},
        {"id": "UC6", "x": 12.5, "y": 7.0, "w": 3.4, "h": 0.82, "text": "UC-06: Deteksi Kecurangan Wajah\n(AI Face Mesh & Gaze)", "fill": "#fef2f2", "stroke": "#ef4444"},
        {"id": "UC7", "x": 6.5, "y": 5.6, "w": 3.4, "h": 0.82, "text": "UC-07: Monitoring Ujian Live\n& Telemetri Pelanggaran", "fill": "#eff6ff", "stroke": "#3b82f6"},
        {"id": "UC8", "x": 12.5, "y": 5.6, "w": 3.4, "h": 0.82, "text": "UC-08: Koreksi Nilai Otomatis &\nEvaluasi Esai AI", "fill": "#f5f3ff", "stroke": "#8b5cf6"},
        {"id": "UC9", "x": 6.5, "y": 4.2, "w": 3.4, "h": 0.82, "text": "UC-09: Chatbot Operasional AI\n(Grounding Live Database)", "fill": "#ecfeff", "stroke": "#06b6d4"},
        {"id": "UC10", "x": 12.5, "y": 4.2, "w": 3.4, "h": 0.82, "text": "UC-10: Audit Log Forensik &\nKonfigurasi Parameter AI", "fill": "#f1f5f9", "stroke": "#64748b"},
        {"id": "UC11", "x": 9.5, "y": 2.2, "w": 3.8, "h": 0.82, "text": "UC-11: Ekspor Rekap Nilai &\nBerita Acara (PDF/Excel)", "fill": "#f8fafc", "stroke": "#475569"}
    ]

    uc_map = {}
    for uc in use_cases:
        el = Ellipse((uc["x"], uc["y"]), uc["w"], uc["h"], facecolor=uc["fill"], edgecolor=uc["stroke"], linewidth=1.8, zorder=4)
        ax.add_patch(el)
        ax.text(uc["x"], uc["y"], uc["text"], fontsize=8.5, fontweight='bold', ha='center', va='center', color='#0f172a', zorder=5)
        uc_map[uc["id"]] = (uc["x"], uc["y"], uc["w"], uc["h"])

    def link(x1, y1, uc_id, color='#94a3b8', style='-', width=1.3):
        x2, y2, w, h = uc_map[uc_id]
        dx = x2 - x1
        tx = x2 - (w/2) * 0.95 if dx > 0 else x2 + (w/2) * 0.95
        ax.plot([x1, tx], [y1, y2], color=color, linestyle=style, linewidth=width, zorder=3)

    # Koneksi Kiri
    link(2.3, 9.5, "UC1", "#7c3aed"); link(2.3, 9.5, "UC3", "#7c3aed"); link(2.3, 9.5, "UC7", "#7c3aed"); link(2.3, 9.5, "UC9", "#7c3aed"); link(2.3, 9.5, "UC10", "#7c3aed")
    link(2.3, 6.8, "UC1", "#0284c7"); link(2.3, 6.8, "UC3", "#0284c7"); link(2.3, 6.8, "UC7", "#0284c7"); link(2.3, 6.8, "UC9", "#0284c7"); link(2.3, 6.8, "UC11", "#0284c7")
    link(2.3, 4.1, "UC1", "#059669"); link(2.3, 4.1, "UC3", "#059669"); link(2.3, 4.1, "UC7", "#059669"); link(2.3, 4.1, "UC8", "#059669"); link(2.3, 4.1, "UC9", "#059669"); link(2.3, 4.1, "UC11", "#059669")
    link(2.3, 1.4, "UC1", "#ea580c", width=1.6); link(2.3, 1.4, "UC2", "#ea580c", width=1.6); link(2.3, 1.4, "UC4", "#ea580c", width=1.6); link(2.3, 1.4, "UC5", "#ea580c", width=1.6)

    # Koneksi Kanan (Sistem Sekunder)
    link(16.5, 7.5, "UC4", "#2563eb", "--", 1.6); link(16.5, 7.5, "UC5", "#2563eb", "--", 1.6); link(16.5, 7.5, "UC6", "#2563eb", "--", 1.8); link(16.5, 7.5, "UC7", "#2563eb", "--", 1.6)
    link(16.5, 3.0, "UC2", "#16a34a", "--", 1.8)

    # Include relations
    ax.annotate("", xy=(10.7, 9.8), xytext=(8.3, 9.8), arrowprops=dict(arrowstyle="->", color="#64748b", linestyle=":", lw=1.5), zorder=6)
    ax.text(9.5, 10.05, "<<include>>", fontsize=7.5, color="#64748b", ha="center", weight="bold", zorder=7)

    ax.annotate("", xy=(10.7, 7.0), xytext=(8.3, 7.0), arrowprops=dict(arrowstyle="->", color="#ef4444", linestyle=":", lw=1.5), zorder=6)
    ax.text(9.5, 7.25, "<<include>>", fontsize=7.5, color="#ef4444", ha="center", weight="bold", zorder=7)

    # Legend
    leg = FancyBboxPatch((4.5, 0.65), 10.0, 0.8, boxstyle="round,pad=0.05,rounding_size=0.08", facecolor='#f1f5f9', edgecolor='#94a3b8', linewidth=1.0, zorder=4)
    ax.add_patch(leg)
    ax.text(9.5, 1.20, "Keterangan Relasi & Batasan Aktor:", fontsize=8.5, fontweight='bold', ha='center', color='#1e293b', zorder=5)
    ax.text(9.5, 0.95, "• Kiri: 4 Aktor Primer (Pengguna Manusia) | • Kanan: 2 Aktor Sekunder (AI Engine & OTP Gateway)", fontsize=8, ha='center', color='#334155', zorder=5)
    ax.text(9.5, 0.75, "Garis Putus-Putus (---): Integrasi Layanan Sistem Otomatis | Garis Titik-Titik (...>): Relasi <<include>>", fontsize=7.5, ha='center', color='#64748b', zorder=5)

    save_diagram(fig, '01_use_case_diagram.png')

# ==============================================================================
# 2. ACTIVITY DIAGRAM (ALUR WORKFLOW UJIAN & AI PROCTORING)
# ==============================================================================
def generate_activity():
    fig, ax = plt.subplots(figsize=(19, 13), dpi=200)
    ax.set_xlim(-1, 19)
    ax.set_ylim(-1, 13)
    ax.axis('off')
    fig.patch.set_facecolor('#f8fafc')
    ax.set_facecolor('#f8fafc')

    draw_header(ax, "ACTIVITY DIAGRAM — ALUR PELAKSANAAN UJIAN & AI PROCTORING",
                "Penyusun: Salsa Nabila (NIM: 41257158) | Kelompok: Tiba Tiba Talun | Dosen: Yudhistira Arie Wijaya, M.Kom., CDS.")

    # Swimlanes (3 Kolom: Peserta Didik, Sistem Smart Exam Backend, AI Proctoring & Notification Engine)
    lanes = [
        {"x": 0.5, "w": 5.8, "title": "1. Peserta Ujian (Siswa)", "color": "#eff6ff", "border": "#3b82f6"},
        {"x": 6.6, "w": 6.3, "title": "2. Sistem Smart Exam (Clean MVC Backend)", "color": "#f8fafc", "border": "#64748b"},
        {"x": 13.2, "w": 5.3, "title": "3. AI Engine & Layanan Gateway", "color": "#f0fdf4", "border": "#22c55e"}
    ]
    for lane in lanes:
        box = FancyBboxPatch((lane["x"], 0.6), lane["w"], 10.6,
                             boxstyle="round,pad=0.05,rounding_size=0.1",
                             facecolor=lane["color"], edgecolor=lane["border"], linewidth=1.5, zorder=2)
        ax.add_patch(box)
        ax.text(lane["x"] + lane["w"]/2, 10.9, lane["title"], fontsize=11, fontweight='bold', ha='center', va='center', color='#0f172a', zorder=3)

    # Simbol Helper
    def draw_action(x, y, w, h, text, fill="#ffffff", border="#2563eb"):
        box = FancyBboxPatch((x - w/2, y - h/2), w, h, boxstyle="round,pad=0.05,rounding_size=0.08",
                             facecolor=fill, edgecolor=border, linewidth=1.6, zorder=5)
        ax.add_patch(box)
        ax.text(x, y, text, fontsize=8.5, ha='center', va='center', color='#0f172a', weight='bold', zorder=6)

    def draw_decision(x, y, text):
        d = 0.55
        diamond = Polygon([[x, y + d], [x + d*1.3, y], [x, y - d], [x - d*1.3, y]],
                          facecolor='#fef3c7', edgecolor='#f59e0b', linewidth=1.6, zorder=5)
        ax.add_patch(diamond)
        ax.text(x, y, text, fontsize=7.5, ha='center', va='center', color='#78350f', weight='bold', zorder=6)

    def draw_arrow(x1, y1, x2, y2, label=""):
        ax.annotate("", xy=(x2, y2), xytext=(x1, y1),
                    arrowprops=dict(arrowstyle="-|>", color="#1e293b", lw=1.6, mutation_scale=12), zorder=4)
        if label:
            mx, my = (x1 + x2)/2, (y1 + y2)/2
            ax.text(mx + 0.15, my + 0.1, label, fontsize=7.5, color='#b91c1c', weight='bold', zorder=7)

    # Node Mulai
    ax.add_patch(Circle((3.4, 10.2), 0.22, facecolor='#0f172a', edgecolor='none', zorder=6))
    ax.text(3.4, 10.55, "Mulai", fontsize=8.5, ha='center', weight='bold', color='#0f172a')

    # Alur 1: Login
    draw_arrow(3.4, 9.98, 3.4, 9.3)
    draw_action(3.4, 8.9, 4.4, 0.7, "Input Email & Password")

    draw_arrow(3.4, 8.55, 9.75, 8.55)
    draw_action(9.75, 8.55, 4.8, 0.7, "Verifikasi Hash Password (bcrypt)")

    draw_arrow(9.75, 8.2, 15.85, 8.2)
    draw_action(15.85, 8.2, 4.4, 0.7, "Generate & Kirim OTP 6 Digit\n(Gmail SMTP / WhatsApp)", fill="#ecfdf5", border="#10b981")

    draw_arrow(15.85, 7.85, 3.4, 7.85)
    draw_action(3.4, 7.5, 4.4, 0.7, "Ketik Manual Kode OTP")

    draw_arrow(3.4, 7.15, 9.75, 7.15)
    draw_decision(9.75, 6.7, "OTP Valid?")
    draw_arrow(9.75, 7.15, 9.75, 7.05)

    # Cabang OTP Tidak Valid
    ax.plot([10.45, 11.8, 11.8, 5.6], [6.7, 6.7, 7.5, 7.5], color="#b91c1c", linestyle="--", lw=1.4, zorder=4)
    ax.text(11.0, 6.85, "Tidak", fontsize=7.5, color="#b91c1c", weight="bold")

    # Cabang OTP Valid -> Kalibrasi Kamera
    draw_arrow(9.75, 6.35, 9.75, 5.7, "Ya")
    draw_action(9.75, 5.7, 4.8, 0.7, "Otorisasi Sesi & Muat Jadwal Ujian")

    draw_arrow(9.75, 5.35, 3.4, 5.35)
    draw_action(3.4, 4.9, 4.4, 0.7, "Kalibrasi Dual Kamera\n(Webcam Laptop + HP Meja)", fill="#fef3c7", border="#f59e0b")

    draw_arrow(3.4, 4.55, 9.75, 4.55)
    draw_action(9.75, 4.2, 4.8, 0.7, "Mulai Sesi Ujian di Server\n(Server-Authoritative Timer)")

    # Loop Ujian & Pengawasan AI
    draw_arrow(9.75, 3.85, 15.85, 3.85)
    draw_action(15.85, 3.85, 4.4, 0.7, "MediaPipe AI Face Mesh:\nDeteksi Gaze, Menoleh, Hilang", fill="#fef2f2", border="#ef4444")

    draw_arrow(15.85, 3.5, 9.75, 3.5)
    draw_action(9.75, 3.0, 4.8, 0.7, "Catat Pelanggaran ke Basis Data\n& Sinkronisasi Auto-Save")

    draw_arrow(9.75, 2.65, 3.4, 2.65)
    draw_action(3.4, 2.3, 4.4, 0.7, "Pilih Jawaban & Selesaikan Ujian")

    draw_arrow(3.4, 1.95, 9.75, 1.95)
    draw_action(9.75, 1.6, 4.8, 0.7, "Kunci Sesi, Nilai Otomatis PG\n& Evaluasi Esai AI")

    # Node Selesai (Bullseye)
    draw_arrow(9.75, 1.25, 9.75, 0.95)
    ax.add_patch(Circle((9.75, 0.85), 0.22, facecolor='none', edgecolor='#0f172a', linewidth=2.0, zorder=6))
    ax.add_patch(Circle((9.75, 0.85), 0.14, facecolor='#0f172a', edgecolor='none', zorder=7))
    ax.text(10.25, 0.85, "Selesai", fontsize=8.5, va='center', weight='bold', color='#0f172a')

    save_diagram(fig, '02_activity_diagram.png')

# ==============================================================================
# 3. CLASS DIAGRAM (ARSITEKTUR MVC & SERVICE LAYER)
# ==============================================================================
def generate_class_diagram():
    fig, ax = plt.subplots(figsize=(20, 13.5), dpi=200)
    ax.set_xlim(-1, 20)
    ax.set_ylim(-1, 13.5)
    ax.axis('off')
    fig.patch.set_facecolor('#f8fafc')
    ax.set_facecolor('#f8fafc')

    draw_header(ax, "CLASS DIAGRAM — ARSITEKTUR CLEAN MVC & SERVICE LAYER",
                "Penyusun: Salsa Nabila (NIM: 41257158) | Kelompok: Tiba Tiba Talun | Dosen: Yudhistira Arie Wijaya, M.Kom., CDS.")

    def draw_uml_class(x, y, w, h_head, h_attr, h_meth, title, stereotype, attrs, meths, color="#1e3a8a", fill="#ffffff"):
        total_h = h_head + h_attr + h_meth
        # Outer box
        box = FancyBboxPatch((x, y - total_h), w, total_h, boxstyle="round,pad=0.03,rounding_size=0.06",
                             facecolor=fill, edgecolor=color, linewidth=1.8, zorder=4)
        ax.add_patch(box)
        # Header banner
        header = Rectangle((x, y - h_head), w, h_head, facecolor=color, edgecolor=color, zorder=5)
        ax.add_patch(header)
        ax.text(x + w/2, y - 0.22, stereotype, fontsize=7.5, color='#cbd5e1', ha='center', weight='bold', zorder=6)
        ax.text(x + w/2, y - 0.50, title, fontsize=9.5, color='white', ha='center', weight='bold', zorder=6)

        # Garis pemisah atribut & method
        ax.plot([x, x + w], [y - h_head - h_attr, y - h_head - h_attr], color='#cbd5e1', lw=1.2, zorder=5)

        # Teks Atribut
        ay = y - h_head - 0.25
        for a in attrs:
            ax.text(x + 0.15, ay, a, fontsize=7.5, color='#334155', fontfamily='monospace', zorder=6)
            ay -= 0.24

        # Teks Method
        my = y - h_head - h_attr - 0.25
        for m in meths:
            ax.text(x + 0.15, my, m, fontsize=7.5, color='#0f172a', fontfamily='monospace', weight='bold', zorder=6)
            my -= 0.24

    # 1. BaseModel (Superclass Model)
    draw_uml_class(1.0, 11.2, 5.0, 0.7, 1.0, 1.4, "BaseModel", "<<Abstract Model>>",
                   ["# db : PDO", "# table : string", "# primaryKey : string"],
                   ["+ find(id) : array", "+ where(conditions) : array", "+ create(data) : int", "+ update(id, data) : bool", "+ delete(id) : bool"],
                   color="#334155", fill="#f8fafc")

    # 2. UserModel (Turunan BaseModel)
    draw_uml_class(1.0, 7.6, 5.0, 0.7, 1.2, 1.0, "UserModel", "<<Model>>",
                   ["+ id : int", "+ name : string", "+ email : string", "+ password : string", "+ role_id : int"],
                   ["+ verifyPassword(plain) : bool", "+ findByEmail(email) : array", "+ getRoleName() : string"],
                   color="#0284c7")

    # 3. ExamModel & ExamSessionModel
    draw_uml_class(1.0, 4.2, 5.0, 0.7, 1.5, 1.2, "ExamSessionModel", "<<Model>>",
                   ["+ id : int", "+ exam_id : int", "+ user_id : int", "+ started_at : datetime", "+ submitted_at : datetime", "+ total_score : float"],
                   ["+ getRemainingSeconds() : int", "+ markSubmitted() : bool", "+ isExpired() : bool"],
                   color="#0284c7")

    # 4. Service Layer (Tengah)
    draw_uml_class(7.0, 11.2, 5.6, 0.7, 1.0, 1.8, "ExamSessionService", "<<Service>>",
                   ["- examModel : ExamModel", "- sessionModel : ExamSessionModel", "- answerModel : ExamAnswerModel"],
                   ["+ startSession(userId, examId) : array", "+ saveAnswer(sessionId, qId, ans) : bool",
                    "+ validateSessionTimer(sessionId) : bool", "+ submitSession(sessionId) : array", "+ autoGradeMultipleChoice() : float"],
                   color="#059669")

    draw_uml_class(7.0, 7.2, 5.6, 0.7, 1.0, 1.6, "AiProctoringService", "<<Service>>",
                   ["- violationModel : AiViolationModel", "- sessionModel : ExamSessionModel"],
                   ["+ recordTelemetry(sessionId, data) : bool", "+ analyzeFaceViolation(type) : void",
                    "+ issueWarning(sessionId, msg) : void", "+ getIncidentSummary(sessionId) : array"],
                   color="#059669")

    draw_uml_class(7.0, 3.4, 5.6, 0.7, 0.8, 1.4, "AiChatService", "<<Service>>",
                   ["- apiKey : string", "- model : string"],
                   ["+ processUserQuery(query, role) : string", "+ getOperationalContext() : array", "+ executeGeminiApi(prompt) : string"],
                   color="#059669")

    # 5. Controllers (Kanan)
    draw_uml_class(13.6, 11.2, 5.4, 0.7, 0.8, 1.6, "ExamController", "<<Controller>>",
                   ["- examService : ExamSessionService", "- proctorService : AiProctoringService"],
                   ["+ start(examId) : Response", "+ showRoom(sessionId) : View", "+ saveAnswer(request) : Json", "+ finish(sessionId) : Response"],
                   color="#7c3aed")

    draw_uml_class(13.6, 7.6, 5.4, 0.7, 0.8, 1.4, "AuthController", "<<Controller>>",
                   ["- userModel : UserModel", "- otpService : OtpService"],
                   ["+ login(request) : Response", "+ verifyOtp(request) : Response", "+ resendOtp() : Json", "+ logout() : Response"],
                   color="#7c3aed")

    draw_uml_class(13.6, 4.2, 5.4, 0.7, 0.6, 1.4, "AiChatController", "<<Controller>>",
                   ["- chatService : AiChatService"],
                   ["+ sendMessage(request) : Json", "+ getHistory(sessionId) : Json", "+ updateConfiguration(req) : Json"],
                   color="#7c3aed")

    # Relasi Generalization: UserModel & ExamSessionModel inherits BaseModel
    def draw_inheritance(x1, y1, x2, y2):
        ax.plot([x1, x1, x2, x2], [y1, (y1+y2)/2, (y1+y2)/2, y2], color="#334155", lw=1.8, zorder=3)
        # Panah segitiga tertutup kosong (UML Generalization)
        triangle = Polygon([[x2, y2], [x2 - 0.15, y2 - 0.25], [x2 + 0.15, y2 - 0.25]],
                           facecolor='white', edgecolor='#334155', linewidth=1.8, zorder=5)
        ax.add_patch(triangle)

    draw_inheritance(3.5, 7.6, 3.5, 8.1)
    draw_inheritance(3.5, 4.2, 3.5, 4.7)

    # Relasi Association (Controller -> Service -> Model)
    def draw_assoc(x1, y1, x2, y2, label=""):
        ax.annotate("", xy=(x2, y2), xytext=(x1, y1),
                    arrowprops=dict(arrowstyle="->", color="#475569", lw=1.5), zorder=3)
        if label:
            ax.text((x1+x2)/2, (y1+y2)/2 + 0.15, label, fontsize=7.5, color='#475569', ha='center', weight='bold', zorder=6)

    draw_assoc(13.6, 9.8, 12.6, 9.8, "memanggil")
    draw_assoc(13.6, 6.2, 12.6, 6.2, "memanggil")
    draw_assoc(13.6, 3.0, 12.6, 3.0, "memanggil")

    draw_assoc(7.0, 9.5, 6.0, 6.5, "mengakses data")
    draw_assoc(7.0, 4.5, 6.0, 3.5, "mengakses data")

    save_diagram(fig, '03_class_diagram.png')

# ==============================================================================
# 4. SEQUENCE DIAGRAM (INTERAKSI LENGKAP END-TO-END)
# ==============================================================================
def generate_sequence_diagram():
    fig, ax = plt.subplots(figsize=(20, 13.5), dpi=200)
    ax.set_xlim(-0.5, 20.5)
    ax.set_ylim(-1, 13.5)
    ax.axis('off')
    fig.patch.set_facecolor('#f8fafc')
    ax.set_facecolor('#f8fafc')

    draw_header(ax, "SEQUENCE DIAGRAM — INTERAKSI END-TO-END SISTEM SMART EXAM",
                "Penyusun: Salsa Nabila (NIM: 41257158) | Kelompok: Tiba Tiba Talun | Dosen: Yudhistira Arie Wijaya, M.Kom., CDS.")

    # 6 Lifeline Komponen
    lifelines = [
        {"x": 1.5, "name": "Peserta\n(Siswa)", "color": "#ea580c"},
        {"x": 4.8, "name": "Browser Client\n(JS + MediaPipe)", "color": "#0284c7"},
        {"x": 8.5, "name": "Controller &\nSecurity Filter", "color": "#7c3aed"},
        {"x": 12.0, "name": "Service Layer\n(Exam/Proctoring)", "color": "#059669"},
        {"x": 15.5, "name": "Basis Data\nMySQL 8.0", "color": "#0f172a"},
        {"x": 19.0, "name": "AI Engine &\nLayanan OTP", "color": "#2563eb"}
    ]

    for line in lifelines:
        x = line["x"]
        # Header Box Lifeline
        box = FancyBboxPatch((x - 1.2, 10.4), 2.4, 0.85,
                             boxstyle="round,pad=0.05,rounding_size=0.08",
                             facecolor=line["color"], edgecolor='none', zorder=4)
        ax.add_patch(box)
        ax.text(x, 10.82, line["name"], fontsize=8.5, color='white', weight='bold', ha='center', va='center', zorder=5)
        # Garis Vertikal Lifeline
        ax.plot([x, x], [10.4, 0.4], color='#94a3b8', linestyle='--', linewidth=1.5, zorder=2)

    # Helper Message
    def seq_msg(y, x1, x2, num, text, dashed=False, color="#1e293b"):
        linestyle = "--" if dashed else "-"
        ax.annotate("", xy=(x2, y), xytext=(x1, y),
                    arrowprops=dict(arrowstyle="-|>", linestyle=linestyle, color=color, lw=1.4, mutation_scale=10), zorder=4)
        mx = (x1 + x2) / 2
        ax.text(mx, y + 0.12, f"{num}. {text}", fontsize=7.5, color=color, weight='bold', ha='center', zorder=5)

    # 1. Login & Auth
    seq_msg(10.0, 1.5, 4.8, 1, "Input Email & Password")
    seq_msg(9.6, 4.8, 8.5, 2, "POST /auth/login")
    seq_msg(9.2, 8.5, 15.5, 3, "Verifikasi hash sandi (bcrypt)")
    seq_msg(8.8, 8.5, 19.0, 4, "Generate OTP & Kirim (Email/WA)")
    seq_msg(8.4, 8.5, 1.5, 5, "Redirect ke Form Input OTP", dashed=True, color="#059669")

    # 2. Input OTP
    seq_msg(8.0, 1.5, 4.8, 6, "Ketik Kode OTP 6 Digit")
    seq_msg(7.6, 4.8, 8.5, 7, "POST /auth/verify-otp")
    seq_msg(7.2, 8.5, 15.5, 8, "Validasi token & update is_used = 1")
    seq_msg(6.8, 8.5, 4.8, 9, "Otorisasi Sukses -> Buka Dashboard", dashed=True, color="#059669")

    # 3. Kalibrasi Kamera & Mulai Ujian
    seq_msg(6.4, 1.5, 4.8, 10, "Inisialisasi Dual Camera (Webcam + HP)")
    seq_msg(6.0, 4.8, 19.0, 11, "MediaPipe aktifkan landmark wajah")
    seq_msg(5.6, 4.8, 8.5, 12, "POST /exam/start (exam_id, token)")
    seq_msg(5.2, 8.5, 12.0, 13, "startSession(userId, examId)")
    seq_msg(4.8, 12.0, 15.5, 14, "Insert into `exam_sessions` (started_at)")
    seq_msg(4.4, 12.0, 4.8, 15, "Return Sesi Aktif + Sisa Waktu Detik", dashed=True)

    # Frame Loop: Proctoring & Auto-Save
    loop_box = FancyBboxPatch((0.8, 1.8), 18.8, 2.3, boxstyle="round,pad=0.05,rounding_size=0.08",
                              facecolor='#fff7ed', edgecolor='#f97316', linestyle=':', linewidth=1.5, zorder=2)
    ax.add_patch(loop_box)
    ax.text(1.2, 3.9, "[LOOP: Setiap 3 Detik Selama Ujian]", fontsize=7.5, color='#c2410c', weight='bold', zorder=5)

    seq_msg(3.6, 4.8, 19.0, 16, "Scan orientasi wajah (MediaPipe)")
    seq_msg(3.2, 19.0, 4.8, 17, "Deteksi Wajah Menoleh / Hilang", dashed=True, color="#b91c1c")
    seq_msg(2.8, 4.8, 12.0, 18, "POST Telemetri Pelanggaran")
    seq_msg(2.4, 12.0, 15.5, 19, "Insert into `ai_violations`")
    seq_msg(2.0, 1.5, 15.5, 20, "Auto-Save Jawaban Siswa ke `exam_answers`", color="#2563eb")

    # 4. Selesai Ujian
    seq_msg(1.5, 1.5, 8.5, 21, "Klik Selesai Ujian / Waktu Habis")
    seq_msg(1.1, 8.5, 12.0, 22, "submitSession(sessionId)")
    seq_msg(0.7, 12.0, 15.5, 23, "Kunci sesi status = 'submitted' & hitung nilai")

    save_diagram(fig, '04_sequence_diagram.png')

if __name__ == '__main__':
    print("Mulai membuat 4 Diagram UML dalam format PNG...")
    generate_use_case()
    generate_activity()
    generate_class_diagram()
    generate_sequence_diagram()
    print("Semua 4 Diagram UML berhasil di-generate!")
