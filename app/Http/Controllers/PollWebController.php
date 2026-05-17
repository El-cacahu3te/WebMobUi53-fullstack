<?php
// app/Http/Controllers/PollWebController.php

namespace App\Http\Controllers;

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
}
