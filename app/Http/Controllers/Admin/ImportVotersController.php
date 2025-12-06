<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\CleanVoterUpload;
use App\Jobs\SoftDeleteMissingVoters;
use App\Models\ImportBatch;
use App\Models\VoterImportTemp;
use App\Models\VoterUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ImportVotersController extends Controller
{
    public function index(): \Inertia\Response
    {
        // Return both a storage-based file list (legacy) and DB-backed uploads for richer UX.
        $files = [];

        if (Storage::exists('imports')) {
            $raw = Storage::files('imports');
            foreach ($raw as $p) {
                $files[] = [
                    'name' => basename($p),
                    'path' => $p,
                    'size' => Storage::size($p),
                    'lastModified' => Storage::lastModified($p),
                ];
            }
        }

        $uploads = VoterUpload::orderByDesc('created_at')->get()->map(function (VoterUpload $u) {
            $meta = $u->meta ?? [];

            $cleanedFile = null;
            $invalidFile = null;

            try {
                if (! empty($meta['cleaned_path']) && Storage::exists($meta['cleaned_path'])) {
                    $cleanedFile = [
                        'path' => $meta['cleaned_path'],
                        'name' => basename($meta['cleaned_path']),
                        'size' => Storage::size($meta['cleaned_path']),
                    ];
                }
            } catch (\Throwable $e) {
                // ignore storage errors
            }

            try {
                if (! empty($meta['invalid_path']) && Storage::exists($meta['invalid_path'])) {
                    $invalidFile = [
                        'path' => $meta['invalid_path'],
                        'name' => basename($meta['invalid_path']),
                        'size' => Storage::size($meta['invalid_path']),
                    ];
                }
            } catch (\Throwable $e) {
                // ignore
            }

            return [
                'id' => $u->id,
                'name' => $u->filename,
                'original_name' => $u->original_name,
                'path' => $u->path,
                'size' => $u->size,
                'status' => $u->status,
                'job_id' => $u->job_id,
                'meta' => $meta,
                'cleaned_file' => $cleanedFile,
                'invalid_file' => $invalidFile,
                'created_at' => $u->created_at?->getTimestamp(),
                'updated_at' => $u->updated_at?->getTimestamp(),
            ];
        })->toArray();

        return Inertia::render('admin/ImportVoters', [
            'files' => $files,
            'uploads' => $uploads,
        ]);
    }

    public function destroy(string $filename): RedirectResponse
    {
        // Only allow deleting files from the imports directory; sanitize input
        $safe = basename($filename);
        $path = 'imports/'.$safe;

        if (! Storage::exists($path)) {
            // Also attempt to delete related files and DB record if present
            $filenameWithoutExt = pathinfo($safe, PATHINFO_FILENAME);
            $cleanedPath = 'imports/cleaned_'.$safe;
            $invalidRowsPath = 'imports/'.$filenameWithoutExt.'.invalid_rows.csv';

            $deletedRelated = false;
            if (Storage::exists($cleanedPath)) {
                Storage::delete($cleanedPath);
                $deletedRelated = true;
            }

            if (Storage::exists($invalidRowsPath)) {
                Storage::delete($invalidRowsPath);
                $deletedRelated = true;
            }

            $db = VoterUpload::where('filename', $safe)->first();
            if ($db) {
                $db->delete();

                $message = $deletedRelated ? "Deleted DB record and related files for: {$safe}" : "Deleted DB record for: {$safe}";

                return back()->with('import_status', 'deleted')->with('import_message', $message);
            }

            if ($deletedRelated) {
                return back()->with('import_status', 'deleted')->with('import_message', "Deleted related files for: {$safe}");
            }

            return back()->with('import_status', 'error')->with('import_message', "File not found: {$safe}");
        }

        try {
            // Delete the main file
            Storage::delete($path);

            // Delete related files (cleaned_ and .invalid_rows files)
            $filenameWithoutExt = pathinfo($safe, PATHINFO_FILENAME);
            $cleanedPath = 'imports/cleaned_'.$safe;
            $invalidRowsPath = 'imports/'.$filenameWithoutExt.'.invalid_rows.csv';

            if (Storage::exists($cleanedPath)) {
                Storage::delete($cleanedPath);
            }

            if (Storage::exists($invalidRowsPath)) {
                Storage::delete($invalidRowsPath);
            }

            // Remove DB record if exists
            $db = VoterUpload::where('filename', $safe)->first();
            if ($db) {
                $db->delete();
            }

            return back()->with('import_status', 'deleted')->with('import_message', "Deleted: {$safe} and related files");
        } catch (\Throwable $e) {
            Log::error('Failed to delete import file: '.$e->getMessage(), ['file' => $path]);

            return back()->with('import_status', 'error')->with('import_message', 'Failed to delete file');
        }
    }

    public function download(string $filename): \Symfony\Component\HttpFoundation\Response
    {
        $safe = basename($filename);
        $path = 'imports/'.$safe;

        if (! Storage::exists($path)) {
            abort(404);
        }

        return Storage::download($path);
    }

    public function store(Request $request): RedirectResponse
    {
        // Log basic request info to help debug upload failures (content-length, hasFile)
        try {
            Log::info('ImportVotersController::store called', [
                'content_length' => $request->header('content-length'),
                'has_file' => $request->hasFile('file'),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_id' => $request->user()?->getKey(),
            ]);
        } catch (\Throwable $e) {
            // ignore logging errors
        }

        $request->validate([
            'file' => ['required', 'file'],
            'batch' => ['nullable', 'integer'],
        ]);

        $uploaded = $request->file('file');
        if (! $uploaded) {
            Log::error('ImportVotersController::store - no uploaded file after validation', ['has_file' => $request->hasFile('file')]);

            return back()
                ->with('import_status', 'error')
                ->with('import_message', 'No file was uploaded.')
                ->with('import_output', 'No file was uploaded.');
        }

        // Log uploaded file details
        try {
            Log::info('Import file details', [
                'original_name' => $uploaded->getClientOriginalName(),
                'client_size' => $uploaded->getSize(),
                'client_mime' => $uploaded->getClientMimeType(),
                'client_ext' => $uploaded->getClientOriginalExtension(),
            ]);
        } catch (\Throwable $e) {
            // ignore
        }
        // Ensure uploaded file is a CSV by extension
        $ext = strtolower($uploaded->getClientOriginalExtension() ?? '');
        if ($ext !== 'csv') {
            return back()
                ->with('import_status', 'error')
                ->with('import_message', 'Only CSV files are supported.')
                ->with('import_output', 'Only CSV files are supported.');
        }

        // Ensure imports directory exists and is writable
        if (! Storage::exists('imports')) {
            Storage::makeDirectory('imports');
        }

        $filename = time().'_'.preg_replace('/[^A-Za-z0-9_.-]/', '_', $uploaded->getClientOriginalName());
        try {
            $path = $uploaded->storeAs('imports', $filename);
        } catch (\Throwable $e) {
            Log::error('Exception while storing uploaded import file', ['exception' => $e->getMessage()]);

            return back()
                ->with('import_status', 'error')
                ->with('import_message', 'Failed to save uploaded file (exception).')
                ->with('import_output', 'Failed to save uploaded file (exception).');
        }

        // Verify the file was stored successfully
        if (! $path || ! Storage::exists($path)) {
            Log::error('Failed to store uploaded import file', ['attempted' => $filename, 'path' => $path]);

            return back()
                ->with('import_status', 'error')
                ->with('import_message', 'Failed to save uploaded file. Check storage permissions.')
                ->with('import_output', 'Failed to save uploaded file.');
        }

        $filePath = storage_path('app/'.$path);
        // Build options for the job (only include flags that were set)
        $options = [];
        $options['--batch'] = $request->input('batch', 1000);
        if ($request->boolean('skip_header')) {
            $options['--skip-header'] = true;
        }
        if ($request->boolean('dry_run')) {
            $options['--dry-run'] = true;
        }
        if ($request->boolean('show_unmatched')) {
            $options['--show-unmatched'] = true;
        }

        // Create a DB record for this upload so admins can track it.
        try {
            $size = Storage::size($path);
        } catch (\Throwable $e) {
            $size = 0;
        }

        $upload = VoterUpload::create([
            'filename' => $filename,
            'original_name' => $uploaded->getClientOriginalName(),
            'path' => $path,
            'size' => $size,
            'status' => 'queued',
            'user_id' => $request->user()?->getKey(),
            'meta' => null,
        ]);

        // Broadcast an event immediately so connected clients get notified that upload succeeded
        try {
            event(new \App\Events\VoterUploadCreated($upload));
            $msg = 'Upload saved to imports and notification broadcast.';

            return back()
                ->with('import_status', 'uploaded')
                ->with('import_message', $msg)
                ->with('import_output', $msg)
                ->with('uploaded_file', $path);
        } catch (\Throwable $e) {
            Log::error('Failed to broadcast VoterUploadCreated: '.$e->getMessage(), ['exception' => $e]);

            return back()
                ->with('import_status', 'uploaded')
                ->with('import_message', 'Upload saved but notification failed')
                ->with('import_output', 'Upload saved but notification failed')
                ->with('uploaded_file', $path);
        }
    }

    /**
     * Trigger cleaning/normalization for a stored upload.
     */
    public function clean(VoterUpload $upload, Request $request): RedirectResponse
    {
        // Ensure file exists
        if (! Storage::exists($upload->path)) {
            return back()->with('import_status', 'error')->with('import_message', 'File not found for cleaning');
        }

        // Mark as cleaning and dispatch job
        $upload->status = 'cleaning';
        $upload->save();

        try {
            CleanVoterUpload::dispatch($upload)->onQueue('default');

            return back()->with('import_status', 'cleaning')->with('import_message', 'Cleaning started in background');
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch CleanVoterUpload', ['exception' => $e->getMessage(), 'upload_id' => $upload->id]);

            $upload->status = 'error';
            $upload->meta = array_merge($upload->meta ?? [], ['dispatch_error' => $e->getMessage()]);
            $upload->save();

            return back()->with('import_status', 'error')->with('import_message', 'Failed to start cleaning job');
        }
    }

    /**
     * Import the cleaned upload into a temporary table for review/merge.
     */
    public function import(VoterUpload $upload, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'batch' => ['nullable', 'integer'],
            'skip_header' => ['nullable'],
            'dry_run' => ['nullable'],
            'show_unmatched' => ['nullable'],
        ]);

        $options = [
            'batch' => (int) ($data['batch'] ?? 1000),
            'skip_header' => $request->boolean('skip_header'),
            'dry_run' => $request->boolean('dry_run'),
            'show_unmatched' => $request->boolean('show_unmatched'),
        ];

        // Mark upload as importing and persist options.
        $upload->status = 'importing';
        $upload->meta = array_merge($upload->meta ?? [], ['import_options' => $options, 'import_requested_at' => now()->getTimestamp()]);
        $upload->save();

        try {
            // Create an ImportBatch record to record provenance and options
            $batch = \App\Models\ImportBatch::create([
                'user_id' => $request->user()?->getKey(),
                'district_id' => null, // Not currently used
                'options' => $options,
                'status' => 'queued',
            ]);

            // Persist the batch id to upload meta so the UI can link to it
            $upload->meta = array_merge($upload->meta ?? [], ['import_options' => array_merge($upload->meta['import_options'] ?? [], $options), 'import_batch_id' => $batch->id]);
            $upload->save();

            // Dispatch the import job which will load the cleaned CSV into the temp table and chain the merge.
            \App\Jobs\ImportVotersToTempTable::dispatch($upload, $options)->onQueue('imports');

            return back()->with('import_status', 'import_started')->with('import_message', 'Import queued (temporary table).');
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch import job', ['exception' => $e->getMessage(), 'upload_id' => $upload->id]);
            $upload->status = 'error';
            $upload->meta = array_merge($upload->meta ?? [], ['import_dispatch_error' => $e->getMessage()]);
            $upload->save();

            return back()->with('import_status', 'error')->with('import_message', 'Failed to queue import job.');
        }
    }

    /**
     * Return the cleaning/report output for an upload as plain text.
     */
    public function report(VoterUpload $upload): \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
    {
        $output = $upload->meta['clean_output'] ?? null;

        if ($output === null) {
            return back()->with('import_status', 'error')->with('import_message', 'No report available for this upload');
        }

        return response($output, 200)
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }

    /**
     * Show details for a specific import batch (temp rows, report, and meta).
     */
    public function showImportBatch(ImportBatch $batch, Request $request): \Inertia\Response|\Illuminate\Http\JsonResponse
    {
        $batch->loadMissing('upload');

        // Get unprocessed temp rows (no longer tied to specific batches)
        $rows = VoterImportTemp::where('processed', false)->orderBy('id')->limit(5000)->get()->map(function (VoterImportTemp $r) {
            return [
                'id' => $r->id,
                'sijil_number' => $r->Sijil ?? null,
                'town_id' => $r->town_id ?? null,
                'name' => $r->name ?? null,
                'processed' => (bool) ($r->processed ?? false),
            ];
        })->toArray();

        // If caller wants JSON (AJAX/modal), return a compact JSON payload
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'batch' => $batch->toArray(),
                'temp_rows' => $rows,
                'report' => $batch->report ?? [],
            ]);
        }

        return Inertia::render('admin/ImportBatchShow', [
            'batch' => $batch,
            'temp_rows' => $rows,
            'report' => $batch->report ?? [],
        ]);
    }

    /**
     * Enqueue or apply soft-delete for voters missing from the import batch.
     * Accepts `apply=1` to actually perform deletes; otherwise runs a dry-run preview.
     */
    public function softDelete(ImportBatch $batch, Request $request): RedirectResponse
    {
        $apply = $request->boolean('apply');

        // Mark batch status and persist intent
        $batch->status = $apply ? 'soft_delete_requested' : 'soft_delete_preview_requested';
        $batch->save();

        try {
            SoftDeleteMissingVoters::dispatch($batch, ['dry_run' => ! $apply])->onQueue('imports');

            return back()->with('import_status', 'soft_delete_queued')->with('import_message', $apply ? 'Soft-delete queued (will apply).' : 'Soft-delete preview queued.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to dispatch SoftDeleteMissingVoters', ['exception' => $e->getMessage(), 'batch_id' => $batch->id]);
            $batch->status = 'error';
            $report = [];
            if (! empty($batch->report)) {
                $decoded = json_decode($batch->report, true);
                if (is_array($decoded)) {
                    $report = $decoded;
                }
            }
            $report['soft_delete_dispatch_error'] = $e->getMessage();
            $encodedReport = json_encode($report, JSON_UNESCAPED_UNICODE);
            $batch->report = $encodedReport === false ? null : $encodedReport;
            $batch->save();

            return back()->with('import_status', 'error')->with('import_message', 'Failed to queue soft-delete job.');
        }
    }
}
