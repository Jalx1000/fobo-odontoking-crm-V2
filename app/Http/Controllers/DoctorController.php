<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DoctorController extends Controller
{
    public function search(Request $request)
    {
        $searchParam = $request->input('search');
        
        // Extraer el término de búsqueda si viene en el formato 'name:termino'
        $term = '';
        if (str_starts_with($searchParam, 'name:')) {
            $term = substr($searchParam, 5);
        } else {
            $term = $searchParam;
        }

        $query = DB::table('doctors')->select('id', 'name');

        if (!empty($term)) {
            $query->where('name', 'like', '%' . $term . '%');
        }

        $doctors = $query->get();

        return response()->json([
            'data' => $doctors->toArray(), // Asegurarnos de que es un array
        ]);
    }
}
