<?php

use App\Helpers\WhatsApp;
use App\Models\Order;
use App\Models\WhatsAppTemplate;

test('whatsapp template returns correct message with replacements', function () {
    $template = WhatsAppTemplate::factory()->create([
        'key' => 'test_greeting',
        'message' => 'Bonjour {client_name}, votre commande {reference} est prête.',
        'is_active' => true,
    ]);

    $message = WhatsAppTemplate::getMessage('test_greeting', [
        'client_name' => 'Jean',
        'reference' => 'CMD-001',
    ]);

    expect($message)->toBe('Bonjour Jean, votre commande CMD-001 est prête.');
});

test('whatsapp template returns null for inactive template', function () {
    $template = WhatsAppTemplate::factory()->create([
        'key' => 'inactive_test',
        'is_active' => false,
    ]);

    $message = WhatsAppTemplate::getMessage('inactive_test', []);

    expect($message)->toBeNull();
});

test('whatsapp template returns null for non-existent key', function () {
    $message = WhatsAppTemplate::getMessage('non_existent_key', []);

    expect($message)->toBeNull();
});

test('whatsapp helper generates correct link', function () {
    $template = WhatsAppTemplate::factory()->create([
        'key' => 'order_ready_test',
        'message' => 'Bonjour {client_name}, commande {reference} prête !',
        'is_active' => true,
    ]);

    $link = WhatsApp::link('+237 690 000 000', 'order_ready_test', [
        'client_name' => 'Jean',
        'reference' => 'CMD-001',
    ]);

    expect($link)->toStartWith('https://wa.me/237690000000?text=');
    expect($link)->toContain(urlencode('Bonjour Jean, commande CMD-001 prête !'));
});

test('whatsapp helper generates link for order', function () {
    WhatsAppTemplate::factory()->create([
        'key' => 'order_contact',
        'message' => 'Bonjour {client_name}, commande {reference}.',
        'is_active' => true,
    ]);

    $client = \App\Models\Client::factory()->create(['name' => 'Marie', 'phone' => '690000001']);
    $order = Order::factory()->create([
        'client_id' => $client->id,
        'client_name' => 'Marie',
        'client_phone' => '690000001',
        'reference' => 'CMD-002',
    ]);

    $link = WhatsApp::linkForOrder($order, 'order_contact');

    expect($link)->toStartWith('https://wa.me/690000001?text=');
    expect($link)->toContain(urlencode('Bonjour Marie'));
});
