{{-- Hiển thị điểm đánh giá dạng sao. Tham số: $rating (0-5) --}}
@php $r = (float) ($rating ?? 0); @endphp
<span class="rating-stars" title="{{ number_format($r, 1) }}/5">
  @for ($i = 1; $i <= 5; $i++)
    @if ($r >= $i)
      <i class="bi bi-star-fill"></i>
    @elseif ($r >= $i - 0.5)
      <i class="bi bi-star-half"></i>
    @else
      <i class="bi bi-star"></i>
    @endif
  @endfor
</span>
