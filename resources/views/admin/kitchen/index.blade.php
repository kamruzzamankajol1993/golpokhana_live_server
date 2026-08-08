<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Kitchen Board — TableTrack RMS</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="{{ asset('public/admin/assets/css/progga-style.css') }}">
</head>
<body class="progga-kitchen-page">

  <header class="progga-kitchen-header">
    <div class="progga-kitchen-brand">
      <div class="progga-kitchen-logo">T</div>
      <div>
        <div class="progga-kitchen-title">Kitchen Board</div>
        <div class="progga-kitchen-subtitle">Live Order Queue</div>
      </div>
    </div>

    <div class="progga-kitchen-header-stats">
      <div class="progga-kitchen-stat progga-kitchen-stat--pending">
        <div class="progga-kitchen-stat-num" id="statPending">0</div>
        <div class="progga-kitchen-stat-label">Pending</div>
      </div>
      <div class="progga-kitchen-stat progga-kitchen-stat--cooking">
        <div class="progga-kitchen-stat-num" id="statCooking">0</div>
        <div class="progga-kitchen-stat-label">Cooking</div>
      </div>
      <div class="progga-kitchen-stat progga-kitchen-stat--ready">
        <div class="progga-kitchen-stat-num" id="statReady">0</div>
        <div class="progga-kitchen-stat-label">Ready</div>
      </div>
    </div>

    <div class="progga-kitchen-header-actions">
      <div class="progga-live-indicator"><span class="progga-live-dot"></span> Live</div>
      <div class="progga-kitchen-refresh-info">
        <i class="bi bi-arrow-clockwise" id="kitchenRefreshIcon"></i>
        Refresh in <strong id="kitchenCountdown">10s</strong>
      </div>
      <button class="progga-kitchen-hdr-btn" id="kitchenRefreshBtn" type="button" onclick="loadLiveOrders()">
        <i class="bi bi-arrow-clockwise"></i> Refresh
      </button>
      <a href="{{ route('home') }}" class="progga-kitchen-hdr-btn">
        <i class="bi bi-arrow-left"></i> Dashboard
      </a>
    </div>
  </header>

  <div class="progga-kitchen-board" id="kitchenBoardArea">
      </div>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script>
    function getCsrfToken() {
        return $('meta[name="csrf-token"]').attr('content');
    }

    function setCsrfToken(token) {
        if (!token) return;
        $('meta[name="csrf-token"]').attr('content', token);
    }

    $.ajaxSetup({
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        beforeSend: function(xhr) {
            // Kitchen board long time open thakte pare, tai prottek AJAX request e latest CSRF token pathano hocche.
            xhr.setRequestHeader('X-CSRF-TOKEN', getCsrfToken());
        }
    });

    function refreshCsrfToken() {
        return $.ajax({
            url: "{{ route('csrf.refresh') }}",
            type: "GET",
            cache: false,
            success: function(res) {
                setCsrfToken(res.csrf_token);
            }
        });
    }

    function postWithCsrfRetry(ajaxOptions, retried = false) {
        let options = $.extend(true, {}, ajaxOptions);
        let originalError = ajaxOptions.error;

        // Laravel _token input ke header er age check kore, tai retry er age _token fresh rakha hocche.
        if (options.data && typeof options.data === 'object') {
            options.data._token = getCsrfToken();
        }

        options.error = function(xhr) {
            if (xhr.status === 419 && !retried) {
                refreshCsrfToken().done(function() {
                    postWithCsrfRetry(ajaxOptions, true);
                }).fail(function() {
                    if (typeof originalError === 'function') {
                        originalError(xhr);
                    }
                });
                return;
            }

            if (typeof originalError === 'function') {
                originalError(xhr);
            }
        };

        return $.ajax(options);
    }

    let countdown = 10;
    let timerInterval;

    // 10 Second Auto Refresh Logic
    function startTimer() {
        clearInterval(timerInterval);
        countdown = 10;
        $('#kitchenCountdown').text(countdown + 's');

        timerInterval = setInterval(() => {
            countdown--;
            if (countdown <= 0) {
                loadLiveOrders();
            } else {
                $('#kitchenCountdown').text(countdown + 's');
            }
        }, 1000);
    }

    // Fetch Live Orders
    function loadLiveOrders() {
        let refreshIcon = $('#kitchenRefreshIcon');
        refreshIcon.addClass('spin'); // Optional CSS for spinning

        $.ajax({
            url: "{{ route('kitchen.get_live_orders') }}",
            type: "GET",
            success: function(res) {
                if (res.csrfToken) {
                    setCsrfToken(res.csrfToken);
                }

                $('#kitchenBoardArea').html(res.html);
                $('#statPending').text(res.pendingCount);
                $('#statCooking').text(res.cookingCount);
                $('#statReady').text(res.readyCount);

                // Restart Timer
                refreshIcon.removeClass('spin');
                startTimer();
            },
            error: function() {
                refreshIcon.removeClass('spin');
                startTimer();
            }
        });
    }

    // Change Status (Pending -> Cooking -> Ready -> Delivered)
    $(document).on('click', '.update-kot-status', function() {
        let kotId = $(this).data('kot-id');
        let newStatus = $(this).data('status');
        let btn = $(this);
        let originalHtml = btn.html();

        btn.html('<i class="spinner-border spinner-border-sm"></i>').prop('disabled', true);

        postWithCsrfRetry({
            url: "{{ route('kitchen.update_status') }}",
            type: "POST",
            data: {
                kot_id: kotId,
                status: newStatus
            },
            success: function(res) {
                if (res.csrfToken) {
                    setCsrfToken(res.csrfToken);
                }

                if(res.status === 'success') {
                    loadLiveOrders(); // Refresh board immediately
                } else {
                    btn.html(originalHtml).prop('disabled', false);
                    alert(res.message || "Status update failed!");
                }
            },
            error: function() {
                btn.html(originalHtml).prop('disabled', false);
                alert("Something went wrong! Please try again.");
            }
        });
    });

    // Live Cooking Countdown Timer Logic
    setInterval(function() {
        $('.cooking-timer').each(function() {
            let sec = parseInt($(this).attr('data-seconds'));
            if(isNaN(sec)) return;

            sec--; // Prottek second e kombe
            $(this).attr('data-seconds', sec);

            let display = $(this).find('.time-display');

            if(sec <= 0) {
                // Time cross hoye gele lal rong (Overdue) dekhabe
                $(this).css({'background': '#f8d7da', 'color': '#721c24'});
                let over = Math.abs(sec);
                let m = Math.floor(over / 60);
                let s = over % 60;

                // Formatted display (e.g., -02:05)
                let mStr = m < 10 ? '0' + m : m;
                let sStr = s < 10 ? '0' + s : s;
                display.text('-' + mStr + ':' + sStr + ' (Late)');
            } else {
                // Normal countdown
                let m = Math.floor(sec / 60);
                let s = sec % 60;

                let mStr = m < 10 ? '0' + m : m;
                let sStr = s < 10 ? '0' + s : s;
                display.text(mStr + ':' + sStr);
            }
        });
    }, 1000);

    // Mark Item as Unavailable (Cross Button Logic)
    $(document).on('click', '.mark-unavailable-btn', function() {
        let detailId = $(this).data('id');
        let btn = $(this);

        if(confirm("Are you sure this item is unavailable? It will be removed from the bill.")) {
            btn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm"></i>');

            postWithCsrfRetry({
                url: "{{ route('kitchen.mark_unavailable') }}",
                type: "POST",
                data: {
                    detail_id: detailId
                },
                success: function(res) {
                    if (res.csrfToken) {
                        setCsrfToken(res.csrfToken);
                    }

                    if(res.status === 'success') {
                        loadLiveOrders(); // বোর্ড রিফ্রেশ করবে
                    } else {
                        btn.prop('disabled', false).html('<i class="bi bi-x"></i>');
                        alert(res.message || "Item update failed!");
                    }
                },
                error: function() {
                    btn.prop('disabled', false).html('<i class="bi bi-x"></i>');
                    alert("Something went wrong! Please try again.");
                }
            });
        }
    });

    // Start everything on page load
    loadLiveOrders();
  </script>
</body>
</html>
