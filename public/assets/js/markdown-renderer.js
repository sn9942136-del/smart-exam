/**
 * AI Markdown & Math Formatter Engine
 * Mengonversi output teks Markdown, LaTeX Math formula, dan Code blocks dari Gemini API
 * menjadi representasi visual HTML yang interaktif, rapi, dan modern.
 */
class MarkdownRenderer {
    /**
     * Parse raw markdown string to rich styled HTML
     * @param {string} rawText 
     * @returns {string} Safe styled HTML
     */
    static render(rawText) {
        if (!rawText || typeof rawText !== 'string') return '';

        // If marked.js is available on window, use it as baseline parser with customizations
        if (typeof window.marked !== 'undefined' && typeof window.marked.parse === 'function') {
            try {
                let html = window.marked.parse(rawText, { gfm: true, breaks: true });
                return this.postProcessHtml(html);
            } catch (e) {
                console.warn('[MarkdownRenderer] marked.js error, falling back to built-in parser:', e);
            }
        }

        // Built-in Lightweight & Robust Markdown Parser
        return this.builtinParse(rawText);
    }

    /**
     * Format inline markdown elements (bold, italic) without corrupting line-level list syntax
     */
    static formatInline(str) {
        if (!str || typeof str !== 'string') return '';
        let s = str;
        // Bold & Italic: ***text***
        s = s.replace(/\*\*\*([^*\n]+)\*\*\*/g, '<strong class="text-dark fw-bold"><em>$1</em></strong>');
        // Bold: **text** or __text__
        s = s.replace(/\*\*([^*\n]+)\*\*/g, '<strong class="text-dark fw-bold">$1</strong>');
        s = s.replace(/__([^_\n]+)__/g, '<strong class="text-dark fw-bold">$1</strong>');
        // Italic: *text* (no whitespace following opening asterisk, no whitespace before closing)
        s = s.replace(/(?<!\*)\*(?!\s|\*)([^*\n]+?)(?<!\s|\*)\*(?!\*)/g, '<em>$1</em>');
        s = s.replace(/(?<!_)_(?!\s|_)([^_\n]+?)(?<!\s|_)_(?!_)/g, '<em>$1</em>');
        return s;
    }

