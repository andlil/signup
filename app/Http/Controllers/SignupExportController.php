<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Support\SignupExport;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SignupExportController extends Controller
{
    /**
     * Download the event's signups as an Excel file.
     */
    public function __invoke(Event $event): BinaryFileResponse
    {
        Gate::authorize('update', $event);

        $export = new SignupExport($event);
        $path = tempnam(sys_get_temp_dir(), 'signup-export-');

        $export->writeTo($path);

        return response()
            ->download($path, $export->fileName(), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }
}
