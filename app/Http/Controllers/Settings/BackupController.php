<?php

namespace App\Http\Controllers\Settings;

use App\Exceptions\BackupFailedException;
use App\Http\Controllers\Controller;
use App\Services\BackupService;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Settings → Backup.
 *
 * The client's only copy of the books is on their own machine, and nobody is
 * going to run a command to protect it. One button takes a copy; every copy
 * taken can be downloaded, because a second file on the same disk survives a
 * mistake but not the disk.
 *
 * Restoring is deliberately not here — see `BackupService::restore()`.
 */
class BackupController extends Controller
{
    public function __construct(private readonly BackupService $backups) {}

    public function index(): Response
    {
        return Inertia::render('settings/backup', [
            'supported' => $this->backups->supported(),
            'backups' => $this->backups->all(),
            'directory' => $this->backups->directory(),
            'keep' => (int) config('backups.keep'),
        ]);
    }

    public function store(): RedirectResponse
    {
        try {
            $this->backups->take();
        } catch (BackupFailedException $failure) {
            return $this->failed($failure);
        }

        Flash::success('Backup saved. Download it and keep the file somewhere other than this computer.');

        return to_route('settings.backup.index');
    }

    /**
     * Hands the file over.
     *
     * A real download rather than an Inertia visit, so this is a plain link and
     * a GET: the browser has to be the one that saves the file.
     */
    public function download(string $name): BinaryFileResponse|RedirectResponse
    {
        try {
            $path = $this->backups->pathFor($name);
        } catch (BackupFailedException $failure) {
            return $this->failed($failure);
        }

        return response()->download($path, basename($name), [
            'Content-Type' => 'application/vnd.sqlite3',
        ]);
    }

    /**
     * Says the sentence, files the evidence — the detail is a disk path or a
     * driver's own words, which is the last thing a shop owner wants in a toast
     * and the first thing a support call needs.
     */
    private function failed(BackupFailedException $failure): RedirectResponse
    {
        Log::error('Backup failed: '.$failure->getMessage(), ['detail' => $failure->detail]);

        Flash::error($failure->getMessage());

        return to_route('settings.backup.index');
    }
}
