<?php

use App\Enums\OrderStatus;
use App\Enums\TransactionType;
use App\Models\Client;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

if (! function_exists('dashboardUser')) {
    function dashboardUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Gérant/Admin'));

        return $user;
    }
}

test('guest is redirected when accessing dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('any authenticated user can access dashboard', function () {
    $roles = ['Gérant/Admin', 'Chef Pâtissier', 'Pâtissier', 'Caissier', 'Comptable'];
    foreach ($roles as $role) {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($role));
        $this->actingAs($user);

        $this->get(route('dashboard'))->assertOk();
    }
});

test('dashboard shows empty state with no data', function () {
    $user = dashboardUser();
    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertViewHas('totalRevenue', 0)
        ->assertViewHas('totalOrders', 0)
        ->assertViewHas('totalClients', 0)
        ->assertViewHas('totalMargin', 0)
        ->assertViewHas('marginPercentage', 0.0);
});

test('dashboard shows revenue from orders', function () {
    $user = dashboardUser();
    $this->actingAs($user);

    Order::factory()->create(['total_amount' => 10000, 'status' => OrderStatus::CONFIRMÉE]);
    Order::factory()->create(['total_amount' => 25000, 'status' => OrderStatus::CONFIRMÉE]);
    Order::factory()->create(['total_amount' => 5000, 'status' => OrderStatus::ANNULÉE, 'cancelled_at' => now()]);

    Livewire::test('pages::dashboard')
        ->assertViewHas('totalRevenue', 35000);
});

test('dashboard excludes cancelled orders from revenue', function () {
    $user = dashboardUser();
    $this->actingAs($user);

    Order::factory()->create(['total_amount' => 10000, 'cancelled_at' => now()]);

    Livewire::test('pages::dashboard')
        ->assertViewHas('totalRevenue', 0);
});

test('dashboard shows refunds deducted from revenue', function () {
    $user = dashboardUser();
    $this->actingAs($user);

    $order = Order::factory()->create(['total_amount' => 20000, 'status' => OrderStatus::CONFIRMÉE]);
    Transaction::factory()->create([
        'type' => TransactionType::REFUND,
        'amount' => 5000,
        'paid_at' => now(),
        'cancelled_at' => null,
        'order_id' => $order->id,
    ]);

    Livewire::test('pages::dashboard')
        ->assertViewHas('totalRevenue', 20000)
        ->assertViewHas('totalRefunds', 5000)
        ->assertViewHas('netRevenue', 15000);
});

test('dashboard counts orders correctly', function () {
    $user = dashboardUser();
    $this->actingAs($user);

    Order::factory()->create(['status' => OrderStatus::CONFIRMÉE]);
    Order::factory()->create(['status' => OrderStatus::CONFIRMÉE]);
    Order::factory()->create(['status' => OrderStatus::ANNULÉE, 'cancelled_at' => now()]);

    Livewire::test('pages::dashboard')
        ->assertViewHas('totalOrders', 2);
});

test('dashboard counts total clients', function () {
    $user = dashboardUser();
    $this->actingAs($user);

    Client::factory()->create();
    Client::factory()->create();
    Client::factory()->create();

    Livewire::test('pages::dashboard')
        ->assertViewHas('totalClients', 3);
});

test('dashboard shows pending orders count', function () {
    $user = dashboardUser();
    $this->actingAs($user);

    Order::factory()->create(['status' => OrderStatus::CONFIRMÉE, 'cancelled_at' => null]);
    Order::factory()->create(['status' => OrderStatus::LIVRÉE, 'cancelled_at' => null]);

    Livewire::test('pages::dashboard')
        ->assertViewHas('pendingOrders', 1);
});

test('dashboard recent orders returns 5 latest not-cancelled orders', function () {
    $user = dashboardUser();
    $this->actingAs($user);

    Order::factory()->count(8)->create(['cancelled_at' => null]);

    Livewire::test('pages::dashboard')
        ->assertViewHas('recentOrders', fn ($orders) => count($orders) === 5);
});

test('dashboard recent orders is empty collection when no orders', function () {
    $user = dashboardUser();
    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertViewHas('recentOrders', fn ($orders) => $orders->isEmpty());
});

test('dashboard status distribution shows counts by status', function () {
    $user = dashboardUser();
    $this->actingAs($user);

    Order::factory()->create(['status' => OrderStatus::CONFIRMÉE, 'cancelled_at' => null]);
    Order::factory()->create(['status' => OrderStatus::CONFIRMÉE, 'cancelled_at' => null]);
    Order::factory()->create(['status' => OrderStatus::LIVRÉE, 'cancelled_at' => null]);

    Livewire::test('pages::dashboard')
        ->assertViewHas('statusDistribution', fn ($dist) => collect($dist)->where('status', OrderStatus::CONFIRMÉE->value)->first()['count'] === 2);
});

