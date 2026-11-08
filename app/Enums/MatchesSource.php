<?php

namespace App\Enums;

/**
 * Where the home matches of a club row come from (RB-03 M06; D-034, D-187), stored in
 * proposal_version_clubs.matches_source (C8): counted on the loaded calendar, or the standard number
 * of the competition prorated on the period (MatchCounter, ClubMatchCount::$fromCalendar).
 */
enum MatchesSource: string
{
    case Calendar = 'calendar';
    case Standard = 'standard';
}
