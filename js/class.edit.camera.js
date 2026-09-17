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
            '.core2-camera-video{width:100%;background:#000;display:block;max-height:60vh;}' +
            '.core2-camera-shots{display:flex;flex-wrap:wrap;gap:6px;padding:8px 16px;}' +
            '.core2-camera-shot{position:relative;width:72px;height:72px;border-radius:4px;overflow:hidden;border:1px solid #ccc;}' +
            '.core2-camera-shot img{width:100%;height:100%;object-fit:cover;}' +
            '.core2-camera-shot button{position:absolute;top:2px;right:2px;border:0;background:rgba(0,0,0,.6);color:#fff;border-radius:50%;width:18px;height:18px;line-height:16px;font-size:12px;cursor:pointer;padding:0;}' +
            '.core2-camera-capture{display:block;margin:10px auto;padding:10px 22px;border:0;border-radius:24px;background:#d32f2f;color:#fff;font-size:15px;cursor:pointer;}' +
            '.core2-camera-hint{padding:8px 16px 0;color:#777;font-size:12px;}';
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

    function openCamera(device, onDone) {
        var stream = null;
        var captures = [];
        var closed = false;
        var canvas = document.createElement('canvas');

        var shell = overlayShell('Съёмка фото', function () { cleanup(); });
        var dialog = shell.dialog;

        var video = document.createElement('video');
        video.className = 'core2-camera-video';
        video.autoplay = true;
        video.muted = true;
        video.setAttribute('playsinline', '');
        video.setAttribute('muted', '');

        var hint = el('div', 'core2-camera-hint', 'Сделайте один или несколько снимков');
        var shots = el('div', 'core2-camera-shots');
        var capture = el('button', 'core2-camera-capture', 'Снять');
        capture.type = 'button';
        var actions = el('div', 'core2-camera-actions');
        var add = el('button', 'buttonSmall', 'Добавить');
        add.type = 'button';
        add.disabled = true;
        var cancel = el('button', 'buttonSmall', 'Отмена');
        cancel.type = 'button';

        actions.appendChild(cancel);
        actions.appendChild(add);
        dialog.appendChild(video);
        dialog.appendChild(hint);
        dialog.appendChild(shots);
        dialog.appendChild(capture);
        dialog.appendChild(actions);

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
            stopStream();
            closeOverlay();
        }

        function refresh() {
            add.disabled = captures.length === 0;
        }

        function addShot(blob) {
            var url = URL.createObjectURL(blob);
            var shot = el('div', 'core2-camera-shot');
            var img = document.createElement('img');
            img.alt = '';
            img.src = url;
            shot.appendChild(img);

            var remove = el('button');
            remove.type = 'button';
            remove.title = 'Удалить';
            remove.innerHTML = '&times;';
            remove.addEventListener('click', function () {
                var idx = captures.indexOf(blob);
                if (idx !== -1) {
                    captures.splice(idx, 1);
                }
                URL.revokeObjectURL(url);
                shot.remove();
                refresh();
            });
            shot.appendChild(remove);
            shots.appendChild(shot);
            refresh();
        }

        capture.addEventListener('click', function () {
            if (!stream) {
                return;
            }
            if (!video.videoWidth || !video.videoHeight) {
                return;
            }
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            if (canvas.toBlob) {
                canvas.toBlob(function (blob) {
                    if (blob) {
                        addShot(blob);
                    }
                }, 'image/jpeg', 0.92);
            } else {
                addShot(dataURLToBlob(canvas.toDataURL('image/jpeg', 0.92)));
            }
        });

        cancel.addEventListener('click', cleanup);
        add.addEventListener('click', function () {
            var result = captures.slice();
            cleanup();
            onDone(result);
        });

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            alert('Камера недоступна в этом браузере.');
            cleanup();
            return;
        }

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
            refreshDevices();
        }).catch(function (err) {
            alert('Не удалось получить доступ к камере: ' + (err && err.message ? err.message : err));
            cleanup();
        });
    }

    function submitCaptures(container, captures) {
        var input = container.querySelector('.fileinput-button input[type="file"]');
        if (!input) {
            return;
        }
        var stamp = Date.now();
        var files = captures.map(function (blob, index) {
            var name = 'photo_' + stamp + '_' + (index + 1) + '.jpg';
            try {
                return new File([blob], name, { type: blob.type || 'image/jpeg' });
            } catch (e) {
                blob.name = name;
                return blob;
            }
        });

        if (typeof DataTransfer === 'undefined') {
            alert('Браузер не поддерживает добавление снимков в форму.');
            return;
        }

        var transfer = new DataTransfer();
        files.forEach(function (file) {
            transfer.items.add(file);
        });
        input.files = transfer.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
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
                    openCamera(device, function (captures) {
                        submitCaptures(container, captures);
                    });
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
