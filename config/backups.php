<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Where copies of the books are kept
    |--------------------------------------------------------------------------
    |
    | Taken by hand from Settings → Backup, and automatically before every
    | update so a failed one can be undone. Below the web root: a copy of the
    | database is the whole business, and it must never be reachable by URL.
    |
    */

    'path' => storage_path('app/private/backups'),

    /*
    |--------------------------------------------------------------------------
    | How many to keep
    |--------------------------------------------------------------------------
    |
    | The oldest are deleted once there are more than this. A client's disk is
    | not an archive, and a folder of a hundred copies of the books is its own
    | kind of confusing — the point of keeping several is being able to go back
    | past a mistake noticed a few days late, not to keep everything for ever.
    |
    */

    'keep' => (int) env('BACKUP_KEEP', 10),

];
