<?php

namespace App\Services\Payments;

/**
 * The booking no longer holds its rooms (its short hold lapsed, or the room
 * has been taken since), so a payment method that would hold the room
 * longer — bank transfer — cannot be chosen for it any more.
 */
class HoldExpiredException extends \RuntimeException {}
