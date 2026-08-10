<script>
$(function () {
    HrUi.initSelect2('.employee-form-select2');

    document.querySelectorAll('.employee-date').forEach(function (element) {
        HrUi.initFlatpickr(element);
    });

    function toggleAccessFields() {
        const waiterEnabled = $('#employeeIsWaiter').is(':checked');
        const loginEnabled = $('#employeeCanLogin').is(':checked');
        const status = HrUi.selectValue('employeeEmploymentStatus');

        $('#waiterAccessFields').toggle(waiterEnabled);
        $('#loginAccessFields').toggle(loginEnabled);
        $('#employeeExitDateWrap').toggle(['resigned', 'terminated'].includes(status));
    }

    $('#employeeIsWaiter, #employeeCanLogin').on('change', toggleAccessFields);
    $('#employeeEmploymentStatus').on('change', toggleAccessFields);

    $('#employeeImage').on('change', function () {
        if (this.files && this.files[0]) {
            $('#employeeImagePreview').attr('src', URL.createObjectURL(this.files[0]));
        }
    });

    $('#employeeForm').on('submit', function () {
        $(this).find('button[type="submit"]').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');
    });

    @if(session('error'))
        Swal.fire('Error', @json(session('error')), 'error');
    @endif

    toggleAccessFields();
});
</script>
