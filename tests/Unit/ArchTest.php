<?php

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Resources\Json\JsonResource;

/*
|--------------------------------------------------------------------------
| Architecture
|--------------------------------------------------------------------------
|
| These encode the conventions this application is built to (P0.T5), so they
| are enforced rather than merely written down. Several cover namespaces that
| are still empty — they start guarding the moment the first class lands.
|
*/

arch('no debugging helpers reach the repository')
    ->expect(['dd', 'dump', 'var_dump', 'ray', 'print_r'])
    ->not->toBeUsed();

arch('controllers do not reach for the query builder')
    ->expect('App\Http\Controllers')
    ->not->toUse(['Illuminate\Support\Facades\DB', 'Illuminate\Support\Facades\Schema']);

arch('form requests extend the framework base')
    ->expect('App\Http\Requests')
    ->toExtend(FormRequest::class);

arch('enums live in App\Enums')
    ->expect('App\Enums')
    ->toBeEnums();

arch('models live in App\Models')
    ->expect('App\Models')
    ->toExtend(Model::class);

arch('casts implement the cast contract')
    ->expect('App\Casts')
    ->toImplement(CastsAttributes::class)
    ->toBeFinal();

arch('support classes are final')
    ->expect('App\Support')
    ->toBeFinal();

arch('actions are final and expose a single entry point')
    ->expect('App\Actions')
    ->toBeFinal()
    ->toHaveMethod('handle');

arch('services are final')
    ->expect('App\Services')
    ->toBeFinal();

arch('read models are final and expose a single entry point')
    ->expect('App\Queries')
    ->toBeFinal()
    ->toHaveMethod('get');

arch('api resources extend the framework base')
    ->expect('App\Http\Resources')
    ->toExtend(JsonResource::class);
