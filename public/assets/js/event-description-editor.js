document.addEventListener('DOMContentLoaded', function () {
    const campo = document.getElementById('descricao');
    const editor = document.getElementById('descricao-editor');
    if (!campo || !editor) {
        return;
    }

    const barra = editor.parentElement ? editor.parentElement.querySelector('.rich-text-toolbar') : null;
    const marcas = { bold: 'STRONG', italic: 'EM', underline: 'U' };

    function textoVisivel() {
        return editor.textContent.replace(/\u00a0/g, ' ').replace(/\u200b/g, '').trim();
    }

    function sincronizar() {
        const texto = textoVisivel();
        campo.value = texto === '' ? '' : editor.innerHTML.replace(/\u200b/g, '');
        editor.setAttribute('data-empty', texto === '' ? '1' : '0');
    }

    function selecaoNoEditor() {
        const selecao = window.getSelection();
        if (!selecao || selecao.rangeCount === 0) {
            return null;
        }
        const range = selecao.getRangeAt(0);
        if (!editor.contains(range.commonAncestorContainer)) {
            return null;
        }
        return { selecao: selecao, range: range };
    }

    function colocarCursor(range) {
        const selecao = window.getSelection();
        if (!selecao) {
            return;
        }
        selecao.removeAllRanges();
        selecao.addRange(range);
    }

    function ancestral(node, tag) {
        let atual = node && node.nodeType === 1 ? node : (node ? node.parentElement : null);
        while (atual && atual !== editor) {
            if (atual.tagName === tag) {
                return atual;
            }
            atual = atual.parentElement;
        }
        return null;
    }

    function desembrulhar(elemento) {
        const pai = elemento.parentNode;
        if (!pai) {
            return;
        }
        while (elemento.firstChild) {
            pai.insertBefore(elemento.firstChild, elemento);
        }
        pai.removeChild(elemento);
    }

    function aplicarBloco(tag) {
        if (textoVisivel() === '') {
            editor.innerHTML = '<' + tag + '><br></' + tag + '>';
            const bloco = editor.firstChild;
            if (!bloco) {
                return;
            }
            const range = document.createRange();
            range.selectNodeContents(bloco);
            range.collapse(true);
            colocarCursor(range);
            return;
        }
        document.execCommand('formatBlock', false, '<' + tag + '>');
    }

    function aplicarMarca(formato) {
        const atual = selecaoNoEditor();
        if (!atual) {
            return;
        }
        const tag = marcas[formato];
        const range = atual.range;
        const existente = ancestral(range.startContainer, tag);

        if (range.collapsed) {
            if (existente) {
                const depois = range.cloneRange();
                depois.selectNodeContents(existente);
                depois.setStart(range.startContainer, range.startOffset);
                const resto = depois.extractContents();
                if (resto.textContent && resto.textContent.replace(/\u200b/g, '') !== '') {
                    existente.parentNode.insertBefore(resto, existente.nextSibling);
                }
                const cursor = document.createRange();
                cursor.setStartAfter(existente);
                cursor.collapse(true);
                colocarCursor(cursor);
                return;
            }

            const marca = document.createElement(tag);
            marca.appendChild(document.createTextNode('\u200b'));
            range.insertNode(marca);
            const cursor = document.createRange();
            cursor.setStart(marca.firstChild, 1);
            cursor.collapse(true);
            colocarCursor(cursor);
            return;
        }

        if (existente && existente.contains(range.endContainer)) {
            desembrulhar(existente);
            return;
        }

        const marca = document.createElement(tag);
        try {
            range.surroundContents(marca);
        } catch (erro) {
            marca.appendChild(range.extractContents());
            range.insertNode(marca);
        }
    }

    function blocoAtual() {
        const valor = String(document.queryCommandValue('formatBlock') || '').toLowerCase();
        const tag = valor.replace(/[<>]/g, '');
        if (tag === 'h1' || tag === 'h2' || tag === 'h3') {
            return tag;
        }
        return 'p';
    }

    function atualizarEstado() {
        if (!barra) {
            return;
        }
        const selecao = document.getSelection();
        const ancora = selecao ? selecao.anchorNode : null;
        const dentro = !!(ancora && editor.contains(ancora));
        barra.querySelectorAll('[data-format]').forEach(function (botao) {
            const formato = botao.getAttribute('data-format');
            let ativo = false;
            if (dentro && marcas[formato]) {
                ativo = !!ancestral(ancora, marcas[formato]);
            } else if (dentro && (formato === 'h1' || formato === 'h2' || formato === 'h3' || formato === 'p')) {
                ativo = blocoAtual() === formato;
            }
            botao.setAttribute('aria-pressed', ativo ? 'true' : 'false');
        });
    }

    editor.setAttribute('data-empty', textoVisivel() === '' ? '1' : '0');
    sincronizar();

    editor.addEventListener('input', function () {
        sincronizar();
        atualizarEstado();
    });
    editor.addEventListener('keyup', atualizarEstado);
    editor.addEventListener('mouseup', atualizarEstado);
    document.addEventListener('selectionchange', atualizarEstado);

    editor.addEventListener('paste', function (event) {
        const texto = event.clipboardData ? event.clipboardData.getData('text/plain') : '';
        event.preventDefault();
        document.execCommand('insertText', false, texto);
        sincronizar();
    });

    const formulario = editor.closest('form');
    if (formulario) {
        formulario.addEventListener('submit', sincronizar, true);
    }

    if (!barra) {
        return;
    }

    barra.querySelectorAll('[data-format]').forEach(function (botao) {
        botao.addEventListener('mousedown', function (event) {
            event.preventDefault();
        });
        botao.addEventListener('click', function () {
            editor.focus();
            const formato = botao.getAttribute('data-format');
            if (formato === 'h1' || formato === 'h2' || formato === 'h3' || formato === 'p') {
                aplicarBloco(formato);
            } else if (formato && marcas[formato]) {
                aplicarMarca(formato);
            }
            sincronizar();
            atualizarEstado();
        });
    });
});
