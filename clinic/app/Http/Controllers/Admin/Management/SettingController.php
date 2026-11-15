<?php

namespace App\Http\Controllers\Admin\Management;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** QT-11: cấu hình tham số quy tắc nghiệp vụ (BR). */
class SettingController extends Controller
{
    public function edit(Settings $settings)
    {
        $definitions = collect(Settings::DEFINITIONS)->map(function ($def, $key) {
            [$code, $label, $type] = $def;
            $options = [];
            if (Str::startsWith($type, 'select:')) {
                foreach (explode(',', Str::after($type, 'select:')) as $pair) {
                    [$value, $text] = array_pad(explode('=', $pair, 2), 2, null);
                    $options[$value] = $text ?? $value;
                }
                $type = 'select';
            }

            return compact('code', 'label', 'type', 'options') + ['default' => config("clinic.rules.{$key}")];
        });

        return view('admin.management.settings.edit', [
            'definitions' => $definitions,
            'values' => $settings->all(),
            'groups' => $definitions->groupBy('code', preserveKeys: true),
        ]);
    }

    public function update(Request $request, Settings $settings)
    {
        $rules = [];
        foreach (Settings::DEFINITIONS as $key => [$code, $label, $type]) {
            $rules[$key] = match (true) {
                $type === 'int' => ['required', 'integer', 'min:0', 'max:100000000'],
                $type === 'bool' => ['nullable', 'boolean'],
                Str::startsWith($type, 'select:') => ['required', Rule::in(collect(explode(',', Str::after($type, 'select:')))
                    ->map(fn ($p) => explode('=', $p)[0])->all())],
                default => ['nullable', 'string', 'max:191'],
            };
        }

        $data = $request->validate($rules);

        foreach (Settings::DEFINITIONS as $key => [, , $type]) {
            if ($type === 'bool') {
                $data[$key] = $request->boolean($key);
            }
        }

        $old = $settings->all();
        $settings->set($data);
        $changed = collect($data)->filter(fn ($v, $k) => (string) ($old[$k] ?? '') !== (string) (is_bool($v) ? (int) $v : $v));
        if ($changed->isNotEmpty()) {
            Audit::log('settings', 'Cập nhật cấu hình quy tắc nghiệp vụ', null, [
                'old' => collect($old)->only($changed->keys())->all(),
                'new' => $changed->all(),
            ]);
        }

        return back()->with('success', $changed->isNotEmpty()
            ? 'Đã lưu cấu hình ('.$changed->count().' tham số thay đổi). Quy tắc mới được áp dụng ngay.'
            : 'Không có tham số nào thay đổi.');
    }
}
