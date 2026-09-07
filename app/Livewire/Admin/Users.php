<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

#[Title('Users')]
class Users extends Component
{
    use WithPagination;

    public bool $showModal = false;

    public bool $showDeleteModal = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public string $search = '';

    public string $name = '';

    public string $email = '';

    public string $password = '';

    /** @var array<int, string> */
    public array $selectedRoles = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'name', 'email', 'password', 'selectedRoles']);
        $this->showModal = true;
    }

    public function openEdit(int $userId): void
    {
        $user = User::with('roles')->findOrFail($userId);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->selectedRoles = $user->roles->pluck('name')->toArray();
        $this->showModal = true;
    }

    public function save(): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'selectedRoles' => ['array'],
            'selectedRoles.*' => ['string', 'exists:roles,name'],
        ];

        if ($this->editingId) {
            $rules['password'] = ['nullable', 'string', 'min:8'];
        } else {
            $rules['password'] = ['required', 'string', 'min:8'];
        }

        $this->validate($rules);

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            $user->update(array_filter([
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password ? Hash::make($this->password) : null,
            ]));
        } else {
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => Hash::make($this->password),
            ]);
        }

        $user->syncRoles($this->selectedRoles);

        $this->showModal = false;
        $this->reset(['editingId', 'name', 'email', 'password', 'selectedRoles']);

        Flux::toast(variant: 'success', text: __('User saved.'));
    }

    public function openDelete(int $userId): void
    {
        $this->deletingId = $userId;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        $user = User::findOrFail($this->deletingId);

        if ($user->id === auth()->id()) {
            Flux::toast(variant: 'danger', text: __('You cannot delete your own account.'));
            $this->showDeleteModal = false;
            $this->reset('deletingId');

            return;
        }

        $user->delete();

        $this->showDeleteModal = false;
        $this->reset('deletingId');

        Flux::toast(variant: 'success', text: __('User deleted.'));
    }

    public function render(): View
    {
        $users = User::with('roles')
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%");
            }))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admin.users', [
            'users' => $users,
            'allRoles' => Role::orderBy('name')->get(),
        ]);
    }
}
