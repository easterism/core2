let connected = false;
let eventSrc = null;
self.addEventListener(
    "connect",
    (e) => {
        e.source.addEventListener(
            "message",
            (ev) => {
                if (ev.data === "start") {
                    if (connected === false) {
                        e.source.postMessage("worker init");
                        connected = true;
                    } else {
                        e.source.postMessage("worker already inited");
                    }
                } else if (ev.data === "sse-open") {
                    e.source.postMessage("SSE init");

                    var that = e.source;
                    if (eventSrc === null) {
                        eventSrc  = new EventSource('../../sse');
                        e.source.postMessage("SSE " + eventSrc.readyState);
                    } else {
                        e.source.postMessage("SSE in state " + eventSrc.readyState);
                    }

                    eventSrc.addEventListener('message', function (event) {

                        that.postMessage({type: "modules", event: JSON.parse(event.data)});

                    }, false);
                    eventSrc.addEventListener('Core2', function (event) {

                        that.postMessage({type: "Core2", event: event.data});

                    }, false);

                    eventSrc.addEventListener('open', function (event) {
                        that.postMessage("SSE open");
                    }, false);
                    eventSrc.addEventListener('error', function (event) {
                        if (event.eventPhase === eventSrc.CLOSED) {
                            // Соединение было закрыто
                            that.postMessage('ERROR: SSE connection closed')
                            eventSrc = null;
                        } else {
                            that.postMessage('ERROR: SSE unknown error occurred');
                        }
                    }, false);

                }  else if (ev.data === "sse-close") {
                    eventSrc.close();
                    that.postMessage('SSE closed')
                    eventSrc = null;
                } else {
                    //any other behavior
                }
            },
            false,
        );
        e.source.start();
    },
    false,
);
