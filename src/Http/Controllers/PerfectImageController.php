<?php

namespace OnlineDigital\PerfectImage\Http\Controllers;

use OnlineDigital\PerfectImage\ImageManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

class PerfectImageController extends Controller
{
    public function __construct(
        protected ImageManager $manager
    ) {}

    public function observe(Request $request): JsonResponse
    {
        $id = $request->input('id');
        $src = $request->input('src');
        $resolutions = $request->input('resolutions', []);
        
        // Also accept 'res' as alternative
        if (empty($resolutions)) {
            $resolutions = $request->input('res', []);
        }
        
        if (!$id || !$src) {
            return response()->json([
                'success' => false,
                'message' => 'Missing id or src'
            ], 400);
        }
        
        $this->manager->saveResolutions($id, $resolutions);
        
        return response()->json(['success' => true]);
    }
}
