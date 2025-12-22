<?php

namespace App\Http\Controllers;

use App\Models\Court;
use Illuminate\Http\Request;

class CourtControllerAPI extends Controller
{
    public function showBookCourt()
    {
        $courts = Court::where('status', 'active')->get();
        return response()->json([
            'status' => 'success',
            'courts' => $courts
        ], 200);
    }

}
