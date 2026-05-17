<?php
// app/Http/Controllers/PollWebController.php

namespace App\Http\Controllers;

use App\Models\Poll;
use Illuminate\Http\Request;

class PollWebController extends Controller
{
    // Page création — rien à passer, Vue gère tout via API
    public function create()
    {
        return view('polls.create');
    }

    // Page édition — on passe le poll en JSON pour pré-remplir le formulaire
    public function edit(Request $request, $id)
    {
        $poll = $request->user()->polls()->with('options')->findOrFail($id);

        return view('polls.edit', ['poll' => $poll]);
    }

    // Page de vote — affiche le formulaire de vote
    public function vote($token)
    {
        $poll = Poll::where('secret_token', $token)->firstOrFail();

        // Vérifier que le sondage n'est pas brouillon
        if ($poll->is_draft) {
            abort(403, 'Ce sondage n\'est pas encore lancé.');
        }

        return view('polls.vote', ['token' => $token]);
    }

    // Page de résultats — affiche les résultats
    public function results($token)
    {
        $poll = Poll::where('secret_token', $token)->firstOrFail();

        // Si résultats privés, vérifier que c'est le créateur
        if (!$poll->results_public && $poll->user_id !== auth()->id()) {
            abort(403, 'Les résultats ne sont pas publics.');
        }

        return view('polls.results', ['token' => $token]);
    }
}
