<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Licensing;

use App\Services\Licensing\PublisherLicenseClient;
use App\Services\Licensing\LicenseStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/** License activation is separate from the completed install on older QA sites. */
final class LicenseController
{
    private function requireAdmin(Request $request): void
    {
        abort_unless($request->user()?->isSystemAdministrator(), 403);
    }

    public function index(Request $request, LicenseStatusService $licenses): View
    {
        $this->requireAdmin($request);
        $domain = PublisherLicenseClient::normalizedDomain(
            (string) parse_url(config('app.url'), PHP_URL_HOST)
        );

        return view('admin.license.index', [
            'licenseStatus' => $licenses->summary(base_path(), $domain),
            'domain' => $domain,
        ]);
    }

    public function revalidate(Request $request, LicenseStatusService $licenses): RedirectResponse
    {
        $this->requireAdmin($request);
        $domain = PublisherLicenseClient::normalizedDomain(
            (string) parse_url(config('app.url'), PHP_URL_HOST)
        );

        try {
            $licenses->revalidate(base_path(), $domain);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report(new RuntimeException('License revalidation was unsuccessful.'));
            return redirect()->route('admin.license.index')->withErrors([
                'license' => 'The publisher could not confirm the current entitlement. The last recorded activation remains unchanged; check the licensing service and try again.',
            ]);
        }

        return redirect()->route('admin.license.index')
            ->with('notice', 'The publisher has confirmed the active license for this domain and installation.');
    }

    public function activate(Request $request): RedirectResponse
    {
        $this->requireAdmin($request);
        $data = $request->validate([
            'purchase_code' => ['required', 'string', 'min:8', 'max:120', 'regex:/^[A-Za-z0-9-]+$/'],
        ]);
        $root = base_path();
        if (PublisherLicenseClient::receipt($root) !== null) {
            return back()->withErrors(['purchase_code' => 'A license is already active for this installation.']);
        }
        $domain = PublisherLicenseClient::normalizedDomain((string) parse_url(config('app.url'), PHP_URL_HOST));
        try {
            $id = PublisherLicenseClient::installationId($root);
            $receipt = PublisherLicenseClient::activate((string) $data['purchase_code'], $domain, $id);
            PublisherLicenseClient::saveReceipt($root, $receipt);
        } catch (Throwable $exception) {
            report(new RuntimeException('Barama license activation did not complete.'));
            return back()->withErrors([
                'purchase_code' => 'The license could not be verified. Check the code, domain and license service.',
            ]);
        } finally {
            unset($data['purchase_code']);
        }
        return redirect()->route('admin.license.index')->with('notice', 'Your Barama license is activated for this domain.');
    }
}
