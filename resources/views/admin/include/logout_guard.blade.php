<script>
(function () {
    const logoutUrl = @json(route('logout'));

    function setLogoutBusy(form, busy) {
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (button) {
            button.disabled = busy;
        });
    }

    function showLogoutBlocked(data) {
        const message = (data && data.message)
            ? data.message
            : 'Logout blocked. Please clear occupied tables and complete or cancel today\'s pending Takeaway/Delivery orders.';

        if (window.Swal) {
            window.Swal.fire({
                icon: 'warning',
                title: 'Logout Blocked',
                text: message,
                confirmButtonText: 'OK'
            });
        } else {
            window.alert(message);
        }
    }

    async function guardedLogout(form) {
        if (form.dataset.logoutSubmitting === '1') return;

        form.dataset.logoutSubmitting = '1';
        setLogoutBusy(form, true);

        try {
            const response = await fetch(logoutUrl, {
                method: 'POST',
                credentials: 'same-origin',
                redirect: 'follow',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new FormData(form)
            });

            if (response.status === 409) {
                let data = {};
                try {
                    data = await response.json();
                } catch (e) {}

                showLogoutBlocked(data);
                return;
            }

            if (!response.ok) {
                let message = 'Logout could not be completed. Please try again.';
                try {
                    const data = await response.json();
                    if (data && data.message) message = data.message;
                } catch (e) {}

                if (window.Swal) {
                    window.Swal.fire('Logout Failed', message, 'error');
                } else {
                    window.alert(message);
                }
                return;
            }

            window.location.href = response.url || '/';
        } catch (error) {
            if (window.Swal) {
                window.Swal.fire('Logout Failed', 'Could not check the current POS status. Please try again.', 'error');
            } else {
                window.alert('Could not check the current POS status. Please try again.');
            }
        } finally {
            form.dataset.logoutSubmitting = '0';
            setLogoutBusy(form, false);
        }
    }

    document.addEventListener('submit', function (event) {
        const form = event.target.closest('form[data-pos-logout="1"]');
        if (!form) return;

        event.preventDefault();
        guardedLogout(form);
    });
})();
</script>
