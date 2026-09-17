(function (window, document, $) {
    'use strict';

    var state = {
        checked: false,
        available: false,
        devices: [],
        pending: null
    };

    var styleInjected = false;

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
        var el = document.createElement('style');
        el.id = 'core2-camera-style';
        el.type = 'text/css';
        el.appendChild(document.createTextNode(css));
        document.head.appendChild(el);
    }

    function readDevices() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
            return $.Deferred().resolve([]).promise();
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
        if (state.pending) {
            return state.pending;
        }
        state.pending = readDevices().then(applyDevices);
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

    function closeOverlay($overlay) {
        $overlay.remove();
        $(document).off('keydown.core2camera');
    }

    function overlayShell(title, onClose) {
        injectStyle();
        var $overlay = $('<div class="core2-camera-overlay" role="dialog" aria-modal="true"></div>');
        var $dialog = $('<div class="core2-camera-dialog"></div>');
        $dialog.append($('<h4></h4>').text(title));
        $overlay.append($dialog);
        $('body').append($overlay);

        function requestClose() {
            if (typeof onClose === 'function') {
                onClose();
            } else {
                closeOverlay($overlay);
            }
        }

        $overlay.on('click', function (e) {
            if (e.target === $overlay[0]) {
                requestClose();
            }
        });
        $(document).on('keydown.core2camera', function (e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                requestClose();
            }
        });

        return { $overlay: $overlay, $dialog: $dialog, close: requestClose };
    }

    function showChooser(onFileManager, onCamera) {
        var shell = overlayShell('Выбор источника файла');
        var $list = $('<div class="core2-camera-list"></div>');

        $('<button type="button" class="core2-camera-item"></button>')
            .append($('<i class="fa fa-folder-open"></i>'))
            .append(document.createTextNode('Файловый менеджер'))
            .on('click', function () {
                closeOverlay(shell.$overlay);
                onFileManager();
            })
            .appendTo($list);

        uniqueDevices().forEach(function (device, index) {
            $('<button type="button" class="core2-camera-item"></button>')
                .append($('<i class="fa fa-camera"></i>'))
                .append(document.createTextNode(device.label || ('Камера ' + (index + 1))))
                .on('click', function () {
                    closeOverlay(shell.$overlay);
                    onCamera(device);
                })
                .appendTo($list);
        });

        var $actions = $('<div class="core2-camera-actions"></div>');
        $('<button type="button" class="buttonSmall">Отмена</button>')
            .on('click', function () { closeOverlay(shell.$overlay); })
            .appendTo($actions);

        shell.$dialog.append($list).append($actions);
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
        var $dialog = shell.$dialog;

        var $video = $('<video class="core2-camera-video" autoplay playsinline muted></video>');
        var $hint = $('<div class="core2-camera-hint">Сделайте один или несколько снимков</div>');
        var $shots = $('<div class="core2-camera-shots"></div>');
        var $capture = $('<button type="button" class="core2-camera-capture">Снять</button>');
        var $actions = $('<div class="core2-camera-actions"></div>');
        var $add = $('<button type="button" class="buttonSmall" disabled="disabled">Добавить</button>');
        var $cancel = $('<button type="button" class="buttonSmall">Отмена</button>');

        $actions.append($cancel).append($add);
        $dialog.append($video).append($hint).append($shots).append($capture).append($actions);

        function stopStream() {
            if (stream) {
                stream.getTracks().forEach(function (track) { track.stop(); });
                stream = null;
            }
            if ($video.length) {
                $video[0].srcObject = null;
            }
        }

        function cleanup() {
            if (closed) {
                return;
            }
            closed = true;
            stopStream();
            closeOverlay(shell.$overlay);
        }

        function refresh() {
            $add.prop('disabled', captures.length === 0);
        }

        function addShot(blob) {
            var url = URL.createObjectURL(blob);
            var $shot = $('<div class="core2-camera-shot"></div>');
            $('<img alt="">').attr('src', url).appendTo($shot);
            $('<button type="button" title="Удалить">&times;</button>')
                .on('click', function () {
                    var idx = captures.indexOf(blob);
                    if (idx !== -1) {
                        captures.splice(idx, 1);
                    }
                    URL.revokeObjectURL(url);
                    $shot.remove();
                    refresh();
                })
                .appendTo($shot);
            $shots.append($shot);
            refresh();
        }

        $capture.on('click', function () {
            if (!stream) {
                return;
            }
            var video = $video[0];
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

        $cancel.on('click', cleanup);
        $add.on('click', function () {
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
            $video[0].srcObject = s;
            var played = $video[0].play();
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
        var $fu = $(container);
        if (!$fu.length || typeof $fu.fileupload !== 'function') {
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
        $fu.fileupload('add', { files: files });
        if (!$fu.fileupload('option', 'autoUpload')) {
            $fu.find('.fileupload-buttonbar button.start').removeClass('hide');
        }
    }

    function init(container) {
        var $fu = $(container);
        if (!$fu.length || $fu.data('core2CameraInit')) {
            return;
        }
        var $input = $fu.find('.fileinput-button input[type="file"]');
        if (!$input.length) {
            return;
        }
        $fu.data('core2CameraInit', true);

        var allowDialog = false;

        $input.on('click.core2camera', function (e) {
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
                    $input[0].click();
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
        $('[data-core2-camera]').each(function () {
            init(this);
        });
    }

    function boot() {
        ensureDevices();
        scan();
    }

    $(boot);
    $(window).on('load', scan);
    if (document.readyState !== 'loading') {
        window.setTimeout(boot, 0);
    }

    window.Core2EditCamera = {
        init: init,
        scan: scan,
        isAvailable: function () { return state.available; },
        devices: function () { return uniqueDevices(); }
    };
})(window, document, jQuery);
