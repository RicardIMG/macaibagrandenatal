/* Painel administrativo — pequenas melhorias de usabilidade (o painel funciona sem JS). */
(function () {
    'use strict';

    // Menu lateral no celular
    var toggle = document.querySelector('[data-menu-toggle]');
    var backdrop = document.querySelector('[data-menu-backdrop]');
    if (toggle) {
        toggle.addEventListener('click', function () { document.body.classList.toggle('menu-open'); });
    }
    if (backdrop) {
        backdrop.addEventListener('click', function () { document.body.classList.remove('menu-open'); });
    }

    // Confirmação antes de ações destrutivas
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            if (!window.confirm(form.getAttribute('data-confirm'))) { ev.preventDefault(); }
        });
    });

    // Linhas de tabela clicáveis
    document.querySelectorAll('tr[data-href]').forEach(function (tr) {
        tr.addEventListener('click', function (ev) {
            if (ev.target.closest('a, button, input, select')) { return; }
            window.location.href = tr.getAttribute('data-href');
        });
    });

    // Substituir imagem da galeria: envia ao escolher o arquivo
    document.querySelectorAll('input[data-autosubmit]').forEach(function (input) {
        input.addEventListener('change', function () {
            if (!input.files.length) { return; }
            var form = document.getElementById(input.getAttribute('form'));
            if (form && window.confirm('Substituir esta imagem pela selecionada?')) { form.submit(); }
            else { input.value = ''; }
        });
    });

    // Evita envio duplo de formulários
    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            if (ev.defaultPrevented) { return; }
            if (form.dataset.sending) { ev.preventDefault(); return; }
            form.dataset.sending = '1';
            setTimeout(function () { delete form.dataset.sending; }, 8000);
        });
    });

    // Reordenação por arrastar e soltar (galeria)
    var list = document.querySelector('[data-sortable]');
    if (list) {
        var dragged = null;
        list.addEventListener('dragstart', function (ev) {
            var row = ev.target.closest('.gallery-row');
            if (!row || ev.target.closest('input')) { return; }
            dragged = row;
            row.classList.add('is-dragging');
            ev.dataTransfer.effectAllowed = 'move';
            ev.dataTransfer.setData('text/plain', row.getAttribute('data-id'));
        });
        list.addEventListener('dragover', function (ev) {
            if (!dragged) { return; }
            ev.preventDefault();
            var row = ev.target.closest('.gallery-row');
            if (!row || row === dragged) { return; }
            var rect = row.getBoundingClientRect();
            var after = ev.clientY > rect.top + rect.height / 2;
            list.insertBefore(dragged, after ? row.nextSibling : row);
        });
        list.addEventListener('dragend', function () {
            if (dragged) { dragged.classList.remove('is-dragging'); }
            dragged = null;
            var hint = document.querySelector('#gallery-form .sticky-actions .btn');
            if (hint) { hint.textContent = 'Salvar nova ordem'; }
        });
    }
})();
