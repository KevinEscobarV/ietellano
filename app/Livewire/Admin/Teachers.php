<?php

namespace App\Livewire\Admin;

use App\Models\Course;
use App\Models\Cycle;
use App\Models\Teacher;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Docentes')]
class Teachers extends Component
{
    use WithPagination;

    public bool $showModal = false;

    public bool $showDeleteModal = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public string $search = '';

    public string $username = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $phone = '';

    /** @var array<int, int> */
    public array $assignedCourseIds = [];

    public string $filterCycleId = '';

    /**
     * Credencial recién generada. Solo vive en esta pantalla y en este momento:
     * no se guarda en claro en ninguna parte, así que hay que entregársela al
     * docente antes de cerrar el aviso.
     */
    public bool $showAccessModal = false;

    public string $accessName = '';

    public string $accessEmail = '';

    public string $accessPassword = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $teacherId): void
    {
        $teacher = Teacher::with('courses')->findOrFail($teacherId);

        $this->editingId = $teacher->id;
        $this->username = (string) $teacher->username;
        $this->first_name = $teacher->first_name;
        $this->last_name = $teacher->last_name;
        $this->email = (string) $teacher->email;
        $this->phone = (string) $teacher->phone;
        $this->assignedCourseIds = $teacher->courses->pluck('id')->all();
        $this->filterCycleId = '';

        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'username' => ['required', 'string', 'max:255', Rule::unique('teachers', 'username')->ignore($this->editingId)],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'assignedCourseIds' => ['array'],
            'assignedCourseIds.*' => ['exists:courses,id'],
        ]);

        $attributes = [
            'username' => $validated['username'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'] ?: null,
            'phone' => $validated['phone'] ?: null,
        ];

        if ($this->editingId) {
            $teacher = Teacher::findOrFail($this->editingId);
            $teacher->update($attributes);
        } else {
            $teacher = Teacher::create($attributes);
        }

        $this->syncCourses($teacher);

        $this->showModal = false;
        $this->resetForm();

        Flux::toast(variant: 'success', text: __('Docente guardado.'));
    }

    private function syncCourses(Teacher $teacher): void
    {
        $ids = array_map('intval', $this->assignedCourseIds);

        Course::where('teacher_id', $teacher->id)->whereNotIn('id', $ids ?: [0])->update(['teacher_id' => null]);

        if ($ids !== []) {
            Course::whereIn('id', $ids)->update(['teacher_id' => $teacher->id]);
        }
    }

    /**
     * Le abre el sistema a un docente. No hay correo saliente configurado, así
     * que la contraseña se genera aquí y se muestra una sola vez para que la
     * coordinación se la entregue en persona.
     */
    public function createAccess(int $teacherId): void
    {
        $teacher = Teacher::findOrFail($teacherId);

        if ($teacher->user_id !== null) {
            Flux::toast(variant: 'warning', text: __('Este docente ya tiene acceso.'));

            return;
        }

        $email = trim((string) $teacher->email);

        if ($email === '') {
            Flux::toast(variant: 'danger', text: __('Primero agrégale un email al docente.'));

            return;
        }

        $existing = User::where('email', $email)->first();

        if ($existing !== null && Teacher::where('user_id', $existing->id)->exists()) {
            Flux::toast(variant: 'danger', text: __('Ya hay otro docente usando esa cuenta.'));

            return;
        }

        if ($existing !== null) {
            // La cuenta ya existía (por ejemplo, alguien de administración que
            // además dicta clase). Se vincula sin tocarle la contraseña.
            $user = $existing;
            $password = '';
        } else {
            $password = Str::password(12, symbols: false);

            $user = User::create([
                'name' => $teacher->name,
                'email' => $email,
                'password' => Hash::make($password),
            ]);

            // La cuenta la crea un administrador que ya conoce la dirección, así
            // que no hay nada que verificar por correo.
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $user->assignRole('docente');
        $teacher->user()->associate($user)->save();

        $this->announceAccess($teacher, $password);
    }

    public function resetAccessPassword(int $teacherId): void
    {
        $teacher = Teacher::with('user')->findOrFail($teacherId);

        if ($teacher->user === null) {
            Flux::toast(variant: 'danger', text: __('Este docente todavía no tiene acceso.'));

            return;
        }

        $password = Str::password(12, symbols: false);

        $teacher->user->forceFill(['password' => Hash::make($password)])->save();

        $this->announceAccess($teacher, $password);
    }

    public function revokeAccess(int $teacherId): void
    {
        $teacher = Teacher::with('user')->findOrFail($teacherId);

        $teacher->user?->removeRole('docente');
        $teacher->user()->dissociate()->save();

        Flux::toast(variant: 'success', text: __('Acceso retirado. La cuenta sigue existiendo en Usuarios.'));
    }

    private function announceAccess(Teacher $teacher, string $password): void
    {
        $this->accessName = $teacher->name;
        $this->accessEmail = (string) $teacher->email;
        $this->accessPassword = $password;
        $this->showAccessModal = true;
    }

    public function openDelete(int $teacherId): void
    {
        $this->deletingId = $teacherId;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        Teacher::findOrFail($this->deletingId)->delete();

        $this->showDeleteModal = false;
        $this->reset('deletingId');

        Flux::toast(variant: 'success', text: __('Docente eliminado.'));
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'username', 'first_name', 'last_name', 'email', 'phone', 'assignedCourseIds', 'filterCycleId']);
    }

    public function render(): View
    {
        $teachers = Teacher::query()
            ->with('user')
            ->withCount('courses')
            ->when($this->search, fn ($query) => $query->where(function ($q) {
                $q->where('first_name', 'like', "%{$this->search}%")
                    ->orWhere('last_name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%");
            }))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15);

        $filterCourses = $this->filterCycleId
            ? Course::where('cycle_id', $this->filterCycleId)->with('subject', 'group', 'teacher')->orderBy('code')->get()
            : collect();

        return view('livewire.admin.teachers', [
            'teachers' => $teachers,
            'cycles' => Cycle::orderBy('level')->get(),
            'filterCourses' => $filterCourses,
            'assignedCourses' => Course::whereIn('id', $this->assignedCourseIds)->with('subject', 'cycle')->orderBy('code')->get(),
        ]);
    }
}
