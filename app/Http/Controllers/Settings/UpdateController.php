<?php

namespace App\Http\Controllers\Settings;

use App\Exceptions\UpdateFailedException;
use App\Http\Controllers\Controller;
use App\Services\UpdateService;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Settings → Updates.
 *
 * The screen a non-technical owner uses to keep their copy current, in place of
 * downloading a zip and replacing files by hand.
 *
 * Whether anything is waiting is not asked here. It lives in the shared
 * `update` prop that every screen carries, refreshed in the background by
 * `UpdateNotice` — so this page opens instantly on a remembered answer, and the
 * sidebar can raise the same news anywhere in the app.
 */
class UpdateController extends Controller
{
    public function __construct(private readonly UpdateService $updates) {}

    public function index(): Response
    {
        return Inertia::render('settings/update', [
            'configured' => $this->updates->isConfigured(),
            'remote' => $this->updates->safeRemote(),
            'branch' => config('updates.branch'),
            'installed' => $this->updates->installed()?->toArray(),
        ]);
    }

    /**
     * Asks the release branch what is there.
     *
     * Called two ways, and the difference is the whole method. The background
     * check runs unattended on whatever screen the user happens to be on, so a
     * shop whose internet is down must not be told about it — repeatedly, in a
     * toast, while they are trying to write an invoice. A check somebody asked
     * for is answered either way, because they are waiting on it.
     */
    public function check(Request $request): RedirectResponse
    {
        $asked = $request->boolean('force');

        try {
            $this->updates->refresh(force: $asked);
        } catch (UpdateFailedException $failure) {
            if ($asked) {
                return $this->failed($failure);
            }

            Log::info('Background update check failed: '.$failure->getMessage(), [
                'detail' => $failure->detail,
            ]);
        } catch (Throwable $crash) {
            if ($asked) {
                throw $crash;
            }

            // Whatever went wrong, it happened on a screen the user is trying
            // to work on. An unasked-for check may never take one down.
            Log::error('Background update check crashed.', ['exception' => $crash]);
        }

        return back();
    }

    public function store(): RedirectResponse
    {
        try {
            $result = $this->updates->apply();
        } catch (UpdateFailedException $failure) {
            return $this->failed($failure);
        }

        if ($result->isUpToDate()) {
            Flash::info('You already have the newest version.');
        } else {
            $released = Carbon::parse($result->latest->committedAt)->format('j M Y');

            Flash::success("Updated. This copy is now the version from {$released}.");
        }

        return to_route('settings.update.index');
    }

    /**
     * Says the sentence, files the evidence.
     *
     * The detail is git's own output — the thing that makes a support call
     * solvable, and the last thing a shop owner wants in a toast.
     */
    private function failed(UpdateFailedException $failure): RedirectResponse
    {
        Log::error('Update failed: '.$failure->getMessage(), ['detail' => $failure->detail]);

        Flash::error($failure->getMessage());

        return to_route('settings.update.index');
    }
}
