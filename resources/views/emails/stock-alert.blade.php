<x-mail::message>
# Alerte Stock Critique

L'ingrédient **{{ $ingredient->name }}** a atteint un niveau critique.

- **Stock actuel :** {{ number_format($currentStock, 2, ',', ' ') }} {{ $ingredient->unit->value }}
- **Seuil d'alerte :** {{ number_format($ingredient->alert_threshold, 2, ',', ' ') }} {{ $ingredient->unit->value }}
- **Déclenché par :** {{ $triggeredBy }}

<x-mail::button :url="route('stock.index')">
Consulter le Stock
</x-mail::button>

Merci de procéder au réapprovisionnement dès que possible.

@lang('Rapport généré automatiquement')
</x-mail::message>
