<?php

namespace App\Enums;

enum WithdrawalStatus: string
{
    case Created = 'CREATED';
    case Broadcasted = 'BROADCASTED';
    case Confirmed = 'CONFIRMED';
    case Failed = 'FAILED';
}
