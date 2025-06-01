<?php

declare(strict_types=1);

namespace App\Application\Actions\Journal;

class JournalFunctions
{
    /**
     * Get fields expected from App via api
     * @return string
     */
    public static function getApiJournalFields()
    {
        $fields = [
            'id', 'title', 'date', 'details'
        ];
        return implode(',', $fields);
    }
}