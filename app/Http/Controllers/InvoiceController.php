<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends Controller
{
    public function download(Order $order)
    {
        $this->authorize('gerantOrGhost');

        $order->load(['levels.recipe', 'transactions', 'client']);

        $pdf = Pdf::loadView('pdfs.invoice', [
            'order' => $order,
            'company' => [
                'name' => Setting::getValue('company_name', 'Pâtisserie Sur Mesure'),
                'email' => Setting::getValue('notification_email', ''),
            ],
        ]);

        return $pdf->download('facture-'.$order->reference.'.pdf');
    }

    public function preview(Order $order)
    {
        $this->authorize('gerantOrGhost');

        $order->load(['levels.recipe', 'transactions', 'client']);

        $pdf = Pdf::loadView('pdfs.invoice', [
            'order' => $order,
            'company' => [
                'name' => Setting::getValue('company_name', 'Pâtisserie Sur Mesure'),
                'email' => Setting::getValue('notification_email', ''),
            ],
        ]);

        return $pdf->stream('facture-'.$order->reference.'.pdf');
    }
}
