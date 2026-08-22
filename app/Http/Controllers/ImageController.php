<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class ImageController extends Controller
{
    public function show($path)
    {
        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'Image not found'], 404);
        }

        $file = Storage::disk('public')->get($path);
        $type = Storage::disk('public')->mimeType($path);

        return response($file, 200)->header('Content-Type', $type);
    }
}
