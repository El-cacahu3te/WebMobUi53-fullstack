<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Poll;
use App\Models\PollVote;
use Illuminate\Http\Request;

class ApiPollController extends Controller
{
    /**
     * Display a listing of the authenticated user's polls.
     */
    public function index(Request $request)
    {
        $polls = $request->user()->polls()->with('options')->orderBy('created_at', 'desc')->get();
        //charge les options en même temps
        return $polls;
    }

    /**
     * Display the specified poll by its secret token.
     */
    public function show(string $token)
    {
        $poll = Poll::with(['options' => function ($query) {
            $query->withCount('votes');
        }])->where('secret_token', $token)->first();

        if (!$poll) {
            return response()->json(['message' => 'Poll not found.'], 404);
        }

        return $poll;
    }

    //sauvegarder
    public function store(Request $request)
    {
        $validated = $request->validate([
            'question'              => 'required|string|max:255',
            'title'                 => 'nullable|string|max:255',
            'allow_multiple_choices'=> 'boolean',
            'allow_vote_change'     => 'boolean',
            'results_public'        => 'boolean',
            'duration'              => 'nullable|integer',
            'is_draft'              => 'boolean',
            'options'               => 'required|array|min:2',
            'options.*'             => 'required|string|max:255',
        ]);

        // Génère un token unique pour le lien de partage
        $token = bin2hex(random_bytes(16));

        $isDraft = $validated['is_draft'] ?? false;
        $poll = Poll::create([
            'user_id'               => $request->user()->id,
            'question'              => $validated['question'],
            'title'                 => $validated['title'] ?? null,
            'secret_token'          => $token,
            'allow_multiple_choices'=> $validated['allow_multiple_choices'] ?? false,
            'allow_vote_change'     => $validated['allow_vote_change'] ?? false,
            'results_public'        => $validated['results_public'] ?? false,
            'duration'              => $validated['duration'] ?? null,
            'is_draft'              => $isDraft,
            'started_at'            => $isDraft ? null : now(),
            'ends_at'               => !$isDraft && $validated['duration'] ? now()->addSeconds($validated['duration']) : null,
        ]);

        // Crée chaque option liée au sondage
        foreach ($validated['options'] as $label) {
            $poll->options()->create(['label' => $label]);
        }

        return response()->json($poll->load('options'), 201);
    }

    //update
    public function update(Request $request, int $id)
{
    $poll = Poll::where('id', $id)->where('user_id', $request->user()->id)->first();

    if (!$poll) {
        return response()->json(['message' => 'Poll not found.'], 404);
    }

    $validated = $request->validate([
        'question'               => 'sometimes|string|max:255',
        'title'                  => 'nullable|string|max:255',
        'allow_multiple_choices' => 'boolean',
        'allow_vote_change'      => 'boolean',
        'results_public'         => 'boolean',
        'duration'               => 'nullable|integer',
        'is_draft'               => 'boolean',
        'options'                => 'sometimes|array|min:2',
        'options.*'              => 'required|string|max:255',
    ]);


    $wasDraft = $poll->is_draft;
    $oldDuration = $poll->duration;
    $poll->update($validated);

    // Bloque la modif des options si sondage déjà actif
    if (isset($validated['options']) && !$wasDraft) {
        return response()->json(['message' => 'Impossible de modifier les options d\'un sondage actif.'], 422);
    }

    // Si on envoie de nouvelles options, on remplace tout
    if (isset($validated['options'])) {
        $poll->options()->delete();
        foreach ($validated['options'] as $label) {
            $poll->options()->create(['label' => $label]);
        }
    }


    $durationChanged = array_key_exists('duration', $validated) && $validated['duration'] !== $oldDuration;
    $becameActive = isset($validated['is_draft']) && $validated['is_draft'] === false && $wasDraft;

    if (!$poll->is_draft && ($becameActive || $durationChanged || !$poll->started_at)) {
        $poll->started_at = $poll->started_at ?? now();

        if ($poll->duration) {
            $poll->ends_at = now()->addSeconds($poll->duration);
        } else {
            $poll->ends_at = null;
        }

        $poll->save();
    }

    return response()->json($poll->load('options'), 200);
}
//vote
public function vote(Request $request, string $token)
{
    $poll = Poll::where('secret_token', $token)->first();

    if (!$poll || $poll->is_draft) {
        return response()->json(['message' => 'Poll not available.'], 404);
    }

    if ($poll->ends_at && now()->gt($poll->ends_at)) {
        return response()->json(['message' => 'Poll has ended.'], 403);
    }

    $validated = $request->validate([
        'option_ids'   => 'required|array|min:1',
        'option_ids.*' => 'required|integer|exists:poll_options,id',
    ]);

    // Si le sondage n'autorise pas les choix multiples, on limite à 1
    if (!$poll->allow_multiple_choices && count($validated['option_ids']) > 1) {
        return response()->json(['message' => 'Only one choice allowed.'], 422);
    }

    $user = $request->user();

    // Vérifie si l'utilisateur a déjà voté
    $alreadyVoted = PollVote::where('user_id', $user->id)
        ->whereIn('poll_option_id', $poll->options->pluck('id'))
        ->exists();

    if ($alreadyVoted && !$poll->allow_vote_change) {
        return response()->json(['message' => 'Already voted.'], 403);
    }

    // Si changement de vote autorisé, on supprime les anciens votes
    if ($alreadyVoted && $poll->allow_vote_change) {
        PollVote::where('user_id', $user->id)
            ->whereIn('poll_option_id', $poll->options->pluck('id'))
            ->delete();
    }

    // Crée un vote par option sélectionnée en utilisant la relation de sondage
    foreach ($validated['option_ids'] as $optionId) {
        $poll->votes()->create([
            'user_id'        => $user->id,
            'poll_option_id' => $optionId,
        ]);
    }

    return response()->json(['message' => 'Vote recorded.'], 201);
}

//resultats
public function results(Request $request, string $token)
{
    $poll = Poll::where('secret_token', $token)->first();

    if (!$poll) {
        return response()->json(['message' => 'Poll not found.'], 404);
    }

    $user = $request->user();
    $isOwner = $user && $user->id === $poll->user_id;

    // Si pas propriétaire et résultats non publics → accès refusé
    if (!$isOwner && !$poll->results_public) {
        return response()->json(['message' => 'Results are private.'], 403);
    }
    $results = $poll->options->map(function ($option) {
        return [
            'id'    => $option->id,
            'label' => $option->label,
            'votes' => $option->votes()->count(),
        ];
    });

    return response()->json([
        'poll'    => $poll->question,
        'ends_at' => $poll->ends_at,
        'results' => $results,
    ]);
}

    /**
     * Remove the specified poll.
     */
    public function remove(Request $request, int $id)
    {
        $poll = Poll::where('id', $id)->where('user_id', $request->user()->id)->first();

        if (!$poll) {
            return response()->json(['message' => 'Poll not found.'], 404);
        }

        $poll->delete();

        return response()->json(['message' => 'success'], 200);
    }
}
