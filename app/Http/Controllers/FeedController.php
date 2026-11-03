<?php

namespace App\Http\Controllers;

use App\Models\FeedFile;
use Illuminate\View\View;

class FeedController extends Controller
{
    public function index(): View
    {
        return view('feeds.index', [
            'feeds' => FeedFile::query()->withCount('priceColumns')->orderBy('name')->get(),
        ]);
    }

    public function show(FeedFile $feed): View
    {
        return view('feeds.show', [
            'feed' => $feed->load('priceColumns'),
        ]);
    }
}
