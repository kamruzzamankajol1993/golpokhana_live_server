<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OrdersExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles, WithTitle
{
    protected Collection $orders;

    public function __construct(Collection $orders)
    {
        $this->orders = $orders;
    }

    public function headings(): array
    {
        return [
            'Order #',
            'Customer',
            'Items',
            'Subtotal',
            'Discount Amount',
            'Service Charge',
            'Tips',
            'Given Money',
            'Change',
            'Grand Total',
            'Payment',
            'Status',
            'Time',
            'Kitchen to Payment',
        ];
    }

    public function collection(): Collection
    {
        return $this->orders->values()->map(function ($order) {
            $previewItems = $order->orderDetails->take(2)->pluck('product_name')->implode(', ');
            $remaining = $order->orderDetails->count() - 2;
            $itemsText = $previewItems ?: '—';

            if ($remaining > 0) {
                $itemsText .= "\n+{$remaining} more item(s)";
            }

            $orderType = strtolower((string) $order->order_type);
            $tableText = in_array($orderType, ['takeaway'], true)
                ? 'Takeaway'
                : 'Table T-' . ($order->table->table_number ?? 'N/A');

            $discountAmount = max(0, (float)($order->discount_amount ?? 0));
            $serviceCharge = max(0, (float)($order->service_charge ?? 0));
            $tipsAmount = max(0, (float)($order->tips_amount ?? ((float)($order->total_paid_amount ?? 0) - (float)($order->grand_total ?? 0))));
            $givenMoney = max(0, (float)($order->given_money ?? 0));
            $changeAmount = max(0, (float)($order->change_amount ?? 0));
            $kitchenToPayment = is_null($order->kitchen_to_payment_minutes) ? '—' : $order->kitchen_to_payment_minutes . ' min';

            // Split Payment formatting
            $paymentText = $order->payment_type ?? 'N/A';
            if ($paymentText === 'Split') {
                $splits = [];
                if((float)$order->paid_in_cash > 0) $splits[] = 'Cash: ' . $order->paid_in_cash;
                if((float)$order->paid_in_card > 0) $splits[] = 'Card: ' . $order->paid_in_card;
                if((float)$order->paid_in_mfc > 0) $splits[] = 'MFC: ' . $order->paid_in_mfc;
                if(!empty($splits)) {
                    $paymentText .= "\n(" . implode(', ', $splits) . ")";
                }
            }

            return [
                '#' . $order->order_number,
                ($order->customer->name ?? 'Walk-in Customer') . "\n" . $tableText,
                $itemsText,
                (float) ($order->subtotal ?? 0),
                $discountAmount,
                $serviceCharge,
                $tipsAmount,
                $givenMoney,
                $changeAmount,
                (float) ($order->grand_total ?? 0),
                $paymentText,
                $order->status ?? 'N/A',
                $order->created_at ? $order->created_at->format('d M Y, h:i A') : '—',
                $kitchenToPayment,
            ];
        });
    }

    public function styles(Worksheet $sheet): array
    {
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        // Header style
        $sheet->getStyle('A1:' . $highestColumn . '1')->getFont()->setBold(true);
        $sheet->getStyle('A1:' . $highestColumn . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Text wrap for readable multi-line values
        $sheet->getStyle('A1:' . $highestColumn . $highestRow)->getAlignment()->setWrapText(true);
        $sheet->getStyle('A1:' . $highestColumn . $highestRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        // Money columns right aligned (D to J)
        $sheet->getStyle('D2:J' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Center alignment for Payment, Status, Time, etc. (K to N)
        $sheet->getStyle('K2:N' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Border for table readability
        $sheet->getStyle('A1:' . $highestColumn . $highestRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        return [];
    }

    public function title(): string
    {
        return 'Order Report';
    }
}
