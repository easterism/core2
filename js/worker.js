const clients = new Set();
let eventSrc = null;

function setupEventSource() {
    if (eventSrc) return eventSrc;

    eventSrc = new EventSource('../../sse');

    eventSrc.addEventListener("message", function (event) {
        const d = JSON.parse(event.data);
        clients.forEach(client => {
            try {
                if (d?.done) {
                    client.postMessage({type: "Core2", event: d.done});
                } else {
                    client.postMessage({type: "modules", event: d});
                }
            } catch (e) {
                // Клиент отключился
                clients.delete(client);
            }
        });
    });

    eventSrc.addEventListener('open', function () {
        clients.forEach(client => {
            try {
                client.postMessage("SSE open");
            } catch (e) {
                clients.delete(client);
            }
        });
    });

    eventSrc.addEventListener('error', function (event) {
        if (eventSrc.readyState === EventSource.CLOSED) {
            clients.forEach(client => {
                try {
                    client.postMessage('ERROR: SSE connection closed');
                } catch (e) {
                    clients.delete(client);
                }
            });
            eventSrc = null;
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
        if (clients.size === 0 && eventSrc) {
            eventSrc.close();
            eventSrc = null;
        }
    });

    port.start();
});
