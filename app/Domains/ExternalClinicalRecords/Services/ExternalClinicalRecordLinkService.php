<?php

namespace App\Domains\ExternalClinicalRecords\Services;

class ExternalClinicalRecordLinkService
{
    public function url(): ?string
    {
        $url = config('services.external_clinical_record.url');

        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return parse_url($url, PHP_URL_SCHEME) === 'https' ? $url : null;
    }
}
