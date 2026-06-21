<?php

use App\Models\Experience;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('profile page is displayed', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get(route('profile.edit'))->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toEqual('Test User');
    expect($user->email)->toEqual('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('name', 'Test User')
        ->set('email', $user->email)
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('additional profile fields can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('designation', 'Lead Pâtissier')
        ->set('phone', '+33123456789')
        ->set('website', 'https://patisserie.example.com')
        ->set('city', 'Paris')
        ->set('country', 'France')
        ->set('address', '75001 Paris')
        ->set('joining_date', '2024-01-15')
        ->set('skills', 'Illustrator, Photoshop, CSS')
        ->set('bio', 'Passionate pastry chef with 10 years of experience.')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->designation)->toEqual('Lead Pâtissier');
    expect($user->phone)->toEqual('+33123456789');
    expect($user->website)->toEqual('https://patisserie.example.com');
    expect($user->city)->toEqual('Paris');
    expect($user->country)->toEqual('France');
    expect($user->address)->toEqual('75001 Paris');
    expect($user->joining_date->format('Y-m-d'))->toEqual('2024-01-15');
    expect($user->skills)->toEqual('Illustrator, Photoshop, CSS');
    expect($user->bio)->toEqual('Passionate pastry chef with 10 years of experience.');
});

test('avatar can be uploaded', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('photo', UploadedFile::fake()->create('avatar.webp', 1024, 'image/webp'))
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->avatar)->not->toBeNull();
    Storage::disk('public')->assertExists($user->avatar);
});

test('old avatar is deleted when replaced', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user);

    // Upload first avatar
    Livewire::test('pages::settings.profile')
        ->set('photo', UploadedFile::fake()->create('avatar1.webp', 1024, 'image/webp'))
        ->call('updateProfileInformation');

    $user->refresh();
    $oldAvatar = $user->avatar;

    // Upload second avatar
    Livewire::test('pages::settings.profile')
        ->set('photo', UploadedFile::fake()->create('avatar2.webp', 1024, 'image/webp'))
        ->call('updateProfileInformation');

    $user->refresh();

    expect($user->avatar)->not->toBeNull();
    expect($user->avatar)->not->toEqual($oldAvatar);
    Storage::disk('public')->assertMissing($oldAvatar);
});

test('avatar is deleted when user is deleted', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('photo', UploadedFile::fake()->create('avatar.webp', 1024, 'image/webp'))
        ->call('updateProfileInformation');

    $user->refresh();
    $avatarPath = $user->avatar;

    $user->delete();

    Storage::disk('public')->assertMissing($avatarPath);
});

test('cover photo can be uploaded', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('coverPhoto', UploadedFile::fake()->create('cover.webp', 2048, 'image/webp'))
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->cover_photo)->not->toBeNull();
    Storage::disk('public')->assertExists($user->cover_photo);
});

test('old cover photo is deleted when replaced', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user);

    // Upload first cover
    Livewire::test('pages::settings.profile')
        ->set('coverPhoto', UploadedFile::fake()->create('cover1.webp', 2048, 'image/webp'))
        ->call('updateProfileInformation');

    $user->refresh();
    $oldCover = $user->cover_photo;

    // Upload second cover
    Livewire::test('pages::settings.profile')
        ->set('coverPhoto', UploadedFile::fake()->create('cover2.webp', 2048, 'image/webp'))
        ->call('updateProfileInformation');

    $user->refresh();

    expect($user->cover_photo)->not->toBeNull();
    expect($user->cover_photo)->not->toEqual($oldCover);
    Storage::disk('public')->assertMissing($oldCover);
});

test('cover photo is deleted when user is deleted', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('coverPhoto', UploadedFile::fake()->create('cover.webp', 2048, 'image/webp'))
        ->call('updateProfileInformation');

    $user->refresh();
    $coverPath = $user->cover_photo;

    $user->delete();

    Storage::disk('public')->assertMissing($coverPath);
});

test('experience can be created', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->call('showAddExperienceForm')
        ->set('exp_title', 'Lead Pâtissier')
        ->set('exp_company', 'Pastry Co.')
        ->set('exp_start_date', '2020-01-01')
        ->set('exp_end_date', '2023-12-31')
        ->set('exp_description', 'Managed the pastry team.')
        ->call('saveExperience');

    $response->assertHasNoErrors();

    expect(Experience::where('user_id', $user->id)->count())->toBe(1);
    expect(Experience::where('user_id', $user->id)->first()->title)->toEqual('Lead Pâtissier');
});

test('experience can be created as current', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->call('showAddExperienceForm')
        ->set('exp_title', 'Current Role')
        ->set('exp_start_date', '2024-01-01')
        ->set('exp_is_current', true)
        ->call('saveExperience');

    $response->assertHasNoErrors();

    $exp = Experience::where('user_id', $user->id)->first();

    expect($exp->is_current)->toBeTrue();
    expect($exp->end_date)->toBeNull();
});

test('experience can be edited', function () {
    $user = User::factory()->create();
    $experience = Experience::factory()->create([
        'user_id' => $user->id,
        'title' => 'Old Title',
        'start_date' => '2020-01-01',
        'end_date' => '2023-12-31',
    ]);

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->call('editExperience', $experience->id)
        ->set('exp_title', 'Updated Title')
        ->call('saveExperience');

    $response->assertHasNoErrors();

    expect($experience->fresh()->title)->toEqual('Updated Title');
});

test('experience can be deleted', function () {
    $user = User::factory()->create();
    $experience = Experience::factory()->create([
        'user_id' => $user->id,
        'start_date' => '2020-01-01',
        'end_date' => '2023-12-31',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->call('deleteExperience', $experience->id);

    expect(Experience::where('user_id', $user->id)->count())->toBe(0);
});
