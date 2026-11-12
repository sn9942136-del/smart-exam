# -*- coding: utf-8 -*-
"""
Generator 4 Diagram UML Resmi untuk Proyek Smart Exam
Mahasiswa: Salsa Nabila (NIM: 41257158) - STMIK IKMI Cirebon
"""

import os
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
import matplotlib.patches as patches
from matplotlib.patches import FancyBboxPatch, Ellipse, Circle, FancyArrowPatch

os.environ['MPLCONFIGDIR'] = os.path.join(os.getcwd(), 'scratch')
OUTPUT_DIR_DOCS = os.path.join('docs', 'diagrams')
OUTPUT_DIR_PUBLIC = os.path.join('public', 'assets', 'diagrams')
os.makedirs(OUTPUT_DIR_DOCS, exist_ok=True)
os.makedirs(OUTPUT_DIR_PUBLIC, exist_ok=True)

plt.rcParams['font.sans-serif'] = ['Segoe UI', 'DejaVu Sans', 'Arial', 'sans-serif']
plt.rcParams['font.family'] = 'sans-serif'

def draw_actor(ax, x, y, name, role_desc, is_system=False, color='#1e40af'):
    """Menggambar aktor UML (Stickman untuk manusia, Box/Cylinder/Server untuk sistem)"""
    if not is_system:
        # Kepala
        head = Circle((x, y + 0.32), 0.12, facecolor='white', edgecolor=color, linewidth=2.5, zorder=5)
        ax.add_patch(head)
        # Badan
        ax.plot([x, x], [y + 0.20, y - 0.15], color=color, linewidth=2.5, zorder=5)
        # Tangan
        ax.plot([x - 0.22, x + 0.22], [y + 0.08, y + 0.08], color=color, linewidth=2.5, zorder=5)
        # Kaki kiri & kanan
        ax.plot([x, x - 0.18], [y - 0.15, y - 0.42], color=color, linewidth=2.5, zorder=5)
        ax.plot([x, x + 0.18], [y - 0.15, y - 0.42], color=color, linewidth=2.5, zorder=5)
    else:
        # System Actor (Kotak bersudut rounded / Server Node)
        box = FancyBboxPatch((x - 0.35, y - 0.25), 0.70, 0.60,
                             boxstyle="round,pad=0.05,rounding_size=0.08",
                             facecolor='#f8fafc', edgecolor=color, linewidth=2.5, zorder=5)
        ax.add_patch(box)
        # Simbol gear / server
        ax.text(x, y + 0.12, "⚙️", fontsize=16, ha='center', va='center', zorder=6)
        ax.text(x, y - 0.08, "<<System>>", fontsize=8, color='#64748b', ha='center', va='center', weight='bold', zorder=6)

    # Label Nama Aktor
    ax.text(x, y - 0.58, name, fontsize=11, fontweight='bold', ha='center', va='top', color='#0f172a', zorder=6)
    if role_desc:
        ax.text(x, y - 0.73, role_desc, fontsize=8.5, color='#475569', ha='center', va='top', zorder=6)

