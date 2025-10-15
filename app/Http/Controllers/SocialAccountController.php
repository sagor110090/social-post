<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class SocialAccountController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    /**
     * Get user's social accounts.
     */
    public function index(): JsonResponse
    {
        $accounts = SocialAccount::where('user_id', Auth::id())
            ->select('id', 'platform', 'platform_username', 'platform_user_id')
            ->orderBy('platform')
            ->orderBy('platform_username')
            ->get();

        return response()->json($accounts);
    }
}