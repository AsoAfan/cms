<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Where Updates Come From
    |--------------------------------------------------------------------------
    |
    | The client's copy is a git checkout of a branch built by CI — source,
    | `vendor/` and `public/build` already in it — so updating needs nothing on
    | the machine but PHP and git. No Composer, no Node.
    |
    | On a private repository this URL carries the credential:
    |
    |     UPDATE_REMOTE="https://x-access-token:github_pat_xxx@github.com/owner/repo.git"
    |
    | It lives in `.env` and NOT in `.git/config`, because every git command is
    | given the URL explicitly. Leave it empty to turn the update screen off,
    | which is what a development checkout wants.
    |
    */

    'remote' => env('UPDATE_REMOTE'),

    'branch' => env('UPDATE_BRANCH', 'release'),

    /*
    |--------------------------------------------------------------------------
    | The Checkout To Update
    |--------------------------------------------------------------------------
    |
    | Always the installation itself. Configurable only so the tests can point
    | it at a scratch repository instead of updating the machine running them.
    |
    */

    'path' => env('UPDATE_PATH', base_path()),

    'git' => env('UPDATE_GIT', 'git'),

    /*
    |--------------------------------------------------------------------------
    | Timeouts
    |--------------------------------------------------------------------------
    |
    | A check is a network round trip and must fail fast — a settings screen
    | that hangs for a minute on a bad connection reads as a broken app.
    | Applying pulls a whole release down, so it gets far longer.
    |
    */

    'check_timeout' => (int) env('UPDATE_CHECK_TIMEOUT', 30),

    'apply_timeout' => (int) env('UPDATE_APPLY_TIMEOUT', 600),

    /*
    |--------------------------------------------------------------------------
    | History Depth
    |--------------------------------------------------------------------------
    |
    | The checkout is shallow, so this is both how many releases are fetched and
    | how many "what's new" lines the screen can show.
    |
    */

    'history' => 25,

    /*
    |--------------------------------------------------------------------------
    | Noticing On Its Own
    |--------------------------------------------------------------------------
    |
    | The app checks the release branch in the background while somebody is
    | using it, and says so in the sidebar when something is waiting. Nobody has
    | to remember to look.
    |
    | Never on a page load, though: `check_every` is how long an answer is
    | trusted for, and the check runs from the browser once it has gone stale, so
    | no screen ever waits on the network. `retry_after` is the shorter wait
    | after a check that could not reach the server — a shop that was offline at
    | nine should not be told about a release at three.
    |
    */

    'check_every' => (int) env('UPDATE_CHECK_EVERY_HOURS', 6),

    'retry_after' => (int) env('UPDATE_RETRY_AFTER_MINUTES', 30),

];
