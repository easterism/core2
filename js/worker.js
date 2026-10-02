const clients = new Set();
let eventSrc = null;
let reconnectTimeout = null;
const RECONNECT_DELAY = 3000;

function scheduleReconnect() {
    if (reconnectTimeout) return;
    if (clients.size === 0) return;

    reconnectTimeout = setTimeout(() => {
        reconnectTimeout = null;
        if (clients.size > 0 && !eventSrc) {
            setupEventSource();
        }
    }, RECONNECT_DELAY);
}

function broadcast(fn) {
    clients.forEach(client => {
        try {
            fn(client);
        } catch (e) {
            clients.delete(client);
        }
    });
}

function setupEventSource() {
    if (eventSrc) return eventSrc;

    eventSrc = new EventSource('../../sse');

    eventSrc.addEventListener("message", function (event) {
        const d = JSON.parse(event.data);
        broadcast(client => {
            if (d?.done) {
                client.postMessage({type: "Core2", event: d.done});
            } else {
                client.postMessage({type: "modules", event: d});
            }
        });
    });

    eventSrc.addEventListener('open', function () {
        broadcast(client => client.postMessage("SSE open"));
    });

    eventSrc.addEventListener('error', function (event) {
        if (eventSrc && eventSrc.readyState === EventSource.CLOSED) {
            broadcast(client => client.postMessage('ERROR: SSE connection closed'));
            eventSrc.close();
            eventSrc = null;
            scheduleReconnect();
        }
    });

    return eventSrc;
}
self.addEventListener("connect", (e) => {
    const port = e.source;
    clients.add(port);

    port.addEventListener("message", (ev) => {
        switch (ev.data) {
            case "start":
                port.postMessage(clients.size === 1 ? "worker init" : "worker already inited");
                break;

            case "sse-open":
                const es = setupEventSource();
                port.postMessage("SSE in state " + es.readyState);
                break;

            case "sse-close":
                if (eventSrc) {
                    eventSrc.close();
                    eventSrc = null;
                }
                port.postMessage('SSE closed');
                break;
        }
    });

    port.addEventListener("close", () => {
        clients.delete(port);
        // ВАЖНО: НЕ закрываем eventSrc при уходе последнего клиента,
    });

    port.start();
});
