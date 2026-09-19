(function (window, document) {
    'use strict';

    var state = {
        checked: false,
        available: false,
        devices: [],
        pending: null
    };

    var styleInjected = false;
    var activeOverlay = null;

    function el(tag, className, text) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text != null) {
            node.textContent = text;
        }
        return node;
    }

    function icon(name) {
        var i = document.createElement('i');
        i.className = 'fa ' + name;
        return i;
    }

    function injectStyle() {
        if (styleInjected || document.getElementById('core2-camera-style')) {
            styleInjected = true;
            return;
        }
        styleInjected = true;
        var css = '' +
            '.core2-camera-overlay{position:fixed;z-index:20000;left:0;top:0;right:0;bottom:0;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;}' +
            '.core2-camera-dialog{background:#fff;border-radius:8px;max-width:440px;width:92%;max-height:88vh;overflow:auto;box-shadow:0 12px 40px rgba(0,0,0,.3);font-size:14px;color:#333;}' +
            '.core2-camera-dialog h4{margin:0;padding:14px 16px;border-bottom:1px solid #e5e5e5;font-size:16px;}' +
            '.core2-camera-list{padding:8px;}' +
            '.core2-camera-item{display:block;width:100%;text-align:left;padding:10px 12px;margin:4px 0;border:1px solid #ddd;border-radius:6px;background:#fafafa;cursor:pointer;font-size:14px;}' +
            '.core2-camera-item:hover{background:#f0f0f0;}' +
            '.core2-camera-item i{margin-right:8px;}' +
            '.core2-camera-actions{display:flex;gap:8px;justify-content:flex-end;padding:12px 16px;border-top:1px solid #e5e5e5;}' +
            '.core2-camera-fullscreen{background:#000;flex-direction:column;align-items:stretch;justify-content:flex-start;}' +
            '.core2-camera-fullscreen video{flex:1 1 auto;width:100%;min-height:0;max-height:none;object-fit:cover;background:#000;display:block;}' +
            '.core2-camera-close{position:absolute;top:10px;right:14px;z-index:2;width:40px;height:40px;border:0;border-radius:50%;background:rgba(0,0,0,.5);color:#fff;font-size:26px;line-height:36px;cursor:pointer;}' +
            '.core2-camera-bottom{flex:0 0 auto;background:rgba(0,0,0,.85);padding:8px 0 14px;}' +
            '.core2-camera-shots{display:flex;flex-wrap:nowrap;overflow-x:auto;overflow-y:hidden;gap:8px;padding:0 12px;height:72px;align-items:center;scrollbar-width:thin;}' +
            '.core2-camera-shots:empty{display:none;}' +
            '.core2-camera-shot{position:relative;flex:0 0 auto;width:64px;height:64px;border-radius:6px;overflow:hidden;border:1px solid rgba(255,255,255,.4);background:#222;}' +
            '.core2-camera-shot img{width:100%;height:100%;object-fit:cover;display:block;}' +
            '.core2-camera-shot.is-uploading::after{content:"";position:absolute;inset:0;background:rgba(0,0,0,.5);}' +
            '.core2-camera-shot.is-uploading::before{content:"";position:absolute;top:50%;left:50%;width:18px;height:18px;margin:-9px 0 0 -9px;border:2px solid rgba(255,255,255,.35);border-top-color:#fff;border-radius:50%;animation:core2-camera-spin .8s linear infinite;z-index:1;}' +
            '.core2-camera-shot.is-uploaded{border-color:#4caf50;}' +
            '.core2-camera-shot.is-error{border-color:#e53935;}' +
            '.core2-camera-shot .core2-camera-error{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);color:#fff;background:#e53935;border-radius:50%;width:20px;height:20px;line-height:20px;text-align:center;font-weight:bold;z-index:2;}' +
            '.core2-camera-controls{position:relative;display:flex;align-items:center;justify-content:center;height:72px;}' +
            '.core2-camera-capture{width:64px;height:64px;border-radius:50%;border:4px solid rgba(255,255,255,.85);background:#fff;color:#d32f2f;font-size:26px;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;}' +
            '.core2-camera-capture:active{transform:scale(.94);}' +
            '.core2-camera-capture:disabled{opacity:.5;cursor:default;}' +
            '.core2-camera-done{position:absolute;right:16px;top:50%;transform:translateY(-50%);border:0;border-radius:20px;background:#d32f2f;color:#fff;font-size:14px;padding:9px 18px;cursor:pointer;}' +
            '.core2-camera-error{color:#ff8a80;font-size:12px;}' +
            '@keyframes core2-camera-spin{to{transform:rotate(360deg);}}';
        var style = document.createElement('style');
        style.id = 'core2-camera-style';
        style.type = 'text/css';
        style.appendChild(document.createTextNode(css));
        document.head.appendChild(style);
    }

    function readDevices() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
            return Promise.resolve([]);
        }
        return navigator.mediaDevices.enumerateDevices().then(function (devices) {
            return (devices || []).filter(function (device) {
                return device.kind === 'videoinput';
            });
        }).catch(function () {
            return [];
        });
    }

    function applyDevices(devices) {
        state.devices = devices;
        state.available = devices.length > 0;
        state.checked = true;
        return devices;
    }

    function ensureDevices() {
        if (!state.pending) {
            state.pending = readDevices().then(applyDevices);
        }
        return state.pending;
    }

    function refreshDevices() {
        state.pending = readDevices().then(applyDevices);
        return state.pending;
    }

    function uniqueDevices() {
        var seen = {};
        var out = [];
        state.devices.forEach(function (device, index) {
            var key = device.deviceId || device.label || ('__default_' + index);
            if (!seen[key]) {
                seen[key] = true;
                out.push(device);
            }
        });
        return out;
    }

    function closeOverlay() {
        if (!activeOverlay) {
            return;
        }
        document.removeEventListener('keydown', activeOverlay.onKey);
        activeOverlay.element.remove();
        activeOverlay = null;
    }

    function overlayShell(title, onClose) {
        injectStyle();
        closeOverlay();

        var overlay = el('div', 'core2-camera-overlay');
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');

        var dialog = el('div', 'core2-camera-dialog');
        dialog.appendChild(el('h4', null, title));
        overlay.appendChild(dialog);
        document.body.appendChild(overlay);

        function requestClose() {
            if (typeof onClose === 'function') {
                onClose();
            } else {
                closeOverlay();
            }
        }

        function onKey(e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                requestClose();
            }
        }

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) {
                requestClose();
            }
        });
        document.addEventListener('keydown', onKey);
        activeOverlay = { element: overlay, onKey: onKey };

        return { element: overlay, dialog: dialog, close: requestClose };
    }

    function fullscreenShell(onClose) {
        injectStyle();
        closeOverlay();

        var overlay = el('div', 'core2-camera-overlay core2-camera-fullscreen');
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        document.body.appendChild(overlay);

        function requestClose() {
            if (typeof onClose === 'function') {
                onClose();
            } else {
                closeOverlay();
            }
        }

        function onKey(e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                requestClose();
            }
        }

        document.addEventListener('keydown', onKey);
        activeOverlay = { element: overlay, onKey: onKey };

        return { element: overlay, close: requestClose };
    }

    function showChooser(onFileManager, onCamera) {
        var shell = overlayShell('Выбор источника файла');
        var list = el('div', 'core2-camera-list');

        var fileManager = el('button', 'core2-camera-item');
        fileManager.type = 'button';
        fileManager.appendChild(icon('fa-folder-open'));
        fileManager.appendChild(document.createTextNode('Файловый менеджер'));
        fileManager.addEventListener('click', function () {
            closeOverlay();
            onFileManager();
        });
        list.appendChild(fileManager);

        uniqueDevices().forEach(function (device, index) {
            var item = el('button', 'core2-camera-item');
            item.type = 'button';
            item.appendChild(icon('fa-camera'));
            item.appendChild(document.createTextNode(device.label || ('Камера ' + (index + 1))));
            item.addEventListener('click', function () {
                closeOverlay();
                onCamera(device);
            });
            list.appendChild(item);
        });

        var actions = el('div', 'core2-camera-actions');
        var cancel = el('button', 'buttonSmall', 'Отмена');
        cancel.type = 'button';
        cancel.addEventListener('click', function () { closeOverlay(); });
        actions.appendChild(cancel);

        shell.dialog.appendChild(list);
        shell.dialog.appendChild(actions);
    }

    function dataURLToBlob(dataURL) {
        var parts = dataURL.split(',');
        var mime = parts[0].match(/:(.*?);/)[1];
        var binary = window.atob(parts[1]);
        var len = binary.length;
        var arr = new Uint8Array(len);
        for (var i = 0; i < len; i++) {
            arr[i] = binary.charCodeAt(i);
        }
        return new Blob([arr], { type: mime });
    }

    function buildFile(blob, index) {
        var name = 'photo_' + Date.now() + '_' + index + '.jpg';
        try {
            return new File([blob], name, { type: blob.type || 'image/jpeg' });
        } catch (e) {
            blob.name = name;
            return blob;
        }
    }

    function triggerUpload(container, file) {
        if (container.getAttribute('data-core2-camera-auto') === '1') {
            return;
        }
        var tries = 0;
        var timer = window.setInterval(function () {
            tries++;
            var rows = container.querySelectorAll('.files .template-upload');
            for (var i = 0; i < rows.length; i++) {
                var nameEl = rows[i].querySelector('.name');
                var start = rows[i].querySelector('button.start');
                if (nameEl && start && nameEl.textContent.indexOf(file.name) !== -1 && !start.disabled) {
                    window.clearInterval(timer);
                    start.click();
                    return;
                }
            }
            if (tries > 60) {
                window.clearInterval(timer);
            }
        }, 50);
    }

    function uploadShot(container, file) {
        var input = container.querySelector('.fileinput-button input[type="file"]');
        if (!input) {
            return false;
        }
        if (typeof DataTransfer === 'undefined') {
            return false;
        }
        var transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        triggerUpload(container, file);
        return true;
    }

    function watchUpload(container, file, shot) {
        var tries = 0;
        var timer = window.setInterval(function () {
            tries++;
            var names = container.querySelectorAll('.files .template-download .name');
            var done = false;
            for (var i = 0; i < names.length; i++) {
                if (names[i].textContent.indexOf(file.name) !== -1) {
                    done = true;
                    break;
                }
            }
            if (done) {
                window.clearInterval(timer);
                shot.classList.remove('is-uploading');
                shot.classList.add('is-uploaded');
            } else if (tries > 160) {
                window.clearInterval(timer);
                shot.classList.remove('is-uploading');
                shot.classList.add('is-error');
                shot.appendChild(el('span', 'core2-camera-error', '!'));
            }
        }, 250);
    }

    function openCamera(device, container) {
        var stream = null;
        var closed = false;
        var shotIndex = 0;
        var canvas = document.createElement('canvas');
        var orientationTimer = null;
        var lastLandscape = window.innerWidth > window.innerHeight;

        var shell = fullscreenShell(function () { cleanup(); });
        var overlay = shell.element;

        var video = document.createElement('video');
        video.className = 'core2-camera-video';
        video.autoplay = true;
        video.muted = true;
        video.setAttribute('playsinline', '');
        video.setAttribute('autoplay', '');
        video.setAttribute('muted', '');

        var close = el('button', 'core2-camera-close');
        close.type = 'button';
        close.innerHTML = '&times;';
        close.title = 'Закрыть';

        var bottom = el('div', 'core2-camera-bottom');
        var shots = el('div', 'core2-camera-shots');
        var controls = el('div', 'core2-camera-controls');
        var capture = el('button', 'core2-camera-capture');
        capture.type = 'button';
        capture.title = 'Снять';
        capture.appendChild(icon('fa-camera'));
        capture.disabled = true;
        var done = el('button', 'core2-camera-done', 'Готово');
        done.type = 'button';

        controls.appendChild(capture);
        controls.appendChild(done);
        bottom.appendChild(shots);
        bottom.appendChild(controls);
        overlay.appendChild(video);
        overlay.appendChild(close);
        overlay.appendChild(bottom);

        function stopStream() {
            if (stream) {
                stream.getTracks().forEach(function (track) { track.stop(); });
                stream = null;
            }
            video.srcObject = null;
        }

        function cleanup() {
            if (closed) {
                return;
            }
            closed = true;
            if (orientationTimer) {
                window.clearTimeout(orientationTimer);
            }
            window.removeEventListener('resize', scheduleOrientation);
            window.removeEventListener('orientationchange', scheduleOrientation);
            if (window.screen && window.screen.orientation && window.screen.orientation.removeEventListener) {
                window.screen.orientation.removeEventListener('change', scheduleOrientation);
            }
            stopStream();
            closeOverlay();
        }

        function addShot(file) {
            var url = URL.createObjectURL(file);
            var shot = el('div', 'core2-camera-shot is-uploading');
            var img = document.createElement('img');
            img.alt = '';
            img.src = url;
            shot.appendChild(img);
            shots.insertBefore(shot, shots.firstChild);
            shots.scrollLeft = 0;

            if (!uploadShot(container, file)) {
                shot.classList.remove('is-uploading');
                shot.appendChild(el('span', 'core2-camera-error', '!'));
                return;
            }
            watchUpload(container, file, shot);
        }

        function startStream() {
            var constraints = {
                audio: false,
                video: device && device.deviceId
                    ? { deviceId: { exact: device.deviceId } }
                    : true
            };
            navigator.mediaDevices.getUserMedia(constraints).then(function (s) {
                if (closed) {
                    s.getTracks().forEach(function (track) { track.stop(); });
                    return;
                }
                stream = s;
                video.srcObject = s;
                var played = video.play();
                if (played && played.catch) {
                    played.catch(function () {});
                }
                capture.disabled = false;
                refreshDevices();
            }).catch(function (err) {
                alert('Не удалось получить доступ к камере: ' + (err && err.message ? err.message : err));
                cleanup();
            });
        }

        function reacquireStream() {
            if (closed || !stream) {
                return;
            }
            capture.disabled = true;
            stopStream();
            startStream();
        }

        function applyOrientation() {
            if (closed) {
                return;
            }
            var landscape = window.innerWidth > window.innerHeight;
            if (landscape === lastLandscape) {
                return;
            }
            lastLandscape = landscape;
            overlay.classList.toggle('is-landscape', landscape);
            overlay.classList.toggle('is-portrait', !landscape);
            reacquireStream();
        }

        function scheduleOrientation() {
            if (orientationTimer) {
                window.clearTimeout(orientationTimer);
            }
            orientationTimer = window.setTimeout(applyOrientation, 300);
        }

        capture.addEventListener('click', function () {
            if (!stream || !video.videoWidth || !video.videoHeight) {
                return;
            }
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            var index = ++shotIndex;
            if (canvas.toBlob) {
                canvas.toBlob(function (blob) {
                    if (blob && !closed) {
                        addShot(buildFile(blob, index));
                    }
                }, 'image/jpeg', 0.92);
            } else if (!closed) {
                addShot(buildFile(dataURLToBlob(canvas.toDataURL('image/jpeg', 0.92)), index));
            }
        });

        close.addEventListener('click', cleanup);
        done.addEventListener('click', cleanup);
        overlay.classList.add('is-portrait');

        window.addEventListener('resize', scheduleOrientation);
        window.addEventListener('orientationchange', scheduleOrientation);
        if (window.screen && window.screen.orientation && window.screen.orientation.addEventListener) {
            window.screen.orientation.addEventListener('change', scheduleOrientation);
        }

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            alert('Камера недоступна в этом браузере.');
            cleanup();
            return;
        }
        startStream();
    }

    function init(container) {
        if (!container || container.dataset.core2CameraInit === '1') {
            return;
        }
        var input = container.querySelector('.fileinput-button input[type="file"]');
        if (!input) {
            return;
        }
        container.dataset.core2CameraInit = '1';

        var allowDialog = false;

        input.addEventListener('click', function (e) {
            if (allowDialog) {
                allowDialog = false;
                return;
            }
            if (!state.checked || !state.available) {
                return;
            }
            e.preventDefault();
            showChooser(
                function () {
                    allowDialog = true;
                    input.click();
                },
                function (device) {
                    openCamera(device, container);
                }
            );
        });
    }

    function scan() {
        var nodes = document.querySelectorAll('[data-core2-camera]');
        for (var i = 0; i < nodes.length; i++) {
            init(nodes[i]);
        }
    }

    function boot() {
        ensureDevices();
        scan();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    window.addEventListener('load', scan);

    window.Core2EditCamera = {
        init: init,
        scan: scan,
        isAvailable: function () { return state.available; },
        devices: function () { return uniqueDevices(); }
    };
})(window, document);