def generate_use_case():
    fig, ax = plt.subplots(figsize=(19, 13), dpi=200)
    ax.set_xlim(-1, 19)
    ax.set_ylim(-1, 13)
    ax.axis('off')

    # Background canvas
    fig.patch.set_facecolor('#f8fafc')
    ax.set_facecolor('#f8fafc')

    # Header Proyek & Identitas
    header_box = FancyBboxPatch((0.5, 11.6), 18.0, 1.1,
                                boxstyle="round,pad=0.08,rounding_size=0.1",
                                facecolor='#1e293b', edgecolor='none', zorder=3)
    ax.add_patch(header_box)
    ax.text(9.5, 12.35, "USE CASE DIAGRAM — SISTEM SMART EXAM (6 AKTOR)", 
            fontsize=15, fontweight='bold', color='white', ha='center', va='center', zorder=4)
    ax.text(9.5, 11.92, "Penyusun: Salsa Nabila (NIM: 41257158) | Kelompok: Tiba Tiba Talun | Dosen: Yudhistira Arie Wijaya, M.Kom., CDS.", 
            fontsize=9.5, color='#94a3b8', ha='center', va='center', zorder=4)

    # System Boundary Box (Smart Exam)
    boundary = FancyBboxPatch((4.2, 0.4), 10.6, 10.8,
                              boxstyle="round,pad=0.1,rounding_size=0.15",
                              facecolor='#ffffff', edgecolor='#cbd5e1', linewidth=2.0, linestyle='--', zorder=2)
    ax.add_patch(boundary)
    ax.text(9.5, 10.85, "Sistem Batasan: Smart Exam (Dual Camera AI & Live DB Chatbot)", 
            fontsize=12, fontweight='bold', color='#1e3a8a', ha='center', va='center', zorder=3)

    # 4 Aktor di sebelah KIRI (Pengguna Manusia)
    left_actors = [
        {"x": 1.8, "y": 9.5, "name": "Super Admin", "desc": "(Sistem & Kontrol Audit)", "color": "#7c3aed"},
        {"x": 1.8, "y": 6.8, "name": "Administrator", "desc": "(Manajemen Akademik)", "color": "#0284c7"},
        {"x": 1.8, "y": 4.1, "name": "Guru / Pengawas", "desc": "(Bank Soal & Penilaian)", "color": "#059669"},
        {"x": 1.8, "y": 1.4, "name": "Peserta (Siswa)", "desc": "(Pelaksana Ujian)", "color": "#ea580c"}
    ]
    for act in left_actors:
        draw_actor(ax, act["x"], act["y"], act["name"], act["desc"], is_system=False, color=act["color"])

    # 2 Aktor di sebelah KANAN (Sistem Eksternal / Layanan Otomatis)
    right_actors = [
        {"x": 17.0, "y": 7.5, "name": "AI Proctoring Engine", "desc": "(MediaPipe Face & Gaze Mesh)", "color": "#2563eb"},
        {"x": 17.0, "y": 3.0, "name": "Sistem Layanan OTP", "desc": "(Gmail SMTP & WhatsApp Gateway)", "color": "#16a34a"}
    ]
    for act in right_actors:
        draw_actor(ax, act["x"], act["y"], act["name"], act["desc"], is_system=True, color=act["color"])

    # 10 Use Cases di Tengah (Ovals)
    use_cases = [
        {"id": "UC1", "x": 6.5, "y": 9.8, "w": 3.4, "h": 0.8, "text": "UC-01: Autentikasi &\nManajemen Sesi Multi-Role", "fill": "#eff6ff", "stroke": "#3b82f6"},
        {"id": "UC2", "x": 12.5, "y": 9.8, "w": 3.4, "h": 0.8, "text": "UC-02: Verifikasi 2FA OTP\n(Email & WhatsApp)", "fill": "#ecfdf5", "stroke": "#10b981"},
        {"id": "UC3", "x": 6.5, "y": 8.4, "w": 3.4, "h": 0.8, "text": "UC-03: Kelola Bank Soal &\nPaket Jadwal Ujian", "fill": "#eff6ff", "stroke": "#3b82f6"},
        {"id": "UC4", "x": 12.5, "y": 8.4, "w": 3.4, "h": 0.8, "text": "UC-04: Kalibrasi Dual Camera\n(Laptop & HP Samping)", "fill": "#fef3c7", "stroke": "#f59e0b"},
        {"id": "UC5", "x": 6.5, "y": 7.0, "w": 3.4, "h": 0.8, "text": "UC-05: Pelaksanaan Ujian CBT\n& Auto-Save Jawaban", "fill": "#fff7ed", "stroke": "#f97316"},
        {"id": "UC6", "x": 12.5, "y": 7.0, "w": 3.4, "h": 0.8, "text": "UC-06: Deteksi Kecurangan Wajah\n(AI Face Mesh & Gaze)", "fill": "#fef2f2", "stroke": "#ef4444"},
        {"id": "UC7", "x": 6.5, "y": 5.6, "w": 3.4, "h": 0.8, "text": "UC-07: Monitoring Ujian Live\n& Telemetri Pelanggaran", "fill": "#eff6ff", "stroke": "#3b82f6"},
        {"id": "UC8", "x": 12.5, "y": 5.6, "w": 3.4, "h": 0.8, "text": "UC-08: Koreksi Nilai Otomatis &\nEvaluasi Esai AI", "fill": "#f5f3ff", "stroke": "#8b5cf6"},
        {"id": "UC9", "x": 6.5, "y": 4.2, "w": 3.4, "h": 0.8, "text": "UC-09: Chatbot Operasional AI\n(Grounding Live Database)", "fill": "#ecfeff", "stroke": "#06b6d4"},
        {"id": "UC10", "x": 12.5, "y": 4.2, "w": 3.4, "h": 0.8, "text": "UC-10: Audit Log Forensik &\nKonfigurasi Parameter AI", "fill": "#f1f5f9", "stroke": "#64748b"},
        {"id": "UC11", "x": 9.5, "y": 2.2, "w": 3.8, "h": 0.8, "text": "UC-11: Ekspor Rekap Nilai &\nBerita Acara (PDF/Excel)", "fill": "#f8fafc", "stroke": "#475569"}
    ]

    uc_map = {}
    for uc in use_cases:
        ellipse = Ellipse((uc["x"], uc["y"]), uc["w"], uc["h"], 
                          facecolor=uc["fill"], edgecolor=uc["stroke"], linewidth=1.8, zorder=4)
        ax.add_patch(ellipse)
        ax.text(uc["x"], uc["y"], uc["text"], fontsize=8.5, fontweight='bold', 
                ha='center', va='center', color='#0f172a', zorder=5)
        uc_map[uc["id"]] = (uc["x"], uc["y"], uc["w"], uc["h"])

    # Relasi Garis Antara Aktor dan Use Case
    def connect(x1, y1, uc_id, color='#94a3b8', style='-', width=1.3):
        x2, y2, w, h = uc_map[uc_id]
        # Penyesuaian titik potong tepi oval
        dx = x2 - x1
        dy = y2 - y1
        if dx > 0:
            target_x = x2 - (w/2) * 0.95
        else:
            target_x = x2 + (w/2) * 0.95
        target_y = y2
        ax.plot([x1, target_x], [y1, target_y], color=color, linestyle=style, linewidth=width, zorder=3)

    # 1. Super Admin (kiri atas) -> UC1, UC3, UC7, UC9, UC10, UC11
    connect(2.3, 9.5, "UC1", color="#7c3aed")
    connect(2.3, 9.5, "UC3", color="#7c3aed")
    connect(2.3, 9.5, "UC7", color="#7c3aed")
    connect(2.3, 9.5, "UC9", color="#7c3aed")
    connect(2.3, 9.5, "UC10", color="#7c3aed")

    # 2. Administrator (kiri tengah atas) -> UC1, UC3, UC7, UC9, UC11
    connect(2.3, 6.8, "UC1", color="#0284c7")
    connect(2.3, 6.8, "UC3", color="#0284c7")
    connect(2.3, 6.8, "UC7", color="#0284c7")
    connect(2.3, 6.8, "UC9", color="#0284c7")
    connect(2.3, 6.8, "UC11", color="#0284c7")

    # 3. Guru (kiri tengah bawah) -> UC1, UC3, UC7, UC8, UC9, UC11
    connect(2.3, 4.1, "UC1", color="#059669")
    connect(2.3, 4.1, "UC3", color="#059669")
    connect(2.3, 4.1, "UC7", color="#059669")
    connect(2.3, 4.1, "UC8", color="#059669")
    connect(2.3, 4.1, "UC9", color="#059669")
    connect(2.3, 4.1, "UC11", color="#059669")

    # 4. Peserta (Siswa) (kiri bawah) -> UC1, UC2, UC4, UC5
    connect(2.3, 1.4, "UC1", color="#ea580c", width=1.6)
    connect(2.3, 1.4, "UC2", color="#ea580c", width=1.6)
    connect(2.3, 1.4, "UC4", color="#ea580c", width=1.6)
    connect(2.3, 1.4, "UC5", color="#ea580c", width=1.6)

    # 5. AI Proctoring Engine (kanan atas) -> UC4, UC5, UC6, UC7
    connect(16.5, 7.5, "UC4", color="#2563eb", style="--", width=1.6)
    connect(16.5, 7.5, "UC5", color="#2563eb", style="--", width=1.6)
    connect(16.5, 7.5, "UC6", color="#2563eb", style="--", width=1.8)
    connect(16.5, 7.5, "UC7", color="#2563eb", style="--", width=1.6)

    # 6. Sistem Layanan OTP (kanan bawah) -> UC2
    connect(16.5, 3.0, "UC2", color="#16a34a", style="--", width=1.8)

    # Include relationship: UC1 includes UC2
    ax.annotate("", xy=(10.7, 9.8), xytext=(8.3, 9.8),
                arrowprops=dict(arrowstyle="->", color="#64748b", linestyle=":", lw=1.5), zorder=6)
    ax.text(9.5, 10.05, "<<include>>", fontsize=7.5, color="#64748b", ha="center", weight="bold", zorder=7)

    # Include relationship: UC5 includes UC6
    ax.annotate("", xy=(10.7, 7.0), xytext=(8.3, 7.0),
                arrowprops=dict(arrowstyle="->", color="#ef4444", linestyle=":", lw=1.5), zorder=6)
    ax.text(9.5, 7.25, "<<include>>", fontsize=7.5, color="#ef4444", ha="center", weight="bold", zorder=7)

    # Legend / Catatan Diagram
    leg_box = FancyBboxPatch((4.5, 0.6), 10.0, 0.9,
                             boxstyle="round,pad=0.05,rounding_size=0.08",
                             facecolor='#f1f5f9', edgecolor='#94a3b8', linewidth=1.0, zorder=4)
    ax.add_patch(leg_box)
    ax.text(9.5, 1.25, "Keterangan Relasi & Batasan Aktor:", fontsize=8.5, fontweight='bold', ha='center', color='#1e293b', zorder=5)
    ax.text(9.5, 0.95, "• Kiri: 4 Aktor Primer (Pengguna Manusia) | • Kanan: 2 Aktor Sekunder (AI Engine & OTP Gateway)", fontsize=8, ha='center', color='#334155', zorder=5)
    ax.text(9.5, 0.72, "Garis Putus-Putus (---): Integrasi Layanan Sistem Otomatis | Garis Titik-Titik (...>): Relasi <<include>> Use Case", fontsize=7.5, ha='center', color='#64748b', zorder=5)

    plt.tight_layout()
    out1 = os.path.join(OUTPUT_DIR_DOCS, '01_use_case_diagram.png')
    out2 = os.path.join(OUTPUT_DIR_PUBLIC, '01_use_case_diagram.png')
    fig.savefig(out1, dpi=200, bbox_inches='tight')
    fig.savefig(out2, dpi=200, bbox_inches='tight')
    plt.close(fig)
    print(f"Use Case Diagram successfully saved to: {out1} & {out2}")

if __name__ == '__main__':
    generate_use_case()

