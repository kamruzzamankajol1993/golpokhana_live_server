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

class DeliveryReportExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(private Collection $orders)
    {
    }

    public function headings(): array
    {
        return [
            'Order #',
            'Branch',
            'Date & Time',
            'Delivery Partner',
            'Customer',
            'Phone',
            'Subtotal',
            'VAT',
            'Discount',
            'Grand Total',
            'Due',
            'Payment',
            'Status',
        ];
    }

    public function collection(): Collection
    {
        return $this->orders->values()->map(function ($order) {
            $partnerLabel = $order->deliveryPartner->name ?? $order->delivery_partner ?? 'N/A';
            $discount = max(0, (float) ($order->product_discount_amount ?? 0))
                + max(0, (float) ($order->discount_amount ?? 0));

            $paymentText = ($order->payment_type ?? '') === 'Card' ? 'Bank / Card' : ($order->payment_type ?? 'N/A');
            if ($paymentText === 'Split') {
                $parts = [];
                if ((float) ($order->paid_in_cash ?? 0) > 0) $parts[] = 'Cash ' . number_format((float) $order->paid_in_cash, 2, '.', '');
                if ((float) ($order->paid_in_card ?? 0) > 0) $parts[] = 'Bank / Card ' . number_format((float) $order->paid_in_card, 2, '.', '');
                if ((float) ($order->paid_in_mfc ?? 0) > 0) $parts[] = 'MFS ' . number_format((float) $order->paid_in_mfc, 2, '.', '');
                if ($parts) $paymentText .= ' (' . implode(', ', $parts) . ')';
            }

            return [
                '#' . $order->order_number,
                $order->branch->name ?? ('Branch #' . $order->branch_id),
                $order->created_at?->format('d M Y h:i A') ?? '',
                $partnerLabel,
                $order->customer->name ?? 'Walk-in Customer',
                $order->customer->phone ?? $order->customer->mobile ?? 'N/A',
                (float) ($order->subtotal ?? 0),
                (float) ($order->vat_tax ?? 0),
                $discount,
                (float) ($order->grand_total ?? 0),
                max(0, (float) ($order->due ?? 0)),
                $paymentText,
                $order->status ?? 'N/A',
            ];
        });
    }

    public function styles(Worksheet $sheet): array
    {
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        $sheet->getStyle('A1:' . $highestColumn . '1')->getFont()->setBold(true);
        $sheet->getStyle('A1:' . $highestColumn . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1:' . $highestColumn . $highestRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle('A1:' . $highestColumn . $highestRow)->getAlignment()->setWrapText(true);
        if ($highestRow > 1) {
            $sheet->getStyle('G2:K' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $sheet->getStyle('A1:' . $highestColumn . $highestRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->freezePane('A2');

        return [];
    }

    public function title(): string
    {
        return 'Delivery Report';
    }
}
