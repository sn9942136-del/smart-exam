/**
 * Exam Timer Module
 * Mengelola countdown timer presisi tinggi berbasis Server Clock Authority.
 */
class ExamTimer {
    constructor(config) {
        this.sessionToken = config.sessionToken;
        this.remainingSeconds = parseInt(config.remainingSeconds, 10) || 0;
        const baseUrl = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';
        const cleanBaseUrl = baseUrl.endsWith('/') ? baseUrl : baseUrl + '/';
        this.syncUrl = config.syncUrl || `${cleanBaseUrl}siswa/exam/timer/sync/${this.sessionToken}`;
        this.onTick = config.onTick || function() {};
        this.onExpired = config.onExpired || function() {};
        this.onWarning = config.onWarning || function() {};
        
        this.timerInterval = null;
        this.syncInterval = null;
        this.isExpiredTriggered = false;
    }

    start() {
        this.render();

        // 1. Local 1-second countdown tick
        this.timerInterval = setInterval(() => {
            if (this.remainingSeconds > 0) {
                this.remainingSeconds--;
                this.render();

                // Warning triggers (< 10 mins and < 5 mins)
                if (this.remainingSeconds === 600 || this.remainingSeconds === 300) {
                    this.onWarning(this.remainingSeconds);
                }
            } else {
                this.stop();
                if (!this.isExpiredTriggered) {
                    this.isExpiredTriggered = true;
                    this.onExpired();
                }
            }
        }, 1000);

        // 2. Periodic background sync with server clock every 30 seconds
        this.syncInterval = setInterval(() => {
            this.syncWithServer();
        }, 30000);
    }

    render() {
        const formatted = this.formatTime(this.remainingSeconds);
        this.onTick(formatted, this.remainingSeconds);
    }

    formatTime(totalSeconds) {
        if (totalSeconds <= 0) return '00:00:00';
        const hrs = Math.floor(totalSeconds / 3600);
        const mins = Math.floor((totalSeconds % 3600) / 60);
        const secs = totalSeconds % 60;

        const pad = (n) => (n < 10 ? '0' + n : n);
        return `${pad(hrs)}:${pad(mins)}:${pad(secs)}`;
    }

    async syncWithServer() {
        try {
            const res = await fetch(this.syncUrl, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (res.ok) {
                const data = await res.json();
                if (data.success && typeof data.remaining_seconds === 'number') {
                    this.remainingSeconds = data.remaining_seconds;
                    if (data.is_expired || this.remainingSeconds <= 0) {
                        this.stop();
                        if (!this.isExpiredTriggered) {
                            this.isExpiredTriggered = true;
                            this.onExpired();
                        }
                    } else {
                        this.render();
                    }
                }
            }
        } catch (e) {
            console.warn('Timer sync temporary network issue, continuing local tick.', e);
        }
    }

    stop() {
        if (this.timerInterval) clearInterval(this.timerInterval);
        if (this.syncInterval) clearInterval(this.syncInterval);
    }
}
