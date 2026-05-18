<?php

namespace App\Livewire\Admin;

use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

#[Title('Roles')]
class Roles extends Component
{
    public bool $showModal = false;

    public bool $showDeleteModal = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public string $name = '';

    /** @var array<int, string> */
    public array $selectedPermissions = [];

    public function openCreate(): void
    {
        $this->reset(['editingId', 'name', 'selectedPermissions']);
        $this->showModal = true;
    }

    public function openEdit(int $roleId): void
    {
        $role = Role::with('permissions')->findOrFail($roleId);

        $this->editingId = $role->id;
        $this->name = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->toArray();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'selectedPermissions' => ['array'],
            'selectedPermissions.*' => ['string', 'exists:permissions,name'],
        ]);

        if ($this->editingId) {
            $role = Role::findOrFail($this->editingId);
            $role->update(['name' => $this->name]);
        } else {
            $role = Role::create(['name' => $this->name]);
        }

        $role->syncPermissions($this->selectedPermissions);

        $this->showModal = false;
        $this->reset(['editingId', 'name', 'selectedPermissions']);

        Flux::toast(variant: 'success', text: __('Role saved.'));
    }

    public function openDelete(int $roleId): void
    {
        $this->deletingId = $roleId;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        Role::findOrFail($this->deletingId)->delete();

        $this->showDeleteModal = false;
        $this->reset('deletingId');

        Flux::toast(variant: 'success', text: __('Role deleted.'));
    }

    public function render(): View
    {
        return view('livewire.admin.roles', [
            'roles' => Role::with('permissions')->orderBy('name')->get(),
            'allPermissions' => Permission::orderBy('name')->get()->groupBy(fn ($p) => explode('.', $p->name)[0]),
        ]);
    }
}
