(function () {
    'use strict';

    const OUTPUT_SIZE = 512;
    const MIN_SCALE = 1;
    const MAX_SCALE = 4;

    function clamp(value, min, max) {
        return Math.min(max, Math.max(min, value));
    }

    function createCropper(options) {
        const modal = document.getElementById(options.modalId);
        const fileInput = document.getElementById(options.inputId);
        if (!modal || !fileInput) {
            return null;
        }

        const stage = modal.querySelector('[data-crop-stage]');
        const image = modal.querySelector('[data-crop-image]');
        const mask = modal.querySelector('[data-crop-mask]');
        const previewSquare = modal.querySelector('[data-crop-preview-square]');
        const previewCircle = modal.querySelector('[data-crop-preview-circle]');
        const nameEl = fileInput.closest('.file-picker')?.querySelector('[data-file-name]');
        const livePreview = document.querySelector(options.previewSelector || '');
        const liveFallback = livePreview ? livePreview.querySelector('.icon') : null;

        let objectUrl = null;
        let sourceFile = null;
        let naturalWidth = 0;
        let naturalHeight = 0;
        let scale = 1;
        let baseScale = 1;
        let offsetX = 0;
        let offsetY = 0;
        let rotation = 0;
        let shape = 'circle';
        let dragging = false;
        let dragStartX = 0;
        let dragStartY = 0;
        let dragOriginX = 0;
        let dragOriginY = 0;
        let frameSize = 280;

        function frameRect() {
            return {
                size: frameSize,
                left: (stage.clientWidth - frameSize) / 2,
                top: (stage.clientHeight - frameSize) / 2,
            };
        }

        function rotatedSize() {
            const quarter = ((rotation % 360) + 360) % 360;
            if (quarter === 90 || quarter === 270) {
                return { width: naturalHeight, height: naturalWidth };
            }
            return { width: naturalWidth, height: naturalHeight };
        }

        function fitBaseScale() {
            const size = rotatedSize();
            const frame = frameRect();
            return Math.max(frame.size / size.width, frame.size / size.height);
        }

        function clampOffsets() {
            const size = rotatedSize();
            const drawnW = size.width * baseScale * scale;
            const drawnH = size.height * baseScale * scale;
            const frame = frameRect();
            const maxX = Math.max(0, (drawnW - frame.size) / 2);
            const maxY = Math.max(0, (drawnH - frame.size) / 2);
            offsetX = clamp(offsetX, -maxX, maxX);
            offsetY = clamp(offsetY, -maxY, maxY);
        }

        function applyTransform() {
            clampOffsets();
            const frame = frameRect();
            image.style.width = naturalWidth * baseScale * scale + 'px';
            image.style.height = naturalHeight * baseScale * scale + 'px';
            image.style.left = frame.left + frame.size / 2 + offsetX + 'px';
            image.style.top = frame.top + frame.size / 2 + offsetY + 'px';
            image.style.transform = 'translate(-50%, -50%) rotate(' + rotation + 'deg)';
            mask.style.width = frame.size + 'px';
            mask.style.height = frame.size + 'px';
            mask.style.left = frame.left + 'px';
            mask.style.top = frame.top + 'px';
            mask.dataset.shape = shape;
            stage.dataset.shape = shape;
            updatePreviews();
        }

        function drawPreview(canvas, rounded) {
            if (!canvas || !naturalWidth) {
                return;
            }
            const ctx = canvas.getContext('2d');
            const size = canvas.width;
            ctx.clearRect(0, 0, size, size);
            ctx.save();
            if (rounded) {
                ctx.beginPath();
                ctx.arc(size / 2, size / 2, size / 2, 0, Math.PI * 2);
                ctx.closePath();
                ctx.clip();
            }
            renderCrop(ctx, size);
            ctx.restore();
        }

        function renderCrop(ctx, size) {
            const frame = frameRect();
            const scaleToOutput = size / frame.size;
            const drawnW = naturalWidth * baseScale * scale * scaleToOutput;
            const drawnH = naturalHeight * baseScale * scale * scaleToOutput;
            const centerX = size / 2 + offsetX * scaleToOutput;
            const centerY = size / 2 + offsetY * scaleToOutput;

            ctx.fillStyle = '#f6f3eb';
            ctx.fillRect(0, 0, size, size);
            ctx.save();
            ctx.translate(centerX, centerY);
            ctx.rotate((rotation * Math.PI) / 180);
            ctx.drawImage(image, -drawnW / 2, -drawnH / 2, drawnW, drawnH);
            ctx.restore();
        }

        function updatePreviews() {
            drawPreview(previewSquare, false);
            drawPreview(previewCircle, true);
        }

        function open(file) {
            if (!file || !file.type.startsWith('image/')) {
                return;
            }

            cleanupUrl();
            sourceFile = file;
            objectUrl = URL.createObjectURL(file);
            function startEditing() {
                naturalWidth = image.naturalWidth;
                naturalHeight = image.naturalHeight;
                if (!naturalWidth || !naturalHeight) {
                    close(true);
                    return;
                }
                rotation = 0;
                scale = 1;
                offsetX = 0;
                offsetY = 0;
                shape = 'circle';
                setShapeButtons();
                document.body.appendChild(modal);
                modal.hidden = false;
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('image-crop-open');
                window.requestAnimationFrame(function () {
                    frameSize = Math.min(
                        280,
                        Math.max(180, Math.min(stage.clientWidth, stage.clientHeight) - 48)
                    );
                    baseScale = fitBaseScale();
                    applyTransform();
                });
            }

            image.onload = startEditing;
            image.onerror = function () {
                close(true);
                window.alert('Não foi possível abrir esta imagem.');
            };
            image.src = objectUrl;
        }

        function close(resetInput) {
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('image-crop-open');
            cleanupUrl();
            if (resetInput) {
                fileInput.value = '';
                if (nameEl) {
                    nameEl.textContent = 'Nenhum arquivo selecionado';
                }
            }
            sourceFile = null;
        }

        function cleanupUrl() {
            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
                objectUrl = null;
            }
            image.removeAttribute('src');
        }

        function setShapeButtons() {
            modal.querySelectorAll('[data-crop-shape]').forEach(function (button) {
                const active = button.getAttribute('data-crop-shape') === shape;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        }

        function exportBlob() {
            return new Promise(function (resolve, reject) {
                const canvas = document.createElement('canvas');
                canvas.width = OUTPUT_SIZE;
                canvas.height = OUTPUT_SIZE;
                const ctx = canvas.getContext('2d');
                if (!ctx) {
                    reject(new Error('Canvas indisponível'));
                    return;
                }
                renderCrop(ctx, OUTPUT_SIZE);
                canvas.toBlob(
                    function (blob) {
                        if (!blob) {
                            reject(new Error('Falha ao gerar imagem'));
                            return;
                        }
                        resolve(blob);
                    },
                    'image/jpeg',
                    0.92
                );
            });
        }

        async function apply() {
            try {
                const blob = await exportBlob();
                const baseName = (sourceFile && sourceFile.name ? sourceFile.name.replace(/\.[^.]+$/, '') : 'foto')
                    .replace(/[^\w\-]+/g, '_')
                    .slice(0, 40);
                const cropped = new File([blob], baseName + '-perfil.jpg', {
                    type: 'image/jpeg',
                    lastModified: Date.now(),
                });
                const transfer = new DataTransfer();
                transfer.items.add(cropped);
                fileInput.files = transfer.files;

                if (nameEl) {
                    nameEl.textContent = cropped.name;
                }

                if (livePreview) {
                    let previewImg = livePreview.querySelector('img');
                    if (!previewImg) {
                        previewImg = document.createElement('img');
                        previewImg.alt = '';
                        livePreview.appendChild(previewImg);
                    }
                    if (liveFallback) {
                        liveFallback.hidden = true;
                    }
                    previewImg.hidden = false;
                    previewImg.src = URL.createObjectURL(blob);
                }

                close(false);
                if (typeof options.onApplied === 'function') {
                    options.onApplied(cropped);
                }
            } catch (error) {
                window.alert('Não foi possível recortar a imagem. Tente outro arquivo.');
            }
        }

        fileInput.addEventListener('change', function () {
            const file = fileInput.files && fileInput.files[0];
            if (!file) {
                return;
            }
            if (!file.type.startsWith('image/')) {
                fileInput.value = '';
                return;
            }
            open(file);
        });

        stage.addEventListener('pointerdown', function (event) {
            if (event.button !== 0) {
                return;
            }
            dragging = true;
            dragStartX = event.clientX;
            dragStartY = event.clientY;
            dragOriginX = offsetX;
            dragOriginY = offsetY;
            stage.setPointerCapture(event.pointerId);
            stage.classList.add('is-dragging');
        });

        stage.addEventListener('pointermove', function (event) {
            if (!dragging) {
                return;
            }
            offsetX = dragOriginX + (event.clientX - dragStartX);
            offsetY = dragOriginY + (event.clientY - dragStartY);
            applyTransform();
        });

        function endDrag(event) {
            if (!dragging) {
                return;
            }
            dragging = false;
            stage.classList.remove('is-dragging');
            if (stage.hasPointerCapture(event.pointerId)) {
                stage.releasePointerCapture(event.pointerId);
            }
        }

        stage.addEventListener('pointerup', endDrag);
        stage.addEventListener('pointercancel', endDrag);

        stage.addEventListener(
            'wheel',
            function (event) {
                event.preventDefault();
                const delta = event.deltaY < 0 ? 0.08 : -0.08;
                scale = clamp(scale + delta, MIN_SCALE, MAX_SCALE);
                applyTransform();
            },
            { passive: false }
        );

        modal.querySelectorAll('[data-crop-rotate]').forEach(function (button) {
            button.addEventListener('click', function () {
                const amount = Number(button.getAttribute('data-crop-rotate') || '0');
                rotation = (((rotation + amount) % 360) + 360) % 360;
                baseScale = fitBaseScale();
                scale = 1;
                offsetX = 0;
                offsetY = 0;
                applyTransform();
            });
        });

        modal.querySelectorAll('[data-crop-zoom]').forEach(function (button) {
            button.addEventListener('click', function () {
                const amount = Number(button.getAttribute('data-crop-zoom') || '0');
                scale = clamp(scale + amount, MIN_SCALE, MAX_SCALE);
                applyTransform();
            });
        });

        modal.querySelectorAll('[data-crop-shape]').forEach(function (button) {
            button.addEventListener('click', function () {
                shape = button.getAttribute('data-crop-shape') === 'square' ? 'square' : 'circle';
                setShapeButtons();
                applyTransform();
            });
        });

        modal.querySelectorAll('[data-close-crop]').forEach(function (el) {
            el.addEventListener('click', function () {
                close(true);
            });
        });

        const applyButton = modal.querySelector('[data-crop-apply]');
        if (applyButton) {
            applyButton.addEventListener('click', apply);
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.hidden) {
                close(true);
            }
        });

        window.addEventListener('resize', function () {
            if (modal.hidden || !naturalWidth) {
                return;
            }
            frameSize = Math.min(280, Math.max(180, Math.min(stage.clientWidth, stage.clientHeight) - 48));
            baseScale = fitBaseScale();
            applyTransform();
        });

        return { open: open, close: close };
    }

    document.addEventListener('DOMContentLoaded', function () {
        createCropper({
            modalId: 'image-crop-modal',
            inputId: 'photo',
            previewSelector: '.profile-photo-preview',
        });
    });
})();
