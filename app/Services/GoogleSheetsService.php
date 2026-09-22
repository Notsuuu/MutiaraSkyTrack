<?php

namespace App\Services;

use Google\Client;
use Google\Service\Sheets;

class GoogleSheetsService
{
    protected Sheets $service;

    public function __construct()
    {
        $client = new Client();
        $client->setAuthConfig(config('services.google_sheets.credentials_path'));
        $client->addScope(Sheets::SPREADSHEETS_READONLY);
        $this->service = new Sheets($client);
    }

    /**
     * Ambil rows dari sheet. Default mulai baris 11 (data), kolom A:AA.
     */
    public function getRows(string $sheetName, string $range = 'A11:AA'): array
    {
        $spreadsheetId = config('services.google_sheets.spreadsheet_id');

        $response = $this->service->spreadsheets_values->get(
            $spreadsheetId,
            "{$sheetName}!{$range}",
            ['valueRenderOption' => 'UNFORMATTED_VALUE'] // angka polos
        );

        return $response->getValues() ?? [];
    }
}
