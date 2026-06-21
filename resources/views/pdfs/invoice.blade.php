<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Facture #{{ $order->reference }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { font-size: 24px; margin: 0; color: #1a1a1a; }
        .header p { font-size: 14px; color: #666; margin: 5px 0 0; }
        .meta { margin-bottom: 30px; }
        .meta table { width: 100%; }
        .meta td { vertical-align: top; }
        .meta h3 { font-size: 14px; margin: 0 0 5px; color: #1a1a1a; }
        .meta p { margin: 2px 0; color: #666; font-size: 11px; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        table.items th { background: #f5f5f5; padding: 8px 10px; text-align: left; font-size: 11px; text-transform: uppercase; color: #666; }
        table.items td { padding: 8px 10px; border-bottom: 1px solid #eee; font-size: 12px; }
        table.items td.right { text-align: right; }
        table.items td.center { text-align: center; }
        .totals { text-align: right; margin-top: 20px; }
        .totals table { margin-left: auto; }
        .totals td { padding: 4px 10px; font-size: 12px; }
        .totals .grand-total td { font-size: 16px; font-weight: bold; padding-top: 10px; border-top: 2px solid #333; }
        .footer { text-align: center; margin-top: 40px; color: #999; font-size: 10px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 10px; }
        .badge-paid { background: #d4edda; color: #155724; }
        .badge-pending { background: #fff3cd; color: #856404; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $company['name'] }}</h1>
        <p>{{ $company['email'] }}</p>
        <h2 style="margin-top: 20px; font-size: 18px;">FACTURE #{{ $order->reference }}</h2>
    </div>

    <div class="meta">
        <table>
            <tr>
                <td>
                    <h3>Client</h3>
                    <p>{{ $order->client->name }}</p>
                    <p>{{ $order->client->phone }}</p>
                    @if($order->contact_phone_2)
                        <p>Alt: {{ $order->contact_phone_2 }}</p>
                    @endif
                    @if($order->contact_phone_3)
                        <p>Alt: {{ $order->contact_phone_3 }}</p>
                    @endif
                    @if($order->client && $order->client->email)
                        <p>{{ $order->client->email }}</p>
                    @endif
                </td>
                <td style="text-align: right;">
                    <h3>Détails</h3>
                    <p>Date commande : {{ $order->created_at->format('d/m/Y') }}</p>
                    <p>Livraison : {{ $order->delivery_due_at?->format('d/m/Y') ?: 'N/A' }}</p>
                    <p>Statut : {{ $order->status->label() }}</p>
                </td>
            </tr>
        </table>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>Étage</th>
                <th>Recette</th>
                <th>Forme</th>
                <th>Dimensions</th>
                <th class="right">Prix</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->levels as $level)
                <tr>
                    <td>Étage {{ $level->level_number }}</td>
                    <td>{{ $level->recipe?->name ?? 'Sur mesure' }}</td>
                    <td>{{ $level->shape?->label() ?? '—' }}</td>
                    <td>
                        @if($level->diameter_cm)
                            Ø {{ $level->diameter_cm }} cm
                        @elseif($level->width_cm && $level->length_cm)
                            {{ $level->width_cm }} × {{ $level->length_cm }} cm
                        @endif
                        @if($level->height_cm) / H {{ $level->height_cm }} cm @endif
                    </td>
                    <td class="right">—</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <table>
            @php
                $totalPaid = $order->total_paid;
                $balance = $order->total_amount - $totalPaid;
            @endphp
            <tr>
                <td>Total commande</td>
                <td>{{ number_format($order->total_amount, 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr>
                <td>Total payé</td>
                <td>{{ number_format($totalPaid, 0, ',', ' ') }} FCFA</td>
            </tr>
            @if($balance > 0)
                <tr>
                    <td>Solde restant</td>
                    <td>{{ number_format($balance, 0, ',', ' ') }} FCFA</td>
                </tr>
            @endif
            <tr class="grand-total">
                <td>Statut</td>
                <td>
                    @if($balance <= 0)
                        <span class="badge badge-paid">Payée</span>
                    @else
                        <span class="badge badge-pending">Solde dû</span>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    @if($order->notes)
        <div style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #eee;">
            <h3 style="font-size: 13px; margin: 0 0 5px;">Notes</h3>
            <p style="font-size: 11px; color: #666;">{{ $order->notes }}</p>
        </div>
    @endif

    <div class="footer">
        <p>{{ $company['name'] }} — Document généré le {{ now()->format('d/m/Y à H:i') }}</p>
        <p>Merci de votre confiance !</p>
    </div>
</body>
</html>
