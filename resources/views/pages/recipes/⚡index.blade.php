<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Support\Facades\DB;

new #[Title('Fiches Techniques')] class extends Component {
    use WithPagination;

    public $search = '';
    public $showInactive = false;
    public $perPage = PER_PAGE;

    public $showModal = false;
    public $editingRecipeId = null;
    public $showDeleteModal = false;
    public $deleteRecipeId = null;

    public $name = '';
    public $category = '';
    public $description = '';
    public $instructions = '';
    public $expected_cost = null;
    public $is_active = true;

    public $recipeIngredients = [];

    public function updatedSearch() { $this->resetPage(); }
    public function updatedShowInactive() { $this->resetPage(); }
    public function updatedPerPage() { $this->resetPage(); }

    public function openModal($id = null)
    {
        $this->resetErrorBag();
        $this->editingRecipeId = $id;

        if ($id) {
            $recipe = Recipe::with('recipeIngredients.ingredient')->findOrFail($id);
            $this->name = $recipe->name;
            $this->category = $recipe->category;
            $this->description = $recipe->description;
            $this->instructions = $recipe->instructions;
            $this->expected_cost = $recipe->expected_cost;
            $this->is_active = $recipe->is_active;

            $this->recipeIngredients = $recipe->recipeIngredients->map(fn($ri) => [
                'ingredient_id' => (string) $ri->ingredient_id,
                'quantity' => (string) $ri->quantity,
                'unit_override' => $ri->unit_override,
            ])->toArray();
        } else {
            $this->reset(['name', 'category', 'description', 'instructions', 'expected_cost', 'recipeIngredients']);
            $this->is_active = true;
        }

        if (empty($this->recipeIngredients)) {
            $this->recipeIngredients = [[
                'ingredient_id' => '',
                'quantity' => '',
                'unit_override' => '',
            ]];
        }

        $this->showModal = true;
    }

    public function addIngredientRow()
    {
        $this->recipeIngredients[] = [
            'ingredient_id' => '',
            'quantity' => '',
            'unit_override' => '',
        ];
    }

    public function removeIngredientRow($index)
    {
        if (count($this->recipeIngredients) > 1) {
            unset($this->recipeIngredients[$index]);
            $this->recipeIngredients = array_values($this->recipeIngredients);
        }
    }

    public function saveRecipe()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'instructions' => 'nullable|string',
            'expected_cost' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'recipeIngredients' => 'required|array|min:1',
            'recipeIngredients.*.ingredient_id' => 'required|exists:ingredients,id',
            'recipeIngredients.*.quantity' => 'required|numeric|min:0.01',
            'recipeIngredients.*.unit_override' => 'nullable|string|max:50',
        ]);

        DB::transaction(function () {
            $data = [
                'name' => $this->name,
                'category' => $this->category,
                'description' => $this->description,
                'instructions' => $this->instructions,
                'expected_cost' => $this->expected_cost,
                'is_active' => $this->is_active,
            ];

            if ($this->editingRecipeId) {
                $recipe = Recipe::findOrFail($this->editingRecipeId);
                $recipe->update($data);
                $recipe->recipeIngredients()->delete();
                $msg = 'Fiche technique mise à jour.';
            } else {
                $recipe = Recipe::create($data);
                $msg = 'Fiche technique créée.';
            }

            foreach ($this->recipeIngredients as $item) {
                $recipe->recipeIngredients()->create([
                    'ingredient_id' => $item['ingredient_id'],
                    'quantity' => $item['quantity'],
                    'unit_override' => $item['unit_override'] ?: null,
                ]);
            }

            $this->dispatch('toast', variant: 'success', heading: $msg);
        });

        $this->showModal = false;
    }

    public function prepareDeleteRecipe($id)
    {
        $this->deleteRecipeId = $id;
        $this->showDeleteModal = true;
    }

    public function confirmDeleteRecipe()
    {
        $this->authorize('gerantOrGhost');

        $recipe = Recipe::find($this->deleteRecipeId);
        if (!$recipe) return;

        DB::transaction(function () use ($recipe) {
            $recipe->recipeIngredients()->delete();
            $recipe->delete();
        });

        $this->showDeleteModal = false;
        $this->dispatch('toast', variant: 'success', heading: 'Fiche technique supprimée.');
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'showInactive']);
        $this->resetPage();
    }

    public function with(): array
    {
        $query = Recipe::withCount('recipeIngredients')->orderBy('name');

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('category', 'like', '%' . $this->search . '%');
            });
        }

        if (!$this->showInactive) {
            $query->where('is_active', true);
        }

        $recipes = $query->paginate($this->perPage);

        return [
            'recipes' => $recipes,
            'isFiltered' => !empty($this->search) || !$this->showInactive,
            'allIngredients' => Ingredient::orderBy('name')->get(),
            'totalRecipes' => Recipe::count(),
            'activeRecipes' => Recipe::where('is_active', true)->count(),
        ];
    }
}; ?>