    /**
     * Built-in Comprehensive Markdown Parser
     */
    static builtinParse(text) {
        let md = text;

        // 1. Normalize line endings
        md = md.replace(/\r\n/g, '\n');

        // 2. Extract & protect fenced code blocks
        const codeBlocks = [];
        md = md.replace(/```([a-zA-Z0-9_\-#+]*)\n([\s\S]*?)```/g, (match, lang, code) => {
            const index = codeBlocks.length;
            const cleanLang = lang.trim() || 'code';
            const escapedCode = this.escapeHtml(code.trim());
            codeBlocks.push(`
                <div class="code-block-wrapper my-2.5 rounded-3 overflow-hidden border border-secondary-subtle shadow-2xs">
                    <div class="code-header d-flex justify-content-between align-items-center bg-dark text-white-50 px-3 py-1.5 fs-3xs">
                        <span class="text-uppercase fw-semibold"><i class="bi bi-code-slash text-info me-1"></i>${cleanLang}</span>
                        <button type="button" class="btn btn-sm btn-link text-white-50 text-decoration-none p-0 fs-3xs btn-copy-code" onclick="MarkdownRenderer.copyCode(this)">
                            <i class="bi bi-clipboard me-1"></i>Salin
                        </button>
                    </div>
                    <pre class="m-0 p-3 bg-dark text-light overflow-auto font-monospace fs-xs"><code>${escapedCode}</code></pre>
                </div>
            `);
            return `__CODE_BLOCK_${index}__`;
        });

        // 3. Extract & protect inline code
        const inlineCodes = [];
        md = md.replace(/`([^`\n]+)`/g, (match, code) => {
            const index = inlineCodes.length;
            inlineCodes.push(`<code class="px-1.5 py-0.5 rounded bg-secondary-subtle text-danger font-monospace fs-xs fw-semibold">${this.escapeHtml(code)}</code>`);
            return `__INLINE_CODE_${index}__`;
        });

        // 4. Block math $$...$$
        md = md.replace(/\$\$([\s\S]*?)\$\$/g, (match, math) => {
            return `<div class="math-block p-2.5 my-2 bg-primary-subtle text-primary border border-primary-subtle rounded-3 text-center font-monospace fw-semibold fs-sm">${this.escapeHtml(math.trim())}</div>`;
        });

        // 5. Inline math $x$ or $...$
        md = md.replace(/\$([^\$\n]+)\$/g, (match, math) => {
            return `<span class="badge bg-primary-subtle text-primary font-monospace px-1.5 py-0.5 fw-semibold">${this.escapeHtml(math)}</span>`;
        });

        // 6. Horizontal Rules (---, ***, ___)
        md = md.replace(/^(?:---|\*\*\*|___)\s*$/gm, '<hr class="my-3 border-secondary-subtle opacity-75">');

        // 7. Headings (#, ##, ###, ####)
        md = md.replace(/^# (.*$)/gim, '<div class="chat-heading-1 fw-bold text-dark mt-2.5 mb-1.5 pb-1 border-bottom d-flex align-items-center gap-1.5"><i class="bi bi-stars text-primary fs-xs"></i><span>$1</span></div>');
        md = md.replace(/^## (.*$)/gim, '<div class="chat-heading-2 fw-bold text-primary mt-2.5 mb-1.5 pb-0.5 border-bottom border-light d-flex align-items-center gap-1.5"><i class="bi bi-chevron-right text-primary fs-3xs"></i><span>$1</span></div>');
        md = md.replace(/^### (.*$)/gim, '<div class="chat-heading-3 fw-bold text-dark mt-2 mb-1 d-flex align-items-center gap-1.5"><i class="bi bi-bookmark-fill text-primary fs-3xs"></i><span>$1</span></div>');
        md = md.replace(/^#### (.*$)/gim, '<div class="chat-heading-4 fw-semibold text-secondary mt-1.5 mb-1">$1</div>');

        // 8. Guardrails, Privacy & Callout boxes
        md = md.replace(/⚠️\s*\[Di Luar Ruang Lingkup Sistem Ujian\]\s*([^\n]+(?:\n[^\n]+)*)/gi, (match, content) => {
            return `<div class="ai-guardrail-card my-2 p-2.5 rounded-3">
                <div class="fw-bold mb-1 d-flex align-items-center gap-1.5">
                    <i class="bi bi-shield-exclamation text-warning fs-6"></i>
                    <span>Di Luar Ruang Lingkup Sistem Ujian</span>
                </div>
                <div>${content}</div>
            </div>`;
        });

        md = md.replace(/🔒\s*\[Privasi Pengguna Terlindungi(?:\s*\/\s*Keamanan Data)?\]\s*([^\n]+(?:\n[^\n]+)*)/gi, (match, content) => {
            return `<div class="ai-privacy-card my-2 p-2.5 rounded-3">
                <div class="fw-bold mb-1 d-flex align-items-center gap-1.5">
                    <i class="bi bi-shield-lock-fill text-danger fs-6"></i>
                    <span>Privasi Pengguna Terlindungi</span>
                </div>
                <div>${content}</div>
            </div>`;
        });

        md = md.replace(/(?:<strong class="text-dark fw-bold">)?(?:Analogi Dunia Nyata|Tips Belajar|Konsep Penting|Catatan Khusus|Kunci Logika):?(?:<\/strong>)?\s*([^\n<]+)/gi, (match, content) => {
            return `<div class="ai-callout-card p-2.5 my-2.5 rounded-3">
                <div class="fw-bold mb-1 d-flex align-items-center gap-1 text-primary">
                    <i class="bi bi-lightbulb-fill text-warning"></i>
                    <span>${match.split(':')[0].replace(/<[^>]+>/g, '')}:</span>
                </div>
                <div>${content}</div>
            </div>`;
        });

        // 9. Process Lists line by line, safely applying inline formatting to contents only
        const lines = md.split('\n');
        let inUl = false;
        let inOl = false;
        const processedLines = [];

        for (let i = 0; i < lines.length; i++) {
            let line = lines[i];

            // Match Unordered list item: starts with *, -, +, or unicode bullet •
            const ulMatch = line.match(/^\s*[\*\-\+•]\s+(.*)$/u);
            // Match Numbered list item: starts with 1., 2., etc.
            const olMatch = line.match(/^\s*(\d+)\.\s+(.*)$/);

            if (ulMatch) {
                if (!inUl) {
                    if (inOl) { processedLines.push('</ol>'); inOl = false; }
                    processedLines.push('<ul class="ai-styled-list">');
                    inUl = true;
                }
                const formattedItem = this.formatInline(ulMatch[1]);
                processedLines.push(`
                    <li class="ai-list-item">
                        <span class="ai-item-bullet">&bull;</span>
                        <span class="ai-item-text">${formattedItem}</span>
                    </li>
                `);
            } else if (olMatch) {
                if (!inOl) {
                    if (inUl) { processedLines.push('</ul>'); inUl = false; }
                    processedLines.push('<ol class="ps-3 mb-2 ai-styled-numbered-list">');
                    inOl = true;
                }
                const formattedItem = this.formatInline(olMatch[2]);
                processedLines.push(`<li class="mb-1"><span class="fw-semibold text-dark">${formattedItem}</span></li>`);
            } else if (line.trim() === '') {
                // Peek ahead: if next non-empty line continues list of same type, keep list open
                let nextNonEmpty = '';
                for (let j = i + 1; j < lines.length; j++) {
                    if (lines[j].trim() !== '') {
                        nextNonEmpty = lines[j];
                        break;
                    }
                }
                const nextIsUl = Boolean(nextNonEmpty.match(/^\s*[\*\-\+•]\s+(.*)$/u));
                const nextIsOl = Boolean(nextNonEmpty.match(/^\s*(\d+)\.\s+(.*)$/));

                if (inUl && nextIsUl) {
                    continue;
                }
                if (inOl && nextIsOl) {
                    continue;
                }

                if (inUl) { processedLines.push('</ul>'); inUl = false; }
                if (inOl) { processedLines.push('</ol>'); inOl = false; }
            } else {
                if (inUl) { processedLines.push('</ul>'); inUl = false; }
                if (inOl) { processedLines.push('</ol>'); inOl = false; }

                // Check if it's already an HTML tag or placeholder
                if (line.startsWith('<') || line.startsWith('__CODE_BLOCK_') || line.startsWith('__INLINE_CODE_')) {
                    processedLines.push(line);
                } else {
                    const formattedLine = this.formatInline(line);
                    processedLines.push(`<p class="chat-p mb-2">${formattedLine}</p>`);
                }
            }
        }

        if (inUl) processedLines.push('</ul>');
        if (inOl) processedLines.push('</ol>');

        let finalHtml = processedLines.join('\n');

        // Restore Code Blocks & Inline Codes
        codeBlocks.forEach((block, idx) => {
            finalHtml = finalHtml.replace(`__CODE_BLOCK_${idx}__`, block);
        });

        inlineCodes.forEach((code, idx) => {
            finalHtml = finalHtml.replace(`__INLINE_CODE_${idx}__`, code);
        });

        return this.postProcessHtml(finalHtml);
    }

    /**
     * Post-process rendered HTML to enhance tables, callout blocks, and copy buttons
     */
    static postProcessHtml(html) {
        // Normalize marked.js headings if present
        html = html.replace(/<h1[^>]*>(.*?)<\/h1>/gi, '<div class="chat-heading-1 fw-bold text-dark mt-2.5 mb-1.5 pb-1 border-bottom d-flex align-items-center gap-1.5"><i class="bi bi-stars text-primary fs-xs"></i><span>$1</span></div>');
        html = html.replace(/<h2[^>]*>(.*?)<\/h2>/gi, '<div class="chat-heading-2 fw-bold text-primary mt-2.5 mb-1.5 pb-0.5 border-bottom border-light d-flex align-items-center gap-1.5"><i class="bi bi-chevron-right text-primary fs-3xs"></i><span>$1</span></div>');
        html = html.replace(/<h3[^>]*>(.*?)<\/h3>/gi, '<div class="chat-heading-3 fw-bold text-dark mt-2 mb-1 d-flex align-items-center gap-1.5"><i class="bi bi-bookmark-fill text-primary fs-3xs"></i><span>$1</span></div>');
        html = html.replace(/<h4[^>]*>(.*?)<\/h4>/gi, '<div class="chat-heading-4 fw-semibold text-secondary mt-1.5 mb-1">$1</div>');
        
        // Ensure marked.js lists also get custom styling classes
        html = html.replace(/<ul>/g, '<ul class="ai-styled-list">');
        html = html.replace(/<ol>/g, '<ol class="ai-styled-numbered-list">');

        // Enhance any table
        html = html.replace(/<table/g, '<div class="table-responsive my-2.5"><table class="table table-sm table-bordered table-striped align-middle fs-xs mb-0 bg-white rounded"');
        html = html.replace(/<\/table>/g, '</table></div>');

        // Sanitize dangerous HTML tags and event handlers
        html = this.sanitizeHtml(html);

        return html;
    }

    /**
     * Sanitize HTML against XSS
     */
    static sanitizeHtml(dirty) {
        if (!dirty || typeof dirty !== 'string') return '';

        try {
            const parser = new DOMParser();
            const doc = parser.parseFromString(dirty, 'text/html');

            const forbiddenTags = ['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'APPLET', 'META', 'LINK', 'FORM', 'INPUT', 'SVG', 'MATH', 'BASE'];

            forbiddenTags.forEach(tag => {
                const elements = doc.body.querySelectorAll(tag);
                elements.forEach(el => el.remove());
            });

            const allElements = doc.body.querySelectorAll('*');
            allElements.forEach(el => {
                const attrs = Array.from(el.attributes);
                attrs.forEach(attr => {
                    const name = attr.name.toLowerCase();
                    const val = attr.value || '';
                    if (name.startsWith('on')) {
                        el.removeAttribute(attr.name);
                    } else if (name === 'href' || name === 'src' || name === 'action') {
                        const cleanVal = val.trim().toLowerCase();
                        if (cleanVal.startsWith('javascript:') || cleanVal.startsWith('vbscript:') || (cleanVal.startsWith('data:') && !cleanVal.startsWith('data:image/'))) {
                            el.setAttribute(attr.name, '#');
                        }
                    } else if (name === 'style') {
                        if (/expression|javascript|behavior/i.test(val)) {
                            el.removeAttribute(attr.name);
                        }
                    }
                });
            });

            return doc.body.innerHTML;
        } catch (e) {
            console.warn('[MarkdownRenderer] sanitizeHtml error, escaping plain text:', e);
            return this.escapeHtml(dirty);
        }
    }

    /**
     * Copy code block contents to clipboard
     */
    static copyCode(button) {
        const wrapper = button.closest('.code-block-wrapper');
        const codeEl = wrapper ? wrapper.querySelector('code') : null;
        if (!codeEl) return;

        const text = codeEl.innerText;
        navigator.clipboard.writeText(text).then(() => {
            const origHtml = button.innerHTML;
            button.innerHTML = '<i class="bi bi-check2 text-success me-1"></i>Tersalin!';
            button.classList.add('text-success');
            setTimeout(() => {
                button.innerHTML = origHtml;
                button.classList.remove('text-success');
            }, 2000);
        }).catch(err => {
            console.warn('[Copy failed]:', err);
        });
    }

    /**
     * Copy full message text to clipboard
     */
    static copyMessage(button) {
        const card = button.closest('.ai-message-card') || button.closest('.ai-message-row') || button.closest('.ai-msg-row') || button.closest('.ai-msg-bubble-wrapper');
        const contentEl = card ? card.querySelector('.chat-markdown-body') : null;
        if (!contentEl) return;

        const text = contentEl.innerText;
        navigator.clipboard.writeText(text).then(() => {
            const origHtml = button.innerHTML;
            button.innerHTML = '<i class="bi bi-check-circle-fill text-success me-1"></i>Tersalin!';
            button.classList.add('btn-success-subtle');
            setTimeout(() => {
                button.innerHTML = origHtml;
                button.classList.remove('btn-success-subtle');
            }, 2000);
        }).catch(err => {
            console.warn('[Copy failed]:', err);
        });
    }

    static escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}

// Expose globally
window.MarkdownRenderer = MarkdownRenderer;
