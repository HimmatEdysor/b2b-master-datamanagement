<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EditorImageUploadController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'image', 'max:10240'],
        ]);

        $path = $request->file('file')->store('page-content/'.date('Y/m'), 'public');

        return response()->json([
            'location' => asset('storage/'.$path),
        ]);
    }
}
