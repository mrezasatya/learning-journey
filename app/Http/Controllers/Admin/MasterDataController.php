<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Division;
use App\Models\Grade;
use App\Models\Position;
use App\Models\WorkLocation;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** CRUD master data: departemen, divisi, jabatan, golongan, lokasi kerja (US-05). */
class MasterDataController extends Controller
{
    private function types(): array
    {
        return [
            'divisions' => ['Divisi', Division::class, ['code' => 'Kode', 'name' => 'Nama']],
            'departments' => ['Departemen', Department::class, ['code' => 'Kode', 'name' => 'Nama', 'division_id' => 'Divisi']],
            'positions' => ['Jabatan', Position::class, ['code' => 'Kode', 'name' => 'Nama', 'level' => 'Level']],
            'grades' => ['Golongan', Grade::class, ['code' => 'Kode', 'sort_order' => 'Urutan']],
            'locations' => ['Lokasi Kerja', WorkLocation::class, ['code' => 'Kode', 'name' => 'Nama', 'type' => 'Tipe', 'region' => 'Wilayah']],
        ];
    }

    public function index(Request $request, string $type = 'divisions')
    {
        [$label, $class, $fields] = $this->type($type);
        $items = $class::query()->when($type === 'departments', fn ($q) => $q->with('division'))
            ->orderBy($type === 'grades' ? 'sort_order' : 'code')->get();
        $usage = $items->mapWithKeys(fn ($m) => [$m->id => $this->usageCount($type, $m)]);
        $edit = $request->query('edit') ? $class::find($request->query('edit')) : null;

        return view('admin.master.index', [
            'types' => $this->types(), 'type' => $type, 'label' => $label, 'fields' => $fields,
            'items' => $items, 'usage' => $usage, 'edit' => $edit,
            'divisions' => Division::active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, string $type)
    {
        [, $class] = $this->type($type);
        $model = $class::create($this->validated($request, $type));
        AuditLogger::log("master.$type.create", $model, null, $model->toArray());

        return redirect()->route('admin.master.index', $type)->with('success', 'Data ditambahkan.');
    }

    public function update(Request $request, string $type, int $id)
    {
        [, $class] = $this->type($type);
        $model = $class::findOrFail($id);
        $original = $model->getOriginal();
        $model->update($this->validated($request, $type, $model));
        AuditLogger::changes("master.$type.update", $model, $original);

        return redirect()->route('admin.master.index', $type)->with('success', 'Data diperbarui.');
    }

    public function toggle(string $type, int $id)
    {
        [, $class] = $this->type($type);
        $model = $class::findOrFail($id);
        $model->update(['is_active' => ! $model->is_active]);
        AuditLogger::log("master.$type.".($model->is_active ? 'activate' : 'deactivate'), $model);

        return back()->with('success', $model->is_active ? 'Data diaktifkan.' : 'Data dinonaktifkan.');
    }

    public function destroy(string $type, int $id)
    {
        [, $class] = $this->type($type);
        $model = $class::findOrFail($id);
        if ($this->usageCount($type, $model) > 0) {
            return back()->withErrors(['delete' => 'Data sudah dipakai sehingga tidak dapat dihapus. Nonaktifkan saja (US-05).']);
        }
        AuditLogger::log("master.$type.delete", $model, $model->toArray());
        $model->delete();

        return back()->with('success', 'Data dihapus.');
    }

    private function type(string $type): array
    {
        abort_unless(array_key_exists($type, $this->types()), 404);

        return $this->types()[$type];
    }

    private function usageCount(string $type, Model $m): int
    {
        return match ($type) {
            'divisions' => Department::where('division_id', $m->id)->count(),
            'departments' => $m->employees()->count(),
            'positions', 'grades' => $m->employees()->count(),
            'locations' => $m->employees()->count(),
        };
    }

    private function validated(Request $request, string $type, ?Model $model = null): array
    {
        [, $class] = $this->type($type);
        $table = (new $class)->getTable();
        $rules = ['code' => ['required', 'string', 'max:30', Rule::unique($table, 'code')->ignore($model?->id)]];
        $rules += match ($type) {
            'divisions' => ['name' => 'required|string|max:255'],
            'departments' => ['name' => 'required|string|max:255', 'division_id' => 'nullable|exists:divisions,id'],
            'positions' => ['name' => 'required|string|max:255', 'level' => ['nullable', Rule::in(Position::LEVELS)]],
            'grades' => ['code' => ['required', 'regex:/^[1-7][AB]$/', Rule::unique('grades', 'code')->ignore($model?->id)], 'sort_order' => 'required|integer|min:0'],
            'locations' => ['name' => 'required|string|max:255', 'type' => ['required', Rule::in(array_keys(WorkLocation::TYPES))], 'region' => 'nullable|string|max:100'],
        };

        $data = $request->validate($rules, ['code.regex' => 'Golongan harus berformat 1A–7B.']);
        $data['code'] = strtoupper($data['code']);

        return $data;
    }
}
