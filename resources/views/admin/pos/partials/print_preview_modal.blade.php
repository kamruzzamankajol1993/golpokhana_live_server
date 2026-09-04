{{--
    Direct browser print helper.

    The old custom print-preview modal has intentionally been removed. Any existing
    .js-pos-print-preview trigger, or any existing call to openPosPrintPreview(), now
    loads the printable route into an off-screen same-origin iframe and immediately
    opens the browser's native print dialog. When the dialog is closed (Print or
    Cancel), the caller returns to the POS table when returnToPos is enabled.
--}}
<script>
(function () {
    const posUrl = @json(route('pos.index'));
    let activePrintFrame = null;
    let activeCleanupTimer = null;
    let printSequence = 0;

    function withEmbeddedFlag(url) {
        const target = new URL(url, window.location.href);
        target.searchParams.set('embedded', '1');
        return target.toString();
    }

    function removeActiveFrame() {
        if (activeCleanupTimer) {
            window.clearTimeout(activeCleanupTimer);
            activeCleanupTimer = null;
        }
        if (activePrintFrame) {
            try { activePrintFrame.remove(); } catch (e) {}
            activePrintFrame = null;
        }
    }

    function finishPrint(returnToPos, token) {
        if (token !== printSequence) return;
        removeActiveFrame();

        if (returnToPos) {
            // Print and Cancel both land back on the POS table. Using replace avoids
            // leaving a transient print state in browser history.
            window.location.replace(posUrl);
        }
    }

    window.openPosPrintPreview = function (url, title, options) {
        options = options || {};
        if (!url) return;

        const returnToPos = options.returnToPos !== false;
        const token = ++printSequence;
        removeActiveFrame();

        const frame = document.createElement('iframe');
        activePrintFrame = frame;
        frame.setAttribute('aria-hidden', 'true');
        frame.setAttribute('title', title || 'Print');
        frame.style.position = 'fixed';
        frame.style.left = '-10000px';
        frame.style.top = '0';
        frame.style.width = '1px';
        frame.style.height = '1px';
        frame.style.opacity = '0';
        frame.style.pointerEvents = 'none';
        frame.style.border = '0';

        let completed = false;
        const done = function () {
            if (completed) return;
            completed = true;
            finishPrint(returnToPos, token);
        };

        frame.onload = function () {
            if (token !== printSequence) return;

            let printWindow;
            try {
                printWindow = frame.contentWindow;
                if (!printWindow) throw new Error('Print frame is unavailable.');

                // afterprint fires for both Print and Cancel in modern Chromium/Firefox.
                printWindow.addEventListener('afterprint', done, { once: true });

                // A focus fallback covers browser builds/extensions that suppress
                // iframe afterprint. It is armed only after the print dialog opens.
                let focusArmed = false;
                const onParentFocus = function () {
                    if (focusArmed) {
                        window.removeEventListener('focus', onParentFocus);
                        window.setTimeout(done, 150);
                    }
                };
                window.addEventListener('focus', onParentFocus);

                window.setTimeout(function () {
                    if (token !== printSequence) return;
                    try {
                        printWindow.focus();
                        focusArmed = true;
                        printWindow.print();
                    } catch (error) {
                        window.removeEventListener('focus', onParentFocus);
                        done();
                    }
                }, 120);
            } catch (error) {
                done();
            }
        };

        frame.onerror = done;
        document.body.appendChild(frame);
        frame.src = withEmbeddedFlag(url);

        // Safety cleanup only. Normally afterprint/focus handles completion.
        activeCleanupTimer = window.setTimeout(function () {
            if (token === printSequence && !completed) {
                done();
            }
        }, 120000);
    };

    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('.js-pos-print-preview');
        if (!trigger) return;

        event.preventDefault();
        window.openPosPrintPreview(
            trigger.getAttribute('href') || trigger.dataset.url,
            trigger.dataset.title || 'Print',
            { returnToPos: trigger.dataset.returnPos !== '0' }
        );
    });
})();
</script>
