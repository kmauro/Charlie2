<?php

test('the application redirects to the login screen', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});