test('dashboard period can be changed', function () {
    $user = dashboardUser();
    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertSet('period', '1M')
        ->call('setPeriod', '6M')
        ->assertSet('period', '6M')
        ->call('setPeriod', '1Y')
        ->assertSet('period', '1Y')
        ->call('setPeriod', 'ALL')
        ->assertSet('period', 'ALL');
});

test('dashboard invalid period is ignored', function () {
    $user = dashboardUser();
    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->call('setPeriod', 'INVALID')
        ->assertSet('period', '1M');
});

test('dashboard top cake types shows most ordered cakes', function () {
    $user = dashboardUser();
    $this->actingAs($user);

    Order::factory()->create(['cake_type' => 'Vanille', 'cancelled_at' => null]);
    Order::factory()->create(['cake_type' => 'Vanille', 'cancelled_at' => null]);
    Order::factory()->create(['cake_type' => 'Chocolat', 'cancelled_at' => null]);

    Livewire::test('pages::dashboard')
        ->assertViewHas('topCakeTypes');
});

test('dashboard critical stock alerts show low stock ingredients', function () {
    $user = dashboardUser();
    $this->actingAs($user);

    Ingredient::factory()->create([
        'name' => 'Farine',
        'stock_quantity' => 2,
        'alert_threshold' => 5,
        'is_critical' => true,
    ]);
    Ingredient::factory()->create([
        'name' => 'Sucre',
        'stock_quantity' => 10,
        'alert_threshold' => 5,
        'is_critical' => true,
    ]);

    Livewire::test('pages::dashboard')
        ->assertViewHas('criticalStocks', fn ($stocks) => $stocks->count() === 1 && $stocks->first()->name === 'Farine');
});

test('dashboard does not alert for non-critical low stock', function () {
    $user = dashboardUser();
    $this->actingAs($user);

    Ingredient::factory()->create([
        'name' => 'Farine',
        'stock_quantity' => 2,
        'alert_threshold' => 5,
        'is_critical' => false,
    ]);

    Livewire::test('pages::dashboard')
        ->assertViewHas('criticalStocks', fn ($stocks) => $stocks->isEmpty());
});

test('dashboard chart data exists with 30 entries for 1M period', function () {
    $user = dashboardUser();
    $this->actingAs($user);

    Livewire::test('pages::dashboard')
        ->assertViewHas('chartData', fn ($data) => count($data) === 30);
});

test('dashboard chart data has correct structure', function () {
    $user = dashboardUser();
    $this->actingAs($user);

    Order::factory()->create(['total_amount' => 5000, 'cancelled_at' => null]);

    Livewire::test('pages::dashboard')
        ->assertViewHas('chartData', function ($data) {
            $day = $data[0];

            return isset($day['date'], $day['label'], $day['revenue'], $day['refunds'], $day['orders']);
        });
});

test('dashboard csv export returns file', function () {
    $user = dashboardUser();
    $this->actingAs($user);

    Order::factory()->create(['total_amount' => 15000, 'cancelled_at' => null, 'client_name' => 'Test Client']);

    Livewire::test('pages::dashboard')
        ->call('exportCsv')
        ->assertFileDownloaded();
});

test('dashboard top biscuits shows top 3 biscuit flavors', function () {
    $user = dashboardUser();
    $this->actingAs($user);

    $order = Order::factory()->create(['status' => OrderStatus::CONFIRMÉE]);
    $order->levels()->createMany([
        ['level_number' => 1, 'flavor_biscuit' => 'Vanille', 'flavor_cream' => 'Chantilly'],
        ['level_number' => 2, 'flavor_biscuit' => 'Vanille', 'flavor_cream' => 'Chocolat'],
        ['level_number' => 3, 'flavor_biscuit' => 'Chocolat', 'flavor_cream' => 'Chantilly'],
    ]);

    Livewire::test('pages::dashboard')
        ->assertViewHas('topBiscuits', fn ($b) => count($b) > 0);
});

test('dashboard top creams shows top 3 cream flavors', function () {
    $user = dashboardUser();
    $this->actingAs($user);

    $order = Order::factory()->create(['status' => OrderStatus::CONFIRMÉE]);
    $order->levels()->createMany([
        ['level_number' => 1, 'flavor_biscuit' => 'Vanille', 'flavor_cream' => 'Chantilly'],
        ['level_number' => 2, 'flavor_biscuit' => 'Chocolat', 'flavor_cream' => 'Chantilly'],
    ]);

    Livewire::test('pages::dashboard')
        ->assertViewHas('topCreams', fn ($c) => count($c) > 0);
});
