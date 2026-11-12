/**
 * Exam Monitoring Engine
 * JavaScript Client-Side Real-Time Polling & Proctor Control Manager
 * 
 * UC-22  Monitoring Live Dashboard
 * UC-22a Beri Teguran / Hentikan Ujian
 * UC-22b Kirim Pesan / Chat
 * UC-22c Reset Sesi / Ujian Susulan
 */

(function () {
    'use strict';

    class ExamMonitoringEngine {
        constructor() {
            this.config = window.EXAM_MONITORING_CONFIG || {};
            this.examId = this.config.examId || 0;
            this.basePrefix = this.config.basePrefix || 'guru';
            this.baseUrl = (this.config.baseUrl || '').replace(/\/$/, '') + '/';
            this.pollInterval = this.config.pollInterval || 4000;
            this.csrfToken = this.config.csrfToken || '_csrf_token';
            this.csrfHash = this.config.csrfHash || '';

            this.participants = [];
            this.summary = {};
            this.activeFilters = {
                status: '',
                risk: '',
                search: ''
            };
            this.viewMode = 'grid'; // 'grid' | 'table'
            this.pollingTimer = null;
            this.timerTicker = null;
            this.isPolling = false;
            this.activeChatSessionId = null;

            this.init();
        }

        init() {
            this.bindElements();
            this.bindEvents();
            this.startPolling();
            this.startTimerTicker();
        }

        bindElements() {
            this.el = {
                gridContainer: document.getElementById('gridParticipantsView'),
                tableContainer: document.getElementById('tableParticipantsView'),
                tableBody: document.getElementById('tableBodyParticipants'),
                searchInput: document.getElementById('searchInput'),
                btnRefresh: document.getElementById('btnRefreshNow'),
                btnViewGrid: document.getElementById('btnViewGrid'),
                btnViewTable: document.getElementById('btnViewTable'),
                lastSyncTime: document.getElementById('lastSyncTime'),
                pollingDot: document.getElementById('pollingStatusDot'),
                pollingText: document.getElementById('pollingStatusText'),
                
                // KPI Elements
                kpiTotal: document.getElementById('kpiTotal'),
                kpiActive: document.getElementById('kpiActive'),
                kpiSubmitted: document.getElementById('kpiSubmitted'),
                kpiCamError: document.getElementById('kpiCamError'),
                kpiCritical: document.getElementById('kpiCritical'),
                kpiAvgScore: document.getElementById('kpiAvgScore'),
                
                // Filter Counters
                countAll: document.getElementById('countAll'),
                countOngoing: document.getElementById('countOngoing'),
                countSubmitted: document.getElementById('countSubmitted'),
                countTerminated: document.getElementById('countTerminated'),
                countCamError: document.getElementById('countCamError'),

                // Modals
                modalDetail: new bootstrap.Modal(document.getElementById('modalStudentDetail')),
                modalWarning: new bootstrap.Modal(document.getElementById('modalSendWarning')),
                modalTerminate: new bootstrap.Modal(document.getElementById('modalTerminate')),
                modalReset: new bootstrap.Modal(document.getElementById('modalResetSession')),
                modalMakeup: new bootstrap.Modal(document.getElementById('modalMakeupExam')),
                chatDrawer: new bootstrap.Offcanvas(document.getElementById('chatDrawer')),

                // Forms
                formWarning: document.getElementById('formSendWarning'),
                formTerminate: document.getElementById('formTerminate'),
                formReset: document.getElementById('formResetSession'),
                formMakeup: document.getElementById('formMakeupExam'),
                formChat: document.getElementById('formSendChat'),
                selectWarningTemplate: document.getElementById('selectWarningTemplate')
            };
        }

        bindEvents() {
            // Refresh manual
            if (this.el.btnRefresh) {
                this.el.btnRefresh.addEventListener('click', () => this.fetchData(true));
            }

            // View toggle
            if (this.el.btnViewGrid) {
                this.el.btnViewGrid.addEventListener('click', () => this.setViewMode('grid'));
            }
            if (this.el.btnViewTable) {
                this.el.btnViewTable.addEventListener('click', () => this.setViewMode('table'));
            }

            // Status filter buttons
            document.querySelectorAll('.filter-status').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    document.querySelectorAll('.filter-status').forEach(b => {
                        b.classList.remove('btn-primary', 'active');
                        b.classList.add('btn-outline-secondary');
                    });
                    const target = e.currentTarget;
                    target.classList.remove('btn-outline-secondary');
                    target.classList.add('btn-primary', 'active');

                    this.activeFilters.status = target.getAttribute('data-status') || '';
                    this.renderParticipants();
                });
            });

            // Risk filter dropdown
            document.querySelectorAll('.filter-risk').forEach(item => {
                item.addEventListener('click', (e) => {
                    document.querySelectorAll('.filter-risk').forEach(i => i.classList.remove('active'));
                    const target = e.currentTarget;
                    target.classList.add('active');

                    const risk = target.getAttribute('data-risk') || '';
                    this.activeFilters.risk = risk;
                    const label = document.getElementById('riskFilterLabel');
                    if (label) {
                        label.textContent = risk ? target.textContent.trim() : 'Semua Risiko';
                    }
                    this.renderParticipants();
                });
            });

            // Search input filter
            if (this.el.searchInput) {
                this.el.searchInput.addEventListener('input', (e) => {
                    this.activeFilters.search = (e.target.value || '').trim().toLowerCase();
                    this.renderParticipants();
                });
            }

            // Warning Template Auto-fill
            if (this.el.selectWarningTemplate) {
                this.el.selectWarningTemplate.addEventListener('change', (e) => {
                    const msgInput = document.getElementById('warningMessage');
                    if (msgInput && e.target.value) {
                        msgInput.value = e.target.value;
                    }
                });
            }

            // Form Submit: Warning (UC-22a)
            if (this.el.formWarning) {
                this.el.formWarning.addEventListener('submit', (e) => this.handleWarningSubmit(e));
            }

            // Form Submit: Terminate (UC-22a)
            if (this.el.formTerminate) {
                this.el.formTerminate.addEventListener('submit', (e) => this.handleTerminateSubmit(e));
            }

            // Form Submit: Reset Session (UC-22c)
            if (this.el.formReset) {
                this.el.formReset.addEventListener('submit', (e) => this.handleResetSubmit(e));
            }

            // Form Submit: Makeup Exam (UC-22c)
            if (this.el.formMakeup) {
                this.el.formMakeup.addEventListener('submit', (e) => this.handleMakeupSubmit(e));
            }

            // Form Submit: Send Chat (UC-22b)
            if (this.el.formChat) {
                this.el.formChat.addEventListener('submit', (e) => this.handleChatSubmit(e));
            }
        }

        setViewMode(mode) {
            this.viewMode = mode;
            if (mode === 'grid') {
                this.el.gridContainer.classList.remove('d-none');
                this.el.tableContainer.classList.add('d-none');
                this.el.btnViewGrid.classList.add('active');
                this.el.btnViewTable.classList.remove('active');
            } else {
                this.el.gridContainer.classList.add('d-none');
                this.el.tableContainer.classList.remove('d-none');
                this.el.btnViewTable.classList.add('active');
                this.el.btnViewGrid.classList.remove('active');
            }
            this.renderParticipants();
        }

        startPolling() {
            this.fetchData();
            this.pollingTimer = setInterval(() => {
                if (!document.hidden) {
                    this.fetchData();
                }
            }, this.pollInterval);
        }

        startTimerTicker() {
            this.timerTicker = setInterval(() => {
                // Tick countdown timer for all active students
                this.participants.forEach(p => {
                    if (p.session_status === 'ongoing' && p.remaining_seconds > 0) {
                        p.remaining_seconds -= 1;
                    }
                });
                this.updateLiveTimersOnDom();
            }, 1000);
        }

        async fetchData(manual = false) {
            if (this.isPolling) return;
            this.isPolling = true;

            if (manual && this.el.btnRefresh) {
                this.el.btnRefresh.classList.add('disabled');
                this.el.btnRefresh.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Memuat...';
            }

            try {
                const url = `${this.baseUrl}${this.basePrefix}/exams/monitoring/${this.examId}/poll`;
                const res = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);

                const data = await res.json();
                if (data.success) {
                    this.participants = data.participants || [];
                    this.summary = data.summary || {};
                    this.updateSummaryDom();
                    this.renderParticipants();
                    this.updateLastSyncTime(data.server_time);
                }
            } catch (err) {
                console.error('Monitoring Poll Error:', err);
                if (this.el.pollingDot) {
                    this.el.pollingDot.className = 'badge bg-warning rounded-circle p-1';
                }
            } finally {
                this.isPolling = false;
                if (manual && this.el.btnRefresh) {
                    this.el.btnRefresh.classList.remove('disabled');
                    this.el.btnRefresh.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i> Refresh';
                }
            }
        }

        updateLastSyncTime(serverTime) {
            if (this.el.lastSyncTime) {
                const now = new Date();
                const timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                this.el.lastSyncTime.textContent = timeStr;
            }
            if (this.el.pollingDot) {
                this.el.pollingDot.className = 'badge bg-success rounded-circle p-1';
            }
        }

        updateSummaryDom() {
            const s = this.summary;
            if (this.el.kpiTotal) this.el.kpiTotal.textContent = s.total_registered || 0;
            if (this.el.kpiActive) this.el.kpiActive.textContent = s.active_count || 0;
            if (this.el.kpiSubmitted) this.el.kpiSubmitted.textContent = s.submitted_count || 0;
            if (this.el.kpiCamError) this.el.kpiCamError.textContent = s.cam_error_count || 0;
            if (this.el.kpiCritical) this.el.kpiCritical.textContent = s.critical_risk_count || 0;
            if (this.el.kpiAvgScore) this.el.kpiAvgScore.textContent = `${parseFloat(s.avg_integrity_score || 100).toFixed(1)}%`;

            if (this.el.countAll) this.el.countAll.textContent = this.participants.length;
            if (this.el.countOngoing) this.el.countOngoing.textContent = s.active_count || 0;
            if (this.el.countSubmitted) this.el.countSubmitted.textContent = s.submitted_count || 0;
            if (this.el.countTerminated) this.el.countTerminated.textContent = s.terminated_count || 0;
            if (this.el.countCamError) this.el.countCamError.textContent = s.cam_error_count || 0;
        }

        getFilteredParticipants() {
            return this.participants.filter(p => {
                // Status Filter
                if (this.activeFilters.status) {
                    if (this.activeFilters.status === 'cam_error') {
                        const hasCamError = p.cam1_status === 'disconnected' || p.cam1_status === 'error' ||
                                           p.cam2_status === 'disconnected' || p.cam2_status === 'error';
                        if (!hasCamError) return false;
                    } else if (p.session_status !== this.activeFilters.status) {
                        return false;
                    }
                }

                // Risk Filter
                if (this.activeFilters.risk) {
                    if (p.risk_tier !== this.activeFilters.risk) return false;
                }

                // Search Filter
                if (this.activeFilters.search) {
                    const term = this.activeFilters.search;
                    const name = (p.student_name || '').toLowerCase();
                    const nisn = (p.student_nisn || '').toLowerCase();
                    const cls = (p.class_name || '').toLowerCase();
                    if (!name.includes(term) && !nisn.includes(term) && !cls.includes(term)) {
                        return false;
                    }
                }

                return true;
            });
        }

        renderParticipants() {
            const filtered = this.getFilteredParticipants();

            if (this.viewMode === 'grid') {
                this.renderGridView(filtered);
            } else {
                this.renderTableView(filtered);
            }
        }

        renderGridView(list) {
            if (!this.el.gridContainer) return;

            if (list.length === 0) {
                this.el.gridContainer.innerHTML = `
                    <div class="col-12 text-center py-5 text-muted bg-white rounded-4 shadow-sm">
                        <i class="bi bi-person-x fs-1 text-secondary d-block mb-2"></i>
                        <h6 class="fw-bold mb-1">Tidak Ada Peserta yang Sesuai Filter</h6>
                        <p class="small text-muted mb-0">Coba ubah filter status, level risiko, atau kata kunci pencarian.</p>
                    </div>
                `;
                return;
            }

            let html = '';
            list.forEach(p => {
                const isOnline = p.is_online;
                const statusBadge = this.getStatusBadge(p.session_status);
                const riskTierBadge = this.getRiskBadge(p.risk_score, p.risk_tier);
                const camPills = this.getCamStatusPills(p.cam1_status, p.cam2_status);
                const progressPercent = p.total_questions > 0 ? Math.round((p.answered_count / p.total_questions) * 100) : 0;
                const timeFormatted = this.formatSeconds(p.remaining_seconds);
                const initials = this.getInitials(p.student_name);

                const cardBorder = p.risk_tier === 'critical' ? 'border-danger border-2' : 
                                  (p.risk_tier === 'high' ? 'border-warning border-2' : 'border-0');

                html += `
                <div class="col-12 col-md-6 col-xl-4 col-xxl-3" id="card_student_${p.student_id}">
                    <div class="card ${cardBorder} shadow-sm rounded-4 h-100 bg-white transition hover-elevate position-relative">
                        <div class="card-body p-3.5 d-flex flex-column">
                            <!-- Top Row: Student Info & Actions Menu -->
                            <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                                <div class="d-flex align-items-center gap-2.5">
                                    <div class="position-relative">
                                        <div class="avatar-circle bg-primary-subtle text-primary fw-bold fs-6 rounded-circle d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                            ${initials}
                                        </div>
                                        <span class="position-absolute bottom-0 end-0 p-1 bg-${isOnline ? 'success' : 'secondary'} border border-white rounded-circle" title="${isOnline ? 'Online (Aktif)' : 'Offline (Tidak Terhubung)'}" style="width: 12px; height: 12px;"></span>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0 text-truncate" style="max-width: 160px;" title="${this.escapeHtml(p.student_name)}">
                                            ${this.escapeHtml(p.student_name)}
                                        </h6>
                                        <small class="text-muted d-block fs-xs">
                                            ${this.escapeHtml(p.class_name)} &bull; ${this.escapeHtml(p.student_nisn || 'NISN -')}
                                        </small>
                                    </div>
                                </div>

                                <div class="dropdown">
                                    <button class="btn btn-light btn-sm rounded-circle p-1 shadow-none" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Menu Tindakan">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 fs-xs">
                                        <li><a class="dropdown-item py-1.5" href="javascript:void(0)" onclick="window.monitoringApp.openStudentDetail(${p.student_id})"><i class="bi bi-eye text-primary me-2"></i> Detail & Bukti AI</a></li>
                                        <li><a class="dropdown-item py-1.5" href="javascript:void(0)" onclick="window.monitoringApp.openSendWarning(${p.session_id}, '${this.escapeJs(p.student_name)}')"><i class="bi bi-exclamation-triangle text-warning me-2"></i> Beri Teguran</a></li>
                                        <li><a class="dropdown-item py-1.5" href="javascript:void(0)" onclick="window.monitoringApp.openChatDrawer(${p.session_id}, ${p.student_id}, '${this.escapeJs(p.student_name)}')"><i class="bi bi-chat-dots text-info me-2"></i> Kirim Chat</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item py-1.5" href="javascript:void(0)" onclick="window.monitoringApp.openResetSession(${p.session_id}, '${this.escapeJs(p.student_name)}')"><i class="bi bi-arrow-clockwise text-primary me-2"></i> Soft Reset Sesi</a></li>
                                        <li><a class="dropdown-item py-1.5" href="javascript:void(0)" onclick="window.monitoringApp.openMakeupExam(${p.student_id}, '${this.escapeJs(p.student_name)}')"><i class="bi bi-calendar2-check text-success me-2"></i> Izin Ujian Susulan</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item py-1.5 text-danger" href="javascript:void(0)" onclick="window.monitoringApp.openTerminateExam(${p.session_id}, '${this.escapeJs(p.student_name)}')"><i class="bi bi-slash-circle me-2"></i> Hentikan Ujian</a></li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Session Status & Cam Statuses -->
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-2.5 pb-2 border-bottom border-light">
                                <div>${statusBadge}</div>
                                <div class="d-flex gap-1">${camPills}</div>
                            </div>

                            <!-- Risk Score & Violations Count -->
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-muted fs-xs">Skor Risiko AI:</span>
                                <div>${riskTierBadge}</div>
                            </div>

                            <div class="d-flex align-items-center justify-content-between mb-3 fs-xs text-muted">
                                <span>Total Pelanggaran: <strong class="${p.violation_count > 0 ? 'text-danger' : 'text-dark'}">${p.violation_count}</strong></span>
                                <span>Peringatan Aktif: <strong class="${p.unacknowledged_warnings > 0 ? 'text-warning' : 'text-dark'}">${p.unacknowledged_warnings}</strong></span>
                            </div>

                            <!-- Progress & Timer Section -->
                            <div class="mt-auto pt-2 border-top border-light">
                                <div class="d-flex align-items-center justify-content-between mb-1 fs-xs">
                                    <span class="text-muted">Dijawab: <strong>${p.answered_count}/${p.total_questions}</strong> (${progressPercent}%)</span>
                                    <span class="fw-semibold text-dark"><i class="bi bi-stopwatch text-primary me-1"></i><span class="student-timer" data-session-id="${p.session_id}">${timeFormatted}</span></span>
                                </div>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: ${progressPercent}%;" aria-valuenow="${progressPercent}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                `;
            });

            this.el.gridContainer.innerHTML = html;
        }

        renderTableView(list) {
            if (!this.el.tableBody) return;

            if (list.length === 0) {
                this.el.tableBody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-person-x fs-1 text-secondary d-block mb-2"></i>
                            <h6 class="fw-bold mb-1">Tidak Ada Peserta yang Sesuai Filter</h6>
                        </td>
                    </tr>
                `;
                return;
            }

            let html = '';
            list.forEach(p => {
                const isOnline = p.is_online;
                const statusBadge = this.getStatusBadge(p.session_status);
                const riskTierBadge = this.getRiskBadge(p.risk_score, p.risk_tier);
                const camPills = this.getCamStatusPills(p.cam1_status, p.cam2_status);
                const progressPercent = p.total_questions > 0 ? Math.round((p.answered_count / p.total_questions) * 100) : 0;
                const timeFormatted = this.formatSeconds(p.remaining_seconds);
                const initials = this.getInitials(p.student_name);

                html += `
                <tr id="row_student_${p.student_id}">
                    <td class="ps-4">
                        <div class="d-flex align-items-center gap-2.5">
                            <div class="position-relative">
                                <div class="avatar-circle bg-primary-subtle text-primary fw-bold fs-xs rounded-circle d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                                    ${initials}
                                </div>
                                <span class="position-absolute bottom-0 end-0 p-1 bg-${isOnline ? 'success' : 'secondary'} border border-white rounded-circle" title="${isOnline ? 'Online' : 'Offline'}" style="width: 10px; height: 10px;"></span>
                            </div>
                            <div>
                                <div class="fw-bold text-dark fs-sm">${this.escapeHtml(p.student_name)}</div>
                                <small class="text-muted fs-xs">${this.escapeHtml(p.student_nisn || 'NISN -')}</small>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge bg-light text-dark border">${this.escapeHtml(p.class_name)}</span></td>
                    <td>${statusBadge}</td>
                    <td><div class="d-flex gap-1">${camPills}</div></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            ${riskTierBadge}
                            <small class="text-muted fs-xs">${p.violation_count} plg</small>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex flex-column" style="min-width: 120px;">
                            <div class="d-flex justify-content-between fs-xs mb-1">
                                <span>${p.answered_count}/${p.total_questions}</span>
                                <span class="fw-semibold text-dark student-timer" data-session-id="${p.session_id}">${timeFormatted}</span>
                            </div>
                            <div class="progress" style="height: 4px;">
                                <div class="progress-bar bg-primary" style="width: ${progressPercent}%;"></div>
                            </div>
                        </div>
                    </td>
                    <td class="text-end pe-4">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary" onclick="window.monitoringApp.openStudentDetail(${p.student_id})" title="Detail & Bukti AI">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button type="button" class="btn btn-outline-warning" onclick="window.monitoringApp.openSendWarning(${p.session_id}, '${this.escapeJs(p.student_name)}')" title="Beri Teguran">
                                <i class="bi bi-exclamation-triangle"></i>
                            </button>
                            <button type="button" class="btn btn-outline-info" onclick="window.monitoringApp.openChatDrawer(${p.session_id}, ${p.student_id}, '${this.escapeJs(p.student_name)}')" title="Chat Siswa">
                                <i class="bi bi-chat-dots"></i>
                            </button>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false"></button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 fs-xs">
                                    <li><a class="dropdown-item py-1.5" href="javascript:void(0)" onclick="window.monitoringApp.openResetSession(${p.session_id}, '${this.escapeJs(p.student_name)}')"><i class="bi bi-arrow-clockwise text-primary me-2"></i> Soft Reset Sesi</a></li>
                                    <li><a class="dropdown-item py-1.5" href="javascript:void(0)" onclick="window.monitoringApp.openMakeupExam(${p.student_id}, '${this.escapeJs(p.student_name)}')"><i class="bi bi-calendar2-check text-success me-2"></i> Izin Ujian Susulan</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item py-1.5 text-danger" href="javascript:void(0)" onclick="window.monitoringApp.openTerminateExam(${p.session_id}, '${this.escapeJs(p.student_name)}')"><i class="bi bi-slash-circle me-2"></i> Hentikan Ujian</a></li>
                                </ul>
                            </div>
                        </div>
                    </td>
                </tr>
                `;
            });

            this.el.tableBody.innerHTML = html;
        }

        updateLiveTimersOnDom() {
            document.querySelectorAll('.student-timer').forEach(el => {
                const sId = parseInt(el.getAttribute('data-session-id'), 10);
                const p = this.participants.find(item => item.session_id === sId);
                if (p) {
                    el.textContent = this.formatSeconds(p.remaining_seconds);
                }
            });
        }

        getStatusBadge(status) {
            switch (status) {
                case 'ongoing':
                    return '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5 rounded-pill fs-xs"><i class="bi bi-play-fill me-0.5"></i> Mengerjakan</span>';
                case 'submitted':
                    return '<span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-0.5 rounded-pill fs-xs"><i class="bi bi-check2 me-0.5"></i> Selesai</span>';
                case 'terminated':
                    return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-0.5 rounded-pill fs-xs"><i class="bi bi-slash-circle me-0.5"></i> Dihentikan</span>';
                case 'not_started':
                default:
                    return '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-0.5 rounded-pill fs-xs"><i class="bi bi-clock me-0.5"></i> Belum Mulai</span>';
            }
        }

        getRiskBadge(score, tier) {
            const num = Math.round(score || 0);
            switch (tier) {
                case 'critical':
                    return `<span class="badge bg-danger text-white px-2 py-0.5 rounded-pill fs-xs fw-bold"><i class="bi bi-exclamation-octagon-fill me-0.5"></i> ${num} (Kritis)</span>`;
                case 'high':
                    return `<span class="badge bg-warning text-dark px-2 py-0.5 rounded-pill fs-xs fw-bold"><i class="bi bi-exclamation-triangle-fill me-0.5"></i> ${num} (Tinggi)</span>`;
                case 'medium':
                    return `<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-0.5 rounded-pill fs-xs"><i class="bi bi-exclamation-circle me-0.5"></i> ${num} (Sedang)</span>`;
                case 'low':
                default:
                    return `<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0.5 rounded-pill fs-xs"><i class="bi bi-check-circle me-0.5"></i> ${num} (Rendah)</span>`;
            }
        }

        getCamStatusPills(cam1, cam2) {
            const c1Ok = cam1 === 'connected';
            const c2Ok = cam2 === 'connected';

            const c1Badge = c1Ok 
                ? '<span class="badge bg-light text-success border fs-xs py-0.5 px-1.5" title="Kamera Utama: Terhubung"><i class="bi bi-camera-video-fill"></i> Cam 1</span>'
                : '<span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-xs py-0.5 px-1.5" title="Kamera Utama: Putus"><i class="bi bi-camera-video-off-fill"></i> Cam 1</span>';

            const c2Badge = c2Ok
                ? '<span class="badge bg-light text-success border fs-xs py-0.5 px-1.5" title="Kamera Sekunder: Terhubung"><i class="bi bi-camera-video-fill"></i> Cam 2</span>'
                : '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle fs-xs py-0.5 px-1.5" title="Kamera Sekunder: Putus"><i class="bi bi-camera-video-off-fill"></i> Cam 2</span>';

            return `${c1Badge} ${c2Badge}`;
        }

        formatSeconds(seconds) {
            if (!seconds || seconds <= 0) return '00:00';
            const m = Math.floor(seconds / 60);
            const s = seconds % 60;
            const mm = m < 10 ? '0' + m : m;
            const ss = s < 10 ? '0' + s : s;
            return `${mm}:${ss}`;
        }

        getInitials(name) {
            if (!name) return 'S';
            const parts = name.trim().split(' ');
            if (parts.length >= 2) {
                return (parts[0][0] + parts[1][0]).toUpperCase();
            }
            return name.substring(0, 2).toUpperCase();
        }

        escapeHtml(str) {
            if (!str) return '';
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        escapeJs(str) {
            if (!str) return '';
            return str.replace(/'/g, "\\'").replace(/"/g, '&quot;');
        }

        // =========================================================================
        // ACTION MODAL TRIGGERS & HANDLERS
        // =========================================================================

        async openStudentDetail(studentId) {
            const modalBody = document.getElementById('modalDetailBody');
            const titleEl = document.getElementById('modalDetailStudentName');
            const subTitleEl = document.getElementById('modalDetailSubTitle');

            if (modalBody) {
                modalBody.innerHTML = `
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="text-muted mt-2 small">Memuat telemetri dan log proctoring...</p>
                    </div>
                `;
            }

            this.el.modalDetail.show();

            try {
                const url = `${this.baseUrl}${this.basePrefix}/exams/monitoring/${this.examId}/student/${studentId}`;
                const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();

                if (!data.success || !data.data) {
                    throw new Error(data.error || 'Gagal memuat detail.');
                }

                const d = data.data;
                const student = d.student || {};
                const session = d.session || {};
                const score = d.risk_score || {};
                const violations = d.violations || [];
                const facePhotos = d.face_verifications || [];

                if (titleEl) titleEl.textContent = student.name || 'Detail Peserta';
                if (subTitleEl) subTitleEl.textContent = `${student.class_name || 'Kelas'} &bull; NISN: ${student.nip_nisn || '-'}`;

                let photosHtml = '';
                if (facePhotos.length > 0) {
                    facePhotos.forEach(fp => {
                        photosHtml += `
                            <div class="col-4 col-md-3 text-center">
                                <img src="${fp.photo_path}" class="img-thumbnail rounded-3 shadow-sm mb-1" style="height: 100px; width: 100%; object-fit: cover;" alt="Verifikasi Wajah">
                                <small class="text-muted fs-xs d-block">${fp.created_at ? fp.created_at.substring(11, 19) : ''}</small>
                            </div>
                        `;
                    });
                } else {
                    photosHtml = '<div class="col-12 text-muted small">Belum ada foto verifikasi awal.</div>';
                }

                let violationsHtml = '';
                if (violations.length > 0) {
                    violations.forEach(v => {
                        const snap = v.snapshot_path 
                            ? `<a href="${v.snapshot_path}" target="_blank"><img src="${v.snapshot_path}" class="img-thumbnail rounded-2 me-2" style="width: 48px; height: 36px; object-fit: cover;" alt="Snapshot"></a>`
                            : '<span class="badge bg-light text-secondary border me-2">No Snap</span>';

                        violationsHtml += `
                            <div class="d-flex align-items-center justify-content-between p-2.5 mb-2 bg-white rounded-3 border">
                                <div class="d-flex align-items-center">
                                    ${snap}
                                    <div>
                                        <div class="fw-semibold text-dark fs-xs">${this.escapeHtml(v.violation_name || v.violation_code)}</div>
                                        <small class="text-muted fs-xs">${v.created_at} &bull; Kamera: <strong>${v.camera_device_id || 'Depan'}</strong></small>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-xs">Conf: ${Math.round(v.confidence_score || 0)}%</span>
                                </div>
                            </div>
                        `;
                    });
                } else {
                    violationsHtml = '<div class="text-center py-4 text-muted small"><i class="bi bi-shield-check fs-3 text-success d-block mb-1"></i>Tidak ada riwayat pelanggaran tercatat.</div>';
                }

                if (modalBody) {
                    modalBody.innerHTML = `
                        <div class="row g-3">
                            <!-- Left: Metric Stats & Face Photos -->
                            <div class="col-12 col-lg-5">
                                <div class="card border-0 bg-light rounded-4 p-3 mb-3">
                                    <h6 class="fw-bold mb-3 text-dark"><i class="bi bi-speedometer2 text-primary me-1"></i> Skor Integritas & Risiko</h6>
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="small text-muted">Skor Integritas:</span>
                                        <span class="fw-bold text-success fs-5">${score.integrity_score || 100}%</span>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="small text-muted">Skor Risiko AI:</span>
                                        <span class="fw-bold text-danger fs-5">${score.risk_score || 0} (${score.tier || 'low'})</span>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="small text-muted">Status Koneksi Cam 1:</span>
                                        <span class="badge bg-${session.cam1_status === 'connected' ? 'success' : 'danger'}">${session.cam1_status || 'offline'}</span>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <span class="small text-muted">Status Koneksi Cam 2:</span>
                                        <span class="badge bg-${session.cam2_status === 'connected' ? 'success' : 'warning'}">${session.cam2_status || 'offline'}</span>
                                    </div>
                                </div>

                                <div class="card border-0 bg-light rounded-4 p-3">
                                    <h6 class="fw-bold mb-2 text-dark"><i class="bi bi-camera text-info me-1"></i> Foto Verifikasi Wajah</h6>
                                    <div class="row g-2">${photosHtml}</div>
                                </div>
                            </div>

                            <!-- Right: Violations Log -->
                            <div class="col-12 col-lg-7">
                                <div class="card border-0 bg-light rounded-4 p-3 h-100">
                                    <h6 class="fw-bold mb-3 text-dark d-flex align-items-center justify-content-between">
                                        <span><i class="bi bi-exclamation-triangle text-warning me-1"></i> Log Pelanggaran Real-Time</span>
                                        <span class="badge bg-secondary">${violations.length} Kejadian</span>
                                    </h6>
                                    <div class="overflow-auto" style="max-height: 380px;">
                                        ${violationsHtml}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                }
            } catch (err) {
                if (modalBody) {
                    modalBody.innerHTML = `
                        <div class="alert alert-danger border-0 rounded-3">
                            <i class="bi bi-exclamation-octagon-fill me-1"></i> Gagal memuat data detail: ${err.message}
                        </div>
                    `;
                }
            }
        }

        openSendWarning(sessionId, studentName) {
            const sidInput = document.getElementById('warningSessionId');
            const nameEl = document.getElementById('warningStudentName');
            const msgInput = document.getElementById('warningMessage');
            if (sidInput) sidInput.value = sessionId || 0;
            if (nameEl) nameEl.textContent = studentName || 'Siswa';
            if (msgInput) msgInput.value = '';
            if (this.el.selectWarningTemplate) this.el.selectWarningTemplate.value = '';
            this.el.modalWarning.show();
        }

        openTerminateExam(sessionId, studentName) {
            const sidInput = document.getElementById('terminateSessionId');
            const nameEl = document.getElementById('terminateStudentName');
            const reasonInput = document.getElementById('terminateReason');
            if (sidInput) sidInput.value = sessionId || 0;
            if (nameEl) nameEl.textContent = studentName || 'Siswa';
            if (reasonInput) reasonInput.value = '';
            this.el.modalTerminate.show();
        }

        openResetSession(sessionId, studentName) {
            const sidInput = document.getElementById('resetSessionId');
            const nameEl = document.getElementById('resetStudentName');
            if (sidInput) sidInput.value = sessionId || 0;
            if (nameEl) nameEl.textContent = studentName || 'Siswa';
            this.el.modalReset.show();
        }

        openMakeupExam(studentId, studentName) {
            const sidInput = document.getElementById('makeupStudentId');
            const nameEl = document.getElementById('makeupStudentName');
            const notesInput = document.getElementById('makeupNotes');
            if (sidInput) sidInput.value = studentId || 0;
            if (nameEl) nameEl.textContent = studentName || 'Siswa';
            if (notesInput) notesInput.value = '';
            this.el.modalMakeup.show();
        }

        async openChatDrawer(sessionId, studentId, studentName) {
            this.activeChatSessionId = sessionId;
            const sidInput = document.getElementById('chatSessionId');
            const recInput = document.getElementById('chatReceiverId');
            const titleEl = document.getElementById('chatDrawerStudentName');

            if (sidInput) sidInput.value = sessionId || 0;
            if (recInput) recInput.value = studentId || 0;
            if (titleEl) titleEl.textContent = `Chat: ${studentName || 'Peserta'}`;

            this.el.chatDrawer.show();
            await this.loadChatThread(sessionId);
        }

        async loadChatThread(sessionId) {
            const container = document.getElementById('chatHistoryContainer');
            if (!container) return;

            try {
                const url = `${this.baseUrl}${this.basePrefix}/exams/monitoring/chat-thread/${sessionId}`;
                const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();

                if (data.success && data.thread) {
                    let html = '';
                    if (data.thread.length === 0) {
                        html = `
                            <div class="text-center py-5 text-muted small">
                                <i class="bi bi-chat-heart fs-2 d-block mb-1 text-secondary"></i>
                                Belum ada pesan. Mulai percakapan resmi pengawas dengan siswa.
                            </div>
                        `;
                    } else {
                        data.thread.forEach(msg => {
                            const isMe = msg.sender_id != document.getElementById('chatReceiverId').value;
                            const bg = isMe ? 'bg-primary text-white ms-auto' : 'bg-white text-dark me-auto border';
                            html += `
                                <div class="d-flex flex-column mb-2" style="max-width: 80%; ${isMe ? 'margin-left: auto;' : ''}">
                                    <div class="p-2.5 rounded-4 shadow-xs fs-xs ${bg}">
                                        ${this.escapeHtml(msg.message)}
                                    </div>
                                    <small class="text-muted fs-xxs mt-0.5 ${isMe ? 'text-end' : ''}">
                                        ${msg.created_at ? msg.created_at.substring(11, 16) : ''} &bull; ${isMe ? 'Pengawas' : 'Siswa'}
                                    </small>
                                </div>
                            `;
                        });
                    }
                    container.innerHTML = html;
                    container.scrollTop = container.scrollHeight;
                }
            } catch (err) {
                console.error('Failed to load chat thread:', err);
            }
        }

        // =========================================================================
        // FORM SUBMISSION AJAX HANDLERS
        // =========================================================================

        async handleWarningSubmit(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSubmitWarning');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mengirim...';

            try {
                const formData = new FormData(this.el.formWarning);
                formData.append(this.csrfToken, this.csrfHash);

                const url = `${this.baseUrl}${this.basePrefix}/exams/monitoring/send-warning`;
                const res = await fetch(url, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();

                if (data.success) {
                    this.showToast('success', data.message || 'Teguran berhasil dikirim.');
                    this.el.modalWarning.hide();
                    this.fetchData();
                } else {
                    this.showToast('danger', data.error || 'Gagal mengirim teguran.');
                }
            } catch (err) {
                this.showToast('danger', 'Terjadi kesalahan sistem: ' + err.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-send-fill me-1"></i> Kirim Peringatan';
            }
        }

        async handleTerminateSubmit(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSubmitTerminate');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Memproses...';

            try {
                const formData = new FormData(this.el.formTerminate);
                formData.append(this.csrfToken, this.csrfHash);

                const url = `${this.baseUrl}${this.basePrefix}/exams/monitoring/terminate`;
                const res = await fetch(url, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();

                if (data.success) {
                    this.showToast('success', data.message || 'Ujian siswa telah dihentikan.');
                    this.el.modalTerminate.hide();
                    this.fetchData();
                } else {
                    this.showToast('danger', data.error || 'Gagal menghentikan ujian.');
                }
            } catch (err) {
                this.showToast('danger', 'Terjadi kesalahan sistem: ' + err.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-x-octagon-fill me-1"></i> Konfirmasi Hentikan Ujian';
            }
        }

        async handleResetSubmit(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSubmitReset');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mereset...';

            try {
                const formData = new FormData(this.el.formReset);
                formData.append(this.csrfToken, this.csrfHash);

                const url = `${this.baseUrl}${this.basePrefix}/exams/monitoring/reset-session`;
                const res = await fetch(url, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();

                if (data.success) {
                    this.showToast('success', data.message || 'Sesi ujian berhasil direset.');
                    this.el.modalReset.hide();
                    this.fetchData();
                } else {
                    this.showToast('danger', data.error || 'Gagal mereset sesi.');
                }
            } catch (err) {
                this.showToast('danger', 'Terjadi kesalahan sistem: ' + err.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> Lakukan Reset Sesi';
            }
        }

        async handleMakeupSubmit(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSubmitMakeup');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...';

            try {
                const formData = new FormData(this.el.formMakeup);
                formData.append(this.csrfToken, this.csrfHash);

                const url = `${this.baseUrl}${this.basePrefix}/exams/monitoring/makeup-exam`;
                const res = await fetch(url, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();

                if (data.success) {
                    this.showToast('success', data.message || 'Otorisasi susulan diberikan.');
                    this.el.modalMakeup.hide();
                    this.fetchData();
                } else {
                    this.showToast('danger', data.error || 'Gagal memberikan izin susulan.');
                }
            } catch (err) {
                this.showToast('danger', 'Terjadi kesalahan sistem: ' + err.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Berikan Izin Susulan';
            }
        }

        async handleChatSubmit(e) {
            e.preventDefault();
            const input = document.getElementById('chatInputMessage');
            const btn = document.getElementById('btnSendChat');
            const msg = (input ? input.value : '').trim();
            if (!msg || !this.activeChatSessionId) return;

            btn.disabled = true;

            try {
                const formData = new FormData();
                formData.append('session_id', this.activeChatSessionId);
                formData.append('receiver_id', document.getElementById('chatReceiverId').value);
                formData.append('message', msg);
                formData.append('channel', 'proctor_to_student');
                formData.append(this.csrfToken, this.csrfHash);

                const url = `${this.baseUrl}${this.basePrefix}/exams/monitoring/send-chat`;
                const res = await fetch(url, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();

                if (data.success) {
                    input.value = '';
                    await this.loadChatThread(this.activeChatSessionId);
                } else {
                    this.showToast('danger', data.error || 'Gagal mengirim pesan.');
                }
            } catch (err) {
                this.showToast('danger', 'Gagal mengirim chat: ' + err.message);
            } finally {
                btn.disabled = false;
            }
        }

        showToast(type, message) {
            if (window.App && typeof window.App.showToast === 'function') {
                window.App.showToast(message, type);
            } else {
                alert(message);
            }
        }
    }

    // Attach instance to window
    document.addEventListener('DOMContentLoaded', () => {
        window.monitoringApp = new ExamMonitoringEngine();
    });
})();
