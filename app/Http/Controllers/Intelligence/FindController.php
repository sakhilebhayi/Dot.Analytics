<?php

namespace App\Http\Controllers\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\Intelligence\SearchLog;
use App\Services\Intelligence\Search\Answerer;
use App\Services\Intelligence\Search\Finder;
use App\Services\Intelligence\Search\Question;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FindController extends Controller
{
    public function __invoke(Request $request, Finder $finder, Answerer $answerer): View
    {
        // These pages have no session, so a bad value is ignored or trimmed, never redirected back with an error.
        $raw = $request->query('q', '');
        $q = is_string($raw) ? trim(mb_substr($raw, 0, 160)) : '';

        if ($q === '') {
            return view('intel.find', ['q' => '', 'answer' => null, 'found' => null]);
        }

        $question = Question::parse($q);
        $found = $finder->find($question);
        $answer = $question->isSpecific() ? $answerer->answer($question) : null;
        $hits = $found['indicators']->count() + $found['items']->count() + $found['industries']->count() + $found['places']->count() + ($answer && $answer['grounded'] ? 1 : 0);

        SearchLog::record($q, $hits);

        return view('intel.find', ['q' => $q, 'answer' => $answer, 'found' => $found, 'hits' => $hits]);
    }
}
