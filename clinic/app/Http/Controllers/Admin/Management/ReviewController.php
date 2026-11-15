<?php

namespace App\Http\Controllers\Admin\Management;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Doctor;
use App\Models\Clinical\Review;
use App\Support\Audit;
use Illuminate\Http\Request;

/** QT-13: quản lý đánh giá bác sĩ - ẩn đánh giá vi phạm. */
class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $reviews = Review::with(['doctor.user', 'patient', 'user', 'visit'])
            ->when($request->filled('doctor_id'), fn ($q) => $q->where('doctor_id', $request->integer('doctor_id')))
            ->when($request->filled('rating'), fn ($q) => $q->where('rating', $request->integer('rating')))
            ->when($request->input('hidden') === '1', fn ($q) => $q->where('is_hidden', true))
            ->when($request->input('hidden') === '0', fn ($q) => $q->where('is_hidden', false))
            ->when($request->filled('q'), fn ($q) => $q->where('comment', 'like', '%'.$request->q.'%'))
            ->latest()
            ->paginate(20)->withQueryString();

        $all = Review::where('is_hidden', false)->get(['rating']);

        return view('admin.management.reviews.index', [
            'reviews' => $reviews,
            'doctors' => Doctor::with('user')->orderBy('specialty_id')->get(),
            'stats' => [
                'count' => $all->count(),
                'avg' => round((float) $all->avg('rating'), 2),
                'hidden' => Review::where('is_hidden', true)->count(),
                'distribution' => collect([5, 4, 3, 2, 1])->mapWithKeys(fn ($r) => [$r => $all->where('rating', $r)->count()]),
            ],
        ]);
    }

    public function toggle(Review $review)
    {
        $review->update(['is_hidden' => ! $review->is_hidden]);
        Audit::log('catalog', $review->is_hidden ? 'Ẩn đánh giá' : 'Hiện lại đánh giá', $review);

        return back()->with('success', $review->is_hidden
            ? 'Đã ẩn đánh giá. Điểm trung bình của bác sĩ đã được tính lại.'
            : 'Đã hiển thị lại đánh giá.');
    }
}
