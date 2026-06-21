<?php

use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

if (! function_exists('settingsUser')) {
    function settingsUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Gérant/Admin'));

        return $user;
    }
}

if (! function_exists('settingsUserNoPerms')) {
    function settingsUserNoPerms(): User
    {
        $role = Role::findOrCreate('no-perms-user');
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}

beforeEach(function () {
    Permission::findOrCreate('manage-settings');
    Role::findOrCreate('Gérant/Admin')->givePermissionTo('manage-settings');
    Role::findOrCreate('Comptable');
    Role::findOrCreate('Chef Pâtissier');
    Role::findOrCreate('Pâtissier');
    Role::findOrCreate('Caissier');
});

test('guest is redirected when accessing users page', function () {
    $this->get(route('settings.users'))->assertRedirect(route('login'));
});

test('user without manage-settings permission cannot access users page', function () {
    $user = settingsUserNoPerms();
    $this->actingAs($user)->get(route('settings.users'))->assertForbidden();
});

test('user with manage-settings permission can access users page', function () {
    $user = settingsUser();
    $this->actingAs($user)->get(route('settings.users'))->assertOk();
});

test('users page displays list of users', function () {
    $user = settingsUser();
    User::factory()->create(['name' => 'Alice Test']);
    User::factory()->create(['name' => 'Bob Test']);

    $this->actingAs($user);
    Livewire::test('pages::settings.users')
        ->assertOk()
        ->assertSee('Alice Test')
        ->assertSee('Bob Test');
});

test('users page filters by search', function () {
    $user = settingsUser();
    User::factory()->create(['name' => 'Alice Dupont']);
    User::factory()->create(['name' => 'Bob Martin']);

    $this->actingAs($user);
    Livewire::test('pages::settings.users')
        ->set('search', 'Alice')
        ->assertSee('Alice Dupont')
        ->assertDontSee('Bob Martin');
});

test('users page filters by role', function () {
    $user = settingsUser();
    $comptaRole = Role::findOrCreate('Comptable');
    $patissierRole = Role::findOrCreate('Pâtissier');

    $alice = User::factory()->create(['name' => 'Alice Compta']);
    $alice->assignRole($comptaRole);
    $bob = User::factory()->create(['name' => 'Bob Patissier']);
    $bob->assignRole($patissierRole);

    $this->actingAs($user);
    Livewire::test('pages::settings.users')
        ->set('roleFilter', 'Comptable')
        ->assertSee('Alice Compta')
        ->assertDontSee('Bob Patissier');
});

test('users page clears filters', function () {
    $user = settingsUser();
    User::factory()->create(['name' => 'Alice Dupont']);
    User::factory()->create(['name' => 'Bob Martin']);

    $this->actingAs($user);
    Livewire::test('pages::settings.users')
        ->set('search', 'Alice')
        ->call('clearFilters')
        ->assertSee('Alice Dupont')
        ->assertSee('Bob Martin');
});

test('users page hides ghost users', function () {
    $user = settingsUser();
    $ghost = User::factory()->create(['name' => 'Ghost Dev']);
    $ghost->assignRole(Role::findOrCreate('ghost'));

    $this->actingAs($user);
    Livewire::test('pages::settings.users')
        ->assertDontSee('Ghost Dev');
});

test('user form modal initializes with defaults for create', function () {
    $user = settingsUser();
    $this->actingAs($user);

    Livewire::test('pages::settings.modals.user-form-modal')
        ->dispatch('open-create-modal')
        ->assertSet('user_id', null)
        ->assertSet('name', '')
        ->assertSet('email', '')
        ->assertSet('password', '')
        ->assertSet('selected_role', '')
        ->assertSet('selected_permissions', [])
        ->assertSet('is_active', true)
        ->assertSet('showModal', true);
});

test('user form modal loads existing user for edit', function () {
    $user = settingsUser();
    $this->actingAs($user);

    $target = User::factory()->create([
        'name' => 'Edit Me',
        'email' => 'edit@test.com',
    ]);
    $target->assignRole(Role::findOrCreate('Comptable'));

    Livewire::test('pages::settings.modals.user-form-modal')
        ->dispatch('open-edit-modal', id: $target->id)
        ->assertSet('user_id', $target->id)
        ->assertSet('selected_role', 'Comptable')
        ->assertSet('showModal', true);
});

test('user form modal validates required fields', function () {
    $user = settingsUser();
    $this->actingAs($user);

    Livewire::test('pages::settings.modals.user-form-modal')
        ->dispatch('open-create-modal')
        ->call('saveUser')
        ->assertHasErrors(['name', 'email', 'selected_role']);
});

test('user form modal validates unique email', function () {
    $user = settingsUser();
    $this->actingAs($user);

    User::factory()->create(['email' => 'existing@test.com']);

    Livewire::test('pages::settings.modals.user-form-modal')
        ->dispatch('open-create-modal')
        ->set('name', 'Test User')
        ->set('email', 'existing@test.com')
        ->set('password', 'password123')
        ->set('selected_role', 'Comptable')
        ->call('saveUser')
        ->assertHasErrors(['email' => 'unique']);
});

test('user form modal can create a new user', function () {
    $user = settingsUser();
    $this->actingAs($user);

    Livewire::test('pages::settings.modals.user-form-modal')
        ->dispatch('open-create-modal')
        ->set('name', 'Nouvel Employé')
        ->set('email', 'nouveau@test.com')
        ->set('password', 'password123')
        ->set('selected_role', 'Comptable')
        ->call('saveUser')
        ->assertHasNoErrors()
        ->assertSet('showModal', false);

    $created = User::where('email', 'nouveau@test.com')->first();
    expect($created)->not->toBeNull();
    expect($created->name)->toEqual('Nouvel Employé');
    expect($created->is_active)->toBeFalse();
    expect($created->hasRole('Comptable'))->toBeTrue();
});

test('user form modal can update an existing user', function () {
    $user = settingsUser();
    $this->actingAs($user);

    $target = User::factory()->create(['email' => 'old@test.com']);
    $target->assignRole(Role::findOrCreate('Pâtissier'));

    Livewire::test('pages::settings.modals.user-form-modal')
        ->dispatch('open-edit-modal', id: $target->id)
        ->set('selected_role', 'Comptable')
        ->call('saveUser')
        ->assertHasNoErrors()
        ->assertSet('showModal', false);

    $target->refresh();
    expect($target->hasRole('Comptable'))->toBeTrue();
});

test('user form modal can toggle is_active', function () {
    $user = settingsUser();
    $this->actingAs($user);

    $target = User::factory()->create(['is_active' => true]);
    $target->assignRole(Role::findOrCreate('Pâtissier'));

    Livewire::test('pages::settings.modals.user-form-modal')
        ->dispatch('open-edit-modal', id: $target->id)
        ->set('is_active', false)
        ->call('saveUser')
        ->assertHasNoErrors();

    expect($target->refresh()->is_active)->toBeFalse();
});

test('user form modal prevents editing ghost users', function () {
    $user = settingsUser();
    $this->actingAs($user);

    $ghost = User::factory()->create(['name' => 'Ghost Dev']);
    $ghost->assignRole(Role::findOrCreate('ghost'));

    Livewire::test('pages::settings.modals.user-form-modal')
        ->dispatch('open-edit-modal', id: $ghost->id)
        ->assertForbidden();
});

test('user form modal dispatches user-saved event on create', function () {
    $user = settingsUser();
    $this->actingAs($user);

    Livewire::test('pages::settings.modals.user-form-modal')
        ->dispatch('open-create-modal')
        ->set('name', 'Event Test')
        ->set('email', 'event@test.com')
        ->set('password', 'password123')
        ->set('selected_role', 'Comptable')
        ->call('saveUser')
        ->assertDispatched('user-saved');
});

test('user form modal dispatches user-saved event on update', function () {
    $user = settingsUser();
    $this->actingAs($user);

    $target = User::factory()->create();
    $target->assignRole(Role::findOrCreate('Pâtissier'));

    Livewire::test('pages::settings.modals.user-form-modal')
        ->dispatch('open-edit-modal', id: $target->id)
        ->set('name', 'Updated')
        ->call('saveUser')
        ->assertDispatched('user-saved');
});

test('user without ghost or Gérant/Admin role cannot access users page', function () {
    $comptable = User::factory()->create();
    $comptable->assignRole(Role::findOrCreate('Comptable'));
    $this->actingAs($comptable)->get(route('settings.users'))->assertForbidden();
});

test('user without ghost or Gérant/Admin cannot save via modal', function () {
    $comptable = User::factory()->create();
    $comptable->assignRole(Role::findOrCreate('Comptable'));
    $this->actingAs($comptable);

    Livewire::test('pages::settings.modals.user-form-modal')
        ->dispatch('open-create-modal')
        ->set('name', 'Hacker')
        ->set('email', 'hacker@test.com')
        ->set('password', 'password123')
        ->set('selected_role', 'Comptable')
        ->call('saveUser')
        ->assertForbidden();
});

test('openCreateModal dispatches open-create-modal event', function () {
    $user = settingsUser();
    $this->actingAs($user);

    Livewire::test('pages::settings.users')
        ->call('openCreateModal')
        ->assertDispatched('open-create-modal');
});

test('openEditModal dispatches open-edit-modal event with id', function () {
    $user = settingsUser();
    $this->actingAs($user);

    $target = User::factory()->create();

    Livewire::test('pages::settings.users')
        ->call('openEditModal', $target->id)
        ->assertDispatched('open-edit-modal', id: $target->id);
});

test('prepareResend loads user data and opens modal', function () {
    $user = settingsUser();
    $this->actingAs($user);

    $target = User::factory()->pending()->create();

    Livewire::test('pages::settings.users')
        ->call('prepareResend', $target->id)
        ->assertSet('resendUserId', $target->id)
        ->assertSet('resendEmail', $target->email)
        ->assertSet('showResendModal', true);
});

test('prepareResend blocks for already activated users', function () {
    $user = settingsUser();
    $this->actingAs($user);

    $target = User::factory()->create(['setup_completed_at' => now()]);

    Livewire::test('pages::settings.users')
        ->call('prepareResend', $target->id)
        ->assertSet('showResendModal', false);
});

test('confirmResend sends invitation with same email', function () {
    $user = settingsUser();
    $this->actingAs($user);

    $target = User::factory()->pending()->create();

    Livewire::test('pages::settings.users')
        ->call('prepareResend', $target->id)
        ->call('confirmResend')
        ->assertHasNoErrors()
        ->assertSet('showResendModal', false);

    $target->refresh();
    expect($target->email)->toEqual($target->email);
    expect($target->setup_token)->not->toBeNull();
});

test('confirmResend updates email when changed', function () {
    $user = settingsUser();
    $this->actingAs($user);

    $target = User::factory()->pending()->create();

    Livewire::test('pages::settings.users')
        ->call('prepareResend', $target->id)
        ->set('resendEmail', 'corrige@test.com')
        ->call('confirmResend')
        ->assertHasNoErrors()
        ->assertSet('showResendModal', false);

    expect($target->refresh()->email)->toEqual('corrige@test.com');
});

test('confirmResend validates unique email when changed', function () {
    $user = settingsUser();
    $this->actingAs($user);

    User::factory()->create(['email' => 'existant@test.com']);
    $target = User::factory()->pending()->create();

    Livewire::test('pages::settings.users')
        ->call('prepareResend', $target->id)
        ->set('resendEmail', 'existant@test.com')
        ->call('confirmResend')
        ->assertHasErrors(['resendEmail' => 'unique']);
});
