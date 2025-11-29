<?php

namespace App\ValueObjects;

class CandidateVoteCount
{
    public function __construct(public ?int $candidate_id, public int $vote_count) {}
}
