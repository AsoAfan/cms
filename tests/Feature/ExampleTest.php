<?php

test('the application boots and answers its health check', function () {
    $this->get('/up')->assertOk();
});
