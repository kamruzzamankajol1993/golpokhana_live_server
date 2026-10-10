    <div class="footer">
        <strong>Generated on:</strong> {{ now()->format('d M, Y h:i A') }}
        &nbsp; | &nbsp;
        <strong>Prepared for:</strong> {{ $restaurant->name ?? $restaurantSettingName }}
        &nbsp; | &nbsp;
        <strong>Report:</strong> Sales &amp; Order Report
    </div>
