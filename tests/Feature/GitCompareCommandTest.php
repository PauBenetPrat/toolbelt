<?php

it('git compares', function () {
    $this->artisan('git-compare')->assertExitCode(0);
});
