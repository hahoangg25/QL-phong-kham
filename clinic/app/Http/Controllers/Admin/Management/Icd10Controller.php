<?php

namespace App\Http\Controllers\Admin\Management;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Icd10Code;
use App\Support\Audit;
use Illuminate\Http\Request;

/** QT-07: danh mục ICD-10 - tra cứu, thêm, import từ file CSV. */
class Icd10Controller extends Controller
{
    public function index(Request $request)
    {
        $codes = Icd10Code::search($request->input('q'))
            ->when($request->filled('chapter'), fn ($q) => $q->where('chapter', $request->chapter))
            ->orderBy('code')
            ->paginate(20)->withQueryString();

        return view('admin.management.icd10.index', [
            'codes' => $codes,
            'chapters' => Icd10Code::whereNotNull('chapter')->distinct()->orderBy('chapter')->pluck('chapter'),
            'total' => Icd10Code::count(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:icd10_codes,code'],
            'name' => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
            'chapter' => ['nullable', 'string', 'max:100'],
        ]);
        $data['code'] = strtoupper(trim($data['code']));

        Icd10Code::create($data);

        return back()->with('success', 'Đã thêm mã '.$data['code'].'.');
    }

    /** Import CSV: code,name[,name_en,chapter] - có hoặc không có dòng tiêu đề, UTF-8 có/không BOM; cập nhật nếu trùng mã. */
    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $imported = 0;
        $skipped = 0;
        $rows = [];
        $line = 0;

        while (($cols = fgetcsv($handle, 0, ',')) !== false) {
            $line++;
            if ($line === 1 && isset($cols[0])) {
                $cols[0] = preg_replace('/^\xEF\xBB\xBF/', '', $cols[0]);
            }
            $cols = array_map(fn ($c) => trim((string) $c), $cols);
            $code = strtoupper($cols[0] ?? '');
            $name = $cols[1] ?? '';

            // Bỏ dòng tiêu đề hoặc dòng không hợp lệ
            if ($line === 1 && in_array(strtolower($code), ['code', 'ma', 'mã', 'ma_icd', 'icd'], true)) {
                continue;
            }
            if ($code === '' || $name === '' || mb_strlen($code) > 10 || ! preg_match('/^[A-Z][0-9A-Z.\-]*$/', $code)) {
                $skipped++;

                continue;
            }

            $rows[$code] = [
                'code' => $code,
                'name' => mb_substr($name, 0, 191),
                'name_en' => ($cols[2] ?? '') !== '' ? mb_substr($cols[2], 0, 191) : null,
                'chapter' => ($cols[3] ?? '') !== '' ? mb_substr($cols[3], 0, 100) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        fclose($handle);

        foreach (array_chunk(array_values($rows), 500) as $chunk) {
            Icd10Code::upsert($chunk, ['code'], ['name', 'name_en', 'chapter', 'updated_at']);
            $imported += count($chunk);
        }

        Audit::log('catalog', 'Import danh mục ICD-10 từ file', null, ['imported' => $imported, 'skipped' => $skipped]);

        return back()->with($imported ? 'success' : 'warning',
            "Đã import {$imported} mã ICD-10".($skipped ? ", bỏ qua {$skipped} dòng không hợp lệ" : '').'.');
    }

    public function destroy(Icd10Code $icd10Code)
    {
        $icd10Code->delete();

        return back()->with('success', 'Đã xóa mã '.$icd10Code->code.'.');
    }
}
