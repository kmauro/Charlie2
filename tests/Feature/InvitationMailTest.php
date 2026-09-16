<?php

use App\Domains\Users\Mail\UserInvitationMail;
use Carbon\CarbonImmutable;

it('renders the one-time registration link and its expiration', function () {
    $mailable = new UserInvitationMail(
        recipientName: 'Cecilia Grierson',
        invitationUrl: 'https://clinica.test/register/1?token=seguro',
        expiresAt: CarbonImmutable::parse('2026-08-06 12:30:00'),
    );

    $mailable
        ->assertHasSubject('Completá tu registro en '.config('app.name'))
        ->assertSeeInHtml('Cecilia Grierson')
        ->assertSeeInHtml('https://clinica.test/register/1?token=seguro')
        ->assertSeeInText('Completar registro')
        ->assertSeeInText('06/08/2026');
});
