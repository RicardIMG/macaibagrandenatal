/* Landing page — interações (sem dependências externas) */
(function () {
    'use strict';

    var UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];
    var STORE_KEY = 'lp_attribution';

    function store(key, value) {
        try { if (value === undefined) { return JSON.parse(localStorage.getItem(key) || 'null'); } localStorage.setItem(key, JSON.stringify(value)); } catch (e) { return null; }
    }

    /* ---------- Origem do lead (UTMs, página e referência) ---------- */
    var attribution = (function () {
        var params = new URLSearchParams(window.location.search);
        var saved = store(STORE_KEY) || {};
        var fromUrl = {};
        var hasUtm = false;
        UTM_KEYS.forEach(function (k) {
            var v = params.get(k);
            if (v) { fromUrl[k] = v.slice(0, 190); hasUtm = true; }
        });
        var ref = document.referrer || '';
        var external = ref && ref.indexOf(window.location.origin) !== 0;
        // Nova campanha na URL substitui a anterior (último toque); sem UTMs, mantém a salva por 30 dias.
        if (hasUtm) {
            saved = { utm: fromUrl, landing: window.location.href, referrer: external ? ref : (saved.referrer || ''), ts: Date.now() };
            store(STORE_KEY, saved);
        } else if (!saved.ts || Date.now() - saved.ts > 30 * 864e5) {
            saved = { utm: {}, landing: window.location.href, referrer: external ? ref : '', ts: Date.now() };
            store(STORE_KEY, saved);
        }
        return saved;
    })();

    /* ---------- Cabeçalho sólido ao rolar ---------- */
    var header = document.querySelector('[data-header]');
    function onScroll() {
        if (header) { header.classList.toggle('is-solid', window.scrollY > 40); }
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    /* ---------- Rolagem suave até o formulário (preserva a URL e os UTMs) ---------- */
    document.querySelectorAll('a[href^="#"]').forEach(function (a) {
        a.addEventListener('click', function (ev) {
            var id = a.getAttribute('href').slice(1);
            var target = id ? document.getElementById(id) : null;
            if (!target) { return; }
            ev.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            if (a.hasAttribute('data-scroll-to-form')) {
                setTimeout(function () {
                    var first = document.getElementById('f-name');
                    if (first && window.matchMedia('(hover: hover)').matches) { first.focus({ preventScroll: true }); }
                }, 700);
            }
        });
    });

    /* ---------- Máscara de telefone (Brasil por padrão, internacional com "+") ---------- */
    function maskPhone(value) {
        var v = value.replace(/[^\d+]/g, '');
        if (v.charAt(0) === '+') {
            // Internacional: "+" seguido apenas de dígitos (até 15, padrão E.164)
            return '+' + v.replace(/\D/g, '').slice(0, 15);
        }
        var n = v.replace(/\D/g, '').slice(0, 11);
        if (n.length <= 2) { return n.length ? '(' + n : ''; }
        if (n.length <= 6) { return '(' + n.slice(0, 2) + ') ' + n.slice(2); }
        if (n.length <= 10) { return '(' + n.slice(0, 2) + ') ' + n.slice(2, 6) + '-' + n.slice(6); }
        return '(' + n.slice(0, 2) + ') ' + n.slice(2, 7) + '-' + n.slice(7);
    }
    document.querySelectorAll('[data-phone-mask]').forEach(function (input) {
        input.addEventListener('input', function () {
            var atEnd = input.selectionStart === input.value.length;
            input.value = maskPhone(input.value);
            if (atEnd) { input.setSelectionRange(input.value.length, input.value.length); }
        });
    });

    /* ---------- Formulário de interesse ---------- */
    var form = document.querySelector('[data-lead-form]');
    if (form) {
        var success = document.querySelector('[data-form-success]');
        var alertBox = form.querySelector('[data-form-alert]');
        var submitBtn = form.querySelector('[data-submit]');
        var sending = false;

        function newSubmissionId() {
            var bytes = new Uint8Array(16);
            (window.crypto || window.msCrypto).getRandomValues(bytes);
            return Array.prototype.map.call(bytes, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
        }
        form.elements.submission_id.value = newSubmissionId();

        function fillAttribution() {
            UTM_KEYS.forEach(function (k) { form.elements[k].value = (attribution.utm && attribution.utm[k]) || ''; });
            form.elements.landing_page.value = attribution.landing || window.location.href;
            form.elements.referrer.value = attribution.referrer || '';
        }
        fillAttribution();

        var rules = {
            name: function (v) { return v.trim().length >= 2 ? '' : 'Informe seu nome.'; },
            whatsapp: function (v) { var d = v.replace(/\D/g, ''); return d.length >= 8 && d.length <= 15 ? '' : 'Informe um WhatsApp válido (com DDD ou código do país).'; },
            email: function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v.trim()) ? '' : 'Informe um e-mail válido.'; },
            company: function (v) { return v.trim().length >= 2 ? '' : 'Informe o nome da empresa.'; },
            website_instagram: function (v) { return v.trim().length >= 3 ? '' : 'Informe o site ou Instagram da empresa.'; },
            interest_reason: function (v) { return v.trim().length >= 10 ? '' : 'Conte-nos brevemente o motivo do seu interesse.'; }
        };

        function setError(name, msg) {
            var el = form.elements[name];
            var holder = form.querySelector('[data-error-for="' + name + '"]');
            if (!el || !holder) { return; }
            holder.textContent = msg || '';
            el.closest('.field').classList.toggle('has-error', !!msg);
            el.setAttribute('aria-invalid', msg ? 'true' : 'false');
        }

        function validate() {
            var first = null;
            Object.keys(rules).forEach(function (name) {
                var msg = rules[name](form.elements[name].value);
                setError(name, msg);
                if (msg && !first) { first = form.elements[name]; }
            });
            return first;
        }

        Object.keys(rules).forEach(function (name) {
            var el = form.elements[name];
            el.addEventListener('blur', function () { if (el.value) { setError(name, rules[name](el.value)); } });
            el.addEventListener('input', function () {
                if (el.closest('.field').classList.contains('has-error')) { setError(name, rules[name](el.value)); }
            });
        });

        function showAlert(msg) {
            alertBox.textContent = msg;
            alertBox.hidden = !msg;
        }

        function setLoading(on) {
            sending = on;
            submitBtn.disabled = on;
            submitBtn.classList.toggle('is-loading', on);
        }

        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            if (sending) { return; }
            showAlert('');
            var invalid = validate();
            if (invalid) { invalid.focus(); return; }
            fillAttribution();
            setLoading(true);

            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' },
                credentials: 'same-origin'
            }).then(function (res) {
                return res.json().catch(function () { return { ok: false, message: 'Não foi possível enviar agora. Tente novamente.' }; });
            }).then(function (data) {
                if (data && data.ok) {
                    form.hidden = true;
                    success.hidden = false;
                    success.focus({ preventScroll: true });
                    success.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return;
                }
                if (data && data.errors) {
                    Object.keys(data.errors).forEach(function (k) { setError(k, data.errors[k]); });
                }
                if (data && data.token) { form.elements.form_token.value = data.token; }
                showAlert((data && data.message) || 'Verifique os campos destacados.');
                setLoading(false);
            }).catch(function () {
                // Mantém o mesmo submission_id: um reenvio não gera lead duplicado.
                showAlert('Falha de conexão. Verifique sua internet e tente novamente.');
                setLoading(false);
            });
        });
    }

    /* ---------- Lightbox com zoom (pinça, roda do mouse, duplo toque) ---------- */
    var lb = document.querySelector('[data-lb]');
    if (!lb) { return; }
    var stage = lb.querySelector('[data-lb-stage]');
    var img = lb.querySelector('[data-lb-img]');
    var caption = lb.querySelector('[data-lb-caption]');
    var counter = lb.querySelector('[data-lb-counter]');
    var openLink = lb.querySelector('[data-lb-open]');
    var loading = lb.querySelector('[data-lb-loading]');
    var prevBtn = lb.querySelector('[data-lb-prev]');
    var nextBtn = lb.querySelector('[data-lb-next]');
    var items = [];
    var index = 0;
    var lastFocus = null;
    var view = { scale: 1, x: 0, y: 0 };
    var base = { w: 0, h: 0, left: 0, top: 0 };
    var pointers = {};
    var gesture = null;

    function apply() {
        img.style.transform = 'translate(' + view.x + 'px,' + view.y + 'px) scale(' + view.scale + ')';
    }
    function measure() {
        img.style.transform = 'none';
        var r = img.getBoundingClientRect();
        var s = stage.getBoundingClientRect();
        base = { w: r.width, h: r.height, left: r.left - s.left, top: r.top - s.top };
        apply();
    }
    function clamp() {
        if (view.scale <= 1) { view.scale = 1; view.x = 0; view.y = 0; return; }
        var s = stage.getBoundingClientRect();
        var w = base.w * view.scale, h = base.h * view.scale;
        // Posição do canto superior esquerdo da imagem ampliada em relação ao palco
        var minX = Math.min(0, s.width - w) - base.left, maxX = Math.max(0, s.width - w) - base.left;
        var minY = Math.min(0, s.height - h) - base.top, maxY = Math.max(0, s.height - h) - base.top;
        if (w <= s.width) { view.x = (s.width - w) / 2 - base.left; } else { view.x = Math.min(maxX, Math.max(minX, view.x)); }
        if (h <= s.height) { view.y = (s.height - h) / 2 - base.top; } else { view.y = Math.min(maxY, Math.max(minY, view.y)); }
    }
    function zoomAt(newScale, cx, cy) {
        newScale = Math.min(8, Math.max(1, newScale));
        var s = stage.getBoundingClientRect();
        var px = cx - s.left - base.left, py = cy - s.top - base.top; // ponto na imagem (não escalada)
        var ix = (px - view.x) / view.scale, iy = (py - view.y) / view.scale;
        view.scale = newScale;
        view.x = px - ix * newScale;
        view.y = py - iy * newScale;
        clamp();
        apply();
    }
    function zoomCenter(factor) {
        var s = stage.getBoundingClientRect();
        zoomAt(view.scale * factor, s.left + s.width / 2, s.top + s.height / 2);
    }
    function resetView() { view = { scale: 1, x: 0, y: 0 }; apply(); }

    function show(i) {
        index = (i + items.length) % items.length;
        var item = items[index];
        resetView();
        loading.hidden = false;
        img.style.opacity = '0';
        img.onload = function () { loading.hidden = true; img.style.opacity = '1'; measure(); };
        img.onerror = function () { loading.hidden = true; };
        img.src = item.src;
        img.alt = item.caption || '';
        caption.textContent = item.caption || '';
        openLink.href = item.src;
        counter.textContent = items.length > 1 ? (index + 1) + ' / ' + items.length : '';
        prevBtn.hidden = nextBtn.hidden = items.length < 2;
    }
    function open(list, i) {
        items = list;
        lastFocus = document.activeElement;
        lb.hidden = false;
        document.body.classList.add('lb-open');
        show(i);
        lb.querySelector('[data-lb-close]').focus();
    }
    function close() {
        lb.hidden = true;
        document.body.classList.remove('lb-open');
        img.removeAttribute('src');
        if (lastFocus) { lastFocus.focus(); }
    }

    document.querySelectorAll('[data-lightbox]').forEach(function (el) {
        el.addEventListener('click', function () {
            var group = el.getAttribute('data-lightbox');
            var all = Array.prototype.slice.call(document.querySelectorAll('[data-lightbox="' + group + '"]'));
            open(all.map(function (n) { return { src: n.getAttribute('data-full'), caption: n.getAttribute('data-caption') }; }), all.indexOf(el));
        });
    });

    lb.querySelector('[data-lb-close]').addEventListener('click', close);
    lb.querySelector('[data-lb-zoom-in]').addEventListener('click', function () { zoomCenter(1.6); });
    lb.querySelector('[data-lb-zoom-out]').addEventListener('click', function () { zoomCenter(1 / 1.6); });
    prevBtn.addEventListener('click', function () { show(index - 1); });
    nextBtn.addEventListener('click', function () { show(index + 1); });

    document.addEventListener('keydown', function (ev) {
        if (lb.hidden) { return; }
        if (ev.key === 'Escape') { close(); }
        else if (ev.key === 'ArrowLeft' && items.length > 1) { show(index - 1); }
        else if (ev.key === 'ArrowRight' && items.length > 1) { show(index + 1); }
        else if (ev.key === '+' || ev.key === '=') { zoomCenter(1.4); }
        else if (ev.key === '-') { zoomCenter(1 / 1.4); }
        else if (ev.key === 'Tab') {
            var f = lb.querySelectorAll('button:not([hidden]), a[href]');
            var first = f[0], last = f[f.length - 1];
            if (ev.shiftKey && document.activeElement === first) { ev.preventDefault(); last.focus(); }
            else if (!ev.shiftKey && document.activeElement === last) { ev.preventDefault(); first.focus(); }
        }
    });

    window.addEventListener('resize', function () { if (!lb.hidden) { resetView(); measure(); } });

    stage.addEventListener('wheel', function (ev) {
        ev.preventDefault();
        zoomAt(view.scale * (ev.deltaY < 0 ? 1.15 : 1 / 1.15), ev.clientX, ev.clientY);
    }, { passive: false });

    var lastTap = 0;
    stage.addEventListener('pointerdown', function (ev) {
        stage.setPointerCapture(ev.pointerId);
        pointers[ev.pointerId] = { x: ev.clientX, y: ev.clientY };
        var ids = Object.keys(pointers);
        if (ids.length === 1) {
            var now = Date.now();
            if (now - lastTap < 300) {
                zoomAt(view.scale > 1.2 ? 1 : 2.5, ev.clientX, ev.clientY);
                lastTap = 0;
            } else { lastTap = now; }
            gesture = { type: 'pan', sx: ev.clientX, sy: ev.clientY, vx: view.x, vy: view.y, moved: false };
            stage.classList.add('is-dragging');
        } else if (ids.length === 2) {
            var a = pointers[ids[0]], b = pointers[ids[1]];
            gesture = { type: 'pinch', dist: Math.hypot(a.x - b.x, a.y - b.y), scale: view.scale };
        }
    });
    stage.addEventListener('pointermove', function (ev) {
        if (!pointers[ev.pointerId] || !gesture) { return; }
        pointers[ev.pointerId] = { x: ev.clientX, y: ev.clientY };
        var ids = Object.keys(pointers);
        if (gesture.type === 'pinch' && ids.length >= 2) {
            var a = pointers[ids[0]], b = pointers[ids[1]];
            var d = Math.hypot(a.x - b.x, a.y - b.y);
            zoomAt(gesture.scale * d / gesture.dist, (a.x + b.x) / 2, (a.y + b.y) / 2);
        } else if (gesture.type === 'pan') {
            var dx = ev.clientX - gesture.sx, dy = ev.clientY - gesture.sy;
            if (Math.abs(dx) + Math.abs(dy) > 4) { gesture.moved = true; }
            if (view.scale > 1) {
                view.x = gesture.vx + dx; view.y = gesture.vy + dy;
                clamp(); apply();
            }
        }
    });
    function endPointer(ev) {
        var g = gesture;
        delete pointers[ev.pointerId];
        stage.classList.remove('is-dragging');
        if (g && g.type === 'pan' && view.scale === 1 && items.length > 1) {
            var dx = ev.clientX - g.sx;
            if (Math.abs(dx) > 60) { show(index + (dx < 0 ? 1 : -1)); }
        }
        if (g && g.type === 'pan' && !g.moved && view.scale === 1 && ev.target === stage) { close(); }
        var rest = Object.keys(pointers);
        if (rest.length === 1) {
            var r = pointers[rest[0]];
            gesture = { type: 'pan', sx: r.x, sy: r.y, vx: view.x, vy: view.y, moved: true };
        } else if (!rest.length) {
            gesture = null;
        }
    }
    stage.addEventListener('pointerup', endPointer);
    stage.addEventListener('pointercancel', endPointer);
})();
