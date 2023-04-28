<?php

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use \Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

function getWords(): Collection
{

    $min = !empty(request()->min) ? intval(request()->min) : 4;
    $max = !empty(request()->max) ? intval(request()->max) : 12;

    $words = collect();

    for ($i = $min; $i <= $max; $i++) {
        $subCollection = array_unique(explode("\n", rtrim(Storage::get("data/words_$i.txt"))));
        foreach ($subCollection as $word) {
            $words->push($word);
        }
    }

    return $words;
}

Route::get('random', function() {
    $response = getWords();
    $number = request()->number ?? 1;
    return response()->json([
        'data' => $number == 1 ? $response->random($number)->first() : $response->random($number)
    ]);
});


Route::get('search/{word}', function(string $word) {
    $length = strlen($word);
    $response = collect(array_unique(explode("\n", rtrim(Storage::get("data/words_$length.txt")))));
    return response()->json([
        'data' => $response->contains($word)
    ]);
});

Route::get('search', function(Request $request) {

    $words = getWords();

    if (!empty($request->contains)) {
        $strings = explode(',', $request->contains);
        foreach ($strings as $string) {
            $words = $words->filter(function ($word) use ($string) {
                return str_contains($word, $string);
            });
        }
    }

    if (!empty($request->excludes)) {
        $strings = explode(',', $request->excludes);
        foreach ($strings as $string) {
            $words = $words->filter(function ($word) use ($string) {
                return !str_contains($word, $string);
            });
        }
    }

    if (!empty($request->start)) {
        $words = $words->filter(function ($word) {
            return str_starts_with($word, request()->start);
        });
    }

    if (!empty($request->end)) {
        $words = $words->filter(function ($word) {
            return str_ends_with($word, request()->end);
        });
    }

    if (!empty($request->good)) {
        $clues = explode('-', $request->good);
        foreach ($clues as $clue) {
            $info = explode(',', $clue);
            $words = $words->filter(function ($word) use ($info) {
                return $word[$info[0]] === $info[1];
            });
        }
    }

    if (!empty($request->bad)) {
        $clues = explode('-', $request->bad);
        foreach ($clues as $clue) {
            $info = explode(',', $clue);
            $words = $words->filter(function ($word) use ($info) {
                return $word[$info[0]] !== $info[1];
            });
        }
    }

    if (!empty($request->sort)) {
        if ($request->sort === 'desc') {
            $words = $words->sortByDesc(function($value) {
                return (string) $value;
            });
        } else {
            $words = $words->sortBy(function($value) {
                return (string) $value;
            });
        }
    }

    $response = [
        'results' => $words->count(),
        'words' => !empty($request->sort) ? $words->values() : $words,
    ];

    if (!empty($request->hint)) {
        $hint = $words->random();
        $response['hint'] = $hint;
    }

    return response()->json([
        'data' => $response
    ]);
});



