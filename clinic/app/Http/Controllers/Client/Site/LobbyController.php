<?php

namespace App\Http\Controllers\Client\Site;

use App\Http\Controllers\Controller;
use App\Services\QueueService;

/** LT-09: màn hình hàng đợi tại sảnh (chỉ hiển thị số thứ tự, phòng và tên đã che bớt). */
class LobbyController extends Controller
{
    public function index()
    {
        return view('client.site.lobby');
    }

    public function data(QueueService $queue)
    {
        return response()->json([
            'time' => now()->format('H:i:s'),
            'date' => now()->translatedFormat('l, d/m/Y'),
            'rooms' => $queue->lobby(),
        ]);
    }
}
