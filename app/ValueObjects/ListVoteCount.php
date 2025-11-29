<?php

namespace App\ValueObjects;

class ListVoteCount
{
    public function __construct(public ?int $list_id, public int $vote_count) {}
}
