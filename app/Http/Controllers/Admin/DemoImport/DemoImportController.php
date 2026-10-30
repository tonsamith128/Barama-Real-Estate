<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\DemoImport;

use App\Models\DemoImport;
use App\Services\DemoImport\DemoContentImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

final class DemoImportController
{
    private function requireAdmin(Request $request): void
    {
        abort_unless($request->user()?->isSystemAdministrator(), 403);
    }

    public function index(Request $request): View
    {
        $this->requireAdmin($request);
        $stage = $request->session()->get('barama_demo_stage');
        return view('admin.demo-import.index', [
            'stage' => is_array($stage) ? $stage : null,
            'import' => DemoImport::query()->latest()->first(),
            'enabled' => app()->environment(['local', 'testing', 'staging'])
                && config('demo.enable_staging_import') === true
                && (string) config('demo.publisher_public_key') !== '',
        ]);
    }

    public function preview(Request $request, DemoContentImporter $importer): RedirectResponse
    {
        $this->requireAdmin($request);
        $data = $request->validate(['archive' => ['required','file','max:65536']]);
        $file = $data['archive'];
        $directory = storage_path('app/private/demo-stage');
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            return back()->withErrors(['archive' => 'Private staging folder is unavailable.']);
        }
        $name = bin2hex(random_bytes(20)).'.zip';
        $file->move($directory, $name);
        $path = $directory.'/'.$name;
        chmod($path, 0600);

        try {
            $preview = $importer->preview($path);
        } catch (Throwable $e) {
            @unlink($path);
            return back()->withErrors(['archive' => 'Archive rejected: '. $e->getMessage()]);
        }

        $previous = $request->session()->get('barama_demo_stage');
        if (is_array($previous) && preg_match('/^[a-f0-9]{40}\.zip$/D', (string) ($previous['file'] ?? ''))) {
            @unlink($directory.'/'.$previous['file']);
        }
        $request->session()->put('barama_demo_stage', [
            'file' => $name,
            'sha256' => $preview['sha256'],
            'version' => $preview['version'],
            'counts' => $preview['counts'],
            'images' => $preview['images'],
            'policies' => $preview['policies'],
            'time' => time(),
        ]);

        return redirect()->route('admin.demo.index')
            ->with('notice', 'Read-only preview completed. Archive signature not yet verified.');
    }

    public function install(Request $request, DemoContentImporter $importer): RedirectResponse
    {
        $this->requireAdmin($request);
        $request->validate([
            'signature' => ['required','string','max:512'],
            'confirmation' => ['required','in:IMPORT VERIFIED DEMO'],
        ]);

        $stage = $request->session()->get('barama_demo_stage');
        if (!is_array($stage) || time() - (int) ($stage['time'] ?? 0) > 3600 ||
            !preg_match('/^[a-f0-9]{40}\.zip$/D', (string) ($stage['file'] ?? ''))) {
            return back()->withErrors(['archive' => 'Preview expired; upload and review again.']);
        }
        $path = storage_path('app/private/demo-stage/'.$stage['file']);
        if (!is_file($path) || is_link($path) ||
            !hash_equals((string) $stage['sha256'], (string) hash_file('sha256', $path))) {
            return back()->withErrors(['archive' => 'Staged archive is missing or has changed.']);
        }

        try {
            $importer->import($path, (string) $request->input('signature'), $request->user());
        } catch (Throwable $e) {
            report($e);
            return back()->withErrors(['archive' => 'Import stopped safely: '.$e->getMessage()]);
        }
        @unlink($path);
        $request->session()->forget('barama_demo_stage');

        return redirect()->route('admin.demo.index')
            ->with('notice', 'Verified demo records and images were imported; legal and appearance templates remain drafts.');
    }
}
