<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeedFile;
use App\Models\Marketplace;
use App\Models\User;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'usersCount' => User::query()->count(),
            'feedsCount' => FeedFile::query()->count(),
            'marketplacesCount' => Marketplace::query()->count(),
        ]);
    }
}
