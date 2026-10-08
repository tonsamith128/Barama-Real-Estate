<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Services\InvoiceTemplateService;
use InvalidArgumentException;
use Throwable;

final class InvoiceTemplateController
{
    public function save(Request $request): Response
    {
        /* BARAMA_INVOICE_LOGO_UPLOAD_CONTROLLER_V152 */
        $uploadedLogo = null;

        try {
            $logoUrl = $this->storeLogoUpload(
                (string) $request->input('logo_url'),
                $uploadedLogo
            );
            (new InvoiceTemplateService())->save([
                'template_name' => $request->input('template_name'),
                'invoice_title' => $request->input('invoice_title'),
                'invoice_prefix' => $request->input('invoice_prefix'),
                'number_pattern' => $request->input('number_pattern'),
                'default_due_days' => $request->input('default_due_days'),
                'default_currency' => $request->input('default_currency'),
                'logo_url' => $logoUrl,
                'accent_color' => $request->input('accent_color'),
                'seller_name' => $request->input('seller_name'),
                'seller_email' => $request->input('seller_email'),
                'seller_phone' => $request->input('seller_phone'),
                'seller_address' => $request->input('seller_address'),
                'seller_tax_id' => $request->input('seller_tax_id'),
                'customer_label' => $request->input('customer_label'),
                'subtotal_label' => $request->input('subtotal_label'),
                'discount_label' => $request->input('discount_label'),
                'tax_label' => $request->input('tax_label'),
                'total_label' => $request->input('total_label'),
                'payment_instructions' => $request->input('payment_instructions'),
                'default_notes' => $request->input('default_notes'),
                'footer_text' => $request->input('footer_text'),
                'show_logo' => $request->input('show_logo'),
                'show_seller_tax_id' => $request->input('show_seller_tax_id'),
                'show_payment_instructions' => $request->input('show_payment_instructions'),
            ]);

            return Response::redirect(url('/dashboard/invoices?invoice_template=saved'));
        } catch (InvalidArgumentException $exception) {
            $this->cleanupUploadedLogo($uploadedLogo);

            return Response::redirect(
                url('/dashboard/invoices?invoice_template=error&message=' . rawurlencode($exception->getMessage()))
            );
        } catch (Throwable $exception) {
            $this->cleanupUploadedLogo($uploadedLogo);

            return Response::redirect(
                url('/dashboard/invoices?invoice_template=error&message=' . rawurlencode('Invoice template could not be saved.'))
            );
        }
    }

    public function reset(Request $request): Response
    {
        try {
            (new InvoiceTemplateService())->reset();
            return Response::redirect(url('/dashboard/invoices?invoice_template=reset'));
        } catch (Throwable $exception) {
            return Response::redirect(
                url('/dashboard/invoices?invoice_template=error&message=' . rawurlencode('Invoice template could not be reset.'))
            );
        }
    }

    private function storeLogoUpload(
        string $currentLogoUrl,
        ?string &$uploadedLogo
    ): string {
        $file = $_FILES['logo_file'] ?? null;

        if (!is_array($file)) {
            return trim($currentLogoUrl);
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            return trim($currentLogoUrl);
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(
                'Logo upload failed. Please choose the file again.'
            );
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size < 1 || $size > 5 * 1024 * 1024) {
            throw new InvalidArgumentException(
                'Logo must be a valid image no larger than 5 MB.'
            );
        }

        $temporary = (string) ($file['tmp_name'] ?? '');
        if ($temporary === '' || !is_uploaded_file($temporary)) {
            throw new InvalidArgumentException(
                'The uploaded logo could not be verified.'
            );
        }

        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = (string) finfo_file($finfo, $temporary);
                finfo_close($finfo);
            }
        }

        $extensions = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        if (!isset($extensions[$mime])) {
            throw new InvalidArgumentException(
                'Logo format is not supported. Use PNG, JPG/JPEG, WebP or GIF.'
            );
        }

        $imageInfo = @getimagesize($temporary);
        if ($imageInfo === false) {
            throw new InvalidArgumentException(
                'The uploaded file is not a valid image.'
            );
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);

        if (
            $width < 1
            || $height < 1
            || $width > 6000
            || $height > 6000
        ) {
            throw new InvalidArgumentException(
                'Logo dimensions must be between 1 and 6000 pixels.'
            );
        }

        $webroot = dirname(__DIR__, 4);
        $uploadDirectory = $webroot . '/assets/uploads/invoice-logos';

        if (
            !is_dir($uploadDirectory)
            && !mkdir($uploadDirectory, 0755, true)
            && !is_dir($uploadDirectory)
        ) {
            throw new InvalidArgumentException(
                'Invoice logo storage is not writable.'
            );
        }

        $webrootReal = realpath($webroot);
        $uploadReal = realpath($uploadDirectory);

        if (
            $webrootReal === false
            || $uploadReal === false
            || !str_starts_with(
                $uploadReal . DIRECTORY_SEPARATOR,
                $webrootReal . DIRECTORY_SEPARATOR
            )
        ) {
            throw new InvalidArgumentException(
                'Invoice logo storage path is invalid.'
            );
        }

        $filename = (
            'invoice-logo-'
            . bin2hex(random_bytes(16))
            . '.'
            . $extensions[$mime]
        );

        $destination = $uploadReal . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($temporary, $destination)) {
            throw new InvalidArgumentException(
                'The logo could not be saved to invoice storage.'
            );
        }

        @chmod($destination, 0644);
        $uploadedLogo = $destination;

        return '/assets/uploads/invoice-logos/' . $filename;
    }

    private function cleanupUploadedLogo(?string $uploadedLogo): void
    {
        if ($uploadedLogo !== null && is_file($uploadedLogo)) {
            @unlink($uploadedLogo);
        }
    }
}
