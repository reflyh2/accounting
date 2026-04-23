<?php

namespace App\Http\Controllers\Concerns;

trait HandlesImportErrors
{
    /**
     * Build an error bag where each row error is its own key so Inertia's
     * default error flattening (one message per key) preserves all of them.
     *
     * @param  string[]  $rowErrors
     * @return array<string, string>
     */
    protected function buildImportErrorBag(array $rowErrors): array
    {
        $bag = [
            'message' => 'Impor gagal. '.count($rowErrors).' baris bermasalah.',
        ];
        foreach (array_values($rowErrors) as $i => $message) {
            $bag["rows.{$i}"] = $message;
        }

        return $bag;
    }
}
