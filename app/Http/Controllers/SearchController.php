<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Book;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->input('query');

        $books = Book::where('isbn', 'like', '%' . $query . '%')->get();

        return view('search.results', compact('books', 'query'));
    }
}

