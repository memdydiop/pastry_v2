<?php

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Experience;
use App\Models\Setting;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Actions\DeletePasskey;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;
    use PasswordValidationRules;
    use WithFileUploads;

    // Avatar
    public $photo = null;

    // Cover photo
    public $coverPhoto = null;

    // Profile fields
    public string $name = '';
    public string $email = '';
    public ?string $phone = null;
    public ?string $bio = null;
    public ?string $designation = null;
    public ?string $website = null;
    public ?string $city = null;
    public ?string $country = null;
    public ?string $address = null;
    public ?string $joining_date = null;
    public ?string $skills = null;

    // Password fields
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    // 2FA fields
    public bool $canManageTwoFactor;
    public bool $twoFactorEnabled;
    public bool $requiresConfirmation;

    // Passkey fields
    #[Locked]
    public bool $canManagePasskeys;

    #[Locked]
    public array $passkeys = [];

    public bool $showDeleteModal = false;

    #[Locked]
    public ?int $deletingPasskeyId = null;

    #[Locked]
    public string $deletingPasskeyName = '';

    // Experience
    public array $experiences = [];
    public bool $showExperienceForm = false;
    public ?int $editingExperienceId = null;
    public string $exp_title = '';
    public ?string $exp_company = null;
    public ?string $exp_description = null;
    public ?string $exp_start_date = null;
    public ?string $exp_end_date = null;
    public bool $exp_is_current = false;

    public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone;
        $this->bio = $user->bio;
        $this->designation = $user->designation;
        $this->website = $user->website;
        $this->city = $user->city;
        $this->country = $user->country;
        $this->address = $user->address;
        $this->joining_date = $user->joining_date?->format('Y-m-d');
        $this->skills = $user->skills;
        // 2FA setup
        $this->canManageTwoFactor = Features::canManageTwoFactorAuthentication();

        if ($this->canManageTwoFactor) {
            if (Fortify::confirmsTwoFactorAuthentication() && is_null(auth()->user()->two_factor_confirmed_at)) {
                $disableTwoFactorAuthentication(auth()->user());
            }

            $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
            $this->requiresConfirmation = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        // Passkeys setup
        $this->canManagePasskeys = Features::canManagePasskeys();

        if ($this->canManagePasskeys) {
            $this->loadPasskeys();
        }

        // Experiences
        $this->loadExperiences();
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        if ($this->photo) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $validated['avatar'] = $this->photo->store('avatars', 'public');
        }

        if ($this->coverPhoto) {
            if ($user->cover_photo) {
                Storage::disk('public')->delete($user->cover_photo);
            }
            $validated['cover_photo'] = $this->coverPhoto->store('covers', 'public');
        }

        unset($validated['photo'], $validated['coverPhoto']);
        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->reset('photo', 'coverPhoto');

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => $validated['password'],
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        Flux::toast(variant: 'success', text: __('Password updated.'));
    }

    /**
     * Load the user's passkeys.
     */
    public function loadPasskeys(): void
    {
        $this->passkeys = auth()->user()->passkeys()
            ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
            ->latest()
            ->get()
            ->map(fn ($passkey) => [
                'id' => $passkey->id,
                'name' => $passkey->name,
                'authenticator' => $passkey->authenticator,
                'created_at_diff' => $passkey->created_at->diffForHumans(),
                'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
            ])
            ->toArray();
    }

    /**
     * Show the delete confirmation modal.
     */
    public function confirmDelete(int $passkeyId): void
    {
        $passkey = auth()->user()->passkeys()->findOrFail($passkeyId);

        $this->deletingPasskeyId = $passkey->id;
        $this->deletingPasskeyName = $passkey->name;
        $this->showDeleteModal = true;
    }

    /**
     * Delete the passkey.
     */
    public function deletePasskey(DeletePasskey $deletePasskey): void
    {
        if (! $this->deletingPasskeyId) {
            return;
        }

        $passkey = auth()->user()->passkeys()->findOrFail($this->deletingPasskeyId);

        $deletePasskey(auth()->user(), $passkey);

        $this->closeDeleteModal();
        $this->loadPasskeys();
    }

    /**
     * Close the delete confirmation modal.
     */
    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deletingPasskeyId = null;
        $this->deletingPasskeyName = '';
    }

    /**
     * Handle the two-factor authentication enabled event.
     */
    #[On('two-factor-enabled')]
    public function onTwoFactorEnabled(): void
    {
        $this->twoFactorEnabled = true;
    }

    /**
     * Disable two-factor authentication for the user.
     */
    public function disableTwoFactor(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $disableTwoFactorAuthentication(auth()->user());

        $this->twoFactorEnabled = false;
    }

    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    // ─── Experience CRUD ─────────────────────────────────────────

    public function loadExperiences(): void
    {
        $this->experiences = Experience::where('user_id', Auth::id())
            ->orderBy('sort_order')
            ->orderByDesc('start_date')
            ->get()
            ->toArray();
    }

    public function showAddExperienceForm(): void
    {
        $this->resetExperienceForm();
        $this->showExperienceForm = true;
        $this->editingExperienceId = null;
    }

    public function editExperience(int $id): void
    {
        $exp = Experience::where('user_id', Auth::id())->findOrFail($id);
        $this->editingExperienceId = $exp->id;
        $this->exp_title = $exp->title;
        $this->exp_company = $exp->company;
        $this->exp_description = $exp->description;
        $this->exp_start_date = $exp->start_date->format('Y-m-d');
        $this->exp_end_date = $exp->end_date?->format('Y-m-d');
        $this->exp_is_current = $exp->is_current;
        $this->showExperienceForm = true;
    }

    public function cancelExperienceForm(): void
    {
        $this->showExperienceForm = false;
        $this->resetExperienceForm();
    }

    public function deleteExperience(int $id): void
    {
        Experience::where('user_id', Auth::id())->findOrFail($id)->delete();
        $this->loadExperiences();
        Flux::toast(variant: 'success', text: __('Experience deleted.'));
    }

    public function saveExperience(): void
    {
        $this->validate([
            'exp_title' => ['required', 'string', 'max:255'],
            'exp_company' => ['nullable', 'string', 'max:255'],
            'exp_description' => ['nullable', 'string', 'max:1000'],
            'exp_start_date' => ['required', 'date'],
            'exp_end_date' => ['nullable', 'date', 'after_or_equal:exp_start_date'],
            'exp_is_current' => ['boolean'],
        ]);

        if ($this->exp_is_current) {
            $this->exp_end_date = null;
        }

        $data = [
            'user_id' => Auth::id(),
            'title' => $this->exp_title,
            'company' => $this->exp_company,
            'description' => $this->exp_description,
            'start_date' => $this->exp_start_date,
            'end_date' => $this->exp_end_date,
            'is_current' => $this->exp_is_current,
        ];

        if ($this->editingExperienceId) {
            Experience::where('user_id', Auth::id())->findOrFail($this->editingExperienceId)->update($data);
        } else {
            $maxSort = Experience::where('user_id', Auth::id())->max('sort_order') ?? 0;
            $data['sort_order'] = $maxSort + 1;
            Experience::create($data);
        }

        $this->cancelExperienceForm();
        $this->loadExperiences();
        Flux::toast(variant: 'success', text: __('Experience saved.'));
    }

    private function resetExperienceForm(): void
    {
        $this->exp_title = '';
        $this->exp_company = null;
        $this->exp_description = null;
        $this->exp_start_date = null;
        $this->exp_end_date = null;
        $this->exp_is_current = false;
        $this->editingExperienceId = null;
    }

    // ─── Computed Properties ─────────────────────────────────────

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function user(): mixed
    {
        return Auth::user();
    }

    #[Computed]
    public function roleBadge(): ?string
    {
        $roles = Auth::user()->roles;
        if ($roles->isEmpty()) return null;

        return $roles->first()->name;
    }

    #[Computed]
    public function initials(): string
    {
        return Auth::user()->initials();
    }

    #[Computed]
    public function profileCompletion(): int
    {
        $fields = ['name', 'email', 'phone', 'bio', 'designation', 'website', 'city', 'country', 'address', 'skills'];
        $user = Auth::user();
        $filled = collect($fields)->filter(fn ($f) => !empty($user->$f))->count();

        return (int) round(($filled / count($fields)) * 100);
    }

    #[Computed]
    public function skillsList(): array
    {
        if (empty($this->user->skills)) return [];

        return array_map('trim', explode(',', $this->user->skills));
    }

    #[Computed]
    public function coverImageUrl(): ?string
    {
        return Auth::user()->coverUrl();
    }
}; ?>

