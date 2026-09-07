<?php

namespace App\Livewire\Admin;

use App\Services\GradeImporter;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Title('Calificaciones')]
class Grades extends Component
{
    use WithFileUploads;

    /** @var array<int, TemporaryUploadedFile> */
    public array $files = [];

    /** @var list<array{file: string, course: ?string, matched: int, unmatched: int, blank: int, unmatched_rows: list<array{name: string, email: ?string, document: ?string}>}> */
    public array $results = [];

    public function import(GradeImporter $importer): void
    {
        $this->validate([
            'files' => ['required', 'array'],
            'files.*' => ['file', 'extensions:xlsx', 'max:10240'],
        ]);

        $this->results = [];

        foreach ($this->files as $file) {
            $result = $importer->import($file->getRealPath(), $file->getClientOriginalName());
            $result['file'] = $file->getClientOriginalName();
            $this->results[] = $result;
        }

        $this->reset('files');

        $imported = collect($this->results)->whereNotNull('course')->sum('matched');
        $skipped = collect($this->results)->whereNull('course')->count();

        Flux::toast(
            variant: $skipped > 0 ? 'warning' : 'success',
            text: __(':grades notas importadas. :skipped archivos sin curso.', ['grades' => $imported, 'skipped' => $skipped]),
        );
    }

    public function render(): View
    {
        return view('livewire.admin.grades');
    }
}
