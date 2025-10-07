(() => {
    const meta = document.querySelector('meta[name="server-now-ms"]');
    if (!meta) {
        return;
    }

    const serverValue = Number(meta.getAttribute('content'));
    if (!Number.isFinite(serverValue)) {
        return;
    }

    const skewMs = serverValue - Date.now();
    window.mesasNowMs = function () {
        return Date.now() + skewMs;
    };
})();
