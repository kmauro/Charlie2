<?php

namespace App\Domains\Billing\Enums;

enum FiscalDocumentStatus: string
{
    case Pending = 'pending';
    case Sending = 'sending';
    case Uncertain = 'uncertain';
    case Rejected = 'rejected';
    case Authorized = 'authorized';
}