<div class="space-y-6">
    <x-page-heading
        :title="'Fiches Techniques'"
        :subtitle="'Recettes et compositions des gâteaux, avec ingrédients et coefficients de perte.'">
        <x-slot:breadcrumbs>
            <flux:breadcrumbs.item>Atelier & Production</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Fiches Techniques</flux:breadcrumbs.item>
        </x-slot:breadcrumbs>
    </x-page-heading>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <flux:card class="border border-indigo-200/80 dark:border-indigo-800/60 bg-indigo-50/50 dark:bg-indigo-950/20">
            <x-card.card-body class="p-4">
                <flux:text class="text-indigo-600 dark:text-indigo-400 text-xs font-semibold uppercase tracking-wider">Fiches techniques</flux:text>
                <div class="text-2xl font-black text-indigo-700 dark:text-indigo-300 mt-1">{{ $totalRecipes }}</div>
            </x-card.card-body>
        </flux:card>

        <flux:card class="border border-emerald-200/80 dark:border-emerald-800/60 bg-emerald-50/50 dark:bg-emerald-950/20">
            <x-card.card-body class="p-4">
                <flux:text class="text-emerald-600 dark:text-emerald-400 text-xs font-semibold uppercase tracking-wider">Actives</flux:text>
                <div class="text-2xl font-black text-emerald-700 dark:text-emerald-300 mt-1">{{ $activeRecipes }}</div>
            </x-card.card-body>
        </flux:card>
    </div>

    <flux:card class="border border-zinc-200/80 dark:border-zinc-700/80 bg-white dark:bg-zinc-900 shadow-xs p-0">
        <x-card.card-header title="Recettes" subtitle="Liste des fiches techniques">
            <x-slot:menu>
                <flux:menu.item icon="plus" wire:click="openModal" class="cursor-pointer">Nouvelle fiche</flux:menu.item>
            </x-slot:menu>
        </x-card.card-header>

        <x-card.card-body class="p-0">
            <x-card.table-filters
                search-placeholder="Rechercher une recette..."
                search-binding="search"
                :has-active-filters="$isFiltered"
            >
                <flux:checkbox wire:model.live="showInactive" label="Voir les inactives" class="text-sm" />
            </x-card.table-filters>

            @if($recipes->isEmpty())
                <div class="text-center py-16 bg-zinc-50/50 dark:bg-zinc-950/20">
                    <div class="size-12 rounded-xl bg-zinc-100 dark:bg-zinc-800 text-zinc-500 flex items-center justify-center text-xl mx-auto shadow-xs">📋</div>
                    <flux:heading class="mt-4 font-bold text-zinc-900 dark:text-white">Aucune fiche technique</flux:heading>
                    <flux:text size="sm" class="mt-1 text-zinc-400">Créez votre première fiche technique pour lier les ingrédients aux gâteaux.</flux:text>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-4">
                    @foreach($recipes as $recipe)
                        <flux:card class="border border-zinc-200/60 dark:border-zinc-800/50 hover:shadow-md transition-shadow">
                            <x-card.card-body class="p-4 space-y-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <div class="font-semibold text-zinc-900 dark:text-white truncate">{{ $recipe->name }}</div>
                                        @if($recipe->category)
                                            <flux:badge size="sm" variant="neutral" class="mt-1 px-1.5 py-0.5">{{ $recipe->category }}</flux:badge>
                                        @endif
                                    </div>
                                    @if(!$recipe->is_active)
                                        <flux:badge size="sm" variant="danger" class="shrink-0">Inactive</flux:badge>
                                    @endif
                                </div>

                                @if($recipe->description)
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400 line-clamp-2">{{ $recipe->description }}</div>
                                @endif

                                <div class="flex items-center justify-between pt-2 border-t border-zinc-100 dark:border-zinc-800">
                                    <span class="text-xs text-zinc-400">
                                        <flux:icon.queue-list variant="micro" class="inline size-3.5 align-middle mr-1" />
                                        {{ $recipe->recipe_ingredients_count }} ingrédient(s)
                                    </span>
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button icon="ellipsis-horizontal" size="xs" variant="ghost" title="Actions" />
                                        <flux:menu>
                                            <flux:menu.item icon="pencil-square" wire:click="openModal({{ $recipe->id }})">
                                                Modifier
                                            </flux:menu.item>
                                            <flux:menu.item icon="trash" variant="danger" wire:click="prepareDeleteRecipe({{ $recipe->id }})">
                                                Supprimer
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </div>
                            </x-card.card-body>
                        </flux:card>
                    @endforeach
                </div>

                @if($recipes->hasPages())
                    <div class="p-4 border-t border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50">
                        {{ $recipes->links() }}
                    </div>
                @endif
            @endif
        </x-card.card-body>
    </flux:card>

    <flux:modal name="delete-recipe-modal" wire:model.self="showDeleteModal" class="min-w-[22rem]">
        <form wire:submit="confirmDeleteRecipe" class="space-y-6">
            <div>
                <flux:heading size="lg">Supprimer cette fiche technique ?</flux:heading>
                <flux:text class="mt-2">Cette action est irréversible.</flux:text>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Annuler</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">Supprimer</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="recipe-modal" wire:model="showModal" class="max-w-3xl space-y-6">
        <form wire:submit.prevent="saveRecipe" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingRecipeId ? "Modifier la fiche technique" : 'Nouvelle fiche technique' }}</flux:heading>
                <flux:subheading>Définissez les ingrédients nécessaires et leurs coefficients de perte.</flux:subheading>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input wire:model="name" label="Nom de la recette" placeholder="Ex: Génoise vanille" required />
                <flux:input wire:model="category" label="Catégorie" placeholder="Ex: Biscuit, Crème, Glaçage" />
            </div>

            <flux:textarea wire:model="description" label="Description" placeholder="Brève description de la recette..." rows="2" />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input type="number" wire:model="expected_cost" label="Coût théorique (FCFA)" step="0.01" min="0" />
                <flux:switch wire:model="is_active" label="Recette active" description="Les recettes inactives ne sont pas proposées" />
            </div>

            <div class="bg-zinc-50 dark:bg-zinc-950/20 p-4 rounded-xl border border-zinc-200/60 dark:border-zinc-800/50 space-y-3">
                <div class="flex items-center justify-between">
                    <flux:heading size="sm" class="uppercase tracking-wider text-zinc-400 font-bold text-[11px]">
                        Ingrédients
                    </flux:heading>
                    <flux:button size="xs" variant="subtle" icon="plus" wire:click="addIngredientRow" class="cursor-pointer">
                        Ajouter
                    </flux:button>
                </div>

                @foreach($recipeIngredients as $index => $item)
                    <div class="flex items-end gap-2 p-2 bg-white dark:bg-zinc-900/50 rounded-lg border border-zinc-200/40 dark:border-zinc-800/40">
                        <div class="flex-1 min-w-0">
                            <flux:select wire:model="recipeIngredients.{{ $index }}.ingredient_id" label="Ingrédient" placeholder="Choisir..." size="sm">
                                @foreach($allIngredients as $ing)
                                    <flux:select.option value="{{ $ing->id }}">
                                        {{ $ing->name }} ({{ $ing->unit }})
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        <div class="w-20">
                            <flux:input type="number" wire:model="recipeIngredients.{{ $index }}.quantity" label="Qté" size="sm" step="0.01" min="0.01" />
                        </div>

                        <div class="w-24">
                            <flux:input wire:model="recipeIngredients.{{ $index }}.unit_override" label="Unité" size="sm" placeholder="Auto" />
                        </div>
                        <div class="pb-1">
                            <flux:button size="sm" variant="danger" icon="trash" class="cursor-pointer" wire:click="removeIngredientRow({{ $index }})" />
                        </div>
                    </div>
                @endforeach

                @error('recipeIngredients')
                    <flux:text class="text-rose-500 text-xs">{{ $message }}</flux:text>
                @enderror
            </div>

            <flux:textarea wire:model="instructions" label="Instructions de préparation" placeholder="Étapes de réalisation..." rows="4" />

            <div class="flex gap-2 justify-end border-t border-zinc-100 dark:border-zinc-800 pt-4">
                <flux:button variant="ghost" wire:click="$set('showModal', false)">Annuler</flux:button>
                <flux:button type="submit" variant="primary" class="cursor-pointer">Enregistrer</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