<section class="">

    <x-page-heading
        :title="'Mon Profil'"
        :subtitle="'Gérez vos informations personnelles, votre sécurité et vos préférences.'">
        <x-slot:breadcrumbs>
            <flux:breadcrumbs.item>Paramètres</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Profil</flux:breadcrumbs.item>
        </x-slot:breadcrumbs>
    </x-page-heading>

    <div class="grid grid-cols-1 md:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-12 gap-6">

        {{-- ──────────────────────────────────────────────────────── --}}
        {{-- LEFT COLUMN — Cover + Avatar + Metadata                  --}}
        {{-- ──────────────────────────────────────────────────────── --}}
        <div class="col-span-1 md:col-span-2 xl:col-span-2 2xl:col-span-3 space-y-6">

            {{-- Profile Header Card (cover + avatar + info + stats) --}}
            <flux:card class="border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-xs p-0">
                {{-- Cover Banner --}}
                <div class="relative h-28 sm:h-36 rounded-t-xl overflow-hidden group/cover after:content-[''] after:absolute after:inset-0 after:bg-primary/40">
                    @if ($coverPhoto)
                        <img src="{{ $coverPhoto->temporaryUrl() }}" alt="" class="absolute inset-0 w-full h-full object-cover">
                    @elseif ($this->coverImageUrl)
                        <img src="{{ $this->coverImageUrl }}" alt="" class="absolute inset-0 w-full h-full object-cover">
                    @else
                        <div class="absolute inset-0" style="background: linear-gradient(to right, #405189, #3577f1, #0ab39c);"></div>
                    @endif
                    <div class="absolute inset-0 opacity-10" style="background-image: url(&quot;data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.4'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4h6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E&quot;);"></div>
                    <div class="absolute bottom-0 left-0 right-0 h-16 bg-gradient-to-t from-black/20 to-transparent"></div>

                    <label for="cover-upload" class="z-10 absolute top-2 right-2 size-8 rounded-full bg-black/40 hover:bg-black/60 text-white flex items-center justify-center cursor-pointer opacity-0 group-hover/cover:opacity-100 transition-opacity duration-200">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
                        </svg>
                    </label>
                    <input id="cover-upload" type="file" wire:model="coverPhoto" accept="image/*" class="hidden">
                    @error('coverPhoto')
                        <p class="absolute bottom-1 left-2 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="px-4 pb-5">
                    {{-- Avatar --}}
                    <div class="flex justify-center -mt-12 sm:-mt-14 mb-3">
                        <div class="flex items-center justify-center relative group mask-squircle bg-accent-foreground overflow-hidden size-24! sm:size-28!">
                            @if ($photo)
                                <div class="size-20! sm:size-24! mask-squircle overflow-hidden">
                                    <img src="{{ $photo->temporaryUrl() }}" alt="Preview" class="size-full object-cover">
                                </div>
                            @else
                                <flux:avatar
                                    mask="squircle"
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                    :src="auth()->user()->avatarUrl()"
                                    class="size-20! sm:size-24! ring-4 ring-white dark:ring-zinc-900 shadow-xl text-xl"
                                />
                            @endif
                            <label for="photo-upload" class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 size-20! sm:size-24! mask-squircle bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-center justify-center cursor-pointer">
                                <svg class="size-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
                                </svg>
                            </label>
                            <input id="photo-upload" type="file" wire:model="photo" accept="image/*" class="hidden">
                            @error('photo')
                                <p class="absolute -bottom-5 left-1/2 -translate-x-1/2 text-xs text-red-500 whitespace-nowrap">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Name + Role + Location --}}
                    <div class="text-center space-y-1 mb-4">
                        <flux:heading size="xl" class="!text-base sm:!text-lg font-semibold truncate">{{ $this->user->name }}</flux:heading>
                        <div class="flex items-center justify-center gap-2 flex-wrap">
                            @if ($this->roleBadge)
                                <flux:badge size="sm" color="blue">{{ $this->roleBadge }}</flux:badge>
                            @endif
                            @if ($this->user->designation)
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $this->user->designation }}</flux:text>
                            @endif
                        </div>
                        @if ($this->user->city || $this->user->country)
                            <div class="flex items-center justify-center gap-1">
                                <svg class="size-3.5 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                </svg>
                                <flux:text class="text-xs text-zinc-400">{{ collect([$this->user->city, $this->user->country])->filter()->join(', ') }}</flux:text>
                            </div>
                        @endif
                    </div>

                    {{-- Stats Row --}}
                    <div class="flex items-center justify-around border-t border-zinc-100 dark:border-zinc-800 pt-4">
                        <div class="text-center">
                            <flux:heading class="!text-base font-bold">{{ $this->profileCompletion }}%</flux:heading>
                            <flux:text class="text-xs text-zinc-400">{{ __('Completion') }}</flux:text>
                        </div>
                        <div class="text-center">
                            <flux:heading class="!text-base font-bold">{{ count($this->experiences) }}</flux:heading>
                            <flux:text class="text-xs text-zinc-400">{{ __('Experience') }}</flux:text>
                        </div>
                        <div class="text-center">
                            <flux:heading class="!text-base font-bold">{{ count($this->skillsList) }}</flux:heading>
                            <flux:text class="text-xs text-zinc-400">{{ __('Skills') }}</flux:text>
                        </div>
                    </div>
                </div>
            </flux:card>

            {{-- Professional Bio Card --}}
            @if ($this->user->bio)
            <flux:card class="overflow-hidden border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-xs">
                <div class="p-5">
                    <div class="mb-3">
                        <flux:heading size="lg" class="!text-base font-semibold card-header-title">{{ __('Professional Bio') }}</flux:heading>
                    </div>
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-300 leading-relaxed">
                        {{ $this->user->bio }}
                    </flux:text>
                </div>
            </flux:card>
            @endif

            {{-- Contact Information Card --}}
            <flux:card class="overflow-hidden border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-xs">
                <div class="p-5">
                    <div class="mb-4">
                        <flux:heading size="lg" class="!text-base font-semibold card-header-title">{{ __('Contact Information') }}</flux:heading>
                    </div>
                    <div class="space-y-4">
                        {{-- Email --}}
                        <div class="flex items-start gap-3">
                            <div class="shrink-0 mt-0.5">
                                <svg class="size-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <flux:text class="text-xs text-zinc-400 uppercase tracking-wider">{{ __('Email') }}</flux:text>
                                <flux:text class="text-sm font-medium truncate">{{ $this->user->email }}</flux:text>
                            </div>
                        </div>

                        {{-- Phone --}}
                        @if ($this->user->phone)
                        <div class="flex items-start gap-3">
                            <div class="shrink-0 mt-0.5">
                                <svg class="size-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <flux:text class="text-xs text-zinc-400 uppercase tracking-wider">{{ __('Phone') }}</flux:text>
                                <flux:text class="text-sm font-medium truncate">{{ $this->user->phone }}</flux:text>
                            </div>
                        </div>
                        @endif

                        {{-- Website --}}
                        @if ($this->user->website)
                        <div class="flex items-start gap-3">
                            <div class="shrink-0 mt-0.5">
                                <svg class="size-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <flux:text class="text-xs text-zinc-400 uppercase tracking-wider">{{ __('Website') }}</flux:text>
                                <a href="{{ $this->user->website }}" target="_blank" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline truncate block">{{ $this->user->website }}</a>
                            </div>
                        </div>
                        @endif

                        {{-- Designation --}}
                        @if ($this->user->designation)
                        <div class="flex items-start gap-3">
                            <div class="shrink-0 mt-0.5">
                                <svg class="size-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <flux:text class="text-xs text-zinc-400 uppercase tracking-wider">{{ __('Designation') }}</flux:text>
                                <flux:text class="text-sm font-medium truncate">{{ $this->user->designation }}</flux:text>
                            </div>
                        </div>
                        @endif

                        {{-- Joining Date --}}
                        @if ($this->user->joining_date)
                        <div class="flex items-start gap-3">
                            <div class="shrink-0 mt-0.5">
                                <svg class="size-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <flux:text class="text-xs text-zinc-400 uppercase tracking-wider">{{ __('Joining Date') }}</flux:text>
                                <flux:text class="text-sm font-medium">{{ $this->user->joining_date->format('d M, Y') }}</flux:text>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </flux:card>

            {{-- Skills Card --}}
            @if (!empty($this->skillsList))
            <flux:card class="overflow-hidden border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-xs">
                <div class="p-5">
                    <div class="mb-4">
                        <flux:heading size="lg" class="!text-base font-semibold card-header-title">{{ __('Skills') }}</flux:heading>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($this->skillsList as $skill)
                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 border border-blue-100 dark:border-blue-800/50">
                                {{ $skill }}
                            </span>
                        @endforeach
                    </div>
                </div>
            </flux:card>
            @endif

        </div>

        {{-- ──────────────────────────────────────────────────────── --}}
        {{-- RIGHT COLUMN — Progress Bar + Tabbed Forms               --}}
        {{-- ──────────────────────────────────────────────────────── --}}
        <div class="col-span-1 md:col-span-3 xl:col-span-4 2xl:col-span-9" x-data="{ activeTab: 'personal' }">

            <flux:card class="overflow-hidden border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-xs p-0">              

                {{-- Tab Navigation --}}
                    <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/30">
                        <nav class="flex gap-0 -mb-px overflow-x-auto scrollbar-none px-2" aria-label="{{ __('Profile tabs') }}">
                            <button
                                @click="activeTab = 'personal'"
                                :class="activeTab === 'personal'
                                    ? 'border-[#405189] text-[#405189] dark:border-blue-400 dark:text-blue-400'
                                    : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-200'"
                                class="px-4 py-3 text-sm font-medium border-b-2 transition-all duration-200 whitespace-nowrap flex items-center gap-2"
                                type="button"
                            >
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                </svg>
                                {{ __('Personal Details') }}
                            </button>
                            <button
                                @click="activeTab = 'security'"
                                :class="activeTab === 'security'
                                    ? 'border-[#405189] text-[#405189] dark:border-blue-400 dark:text-blue-400'
                                    : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-200'"
                                class="px-4 py-3 text-sm font-medium border-b-2 transition-all duration-200 whitespace-nowrap flex items-center gap-2"
                                type="button"
                            >
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                                </svg>
                                {{ __('Security') }}
                            </button>
                            <button
                                @click="activeTab = 'experience'"
                                :class="activeTab === 'experience'
                                    ? 'border-[#405189] text-[#405189] dark:border-blue-400 dark:text-blue-400'
                                    : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-200'"
                                class="px-4 py-3 text-sm font-medium border-b-2 transition-all duration-200 whitespace-nowrap flex items-center gap-2"
                                type="button"
                            >
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                                {{ __('Experience') }}
                            </button>
                        </nav>
                        {{-- Profile Completion Progress (Ynex-style, in tab bar) --}}
                        {{-- <div class="px-5 py-2.5 gap-4 border-b border-zinc-100 dark:border-zinc-800">
                            <div class="flex items-center gap-3 min-w-0 flex-1 mb-1">
                                <flux:heading size="lg" class="text-xs! font-semibold shrink-0 text-zinc-500 tracking-wider">
                                    {{ __('Profile') }}
                                </flux:heading>
                                <flux:text size="sm" color="{{ $this->profileCompletion >= 80 ? 'green' : ($this->profileCompletion >= 50 ? 'yellow' : 'red') }}">
                                    {{ $this->profileCompletion }}% {{ __('completed') }}
                                    @if ($this->profileCompletion < 100) — <a href="#" @click.prevent="activeTab = 'personal'" class="underline hover:no-underline">{{ __('Finish now') }}</a>@endif
                                </flux:text>
                            </div>
                            <div class="flex-1 max-w-xs bg-zinc-100 dark:bg-zinc-800 rounded-full h-1.5 overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-500 ease-out" style="width: {{ $this->profileCompletion }}%; background: linear-gradient(90deg, #405189, #0ab39c);"></div>
                            </div>
                        </div> --}}
                    </div>

                    {{-- Tab Content --}}
                    <div class="p-5 sm:p-6">

                        {{-- ═══ TAB: Personal Details ═══ --}}
                        <div x-show="activeTab === 'personal'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                            <form wire:submit="updateProfileInformation" class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2  xl:grid-cols-3 gap-x-6 gap-y-5">
                                    <flux:input wire:model="name" :label="__('Full Name')" type="text" required autofocus autocomplete="name" />
                                    <flux:input wire:model="designation" :label="__('Designation')" type="text" placeholder="e.g. Lead Designer / Partner" />
                                    <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />
                                    <flux:input wire:model="phone" :label="__('Phone Number')" type="tel" autocomplete="tel" />
                                    <flux:input wire:model="joining_date" :label="__('Joining Date')" type="date" />
                                    <flux:input wire:model="website" :label="__('Website')" type="url" placeholder="https://example.com" />
                                    <flux:input wire:model="city" :label="__('City')" type="text" />
                                    <flux:input wire:model="country" :label="__('Country')" type="text" />
                                    <flux:input wire:model="address" :label="__('Address')" type="text" />
                                </div>

                                @if ($this->hasUnverifiedEmail)
                                    <div class="rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/50 p-4">
                                        <div class="flex items-start gap-3">
                                            <svg class="size-5 text-amber-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                            </svg>
                                            <div>
                                                <flux:text class="text-sm text-amber-800 dark:text-amber-200">
                                                    {{ __('Your email address is unverified.') }}
                                                    <flux:link class="text-sm cursor-pointer font-medium underline" wire:click.prevent="resendVerificationNotification">
                                                        {{ __('Click here to re-send the verification email.') }}
                                                    </flux:link>
                                                </flux:text>

                                                @if (session('status') === 'verification-link-sent')
                                                    <flux:text class="mt-2 font-medium text-sm !text-green-600 dark:!text-green-400">
                                                        {{ __('A new verification link has been sent to your email address.') }}
                                                    </flux:text>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <flux:textarea wire:model="skills" :label="__('Skills')" rows="2" placeholder="e.g. Illustrator, Photoshop, CSS, HTML, Javascript" maxlength="500" description="{{ __('Comma-separated list of skills') }}" />
                                <flux:textarea wire:model="bio" :label="__('Bio / Description')" rows="3" maxlength="500" placeholder="{{ __('Tell us about yourself...') }}" />

                                <div class="flex items-center gap-4 pt-2">
                                    <flux:button variant="primary" type="submit" data-test="update-profile-button" class="!bg-[#405189] hover:!bg-[#364478] !border-[#405189]">
                                        {{ __('Update') }}
                                    </flux:button>
                                    <flux:button variant="ghost" type="reset">
                                        {{ __('Cancel') }}
                                    </flux:button>
                                </div>
                            </form>
                        </div>

                        {{-- ═══ TAB: Security ═══ --}}
                        <div x-show="activeTab === 'security'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                            <div class="space-y-10">

                                {{-- Update Password Section --}}
                                <section>
                                    <flux:heading>{{ __('Update password') }}</flux:heading>
                                    <flux:subheading>{{ __('Ensure your account is using a long, random password to stay secure') }}</flux:subheading>

                                    <form method="POST" wire:submit="updatePassword" class="mt-6 space-y-6">
                                        <flux:input
                                            wire:model="current_password"
                                            :label="__('Current password')"
                                            type="password"
                                            required
                                            autocomplete="current-password"
                                            viewable
                                        />
                                        <flux:input
                                            wire:model="password"
                                            :label="__('New password')"
                                            type="password"
                                            required
                                            autocomplete="new-password"
                                            passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                                            viewable
                                        />
                                        <flux:input
                                            wire:model="password_confirmation"
                                            :label="__('Confirm password')"
                                            type="password"
                                            required
                                            autocomplete="new-password"
                                            passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                                            viewable
                                        />

                                        <div class="flex items-center gap-4">
                                            <flux:button variant="primary" type="submit" data-test="update-password-button" class="!bg-[#405189] hover:!bg-[#364478] !border-[#405189]">
                                                {{ __('Save') }}
                                            </flux:button>
                                        </div>
                                    </form>
                                </section>

                                {{-- Two-Factor Authentication Section --}}
                                @if ($canManageTwoFactor)
                                    <section class="pt-8 border-t border-zinc-200 dark:border-zinc-700">
                                        <flux:heading>{{ __('Two-factor authentication') }}</flux:heading>
                                        <flux:subheading>{{ __('Manage your two-factor authentication settings') }}</flux:subheading>

                                        <div class="flex flex-col w-full mx-auto space-y-6 text-sm mt-6" wire:cloak>
                                            @if ($twoFactorEnabled)
                                                <div class="space-y-4">
                                                    <flux:text>
                                                        {{ __('You will be prompted for a secure, random pin during login, which you can retrieve from the TOTP-supported application on your phone.') }}
                                                    </flux:text>

                                                    <div class="flex justify-start">
                                                        <flux:button
                                                            variant="danger"
                                                            wire:click="disableTwoFactor"
                                                        >
                                                            {{ __('Disable 2FA') }}
                                                        </flux:button>
                                                    </div>

                                                    <livewire:pages::settings.two-factor.recovery-codes :$requiresConfirmation />
                                                </div>
                                            @else
                                                <div class="space-y-4">
                                                    <flux:text variant="subtle">
                                                        {{ __('When you enable two-factor authentication, you will be prompted for a secure pin during login. This pin can be retrieved from a TOTP-supported application on your phone.') }}
                                                    </flux:text>

                                                    <flux:modal.trigger name="two-factor-setup-modal">
                                                        <flux:button
                                                            variant="primary"
                                                            wire:click="$dispatch('start-two-factor-setup')"
                                                            class="!bg-[#405189] hover:!bg-[#364478] !border-[#405189]"
                                                        >
                                                            {{ __('Enable 2FA') }}
                                                        </flux:button>
                                                    </flux:modal.trigger>

                                                    <livewire:pages::settings.two-factor-setup-modal :requires-confirmation="$requiresConfirmation" />
                                                </div>
                                            @endif
                                        </div>
                                    </section>
                                @endif

                                {{-- Passkeys Section --}}
                                @if ($canManagePasskeys)
                                    <section class="pt-8 border-t border-zinc-200 dark:border-zinc-700">
                                        <flux:heading>{{ __('Passkeys') }}</flux:heading>
                                        <flux:subheading>{{ __('Manage your passkeys for passwordless sign-in') }}</flux:subheading>

                                        <div class="mt-6 flex flex-col w-full mx-auto space-y-6 text-sm" wire:cloak>
                                            <div class="border rounded-lg border-zinc-200 dark:border-zinc-700 overflow-hidden">
                                                @forelse ($passkeys as $passkey)
                                                    <div class="flex items-center justify-between p-4 {{ ! $loop->last ? 'border-b border-zinc-200 dark:border-zinc-700' : '' }}">
                                                        <div class="flex items-center gap-4">
                                                            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-800">
                                                                <flux:icon.key class="size-5 text-zinc-500 dark:text-zinc-400" />
                                                            </div>
                                                            <div class="space-y-1">
                                                                <div class="flex items-center gap-2.5">
                                                                    <p class="font-medium tracking-tight">{{ $passkey['name'] }}</p>
                                                                    @if ($passkey['authenticator'])
                                                                        <flux:badge size="sm">{{ $passkey['authenticator'] }}</flux:badge>
                                                                    @endif
                                                                </div>
                                                                <p class="text-zinc-500 dark:text-zinc-400 text-xs">
                                                                    {{ __('Added :time', ['time' => $passkey['created_at_diff']]) }}
                                                                    @if ($passkey['last_used_at_diff'])
                                                                        <span class="opacity-50 mx-1">/</span>
                                                                        {{ __('Last used :time', ['time' => $passkey['last_used_at_diff']]) }}
                                                                    @endif
                                                                </p>
                                                            </div>
                                                        </div>

                                                        <flux:button
                                                            variant="ghost"
                                                            size="sm"
                                                            icon="trash"
                                                            icon:variant="outline"
                                                            wire:click="confirmDelete({{ $passkey['id'] }})"
                                                            class="text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/50"
                                                        />
                                                    </div>
                                                @empty
                                                    <div class="p-8 text-center">
                                                        <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-zinc-100 dark:bg-zinc-800">
                                                            <flux:icon.key class="size-7 text-zinc-400 dark:text-zinc-500" />
                                                        </div>
                                                        <p class="font-medium">{{ __('No passkeys yet') }}</p>
                                                        <flux:text class="mt-1">{{ __('Add a passkey to sign in without a password') }}</flux:text>
                                                    </div>
                                                @endforelse
                                            </div>

                                            <x-passkey-registration />
                                        </div>
                                    </section>
                                @endif

                            </div>
                        </div>

                        {{-- ═══ TAB: Experience ═══ --}}
                        <div x-show="activeTab === 'experience'" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                            <div class="space-y-6">
                                <div class="flex items-center justify-between mb-4">
                                    <flux:heading class="!text-base font-semibold">{{ __('Professional Experience') }}</flux:heading>
                                    <flux:button variant="primary" size="sm" wire:click="showAddExperienceForm" class="!bg-[#405189] hover:!bg-[#364478] !border-[#405189]">
                                        {{ __('Add Experience') }}
                                    </flux:button>
                                </div>

                                @if ($showExperienceForm)
                                    <div class="p-4 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50">
                                        <form wire:submit="saveExperience" class="space-y-4">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <flux:input wire:model="exp_title" :label="__('Title')" type="text" required placeholder="e.g. Lead Designer" />
                                                <flux:input wire:model="exp_company" :label="__('Company')" type="text" placeholder="e.g. Pastry Co." />
                                                <flux:input wire:model="exp_start_date" :label="__('Start Date')" type="date" required />
                                                <flux:input wire:model="exp_end_date" :label="__('End Date')" type="date" :disabled="$exp_is_current" />
                                            </div>
                                            <flux:textarea wire:model="exp_description" :label="__('Description')" rows="3" maxlength="1000" />
                                            <flux:checkbox wire:model="exp_is_current" :label="__('I currently work here')" />
                                            <div class="flex items-center gap-3 pt-2">
                                                <flux:button variant="primary" type="submit" class="!bg-[#405189] hover:!bg-[#364478] !border-[#405189]">
                                                    {{ $editingExperienceId ? __('Update') : __('Save') }}
                                                </flux:button>
                                                <flux:button variant="ghost" type="button" wire:click="cancelExperienceForm">
                                                    {{ __('Cancel') }}
                                                </flux:button>
                                            </div>
                                        </form>
                                    </div>
                                @endif

                                @forelse ($this->experiences as $exp)
                                    <div class="relative pl-8 before:absolute before:left-3 before:top-2 before:bottom-0 before:w-px before:bg-zinc-200 dark:before:bg-zinc-700">
                                        <div class="absolute left-0 top-1.5 size-6 rounded-full bg-[#405189]/10 dark:bg-[#405189]/30 flex items-center justify-center ring-4 ring-white dark:ring-zinc-900">
                                            <div class="size-2 rounded-full bg-[#405189]"></div>
                                        </div>
                                        <div class="pb-6">
                                            <div class="flex items-center gap-2 mb-1">
                                                <flux:heading class="!text-sm font-semibold">{{ $exp['title'] }}</flux:heading>
                                                @if ($exp['is_current'])
                                                    <flux:badge size="sm" color="green">{{ __('Present') }}</flux:badge>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <flux:text class="text-xs text-blue-600 dark:text-blue-400 font-medium mb-2">
                                                    {{ \Carbon\CarbonImmutable::parse($exp['start_date'])->format('Y') }}
                                                    – {{ $exp['is_current'] ? __('Present') : \Carbon\CarbonImmutable::parse($exp['end_date'])->format('Y') }}
                                                </flux:text>
                                                @if ($exp['company'])
                                                    <flux:text class="text-xs text-zinc-400 mb-2">· {{ $exp['company'] }}</flux:text>
                                                @endif
                                            </div>
                                            @if ($exp['description'])
                                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                                    {{ $exp['description'] }}
                                                </flux:text>
                                            @endif
                                            <div class="flex items-center gap-2 mt-2">
                                                <flux:button variant="ghost" size="xs" wire:click="editExperience({{ $exp['id'] }})" icon="pencil">
                                                    {{ __('Edit') }}
                                                </flux:button>
                                                <flux:button variant="ghost" size="xs" wire:click="deleteExperience({{ $exp['id'] }})" icon="trash" class="text-red-500 hover:text-red-600">
                                                    {{ __('Delete') }}
                                                </flux:button>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-center py-8">
                                        <flux:text class="text-zinc-400">{{ __('No experience added yet. Click "Add Experience" to get started.') }}</flux:text>
                                    </div>
                                @endforelse
                            </div>
                        </div>


                            </div>
                        </div>

                    </div>
                </flux:card>
            </div>
        </div>
    
        {{-- Passkey Delete Modal --}}
    <flux:modal
        name="delete-passkey-modal"
        class="max-w-md md:min-w-md"
        @close="closeDeleteModal"
        wire:model="showDeleteModal"
    >
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Remove passkey') }}</flux:heading>
                <flux:text>
                    {{ __('Are you sure you want to remove the passkey ":name"? You will no longer be able to use it to sign in.', ['name' => $deletingPasskeyName]) }}
                </flux:text>
            </div>

            <div class="flex gap-3 justify-end">
                <flux:button
                    variant="outline"
                    wire:click="closeDeleteModal"
                >
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button
                    variant="danger"
                    wire:click="deletePasskey"
                >
                    {{ __('Remove passkey') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
